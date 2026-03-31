<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\SyncState;

class CakeOrderController extends BaseController
{
    public function pending(): void
    {
        $this->requireAuth();

        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT co.id, co.pickup_date, co.shape, co.inscription,
                   co.additional_cost,
                   co.full_price, co.deposit_amount, co.amount_paid,
                   co.balance_due, co.payment_status, co.order_status,
                   co.customer_name, co.customer_phone,
                   co.created_at,
                   cf.name AS flavour_name, cs.name AS size_name,
                   t.transaction_ref, t.created_at AS order_date
            FROM cake_orders co
            LEFT JOIN cake_flavours cf ON cf.id = co.flavour_id
            LEFT JOIN cake_sizes cs ON cs.id = co.size_id
            LEFT JOIN transaction_items ti ON ti.id = co.transaction_item_id
            LEFT JOIN transactions t ON t.id = ti.transaction_id
            WHERE co.order_status != 'collected'
            ORDER BY
                CASE co.order_status
                    WHEN 'ready' THEN 1
                    WHEN 'in_production' THEN 2
                    WHEN 'pending' THEN 3
                END,
                co.pickup_date ASC, co.created_at ASC
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll();

        foreach ($orders as &$o) {
            $o['id'] = (int)$o['id'];
            $o['additional_cost'] = (float)$o['additional_cost'];
            $o['full_price'] = (float)$o['full_price'];
            $o['deposit_amount'] = (float)$o['deposit_amount'];
            $o['amount_paid'] = (float)$o['amount_paid'];
            $o['balance_due'] = (float)$o['balance_due'];
        }
        unset($o);

        $this->json(['success' => true, 'orders' => $orders]);
    }

    public function collectBalance(string $id): void
    {
        $this->requireAuth();

        $db = Database::getConnection();
        $cakeOrderId = (int)$id;

        if ($cakeOrderId <= 0) {
            $this->jsonError('Invalid cake order ID.');
        }

        $order = $db->prepare("SELECT * FROM cake_orders WHERE id = ? AND payment_status IN ('deposit','partial')");
        $order->execute([$cakeOrderId]);
        $order = $order->fetch();

        if (!$order) {
            $this->jsonError('Cake order not found or already fully paid.');
        }

        $balanceDue = (float)$order['balance_due'];
        if ($balanceDue <= 0) {
            $this->jsonError('No balance due on this order.');
        }

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);

        $paymentMethod = $input['payment_method'] ?? 'cash';
        $cashTendered = (float)($input['cash_tendered'] ?? 0);
        $cardAmount = (float)($input['card_amount'] ?? 0);
        $reference = trim($input['reference_number'] ?? '');

        $allowedMethods = ['cash', 'card', 'mobile'];
        if (!in_array($paymentMethod, $allowedMethods, true)) {
            $this->jsonError('Invalid payment method.');
        }

        if ($paymentMethod === 'cash' && $cashTendered < $balanceDue) {
            $this->jsonError('Cash tendered (' . number_format($cashTendered, 2) . ') is less than balance due (' . number_format($balanceDue, 2) . ').');
        }

        $changeGiven = $paymentMethod === 'cash'
            ? round(max(0, $cashTendered - $balanceDue), 2)
            : 0.0;

        if ($paymentMethod !== 'cash') {
            $cardAmount = $balanceDue;
        }

        $db->beginTransaction();

        try {
            $terminalId = Env::get('TERMINAL_ID', 'TXN001');
            $txnRef = 'BAL-' . strtoupper(bin2hex(random_bytes(5))) . '-' . time();

            $stmt = $db->prepare("
                INSERT INTO transactions
                    (transaction_ref, cashier_id, subtotal, discount, total, payment_method,
                     cash_tendered, change_given, card_amount, reference_number, terminal_id,
                     sync_status, notes)
                VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $txnRef,
                Auth::id(),
                $balanceDue,
                $balanceDue,
                $paymentMethod,
                $cashTendered,
                $changeGiven,
                $cardAmount,
                $reference ?: null,
                $terminalId,
                'Cake balance payment for order #' . $cakeOrderId,
            ]);
            $balanceTxnId = (int)$db->lastInsertId();

            $db->prepare("
                INSERT INTO transaction_items
                    (transaction_id, product_id, product_name, unit_price, quantity, line_total)
                VALUES (?, NULL, ?, ?, 1, ?)
            ")->execute([
                $balanceTxnId,
                'Cake Balance Payment',
                $balanceDue,
                $balanceDue,
            ]);

            $db->prepare("
                UPDATE cake_orders
                SET amount_paid = full_price,
                    balance_due = 0,
                    payment_status = 'paid',
                    order_status = 'collected',
                    balance_transaction_id = ?
                WHERE id = ?
            ")->execute([$balanceTxnId, $cakeOrderId]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $this->jsonError('Failed to process balance payment: ' . $e->getMessage(), 500);
        }

        SyncState::markDirty($db, ['transactions', 'cake_orders']);

        $receipt = $this->buildBalanceReceipt($cakeOrderId, $balanceTxnId, $db);

        $this->json([
            'success' => true,
            'transaction_id' => $balanceTxnId,
            'transaction_ref' => $txnRef,
            'total' => $balanceDue,
            'change' => $changeGiven,
            'receipt' => $receipt,
        ]);
    }

    public function markCollected(string $id): void
    {
        $this->requireAuth();

        $db = Database::getConnection();
        $cakeOrderId = (int)$id;

        if ($cakeOrderId <= 0) {
            $this->jsonError('Invalid cake order ID.');
        }

        $order = $db->prepare("SELECT id, payment_status, order_status FROM cake_orders WHERE id = ?");
        $order->execute([$cakeOrderId]);
        $order = $order->fetch();

        if (!$order) {
            $this->jsonError('Cake order not found.');
        }

        if ($order['payment_status'] !== 'paid') {
            $this->jsonError('Order has an outstanding balance. Use balance collection instead.');
        }

        if ($order['order_status'] === 'collected') {
            $this->jsonError('Order is already collected.');
        }

        $db->prepare("UPDATE cake_orders SET order_status = 'collected' WHERE id = ?")
           ->execute([$cakeOrderId]);

        SyncState::markDirty($db, 'cake_orders');
        $this->json(['success' => true]);
    }

    private function buildBalanceReceipt(int $cakeOrderId, int $txnId, \PDO $db): array
    {
        $txn = $db->prepare("
            SELECT t.*, u.name AS cashier_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.cashier_id
            WHERE t.id = ?
        ");
        $txn->execute([$txnId]);
        $transaction = $txn->fetch();

        $shop = $db->query("SELECT * FROM shops WHERE id = 1 LIMIT 1")->fetch();

        $co = $db->prepare("
            SELECT co.*, cf.name AS flavour_name, cs.name AS size_name
            FROM cake_orders co
            LEFT JOIN cake_flavours cf ON cf.id = co.flavour_id
            LEFT JOIN cake_sizes cs ON cs.id = co.size_id
            WHERE co.id = ?
        ");
        $co->execute([$cakeOrderId]);
        $cakeOrder = $co->fetch();

        $items = [[
            'product_name' => 'Cake Balance Payment',
            'qty' => 1,
            'unit_price' => (float)$transaction['total'],
            'line_total' => (float)$transaction['total'],
            'cake' => [
                'flavour_name' => $cakeOrder['flavour_name'] ?? null,
                'size_name' => $cakeOrder['size_name'] ?? null,
                'shape' => $cakeOrder['shape'] ?? null,
                'inscription' => $cakeOrder['inscription'] ?? null,
                'pickup_date' => $cakeOrder['pickup_date'] ?? null,
                'additional_cost' => (float)($cakeOrder['additional_cost'] ?? 0),
                'payment_status' => 'paid',
                'full_price' => (float)$cakeOrder['full_price'],
                'deposit_paid' => (float)$cakeOrder['deposit_amount'],
                'balance_due' => 0.0,
                'customer_name' => $cakeOrder['customer_name'] ?? null,
            ],
        ]];

        return [
            'transaction_id' => $txnId,
            'transaction_ref' => $transaction['transaction_ref'],
            'created_at' => $transaction['created_at'],
            'cashier_name' => $transaction['cashier_name'],
            'shop_name' => $shop['name'] ?? '',
            'shop_address' => $shop['address'] ?? '',
            'shop_phone' => $shop['phone'] ?? '',
            'shop_email' => $shop['email'] ?? '',
            'receipt_header' => $shop['receipt_header'] ?? '',
            'receipt_footer' => $shop['receipt_footer'] ?? 'Thank you!',
            'subtotal' => (float)$transaction['subtotal'],
            'discount' => (float)$transaction['discount'],
            'total' => (float)$transaction['total'],
            'payment_method' => $transaction['payment_method'],
            'cash_tendered' => (float)$transaction['cash_tendered'],
            'change_given' => (float)$transaction['change_given'],
            'card_amount' => (float)$transaction['card_amount'],
            'reference_number' => $transaction['reference_number'],
            'items' => $items,
        ];
    }
}

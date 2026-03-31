<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;

class ReceiptController extends BaseController
{
    public function show(string $id): void
    {
        $this->requireAuth();

        $txnId = (int)$id;
        if ($txnId <= 0) {
            $this->jsonError('Invalid transaction ID.');
        }

        $db  = Database::getConnection();
        $txn = $db->prepare("
            SELECT t.*, u.name AS cashier_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.cashier_id
            WHERE t.id = ?
            LIMIT 1
        ");
        $txn->execute([$txnId]);
        $transaction = $txn->fetch();

        if (!$transaction) {
            $this->jsonError('Transaction not found.', 404);
        }

        $shop = $db->query("SELECT * FROM shops WHERE id = 1 LIMIT 1")->fetch();

        $stmt = $db->prepare("
            SELECT ti.*, co.shape, co.inscription, co.pickup_date,
                   co.additional_cost,
                   co.full_price, co.deposit_amount AS co_deposit_amount,
                   co.amount_paid, co.balance_due, co.payment_status,
                   co.customer_name,
                   cf.name AS flavour_name, cs.name AS size_name
            FROM transaction_items ti
            LEFT JOIN cake_orders   co ON co.transaction_item_id = ti.id
            LEFT JOIN cake_flavours cf ON cf.id = co.flavour_id
            LEFT JOIN cake_sizes    cs ON cs.id = co.size_id
            WHERE ti.transaction_id = ?
        ");
        $stmt->execute([$txnId]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $item = [
                'product_name' => $row['product_name'],
                'qty'          => (int)$row['quantity'],
                'unit_price'   => (float)$row['unit_price'],
                'line_total'   => (float)$row['line_total'],
                'cake'         => null,
            ];
            if ($row['shape']) {
                $item['cake'] = [
                    'flavour_name'   => $row['flavour_name'],
                    'size_name'      => $row['size_name'],
                    'shape'          => $row['shape'],
                    'inscription'    => $row['inscription'],
                    'pickup_date'    => $row['pickup_date'],
                    'additional_cost'=> (float)($row['additional_cost'] ?? 0),
                    'payment_status' => $row['payment_status'] ?? 'paid',
                    'full_price'     => (float)($row['full_price'] ?? $row['line_total']),
                    'deposit_paid'   => (float)($row['co_deposit_amount'] ?? 0),
                    'balance_due'    => (float)($row['balance_due'] ?? 0),
                    'customer_name'  => $row['customer_name'] ?? null,
                ];
            }
            $items[] = $item;
        }

        $this->json([
            'transaction_id'  => $txnId,
            'transaction_ref' => $transaction['transaction_ref'],
            'created_at'      => $transaction['created_at'],
            'cashier_name'    => $transaction['cashier_name'],
            'shop_name'       => $shop['name']           ?? '',
            'shop_address'    => $shop['address']        ?? '',
            'shop_phone'      => $shop['phone']          ?? '',
            'shop_email'      => $shop['email']          ?? '',
            'receipt_header'  => $shop['receipt_header'] ?? '',
            'receipt_footer'  => $shop['receipt_footer'] ?? 'Thank you!',
            'subtotal'        => (float)$transaction['subtotal'],
            'discount'        => (float)$transaction['discount'],
            'total'           => (float)$transaction['total'],
            'payment_method'  => $transaction['payment_method'],
            'cash_tendered'   => (float)$transaction['cash_tendered'],
            'change_given'    => (float)$transaction['change_given'],
            'card_amount'     => (float)$transaction['card_amount'],
            'reference_number'=> $transaction['reference_number'],
            'items'           => $items,
        ]);
    }
}

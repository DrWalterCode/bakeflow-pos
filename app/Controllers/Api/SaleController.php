<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;

class SaleController extends BaseController
{
    public function store(): void
    {
        $this->requireAuth();

        $raw   = file_get_contents('php://input');
        $input = json_decode($raw, true);

        if (!$input || !isset($input['items']) || !is_array($input['items'])) {
            $this->jsonError('Invalid request payload.');
        }

        $items         = $input['items']          ?? [];
        $paymentMethod = $input['payment_method'] ?? 'cash';
        $cashTendered  = (float)($input['cash_tendered']  ?? 0);
        $cardAmount    = (float)($input['card_amount']    ?? 0);
        $discount      = (float)($input['discount']       ?? 0);
        $reference     = trim($input['reference_number']  ?? '');

        $allowedMethods = ['cash','card','mobile','split'];
        if (!in_array($paymentMethod, $allowedMethods, true)) {
            $this->jsonError('Invalid payment method.');
        }

        if (empty($items)) {
            $this->jsonError('No items in sale.');
        }

        if ($discount < 0) {
            $this->jsonError('Discount cannot be negative.');
        }

        if ($cashTendered < 0) {
            $this->jsonError('Cash tendered cannot be negative.');
        }

        if ($cardAmount < 0) {
            $this->jsonError('Card amount cannot be negative.');
        }

        $db = Database::getConnection();

        // Load product prices from DB (never trust client prices)
        $productIds = array_unique(array_column($items, 'product_id'));
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $db->prepare("SELECT id, name, price, is_cake FROM products WHERE id IN ({$placeholders}) AND is_active = 1");
        $stmt->execute($productIds);
        $productMap = [];
        while ($row = $stmt->fetch()) {
            $productMap[(int)$row['id']] = $row;
        }

        // Calculate totals server-side
        $subtotal = 0.0;
        $lineItems = [];

        foreach ($items as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));

            if (!isset($productMap[$pid])) {
                $this->jsonError("Product ID {$pid} not found.");
            }

            $product = $productMap[$pid];
            $unitPrice = (float)$product['price'];
            $isCake    = (bool)$product['is_cake'];

            // For cakes, price is calculated from size, shape, and any custom surcharge.
            $cakeDepositAmt = 0.0;
            $cakeAdditionalCost = 0.0;
            if ($isCake && !empty($item['cake_data'])) {
                $cakeData = $item['cake_data'];
                $sizeId   = (int)($cakeData['size_id'] ?? 0);
                $cakeAdditionalCost = round((float)($cakeData['additional_cost'] ?? 0), 2);
                if ($cakeAdditionalCost < 0) {
                    $this->jsonError('Cake additional cost cannot be negative.');
                }
                if ($sizeId > 0) {
                    $sizeStmt = $db->prepare("SELECT price_base, deposit_amount FROM cake_sizes WHERE id = ? AND is_active = 1");
                    $sizeStmt->execute([$sizeId]);
                    $size = $sizeStmt->fetch();
                    if ($size) {
                        $unitPrice = (float)$size['price_base'];
                        $cakeDepositAmt = (float)$size['deposit_amount'];
                        if (($cakeData['shape'] ?? 'round') === 'square') {
                            $unitPrice += 5.0;
                        }
                        $unitPrice += $cakeAdditionalCost;
                    }
                }
            }

            // For deposit cakes, the line_total reflects only the deposit
            $paymentChoice = ($isCake && !empty($item['cake_data'])) ? ($item['cake_data']['payment_choice'] ?? 'full') : 'full';
            $actualPayAmount = $unitPrice;
            if ($paymentChoice === 'deposit' && $cakeDepositAmt > 0) {
                $actualPayAmount = $cakeDepositAmt;
            }

            $lineTotal  = round($actualPayAmount * $qty, 2);
            $subtotal  += $lineTotal;

            $lineItems[] = [
                'product_id'     => $pid,
                'product_name'   => $product['name'],
                'unit_price'     => $unitPrice,
                'quantity'       => $qty,
                'line_total'     => $lineTotal,
                'is_cake'        => $isCake,
                'cake_data'      => $isCake ? ($item['cake_data'] ?? null) : null,
                'cake_full_price'=> $unitPrice,
                'cake_deposit'   => $cakeDepositAmt,
                'cake_additional_cost' => $cakeAdditionalCost,
                'payment_choice' => $paymentChoice,
            ];
        }

        $subtotal = round($subtotal, 2);
        $discount = round(min($discount, $subtotal), 2);
        $total    = round($subtotal - $discount, 2);

        if ($paymentMethod === 'cash' && $cashTendered < $total) {
            $this->jsonError('Cash tendered ($' . number_format($cashTendered, 2) . ') is less than total ($' . number_format($total, 2) . ').');
        }

        if ($paymentMethod === 'split') {
            if ($cardAmount < 0 || $cardAmount > $total) {
                $this->jsonError('Invalid card amount for split payment.');
            }
            $cashPortion = $total - $cardAmount;
            if ($cashTendered < $cashPortion) {
                $this->jsonError('Cash tendered does not cover the cash portion of the split payment.');
            }
        }

        $changeGiven = $paymentMethod === 'cash'  ? round(max(0, $cashTendered - $total), 2)
                     : ($paymentMethod === 'split' ? round(max(0, $cashTendered - ($total - $cardAmount)), 2) : 0.0);

        // Begin transaction
        $db->beginTransaction();

        try {
            $terminalId = Env::get('TERMINAL_ID', 'TXN001');
            $txnRef     = 'TXN-' . strtoupper(bin2hex(random_bytes(5))) . '-' . time();

            $stmt = $db->prepare("
                INSERT INTO transactions
                    (transaction_ref, cashier_id, subtotal, discount, total, payment_method,
                     cash_tendered, change_given, card_amount, reference_number, terminal_id, sync_status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $txnRef,
                Auth::id(),
                $subtotal,
                $discount,
                $total,
                $paymentMethod,
                $cashTendered,
                $changeGiven,
                $cardAmount,
                $reference ?: null,
                $terminalId,
            ]);
            $transactionId = (int)$db->lastInsertId();

            foreach ($lineItems as $line) {
                $stmt = $db->prepare("
                    INSERT INTO transaction_items
                        (transaction_id, product_id, product_name, unit_price, quantity, line_total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $transactionId,
                    $line['product_id'],
                    $line['product_name'],
                    $line['unit_price'],
                    $line['quantity'],
                    $line['line_total'],
                ]);
                $itemId = (int)$db->lastInsertId();

                // Cake order details
                if ($line['is_cake'] && !empty($line['cake_data'])) {
                    $ck = $line['cake_data'];
                    $pickupDate = $ck['pickup_date'] ?? null;
                    if ($pickupDate !== null && $pickupDate !== '' && $pickupDate < date('Y-m-d')) {
                        $db->rollBack();
                        $this->jsonError('Cake pickup date cannot be in the past.');
                    }

                    $cakeFullPrice = $line['cake_full_price'];
                    $cakeDeposit   = $line['cake_deposit'];
                    $amountPaid    = $line['line_total'];
                    $balanceDue    = round($cakeFullPrice - $amountPaid, 2);
                    $payStatus     = ($balanceDue > 0) ? 'deposit' : 'paid';

                    $stmt = $db->prepare("
                        INSERT INTO cake_orders
                            (transaction_item_id, flavour_id, size_id, shape, inscription, pickup_date, notes, additional_cost,
                             full_price, deposit_amount, amount_paid, balance_due, payment_status,
                             customer_name, customer_phone)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $itemId,
                        (int)($ck['flavour_id'] ?? 0) ?: null,
                        (int)($ck['size_id']    ?? 0) ?: null,
                        ($ck['shape'] ?? 'round') === 'square' ? 'square' : 'round',
                        $ck['inscription'] ?? null,
                        ($pickupDate !== '' ? $pickupDate : null),
                        $ck['notes']       ?? null,
                        $line['cake_additional_cost'],
                        $cakeFullPrice,
                        $cakeDeposit,
                        $amountPaid,
                        $balanceDue,
                        $payStatus,
                        trim($ck['customer_name'] ?? '') ?: null,
                        trim($ck['customer_phone'] ?? '') ?: null,
                    ]);
                }
            }

            // Deduct stock for non-cake products
            foreach ($lineItems as $line) {
                if (!$line['is_cake']) {
                    $db->prepare("
                        UPDATE products
                        SET stock_quantity = CASE
                            WHEN stock_quantity - ? < 0 THEN 0
                            ELSE stock_quantity - ?
                        END
                        WHERE id = ?
                    ")->execute([
                        $line['quantity'],
                        $line['quantity'],
                        $line['product_id'],
                    ]);
                }
            }

            $db->commit();

        } catch (\Exception $e) {
            $db->rollBack();
            $this->jsonError('Failed to save transaction: ' . $e->getMessage(), 500);
        }

        // Build inline receipt data
        $receipt = $this->buildReceipt($transactionId, $db);

        $this->json([
            'success'         => true,
            'transaction_id'  => $transactionId,
            'transaction_ref' => $txnRef,
            'total'           => $total,
            'change'          => $changeGiven,
            'receipt'         => $receipt,
        ]);
    }

    private function buildReceipt(int $txnId, \PDO $db): array
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

        $items = $db->prepare("
            SELECT ti.*, co.flavour_id, co.size_id, co.shape, co.inscription, co.pickup_date,
                   co.additional_cost,
                   co.full_price, co.deposit_amount AS co_deposit_amount,
                   co.amount_paid, co.balance_due, co.payment_status,
                   co.customer_name,
                   cf.name AS flavour_name, cs.name AS size_name
            FROM transaction_items ti
            LEFT JOIN cake_orders co ON co.transaction_item_id = ti.id
            LEFT JOIN cake_flavours cf ON cf.id = co.flavour_id
            LEFT JOIN cake_sizes    cs ON cs.id = co.size_id
            WHERE ti.transaction_id = ?
        ");
        $items->execute([$txnId]);
        $lineItems = $items->fetchAll();

        $formattedItems = [];
        foreach ($lineItems as $item) {
            $row = [
                'product_name' => $item['product_name'],
                'qty'          => (int)$item['quantity'],
                'unit_price'   => (float)$item['unit_price'],
                'line_total'   => (float)$item['line_total'],
                'cake'         => null,
            ];
            if ($item['shape']) {
                $row['cake'] = [
                    'flavour_name'   => $item['flavour_name'],
                    'size_name'      => $item['size_name'],
                    'shape'          => $item['shape'],
                    'inscription'    => $item['inscription'],
                    'pickup_date'    => $item['pickup_date'],
                    'additional_cost'=> (float)($item['additional_cost'] ?? 0),
                    'payment_status' => $item['payment_status'] ?? 'paid',
                    'full_price'     => (float)($item['full_price'] ?? $item['line_total']),
                    'deposit_paid'   => (float)($item['co_deposit_amount'] ?? 0),
                    'balance_due'    => (float)($item['balance_due'] ?? 0),
                    'customer_name'  => $item['customer_name'] ?? null,
                ];
            }
            $formattedItems[] = $row;
        }

        return [
            'transaction_id'  => $txnId,
            'transaction_ref' => $transaction['transaction_ref'],
            'created_at'      => $transaction['created_at'],
            'cashier_name'    => $transaction['cashier_name'],
            'shop_name'       => $shop['name']    ?? '',
            'shop_address'    => $shop['address'] ?? '',
            'shop_phone'      => $shop['phone']   ?? '',
            'shop_email'      => $shop['email']   ?? '',
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
            'items'           => $formattedItems,
        ];
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;

class PrinterController extends BaseController
{
    private const LINE_WIDTH = 42;
    private const PRINTER_CODE_PAGE = 16;
    private const CUT_FEED_LINES = 5;
    private const DRAWER_PIN = 0;
    private const DRAWER_PULSE_ON = 25;
    private const DRAWER_PULSE_OFF = 250;

    public function printReceipt(): void
    {
        $this->requireAuth();

        $input = json_decode((string)file_get_contents('php://input'), true);
        $transactionId = (int)($input['transaction_id'] ?? 0);

        if ($transactionId <= 0) {
            $this->jsonError('Invalid transaction ID.');
        }

        try {
            $receipt = $this->loadReceiptData($transactionId);
            $printSettings = $this->loadPrintSettings();
            $receipt['open_drawer'] = ($printSettings['receipt_open_drawer'] ?? '1') === '1';

            $printerName = trim((string)($input['printer_name'] ?? ''));
            if ($printerName === '') {
                $printerName = trim((string)($printSettings['receipt_printer_name'] ?? ''));
            }

            $result = $this->dispatchRawPrintJob(
                $this->buildEscPosReceipt($receipt),
                (string)($receipt['transaction_ref'] ?: 'BakeFlow Receipt'),
                $printerName
            );

            $this->json([
                'success'        => true,
                'printer_name'   => $result['printer_name'] ?? '',
                'bytes_written'  => (int)($result['bytes_written'] ?? 0),
                'transaction_id' => $transactionId,
            ]);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function printCakeOrderSlip(): void
    {
        $this->requireAdmin();

        $input = json_decode((string)file_get_contents('php://input'), true);
        $cakeOrderId = (int)($input['cake_order_id'] ?? 0);

        if ($cakeOrderId <= 0) {
            $this->jsonError('Invalid cake order ID.');
        }

        try {
            $printSettings = $this->loadPrintSettings();
            $slip = $this->loadCakeOrderSlipData($cakeOrderId);

            $printerName = trim((string)($input['printer_name'] ?? ''));
            if ($printerName === '') {
                $printerName = trim((string)($printSettings['receipt_printer_name'] ?? ''));
            }

            $jobName = $slip['transaction_ref'] !== ''
                ? 'Cake Slip ' . $slip['transaction_ref']
                : 'Cake Slip #' . $cakeOrderId;

            $result = $this->dispatchRawPrintJob(
                $this->buildEscPosCakeOrderSlip($slip),
                $jobName,
                $printerName
            );

            $this->json([
                'success'       => true,
                'printer_name'  => $result['printer_name'] ?? '',
                'bytes_written' => (int)($result['bytes_written'] ?? 0),
                'cake_order_id' => $cakeOrderId,
            ]);
        } catch (\RuntimeException $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    private function loadPrintSettings(): array
    {
        $db = Database::getConnection();
        $rows = $db->query("SELECT `key`, value FROM settings WHERE `key` IN ('receipt_printer_name', 'receipt_open_drawer')")->fetchAll();
        $settings = [];

        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return $settings;
    }

    private function loadReceiptData(int $transactionId): array
    {
        $db = Database::getConnection();

        $txn = $db->prepare("
            SELECT t.*, u.name AS cashier_name
            FROM transactions t
            LEFT JOIN users u ON u.id = t.cashier_id
            WHERE t.id = ?
            LIMIT 1
        ");
        $txn->execute([$transactionId]);
        $transaction = $txn->fetch();

        if (!$transaction) {
            throw new \RuntimeException('Transaction not found.');
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
            ORDER BY ti.id ASC
        ");
        $stmt->execute([$transactionId]);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $item = [
                'product_name' => (string)$row['product_name'],
                'qty'          => (int)$row['quantity'],
                'unit_price'   => (float)$row['unit_price'],
                'line_total'   => (float)$row['line_total'],
                'cake'         => null,
            ];

            if (!empty($row['shape'])) {
                $item['cake'] = [
                    'flavour_name'    => $row['flavour_name'] ?? null,
                    'size_name'       => $row['size_name'] ?? null,
                    'shape'           => (string)$row['shape'],
                    'inscription'     => $row['inscription'] ?? null,
                    'pickup_date'     => $row['pickup_date'] ?? null,
                    'additional_cost' => (float)($row['additional_cost'] ?? 0),
                    'payment_status'  => $row['payment_status'] ?? 'paid',
                    'full_price'      => (float)($row['full_price'] ?? $row['line_total']),
                    'deposit_paid'    => (float)($row['co_deposit_amount'] ?? 0),
                    'balance_due'     => (float)($row['balance_due'] ?? 0),
                    'customer_name'   => $row['customer_name'] ?? null,
                ];
            }

            $items[] = $item;
        }

        return [
            'transaction_id'   => $transactionId,
            'transaction_ref'  => (string)$transaction['transaction_ref'],
            'created_at'       => (string)$transaction['created_at'],
            'cashier_name'     => (string)($transaction['cashier_name'] ?? ''),
            'shop_name'        => (string)($shop['name'] ?? ''),
            'shop_address'     => (string)($shop['address'] ?? ''),
            'shop_phone'       => (string)($shop['phone'] ?? ''),
            'shop_email'       => (string)($shop['email'] ?? ''),
            'currency_symbol'  => (string)($shop['currency_symbol'] ?? '$'),
            'receipt_header'   => (string)($shop['receipt_header'] ?? ''),
            'receipt_footer'   => (string)($shop['receipt_footer'] ?? 'Thank you!'),
            'subtotal'         => (float)$transaction['subtotal'],
            'discount'         => (float)$transaction['discount'],
            'total'            => (float)$transaction['total'],
            'payment_method'   => (string)$transaction['payment_method'],
            'cash_tendered'    => (float)$transaction['cash_tendered'],
            'change_given'     => (float)$transaction['change_given'],
            'card_amount'      => (float)$transaction['card_amount'],
            'reference_number' => (string)($transaction['reference_number'] ?? ''),
            'items'            => $items,
        ];
    }

    private function loadCakeOrderSlipData(int $cakeOrderId): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT co.*, cf.name AS flavour_name, cs.name AS size_name,
                   t.transaction_ref, t.created_at AS order_date
            FROM cake_orders co
            LEFT JOIN cake_flavours cf ON cf.id = co.flavour_id
            LEFT JOIN cake_sizes cs ON cs.id = co.size_id
            LEFT JOIN transaction_items ti ON ti.id = co.transaction_item_id
            LEFT JOIN transactions t ON t.id = ti.transaction_id
            WHERE co.id = ?
            LIMIT 1
        ");
        $stmt->execute([$cakeOrderId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new \RuntimeException('Cake order not found.');
        }

        $shop = $db->query("SELECT * FROM shops WHERE id = 1 LIMIT 1")->fetch();

        return [
            'id'              => (int)$order['id'],
            'transaction_ref' => (string)($order['transaction_ref'] ?? ''),
            'order_date'      => (string)($order['order_date'] ?? $order['created_at']),
            'created_at'      => (string)$order['created_at'],
            'pickup_date'     => (string)($order['pickup_date'] ?? ''),
            'customer_name'   => (string)($order['customer_name'] ?? ''),
            'customer_phone'  => (string)($order['customer_phone'] ?? ''),
            'flavour_name'    => (string)($order['flavour_name'] ?? ''),
            'size_name'       => (string)($order['size_name'] ?? ''),
            'shape'           => (string)($order['shape'] ?? ''),
            'inscription'     => (string)($order['inscription'] ?? ''),
            'notes'           => (string)($order['notes'] ?? ''),
            'additional_cost' => (float)($order['additional_cost'] ?? 0),
            'full_price'      => (float)($order['full_price'] ?? 0),
            'amount_paid'     => (float)($order['amount_paid'] ?? 0),
            'balance_due'     => (float)($order['balance_due'] ?? 0),
            'payment_status'  => (string)($order['payment_status'] ?? ''),
            'order_status'    => (string)($order['order_status'] ?? ''),
            'currency_symbol' => (string)($shop['currency_symbol'] ?? '$'),
            'shop_name'       => (string)($shop['name'] ?? ''),
        ];
    }

    private function dispatchRawPrintJob(string $payload, string $jobName, string $printerName = ''): array
    {
        $scriptPath = APP_ROOT . '/scripts/windows/raw-print-receipt.ps1';
        if (!is_file($scriptPath)) {
            throw new \RuntimeException('Windows receipt printer helper script was not found.');
        }

        $powershell = $this->resolvePowerShellPath();
        $payloadBase64 = base64_encode($payload);

        $command = sprintf(
            '"%s" -NoProfile -ExecutionPolicy Bypass -File "%s" -PayloadBase64 %s -JobName %s',
            $powershell,
            $scriptPath,
            escapeshellarg($payloadBase64),
            escapeshellarg($jobName)
        );

        if ($printerName !== '') {
            $command .= ' -PrinterName ' . escapeshellarg($printerName);
        }

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, APP_ROOT);
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start the Windows printer helper.');
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $decoded = json_decode(trim((string)$stdout), true);

        if ($exitCode !== 0 || !is_array($decoded) || !($decoded['success'] ?? false)) {
            $message = trim((string)($decoded['error'] ?? $stderr ?: $stdout));
            throw new \RuntimeException($message !== '' ? $message : 'Receipt printer did not accept the job.');
        }

        return $decoded;
    }

    private function buildEscPosReceipt(array $receipt): string
    {
        $output = '';

        $output .= $this->esc(0x40);
        $output .= $this->esc(0x74, self::PRINTER_CODE_PAGE);
        $output .= $this->esc(0x61, 1);
        $output .= $this->gs(0x21, 0x11);
        $output .= $this->esc(0x45, 1);
        $output .= $this->appendCenteredBlock((string)$receipt['shop_name'], 21);
        $output .= $this->gs(0x21, 0x00);
        $output .= $this->esc(0x45, 0);

        foreach (['shop_address', 'shop_phone', 'shop_email', 'receipt_header'] as $field) {
            $value = trim((string)($receipt[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $output .= $this->appendCenteredBlock($value, self::LINE_WIDTH);
        }

        $output .= $this->esc(0x61, 0);
        $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
        $output .= $this->formatRow('Receipt #', (string)$receipt['transaction_ref']);
        $output .= $this->formatRow('Date', $this->formatTimestamp((string)$receipt['created_at']));
        $output .= $this->formatRow('Cashier', (string)$receipt['cashier_name']);
        $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));

        foreach ($receipt['items'] as $item) {
            $output .= $this->formatItemRow(
                sprintf('%dx %s', (int)$item['qty'], (string)$item['product_name']),
                $this->formatMoney((float)$item['line_total'], $receipt)
            );

            if (!empty($item['cake']) && is_array($item['cake'])) {
                $output .= $this->appendCakeLines($item['cake'], $receipt);
            }
        }

        $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
        $output .= $this->formatRow('Subtotal', $this->formatMoney((float)$receipt['subtotal'], $receipt));

        if ((float)$receipt['discount'] > 0) {
            $output .= $this->formatRow('Discount', '-' . $this->formatMoney((float)$receipt['discount'], $receipt));
        }

        $output .= $this->esc(0x45, 1);
        $output .= $this->formatRow('TOTAL', $this->formatMoney((float)$receipt['total'], $receipt));
        $output .= $this->esc(0x45, 0);
        $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));

        $paymentMethod = (string)$receipt['payment_method'];
        if ($paymentMethod === 'cash' || $paymentMethod === 'split') {
            $output .= $this->formatRow('Cash Tendered', $this->formatMoney((float)$receipt['cash_tendered'], $receipt));
        }
        if ($paymentMethod === 'card') {
            $output .= $this->formatRow('Card Payment', $this->formatMoney((float)$receipt['total'], $receipt));
        }
        if ($paymentMethod === 'mobile') {
            $output .= $this->formatRow('Mobile Payment', $this->formatMoney((float)$receipt['total'], $receipt));
        }
        if ($paymentMethod === 'split' && (float)$receipt['card_amount'] > 0) {
            $output .= $this->formatRow('Card Portion', $this->formatMoney((float)$receipt['card_amount'], $receipt));
        }
        if ((float)$receipt['change_given'] > 0 || $paymentMethod === 'cash' || $paymentMethod === 'split') {
            $output .= $this->formatRow('Change', $this->formatMoney((float)$receipt['change_given'], $receipt));
        }
        if (trim((string)$receipt['reference_number']) !== '') {
            $output .= $this->formatRow('Reference', (string)$receipt['reference_number']);
        }

        $footer = trim((string)($receipt['receipt_footer'] ?? ''));
        if ($footer !== '') {
            $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
            $output .= $this->esc(0x61, 1);
            $output .= $this->appendCenteredBlock($footer, self::LINE_WIDTH);
            $output .= $this->esc(0x61, 0);
        }

        if ($this->shouldOpenDrawer($receipt)) {
            $output .= $this->esc(0x70, self::DRAWER_PIN, self::DRAWER_PULSE_ON, self::DRAWER_PULSE_OFF);
        }

        $output .= $this->esc(0x64, self::CUT_FEED_LINES);
        $output .= $this->gs(0x56, 0x00);

        return $output;
    }

    private function buildEscPosCakeOrderSlip(array $slip): string
    {
        $output = '';

        $output .= $this->esc(0x40);
        $output .= $this->esc(0x74, self::PRINTER_CODE_PAGE);
        $output .= $this->esc(0x61, 1);
        $output .= $this->gs(0x21, 0x11);
        $output .= $this->esc(0x45, 1);
        $output .= $this->appendCenteredBlock('PRODUCTION SLIP', 21);
        $output .= $this->gs(0x21, 0x00);
        $output .= $this->esc(0x45, 0);

        if (trim((string)$slip['shop_name']) !== '') {
            $output .= $this->appendCenteredBlock((string)$slip['shop_name'], self::LINE_WIDTH);
        }

        $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
        $output .= $this->formatRow('Order #', (string)$slip['id']);

        if ((string)$slip['transaction_ref'] !== '') {
            $output .= $this->formatRow('Receipt #', (string)$slip['transaction_ref']);
        }

        $output .= $this->formatRow('Ordered', $this->formatTimestamp((string)$slip['order_date']));

        if ((string)$slip['pickup_date'] !== '') {
            $output .= $this->esc(0x45, 1);
            $output .= $this->formatRow('Pickup', $this->formatPickupDate((string)$slip['pickup_date']));
            $output .= $this->esc(0x45, 0);
        }

        $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
        $output .= $this->formatItemRow('Customer', '', 0);
        $output .= $this->formatItemRow((string)($slip['customer_name'] !== '' ? $slip['customer_name'] : 'Walk-in'), '', 2);

        if ((string)$slip['customer_phone'] !== '') {
            $output .= $this->formatItemRow((string)$slip['customer_phone'], '', 2);
        }

        $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
        $output .= $this->formatRow('Flavour', (string)($slip['flavour_name'] !== '' ? $slip['flavour_name'] : '-'));
        $output .= $this->formatRow('Size', (string)($slip['size_name'] !== '' ? $slip['size_name'] : '-'));
        $output .= $this->formatRow('Shape', ucfirst((string)($slip['shape'] !== '' ? $slip['shape'] : 'round')));

        if ((float)$slip['additional_cost'] > 0) {
            $output .= $this->formatRow('Extras', $this->formatMoney((float)$slip['additional_cost'], $slip));
        }

        if ((string)$slip['inscription'] !== '') {
            $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
            $output .= $this->formatItemRow('Inscription', '', 0);
            $output .= $this->esc(0x45, 1);
            $output .= $this->formatItemRow('"' . (string)$slip['inscription'] . '"', '', 2);
            $output .= $this->esc(0x45, 0);
        }

        if ((string)$slip['notes'] !== '') {
            $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
            $output .= $this->formatItemRow('Notes', '', 0);
            $output .= $this->formatItemRow((string)$slip['notes'], '', 2);
        }

        $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
        $output .= $this->formatRow('Total', $this->formatMoney((float)$slip['full_price'], $slip));
        $output .= $this->formatRow('Paid', $this->formatMoney((float)$slip['amount_paid'], $slip));

        if ((float)$slip['balance_due'] > 0) {
            $output .= $this->formatRow('Balance', $this->formatMoney((float)$slip['balance_due'], $slip));
        }

        $paymentStatus = (string)$slip['payment_status'];
        if ($paymentStatus !== '') {
            $output .= $this->formatRow('Payment', strtoupper(str_replace('_', ' ', $paymentStatus)));
        }

        $orderStatus = (string)$slip['order_status'];
        if ($orderStatus !== '') {
            $output .= $this->formatRow('Status', strtoupper(str_replace('_', ' ', $orderStatus)));
        }

        $output .= $this->textLine(str_repeat('-', self::LINE_WIDTH));
        $output .= $this->esc(0x61, 1);
        $output .= $this->appendCenteredBlock('Printed ' . date('Y-m-d H:i'), self::LINE_WIDTH);
        $output .= $this->esc(0x61, 0);
        $output .= $this->esc(0x64, self::CUT_FEED_LINES);
        $output .= $this->gs(0x56, 0x00);

        return $output;
    }

    private function shouldOpenDrawer(array $receipt): bool
    {
        if (array_key_exists('open_drawer', $receipt) && !$receipt['open_drawer']) {
            return false;
        }

        $paymentMethod = (string)($receipt['payment_method'] ?? '');
        return in_array($paymentMethod, ['cash', 'split'], true);
    }

    private function appendCakeLines(array $cake, array $receipt): string
    {
        $output = '';
        $summary = array_filter([
            trim((string)($cake['size_name'] ?? '')),
            trim((string)($cake['flavour_name'] ?? '')),
        ]);

        if ($summary !== []) {
            $output .= $this->formatItemRow(implode(', ', $summary), '', 2);
        }
        if (($cake['shape'] ?? '') === 'square') {
            $output .= $this->formatItemRow('Square shape', '', 2);
        }
        if ((float)($cake['additional_cost'] ?? 0) > 0) {
            $output .= $this->formatItemRow('Additional cost', $this->formatMoney((float)$cake['additional_cost'], $receipt), 2);
        }
        if (trim((string)($cake['inscription'] ?? '')) !== '') {
            $output .= $this->formatItemRow('"' . (string)$cake['inscription'] . '"', '', 2);
        }
        if (trim((string)($cake['pickup_date'] ?? '')) !== '') {
            $output .= $this->formatItemRow('Pickup: ' . $this->formatPickupDate((string)$cake['pickup_date']), '', 2);
        }
        if (trim((string)($cake['customer_name'] ?? '')) !== '') {
            $output .= $this->formatItemRow('Customer: ' . (string)$cake['customer_name'], '', 2);
        }
        if (($cake['payment_status'] ?? 'paid') === 'deposit') {
            $output .= $this->formatItemRow('Cake Price', $this->formatMoney((float)($cake['full_price'] ?? 0), $receipt), 2);
            $output .= $this->formatItemRow('Deposit Paid', $this->formatMoney((float)($cake['deposit_paid'] ?? 0), $receipt), 2);
            $output .= $this->formatItemRow('Balance Due', $this->formatMoney((float)($cake['balance_due'] ?? 0), $receipt), 2);
        } elseif (($cake['payment_status'] ?? 'paid') === 'paid') {
            $output .= $this->formatItemRow('PAID IN FULL', '', 2);
        }

        return $output;
    }

    private function appendCenteredBlock(string $text, int $width): string
    {
        $output = '';
        foreach ($this->wrapPrintableText($text, $width) as $line) {
            $output .= $this->textLine($line);
        }
        return $output;
    }

    private function formatRow(string $label, string $value): string
    {
        return $this->formatItemRow($label, $value, 0);
    }

    private function formatItemRow(string $label, string $value, int $indent = 0): string
    {
        $label = $this->sanitizeText($label);
        $value = $this->sanitizeText($value);
        $prefix = str_repeat(' ', max(0, $indent));

        if ($value === '') {
            $output = '';
            foreach ($this->wrapPrintableText($label, self::LINE_WIDTH - $indent) as $line) {
                $output .= $this->textLine($prefix . $line);
            }
            return $output;
        }

        $available = max(8, self::LINE_WIDTH - $indent - strlen($value) - 1);
        $lines = $this->wrapPrintableText($label, $available);
        $firstLine = $lines[0] ?? '';
        $output = $this->textLine($prefix . str_pad($firstLine, self::LINE_WIDTH - $indent - strlen($value), ' ', STR_PAD_RIGHT) . $value);

        foreach (array_slice($lines, 1) as $line) {
            $output .= $this->textLine($prefix . $line);
        }

        return $output;
    }

    private function wrapPrintableText(string $text, int $width): array
    {
        $text = trim($this->sanitizeText($text));
        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            while (strlen($word) > $width) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                $lines[] = substr($word, 0, $width);
                $word = substr($word, $width);
            }

            if ($line === '') {
                $line = $word;
                continue;
            }

            if (strlen($line) + 1 + strlen($word) <= $width) {
                $line .= ' ' . $word;
                continue;
            }

            $lines[] = $line;
            $line = $word;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function sanitizeText(string $text): string
    {
        return trim($this->encodeText($text, true));
    }

    private function formatMoney(float $amount, array $receipt = []): string
    {
        $currency = trim((string)($receipt['currency_symbol'] ?? '$'));
        if ($currency === '') {
            $currency = '$';
        }

        return $currency . number_format($amount, 2, '.', '');
    }

    private function formatTimestamp(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('Y-m-d H:i', $timestamp);
    }

    private function formatPickupDate(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('d M Y', $timestamp);
    }

    private function resolvePowerShellPath(): string
    {
        $systemRoot = rtrim((string)getenv('SystemRoot'), '\\/');
        $candidate = $systemRoot !== ''
            ? $systemRoot . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe'
            : '';

        return $candidate !== '' && is_file($candidate) ? $candidate : 'powershell.exe';
    }

    private function textLine(string $text): string
    {
        return $this->encodeText($text, false) . "\n";
    }

    private function esc(int ...$bytes): string
    {
        return chr(0x1B) . implode('', array_map('chr', $bytes));
    }

    private function gs(int ...$bytes): string
    {
        return chr(0x1D) . implode('', array_map('chr', $bytes));
    }

    private function encodeText(string $text, bool $collapseWhitespace): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        if ($collapseWhitespace) {
            $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        }

        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return $converted === false ? $text : $converted;
    }
}

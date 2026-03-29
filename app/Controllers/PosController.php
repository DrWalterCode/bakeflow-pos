<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class PosController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $db = Database::getConnection();

        // Load shop settings
        $shop = $db->query("SELECT * FROM shops WHERE id = 1 LIMIT 1")->fetch();

        // Cashier info
        $user = Auth::user();

        // Settings
        $stmtSettings = $db->query("SELECT `key`, value FROM settings");
        $settings = [];
        while ($row = $stmtSettings->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        $idleTimeout  = (int)($settings['idle_timeout']  ?? 600);
        $terminalId   = $settings['terminal_id']         ?? 'TXN001';
        $currencySymbol = $shop['currency_symbol']        ?? '$';

        View::renderNoLayout('pos.index', compact(
            'shop', 'user', 'settings', 'idleTimeout', 'terminalId', 'currencySymbol'
        ));
    }
}

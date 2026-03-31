<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;

class ProductsController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();

        $db = Database::getConnection();

        // Categories
        $categories = $db->query(
            "SELECT id, name, color, sort_order FROM categories WHERE is_active = 1 ORDER BY sort_order, name"
        )->fetchAll();

        // Products
        $products = $db->query(
            "SELECT id, category_id, name, price, barcode, is_cake, is_quick_item, quick_item_order, sort_order
             FROM products
             WHERE is_active = 1
             ORDER BY category_id, sort_order, name"
        )->fetchAll();

        // Cast types
        foreach ($products as &$p) {
            $p['id']          = (int)$p['id'];
            $p['category_id'] = (int)$p['category_id'];
            $p['price']            = (float)$p['price'];
            $p['is_cake']          = (bool)$p['is_cake'];
            $p['is_quick_item']    = (bool)$p['is_quick_item'];
            $p['quick_item_order'] = (int)$p['quick_item_order'];
            $p['sort_order']       = (int)$p['sort_order'];
        }
        unset($p);

        foreach ($categories as &$c) {
            $c['id']         = (int)$c['id'];
            $c['sort_order'] = (int)$c['sort_order'];
        }
        unset($c);

        $response = [
            'products'   => $products,
            'categories' => $categories,
        ];

        // Include cake options if requested
        if (isset($_GET['include']) && str_contains($_GET['include'], 'cake_options')) {
            $response['cake_flavours'] = $db->query(
                "SELECT id, name FROM cake_flavours WHERE is_active = 1 ORDER BY sort_order, name"
            )->fetchAll();

            $response['cake_sizes'] = $db->query(
                "SELECT id, name, label, price_base, deposit_amount FROM cake_sizes WHERE is_active = 1 ORDER BY sort_order"
            )->fetchAll();

            foreach ($response['cake_flavours'] as &$f) { $f['id'] = (int)$f['id']; }
            foreach ($response['cake_sizes']    as &$s) {
                $s['id']             = (int)$s['id'];
                $s['price_base']     = (float)$s['price_base'];
                $s['deposit_amount'] = (float)$s['deposit_amount'];
            }
        }

        $this->json($response);
    }
}

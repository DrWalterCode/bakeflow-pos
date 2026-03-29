<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\View;

class CakeOrderController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();

        $db = Database::getConnection();

        $statusFilter = $_GET['status'] ?? '';
        $allowedStatuses = ['pending', 'in_production', 'ready', 'collected'];

        $where = '';
        $params = [];
        if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
            $where = 'WHERE co.order_status = ?';
            $params[] = $statusFilter;
        }

        $stmt = $db->prepare("
            SELECT co.*, cf.name AS flavour_name, cs.name AS size_name,
                   t.transaction_ref, t.created_at AS order_date
            FROM cake_orders co
            LEFT JOIN cake_flavours cf ON cf.id = co.flavour_id
            LEFT JOIN cake_sizes cs ON cs.id = co.size_id
            LEFT JOIN transaction_items ti ON ti.id = co.transaction_item_id
            LEFT JOIN transactions t ON t.id = ti.transaction_id
            {$where}
            ORDER BY
                CASE co.order_status
                    WHEN 'pending' THEN 1
                    WHEN 'in_production' THEN 2
                    WHEN 'ready' THEN 3
                    WHEN 'collected' THEN 4
                END,
                co.pickup_date ASC,
                co.created_at DESC
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Summary counts
        $counts = $db->query("
            SELECT order_status, COUNT(*) AS cnt
            FROM cake_orders
            GROUP BY order_status
        ")->fetchAll();

        $summary = ['pending' => 0, 'in_production' => 0, 'ready' => 0, 'collected' => 0];
        foreach ($counts as $c) {
            $summary[$c['order_status']] = (int)$c['cnt'];
        }
        $totalOrders = array_sum($summary);

        View::render('admin.cake-orders.index', compact('orders', 'summary', 'totalOrders', 'statusFilter'));
    }

    public function startProduction(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/admin/cake-orders', 'Invalid order.', 'error');
        }

        $db = Database::getConnection();
        $order = $db->prepare("SELECT id, order_status FROM cake_orders WHERE id = ?");
        $order->execute([$id]);
        $order = $order->fetch();

        if (!$order || $order['order_status'] !== 'pending') {
            $this->redirect('/admin/cake-orders', 'Order is not in pending status.', 'error');
        }

        $db->prepare("UPDATE cake_orders SET order_status = 'in_production' WHERE id = ?")
           ->execute([$id]);

        $this->redirect('/admin/cake-orders', 'Order #' . $id . ' moved to production.');
    }

    public function markReady(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/admin/cake-orders', 'Invalid order.', 'error');
        }

        $db = Database::getConnection();
        $order = $db->prepare("SELECT id, order_status FROM cake_orders WHERE id = ?");
        $order->execute([$id]);
        $order = $order->fetch();

        if (!$order || $order['order_status'] !== 'in_production') {
            $this->redirect('/admin/cake-orders', 'Order is not in production.', 'error');
        }

        $db->prepare("UPDATE cake_orders SET order_status = 'ready' WHERE id = ?")
           ->execute([$id]);

        $this->redirect('/admin/cake-orders', 'Order #' . $id . ' marked as ready for collection.');
    }

    public function printSlip(): void
    {
        $this->requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/admin/cake-orders', 'Invalid order.', 'error');
        }

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
        ");
        $stmt->execute([$id]);
        $order = $stmt->fetch();

        if (!$order) {
            $this->redirect('/admin/cake-orders', 'Order not found.', 'error');
        }

        $shop = $db->query("SELECT * FROM shops WHERE id = 1 LIMIT 1")->fetch();

        View::renderNoLayout('admin.cake-orders.print-slip', compact('order', 'shop'));
    }
}

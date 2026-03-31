<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\PosController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\ReportsController;
use App\Controllers\Admin\ExpenseController;
use App\Controllers\Admin\ProductionController;
use App\Controllers\Api\ProductsController;
use App\Controllers\Api\SaleController;
use App\Controllers\Api\ReceiptController;
use App\Controllers\Api\PrinterController;
use App\Controllers\Api\SyncController;
use App\Controllers\Api\CakeOrderController;
use App\Controllers\Api\DayEndReportController;
use App\Controllers\Admin\CakeOrderController as AdminCakeOrderController;

// -----------------------------------------------------------------
// Public routes
// -----------------------------------------------------------------
Router::get('/login',  [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login']);
Router::get('/logout', [AuthController::class, 'logout']);

// -----------------------------------------------------------------
// POS
// -----------------------------------------------------------------
Router::get('/',    [PosController::class, 'index']);
Router::get('/pos', [PosController::class, 'index']);

// -----------------------------------------------------------------
// Admin
// -----------------------------------------------------------------
Router::get('/admin',                   [DashboardController::class, 'index']);

Router::get('/admin/products',          [ProductController::class, 'index']);
Router::post('/admin/products/store',   [ProductController::class, 'store']);
Router::post('/admin/products/update',  [ProductController::class, 'update']);
Router::post('/admin/products/delete',  [ProductController::class, 'delete']);

Router::get('/admin/categories',         [CategoryController::class, 'index']);
Router::post('/admin/categories/store',  [CategoryController::class, 'store']);
Router::post('/admin/categories/update', [CategoryController::class, 'update']);
Router::post('/admin/categories/delete', [CategoryController::class, 'delete']);

Router::get('/admin/users',          [UserController::class, 'index']);
Router::post('/admin/users/store',   [UserController::class, 'store']);
Router::post('/admin/users/update',  [UserController::class, 'update']);
Router::post('/admin/users/delete',  [UserController::class, 'delete']);

Router::get('/admin/settings',        [SettingsController::class, 'index']);
Router::post('/admin/settings/save',  [SettingsController::class, 'save']);

Router::get('/admin/expenses',          [ExpenseController::class, 'index']);
Router::post('/admin/expenses/store',  [ExpenseController::class, 'store']);
Router::post('/admin/expenses/update', [ExpenseController::class, 'update']);
Router::post('/admin/expenses/delete', [ExpenseController::class, 'delete']);

Router::get('/admin/production',          [ProductionController::class, 'index']);
Router::post('/admin/production/store',   [ProductionController::class, 'store']);
Router::post('/admin/production/delete',  [ProductionController::class, 'delete']);

Router::get('/admin/cake-orders',                     [AdminCakeOrderController::class, 'index']);
Router::post('/admin/cake-orders/start-production',   [AdminCakeOrderController::class, 'startProduction']);
Router::post('/admin/cake-orders/mark-ready',         [AdminCakeOrderController::class, 'markReady']);
Router::get('/admin/cake-orders/print-slip',          [AdminCakeOrderController::class, 'printSlip']);

Router::get('/admin/reports',          [ReportsController::class, 'index']);
Router::get('/admin/reports/daily',    [ReportsController::class, 'daily']);
Router::post('/admin/reports/daily/close',  [ReportsController::class, 'closeDay']);
Router::post('/admin/reports/daily/reopen', [ReportsController::class, 'reopenDay']);
Router::get('/admin/reports/products', [ReportsController::class, 'products']);
Router::get('/admin/reports/cashiers', [ReportsController::class, 'cashiers']);

// -----------------------------------------------------------------
// API — JSON endpoints (used by POS front-end)
// -----------------------------------------------------------------
Router::get('/api/products',         [ProductsController::class, 'index']);
Router::post('/api/sale',            [SaleController::class, 'store']);
Router::get('/api/receipt/{id}',     [ReceiptController::class, 'show']);
Router::post('/api/print/receipt',   [PrinterController::class, 'printReceipt']);
Router::post('/api/print/cake-order-slip', [PrinterController::class, 'printCakeOrderSlip']);
Router::get('/api/sync/status',      [SyncController::class, 'status']);
Router::post('/api/sync/push',       [SyncController::class, 'push']);
Router::get('/api/cake-orders/pending',              [CakeOrderController::class, 'pending']);
Router::post('/api/cake-orders/{id}/collect-balance', [CakeOrderController::class, 'collectBalance']);
Router::post('/api/cake-orders/{id}/mark-collected',  [CakeOrderController::class, 'markCollected']);
Router::post('/api/auth/heartbeat',  [AuthController::class, 'heartbeat']);
Router::get('/api/reports/day-end',  [DayEndReportController::class, 'show']);
Router::post('/api/reports/day-end/close',  [DayEndReportController::class, 'close']);
Router::post('/api/reports/day-end/reopen', [DayEndReportController::class, 'reopen']);

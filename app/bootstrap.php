<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/app/Core/Env.php';
require_once APP_ROOT . '/app/Core/Database.php';
require_once APP_ROOT . '/app/Core/RemoteDatabase.php';
require_once APP_ROOT . '/app/Core/SyncState.php';
require_once APP_ROOT . '/app/Core/SyncService.php';
require_once APP_ROOT . '/app/Core/Session.php';
require_once APP_ROOT . '/app/Core/Auth.php';
require_once APP_ROOT . '/app/Core/Router.php';
require_once APP_ROOT . '/app/Core/View.php';
require_once APP_ROOT . '/app/Lib/PdfWriter.php';
require_once APP_ROOT . '/app/Services/DayEndReportService.php';
require_once APP_ROOT . '/app/Services/DatabaseBackupService.php';
require_once APP_ROOT . '/app/Services/SystemResetService.php';
require_once APP_ROOT . '/app/Controllers/BaseController.php';
require_once APP_ROOT . '/app/Controllers/AuthController.php';
require_once APP_ROOT . '/app/Controllers/PosController.php';
require_once APP_ROOT . '/app/Controllers/Admin/DashboardController.php';
require_once APP_ROOT . '/app/Controllers/Admin/ProductController.php';
require_once APP_ROOT . '/app/Controllers/Admin/CategoryController.php';
require_once APP_ROOT . '/app/Controllers/Admin/UserController.php';
require_once APP_ROOT . '/app/Controllers/Admin/SettingsController.php';
require_once APP_ROOT . '/app/Controllers/Admin/ReportsController.php';
require_once APP_ROOT . '/app/Controllers/Admin/ExpenseController.php';
require_once APP_ROOT . '/app/Controllers/Admin/ProductionController.php';
require_once APP_ROOT . '/app/Controllers/Api/ProductsController.php';
require_once APP_ROOT . '/app/Controllers/Api/SaleController.php';
require_once APP_ROOT . '/app/Controllers/Api/ReceiptController.php';
require_once APP_ROOT . '/app/Controllers/Api/PrinterController.php';
require_once APP_ROOT . '/app/Controllers/Api/SyncController.php';
require_once APP_ROOT . '/app/Controllers/Api/CakeOrderController.php';
require_once APP_ROOT . '/app/Controllers/Api/DayEndReportController.php';
require_once APP_ROOT . '/app/Controllers/Admin/CakeOrderController.php';

use App\Core\Env;
use App\Core\Session;
use App\Core\Database;

Env::load(APP_ROOT . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'Africa/Harare'));

Session::start();

// Boot database (creates + seeds on first run)
Database::connect();

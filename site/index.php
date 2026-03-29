<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once APP_ROOT . '/app/routes.php';

use App\Core\Router;

Router::dispatch();

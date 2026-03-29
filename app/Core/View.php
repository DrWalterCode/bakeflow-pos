<?php
declare(strict_types=1);

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        extract($data);

        $csrfToken = Session::generateCsrfToken();
        $authUser  = Auth::check() ? Auth::user() : null;

        $viewPath = APP_ROOT . '/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            die("View not found: {$view}");
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout) {
            $layoutPath = APP_ROOT . '/views/layouts/' . $layout . '.php';
            if (file_exists($layoutPath)) {
                require $layoutPath;
            } else {
                echo $content;
            }
        } else {
            echo $content;
        }
    }

    public static function renderNoLayout(string $view, array $data = []): void
    {
        extract($data);

        $csrfToken = Session::generateCsrfToken();

        $viewPath = APP_ROOT . '/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            die("View not found: {$view}");
        }

        require $viewPath;
    }
}

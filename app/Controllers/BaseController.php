<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;

abstract class BaseController
{
    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        Auth::touchActivity();
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (!Auth::isAdmin()) {
            http_response_code(403);
            die('Access denied. Admin only.');
        }
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            http_response_code(403);
            die('Invalid CSRF token.');
        }
    }

    protected function verifyJsonCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            $this->jsonError('Invalid CSRF token.', 403);
        }
    }

    protected function redirect(string $url, string $message = '', string $type = 'success'): void
    {
        if ($message) {
            Session::flash('message', $message);
            Session::flash('message_type', $type);
        }
        header('Location: ' . $url);
        exit;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function jsonError(string $message, int $status = 400): void
    {
        $this->json(['success' => false, 'error' => $message], $status);
    }
}

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Core\SyncState;
use App\Core\View;

class AuthController extends BaseController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $role = Auth::user()['role'] ?? null;
            if ($role !== null) {
                $this->redirectAfterLogin($role);
            }
        }
        $error = Session::getFlash('error');
        View::renderNoLayout('auth.login', ['error' => $error]);
    }

    public function login(): void
    {
        $this->verifyCsrf();

        $username = trim($_POST['username'] ?? '');
        $pin = $_POST['pin'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($username === '') {
            Session::flash('error', 'Please enter your username.');
            header('Location: /login');
            exit;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            Session::flash('error', 'Invalid username or credentials.');
            header('Location: /login');
            exit;
        }

        if ($user['pin_locked_until'] !== null) {
            $lockedUntil = strtotime($user['pin_locked_until']);
            if (time() < $lockedUntil) {
                $mins = ceil(($lockedUntil - time()) / 60);
                Session::flash('error', "Account locked. Try again in {$mins} minute(s).");
                header('Location: /login');
                exit;
            }
        }

        $authenticated = false;

        if ($user['role'] === 'admin' && $password !== '') {
            $authenticated = ($user['password_hash'] !== null &&
                              password_verify($password, $user['password_hash']));
        } elseif ($pin !== '') {
            $authenticated = ($user['pin_hash'] !== null &&
                              password_verify($pin, $user['pin_hash']));
        }

        if (!$authenticated) {
            $fails = (int)$user['pin_fail_count'] + 1;
            if ($fails >= 5) {
                $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $db->prepare("UPDATE users SET pin_fail_count = ?, pin_locked_until = ? WHERE id = ?")
                   ->execute([$fails, $lockUntil, $user['id']]);
                SyncState::markDirty($db, 'users');
                Session::flash('error', 'Too many failed attempts. Account locked for 15 minutes.');
            } else {
                $db->prepare("UPDATE users SET pin_fail_count = ? WHERE id = ?")
                   ->execute([$fails, $user['id']]);
                SyncState::markDirty($db, 'users');
                $remaining = 5 - $fails;
                Session::flash('error', "Invalid credentials. {$remaining} attempt(s) remaining.");
            }
            header('Location: /login');
            exit;
        }

        $db->prepare("UPDATE users SET pin_fail_count = 0, pin_locked_until = NULL, last_login_at = NOW() WHERE id = ?")
           ->execute([$user['id']]);
        SyncState::markDirty($db, 'users');

        Auth::loginUser($user);

        $this->redirectAfterLogin($user['role']);
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: /login');
        exit;
    }

    public function heartbeat(): void
    {
        if (!Auth::check()) {
            $this->json(['authenticated' => false], 401);
        }
        Auth::touchActivity();
        $this->json(['authenticated' => true]);
    }

    private function redirectAfterLogin(?string $role): void
    {
        if ($role === 'admin') {
            header('Location: /admin');
        } else {
            header('Location: /pos');
        }
        exit;
    }
}

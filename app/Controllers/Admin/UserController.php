<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\SyncState;
use App\Core\View;

class UserController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $db = Database::getConnection();
        $users = $db->query("SELECT id, name, username, role, is_active, last_login_at, created_at FROM users ORDER BY role, name")->fetchAll();
        View::render('admin.users.index', compact('users'));
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = ($_POST['role'] ?? 'cashier') === 'admin' ? 'admin' : 'cashier';
        $password = $_POST['password'] ?? '';
        $pin = $_POST['pin'] ?? '';

        if ($name === '' || $username === '') {
            $this->redirect('/admin/users', 'Name and username are required.', 'error');
        }

        if ($pin !== '' && !preg_match('/^\d{4,6}$/', $pin)) {
            $this->redirect('/admin/users', 'PIN must be 4 to 6 digits.', 'error');
        }

        if ($role === 'admin' && $password === '') {
            $this->redirect('/admin/users', 'Admin users must have a password.', 'error');
        }

        if ($role === 'admin' && strlen($password) < 6) {
            $this->redirect('/admin/users', 'Password must be at least 6 characters.', 'error');
        }

        if ($role === 'cashier' && $pin === '') {
            $this->redirect('/admin/users', 'Cashier users must have a PIN.', 'error');
        }

        $db = Database::getConnection();

        $exists = $db->prepare("SELECT id FROM users WHERE username = ?");
        $exists->execute([$username]);
        if ($exists->fetch()) {
            $this->redirect('/admin/users', 'Username already exists.', 'error');
        }

        $passwordHash = ($password !== '') ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]) : null;
        $pinHash = ($pin !== '') ? password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]) : null;

        $db->prepare("INSERT INTO users (name, username, password_hash, pin_hash, role) VALUES (?, ?, ?, ?, ?)")
           ->execute([$name, $username, $passwordHash, $pinHash, $role]);

        SyncState::markDirty($db, 'users');
        $this->redirect('/admin/users', 'User created.');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $role = ($_POST['role'] ?? 'cashier') === 'admin' ? 'admin' : 'cashier';
        $active = isset($_POST['is_active']) ? 1 : 0;
        $pin = $_POST['pin'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($name === '') {
            $this->redirect('/admin/users', 'Name is required.', 'error');
        }

        if ($pin !== '' && !preg_match('/^\d{4,6}$/', $pin)) {
            $this->redirect('/admin/users', 'PIN must be 4 to 6 digits.', 'error');
        }

        if ($password !== '' && strlen($password) < 6) {
            $this->redirect('/admin/users', 'Password must be at least 6 characters.', 'error');
        }

        $db = Database::getConnection();
        $db->prepare("UPDATE users SET name = ?, role = ?, is_active = ? WHERE id = ?")
           ->execute([$name, $role, $active, $id]);

        if ($pin !== '') {
            $db->prepare("UPDATE users SET pin_hash = ?, pin_fail_count = 0, pin_locked_until = NULL WHERE id = ?")
               ->execute([password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
        }

        if ($password !== '') {
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
               ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
        }

        SyncState::markDirty($db, 'users');
        $this->redirect('/admin/users', 'User updated.');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $db = Database::getConnection();
        $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")
           ->execute([(int)$_POST['id']]);
        SyncState::markDirty($db, 'users');
        $this->redirect('/admin/users', 'User deactivated.');
    }
}

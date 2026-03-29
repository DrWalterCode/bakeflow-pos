<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function loginUser(array $user): void
    {
        Session::set('user_id',   (int)$user['id']);
        Session::set('user_name', $user['name']);
        Session::set('username',  $user['username']);
        Session::set('user_role', $user['role']);
        Session::set('shop_id',   1);
        Session::set('last_active', time());
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id !== null ? (int)$id : null;
    }

    public static function user(): array
    {
        return [
            'id'        => Session::get('user_id'),
            'name'      => Session::get('user_name'),
            'username'  => Session::get('username'),
            'role'      => Session::get('user_role'),
            'shop_id'   => Session::get('shop_id'),
        ];
    }

    public static function isAdmin(): bool
    {
        return Session::get('user_role') === 'admin';
    }

    public static function isCashier(): bool
    {
        return Session::get('user_role') === 'cashier';
    }

    public static function touchActivity(): void
    {
        Session::set('last_active', time());
    }

    public static function isTimedOut(int $idleSeconds = 600): bool
    {
        $last = Session::get('last_active', 0);
        return (time() - $last) > $idleSeconds;
    }
}

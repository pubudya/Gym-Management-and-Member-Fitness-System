<?php
declare(strict_types=1);

final class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $user = Database::fetch(
            'SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1',
            [$username, $username]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        if ($user['status'] !== 'ACTIVE') {
            flash('error', 'This account is inactive or suspended.');
            return false;
        }
        if (!in_array($user['role'], ['ADMIN', 'MEMBER', 'COACH', 'PLATFORM'], true)) {
            return false;
        }

        Database::update('users', ['last_login' => date('Y-m-d H:i:s')], 'user_id = ?', [$user['user_id']]);

        $_SESSION['user'] = [
            'user_id' => (int) $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
        ];

        if ($user['role'] === 'ADMIN') {
            $_SESSION['admin'] = Database::fetch('SELECT * FROM admins WHERE user_id = ?', [$user['user_id']]);
        } elseif ($user['role'] === 'COACH') {
            $_SESSION['coach'] = Database::fetch('SELECT * FROM trainers WHERE user_id = ?', [$user['user_id']]);
        } else {
            $_SESSION['member'] = Database::fetch('SELECT * FROM members WHERE user_id = ?', [$user['user_id']]);
        }

        audit('LOGIN', 'auth', 'User logged in', $user['user_id']);
        return true;
    }

    public static function logout(): void
    {
        if (current_user()) {
            audit('LOGOUT', 'auth', 'User logged out', current_user()['user_id']);
        }
        $_SESSION = [];
        session_regenerate_id(true);
    }
}

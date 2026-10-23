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

    public static function registerAdmin(
        string $fullName,
        string $username,
        string $email,
        string $phone,
        string $password,
        string $confirmPassword
    ): int {
        $fullName = trim($fullName);
        $username = trim($username);
        $email = trim($email);
        $phone = preg_replace('/\s+/', '', trim($phone)) ?? '';

        if ($fullName === '' || $username === '' || $email === '' || $phone === '' || $password === '') {
            throw new RuntimeException('Fill in every field to create the admin account.');
        }
        if (!preg_match('/^[A-Za-z0-9._]{3,50}$/', $username)) {
            throw new RuntimeException('Username must be 3–50 letters, numbers, dots or underscores.');
        }
        if (!valid_email($email)) {
            throw new RuntimeException('Enter a valid email address.');
        }
        if (!valid_phone($phone)) {
            throw new RuntimeException('Enter a valid Sri Lankan mobile number.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }
        if ($password !== $confirmPassword) {
            throw new RuntimeException('Password confirmation does not match.');
        }

        $dup = Database::fetch(
            'SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$username, $email]
        );
        if ($dup) {
            throw new RuntimeException('That username or email is already used.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $userId = Database::insert('users', [
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'ADMIN',
                'status' => 'ACTIVE',
            ]);
            Database::insert('admins', [
                'user_id' => $userId,
                'full_name' => $fullName,
                'phone' => $phone,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException('Could not save the admin account. Try a different username or email.');
        }

        audit('CREATE', 'auth', 'Admin account created: ' . $username, $userId);
        return $userId;
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

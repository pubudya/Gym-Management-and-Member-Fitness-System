<?php
declare(strict_types=1);

/**
 * Creates tables and the first admin user when the Railway MySQL database is empty.
 */
require dirname(__DIR__) . '/config/config.php';

$adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
$adminPass = getenv('ADMIN_PASSWORD') ?: 'Admin@123';
$adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@voltgym.local';

$dsnHost = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET;
$attempts = 0;
$pdo = null;
$lastError = '';

while ($attempts < 30) {
    $attempts++;
    try {
        $pdo = new PDO($dsnHost . ';dbname=' . DB_NAME, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        break;
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        try {
            $server = new PDO($dsnHost, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $db = str_replace('`', '', DB_NAME);
            $server->exec('CREATE DATABASE IF NOT EXISTS `' . $db . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo = new PDO($dsnHost . ';dbname=' . DB_NAME, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            break;
        } catch (Throwable $e2) {
            $lastError = $e2->getMessage();
            echo "MySQL not ready yet ({$attempts}/30): {$lastError}\n";
            sleep(2);
        }
    }
}

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Could not connect to MySQL: {$lastError}\n");
    exit(1);
}

$hasUsers = false;
try {
    $hasUsers = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
} catch (Throwable $e) {
    $hasUsers = false;
}

if (!$hasUsers) {
    echo "Installing gym database schema...\n";
    $sql = (string) file_get_contents(dirname(__DIR__) . '/database/gym_management.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        if (preg_match('/^(CREATE DATABASE|USE)\b/i', $stmt)) {
            continue;
        }
        $pdo->exec($stmt);
    }
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count === 0) {
    echo "Creating gym owner account...\n";
    $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, status) VALUES (?,?,?,?,?)');
    $ins->execute([$adminUser, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), 'ADMIN', 'ACTIVE']);
    $adminId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO admins (user_id, full_name, phone) VALUES (?,?,?)')
        ->execute([$adminId, 'Gym Administrator', '0770000001']);
}

echo "Database is ready.\n";

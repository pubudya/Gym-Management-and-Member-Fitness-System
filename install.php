<?php
declare(strict_types=1);

$app = require __DIR__ . '/config/config.php';
$installed = false;
$error = '';
$ok = '';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $exists = $pdo->query("SHOW DATABASES LIKE '" . str_replace("'", '', DB_NAME) . "'")->fetch();
    if ($exists) {
        $pdo->exec('USE `' . DB_NAME . '`');
        $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if ($tables) {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $installed = $count > 0;
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    try {
        $sql = file_get_contents(__DIR__ . '/database/gym_management.sql');
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $sql = preg_replace('/^\s*--.*$/m', '', (string) $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            $pdo->exec($stmt);
        }

        $pdo->exec('USE `' . DB_NAME . '`');
        $adminHash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $insUser = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, status) VALUES (?,?,?,?,?)');
        $insUser->execute(['admin', 'admin@voltgym.local', $adminHash, 'ADMIN', 'ACTIVE']);
        $adminUserId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO admins (user_id, full_name, phone) VALUES (?,?,?)')
            ->execute([$adminUserId, 'Gym Administrator', '0770000001']);

        $installed = true;
        $ok = 'Database created. Sign in as admin and change the default password in Settings after login.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Volt Gym</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-body">
<section class="login-art">
    <div>
        <span class="badge ok">PHP · MySQL</span>
        <h1>Install the gym system</h1>
        <p>Creates the MySQL schema and one gym owner account. No sample members, coaches or fake gym data are added.</p>
    </div>
</section>
<section class="login-panel">
    <div class="login-card card">
        <h2>Setup</h2>
        <?php if ($error): ?><div class="alert bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($ok): ?><div class="alert ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
        <?php if ($installed): ?>
            <p>The system is ready.</p>
            <p><a class="btn full" href="index.php">Go to login</a></p>
        <?php else: ?>
            <p class="muted">Uses WAMP MySQL user <b>root</b> and database <b>gym_management</b>. Edit <code>config/config.local.php</code> if your password is not empty.</p>
            <form method="post">
                <button class="btn full" type="submit">Create database</button>
            </form>
        <?php endif; ?>
    </div>
</section>
</body>
</html>

<?php
declare(strict_types=1);

require __DIR__ . '/includes/init.php';

$installed = Installer::schemaReady();
$error = '';
$ok = '';

if (is_post() && !$installed) {
    try {
        Installer::ensureSchema();
        $installed = true;
        $ok = 'Database tables are ready. Create an admin account next.';
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
    <title>Install <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="login-body">
<section class="login-art">
    <div>
        <span class="badge ok">PHP · MySQL</span>
        <h1>Install the gym system</h1>
        <p>Creates the MySQL schema. Then create an admin account. No sample members or coaches are added.</p>
    </div>
</section>
<section class="login-panel">
    <div class="login-card card">
        <h2>Setup</h2>
        <?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>
        <?php if ($ok): ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>
        <?php if ($installed): ?>
            <p>The database is ready.</p>
            <p><a class="btn full" href="<?= e(url('register.php')) ?>">Create admin account</a></p>
            <p class="auth-switch"><a href="<?= e(url('login.php')) ?>">Sign in</a> · <a href="<?= e(url('index.php')) ?>">Home</a></p>
        <?php else: ?>
            <p class="muted">Uses MySQL database <b><?= e(DB_NAME) ?></b>. Edit <code>config/config.local.php</code> if your password is not empty.</p>
            <form method="post">
                <?= csrf_field() ?>
                <button class="btn full" type="submit">Create database</button>
            </form>
        <?php endif; ?>
    </div>
</section>
</body>
</html>

<?php
declare(strict_types=1);
require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect(home_path());
}

$needsInstall = !Installer::schemaReady();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&family=Syne:wght@800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="home-body">
<header class="public-nav">
    <a class="brand" href="<?= e(url('index.php')) ?>">
        <span class="brand-mark">V</span>
        <span><strong><?= e(APP_NAME) ?></strong><small>Gym management</small></span>
    </a>
    <div class="links">
        <a class="btn ghost" href="<?= e(url('login.php')) ?>">Sign in</a>
        <a class="btn" href="<?= e(url('register.php')) ?>">Create admin account</a>
    </div>
</header>
<main class="home-hero">
    <?php if ($needsInstall): ?>
        <div class="alert">The database is not ready yet. Creating an admin account will set up MySQL tables automatically. You can also <a href="<?= e(url('install.php')) ?>"><b>run the installer</b></a>.</div>
    <?php endif; ?>
    <span class="badge ok">Admin accounts only</span>
    <h1>Run your gym from one simple home.</h1>
    <p class="muted">Create an admin account to own the gym system. Admins then add coaches and members from the console. Public signup is not open to members or coaches.</p>
    <div class="home-actions">
        <a class="btn" href="<?= e(url('register.php')) ?>">Create admin account</a>
        <a class="btn ghost" href="<?= e(url('login.php')) ?>">Sign in</a>
    </div>
    <div class="home-points">
        <div class="card">
            <h3>Admin</h3>
            <p class="muted">Create the gym, register coaches and members, and manage memberships, payments and attendance.</p>
        </div>
        <div class="card">
            <h3>Coach</h3>
            <p class="muted">Coaches are added by an admin. They train only the members assigned to them.</p>
        </div>
        <div class="card">
            <h3>Member</h3>
            <p class="muted">Members are registered by an admin. They log in to follow workouts, meals and gym visits.</p>
        </div>
    </div>
</main>
</body>
</html>

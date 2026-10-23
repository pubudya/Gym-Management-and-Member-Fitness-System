<?php
declare(strict_types=1);
require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect(home_path());
}

$error = flash('error');
$success = flash('success');
if (is_post()) {
    $username = trim((string) post('username'));
    $password = (string) post('password');
    if ($username === '' || $password === '') {
        $error = 'Enter username and password.';
    } elseif (!Auth::attempt($username, $password)) {
        $error = $error ?: 'Invalid username or password.';
        audit('LOGIN_FAILED', 'auth', $username);
    } else {
        redirect(home_path());
    }
}

$needsInstall = !Installer::schemaReady();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&family=Syne:wght@800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="login-body">
<section class="login-art">
    <div>
        <span class="badge ok">Admin · Coach · Member</span>
        <h1>Train harder.<br>Run the gym smarter.</h1>
        <p>Admin runs the business. Coaches train only their assigned members. Members follow workouts, meals and gym visits.</p>
    </div>
    <div class="muted">QR attendance · SMS alerts · workout photos · meal plans · progress</div>
</section>
<section class="login-panel">
    <form class="login-card card" method="post" data-validate>
        <h2>Sign in</h2>
        <p class="muted">Use the account your gym created for you.</p>
        <?php if ($needsInstall): ?>
            <div class="alert">Database is not installed yet. <a href="<?= e(url('register.php')) ?>"><b>Create an admin account</b></a> to set it up.</div>
        <?php endif; ?>
        <?php if ($success): ?><div class="alert ok"><?= e((string) $success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>
        <?= csrf_field() ?>
        <label>Username or email</label>
        <input name="username" required autofocus value="<?= e((string) post('username')) ?>">
        <label>Password</label>
        <input type="password" name="password" required>
        <button class="btn full" type="submit">Enter gym system</button>
        <p class="auth-switch">Gym owner? <a href="<?= e(url('register.php')) ?>">Create admin account</a> · <a href="<?= e(url('index.php')) ?>">Home</a></p>
    </form>
</section>
</body>
</html>

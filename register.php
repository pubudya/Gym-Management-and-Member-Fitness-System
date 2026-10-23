<?php
declare(strict_types=1);
require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect(home_path());
}

$error = flash('error');
$values = [
    'full_name' => '',
    'username' => '',
    'email' => '',
    'phone' => '',
];

if (is_post()) {
    $values = [
        'full_name' => trim((string) post('full_name')),
        'username' => trim((string) post('username')),
        'email' => trim((string) post('email')),
        'phone' => trim((string) post('phone')),
    ];
    $password = (string) post('password');
    $confirm = (string) post('password_confirm');
    try {
        Installer::ensureSchema();
        Auth::registerAdmin(
            $values['full_name'],
            $values['username'],
            $values['email'],
            $values['phone'],
            $password,
            $confirm
        );
        if (!Auth::attempt($values['username'], $password)) {
            flash('success', 'Admin account created. Sign in to continue.');
            redirect('login.php');
        }
        flash('success', 'Admin account created and saved in the database.');
        redirect(home_path());
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
    <title>Create admin account · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&family=Syne:wght@800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="login-body">
<section class="login-art">
    <div>
        <span class="badge ok">Admin only</span>
        <h1>Create the gym owner account.</h1>
        <p>This form creates an Admin user in MySQL. Coaches and members cannot sign up here — an admin adds them later.</p>
    </div>
    <div class="muted">Tables are created automatically if the database is empty.</div>
</section>
<section class="login-panel">
    <form class="login-card card" method="post" data-validate>
        <h2>Create admin account</h2>
        <p class="muted">Saved as role <b>ADMIN</b> in the gym database.</p>
        <?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>
        <?= csrf_field() ?>
        <label>Full name</label>
        <input name="full_name" required autofocus value="<?= e($values['full_name']) ?>">
        <label>Username</label>
        <input name="username" required value="<?= e($values['username']) ?>" autocomplete="username">
        <label>Email</label>
        <input type="email" name="email" required value="<?= e($values['email']) ?>">
        <label>Phone</label>
        <input name="phone" required value="<?= e($values['phone']) ?>" placeholder="0771234567">
        <label>Password</label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
        <label>Confirm password</label>
        <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
        <button class="btn full" type="submit">Create admin account</button>
        <p class="auth-switch">Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a> · <a href="<?= e(url('index.php')) ?>">Home</a></p>
    </form>
</section>
</body>
</html>

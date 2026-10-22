<?php
declare(strict_types=1);

$role = current_user()['role'] ?? 'MEMBER';
$isCoach = $role === 'COACH';
$pageTitle = 'Gym subscription expired';
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e(setting('gym_name', APP_NAME)) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&family=Syne:wght@800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="login-body">
<section class="login-art">
    <div>
        <span class="badge bad">Subscription expired</span>
        <h1>Access is paused.<br>Gym data is safe.</h1>
        <p>This gym's software subscription is inactive. Historical members, attendance, payments and training records have not been deleted.</p>
    </div>
</section>
<section class="login-panel">
    <div class="login-card card">
        <h2>Gym Subscription Expired</h2>
        <?php if ($isCoach): ?>
            <p>Your gym's software subscription has expired.</p>
            <p>Please ask the gym owner to renew the subscription.</p>
        <?php else: ?>
            <p>Your gym's subscription is currently inactive.</p>
            <p>Please contact your gym owner for more information.</p>
        <?php endif; ?>
        <p class="muted">Coaches and members do not pay for this software separately. Only the gym owner can renew.</p>
        <a class="btn full" href="<?= e(url('logout.php')) ?>">Log out</a>
    </div>
</section>
</body>
</html>

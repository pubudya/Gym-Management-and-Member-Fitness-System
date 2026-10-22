<?php
$pageTitle = $pageTitle ?? 'Volt Gym';
$role = current_user()['role'] ?? '';
$meName = match ($role) {
    'ADMIN' => current_admin()['full_name'] ?? current_user()['username'],
    'COACH' => current_coach()['full_name'] ?? current_user()['username'],
    'PLATFORM' => 'Payment reviewer',
    default => current_member()['full_name'] ?? current_user()['username'],
};
$meImage = match ($role) {
    'ADMIN' => current_admin()['profile_image'] ?? '',
    'COACH' => current_coach()['profile_image'] ?? '',
    default => current_member()['profile_image'] ?? '',
};
$home = home_path();
$roleLabel = match ($role) {
    'ADMIN' => 'Admin console',
    'COACH' => 'Coach workspace',
    'PLATFORM' => 'Company billing review',
    default => 'Member portal',
};
$subscriptionReady = class_exists(SubscriptionService::class) && SubscriptionService::ready();
$subscription = $subscriptionReady ? SubscriptionService::current() : null;
$subscriptionLocked = $role !== 'PLATFORM' && $subscriptionReady && !SubscriptionService::allowsProtectedAccess($subscription);
$billingScreen = in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ['billing.php', 'renew.php'], true);
$showRenewalReminder = $role === 'ADMIN' && $subscriptionReady && !$subscriptionLocked && !$billingScreen && SubscriptionService::shouldShowRenewalReminder($subscription);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e(setting('gym_name', APP_NAME)) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="app <?= e(strtolower($role)) ?>">
<aside class="sidebar">
    <a class="brand" href="<?= e(url($home)) ?>">
        <span class="brand-mark">V</span>
        <span>
            <strong><?= e(setting('gym_name', APP_NAME)) ?></strong>
            <small><?= e($roleLabel) ?></small>
        </span>
    </a>
    <nav>
        <?php if ($role === 'PLATFORM'): ?>
            <p class="nav-label">Company</p>
            <a href="<?= e(url('platform/reviews.php')) ?>">Payment reviews</a>
        <?php elseif ($role === 'ADMIN' && $subscriptionLocked): ?>
            <p class="nav-label">Subscription</p>
            <a href="<?= e(url('admin/billing.php')) ?>">Subscription & billing</a>
            <a href="<?= e(url('admin/renew.php')) ?>">Renew subscription</a>
        <?php elseif ($role === 'ADMIN'): ?>
            <p class="nav-label">Overview</p>
            <a href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a>
            <a href="<?= e(url('admin/capacity.php')) ?>">Capacity & peaks</a>
            <p class="nav-label">People</p>
            <a href="<?= e(url('admin/members.php')) ?>">Members</a>
            <a href="<?= e(url('admin/memberships.php')) ?>">Memberships</a>
            <a href="<?= e(url('admin/trainers.php')) ?>">Coaches</a>
            <p class="nav-label">Training</p>
            <a href="<?= e(url('admin/workouts.php')) ?>">Workout plans</a>
            <a href="<?= e(url('admin/meals.php')) ?>">Meal plans</a>
            <a href="<?= e(url('admin/fitness.php')) ?>">Progress & goals</a>
            <p class="nav-label">Operations</p>
            <a href="<?= e(url('admin/attendance.php')) ?>">Attendance</a>
            <a href="<?= e(url('admin/classes.php')) ?>">Classes</a>
            <a href="<?= e(url('admin/appointments.php')) ?>">Appointments</a>
            <p class="nav-label">Business</p>
            <a href="<?= e(url('admin/payments.php')) ?>">Payments</a>
            <a href="<?= e(url('admin/expenses.php')) ?>">Expenses</a>
            <a href="<?= e(url('admin/equipment.php')) ?>">Equipment</a>
            <a href="<?= e(url('admin/inventory.php')) ?>">Inventory</a>
            <a href="<?= e(url('admin/reports.php')) ?>">Reports</a>
            <a href="<?= e(url('admin/billing.php')) ?>">Subscription & billing</a>
            <p class="nav-label">System</p>
            <a href="<?= e(url('admin/feedback.php')) ?>">Feedback</a>
            <a href="<?= e(url('admin/notifications.php')) ?>">Notifications</a>
            <a href="<?= e(url('admin/audit.php')) ?>">Audit logs</a>
            <a href="<?= e(url('admin/settings.php')) ?>">Settings</a>
        <?php elseif ($role === 'COACH'): ?>
            <p class="nav-label">My coaching</p>
            <a href="<?= e(url('coach/dashboard.php')) ?>">Dashboard</a>
            <a href="<?= e(url('coach/members.php')) ?>">Assigned members</a>
            <a href="<?= e(url('coach/progress.php')) ?>">Member progress</a>
            <a href="<?= e(url('coach/workouts.php')) ?>">Workout plans</a>
            <a href="<?= e(url('coach/meals.php')) ?>">Meal plans</a>
        <?php else: ?>
            <a href="<?= e(url('member/dashboard.php')) ?>">Dashboard</a>
            <a href="<?= e(url('member/profile.php')) ?>">My profile</a>
            <a href="<?= e(url('member/membership.php')) ?>">Membership</a>
            <a href="<?= e(url('member/payments.php')) ?>">Payments</a>
            <a href="<?= e(url('member/attendance.php')) ?>">Attendance</a>
            <a href="<?= e(url('member/fitness.php')) ?>">My workouts</a>
            <a href="<?= e(url('member/meals.php')) ?>">Meal plan</a>
            <a href="<?= e(url('member/classes.php')) ?>">Classes</a>
            <a href="<?= e(url('member/appointments.php')) ?>">Appointments</a>
            <a href="<?= e(url('member/feedback.php')) ?>">Feedback</a>
            <a href="<?= e(url('member/notifications.php')) ?>">Notifications</a>
        <?php endif; ?>
    </nav>
</aside>
<div class="main">
    <header class="topbar">
        <button class="nav-toggle" data-toggle-nav type="button" aria-label="Open menu">☰</button>
        <div>
            <h1><?= e($pageTitle) ?></h1>
            <p class="muted"><?= e($pageSubtitle ?? date('l, d M Y')) ?></p>
        </div>
        <div class="top-actions">
            <span class="who">
                <?php if ($meImage): ?>
                    <img src="<?= e(url('uploads/' . $meImage)) ?>" alt="">
                <?php else: ?>
                    <span class="avatar"><?= e(strtoupper(substr((string) $meName, 0, 1))) ?></span>
                <?php endif; ?>
                <?= e($meName) ?>
            </span>
            <a class="btn ghost" href="<?= e(url('logout.php')) ?>">Log out</a>
        </div>
    </header>
    <main class="content">
        <?php if ($msg = flash('success')): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
        <?php if ($msg = flash('error')): ?><div class="alert bad"><?= e($msg) ?></div><?php endif; ?>
        <?php if ($msg = flash('info')): ?><div class="alert"><?= e($msg) ?></div><?php endif; ?>
        <?php if ($showRenewalReminder && $subscription): ?>
            <?php
            $remaining = SubscriptionService::remaining($subscription);
            $dueAmount = SubscriptionService::expectedAmount($subscription);
            ?>
            <aside class="renewal-float" data-renewal-expiry="<?= (int) $remaining['expiry'] ?>">
                <h3>Subscription Renewal Reminder</h3>
                <p>Your Gym Management System subscription is due for renewal.</p>
                <p><strong>Amount:</strong> <?= e(money($dueAmount)) ?></p>
                <p><strong>Due Date:</strong> <?= e(date('d F Y', strtotime((string) $subscription['expiry_date']))) ?></p>
                <p><strong>Remaining:</strong> <span data-renewal-left><?= e(SubscriptionService::remainingLabel($subscription)) ?></span></p>
                <a class="btn full" href="<?= e(url('admin/renew.php')) ?>">Renew Subscription</a>
            </aside>
        <?php endif; ?>

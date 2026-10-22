<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';

if (PHP_SAPI !== 'cli') {
    require_login('ADMIN');
}

$changed = SubscriptionService::refreshStatuses();
$payload = ['updated' => $changed, 'checked_at' => date('c')];

if (PHP_SAPI === 'cli') {
    echo json_encode($payload, JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

flash('success', 'Subscription statuses refreshed: ' . $changed . ' updated.');
redirect('admin/billing.php');

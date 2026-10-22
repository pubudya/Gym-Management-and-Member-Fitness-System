<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';

$token = $_GET['token'] ?? $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token) && is_post()) {
    // cron may be called without a session from CLI
}

if (PHP_SAPI !== 'cli') {
    require_login('ADMIN');
}

$results = RulesEngine::run();

if (PHP_SAPI === 'cli') {
    echo json_encode($results, JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

flash('success', 'Automation rules ran: ' . json_encode($results));
redirect('admin/dashboard.php');

<?php
declare(strict_types=1);

$app_config = require dirname(__DIR__) . '/config/config.php';
$GLOBALS['app_config'] = $app_config;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('VOLTGYMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) getenv('RAILWAY_ENVIRONMENT') !== '',
    ]);
    session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Installer.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/SmsService.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/RulesEngine.php';
require_once __DIR__ . '/AttendanceService.php';
require_once __DIR__ . '/PaymentService.php';
require_once __DIR__ . '/SchemaUpgrade.php';
require_once __DIR__ . '/TrainingService.php';
require_once __DIR__ . '/ReceiptOcrService.php';
require_once __DIR__ . '/ReceiptVerificationService.php';
require_once __DIR__ . '/SubscriptionService.php';

try {
    SchemaUpgrade::apply();
} catch (Throwable $e) {
}

try {
    SubscriptionService::refreshStatuses();
} catch (Throwable $e) {
}

if (is_post()) {
    verify_csrf();
}

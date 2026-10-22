<?php
declare(strict_types=1);

/**
 * Default configuration. Copy to config.local.php to override without editing this file.
 * On Railway, set the Variables in the dashboard instead of this file.
 */
if (!function_exists('env_value')) {
    function env_value(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}

$app = [
    'name' => env_value('APP_NAME', 'Volt Gym'),
    'base_url' => env_value('APP_BASE_URL', '/gym-management'),
    'timezone' => env_value('APP_TIMEZONE', 'Asia/Colombo'),
    'app_secret' => env_value('APP_SECRET', 'change-this-secret-in-production-volt-gym-2026'),
    'currency' => env_value('APP_CURRENCY', 'LKR'),
    'db' => [
        'host' => env_value('MYSQLHOST', env_value('MYSQL_HOST', env_value('DB_HOST', '127.0.0.1'))),
        'port' => (int) env_value('MYSQLPORT', env_value('MYSQL_PORT', env_value('DB_PORT', '3306'))),
        'name' => env_value('MYSQLDATABASE', env_value('MYSQL_DATABASE', env_value('DB_NAME', 'gym_management'))),
        'user' => env_value('MYSQLUSER', env_value('MYSQL_USER', env_value('DB_USER', 'root'))),
        'pass' => env_value('MYSQLPASSWORD', env_value('MYSQL_PASSWORD', env_value('DB_PASS', ''))),
        'charset' => 'utf8mb4',
    ],
    'sms' => [
        'enabled' => true,
        'provider' => env_value('SMS_PROVIDER', 'demo'),
        'api_url' => env_value('SMS_API_URL', ''),
        'api_key' => env_value('SMS_API_KEY', ''),
        'sender_id' => env_value('SMS_SENDER_ID', 'VOLTGYM'),
    ],
    'uploads' => [
        'max_bytes' => 2 * 1024 * 1024,
        'allowed' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    ],
    'receipts' => [
        'max_bytes' => 5 * 1024 * 1024,
        'allowed' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ],
    ],
    'billing' => [
        'company_name' => 'Volt Gym Software',
        'account_name' => 'Volt Gym (Pvt) Ltd',
        'bank_name' => 'Commercial Bank of Ceylon',
        'account_number' => '8001234567',
        'branch' => 'Colombo 03',
        'support_email' => 'billing@voltgym.local',
        'support_phone' => '0112345678',
        'instructions' => 'Transfer the exact subscription amount to the company bank account below. Use your gym name as the payment reference, then upload a clear photo or PDF of the bank slip or transfer confirmation.',
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $app = array_replace_recursive($app, $override);
    }
}

$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
if (env_value('RAILWAY_ENVIRONMENT') || env_value('RAILWAY_PUBLIC_DOMAIN')) {
    $app['base_url'] = (string) env_value('APP_BASE_URL', '');
} elseif (PHP_SAPI === 'cli-server') {
    $app['base_url'] = '';
} elseif ($docRoot) {
    $docRoot = str_replace('\\', '/', $docRoot);
    if (str_starts_with($projectRoot, $docRoot)) {
        $app['base_url'] = rtrim(substr($projectRoot, strlen($docRoot)), '/');
    }
}

define('APP_NAME', $app['name']);
define('BASE_URL', rtrim($app['base_url'], '/'));
define('BASE_PATH', dirname(__DIR__));
define('APP_SECRET', $app['app_secret']);
define('APP_CURRENCY', $app['currency']);
define('DB_HOST', $app['db']['host']);
define('DB_PORT', (int) $app['db']['port']);
define('DB_NAME', $app['db']['name']);
define('DB_USER', $app['db']['user']);
define('DB_PASS', $app['db']['pass']);
define('DB_CHARSET', $app['db']['charset']);

date_default_timezone_set($app['timezone']);

return $app;

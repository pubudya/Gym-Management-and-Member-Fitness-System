<?php
declare(strict_types=1);

/**
 * Runs every hour so subscriptions expire even if nobody logs in.
 */
require dirname(__DIR__) . '/includes/init.php';

$seconds = max(300, (int) (getenv('CRON_INTERVAL_SECONDS') ?: 3600));

while (true) {
    try {
        $sub = class_exists(SubscriptionService::class) ? SubscriptionService::refreshStatuses() : 0;
        $rules = class_exists(RulesEngine::class) ? RulesEngine::run() : [];
        echo date('c') . " subscriptions={$sub} automation=" . json_encode($rules) . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, date('c') . ' cron error: ' . $e->getMessage() . PHP_EOL);
    }
    sleep($seconds);
}

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';

if (class_exists(SubscriptionService::class) && SubscriptionService::ready() && !SubscriptionService::allowsProtectedAccess()) {
    json_response(['ok' => false, 'error' => 'Gym software subscription has expired. Ask the gym owner to renew.'], 403);
}

$raw = trim((string) (post('code') ?: query_param('code')));
$method = strtoupper((string) (post('method') ?: query_param('method') ?: 'QR'));
if (!in_array($method, ['QR', 'MEMBER_CODE', 'ADMIN'], true)) {
    $method = 'QR';
}

$member = parse_member_qr($raw);
if (!$member) {
    json_response(['ok' => false, 'error' => 'Member not found for that QR / code.'], 404);
}

$by = current_user()['user_id'] ?? null;
$result = AttendanceService::toggle($member, $method, $by);
json_response($result, $result['ok'] ? 200 : 422);

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login(['ADMIN', 'PLATFORM']);

$id = (int) query_param('id');
$payment = $id ? SubscriptionService::payment($id) : null;
if (!$payment) {
    http_response_code(404);
    exit('Receipt not found.'); 
    
}

$role = current_user()['role'] ?? '';
if ($role === 'ADMIN') {
    $gymId = SubscriptionService::currentGymId();
    if ((int) $payment['gym_id'] !== $gymId) {
        http_response_code(403);
        exit('Access denied.');
    }
}

$path = receipt_absolute_path((string) $payment['receipt_path']);
$realBase = realpath(BASE_PATH . '/storage/receipts');
$realFile = realpath($path);
if (!$realBase || !$realFile || !str_starts_with($realFile, $realBase) || !is_file($realFile)) {
    http_response_code(404);
    exit('Receipt file is not available.');
}

$mime = (string) ($payment['receipt_mime'] ?: (new finfo(FILEINFO_MIME_TYPE))->file($realFile));
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($mime, $allowed, true)) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Disposition: inline; filename="receipt-' . $id . '"');
header('Content-Length: ' . (string) filesize($realFile));
readfile($realFile);
exit;

<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function flash(string $key, ?string $message = null)
{
    if ($message === null) {
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
    }
    $_SESSION['flash'][$key] = $message;
}

function money($amount): string
{
    return APP_CURRENCY . ' ' . number_format((float) $amount, 2);
}

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (Database::fetchAll('SELECT setting_key, setting_value FROM system_settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            return $default;
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, $value): void
{
    Database::execute(
        'INSERT INTO system_settings (setting_key, setting_value, updated_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
        [$key, (string) $value, current_user()['user_id'] ?? null]
    );
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_member(): ?array
{
    return $_SESSION['member'] ?? null;
}

function current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function current_coach(): ?array
{
    return $_SESSION['coach'] ?? null;
}

function home_path(): string
{
    return match (current_user()['role'] ?? '') {
        'ADMIN' => 'admin/dashboard.php',
        'COACH' => 'coach/dashboard.php',
        'PLATFORM' => 'platform/reviews.php',
        default => 'member/dashboard.php',
    };
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function post(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function query_param(string $key, $default = '')
{
    return $_GET[$key] ?? $default;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
        http_response_code(403);
        exit('Invalid security token. Please refresh and try again.');
    }
}

function require_login(string|array|null $role = null): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please log in to continue.');
        redirect('index.php');
    }
    if ($user['status'] !== 'ACTIVE') {
        $_SESSION = [];
        flash('error', 'This account is not active.');
        redirect('index.php');
    }
    $roles = $role === null ? [] : (array) $role;
    if ($roles && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied for this role.');
    }
    if (($user['role'] ?? '') !== 'PLATFORM' && class_exists(SubscriptionService::class)) {
        SubscriptionService::enforceAccess();
    }
    return $user;
}

function audit(string $action, string $module, string $description = '', $recordId = null): void
{
    try {
        Database::insert('audit_logs', [
            'user_id' => current_user()['user_id'] ?? null,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'record_id' => $recordId !== null ? (string) $recordId : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
    } catch (Throwable $e) {
        // never block the request because logging failed
    }
}

function next_code(string $prefix, string $table, string $column): string
{
    $row = Database::fetch("SELECT $column AS code FROM $table WHERE $column LIKE ? ORDER BY $column DESC LIMIT 1", [$prefix . '%']);
    $n = 1;
    if ($row && preg_match('/(\d+)$/', (string) $row['code'], $m)) {
        $n = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

function valid_nic(string $nic): bool
{
    return (bool) preg_match('/^(\d{9}[vVxX]|\d{12})$/', trim($nic));
}

function valid_phone(string $phone): bool
{
    $phone = preg_replace('/\s+/', '', $phone);
    return (bool) preg_match('/^(\+94|0)?7\d{8}$/', $phone);
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function upload_image(string $field, string $folder): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }
    if ($file['size'] > $GLOBALS['app_config']['uploads']['max_bytes']) {
        throw new RuntimeException('Image must be 2MB or smaller.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = $GLOBALS['app_config']['uploads']['allowed'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    }
    $dir = BASE_PATH . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload folder could not be created.');
    }
    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    return $folder . '/' . $name;
}

function upload_receipt(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        throw new RuntimeException('Please upload a payment receipt.');
    }
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Receipt upload failed. Try a smaller file.');
    }
    $max = (int) ($GLOBALS['app_config']['receipts']['max_bytes'] ?? (5 * 1024 * 1024));
    if (($file['size'] ?? 0) > $max) {
        throw new RuntimeException('Receipt must be 5MB or smaller.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('The uploaded receipt could not be read.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowed = $GLOBALS['app_config']['receipts']['allowed'] ?? [];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP and PDF receipts are allowed.');
    }
    $ext = $allowed[$mime];
    $hash = hash_file('sha256', $tmp);
    $dir = BASE_PATH . '/storage/receipts/' . date('Y/m');
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Secure receipt storage could not be created.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not store the payment receipt.');
    }
    @chmod($dest, 0640);
    return [
        'path' => date('Y/m') . '/' . $name,
        'hash' => $hash,
        'mime' => $mime,
        'original' => substr((string) $file['name'], 0, 255),
    ];
}

function receipt_absolute_path(string $relative): string
{
    $relative = str_replace(['\\', '..'], ['/', ''], $relative);
    return BASE_PATH . '/storage/receipts/' . ltrim($relative, '/');
}

function badge_class(string $status): string
{
    $status = strtoupper($status);
    return match (true) {
        in_array($status, ['ACTIVE', 'SUCCESS', 'COMPLETED', 'AVAILABLE', 'SENT', 'BOOKED', 'ATTENDED', 'APPROVED', 'VERIFIED'], true) => 'ok',
        in_array($status, ['PENDING', 'PROCESSING', 'IN_PROGRESS', 'IN_REVIEW', 'SCHEDULED', 'WAITING', 'LOW_STOCK', 'FROZEN', 'EXPIRING_SOON', 'PAYMENT_PENDING', 'PAYMENT_REVIEW', 'REVIEW_REQUIRED'], true) => 'warn',
        in_array($status, ['EXPIRED', 'FAILED', 'CANCELLED', 'INACTIVE', 'SUSPENDED', 'CRITICAL', 'OUT_OF_STOCK', 'DAMAGED', 'OVERDUE', 'REJECTED'], true) => 'bad',
        default => 'muted',
    };
}

function occupancy_count(): int
{
    $row = Database::fetch(
        "SELECT COUNT(*) AS c FROM attendance
         WHERE attendance_date = CURDATE() AND check_out IS NULL"
    );
    return (int) ($row['c'] ?? 0);
}

function engagement_score(int $memberId): int
{
    $visits = Database::fetch(
        'SELECT COUNT(*) AS c FROM attendance WHERE member_id = ? AND check_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
        [$memberId]
    );
    $expected = 12;
    $score = min(100, (int) round(((int) ($visits['c'] ?? 0) / $expected) * 100));
    return $score;
}

function monthly_fee(): float
{
    return max(0, (float) setting('monthly_fee', '0'));
}

function active_membership(int $memberId): ?array
{
    return Database::fetch(
        "SELECT ms.*
         FROM memberships ms
         WHERE ms.member_id = ? AND ms.status IN ('ACTIVE','PENDING','FROZEN')
         ORDER BY FIELD(ms.status,'ACTIVE','FROZEN','PENDING'), ms.end_date DESC
         LIMIT 1",
        [$memberId]
    );
}

function member_qr_payload(array $member): string
{
    $payload = $member['member_code'] . '|' . $member['member_id'];
    return $payload . '.' . hash_hmac('sha256', $payload, APP_SECRET);
}

function parse_member_qr(string $raw): ?array
{
    $raw = trim($raw);
    if (!str_contains($raw, '.')) {
        $member = Database::fetch('SELECT * FROM members WHERE member_code = ? OR nic = ?', [$raw, $raw]);
        return $member ?: null;
    }
    [$payload, $sig] = explode('.', $raw, 2);
    $expected = hash_hmac('sha256', $payload, APP_SECRET);
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    [$code, $id] = array_pad(explode('|', $payload, 2), 2, '');
    return Database::fetch('SELECT * FROM members WHERE member_id = ? AND member_code = ?', [$id, $code]);
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? 'selected' : '';
}

function checked($a, $b): string
{
    return (string) $a === (string) $b ? 'checked' : '';
}

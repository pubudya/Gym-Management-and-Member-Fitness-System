<?php
declare(strict_types=1);

final class SubscriptionService
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_EXPIRING_SOON = 'EXPIRING_SOON';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_PAYMENT_PENDING = 'PAYMENT_PENDING';
    public const STATUS_PAYMENT_REVIEW = 'PAYMENT_REVIEW';
    public const STATUS_SUSPENDED = 'SUSPENDED';

    public const REMINDER_DAYS = 10;

    private const EXEMPT_SUFFIXES = [
        '/admin/billing.php',
        '/admin/renew.php',
        '/admin/receipt.php',
        '/platform/reviews.php',
        '/cron/automate.php',
        '/cron/subscriptions.php',
    ];

    private const SCRIPT_FEATURES = [
        'admin/members.php' => 'member_management',
        'admin/memberships.php' => 'member_management',
        'admin/trainers.php' => 'coach_management',
        'admin/attendance.php' => 'attendance',
        'admin/capacity.php' => 'attendance',
        'admin/payments.php' => 'basic_payments',
        'admin/expenses.php' => 'expenses',
        'admin/workouts.php' => 'workout_management',
        'admin/meals.php' => 'meal_plans',
        'admin/fitness.php' => 'progress_tracking',
        'admin/reports.php' => 'reports',
        'admin/equipment.php' => 'equipment_management',
        'admin/inventory.php' => 'inventory',
        'admin/classes.php' => 'classes',
        'admin/appointments.php' => 'appointments',
        'admin/notifications.php' => 'sms_notifications',
        'admin/feedback.php' => 'feedback',
        'coach/members.php' => 'member_management',
        'coach/progress.php' => 'progress_tracking',
        'coach/workouts.php' => 'workout_management',
        'coach/meals.php' => 'meal_plans',
        'member/membership.php' => 'member_management',
        'member/payments.php' => 'basic_payments',
        'member/attendance.php' => 'attendance',
        'member/fitness.php' => 'workout_management',
        'member/meals.php' => 'meal_plans',
        'member/classes.php' => 'classes',
        'member/appointments.php' => 'appointments',
    ];

    public static function ready(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM gym_subscriptions LIMIT 1');
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    public static function currentGym(): ?array
    {
        if (!self::ready()) {
            return null;
        }
        return Database::fetch('SELECT * FROM gyms ORDER BY gym_id ASC LIMIT 1');
    }

    public static function currentGymId(): int
    {
        return (int) (self::currentGym()['gym_id'] ?? 0);
    }

    public static function current(): ?array
    {
        if (!self::ready()) {
            return null;
        }
        $gymId = self::currentGymId();
        if ($gymId < 1) {
            return null;
        }
        return Database::fetch(
            'SELECT gs.*, sp.plan_code, sp.plan_name, sp.monthly_price, sp.duration_days,
                    sp.features_json, sp.member_limit, sp.coach_limit, sp.notification_limit
             FROM gym_subscriptions gs
             JOIN subscription_plans sp ON sp.plan_id = gs.plan_id
             WHERE gs.gym_id = ?
             ORDER BY gs.subscription_id DESC
             LIMIT 1',
            [$gymId]
        );
    }

    public static function planByCode(string $code): ?array
    {
        if (!self::ready()) {
            return null;
        }
        return Database::fetch('SELECT * FROM subscription_plans WHERE plan_code = ? LIMIT 1', [$code]);
    }

    public static function defaultPlan(): ?array
    {
        return self::planByCode('PROFESSIONAL')
            ?: Database::fetch("SELECT * FROM subscription_plans WHERE status='ACTIVE' ORDER BY plan_id LIMIT 1");
    }

    public static function planFeatures(?array $plan): array
    {
        $raw = $plan['features_json'] ?? '[]';
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function hasFeature(string $feature, ?array $subscription = null): bool
    {
        $subscription ??= self::current();
        if (!$subscription) {
            return false;
        }
        $features = self::planFeatures($subscription);
        return in_array($feature, $features, true);
    }

    public static function expectedAmount(?array $subscription = null): float
    {
        $subscription ??= self::current();
        if ($subscription) {
            return (float) ($subscription['amount'] ?: $subscription['monthly_price'] ?? 0);
        }
        $plan = self::defaultPlan();
        return (float) ($plan['monthly_price'] ?? 0);
    }

    public static function durationDays(?array $subscription = null): int
    {
        $subscription ??= self::current();
        $days = (int) ($subscription['duration_days'] ?? 0);
        return $days > 0 ? $days : 30;
    }

    public static function billingDetails(): array
    {
        $cfg = $GLOBALS['app_config']['billing'] ?? [];
        return [
            'company_name' => (string) setting('billing_company_name', $cfg['company_name'] ?? 'Volt Gym Software'),
            'account_name' => (string) setting('billing_account_name', $cfg['account_name'] ?? ''),
            'bank_name' => (string) setting('billing_bank_name', $cfg['bank_name'] ?? ''),
            'account_number' => (string) setting('billing_account_number', $cfg['account_number'] ?? ''),
            'branch' => (string) setting('billing_branch', $cfg['branch'] ?? ''),
            'support_email' => (string) setting('billing_support_email', $cfg['support_email'] ?? ''),
            'support_phone' => (string) setting('billing_support_phone', $cfg['support_phone'] ?? ''),
            'instructions' => (string) setting('billing_instructions', $cfg['instructions'] ?? ''),
        ];
    }

    public static function expiryTimestamp(?array $subscription = null): int
    {
        $subscription ??= self::current();
        if (!$subscription || empty($subscription['expiry_date'])) {
            return 0;
        }
        $ts = strtotime((string) $subscription['expiry_date'] . ' 23:59:59');
        return $ts ?: 0;
    }

    public static function remaining(?array $subscription = null): array
    {
        $expiry = self::expiryTimestamp($subscription);
        $now = time();
        $seconds = max(0, $expiry - $now);
        return [
            'seconds' => $seconds,
            'days' => intdiv($seconds, 86400),
            'hours' => intdiv($seconds % 86400, 3600),
            'minutes' => intdiv($seconds % 3600, 60),
            'expiry' => $expiry,
            'past' => $expiry > 0 && $expiry < $now,
        ];
    }

    public static function remainingLabel(?array $subscription = null): string
    {
        $r = self::remaining($subscription);
        if ($r['past'] || $r['seconds'] <= 0) {
            return 'Expired';
        }
        return $r['days'] . ' days ' . $r['hours'] . ' hours remaining';
    }

    public static function isWithinPeriod(?array $subscription = null): bool
    {
        $subscription ??= self::current();
        if (!$subscription) {
            return false;
        }
        $expiry = strtotime((string) $subscription['expiry_date'] . ' 23:59:59');
        return $expiry !== false && $expiry >= time();
    }

    public static function allowsProtectedAccess(?array $subscription = null): bool
    {
        if (!self::ready()) {
            return true;
        }
        $subscription ??= self::current();
        if (!$subscription) {
            return true;
        }
        if (($subscription['status'] ?? '') === self::STATUS_SUSPENDED) {
            return false;
        }
        return self::isWithinPeriod($subscription);
    }

    public static function shouldShowRenewalReminder(?array $subscription = null): bool
    {
        $subscription ??= self::current();
        if (!$subscription || !self::allowsProtectedAccess($subscription)) {
            return false;
        }
        $role = current_user()['role'] ?? '';
        if ($role !== 'ADMIN') {
            return false;
        }
        $remaining = self::remaining($subscription);
        return $remaining['days'] <= self::REMINDER_DAYS;
    }

    public static function isExemptPath(): bool
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        foreach (self::EXEMPT_SUFFIXES as $suffix) {
            if (str_ends_with($script, $suffix)) {
                return true;
            }
        }
        return false;
    }

    public static function currentScriptKey(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (preg_match('#/(admin|coach|member|platform|api)/[^/]+\.php$#', $script, $m)) {
            return ltrim($m[0], '/');
        }
        return basename($script);
    }

    public static function enforceAccess(): void
    {
        if (!self::ready() || self::isExemptPath()) {
            return;
        }
        $role = current_user()['role'] ?? '';
        if ($role === 'PLATFORM') {
            return;
        }

        self::refreshStatuses();
        $subscription = self::current();

        if (self::allowsProtectedAccess($subscription)) {
            $feature = self::SCRIPT_FEATURES[self::currentScriptKey()] ?? null;
            if ($feature && !self::hasFeature($feature, $subscription)) {
                http_response_code(403);
                exit('This feature is not included in your current subscription plan.');
            }
            return;
        }

        if ($role === 'ADMIN') {
            flash('error', 'Your gym software subscription has expired. Renew to restore access. All gym data is still safely stored.');
            redirect('admin/billing.php');
        }

        require BASE_PATH . '/includes/subscription_blocked.php';
        exit;
    }

    public static function refreshStatuses(): int
    {
        if (!self::ready()) {
            return 0;
        }
        $rows = Database::fetchAll('SELECT * FROM gym_subscriptions');
        $changed = 0;
        foreach ($rows as $row) {
            $next = self::deriveStatus($row);
            if ($next !== $row['status']) {
                Database::update('gym_subscriptions', [
                    'status' => $next,
                ], 'subscription_id = ?', [$row['subscription_id']]);
                $changed++;
                if ($next === self::STATUS_EXPIRED && $row['status'] !== self::STATUS_EXPIRED) {
                    self::notifyAdmins(
                        'SUBSCRIPTION_EXPIRED',
                        'Your Gym Management System subscription has expired. All gym data is safely stored. Renew from Subscription & Billing to restore access.'
                    );
                } elseif ($next === self::STATUS_EXPIRING_SOON && $row['status'] === self::STATUS_ACTIVE) {
                    self::notifyAdmins(
                        'SUBSCRIPTION_EXPIRING',
                        'Your Gym Management System subscription is due for renewal on ' . $row['expiry_date'] . '.'
                    );
                }
            }
        }
        return $changed;
    }

    public static function deriveStatus(array $subscription): string
    {
        if (($subscription['status'] ?? '') === self::STATUS_SUSPENDED) {
            return self::STATUS_SUSPENDED;
        }

        $expiry = strtotime((string) $subscription['expiry_date'] . ' 23:59:59');
        $now = time();
        if ($expiry === false || $expiry < $now) {
            return self::STATUS_EXPIRED;
        }

        $open = self::latestOpenPayment((int) $subscription['gym_id']);
        if ($open) {
            if (($open['verification_result'] ?? '') === 'REVIEW_REQUIRED') {
                return self::STATUS_PAYMENT_REVIEW;
            }
            return self::STATUS_PAYMENT_PENDING;
        }

        $days = intdiv(max(0, $expiry - $now), 86400);
        if ($days <= self::REMINDER_DAYS) {
            return self::STATUS_EXPIRING_SOON;
        }
        return self::STATUS_ACTIVE;
    }

    public static function latestOpenPayment(int $gymId): ?array
    {
        return Database::fetch(
            "SELECT * FROM subscription_payments
             WHERE gym_id = ? AND payment_status = 'PENDING'
             ORDER BY payment_id DESC LIMIT 1",
            [$gymId]
        );
    }

    public static function payments(int $gymId): array
    {
        return Database::fetchAll(
            'SELECT * FROM subscription_payments WHERE gym_id = ? ORDER BY payment_id DESC',
            [$gymId]
        );
    }

    public static function payment(int $paymentId): ?array
    {
        return Database::fetch('SELECT * FROM subscription_payments WHERE payment_id = ?', [$paymentId]);
    }

    public static function logs(int $paymentId): array
    {
        return Database::fetchAll(
            'SELECT * FROM subscription_payment_logs WHERE payment_id = ? ORDER BY log_id ASC',
            [$paymentId]
        );
    }

    public static function submitRenewal(array $file, string $reference, int $userId): array
    {
        $subscription = self::current();
        $plan = $subscription ?: self::defaultPlan();
        if (!$subscription || !$plan) {
            throw new RuntimeException('No gym subscription is configured.');
        }

        $stored = upload_receipt($file);
        $amount = self::expectedAmount($subscription);

        if ($reference !== '' && self::isDuplicateReference($reference)) {
            @unlink(receipt_absolute_path($stored['path']));
            throw new RuntimeException('This payment reference has already been used for a subscription renewal.');
        }
        if (self::isDuplicateReceipt($stored['hash'])) {
            @unlink(receipt_absolute_path($stored['path']));
            throw new RuntimeException('This receipt has already been used for a subscription payment.');
        }

        $paymentId = Database::insert('subscription_payments', [
            'gym_id' => (int) $subscription['gym_id'],
            'subscription_id' => (int) $subscription['subscription_id'],
            'plan_id' => (int) $subscription['plan_id'],
            'amount' => $amount,
            'currency' => APP_CURRENCY,
            'payment_reference' => $reference !== '' ? $reference : null,
            'payment_date' => date('Y-m-d'),
            'receipt_path' => $stored['path'],
            'receipt_hash' => $stored['hash'],
            'receipt_mime' => $stored['mime'],
            'original_filename' => $stored['original'],
            'verification_result' => 'PENDING',
            'payment_status' => 'PENDING',
            'ocr_json' => null,
            'submitted_by' => $userId,
        ]);

        Database::update('gym_subscriptions', [
            'status' => self::STATUS_PAYMENT_PENDING,
            'next_payment_due_date' => $subscription['expiry_date'],
        ], 'subscription_id = ?', [$subscription['subscription_id']]);

        self::log($paymentId, 'SUBMITTED', 'PENDING', 'Owner uploaded a payment receipt.', $userId, [
            'reference' => $reference,
            'filename' => $stored['original'],
            'hash' => $stored['hash'],
        ]);

        audit('SUBMIT', 'subscription', 'Subscription payment receipt uploaded', $paymentId);

        $ocr = ReceiptOcrService::analyze(receipt_absolute_path($stored['path']), $stored['mime']);
        Database::update('subscription_payments', [
            'ocr_json' => json_encode($ocr, JSON_UNESCAPED_UNICODE),
            'extracted_amount' => $ocr['parsed']['amount'] ?? null,
            'extracted_date' => $ocr['parsed']['date'] ?? null,
            'extracted_reference' => $ocr['parsed']['reference'] ?? null,
            'extracted_bank' => $ocr['parsed']['bank'] ?? null,
            'extracted_sender' => $ocr['parsed']['sender'] ?? null,
            'extracted_recipient' => $ocr['parsed']['recipient'] ?? null,
        ], 'payment_id = ?', [$paymentId]);

        self::log($paymentId, 'OCR', 'PENDING', $ocr['engine'] . ' extracted receipt text.', $userId, [
            'engine' => $ocr['engine'],
            'readable' => $ocr['readable'],
            'text_length' => strlen((string) ($ocr['text'] ?? '')),
        ]);

        if ($reference === '' && !empty($ocr['parsed']['reference'])) {
            $reference = (string) $ocr['parsed']['reference'];
            if (self::isDuplicateReference($reference, $paymentId)) {
                return self::rejectPayment(
                    $paymentId,
                    null,
                    'This transaction/reference number has already been used for a subscription renewal.',
                    'REJECTED'
                );
            }
            Database::update('subscription_payments', [
                'payment_reference' => $reference,
            ], 'payment_id = ?', [$paymentId]);
        }

        $verification = ReceiptVerificationService::verify(
            self::payment($paymentId) ?? [],
            $ocr,
            $amount,
            self::billingDetails()
        );

        Database::update('subscription_payments', [
            'verification_result' => $verification['result'],
            'verification_notes' => $verification['summary'],
            'confidence_score' => $verification['confidence'],
        ], 'payment_id = ?', [$paymentId]);

        self::log($paymentId, 'VERIFY', $verification['result'], $verification['summary'], $userId, $verification);

        if ($verification['result'] === 'VERIFIED') {
            return self::approvePayment($paymentId, $userId, 'Automatically verified against the expected subscription payment.', true);
        }

        if ($verification['result'] === 'REJECTED') {
            return self::rejectPayment($paymentId, null, $verification['summary'], 'REJECTED');
        }

        Database::update('gym_subscriptions', [
            'status' => self::isWithinPeriod($subscription) ? self::STATUS_PAYMENT_REVIEW : self::STATUS_EXPIRED,
        ], 'subscription_id = ?', [$subscription['subscription_id']]);

        self::notifyAdmins(
            'SUBSCRIPTION_PAYMENT_REVIEW',
            'A subscription payment receipt is waiting for manual verification.'
        );

        return [
            'ok' => false,
            'state' => 'REVIEW_REQUIRED',
            'payment_id' => $paymentId,
            'message' => 'Your receipt was received and is being reviewed. The subscription will renew after verification.',
            'verification' => $verification,
        ];
    }

    public static function approvePayment(int $paymentId, ?int $reviewerId, string $notes, bool $automatic = false): array
    {
        $payment = self::payment($paymentId);
        if (!$payment) {
            throw new RuntimeException('Payment not found.');
        }
        if ($payment['payment_status'] === 'APPROVED') {
            return ['ok' => true, 'state' => 'APPROVED', 'payment_id' => $paymentId, 'already' => true];
        }

        $reference = trim((string) ($payment['payment_reference'] ?? ''));
        if ($reference !== '' && self::isDuplicateReference($reference, $paymentId)) {
            return self::rejectPayment($paymentId, $reviewerId, 'Duplicate transaction/reference number.', 'REJECTED');
        }
        if (!empty($payment['receipt_hash']) && self::isDuplicateReceipt((string) $payment['receipt_hash'], $paymentId)) {
            return self::rejectPayment($paymentId, $reviewerId, 'This receipt was already used for another renewal.', 'REJECTED');
        }

        $subscription = Database::fetch(
            'SELECT gs.*, sp.plan_name, sp.duration_days, sp.monthly_price
             FROM gym_subscriptions gs
             JOIN subscription_plans sp ON sp.plan_id = gs.plan_id
             WHERE gs.subscription_id = ?',
            [$payment['subscription_id']]
        );
        if (!$subscription) {
            throw new RuntimeException('Subscription not found.');
        }

        $days = (int) ($subscription['duration_days'] ?? 30);
        $from = max(strtotime((string) $subscription['expiry_date']), strtotime('today'));
        $newExpiry = date('Y-m-d', strtotime('+' . $days . ' days', $from));
        $newStart = date('Y-m-d', $from);

        Database::update('subscription_payments', [
            'payment_status' => 'APPROVED',
            'verification_result' => $automatic ? 'VERIFIED' : 'VERIFIED',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $notes,
        ], 'payment_id = ?', [$paymentId]);

        Database::update('gym_subscriptions', [
            'start_date' => $newStart,
            'expiry_date' => $newExpiry,
            'next_payment_due_date' => $newExpiry,
            'last_payment_id' => $paymentId,
            'status' => self::STATUS_ACTIVE,
            'amount' => $payment['amount'],
        ], 'subscription_id = ?', [$subscription['subscription_id']]);

        self::log(
            $paymentId,
            $automatic ? 'AUTO_APPROVED' : 'MANUAL_APPROVED',
            'VERIFIED',
            $notes,
            $reviewerId,
            ['new_expiry' => $newExpiry]
        );
        audit($automatic ? 'APPROVE_AUTO' : 'APPROVE', 'subscription', $notes, $paymentId);

        self::notifyAdmins(
            'SUBSCRIPTION_RENEWED',
            'Your Gym Management System subscription was renewed until ' . $newExpiry . '.'
        );

        return [
            'ok' => true,
            'state' => 'APPROVED',
            'payment_id' => $paymentId,
            'plan' => $subscription['plan_name'],
            'amount' => $payment['amount'],
            'expiry_date' => $newExpiry,
            'automatic' => $automatic,
        ];
    }

    public static function rejectPayment(int $paymentId, ?int $reviewerId, string $notes, string $result = 'REJECTED'): array
    {
        $payment = self::payment($paymentId);
        if (!$payment) {
            throw new RuntimeException('Payment not found.');
        }

        Database::update('subscription_payments', [
            'payment_status' => 'REJECTED',
            'verification_result' => $result,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $notes,
            'verification_notes' => $notes,
        ], 'payment_id = ?', [$paymentId]);

        $subscription = Database::fetch('SELECT * FROM gym_subscriptions WHERE subscription_id = ?', [$payment['subscription_id']]);
        if ($subscription && ($subscription['status'] ?? '') !== self::STATUS_SUSPENDED) {
            Database::update('gym_subscriptions', [
                'status' => self::deriveStatus(array_merge($subscription, ['status' => self::STATUS_ACTIVE])),
            ], 'subscription_id = ?', [$subscription['subscription_id']]);
        }

        self::log($paymentId, $reviewerId ? 'MANUAL_REJECTED' : 'AUTO_REJECTED', 'REJECTED', $notes, $reviewerId);
        audit('REJECT', 'subscription', $notes, $paymentId);

        return [
            'ok' => false,
            'state' => 'REJECTED',
            'payment_id' => $paymentId,
            'message' => $notes,
        ];
    }

    public static function isDuplicateReference(string $reference, ?int $excludeId = null): bool
    {
        $reference = trim($reference);
        if ($reference === '') {
            return false;
        }
        $sql = "SELECT payment_id FROM subscription_payments
                WHERE payment_reference = ? AND payment_status IN ('APPROVED','PENDING')";
        $params = [$reference];
        if ($excludeId) {
            $sql .= ' AND payment_id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        return Database::fetch($sql, $params) !== null;
    }

    public static function isDuplicateReceipt(string $hash, ?int $excludeId = null): bool
    {
        if ($hash === '') {
            return false;
        }
        $sql = "SELECT payment_id FROM subscription_payments
                WHERE receipt_hash = ? AND payment_status IN ('APPROVED','PENDING')";
        $params = [$hash];
        if ($excludeId) {
            $sql .= ' AND payment_id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        return Database::fetch($sql, $params) !== null;
    }

    public static function log(int $paymentId, string $action, string $result, string $details, ?int $actorId = null, array $payload = []): void
    {
        try {
            Database::insert('subscription_payment_logs', [
                'payment_id' => $paymentId,
                'action' => $action,
                'result' => $result,
                'details' => $details,
                'payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                'actor_user_id' => $actorId,
            ]);
        } catch (Throwable $e) {
        }
    }

    private static function notifyAdmins(string $type, string $message): void
    {
        try {
            NotificationService::notifyAdmins($type, $message, ['dedupe' => true, 'hours' => 20]);
        } catch (Throwable $e) {
        }
    }
}

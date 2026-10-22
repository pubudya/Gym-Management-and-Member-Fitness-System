<?php
declare(strict_types=1);

final class NotificationService
{
    public static function template(string $code): ?array
    {
        return Database::fetch(
            "SELECT * FROM notification_templates WHERE template_code = ? AND status = 'ACTIVE'",
            [$code]
        );
    }

    public static function render(string $code, array $vars = [], string $fallback = ''): string
    {
        $tpl = self::template($code);
        $text = $tpl['message_template'] ?? $fallback;
        foreach ($vars as $k => $v) {
            $text = str_replace('{{' . $k . '}}', (string) $v, $text);
        }
        return $text;
    }

    public static function alreadySent(string $type, ?int $memberId, ?int $adminId, int $hours = 20): bool
    {
        $sql = 'SELECT notification_id FROM notifications WHERE notification_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)';
        $params = [$type, $hours];
        if ($memberId) {
            $sql .= ' AND member_id = ?';
            $params[] = $memberId;
        }
        if ($adminId) {
            $sql .= ' AND admin_id = ?';
            $params[] = $adminId;
        }
        $sql .= ' LIMIT 1';
        return Database::fetch($sql, $params) !== null;
    }

    public static function notifyMember(?array $member, string $type, string $message, array $opts = []): ?int
    {
        if (!$member || empty($member['phone'])) {
            return null;
        }
        return self::dispatch([
            'member_id' => $member['member_id'] ?? null,
            'admin_id' => null,
            'recipient_type' => 'MEMBER',
            'recipient_name' => $member['full_name'] ?? '',
            'phone_number' => $member['phone'],
            'notification_type' => $type,
            'title' => $opts['title'] ?? $type,
            'message' => $message,
            'channel' => $opts['channel'] ?? 'SMS',
        ]);
    }

    public static function notifyAdmins(string $type, string $message, array $opts = []): void
    {
        $admins = Database::fetchAll(
            "SELECT a.*, u.status FROM admins a JOIN users u ON u.user_id = a.user_id WHERE u.status = 'ACTIVE'"
        );
        foreach ($admins as $admin) {
            if (!empty($opts['dedupe']) && self::alreadySent($type, null, (int) $admin['admin_id'], (int) ($opts['hours'] ?? 12))) {
                continue;
            }
            self::dispatch([
                'member_id' => null,
                'admin_id' => $admin['admin_id'],
                'recipient_type' => 'ADMIN',
                'recipient_name' => $admin['full_name'],
                'phone_number' => $admin['phone'],
                'notification_type' => $type,
                'title' => $opts['title'] ?? $type,
                'message' => $message,
                'channel' => $opts['channel'] ?? 'SMS',
            ]);
        }
    }

    public static function dispatch(array $row): int
    {
        $id = Database::insert('notifications', [
            'member_id' => $row['member_id'],
            'admin_id' => $row['admin_id'],
            'recipient_type' => $row['recipient_type'],
            'recipient_name' => $row['recipient_name'],
            'phone_number' => $row['phone_number'],
            'notification_type' => $row['notification_type'],
            'title' => $row['title'] ?? null,
            'message' => $row['message'],
            'channel' => $row['channel'] ?? 'SMS',
            'delivery_status' => 'PENDING',
            'scheduled_at' => date('Y-m-d H:i:s'),
        ]);

        if (($row['channel'] ?? 'SMS') === 'SMS') {
            $sms = SmsService::send($row['phone_number'], $row['message'], $row);
            Database::update('notifications', [
                'delivery_status' => $sms['status'],
                'provider_name' => $sms['provider'],
                'provider_message_id' => $sms['provider_message_id'],
                'error_message' => $sms['error'],
                'sent_at' => $sms['status'] === 'SENT' ? date('Y-m-d H:i:s') : null,
            ], 'notification_id = ?', [$id]);
        } else {
            Database::update('notifications', [
                'delivery_status' => 'SENT',
                'sent_at' => date('Y-m-d H:i:s'),
            ], 'notification_id = ?', [$id]);
        }

        return $id;
    }

    public static function announce(string $title, string $message, bool $sms = true): int
    {
        $members = Database::fetchAll("SELECT * FROM members WHERE status = 'ACTIVE'");
        $count = 0;
        foreach ($members as $member) {
            self::notifyMember($member, 'ANNOUNCEMENT', $message, [
                'title' => $title,
                'channel' => $sms ? 'SMS' : 'SYSTEM',
            ]);
            $count++;
        }
        audit('ANNOUNCEMENT', 'notifications', $title . ' sent to ' . $count . ' members');
        return $count;
    }
}

<?php
declare(strict_types=1);

final class RulesEngine
{
    public static function run(): array
    {
        $out = [
            'expired' => self::expireMemberships(),
            'expiry_reminders' => self::expiryReminders(),
            'payment_due' => self::paymentDue(),
            'inactive' => self::inactiveMembers(),
            'low_stock' => self::lowStock(),
            'maintenance' => self::maintenanceDue(),
            'capacity' => self::capacityWatch(),
            'subscriptions' => class_exists(SubscriptionService::class) ? SubscriptionService::refreshStatuses() : 0,
        ];
        audit('RULES_RUN', 'automation', json_encode($out));
        return $out;
    }

    public static function expireMemberships(): int
    {
        $rows = Database::fetchAll(
            "SELECT ms.*, m.full_name, m.phone, m.member_id
             FROM memberships ms
             JOIN members m ON m.member_id = ms.member_id
             WHERE ms.status = 'ACTIVE' AND ms.end_date < CURDATE()"
        );
        foreach ($rows as $row) {
            Database::update('memberships', ['status' => 'EXPIRED'], 'membership_id = ?', [$row['membership_id']]);
            NotificationService::notifyMember($row, 'MEMBERSHIP_EXPIRED', NotificationService::render(
                'MEMBERSHIP_EXPIRED',
                ['name' => $row['full_name']],
                'Hi ' . $row['full_name'] . ', your gym membership has expired. Please renew to continue access.'
            ));
        }
        return count($rows);
    }

    public static function expiryReminders(): int
    {
        $days = (int) setting('membership_expiry_reminder_days', 7);
        $rows = Database::fetchAll(
            "SELECT ms.*, m.full_name, m.phone, m.member_id
             FROM memberships ms
             JOIN members m ON m.member_id = ms.member_id
             WHERE ms.status = 'ACTIVE'
               AND ms.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)",
            [$days]
        );
        $n = 0;
        foreach ($rows as $row) {
            if (NotificationService::alreadySent('MEMBERSHIP_EXPIRY', (int) $row['member_id'], null, 20)) {
                continue;
            }
            NotificationService::notifyMember($row, 'MEMBERSHIP_EXPIRY', NotificationService::render(
                'MEMBERSHIP_EXPIRY',
                ['name' => $row['full_name'], 'end_date' => $row['end_date']],
                'Hi ' . $row['full_name'] . ', your membership expires on ' . $row['end_date'] . '. Please renew soon.'
            ));
            $n++;
        }
        return $n;
    }

    public static function paymentDue(): int
    {
        $rows = Database::fetchAll(
            "SELECT ms.*, m.full_name, m.phone, m.member_id
             FROM memberships ms
             JOIN members m ON m.member_id = ms.member_id
             WHERE ms.balance > 0 AND ms.status IN ('ACTIVE','PENDING')"
        );
        $n = 0;
        foreach ($rows as $row) {
            $type = (strtotime((string) $row['end_date']) < time()) ? 'PAYMENT_OVERDUE' : 'PAYMENT_DUE';
            if (NotificationService::alreadySent($type, (int) $row['member_id'], null, 20)) {
                continue;
            }
            $msg = NotificationService::render(
                $type === 'PAYMENT_OVERDUE' ? 'PAYMENT_OVERDUE' : 'PAYMENT_DUE',
                ['name' => $row['full_name'], 'balance' => $row['balance']],
                'Hi ' . $row['full_name'] . ', you have an outstanding gym balance of ' . money($row['balance']) . '.'
            );
            NotificationService::notifyMember($row, $type, $msg);
            if ($type === 'PAYMENT_OVERDUE') {
                NotificationService::notifyAdmins(
                    'ADMIN_PAYMENT_OVERDUE',
                    'Overdue payment: ' . $row['full_name'] . ' owes ' . money($row['balance']) . '.',
                    ['dedupe' => true]
                );
            }
            $n++;
        }
        return $n;
    }

    public static function inactiveMembers(): int
    {
        $days = (int) setting('inactive_member_days', 14);
        $rows = Database::fetchAll(
            "SELECT m.*, MAX(a.check_in) AS last_visit
             FROM members m
             JOIN memberships ms ON ms.member_id = m.member_id AND ms.status = 'ACTIVE'
             LEFT JOIN attendance a ON a.member_id = m.member_id
             WHERE m.status = 'ACTIVE'
             GROUP BY m.member_id
             HAVING last_visit IS NULL OR last_visit < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        $n = 0;
        foreach ($rows as $row) {
            if (NotificationService::alreadySent('INACTIVE_MEMBER', (int) $row['member_id'], null, 48)) {
                continue;
            }
            NotificationService::notifyAdmins(
                'INACTIVE_MEMBER',
                'Inactive member alert: ' . $row['full_name'] . ' has not attended for ' . $days . '+ days.',
                ['dedupe' => true, 'hours' => 48]
            );
            $n++;
        }
        return $n;
    }

    public static function lowStock(): int
    {
        $rows = Database::fetchAll(
            "SELECT * FROM inventory WHERE quantity <= minimum_stock AND status <> 'INACTIVE'"
        );
        $n = 0;
        foreach ($rows as $row) {
            $status = ((int) $row['quantity'] === 0) ? 'OUT_OF_STOCK' : 'LOW_STOCK';
            Database::update('inventory', ['status' => $status], 'inventory_id = ?', [$row['inventory_id']]);
            if (NotificationService::alreadySent('LOW_STOCK', null, null, 12) && $n === 0) {
                continue;
            }
            NotificationService::notifyAdmins(
                'LOW_STOCK',
                NotificationService::render(
                    'LOW_STOCK',
                    ['item' => $row['item_name'], 'qty' => $row['quantity']],
                    'Low stock: ' . $row['item_name'] . ' is at ' . $row['quantity'] . ' (min ' . $row['minimum_stock'] . ').'
                ),
                ['dedupe' => true, 'hours' => 12]
            );
            $n++;
        }
        return $n;
    }

    public static function maintenanceDue(): int
    {
        $rows = Database::fetchAll(
            "SELECT em.*, e.equipment_name
             FROM equipment_maintenance em
             JOIN equipment e ON e.equipment_id = em.equipment_id
             WHERE em.status IN ('SCHEDULED','IN_PROGRESS')
               AND (em.maintenance_date <= CURDATE() OR (em.next_maintenance_date IS NOT NULL AND em.next_maintenance_date <= CURDATE()))"
        );
        $n = 0;
        foreach ($rows as $row) {
            if (NotificationService::alreadySent('MAINTENANCE_DUE', null, null, 24) && $n === 0) {
                continue;
            }
            NotificationService::notifyAdmins(
                'MAINTENANCE_DUE',
                NotificationService::render(
                    'MAINTENANCE_DUE',
                    ['equipment' => $row['equipment_name']],
                    'Equipment maintenance due: ' . $row['equipment_name'] . '.'
                ),
                ['dedupe' => true, 'hours' => 24]
            );
            $n++;
        }
        return $n;
    }

    public static function capacityWatch(): int
    {
        $cap = (int) setting('gym_capacity', 80);
        $now = occupancy_count();
        if ($cap > 0 && $now >= $cap) {
            NotificationService::notifyAdmins(
                'GYM_CAPACITY',
                'Gym is at capacity: ' . $now . '/' . $cap . ' members currently inside.',
                ['dedupe' => true, 'hours' => 2]
            );
            return 1;
        }
        return 0;
    }
}

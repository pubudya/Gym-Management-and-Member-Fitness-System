<?php
declare(strict_types=1);

final class AttendanceService
{
    public static function openVisit(int $memberId): ?array
    {
        return Database::fetch(
            'SELECT * FROM attendance WHERE member_id = ? AND check_out IS NULL ORDER BY check_in DESC LIMIT 1',
            [$memberId]
        );
    }

    public static function canEnter(array $member): array
    {
        if ($member['status'] !== 'ACTIVE') {
            return ['ok' => false, 'error' => 'Member account is not active.'];
        }
        $ms = Database::fetch(
            "SELECT * FROM memberships WHERE member_id = ? AND status = 'ACTIVE' AND start_date <= CURDATE() AND end_date >= CURDATE() ORDER BY end_date DESC LIMIT 1",
            [$member['member_id']]
        );
        if (!$ms) {
            return ['ok' => false, 'error' => 'No active membership. Renew before checking in.'];
        }
        $cap = (int) setting('gym_capacity', 80);
        $open = occupancy_count();
        if ($cap > 0 && $open >= $cap && !self::openVisit((int) $member['member_id'])) {
            return ['ok' => false, 'error' => 'Gym is currently at full capacity (' . $open . '/' . $cap . ').'];
        }
        return ['ok' => true, 'membership' => $ms];
    }

    public static function checkIn(array $member, string $method = 'QR', ?int $byUserId = null): array
    {
        $gate = self::canEnter($member);
        if (!$gate['ok']) {
            return $gate;
        }
        if (self::openVisit((int) $member['member_id'])) {
            return ['ok' => false, 'error' => 'Member is already checked in. Check out first.'];
        }

        $id = Database::insert('attendance', [
            'member_id' => $member['member_id'],
            'attendance_date' => date('Y-m-d'),
            'check_in' => date('Y-m-d H:i:s'),
            'attendance_method' => $method,
            'checked_in_by' => $byUserId,
        ]);

        NotificationService::notifyMember(
            $member,
            'CHECK_IN',
            NotificationService::render('CHECK_IN', ['name' => $member['full_name']], 'Hi ' . $member['full_name'] . ', you have successfully checked in to the gym.')
        );
        audit('CHECK_IN', 'attendance', $member['full_name'] . ' checked in via ' . $method, $id);
        RulesEngine::capacityWatch();

        return ['ok' => true, 'attendance_id' => $id, 'action' => 'check_in'];
    }

    public static function checkOut(array $member): array
    {
        $open = self::openVisit((int) $member['member_id']);
        if (!$open) {
            return ['ok' => false, 'error' => 'No open check-in found.'];
        }
        $minutes = max(0, (int) round((time() - strtotime($open['check_in'])) / 60));
        Database::update('attendance', [
            'check_out' => date('Y-m-d H:i:s'),
            'duration_minutes' => $minutes,
        ], 'attendance_id = ?', [$open['attendance_id']]);

        NotificationService::notifyMember(
            $member,
            'CHECK_OUT',
            NotificationService::render('CHECK_OUT', ['name' => $member['full_name']], 'Hi ' . $member['full_name'] . ', you have successfully checked out from the gym.')
        );
        audit('CHECK_OUT', 'attendance', $member['full_name'] . ' checked out', $open['attendance_id']);

        return ['ok' => true, 'attendance_id' => $open['attendance_id'], 'action' => 'check_out', 'duration_minutes' => $minutes];
    }

    public static function toggle(array $member, string $method = 'QR', ?int $byUserId = null): array
    {
        if (self::openVisit((int) $member['member_id'])) {
            return self::checkOut($member);
        }
        return self::checkIn($member, $method, $byUserId);
    }
}

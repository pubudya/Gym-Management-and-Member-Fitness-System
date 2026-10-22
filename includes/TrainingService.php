<?php
declare(strict_types=1);

final class TrainingService
{
    public static function styles(): array
    {
        return ['STRENGTH', 'CARDIO', 'HIIT', 'PILATES', 'YOGA', 'FLEXIBILITY', 'FUNCTIONAL'];
    }

    public static function slots(): array
    {
        return ['BREAKFAST', 'LUNCH', 'DINNER', 'SNACK'];
    }

    public static function goals(): array
    {
        return ['WEIGHT_LOSS', 'MUSCLE_GAIN', 'STRENGTH', 'ENDURANCE', 'FLEXIBILITY', 'GENERAL_FITNESS'];
    }

    public static function days(): array
    {
        return ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
    }

    public static function defaultWorkoutImage(string $style): string
    {
        $map = [
            'STRENGTH' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=80',
            'CARDIO' => 'https://images.unsplash.com/photo-1476480862126-209bfaa8edc8?auto=format&fit=crop&w=900&q=80',
            'HIIT' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=80',
            'PILATES' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=900&q=80',
            'YOGA' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=900&q=80',
            'FLEXIBILITY' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=900&q=80',
            'FUNCTIONAL' => 'https://images.unsplash.com/photo-1517963879433-6ad2b056d712?auto=format&fit=crop&w=900&q=80',
        ];
        return $map[strtoupper($style)] ?? $map['STRENGTH'];
    }

    public static function defaultMealImage(string $slot): string
    {
        $map = [
            'BREAKFAST' => 'https://images.unsplash.com/photo-1525351484163-7529414344d8?auto=format&fit=crop&w=900&q=80',
            'LUNCH' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=900&q=80',
            'DINNER' => 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?auto=format&fit=crop&w=900&q=80',
            'SNACK' => 'https://images.unsplash.com/photo-1490474418585-ba9bad8fd0ea?auto=format&fit=crop&w=900&q=80',
        ];
        return $map[strtoupper($slot)] ?? $map['LUNCH'];
    }

    public static function mediaUrl(?string $path, string $fallback): string
    {
        if ($path) {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return url('uploads/' . ltrim($path, '/'));
        }
        return $fallback;
    }

    public static function prescription(array $ex): string
    {
        $type = $ex['tracking_type'] ?? 'SETS_REPS';
        if ($type === 'DURATION' || $type === 'HOLD') {
            $sec = (int) ($ex['duration_seconds'] ?? 0);
            if ($sec >= 60) {
                $m = intdiv($sec, 60);
                $s = $sec % 60;
                $label = $s ? ($m . ' min ' . $s . ' sec') : ($m . ' min');
            } else {
                $label = $sec . ' sec';
            }
            return $type === 'HOLD' ? 'Hold ' . $label : $label;
        }
        $sets = (int) ($ex['sets'] ?? 0);
        $reps = (int) ($ex['repetitions'] ?? 0);
        if ($sets && $reps) {
            return $reps . ' × ' . $sets;
        }
        if ($sets) {
            return $sets . ' sets';
        }
        if ($reps) {
            return $reps . ' reps';
        }
        return 'Custom';
    }

    public static function assignedMemberIds(): array
    {
        $coach = current_coach();
        if (!$coach) {
            return [];
        }
        $rows = Database::fetchAll(
            "SELECT member_id FROM trainer_members WHERE trainer_id = ? AND status = 'ACTIVE'",
            [$coach['trainer_id']]
        );
        return array_map(static fn($r) => (int) $r['member_id'], $rows);
    }

    public static function canManageMember(int $memberId): bool
    {
        $role = current_user()['role'] ?? '';
        if ($role === 'ADMIN') {
            return true;
        }
        return in_array($memberId, self::assignedMemberIds(), true);
    }

    public static function membersForSelect(): array
    {
        if ((current_user()['role'] ?? '') === 'ADMIN') {
            return Database::fetchAll("SELECT member_id, full_name, member_code FROM members WHERE status='ACTIVE' ORDER BY full_name");
        }
        $ids = self::assignedMemberIds();
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        return Database::fetchAll("SELECT member_id, full_name, member_code FROM members WHERE member_id IN ($in) ORDER BY full_name", $ids);
    }

    public static function assertMember(int $memberId): void
    {
        if (!self::canManageMember($memberId)) {
            flash('error', 'You can only work with members assigned to you.');
            $home = (current_user()['role'] ?? '') === 'COACH' ? 'coach/dashboard.php' : 'admin/dashboard.php';
            redirect($home);
        }
    }

    public static function trainerIdForSave(): ?int
    {
        if ((current_user()['role'] ?? '') === 'COACH') {
            return (int) (current_coach()['trainer_id'] ?? 0) ?: null;
        }
        $posted = (int) post('trainer_id');
        return $posted ?: null;
    }
}

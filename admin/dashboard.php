<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

$stats = [
    'total' => Database::fetch('SELECT COUNT(*) c FROM members')['c'] ?? 0,
    'active' => Database::fetch("SELECT COUNT(*) c FROM members WHERE status='ACTIVE'")['c'] ?? 0,
    'expired' => Database::fetch("SELECT COUNT(DISTINCT member_id) c FROM memberships WHERE status='EXPIRED'")['c'] ?? 0,
    'today_reg' => Database::fetch('SELECT COUNT(*) c FROM members WHERE registration_date = CURDATE()')['c'] ?? 0,
    'today_in' => Database::fetch('SELECT COUNT(*) c FROM attendance WHERE attendance_date = CURDATE()')['c'] ?? 0,
    'occupancy' => occupancy_count(),
    'capacity' => (int) setting('gym_capacity', 80),
    'revenue' => Database::fetch("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE payment_status='SUCCESS' AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")['s'] ?? 0,
    'pending' => Database::fetch("SELECT COALESCE(SUM(balance),0) s FROM memberships WHERE status IN ('ACTIVE','PENDING') AND balance>0")['s'] ?? 0,
    'expiring' => Database::fetch("SELECT COUNT(*) c FROM memberships WHERE status='ACTIVE' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")['c'] ?? 0,
    'maint' => Database::fetch("SELECT COUNT(*) c FROM equipment_maintenance WHERE status IN ('SCHEDULED','IN_PROGRESS')")['c'] ?? 0,
    'low' => Database::fetch("SELECT COUNT(*) c FROM inventory WHERE quantity <= minimum_stock")['c'] ?? 0,
];

$upcoming = Database::fetchAll(
    "SELECT cs.*, gc.class_name, t.full_name AS trainer_name,
            (SELECT COUNT(*) FROM class_bookings b WHERE b.schedule_id = cs.schedule_id AND b.status IN ('BOOKED','ATTENDED','WAITING')) AS booked
     FROM class_schedules cs
     JOIN gym_classes gc ON gc.class_id = cs.class_id
     JOIN trainers t ON t.trainer_id = cs.trainer_id
     WHERE cs.class_date >= CURDATE() AND cs.status='SCHEDULED'
     ORDER BY cs.class_date, cs.start_time LIMIT 6"
);
$inactive = Database::fetchAll(
    "SELECT m.full_name, m.member_code, m.phone, MAX(a.check_in) last_visit
     FROM members m
     LEFT JOIN attendance a ON a.member_id = m.member_id
     WHERE m.status='ACTIVE'
     GROUP BY m.member_id
     HAVING last_visit IS NULL OR last_visit < DATE_SUB(NOW(), INTERVAL 14 DAY)
     ORDER BY last_visit ASC LIMIT 6"
);
$hours = Database::fetchAll(
    "SELECT HOUR(check_in) h, COUNT(*) c FROM attendance
     WHERE check_in >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY HOUR(check_in) ORDER BY h"
);
$hourLabels = [];
$hourData = [];
for ($i = 5; $i <= 22; $i++) {
    $hourLabels[] = sprintf('%02d:00', $i);
    $found = 0;
    foreach ($hours as $h) {
        if ((int) $h['h'] === $i) {
            $found = (int) $h['c'];
        }
    }
    $hourData[] = $found;
}

$pageTitle = 'Dashboard';
$pageSubtitle = 'Live gym operations snapshot';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid stats">
    <div class="stat volt"><span>Total members</span><b><?= (int) $stats['total'] ?></b></div>
    <div class="stat"><span>Active members</span><b><?= (int) $stats['active'] ?></b></div>
    <div class="stat ember"><span>Expired memberships</span><b><?= (int) $stats['expired'] ?></b></div>
    <div class="stat ice"><span>Today's registrations</span><b><?= (int) $stats['today_reg'] ?></b></div>
    <div class="stat"><span>Today's check-ins</span><b><?= (int) $stats['today_in'] ?></b></div>
    <div class="stat volt"><span>Occupancy</span><b><?= (int) $stats['occupancy'] ?>/<?= (int) $stats['capacity'] ?></b></div>
    <div class="stat"><span>Monthly revenue</span><b><?= e(money($stats['revenue'])) ?></b></div>
    <div class="stat ember"><span>Pending payments</span><b><?= e(money($stats['pending'])) ?></b></div>
    <div class="stat warn"><span>Expiring in 7 days</span><b><?= (int) $stats['expiring'] ?></b></div>
    <div class="stat"><span>Maintenance jobs</span><b><?= (int) $stats['maint'] ?></b></div>
    <div class="stat ember"><span>Low-stock items</span><b><?= (int) $stats['low'] ?></b></div>
    <div class="stat ice"><span>Inactive members</span><b><?= count($inactive) ?>+</b></div>
</div>

<div class="grid cards-2" style="margin-top:16px">
    <div class="card">
        <div class="card-h"><h2>Peak hours (14 days)</h2><a class="btn small ghost" href="<?= e(url('cron/automate.php')) ?>">Run automation</a></div>
        <canvas id="peakChart" height="120"></canvas>
    </div>
    <div class="card">
        <div class="card-h"><h2>Upcoming classes</h2><a class="btn small ghost" href="<?= e(url('admin/classes.php')) ?>">Manage</a></div>
        <div class="table-wrap">
            <table>
                <tr><th>Class</th><th>When</th><th>Booked</th></tr>
                <?php foreach ($upcoming as $c): ?>
                    <tr>
                        <td><?= e($c['class_name']) ?><br><small class="muted"><?= e($c['trainer_name']) ?></small></td>
                        <td><?= e($c['class_date'] . ' ' . substr($c['start_time'], 0, 5)) ?></td>
                        <td><?= (int) $c['booked'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$upcoming): ?><tr><td colspan="3">No upcoming classes.</td></tr><?php endif; ?>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-h"><h2>Inactive / low-engagement members</h2><a class="btn small ghost" href="<?= e(url('admin/members.php?status=ACTIVE')) ?>">View members</a></div>
    <div class="table-wrap">
        <table>
            <tr><th>Member</th><th>Code</th><th>Phone</th><th>Last visit</th></tr>
            <?php foreach ($inactive as $m): ?>
                <tr>
                    <td><?= e($m['full_name']) ?></td>
                    <td><?= e($m['member_code']) ?></td>
                    <td><?= e($m['phone']) ?></td>
                    <td><?= e($m['last_visit'] ?: 'Never') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$inactive): ?><tr><td colspan="4">No inactive members in this window.</td></tr><?php endif; ?>
        </table>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  voltCharts('peakChart', 'bar', <?= json_encode($hourLabels) ?>, <?= json_encode($hourData) ?>, {label:'Check-ins'});
});
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

$message = '';
if (is_post()) {
    $raw = trim((string) post('code'));
    $member = parse_member_qr($raw);
    if (!$member) {
        $message = 'Member not found.';
    } else {
        $res = AttendanceService::toggle($member, (string) post('method') ?: 'ADMIN', current_user()['user_id']);
        $message = $res['ok'] ? ucfirst(str_replace('_', ' ', $res['action'])) . ' recorded for ' . $member['full_name'] . '.' : $res['error'];
        flash($res['ok'] ? 'success' : 'error', $message);
        redirect('admin/attendance.php');
    }
}

$inside = Database::fetchAll(
    "SELECT a.*, m.full_name, m.member_code FROM attendance a JOIN members m ON m.member_id=a.member_id
     WHERE a.attendance_date=CURDATE() AND a.check_out IS NULL ORDER BY a.check_in DESC"
);
$history = Database::fetchAll(
    "SELECT a.*, m.full_name, m.member_code FROM attendance a JOIN members m ON m.member_id=a.member_id
     ORDER BY a.check_in DESC LIMIT 80"
);
$pageTitle = 'Attendance';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <div class="card kiosk">
        <h2>QR / member-code desk</h2>
        <p class="muted">Scan the member QR or type GYM-0001. If they are already in, this checks them out.</p>
        <?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input name="code" required placeholder="Scan or enter member code" autofocus>
            <select name="method"><option>QR</option><option>MEMBER_CODE</option><option>ADMIN</option></select>
            <button class="btn full" type="submit">Check in / out</button>
        </form>
        <p class="muted">Inside now: <b><?= occupancy_count() ?></b> / <?= e(setting('gym_capacity', '80')) ?></p>
    </div>
    <div class="card">
        <h2>Currently in the gym</h2>
        <table>
            <tr><th>Member</th><th>In at</th><th>Method</th></tr>
            <?php foreach ($inside as $r): ?>
                <tr><td><?= e($r['full_name']) ?> (<?= e($r['member_code']) ?>)</td><td><?= e($r['check_in']) ?></td><td><?= e($r['attendance_method']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$inside): ?><tr><td colspan="3">Nobody is checked in.</td></tr><?php endif; ?>
        </table>
    </div>
</div>
<div class="card" style="margin-top:16px">
    <h2>Attendance history</h2>
    <table>
        <tr><th>Date</th><th>Member</th><th>In</th><th>Out</th><th>Minutes</th><th>Method</th></tr>
        <?php foreach ($history as $r): ?>
            <tr>
                <td><?= e($r['attendance_date']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['check_in']) ?></td>
                <td><?= e($r['check_out'] ?: '—') ?></td>
                <td><?= e((string) ($r['duration_minutes'] ?? '—')) ?></td>
                <td><?= e($r['attendance_method']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

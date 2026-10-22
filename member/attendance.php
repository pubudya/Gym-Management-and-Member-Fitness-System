<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();

if (is_post() && post('form') === 'toggle') {
    $res = AttendanceService::toggle($m, 'QR', current_user()['user_id']);
    flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Attendance updated.' : $res['error']);
    redirect('member/attendance.php');
}

$rows = Database::fetchAll('SELECT * FROM attendance WHERE member_id=? ORDER BY check_in DESC LIMIT 60', [$m['member_id']]);
$open = AttendanceService::openVisit((int) $m['member_id']);
$pageTitle = 'My attendance';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <div class="card-h">
        <h2><?= $open ? 'Checked in at '.$open['check_in'] : 'Not checked in' ?></h2>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="toggle"><button class="btn"><?= $open ? 'Check out' : 'Check in with member QR' ?></button></form>
    </div>
    <p class="muted">Your code <?= e($m['member_code']) ?> also works at the admin desk scanner.</p>
    <div class="qr-box" data-qr="<?= e(member_qr_payload($m)) ?>"></div>
    <table>
        <tr><th>Date</th><th>In</th><th>Out</th><th>Minutes</th><th>Method</th></tr>
        <?php foreach ($rows as $r): ?>
            <tr><td><?= e($r['attendance_date']) ?></td><td><?= e($r['check_in']) ?></td><td><?= e($r['check_out'] ?: '—') ?></td><td><?= e((string)($r['duration_minutes'] ?? '—')) ?></td><td><?= e($r['attendance_method']) ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

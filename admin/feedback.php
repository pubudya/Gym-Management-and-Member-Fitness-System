<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post()) {
    $table = post('table') === 'complaints' ? 'complaints' : 'feedback';
    $idCol = $table === 'complaints' ? 'complaint_id' : 'feedback_id';
    Database::update($table, [
        'status' => post('status'),
        'admin_response' => post('admin_response'),
        'resolved_at' => in_array(post('status'), ['RESOLVED','CLOSED'], true) ? date('Y-m-d H:i:s') : null,
    ], "$idCol=?", [(int) post('id')]);
    flash('success', 'Response saved.');
    redirect('admin/feedback.php');
}

$feedback = Database::fetchAll("SELECT f.*, m.full_name FROM feedback f JOIN members m ON m.member_id=f.member_id ORDER BY created_at DESC");
$complaints = Database::fetchAll("SELECT c.*, m.full_name FROM complaints c JOIN members m ON m.member_id=c.member_id ORDER BY created_at DESC");
$pageTitle = 'Feedback & complaints';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <div class="card">
        <h2>Feedback</h2>
        <?php foreach ($feedback as $f): ?>
            <form method="post" class="card" style="box-shadow:none">
                <?= csrf_field() ?><input type="hidden" name="table" value="feedback"><input type="hidden" name="id" value="<?= (int)$f['feedback_id'] ?>">
                <b><?= e($f['full_name']) ?></b> · <?= (int)($f['rating']??0) ?>/5
                <p><?= e($f['subject']) ?><br><?= e($f['message']) ?></p>
                <textarea name="admin_response"><?= e($f['admin_response']??'') ?></textarea>
                <select name="status"><?php foreach (['NEW','IN_REVIEW','RESOLVED','CLOSED'] as $s): ?><option <?= selected($f['status'],$s) ?>><?= $s ?></option><?php endforeach; ?></select>
                <button class="btn small">Update</button>
            </form>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h2>Complaints</h2>
        <?php foreach ($complaints as $c): ?>
            <form method="post" class="card" style="box-shadow:none">
                <?= csrf_field() ?><input type="hidden" name="table" value="complaints"><input type="hidden" name="id" value="<?= (int)$c['complaint_id'] ?>">
                <b><?= e($c['full_name']) ?></b> · <span class="badge <?= badge_class($c['priority']) ?>"><?= e($c['priority']) ?></span>
                <p><?= e($c['subject']) ?><br><?= e($c['description']) ?></p>
                <textarea name="admin_response"><?= e($c['admin_response']??'') ?></textarea>
                <select name="status"><?php foreach (['NEW','IN_PROGRESS','RESOLVED','CLOSED'] as $s): ?><option <?= selected($c['status'],$s) ?>><?= $s ?></option><?php endforeach; ?></select>
                <button class="btn small ember">Update</button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

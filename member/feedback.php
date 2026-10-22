<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();

if (is_post() && post('form') === 'feedback') {
    Database::insert('feedback', [
        'member_id' => $m['member_id'],
        'rating' => (int) post('rating') ?: null,
        'subject' => post('subject'),
        'message' => post('message'),
        'status' => 'NEW',
    ]);
    flash('success', 'Feedback sent.');
    redirect('member/feedback.php');
}
if (is_post() && post('form') === 'complaint') {
    Database::insert('complaints', [
        'member_id' => $m['member_id'],
        'subject' => post('subject'),
        'description' => post('description'),
        'priority' => post('priority') ?: 'MEDIUM',
        'status' => 'NEW',
    ]);
    flash('success', 'Complaint submitted.');
    redirect('member/feedback.php');
}

$feedback = Database::fetchAll('SELECT * FROM feedback WHERE member_id=? ORDER BY created_at DESC', [$m['member_id']]);
$complaints = Database::fetchAll('SELECT * FROM complaints WHERE member_id=? ORDER BY created_at DESC', [$m['member_id']]);
$pageTitle = 'Feedback';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <form class="card" method="post">
        <h2>Send feedback</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="feedback">
        <select name="rating"><option value="5">5</option><option>4</option><option>3</option><option>2</option><option>1</option></select>
        <input name="subject" placeholder="Subject">
        <textarea name="message" required></textarea>
        <button class="btn">Submit</button>
    </form>
    <form class="card" method="post">
        <h2>Complaint</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="complaint">
        <input name="subject" required placeholder="Subject">
        <select name="priority"><option>LOW</option><option selected>MEDIUM</option><option>HIGH</option><option>URGENT</option></select>
        <textarea name="description" required></textarea>
        <button class="btn ember">Submit complaint</button>
    </form>
</div>
<div class="card" style="margin-top:16px">
    <h2>Your tickets</h2>
    <table>
        <?php foreach ($feedback as $f): ?><tr><td>Feedback</td><td><?= e($f['subject']) ?></td><td><?= e($f['status']) ?></td><td><?= e($f['admin_response'] ?: 'Waiting for admin') ?></td></tr><?php endforeach; ?>
        <?php foreach ($complaints as $c): ?><tr><td>Complaint</td><td><?= e($c['subject']) ?></td><td><?= e($c['status']) ?></td><td><?= e($c['admin_response'] ?: 'Waiting for admin') ?></td></tr><?php endforeach; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

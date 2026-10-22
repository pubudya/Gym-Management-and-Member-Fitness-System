<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post() && post('form') === 'trainer') {
    $id = (int) post('trainer_id');
    $payload = [
        'full_name' => trim((string) post('full_name')),
        'phone' => trim((string) post('phone')),
        'email' => trim((string) post('email')),
        'specialization' => trim((string) post('specialization')),
        'experience_years' => (float) post('experience_years'),
        'certification' => trim((string) post('certification')),
        'status' => post('status') ?: 'ACTIVE',
    ];
    try {
        $img = upload_image('profile_image', 'trainers');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('admin/trainers.php');
    }
    if ($img) {
        $payload['profile_image'] = $img;
    }

    $username = trim((string) post('username'));
    $password = (string) post('password');
    $email = $payload['email'] ?: ($username . '@voltgym.local');

    try {
        if ($id) {
            $existing = Database::fetch('SELECT * FROM trainers WHERE trainer_id=?', [$id]);
            if ($existing['user_id']) {
                Database::update('users', [
                    'email' => $email,
                    'status' => $payload['status'] === 'ACTIVE' ? 'ACTIVE' : 'INACTIVE',
                ], 'user_id=?', [$existing['user_id']]);
                if ($username !== '') {
                    Database::update('users', ['username' => $username], 'user_id=?', [$existing['user_id']]);
                }
                if ($password !== '') {
                    Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'user_id=?', [$existing['user_id']]);
                }
            } elseif ($username !== '' && $password !== '') {
                $uid = Database::insert('users', [
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'COACH',
                    'status' => $payload['status'] === 'ACTIVE' ? 'ACTIVE' : 'INACTIVE',
                ]);
                $payload['user_id'] = $uid;
            }
            Database::update('trainers', $payload, 'trainer_id=?', [$id]);
            audit('UPDATE', 'coaches', $payload['full_name'], $id);
        } else {
            if ($username === '' || $password === '') {
                throw new RuntimeException('New coaches need a username and password so they can log in.');
            }
            $uid = Database::insert('users', [
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'COACH',
                'status' => 'ACTIVE',
            ]);
            $payload['user_id'] = $uid;
            $id = Database::insert('trainers', $payload);
            audit('CREATE', 'coaches', $payload['full_name'], $id);
        }
        flash('success', 'Coach saved. They sign in with their username — they cannot access admin menus.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/trainers.php');
}

if (is_post() && post('form') === 'assign') {
    Database::insert('trainer_members', [
        'trainer_id' => (int) post('trainer_id'),
        'member_id' => (int) post('member_id'),
        'assigned_date' => date('Y-m-d'),
        'status' => 'ACTIVE',
        'notes' => post('notes'),
    ]);
    audit('ASSIGN', 'coaches', 'Assigned coach to member');
    flash('success', 'Member assigned to this coach.');
    redirect('admin/trainers.php');
}

if (is_post() && post('form') === 'end') {
    Database::update('trainer_members', ['status' => 'ENDED', 'end_date' => date('Y-m-d')], 'assignment_id=?', [(int) post('assignment_id')]);
    flash('success', 'Assignment ended.');
    redirect('admin/trainers.php');
}

$edit = query_param('id') ? Database::fetch(
    'SELECT t.*, u.username FROM trainers t LEFT JOIN users u ON u.user_id=t.user_id WHERE t.trainer_id=?',
    [(int) query_param('id')]
) : null;
$trainers = Database::fetchAll('SELECT t.*, u.username FROM trainers t LEFT JOIN users u ON u.user_id=t.user_id ORDER BY t.full_name');
$members = Database::fetchAll("SELECT member_id, full_name FROM members WHERE status='ACTIVE' ORDER BY full_name");
$assignments = Database::fetchAll(
    "SELECT tm.*, t.full_name trainer_name, m.full_name member_name
     FROM trainer_members tm JOIN trainers t ON t.trainer_id=tm.trainer_id JOIN members m ON m.member_id=tm.member_id
     ORDER BY tm.assigned_date DESC"
);
$pageTitle = 'Personal coaches';
$pageSubtitle = 'Register coaches, give them login, and assign members';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="callout">Coaches only log in to their own workspace. They can create workouts and meal plans for <b>assigned members</b>. You can create plans for <b>all members</b>.</div>
<div class="grid split">
    <form class="card" method="post" enctype="multipart/form-data" data-validate>
        <h2><?= $edit ? 'Edit coach' : 'Register coach' ?></h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="trainer"><input type="hidden" name="trainer_id" value="<?= (int) ($edit['trainer_id'] ?? 0) ?>">
        <label>Full name</label><input name="full_name" required value="<?= e($edit['full_name'] ?? '') ?>">
        <label>Phone</label><input name="phone" required value="<?= e($edit['phone'] ?? '') ?>">
        <label>Email</label><input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>">
        <label>Login username <?= $edit ? '' : '*' ?></label>
        <input name="username" <?= $edit ? '' : 'required' ?> value="<?= e($edit['username'] ?? '') ?>" placeholder="e.g. isuru">
        <label>Password <?= $edit ? '(leave blank to keep)' : '*' ?></label>
        <input type="password" name="password" <?= $edit ? '' : 'required' ?>>
        <label>Specialization</label><input name="specialization" value="<?= e($edit['specialization'] ?? '') ?>" placeholder="Pilates, strength, HIIT…">
        <label>Experience (years)</label><input type="number" step="0.1" name="experience_years" value="<?= e((string) ($edit['experience_years'] ?? '0')) ?>">
        <label>Certification</label><textarea name="certification"><?= e($edit['certification'] ?? '') ?></textarea>
        <label>Status</label><select name="status"><option>ACTIVE</option><option <?= selected($edit['status'] ?? '', 'INACTIVE') ?>>INACTIVE</option></select>
        <label>Photo</label><input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
        <button class="btn">Save coach login</button>
    </form>
    <div class="card">
        <h2>Coaches</h2>
        <table>
            <tr><th>Coach</th><th>Login</th><th>Status</th><th></th></tr>
            <?php foreach ($trainers as $t): ?>
                <tr>
                    <td><?= e($t['full_name']) ?><br><small><?= e($t['specialization']) ?></small></td>
                    <td><?= e($t['username'] ?: 'No login yet') ?></td>
                    <td><span class="badge <?= badge_class($t['status']) ?>"><?= e($t['status']) ?></span></td>
                    <td><a class="btn small ghost" href="?id=<?= (int) $t['trainer_id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <h2 style="margin-top:18px">Assign a member to a coach</h2>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="form" value="assign">
            <select name="trainer_id"><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['trainer_id'] ?>"><?= e($t['full_name']) ?></option><?php endforeach; ?></select>
            <select name="member_id"><?php foreach ($members as $m): ?><option value="<?= (int) $m['member_id'] ?>"><?= e($m['full_name']) ?></option><?php endforeach; ?></select>
            <button class="btn">Assign member</button>
        </form>
        <table>
            <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= e($a['member_name']) ?></td>
                    <td><?= e($a['trainer_name']) ?></td>
                    <td><span class="badge <?= badge_class($a['status']) ?>"><?= e($a['status']) ?></span></td>
                    <td>
                        <?php if ($a['status'] === 'ACTIVE'): ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="end"><input type="hidden" name="assignment_id" value="<?= (int) $a['assignment_id'] ?>"><button class="btn small warn" data-confirm="End this assignment?">End</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

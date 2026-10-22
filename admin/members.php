<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

$action = query_param('action', 'list');
$id = (int) query_param('id');

if (is_post() && post('form') === 'member') {
    $data = [
        'full_name' => trim((string) post('full_name')),
        'nic' => strtoupper(trim((string) post('nic'))),
        'phone' => trim((string) post('phone')),
        'email' => trim((string) post('email')),
        'username' => trim((string) post('username')),
        'date_of_birth' => post('date_of_birth') ?: null,
        'gender' => post('gender') ?: null,
        'address' => trim((string) post('address')),
        'emergency_contact_name' => trim((string) post('emergency_contact_name')),
        'emergency_contact_phone' => trim((string) post('emergency_contact_phone')),
        'status' => post('status') ?: 'ACTIVE',
    ];
    $errors = [];
    if ($data['full_name'] === '' || $data['nic'] === '' || $data['phone'] === '' || $data['email'] === '') {
        $errors[] = 'Name, NIC, phone and email are required.';
    }
    if ($data['nic'] && !valid_nic($data['nic'])) {
        $errors[] = 'NIC must be 9 digits + V or 12 digits.';
    }
    if ($data['email'] && !valid_email($data['email'])) {
        $errors[] = 'Invalid email.';
    }
    if ($data['phone'] && !valid_phone($data['phone'])) {
        $errors[] = 'Enter a valid Sri Lankan mobile number.';
    }

    $editId = (int) post('member_id');
    $dup = Database::fetch(
        'SELECT m.member_id FROM members m JOIN users u ON u.user_id = m.user_id
         WHERE (m.nic=? OR m.email=? OR m.phone=? OR u.email=? OR u.username=?) AND m.member_id <> ?',
        [$data['nic'], $data['email'], $data['phone'], $data['email'], $data['username'] ?: $data['email'], $editId]
    );
    if ($dup) {
        $errors[] = 'NIC, email, phone or username is already used.';
    }

    try {
        $image = upload_image('profile_image', 'members');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $image = null;
    }

    if (!$errors) {
        if ($editId) {
            $member = Database::fetch('SELECT * FROM members WHERE member_id=?', [$editId]);
            Database::update('members', [
                'full_name' => $data['full_name'],
                'nic' => $data['nic'],
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_phone' => $data['emergency_contact_phone'],
                'status' => $data['status'],
                'profile_image' => $image ?: $member['profile_image'],
            ], 'member_id=?', [$editId]);
            Database::update('users', [
                'email' => $data['email'],
                'status' => $data['status'] === 'SUSPENDED' ? 'SUSPENDED' : $data['status'],
            ], 'user_id=?', [$member['user_id']]);
            if (post('password')) {
                Database::update('users', ['password_hash' => password_hash((string) post('password'), PASSWORD_DEFAULT)], 'user_id=?', [$member['user_id']]);
            }
            audit('UPDATE', 'members', 'Updated ' . $data['full_name'], $editId);
            flash('success', 'Member updated.');
        } else {
            $username = $data['username'] ?: strtolower(preg_replace('/[^a-z0-9]+/i', '', explode('@', $data['email'])[0]));
            $password = (string) post('password') ?: 'Member@123';
            $userId = Database::insert('users', [
                'username' => $username,
                'email' => $data['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'MEMBER',
                'status' => $data['status'],
            ]);
            $code = next_code('GYM-', 'members', 'member_code');
            $memberId = Database::insert('members', [
                'user_id' => $userId,
                'member_code' => $code,
                'full_name' => $data['full_name'],
                'nic' => $data['nic'],
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'],
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_phone' => $data['emergency_contact_phone'],
                'profile_image' => $image,
                'registration_date' => date('Y-m-d'),
                'status' => $data['status'],
            ]);
            $member = Database::fetch('SELECT * FROM members WHERE member_id=?', [$memberId]);
            NotificationService::notifyMember($member, 'MEMBER_REGISTERED', NotificationService::render(
                'MEMBER_REGISTERED',
                ['name' => $member['full_name'], 'code' => $member['member_code']],
                'Welcome to the gym. Your member code is ' . $member['member_code'] . '.'
            ));
            NotificationService::notifyAdmins('NEW_MEMBER', 'New member registered: ' . $member['full_name'] . ' (' . $member['member_code'] . ').');
            audit('CREATE', 'members', 'Registered ' . $data['full_name'], $memberId);
            flash('success', 'Member registered. Code ' . $code . '. Temporary password: ' . $password);
        }
        redirect('admin/members.php');
    } else {
        flash('error', implode(' ', $errors));
        $action = 'form';
        $id = $editId;
    }
}

if (is_post() && post('form') === 'status') {
    $member = Database::fetch('SELECT * FROM members WHERE member_id=?', [(int) post('member_id')]);
    if ($member) {
        $status = post('status');
        Database::update('members', ['status' => $status], 'member_id=?', [$member['member_id']]);
        Database::update('users', ['status' => $status === 'SUSPENDED' ? 'SUSPENDED' : $status], 'user_id=?', [$member['user_id']]);
        audit('STATUS', 'members', $member['full_name'] . ' -> ' . $status, $member['member_id']);
        flash('success', 'Member status updated.');
    }
    redirect('admin/members.php');
}

if ($action === 'form') {
    $member = $id ? Database::fetch('SELECT m.*, u.username FROM members m JOIN users u ON u.user_id=m.user_id WHERE m.member_id=?', [$id]) : null;
    $pageTitle = $member ? 'Edit member' : 'Register member';
    require dirname(__DIR__) . '/includes/header.php';
    ?>
    <form class="card" method="post" enctype="multipart/form-data" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="member">
        <input type="hidden" name="member_id" value="<?= (int) ($member['member_id'] ?? 0) ?>">
        <div class="grid form-grid">
            <div><label>Full name *</label><input name="full_name" required value="<?= e($member['full_name'] ?? '') ?>"></div>
            <div><label>NIC *</label><input name="nic" required value="<?= e($member['nic'] ?? '') ?>"></div>
            <div><label>Phone *</label><input name="phone" required value="<?= e($member['phone'] ?? '') ?>"></div>
            <div><label>Email *</label><input type="email" name="email" required value="<?= e($member['email'] ?? '') ?>"></div>
            <div><label>Username</label><input name="username" value="<?= e($member['username'] ?? '') ?>" placeholder="auto from email if empty"></div>
            <div><label>Password <?= $member ? '(leave blank to keep)' : '*' ?></label><input type="password" name="password" <?= $member ? '' : 'required' ?>></div>
            <div><label>Date of birth</label><input type="date" name="date_of_birth" value="<?= e($member['date_of_birth'] ?? '') ?>"></div>
            <div><label>Gender</label>
                <select name="gender">
                    <option value="">Select</option>
                    <?php foreach (['MALE','FEMALE','OTHER'] as $g): ?>
                        <option value="<?= $g ?>" <?= selected($member['gender'] ?? '', $g) ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Status</label>
                <select name="status">
                    <?php foreach (['ACTIVE','INACTIVE','SUSPENDED'] as $s): ?>
                        <option value="<?= $s ?>" <?= selected($member['status'] ?? 'ACTIVE', $s) ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Profile image</label><input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp"></div>
            <div><label>Emergency contact</label><input name="emergency_contact_name" value="<?= e($member['emergency_contact_name'] ?? '') ?>"></div>
            <div><label>Emergency phone</label><input name="emergency_contact_phone" value="<?= e($member['emergency_contact_phone'] ?? '') ?>"></div>
        </div>
        <label>Address</label>
        <textarea name="address"><?= e($member['address'] ?? '') ?></textarea>
        <button class="btn" type="submit">Save member</button>
        <a class="btn ghost" href="<?= e(url('admin/members.php')) ?>">Cancel</a>
    </form>
    <?php
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$q = trim((string) query_param('q'));
$status = (string) query_param('status');
$sql = "SELECT m.*, ms.status membership_status, ms.end_date
        FROM members m
        LEFT JOIN memberships ms ON ms.membership_id = (
            SELECT membership_id FROM memberships x WHERE x.member_id = m.member_id ORDER BY x.created_at DESC LIMIT 1
        )
        WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= ' AND (m.full_name LIKE ? OR m.nic LIKE ? OR m.phone LIKE ? OR m.member_code LIKE ? OR m.email LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like, $like];
}
if ($status !== '') {
    $sql .= ' AND m.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY m.created_at DESC';
$rows = Database::fetchAll($sql, $params);

$pageTitle = 'Members';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <div class="card-h">
        <h2>Member directory</h2>
        <a class="btn" href="<?= e(url('admin/members.php?action=form')) ?>">Register member</a>
    </div>
    <form class="filters" method="get">
        <div><label>Search</label><input name="q" value="<?= e($q) ?>" placeholder="Name, NIC, phone, code"></div>
        <div><label>Status</label>
            <select name="status">
                <option value="">All</option>
                <?php foreach (['ACTIVE','INACTIVE','SUSPENDED'] as $s): ?>
                    <option value="<?= $s ?>" <?= selected($status, $s) ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn" type="submit">Filter</button>
    </form>
    <div class="table-wrap">
        <table>
            <tr><th>Code</th><th>Member</th><th>NIC</th><th>Phone</th><th>Membership</th><th>Engagement</th><th>Status</th><th></th></tr>
            <?php foreach ($rows as $r): $score = engagement_score((int) $r['member_id']); ?>
                <tr>
                    <td><?= e($r['member_code']) ?></td>
                    <td><?= e($r['full_name']) ?><br><small class="muted"><?= e($r['email']) ?></small></td>
                    <td><?= e($r['nic']) ?></td>
                    <td><?= e($r['phone']) ?></td>
                    <td><?= e($r['membership_status'] ?: '—') ?><br><small><?= e($r['end_date'] ?: '') ?></small></td>
                    <td>
                        <div class="progress"><span style="width:<?= $score ?>%"></span></div>
                        <small><?= $score ?>/100</small>
                    </td>
                    <td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td>
                        <a class="btn small ghost" href="<?= e(url('admin/members.php?action=form&id=' . $r['member_id'])) ?>">Edit</a>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form" value="status">
                            <input type="hidden" name="member_id" value="<?= (int) $r['member_id'] ?>">
                            <input type="hidden" name="status" value="<?= $r['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                            <button class="btn small warn" data-confirm="Change member status?"><?= $r['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

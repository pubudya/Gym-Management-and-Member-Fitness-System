<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();

if (is_post()) {
    $email = trim((string) post('email'));
    $phone = trim((string) post('phone'));
    if (!valid_email($email) || !valid_phone($phone)) {
        flash('error', 'Enter a valid email and mobile number.');
    } else {
        $dup = Database::fetch('SELECT member_id FROM members WHERE (email=? OR phone=?) AND member_id<>?', [$email, $phone, $m['member_id']]);
        if ($dup) {
            flash('error', 'Email or phone already used.');
        } else {
            try { $img = upload_image('profile_image', 'members'); } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('member/profile.php'); }
            Database::update('members', [
                'phone' => $phone,
                'email' => $email,
                'address' => post('address'),
                'emergency_contact_name' => post('emergency_contact_name'),
                'emergency_contact_phone' => post('emergency_contact_phone'),
                'profile_image' => $img ?: $m['profile_image'],
            ], 'member_id=?', [$m['member_id']]);
            Database::update('users', ['email' => $email], 'user_id=?', [$m['user_id']]);
            if (post('password')) {
                Database::update('users', ['password_hash' => password_hash((string) post('password'), PASSWORD_DEFAULT)], 'user_id=?', [$m['user_id']]);
            }
            $_SESSION['member'] = Database::fetch('SELECT * FROM members WHERE member_id=?', [$m['member_id']]);
            audit('UPDATE', 'profile', 'Member updated own profile', $m['member_id']);
            flash('success', 'Profile updated.');
        }
    }
    redirect('member/profile.php');
}

$pageTitle = 'My profile';
require dirname(__DIR__) . '/includes/header.php';
$m = current_member();
?>
<form class="card" method="post" enctype="multipart/form-data" style="max-width:720px">
    <?= csrf_field() ?>
    <p>Member code <b><?= e($m['member_code']) ?></b> · NIC <?= e($m['nic']) ?> (managed by admin)</p>
    <div class="grid form-grid">
        <div><label>Phone</label><input name="phone" required value="<?= e($m['phone']) ?>"></div>
        <div><label>Email</label><input type="email" name="email" required value="<?= e($m['email']) ?>"></div>
        <div><label>Emergency name</label><input name="emergency_contact_name" value="<?= e($m['emergency_contact_name']) ?>"></div>
        <div><label>Emergency phone</label><input name="emergency_contact_phone" value="<?= e($m['emergency_contact_phone']) ?>"></div>
        <div><label>New password</label><input type="password" name="password" placeholder="Leave blank to keep"></div>
        <div><label>Photo</label><input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp"></div>
    </div>
    <label>Address</label><textarea name="address"><?= e($m['address']) ?></textarea>
    <button class="btn">Save profile</button>
</form>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

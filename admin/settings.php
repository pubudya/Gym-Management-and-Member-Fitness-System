<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post()) {
    $fee = (float) post('monthly_fee', setting('monthly_fee', '0'));
    if ($fee < 0) {
        flash('error', 'Monthly gym fee cannot be negative.');
        redirect('admin/settings.php');
    }
    foreach (['gym_name','gym_phone','gym_email','gym_address','gym_capacity','membership_expiry_reminder_days','inactive_member_days','sms_notifications_enabled','online_payments_enabled'] as $key) {
        set_setting($key, post($key, setting($key, '')));
    }
    set_setting('monthly_fee', number_format($fee, 2, '.', ''));
    audit('UPDATE', 'settings', 'System settings updated');
    flash('success', 'Settings saved.');
    redirect('admin/settings.php');
}

$pageTitle = 'Settings';
require dirname(__DIR__) . '/includes/header.php';
?>
<form class="card" method="post" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="grid form-grid">
        <div><label>Gym name</label><input name="gym_name" value="<?= e((string) setting('gym_name')) ?>"></div>
        <div><label>Phone</label><input name="gym_phone" value="<?= e((string) setting('gym_phone')) ?>"></div>
        <div><label>Email</label><input name="gym_email" value="<?= e((string) setting('gym_email')) ?>"></div>
        <div><label>Capacity</label><input type="number" name="gym_capacity" value="<?= e((string) setting('gym_capacity', '80')) ?>"></div>
        <div>
            <label>Monthly gym fee (<?= e(APP_CURRENCY) ?>)</label>
            <input type="number" name="monthly_fee" min="0" step="0.01" value="<?= e((string) setting('monthly_fee', '0')) ?>">
        </div>
        <div><label>Expiry reminder (days)</label><input type="number" name="membership_expiry_reminder_days" value="<?= e((string) setting('membership_expiry_reminder_days', '7')) ?>"></div>
        <div><label>Inactive after (days)</label><input type="number" name="inactive_member_days" value="<?= e((string) setting('inactive_member_days', '14')) ?>"></div>
        <div><label>SMS enabled (1/0)</label><input name="sms_notifications_enabled" value="<?= e((string) setting('sms_notifications_enabled', '1')) ?>"></div>
        <div><label>Online payments (1/0)</label><input name="online_payments_enabled" value="<?= e((string) setting('online_payments_enabled', '1')) ?>"></div>
    </div>
    <label>Address</label>
    <textarea name="gym_address"><?= e((string) setting('gym_address')) ?></textarea>
    <p class="muted">The monthly gym fee is what members pay. Only admin can change it. Coaches and members cannot edit this amount.</p>
    <p class="muted">Connect a live SMS API in <code>config/config.local.php</code> (<code>provider: http</code>, api_url, api_key). Demo mode stores SMS in MySQL and marks them SENT without a carrier.</p>
    <button class="btn">Save settings</button>
</form>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

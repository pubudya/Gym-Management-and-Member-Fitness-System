<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

$fee = monthly_fee();

if (is_post() && post('form') === 'membership') {
    $memberId = (int) post('member_id');
    $member = Database::fetch('SELECT * FROM members WHERE member_id=?', [$memberId]);
    $start = post('start_date') ?: date('Y-m-d');
    $end = date('Y-m-d', strtotime($start . ' +1 month'));
    $amount = $fee;
    $discount = max(0, (float) post('discount'));
    $final = max(0, $amount - $discount);
    $paid = max(0, (float) post('paid_amount'));
    $balance = max(0, $final - $paid);
    $status = $balance <= 0 ? 'ACTIVE' : 'PENDING';
    if (!$member) {
        flash('error', 'Select a valid member.');
    } elseif ($amount <= 0) {
        flash('error', 'Set the monthly gym fee in Settings before assigning memberships.');
    } else {
        $id = Database::insert('memberships', [
            'member_id' => $memberId,
            'start_date' => $start,
            'end_date' => $end,
            'amount' => $amount,
            'discount' => $discount,
            'final_amount' => $final,
            'paid_amount' => $paid,
            'balance' => $balance,
            'status' => $status,
            'created_by' => current_user()['user_id'],
        ]);
        if ($paid > 0) {
            PaymentService::record([
                'member_id' => $memberId,
                'membership_id' => $id,
                'amount' => $paid,
                'payment_method' => post('payment_method') ?: 'CASH',
                'payment_status' => 'SUCCESS',
                'paid_by' => 'ADMIN',
                'notes' => 'Monthly gym fee',
            ]);
        }
        if ($status === 'ACTIVE') {
            NotificationService::notifyMember($member, 'MEMBERSHIP_ACTIVATED', NotificationService::render(
                'MEMBERSHIP_ACTIVATED',
                ['name' => $member['full_name'], 'end_date' => $end],
                'Your monthly gym membership is active until ' . $end . '.'
            ));
        }
        audit('CREATE', 'memberships', $member['full_name'] . ' monthly fee', $id);
        flash('success', 'Monthly membership saved.');
    }
    redirect('admin/memberships.php');
}

if (is_post() && post('form') === 'status') {
    Database::update('memberships', ['status' => post('status')], 'membership_id=?', [(int) post('membership_id')]);
    audit('STATUS', 'memberships', 'Status ' . post('status'), post('membership_id'));
    flash('success', 'Membership status updated.');
    redirect('admin/memberships.php');
}

$filter = (string) query_param('status');
$sql = "SELECT ms.*, m.full_name, m.member_code
        FROM memberships ms JOIN members m ON m.member_id=ms.member_id WHERE 1=1";
$params = [];
if ($filter) {
    $sql .= ' AND ms.status=?';
    $params[] = $filter;
}
$sql .= ' ORDER BY ms.created_at DESC';
$rows = Database::fetchAll($sql, $params);
$members = Database::fetchAll("SELECT member_id, full_name, member_code FROM members ORDER BY full_name");
$pageTitle = 'Memberships';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <form class="card" method="post" data-validate>
        <h2>Assign / renew monthly fee</h2>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="membership">
        <p>Monthly gym fee: <b><?= e(money($fee)) ?></b> · 1 month
            <a href="<?= e(url('admin/settings.php')) ?>">Change in Settings</a>
        </p>
        <label>Member</label>
        <select name="member_id" required>
            <option value="">Select</option>
            <?php foreach ($members as $m): ?>
                <option value="<?= (int) $m['member_id'] ?>"><?= e($m['full_name'] . ' · ' . $m['member_code']) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Start date</label><input type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>">
        <label>Discount</label><input type="number" min="0" step="0.01" name="discount" value="0">
        <label>Amount paid now</label><input type="number" min="0" step="0.01" name="paid_amount" value="0">
        <label>Payment method</label>
        <select name="payment_method"><option>CASH</option><option>CARD</option><option>BANK_TRANSFER</option><option>ONLINE</option></select>
        <button class="btn" type="submit">Save monthly membership</button>
    </form>
    <div class="card">
        <div class="card-h">
            <h2>All memberships</h2>
            <div class="tabs">
                <a class="<?= $filter===''?'active':'' ?>" href="?">All</a>
                <?php foreach (['ACTIVE','PENDING','EXPIRED','FROZEN'] as $s): ?>
                    <a class="<?= $filter===$s?'active':'' ?>" href="?status=<?= $s ?>"><?= $s ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <tr><th>Member</th><th>Fee</th><th>Dates</th><th>Balance</th><th>Status</th></tr>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['full_name']) ?><br><small><?= e($r['member_code']) ?></small></td>
                        <td><?= e(money($r['final_amount'])) ?></td>
                        <td><?= e($r['start_date']) ?> → <?= e($r['end_date']) ?></td>
                        <td><?= e(money($r['balance'])) ?></td>
                        <td>
                            <span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form" value="status">
                                <input type="hidden" name="membership_id" value="<?= (int) $r['membership_id'] ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <?php foreach (['PENDING','ACTIVE','EXPIRED','FROZEN','CANCELLED'] as $s): ?>
                                        <option <?= selected($r['status'], $s) ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

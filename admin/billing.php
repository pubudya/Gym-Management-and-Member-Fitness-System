<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

$subscription = SubscriptionService::current();
$billing = SubscriptionService::billingDetails();
$payments = $subscription ? SubscriptionService::payments((int) $subscription['gym_id']) : [];
$locked = !SubscriptionService::allowsProtectedAccess($subscription);
$amount = SubscriptionService::expectedAmount($subscription);

$pageTitle = 'Subscription & billing';
$pageSubtitle = 'Gym software subscription — coaches and members do not pay separately';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php if ($locked): ?>
    <div class="alert bad">
        This gym's software subscription is <?= e($subscription['status'] ?? 'EXPIRED') ?>.
        Gym data has not been deleted. Renew to restore member, coach and admin operations.
    </div>
<?php endif; ?>

<div class="grid cards-2">
    <div class="card">
        <div class="card-h">
            <h2>Current subscription</h2>
            <span class="badge <?= e(badge_class($subscription['status'] ?? 'EXPIRED')) ?>"><?= e($subscription['status'] ?? 'UNAVAILABLE') ?></span>
        </div>
        <p class="muted">The subscription belongs to the gym, not to individual coaches or members.</p>
        <div class="grid stats" style="grid-template-columns:1fr 1fr;margin-top:12px">
            <div class="stat"><span>Plan</span><b><?= e($subscription['plan_name'] ?? '—') ?></b></div>
            <div class="stat"><span>Amount</span><b><?= e(money($amount)) ?></b></div>
            <div class="stat"><span>Started</span><b><?= e($subscription['start_date'] ?? '—') ?></b></div>
            <div class="stat"><span>Expires</span><b><?= e($subscription['expiry_date'] ?? '—') ?></b></div>
        </div>
        <p style="margin-top:14px"><strong>Next payment due:</strong> <?= e($subscription['next_payment_due_date'] ?? $subscription['expiry_date'] ?? '—') ?></p>
        <p><strong>Remaining:</strong> <?= e(SubscriptionService::remainingLabel($subscription)) ?></p>
        <a class="btn" href="<?= e(url('admin/renew.php')) ?>">Renew Subscription</a>
    </div>
    <div class="card">
        <h2>Payment instructions</h2>
        <p><?= e($billing['instructions']) ?></p>
        <p><strong>Company:</strong> <?= e($billing['company_name']) ?></p>
        <p><strong>Account name:</strong> <?= e($billing['account_name']) ?></p>
        <p><strong>Bank:</strong> <?= e($billing['bank_name']) ?></p>
        <p><strong>Account number:</strong> <?= e($billing['account_number']) ?></p>
        <p><strong>Branch:</strong> <?= e($billing['branch']) ?></p>
        <div class="callout" style="margin-top:14px">
            <strong>Contact support</strong><br>
            <?= e($billing['support_email']) ?> · <?= e($billing['support_phone']) ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-h"><h2>Subscription payment history</h2></div>
    <div class="table-wrap">
        <table>
            <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Payment reference</th>
                <th>Status</th>
                <th>Receipt</th>
            </tr>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= e(date('d M Y', strtotime((string) ($p['created_at'] ?: $p['payment_date'])))) ?></td>
                    <td><?= e(money($p['amount'])) ?></td>
                    <td><?= e($p['payment_reference'] ?: '—') ?></td>
                    <td>
                        <span class="badge <?= e(badge_class($p['payment_status'])) ?>"><?= e($p['payment_status']) ?></span>
                        <?php if ($p['verification_result'] && $p['verification_result'] !== $p['payment_status']): ?>
                            <br><small class="muted"><?= e($p['verification_result']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn small ghost" href="<?= e(url('admin/receipt.php?id=' . (int) $p['payment_id'])) ?>" target="_blank" rel="noopener">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?>
                <tr><td colspan="5">No subscription payments yet.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

$subscription = SubscriptionService::current();
$billing = SubscriptionService::billingDetails();
$amount = SubscriptionService::expectedAmount($subscription);
$result = $_SESSION['subscription_result'] ?? null;
unset($_SESSION['subscription_result']);

if (is_post() && post('action') === 'submit_payment') {
    try {
        $reference = strtoupper(trim((string) post('payment_reference')));
        $outcome = SubscriptionService::submitRenewal($_FILES['receipt'] ?? [], $reference, (int) current_user()['user_id']);
        $_SESSION['subscription_result'] = $outcome;
        redirect('admin/renew.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('admin/renew.php');
    }
}

$pageTitle = 'Renew subscription';
$pageSubtitle = 'Pay the gym software subscription and upload the receipt';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php if ($result && ($result['state'] ?? '') === 'APPROVED'): ?>
    <div class="card renewal-result ok-card">
        <h2>Subscription Renewed Successfully</h2>
        <p>Your subscription has been successfully renewed.</p>
        <p><strong>Plan:</strong> <?= e((string) ($result['plan'] ?? $subscription['plan_name'] ?? 'Professional')) ?></p>
        <p><strong>Amount Paid:</strong> <?= e(money($result['amount'] ?? $amount)) ?></p>
        <p><strong>New Expiry Date:</strong> <?= e(date('d F Y', strtotime((string) ($result['expiry_date'] ?? $subscription['expiry_date'])))) ?></p>
        <p>Thank you for continuing to use the Gym Management System.</p>
        <a class="btn" href="<?= e(url('admin/dashboard.php')) ?>">Back to dashboard</a>
        <a class="btn ghost" href="<?= e(url('admin/billing.php')) ?>">View billing history</a>
    </div>
<?php elseif ($result && ($result['state'] ?? '') === 'REJECTED'): ?>
    <div class="card renewal-result bad-card">
        <h2>Payment Verification Failed</h2>
        <p>We could not verify your payment receipt.</p>
        <p>Possible reasons:</p>
        <ul>
            <li>Incorrect payment amount</li>
            <li>Invalid receipt</li>
            <li>Duplicate transaction</li>
            <li>Unreadable receipt</li>
            <li>Payment could not be confirmed</li>
        </ul>
        <?php if (!empty($result['message'])): ?>
            <p class="muted"><?= e((string) $result['message']) ?></p>
        <?php endif; ?>
        <p>Please upload a valid payment receipt or contact support.</p>
        <a class="btn" href="#upload-receipt">Upload Another Receipt</a>
    </div>
<?php elseif ($result && ($result['state'] ?? '') === 'REVIEW_REQUIRED'): ?>
    <div class="card renewal-result">
        <h2>Payment received — review required</h2>
        <p><?= e((string) ($result['message'] ?? 'Your receipt is being reviewed by the company payment team.')) ?></p>
        <p>The subscription will be extended after the receipt is verified. Gym data remains stored.</p>
        <a class="btn ghost" href="<?= e(url('admin/billing.php')) ?>">View payment status</a>
    </div>
<?php endif; ?>

<div class="grid cards-2">
    <div class="card">
        <h2>Renewal details</h2>
        <p><strong>Current plan:</strong> <?= e($subscription['plan_name'] ?? 'Professional') ?></p>
        <p><strong>Amount to pay:</strong> <?= e(money($amount)) ?></p>
        <p><strong>Due date:</strong> <?= e(date('d F Y', strtotime((string) ($subscription['expiry_date'] ?? 'now')))) ?></p>
        <p><strong>Status:</strong> <span class="badge <?= e(badge_class($subscription['status'] ?? '')) ?>"><?= e($subscription['status'] ?? '') ?></span></p>
        <hr class="soft">
        <h3>Payment instructions</h3>
        <p><?= e($billing['instructions']) ?></p>
        <p><strong>Bank:</strong> <?= e($billing['bank_name']) ?></p>
        <p><strong>Account name:</strong> <?= e($billing['account_name']) ?></p>
        <p><strong>Account number:</strong> <?= e($billing['account_number']) ?></p>
        <p><strong>Branch:</strong> <?= e($billing['branch']) ?></p>
        <p class="muted">Support: <?= e($billing['support_email']) ?> · <?= e($billing['support_phone']) ?></p>
    </div>
    <div class="card" id="upload-receipt">
        <h2>Upload payment receipt</h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_payment">
            <label>Payment reference (if shown on the slip)</label>
            <input name="payment_reference" maxlength="80" placeholder="e.g. TXN001 or bank trace number">
            <label>Payment receipt</label>
            <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
            <p class="muted">JPG, PNG, WEBP or PDF. Maximum 5MB. Receipts are stored privately and are not publicly accessible. The system will try to read the slip automatically. If it cannot verify the payment with high confidence, the receipt is sent for company review instead of being approved blindly.</p>
            <button class="btn full" type="submit">Submit Payment</button>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

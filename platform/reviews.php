<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('PLATFORM');

if (is_post()) {
    $paymentId = (int) post('payment_id');
    $notes = trim((string) post('review_notes'));
    try {
        if (post('action') === 'approve') {
            $out = SubscriptionService::approvePayment($paymentId, (int) current_user()['user_id'], $notes !== '' ? $notes : 'Manually approved by company reviewer.');
            flash('success', 'Payment approved and gym subscription renewed until ' . ($out['expiry_date'] ?? 'the new expiry date') . '.');
        } else {
            SubscriptionService::rejectPayment($paymentId, (int) current_user()['user_id'], $notes !== '' ? $notes : 'Manually rejected by company reviewer.');
            flash('info', 'Payment rejected. The gym owner can upload another receipt.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('platform/reviews.php');
}

$queue = Database::fetchAll(
    "SELECT p.*, g.gym_name, sp.plan_name
     FROM subscription_payments p
     JOIN gyms g ON g.gym_id = p.gym_id
     JOIN subscription_plans sp ON sp.plan_id = p.plan_id
     WHERE p.payment_status = 'PENDING'
     ORDER BY p.payment_id ASC"
);
$recent = Database::fetchAll(
    "SELECT p.*, g.gym_name, sp.plan_name, u.username AS reviewer
     FROM subscription_payments p
     JOIN gyms g ON g.gym_id = p.gym_id
     JOIN subscription_plans sp ON sp.plan_id = p.plan_id
     LEFT JOIN users u ON u.user_id = p.reviewed_by
     WHERE p.payment_status <> 'PENDING'
     ORDER BY p.updated_at DESC LIMIT 20"
);

$pageTitle = 'Subscription payment reviews';
$pageSubtitle = 'Verify gym owner receipts before renewing software access';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <h2>Awaiting review</h2>
    <?php if (!$queue): ?>
        <p class="muted">No subscription payments are waiting for review.</p>
    <?php endif; ?>
    <?php foreach ($queue as $p): ?>
        <?php
        $ocr = json_decode((string) ($p['ocr_json'] ?? ''), true) ?: [];
        $logs = SubscriptionService::logs((int) $p['payment_id']);
        ?>
        <article class="review-item">
            <div class="card-h">
                <div>
                    <h3><?= e($p['gym_name']) ?> · <?= e($p['plan_name']) ?></h3>
                    <p class="muted">Submitted <?= e($p['created_at']) ?> · <?= e(money($p['amount'])) ?></p>
                </div>
                <span class="badge <?= e(badge_class($p['verification_result'])) ?>"><?= e($p['verification_result']) ?></span>
            </div>
            <p><a class="btn small ghost" href="<?= e(url('admin/receipt.php?id=' . (int) $p['payment_id'])) ?>" target="_blank" rel="noopener">View receipt</a></p>
            <div class="grid form-grid">
                <div>
                    <p><strong>Owner reference:</strong> <?= e($p['payment_reference'] ?: '—') ?></p>
                    <p><strong>Extracted amount:</strong> <?= e($p['extracted_amount'] !== null ? money($p['extracted_amount']) : '—') ?></p>
                    <p><strong>Extracted date:</strong> <?= e($p['extracted_date'] ?: '—') ?></p>
                    <p><strong>Extracted reference:</strong> <?= e($p['extracted_reference'] ?: '—') ?></p>
                    <p><strong>Bank / sender / recipient:</strong> <?= e(trim(($p['extracted_bank'] ?: '—') . ' / ' . ($p['extracted_sender'] ?: '—') . ' / ' . ($p['extracted_recipient'] ?: '—'))) ?></p>
                    <p><strong>System notes:</strong> <?= e($p['verification_notes'] ?: '—') ?></p>
                </div>
                <div>
                    <p class="muted">OCR engine: <?= e($ocr['engine'] ?? 'none') ?><?= !empty($ocr['readable']) ? ' · readable' : ' · low confidence' ?></p>
                    <pre class="ocr-text"><?= e(substr((string) ($ocr['text'] ?? ''), 0, 1200)) ?></pre>
                </div>
            </div>
            <?php if ($logs): ?>
                <p class="muted">Verification log: <?= e(implode(' → ', array_map(static fn ($l) => $l['action'] . ' (' . $l['result'] . ')', $logs))) ?></p>
            <?php endif; ?>
            <form method="post" class="filters">
                <?= csrf_field() ?>
                <input type="hidden" name="payment_id" value="<?= (int) $p['payment_id'] ?>">
                <div>
                    <label>Reviewer notes</label>
                    <input name="review_notes" placeholder="Why this receipt is approved or rejected">
                </div>
                <button class="btn" name="action" value="approve">Approve & renew</button>
                <button class="btn ember" name="action" value="reject" data-confirm="Reject this receipt?">Reject</button>
            </form>
        </article>
    <?php endforeach; ?>
</div>

<div class="card" style="margin-top:16px">
    <h2>Recent decisions</h2>
    <div class="table-wrap">
        <table>
            <tr><th>Date</th><th>Gym</th><th>Amount</th><th>Reference</th><th>Status</th><th>Reviewer</th><th>Receipt</th></tr>
            <?php foreach ($recent as $p): ?>
                <tr>
                    <td><?= e(date('d M Y H:i', strtotime((string) $p['updated_at']))) ?></td>
                    <td><?= e($p['gym_name']) ?></td>
                    <td><?= e(money($p['amount'])) ?></td>
                    <td><?= e($p['payment_reference'] ?: '—') ?></td>
                    <td><span class="badge <?= e(badge_class($p['payment_status'])) ?>"><?= e($p['payment_status']) ?></span></td>
                    <td><?= e($p['reviewer'] ?: 'system') ?></td>
                    <td><a class="btn small ghost" href="<?= e(url('admin/receipt.php?id=' . (int) $p['payment_id'])) ?>" target="_blank" rel="noopener">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="7">No reviewed payments yet.</td></tr><?php endif; ?>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

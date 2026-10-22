<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('COACH');

$members = TrainingService::membersForSelect();
$rows = [];
foreach ($members as $m) {
    $id = (int) $m['member_id'];
    $ms = active_membership($id);
    $last = Database::fetch('SELECT MAX(check_in) last_in FROM attendance WHERE member_id=?', [$id]);
    $measure = Database::fetch('SELECT * FROM fitness_measurements WHERE member_id=? ORDER BY measurement_date DESC LIMIT 1', [$id]);
    $first = Database::fetch('SELECT * FROM fitness_measurements WHERE member_id=? ORDER BY measurement_date ASC LIMIT 1', [$id]);
    $gain = null;
    if ($measure && $first && $measure['measurement_id'] !== $first['measurement_id']) {
        $gain = (float) $measure['weight_kg'] - (float) $first['weight_kg'];
    }
    $rows[] = [
        'member' => $m,
        'plan' => $ms ? ($ms['status'] . ' until ' . $ms['end_date']) : '—',
        'score' => engagement_score($id),
        'last' => $last['last_in'] ?? 'Never',
        'weight' => $measure['weight_kg'] ?? null,
        'gain' => $gain,
    ];
}

$pageTitle = 'Assigned members';
$pageSubtitle = 'These are the only people you can train';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="callout">You cannot see or edit other gym members. Ask admin if someone is missing from this list.</div>
<div class="card">
    <table>
        <tr><th>Member</th><th>Membership</th><th>Last visit</th><th>Weight</th><th>Change</th><th>Engagement</th><th></th></tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><b><?= e($r['member']['full_name']) ?></b><br><small><?= e($r['member']['member_code']) ?></small></td>
                <td><?= e($r['plan']) ?></td>
                <td><?= e((string) $r['last']) ?></td>
                <td><?= $r['weight'] !== null ? e($r['weight'] . ' kg') : '—' ?></td>
                <td><?php
                    if ($r['gain'] === null) {
                        echo '—';
                    } else {
                        $g = $r['gain'];
                        echo ($g > 0 ? '+' : '') . number_format($g, 1) . ' kg';
                    }
                ?></td>
                <td>
                    <div class="progress"><span style="width:<?= (int) $r['score'] ?>%"></span></div>
                    <small><?= (int) $r['score'] ?>/100</small>
                </td>
                <td><a class="btn small" href="<?= e(url('coach/progress.php?member_id=' . $r['member']['member_id'])) ?>">Progress</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7">No assigned members yet.</td></tr><?php endif; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

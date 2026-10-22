<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');
$q = trim((string) query_param('q'));
$sql = "SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON u.user_id=a.user_id WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= ' AND (a.action LIKE ? OR a.module LIKE ? OR a.description LIKE ? OR u.username LIKE ?)';
    $like = '%'.$q.'%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY a.created_at DESC LIMIT 200';
$rows = Database::fetchAll($sql, $params);
$pageTitle = 'Audit logs';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <form class="filters" method="get"><div><label>Search</label><input name="q" value="<?= e($q) ?>"></div><button class="btn">Search</button></form>
    <table>
        <tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Detail</th><th>IP</th></tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['created_at']) ?></td>
                <td><?= e($r['username'] ?: 'system') ?></td>
                <td><?= e($r['action']) ?></td>
                <td><?= e($r['module']) ?></td>
                <td><?= e($r['description']) ?></td>
                <td><?= e($r['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

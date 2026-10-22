<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

$type = (string) query_param('type', 'members');
$from = (string) query_param('from', date('Y-m-01'));
$to = (string) query_param('to', date('Y-m-d'));

$queries = [
    'members' => ["SELECT member_code, full_name, nic, phone, email, status, registration_date FROM members WHERE registration_date BETWEEN ? AND ?", [$from, $to]],
    'memberships' => ["SELECT m.full_name, ms.start_date, ms.end_date, ms.final_amount, ms.paid_amount, ms.balance, ms.status FROM memberships ms JOIN members m ON m.member_id=ms.member_id WHERE ms.start_date BETWEEN ? AND ?", [$from, $to]],
    'attendance' => ["SELECT m.full_name, a.attendance_date, a.check_in, a.check_out, a.duration_minutes, a.attendance_method FROM attendance a JOIN members m ON m.member_id=a.member_id WHERE a.attendance_date BETWEEN ? AND ?", [$from, $to]],
    'payments' => ["SELECT m.full_name, p.amount, p.payment_method, p.payment_status, p.transaction_reference, p.payment_date FROM payments p JOIN members m ON m.member_id=p.member_id WHERE DATE(p.created_at) BETWEEN ? AND ?", [$from, $to]],
    'revenue' => ["SELECT DATE(payment_date) d, SUM(amount) total FROM payments WHERE payment_status='SUCCESS' AND DATE(payment_date) BETWEEN ? AND ? GROUP BY DATE(payment_date)", [$from, $to]],
    'expenses' => ["SELECT e.expense_date, c.category_name, e.amount, e.description FROM expenses e JOIN expense_categories c ON c.category_id=e.category_id WHERE e.expense_date BETWEEN ? AND ?", [$from, $to]],
    'trainers' => ["SELECT full_name, phone, specialization, experience_years, status FROM trainers", []],
    'workouts' => ["SELECT m.full_name, we.exercise_name, wp.progress_date, wp.completed_sets, wp.weight_used_kg, wp.completion_percentage FROM workout_progress wp JOIN members m ON m.member_id=wp.member_id JOIN workout_exercises we ON we.exercise_id=wp.exercise_id WHERE wp.progress_date BETWEEN ? AND ?", [$from, $to]],
    'classes' => ["SELECT gc.class_name, cs.class_date, cs.start_time, t.full_name trainer, cs.status FROM class_schedules cs JOIN gym_classes gc ON gc.class_id=cs.class_id JOIN trainers t ON t.trainer_id=cs.trainer_id WHERE cs.class_date BETWEEN ? AND ?", [$from, $to]],
    'equipment' => ["SELECT equipment_code, equipment_name, condition_status, status, health_score FROM equipment", []],
    'inventory' => ["SELECT item_code, item_name, quantity, minimum_stock, status FROM inventory", []],
    'maintenance' => ["SELECT e.equipment_name, em.maintenance_date, em.problem, em.cost, em.status FROM equipment_maintenance em JOIN equipment e ON e.equipment_id=em.equipment_id WHERE em.maintenance_date BETWEEN ? AND ?", [$from, $to]],
    'notifications' => ["SELECT recipient_type, recipient_name, notification_type, phone_number, delivery_status, created_at FROM notifications WHERE DATE(created_at) BETWEEN ? AND ?", [$from, $to]],
];
if (!isset($queries[$type])) $type = 'members';
[$sql, $params] = $queries[$type];
$rows = Database::fetchAll($sql, $params);

$rev = (float) (Database::fetch("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE payment_status='SUCCESS' AND DATE(payment_date) BETWEEN ? AND ?", [$from, $to])['s'] ?? 0);
$exp = (float) (Database::fetch("SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE expense_date BETWEEN ? AND ?", [$from, $to])['s'] ?? 0);

if (query_param('export') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$type.'-report.csv"');
    $out = fopen('php://output', 'w');
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Reports';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <form class="filters" method="get">
        <div><label>Report</label>
            <select name="type"><?php foreach (array_keys($queries) as $t): ?><option value="<?= e($t) ?>" <?= selected($type,$t) ?>><?= e(ucfirst($t)) ?></option><?php endforeach; ?></select>
        </div>
        <div><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
        <div><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
        <button class="btn">View</button>
        <a class="btn ghost" href="?<?= e(http_build_query(['type'=>$type,'from'=>$from,'to'=>$to,'export'=>'csv'])) ?>">Export CSV</a>
        <button class="btn ghost" type="button" onclick="voltPrint()">Print</button>
    </form>
    <div class="grid stats">
        <div class="stat volt"><span>Revenue</span><b><?= e(money($rev)) ?></b></div>
        <div class="stat ember"><span>Expenses</span><b><?= e(money($exp)) ?></b></div>
        <div class="stat ice"><span>Profit</span><b><?= e(money($rev - $exp)) ?></b></div>
        <div class="stat"><span>Rows</span><b><?= count($rows) ?></b></div>
    </div>
    <div class="table-wrap" style="margin-top:16px">
        <table>
            <?php if ($rows): ?>
                <tr><?php foreach (array_keys($rows[0]) as $h): ?><th><?= e(str_replace('_',' ',$h)) ?></th><?php endforeach; ?></tr>
                <?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e((string)$v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
            <?php else: ?><tr><td>No rows for this filter.</td></tr><?php endif; ?>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

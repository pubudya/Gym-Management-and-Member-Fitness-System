<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

$cap = (int) setting('gym_capacity', 80);
$now = occupancy_count();
$hours = Database::fetchAll("SELECT HOUR(check_in) h, COUNT(*) c FROM attendance WHERE check_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY HOUR(check_in) ORDER BY c DESC");
$days = Database::fetchAll("SELECT attendance_date d, COUNT(*) c FROM attendance WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY attendance_date ORDER BY d");
$pageTitle = 'Capacity & peak hours';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid stats">
    <div class="stat volt"><span>Live occupancy</span><b><?= $now ?>/<?= $cap ?></b></div>
    <div class="stat"><span>Fill</span><b><?= $cap ? round($now / $cap * 100) : 0 ?>%</b></div>
    <div class="stat ice"><span>Busiest hour</span><b><?= isset($hours[0]) ? sprintf('%02d:00', $hours[0]['h']) : '—' ?></b></div>
    <div class="stat ember"><span>Peak visits</span><b><?= isset($hours[0]) ? (int)$hours[0]['c'] : 0 ?></b></div>
</div>
<div class="card" style="margin-top:16px">
    <h2>Daily visits (14 days)</h2>
    <canvas id="dayChart" height="120"></canvas>
</div>
<div class="card" style="margin-top:16px">
    <h2>Hour ranking</h2>
    <table><tr><th>Hour</th><th>Check-ins</th></tr>
        <?php foreach ($hours as $h): ?><tr><td><?= sprintf('%02d:00', $h['h']) ?></td><td><?= (int)$h['c'] ?></td></tr><?php endforeach; ?>
    </table>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>voltCharts('dayChart','line',<?= json_encode(array_column($days,'d')) ?>,<?= json_encode(array_map('intval', array_column($days,'c'))) ?>,{label:'Visits'}));</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

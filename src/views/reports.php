<?php
// ==========================
// FILE: /views/reports.php
// ==========================
function view_reports(){
global $db; layout_header('Báo cáo');
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t');
echo '<div class="card"><h3>Khoảng thời gian</h3><form method="get" style="display:flex;gap:8px;align-items:end">';
echo '<input type="hidden" name="action" value="reports">';
echo '<div><label>Bắt đầu</label><input type="date" name="start" value="'.h($start).'"></div>';
echo '<div><label>Kết thúc</label><input type="date" name="end" value="'.h($end).'"></div>';
echo '<div><button class="btn secondary">Áp dụng</button></div></form></div>';
$spent = (float) q($db, "SELECT IFNULL(SUM(spent),0) FROM campaigns WHERE (start_date IS NULL OR date(start_date) <= date(?)) AND (end_date IS NULL OR date(end_date) >= date(?))", [$end,$start])->fetchColumn();
$won = (float) q($db, "SELECT IFNULL(SUM(value),0) FROM deals WHERE stage='Won' AND date(created_at) BETWEEN date(?) AND date(?)", [$start,$end])->fetchColumn();
echo '<div class="grid cols-3">';
echo '<div class="card"><div>Chi quảng cáo (giao cắt kỳ)</div><div class="kpi">'.number_format($spent,2).'</div></div>';
echo '<div class="card"><div>Doanh thu thắng (Won)</div><div class="kpi">'.number_format($won,2).'</div></div>';
$ratio = $won>0 ? $spent/$won : 0; echo '<div class="card"><div>Tỉ lệ Chi/Doanh thu</div><div class="kpi">'.number_format($ratio,3).'</div></div>';
echo '</div>';
$top = q($db, "SELECT channel, COUNT(*) n, SUM(value) total FROM deals WHERE stage='Won' AND date(created_at) BETWEEN date(?) AND date(?) GROUP BY channel ORDER BY total DESC LIMIT 10", [$start,$end])->fetchAll(PDO::FETCH_ASSOC);
echo '<div class="card"><h3>Kênh hiệu quả (Won)</h3><table><tr><th>Kênh</th><th>Số deal</th><th>Tổng</th></tr>';
foreach($top as $t){ echo '<tr><td>'.h($t['channel']).'</td><td>'.(int)$t['n'].'</td><td>'.number_format($t['total'],2).'</td></tr>'; }
echo '</table></div>';
layout_footer();
}
?>
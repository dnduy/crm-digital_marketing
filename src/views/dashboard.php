<?php
// ==========================
// FILE: /views/dashboard.php
// ==========================
function view_dashboard(){
global $db; layout_header('Bảng điều khiển');
$k_contacts = (int) q($db, "SELECT COUNT(*) FROM contacts")->fetchColumn();
$sum_pipeline = (float) q($db, "SELECT IFNULL(SUM(value),0) FROM deals WHERE stage IN ('New','Qualified','Proposal')")->fetchColumn();
$sum_won = (float) q($db, "SELECT IFNULL(SUM(value),0) FROM deals WHERE stage='Won'")->fetchColumn();
echo '<div class="grid cols-3">';
echo '<div class="card"><div>Tổng liên hệ</div><div class="kpi">'.number_format($k_contacts).'</div></div>';
echo '<div class="card"><div>Pipeline mở</div><div class="kpi">'.number_format($sum_pipeline,2).'</div></div>';
echo '<div class="card"><div>Doanh thu thắng</div><div class="kpi">'.number_format($sum_won,2).'</div></div>';
echo '</div>';
echo '<div class="card">Tạo nhanh: <a class="btn" href="?action=contacts&op=new">Liên hệ</a> <a class="btn" href="?action=deals&op=new">Giao dịch</a> <a class="btn" href="?action=campaigns&op=new">Chiến dịch</a></div>';
layout_footer();
}
?>
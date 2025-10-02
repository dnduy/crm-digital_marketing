<?php
function view_reports(){ global $db; layout_header('Báo cáo'); $start=$_GET['start']??date('Y-m-01'); $end=$_GET['end']??date('Y-m-t'); echo '<div class="card"><h3>Khoảng thời gian</h3><form method="get" style="display:flex;gap:8px;align-items:end"><input type="hidden" name="action" value="reports"><div><label>Bắt đầu</label><input type="date" name="start" value="'.h($start).'"></div><div><label>Kết thúc</label><input type="date" name="end" value="'.h($end).'"></div><div><button class="btn secondary">Áp dụng</button></div></form></div>'; $spent=(float)q($db,"SELECT IFNULL(SUM(spent),0) FROM campaigns WHERE (start_date IS NULL OR date(start_date) <= date(?)) AND (end_date IS NULL OR date(end_date) >= date(?))",[$end,$start])->fetchColumn(); $won=(float)q($db,"SELECT IFNULL(SUM(value),0) FROM deals WHERE stage='Won' AND date(created_at) BETWEEN date(?) AND date(?)",[$start,$end])->fetchColumn(); echo '<div class="grid cols-3"><div class="card"><div>Chi quảng cáo (giao cắt kỳ)</div><div class="kpi">'.number_format($spent,2).'</div></div><div class="card"><div>Doanh thu thắng (Won)</div><div class="kpi">'.number_format($won,2).'</div></div><div class="card"><div>Tỉ lệ Chi/Doanh thu</div><div class="kpi">'.number_format($won>0?$spent/$won:0,3).'</div></div></div>'; $top=q($db,"SELECT channel, COUNT(*) n, SUM(value) total FROM deals WHERE stage='Won' AND date(created_at) BETWEEN date(?) AND date(?) GROUP BY channel ORDER BY total DESC LIMIT 10",[$start,$end])->fetchAll(PDO::FETCH_ASSOC); echo '<div class="card"><h3>Kênh hiệu quả (Won)</h3><table><tr><th>Kênh</th><th>Số deal</th><th>Tổng</th></tr>'; foreach($top as $t){ echo '<tr><td>'.h($t['channel']).'</td><td>'.(int)$t['n'].'</td><td>'.number_format($t['total'],2).'</td></tr>'; } echo '</table></div>';
  $lead_conversion_report = q($db, "
    SELECT 
        c.source, 
        COUNT(DISTINCT c.id) AS total_leads,
        COUNT(DISTINCT d.id) AS total_deals
    FROM contacts c
    LEFT JOIN deals d ON d.contact_id = c.id
    WHERE date(c.created_at) BETWEEN date(?) AND date(?)
    GROUP BY c.source
    ORDER BY total_leads DESC
  ", [$start, $end])->fetchAll(PDO::FETCH_ASSOC);

  echo '<div class="card"><h3>Phân tích Chuyển đổi Lead-to-Deal theo Nguồn</h3>';
  echo '<table><tr><th>Nguồn (Source)</th><th>Tổng Leads</th><th>Tổng Deals</th><th>Tỷ lệ L2D (%)</th></tr>';
  foreach($lead_conversion_report as $r){
    $l2d = ((int)$r['total_leads'] > 0) ? ((float)$r['total_deals'] / (float)$r['total_leads'] * 100.0) : 0.0;
    echo '<tr>';
    echo '<td>'.h($r['source']).'</td>';
    echo '<td>'.(int)$r['total_leads'].'</td>';
    echo '<td>'.(int)$r['total_deals'].'</td>';
    echo '<td style="font-weight:700">'.number_format($l2d, 2).'%</td>';
    echo '</tr>';
  }
  echo '</table>';
  echo '<div class="hint">Tỷ lệ L2D = Deals / Leads (tính theo Leads tạo trong kỳ, Deals nối với Leads đó).</div>';
  echo '</div>';

  layout_footer();
}

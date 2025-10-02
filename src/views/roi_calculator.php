<?php
// ==========================
// FILE: /views/roi_calculator.php - ROI Calculator & Advanced Reports
// ==========================
function view_roi_calculator($op){
global $db; 
layout_header('ROI Calculator');

// Handle custom calculation
if($op === 'calculate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $investment = (float)($_POST['investment'] ?? 0);
    $revenue = (float)($_POST['revenue'] ?? 0);
    $period_days = (int)($_POST['period_days'] ?? 30);
    $additional_costs = (float)($_POST['additional_costs'] ?? 0);
    
    $total_cost = $investment + $additional_costs;
    $profit = $revenue - $total_cost;
    $roi_percentage = $total_cost > 0 ? (($profit / $total_cost) * 100) : 0;
    $roas = $total_cost > 0 ? ($revenue / $total_cost) : 0;
    $daily_revenue = $period_days > 0 ? ($revenue / $period_days) : 0;
    $break_even_days = $daily_revenue > 0 ? ($total_cost / $daily_revenue) : 0;
    
    echo '<div class="card" style="background:#10b981;color:#fff">';
    echo '<h3>💰 Kết quả tính toán ROI</h3>';
    echo '<div class="grid cols-4">';
    echo '<div><div>ROI</div><div class="kpi">'.number_format($roi_percentage,1).'%</div></div>';
    echo '<div><div>ROAS</div><div class="kpi">'.number_format($roas,2).'x</div></div>';
    echo '<div><div>Profit</div><div class="kpi">'.number_format($profit,0).'đ</div></div>';
    echo '<div><div>Break-even</div><div class="kpi">'.number_format($break_even_days,1).' ngày</div></div>';
    echo '</div></div>';
    
    // ROI analysis
    echo '<div class="card">';
    echo '<h4>📊 Phân tích ROI</h4>';
    echo '<div style="display:grid;gap:16px">';
    
    if($roi_percentage > 50) {
        echo '<div style="padding:12px;background:#10b981;border-radius:8px;color:#fff">✅ <strong>Excellent ROI:</strong> Campaign này có hiệu quả rất tốt với ROI >50%</div>';
    } elseif($roi_percentage > 20) {
        echo '<div style="padding:12px;background:#f59e0b;border-radius:8px;color:#fff">🟡 <strong>Good ROI:</strong> Campaign hiệu quả tốt, có thể tối ưu thêm</div>';
    } elseif($roi_percentage > 0) {
        echo '<div style="padding:12px;background:#ef4444;border-radius:8px;color:#fff">🔴 <strong>Low ROI:</strong> Cần xem xét lại chiến lược</div>';
    } else {
        echo '<div style="padding:12px;background:#7f1d1d;border-radius:8px;color:#fff">❌ <strong>Negative ROI:</strong> Campaign đang lỗ, cần dừng hoặc điều chỉnh ngay</div>';
    }
    
    echo '<div style="background:#111827;padding:16px;border-radius:8px">';
    echo '<h5>Breakdown chi tiết:</h5>';
    echo '<div style="display:grid;gap:8px">';
    echo '<div>• Tổng đầu tư: '.number_format($total_cost,0).'đ</div>';
    echo '<div>• Tổng doanh thu: '.number_format($revenue,0).'đ</div>';
    echo '<div>• Lợi nhuận: '.number_format($profit,0).'đ</div>';
    echo '<div>• Doanh thu/ngày: '.number_format($daily_revenue,0).'đ</div>';
    echo '<div>• Thời gian hoàn vốn: '.number_format($break_even_days,1).' ngày</div>';
    echo '</div></div>';
    
    echo '</div></div>';
}

// Campaign ROI Analysis
if($op === '' || $op === 'campaigns') {
    echo '<div class="card" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff">';
    echo '<h2>💰 ROI Calculator & Advanced Reports</h2>';
    echo '<p>Phân tích ROI chi tiết và báo cáo hiệu quả chiến dịch marketing</p>';
    echo '</div>';
    
    // Campaign ROI Overview
    $campaign_roi = q($db, "SELECT 
        c.id, c.name, c.budget, c.spent,
        COALESCE(SUM(cm.cost), 0) as total_cost,
        COALESCE(SUM(cm.revenue), 0) as total_revenue,
        COALESCE(SUM(cm.conversions), 0) as total_conversions,
        COALESCE(AVG(cm.roas), 0) as avg_roas
        FROM campaigns c 
        LEFT JOIN campaign_metrics cm ON c.id = cm.campaign_id 
        GROUP BY c.id, c.name, c.budget, c.spent 
        ORDER BY total_revenue DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    if($campaign_roi) {
        echo '<div class="card">';
        echo '<h4>📈 Campaign ROI Analysis</h4>';
        echo '<table>';
        echo '<tr><th>Campaign</th><th>Budget/Spend</th><th>Cost</th><th>Revenue</th><th>Profit</th><th>ROI</th><th>ROAS</th><th>Conversions</th></tr>';
        
        $total_cost = 0; $total_revenue = 0; $total_conversions = 0;
        
        foreach($campaign_roi as $campaign) {
            $cost = $campaign['total_cost'] ?: $campaign['spent'];
            $revenue = $campaign['total_revenue'];
            $profit = $revenue - $cost;
            $roi = $cost > 0 ? (($profit / $cost) * 100) : 0;
            $roas = $campaign['avg_roas'] ?: ($cost > 0 ? ($revenue / $cost) : 0);
            
            $total_cost += $cost;
            $total_revenue += $revenue;
            $total_conversions += $campaign['total_conversions'];
            
            echo '<tr>';
            echo '<td><strong>'.h($campaign['name']).'</strong></td>';
            echo '<td>'.number_format($campaign['budget'],0).'đ<br><small>Spent: '.number_format($cost,0).'đ</small></td>';
            echo '<td>'.number_format($cost,0).'đ</td>';
            echo '<td>'.number_format($revenue,0).'đ</td>';
            echo '<td style="color:'.($profit>=0?'#10b981':'#ef4444').'">'.number_format($profit,0).'đ</td>';
            echo '<td style="color:'.($roi>=20?'#10b981':($roi>=0?'#f59e0b':'#ef4444')).'">'.number_format($roi,1).'%</td>';
            echo '<td style="color:'.($roas>=2?'#10b981':($roas>=1?'#f59e0b':'#ef4444')).'">'.number_format($roas,2).'x</td>';
            echo '<td>'.number_format($campaign['total_conversions']).'</td>';
            echo '</tr>';
        }
        
        // Summary row
        $total_profit = $total_revenue - $total_cost;
        $total_roi = $total_cost > 0 ? (($total_profit / $total_cost) * 100) : 0;
        $total_roas = $total_cost > 0 ? ($total_revenue / $total_cost) : 0;
        
        echo '<tr style="background:#111827;font-weight:bold">';
        echo '<td>TỔNG CỘNG</td><td>-</td>';
        echo '<td>'.number_format($total_cost,0).'đ</td>';
        echo '<td>'.number_format($total_revenue,0).'đ</td>';
        echo '<td style="color:'.($total_profit>=0?'#10b981':'#ef4444').'">'.number_format($total_profit,0).'đ</td>';
        echo '<td style="color:'.($total_roi>=20?'#10b981':($total_roi>=0?'#f59e0b':'#ef4444')).'">'.number_format($total_roi,1).'%</td>';
        echo '<td style="color:'.($total_roas>=2?'#10b981':($total_roas>=1?'#f59e0b':'#ef4444')).'">'.number_format($total_roas,2).'x</td>';
        echo '<td>'.number_format($total_conversions).'</td>';
        echo '</tr>';
        echo '</table></div>';
    }
    
    // Quick ROI Calculator
    echo '<div class="card">';
    echo '<h4>🧮 Quick ROI Calculator</h4>';
    echo '<form method="post" action="?action=roi_calculator&op=calculate">';
    echo '<div class="grid cols-2" style="gap:16px">';
    echo '<div>';
    echo '<div style="display:grid;gap:12px">';
    echo '<div><label>Tổng đầu tư (Investment)</label><input name="investment" type="number" step="0.01" placeholder="1000000" required></div>';
    echo '<div><label>Doanh thu (Revenue)</label><input name="revenue" type="number" step="0.01" placeholder="1500000" required></div>';
    echo '<div><label>Chi phí khác (Additional Costs)</label><input name="additional_costs" type="number" step="0.01" placeholder="0" value="0"></div>';
    echo '<div><label>Thời gian (ngày)</label><input name="period_days" type="number" min="1" value="30"></div>';
    echo '</div>';
    echo '</div>';
    echo '<div style="background:#111827;padding:16px;border-radius:8px">';
    echo '<h5>💡 ROI Benchmarks</h5>';
    echo '<div style="font-size:14px;line-height:1.6">';
    echo '<div>• <strong>Excellent:</strong> ROI > 50% (ROAS > 1.5x)</div>';
    echo '<div>• <strong>Good:</strong> ROI 20-50% (ROAS 1.2-1.5x)</div>';
    echo '<div>• <strong>Average:</strong> ROI 5-20% (ROAS 1.05-1.2x)</div>';
    echo '<div>• <strong>Poor:</strong> ROI 0-5% (ROAS 1.0-1.05x)</div>';
    echo '<div>• <strong>Loss:</strong> ROI < 0% (ROAS < 1.0x)</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div style="margin-top:16px">';
    echo '<button class="btn">Tính ROI</button>';
    echo '</div>';
    echo '</form></div>';
}

// Advanced Reports
if($op === 'reports') {
    $date_from = $_GET['from'] ?? date('Y-m-01'); // First day of current month
    $date_to = $_GET['to'] ?? date('Y-m-d');
    
    echo '<div class="card" style="background:linear-gradient(135deg,#6366f1,#4338ca);color:#fff">';
    echo '<h2>📊 Advanced Reports</h2>';
    echo '<p>Báo cáo chi tiết với khả năng filter theo thời gian và export dữ liệu</p>';
    echo '</div>';
    
    // Date Range Filter
    echo '<div class="card">';
    echo '<h4>📅 Chọn thời gian báo cáo</h4>';
    echo '<form method="get" style="display:flex;gap:12px;align-items:end">';
    echo '<input type="hidden" name="action" value="roi_calculator">';
    echo '<input type="hidden" name="op" value="reports">';
    echo '<div><label>Từ ngày</label><input type="date" name="from" value="'.$date_from.'"></div>';
    echo '<div><label>Đến ngày</label><input type="date" name="to" value="'.$date_to.'"></div>';
    echo '<div><button class="btn">Lọc</button></div>';
    echo '<div><a class="btn secondary" href="?action=roi_calculator&op=export&from='.$date_from.'&to='.$date_to.'">Export CSV</a></div>';
    echo '</form></div>';
    
    // Performance Report
    $performance_data = q($db, "SELECT 
        DATE(cm.date) as report_date,
        SUM(cm.impressions) as total_impressions,
        SUM(cm.clicks) as total_clicks,
        SUM(cm.conversions) as total_conversions,
        SUM(cm.cost) as total_cost,
        SUM(cm.revenue) as total_revenue,
        AVG(cm.ctr) as avg_ctr,
        AVG(cm.cpc) as avg_cpc,
        AVG(cm.roas) as avg_roas
        FROM campaign_metrics cm 
        WHERE cm.date BETWEEN ? AND ?
        GROUP BY DATE(cm.date) 
        ORDER BY report_date DESC", [$date_from, $date_to])->fetchAll(PDO::FETCH_ASSOC);
    
    if($performance_data) {
        echo '<div class="card">';
        echo '<h4>📈 Performance Report ('.$date_from.' → '.$date_to.')</h4>';
        echo '<table>';
        echo '<tr><th>Date</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Conversions</th><th>CPC</th><th>Cost</th><th>Revenue</th><th>ROAS</th><th>Profit</th></tr>';
        
        $total_impr = 0; $total_clicks = 0; $total_conv = 0; $total_cost = 0; $total_rev = 0;
        
        foreach($performance_data as $row) {
            $profit = $row['total_revenue'] - $row['total_cost'];
            $total_impr += $row['total_impressions'];
            $total_clicks += $row['total_clicks'];
            $total_conv += $row['total_conversions'];
            $total_cost += $row['total_cost'];
            $total_rev += $row['total_revenue'];
            
            echo '<tr>';
            echo '<td>'.date('d/m/Y', strtotime($row['report_date'])).'</td>';
            echo '<td>'.number_format($row['total_impressions']).'</td>';
            echo '<td>'.number_format($row['total_clicks']).'</td>';
            echo '<td>'.number_format($row['avg_ctr'],2).'%</td>';
            echo '<td>'.number_format($row['total_conversions']).'</td>';
            echo '<td>'.number_format($row['avg_cpc'],0).'đ</td>';
            echo '<td>'.number_format($row['total_cost'],0).'đ</td>';
            echo '<td>'.number_format($row['total_revenue'],0).'đ</td>';
            echo '<td>'.number_format($row['avg_roas'],2).'x</td>';
            echo '<td style="color:'.($profit>=0?'#10b981':'#ef4444').'">'.number_format($profit,0).'đ</td>';
            echo '</tr>';
        }
        
        // Summary
        $total_profit = $total_rev - $total_cost;
        $total_ctr = $total_impr > 0 ? (($total_clicks / $total_impr) * 100) : 0;
        $total_cpc = $total_clicks > 0 ? ($total_cost / $total_clicks) : 0;
        $total_roas = $total_cost > 0 ? ($total_rev / $total_cost) : 0;
        
        echo '<tr style="background:#111827;font-weight:bold">';
        echo '<td>TOTAL</td>';
        echo '<td>'.number_format($total_impr).'</td>';
        echo '<td>'.number_format($total_clicks).'</td>';
        echo '<td>'.number_format($total_ctr,2).'%</td>';
        echo '<td>'.number_format($total_conv).'</td>';
        echo '<td>'.number_format($total_cpc,0).'đ</td>';
        echo '<td>'.number_format($total_cost,0).'đ</td>';
        echo '<td>'.number_format($total_rev,0).'đ</td>';
        echo '<td>'.number_format($total_roas,2).'x</td>';
        echo '<td style="color:'.($total_profit>=0?'#10b981':'#ef4444').'">'.number_format($total_profit,0).'đ</td>';
        echo '</tr>';
        echo '</table></div>';
    }
}

// Export CSV
if($op === 'export') {
    $date_from = $_GET['from'] ?? date('Y-m-01');
    $date_to = $_GET['to'] ?? date('Y-m-d');
    
    $export_data = q($db, "SELECT 
        c.name as campaign_name,
        cm.date,
        cm.impressions,
        cm.clicks,
        cm.conversions,
        cm.cost,
        cm.revenue,
        cm.ctr,
        cm.cpc,
        cm.roas,
        (cm.revenue - cm.cost) as profit
        FROM campaign_metrics cm 
        LEFT JOIN campaigns c ON cm.campaign_id = c.id
        WHERE cm.date BETWEEN ? AND ?
        ORDER BY cm.date DESC, c.name", [$date_from, $date_to])->fetchAll(PDO::FETCH_ASSOC);
    
    if($export_data) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="campaign_report_'.$date_from.'_'.$date_to.'.csv"');
        
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo "Campaign,Date,Impressions,Clicks,Conversions,Cost,Revenue,CTR,CPC,ROAS,Profit\n";
        
        foreach($export_data as $row) {
            echo '"'.str_replace('"', '""', $row['campaign_name']).'",';
            echo $row['date'].',';
            echo $row['impressions'].',';
            echo $row['clicks'].',';
            echo $row['conversions'].',';
            echo $row['cost'].',';
            echo $row['revenue'].',';
            echo $row['ctr'].',';
            echo $row['cpc'].',';
            echo $row['roas'].',';
            echo $row['profit']."\n";
        }
        exit;
    }
}

// Navigation
echo '<div class="card">';
echo '<div style="display:flex;gap:8px">';
echo '<a class="btn '.($op===''||$op==='campaigns'?'':'secondary').'" href="?action=roi_calculator">Campaign ROI</a>';
echo '<a class="btn '.($op==='reports'?'':'secondary').'" href="?action=roi_calculator&op=reports">Advanced Reports</a>';
echo '</div></div>';

if(!empty($_SESSION['flash'])){
    echo '<div class="card" style="background:#059669;color:#fff">'.h($_SESSION['flash']).'</div>';
    unset($_SESSION['flash']);
}

layout_footer();
}
?>
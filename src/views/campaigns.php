<?php
// ==========================
// FILE: /views/campaigns.php - Advanced Campaign Analytics
// ==========================
function view_campaigns($op){
global $db; 

// Download sample CSV - handle before any output
if($op==='download_sample'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="campaign_import_sample.csv"');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo "name,channel,budget,spent,status,start_date,end_date,notes\n";
    echo "Campaign Google Ads Q4,google,5000000,2500000,Active,2025-10-01,2025-12-31,Test campaign for Q4\n";
    echo "Campaign Facebook Lead,facebook,3000000,1800000,Active,2025-10-01,2025-11-30,Lead generation campaign\n";
    echo "Campaign TikTok Video,tiktok,2000000,800000,Paused,2025-09-15,2025-10-15,Video marketing campaign\n";
    exit;
}

layout_header('Quản lý Chiến dịch');

// Xử lý các action
if ($op==='create' && $_SERVER['REQUEST_METHOD']==='POST'){ 
    require_csrf(); 
    q($db, "INSERT INTO campaigns(name,channel,budget,spent,status,start_date,end_date,notes) VALUES(?,?,?,?,?,?,?,?)", [
        $_POST['name']??'',
        $_POST['channel']??'',
        (float)($_POST['budget']??0),
        (float)($_POST['spent']??0),
        $_POST['status']??'Active',
        $_POST['start_date']??null,
        $_POST['end_date']??null,
        $_POST['notes']??''
    ]); 
    header('Location: ?action=campaigns'); 
    exit; 
}

if ($op==='update' && $_SERVER['REQUEST_METHOD']==='POST'){ 
    require_csrf(); 
    q($db, "UPDATE campaigns SET name=?,channel=?,budget=?,spent=?,status=?,start_date=?,end_date=?,notes=? WHERE id=?", [
        $_POST['name']??'',
        $_POST['channel']??'',
        (float)($_POST['budget']??0),
        (float)($_POST['spent']??0),
        $_POST['status']??'Active',
        $_POST['start_date']??null,
        $_POST['end_date']??null,
        $_POST['notes']??'',
        (int)$_POST['id']
    ]); 
    header('Location: ?action=campaigns'); 
    exit; 
}

if ($op==='delete'){ 
    q($db, "DELETE FROM campaigns WHERE id=?", [(int)($_GET['id']??0)]); 
    header('Location: ?action=campaigns'); 
    exit; 
}

// Bulk Import Campaign Data
if($op==='bulk_import'){
    if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv_file'])){
        require_csrf();
        
        $file = $_FILES['csv_file'];
        if($file['error'] === UPLOAD_ERR_OK && pathinfo($file['name'], PATHINFO_EXTENSION) === 'csv'){
            $handle = fopen($file['tmp_name'], 'r');
            
            if($handle !== FALSE){
                $header = fgetcsv($handle); // Skip header row
                $imported = 0;
                $errors = [];
                
                while(($data = fgetcsv($handle)) !== FALSE){
                    if(count($data) >= 8) { // Minimum required columns
                        try {
                            q($db, "INSERT INTO campaigns(name,channel,budget,spent,status,start_date,end_date,notes) VALUES(?,?,?,?,?,?,?,?)", [
                                $data[0] ?? '', // name
                                $data[1] ?? '', // channel  
                                (float)($data[2] ?? 0), // budget
                                (float)($data[3] ?? 0), // spent
                                $data[4] ?? 'Active', // status
                                $data[5] ?? null, // start_date
                                $data[6] ?? null, // end_date
                                $data[7] ?? '' // notes
                            ]);
                            $imported++;
                        } catch(Exception $e) {
                            $errors[] = "Row ".($imported+1).": ".$e->getMessage();
                        }
                    }
                }
                fclose($handle);
                
                if($imported > 0) {
                    $_SESSION['flash'] = "Đã import thành công $imported campaigns";
                } else {
                    $_SESSION['flash'] = "Không có campaign nào được import. ".implode('; ', $errors);
                }
            } else {
                $_SESSION['flash'] = 'Không thể đọc file CSV';
            }
        } else {
            $_SESSION['flash'] = 'Vui lòng chọn file CSV hợp lệ';
        }
        
        header('Location: ?action=campaigns&op=bulk_import');
        exit;
    }
    
    // Show import form
    echo '<div class="card" style="background:linear-gradient(135deg,#059669,#047857);color:#fff">';
    echo '<h2>📤 Bulk Import Campaigns</h2>';
    echo '<p>Import hàng loạt campaign data từ file CSV để tiết kiệm thời gian quản lý</p>';
    echo '</div>';
    
    echo '<div class="card">';
    echo '<h4>📋 CSV Format Requirements</h4>';
    echo '<p>File CSV cần có các cột theo thứ tự sau:</p>';
    echo '<div style="background:#111827;padding:16px;border-radius:8px;margin:16px 0">';
    echo '<code>name,channel,budget,spent,status,start_date,end_date,notes</code>';
    echo '</div>';
    
    echo '<div style="background:#0f172a;padding:16px;border-radius:8px;margin:16px 0">';
    echo '<h5>📖 Mô tả các cột:</h5>';
    echo '<ul style="margin:8px 0;padding-left:20px">';
    echo '<li><strong>name:</strong> Tên campaign (bắt buộc)</li>';
    echo '<li><strong>channel:</strong> Kênh marketing (google, facebook, tiktok...)</li>';
    echo '<li><strong>budget:</strong> Ngân sách (số)</li>';
    echo '<li><strong>spent:</strong> Đã chi tiêu (số)</li>';
    echo '<li><strong>status:</strong> Trạng thái (Active, Paused, Completed)</li>';
    echo '<li><strong>start_date:</strong> Ngày bắt đầu (YYYY-MM-DD)</li>';
    echo '<li><strong>end_date:</strong> Ngày kết thúc (YYYY-MM-DD)</li>';
    echo '<li><strong>notes:</strong> Ghi chú</li>';
    echo '</ul></div>';
    
    echo '<div style="background:#f59e0b;color:#fff;padding:12px;border-radius:8px;margin:16px 0">';
    echo '<h5>💡 Ví dụ CSV:</h5>';
    echo '<pre style="margin:8px 0;font-size:12px">Campaign Google Ads Q4,google,5000000,2500000,Active,2025-10-01,2025-12-31,Test campaign for Q4
Campaign Facebook Lead,facebook,3000000,1800000,Active,2025-10-01,2025-11-30,Lead generation campaign</pre>';
    echo '</div>';
    
    // Download sample CSV
    echo '<div style="margin:16px 0">';
    echo '<a class="btn secondary" href="?action=campaigns&op=download_sample">📥 Download Sample CSV</a>';
    echo '</div>';
    echo '</div>';
    
    // Upload form
    echo '<div class="card">';
    echo '<h4>📤 Upload CSV File</h4>';
    echo '<form method="post" enctype="multipart/form-data">'; csrf_field();
    echo '<div style="display:grid;gap:16px">';
    echo '<div><label>Chọn file CSV</label>';
    echo '<input type="file" name="csv_file" accept=".csv" required>';
    echo '<div class="hint">Chỉ chấp nhận file .csv, tối đa 10MB</div></div>';
    
    echo '<div><button class="btn">Import Campaigns</button>';
    echo ' <a class="btn secondary" href="?action=campaigns">Hủy</a></div>';
    echo '</div></form></div>';
    
    if(!empty($_SESSION['flash'])){
        echo '<div class="card" style="background:#059669;color:#fff">'.h($_SESSION['flash']).'</div>';
        unset($_SESSION['flash']);
    }
    
    layout_footer();
    return;
}

// Campaign Analytics Dashboard
if($op==='analytics'){
    $campaign_id = (int)($_GET['id'] ?? 0);
    $campaign = q($db, "SELECT * FROM campaigns WHERE id=?", [$campaign_id])->fetch(PDO::FETCH_ASSOC);
    
    if(!$campaign) {
        echo '<div class="card" style="color:#f87171">Campaign không tồn tại</div>';
        layout_footer();
        return;
    }
    
    // Campaign Performance Summary
    $metrics = q($db, "SELECT 
        SUM(impressions) as total_impressions,
        SUM(clicks) as total_clicks,
        SUM(conversions) as total_conversions,
        SUM(cost) as total_cost,
        SUM(revenue) as total_revenue,
        AVG(ctr) as avg_ctr,
        AVG(cpc) as avg_cpc,
        AVG(cpa) as avg_cpa,
        AVG(roas) as avg_roas
        FROM campaign_metrics WHERE campaign_id=?", [$campaign_id])->fetch(PDO::FETCH_ASSOC);
    
    $metrics = $metrics ?: [
        'total_impressions' => 0, 'total_clicks' => 0, 'total_conversions' => 0,
        'total_cost' => 0, 'total_revenue' => 0, 'avg_ctr' => 0, 'avg_cpc' => 0, 'avg_cpa' => 0, 'avg_roas' => 0
    ];
    
    echo '<div class="card" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff">';
    echo '<h2>📊 '.h($campaign['name']).'</h2>';
    echo '<div class="grid cols-4" style="margin-top:12px">';
    echo '<div>Kênh: <strong>'.h($campaign['channel']).'</strong></div>';
    echo '<div>Ngân sách: <strong>'.number_format($campaign['budget'],0).'đ</strong></div>';
    echo '<div>Trạng thái: <strong>'.h($campaign['status']).'</strong></div>';
    echo '<div>Thời gian: <strong>'.h($campaign['start_date']).' → '.h($campaign['end_date']).'</strong></div>';
    echo '</div></div>';
    
    // Key Performance Metrics
    echo '<div class="grid cols-4">';
    echo '<div class="card"><div>Lượt hiển thị</div><div class="kpi">'.number_format($metrics['total_impressions']).'</div></div>';
    echo '<div class="card"><div>Lượt click</div><div class="kpi" style="color:#22d3ee">'.number_format($metrics['total_clicks']).'</div></div>';
    echo '<div class="card"><div>Chuyển đổi</div><div class="kpi" style="color:#10b981">'.number_format($metrics['total_conversions']).'</div></div>';
    echo '<div class="card"><div>ROAS</div><div class="kpi" style="color:'.($metrics['avg_roas']>=3?'#10b981':($metrics['avg_roas']>=1?'#fbbf24':'#f87171')).'">'.number_format($metrics['avg_roas'],2).'x</div></div>';
    echo '</div>';
    
    echo '<div class="grid cols-4">';
    echo '<div class="card"><div>Chi phí</div><div class="kpi">'.number_format($metrics['total_cost'],0).'đ</div></div>';
    echo '<div class="card"><div>Doanh thu</div><div class="kpi" style="color:#10b981">'.number_format($metrics['total_revenue'],0).'đ</div></div>';
    echo '<div class="card"><div>CTR</div><div class="kpi">'.number_format($metrics['avg_ctr'],2).'%</div></div>';
    echo '<div class="card"><div>CPC</div><div class="kpi">'.number_format($metrics['avg_cpc'],0).'đ</div></div>';
    echo '</div>';
    
    // Attribution Analysis
    $attributions = q($db, "SELECT 
        utm_source, utm_medium, utm_campaign,
        COUNT(*) as touchpoints,
        SUM(revenue_attributed) as attributed_revenue,
        COUNT(DISTINCT contact_id) as unique_contacts
        FROM attribution_touchpoints 
        WHERE campaign_id=? 
        GROUP BY utm_source, utm_medium, utm_campaign 
        ORDER BY attributed_revenue DESC", [$campaign_id])->fetchAll(PDO::FETCH_ASSOC);
    
    if($attributions) {
        echo '<div class="card"><h4>🎯 Attribution Analysis</h4>';
        echo '<table>';
        echo '<tr><th>Source/Medium</th><th>Touchpoints</th><th>Unique Contacts</th><th>Attributed Revenue</th><th>Revenue per Contact</th></tr>';
        foreach($attributions as $attr) {
            $revenue_per_contact = $attr['unique_contacts'] > 0 ? $attr['attributed_revenue'] / $attr['unique_contacts'] : 0;
            echo '<tr>';
            echo '<td><strong>'.h($attr['utm_source']).'</strong> / '.h($attr['utm_medium']).'</td>';
            echo '<td>'.number_format($attr['touchpoints']).'</td>';
            echo '<td>'.number_format($attr['unique_contacts']).'</td>';
            echo '<td>'.number_format($attr['attributed_revenue'],0).'đ</td>';
            echo '<td>'.number_format($revenue_per_contact,0).'đ</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
    
    // Daily Performance Chart Data (for future chart implementation)
    $daily_metrics = q($db, "SELECT date, impressions, clicks, conversions, cost, revenue 
        FROM campaign_metrics 
        WHERE campaign_id=? 
        ORDER BY date DESC LIMIT 30", [$campaign_id])->fetchAll(PDO::FETCH_ASSOC);
    
    if($daily_metrics) {
        echo '<div class="card"><h4>📈 Performance xu hướng (30 ngày gần nhất)</h4>';
        echo '<table>';
        echo '<tr><th>Ngày</th><th>Impressions</th><th>Clicks</th><th>Conversions</th><th>Chi phí</th><th>Doanh thu</th><th>ROI</th></tr>';
        foreach($daily_metrics as $day) {
            $roi = $day['cost'] > 0 ? (($day['revenue'] - $day['cost']) / $day['cost']) * 100 : 0;
            $roi_color = $roi >= 0 ? '#10b981' : '#f87171';
            echo '<tr>';
            echo '<td>'.date('d/m/Y', strtotime($day['date'])).'</td>';
            echo '<td>'.number_format($day['impressions']).'</td>';
            echo '<td>'.number_format($day['clicks']).'</td>';
            echo '<td>'.number_format($day['conversions']).'</td>';
            echo '<td>'.number_format($day['cost'],0).'đ</td>';
            echo '<td>'.number_format($day['revenue'],0).'đ</td>';
            echo '<td style="color:'.$roi_color.'">'.number_format($roi,1).'%</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
    
    echo '<div class="card">';
    echo '<a class="btn secondary" href="?action=campaigns">← Quay lại danh sách</a> ';
    echo '<a class="btn secondary" href="?action=campaigns&op=add_metrics&id='.$campaign_id.'">Thêm metrics</a> ';
    echo '<a class="btn secondary" href="?action=campaigns&op=ab_test&id='.$campaign_id.'">A/B Test</a>';
    echo '</div>';
    
    layout_footer();
    return;
}

// Add Campaign Metrics
if($op==='add_metrics'){
    $campaign_id = (int)($_GET['id'] ?? 0);
    $campaign = q($db, "SELECT name FROM campaigns WHERE id=?", [$campaign_id])->fetch(PDO::FETCH_ASSOC);
    
    if($_SERVER['REQUEST_METHOD']==='POST'){
        require_csrf();
        q($db, "INSERT INTO campaign_metrics(campaign_id,date,impressions,clicks,conversions,cost,revenue,ctr,cpc,cpa,roas) VALUES(?,?,?,?,?,?,?,?,?,?,?)", [
            $campaign_id,
            $_POST['date'] ?? date('Y-m-d'),
            (int)($_POST['impressions']??0),
            (int)($_POST['clicks']??0),
            (int)($_POST['conversions']??0),
            (float)($_POST['cost']??0),
            (float)($_POST['revenue']??0),
            (float)($_POST['ctr']??0),
            (float)($_POST['cpc']??0),
            (float)($_POST['cpa']??0),
            (float)($_POST['roas']??0)
        ]);
        header('Location: ?action=campaigns&op=analytics&id='.$campaign_id);
        exit;
    }
    
    echo '<div class="card"><h3>📊 Thêm Metrics cho: '.h($campaign['name']).'</h3>';
    echo '<form method="post">'; csrf_field();
    echo '<div class="grid cols-3">';
    echo '<div><label>Ngày *</label><input name="date" type="date" value="'.date('Y-m-d').'" required></div>';
    echo '<div><label>Impressions</label><input name="impressions" type="number" placeholder="10000"></div>';
    echo '<div><label>Clicks</label><input name="clicks" type="number" placeholder="500"></div>';
    echo '<div><label>Conversions</label><input name="conversions" type="number" placeholder="25"></div>';
    echo '<div><label>Chi phí (VND)</label><input name="cost" type="number" step="0.01" placeholder="1000000"></div>';
    echo '<div><label>Doanh thu (VND)</label><input name="revenue" type="number" step="0.01" placeholder="5000000"></div>';
    echo '<div><label>CTR (%)</label><input name="ctr" type="number" step="0.01" placeholder="5.0"></div>';
    echo '<div><label>CPC (VND)</label><input name="cpc" type="number" step="0.01" placeholder="2000"></div>';
    echo '<div><label>CPA (VND)</label><input name="cpa" type="number" step="0.01" placeholder="40000"></div>';
    echo '</div>';
    echo '<div style="margin-top:12px"><button class="btn">Lưu Metrics</button> <a class="btn secondary" href="?action=campaigns&op=analytics&id='.$campaign_id.'">Hủy</a></div>';
    echo '</form></div>';
    layout_footer();
    return;
}

// Main Campaign List with Analytics Overview
if($op==='' || $op==='list'){
    // Overall Campaign Performance
    $overview = q($db, "SELECT 
        COUNT(*) as total_campaigns,
        SUM(budget) as total_budget,
        SUM(spent) as total_spent,
        SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active_campaigns
        FROM campaigns")->fetch(PDO::FETCH_ASSOC);
    
    $total_metrics = q($db, "SELECT 
        SUM(cm.impressions) as total_impressions,
        SUM(cm.clicks) as total_clicks,
        SUM(cm.conversions) as total_conversions,
        SUM(cm.revenue) as total_revenue
        FROM campaign_metrics cm 
        JOIN campaigns c ON c.id = cm.campaign_id")->fetch(PDO::FETCH_ASSOC);
    
    echo '<div class="grid cols-4">';
    echo '<div class="card"><div>Tổng Campaigns</div><div class="kpi">'.(int)$overview['total_campaigns'].'</div></div>';
    echo '<div class="card"><div>Đang chạy</div><div class="kpi" style="color:#22d3ee">'.(int)$overview['active_campaigns'].'</div></div>';
    echo '<div class="card"><div>Tổng ngân sách</div><div class="kpi">'.number_format($overview['total_budget'],0).'đ</div></div>';
    echo '<div class="card"><div>Đã sử dụng</div><div class="kpi">'.number_format($overview['total_spent'],0).'đ</div></div>';
    echo '</div>';
    
    if($total_metrics['total_impressions']) {
        echo '<div class="grid cols-4">';
        echo '<div class="card"><div>Tổng Impressions</div><div class="kpi">'.number_format($total_metrics['total_impressions']).'</div></div>';
        echo '<div class="card"><div>Tổng Clicks</div><div class="kpi">'.number_format($total_metrics['total_clicks']).'</div></div>';
        echo '<div class="card"><div>Tổng Conversions</div><div class="kpi" style="color:#10b981">'.number_format($total_metrics['total_conversions']).'</div></div>';
        echo '<div class="card"><div>Tổng Revenue</div><div class="kpi" style="color:#10b981">'.number_format($total_metrics['total_revenue'],0).'đ</div></div>';
        echo '</div>';
    }
    
    echo '<div class="card" style="display:flex;gap:8px;align-items:center;justify-content:space-between">';
    echo '<div><a class="btn" href="?action=campaigns&op=new">Tạo Campaign</a> <a class="btn secondary" href="?action=campaigns&op=bulk_import">Import Data</a></div>';
    echo '<div>Filter: <select onchange="location.href=\'?action=campaigns&status=\'+this.value">';
    echo '<option value="">Tất cả</option><option value="Active">Đang chạy</option><option value="Paused">Tạm dừng</option><option value="Completed">Hoàn thành</option>';
    echo '</select></div></div>';

    // Campaign List with Performance Preview
    $where = [];
    $params = [];
    if(!empty($_GET['status'])){
        $where[] = 'c.status = ?';
        $params[] = $_GET['status'];
    }
    
    $sql = 'SELECT c.*, 
        COALESCE(SUM(cm.impressions),0) as total_impressions,
        COALESCE(SUM(cm.clicks),0) as total_clicks,
        COALESCE(SUM(cm.conversions),0) as total_conversions,
        COALESCE(SUM(cm.revenue),0) as total_revenue,
        COALESCE(AVG(cm.roas),0) as avg_roas
        FROM campaigns c 
        LEFT JOIN campaign_metrics cm ON cm.campaign_id = c.id';
    if($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' GROUP BY c.id ORDER BY c.created_at DESC LIMIT 100';
    
    $rows = q($db, $sql, $params)->fetchAll(PDO::FETCH_ASSOC);

    echo '<div class="card"><table>';
    echo '<tr><th>Campaign</th><th>Kênh/Trạng thái</th><th>Ngân sách</th><th>Performance</th><th>ROAS</th><th>Thời gian</th><th></th></tr>';
    foreach($rows as $r){
        $status_color = $r['status'] === 'Active' ? '#22d3ee' : ($r['status'] === 'Paused' ? '#fbbf24' : '#94a3b8');
        $roas_color = $r['avg_roas'] >= 3 ? '#10b981' : ($r['avg_roas'] >= 1 ? '#fbbf24' : '#f87171');
        
        echo '<tr>';
        echo '<td><strong><a href="?action=campaigns&op=analytics&id='.(int)$r['id'].'">'.h($r['name']).'</a></strong></td>';
        echo '<td>'.h($r['channel']).'<br><span style="color:'.$status_color.'">'.h($r['status']).'</span></td>';
        echo '<td>'.number_format($r['budget'],0).'đ<br><small>Spent: '.number_format($r['spent'],0).'đ</small></td>';
        echo '<td>';
        if($r['total_impressions']) {
            echo number_format($r['total_impressions']).' imp<br>';
            echo number_format($r['total_clicks']).' clicks<br>';
            echo '<span style="color:#10b981">'.number_format($r['total_conversions']).' conv</span>';
        } else {
            echo '<em>Chưa có data</em>';
        }
        echo '</td>';
        echo '<td style="color:'.$roas_color.'">'.($r['avg_roas'] ? number_format($r['avg_roas'],2).'x' : '-').'</td>';
        echo '<td>'.date('d/m/Y', strtotime($r['start_date'])).'<br>→ '.date('d/m/Y', strtotime($r['end_date'])).'</td>';
        echo '<td>';
        echo '<a href="?action=campaigns&op=analytics&id='.(int)$r['id'].'">Analytics</a><br>';
        echo '<a href="?action=campaigns&op=edit&id='.(int)$r['id'].'">Sửa</a> · ';
        echo '<a href="?action=campaigns&op=delete&id='.(int)$r['id'].'" onclick="return confirm(\'Xóa campaign?\')">Xóa</a>';
        echo '</td></tr>';
    }
    echo '</table></div>';
}

// Form New/Edit Campaign (Enhanced)
if($op==='new'||$op==='edit'){ 
    $c = ['id'=>'','name'=>'','channel'=>'','budget'=>'','spent'=>'','status'=>'Active','start_date'=>'','end_date'=>'','notes'=>''];
    if($op==='edit'){ 
        $c = q($db,'SELECT * FROM campaigns WHERE id=?',[(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC)?:$c; 
    }

    echo '<div class="card"><h3>'.($op==='new'?'Tạo Campaign Mới':'Chỉnh sửa Campaign').'</h3>';
    echo '<form method="post" action="?action=campaigns&op='.($op==='new'?'create':'update').'">';
    csrf_field(); 
    if($op==='edit') echo '<input type="hidden" name="id" value="'.h($c['id']).'">';
    
    echo '<div class="grid cols-3">';
    echo '<div><label>Tên Campaign *</label><input name="name" required value="'.h($c['name']).'" placeholder="Black Friday 2025 - Facebook Ads"></div>';
    echo '<div><label>Kênh</label><select name="channel">';
    foreach(['google'=>'Google Ads','facebook'=>'Facebook Ads','tiktok'=>'TikTok Ads','email'=>'Email Marketing','seo'=>'SEO Organic','influencer'=>'Influencer Marketing','affiliate'=>'Affiliate','display'=>'Display Ads'] as $ch=>$label){
        $sel = $c['channel']===$ch?'selected':'';
        echo '<option '.h($sel).' value="'.h($ch).'">'.h($label).'</option>';
    }
    echo '</select></div>';
    echo '<div><label>Trạng thái</label><select name="status">';
    foreach(['Active'=>'Đang chạy','Paused'=>'Tạm dừng','Completed'=>'Hoàn thành'] as $st=>$label){
        $sel = $c['status']===$st?'selected':'';
        echo '<option '.h($sel).' value="'.h($st).'">'.h($label).'</option>';
    }
    echo '</select></div>';
    echo '</div>';
    
    echo '<div class="grid cols-3">';
    echo '<div><label>Ngân sách (VND)</label><input name="budget" type="number" step="0.01" value="'.h($c['budget']).'" placeholder="10000000"></div>';
    echo '<div><label>Đã chi (VND)</label><input name="spent" type="number" step="0.01" value="'.h($c['spent']).'" placeholder="2500000"></div>';
    echo '<div></div>';
    echo '</div>';
    
    echo '<div class="grid cols-2">';
    echo '<div><label>Ngày bắt đầu</label><input name="start_date" type="date" value="'.h($c['start_date']).'"></div>';
    echo '<div><label>Ngày kết thúc</label><input name="end_date" type="date" value="'.h($c['end_date']).'"></div>';
    echo '</div>';
    
    echo '<div><label>Ghi chú Campaign</label><textarea name="notes" rows="3" placeholder="Mô tả chi tiết về campaign, target audience, creative strategy...">'.h($c['notes']).'</textarea></div>';
    
    echo '<div style="margin-top:20px"><button class="btn">'.($op==='new'?'Tạo Campaign':'Cập nhật').'</button> <a class="btn secondary" href="?action=campaigns">Hủy</a></div>';
    echo '</form></div>';
}

layout_footer();
}
?>
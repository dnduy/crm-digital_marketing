<?php
// ==========================
// FILE: /views/dashboard.php
// ==========================
function view_dashboard(){ 
global $db; 
layout_header('Bảng điều khiển'); 

// CRM Metrics
$k_contacts=(int)q($db,"SELECT COUNT(*) FROM contacts")->fetchColumn(); 
$sum_pipeline=(float)q($db,"SELECT IFNULL(SUM(value),0) FROM deals WHERE stage IN ('New','Qualified','Proposal')")->fetchColumn(); 
$sum_won=(float)q($db,"SELECT IFNULL(SUM(value),0) FROM deals WHERE stage='Won'")->fetchColumn(); 

// SEO & Content Metrics
$content_pages=(int)q($db,"SELECT COUNT(*) FROM content_pages WHERE status='published'")->fetchColumn();
$avg_seo_score=(float)q($db,"SELECT IFNULL(AVG(seo_score),0) FROM content_pages WHERE status='published'")->fetchColumn();
$total_keywords=(int)q($db,"SELECT COUNT(*) FROM keywords_tracking")->fetchColumn();
$top10_keywords=(int)q($db,"SELECT COUNT(*) FROM keywords_tracking WHERE current_rank IS NOT NULL AND current_rank <= 10")->fetchColumn();

echo '<h3>📊 CRM Performance</h3>';
echo '<div class="grid cols-3">'; 
echo '<div class="card"><div>Tổng liên hệ</div><div class="kpi">'.number_format($k_contacts).'</div></div>'; 
echo '<div class="card"><div>Pipeline mở</div><div class="kpi">'.number_format($sum_pipeline,2).'</div></div>'; 
echo '<div class="card"><div>Doanh thu thắng</div><div class="kpi">'.number_format($sum_won,2).'</div></div>'; 
echo '</div>'; 

echo '<h3 style="margin-top:20px">🔍 SEO & Content Performance</h3>';
echo '<div class="grid cols-4">'; 
echo '<div class="card"><div>Content đã xuất bản</div><div class="kpi">'.$content_pages.'</div></div>'; 
echo '<div class="card"><div>SEO Score TB</div><div class="kpi" style="color:'.($avg_seo_score>=80?'#22d3ee':($avg_seo_score>=60?'#fbbf24':'#f87171')).'">'.round($avg_seo_score,1).'/100</div></div>'; 
echo '<div class="card"><div>Từ khóa theo dõi</div><div class="kpi">'.$total_keywords.'</div></div>'; 
echo '<div class="card"><div>Top 10 Rankings</div><div class="kpi" style="color:#22d3ee">'.$top10_keywords.'</div></div>'; 
echo '</div>'; 

echo '<div class="card">Tạo nhanh: <a class="btn" href="?action=contacts&op=new">Liên hệ</a> <a class="btn" href="?action=deals&op=new">Giao dịch</a> <a class="btn" href="?action=campaigns&op=new">Chiến dịch</a> <a class="btn" href="?action=content&op=new">Content</a></div>'; 

// Recent content activity
$recent_content = q($db,"SELECT title, status, seo_score, updated_at FROM content_pages ORDER BY updated_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if($recent_content) {
    echo '<div class="card"><h4>📝 Content gần đây</h4><table>';
    echo '<tr><th>Tiêu đề</th><th>Trạng thái</th><th>SEO Score</th><th>Cập nhật</th></tr>';
    foreach($recent_content as $c) {
        $status_color = $c['status'] === 'published' ? '#22d3ee' : ($c['status'] === 'draft' ? '#fbbf24' : '#94a3b8');
        $seo_color = $c['seo_score'] >= 80 ? '#22d3ee' : ($c['seo_score'] >= 60 ? '#fbbf24' : '#f87171');
        echo '<tr>';
        echo '<td>'.h($c['title']).'</td>';
        echo '<td style="color:'.$status_color.'">'.h($c['status']).'</td>';
        echo '<td style="color:'.$seo_color.'">'.(int)$c['seo_score'].'/100</td>';
        echo '<td>'.date('d/m H:i', strtotime($c['updated_at'])).'</td>';
        echo '</tr>';
    }
    echo '</table></div>';
}

// Secret webhook hiển thị cho Admin
if(($_SESSION['role']??'')==='Admin'){ 
    $sec = setting_get('webhook_secret',''); 
    if(!$sec){ 
        $sec=bin2hex(random_bytes(16)); 
        setting_set('webhook_secret',$sec);
    } 
    $url = (isset($_SERVER['HTTPS'])?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').dirname($_SERVER['REQUEST_URI'] ?: '/').'/?action=webhook&token='.$sec; 
    echo '<div class="card"><strong>🔗 Webhook Lead Intake</strong><div class="hint">POST JSON/form đến URL này để đẩy lead vào CRM</div><div style="word-break:break-all">'.h($url).'</div></div>';
    
    // SEO Tools for Admin
    echo '<div class="card"><strong>🛠️ SEO Tools</strong><div style="margin-top:8px">';
    echo '<a class="btn secondary" href="/sitemap.xml" target="_blank">Sitemap XML</a> ';
    echo '<a class="btn secondary" href="/robots.txt" target="_blank">Robots.txt</a> ';
    echo '<a class="btn secondary" href="?action=content&op=seo_settings">Cài đặt SEO</a>';
    echo '</div></div>';
}

layout_footer(); 
}
?>
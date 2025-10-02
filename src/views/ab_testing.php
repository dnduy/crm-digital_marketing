<?php
// ==========================
// FILE: /views/ab_testing.php - A/B Testing Management
// ==========================
function view_ab_testing($op){
global $db; 

// Process form submissions BEFORE any output to avoid header issues
if($op === 'new' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $campaign_id = $_POST['campaign_id'] ?? '';
    $test_name = $_POST['test_name'] ?? '';
    $hypothesis = $_POST['hypothesis'] ?? '';
    $variable_tested = $_POST['variable_tested'] ?? '';
    $control_value = $_POST['control_value'] ?? '';
    $variant_value = $_POST['variant_value'] ?? '';
    $sample_size = (int)($_POST['sample_size'] ?? 1000);
    $confidence_level = $_POST['confidence_level'] ?? '95';
    
    if($test_name && $campaign_id) {
        q($db, "INSERT INTO ab_tests(campaign_id,test_name,hypothesis,variable_tested,control_value,variant_value,sample_size,confidence_level,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)", [
            $campaign_id, $test_name, $hypothesis, $variable_tested, $control_value, $variant_value, $sample_size, $confidence_level, 'setup', date('Y-m-d H:i:s')
        ]);
        
        $_SESSION['flash'] = "A/B test '$test_name' đã được tạo";
        header('Location: ?action=ab_testing');
        exit;
    }
    
    $error = 'Vui lòng điền đầy đủ thông tin bắt buộc';
}

// Update test results
if($op === 'update_results' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $test_id = $_POST['test_id'] ?? '';
    $control_conversions = (int)($_POST['control_conversions'] ?? 0);
    $variant_conversions = (int)($_POST['variant_conversions'] ?? 0);
    $control_participants = (int)($_POST['control_participants'] ?? 0);
    $variant_participants = (int)($_POST['variant_participants'] ?? 0);
    
    if($test_id) {
        // Calculate conversion rates
        $control_rate = $control_participants > 0 ? ($control_conversions / $control_participants) * 100 : 0;
        $variant_rate = $variant_participants > 0 ? ($variant_conversions / $variant_participants) * 100 : 0;
        
        // Simple statistical significance calculation (Z-test)
        $pooled_rate = ($control_conversions + $variant_conversions) / ($control_participants + $variant_participants);
        $se = sqrt($pooled_rate * (1 - $pooled_rate) * (1/$control_participants + 1/$variant_participants));
        $z_score = $se > 0 ? abs(($variant_rate/100 - $control_rate/100) / $se) : 0;
        $is_significant = $z_score > 1.96; // 95% confidence
        
        $status = $is_significant ? 'completed' : 'running';
        $winner = '';
        if($is_significant) {
            $winner = $variant_rate > $control_rate ? 'variant' : 'control';
        }
        
        q($db, "UPDATE ab_tests SET control_conversions=?, variant_conversions=?, control_participants=?, variant_participants=?, control_rate=?, variant_rate=?, z_score=?, is_significant=?, status=?, winner=?, updated_at=? WHERE id=?", [
            $control_conversions, $variant_conversions, $control_participants, $variant_participants, 
            $control_rate, $variant_rate, $z_score, $is_significant ? 1 : 0, $status, $winner, date('Y-m-d H:i:s'), $test_id
        ]);
        
        $_SESSION['flash'] = 'Kết quả A/B test đã được cập nhật';
    }
    
    header('Location: ?action=ab_testing');
    exit;
}

// Now start HTML output
layout_header('A/B Testing');

// Create new A/B test
if($op === 'new') {
    $campaigns = q($db, "SELECT id, name FROM campaigns ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="card">';
    echo '<h3>🧪 Tạo A/B Test mới</h3>';
    if(isset($error)) echo '<div style="color:#ef4444;margin-bottom:12px">'.$error.'</div>';
    
    echo '<form method="post">'; csrf_field();
    echo '<div style="display:grid;gap:12px">';
    
    echo '<div><label>Campaign</label>';
    echo '<select name="campaign_id" required>';
    echo '<option value="">Chọn campaign...</option>';
    foreach($campaigns as $campaign) {
        echo '<option value="'.$campaign['id'].'">'.h($campaign['name']).'</option>';
    }
    echo '</select></div>';
    
    echo '<div><label>Tên A/B Test</label>';
    echo '<input name="test_name" placeholder="Ví dụ: Landing Page Headline Test" required></div>';
    
    echo '<div><label>Giả thuyết (Hypothesis)</label>';
    echo '<textarea name="hypothesis" placeholder="Ví dụ: Thay đổi headline sẽ tăng conversion rate 15%"></textarea></div>';
    
    echo '<div><label>Variable được test</label>';
    echo '<select name="variable_tested">';
    echo '<option value="headline">Headline</option>';
    echo '<option value="cta_button">CTA Button</option>';
    echo '<option value="image">Hình ảnh</option>';
    echo '<option value="landing_page">Landing Page</option>';
    echo '<option value="ad_creative">Ad Creative</option>';
    echo '<option value="audience">Audience</option>';
    echo '<option value="other">Khác</option>';
    echo '</select></div>';
    
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
    echo '<div><label>Control Value (A)</label>';
    echo '<input name="control_value" placeholder="Giá trị hiện tại"></div>';
    echo '<div><label>Variant Value (B)</label>';
    echo '<input name="variant_value" placeholder="Giá trị test"></div>';
    echo '</div>';
    
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
    echo '<div><label>Sample Size</label>';
    echo '<input name="sample_size" type="number" value="1000" min="100"></div>';
    echo '<div><label>Confidence Level</label>';
    echo '<select name="confidence_level">';
    echo '<option value="90">90%</option>';
    echo '<option value="95" selected>95%</option>';
    echo '<option value="99">99%</option>';
    echo '</select></div>';
    echo '</div>';
    
    echo '<div><button class="btn">Tạo A/B Test</button> <a class="btn secondary" href="?action=ab_testing">Hủy</a></div>';
    echo '</div></form></div>';
    
    layout_footer();
    return;
}

// Test details
if($op === 'view' && isset($_GET['id'])) {
    $test_id = (int)$_GET['id'];
    $test = q($db, "SELECT t.*, c.name as campaign_name FROM ab_tests t LEFT JOIN campaigns c ON t.campaign_id=c.id WHERE t.id=?", [$test_id])->fetch(PDO::FETCH_ASSOC);
    
    if(!$test) {
        echo '<div class="card">Test không tồn tại</div>';
        layout_footer();
        return;
    }
    
    echo '<div class="card">';
    echo '<h3>🧪 '.h($test['test_name']).'</h3>';
    echo '<div style="margin-bottom:16px">';
    echo '<strong>Campaign:</strong> '.h($test['campaign_name']).' | ';
    echo '<strong>Status:</strong> <span style="color:'.($test['status']==='completed'?'#10b981':($test['status']==='running'?'#f59e0b':'#6b7280')).'">'.ucfirst($test['status']).'</span> | ';
    echo '<strong>Variable:</strong> '.h($test['variable_tested']);
    echo '</div>';
    
    if($test['hypothesis']) {
        echo '<div style="background:#111827;padding:12px;border-radius:8px;margin-bottom:16px">';
        echo '<strong>Hypothesis:</strong> '.h($test['hypothesis']);
        echo '</div>';
    }
    
    // Results form
    echo '<form method="post" action="?action=ab_testing&op=update_results">'; csrf_field();
    echo '<input type="hidden" name="test_id" value="'.$test['id'].'">';
    
    echo '<div class="grid cols-2" style="margin-bottom:16px">';
    echo '<div style="background:#111827;padding:16px;border-radius:8px">';
    echo '<h4>Control (A): '.h($test['control_value']).'</h4>';
    echo '<div style="display:grid;gap:8px">';
    echo '<div><label>Participants</label><input name="control_participants" type="number" value="'.$test['control_participants'].'" min="0"></div>';
    echo '<div><label>Conversions</label><input name="control_conversions" type="number" value="'.$test['control_conversions'].'" min="0"></div>';
    if($test['control_rate'] > 0) echo '<div style="color:#10b981">Rate: '.number_format($test['control_rate'],2).'%</div>';
    echo '</div></div>';
    
    echo '<div style="background:#111827;padding:16px;border-radius:8px">';
    echo '<h4>Variant (B): '.h($test['variant_value']).'</h4>';
    echo '<div style="display:grid;gap:8px">';
    echo '<div><label>Participants</label><input name="variant_participants" type="number" value="'.$test['variant_participants'].'" min="0"></div>';
    echo '<div><label>Conversions</label><input name="variant_conversions" type="number" value="'.$test['variant_conversions'].'" min="0"></div>';
    if($test['variant_rate'] > 0) echo '<div style="color:#10b981">Rate: '.number_format($test['variant_rate'],2).'%</div>';
    echo '</div></div>';
    echo '</div>';
    
    echo '<button class="btn">Cập nhật kết quả</button>';
    echo '</form>';
    
    // Statistical results
    if($test['z_score'] > 0) {
        echo '<div style="margin-top:20px;padding:16px;background:'.($test['is_significant']?'#10b981':'#f59e0b').';border-radius:8px;color:#fff">';
        echo '<h4>📊 Kết quả thống kê</h4>';
        echo '<div><strong>Z-Score:</strong> '.number_format($test['z_score'],3).'</div>';
        echo '<div><strong>Significant:</strong> '.($test['is_significant'] ? 'Yes' : 'No').' (95% confidence)</div>';
        if($test['winner']) {
            $winner_name = $test['winner'] === 'variant' ? $test['variant_value'] : $test['control_value'];
            echo '<div><strong>Winner:</strong> '.h($winner_name).'</div>';
        }
        echo '</div>';
    }
    
    echo '</div>';
    
    layout_footer();
    return;
}

// List all tests
echo '<div class="card" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff">';
echo '<h2>🧪 A/B Testing Dashboard</h2>';
echo '<p>Quản lý và theo dõi các A/B tests để tối ưu hiệu quả campaigns</p>';
echo '</div>';

$tests = q($db, "SELECT t.*, c.name as campaign_name FROM ab_tests t LEFT JOIN campaigns c ON t.campaign_id=c.id ORDER BY t.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

echo '<div class="card">';
echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">';
echo '<h3>📋 Active Tests</h3>';
echo '<a class="btn" href="?action=ab_testing&op=new">Tạo A/B Test</a>';
echo '</div>';

if($tests) {
    echo '<table>';
    echo '<tr><th>Test Name</th><th>Campaign</th><th>Variable</th><th>Status</th><th>Control Rate</th><th>Variant Rate</th><th>Significance</th><th></th></tr>';
    
    foreach($tests as $test) {
        echo '<tr>';
        echo '<td><strong>'.h($test['test_name']).'</strong><br><small>'.h($test['hypothesis']).'</small></td>';
        echo '<td>'.h($test['campaign_name']).'</td>';
        echo '<td>'.h($test['variable_tested']).'</td>';
        echo '<td><span style="color:'.($test['status']==='completed'?'#10b981':($test['status']==='running'?'#f59e0b':'#6b7280')).'">'.ucfirst($test['status']).'</span></td>';
        echo '<td>'.($test['control_rate'] > 0 ? number_format($test['control_rate'],2).'%' : '-').'</td>';
        echo '<td>'.($test['variant_rate'] > 0 ? number_format($test['variant_rate'],2).'%' : '-').'</td>';
        echo '<td>'.($test['is_significant'] ? '✅ Significant' : ($test['z_score'] > 0 ? '⏳ Insufficient' : '-')).'</td>';
        echo '<td><a href="?action=ab_testing&op=view&id='.$test['id'].'">View</a></td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<div style="text-align:center;padding:40px;color:#6b7280">';
    echo '<div style="font-size:48px;margin-bottom:16px">🧪</div>';
    echo '<div>Chưa có A/B tests nào</div>';
    echo '<div style="margin-top:8px"><a class="btn" href="?action=ab_testing&op=new">Tạo test đầu tiên</a></div>';
    echo '</div>';
}

echo '</div>';

if(!empty($_SESSION['flash'])){
    echo '<div class="card" style="background:#059669;color:#fff">'.h($_SESSION['flash']).'</div>';
    unset($_SESSION['flash']);
}

layout_footer();
}
?>
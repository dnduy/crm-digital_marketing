<?php
// ==========================
// FILE: /views/keywords.php - Keywords Tracking & SEO Analytics
// ==========================
function view_keywords($op){
global $db; 

// Handle ALL POST/redirect operations BEFORE any output
if ($op==='create' && $_SERVER['REQUEST_METHOD']==='POST'){ 
    require_csrf(); 
    q($db, "INSERT INTO keywords_tracking(keyword,page_id,target_rank,search_volume,difficulty,url) VALUES(?,?,?,?,?,?)", [
        $_POST['keyword']??'',
        $_POST['page_id']?:null,
        (int)($_POST['target_rank']??10),
        (int)($_POST['search_volume']??0),
        (int)($_POST['difficulty']??50),
        $_POST['url']??''
    ]); 
    header('Location: ?action=keywords'); 
    exit; 
}

if ($op==='update' && $_SERVER['REQUEST_METHOD']==='POST'){ 
    require_csrf(); 
    q($db, "UPDATE keywords_tracking SET keyword=?,page_id=?,current_rank=?,target_rank=?,search_volume=?,difficulty=?,url=?,checked_at=? WHERE id=?", [
        $_POST['keyword']??'',
        $_POST['page_id']?:null,
        $_POST['current_rank']?:null,
        (int)($_POST['target_rank']??10),
        (int)($_POST['search_volume']??0),
        (int)($_POST['difficulty']??50),
        $_POST['url']??'',
        !empty($_POST['current_rank']) ? date('Y-m-d H:i:s') : null,
        (int)$_POST['id']
    ]); 
    header('Location: ?action=keywords'); 
    exit; 
}

if ($op==='delete'){ 
    q($db, "DELETE FROM keywords_tracking WHERE id=?", [(int)($_GET['id']??0)]); 
    header('Location: ?action=keywords'); 
    exit; 
}

// Handle rank checking POST operation BEFORE any output
if ($op==='check_ranks' && $_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();
    $keywords = q($db, "SELECT * FROM keywords_tracking ORDER BY checked_at ASC NULLS FIRST LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    
    foreach($keywords as $k){
        // Simulate rank checking (in real implementation, this would use Google Search API or scraping)
        $simulated_rank = rand(1, 100);
        q($db, "UPDATE keywords_tracking SET current_rank=?, checked_at=? WHERE id=?", [
            $simulated_rank,
            date('Y-m-d H:i:s'),
            $k['id']
        ]);
        $updated++;
    }
    
    $_SESSION['flash'] = "Đã cập nhật ranking cho $updated từ khóa";
    header('Location: ?action=keywords');
    exit;
}

// Now safe to output HTML
layout_header('Theo dõi Từ khóa');

// Keywords listing & analytics
if($op==='' || $op==='list'){
    // Analytics summary
    $stats = [
        'total_keywords' => q($db, "SELECT COUNT(*) FROM keywords_tracking")->fetchColumn(),
        'ranking_keywords' => q($db, "SELECT COUNT(*) FROM keywords_tracking WHERE current_rank IS NOT NULL AND current_rank <= 100")->fetchColumn(),
        'top_10_keywords' => q($db, "SELECT COUNT(*) FROM keywords_tracking WHERE current_rank IS NOT NULL AND current_rank <= 10")->fetchColumn(),
        'avg_rank' => q($db, "SELECT ROUND(AVG(current_rank), 1) FROM keywords_tracking WHERE current_rank IS NOT NULL")->fetchColumn() ?: 0
    ];
    
    echo '<div class="grid cols-4">';
    echo '<div class="card"><div>Tổng từ khóa</div><div class="kpi">'.(int)$stats['total_keywords'].'</div></div>';
    echo '<div class="card"><div>Ranking (Top 100)</div><div class="kpi">'.(int)$stats['ranking_keywords'].'</div></div>';
    echo '<div class="card"><div>Top 10</div><div class="kpi" style="color:#22d3ee">'.(int)$stats['top_10_keywords'].'</div></div>';
    echo '<div class="card"><div>Vị trí TB</div><div class="kpi">'.h($stats['avg_rank']).'</div></div>';
    echo '</div>';
    
    echo '<div class="card" style="display:flex;gap:8px;align-items:center;justify-content:space-between">';
    echo '<div><a class="btn" href="?action=keywords&op=new">Thêm từ khóa</a> <a class="btn secondary" href="?action=keywords&op=check_ranks">Kiểm tra Ranking</a></div>';
    echo '<div>Filter: <select onchange="location.href=\'?action=keywords&filter=\'+this.value">';
    echo '<option value="">Tất cả</option><option value="top10">Top 10</option><option value="top50">Top 50</option><option value="unranked">Chưa rank</option>';
    echo '</select></div></div>';

    // Filter
    $where = [];
    $params = [];
    $filter = $_GET['filter'] ?? '';
    if($filter === 'top10'){
        $where[] = 'current_rank IS NOT NULL AND current_rank <= 10';
    } elseif($filter === 'top50'){
        $where[] = 'current_rank IS NOT NULL AND current_rank <= 50';
    } elseif($filter === 'unranked'){
        $where[] = 'current_rank IS NULL';
    }
    
    $sql = 'SELECT k.*, c.title as page_title FROM keywords_tracking k LEFT JOIN content_pages c ON c.id = k.page_id';
    if($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY CASE WHEN k.current_rank IS NULL THEN 999 ELSE k.current_rank END ASC, k.created_at DESC LIMIT 200';
    
    $rows = q($db, $sql, $params)->fetchAll(PDO::FETCH_ASSOC);

    echo '<div class="card"><table>';
    echo '<tr><th>Từ khóa</th><th>Trang</th><th>Vị trí hiện tại</th><th>Mục tiêu</th><th>Search Volume</th><th>Difficulty</th><th>Kiểm tra cuối</th><th></th></tr>';
    foreach($rows as $r){
        $rank_color = '';
        if($r['current_rank']) {
            if($r['current_rank'] <= 10) $rank_color = 'style="color:#22d3ee"';
            elseif($r['current_rank'] <= 50) $rank_color = 'style="color:#fbbf24"';
            else $rank_color = 'style="color:#f87171"';
        }
        
        echo '<tr>';
        echo '<td><strong>'.h($r['keyword']).'</strong></td>';
        echo '<td>'.($r['page_title'] ? h($r['page_title']) : '<em>Không gắn</em>').'</td>';
        echo '<td '.$rank_color.'>'.($r['current_rank'] ? '#'.(int)$r['current_rank'] : '-').'</td>';
        echo '<td>#'.(int)$r['target_rank'].'</td>';
        echo '<td>'.number_format($r['search_volume']).'</td>';
        echo '<td>'.(int)$r['difficulty'].'/100</td>';
        echo '<td>'.($r['checked_at'] ? date('d/m H:i', strtotime($r['checked_at'])) : '-').'</td>';
        echo '<td><a href="?action=keywords&op=edit&id='.(int)$r['id'].'">Sửa</a> · <a href="?action=keywords&op=delete&id='.(int)$r['id'].'" onclick="return confirm(\'Xóa từ khóa?\')">Xóa</a>';
        if($r['url']) echo ' · <a href="'.h($r['url']).'" target="_blank">Xem</a>';
        echo '</td></tr>';
    }
    echo '</table></div>';
}

// Manual rank checking (simulated)
if($op==='check_ranks'){
    echo '<div class="card"><h3>Kiểm tra Ranking</h3>';
    echo '<p>Tính năng này sẽ kiểm tra vị trí hiện tại của các từ khóa trên Google.</p>';
    echo '<form method="post">'; csrf_field();
    echo '<div class="hint">⚠️ Đây là phiên bản demo - trong thực tế sẽ tích hợp với Google Search API hoặc tools SEO chuyên nghiệp.</div>';
    echo '<div style="margin-top:12px"><button class="btn">Kiểm tra ngay (Demo)</button> <a class="btn secondary" href="?action=keywords">Quay lại</a></div>';
    echo '</form></div>';
}

// Form New/Edit Keyword
if($op==='new'||$op==='edit'){ 
    $k = ['id'=>'','keyword'=>'','page_id'=>'','current_rank'=>'','target_rank'=>10,'search_volume'=>0,'difficulty'=>50,'url'=>''];
    if($op==='edit'){ 
        $k = q($db,'SELECT * FROM keywords_tracking WHERE id=?',[(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC)?:$k; 
    }
    
    $pages = q($db, "SELECT id, title FROM content_pages WHERE status='published' ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

    echo '<div class="card"><h3>'.($op==='new'?'Thêm từ khóa theo dõi':'Chỉnh sửa từ khóa').'</h3>';
    echo '<form method="post" action="?action=keywords&op='.($op==='new'?'create':'update').'">';
    csrf_field(); 
    if($op==='edit') echo '<input type="hidden" name="id" value="'.h($k['id']).'">';
    
    echo '<div class="grid cols-2">';
    echo '<div><label>Từ khóa *</label><input name="keyword" required value="'.h($k['keyword']).'" placeholder="digital marketing"></div>';
    echo '<div><label>Gắn với trang</label><select name="page_id"><option value="">-- Chọn trang --</option>';
    foreach($pages as $p){
        $sel = $k['page_id']==$p['id']?'selected':'';
        echo '<option '.h($sel).' value="'.(int)$p['id'].'">'.h($p['title']).'</option>';
    }
    echo '</select></div>';
    echo '</div>';
    
    echo '<div class="grid cols-3">';
    echo '<div><label>Vị trí hiện tại</label><input name="current_rank" type="number" value="'.h($k['current_rank']).'" placeholder="15"></div>';
    echo '<div><label>Mục tiêu</label><input name="target_rank" type="number" value="'.h($k['target_rank']).'" placeholder="10"></div>';
    echo '<div><label>Search Volume/tháng</label><input name="search_volume" type="number" value="'.h($k['search_volume']).'" placeholder="1000"></div>';
    echo '</div>';
    
    echo '<div class="grid cols-2">';
    echo '<div><label>Difficulty (1-100)</label><input name="difficulty" type="number" min="1" max="100" value="'.h($k['difficulty']).'" placeholder="50"></div>';
    echo '<div><label>URL kiểm tra</label><input name="url" type="url" value="'.h($k['url']).'" placeholder="https://example.com/page"></div>';
    echo '</div>';
    
    echo '<div style="margin-top:20px"><button class="btn">'.($op==='new'?'Thêm từ khóa':'Cập nhật').'</button> <a class="btn secondary" href="?action=keywords">Hủy</a></div>';
    echo '</form></div>';
}

if(!empty($_SESSION['flash'])){
    echo '<div class="card" style="background:#059669;color:#fff">'.h($_SESSION['flash']).'</div>';
    unset($_SESSION['flash']);
}

layout_footer();
}
?>
<?php
// ==========================
// FILE: /views/content.php - Content SEO Management
// ==========================
function view_content($op){
global $db; 

// Handle POST operations BEFORE any output
if ($op==='create' && $_SERVER['REQUEST_METHOD']==='POST'){ 
    require_csrf(); 
    $slug = generate_slug($_POST['title'] ?? '');
    q($db, "INSERT INTO content_pages(title,slug,meta_title,meta_description,meta_keywords,og_title,og_description,og_image,content,excerpt,status,target_keywords,content_type,author_id,published_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
        $_POST['title']??'',
        $slug,
        $_POST['meta_title']??'',
        $_POST['meta_description']??'',
        $_POST['meta_keywords']??'',
        $_POST['og_title']??'',
        $_POST['og_description']??'',
        $_POST['og_image']??'',
        $_POST['content']??'',
        $_POST['excerpt']??'',
        $_POST['status']??'draft',
        $_POST['target_keywords']??'',
        $_POST['content_type']??'page',
        $_SESSION['uid']??1,
        ($_POST['status']??'draft')==='published' ? date('Y-m-d H:i:s') : null
    ]); 
    
    // Calculate SEO score
    $page_id = $db->lastInsertId();
    $seo_score = calculate_seo_score($_POST);
    q($db, "UPDATE content_pages SET seo_score=? WHERE id=?", [$seo_score, $page_id]);
    
    header('Location: ?action=content'); 
    exit; 
}

if ($op==='update' && $_SERVER['REQUEST_METHOD']==='POST'){ 
    require_csrf(); 
    $slug = generate_slug($_POST['title'] ?? '');
    q($db, "UPDATE content_pages SET title=?,slug=?,meta_title=?,meta_description=?,meta_keywords=?,og_title=?,og_description=?,og_image=?,content=?,excerpt=?,status=?,target_keywords=?,content_type=?,published_at=?,updated_at=? WHERE id=?", [
        $_POST['title']??'',
        $slug,
        $_POST['meta_title']??'',
        $_POST['meta_description']??'',
        $_POST['meta_keywords']??'',
        $_POST['og_title']??'',
        $_POST['og_description']??'',
        $_POST['og_image']??'',
        $_POST['content']??'',
        $_POST['excerpt']??'',
        $_POST['status']??'draft',
        $_POST['target_keywords']??'',
        $_POST['content_type']??'page',
        ($_POST['status']??'draft')==='published' ? date('Y-m-d H:i:s') : null,
        date('Y-m-d H:i:s'),
        (int)$_POST['id']
    ]); 
    
    // Recalculate SEO score
    $seo_score = calculate_seo_score($_POST);
    q($db, "UPDATE content_pages SET seo_score=? WHERE id=?", [$seo_score, (int)$_POST['id']]);
    
    header('Location: ?action=content'); 
    exit; 
}

if ($op==='delete'){ 
    q($db, "DELETE FROM content_pages WHERE id=?", [(int)($_GET['id']??0)]); 
    header('Location: ?action=content'); 
    exit; 
}

// SEO Settings POST handling
if($op==='seo_settings' && $_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();
    $settings = q($db, "SELECT * FROM seo_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if($settings){
        q($db, "UPDATE seo_settings SET site_title=?,site_description=?,site_keywords=?,google_analytics_id=?,google_search_console_id=?,facebook_pixel_id=?,robots_txt=? WHERE id=?", [
            $_POST['site_title']??'',
            $_POST['site_description']??'',
            $_POST['site_keywords']??'',
            $_POST['google_analytics_id']??'',
            $_POST['google_search_console_id']??'',
            $_POST['facebook_pixel_id']??'',
            $_POST['robots_txt']??'',
            $settings['id']
        ]);
    } else {
        q($db, "INSERT INTO seo_settings(site_title,site_description,site_keywords,google_analytics_id,google_search_console_id,facebook_pixel_id,robots_txt) VALUES(?,?,?,?,?,?,?)", [
            $_POST['site_title']??'',
            $_POST['site_description']??'',
            $_POST['site_keywords']??'',
            $_POST['google_analytics_id']??'',
            $_POST['google_search_console_id']??'',
            $_POST['facebook_pixel_id']??'',
            $_POST['robots_txt']??''
        ]);
    }
    $_SESSION['flash'] = 'Cài đặt SEO đã được lưu';
    header('Location: ?action=content&op=seo_settings');
    exit;
}

// Now safe to output HTML
layout_header('Quản lý Content SEO');

// Content listing
if($op==='' || $op==='list'){
    echo '<div class="card" style="display:flex;gap:8px;align-items:center;justify-content:space-between">';
    echo '<div><a class="btn" href="?action=content&op=new">Tạo Content Mới</a> <a class="btn secondary" href="?action=content&op=seo_settings">Cài đặt SEO</a></div>';
    echo '<div>Filter: <select onchange="location.href=\'?action=content&type=\'+this.value">';
    echo '<option value="">Tất cả</option><option value="page">Trang</option><option value="blog">Blog</option><option value="product">Sản phẩm</option>';
    echo '</select></div></div>';

    // Filter
    $where = [];
    $params = [];
    if(!empty($_GET['type'])){
        $where[] = 'content_type = ?';
        $params[] = $_GET['type'];
    }
    
    $sql = 'SELECT c.*, u.username as author FROM content_pages c LEFT JOIN users u ON u.id = c.author_id';
    if($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY c.updated_at DESC LIMIT 100';
    
    $rows = q($db, $sql, $params)->fetchAll(PDO::FETCH_ASSOC);

    echo '<div class="card"><table>';
    echo '<tr><th>Tiêu đề</th><th>Loại</th><th>Trạng thái</th><th>SEO Score</th><th>Tác giả</th><th>Cập nhật</th><th></th></tr>';
    foreach($rows as $r){
        $status_class = $r['status'] === 'published' ? 'style="color:#22d3ee"' : ($r['status'] === 'draft' ? 'style="color:#fbbf24"' : 'style="color:#94a3b8"');
        $seo_color = $r['seo_score'] >= 80 ? '#22d3ee' : ($r['seo_score'] >= 60 ? '#fbbf24' : '#f87171');
        echo '<tr>';
        echo '<td><a href="?action=content&op=edit&id='.(int)$r['id'].'">'.h($r['title']).'</a></td>';
        echo '<td>'.h($r['content_type']).'</td>';
        echo '<td '.$status_class.'>'.h($r['status']).'</td>';
        echo '<td style="color:'.$seo_color.'">'.(int)$r['seo_score'].'/100</td>';
        echo '<td>'.h($r['author']).'</td>';
        echo '<td>'.h(date('d/m/Y', strtotime($r['updated_at']))).'</td>';
        echo '<td><a href="?action=content&op=edit&id='.(int)$r['id'].'">Sửa</a> · <a href="?action=content&op=delete&id='.(int)$r['id'].'" onclick="return confirm(\'Xóa content?\')">Xóa</a>';
        if($r['status'] === 'published') echo ' · <a href="/content/'.h($r['slug']).'" target="_blank">Xem</a>';
        echo '</td></tr>';
    }
    echo '</table></div>';
}

// SEO Settings
if($op==='seo_settings'){
    $settings = q($db, "SELECT * FROM seo_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [
        'site_title' => '',
        'site_description' => '',
        'site_keywords' => '',
        'google_analytics_id' => '',
        'google_search_console_id' => '',
        'facebook_pixel_id' => '',
        'robots_txt' => "User-agent: *\nDisallow: /admin/\nSitemap: " . (isset($_SERVER['HTTPS'])?'https':'http') . '://' . ($_SERVER['HTTP_HOST']??'localhost') . '/sitemap.xml'
    ];
    
    if(!empty($_SESSION['flash'])){
        echo '<div class="card" style="background:#059669;color:#fff">'.h($_SESSION['flash']).'</div>';
        unset($_SESSION['flash']);
    }
    
    echo '<div class="card"><h3>Cài đặt SEO Tổng quát</h3>';
    echo '<form method="post">'; csrf_field();
    echo '<div class="grid cols-2">';
    echo '<div><label>Tiêu đề Website</label><input name="site_title" value="'.h($settings['site_title']).'" placeholder="Website ABC - Giải pháp XYZ"></div>';
    echo '<div><label>Mô tả Website</label><input name="site_description" value="'.h($settings['site_description']).'" placeholder="Mô tả ngắn gọn về website"></div>';
    echo '<div><label>Từ khóa chính</label><input name="site_keywords" value="'.h($settings['site_keywords']).'" placeholder="keyword1, keyword2, keyword3"></div>';
    echo '<div><label>Google Analytics ID</label><input name="google_analytics_id" value="'.h($settings['google_analytics_id']).'" placeholder="G-XXXXXXXXXX"></div>';
    echo '<div><label>Google Search Console ID</label><input name="google_search_console_id" value="'.h($settings['google_search_console_id']).'" placeholder="google-site-verification-code"></div>';
    echo '<div><label>Facebook Pixel ID</label><input name="facebook_pixel_id" value="'.h($settings['facebook_pixel_id']).'" placeholder="1234567890"></div>';
    echo '</div>';
    echo '<div><label>Robots.txt</label><textarea name="robots_txt" rows="8">'.h($settings['robots_txt']).'</textarea></div>';
    echo '<div style="margin-top:12px"><button class="btn">Lưu cài đặt</button> <a class="btn secondary" href="?action=content">Quay lại</a></div>';
    echo '</form></div>';
}

// Form New/Edit Content
if($op==='new'||$op==='edit'){ 
    $c = ['id'=>'','title'=>'','slug'=>'','meta_title'=>'','meta_description'=>'','meta_keywords'=>'','og_title'=>'','og_description'=>'','og_image'=>'','content'=>'','excerpt'=>'','status'=>'draft','target_keywords'=>'','content_type'=>'page','seo_score'=>0];
    if($op==='edit'){ 
        $c = q($db,'SELECT * FROM content_pages WHERE id=?',[(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC)?:$c; 
    }

    echo '<div class="card"><h3>'.($op==='new'?'Tạo Content Mới':'Chỉnh sửa Content').'</h3>';
    echo '<form method="post" action="?action=content&op='.($op==='new'?'create':'update').'">';
    csrf_field(); 
    if($op==='edit') echo '<input type="hidden" name="id" value="'.h($c['id']).'">';
    
    echo '<div class="grid cols-2">';
    echo '<div><label>Tiêu đề *</label><input name="title" required value="'.h($c['title']).'" onkeyup="generateSlug(this.value)"></div>';
    echo '<div><label>Loại Content</label><select name="content_type">';
    foreach(['page'=>'Trang','blog'=>'Blog','product'=>'Sản phẩm'] as $type=>$label){
        $sel = $c['content_type']===$type?'selected':'';
        echo '<option '.h($sel).' value="'.h($type).'">'.h($label).'</option>';
    }
    echo '</select></div>';
    echo '</div>';
    
    echo '<div><label>Slug URL</label><input name="slug" id="slug" value="'.h($c['slug']).'" readonly style="background:#1a1a1a"></div>';
    
    echo '<div><label>Nội dung chính</label><textarea name="content" rows="10" placeholder="Nội dung đầy đủ của bài viết...">'.h($c['content']).'</textarea></div>';
    echo '<div><label>Tóm tắt</label><textarea name="excerpt" rows="3" placeholder="Tóm tắt ngắn gọn (150-160 ký tự)">'.h($c['excerpt']).'</textarea></div>';
    
    // SEO Section
    echo '<h4 style="margin-top:20px;color:#4f46e5">🔍 SEO Optimization</h4>';
    echo '<div class="grid cols-2">';
    echo '<div><label>Meta Title</label><input name="meta_title" value="'.h($c['meta_title']).'" placeholder="Tối đa 60 ký tự" maxlength="60"></div>';
    echo '<div><label>Từ khóa target</label><input name="target_keywords" value="'.h($c['target_keywords']).'" placeholder="keyword1, keyword2"></div>';
    echo '</div>';
    echo '<div><label>Meta Description</label><textarea name="meta_description" rows="3" placeholder="Mô tả ngắn gọn (150-160 ký tự)" maxlength="160">'.h($c['meta_description']).'</textarea></div>';
    echo '<div><label>Meta Keywords</label><input name="meta_keywords" value="'.h($c['meta_keywords']).'" placeholder="keyword1, keyword2, keyword3"></div>';
    
    // Open Graph
    echo '<h4 style="margin-top:20px;color:#22d3ee">📱 Social Media (Open Graph)</h4>';
    echo '<div class="grid cols-2">';
    echo '<div><label>OG Title</label><input name="og_title" value="'.h($c['og_title']).'" placeholder="Tiêu đề cho social media"></div>';
    echo '<div><label>OG Image URL</label><input name="og_image" value="'.h($c['og_image']).'" placeholder="https://example.com/image.jpg"></div>';
    echo '</div>';
    echo '<div><label>OG Description</label><textarea name="og_description" rows="2" placeholder="Mô tả cho social media">'.h($c['og_description']).'</textarea></div>';
    
    // Status & Publish
    echo '<div class="grid cols-2" style="margin-top:20px">';
    echo '<div><label>Trạng thái</label><select name="status">';
    foreach(['draft'=>'Nháp','published'=>'Đã xuất bản','archived'=>'Lưu trữ'] as $status=>$label){
        $sel = $c['status']===$status?'selected':'';
        echo '<option '.h($sel).' value="'.h($status).'">'.h($label).'</option>';
    }
    echo '</select></div>';
    if($op==='edit') echo '<div><label>SEO Score</label><div class="kpi" style="color:'.($c['seo_score']>=80?'#22d3ee':($c['seo_score']>=60?'#fbbf24':'#f87171')).'">'.(int)$c['seo_score'].'/100</div></div>';
    echo '</div>';
    
    echo '<div style="margin-top:20px"><button class="btn">'.($op==='new'?'Tạo Content':'Cập nhật').'</button> <a class="btn secondary" href="?action=content">Hủy</a></div>';
    echo '</form></div>';
    
    // JavaScript for slug generation
    echo '<script>
    function generateSlug(title) {
        var slug = title.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, "")
            .replace(/\s+/g, "-")
            .replace(/-+/g, "-")
            .trim("-");
        document.getElementById("slug").value = slug;
    }
    </script>';
}

layout_footer();
}

// Helper functions
function generate_slug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

function calculate_seo_score($data) {
    $score = 0;
    
    // Title (20 points)
    if(!empty($data['title'])) {
        $score += 10;
        if(strlen($data['title']) >= 30 && strlen($data['title']) <= 60) $score += 10;
    }
    
    // Meta description (15 points)
    if(!empty($data['meta_description'])) {
        $score += 8;
        if(strlen($data['meta_description']) >= 120 && strlen($data['meta_description']) <= 160) $score += 7;
    }
    
    // Content length (15 points)
    if(!empty($data['content'])) {
        $word_count = str_word_count(strip_tags($data['content']));
        if($word_count >= 300) $score += 15;
        elseif($word_count >= 150) $score += 10;
        elseif($word_count >= 50) $score += 5;
    }
    
    // Target keywords (20 points)
    if(!empty($data['target_keywords'])) {
        $score += 10;
        // Check if keywords appear in title
        $keywords = explode(',', $data['target_keywords']);
        foreach($keywords as $keyword) {
            if(stripos($data['title'], trim($keyword)) !== false) {
                $score += 5;
                break;
            }
        }
        // Check if keywords appear in content
        foreach($keywords as $keyword) {
            if(stripos($data['content'], trim($keyword)) !== false) {
                $score += 5;
                break;
            }
        }
    }
    
    // Meta title (10 points)
    if(!empty($data['meta_title'])) {
        $score += 5;
        if(strlen($data['meta_title']) <= 60) $score += 5;
    }
    
    // Excerpt (10 points)
    if(!empty($data['excerpt'])) {
        $score += 5;
        if(strlen($data['excerpt']) >= 120 && strlen($data['excerpt']) <= 160) $score += 5;
    }
    
    // Open Graph (10 points)
    if(!empty($data['og_title'])) $score += 3;
    if(!empty($data['og_description'])) $score += 3;
    if(!empty($data['og_image'])) $score += 4;
    
    return min(100, $score);
}
?>
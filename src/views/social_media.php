<?php
// ==========================
// FILE: /views/social_media.php - Social Media Management Dashboard
// ==========================

require_once __DIR__ . '/../lib/social/SocialMediaManager.php';

function view_social_media($op) {
    global $db;
    
    // Initialize social media manager
    try {
        // For now, use SocialMediaManager without complex AI service
        // The AI integration will be handled by the automation system
        $socialManager = new SocialMediaManager($db, null);
        $aiService = null;
    } catch (Exception $e) {
        $socialManager = new SocialMediaManager($db);
        $aiService = null;
    }
    
    // Handle POST operations BEFORE any output
    if ($op === 'connect_account' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        
        $platform = $_POST['platform'] ?? '';
        $credentials = [
            'access_token' => $_POST['access_token'] ?? '',
            'api_key' => $_POST['api_key'] ?? '',
            'api_secret' => $_POST['api_secret'] ?? '',
            'bearer_token' => $_POST['bearer_token'] ?? '',
            'client_id' => $_POST['client_id'] ?? '',
            'client_secret' => $_POST['client_secret'] ?? '',
            'app_id' => $_POST['app_id'] ?? '',
            'app_secret' => $_POST['app_secret'] ?? '',
            'page_id' => $_POST['page_id'] ?? ''
        ];
        
        $result = $socialManager->connectAccount($platform, $credentials);
        
        if ($result['success']) {
            $_SESSION['flash'] = "✅ Đã kết nối thành công tài khoản {$platform}!";
        } else {
            $_SESSION['flash'] = "❌ Kết nối thất bại: " . $result['error'];
        }
        
        header('Location: ?action=social_media');
        exit;
    }
    
    if ($op === 'create_post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        
        $postData = [
            'content' => $_POST['content'] ?? '',
            'platforms' => $_POST['platforms'] ?? [],
            'ai_generated' => isset($_POST['ai_generated']),
            'topic' => $_POST['ai_topic'] ?? '',
            'tone' => $_POST['ai_tone'] ?? 'engaging',
            'include_emoji' => isset($_POST['include_emoji']),
            'campaign_id' => $_POST['campaign_id'] ?: null
        ];
        
        if (isset($_POST['schedule_post'])) {
            $scheduleDateTime = new DateTime($_POST['schedule_date'] . ' ' . $_POST['schedule_time']);
            $result = $socialManager->scheduleMultiPlatformPost($postData, $scheduleDateTime, $postData['platforms']);
            $action = 'scheduled';
        } else {
            $result = $socialManager->createMultiPlatformPost($postData, $postData['platforms']);
            $action = 'posted';
        }
        
        if ($result['success']) {
            $summary = $result['summary'];
            $_SESSION['flash'] = "✅ Đã {$action} thành công! {$summary['successful_posts']}/{$summary['total_platforms']} platforms";
        } else {
            $_SESSION['flash'] = "❌ Đăng bài thất bại: " . $result['error'];
        }
        
        header('Location: ?action=social_media');
        exit;
    }
    
    // Start HTML output
    layout_header('Quản lý Social Media');
    
    // Show flash messages
    if (isset($_SESSION['flash'])) {
        echo '<div class="card" style="background: #065f46; border-color: #10b981; margin-bottom: 16px;">';
        echo '<p style="margin: 0; color: #d1fae5;">' . h($_SESSION['flash']) . '</p>';
        echo '</div>';
        unset($_SESSION['flash']);
    }
    
    // Main content based on operation
    if ($op === '' || $op === 'dashboard') {
        renderSocialMediaDashboard($db, $socialManager);
    } elseif ($op === 'connect') {
        renderConnectAccountForm();
    } elseif ($op === 'create_post') {
        renderCreatePostForm($db, $aiService !== null);
    } elseif ($op === 'analytics') {
        renderAnalyticsDashboard($socialManager);
    } elseif ($op === 'posts') {
        renderPostsHistory($db);
    }
    
    layout_footer();
}

function renderSocialMediaDashboard($db, $socialManager) {
    echo '<div class="toolbar">';
    echo '<h1>🚀 Social Media Management</h1>';
    echo '<div>';
    echo '<a href="?action=social_media&op=create_post" class="btn">➕ Tạo bài đăng</a> ';
    echo '<a href="?action=social_media&op=connect" class="btn secondary">🔗 Kết nối tài khoản</a>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="container">';
    
    // Connected accounts overview
    $accounts = q($db, "SELECT * FROM social_media_accounts WHERE account_status = 'active' ORDER BY platform, connected_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="card">';
    echo '<h3>📱 Tài khoản đã kết nối</h3>';
    
    if (empty($accounts)) {
        echo '<div style="text-align: center; padding: 40px; color: #94a3b8;">';
        echo '<p>🔌 Chưa có tài khoản nào được kết nối</p>';
        echo '<a href="?action=social_media&op=connect" class="btn">Kết nối tài khoản đầu tiên</a>';
        echo '</div>';
    } else {
        echo '<div class="grid cols-3">';
        foreach ($accounts as $account) {
            $platformIcon = [
                'facebook' => '📘',
                'twitter' => '🐦', 
                'linkedin' => '💼',
                'instagram' => '📸',
                'tiktok' => '🎵'
            ];
            
            echo '<div class="card">';
            echo '<h4>' . ($platformIcon[$account['platform']] ?? '🌐') . ' ' . ucfirst($account['platform']) . '</h4>';
            echo '<p><strong>' . h($account['display_name'] ?: $account['account_name']) . '</strong></p>';
            if ($account['username']) echo '<p>@' . h($account['username']) . '</p>';
            echo '<p>👥 ' . number_format($account['followers_count']) . ' followers</p>';
            echo '<p class="hint">Kết nối: ' . date('d/m/Y H:i', strtotime($account['connected_at'])) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
    
    // Recent posts
    $recentPosts = q($db, "
        SELECT p.*, a.platform, a.display_name as account_name 
        FROM social_media_posts p 
        JOIN social_media_accounts a ON a.id = p.account_id 
        ORDER BY p.created_at DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="card">';
    echo '<h3>📝 Bài đăng gần đây</h3>';
    
    if (empty($recentPosts)) {
        echo '<p style="text-align: center; color: #94a3b8; padding: 20px;">Chưa có bài đăng nào</p>';
    } else {
        echo '<div style="overflow-x: auto;">';
        echo '<table>';
        echo '<tr><th>Platform</th><th>Nội dung</th><th>Trạng thái</th><th>Engagement</th><th>Thời gian</th></tr>';
        
        foreach ($recentPosts as $post) {
            $platformIcon = [
                'facebook' => '📘',
                'twitter' => '🐦', 
                'linkedin' => '💼'
            ];
            
            $statusColors = [
                'published' => '#22d3ee',
                'scheduled' => '#fbbf24',
                'draft' => '#94a3b8',
                'failed' => '#f87171'
            ];
            
            echo '<tr>';
            echo '<td>' . ($platformIcon[$post['platform']] ?? '🌐') . ' ' . ucfirst($post['platform']) . '</td>';
            echo '<td>' . h(substr($post['content'], 0, 60)) . (strlen($post['content']) > 60 ? '...' : '') . '</td>';
            echo '<td><span style="color: ' . ($statusColors[$post['post_status']] ?? '#94a3b8') . '">● ' . ucfirst($post['post_status']) . '</span></td>';
            echo '<td>';
            if ($post['post_status'] === 'published') {
                echo '👍 ' . number_format($post['likes_count']) . ' ';
                echo '💬 ' . number_format($post['comments_count']) . ' ';
                echo '🔄 ' . number_format($post['shares_count']);
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td>' . date('d/m H:i', strtotime($post['created_at'])) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    echo '</div>';
    
    // Quick stats
    $stats = q($db, "
        SELECT 
            COUNT(*) as total_posts,
            SUM(p.likes_count) as total_likes,
            SUM(p.comments_count) as total_comments,
            SUM(p.shares_count) as total_shares,
            COUNT(DISTINCT p.account_id) as connected_accounts
        FROM social_media_posts p
        JOIN social_media_accounts a ON a.id = p.account_id
        WHERE p.post_status = 'published' AND p.created_at >= date('now', '-30 days')
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo '<div class="grid cols-3">';
    echo '<div class="card">';
    echo '<div class="kpi">' . number_format($stats['total_posts'] ?? 0) . '</div>';
    echo '<div>Bài đăng (30 ngày)</div>';
    echo '</div>';
    
    echo '<div class="card">';
    echo '<div class="kpi">' . number_format($stats['total_likes'] ?? 0) . '</div>';
    echo '<div>Tổng lượt thích</div>';
    echo '</div>';
    
    echo '<div class="card">';
    echo '<div class="kpi">' . number_format(($stats['total_likes'] ?? 0) + ($stats['total_comments'] ?? 0) + ($stats['total_shares'] ?? 0)) . '</div>';
    echo '<div>Tổng tương tác</div>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
}

function renderConnectAccountForm() {
    echo '<div class="toolbar">';
    echo '<h1>🔗 Kết nối tài khoản Social Media</h1>';
    echo '<a href="?action=social_media" class="btn secondary">← Quay lại</a>';
    echo '</div>';
    
    echo '<div class="container">';
    echo '<div class="grid cols-3">';
    
    // Twitter connection
    echo '<div class="card">';
    echo '<h3>🐦 Twitter / X</h3>';
    echo '<form method="post">';
    csrf_field();
    echo '<input type="hidden" name="platform" value="twitter">';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>Bearer Token:</label>';
    echo '<input type="text" name="bearer_token" placeholder="AAAAAAAAAAAAAAAAAAAAAMLhGAEAAAA..." required>';
    echo '</div>';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>API Key (Optional):</label>';
    echo '<input type="text" name="api_key" placeholder="API Key">';
    echo '</div>';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>API Secret (Optional):</label>';
    echo '<input type="text" name="api_secret" placeholder="API Secret">';
    echo '</div>';
    echo '<button type="submit" class="btn">Kết nối Twitter</button>';
    echo '</form>';
    echo '<div class="hint">Cần Twitter API v2 Bearer Token. Đăng ký tại developer.twitter.com</div>';
    echo '</div>';
    
    // LinkedIn connection
    echo '<div class="card">';
    echo '<h3>💼 LinkedIn</h3>';
    echo '<form method="post">';
    csrf_field();
    echo '<input type="hidden" name="platform" value="linkedin">';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>Access Token:</label>';
    echo '<input type="text" name="access_token" placeholder="Access Token" required>';
    echo '</div>';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>Client ID:</label>';
    echo '<input type="text" name="client_id" placeholder="Client ID">';
    echo '</div>';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>Client Secret:</label>';
    echo '<input type="text" name="client_secret" placeholder="Client Secret">';
    echo '</div>';
    echo '<button type="submit" class="btn">Kết nối LinkedIn</button>';
    echo '</form>';
    echo '<div class="hint">Cần LinkedIn OAuth access token. Đăng ký app tại developer.linkedin.com</div>';
    echo '</div>';
    
    // Facebook connection
    echo '<div class="card">';
    echo '<h3>📘 Facebook</h3>';
    echo '<form method="post">';
    csrf_field();
    echo '<input type="hidden" name="platform" value="facebook">';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>Access Token:</label>';
    echo '<input type="text" name="access_token" placeholder="Page/User Access Token" required>';
    echo '</div>';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>App ID:</label>';
    echo '<input type="text" name="app_id" placeholder="Facebook App ID">';
    echo '</div>';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>App Secret:</label>';
    echo '<input type="text" name="app_secret" placeholder="Facebook App Secret">';
    echo '</div>';
    echo '<div style="margin-bottom: 12px;">';
    echo '<label>Page ID (Optional):</label>';
    echo '<input type="text" name="page_id" placeholder="Facebook Page ID">';
    echo '</div>';
    echo '<button type="submit" class="btn">Kết nối Facebook</button>';
    echo '</form>';
    echo '<div class="hint">Cần Facebook Graph API access token. Tạo app tại developers.facebook.com</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
}

function renderCreatePostForm($db, $hasAI) {
    echo '<div class="toolbar">';
    echo '<h1>➕ Tạo bài đăng mới</h1>';
    echo '<a href="?action=social_media" class="btn secondary">← Quay lại</a>';
    echo '</div>';
    
    echo '<div class="container">';
    echo '<form method="post" style="max-width: 800px;">';
    csrf_field();
    
    echo '<div class="card">';
    echo '<h3>📝 Nội dung bài đăng</h3>';
    
    if ($hasAI) {
        echo '<div style="margin-bottom: 16px; padding: 12px; background: #1e293b; border-radius: 8px; border-left: 4px solid #22d3ee;">';
        echo '<label><input type="checkbox" name="ai_generated" onchange="toggleAIOptions(this)"> 🤖 Sử dụng AI để tạo nội dung</label>';
        echo '</div>';
        
        echo '<div id="ai_options" style="display: none; margin-bottom: 16px;">';
        echo '<div style="margin-bottom: 12px;">';
        echo '<label>Chủ đề/Ý tưởng:</label>';
        echo '<input type="text" name="ai_topic" placeholder="VD: Tips marketing digital cho doanh nghiệp nhỏ">';
        echo '</div>';
        echo '<div style="margin-bottom: 12px;">';
        echo '<label>Tone/Phong cách:</label>';
        echo '<select name="ai_tone">';
        echo '<option value="engaging">Engaging (Thu hút)</option>';
        echo '<option value="professional">Professional (Chuyên nghiệp)</option>';
        echo '<option value="casual">Casual (Thân thiện)</option>';
        echo '<option value="authoritative">Authoritative (Uy tín)</option>';
        echo '<option value="humorous">Humorous (Hài hước)</option>';
        echo '</select>';
        echo '</div>';
        echo '<div style="margin-bottom: 12px;">';
        echo '<label><input type="checkbox" name="include_emoji" checked> Bao gồm emoji</label>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '<div style="margin-bottom: 16px;">';
    echo '<label>Nội dung:</label>';
    echo '<textarea name="content" rows="6" placeholder="Nhập nội dung bài đăng hoặc để AI tạo tự động..." style="resize: vertical;"></textarea>';
    echo '</div>';
    echo '</div>';
    
    // Platform selection
    $connectedPlatforms = q($db, "SELECT DISTINCT platform FROM social_media_accounts WHERE account_status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    
    echo '<div class="card">';
    echo '<h3>📱 Chọn platform</h3>';
    
    if (empty($connectedPlatforms)) {
        echo '<p style="color: #f87171;">❌ Chưa có platform nào được kết nối. <a href="?action=social_media&op=connect">Kết nối ngay</a></p>';
    } else {
        echo '<div style="margin-bottom: 16px;">';
        foreach ($connectedPlatforms as $platform) {
            $platformNames = [
                'facebook' => '📘 Facebook',
                'twitter' => '🐦 Twitter', 
                'linkedin' => '💼 LinkedIn'
            ];
            echo '<label style="display: block; margin-bottom: 8px;">';
            echo '<input type="checkbox" name="platforms[]" value="' . $platform . '" checked> ';
            echo $platformNames[$platform] ?? ucfirst($platform);
            echo '</label>';
        }
        echo '</div>';
    }
    echo '</div>';
    
    // Scheduling options
    echo '<div class="card">';
    echo '<h3>⏰ Lên lịch đăng</h3>';
    echo '<div style="margin-bottom: 16px;">';
    echo '<label><input type="checkbox" name="schedule_post" onchange="toggleScheduling(this)"> Lên lịch đăng sau</label>';
    echo '</div>';
    
    echo '<div id="schedule_options" style="display: none;">';
    echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">';
    echo '<div>';
    echo '<label>Ngày:</label>';
    echo '<input type="date" name="schedule_date" min="' . date('Y-m-d') . '">';
    echo '</div>';
    echo '<div>';
    echo '<label>Giờ:</label>';
    echo '<input type="time" name="schedule_time">';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Campaign association
    $campaigns = q($db, "SELECT id, name FROM campaigns WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($campaigns)) {
        echo '<div class="card">';
        echo '<h3>📊 Gắn với Campaign</h3>';
        echo '<select name="campaign_id">';
        echo '<option value="">-- Không gắn campaign --</option>';
        foreach ($campaigns as $campaign) {
            echo '<option value="' . $campaign['id'] . '">' . h($campaign['name']) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }
    
    echo '<div style="margin-top: 24px; text-align: center;">';
    if (!empty($connectedPlatforms)) {
        echo '<button type="submit" class="btn" style="margin-right: 12px;">🚀 Đăng ngay</button>';
    }
    echo '<a href="?action=social_media" class="btn secondary">Hủy</a>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    
    // JavaScript for dynamic form
    echo '<script>';
    echo 'function toggleAIOptions(checkbox) {';
    echo '  document.getElementById("ai_options").style.display = checkbox.checked ? "block" : "none";';
    echo '}';
    echo 'function toggleScheduling(checkbox) {';
    echo '  document.getElementById("schedule_options").style.display = checkbox.checked ? "block" : "none";';
    echo '}';
    echo '</script>';
}

function renderPostsHistory($db) {
    echo '<div class="toolbar">';
    echo '<h1>📋 Lịch sử bài đăng</h1>';
    echo '<a href="?action=social_media" class="btn secondary">← Dashboard</a>';
    echo '</div>';
    
    echo '<div class="container">';
    
    $posts = q($db, "
        SELECT p.*, a.platform, a.display_name as account_name, a.username
        FROM social_media_posts p 
        JOIN social_media_accounts a ON a.id = p.account_id 
        ORDER BY p.created_at DESC 
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="card">';
    echo '<div style="overflow-x: auto;">';
    echo '<table>';
    echo '<tr>';
    echo '<th>Platform</th><th>Tài khoản</th><th>Nội dung</th><th>Trạng thái</th>';
    echo '<th>AI</th><th>Engagement</th><th>Thời gian</th>';
    echo '</tr>';
    
    foreach ($posts as $post) {
        $platformIcons = [
            'facebook' => '📘',
            'twitter' => '🐦', 
            'linkedin' => '💼'
        ];
        
        echo '<tr>';
        echo '<td>' . ($platformIcons[$post['platform']] ?? '🌐') . ' ' . ucfirst($post['platform']) . '</td>';
        echo '<td>' . h($post['account_name'] ?: $post['username']) . '</td>';
        echo '<td style="max-width: 300px;">' . h(substr($post['content'], 0, 100)) . (strlen($post['content']) > 100 ? '...' : '') . '</td>';
        echo '<td>';
        $statusColors = [
            'published' => '#22d3ee',
            'scheduled' => '#fbbf24', 
            'draft' => '#94a3b8',
            'failed' => '#f87171'
        ];
        echo '<span style="color: ' . ($statusColors[$post['post_status']] ?? '#94a3b8') . '">● ' . ucfirst($post['post_status']) . '</span>';
        echo '</td>';
        echo '<td>' . ($post['ai_generated'] ? '🤖' : '-') . '</td>';
        echo '<td>';
        if ($post['post_status'] === 'published') {
            echo '👍 ' . number_format($post['likes_count']) . ' ';
            echo '💬 ' . number_format($post['comments_count']) . ' ';
            echo '🔄 ' . number_format($post['shares_count']);
        } else {
            echo '-';
        }
        echo '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($post['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
}
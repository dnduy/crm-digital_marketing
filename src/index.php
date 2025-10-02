<?php
session_start();


require __DIR__.'/lib/db.php';
require __DIR__.'/lib/auth.php';


// Router đơn giản
$action = $_GET['action'] ?? 'dashboard';
$op = $_GET['op'] ?? '';

// Initialize CSRF token
$csrf_token = csrf_token();


// API (read-only) — có thể dùng trước khi login nếu cần public
if ($action === 'api') {
header('Content-Type: application/json');
$what = $_GET['what'] ?? '';
if ($what === 'contacts') {
$rows = q($db, "SELECT id,name,email,phone,company,source,tags,utm_source,utm_medium,utm_campaign,created_at FROM contacts ORDER BY id DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['contacts'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
if ($what === 'deals') {
$rows = q($db, "SELECT d.id,d.title,d.value,d.currency,d.stage,d.channel,d.utm_source,d.utm_medium,d.utm_campaign,d.expected_close,d.created_at,c.name AS contact FROM deals d LEFT JOIN contacts c ON c.id=d.contact_id ORDER BY d.id DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['deals'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
echo json_encode(['error'=>'unknown'], JSON_UNESCAPED_UNICODE); exit;
}


// Login / Logout
if ($action === 'login') { require __DIR__.'/views/layout.php'; view_login($csrf_token); exit; }
if ($action === 'do_login' && $_SERVER['REQUEST_METHOD']==='POST') {
require_csrf();
if (auth_login($_POST['username'] ?? '', $_POST['password'] ?? '')) { header('Location: ?action=dashboard'); exit; }
$_SESSION['flash'] = 'Sai tài khoản hoặc mật khẩu'; header('Location: ?action=login'); exit;
}
if ($action === 'logout') { auth_logout(); header('Location: ?action=login'); exit; }


// Yêu cầu đăng nhập cho các trang sau
require_login();


// Trang chính (gọi view theo action)
require __DIR__.'/views/layout.php';


switch ($action) {
case 'dashboard': require __DIR__.'/views/dashboard.php'; view_dashboard(); break;
case 'contacts': require __DIR__.'/views/contacts.php'; view_contacts($op); break;
case 'deals': require __DIR__.'/views/deals.php'; view_deals($op); break;
case 'activities': require __DIR__.'/views/activities.php'; view_activities($op); break;
case 'campaigns': require __DIR__.'/views/campaigns.php'; view_campaigns($op); break;
case 'tasks': require __DIR__.'/views/tasks.php'; view_tasks($op); break;
case 'reports': require __DIR__.'/views/reports.php'; view_reports(); break;
default:
layout_header('Không tìm thấy');
echo '<div class="card">Không tìm thấy trang yêu cầu</div>';
layout_footer();
}
?>
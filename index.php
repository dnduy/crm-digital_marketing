<?php
session_start();
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/auth.php';
require __DIR__.'/lib/util.php';

$action = $_GET['action'] ?? 'dashboard';
$op     = $_GET['op'] ?? '';

// API read-only
if ($action === 'api') { header('Content-Type: application/json');
  $what = $_GET['what'] ?? '';
  if ($what==='contacts') { $rows=q($db,"SELECT id,name,email,phone,company,source,tags,utm_source,utm_medium,utm_campaign,created_at FROM contacts ORDER BY id DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC); echo json_encode(['contacts'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
  if ($what==='deals')    { $rows=q($db,"SELECT d.id,d.title,d.value,d.currency,d.stage,d.channel,d.utm_source,d.utm_medium,d.utm_campaign,d.expected_close,d.created_at,c.name AS contact FROM deals d LEFT JOIN contacts c ON c.id=d.contact_id ORDER BY d.id DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC); echo json_encode(['deals'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
  echo json_encode(['error'=>'unknown'],JSON_UNESCAPED_UNICODE); exit; }

// Webhook Lead Intake
if ($action === 'webhook') {
  $secret = (string)setting_get('webhook_secret','');
  $tok = $_GET['token'] ?? ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '');
  if (!$secret || !hash_equals($secret, $tok)) { http_response_code(403); echo 'Forbidden'; exit; }
  $data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
  $name  = trim($data['name']  ?? ($data['full_name']??''));
  $email = trim($data['email'] ?? '');
  $phone = trim($data['phone'] ?? ($data['tel']??''));
  $company = trim($data['company'] ?? '');
  $source  = trim($data['source']  ?? 'webhook');
  $tags    = trim($data['tags']    ?? '');
  $utm_s = $data['utm_source']   ?? '';
  $utm_m = $data['utm_medium']   ?? '';
  $utm_c = $data['utm_campaign'] ?? '';
  $title = $data['deal_title']   ?? ('Lead từ ' . ($source ?: 'web'));
  $value = (float)($data['deal_value'] ?? 0);
  $cid = null;
  if ($email) { $cid = q($db,"SELECT id FROM contacts WHERE email=? ORDER BY id DESC LIMIT 1",[$email])->fetchColumn() ?: null; }
  if (!$cid) {
    q($db,"INSERT INTO contacts(name,email,phone,company,source,tags,utm_source,utm_medium,utm_campaign) VALUES(?,?,?,?,?,?,?,?,?)",
      [$name,$email,$phone,$company,$source,$tags,$utm_s,$utm_m,$utm_c]);
    $cid = (int)$db->lastInsertId();
  }
  if ($title || $value>0) {
    q($db,"INSERT INTO deals(contact_id,title,value,currency,stage,channel,utm_source,utm_medium,utm_campaign) VALUES(?,?,?,?,?,?,?,?,?)",
      [$cid,$title,$value,'USD','New',$source,$utm_s,$utm_m,$utm_c]);
  }
  audit('webhook_intake', ['email'=>$email,'source'=>$source,'cid'=>$cid]);
  echo json_encode(['ok'=>true,'contact_id'=>$cid], JSON_UNESCAPED_UNICODE); exit;
}

// Login
if ($action==='login') { require __DIR__.'/views/layout.php'; view_login(); exit; }
if ($action==='do_login' && $_SERVER['REQUEST_METHOD']==='POST') { require_csrf(); if (auth_login($_POST['username']??'',$_POST['password']??'')) { header('Location: ?action=dashboard'); exit; } $_SESSION['flash']='Sai tài khoản hoặc mật khẩu'; header('Location: ?action=login'); exit; }
if ($action==='logout') { auth_logout(); header('Location: ?action=login'); exit; }

require_login();

require __DIR__.'/views/layout.php';
switch ($action) {
  case 'dashboard':   require __DIR__.'/views/dashboard.php';  view_dashboard(); break;
  case 'contacts':    require __DIR__.'/views/contacts.php';   view_contacts($op); break;
  case 'deals':       require __DIR__.'/views/deals.php';      view_deals($op); break;
  case 'activities':  require __DIR__.'/views/activities.php'; view_activities($op); break;
  case 'campaigns':   require __DIR__.'/views/campaigns.php';  view_campaigns($op); break;
  case 'tasks':       require __DIR__.'/views/tasks.php';      view_tasks($op); break;
  case 'reports':     require __DIR__.'/views/reports.php';    view_reports(); break;
  case 'users':       require __DIR__.'/views/users.php';      view_users($op); break;
  default:
    layout_header('Không tìm thấy'); echo '<div class="card">Không tìm thấy</div>'; layout_footer();
}

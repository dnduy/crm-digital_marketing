<?php
session_start();
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/auth.php';
require __DIR__.'/lib/util.php';
require __DIR__.'/lib/marketing.php';
session_guard();

$action = $_GET['action'] ?? 'dashboard';
$op     = $_GET['op'] ?? '';

// Tracking
if ($action==='t_open'){ $cid=(int)($_GET['c']??0); if($cid>0) update_lead_score($db,$cid,'email_open','Pixel'); header('Content-Type:image/gif'); echo base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='); exit; }
if ($action==='t_click'){ $cid=(int)($_GET['c']??0); $u=$_GET['u']??''; if($cid>0) update_lead_score($db,$cid,'link_click','Click'); if($u){ header('Location: '.$u,302); exit; } echo 'OK'; exit; }

// API CSV export
if ($action==='export' && $_GET['type']==='funnel'){ header('Content-Type:text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=funnel.csv'); $start=$_GET['start']??date('Y-m-01'); $end=$_GET['end']??date('Y-m-t');
  echo "source,total_leads,mql,sql,won\n";
  $rows = q($db,"SELECT source, COUNT(*) total_leads, SUM(CASE WHEN lead_score>=? THEN 1 ELSE 0 END) mql FROM contacts WHERE date(created_at) BETWEEN date(?) AND date(?) GROUP BY source",[(int)($_GET['mql_score']??100),$start,$end])->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as $r){
    $sql = (int)q($db,"SELECT COUNT(DISTINCT d.id) FROM deals d JOIN contacts c ON c.id=d.contact_id WHERE c.source=? AND date(c.created_at) BETWEEN date(?) AND date(?)",[$r['source'],$start,$end])->fetchColumn();
    $won = (int)q($db,"SELECT COUNT(DISTINCT d.id) FROM deals d JOIN contacts c ON c.id=d.contact_id WHERE d.stage='Won' AND c.source=? AND date(c.created_at) BETWEEN date(?) AND date(?)",[$r['source'],$start,$end])->fetchColumn();
    echo implode(',', [ $r['source']?:'unknown', $r['total_leads'], $r['mql'], $sql, $won ])."\n";
  } exit;
}
if ($action==='export' && $_GET['type']==='cohort'){ header('Content-Type:text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=cohort.csv'); $weeks=(int)($_GET['weeks']??12);
  echo "cohort_week,total_leads,deal_30d,deal_60d,deal_90d\n";
  for($i=0;$i<$weeks;$i++){
    $start = date('Y-m-d', strtotime("-$i week Monday"));
    $end   = date('Y-m-d', strtotime($start.' +6 days'));
    $leads = (int)q($db,"SELECT COUNT(*) FROM contacts WHERE date(created_at) BETWEEN date(?) AND date(?)",[$start,$end])->fetchColumn();
    $d30 = (int)q($db,"SELECT COUNT(DISTINCT d.id) FROM deals d JOIN contacts c ON c.id=d.contact_id WHERE date(d.created_at) BETWEEN date(?) AND date(?, '+30 days') AND date(c.created_at) BETWEEN date(?) AND date(?)",[$start,$start,$start,$end])->fetchColumn();
    $d60 = (int)q($db,"SELECT COUNT(DISTINCT d.id) FROM deals d JOIN contacts c ON c.id=d.contact_id WHERE date(d.created_at) BETWEEN date(?) AND date(?, '+60 days') AND date(c.created_at) BETWEEN date(?) AND date(?)",[$start,$start,$start,$end])->fetchColumn();
    $d90 = (int)q($db,"SELECT COUNT(DISTINCT d.id) FROM deals d JOIN contacts c ON c.id=d.contact_id WHERE date(d.created_at) BETWEEN date(?) AND date(?, '+90 days') AND date(c.created_at) BETWEEN date(?) AND date(?)",[$start,$start,$start,$end])->fetchColumn();
    echo implode(',', [$start, $leads, $d30, $d60, $d90])."\n";
  } exit;
}

// AJAX reschedule content
if ($action==='reschedule' && $_SERVER['REQUEST_METHOD']==='POST'){
  require_csrf();
  $id=(int)($_POST['id']??0);
  $dt=$_POST['scheduled_at']??'';
  q($db,"UPDATE content_posts SET scheduled_at=?, status=CASE WHEN status='Draft' THEN 'Scheduled' ELSE status END WHERE id=?",[$dt,$id]);
  header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}

// Webhook
if ($action === 'webhook') {
  $secret = (string)setting_get('webhook_secret','');
  $tok = $_GET['token'] ?? ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '');
  if (!$secret || !hash_equals($secret, $tok)) { http_response_code(403); echo 'Forbidden'; exit; }
  $data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
  $name  = trim($data['name']  ?? ($data['full_name']??'')); $email = trim($data['email'] ?? ''); $phone = trim($data['phone'] ?? ($data['tel']??'')); $company = trim($data['company'] ?? '');
  $source  = trim($data['source']  ?? 'webhook'); $tags = trim($data['tags'] ?? '');
  $utm_s = $data['utm_source']??''; $utm_m = $data['utm_medium']??''; $utm_c = $data['utm_campaign']??'';
  $title = $data['deal_title'] ?? ('Lead từ '.($source?:'web')); $value = (float)($data['deal_value'] ?? 0);
  $is_new=false; $cid=null; if ($email) { $cid = q($db,'SELECT id FROM contacts WHERE email=? ORDER BY id DESC LIMIT 1',[$email])->fetchColumn() ?: null; }
  if (!$cid) { q($db,'INSERT INTO contacts(name,email,phone,company,source,tags,utm_source,utm_medium,utm_campaign,lead_score) VALUES(?,?,?,?,?,?,?,?,?,0)',[$name,$email,$phone,$company,$source,$tags,$utm_s,$utm_m,$utm_c]); $cid=(int)$db->lastInsertId(); $is_new=true; }
  if ($cid) {
    update_lead_score($db,$cid,'webhook_intake','Lead data received');
    if (!empty($data['events']) && is_array($data['events'])){ foreach($data['events'] as $ev){ record_lead_event($cid, $ev['type']??'event', $ev); } }
    if ($is_new) trigger_automation_engine($db,$cid,'contact_created');
  }
  if ($cid && ($title || $value>0)) { q($db,'INSERT INTO deals(contact_id,title,value,currency,stage,channel,utm_source,utm_medium,utm_campaign) VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$title,$value,'USD','New',$source,$utm_s,$utm_m,$utm_c]); }
  audit('webhook_intake',['email'=>$email,'source'=>$source,'cid'=>$cid]);
  header('Content-Type: application/json'); echo json_encode(['ok'=>true,'contact_id'=>$cid], JSON_UNESCAPED_UNICODE); exit;
}

// Auth
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
  case 'lead_score_rules': require __DIR__.'/views/lead_score_rules.php'; view_lead_score_rules($op); break;
  case 'content_scheduler': require __DIR__.'/views/content_scheduler.php'; view_content_scheduler($op); break;
  case 'automation_workflows': require __DIR__.'/views/automation_workflows.php'; view_automation_workflows($op); break;
  case 'users':       require __DIR__.'/views/users.php';      view_users($op); break;
  default:
    layout_header('Không tìm thấy'); echo '<div class="card">Không tìm thấy</div>'; layout_footer();
}

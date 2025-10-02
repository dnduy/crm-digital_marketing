<?php
// ==========================
// FILE: /lib/db.php
// ==========================
$DB_FILE = __DIR__.'/../crm.sqlite';
$db = new PDO('sqlite:'.$DB_FILE);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Schema creation
$db->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT);");
$db->exec("CREATE TABLE IF NOT EXISTS users (
id INTEGER PRIMARY KEY AUTOINCREMENT,
username TEXT UNIQUE,
password_hash TEXT,
role TEXT DEFAULT 'User',
created_at TEXT DEFAULT CURRENT_TIMESTAMP
);");
$db->exec("CREATE TABLE IF NOT EXISTS contacts (
id INTEGER PRIMARY KEY AUTOINCREMENT,
name TEXT NOT NULL,
email TEXT,
phone TEXT,
company TEXT,
source TEXT,
tags TEXT,
utm_source TEXT,
utm_medium TEXT,
utm_campaign TEXT,
created_at TEXT DEFAULT CURRENT_TIMESTAMP
);");
$db->exec("CREATE TABLE IF NOT EXISTS deals (
id INTEGER PRIMARY KEY AUTOINCREMENT,
contact_id INTEGER,
title TEXT NOT NULL,
value REAL DEFAULT 0,
currency TEXT DEFAULT 'USD',
stage TEXT DEFAULT 'New',
channel TEXT,
utm_source TEXT,
utm_medium TEXT,
utm_campaign TEXT,
expected_close TEXT,
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (contact_id) REFERENCES contacts(id)
);");
$db->exec("CREATE TABLE IF NOT EXISTS activities (
id INTEGER PRIMARY KEY AUTOINCREMENT,
contact_id INTEGER,
type TEXT DEFAULT 'note',
content TEXT,
at TEXT DEFAULT CURRENT_TIMESTAMP,
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (contact_id) REFERENCES contacts(id)
);");
$db->exec("CREATE TABLE IF NOT EXISTS campaigns (
id INTEGER PRIMARY KEY AUTOINCREMENT,
name TEXT NOT NULL,
channel TEXT,
budget REAL DEFAULT 0,
spent REAL DEFAULT 0,
status TEXT DEFAULT 'Active',
start_date TEXT,
end_date TEXT,
notes TEXT,
created_at TEXT DEFAULT CURRENT_TIMESTAMP
);");
$db->exec("CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, contact_id INTEGER, title TEXT NOT NULL, due_date TEXT, status TEXT DEFAULT 'Open', owner TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(contact_id) REFERENCES contacts(id));");
// Mới (M): Saved Views
$db->exec("CREATE TABLE IF NOT EXISTS saved_views (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT NOT NULL, name TEXT NOT NULL, query_json TEXT NOT NULL, owner TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);");
// Mới (J): Audit log
$db->exec("CREATE TABLE IF NOT EXISTS audits (id INTEGER PRIMARY KEY AUTOINCREMENT, event TEXT, payload TEXT, at TEXT DEFAULT CURRENT_TIMESTAMP, actor TEXT);");

// Phase 1: Content SEO Management
$db->exec("CREATE TABLE IF NOT EXISTS content_pages (
id INTEGER PRIMARY KEY AUTOINCREMENT,
title TEXT NOT NULL,
slug TEXT UNIQUE NOT NULL,
meta_title TEXT,
meta_description TEXT,
meta_keywords TEXT,
og_title TEXT,
og_description TEXT,
og_image TEXT,
canonical_url TEXT,
content TEXT,
excerpt TEXT,
status TEXT DEFAULT 'draft',
seo_score INTEGER DEFAULT 0,
target_keywords TEXT,
content_type TEXT DEFAULT 'page',
author_id INTEGER,
published_at TEXT,
updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY(author_id) REFERENCES users(id)
);");

$db->exec("CREATE TABLE IF NOT EXISTS keywords_tracking (
id INTEGER PRIMARY KEY AUTOINCREMENT,
keyword TEXT NOT NULL,
page_id INTEGER,
current_rank INTEGER,
target_rank INTEGER,
search_volume INTEGER,
difficulty INTEGER,
url TEXT,
checked_at TEXT,
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY(page_id) REFERENCES content_pages(id)
);");

$db->exec("CREATE TABLE IF NOT EXISTS seo_settings (
id INTEGER PRIMARY KEY AUTOINCREMENT,
site_title TEXT,
site_description TEXT,
site_keywords TEXT,
google_analytics_id TEXT,
google_search_console_id TEXT,
facebook_pixel_id TEXT,
robots_txt TEXT,
sitemap_frequency TEXT DEFAULT 'weekly',
created_at TEXT DEFAULT CURRENT_TIMESTAMP
);");

// Phase 2: Advanced Campaign Analytics
$db->exec("CREATE TABLE IF NOT EXISTS campaign_metrics (
id INTEGER PRIMARY KEY AUTOINCREMENT,
campaign_id INTEGER,
date TEXT NOT NULL,
impressions INTEGER DEFAULT 0,
clicks INTEGER DEFAULT 0,
conversions INTEGER DEFAULT 0,
cost REAL DEFAULT 0,
revenue REAL DEFAULT 0,
ctr REAL DEFAULT 0,
cpc REAL DEFAULT 0,
cpa REAL DEFAULT 0,
roas REAL DEFAULT 0,
bounce_rate REAL DEFAULT 0,
session_duration INTEGER DEFAULT 0,
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY(campaign_id) REFERENCES campaigns(id)
);");

$db->exec("CREATE TABLE IF NOT EXISTS attribution_touchpoints (
id INTEGER PRIMARY KEY AUTOINCREMENT,
contact_id INTEGER,
deal_id INTEGER,
campaign_id INTEGER,
touchpoint_type TEXT DEFAULT 'interaction',
utm_source TEXT,
utm_medium TEXT,
utm_campaign TEXT,
utm_content TEXT,
utm_term TEXT,
page_url TEXT,
referrer TEXT,
device_type TEXT,
browser TEXT,
location TEXT,
attribution_weight REAL DEFAULT 1.0,
revenue_attributed REAL DEFAULT 0,
occurred_at TEXT DEFAULT CURRENT_TIMESTAMP,
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY(contact_id) REFERENCES contacts(id),
FOREIGN KEY(deal_id) REFERENCES deals(id),
FOREIGN KEY(campaign_id) REFERENCES campaigns(id)
);");

$db->exec("CREATE TABLE IF NOT EXISTS ab_tests (
id INTEGER PRIMARY KEY AUTOINCREMENT,
campaign_id INTEGER,
test_name TEXT NOT NULL,
test_type TEXT DEFAULT 'landing_page',
variant_a_config TEXT,
variant_b_config TEXT,
variant_a_traffic INTEGER DEFAULT 50,
variant_b_traffic INTEGER DEFAULT 50,
variant_a_conversions INTEGER DEFAULT 0,
variant_b_conversions INTEGER DEFAULT 0,
variant_a_revenue REAL DEFAULT 0,
variant_b_revenue REAL DEFAULT 0,
status TEXT DEFAULT 'running',
winner TEXT,
confidence_level REAL,
start_date TEXT,
end_date TEXT,
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY(campaign_id) REFERENCES campaigns(id)
);");

$db->exec("CREATE TABLE IF NOT EXISTS campaign_goals (
id INTEGER PRIMARY KEY AUTOINCREMENT,
campaign_id INTEGER,
goal_type TEXT NOT NULL,
goal_name TEXT NOT NULL,
target_value REAL NOT NULL,
current_value REAL DEFAULT 0,
unit TEXT DEFAULT 'number',
priority TEXT DEFAULT 'medium',
deadline TEXT,
status TEXT DEFAULT 'active',
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY(campaign_id) REFERENCES campaigns(id)
);");

$db->exec("CREATE TABLE IF NOT EXISTS conversion_funnels (
id INTEGER PRIMARY KEY AUTOINCREMENT,
campaign_id INTEGER,
funnel_name TEXT NOT NULL,
stage_1_name TEXT DEFAULT 'Awareness',
stage_2_name TEXT DEFAULT 'Interest', 
stage_3_name TEXT DEFAULT 'Consideration',
stage_4_name TEXT DEFAULT 'Conversion',
stage_5_name TEXT DEFAULT 'Retention',
stage_1_count INTEGER DEFAULT 0,
stage_2_count INTEGER DEFAULT 0,
stage_3_count INTEGER DEFAULT 0,
stage_4_count INTEGER DEFAULT 0,
stage_5_count INTEGER DEFAULT 0,
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY(campaign_id) REFERENCES campaigns(id)
);");

// Indexes
$db->exec("CREATE INDEX IF NOT EXISTS idx_contacts_email ON contacts(email);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_contacts_created ON contacts(created_at);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_deals_stage_created ON deals(stage, created_at);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_content_pages_slug ON content_pages(slug);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_content_pages_status ON content_pages(status);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_keywords_tracking_keyword ON keywords_tracking(keyword);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_campaign_metrics_date ON campaign_metrics(campaign_id, date);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_attribution_touchpoints_contact ON attribution_touchpoints(contact_id, occurred_at);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_attribution_touchpoints_campaign ON attribution_touchpoints(campaign_id, occurred_at);");

// Seed admin
function ensure_user($db){
$exists = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($exists===0){
$pass = getenv('ADMIN_PASS') ?: 'admin123';
$hash = password_hash($pass, PASSWORD_DEFAULT);
$st = $db->prepare("INSERT INTO users(username,password_hash,role) VALUES(?,?,?)");
$st->execute(['admin',$hash,'Admin']);
}
}
ensure_user($db);

function q($db,$sql,$params=[]){ $st=$db->prepare($sql); $st->execute($params); return $st; }
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// CSRF
if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf_token = $_SESSION['csrf'];
function csrf_field(){ echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function require_csrf(){ 
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { 
        http_response_code(403); 
        die('CSRF token không hợp lệ'); 
    } 
}

// Settings helpers
function setting_get($k,$def=null){ global $db; $v=q($db,"SELECT v FROM settings WHERE k=?",[$k])->fetchColumn(); return ($v===false)?$def:$v; }
function setting_set($k,$v){ global $db; q($db,"INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v",[$k,$v]); }

// Audit
function audit($event,$payload=[], $actor=null){ global $db; $actor = $actor ?? ($_SESSION['uname']??''); q($db,"INSERT INTO audits(event,payload,actor) VALUES(?,?,?)",[$event,json_encode($payload,JSON_UNESCAPED_UNICODE),$actor]); }
?>
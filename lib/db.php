<?php
$DB_FILE = __DIR__.'/../crm.sqlite';
$db = new PDO('sqlite:'.$DB_FILE);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT);");
$db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password_hash TEXT, role TEXT DEFAULT 'Admin', created_at TEXT DEFAULT CURRENT_TIMESTAMP);");
$db->exec("CREATE TABLE IF NOT EXISTS contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT, phone TEXT, company TEXT, source TEXT, tags TEXT, utm_source TEXT, utm_medium TEXT, utm_campaign TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);");
$db->exec("CREATE TABLE IF NOT EXISTS deals (id INTEGER PRIMARY KEY AUTOINCREMENT, contact_id INTEGER, title TEXT NOT NULL, value REAL DEFAULT 0, currency TEXT DEFAULT 'USD', stage TEXT DEFAULT 'New', channel TEXT, utm_source TEXT, utm_medium TEXT, utm_campaign TEXT, expected_close TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(contact_id) REFERENCES contacts(id));");
$db->exec("CREATE TABLE IF NOT EXISTS activities (id INTEGER PRIMARY KEY AUTOINCREMENT, contact_id INTEGER, type TEXT, content TEXT, at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(contact_id) REFERENCES contacts(id));");
$db->exec("CREATE TABLE IF NOT EXISTS campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, channel TEXT, budget REAL DEFAULT 0, spent REAL DEFAULT 0, status TEXT DEFAULT 'Active', start_date TEXT, end_date TEXT, notes TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);");
$db->exec("CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, contact_id INTEGER, title TEXT NOT NULL, due_date TEXT, status TEXT DEFAULT 'Open', owner TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(contact_id) REFERENCES contacts(id));");
$db->exec("CREATE TABLE IF NOT EXISTS saved_views (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT NOT NULL, name TEXT NOT NULL, query_json TEXT NOT NULL, owner TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);");
$db->exec("CREATE TABLE IF NOT EXISTS audits (id INTEGER PRIMARY KEY AUTOINCREMENT, event TEXT, payload TEXT, at TEXT DEFAULT CURRENT_TIMESTAMP, actor TEXT);");

$db->exec("CREATE INDEX IF NOT EXISTS idx_contacts_email ON contacts(email);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_contacts_created ON contacts(created_at);");
$db->exec("CREATE INDEX IF NOT EXISTS idx_deals_stage_created ON deals(stage, created_at);");

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

if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf_token = $_SESSION['csrf'];
function csrf_field(){ echo '<input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">'; }
function require_csrf(){ if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(403); die('CSRF token không hợp lệ'); } }

function setting_get($k,$def=null){ global $db; $v=q($db,"SELECT v FROM settings WHERE k=?",[$k])->fetchColumn(); return ($v===false)?$def:$v; }
function setting_set($k,$v){ global $db; q($db,"INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v",[$k,$v]); }

function audit($event,$payload=[], $actor=null){ global $db; $actor = $actor ?? ($_SESSION['uname']??''); q($db,"INSERT INTO audits(event,payload,actor) VALUES(?,?,?)",[$event,json_encode($payload,JSON_UNESCAPED_UNICODE),$actor]); }

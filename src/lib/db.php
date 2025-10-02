<?php
// ==========================
// FILE: /lib/db.php
// ==========================
$DB_FILE = __DIR__.'/../crm.sqlite';
$db = new PDO('sqlite:'.$DB_FILE);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Helper function for database queries
function q($db, $sql, $params = []) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Helper function for HTML escaping
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF protection functions
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    echo '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">';
}

function require_csrf() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) {
        die('CSRF token mismatch');
    }
}

// Schema
$db->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT);");
$db->exec("CREATE TABLE IF NOT EXISTS users (
id INTEGER PRIMARY KEY AUTOINCREMENT,
username TEXT UNIQUE,
password_hash TEXT,
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
$db->exec("CREATE TABLE IF NOT EXISTS tasks (
id INTEGER PRIMARY KEY AUTOINCREMENT,
contact_id INTEGER,
title TEXT NOT NULL,
due_date TEXT,
status TEXT DEFAULT 'Open',
owner TEXT,
created_at TEXT DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (contact_id) REFERENCES contacts(id)
);");

// Create default admin user if not exists
$admin_exists = q($db, "SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
if ($admin_exists == 0) {
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    q($db, "INSERT INTO users (username, password_hash) VALUES (?, ?)", ['admin', $admin_password]);
}
?>
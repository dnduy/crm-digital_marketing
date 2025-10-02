<?php
// ==========================
// FILE: /lib/db.php
// ==========================
$DB_FILE = __DIR__.'/../crm.sqlite';
$db = new PDO('sqlite:'.$DB_FILE);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


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
?>
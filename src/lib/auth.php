<?php
function auth_login($username, $password){
global $db; $st = q($db, "SELECT * FROM users WHERE username=?", [$username]); $u = $st->fetch(PDO::FETCH_ASSOC);
if ($u && password_verify($password, $u['password_hash'])) { $_SESSION['uid']=$u['id']; $_SESSION['uname']=$u['username']; return true; }
return false;
}
function auth_logout(){ unset($_SESSION['uid'], $_SESSION['uname']); }
function require_login(){ if (!isset($_SESSION['uid'])) { header('Location: ?action=login'); exit; } }
?>
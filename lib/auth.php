<?php
function auth_login($username,$password){ global $db; $st=q($db,"SELECT * FROM users WHERE username=?",[$username]); $u=$st->fetch(PDO::FETCH_ASSOC); if($u && password_verify($password,$u['password_hash'])){ $_SESSION['uid']=$u['id']; $_SESSION['uname']=$u['username']; $_SESSION['role']=$u['role']?:'Admin'; audit('login_success',['u'=>$u['username']]); return true; } audit('login_failed',['u'=>$username]); return false; }
function auth_logout(){ audit('logout',[]); unset($_SESSION['uid'],$_SESSION['uname'],$_SESSION['role']); }
function require_login(){ if (!isset($_SESSION['uid'])) { header('Location: ?action=login'); exit; } }
function require_admin(){ if (($_SESSION['role']??'')!=='Admin'){ http_response_code(403); die('Chỉ Admin mới truy cập chức năng này'); } }

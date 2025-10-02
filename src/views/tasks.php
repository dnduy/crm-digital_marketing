<?php
// ==========================
// FILE: /views/tasks.php
// ==========================
function view_tasks($op){
global $db; layout_header('Công việc');
if ($op==='create' && $_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db, "INSERT INTO tasks(contact_id,title,due_date,status,owner) VALUES(?,?,?,?,?)", [$_POST['contact_id']?:null,$_POST['title']??'',$_POST['due_date']??null,$_POST['status']??'Open',$_POST['owner']??($_SESSION['uname']??'')]); header('Location: ?action=tasks'); exit; }
if ($op==='update' && $_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db, "UPDATE tasks SET contact_id=?,title=?,due_date=?,status=?,owner=? WHERE id=?", [$_POST['contact_id']?:null,$_POST['title']??'',$_POST['due_date']??null,$_POST['status']??'Open',$_POST['owner']??'',(int)$_POST['id']]); header('Location: ?action=tasks'); exit; }
if ($op==='delete'){ q($db, "DELETE FROM tasks WHERE id=?", [(int)($_GET['id']??0)]); header('Location: ?action=tasks'); exit; }


if ($op==='new' || $op==='edit'){
$t=['id'=>'','contact_id'=>($_GET['contact_id']??''),'title'=>'','due_date'=>'','status'=>'Open','owner'=>($_SESSION['uname']??'')];
if ($op==='edit') $t = q($db, "SELECT * FROM tasks WHERE id=?", [(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC) ?: $t;
$contacts = q($db, "SELECT id,name FROM contacts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
echo '<div class="card"><h3>'.($op==='new'?'Thêm công việc':'Sửa công việc').'</h3><form method="post" action="?action=tasks&op='.($op==='new'?'create':'update').'">'; csrf_field(); if ($op==='edit') echo '<input type="hidden" name="id" value="'.h($t['id']).'">';
echo '<div class="grid cols-3">';
echo '<div><label>Liên hệ</label><select name="contact_id"><option value="">—</option>'; foreach($contacts as $c){ $sel=$t['contact_id']==$c['id']?'selected':''; echo '<option '.h($sel).' value="'.h($c['id']).'">'.h($c['name']).'</option>'; } echo '</select></div>';
echo '<div><label>Tiêu đề</label><input name="title" required value="'.h($t['title']).'"></div>';
echo '<div><label>Hạn</label><input name="due_date" type="date" value="'.h($t['due_date']).'"></div>';
echo '<div><label>Trạng thái</label><select name="status">'; foreach(['Open','In Progress','Done'] as $st){ $sel=$t['status']===$st?'selected':''; echo '<option '.h($sel).' value="'.h($st).'">'.h($st).'</option>'; } echo '</select></div>';
echo '<div><label>Phụ trách</label><input name="owner" value="'.h($t['owner']).'"></div>';
echo '</div><div style="margin-top:12px"><button class="btn">Lưu</button> <a class="btn secondary" href="?action=tasks">Huỷ</a></div></form></div>';
}


echo '<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h3>Danh sách công việc</h3><a class="btn" href="?action=tasks&op=new">Thêm</a></div>';
$rows = q($db, "SELECT t.*, c.name as contact_name FROM tasks t LEFT JOIN contacts c ON c.id=t.contact_id ORDER BY CASE t.status WHEN 'Open' THEN 0 WHEN 'In Progress' THEN 1 ELSE 2 END, IFNULL(due_date,'9999-12-31') ASC")->fetchAll(PDO::FETCH_ASSOC);
echo '<table><tr><th>Hạn</th><th>Tiêu đề</th><th>Trạng thái</th><th>Phụ trách</th><th>Liên hệ</th><th></th></tr>';
foreach($rows as $r){ echo '<tr><td>'.h($r['due_date']).'</td><td>'.h($r['title']).'</td><td>'.h($r['status']).'</td><td>'.h($r['owner']).'</td><td>'.h($r['contact_name']).'</td><td><a href="?action=tasks&op=edit&id='.(int)$r['id'].'">Sửa</a> · <a href="?action=tasks&op=delete&id='.(int)$r['id'].'" onclick="return confirm(\'Xoá?\')">Xoá</a></td></tr>'; }
echo '</table></div>';
layout_footer();
}
?>
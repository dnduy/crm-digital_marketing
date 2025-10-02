<?php
// ==========================
// FILE: /views/campaigns.php
// ==========================
function view_campaigns($op){
global $db; layout_header('Chiến dịch');
if ($op==='create' && $_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db, "INSERT INTO campaigns(name,channel,budget,spent,status,start_date,end_date,notes) VALUES(?,?,?,?,?,?,?,?)", [$_POST['name']??'',$_POST['channel']??'',(float)($_POST['budget']??0),(float)($_POST['spent']??0),$_POST['status']??'Active',$_POST['start_date']??null,$_POST['end_date']??null,$_POST['notes']??'']); header('Location: ?action=campaigns'); exit; }
if ($op==='update' && $_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db, "UPDATE campaigns SET name=?,channel=?,budget=?,spent=?,status=?,start_date=?,end_date=?,notes=? WHERE id=?", [$_POST['name']??'',$_POST['channel']??'',(float)($_POST['budget']??0),(float)($_POST['spent']??0),$_POST['status']??'Active',$_POST['start_date']??null,$_POST['end_date']??null,$_POST['notes']??'',(int)$_POST['id']]); header('Location: ?action=campaigns'); exit; }
if ($op==='delete'){ q($db, "DELETE FROM campaigns WHERE id=?", [(int)($_GET['id']??0)]); header('Location: ?action=campaigns'); exit; }


if ($op==='new' || $op==='edit'){
$c=['id'=>'','name'=>'','channel'=>'','budget'=>'','spent'=>'','status'=>'Active','start_date'=>'','end_date'=>'','notes'=>''];
if ($op==='edit') $c = q($db, "SELECT * FROM campaigns WHERE id=?", [(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC) ?: $c;
echo '<div class="card"><h3>'.($op==='new'?'Thêm chiến dịch':'Sửa chiến dịch').'</h3><form method="post" action="?action=campaigns&op='.($op==='new'?'create':'update').'">'; csrf_field(); if ($op==='edit') echo '<input type="hidden" name="id" value="'.h($c['id']).'">';
echo '<div class="grid cols-3">';
echo '<div><label>Tên</label><input name="name" required value="'.h($c['name']).'"></div>';
echo '<div><label>Kênh</label><select name="channel">'; foreach(['google','facebook','tiktok','email','seo'] as $ch){ $sel=$c['channel']===$ch?'selected':''; echo '<option '.h($sel).' value="'.h($ch).'">'.h($ch).'</option>'; } echo '</select></div>';
echo '<div><label>Ngân sách</label><input name="budget" type="number" step="0.01" value="'.h($c['budget']).'"></div>';
echo '<div><label>Đã chi</label><input name="spent" type="number" step="0.01" value="'.h($c['spent']).'"></div>';
echo '<div><label>Trạng thái</label><select name="status">'; foreach(['Active','Paused','Completed'] as $st){ $sel=$c['status']===$st?'selected':''; echo '<option '.h($sel).' value="'.h($st).'">'.h($st).'</option>'; } echo '</select></div>';
echo '<div><label>Bắt đầu</label><input name="start_date" type="date" value="'.h($c['start_date']).'"></div>';
echo '<div><label>Kết thúc</label><input name="end_date" type="date" value="'.h($c['end_date']).'"></div>';
echo '</div><div><label>Ghi chú</label><textarea name="notes" rows="3">'.h($c['notes']).'</textarea></div>';
echo '<div style="margin-top:12px"><button class="btn">Lưu</button> <a class="btn secondary" href="?action=campaigns">Huỷ</a></div></form></div>';
}


echo '<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h3>Danh sách chiến dịch</h3><a class="btn" href="?action=campaigns&op=new">Thêm</a></div>';
$rows = q($db, "SELECT * FROM campaigns ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
echo '<table><tr><th>Tên</th><th>Kênh</th><th>Ngân sách</th><th>Đã chi</th><th>Trạng thái</th><th>Thời gian</th><th></th></tr>';
foreach($rows as $r){ echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['channel']).'</td><td>'.number_format($r['budget'],2).'</td><td>'.number_format($r['spent'],2).'</td><td>'.h($r['status']).'</td><td>'.h($r['start_date']).' → '.h($r['end_date']).'</td><td><a href="?action=campaigns&op=edit&id='.(int)$r['id'].'">Sửa</a> · <a href="?action=campaigns&op=delete&id='.(int)$r['id'].'" onclick="return confirm(\'Xoá?\')">Xoá</a></td></tr>'; }
echo '</table></div>';
layout_footer();
}
?>
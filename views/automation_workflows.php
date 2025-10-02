<?php
function view_automation_workflows($op){ global $db; require_admin(); layout_header('Tự động hoá');
  if($op==='create'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"INSERT INTO automation_workflows(name,trigger_type,config_json,status) VALUES(?,?,?,?)",[$_POST['name']??'',$_POST['trigger_type']??'',$_POST['config_json']??'{}',$_POST['status']??'Active']); header('Location: ?action=automation_workflows'); exit; }
  if($op==='update'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"UPDATE automation_workflows SET name=?,trigger_type=?,config_json=?,status=? WHERE id=?",[$_POST['name']??'',$_POST['trigger_type']??'',$_POST['config_json']??'{}',$_POST['status']??'Active',(int)$_POST['id']]); header('Location: ?action=automation_workflows'); exit; }
  if($op==='delete'){ $id=(int)($_GET['id']??0); q($db,'DELETE FROM automation_workflows WHERE id=?',[$id]); header('Location: ?action=automation_workflows'); exit; }
  if($op==='new'||$op==='edit'){ $r=['id'=>'','name'=>'','trigger_type'=>'score_achieved','config_json'=>'{"min_score":100,"action":"send_email","email_subject":"Chúc mừng","email_body":"<p>Xin chúc mừng!</p>"}','status'=>'Active']; if($op==='edit'){ $r=q($db,'SELECT * FROM automation_workflows WHERE id=?',[(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC)?:$r; }
    echo '<div class="card"><h3>'.($op==='new'?'Thêm workflow':'Sửa workflow').'</h3><form method="post" action="?action=automation_workflows&op='.($op==='new'?'create':'update').'">'; csrf_field(); if($op==='edit') echo '<input type="hidden" name="id" value="'.h($r['id']).'">'; echo '<div class="grid cols-3">';
    echo '<div><label>Tên</label><input name="name" required value="'.h($r['name']).'"></div>';
    echo '<div><label>Trigger</label><select name="trigger_type">'; foreach(['score_achieved','contact_created'] as $t){ $sel=$r['trigger_type']===$t?'selected':''; echo '<option '.$sel.'>'.$t.'</option>'; } echo '</select></div>';
    echo '<div><label>Trạng thái</label><select name="status">'; foreach(['Active','Paused'] as $st){ $sel=$r['status']===$st?'selected':''; echo '<option '.$sel.'>'.$st.'</option>'; } echo '</select></div>';
    echo '<div style="grid-column:1/4"><label>Config (JSON)</label><textarea name="config_json" rows="6">'.h($r['config_json']).'</textarea><div class="hint">VD score_achieved: {"min_score":120,"action":"send_email","email_subject":"Xin chào","email_body":"<p>Nội dung</p>"}</div></div>';
    echo '</div><div style="margin-top:12px"><button class="btn">Lưu</button> <a class="btn secondary" href="?action=automation_workflows">Huỷ</a></div></form></div>'; }
  echo '<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h3>Danh sách workflows</h3><a class="btn" href="?action=automation_workflows&op=new">Thêm</a></div>';
  $rows=q($db,'SELECT * FROM automation_workflows ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
  echo '<table><tr><th>Tên</th><th>Trigger</th><th>Trạng thái</th><th>Config</th><th></th></tr>';
  foreach($rows as $r){ echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['trigger_type']).'</td><td>'.h($r['status']).'</td><td><pre style="white-space:pre-wrap">'.h($r['config_json']).'</pre></td><td><a href="?action=automation_workflows&op=edit&id='.(int)$r['id'].'">Sửa</a> · <a href="?action=automation_workflows&op=delete&id='.(int)$r['id'].'" onclick="return confirm('Xoá?')">Xoá</a></td></tr>'; }
  echo '</table></div>';
  layout_footer();
}

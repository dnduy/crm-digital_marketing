<?php
function view_lead_score_rules($op){ global $db; require_admin(); layout_header('Quy tắc chấm điểm');
  if($op==='create'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"INSERT INTO lead_score_rules(name,event_type,score,description) VALUES(?,?,?,?)",[$_POST['name']??'',$_POST['event_type']??'',(int)($_POST['score']??0),$_POST['description']??'']); header('Location: ?action=lead_score_rules'); exit; }
  if($op==='update'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"UPDATE lead_score_rules SET name=?,event_type=?,score=?,description=? WHERE id=?",[$_POST['name']??'',$_POST['event_type']??'',(int)($_POST['score']??0),$_POST['description']??'',(int)$_POST['id']]); header('Location: ?action=lead_score_rules'); exit; }
  if($op==='delete'){ q($db,"DELETE FROM lead_score_rules WHERE id=?",[(int)($_GET['id']??0)]); header('Location: ?action=lead_score_rules'); exit; }
  if($op==='new'||$op==='edit'){ $r=['id'=>'','name'=>'','event_type'=>'','score'=>0,'description'=>'']; if($op==='edit'){ $r=q($db,'SELECT * FROM lead_score_rules WHERE id=?',[(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC)?:$r; }
    echo '<div class="card"><h3>'.($op==='new'?'Thêm quy tắc':'Sửa quy tắc').'</h3><form method="post" action="?action=lead_score_rules&op='.($op==='new'?'create':'update').'">'; csrf_field(); if($op==='edit') echo '<input type="hidden" name="id" value="'.h($r['id']).'">'; echo '<div class="grid cols-3">';
    echo '<div><label>Tên</label><input name="name" required value="'.h($r['name']).'"></div>';
    echo '<div><label>Event Type</label><input name="event_type" placeholder="webhook_intake" required value="'.h($r['event_type']).'"></div>';
    echo '<div><label>Score</label><input name="score" type="number" value="'.h($r['score']).'"></div>';
    echo '<div style="grid-column:1/4"><label>Mô tả</label><textarea name="description" rows="3">'.h($r['description']).'</textarea></div>';
    echo '</div><div style="margin-top:12px"><button class="btn">Lưu</button> <a class="btn secondary" href="?action=lead_score_rules">Huỷ</a></div></form></div>'; }
  echo '<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h3>Danh sách quy tắc</h3><a class="btn" href="?action=lead_score_rules&op=new">Thêm</a></div>';
  $rows=q($db,'SELECT * FROM lead_score_rules ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
  echo '<table><tr><th>Tên</th><th>Event</th><th>Score</th><th>Mô tả</th><th></th></tr>';
  foreach($rows as $r){ echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['event_type']).'</td><td>'.(int)$r['score'].'</td><td>'.h($r['description']).'</td><td><a href="?action=lead_score_rules&op=edit&id='.(int)$r['id'].'">Sửa</a> · <a href="?action=lead_score_rules&op=delete&id='.(int)$r['id'].'" onclick="return confirm('Xoá?')">Xoá</a></td></tr>'; }
  echo '</table></div>';
  layout_footer();
}

<?php
// ==========================
// FILE: /views/contacts.php (Import CSV + Saved Views + Filters nâng cao)
// ==========================
function view_contacts($op){ global $db; if($op==='create'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"INSERT INTO contacts(name,email,phone,company,source,tags,utm_source,utm_medium,utm_campaign) VALUES(?,?,?,?,?,?,?,?,?)",[$_POST['name']??'',$_POST['email']??'',$_POST['phone']??'',$_POST['company']??'',$_POST['source']??'',$_POST['tags']??'',$_POST['utm_source']??'',$_POST['utm_medium']??'',$_POST['utm_campaign']??'']); header('Location: ?action=contacts'); exit; } if($op==='update'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"UPDATE contacts SET name=?,email=?,phone=?,company=?,source=?,tags=?,utm_source=?,utm_medium=?,utm_campaign=? WHERE id=?",[$_POST['name']??'',$_POST['email']??'',$_POST['phone']??'',$_POST['company']??'',$_POST['source']??'',$_POST['tags']??'',$_POST['utm_source']??'',$_POST['utm_medium']??'',$_POST['utm_campaign']??'',(int)$_POST['id']]); header('Location: ?action=contacts'); exit; } if($op==='delete'){ q($db,"DELETE FROM contacts WHERE id=?",[(int)($_GET['id']??0)]); header('Location: ?action=contacts'); exit; }
// Import CSV (L)
if($op==='import_upload' && $_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); if(!isset($_FILES['csv'])||$_FILES['csv']['error']!==UPLOAD_ERR_OK){ $_SESSION['flash']='Tải CSV lỗi'; header('Location: ?action=contacts&op=import'); exit; } $tmp=$_FILES['csv']['tmp_name']; $rows=array_map('str_getcsv', file($tmp)); $header=array_map('trim',$rows[0]??[]); $_SESSION['import_header']=$header; $_SESSION['import_rows']=array_slice($rows,1,2000); header('Location: ?action=contacts&op=import_map'); exit; }
if($op==='import_commit' && $_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); $H=$_SESSION['import_header']??[]; $R=$_SESSION['import_rows']??[]; if(!$H||!$R){ $_SESSION['flash']='Phiên import hết hạn'; header('Location: ?action=contacts&op=import'); exit; } $map=[ 'name'=>$_POST['m_name']??'', 'email'=>$_POST['m_email']??'', 'phone'=>$_POST['m_phone']??'', 'company'=>$_POST['m_company']??'', 'source'=>$_POST['m_source']??'', 'tags'=>$_POST['m_tags']??'', 'utm_source'=>$_POST['m_utm_source']??'', 'utm_medium'=>$_POST['m_utm_medium']??'', 'utm_campaign'=>$_POST['m_utm_campaign']??'' ]; $idx=[]; foreach($map as $k=>$col){ if($col==='') continue; $i=array_search($col,$H,true); if($i!==false) $idx[$k]=$i; }
$n=0; foreach($R as $row){ $vals=[]; foreach(['name','email','phone','company','source','tags','utm_source','utm_medium','utm_campaign'] as $k){ $vals[$k]=isset($idx[$k])?trim($row[$idx[$k]]??''):''; } if(!$vals['name'] && !$vals['email'] && !$vals['phone']) continue; q($db,"INSERT INTO contacts(name,email,phone,company,source,tags,utm_source,utm_medium,utm_campaign) VALUES(?,?,?,?,?,?,?,?,?)",[$vals['name'],$vals['email'],$vals['phone'],$vals['company'],$vals['source'],$vals['tags'],$vals['utm_source'],$vals['utm_medium'],$vals['utm_campaign']]); $n++; }
unset($_SESSION['import_header'],$_SESSION['import_rows']); $_SESSION['flash']='Đã import '.$n.' liên hệ'; header('Location: ?action=contacts'); exit; }


layout_header('Liên hệ');
// Top actions
echo '<div class="card" style="display:flex;gap:8px;align-items:center;justify-content:space-between"><div><a class="btn" href="?action=contacts&op=new">Thêm</a> <a class="btn secondary" href="?action=contacts&op=import">Import CSV</a></div><div>Saved Views: ';
// Saved Views (M)
$views = q($db,"SELECT id,name FROM saved_views WHERE kind='contacts' AND (owner IS NULL OR owner=? ) ORDER BY created_at DESC",[$_SESSION['uname']??''])->fetchAll(PDO::FETCH_ASSOC);
foreach($views as $v){ echo '<a class="btn secondary" style="margin-right:6px" href="?action=contacts&op=use_view&id='.(int)$v['id'].'">'.h($v['name']).'</a>'; }
echo '</div></div>';


// Import wizard UI
if($op==='import'){ echo '<div class="card"><h3>Import CSV</h3><form method="post" enctype="multipart/form-data" action="?action=contacts&op=import_upload">'; csrf_field(); echo '<input type="file" name="csv" accept=".csv" required> <button class="btn">Tải lên</button></form><div class="hint">CSV nên có hàng tiêu đề. Hỗ trợ cột: name,email,phone,company,source,tags,utm_source,utm_medium,utm_campaign</div></div>'; layout_footer(); return; }
if($op==='import_map'){ $H=$_SESSION['import_header']??[]; $R=$_SESSION['import_rows']??[]; echo '<div class="card"><h3>Ghép cột</h3><form method="post" action="?action=contacts&op=import_commit">'; csrf_field(); $opts=function($H){ $s='<option value="">—</option>'; foreach($H as $h){ $s.='<option>'.h($h).'</option>'; } return $s; }; echo '<div class="grid cols-3">'; foreach(['name'=>'Họ tên','email'=>'Email','phone'=>'Điện thoại','company'=>'Công ty','source'=>'Nguồn','tags'=>'Tags','utm_source'=>'UTM Source','utm_medium'=>'UTM Medium','utm_campaign'=>'UTM Campaign'] as $k=>$label){ echo '<div><label>'.h($label).'</label><select name="m_'.h($k).'">'.$opts($H).'</select></div>'; } echo '</div><div style="margin-top:12px"><button class="btn">Import</button> <a class="btn secondary" href="?action=contacts">Huỷ</a></div></form></div>'; if($R){ echo '<div class="card"><h3>Preview (5 dòng)</h3><table>'; $hrow='<tr>'; foreach($H as $h){ $hrow.='<th>'.h($h).'</th>'; } echo $hrow.'</tr>'; foreach(array_slice($R,0,5) as $r){ echo '<tr>'; foreach($H as $i=>$col){ echo '<td>'.h($r[$i]??'').'</td>'; } echo '</tr>'; } echo '</table></div>'; } layout_footer(); return; }


// Saved view actions (M)
if($op==='save_view' && $_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); $name=trim($_POST['view_name']??''); $qj=json_encode(filter_params(),JSON_UNESCAPED_UNICODE); if($name){ q($db,"INSERT INTO saved_views(kind,name,query_json,owner) VALUES(?,?,?,?)",['contacts',$name,$qj,$_SESSION['uname']??null]); } header('Location: ?action=contacts'); exit; }
if($op==='use_view'){ $id=(int)($_GET['id']??0); $v=q($db,"SELECT * FROM saved_views WHERE id=?",[$id])->fetch(PDO::FETCH_ASSOC); if($v){ $p=json_decode($v['query_json'],true)?:[]; $_GET=array_merge($_GET,$p); } }
if($op==='delete_view'){ require_admin(); $id=(int)($_GET['id']??0); q($db,"DELETE FROM saved_views WHERE id=?",[$id]); header('Location: ?action=contacts'); exit; }


// Lọc nâng cao (M)
$p = filter_params();
echo '<div class="card"><form method="get" class="grid" style="grid-template-columns: 1fr 180px 180px 180px 140px 140px; gap:8px">';
echo '<input type="hidden" name="action" value="contacts">';
echo '<input name="q" placeholder="Tìm tên/email/điện thoại/tags" value="'.h($p['q']).'">';
echo '<input name="tags" placeholder="Tags (phân tách dấu phẩy)" value="'.h($p['tags']).'">';
echo '<input name="sourcef" placeholder="Nguồn" value="'.h($p['sourcef']).'">';
echo '<input type="date" name="from" value="'.h($p['from']).'"><input type="date" name="to" value="'.h($p['to']).'">';
echo '<button class="btn secondary">Lọc</button></form>';
echo '<form method="post" action="?action=contacts&op=save_view" style="margin-top:8px">'; csrf_field(); echo '<input name="view_name" placeholder="Tên Saved View" style="width:240px"> <button class="btn">Lưu view</button></form></div>';


// Truy vấn + phân trang
$where=[];$params=[]; if($p['q']){ $like='%'.$p['q'].'%'; $where[]='(name LIKE ? OR email LIKE ? OR phone LIKE ? OR tags LIKE ?)'; array_push($params,$like,$like,$like,$like); }
if($p['sourcef']){ $where[]='source=?'; $params[]=$p['sourcef']; }
if($p['tags']){ foreach(array_filter(array_map('trim', explode(',',$p['tags']))) as $tg){ $where[]='tags LIKE ?'; $params[]='%'.$tg.'%'; } }
if($p['from']){ $where[]='date(created_at) >= date(?)'; $params[]=$p['from']; }
if($p['to']){ $where[]='date(created_at) <= date(?)'; $params[]=$p['to']; }
$cnt = (int)q($db, 'SELECT COUNT(*) FROM contacts '.(count($where)?'WHERE '.implode(' AND ',$where):''), $params)->fetchColumn();
$page=max(1,(int)($_GET['page']??1)); $per=50; list($page,$pages)=paginate($page,$per,$cnt); $off=($page-1)*$per;
$sql='SELECT * FROM contacts '.(count($where)?'WHERE '.implode(' AND ',$where):'').' ORDER BY created_at DESC LIMIT '.$per.' OFFSET '.$off; $rows=q($db,$sql,$params)->fetchAll(PDO::FETCH_ASSOC);


echo '<div class="card"><table><tr><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Công ty</th><th>Nguồn</th><th>Tags</th><th>UTM</th><th></th></tr>';
foreach($rows as $r){ $utm=trim(($r['utm_source']?'src:'.$r['utm_source'].' ':'').($r['utm_medium']?'med:'.$r['utm_medium'].' ':'').($r['utm_campaign']?'cmp:'.$r['utm_campaign']:'')); echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['email']).'</td><td>'.h($r['phone']).'</td><td>'.h($r['company']).'</td><td>'.h($r['source']).'</td><td>'.h($r['tags']).'</td><td>'.h($utm).'</td><td><a href="?action=contacts&op=edit&id='.(int)$r['id'].'">Sửa</a> · <a href="?action=contacts&op=delete&id='.(int)$r['id'].'" onclick="return confirm(\'Xoá liên hệ?\')">Xoá</a> · <a href="?action=deals&op=new&contact_id='.(int)$r['id'].'">Deal</a></td></tr>'; }
echo '</table>'; pagenav($page,$pages,['action'=>'contacts']); echo '</div>';


// Form New/Edit
if($op==='new'||$op==='edit'){ $c=['id'=>'','name'=>'','email'=>'','phone'=>'','company'=>'','source'=>'','tags'=>'','utm_source'=>'','utm_medium'=>'','utm_campaign'=>'']; if($op==='edit'){ $c=q($db,'SELECT * FROM contacts WHERE id=?',[(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC)?:$c; }
echo '<div class="card"><h3>'.($op==='new'?'Thêm liên hệ':'Sửa liên hệ').'</h3><form method="post" action="?action=contacts&op='.($op==='new'?'create':'update').'">'; csrf_field(); if($op==='edit') echo '<input type="hidden" name="id" value="'.h($c['id']).'">'; echo '<div class="grid cols-3">';
echo '<div><label>Họ tên</label><input name="name" required value="'.h($c['name']).'"></div><div><label>Email</label><input name="email" type="email" value="'.h($c['email']).'"></div><div><label>Điện thoại</label><input name="phone" value="'.h($c['phone']).'"></div><div><label>Công ty</label><input name="company" value="'.h($c['company']).'"></div><div><label>Nguồn</label><input name="source" value="'.h($c['source']).'"></div><div><label>Tags</label><input name="tags" value="'.h($c['tags']).'"></div><div><label>UTM Source</label><input name="utm_source" value="'.h($c['utm_source']).'"></div><div><label>UTM Medium</label><input name="utm_medium" value="'.h($c['utm_medium']).'"></div><div><label>UTM Campaign</label><input name="utm_campaign" value="'.h($c['utm_campaign']).'"></div>';
echo '</div><div style="margin-top:12px"><button class="btn">Lưu</button> <a class="btn secondary" href="?action=contacts">Huỷ</a></div></form></div>'; }
layout_footer();
}
function filter_params(){ return [ 'q'=>trim($_GET['q']??''), 'tags'=>trim($_GET['tags']??''), 'sourcef'=>trim($_GET['sourcef']??''), 'from'=>trim($_GET['from']??''), 'to'=>trim($_GET['to']??'') ]; }
?>
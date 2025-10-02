<?php
// ==========================
// FILE: /views/deals.php
// ==========================
function view_deals($op){
global $db; layout_header('Giao dịch');
if ($op==='create' && $_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db, "INSERT INTO deals(contact_id,title,value,currency,stage,channel,utm_source,utm_medium,utm_campaign,expected_close) VALUES(?,?,?,?,?,?,?,?,?,?)", [$_POST['contact_id']?:null,$_POST['title']??'',(float)($_POST['value']??0),$_POST['currency']??'USD',$_POST['stage']??'New',$_POST['channel']??'',$_POST['utm_source']??'',$_POST['utm_medium']??'',$_POST['utm_campaign']??'',$_POST['expected_close']??null]); header('Location: ?action=deals'); exit; }
if ($op==='update' && $_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db, "UPDATE deals SET contact_id=?,title=?,value=?,currency=?,stage=?,channel=?,utm_source=?,utm_medium=?,utm_campaign=?,expected_close=? WHERE id=?", [$_POST['contact_id']?:null,$_POST['title']??'',(float)($_POST['value']??0),$_POST['currency']??'USD',$_POST['stage']??'New',$_POST['channel']??'',$_POST['utm_source']??'',$_POST['utm_medium']??'',$_POST['utm_campaign']??'',$_POST['expected_close']??null,(int)$_POST['id']]); header('Location: ?action=deals'); exit; }
if ($op==='delete'){ q($db, "DELETE FROM deals WHERE id=?", [(int)($_GET['id']??0)]); header('Location: ?action=deals'); exit; }


if ($op==='new' || $op==='edit'){
$d=['id'=>'','contact_id'=>($_GET['contact_id']??''),'title'=>'','value'=>'','currency'=>'USD','stage'=>'New','channel'=>'','utm_source'=>'','utm_medium'=>'','utm_campaign'=>'','expected_close'=>''];
if ($op==='edit') $d = q($db, "SELECT * FROM deals WHERE id=?", [(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC) ?: $d;
$contacts = q($db, "SELECT id,name FROM contacts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
echo '<div class="card"><h3>'.($op==='new'?'Thêm giao dịch':'Sửa giao dịch').'</h3><form method="post" action="?action=deals&op='.($op==='new'?'create':'update').'">'; csrf_field(); if ($op==='edit') echo '<input type="hidden" name="id" value="'.h($d['id']).'">';
echo '<div class="grid cols-3">';
echo '<div><label>Liên hệ</label><select name="contact_id"><option value="">—</option>'; foreach($contacts as $c){ $sel=$d['contact_id']==$c['id']?'selected':''; echo '<option '.$sel.' value="'.$c['id'].'">'.h($c['name']).'</option>'; } echo '</select></div>';
echo '<div><label>Tiêu đề</label><input name="title" required value="'.h($d['title']).'"></div>';
echo '<div><label>Giá trị</label><input name="value" type="number" step="0.01" value="'.h($d['value']).'"></div>';
echo '<div><label>Tiền tệ</label><input name="currency" value="'.h($d['currency']).'"></div>';
echo '<div><label>Trạng thái</label><select name="stage">'; foreach(['New','Qualified','Proposal','Won','Lost'] as $st){ $sel=$d['stage']===$st?'selected':''; echo '<option '.$sel.'>'.$st.'</option>'; } echo '</select></div>';
echo '<div><label>Kênh</label><input name="channel" placeholder="google-2025-q4-brand" value="'.h($d['channel']).'"></div>';
echo '<div><label>UTM Source</label><input name="utm_source" value="'.h($d['utm_source']).'"></div>';
echo '<div><label>UTM Medium</label><input name="utm_medium" value="'.h($d['utm_medium']).'"></div>';
echo '<div><label>UTM Campaign</label><input name="utm_campaign" value="'.h($d['utm_campaign']).'"></div>';
echo '<div><label>Ngày kỳ vọng chốt</label><input name="expected_close" type="date" value="'.h($d['expected_close']).'"></div>';
echo '</div><div class="hint">Gợi ý: Kanban kéo-thả sẽ bổ sung sau; tạm thời chỉnh Stage tại đây.</div><div style="margin-top:12px"><button class="btn">Lưu</button> <a class="btn secondary" href="?action=deals">Huỷ</a></div></form></div>';
}


echo '<div class="grid cols-3">';
foreach(['New','Qualified','Proposal'] as $col){
$rows = q($db, "SELECT d.*, c.name as contact_name FROM deals d LEFT JOIN contacts c ON c.id=d.contact_id WHERE stage=? ORDER BY created_at DESC", [$col])->fetchAll(PDO::FETCH_ASSOC);
echo '<div class="card"><h3>'.h($col).'</h3><div class="hint">Mẹo: Sắp có kéo-thả giữa các cột</div>';
foreach($rows as $r){
$utm = trim(($r['utm_source']?'src:'.$r['utm_source'].' ':'').($r['utm_medium']?'med:'.$r['utm_medium'].' ':'').($r['utm_campaign']?'cmp:'.$r['utm_campaign']:''));
echo '<div style="padding:8px;border:1px solid var(--muted);border-radius:8px;margin-bottom:8px">';
echo '<div style="font-weight:700">'.h($r['title']).'</div>';
echo '<div>'.h($r['contact_name']).' · '.h($r['currency']).' '.number_format($r['value'],2).'</div>';
echo '<div style="font-size:12px;opacity:.8">UTM: '.h($utm).'</div>';
echo '<div style="margin-top:6px"><a class="btn secondary" href="?action=deals&op=edit&id='.(int)$r['id'].'">Sửa</a> <a class="btn secondary" href="?action=deals&op=delete&id='.(int)$r['id'].'" onclick="return confirm(\'Xoá giao dịch?\')">Xoá</a></div>';
echo '</div>';
}
echo '</div>';
}
echo '<div class="card"><h3>Đã chốt</h3><table><tr><th>Tiêu đề</th><th>Liên hệ</th><th>Giá trị</th><th>Stage</th><th>Kênh</th><th>UTM</th><th></th></tr>';
$closed = q($db, "SELECT d.*, c.name as contact_name FROM deals d LEFT JOIN contacts c ON c.id=d.contact_id WHERE stage IN ('Won','Lost') ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach($closed as $r){ $utm = trim(($r['utm_source']?'src:'.$r['utm_source'].' ':'').($r['utm_medium']?'med:'.$r['utm_medium'].' ':'').($r['utm_campaign']?'cmp:'.$r['utm_campaign']:''));
echo '<tr><td>'.h($r['title']).'</td><td>'.h($r['contact_name']).'</td><td>'.h($r['currency']).' '.number_format($r['value'],2).'</td><td>'.h($r['stage']).'</td><td>'.h($r['channel']).'</td><td>'.h($utm).'</td><td><a href="?action=deals&op=edit&id='.(int)$r['id'].'">Sửa</a></td></tr>'; }
echo '</table></div>';
echo '</div>';
layout_footer();
}
?>
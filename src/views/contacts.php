<?php
// ==========================
// FILE: /views/contacts.php
// ==========================
function view_contacts($op){
global $db;

// Xử lý các action
if ($op==='create' && $_SERVER['REQUEST_METHOD']==='POST'){ 
    require_csrf(); 
    q($db, "INSERT INTO contacts(name,email,phone,company,source,tags,utm_source,utm_medium,utm_campaign) VALUES(?,?,?,?,?,?,?,?,?)", [
        $_POST['name']??'',
        $_POST['email']??'',
        $_POST['phone']??'',
        $_POST['company']??'',
        $_POST['source']??'',
        $_POST['tags']??'',
        $_POST['utm_source']??'',
        $_POST['utm_medium']??'',
        $_POST['utm_campaign']??''
    ]); 
    header('Location: ?action=contacts'); exit; 
}

if ($op==='update' && $_SERVER['REQUEST_METHOD']==='POST'){ 
    require_csrf(); 
    q($db, "UPDATE contacts SET name=?,email=?,phone=?,company=?,source=?,tags=?,utm_source=?,utm_medium=?,utm_campaign=? WHERE id=?", [
        $_POST['name']??'',
        $_POST['email']??'',
        $_POST['phone']??'',
        $_POST['company']??'',
        $_POST['source']??'',
        $_POST['tags']??'',
        $_POST['utm_source']??'',
        $_POST['utm_medium']??'',
        $_POST['utm_campaign']??'',
        (int)$_POST['id']
    ]); 
    header('Location: ?action=contacts'); exit; 
}

if ($op==='delete'){ 
    q($db, "DELETE FROM contacts WHERE id=?", [(int)($_GET['id']??0)]); 
    header('Location: ?action=contacts'); 
    exit; 
}

if ($op==='export_csv' && $_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();
    $ids = $_POST['ids'] ?? [];
    if ($ids){
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="contacts_'.date('Y-m-d').'.csv"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo "ID,Name,Email,Phone,Company,Source,Tags,UTM Source,UTM Medium,UTM Campaign,Created\n";
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = q($db, "SELECT * FROM contacts WHERE id IN ($placeholders)", $ids)->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $r){
            echo csv_escape($r['id']).','.csv_escape($r['name']).','.csv_escape($r['email']).','.csv_escape($r['phone']).','.csv_escape($r['company']).','.csv_escape($r['source']).','.csv_escape($r['tags']).','.csv_escape($r['utm_source']).','.csv_escape($r['utm_medium']).','.csv_escape($r['utm_campaign']).','.csv_escape($r['created_at'])."\n";
        }
    }
    exit;
}

if ($op==='bulk_task' && $_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();
    $ids = $_POST['ids'] ?? [];
    $title = trim($_POST['task_title'] ?? '');
    $due = $_POST['task_due'] ?? null;
    if ($ids && $title){ 
        foreach($ids as $id){ 
            q($db, "INSERT INTO tasks(contact_id,title,due_date,status,owner) VALUES(?,?,?,?,?)", [
                $id,
                $title,
                $due,
                'Open',
                ($_SESSION['uname']??'')
            ]); 
        } 
    }
    header('Location: ?action=contacts'); 
    exit;
}

// Helper function for CSV export
function csv_escape($field) {
    return '"' . str_replace('"', '""', $field ?? '') . '"';
}

// Render
layout_header('Liên hệ');
if ($op==='new' || $op==='edit'){
    $c = ['id'=>'','name'=>'','email'=>'','phone'=>'','company'=>'','source'=>'','tags'=>'','utm_source'=>'','utm_medium'=>'','utm_campaign'=>''];
    if ($op==='edit'){ 
        $c = q($db, "SELECT * FROM contacts WHERE id=?", [(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC) ?: $c; 
    }
    echo '<div class="card"><h3>'.($op==='new'?'Thêm liên hệ':'Sửa liên hệ').'</h3><form method="post" action="?action=contacts&op='.($op==='new'?'create':'update').'">'; 
    csrf_field(); 
    if($op==='edit') echo '<input type="hidden" name="id" value="'.h($c['id']).'">';
    echo '<div class="grid cols-3">';
    echo '<div><label>Họ tên</label><input name="name" required value="'.h($c['name']).'"></div>';
    echo '<div><label>Email</label><input name="email" type="email" value="'.h($c['email']).'"></div>';
    echo '<div><label>Điện thoại</label><input name="phone" value="'.h($c['phone']).'"></div>';
    echo '<div><label>Công ty</label><input name="company" value="'.h($c['company']).'"></div>';
    echo '<div><label>Nguồn (source)</label><input name="source" placeholder="Google Ads / FB / SEO" value="'.h($c['source']).'"></div>';
    echo '<div><label>Tags</label><input name="tags" placeholder="lead,warm" value="'.h($c['tags']).'"></div>';
    echo '<div><label>UTM Source</label><input name="utm_source" value="'.h($c['utm_source']).'"></div>';
    echo '<div><label>UTM Medium</label><input name="utm_medium" value="'.h($c['utm_medium']).'"></div>';
    echo '<div><label>UTM Campaign</label><input name="utm_campaign" value="'.h($c['utm_campaign']).'"></div>';
    echo '</div><div class="hint">Gợi ý: lưu UTM để theo dõi hiệu quả nguồn lead.</div><div style="margin-top:12px"><button class="btn">Lưu</button> <a class="btn secondary" href="?action=contacts">Huỷ</a></div></form></div>';
}

echo '<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h3>Tất cả liên hệ</h3><div>';
echo '<a class="btn" href="?action=contacts&op=new">Thêm</a> ';
echo '</div></div>';

$qstr = trim($_GET['q'] ?? ''); 
$tagf = trim($_GET['tag'] ?? ''); 
$sourcef = trim($_GET['sourcef'] ?? '');
echo '<form method="get" style="margin:8px 0; display:grid; grid-template-columns: 1fr 180px 180px 120px; gap:8px">';
echo '<input type="hidden" name="action" value="contacts">';
echo '<input name="q" placeholder="Tìm tên/email/điện thoại/tags" value="'.h($qstr).'">';
echo '<input name="tag" placeholder="Lọc theo tag" value="'.h($tagf).'">';
echo '<input name="sourcef" placeholder="Lọc theo nguồn" value="'.h($sourcef).'">';
echo '<button class="btn secondary">Lọc</button>';
echo '</form>';

$where=[];
$params=[];
if ($qstr){
    $like='%'.$qstr.'%';
    $where[]='(name LIKE ? OR email LIKE ? OR phone LIKE ? OR tags LIKE ?)';
    array_push($params,$like,$like,$like,$like);
}
if ($tagf){
    $where[]='(tags LIKE ?)';
    $params[]='%'.$tagf.'%';
}
if ($sourcef){
    $where[]='(source = ?)';
    $params[]=$sourcef;
}
$sql = "SELECT * FROM contacts".(count($where)?' WHERE '.implode(' AND ',$where):'')." ORDER BY created_at DESC LIMIT 300";
$rows = q($db, $sql, $params)->fetchAll(PDO::FETCH_ASSOC);

echo '<form method="post" action="?action=contacts&op=bulk_task" style="margin-bottom:8px">'; 
csrf_field();
echo '<div class="grid" style="grid-template-columns: 1fr 1fr; align-items:end">';
echo '<div><label>Tiêu đề Task</label><input name="task_title" placeholder="Gọi lại / Gửi báo giá"></div>';
echo '<div><label>Hạn</label><input name="task_due" type="date"></div>';
echo '</div><div style="margin-top:8px">';
echo '<button class="btn">Tạo Task cho mục đã chọn</button> ';
echo '<button class="btn secondary" formaction="?action=contacts&op=export_csv" formmethod="post">Export CSV (đã chọn)</button>';
echo '</div>';
echo '<table><tr><th><input type="checkbox" onclick="document.querySelectorAll(\'input[name=ids[]]\').forEach(e=>e.checked=this.checked)"></th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Công ty</th><th>Nguồn</th><th>Tags</th><th>UTM</th><th></th></tr>';
foreach($rows as $r){
    $utm = trim(($r['utm_source']?'src:'.$r['utm_source'].' ':'').($r['utm_medium']?'med:'.$r['utm_medium'].' ':'').($r['utm_campaign']?'cmp:'.$r['utm_campaign']:''));
    echo '<tr>';
    echo '<td><input type="checkbox" name="ids[]" value="'.(int)$r['id'].'"></td>';
    echo '<td>'.h($r['name']).'</td><td>'.h($r['email']).'</td><td>'.h($r['phone']).'</td><td>'.h($r['company']).'</td><td>'.h($r['source']).'</td><td>'.h($r['tags']).'</td><td>'.h($utm).'</td><td>';
    echo '<a href="?action=contacts&op=edit&id='.(int)$r['id'].'">Sửa</a> · ';
    echo '<a href="?action=contacts&op=delete&id='.(int)$r['id'].'" onclick="return confirm(\'Xoá liên hệ?\')">Xoá</a> · ';
    echo '<a href="?action=deals&op=new&contact_id='.(int)$r['id'].'" title="Tạo giao dịch nhanh">Deal</a> · ';
    echo '<a href="?action=activities&op=new&contact_id='.(int)$r['id'].'">Ghi hoạt động</a>';
    echo '</td></tr>';
}
echo '</table></form>';
echo '<div class="hint">Gợi ý: Chọn nhiều liên hệ để tạo Task hàng loạt hoặc Export CSV nhanh.</div>';
echo '</div>';
layout_footer();
}
?>
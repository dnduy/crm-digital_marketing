<?php
function view_content_scheduler($op){ global $db; layout_header('Lịch nội dung');
  if($op==='create'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"INSERT INTO content_posts(title,slug,content,seo_keywords,scheduled_at,status,owner) VALUES(?,?,?,?,?,?,?)",[$_POST['title']??'',$_POST['slug']??'',$_POST['content']??'',$_POST['seo_keywords']??'',$_POST['scheduled_at']??null,$_POST['status']??'Draft',$_SESSION['uname']??'']); header('Location: ?action=content_scheduler'); exit; }
  if($op==='update'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"UPDATE content_posts SET title=?,slug=?,content=?,seo_keywords=?,scheduled_at=?,status=? WHERE id=?",[$_POST['title']??'',$_POST['slug']??'',$_POST['content']??'',$_POST['seo_keywords']??'',$_POST['scheduled_at']??null,$_POST['status']??'Draft',(int)$_POST['id']]); header('Location: ?action=content_scheduler'); exit; }
  if($op==='publish'){ $id=(int)($_GET['id']??0); q($db,"UPDATE content_posts SET status='Published', published_at=CURRENT_TIMESTAMP WHERE id=?",[$id]); header('Location: ?action=content_scheduler'); exit; }
  if($op==='delete'){ $id=(int)($_GET['id']??0); q($db,"DELETE FROM content_posts WHERE id=?",[$id]); header('Location: ?action=content_scheduler'); exit; }
  if($op==='new'||$op==='edit'){ $c=['id'=>'','title'=>'','slug'=>'','content'=>'','seo_keywords'=>'','scheduled_at'=>'','status'=>'Draft']; if($op==='edit'){ $c=q($db,'SELECT * FROM content_posts WHERE id=?',[(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC)?:$c; }
    echo '<div class="card"><h3>'.($op==='new'?'Thêm bài viết':'Sửa bài viết').'</h3><form method="post" action="?action=content_scheduler&op='.($op==='new'?'create':'update').'">'; csrf_field(); if($op==='edit') echo '<input type="hidden" name="id" value="'.h($c['id']).'">'; echo '<div class="grid cols-3">';
    echo '<div style="grid-column:1/3"><label>Tiêu đề</label><input name="title" required value="'.h($c['title']).'"></div>';
    echo '<div><label>Slug</label><input name="slug" value="'.h($c['slug']).'"></div>';
    echo '<div style="grid-column:1/4"><label>Nội dung</label><textarea name="content" rows="6">'.h($c['content']).'</textarea></div>';
    echo '<div style="grid-column:1/2"><label>SEO Keywords</label><input name="seo_keywords" value="'.h($c['seo_keywords']).'"></div>';
    echo '<div><label>Lịch đăng</label><input type="datetime-local" name="scheduled_at" value="'.h($c['scheduled_at']).'"></div>';
    echo '<div><label>Trạng thái</label><select name="status">'; foreach(['Draft','Scheduled','Published','Failed'] as $st){ $sel=$c['status']===$st?'selected':''; echo '<option '.$sel.'>'.$st.'</option>'; } echo '</select></div>';
    echo '</div><div style="margin-top:12px"><button class="btn">Lưu</button> <a class="btn secondary" href="?action=content_scheduler">Huỷ</a></div></form></div>'; }
  echo '<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h3>Danh sách bài viết</h3><a class="btn" href="?action=content_scheduler&op=new">Thêm</a></div>';
  $rows=q($db,'SELECT * FROM content_posts ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
  echo '<table><tr><th>Tiêu đề</th><th>Slug</th><th>Trạng thái</th><th>Lịch đăng</th><th>Đã đăng</th><th>Owner</th><th></th></tr>';
  foreach($rows as $r){ echo '<tr><td>'.h($r['title']).'</td><td>'.h($r['slug']).'</td><td>'.h($r['status']).'</td><td>'.h($r['scheduled_at']).'</td><td>'.h($r['published_at']).'</td><td>'.h($r['owner']).'</td><td><a href="?action=content_scheduler&op=edit&id='.(int)$r['id'].'">Sửa</a> · <a href="?action=content_scheduler&op=publish&id='.(int)$r['id'].'">Đăng</a> · <a href="?action=content_scheduler&op=delete&id='.(int)$r['id'].'" onclick="return confirm('Xoá?')">Xoá</a></td></tr>'; }
  echo '</table></div>';
  layout_footer();
}

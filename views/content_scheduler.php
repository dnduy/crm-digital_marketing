<?php
function view_content_scheduler($op){ global $db; layout_header('Lịch nội dung (Tháng/Tuần)');

  // CRUD
  if($op==='create'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"INSERT INTO content_posts(title,slug,content,seo_keywords,scheduled_at,status,owner) VALUES(?,?,?,?,?,?,?)",[$_POST['title']??'',$_POST['slug']??'',$_POST['content']??'',$_POST['seo_keywords']??'',$_POST['scheduled_at']??null,$_POST['status']??'Draft',$_SESSION['uname']??'']); header('Location: ?action=content_scheduler'); exit; }
  if($op==='update'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); q($db,"UPDATE content_posts SET title=?,slug=?,content=?,seo_keywords=?,scheduled_at=?,status=? WHERE id=?",[$_POST['title']??'',$_POST['slug']??'',$_POST['content']??'',$_POST['seo_keywords']??'',$_POST['scheduled_at']??null,$_POST['status']??'Draft',(int)$_POST['id']]); header('Location: ?action=content_scheduler'); exit; }
  if($op==='publish'){ $id=(int)($_GET['id']??0); q($db,"UPDATE content_posts SET status='Published', published_at=CURRENT_TIMESTAMP WHERE id=?",[$id]); header('Location: ?action=content_scheduler'); exit; }
  if($op==='delete'&&$_SERVER['REQUEST_METHOD']==='POST'){ require_csrf(); $id=(int)($_POST['id']??0); q($db,"DELETE FROM content_posts WHERE id=?",[$id]); header('Location: ?action=content_scheduler'); exit; }

  $view = $_GET['view'] ?? 'month'; // month|week
  echo '<div class="card" style="display:flex;justify-content:space-between;align-items:center"><div><strong>Chế độ:</strong> ';
  echo '<a class="btn secondary" href="?action=content_scheduler&view=month">Tháng</a> ';
  echo '<a class="btn secondary" href="?action=content_scheduler&view=week">Tuần</a>';
  echo '</div><div><a class="btn" href="?action=content_scheduler&op=new&view='.htmlspecialchars($view,ENT_QUOTES,'UTF-8').'">Thêm bài</a></div></div>';

  // Form new/edit
  if($op==='new'||$op==='edit'){ $c=['id'=>'','title'=>'','slug'=>'','content'=>'','seo_keywords'=>'','scheduled_at'=>'','status'=>'Draft']; if($op==='edit'){ $c=q($db,'SELECT * FROM content_posts WHERE id=?',[(int)$_GET['id']])->fetch(PDO::FETCH_ASSOC)?:$c; }
    echo '<div class="card"><h3>'.($op==='new'?'Thêm bài viết':'Sửa bài viết').'</h3><form method="post" action="?action=content_scheduler&op='.($op==='new'?'create':'update').'&view='.$view.'">'; csrf_field(); if($op==='edit') echo '<input type="hidden" name="id" value="'.htmlspecialchars($c['id'],ENT_QUOTES,'UTF-8').'">'; echo '<div class="grid cols-3">';
    echo '<div style="grid-column:1/3"><label>Tiêu đề</label><input name="title" required value="'.htmlspecialchars($c['title'],ENT_QUOTES,'UTF-8').'"></div>';
    echo '<div><label>Slug</label><input name="slug" value="'.htmlspecialchars($c['slug'],ENT_QUOTES,'UTF-8').'"></div>';
    echo '<div style="grid-column:1/4"><label>Nội dung</label><textarea name="content" rows="6">'.htmlspecialchars($c['content'],ENT_QUOTES,'UTF-8').'</textarea></div>';
    echo '<div><label>SEO Keywords</label><input name="seo_keywords" value="'.htmlspecialchars($c['seo_keywords'],ENT_QUOTES,'UTF-8').'"></div>';
    echo '<div><label>Lịch đăng</label><input type="datetime-local" name="scheduled_at" value="'.htmlspecialchars($c['scheduled_at'],ENT_QUOTES,'UTF-8').'"></div>';
    echo '<div><label>Trạng thái</label><select name="status">'; foreach(['Draft','In Review','Approved','Scheduled','Published','Failed'] as $st){ $sel=$c['status']===$st?'selected':''; echo '<option '.$sel.'>'.$st.'</option>'; } echo '</select></div>';
    echo '</div><div style="margin-top:12px"><button class="btn">Lưu</button> <a class="btn secondary" href="?action=content_scheduler&view='.$view.'">Huỷ</a></div></form></div>'; 
  }

  if ($view === 'month') {
    $month = $_GET['month'] ?? date('Y-m');
    $firstDay = $month.'-01';
    $startTs = strtotime($firstDay);
    $days = (int)date('t', $startTs);
    $wday = (int)date('N', $startTs); // 1-7 (Mon-Sun)
    $posts = q($db,"SELECT * FROM content_posts WHERE scheduled_at IS NOT NULL AND strftime('%Y-%m', scheduled_at)=? ORDER BY scheduled_at",[$month])->fetchAll(PDO::FETCH_ASSOC);
    $byDay = [];
    foreach($posts as $p){ $d = (int)date('j', strtotime($p['scheduled_at'])); $byDay[$d] = $byDay[$d] ?? []; $byDay[$d][] = $p; }

    echo '<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h3>Lịch tháng '.htmlspecialchars($month,ENT_QUOTES,'UTF-8').'</h3><div><a class="btn secondary" href="?action=content_scheduler&view=month&month='.date('Y-m', strtotime($firstDay.' -1 month')).'">◀</a> <a class="btn secondary" href="?action=content_scheduler&view=month&month='.date('Y-m', strtotime($firstDay.' +1 month')).'">▶</a></div></div>';
    echo '<div id="cal" data-csrf="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8').'" style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px">';
    $labels=['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; foreach($labels as $lb){ echo '<div class="slot-head">'.$lb.'</div>'; }
    for($i=1;$i<$wday;$i++){ echo '<div></div>'; }
    for($d=1;$d<=$days;$d++){
      echo '<div class="day" data-date="'.htmlspecialchars(sprintf('%s-%02d',$month,$d),ENT_QUOTES,'UTF-8').'" ondragover="event.preventDefault()" ondrop="dropPostDay(event,this)"><div class="day-num">'.(int)$d.'</div>';
      if(!empty($byDay[$d])){ foreach($byDay[$d] as $p){
        echo '<div class="card slot-card" draggable="true" ondragstart="dragPost(event)" data-id="'.(int)$p['id'].'"><div class="slot-title">'.htmlspecialchars($p['title'],ENT_QUOTES,'UTF-8').'</div><div class="slot-meta">'.htmlspecialchars(date('H:i', strtotime($p['scheduled_at'])),ENT_QUOTES,'UTF-8').' • '.htmlspecialchars($p['status'],ENT_QUOTES,'UTF-8').'</div></div>';
      }}
      echo '</div>';
    }
    echo '</div></div>';
  } else {
    // WEEK VIEW
    $start = $_GET['start'] ?? date('Y-m-d', strtotime('monday this week'));
    $startTs = strtotime($start);
    $days = [];
    for($i=0;$i<7;$i++){ $days[] = date('Y-m-d', strtotime("+$i day", $startTs)); }
    $labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

    $rows = q($db,"SELECT * FROM content_posts WHERE scheduled_at IS NOT NULL AND date(scheduled_at) BETWEEN date(?) AND date(?) ORDER BY scheduled_at",[$days[0],$days[6]])->fetchAll(PDO::FETCH_ASSOC);
    $by = [];
    foreach($rows as $p){
      $d = date('Y-m-d', strtotime($p['scheduled_at']));
      $h = (int)date('G', strtotime($p['scheduled_at']));
      if (!isset($by[$d])) $by[$d] = [];
      if (!isset($by[$d][$h])) $by[$d][$h] = [];
      $by[$d][$h][] = $p;
    }

    echo '<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h3>Tuần bắt đầu '.htmlspecialchars($days[0],ENT_QUOTES,'UTF-8').'</h3><div>';
    echo '<a class="btn secondary" href="?action=content_scheduler&view=week&start='.date('Y-m-d', strtotime($days[0].' -7 days')).'">◀</a> ';
    echo '<a class="btn secondary" href="?action=content_scheduler&view=week&start='.date('Y-m-d', strtotime($days[0].' +7 days')).'">▶</a>';
    echo '</div></div>';

    echo '<div id="calw" data-csrf="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8').'" class="week-grid">';
    // Header row
    echo '<div></div>'; // empty corner
    foreach($labels as $i=>$lb){ echo '<div class="slot-head">'.$lb.'<br><span class="hint">'.htmlspecialchars($days[$i],ENT_QUOTES,'UTF-8').'</span></div>'; }

    // Hours 08-20
    for($hour=8; $hour<=20; $hour++){
      echo '<div class="hour-col">'.sprintf('%02d:00',$hour).'</div>';
      foreach($days as $d){
        $cellId = $d.' '.sprintf('%02d:00:00',$hour);
        echo '<div class="slot-cell" data-dt="'.htmlspecialchars($cellId,ENT_QUOTES,'UTF-8').'" ondragover="event.preventDefault()" ondrop="dropPostHour(event,this)">';
        if (!empty($by[$d][$hour])) {
          foreach($by[$d][$hour] as $p){
            echo '<div class="card slot-card" draggable="true" ondragstart="dragPost(event)" data-id="'.(int)$p['id'].'"><div class="slot-title">'.htmlspecialchars($p['title'],ENT_QUOTES,'UTF-8').'</div><div class="slot-meta">'.htmlspecialchars(date('H:i', strtotime($p['scheduled_at'])),ENT_QUOTES,'UTF-8').' • '.htmlspecialchars($p['status'],ENT_QUOTES,'UTF-8').'</div></div>';
          }
        }
        echo '</div>';
      }
    }
    echo '</div></div>';
  }

  // List view under calendar
  $rows=q($db,'SELECT * FROM content_posts ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
  echo '<div class="card"><div style="display:flex;justify-content:space-between;align-items:center"><h3>Danh sách bài viết</h3></div>';
  echo '<table><tr><th>Tiêu đề</th><th>Slug</th><th>Trạng thái</th><th>Lịch đăng</th><th>Đã đăng</th><th>Owner</th><th></th></tr>';
  foreach($rows as $r){ echo '<tr><td>'.htmlspecialchars($r['title'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($r['slug'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($r['status'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($r['scheduled_at'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($r['published_at'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($r['owner'],ENT_QUOTES,'UTF-8').'</td><td><a class="btn secondary" href="?action=content_scheduler&op=edit&id='.(int)$r['id'].'&view='.$view.'">Sửa</a> <form method="post" action="?action=content_scheduler&op=delete&view='.$view.'" style="display:inline">'.csrf_field().'<input type="hidden" name="id" value="'.(int)$r['id'].'"><button class="btn secondary" onclick="return confirm(\'Xoá?\')">Xoá</button></form> <a class="btn" href="?action=content_scheduler&op=publish&id='.(int)$r['id'].'&view='.$view.'">Đăng</a></td></tr>'; }
  echo '</table></div>';

  echo '<script>
  function dragPost(e){ e.dataTransfer.setData("id", e.target.closest(".slot-card").dataset.id); }
  async function postReschedule(id, dt){
    const csrf = (document.getElementById("cal")||document.getElementById("calw")).dataset.csrf;
    const fd = new FormData(); fd.append("csrf", csrf); fd.append("id", id); fd.append("scheduled_at", dt);
    const res = await fetch("?action=reschedule", {method:"POST", body: fd});
    const j = await res.json().catch(()=>({ok:false}));
    if(j.ok) location.reload(); else alert("Lỗi reschedule");
  }
  function dropPostDay(e, el){
    const id = e.dataTransfer.getData("id");
    const date = el.dataset.date + " 09:00:00";
    postReschedule(id, date);
  }
  function dropPostHour(e, el){
    const id = e.dataTransfer.getData("id");
    const dt = el.dataset.dt;
    postReschedule(id, dt);
  }
  </script>';

  layout_footer();
}

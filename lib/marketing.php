<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/util.php';

function update_lead_score($db, $contact_id, $event_type, $content_detail=''){
  $ruleScore = q($db, "SELECT score FROM lead_score_rules WHERE event_type=?", [$event_type])->fetchColumn();
  if ($ruleScore !== false) {
    $score_change = (int)$ruleScore;
    q($db, "UPDATE contacts SET lead_score = COALESCE(lead_score,0) + ? WHERE id=?", [$score_change, $contact_id]);
    $content = "Event: $event_type. Score: $score_change. Detail: $content_detail";
    q($db, "INSERT INTO activities(contact_id,type,content) VALUES(?,?,?)", [$contact_id, 'score_event', $content]);
    trigger_automation_engine($db, $contact_id, 'lead_score_changed');
  }
}

function send_email_via_api($recipient_email, $subject, $body_html){
  if(!$recipient_email) return false;
  audit('email_sent', ['to'=>$recipient_email, 'subject'=>$subject, 'provider'=>'Mocked_API']);
  return true;
}

function trigger_automation_engine($db, $contact_id, $trigger_type){
  $contact = q($db, "SELECT * FROM contacts WHERE id=?", [$contact_id])->fetch(PDO::FETCH_ASSOC);
  if (!$contact) return;
  $current_score = (int)($contact['lead_score'] ?? 0);
  $flows = q($db, "SELECT * FROM automation_workflows WHERE status='Active'")->fetchAll(PDO::FETCH_ASSOC);
  foreach($flows as $flow){
    $config = json_decode($flow['config_json'] ?? '{}', true) ?: [];
    if ($flow['trigger_type'] === 'score_achieved' && $current_score >= (int)($config['min_score'] ?? 100)){
      if (($config['action']??'') === 'send_email'){
        send_email_via_api(
          $contact['email'] ?? '',
          $config['email_subject'] ?? 'Chúc mừng! Bạn đạt ngưỡng điểm',
          $config['email_body'] ?? '<p>Xin chúc mừng!</p>'
        );
        audit('automation_executed', ['flow_id'=>$flow['id'], 'action'=>'email_sent', 'cid'=>$contact_id]);
      }
    }
    if ($flow['trigger_type'] === 'contact_created' && $trigger_type === 'contact_created'){
      if (($config['action']??'') === 'send_email'){
        send_email_via_api(
          $contact['email'] ?? '',
          $config['email_subject'] ?? 'Chào mừng!',
          $config['email_body'] ?? '<p>Cám ơn bạn đã quan tâm.</p>'
        );
        audit('automation_executed', ['flow_id'=>$flow['id'], 'action'=>'welcome_email', 'cid'=>$contact_id]);
      }
    }
  }
}

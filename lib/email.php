<?php
require_once __DIR__.'/db.php';

function send_email($to, $subject, $html){
  $provider = getenv('SMTP_PROVIDER') ?: 'phpmail';
  $from = getenv('SMTP_FROM') ?: 'CRM <noreply@localhost>';
  audit('email_attempt', ['to'=>$to,'subject'=>$subject,'provider'=>$provider]);

  if ($provider === 'phpmail') {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from}\r\n";
    $ok = @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, $headers);
    audit($ok?'email_sent':'email_failed', ['to'=>$to,'subject'=>$subject,'provider'=>$provider]);
    return $ok;
  }

  if ($provider === 'smtp') {
    $host = getenv('SMTP_HOST') ?: 'localhost';
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $user = getenv('SMTP_USER') ?: '';
    $pass = getenv('SMTP_PASS') ?: '';
    $secure = strtolower(getenv('SMTP_SECURE') ?: 'tls'); // tls|ssl|none

    try {
      $transport = ($secure==='ssl') ? 'ssl://'.$host : $host;
      $fp = @stream_socket_client($transport.':'.$port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
      if (!$fp) throw new Exception("Connect fail: $errstr ($errno)");
      stream_set_timeout($fp, 10);

      $read = function() use ($fp){ $resp=''; while(!feof($fp)){ $line=fgets($fp, 515); if($line===false) break; $resp.=$line; if(strlen($line)>=4 && $line[3] != '-') break; } return $resp; };
      $write = function($cmd) use ($fp){ fwrite($fp, $cmd."\r\n"); };

      $greet = $read();
      if (strpos($greet, '220') !== 0) throw new Exception('No 220 greeting: '.$greet);

      $write('EHLO localhost'); $ehlo = $read();
      if ($secure==='tls' && stripos($ehlo, 'STARTTLS') !== false) {
        $write('STARTTLS'); $tls=$read();
        if (strpos($tls, '220') !== 0) throw new Exception('STARTTLS failed: '.$tls);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
          throw new Exception('TLS crypto failed');
        }
        $write('EHLO localhost'); $ehlo = $read();
      }

      if ($user && $pass) {
        $write('AUTH LOGIN'); $auth=$read();
        if (strpos($auth,'334') !== 0) throw new Exception('AUTH not accepted: '.$auth);
        $write(base64_encode($user)); $u=$read();
        $write(base64_encode($pass)); $p=$read();
        if (strpos($p,'235') !== 0) throw new Exception('AUTH failed: '.$p);
      }

      // From
      if (preg_match('/<(.*?)>/', $from, $m)) { $fromEmail=$m[1]; } else { $fromEmail=trim($from); }
      $write('MAIL FROM:<'.$fromEmail.'>'); $mfrom=$read(); if (strpos($mfrom,'250') !== 0) throw new Exception('MAIL FROM failed: '.$mfrom);
      $write('RCPT TO:<'.$to.'>'); $rcpt=$read(); if (strpos($rcpt,'250') !== 0 && strpos($rcpt,'251') !== 0) throw new Exception('RCPT TO failed: '.$rcpt);
      $write('DATA'); $dataresp=$read(); if (strpos($dataresp,'354') !== 0) throw new Exception('DATA not accepted: '.$dataresp);

      $boundary = 'bnd_'.bin2hex(random_bytes(8));
      $headers = [];
      $headers[] = 'From: '.$from;
      $headers[] = 'To: <'.$to.'>';
      $headers[] = 'Subject: =?UTF-8?B?'.base64_encode($subject).'?=';
      $headers[] = 'MIME-Version: 1.0';
      $headers[] = 'Content-Type: multipart/alternative; boundary="'.$boundary.'"';
      $body  = implode("\r\n", $headers)."\r\n\r\n";
      $body .= '--'.$boundary."\r\n";
      $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
      $body .= strip_tags($html)."\r\n";
      $body .= '--'.$boundary."\r\n";
      $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
      $body .= $html."\r\n";
      $body .= '--'.$boundary.'--'."\r\n";
      $body .= ".\r\n";
      fwrite($fp, $body);
      $end = $read(); if (strpos($end,'250') !== 0) throw new Exception('Message not accepted: '.$end);
      $write('QUIT'); $read();
      fclose($fp);
      audit('email_sent', ['to'=>$to,'subject'=>$subject,'provider'=>$provider,'host'=>$host,'port'=>$port,'secure'=>$secure]);
      return true;
    } catch (Exception $e) {
      audit('email_failed', ['to'=>$to,'subject'=>$subject,'provider'=>$provider,'error'=>$e->getMessage()]);
      return false;
    }
  }

  audit('email_failed', ['to'=>$to,'subject'=>$subject,'provider'=>$provider,'error'=>'Unknown provider']);
  return false;
}

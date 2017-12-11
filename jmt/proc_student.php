<?php

$msgs = array();
$exts = array('.xls','.txt');

switch($pv['action']){
 case 'upload':
   $pv['site'] = 'upload';
   $pv['which'] = $pv['job'];

   $job = $db->read_row("SELECT * FROM jobs WHERE id=$pv[job] AND (status=2 or status=5)");
   if(is_null($job)) {$msgs[] = 'Error: invalid job id'; break;}

   if(!preg_match('/^ *[0-9]+ *$/',$pv['time']))  {$msgs[] = 'Error: please edit time too'; break;}

   if(empty($_FILES['upfile'])) {$msgs[] = 'upload failed'; break;}
   $upl = $_FILES['upfile'];
   if($upl['error']!=0) {$msgs[] = 'Error: upload failed'; break;}
   if($upl['size']==0) {$msgs[] = 'Error: empty file'; break;}
   $ext = strtolower(substr($upl['name'],-4));
   if(!in_array($ext,$exts)){
     $msgs[] = 'Error: Unallowed file type. Allowed are: ' . implode(', ',$exts);
     break;
   }
   if(!is_uploaded_file($upl['tmp_name'])) {$msgs[] = 'Error: upload failed'; break;}


   $mod = $job['status']==2?'dig':'chk';
   $fn = preg_replace('/[^a-zA-Z]/','',$stations[$job['station']])
     . "_$job[year]_" . sprintf("%02d",$job['month']) . "_$mod$ext";
   if(FALSE===move_uploaded_file($upl['tmp_name'],$uploaddir . $fn)) {$msgs[] = 'Error: upload failed'; break;}
   $sta = $job['status']+1;
   $dat = date('Ymd');
   $sql = "UPDATE jobs"
     . " SET status=$sta, {$mod}_file='$fn', {$mod}_datein='$dat', {$mod}_time=$pv[time]"
     . " WHERE id=$pv[job]";
   /* disabled since to many mails were generated per day 
   $mailtext = "File upload ($fn) by $users[$legi]";
   $reply = "From: noreply@iac.ethz.ch\r\nReply-To: noreply@iac.ethz.ch\r\n";
   mail($mail_to,"DigiHom: $mailtext","DigiHom: $mailtext\n\nDO NOT REPLY TO THIS MAIL!!",$reply);
   */
   $db->sql_execute($sql);
   $msgs[] = 'ok: thanks a lot';
   $pv['site'] = 'myjobs';
   break;



 case 'yrupload':
   $pv['site'] = 'yrupload';
   $pv['which'] = $pv['job'];
   $id = explode('-',$pv['job']);
   $sql = "SELECT * FROM jobs WHERE station=$id[0] and year=$id[1] AND (status=2 or status=5)";
   $jobs = $db->read_array($sql,'month');
   if(is_null($jobs)) {$msgs[] = 'Error: invalid job'; break;}

   // Test
   for($cm=1;$cm<=12;$cm++){
     if(!isset($jobs[$cm]) 
	or !preg_match('/^ *[0-9]+ *$/',$pv['time' . $cm])
	or $_FILES['upfile' . $cm]['error']!=0
	or $_FILES['upfile' . $cm]['size']==0
	or !in_array(strtolower(substr($_FILES['upfile' . $cm]['name'],-4)),$exts)
	){
       unset($_FILES['upfile' . $cm]);
       unset($pv['time' . $cm]);
     }
   }

   foreach($_FILES as $key=>$upl){
     $mon = (int)preg_replace('|\D|','',$key);
     $job = $jobs[$mon];
     $tim = $pv['time' . $mon];

     $mod = $job['status']==2?'dig':'chk';
     $fn = preg_replace('/[^a-zA-Z]/','',$stations[$job['station']])
       . "_$job[year]_" . sprintf("%02d",$job['month']) . "_$mod" . strtolower(substr($upl['name'],-4));
     if(FALSE===move_uploaded_file($upl['tmp_name'],$uploaddir . $fn)) {$msgs[] = 'Error: upload failed'; break;}
     $sta = $job['status']+1;
     $dat = date('Ymd');
     $sql = "UPDATE jobs"
       . " SET status=$sta, {$mod}_file='$fn', {$mod}_datein='$dat', {$mod}_time=$tim"
       . " WHERE id=$job[id] AND {$mod}_user='$legi'";
     $db->sql_execute($sql);
     $msgs[] = "ok: $job[year]-$job[month] uploaded";
   }
   break;

 default:
   $msgs[] = "Error: unknown job $pv[action]";

 }

?>
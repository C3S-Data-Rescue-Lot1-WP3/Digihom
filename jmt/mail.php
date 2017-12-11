<?php

  /* Crontab entry necessary for mail notification
# check for new files and inform per mail, n defines the range in hours
0 12,15,18 * * 1,2,3,4,5 wget -O- "http://WO AUCH IMMER/index.php?site=mail&n=3" > /dev/null
0 9 * * 2,3,4,5 wget -O- "http://WO AUCH IMMER/index.php?site=mail&n=15" > /dev/null
0 9 * * 1 wget -O- "http://WO AUCH IMMER/index.php?site=mail&n=63" > /dev/null
  */

$dth = (float)$pv['n'];  
if($dth==0) $dth = 3;
$dh = dir($dir_upload);
$orp = array();
while (false !== ($entry = $dh->read())) {
  if(!in_array(strtolower(substr($entry,-4)),array('.xls','.txt'))) continue;
  if((time()-filemtime($dir_upload . $entry))/3600<=$dth)
    $orp[$entry] = $entry . ' - ' . date('d.m.y H:i:s',filemtime($dir_upload . $entry));
 }
$dh->close();
asort($orp);

$reply = "From: noreply@iac.ethz.ch\r\nReply-To: noreply@iac.ethz.ch\r\n";
$txt = "New digihom files in the last $dth hours\n\n" . implode("\n",$orp) .  "\n\nDO NOT REPLY TO THIS MAIL!!";
mail($mail_to,'DigiHom - New Files: ' . count($orp),$txt,$reply);

?>
<?php
switch($pv['direct']){
 case 'nextstep':
   $sql = "UPDATE jobs SET status=status+1"
     . " WHERE station=$pv[station] AND year=$pv[year] AND month=$pv[month]";
   $db->sql_execute($sql);
   $msgs[] = 'new status applied';
   $pv['site'] = 'jobs';
   break;

 case 'removeuser':
   if(!preg_match('/^[0-9]+-[0-9]+-[0-9]+$/',$pv['legi'])) break;
   $res = $db->remove('users',array('legi'=>$pv['legi']));
   $msgs[] = $res==1?'ok: user removed':'Error: not possible to remove user';
   break;


 case 'removestation':
   if(!preg_match('/^[0-9]+$/',$pv['id'])) break;
   $res = $db->remove('stations',array('id'=>$pv['id']));
   $msgs[] = $res==1?'ok: station removed':'Error: not possible to remove station';
   break;

 case 'rmfile':
   if(file_exists($uploaddir . $pv['file']))
     unlink($uploaddir . $pv['file']);
   else
     $msgs[] = 'unknown file';
   $pv['site'] = 'files';
   break;

 default:
   vd($pv);
 }
?>
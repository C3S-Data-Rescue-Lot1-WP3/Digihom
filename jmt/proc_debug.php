<?php
if(!is_object($this->cuser)) return;
if(!$this->is_admin) return $this->main->div($ip->txp->t('err-access'),'err');
// reactivate if testing at oeschger is over if($this->tool->mode=='operational') return ;

$task = def($this->pv,'task','-');
switch($task){
 case 'new_import':
   $this->prop_set('site','admin',TRUE,'pgs');
   for($i=0;$i<4;$i++){
     $dir = chr($i+65) . chr(rand(1,26)+65) . date('YmdHis');
     if(is_dir($this->dir_imp . $dir)) continue;
     if(mkdir($this->dir_imp . $dir,0777)===FALSE) continue;
     $n = rand(3,6);
     for($j=0;$j<$n;$j++){
       $file = chr($j+65) . chr(rand(1,26)+65) . '.txt';
       file_put_contents($this->dir_imp . $dir . '/' . $file,'Created for debug testing ' . date('Ymd His'));
     }
   }
   break;

 case 'del_jobs': case 'del_files': case 'del_keys': case 'del_users':
   $this->prop_set('site','admin',TRUE,'pgs');

   $this->db->sql_execute('TRUNCATE files');

   if(in_array($task,array('del_jobs','del_keys','del_users'))){
     $dir = (array)$this->db->read_column('SELECT dir FROM jobs');
     foreach($dir as $cd) @unlink($this->dir_jobs . $dir);
     $this->db->sql_execute('TRUNCATE jobs CASCADE');
   }

   if(in_array($task,array('del_keys','del_users')))
     $this->db->sql_execute('TRUNCATE key1, key2, key3 CASCADE');

   if(in_array($task,array('del_users')))
     foreach(array_diff(array_keys($this->users),$this->admins) as $vdn)
       $this->um->user_remove($vdn);
   break;
 }

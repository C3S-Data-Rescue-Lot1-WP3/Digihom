<?php
if(!is_object($this->cuser)) return;

switch(def($this->pv,'task','-')){
 case 'unlink':
   $fn = def($this->pv,'file','-');
   $dfn = $this->dir_upload . $fn;
   $typ = substr($fn,0,3);
   if($typ!='dig' and $typ!='chk')
     return $this->main->div($this->txp->t('err-unknownrequest'),'err');
   if(file_Exists($dfn) and !is_writeable($dfn))
     return $this->main->div($this->txp->t('err-ro'),'err');
   $sql = 'SELECT * FROM jobs WHERE ' . $typ . '_file=\'' . pg_escape_String($fn) . '\'';
   $job = $this->db->read_row($sql);

   if(empty($job))
     return $this->main->div($this->txp->t('err-nojob'),'err');
   if($job[$typ . '_user']!=$this->cuser->vdn and !$this->is_admin)
     return $this->main->div($this->txp->t('err-unlink_na_acc'),'err');
   if($typ=='dig' and !is_null($job['chk_dateout']))
     return $this->main->div($this->txp->t('err-unlink_na_chk'),'err');
   $sql = 'UPDATE jobs SET %_file=NULL, %_datein=NULL' . ' WHERE id=' . $job['id'];
   $this->db->sql_execute(str_replace('%',$typ,$sql));
   if(file_exists($dfn)) unlink($dfn);
   return $this->main->div($this->txp->t('hint-done'),'succ');



 case 'zip':
   $users = $this->um->users_list();
   $usr = def($this->pv,'user');
   if(!isset($users[$usr])) return $this->main->div($this->txp->t('hint-unknownuser'),'hint');
   $dir = def($this->pv,'dir','x');
   if(!is_dir($this->dir_jobs . $dir)) 
     return $this->main->div($this->txp->t('hint-unknownjob') . ': ' . $dir,'hint');
   $files = preg_grep('/^[^.]/',scandir($this->dir_jobs . $dir));
   list($dummy,$uname) = $this->um->vdn_split($usr);
   $zfile = $this->dir_jobs . $uname . '_' . $dir . '.zip';
   if(file_exists($zfile)) unlink($zfile);
   $zip = new ZipArchive();
   if ($zip->open($zfile, ZIPARCHIVE::CREATE)!==TRUE) return $this->main->div($this->txp->t('err-zip'),'err');
   foreach($files as $file) $zip->addFile($this->dir_jobs . $dir . '/' . $file,$file);
   $zip->close();
   return $this->main->div($this->txp->t('hint-saved'),'ok');
 }


?>
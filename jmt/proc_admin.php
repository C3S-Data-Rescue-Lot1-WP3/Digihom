<?php
if(!is_object($this->cuser)) return;
if(!$this->is_admin) return $this->main->div($this->txp->t('err-access'),'err');
$this->prop_set('site','admin',TRUE,'pgs');
$task = def($this->pv,'task','-');
switch($task){

 case 'jobdel': case 'pictdel':
   $this->prop_set('site','jobs',TRUE,'pgs');
   $jid = (int)def($this->pv,'id');

   $sql = 'SELECT dir FROM jobs WHERE id=' . $jid;
   $dir = $this->dir_jobs . $this->db->read_field($sql);
   if($dir == $this->dir_jobs) // sql-field empty
     return $this->main->div($this->txp->t('err-nojob'),'hint');

   if(!is_dir($dir) or !is_writeable($dir))
     return $this->main->div($this->txp->t('err-dir-jobs') . $dir,'err');

   foreach(scandir($dir) as $fn)
     if(preg_match('/^[^.].*\.(gif|tif|tiff|jpg|jpeg|png|txt|zip)$/i',$fn))
       unlink($dir . '/' . $fn);

   $this->main->div($this->txp->t('msg-pictdel-ok'),'ok');

   if($task=='pictdel') break;
   $sql = 'DELETE FROM files where job=' . $jid;
   $this->db->sql_execute($sql);
   $sql = 'DELETE FROM jobs where id=' . $jid;
   $this->db->sql_execute($sql);
   $this->main->div($this->txp->t('msg-jobdel-ok'),'ok');
   break;

 case 'del_user':
   $vdn = def($this->pv,'vdn','-');
   $this->prop_set('site','users',TRUE,'pgs');
   $this->pv['vdn'] = NULL;
   if(!isset($this->users[$vdn]))
     return $this->main->div($this->txp->t('hint-unknownuser'),'hint');
   $tmp = pg_escape_string($vdn);
   $sql = "SELECT count(*) FROM jobs WHERE dig_user='$vdn' or chk_user='$vdn'";
   if($this->db->read_field($sql)>0)
     return $this->main->div($this->txp->t('err-userhasjobs'),'hint');
   $res = max($this->um->user_remove($vdn));
   $this->lists_set();
   if($res<=0)
     return $this->main->div($this->txp->t('hint-done'),'ok');
   return $this->main->div($this->txp->t('hint-failed'),'err');


 case 'clean_keywords':
   $sql = 'DELETE FROM key# WHERE key#.id IN ('
     . ' SELECT key#.id FROM key# LEFT JOIN jobs ON jobs.key#=key#.id'
     . ' GROUP BY key#.id HAVING count(jobs.id)=0)';
   
   $this->db->sql_execute(str_replace('#',1,$sql));
   $this->db->sql_execute(str_replace('#',2,$sql));
   $this->db->sql_execute(str_replace('jobs','files',str_replace('#',3,$sql)));
   break;

 case 'clean_users':
   $sql = 'SELECT DISTINCT dig_user FROM jobs UNION SELECT DISTINCT chk_user FROM jobs';
   $used = array_filter((array)$this->db->read_column($sql));
   $all = array_keys($this->users);
   foreach(array_diff($all,$used,$this->admins) as $vdn)
     $this->um->user_remove($vdn);
   break;
 }

?>
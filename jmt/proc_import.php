<?php
if(!is_object($this->cuser)) return;
if(!$this->is_admin) return $this->main->div($this->txp->t('err-access'),'err');

$this->prop_set('site','import',TRUE,'pgs');
$prefix = 'import__';
$users = $this->um->users_list();

// collect form data -> array(dir=>array(details))
$tmp = array();
foreach(preg_grep('/^' . $prefix . '/',array_keys($this->pv)) as $key){
  $ck = explode('__',$key);
  $tmp[$ck[2]][$ck[1]] = $this->pv[$key];
}

// loop through dirs
foreach($tmp as $dir=>$dat){
  // import keys textfield favoured over select field, add new if necessary, repalce by id
  $keys = array();
  for($i=1;$i<=$this->nkey;$i++){
    $tmp = def($dat,'key' . $i);
    if(empty($tmp)){
      $tmp = def($dat,'key' . $i . '_pre');
    } else {
      $id = $this->db->load_field('key' . $i,array('keyword'=>$tmp));
      if(!is_numeric($id)){
	$this->db->write_row('key' . $i,array('keyword'=>$tmp));
	$tmp = $this->db->load_field('key' . $i,array('keyword'=>$tmp));
      } else $tmp = $id;
    }
    $keys[$i] = empty($tmp)?NULL:$tmp;
  }

  // basic checks
  if(is_null($keys[1])) continue;
  if(!is_dir($this->dir_imp . $dir)) continue;
  /* disabled -> da neu via ftp, kein problem wegen datenverdoppelung
  if(!is_writeable($this->dir_imp . $dir)){
    $this->main->div($this->txp->t('err-dir-ro') . preg_replace('|^[^@]*@|','',$this->dir_imp) . $dir,'err');
    continue;
  } 
  */

  // find new place for the data
  do $dir_tar = md5(rand()); while(is_dir($this->dir_jobs . $dir_tar));

  // collect data for db
  $job = array('dir'=>$dir_tar,'remark'=>def($dat,'rem'));

  // set user if given
  if(!empty($dat['user']) and isset($users[$dat['user']])){
    $job['dig_dateout'] = date('Y-m-d');
    $job['dig_user'] = $dat['user'];
  }

  for($i=1;$i<=$this->nkey;$i++) $job['key' . $i] = $keys[$i];

  // move directory
  if(rcopy($this->dir_imp . $dir,$this->dir_jobs . $dir_tar)===FALSE){
    $this->main->div($this->txp->t('err-movedir'),'err');
    continue;
  } 

  // save to database
  foreach($job as $ck=>$cv) if($cv==='') $job[$ck] = NULL; // empty string to NULL
  if($this->db->write_row('jobs',$job)===FALSE)
    $this->main->div($this->txp->t('err-savejob') . ': ' . $dir,'err');
  else
    $this->main->div($this->txp->t('hint-saved') . ': ' . $dir . '&emsp;' . $this->txp->t('hint-rmftp'),'ok');
}

?>
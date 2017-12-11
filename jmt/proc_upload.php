<?php
if(!is_object($this->cuser)) return;

$prefix = 'upload__';

// collect form data -> array(dir=>array(details))
$tmp = array();
foreach(preg_grep('/^' . $prefix . '/',array_keys($this->pv)) as $key){
  $ck = explode('__',$key);
  $tmp[$ck[1]][$ck[2]] = $this->pv[$key];
}

foreach($tmp as $key=>$dat){
  $file = def($_FILES,$prefix . $key . '__file',array());
  if(empty($file)) continue;
  if(def($file,'name','')=='') continue;
  if(def($file,'error',1)!=0) continue;
  if(def($file,'size',0)==0) continue;

  $job = $this->db->read_row('SELECT * FROM jobs WHERE id=' . $key);
  $pre = $job['dig_user'] == $this->cuser->vdn?'dig':'chk';
  $fname = $pre . $key . '_' . $this->cuser->uname . preg_replace('/^.*(\.[a-zA-Z0-9]+)$/','$1',$file['name']);
  $dest = $this->dir_upload . $fname ;
  if(move_uploaded_file($file['tmp_name'],$dest)===TRUE){
    $dbdat = array($pre . '_file'=>$fname,
		   $pre . '_datein'=>date('Y-m-d'),
		   $pre . '_time'=>empty($dat['time'])?NULL:((int)$dat['time']),
		   $pre . '_remark'=>empty($dat['remark'])?NULL:$dat['remark']);
    if($this->db->write_row('jobs',$dbdat,array('id'=>$key))===FALSE)
      $this->main->div($this->txp->t('err-uploadfailed') . ': ' . $file['name'],'err');
    else
      $this->main->div($this->txp->t('hint-saved'),'ok');
  } else $this->main->div($this->txp->t('err-uploadfailed') . ': ' . $file['name'],'err');
}

?>

<?php
if(!is_object($this->cuser)) $ip->msgclose('@err-access');

// remove old zips
foreach(preg_grep('/^[^.].*\.zip$/',scandir($this->dir_jobs)) as $file){
  if(time()-filemtime($this->dir_jobs . $file) > $this->zip_delay) unlink($this->dir_jobs . $file);
}


$id = def($this->pv,'id');
if(!is_null($id)) {
  $sql = 'SELECT * FROM job_view WHERE id=' . $id;
  $job = $this->db->read_row($sql);
  $ht = $this->main;
  $ht->open('table','job');
  $ht->open('tr');
  $ht->tag('th',$this->txp->t('tit-details'));
  $ht->tag('th',$this->txp->t('tit-files'));

  $ht->next();

  $ht->open('td');
  $this->job_basics($ht,$job,$this->um->cuser->vdn);
  $ht->next();
  $this->job_files($ht,$job);
  $ht->close(3);
 }



$prefix = 'upload__';

$tmp = '_user=\'' . pg_escape_string($this->cuser->vdn) . '\'';
$sql = "SELECT * FROM job_view WHERE dig$tmp OR chk$tmp"
  . ' ORDER BY dig_datein DESC,  dig_dateout DESC,  chk_datein DESC,  chk_dateout DESC';

$jobs = $this->db->read_array($sql);
if(empty($jobs)) return $this->main->div($this->txp->t('hint-nothing'),'hint');

$hf = $this->ptr('form');
$hf->fopen(array('target'=>'upload'));

$hf->open('table','jobs');
$hf->open('tr');
$ar = array('id','key1','key2','remark','dateout','datein','file','time','remark');//,'zip'
foreach($ar as $ce) $hf->tag('th',$this->txp->t('col-' . $ce));

$has = FALSE;

foreach($jobs as $job){
  $pre = $job['dig_user'] == $this->cuser->vdn?'dig':'chk';

  $hf->next();
  $hf->open('td');
  $hf->a($job['id'],array('site'=>$this->nav->cur_get(),'id'=>$job['id']));
  $hf->close();

  $hf->tag('td',$job['key1wrd']);
  $hf->tag('td',$job['key2wrd']);
  $hf->tag('td',$job['remark']);

  $hf->tag('td',$job[$pre . '_dateout']);

  /*
  // zip file
  $hf->open('td');
  $file = $this->dir_jobs . $this->cuser->uname . '_' . $job['dir'] . '.zip';
  if(file_exists($file)){
    $hf->page($file,$this->txp->t('lab-download'),'cmd easy');
  } else {
    $add = array('target'=>'div','task'=>'zip','dir'=>$job['dir'],'user'=>$this->cuser->vdn,'id'=>$job['id']);
    $hf->a($this->txp->t('lab-create'),$add,array('class'=>'cmd hint','style'=>'margin: 15px;'));
  }
  $hf->close();
  */

  if(empty($job[$pre . '_datein'])){
    $has = TRUE;
    $hf->open('td',array('colspan'=>2));
    $hf->file($prefix . $job['id'] . '__file');
    $hf->next(1,array('colspan'=>1));
    $hf->text($prefix . $job['id'] . '__time',$job[$pre . '_time'],array('size'=>3));
    $hf->next();
    $hf->textarea($prefix . $job['id'] . '__remark',$job[$pre . '_remark']);
    $hf->close();
  } else {

    $hf->tag('td',$job[$pre . '_datein']);

    $hf->open('td');
    $fn = $job[$pre . '_file'];
    $hf->page($this->dir_upload . $fn,$fn);
    $add = array('target'=>'div','task'=>'unlink','file'=>$fn);
    if($pre=='chk' or ($pre=='dig' and is_null($job['chk_dateout']))){
      $hf->add('&emsp;');
      $hf->a('<img src="trash.png" alt="del"',$add,array('title'=>'remove file'));
    }
    $hf->close();

    $hf->tag('td',$job[$pre . '_time']);
    $hf->tag('td',$job[$pre . '_remark']);
  }

}
$hf->close(2);
if($has) {
  $hf->send($this->txp->t('lab-upload'),array('class'=>'cmd hint'));
  //$hf->div($this->txp->t('hint-zipcreate_user'),'hint');
 }
$hf->fclose();
$hf->div($this->txp->t('hint-download'),'hint');
$this->main->incl($hf->root);

?>
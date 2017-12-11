<?php
if(!is_object($this->cuser) or !$this->is_admin) $ip->msgclose('@err-access');

$prefix = 'jobs__';

$ht = $this->main;

$ht->h(2,$this->txp->t('tit-jobs'));

$id = def($this->pv,'id');
if(!is_null($id)) {
  $sql = 'SELECT * FROM job_view WHERE id=' . $id;
  $job = $this->db->read_row($sql);
  if(!empty($job)){ // otherwise all files are listed!
    $ht->open('table','job');
    $ht->open('tr');
    $ht->tag('th',$this->txp->t('tit-details'));
    $ht->tag('th',$this->txp->t('tit-files'));
    $ht->tag('th',$this->txp->t('lab-edit'));
    
    $ht->next();
    
    $ht->open('td');
    $this->job_basics($ht,$job);
    $ht->next();
    $this->job_files($ht,$job);
    $ht->next();
    $this->job_edit($ht,$job);
    $ht->close(3);
  } else $ht->div($this->txp->t('err-nojob'),'hint');
 }



$ht->h(3,$this->txp->t('tit-overview'));

// Apply Filter ================================================================================
$sql = 'SELECT * FROM job_view WHERE 1=1';
for($i=1;$i<3;$i++){
  $tmp = def($this->pv,'filter__key' . $i,array());
  if(!empty($tmp)) $sql .= ' AND (key' . $i . '=' . implode(' OR key' . $i . '=',$tmp) . ')';
 }

$tmp = def($this->pv,'filter__user',array());
if(!empty($tmp)){
  $sql .= ' AND (';
  foreach($tmp as $cu)
    $sql .= 'dig_user=\'' . pg_escape_string($cu) . '\' OR chk_user=\'' . pg_escape_string($cu) . '\'';
  $sql .= ')';
 }

// Filter
$tmp = def($this->pv,'filter__status','st-99');
if($tmp!='st-99') $sql .= ' AND status IN (' . implode(', ',str_split(substr($tmp,3),1)) . ')';

// Sorting
$sql .= ' ORDER BY key1wrd, key2wrd, dig_dateout DESC , chk_dateout DESC';
$jobs = $this->db->read_array($sql);





if(!empty($jobs)) {

  $ht->open('table','jobs');
  $ht->open('tr');
  $ht->tag('th','&nbsp;',array('colspan'=>5));
  $ht->tag('th',$this->txp->t('col-dig'),array('colspan'=>6));
  $ht->tag('th',$this->txp->t('col-chk'),array('colspan'=>6));
  $ht->next();

  foreach($this->job_cols as $ck) $ht->tag('th',$this->txp->t('col-' . preg_replace('/^(dig_|chk_)/','',$ck)));
  $r = 0;
  foreach($jobs as $job){
    $ht->next(1,array('class'=>'row' . (($r++)%3)));
    $ht->open('td');
    foreach($this->job_cols as $ck){
      if(is_null($job[$ck])){
	$ht->add('&nbsp;');
      } else if($ck=='id'){
	$ht->a($job[$ck],array('site'=>$this->nav->cur_get(),'id'=>$job[$ck]));
      } else if($ck=='status'){
	$ht->add($this->txp->t('st-' . $job[$ck]));
      } else if(substr($ck,0,3)=='key'){
	$ht->add($this->keys[(int)substr($ck,3)][$job[$ck]]);
      } else if(substr($ck,-4)=='file'){
	$ht->page($this->dir_upload . $job[$ck],$this->txp->t('col-file'));
      } else if(substr($ck,-4)=='user'){
	$ht->a($this->users[$job[$ck]],array('site'=>'users','vdn'=>$job[$ck]));
      } else $ht->add($job[$ck]);
      $ht->next();
    }
    $ht->close();
  }
  $ht->close(2);

 } else $ht->div($this->txp->t('hint-nothing'),'hint');





// Filter ================================================================================

$ht->h(3,$this->txp->t('tit-filter'));
$hf = $this->ptr('form');
$hf->fopen(FALSE);
$hf->open('table','filter');
$hf->open('tr');
for($i=1;$i<3;$i++) $hf->tag('th',$this->txp->t('col-key' . $i));
$hf->tag('th',$this->txp->t('col-user'));
$hf->tag('th',$this->txp->t('col-status'));

$hf->next();
$hf->open('td');
for($i=1;$i<=$this->nkey;$i++){
  $hf->select('filter__key' . $i,$this->keys[$i],def($this->pv,'filter__key' . $i,array()),array('size'=>8));
  $hf->next();
 }

$hf->select('filter__user',$this->users,def($this->pv,'filter__user',array()),array('size'=>8));
$hf->next();

$ak = preg_grep('/^st-\d+$/',array_keys($this->txp->data));
$ar = array();
foreach($ak as $ck) $ar[$ck] = $this->txp->t($ck);
$hf->select('filter__status',$ar,def($this->pv,'filter__status','st-99'),array('size'=>8,'multiple'=>FALSE));
$hf->next();



$hf->send($this->txp->t('lab-filter'),array('class'=>'cmd easy'));
$hf->a($this->txp->t('lab-showall'),array(),array('class'=>'cmd easy','style'=>'margin: 5px;'));
$hf->close(2);
$hf->fclose();
$ht->incl($hf->root);
$ht->div($this->txp->t('hint-filter'),'hint');


?>


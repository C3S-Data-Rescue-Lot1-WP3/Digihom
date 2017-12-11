<?php
  // no user -> nothing to do
if(!is_object($this->user))   $this->msgclose('@err-access');
// not logged in -> not allowed
if(!is_object($this->cuser)) $this->msgclose('@err-access');
// either myself or I'm admin
if($this->cuser->vdn != $this->user->vdn and !in_array($this->cuser->vdn,$this->admins)) 
  $this->msgclose('@err-access');

$ht->open('dl','user');
$labels = $this->user_info_labels($this->user_infos);
$values = $this->user_values($this->user->vdn,$this->user_infos);
foreach($this->user_infos as $key=>$set){
  if(empty($values[$key])) continue;
  $ht->tag('dt',$labels[$key]);
  $ht->open('dd');
  $tmp = def($set,'disp','text');
  switch($tmp){
  case 'email':
    $ht->mail($values[$key],$values[$key],array('subject'=>$this->txp->_title));
    break;
  default:
    $ht->add($values[$key]);
  }
  $ht->close();
}

$ar = array('dig','chk');
foreach($ar as $ck){
  $sql = 'SELECT * FROM job_view WHERE ' . $ck . '_user=\'' . pg_escape_string($vdn) . '\' ORDER BY status';
  $rows = $this->db->read_array($sql);
  if(empty($rows)) continue;
  $ht->tag('dt',$this->txp->t('lab-jobs') . ' ' . $this->txp->t('col-' . $ck));
  $ht->open('dd');
  for($i=0;$i<count($rows);$i++){
    $row = $rows[$i];
    if($i>0) $ht->add('&emsp;&diams;&emsp;');
    $ht->a($row['id'],array('site'=>'jobs','id'=>$row['id']));
    $ht->add("&emsp;$row[key1wrd]&emsp;$row[key2wrd]");
    if($ck=='dig') $tmp = $row['status']==1?'pend':'done';
    else           $tmp = $row['status']==3?'pend':'done';
    $ht->add('&emsp;(' . $this->txp->t('lab-' . $tmp) . ')');
  }
  $ht->close();
}

$ht->close();



?>
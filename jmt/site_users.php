<?php
if(!isset($this->cuser) or !is_object($this->cuser)) $this->msgclose('@err-access');

$um = $this->um;

// current user ------------------------------------------------------------
$vdn = def($this->pv,'vdn');
$this->user = $this->um->umu_get($vdn);
if(!is_null($vdn) and !is_object($this->user))
  return $this->main->div($this->txp->t('hint-unknownuser'),'hint');

$cmd = def($this->pv,'cmd',is_object($this->user)?'show':NULL);
$file = $this->tool->dir('jmt') . 'site_user_' . $cmd . '.php';
if(is_object($this->user) and isset($this->users[$vdn]) and file_exists($file)){
  $ht = $this->main;
  $ht->h(2,$this->users[$vdn]);
  include($file);
 }


// User table ------------------------------------------------------------
$this->main->h(2,$this->txp->t('tit-overview'));
$this->main->incl($this->user_tab());


$res = array('all'=>array(),'admin'=>array(),'user'=>array());
foreach($this->users as $vdn=>$val){
  $mail = $um->user_info($vdn,'email');
  if(empty($mail)) continue;
  $res['all'][] = $mail;
  $res[in_array($vdn,$this->admins)?'admin':'user'][] = $mail;
}

$this->main->h(2,$this->txp->t('tit-create'));
$this->main->incl($this->user_new());

// Mail --------------------------------------------------
if(!empty($res)){
  $this->main->h(2,$this->txp->t('tit-mail'));
  foreach($res as $key=>$val){
    $add = array('bcc'=>$val,'subject'=>$this->txp->_title);
    $this->main->mail($this->cuser->info_get('email'),$key,$add,'cmd easy');
  }
 }


?>
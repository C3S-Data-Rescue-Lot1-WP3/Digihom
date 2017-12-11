<?php
if(!is_object($this->cuser)) $ip->msgclose('@err-access');

$this->user = $this->cuser;
$vdn = $this->cuser->vdn;

$cmd = def($this->pv,'cmd');
$file = $this->tool->dir('jmt') . 'site_user_' . $cmd . '.php';
if(is_object($this->user) and file_exists($file)){
  $ht = $this->main;
  $ht->h(2,$this->user->name);
  include($file);
 }


$this->main->h(2,$this->txp->t('tit-persbasic'));
$this->main->incl($this->user_tab($this->user->vdn));


?>
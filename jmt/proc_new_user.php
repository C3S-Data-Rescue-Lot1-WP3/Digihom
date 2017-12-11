<?php
if(!is_object($this->cuser)) return;
if(!$this->is_admin) return $this->main->div($this->txp->t('err-access'),'err');

$uname = def($this->pv,'usernew__uname');

if(empty($uname) or strlen($uname)<3 or strlen($uname)>60)
  return $this->main->div($this->txp->t('err-unamefailed'),'hint');
$vdn = $this->um->vdn_make($uname);
$this->pv['vdn'] = $vdn;
$this->pv['cmd'] = 'edit';

if($this->um->user_exists($vdn))
  return $this->main->div($this->txp->t('err-userexists'),'hint');
$data = array('uname'=>$uname);
if($this->um->user_create($data)>0)
  return $this->main->div($this->txp->t('hint-failed'),'hint');
$this->lists_set();
$this->main->div($this->txp->t('hint-saved'),'ok');
?>
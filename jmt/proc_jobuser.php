<?php

if(!is_object($this->cuser)) return;
if(!$this->is_admin) return $this->main->div($this->txp->t('err-access'),'err');

$id = def($this->pv,'id',0);
$typ = def($this->pv,'typ','-');
if($typ!='chk' and $typ!='dig') 
  return $this->main->div($this->txp->t('err-invalidrequest'),'err');

$job = $this->db->read_row('SELECT * FROM job_view WHERE id=' . (int)$id);

if(empty($job)) 
  return $this->main->div($this->txp->t('err-invalidrequest'),'err');

$vdn = def($this->pv,'jobuser__user','-');
if(!isset($this->users[$vdn])) 
  return $this->main->div($this->txp->t('err-invalidrequest'),'err');
if($job['dig_user']===$vdn) 
  return $this->main->div($this->txp->t('err-invalidrequest'),'err');
if($typ=='dig' and $job['status']!=1 and $job['status']!=0) 
  return $this->main->div($this->txp->t('err-invalidrequest'),'err');
if($typ=='chk' and $job['status']!=2 and $job['status']!=3) 
  return $this->main->div($this->txp->t('err-invalidrequest'),'err');

$dbdat = array($typ . '_user'=>$vdn,$typ . '_dateout'=>date('Y-m-d'));
if($this->db->write_row('jobs',$dbdat,array('id'=>$id))===FALSE)
  return $this->main->div($this->txp->t('err-savejob'),'err');
return $this->main->div($this->txp->t('hint-saved'),'ok');
?>
<?php
if(!is_object($this->cuser)) return;

$vdn = def($this->pv,'vdn','xxx');
if(!$this->um->user_exists($vdn)) 
  return $this->main->div($this->txp->t('hint-unknownuser'),'hint');

$user = $this->um->umu_get($vdn);
$fprefix = 'useredit__';

// check password for edit by user
if(!$this->is_admin and $this->um->user_validate($vdn,def($this->pv,$fprefix . 'pwd','-'))>0){
  $this->pv['cmd'] = 'edit';
  return $this->main->div($this->txp->t('err-pwdfailed'),'hint');
 }



$data = array();
foreach($this->user_infos as $key=>$set){
  $cval = def($this->pv,$fprefix . $key);
  if(is_null($cval)) continue;
  switch(def($set,'get','um')){
  case 'um':
    $data[$key] = $cval;
    break;
  case 'isadmin':
    $gdn = $this->um->gdn_make('admin');
    if($cval=='on') $this->um->group_user_add($gdn,$vdn,'member');
    else            $this->um->group_user_remove($gdn,$vdn);
  }
}

$user->info_setn($data,$res);



$npwd = def($this->pv,$fprefix . 'pwdnew');
if(!empty($npwd)) $res['pwd'] = $this->um->user_pwd_set($vdn,$npwd);

switch(max($res)){
 case -1:
   $this->main->div($this->txp->t('hint-nothingchanged'),'hint');
   break;
 case 0:
   $this->main->div($this->txp->t('hint-saved'),'ok');
   break;
 default:
   qq($res);
   $this->main->div($this->txp->t('hint-failed'),'err');
   $this->pv['cmd'] = 'edit';
 }

// update lists
$this->lists_set();
?>

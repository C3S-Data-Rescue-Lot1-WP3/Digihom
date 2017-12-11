<?php
  // no user -> nothing to do
if(!is_object($this->user))   $this->msgclose('@err-access');
// not logged in -> not allowed
if(!is_object($this->cuser)) $this->msgclose('@err-access');
// either myself or I'm admin
if($this->cuser->vdn != $this->user->vdn and !$this->is_admin) $this->msgclose('@err-access');

$fprefix = 'useredit__';

$hf = $this->ptr('form');
$add = array('target'=>'edit_user','vdn'=>$this->user->vdn,'site'=>$this->nav->cur_get());
$hf->fopen($add);

$hf->open('dl','user');
$labels = $this->user_info_labels($this->user_infos);
$values = $this->user_values($this->user->vdn,$this->user_infos);
foreach($this->user_infos as $key=>$set){
  $fkey = $fprefix . $key;
  if(!isset($this->pv[$fkey])){
    $cval = $values[$key];
    switch(def($set,'type','text')){
    case 'text':
      $hf->def_add($fkey,$cval);
      break;
    case 'isadmin':
      $hf->def_add($fkey,$cval=='admin');
      break;
    }
  } else $hf->def_add($fkey,$this->pv[$fkey]);

  $hf->tag('dt',$labels[$key]);
  $hf->open('dd');
  switch(def($set,'write','user')){
  case 'none':
    $hf->add($values[$key]);
    break;
  case 'admin':
    if(!$this->is_admin){
      $hf->add($values[$key]);
      break;
    } // no break here
  case 'user':
    $tmp = def($set,'edit','text');
    switch($tmp){
    case 'text':
      $hf->text($fkey,NULL,array('size'=>40));
      break;
    case 'isadmin':
      $hf->add($this->txp->t('qst-isadmin'));
      $hf->checkbox($fkey);
    }
    break;
  }
  $hf->close();
}
$hf->tag('dt',$this->txp->t('lab-pwdnew'));
$hf->open('dd');
$hf->password($fprefix . 'pwdnew');
$hf->close();

if(!$this->is_admin){
  $hf->tag('dt',$this->txp->t('um-fld-pwd'));
  $hf->open('dd');
  $hf->password($fprefix . 'pwd');
  $hf->close();
 }

$hf->p($this->txp->t('hint-edituser-as' . ($this->is_admin?'admin':'user')));


$hf->close();
$hf->open('p');
$hf->send($this->txp->t('lab-save'),array('class'=>'cmd hint'));
$hf->close();
$hf->fclose();
$this->main->incl($hf);

?>
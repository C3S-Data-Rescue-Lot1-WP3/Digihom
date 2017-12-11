<?php
if(!isset($cuser) or !is_object($cuser)) 
  return;

if(!$is_admin) 
  return $ip->main->div($ip->txp->t('err-access'),'err');

$id = $pv['station'];
if(empty($id))
  return $ip->main->div($ip->txp->t('err-incomplete'),'err');

$prefix = 'station__';
$dat = array();
foreach(preg_grep('/^' . $prefix . '/',array_keys($pv)) as $key){
  if($pv[$key]==='') $pv[$key] = NULL;
  $dat[preg_replace('/^(' . $prefix . ')/','',$key)] = $pv[$key];
}

if(@$db->write_row('stations',$dat,array('id'=>$id))===FALSE){
  $pv['cmd'] = 'edit';
  return $ip->main->div($ip->txp->t('hint-failed'),'err');
 } else {
  $pv['cmd'] = 'show';
  $ip->main->div($ip->txp->t('hint-saved'),'ok');
 }




?>
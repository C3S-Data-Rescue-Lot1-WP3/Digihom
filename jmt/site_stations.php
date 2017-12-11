<?php
if(!isset($cuser) or !is_object($cuser)) $ip->msgclose('@err-access');

$prefix = 'station__';
$ht = $ip->main;

$station = def($pv,'station');
$sql = 'SELECT * FROM stations WHERE ' . (is_numeric($station)?('id=' . $station):('station=\'' . pg_escape_String($station) . '\''));
$stat = $db->read_row($sql);
$cmd = def($pv,'cmd','show');

if(is_array($stat) and $cmd=='show'){
  $ht->h(3,$stat['station']);
  $ht->open('dl','user');
  foreach($stat as $key=>$val){
    if(is_null($val)) continue;
    $ht->tag('dt',$ip->txp->t('col-' . $key));
    $ht->tag('dd',$val);
  }
  $ht->close();
  $ht->a($ip->txp->t('lab-edit'),array('cmd'=>'edit','station'=>$station),'cmd');


 } else if(is_array($stat) and $cmd=='edit'){
  $ht->h(3,$stat['station']);

  $hf = $ip->ptr('form');
  foreach($stat as $ck=>$cv){
    $fkey = $prefix . $ck;
    $hf->def_add($fkey,def($pv,$fkey,$cv));
  }

  $add = array('target'=>'edit_station','station'=>$stat['id']);
  $hf->fopen($add);
  $hf->open('dl','user');
  foreach($stat as $key=>$val){
    $hf->tag('dt',$ip->txp->t('col-' . $key));
    $hf->open('dd');
    if($key=='id')
      $hf->add($val);
    else
      $hf->text($prefix . $key);
    $hf->close();
  }
  $hf->send($ip->txp->t('lab-save'));
  $hf->fclose();
  $ht->incl($hf);

 }


$ht->h(3,$ip->txp->t('tit-stations'));
$sql = 'SELECT * FROM stations ORDER BY station';
$all = $ip->db->read_array($sql);
if(is_array($all) and count($all)>0){
  $tab = new opc_ht2o_table($ht);
  $tab->show_rown = FALSE;
  $tab->tag_table = array('class'=>'user-tab');
  $col = array_keys($all[0]);
  $ht->in();
  foreach($col as $ck) 
    $tab->set($ip->txp->t('col-' . $ck),'colkey',$ck);
  $tab->set($ip->txp->t('lab-cmds'),'colkey','cmds');
  foreach($all as $one){
    $row = 'r' . $one['id'];
    $tab->set($one['id'],'rowkey',$row);
    foreach($one as $ck=>$cv){
      $tab->set($cv,'cell',$row,$ck);
    }
    $tab->set_ht($ht,'cell',$row,'cmds');
    $add = array('site'=>$ip->nav->cur_get(),'station'=>$one['station']);
    $add['cmd'] = 'show';
    $ht->a($ip->txp->t('lab-show'),$add,'cmd'); 
    $add['cmd'] = 'edit';
    $ht->a($ip->txp->t('lab-edit'),$add,'cmd'); 
  }
  $ht->out();
  $tab->set(FALSE,'rowkeys');
  $tab->output();

 } else $ht->div($ip->txp->t('hint-nostation'),'hint');

$ht->h(3,$ip->txp->t('tit-create'));
$hf = $ip->ptr('form');
$hf->fopen(array('target'=>'new_station'));
$hf->add($ip->txp->t('lab-name'));
$hf->text('stationname');
$hf->send($ip->txp->t('lab-create'));
$hf->fclose();
$ht->incl($hf->root);

?>

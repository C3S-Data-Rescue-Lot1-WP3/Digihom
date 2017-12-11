<?php
$ht->h(2,'Station managment');


$flds = array('station'=>'First Name',
	      'prio'=>'Priority',
	      'fyear'=>'First Year',
	      'lyear'=>'Last year',
	      'remark'=>'Remark');
$sort = isset($flds[def($pv,'sort')])?$pv['sort']:keyn($flds);
$sql = "SELECT stations.*, a.njobs FROM stations LEFT JOIN"
  . " (SELECT station, count(*) as njobs FROM jobs GROUP BY station) a ON stations.id=a.station"
  . " ORDER BY stations.$sort, stations.station";
$udata = $db->read_array($sql,'id');

$ht->h(3,'Add station');
$hf->reset();
if(def($pv,'prepare')=='editstation'){
  if(!isset($nu)) $nu = $udata[$pv['id']];
     $ha = array('action'=>'editstation','id'=>$pv['id']);
     $txt = 'change';
 } else {
  if(!isset($nu)) $nu = array('station'=>'','prio'=>'','fyear'=>'','lyear'=>'');
  $ha = array('action'=>'newstation');
  $txt = 'add';
 }
$ha['site'] = $site;
$hf->fopen('blockform',$ha);
$ar = array('Station'=>$hf->text2str('station',def($nu,'station'),array('size'=>30)),
	    'Priority'=>$hf->text2str('prio',def($nu,'prio'),array('size'=>5)),
	    'First year'=>$hf->text2str('fyear',def($nu,'fyear'),array('size'=>5)),
	    'Last year'=>$hf->text2str('lyear',def($nu,'lyear'),array('size'=>5)),
	    'Remark'=>$hf->text2str('remark',def($nu,'remark'),array('size'=>50)));
$ar[''] = $hf->submit2str($txt);
$hf->add($ht->array2list2str($ar,array('typ'=>'htable','nt'=>'th')));

$ht->div($hf->output());


$ht->h(3,'Current stations');
$ht->open('table','dbtable');

$ht->open('tr');
foreach($flds as $key=>$val){
  $ht->tag('th',$ht->a2str($val,0,array('site'=>$site,'sort'=>$key),NULL,$sort==$key?'sort_this':'sort'));
}
$ht->tag('td','#Jobs');
$ht->tag('td','(pre)');
$ht->tag('td','(digi)');
$ht->tag('td','(check)');
$ht->close();

$ar = array('site'=>$site,'prepare'=>'editstation','sort'=>$sort);
$br = array('site'=>$site,'direct'=>'removestation','sort'=>$sort);
foreach($udata as $cu){
  $ht->open('tr');
  foreach($flds as $key=>$val) $ht->tag('td',$cu[$key]);
  if($cu['njobs']>0){
    $ht->tag('td',$ht->a2str($cu['njobs'],0,array('site'=>'jobs','station'=>$cu['id']),'overview','intab'));
    $sql= "SELECT status, count(*) as c FROM jobs WHERE station=$cu[id] GROUP BY status ";
    $sdat = $db->read_column($sql,1,0);
    for($jj=0;$jj<8;$jj++) {
      if(is_null(def($sdat,$jj))) $sdat[$jj] = '-';
      else $sdat[$jj] = $ht->a2str($sdat[$jj],0,
				   array('site'=>'jobs','station'=>$cu['id'],'period'=>$jj),
				   'overview','intab');
    }
    $txt = "($sdat[0] / $sdat[1]) ($sdat[2] / $sdat[3] / $sdat[4]) ($sdat[5] / $sdat[6] / $sdat[7])";
    $ht->tag('td',"($sdat[0] / $sdat[1])");
    $ht->tag('td',"($sdat[2] / $sdat[3] / $sdat[4])");
    $ht->tag('td',"($sdat[5] / $sdat[6] / $sdat[7])");

  } else {
    $ht->tag('td','<i>none</i>',array('colspan'=>4));
  }
  $ar['id'] = $cu['id'];
  $br['id'] = $cu['id'];
  $ht->tag('td',$ht->a2str('edit',1,$ar,NULL,'buttonsmall')
	   . ($cu['njobs']==0?$ht->a2str('remove',1,$br,NULL,'buttonsmall'):'')
	   );
  $ht->close();
}
$ht->close();
?>
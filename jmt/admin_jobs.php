<?php
$ht->h(2,'Job managment');

$ht->h(3,'Stations');
$station = isset($stations[def($pv,'station')])?$pv['station']:keyn($stations);
$hs = new opc_htselect_array($stations,$station,$ip->set['xhtml']);
$hs->class = 'station';
$hs->id = 'station';
$hs->byrow = FALSE;
$hs->args = array('site'=>$site);
$ht->add($hs->select2str());
$stat = $db->read_row("SELECT * FROM stations WHERE id=$station");




$periods = array();
$cy = (int)$stat['fyear'];
$ly = (int)$stat['lyear'];
while($cy<=$ly){
  $yz = floor(($cy-1)/10);
  $periods[$yz] = $cy . '-' . ($yz*10+10);
  $cy = $yz*10+11;
 }
$yrange = range((int)$stat['fyear'],(int)$stat['lyear']);

$ht->h(3,'Mass job creation/modification');
$ht->p('<b>Create station entries</b>:&emsp;Will create for the asked time period one entry for each month, if it not already exists.','TitlePlus');
$hf->reset();
$hf->fopen(array(),array('site'=>$site,'station'=>$station,'action'=>'jobentries'));
if(!isset($nu)) $nu = array();
$ar = array('First year'=>$hf->select2str('fyear',$yrange,def($nu,'fyear'),array('key'=>FALSE)),
	    'Last year'=>$hf->select2str('lyear',$yrange,def($nu,'lyear'),array('key'=>FALSE)),
	    'Source'=>$hf->text2str('quelle',def($nu,'quelle'),array('size'=>20)),
	    'Status'=>$hf->select2str('status',array(0=>$stati[0],1=>$stati[1]),is_null(def($nu,'status'))?1:def($nu,'status')),
	    '#Vars.'=>$hf->text2str('nvar',def($nu,'nvar'),array('size'=>4)),
	    'Variable names'=>$hf->text2str('varnames',def($nu,'varnames'),array('size'=>30)),
	    'Remarks'=>$hf->text2str('remark',def($nu,'remark'),array('size'=>20)),
	    ''=>$hf->submit2str('create')
	    );
$hf->add($ht->array2list2str($ar,array('typ'=>'htable','nt'=>'th')));
$ht->div($hf->output());








$ht->p('<b>Assign user</b>:&emsp;Will assign the user for digitalisation or controlling to the jobs of the asked time period.'
       . " Works only if they have status 1 ($stati[1]) or 4 ($stati[4]).",'TitlePlus');
$hf->reset();
$hf->fopen(array(),array('site'=>$site,'station'=>$station,'action'=>'jobuser'));
$ar = array('First year'=>$hf->select2str('fyear',$yrange,def($nu,'fyear'),array('key'=>FALSE)),
	    'Last year'=>$hf->select2str('lyear',$yrange,def($nu,'lyear'),array('key'=>FALSE)),
	    'User'=>$hf->select2str('user',$users),
	    ''=>$hf->submit2str('create')
	    );
$hf->add($ht->array2list2str($ar,array('typ'=>'htable','nt'=>'th')));
$ht->div($hf->output());








$ht->hr();
$ht->h(3,"Overview $stations[$station]",'overview');
$ht->open('div','period');
if(isset($periods[def($pv,'period')])) $period = $pv['period'];
 else if(isset($stati[def($pv,'period')])) $period = $pv['period'];
 else $period = keyn($periods);

$hs = new opc_htselect_list($periods,$period,$ip->set['xhtml']);
$hs->class = 'period';
$hs->id = 'period';
$hs->args = array('site'=>$site,'station'=>$station,'#'=>'overview','intab');
$ht->add($hs->select2str());

$hs = new opc_htselect_list($stati,$period,$ip->set['xhtml']);
$hs->class = 'period';
$hs->id = 'period';
$hs->args = array('site'=>$site,'station'=>$station,'#'=>'overview','intab');
$ht->add($hs->select2str());
$ht->close();

$sql = "SELECT * FROM jobs WHERE station=$station AND ";
if($period<100) $sql .= "status=$period";
 else $sql .= "year>" . ($period*10) . ' AND year<=' . ($period*10+10);
$sql .= ' ORDER BY year, month';
$data = $db->read_array($sql);

function headerrow(&$ht){
  $ht->open('tr');
  $ar = array('Time','Source','#Var','Variables','Status','Remark','');
  $ht->chain($ar,array('tag'=>'th','style'=>'color: black;'));
  $ar = array('User','Date out','Date in','Time');
  $ht->chain($ar,array('tag'=>'th','style'=>'color: #00008B;'));
  $ar = array('User','Date out','Date in','Time');
  $ht->chain($ar,array('tag'=>'th','style'=>'color: green;'));
  $ht->close();
}

if(is_array($data)){
  $ht->add($hf->fopen2str(array(),array('site'=>$site,'action'=>'jobremark','period'=>$period,'station'=>$station)));

  $ht->open('table','jobs');
  $ht->open('tr');
  $ht->tag('th','Basic data',array('colspan'=>7));
  $ht->tag('th','Digitalisation',array('colspan'=>4,'style'=>'color: #00008B;'));
  $ht->tag('th','Controlling',array('colspan'=>4,'style'=>'color: green;'));
  $ht->close();//tr
  $ly = 0;
  foreach($data as $cl){
    $ak = array_keys($cl);
    foreach($ak as $ck) if(is_null($cl[$ck])) $cl[$ck] = '-';
    if($ly!=$cl['year']) { headerrow($ht); $ly = $cl['year'];}
    

    $ht->open('tr');

    $txt = sprintf("%d-%02d",$cl['year'],$cl['month']) . "<a name='T$cl[year]$cl[month]'></a>";
    if($period<100){
      $ht->tag('td',$ht->a2str($txt,1,
			       array('site'=>$site,'station'=>$station,'period'=>floor(($cl['year']-1)/10)),
			       'overview','intab'));
    } else $ht->tag('td',$txt);
    
    $ht->chain(array($cl['quelle'],$cl['varcount'],$cl['varnames']),'td');
    if($period>100){
      $ht->tag('td',$ht->a2str($stati[$cl['status']],1,
			       array('site'=>$site,'station'=>$station,'period'=>$cl['status']),
			       'overview','intab'));
    } else $ht->tag('td',$stati[$cl['status']]);
    $ht->tag('td',$cl['remark']);
    $ht->tag('td',$hf->checkbox2str('J' . $cl['id']));


    $ht->tag('td',$ht->a2str(def($users,$cl['dig_user']),1,
			     array('site'=>'users','user'=>$cl['dig_user'],'status'=>$cl['status']),
			     'overview','intab'));
    $ht->chain(array($cl['dig_dateout'],
		     is_null($cl['dig_datein'])?'-':("<a href='$uploaddir$cl[dig_file]' class='linkfile'>" . $cl['dig_datein'] . "</a>"),
		     $cl['dig_time']),'td');

    $ht->tag('td',$ht->a2str(def($users,$cl['chk_user']),1,
			     array('site'=>'users','user'=>$cl['chk_user'],'status'=>$cl['status']),
			     'overview','intab'));
    $ht->chain(array($cl['chk_dateout'],
		     is_null($cl['chk_datein'])?'-':("<a href='$uploaddir$cl[chk_file]' class='linkfile'>" . $cl['chk_datein'] . "</a>"),
		     $cl['chk_time']),'td');
    if($cl['status']==0 or $cl['status']==3 or $cl['status']==6){
      $br = array('direct'=>'nextstep','station'=>$station,'year'=>$cl['year'],'month'=>$cl['month']);
      $ht->tag('td',$ht->a2str('next step',1,$br,'overview','buttonsmall'));
    }
    $ht->close();
  }
  $ht->close();
  $ht->p('To change existing remarks please use the following field and check the checkboxes in the asked rows');
  $ht->p('new remark: ' . $hf->text2str('remark',def($nu,'remark'),array('size'=>40)) . $hf->submit2str('update remark'));
  $ht->p('To move multiple jobs to the next step please  check the checkboxes in the asked rows. '
	 . $hf->input2str('nextstep','next step','submit'));
  $ht->p('Remark: If you also fill in the remark field. they will be updated too.');
  $ht->add('</form>');
 } else $ht->p('no data (yet)');




?>
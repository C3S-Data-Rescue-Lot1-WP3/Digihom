<?php

$ht->h(2,'Uploaded files');
$ht->p('Overview over the uploded files (if not yet moved to lake).');

$dh = dir($uploaddir);
$orp = array();
while (false !== ($entry = $dh->read())) {
  if(strtolower(substr($entry,-4))!='.xls') continue;
  $orp[$entry] = $entry;
}
$dh->close();
asort($orp);

$ht->open('ul');
foreach($orp as $cfile){
  $fn = $uploaddir . $cfile;
  $ht->tag('li',"<a href='$fn'>$cfile</a> " . date('d.m.y H:i',filemtime($fn)));
}
$ht->close();
return;


// old version where files were not automatically moved to lake
$ht->p('Overview over the uploded files. The dates represent the upload date.'
       . ' If a <i>checked</i> file  exists too it will appear on a second line.');
$ht->p('At the <a href="#uuf">bottom of the site</a> you will find a list of existing files'
       . ' which are not listed in the database.');


$ht->h(3,'Stations');

$sql = 'SELECT DISTINCT stations.station, stations.id'
  . ' FROM stations INNER JOIN jobs ON jobs.station=stations.id'
  . ' WHERE dig_file IS NOT NULL ORDER BY stations.station';
$sdata = $db->read_column($sql,0,1);
$hs = new opc_htselect_array($sdata,NULL,$iact->xhtml);
$hs->class = 'station';
$hs->id = '#S';
$hs->byrow = FALSE;
$ht->add($hs->select2str());
$sql = 'SELECT * FROM jobs  WHERE dig_file IS NOT NULL ORDER BY station, year, month';
$data = $db->read_array($sql);

$cs = 0;
$cy = 0;
$cm = 0;
$ht->open('table');
foreach($data as $cd){
  if($cd['station']!=$cs){ // next station
    if($cs!='') $ht->close();
    $cs = $cd['station'];
    $cy = 0;
    $ht->open('tr');
    $ht->tag('th',$stations[$cs] . "<a name='S$cs'></a>");
    for($ii=1;$ii<=12;$ii++)$ht->tag('th',date('M',mktime(12,0,0,$ii,1,1975)));
  } 
  if($cd['year']!=$cy){ // next year
    $ht->close();
    $cy = $cd['year'];
    $cm = 1;
    $ht->open('tr');
    $ht->tag('th',$cy,'text-align: right;');
  }

  // skip missing months
  while($cm++<$cd['month']) $ht->tag('td','&nbsp;');

  $fn = $uploaddir . $cd['dig_file'];
  if(isset($orp[$cd['dig_file']])){
    unset($orp[$cd['dig_file']]);
    $txt = "D:<a href='$fn'>" . date('d.m.y',filemtime($fn)) . '</a>';
  } else $txt = date($cd['chk_date'],'H:i');

  if(!is_null($cd['chk_file'])){
    $fn = $uploaddir . $cd['chk_file'];
    $txt .= $ht->br2str();
    if(isset($orp[$cd['chk_file']])){
      unset($orp[$cd['chk_file']]);
      $txt .= "C:<a href='$fn' style='color: blue;'>" . date('d.m.y',filemtime($fn)) . '</a>';
    } else $txt .= date($cd['chk_date'],'H:i');
  }

  $ht->tag('td',$txt);

}
$ht->close(2);

$ht->h(3,'Unused files','uuf');

$ht->open('ul');
foreach($orp as $val)
  $ht->tag('li',"<a href='$uploaddir$val'>$val</a>" 
	   . date(' d.m.Y H:i',filemtime($uploaddir . $val))
	   . $ht->a2str('remove',1,array('direct'=>'rmfile','file'=>$val),NULL,'buttonxsmall'));
$ht->close();

?>
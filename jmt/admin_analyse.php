<?php
$ht->h(3,'Statistics');
$sql = "SELECT date_trunc('month',dig_datein) as dat, dig_user," 
  . ' sum(dig_time) as totaltime'
  . ' FROM jobs'
  . ' WHERE status>2 AND dig_time IS NOT NULL'
  . " GROUP BY date_trunc('month',dig_datein), dig_user"
  . " ORDER BY date_trunc('month',dig_datein) DESC, dig_user";
$qa = $db->read_array($sql);

$ht->h(1,'Statistics over ' . $this->txp->_title);
$ht->h(2,'Total time per user and month');
$cm = ''; 
$ht->open('dl');
foreach($qa as $qe){
  if($cm!=$qe['dat']){
    $cm = $qe['dat'];
    $ht->tag('dt',substr($cm,0,7));
  }
  $ht->tag('dd',$users[$qe['dig_user']] . ': ' .  $qe['totaltime']);
}
$ht->close();
?>
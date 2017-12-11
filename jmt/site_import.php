<?php
if(!is_object($this->cuser) or !$this->is_admin) $this->msgclose('@err-access');
 
$prefix = 'import__';

$ht = $this->main;
$ht->h(2,$this->txp->t('nav-import'));
$tmp = $this->txp->t('hint-import');
$ar = array('%1'=>preg_replace('|^[^@]*@|','',$this->dir_imp),
	    '%2'=>$this->dir_jobs,'%3'=>$this->dir_upload);
foreach($ar as $ck=>$cv) $tmp = str_replace($ck,$cv,$tmp);
$ht->div($tmp,'hint');


$dirs = array();
$sd = @scandir($this->dir_imp);
if($sd===FALSE) $sd = array();
foreach($sd as $cd)
  if(substr($cd,0,1)!='.' and is_dir($this->dir_imp . $cd)) $dirs[] = $cd;
if(empty($dirs)) return $ht->div($this->txp->t('hint-nothing'),'hint');


asort($dirs);

$hf = $this->ptr('form');

foreach(preg_grep('/^' . $prefix .'/',array_keys($this->pv)) as $ck){
  $hf->def_add($ck,$this->pv[$ck]);
}

$hid = array('target'=>'import');
$hf->fopen($hid);

$hf->open('table','import');
$cols = array('dir','key1','key2','rem','usr');
$hf->open('tr');
foreach($cols as $ck) $hf->tag('th',$this->txp->t('col-' . $ck));
foreach($dirs as $dir){
  $hf->next();
  $hf->open('td');
  // bei nicht ftp-verzeichnissen müsste man um 2 korrigieren (. und ..) oder diese rausfiltern
  $hf->add('<b>' . $dir . '</b><br/>' . (count(scandir($this->dir_imp . $dir))) . ' file(s)');
  for($i=1;$i<3;$i++){
    $hf->next();
    $hf->text($prefix . 'key' . $i . '__' . $dir);
    $hf->br();
    $hf->select($prefix . 'key' . $i . '_pre__' . $dir,$this->keys_sel[$i]);
  }
  $hf->next();
  $hf->textarea($prefix . 'rem__' . $dir);
  $hf->next();
  $hf->select($prefix . 'user__' . $dir,$this->users_sel);
  $hf->close();
}
$hf->close(2);

$hf->send($this->txp->t('lab-create'),array('class'=>'cmd hint'));
$hf->fclose();
$ht->incl($hf->root);

?>

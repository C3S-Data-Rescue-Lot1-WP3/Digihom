<?php
if(!is_object($this->cuser) or !$this->is_admin) $this->msgclose('@err-access');

$ht = $this->main;

$ht->h(2,$this->txp->t('nav-admin'));
$ht->open('p');
$ht->a('remove unused users',array('target'=>'admin','task'=>'clean_users'),'cmd hint');
$ht->next();
$ht->a('remove unused keywords',array('target'=>'admin','task'=>'clean_keywords'),'cmd hint');
$sql = 'SELECT count(*) FROM key#';
$ht->add(sprintf('&emsp;Current count: keyword-1:  %s; keyword-2: %s; keyword-3: %s',
		 $this->db->read_field(str_replace('#',1,$sql)),
		 $this->db->read_field(str_replace('#',2,$sql)),
		 $this->db->read_field(str_replace('#',3,$sql))));
$ht->close();

// reactivate testing is over at oeschger 
if($this->tool->mode=='operational') return;

$ht->h(3,'Debug mode only',NULL,'debug');
$ht->open('p');
$ht->a('create random folders for import',array('target'=>'debug','task'=>'new_import'),'cmd debug');
$ht->close();
$ht->p('Some deletions includes cascading deletions too. The last deletions is the <i>hardest</i>','hint');
$ht->open('p');
$ht->a('delete all file infos',array('target'=>'debug','task'=>'del_files'),'cmd debug');
$ht->next();
$ht->a('delete all jobs',array('target'=>'debug','task'=>'del_jobs'),'cmd debug');
$ht->next();
$ht->a('delete all keywords',array('target'=>'debug','task'=>'del_keys'),'cmd debug');
$ht->next();
$ht->a('delete all users',array('target'=>'debug','task'=>'del_users'),'cmd debug');
$ht->close();

?>

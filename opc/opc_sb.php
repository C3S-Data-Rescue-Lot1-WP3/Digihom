<?php
  /*
   * allgemein besser machen
   * sites definiert die umgebung (fw-layout undso)
   * mehrerre source -> kann abändern (einfügen löschen etc)
   */

class opc_sb extends opc_tmpl1{
  protected $fw =NULL;
  protected $sites = array();
  protected $parts = array();
  protected $stack = array();
  protected $ht_stack = array();
  protected $ht = array();
  protected $hlev = 0;

  function __construct(&$fw){
    parent::__construct();
    $this->fw = &$fw;
  }

  function ___get($key,&$res){
    switch($key){ 
    case 'sites': case 'parts':$res = $this->$key; return 0;
    }
    return 201;
  }

  function sites_load($sites){
    $this->sites = $sites;
    if(is_object($this->fw->nav)){
      foreach($sites as $key=>$val) {
	$this->fw->nav->site_add($key,$val['parent'],$val);
      }
    }
  }

  function parts_load($parts){
    $this->parts = $parts;
  }

  function site_show($site){
    if(!isset($this->sites[$site])) return $this->fw->msg->msg_add('Load page','warn','Page not found');
    array_unshift($this->ht_stack,$this->ht);
    $this->ht = $this->fw->main;
    $site = $this->sites[$site];
    foreach(def($site,'parts',array()) as $part) $this->show_part($part);
    $this->ht = array_shift($this->ht_stack);
  }

  function parts_show(){
    if(empty($this->parts)) return;
    array_unshift($this->ht_stack,$this->ht);
    $this->ht = $this->fw->main;
    foreach($this->parts as $part)
      if(def($part,'show')==='auto') $this->show_part($part);
    $this->ht = array_shift($this->ht_stack);
  }

  function show_part($part){
    if(isset($part['alias'])) $part = array_merge($this->parts[$part['alias']],$part);
    array_unshift($this->ht_stack,$this->ht);
    if($tar = def($part,'target')) $this->ht = $this->fw->$tar;
    $mth = '_part__' . $part['type'] . ($part['type']==='int'?('_' . $part['int-key']):'');
    if(method_exists($this,$mth)){
      // embed part by a tag?
      $ak = is_object($part)?$part->attr_keys():array_keys($part);
      $tag = preg_grep('/^tag(-.*)?$/',$ak);
      if(count($tag)>0){
	$add = array();
	foreach($tag as $ck) if($ck!=='tag') $add[substr($ck,4)] = $part[$ck];
	$this->ht->open(def($part,'tag','div'),$add);
	$this->$mth($part);
	$this->ht->close();
      } else $res = $this->$mth($part); // ... or show direct
      $this->ht = array_shift($this->ht_stack);
    } else $this->fw->msg->msg_add('Build site','warn',"unkown part '$mth'");
  }

  protected function _part__text($part){
    if(isset($part['text'])) $txt = $part['text'];
    else $txt = $this->fw->text->te($part['text-key']);
    return $this->ht->add($txt);
  }

  protected function _part__int_login($part){
    $this->fw->auth->html($this->ht);
  }

  protected function _part__int_logout($part){
    $this->fw->auth->html($this->ht);
  }

  protected function _part__int_header($part){
    $this->ht->h(1,$this->fw->text->proj);
  }

  protected function _part__ext($part){
    $cls = $part['ext-key'];
    if(!isset($this->fw->extsb[$cls])){
      $this->fw->tool->req_files($this->fw->tool->pat2file($cls) . '.php');
      $this->fw->extsb[$cls] = new $cls($this->fw,$this);
    }
    $this->fw->extsb[$cls]->show($this->ht,$part);
  }

  }
?>
<?php

class opc_textprovider implements opi_textprovider {

  protected $tool = NULL;
  protected $data = array();

  protected $prefix_sep = '-';

  public $lng_order = array('en');

  /* External data (load late) ==================================================
   * only used if ext_use is TRUE
   * ext_prefix: if a text start with this characters readlate is called
   *             with the rest of the original message (exploded by ':') as argument
   */
  public $ext_use = TRUE;
  public $ext_prefix = '@:';

  /* Replacment functionality ==================================================
   * only used if repl_use is true
   * repl_dict: named array, used as dictonary
   * repl_mth: method of this class which is used
   * repl_lim: used by repl_mth
   */
  public $repl_use = TRUE;
  public $repl_dict = array();
  public $repl_mth = 'replace';
  public $repl_lim = '%';

  /* how act if an existing value wants to be overwritten
   * TRUE: overwrite 
   * FALSE: keep existing value
   */
  public $hard = FALSE;


  function __construct(&$tool,$args){
    $this->tool = &$tool;
    if(isset($args['sources'])) 
      foreach((array)$args['sources'] as $key=>$src) 
	$this->source_read($src,is_numeric($key)?NULL:$key);
  }

  // allow magic get if leading _
  function __get($key){ 
    if(substr($key,0,1)=='_') return $this->text(substr($key,1),NULL);
    if($key=='data') return $this->data;
    trigger_error('opc_textprovider read denied to: ' . $key);
  }

  function source_read($src,$prefix){
    if(!is_string($src)){
    } else if(file_exists($src)){
      if(preg_match('/^.*\.xml$/',$src)) $this->source_read_xml($src,$prefix);
    }
  }

  function source_read_xml($xmlfile,$prefix=NULL){
    $xml  = new opc_sx_text();
    $xml->lng_order = $this->lng_order;
    $xml->read($xmlfile,$this->lng_order[0]);
    return $this->data_add($xml->txts,$prefix);
  }

  function source_array($arr,$prefix=NULL){
    $this->data_add($arr,$prefix);
  }

  protected function data_add($arr,$prefix,$hard=NULL){
    if(is_null($hard)) $hard = $this->hard;
    if(is_null($prefix)){
      $this->data = $hard?array_merge($this->data,$arr):array_merge($arr,$this->data);
    } else if($hard) {
      foreach($arr as $ck=>$cv)
	$this->data[$prefix . $this->prefix_sep . $ck] = $cv;
    } else {
      foreach($arr as $ck=>$cv){
	$key = $prefix . $this->prefix_sep . $ck;
	if(!isset($this->data[$key])) $this->data[$key] = $cv;
      }
    }
    return 0;
  }

  function source_defca($var,$cls,$prefix){
    $this->data_add(defca($var,$cls,array('fct'=>array('ops_array','merge_preserve'))),$prefix);
  }



  // the default access functions
  function t($key,$def=NULL,$prefix=NULL){ 
    return $this->text($key,$def,$prefix);
  }
 
  function text($key,$def=NULL,$prefix=NULL){ 
    if(!is_null($prefix)) $key = $prefix . $this->prefix_sep . $key;
    $res = def($this->data,$key,$def);
    if($this->ext_use and substr($res,0,strlen($this->ext_prefix))==$this->ext_prefix)
      $res = $this->readlate(explode(':',substr($res,strlen($this->ext_prefix))),$key,$prefix);
    if($this->repl_use and method_exists($this,$this->repl_mth))
      $res = $this->{$this->repl_mth}($res);
    return $res;
  }


  /* ============================================================
   Load from other sources
   ============================================================ */

  protected function readlate($args,$key,$prefix){
    $mth = 'readlate__' . array_shift($args);
    if(method_exists($this,$mth)) return $this->$mth($args,$key,$prefix);
    return substr($mth,10) . ':' . implode(':',$args);
  }

  protected function readlate__file_xml($args,$key,$prefix){
    $xmlfile = array_shift($args);
    $xml  = new opc_sx_text();
    $xml->lng_order = $this->lng_order;
    $xml->read($xmlfile,$this->lng_order[0]);
    if(empty($args)){
      $res = array_shift($xml->txts);
      $this->data_add(array($key=>$res),$prefix);
      return $res;
    } else {
      $this->data_add($xml->txts,$prefix);
      return def($xm->txts,$key);
    }
  }

  /* ============================================================
   Replacements
   ============================================================ */
  protected function replace($res){
    foreach($this->repl_dict as $key=>$val)
      $res = str_replace($this->repl_lim . $key . $this->repl_lim,$val,$res);
    return $res;
  }
  
  function array_adjust(&$data,$lng='en',$sub=NULL){
    if(isset($this->subfiles[$sub])) {
      $this->read_subfile($sub,$lng);
      $pre = $sub . $this->prefix_sep;
    } else $pre = '';
    foreach($data as $key=>$val)
      $data[$key] = $this->text($pre . $key,$val);
    return 0;
  }


  }

?>
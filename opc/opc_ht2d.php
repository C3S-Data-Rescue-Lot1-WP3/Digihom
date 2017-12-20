<?php

abstract class opc_ht2d {
  protected $txp_cls = NULL;

  // the object to display (partial)
  protected $obj = NULL;
  // link to the framework (if knwon)
  protected $fw = NULL;
  // link to the framework (if knwon)
  protected $tool = NULL;

  // text table
  protected  $txp = NULL;
  static $_def_txt = array();

  protected  $logit = NULL;
  protected $logH = array('level'=>'hint','scope'=>'user');
  protected $logE = array('level'=>'error','scope'=>'user');
  protected $logS = array('level'=>'success','scope'=>'user');


  public $error_type_init = E_USER_NOTICE;

  abstract function init__object(&$obj);

  /* ================================================================================
     Construct object
     ================================================================================ */
  /* arguments where divided by type
   * objects: using init_byObject
   * arrays: each element by itself using init_byKey
   * [others]: using init_byType
   */

  function __construct(){
    foreach(func_get_args() as $ar){
      if(is_object($ar)) 
	$this->init_byObject($ar);
      else if(is_array($ar))
	foreach($ar as $ck=>$cv)
	  $this->init_byKey($ck,$cv);
      else
	$this->init_byType($ar);
    }
    $this->txp->source_defca('_def_txt',$this,$this->txp_cls);
  }

  /* calls init__KEY */
  function init_byKey($key,$val){
    $mth = 'init__' . str_replace('-','_',$key);
    if(method_exists($this,$mth)) return $this->$mth($val);

    $msg = 'Error constructing ' . get_class($this)
      . ' Unkown key:: ' . $key;
    trigger_error($msg,$this->error_type_init);
    return NULL;
  }

  function init_byObject($ar){
    if($ar instanceof opc_fw)
      return $this->init__fw($ar);
    if($ar instanceof _tools_)
      return $this->init__tool($ar);
    return $this->init__object($ar);
  }

  function init_byType($ar){
    $msg = 'Error constructing ' . get_class($this)
      . ' Unkown argument type: ' . gettype($ar);
    trigger_error($msg,$this->error_type_init);
  }


  function init__fw(&$fw){
    if(!($fw instanceof opc_fw)) return 2;
    $this->fw = $fw; 
    $this->txp = &$fw->txp;
    $this->logit = &$fw->logit;
    if(!is_object($this->tool)) $this->tool = &$fw->tool;
    return 0;
  }

  function init__tool(&$tool){
    if(!($tool instanceof _tools_)) return 2;
    $this->tool = $tool;
    return 0;
  }

  /* END ================================================================================ */

  function output(&$ht,$what,$add=array()){
    $mth = 'output__' . $what;
    if(method_exists($this,$mth)) return $this->$mth($ht,$add);
    return trg_ret("unkown method for an opc_ht2d-output: '$what'",NULL); 
  }

  function css(){
    return self::css_static();
  }

  static function css_static(){
    return array();
  }

  protected function txp($key){
    return $this->txp->text($key,$key,$this->txp_cls);
  }

  function log(){
    $args = opc_logit::args_prep(func_get_args(),$this->txp_cls);
    if(is_object($this->logit)) return $this->logit->log($args);
    return deff($args,'ret','code',NULL);
  }

  }

?>
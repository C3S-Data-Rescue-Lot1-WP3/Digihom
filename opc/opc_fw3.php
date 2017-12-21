<?php

class opc_fw3 extends opc_classA{
  
  public $map_class = array('str'=>'opc_ht3s',
			    'ptr-ht'=>'opc_ht3p',
			    'ptr-head'=>'opc_ht3h',
			    );
  
  // callback function to load missing classes
  public $cb_class_loader = NULL;


  /* --------------------------------------------------
   * data structer (opc_ht3s)
   * pointer to head
   */
  public $str = NULL;

  public $head = NULL;
  public $body = NULL;
  
  public $header = NULL;
  public $left =   NULL;
  public $main =   NULL;
  public $right =  NULL;
  public $footer = NULL;

  public $data = array();

  public $xhtml   = FALSE;
  public $charset = 'UTF-8';
  public $httype  = 'strict';
  public $htvers  = 4.01;
  
  protected $map_dir = array('xhtml','charset','httype','htvers');
  
  /* processed by method prepare_obj in this oreder
   */
  public $objects = array('str',
			  'head',
			  'body',
			  'main'=>'htp',
			  );

  public $tool = NULL;

  protected $init_class = 'fw3';

  /* opi_classA ------------------------------------------------------------ */
  function initOne($key,$val){
    if($val instanceof _tools_){
      $this->tool = $val;
    } else if(is_string($key)){
      if(in_array($key,$this->map_dir))
	$this->$key = $val;
      else 
	$this->data[$key] = $val;
    } else if(is_string($val)){
      $this->data['title'] = $val;
    } else if(is_bool($val)){
      $this->xhtml = $val;
    } else if(is_array($val)){
      foreach($val as $ck=>$cv) $this->initOne($ck,$cv);
    } else return parent::initOne($key,$val);
    return 0;
  }

  

  public function prepare(){
    $this->prepare_obj();
  }

  /* ================================================================================
     Prepare Objects 
     ================================================================================ */

  /* works through $objects
   * numeric key and string value: same as key=>TRUE
   * key=>TRUE: try prep_obj__KEY() or create($key)
   */
  function prepare_obj(){
    foreach($this->objects as $key=>$val){
      if(is_numeric($key)){
	if(is_string($val)) { $key = $val; $val = TRUE;}
      }
      if($val===FALSE) continue;
      if($val===TRUE)          $mth = $key;
      else if(is_string($val)) $mth = $val;
      else if(is_array($val))  $mth = def($val,'class',def($val));
      if(is_string($mth)) $this->prep_obj_str($mth,$key,$val);
    }
  }

  function prep_obj_str($what,$key,$val){
    $mth = 'prep_obj__' . $what;
    $this->$key = method_exists($this,$mth)?$this->$mth($val):$this->create($what);
    if(is_object($this->$key)) return 0;
    return opt::r($this->$key,"Error (fw3 prepare objects) $val/$key/$mth: " . $this->$key);
  }

  function prep_obj__htp($add=array()){
    $class = $this->map_class('ptr-ht');
    return new $class($this->str,$this->tool);
  }

  function prep_obj__str($add=array()){
    $class = $this->map_class('str');
    $res = new $class();
    $res->import_fw($this);
    return $res; 
  }

  function prep_obj__head($add=array()){
    $class = $this->map_class('ptr-head');
    $tmp = new $class($this->str,$this->tool,'fw3head');
    $tmp->import_data($this->data);
    return $tmp;
  }

  function prep_obj__body($add=array()){
    $class = $this->map_class('ptr-ht');
    $tmp = new $class($this->str,$this->tool,'fw3body');
    $tmp->otag('body');
    return $tmp;
  }


  public function map_class($class){
    // short try
    if(class_exists($class)) return $class;
    // try loading class using cb_class_loader
    $cb = $this->cb_class_loader;
    if(is_string($cb) and method_exists($this,$cb))
      $this->cb($class);
    else if(is_callable($cb))
      call_user_function($cb,$class);
    // try again
    if(class_exists($class)) return $class;
    if(isset($this->map_class[$class]))
      return $this->map_class($this->map_class[$class]);
    return 20101;
  }

  public function create($class/* */){
    if(is_array($class)){
      $args = $class;
      $class = ops_array::extract($class,'class');
      $byName = TRUE;
    } else {
      $args = func_get_args();
      $class = array_shift($args);
      $byName = FALSE;
    }
    $class = $this->map_class($class);
    if(is_numeric($class)) return $class;
    $obj = new $class();
    if($obj instanceof opi_classA)
      $obj->initByArray($args,$byName);
    return $obj;
  }

  function output(){
    $this->body->aobj(new opc_ht3str($this->str,
				     'incl',
				     $this->main));
    return $this->str->output($this->head,$this->body);
  }

  }

?>
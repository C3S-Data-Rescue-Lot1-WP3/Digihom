<?php
  /** ideas
   *  class opc_sobjs which holds a whole collection of simple objects (= one table/xml-file)
   */

interface opi_sobj {
  /** test if an item exitst */
  function exists($key);

  /** removes an item */
  function remove($key);

  /** loads an item (you can use $obj->key ->data ->[attrname] */
  function load($key);

  /** saves a new item */
  function save($key,$data,$attr=array());

  /** reads only the data part and returns it, does not change the current state */
  function get($key);

  /** what it says */
  function unload();

  /** list all available items */
  function listall();

  /** when write data down to source? 0: after any change; 1: destruct; 2: manual */
  function flush_mode();

  /** manual write down the data */
  function flush();

  /** returns an array will all attributes */
  function attrs();

  /** returns an array with all atrribute names */
  function attrkeys();

  /** test if source definition is valid */
  function testsource($def);

  /** test if key is valid */
  function test_key($key);
  /** test if attribute value is valid */
  function test_attr($val,$key);
  /** test if attribute key is valid */
  function test_attrkey($key);
  /** test if data is valid */
  function test_data($val);
  }

abstract class opc_sobj implements opi_sobj{
  
  /** external identifier */
  protected $key = NULL;

  /** additional data */
  protected $attrs = array();

  /** the main data */
  protected $data = NULL;

  /** syntax */
  protected $syntax = array('data-type'=>array('opc_sobj'),'version'=>1);

  /** is loaded? */
  protected $loaded = FALSE;

  /** source */
  protected $source = NULL;

  /** source definition */
  protected $source_def = NULL;

  /** is running */
  protected $running = FALSE;

  /** flush_mode 
   * 0: write down on every change
   * 1: write down destruction
   * 2: no auto write down
   */
  protected $fm = 0;

  /** error/status object */
  public $err = NULL;

  /**   function __get
   * @access private
   * @param $key name of the asked variable
   * @return aksed value or error 103 is triggered
   */
  function __get($key){
    $tmp = NULL;
    if($this->___get($key,$tmp)) return $this->err->ok($tmp);
    return $this->err->err(103);    
  }

  /** subfunction of magic method __get to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return bool: returns TRUE if key was accepted (result is saved in $res) otherwise FALSE
   */
  protected function ___get($key,&$res){
    switch($key){
    case 'key':  case 'source_def': case 'loaded': case 'running': case 'syntax': case 'data':
      $res = $this->$key; return TRUE;
    case 'id':
      $res = $this->key2id($this->key); return TRUE;
    }
    return FALSE;
  }

  /**   function __set
   * @access private
   * @param $key name of the asked variable
   * @param mixed $value new value
   * @return aksed value or error 103 is triggered
   */
  function __set($key,$value){
    $tmp = NULL;
    $tmp = $this->___set($key,$value);
    if($tmp>0) return $this->err->errM($tmp,$key,$value);
    $this->loaded = TRUE;
    if($this->fm==0) $this->flush();
    return $this->err->ok();
  }

  /** subfunction of magic method __set to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return int err-code (0=success)
   */
  protected function ___set($key,$value){
    switch($key){
    case 'id': return 7;
    case 'key': 
      if(0!= $tmp = $this->test_key($value)) return $tmp;
      $this->key = $value;
      return 0;
    case 'data': 
      if(0!= $tmp = $this->test_data($value)) return $tmp;
      $this->data = $value;
      return 0;
    }
    if(0!= $tmp = $this->test_attrkey($key)) return $tmp;
    if(0!= $tmp = $this->test_attr($value,$key)) return $tmp;
    if(is_null($value)) unset($this->attrs[$key]);
    else $this->attrs[$key] = $value;
    return 0;
  }

  public function __construct($source=NULL){
    $this->err = new opc_status($this->_msgs());
    $this->init($source);
  }

  function __destruct(){
    if($this->running and $this->loaded and $this->fm==1) $this->flush();
  }

  public function init($source){
    $this->running = FALSE;
    if(!is_null($source)){
      $tmp = $this->testsource($source);
      if(is_numeric($tmp)) return $this->err->err($tmp);
      $this->source = $tmp;
      $this->source_def = $source;
      $this->running = TRUE;
    }
    $this->unload();
  }

  protected function _msgs(){
    return array(0=>array('ok','ok'),
		 -1=>array('nothing changed','ok'),
		 1=>array('invalid source','die'),
		 2=>array('unkown source','die'),
		 5=>array('invalid key','notice'),
		 6=>array('invalid attribute value','notice'),
		 7=>array('invalid attribute key','notice'),
		 8=>array('invalid data','notice'),
		 103=>array('access denied','int'),
		 );
  }


  function test_key($key){ 
    if(!is_string($key)) return 5;
    return preg_match('/^[_a-z][a-z0-9:_]*$/i',$key)?0:5;
  }

  function test_attrkey($key){ 
    if(!is_string($key)) return 7;
    return preg_match('/^[_a-z][a-z0-9:_]*$/i',$key)?0:7;
  }

  // allow scalar only
  function test_attr($val,$key){ 
    return is_scalar($val)?0:6; 
  }

  // allow everything
  function test_data($val){return 0;}


  function flush_mode($new=NULL){
    if(is_null($new)) return $this->fm;
    if(!is_int($new) or $new<0 or $new>3) return FALSE;
    if($this->fm==$new) return TRUE;
    $this->fm = $new;
    if($this->fm==0) $this->flush();
    return TRUE;
  }
  
  function attrs(){
    if($this->loaded) return $this->attrs;
    return NULL;
  }

  function attrkeys(){
    if($this->loaded) return array_keys($this->attrs);
    return NULL;
  }
  /** reset state of the object */
  function unload(){
    $this->key = NULL;
    $this->attrs = array();
    $this->data = NULL;
    $this->loaded = FALSE;
  }

  /** decode attribute value after read in the raw data */
  function attr_decode($txt){ return trim($txt);}
  /** decode attribute value before write */
  function attr_encode($txt){ return $txt;}

  /** decode data value after read in the raw data */
  function data_decode($txt){ return unserialize($txt);}
  /** decode data value before write */
  function data_encode($txt){ return serialize($txt);}

  /** translate between internal identifier and user key */
  abstract function id2key($id);
  /** translate between user key and internal identifier */
  abstract function key2id($key);


  function save($key,$data,$attrs=array()){
    if(!$this->running) return $this->err->err(1);
    if(0 != $tmp=$this->test_key($key)) return $this->err->err($tmp);
    if(0!= $tmp = $this->test_data($data)) return $tmp;
    foreach($attrs as $ck=>$cv){
      if(0!= $tmp = $this->test_attrkey($ck)) return $tmp;
      if(0!= $tmp = $this->test_attr($cv,$ck)) return $tmp;
    }
    return $this->_save($this->key2id($key),$key,$data,$attrs);
  }

  // the real write function!
  abstract protected function _save($id,$key,$data,$attrs);

  function flush(){
    return $this->_save($this->key2id($this->key),$this->key,$this->data,$this->attrs);
  }


}
?>
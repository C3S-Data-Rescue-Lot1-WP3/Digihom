<?php

interface opi_logit extends countable{

  /** Constructor (for details see {@link init()}) */
  public function __construct();

  /** test if the bag is valid and ready*/
  public function testsink($sink);

  /** the main function */
  public function log();

  /** list logs */
  public function getn($typ=NULL);
  }

class opc_logit implements opi_logit{

  protected $sink = NULL;
  protected $sink_def = NULL;

  protected $fw = NULL;
  public $txp = NULL;

  protected $data = array();

  function __construct(){}			  

  function count(){
    return count($this->data);
  }

  /**   function __get
   * @access private
   * @param $key name of the asked variable
   * @return aksed value or error 103 is triggered
   */
  function __get($key){
    $tmp = NULL;
    if($this->___get($key,$tmp)) return $tmp;
    return 103;    
  }

  /** subfunction of magic method __get to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return bool: returns TRUE if key was accepted (result is saved in $res) otherwise FALSE
   */
  protected function ___get($key,&$res){
    switch($key){
    case 'sink_def':
      $res = $this->$key; return TRUE;
    }
    return FALSE;
  }

  protected function _msgs(){
    return array(0=>array('ok','ok'),
		 1=>array('invalid sink','die'),
		 2=>array('unkown sink','die'),
		 10=>array('no read access','die'),
		 11=>array('no write access','notice'),
		 );
  }

  public function init($sink=NULL){
    if(!is_null($sink)){
      $tmp = $this->testsink($sink);
      if(is_numeric($tmp)) return $this->err->err($tmp);
      $this->sink = $tmp;
      $this->sink_def = $sink;
    }
  }

  function init__fw(&$fw){
    $this->fw = $fw;
    $this->txp = $fw->txp;
  }

  public function test_key($key){
    if(trim($key)!=$key) return FALSE;
    if(strlen($key)>128) return FALSE;
    if(!preg_match('/^[_\w][\w.-_@: ]*$/',$key)) return FALSE;
    return TRUE;
  }
  

  public function testsink($sink){
    return $this;
  }


  public function log(){
    $args = array();
    foreach(func_get_args() as $ca){
      if(is_array($ca)) $args = array_merge($args,$ca); else $args[] = $ca;
    }
    $this->data[] = $this->log_make($args);
    return deff($args,'ret','code',NULL);
  }

  protected function log_make($args){
    $res = array('time'=>time());
    foreach($args as $key=>$arg){
      if(is_numeric($key)){
	qq($arg,$key);
      } else {
	$res[$key] = $arg;
      }
    }
    if(isset($res['code']) and !isset($res['msg'])) 
      $res['msg'] = $this->msg_get($res['code'],def($res,'obj'));
    if(isset($res['msg'])) $this->msg_make($res);
    return $res;
  }

  public function getn($filter= array(),$nullok=TRUE){
    if(empty($filter)) return $this->data;
    $res = $this->data;
    foreach($filter as $key=>$val){
      if(empty($res)) return $res;
      $keys = array_keys($res);
      if(is_string($val)) $val = explode(' ',$val);
      foreach($keys as $lkey){
	if(isset($res[$lkey][$key])){
	  if(!in_array($res[$lkey][$key],$val)) unset($res[$lkey]);
	} else if(!$nullok) unset($res[$lkey]);
      }
    }
    return $res;
  }


  protected function msg_get($code,$obj){
    if(is_object($this->txp)) 
      return $this->txp->text($code,$code,$obj);
  }

  protected function msg_make(&$log){  
    if(!isset($log['msg']) or !isset($log['parts'])) return;
    foreach($log['parts'] as $key=>$val)
      $log['msg'] = str_replace('$' . $key . '$',$val,$log['msg']);
  }

  static function args_prep($ar,$obj=NULL){
    $args = array('obj'=>$obj);
    $keys = array('code','ret','msg');
    foreach($ar as $ca){
      if(is_array($ca)) $args = array_merge($args,$ca); 
      else $args[array_shift($keys)] = $ca;
    }
    return $args;

  }
}

?>
<?php
/*
 problems
  if variables are removed, what are the reactions of method rewrite and sup-class ddealer


 open
  class opc_dsrc_glob: one for cookies and one for query?
  ini-files, bag from database, xml-files?
  insideacces using ops_narray?
  cache-mode 4?
*/

require_once('opc_err.php');

class opc_dsrc{
  var $con = NULL; //all things you need for the store
  /* cache mode
   0 -> read/write goes allways to the source
   1 -> read uses cache, write not
   2 -> read/write uses cache
  */
  var $cache = 2; // please override this in subclasses!
  var $allow_new = TRUE;

  // named arrays
  var $value = array(); // the real values
  var $type = array(); // allows to save the type, use optional
  var $changed = array(); // bool, allows to save, if the variable has changed since last read
  var $protect = array(); // bool, allows to protect overwriting

  //Error object
  var $err = NULL;

  // internal
  var $_reread = FALSE; // should be TRUE during reread/write to prevent infinit recursion
  var $_remove = array(); // values to remove in rewrite

  function opc_dsrc($data=NULL){
    $msgs = array(2=>'no store',
		  3=>'variabel not found',
		  4=>'read only',
		  5=>'unkonwn type');
    $this->err = new opc_err($msgs);
    if(is_array($data)) $this->set_arr($data);
  }

  function reset(){
    $this->value = array();
    $this->type = array(); // allows to save the type, use optional
    $this->changed = array(); // bool, allows to save, if the variable has changed since last read
    $this->protect = array(); // bool, allows to protect overwriting
    $this->err->set(0);
  }

  function changed($name=NULL){
    if(is_null($name)){
      foreach($this->changed as $cv) if($cv) return(TRUE);
      return(FALSE);
    } 
    if(!array_key_exists($name,$this->value)) return($this->err->ret(3));
    if(!array_key_exists($name,$this->changed)) return(FALSE);
    return($this->changed);
  }

  function reset_changed(){ 
    $this->changed = array(); return; 
  }

  function exists($name){
    return(array_key_exists($name,$this->value));
  }

  function keys(){
    return(array_keys($this->value));
  }

  function set($name,$value){
    $ar = func_get_args(); $br = $ar;
    array_splice($ar,1,1);
    if(!$this->_reread){
      if(!call_user_func_array(array($this,'_allow_write'),$ar)) return($this->err->ret(4));
      if(call_user_func_array(array($this,'exists'),$ar)){
	$oval = call_user_func_array(array($this,'_get'),$ar);
	if($oval===$value) return($this->err->ret());
      }
    }
    call_user_func_array(array(&$this,'_set'),$br);
    $this->changed[$name] = !$this->_reread;
    $this->type[$name] = $this->_type($value);
    return($this->err->ret());
  }

  // similar to set but only if the value does not already existst (useful for post-init)
  function setweak($name,$value){
    if($this->exists($name)) return(FALSE);
    return($this->set($name,$value));
  }
  

  function get($name /* ... */){
    $ar = func_get_args();
    if(!call_user_func_array(array($this,'exists'),$ar)) return($this->err->ret(3,NULL));
    return(call_user_func_array(array($this,'_get'),$ar));
  }

  function get_type($name /* ... */){
    $ar = func_get_args();
    if(!call_user_func_array(array($this,'exists'),$ar)) return($this->err->ret(3,NULL));
    if(!array_key_exists($name,$this->type)) return($this->err->ret(3,NULL));
    $this->type[$name] = call_user_func_array(array($this,'_type'),$ar);
    return($this->type[$name]);
  }

  function remove($name=NULL){
    $names = $this->_names($name);
    if(count($names)==0) return(NULL);
    $res = array();
    foreach($names as $cn){
      if($this->_allow_write($cn)){
	$res[$cn] = 0;
	unset($this->value[$cn]);
	unset($this->type[$cn]);
	unset($this->changed[$cn]);
	unset($this->protect[$cn]);
      } else $res[$cn] = 4;
    }
    if($this->cache!=2) $this->rewrite();
    return($this->err->ret());
  }

  function get_arr($name=NULL){
    $name = $this->_names($name);
    $res = array();
    foreach($name as $key) $res[$key] = $this->get($key);
    return($res);
  }

  function set_arr($arr){
    if(!is_array($arr)) return(NULL);
    $res = array();
    foreach($arr as $key=>$val) $res[$key] = $this->set($key,$val);
    return($res);
  }

  function init(){ $this->reset_changed();}
  function reread(){}
  function rewrite(){}



  function _set($name,$value){
    $this->value[$name] = $value;
    if($this->cache!=2 and !$this->_reread) $this->rewrite($name);
  }

  function _get($name){
    if($this->cache==0 and !$this->_reread) $this->reread($name);
    return($this->value[$name]); 
  }

  function _type($value){
    if(is_scalar($value)){
      if(is_bool($value)) return(1);
      if(is_int($value)) return(2);
      if(is_float($value)) return(3);
      if(is_string($value)) return(4);
      return(5);
    }
    if(is_array($value)) return(10);
    if(is_resource($value)) return('#' . get_resource_type($value));
    if(is_object($value)) return('&' . get_class($value));
    return(0);
  }

  function _type2name($typ){
    if(!is_numeric($typ)) return($typ);
    switch($typ){
    case 0: return('unknown');
    case 1: return('bbool');
    case 2: return('int');
    case 3: return('float');
    case 4: return('string');
    case 5: return('scalar');
    case 10: return('array');
    }
  }

  function _allow_write($name /* ... */){
    if(!array_key_exists($name,$this->value)) return($this->allow_new);
    if(!is_array($this->protect)) return($this->protect);
    if(!array_key_exists($name,$this->protect)) return(TRUE);
    return($this->protect[$name]);
  }
       
  /* name is an variable name, an array of them or NULL
   returns an array of only those names which exists in the storage
   NULL will return the keys
   */
  function _names($name=NULL,$ar=NULL){
    $ak = is_null($ar)?$this->keys():array_keys($ar);
    if(is_null($name)) return($ak);
    if(!is_array($name)) $name = array($name);
    return(array_intersect($ak,$name));
  }


  /* ============================================================
   functions to the external instance (databas, files, globals ...)
  ============================================================ */
  function store_test(){ // test connection and access
    if(is_null($this->store)) return($this->err->ret_set(2));
    return($this->err->ret_set(0));
  }

  function store_set($store){die('overload this method this method!');}
  function store_get(){return($this->store); }
  function store_reset(){$this->store = NULL;}
}











/* ============================================================
 the main php globals as opc_dsrc
 ============================================================ */
class opc_dsrc_glob extends opc_dsrc{
  var $which = 'get';
  var $cache = 2; // read/write uses cache
  var $sub = NULL;

  // sub allows to refer to a single element; not used in get/post
  function opc_dsrc_glob($which='get',$sub=NULL){
    $this->which = $which;
    $this->sub = $sub;
    parent::opc_dsrc();
    $this->init();
  }

  function remove($name=NULL){
    $names = $this->_names($name);
    if(count($names)==0) return(NULL);
    parent::remove($names);
    if($this->cache<2) $this->_glob_unset($names);
    else $this->_remove = array_merge($this->_remove,$names);
    return($this->err->ret());
  }

  function _glob_get(){
    switch($this->which){
    case 'post': return($_POST);
    case 'get': return($_GET);
    case 'session': 
      if(is_null($this->sub)) return($_SESSION);
      else return(isset($_SESSION[$this->sub])?$_SESSION[$this->sub]:array());
    }
  }

  function _glob_set($ar){
    switch($this->which){
    case 'post': $_POST = $ar; break;
    case 'get': $_GET = $ar; break;
    case 'session': 
      if(is_null($this->sub)) $_SESSION = $ar; 
      else  $_SESSION[$this->sub] = $ar; 
      break;
    default:
      return(FALSE);
    }
    return(TRUE);
  }

  function _glob_unset($name){
    if(!is_array($name)) $name = array($name);
    if(count($name)==0) return(NULL);
    switch($this->which){
    case 'post': foreach($name as $cn) unset($_POST[$cn]); break;
    case 'get': foreach($name as $cn) unset($_GET[$cn]); break;
    case 'session': 
      if(is_null($this->sub)) foreach($name as $cn) unset($_SESSION[$cn]); 
      else foreach($name as $cn) unset($_SESSION[$this->sub][$cn]); 
      break;
    default:
      return(FALSE);
    }
    return(TRUE);
  }
    
  function init(){
    $this->reset();
    $this->reread();
    return(TRUE);
  }

  function reread($name=NULL){
    $this->_reread = TRUE;
    $ar = $this->_glob_get();
    $name = $this->_names($name,$ar);
    foreach($name as $cn) $this->set($cn,$ar[$cn]);
    $this->_reread = FALSE;
    return(TRUE);
  }

  function rewrite($name=NULL){
    $this->_reread = TRUE;
    $this->_glob_unset($this->_remove);
    $this->_remove = array();
    $name = $this->_names($name);
    foreach($name as $cn) $ar[$cn] = $this->get($cn);
    $this->_glob_set($ar);
    $this->_reread = FALSE;
    return(TRUE);
  }
  
}

?>
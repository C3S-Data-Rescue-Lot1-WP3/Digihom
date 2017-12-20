<?php

  /* Wrapper class to basic ersg-functions
   *  allows statical calls to connected objects (targets)
   *  identify different targets
   *
   * register a target using target_init(key,mode,additional)
   * afterward you call this target by its key
   *  eg: ops_cio::load($tar-key,$var-key,$default);
   *      ops_cio::save($tar-key,$var-key,$value);
   *
   *
   * Current Targets: 
   *  Array: uses an internal or an external array (eg $_GET)
   *  SESSION: uses a part of $_SESSION
   *  Object: uses an external object s and its methods
   *  File: uses a directory and its content
   *  Null: just a link to nothing!
   *
   * each method needs the following functions (~ is mode)
   * init__~ clear__~, detach__~
   * load__~, save__~, remove__~, exists__~, keys_list__~
   */
class ops_cio {
  protected static $tool = NULL;

  protected static $targets = array();
  protected static $adds = array();

  /* target managment -------------------------------------------------- */
  static function target_init($key,$mode,$add=array()){
    $mth = 'init__' . $mode;
    if(!method_exists('ops_cio',$mth)?1:0) return 1;
    if(0 < $tmp = self::$mth($key,$add)) return $tmp;
    self::$targets[$key] = $mode;
    if(!isset($add['coding'])) $add['coding'] = 'serialize';
    self::$adds[$key] = $add;
    return 0;
  }

  static function target_detach($key,$clear=FALSE){
    if(!isset(self::$targets[$key])) return -1;
    if($clear) self::target_clear($key);
    $mth = 'detach__' . self::$targets[$key];
    return self::$mth($key);
  }
  
  static function target_clear($key,$add=NULL){
    if(!isset(self::$targets[$key])) return 1;
    $mth = 'clear__' . self::$targets[$key];
    return self::$mth($key,$add);
  }

  /* main function -------------------------------------------------- */
  static function save($tar,$key,$val){
    if(!isset(self::$targets[$tar])) return 1;
    $mth = 'save__' . self::$targets[$tar];
    if(self::encode($val,self::$adds[$tar]['coding'])>0) return 2;
    return self::$mth($tar,$key,$val);
  }

  static function load($tar,$key,$def=NULL){
    if(!isset(self::$targets[$tar])) return $def;
    $mth = 'load__' . self::$targets[$tar];
    $status = 0;
    $val = self::$mth($tar,$key,$status);
    if($status>0) return $def;
    if(self::decode($val,self::$adds[$tar]['coding'])>0) return $def;
    return $val;
  }

  static function remove($tar,$key){
    if(!isset(self::$targets[$tar])) return 1;
    $mth = 'remove__' . self::$targets[$tar];
    return self::$mth($tar,$key);
  }

  static function exists($tar,$key){
    if(!isset(self::$targets[$tar])) return FALSE;
    $mth = 'exists__' . self::$targets[$tar];
    return self::$mth($tar,$key)<=0;
  }

  static function keys_list($tar){
    if(!isset(self::$targets[$tar])) return 1;
    $mth = 'keys_list__' . self::$targets[$tar];
    return self::$mth($tar);
  }


  /* aux function -------------------------------------------------- */
  static function encode(&$val,$mode){
    switch($mode){
    case 'serialize': 
      try { $val = serialize($val); } catch (Exception $ex){ return 2;}
      return 0;
    case 'none': return 0;
    }
    return 5;
  }

  static function decode(&$val,$mode){
    switch($mode){
    case 'serialize': 
      try { $val = unserialize($val); } catch (Exception $ex){ return 2;}
      return 0;
    case 'none': return 0;
    }
    return 5;
  }



  protected static function tool_get(){
    if(isset(self::$tool) and (self::$tool instanceof _tools_)) return -1;
    if(!isset($GLOBALS['_tool_'])) return 1;
    if(!($GLOBALS['_tool_'] instanceof _tools_)) return 2;
    self::$tool = &$GLOBALS['_tool_'];
    return 0;
  }

  /* NULL ============================================================ 
   * saves to nowhere
   */
  static function init__null($key,$add){ return 0;}
  static function clear__null($key){ return 0;}
  static function detach__null($key){ return 0;}
  static function exists__null($tar,$key){ return 1;}
  static function keys_list__null($tar){ return array();}
  static function save__null($tar,$key,$val){ return 0;}
  static function load__null($tar,$key,&$status){ $status = 1;}
  static function remove__null($tar,$key){ return -1;}


  /* Array ============================================================ 
   * saves to internal or external array
   * for external array add['array'] is link to it (eg data = array('array'=>&$_GET))
   */
  protected static $array = NULL;
  static function init__array($key,$add){
    if(isset($add['array']) and is_array($add['array']))
      self::$array[$key] = &$add['array'];
    else
      self::$array[$key] = array();
    return 0;
  }
  
  static function clear__array($key){
    self::$array[$key] = array();
    return 0;
  }

  static function detach__array($key){ return 0;}

  static function exists__array($tar,$key){ 
    return array_key_exists($key,self::$array[$tar])?0:1;
  }

  static function keys_list__array($tar){ 
    return array_keys(self::$array[$tar]);
  }

  static function save__array($tar,$key,$val){ 
    self::$array[$tar][$key] = $val;
    return 0;
  }

  static function load__array($tar,$key,&$status){ 
    if(array_key_exists($key,self::$array[$tar]))
      return self::$array[$tar][$key];
    $status = 1;
  }

  static function remove__array($tar,$key){ 
    if(!array_key_exists($key,self::$array[$tar])) return -1;
    unset(self::$array[$tar][$key]);
    return 0;
  }

  /* SESSION ============================================================ 
   * similar to arraybut uses $_SESSION instead of self::$array
   */
  static function init__session($key,$add){
    @session_start();
    if(!isset($_SESSION[$key])) 
      $_SESSION[$key] = array();
    else if(!is_array($_SESSION[$key]))
      $_SESSION[$key] = array();
    return 0;
  }

  static function clear__session($key){ 
    $_SESSION[$key] = array();
    return 0;
  }

  static function detach__session($key){ return 0;}

  static function exists__session($tar,$key){ 
    return array_key_exists($key,$_SESSION[$tar])?0:1;
  }

  static function keys_list__session($tar){ 
    return array_keys($_SESSION[$tar]);
  }

  static function save__session($tar,$key,$val){ 
    $_SESSION[$tar][$key] = $val;
    return 0;
  }

  static function load__session($tar,$key,&$status){ 
    if(array_key_exists($key,$_SESSION[$tar]))
      return $_SESSION[$tar][$key];
    $status = 1;
  }

  static function remove__session($tar,$key){ 
    if(!array_key_exists($key,$_SESSION[$tar])) return -1;
    unset($_SESSION[$tar][$key]);
    return 0;
  }


  /* Object ============================================================ 
   * saves to an object
   * adds if other methodnames are used
   *  add for each difference cio-name=>obj->name eg array('keys_list'=>'keys',...);
   */
  protected static $obj = NULL;
  static function init__obj($key,$add){ 
    if(!isset($add['obj']) or !is_object($add['obj'])) return 1;
    self::$obj[$key] = &$add['obj'];
    self::$adds[$key] = $add;
    $mth = def(self::$adds[$key],'init','init');
    if(empty($mth) or !method_exists(self::$obj[$key],$mth)) return -1;
    return self::$obj[$key]->$mth();
    return 0;
  }
  
  static function clear__obj($key){
    $mth = def(self::$adds[$key],'clear','clear');
    if(empty($mth) or !method_exists(self::$obj[$key],$mth)) return -1;
    return self::$obj[$key]->$mth();
  }

  static function detach__obj($key){
    $mth = def(self::$adds[$key],'detach','detach');
    if(empty($mth) or !method_exists(self::$obj[$key],$mth)) return -1;
    return self::$obj[$key]->$mth();
  }

  static function exists__obj($tar,$key){ 
    $mth = def(self::$adds[$tar],'exists','exists');
    if(empty($mth) or !method_exists(self::$obj[$tar],$mth)) return -1;
    $tmp = self::$obj[$tar]->$mth($key);
    return is_bool($tmp)?($tmp?0:1):$tmp;
  }
  
  static function keys_list__obj($tar){ 
    $mth = def(self::$adds[$tar],'keys_list','keys_list');
    if(empty($mth) or !method_exists(self::$obj[$tar],$mth)) return -1;
    return self::$obj[$tar]->$mth();
  }

  static function save__obj($tar,$key,$val){ 
    $mth = def(self::$adds[$tar],'save','save');
    if(empty($mth) or (!method_exists(self::$obj[$tar],$mth))) return 1;
    $tmp = self::$obj[$tar]->$mth($key,$val);
    if(is_null($tmp)) return 0;
    if(is_bool($tmp)) return $tmp?0:1;
    return $tmp;
  }

  static function load__obj($tar,$key,&$status){ 
    $mth = def(self::$adds[$tar],'load','load');
    if(empty($mth) or !method_exists(self::$obj[$tar],$mth)) return -1;
    return self::$obj[$tar]->$mth($key,$status);
  }

  static function remove__obj($tar,$key){ 
    $mth = def(self::$adds[$tar],'remove','remove');
    if(empty($mth) or !method_exists(self::$obj[$tar],$mth)) return -1;
    return self::$obj[$tar]->$mth($key);
  }



   

  /* file ======================================================================
   * add-arguments
   *  dir: directory to save, if not given tries tool->dir('cache','abs')
   *  fn-mode: mode to create the filename
   *   md5: using md5 of key (keys_list not possible!)
   *   asc: key is used, where some character are repalced by _ (see file_pat)
   *  time: if given files older than this (in minutes are removed)
   */
  protected static $file = array();
  protected static $file_pat = '{[^- _0-9a-zA-ZàèéöäüÖÄÜÉÀÈ]}';

  static function init__file($key,$add){
    if(isset($add['dir'])){
      $dir = $add['dir'];
    } else if(self::tool_get()<=0){
      $dir = self::$tool->dir('cache','abs');
    } else return 2;
    if(substr($dir,-1)!='/') $dir .= '/';
    if(!is_writeable($dir)) return 3;
    $pre = 'cio_' . $key . '_';
    self::$file[$key] = array('prefix'=>$pre,
			      'dir'=>$dir,
			      'fn-mode'=>def($add,'fn-mode','md5'));
    $tim = def($add,'time',0);
    if($tim!=0){
      $n = strlen($pre);
      foreach(scandir($dir) as $ck){
	if(substr($ck,0,$n)==$pre and (time()-filemtime($dir . $ck))/60>$tim)
	  unlink($dir . $ck);
      }
    }
    return 0;
  }

  static function clear__file($tar){
    $pre = self::$file[$tar]['prefix'];
    $n = strlen($pre);
    $res = array();
    foreach(scandir(self::$file[$tar]['dir']) as $ck){
      if(substr($ck,0,$n)==$pre) unlink(self::$file[$tar]['dir'] . $ck);
    }
    return 0;
  }

  static function detach__file($key){ return 0;}

  static function exists__file($tar,$key){
    $fn = self::file_fn($tar,$key);
    if($fn===FALSE)       $status = 4;
    return file_exists($fn)?0:5;
  }

  static function keys_list__file($tar){
    if(self::$file[$tar]['fn-mode']=='md5') return FALSE;
    $pre = self::$file[$tar]['prefix'];
    $n = strlen($pre);
    $res = array();
    foreach(scandir(self::$file[$tar]['dir']) as $ck)
      if(substr($ck,0,$n)==$pre) $res[] = substr($ck,$n);
    return $res;
  }

  static function save__file($tar,$key,$val){
    $fn = self::file_fn($tar,$key);
    if($fn===FALSE) return 4;
    return file_put_contents($fn,$val)>0?0:3;
  }

  static function load__file($tar,$key,&$status){
    $fn = self::file_fn($tar,$key);
    if($fn===FALSE)            $status = 4;
    else if(!file_exists($fn)) $status = 5;
    else if(!filesize($fn))    $status = 6;
    else return file_get_contents($fn);
  }

  static function remove__file($tar,$key){
    $fn = self::file_fn($tar,$key);
    if($fn===FALSE)            $status = 4;
    else if(!file_exists($fn)) $status = -1;
    else return unlink($fn)?0:5;
  }

  protected static function file_fn($tar,$key){
    switch(self::$file[$tar]['fn-mode']){
    case 'md5': $fn = self::$file[$tar]['prefix'] . md5($key);
      break;
    case 'asc':
      $fn = preg_replace(self::$file_pat,'_',self::$file[$tar]['prefix'] . $key);
      break;
    default:
      return FALSE;
    }
    return self::$file[$tar]['dir'] . $fn;
  }


}
?>
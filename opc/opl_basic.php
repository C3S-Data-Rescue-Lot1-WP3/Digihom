<?php

/** Some basic function which are missing IHMO */


  // set default timezone depending on phpversion
if((float)preg_replace('/^(\d+\.\d+).*$/','$1',phpversion())>=5.2)
  date_default_timezone_set('Europe/Zurich');

/** retunrs the first non null argument (similiar to coalesce in postgresql) */
function nz(/* ... */){
  $na = func_num_args();
  for($ii=0;$ii<$na;$ii++){
    $cv = func_get_arg($ii);
    if(!is_null($cv)) return($cv);
  }
}


/* ================================================================================
 The def-family (functions about default elements for arrays and objects)
 ================================================================================ */

/** def: returns a array or object element or a default
 * @param mixed $arr: the array / object to look into
 * @param int|str $key: the key of the element (numeric for arrays only)
 * @param: mixed $def: default value if $key is missing in $arr
 * @param: bool $retarr: if $arr is neither an array nor an object return $arr (TRUE) or $def (FALSE);
 * @return: an element from $arr, the default or $arr itself
 */
function def($arr,$key=0,$def=NULL,$retarr=FALSE){
  if(is_null($key)) return $retarr?$arr:$def; 
  if(!is_string($key) and !is_int($key)) qk();
  if(is_array($arr))  return array_key_exists($key,$arr)?$arr[$key]:$def;
  if($arr instanceof ArrayAccess) return isset($arr[$key])?$arr[$key]:$def;
  if(is_object($arr)) return property_exists($arr,$key)?$arr->$key:$def;
  return $retarr?$arr:$def; 
}


/** similar to {@link def} but ignores NULL-values */
function defnz($arr,$key=0,$def=NULL,$retarr=FALSE){
  if(is_array($arr))  return isset($arr[$key])?$arr[$key]:$def;
  if(is_object($arr)) return isset($arr->$key)?$arr->$key:$def;
  return $retarr?$arr:$def; 
}


/** def: extract an array element and returns this (or a default)
 *
 * if $key exists in $arr it will be removed (side effect)
 * @param $arr: the array to look into (side effects!)
 * @param int|str $key: the key of the element
 * @param: mixed $def: default value if $key is missing in $arr
 * @param: bool $retarr: if $arr is not an array: return $arr (TRUE) or $def (FALSE);
 * @return: an element from $arr, $def or $arr itself
 */
function defex(&$arr,$key=0,$def=NULL,$retarr=FALSE){
  if(!is_array($arr))               return($retarr?$arr:$def);
  if(!array_key_exists($key,$arr))  return($def);
  $res = $arr[$key];
  unset($arr[$key]);
  return($res);
}


/** returns the first existing element (from a given key-list) from an array/object or a default
 *
 * argument 2 to n-1 are possible $key. The value of the first key that exists in $arr (argument 1)
 * is returned. If none is set the last argument is returned (default)
 * @param mixed $arr: the array / object to look into
 * @param int|str $key (multiple): the key of the element (numeric for arrays only)
 * @param: mixed $def (last argument): default value if $key is missing in $arr
 * @return: an element from $arr or the default
 */
function deff($arr,/* ... */$def=NULL){
  $na = func_num_args();
  if(is_array($arr) or ($arr instanceof ArrayAccess)){
    for($ii=1;$ii<$na-1;$ii++){
      $ca = func_get_arg($ii);
      if(array_key_exists($ca,$arr)) return($arr[$ca]);
    }
  } else if(is_object($arr)){
    for($ii=1;$ii<$na-1;$ii++){
      $ca = func_get_arg($ii);
      if(property_exists($ca,$arr)) return($arr->$ca);
    }
  }
  return(func_get_arg($na-1));
}

/** similar to {@link deff} but igonres NULL value */
function deffnz($arr,/* ... */$def=NULL){
  $na = func_num_args();
  if(is_array($arr)){
    for($ii=1;$ii<$na-1;$ii++){
      $ca = func_get_arg($ii);
      if(isset($arr[$ca])) return($arr[$ca]);
    }
  } else if(is_object($arr)){
    for($ii=1;$ii<$na-1;$ii++){
      $ca = func_get_arg($ii);
      if(isset($arr->$ca)) return($arr->$ca);
    }
  }
  return(func_get_arg($na-1));
}

/* get a element from the first array/object where key is set
 * 
 * similar to {@link deff} but test different array/objects instead of keys
 * @param int|str $key: key to test
 * @param array|object $arr (multiple): array or object where $key may defined
 * @param mixed $def (last argument): default if $key was found in non of the arrays/objects
 * @return mixed
 */
function defm($key,/* ... */$def=NULL){
  $na = func_num_args();
  for($ii=1;$ii<$na-1;$ii++){
    $ca = func_get_arg($ii);
    if(is_array($ca)  and array_key_exists($key,$ca)) return($ca[$key]);
    if(is_object($ca) and property_exists( $arr,$ca)) return($ca->$key);
  }
  return(func_get_arg($na-1));
}

/* similar to {@link defm} but ignores NULL-values */
function defmnz($key,/* ... */$def=NULL){
  $na = func_num_args();
  for($ii=1;$ii<$na-1;$ii++){
    $ca = func_get_arg($ii);
    if(is_array($ca)  and isset($ca[$key])) return($ca[$key]);
    if(is_object($ca) and isset($ca->$key)) return($ca->$key);
  }
  return(func_get_arg($na-1));
}

/* similar to def where array is nested and key a list of keys or an array of it */
function defn($arr,/* key, key */$def=NULL){
  $na = func_num_args();
  if($na<3) return $na==0?NULL:func_get_arg($na-1);
  $ar = func_get_args();
  $arr = array_shift($ar);
  $def = array_pop($ar);
  $keys = is_array($ar[0])?$ar[0]:$ar;
  while(count($keys)){
    if(!is_array($arr)) return $def;
    if(!isset($keys[0])) qk();
    if(!array_key_exists($keys[0],$arr)) return $def;
    $arr = $arr[array_shift($keys)];
  }
  return $arr;
}


/** default collects through an inherit structure array and 'merges' them */
function defca($name,$cls,$add=array()){
  $cname = is_object($cls)?get_class($cls):$cls;
  $chain = array(def($add,'postdef',array()));
  while($cname){
    array_unshift($chain,def(get_class_vars($cname),$name,array()));
    $cname = get_parent_class($cname);
  }
  array_unshift($chain,def($add,'predef',array()));
  return call_user_func_array(def($add,'fct','array_merge'),$chain);
}


/* END def-family ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */



/* ================================================================================
 The array related
 ================================================================================ */


/** extracts from a nested array a 'column' (does not test if it exists) */
function arr_subele($arr,$key){
  $res = array();
  foreach($arr as $ck=>$cv) $res[$ck] = $cv[$key];
  return $res;
}

/** extracts from a nested array a 'column', including tetsing and default value */
function arr_subele_def($arr,$key,$def=NULL){
  $res = array();
  foreach($arr as $ck=>$cv) 
    $res[$ck] = isset($cv[$key])?$cv[$key]:$def;
  return $res;
}






/** ensure that $arr is an array
 * @param mixed $arr if an array it will be returned as it is otherwise an array will embed it
 * @param int|str $key (default: 0): default key used if $arr is not already an array
 * @return array
 */
function asarray($arr,$key=0){
  return(is_array($arr)?$arr:array($key=>$arr));
}

/** makes a profile of an array
 * returns allways -1 for a non array, and 0 for an empty array
 * 'key-kind: 1: only int-keys, 2: only str-keys, 3: mixed
 */
function array_profile($arr,$what='key-kind'){
  if(!is_array($arr)) return -1;
  if(count($arr)==0) return 0;
  switch($what){
    case 'keys-kind':
      $arr = array_map('is_int',array_keys($arr));
      return (max($arr)==0)?2:(min($arr)==1?1:3);
      break;
  }
  
}

/** array (self) combine 
 * newskey=NULL: simply combines an array with itself leading to the same keys as values
 * use the values of newskey as new keys for $arr (including reodering)
 */
function ac($arr,$newkeys=NULL){
  if(!is_array($arr)) qk();
  if(is_null($newkeys)) return array_combine($arr,$arr);
  $res = array();
  foreach($newkeys as $key=>$val) $res[$val] = def($arr,$key);
  return $res;
}

/** returns the nth key of an array (or NULL/$def) */
function keyn($arr,$n=0,$def=NULL){
  if(!is_array($arr)) return $def;
  $tmp = array_keys($arr);
  return ($n<0 or $n>=count($tmp))?$def:$tmp[$n];
}


function array_merge_numkeys(/* */){
  $res = array();
  foreach(func_get_args() as $ca) if(is_array($ca)) foreach($ca as $key=>$val) $res[$key] = $val;
  return $res;
}

// like explode with limit, but result will have exavt n elements
function explode_n($sep,$str,$n=2,$def=NULL){
  $res = explode($sep,$str,$n);
  for($i=count($res);$i<$n;$i++) $res[] = $def;
  return $res;
  
}

/* END array related ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */


/** triggers an error
 * @param int $lev: number of levels to go upward in the debug_backtrace structer to get the file/class information
 * @param string $msg: Error message
 * @param int $type: Error type. see {@link tirgger_error}, default: E_USER_NOTICE
 * @param mixed $ret: return value (default: 0)
 * @return: $ret
 */

// deprecated, switch to ops_trg
function trg_err($lev,$msg,$type=E_USER_NOTICE,$ret=NULL){
  $bt = debug_backtrace();
  $bt = def($bt,$lev+1,NULL);
  if(is_null($bt)){
    trigger_error($msg,$type);
  } else if(isset($bt['class'])){
    $imsg = " #$bt[class]$bt[type]$bt[function]";
    if(isset($bt['file'])) $imsg .= " in $bt[file]@$bt[line]";
    trigger_error($msg . $imsg,$type);
  } else {
    $imsg = " #$bt[function] in $bt[file]@$bt[line]";
    trigger_error($msg . $imsg,$type);
  }
  return($ret);
}



// deprecated, switch to ops_trg
function trg_ret(/* */$ret){
  $ar = func_get_args();
  $ret = array_pop($ar);

  $mode = isset($GLOBALS['_tool_'])?$GLOBALS['_tool_']->mode:$mode = 'operational';
  
  $msg = 'Unkonw error';
  $add = array();
  $typ = E_USER_NOTICE;
  $sbl = ($mode=='test' or $mode=='devel');
  foreach($ar as $cv){
    if(is_string($cv))     $msg = $cv;
    else if(is_array($cv)) $add = $cv;
    else if(is_int($cv))   $typ = $cv;
    else if(is_bool($cv))  $sbl = $cv;
  }
  if($sbl){
    $btl = opt::trg_line($add);
    if($mode=='devel') $msg .= ' | ' . $btl;
    else $msg = "<span title='$btl'>$msg</span>";
  }
  if(strpos($msg,'%__CLASS__%')!==FALSE)
    $msg = opc_dbt::class_get($mgs);

  trigger_error($msg,$typ);
  return $ret;
}

function err($code=NULL){
  static $last_code;
  if(func_num_args()==0) return $last_code;
  $last_code = $code;
  return (is_int($code) and $code<=0);
}

?>
<?php
  /* filter used everywherer (getp)?
   * array with prefix -> all values with keys not starting with prefix stay untouched (-> uses a virtual subarray)
   *
   */

  /*
   * Hints: if multiple piles can be definied: by default the first wins
   *  that means the value of the first pile where the keys is defined,
   *  defines the final result
   */


  /* ================================================================================
     Interface
     ================================================================================ */
interface opi_argspile{
  function exists($key); // NULL means TRUE
  function is_set($key);  // NULL means FALSE
  function is_used($key); // NULL means (see null_ok)
  function remove($key);
  function get($key,$def=NULL);
  function set($key,$val);
  function add($key,$val);
  function getn($keys=NULL,$defs=array());
  function getp($pat,$defs=array());
  function setn($kv);
  function addn($kv);
  function keys();
  function keysp($pat);
  function clear();
  }


/* ================================================================================
   Generic base class (implements filter and interfaces)
   ================================================================================ */
abstract class opc_argspile_generic implements opi_argspile, countable, ArrayAccess, Iterator {

  /* standard Filter
   *  string: '-' (no filter) n (filter NULL) e (filter NULL and '')
   *  callback function
   */
  public $__filter = '-';

  /* pointer for Iterator */
  public $__pos = 0;

  function __construct(){
    $this->__pos=0;
  }

  function id(){return $this->__id;}

  /* Magic functions --------------------------------------------------*/
  function __get($key)         { return $this->get($key);}
  function __set($key,$val)    { return $this->set($key,$val);}
  /* ArrayAccess ------------------------------------------------------*/
  function offsetSet($key,$val){ return $this->set($key,$val,TRUE);}
  function offsetGet($key)     { return $this->get($key,NULL);}
  function offsetExists($key)  { return $this->is_used($key);}
  function offsetUnset($key)   { return $this->unset($key);}
  /* Iterator ---------------------------------------------------------*/
  function rewind()  {$this->__pos = 0;}
  function current() { $ck = $this->keys(); return $this->get($ck[$this->__pos]);}
  function key()     { $ck = $this->keys(); return $ck[$this->__pos];}
  function next()    { ++$this->__pos;}
  function valid()   { $ck = $this->keys(); return $this->is_used($ck[$this->__pos]);}


  /* applies filter
   * scalar: returns boolean (if it would pass filter)
   * array: returns filtered array
   * object with get_all or getn: as array
   * [other] return $data
   */
  function filter($data,$filter=NULL){
    $fil = is_null($filter)?$this->__filter:$filter;
    if(is_array($data)){
      if($fil==='-') return $data;
      if($fil==='n') return array_filter($data,create_function('$x','return !is_null($x);'));
      if($fil==='e') return array_filter($data,create_function('$x','return !(is_null($x) or $x==="");'));
      if(is_callable($fil)) return array_filter($data,$fil);
      return $data;
    } else if(is_scalar($data)){
      if($fil==='-') return TRUE;
      if($fil==='n') return !is_null($data);
      if($fil==='e') return !(is_null($data) or $data==='');
      if(is_callable($fil)) return call_user_func($fil,$data);
      return TRUE;
    } else if(is_object($data)){
      if(method_exists($data,'get_all')) $dat = $data->get_all();
      else if(method_exists($data,'getn')) $dat = $data->getn(NULL,array());
      else return $data;
      if($fil==='-') return $dat;
      if($fil==='n') return array_filter($dat);
      if($fil==='e') return array_filter($dat,create_function('$x','return !(is_null($x) or $x==="");'));
      if(is_callable($fil)) return array_filter($dat,$fil);
      return $dat;
    } else return $data;
  }
}

/* ================================================================================
   Array version of the pile
   ================================================================================ */
class opc_argspile_array extends opc_argspile_generic{
  protected $__val = array();
  protected $__id = NULL;

  /* constructer with an array to point at */
  function __construct(&$arr,$id=NULL){
    parent::__construct(); 
    $this->__id = $id;
    if(is_array($arr)) $this->__val = &$arr;
    else trigger_error(E_USER_WARN,'only array allowed to init args-pile');
  }

  /* count values (only those who pass the filter */
  function count(){return count($this->filter($this->__val));}

  /* key exitst (all values) */
  function exists($key){ return array_key_exists($key,$this->__val);}

  /* pendant of isset (ignores NULL-values */
  function is_set($key){ return isset($this->__val[$key]);}

  /* would the value pass the filterpendant of isset (ignores NULL-values */
  function is_used($key){ 
    return $this->filter(isset($this->__val[$key])?$this->__val[$key]:NULL);
  }

  /* remove one or moe keys */
  function remove($key){ 
    foreach((array)$key as $ck) unset($this->__val[$ck]);
  }

  /* clear all values */
  function clear(){ $this->__val = array();}

  /* set key, but only value is not filtered */
  function set($key,$val){ 
    if($this->filter($val)){
      $this->__val[$key] = $val; 
      return TRUE;
    } else return FALSE;
  }

  /* get including default (if value is filtered) */
  function get($key,$def=NULL){
    $tmp = isset($this->__val[$key])?$this->__val[$key]:NULL;
    return $this->filter($tmp)?$tmp:$def;
  }

  /* get without filter and anything, useful if tested before */
  function _get($key){ return $this->__val[$key]; }

  /* extracts one or more values unsig one or more keys*/
  function extract($key,$def=NULL){
    if(is_array($key)){
      $res = array();
      foreach($key as $ck) $res[$ck] = $this->extract($key,def($def,$key,NULL));
      return $res;
    } else {
      $tmp = isset($this->__val[$key])?$this->__val[$key]:NULL;
      if($this->filter($tmp)){
	$this->unset($key);
	return $tmp;
      } else return $def;
    }
  }

  /* extracts one or more values using a pattern */
  function extractp($pat,$def=NULL){
    $res = $this->getp($pat);
    $this->remove(array_keys($res));
    return array_merge($ddef,$res);
  }

  /* sets key only if not yet set (regards filter) */
  function add($key,$val){ 
    $tmp = isset($this->__val[$key])?$this->__val[$key]:NULL;
    if($this->filter($tmp)) return FALSE;
    $this->__val[$key] = $val;
    return TRUE;
  }
  
  /* returns array-keys (uses filter) */
  function keys(){return array_keys($this->filter($this->__val));}

  /* sets multiple values */
  function setn($kv){$this->__val = array_merge($this->__val,$this->filter($kv));}

  /* adds multiple values */
  function addn($kv){$this->__val = array_merge($this->filter($kv),$this->__val);}

  /* get multiple values */
  function getn($keys=NULL,$defs=array()){
    if(is_null($keys)) return array_merge($defs,$this->filter($this->__val));
    $res = array();
    foreach((array)$keys as $ck) if($this->is_used($ck)) $defs[$ck] = $this->__val[$ck];
    return $defs;
  }

  /* get by pattern for keys */
  function getp($pat,$def=array()){ 
    return array_merge($def,$this->getn(preg_grep($pat,$this->keys()))); 
  }

  /* returns all keys which mathc the pattern */
  function keysp($pat,$def=array()){ 
    return preg_grep($pat,$this->keys()); 
  }
}


/* ================================================================================
   Main class which handles the other piles
   ================================================================================ */
class opc_args extends opc_argspile_array{
  /* key used by sub-SESSION */
  protected $key;
  /* array of the single piles */
  protected $piles = array();

  // which piles are clear at the beginning of deconstruction
  protected $sink = array();
  // which sinks are cleared on the start of destrcution (before saving)
  public $autoclear = array();

  /* key is used by s-session
   * init_std -> see methid with same name
   */
  function __construct($key=NULL,$init_std=''){
    $this->key = is_null($key)?date('\TYmdHis'):$key;
    if(strlen($init_std)>0) $this->init_std($init_std);
  }

  function id(){return $this->key;}

  function init_std($keylist){
    if(is_string($keylist)) $keylist = str_split($keylist,1);
    foreach($keylist as $key){
      switch($key){
      case 'g': $this->piles[$key] = new opc_argspile_array($_GET,'g');  break;
      case 'p': $this->piles[$key] = new opc_argspile_array($_POST,'p');  break;
      case 'f': $this->piles[$key] = new opc_argspile_array($_FILES,'f');  break;
      case 'G': $this->piles[$key] = new opc_argspile_array($GLOBALS,'g');  break;
      case 'S': $this->piles[$key] = new opc_argspile_array($_SESSION,'S');  break;
      case 's': 
	if(!isset($_SESSION[$this->key])) $_SESSION[$this->key] = array();
	$this->piles[$key] = new opc_argspile_array($_SESSION[$this->key],'s');  break;
      }
    }
  }

  /* key: identifier for the pile (one letter only!)
   * arr: is an array -> use this as storage
   *      is a string -> use SESSION[$this->key][$arr] as storage
   * ret: object if success 
          integer>0 if failed: 1: key not a one-letter string; 1: key already used; 4: invalid $arr
   */   

  function init_pile($key,&$arr){
    if(!is_string($key) and strlen($key)!=1)return 1;
    if(isset($this->piles[$key]))           return 2;
      
    if(is_array($arr)){
      $this->piles[$key] = new opc_argspile_array($arr);
    } else if(is_string($arr)){
      if(!isset($_SESSION[$this->key])) $_SESSION[$this->key] = array($arr=>array());
      else if(!isset($_SESSION[$this->key][$arr])) $_SESSION[$this->key][$arr] = array();
      $this->piles[$key] = new opc_argspile_array($_SESSION[$this->key][$arr],'s');
    } else if ($arr instanceof opi_argspile){
      $this->piles[$key] = &$arr;
    } else return 3;
    return is_object($this->piles[$key])?$this->piles[$key]:4;
  }

  /* internal function to normalice the argument $piles 
   * string -> every character is a key
   * array -> every item is a key
   * FALSE -> return all pile keys
   * TRUE -> return all pile keys plus '*' for self
   * Hint: non existing keys are filtered out (excl '*');
   */
  protected function np($piles){
    if(is_string($piles)) 
      return array_intersect(str_split($piles,1),array_merge(array('*'),array_keys($this->piles)));
    if(is_array($piles)) 
      return array_intersect($piles,array_merge(array('*'),array_keys($this->piles)));
    if($piles===TRUE) 
      return array_merge(array('*'),array_keys($this->piles));
    if($piles===FALSE) 
      return array_keys($this->piles);
    return array();
  }


  /* clears one or more piles */
  function clear($piles=NULL){
    if(is_null($piles)) return $this->clear();
    foreach($this->np($piles) as $key) 
      if($key=='*') $this->clear(); else $this->piles[$key]->clear();
  }

  /* Save the values from this class to one or more piles
   * adds
   *  clear_before TF: if TRUE the target pile is cleard before saving to it
   *  clear_after TF: if TRUE this class is cleared after saving to this class
   */
  function save($piles,$add=array()){
    $cb = def($add,'clear_before',FALSE);
    $val = $this->filter($this->__val,def($add,'filter'));
    foreach($this->np($piles) as $key) {
      if($cb) $this->piles[$key]->clear();
      $this->piles[$key]->setn($val);
    }
    if(def($add,'clear_after',FALSE)) $this->clear();
  }

  /* Loads values from one or more piles to this class
   * adds see get_all plus
   *  clear_before TF: if TRUE this class is cleard before loading
   */
  function load($piles,$add=array()){
    $res = $this->piles_get_all($piles,$add);
    if(def($add,'clear_before',FALSE)) $this->__val = $res;
    else if(def($add,'reverse',FALSE)) $this->__val = array_merge($this->__val,$res);
    else                               $this->__val = array_merge($res,$this->__val);
  }

  /* Loads values from one or more piles and returns them
   * adds
   *  reverse TF:
   *   if FALSE the first pile wins (including this) that means no values will be overwritten
   *   if TRUE the last pile wins
   *  clear_before TF: if TRUE this class is cleard before loading
   *  clear_after TF: if TRUE  the target piles are cleared after loading to this
   *  filter: see there
   */
  function piles_get_all($piles='*',$add=array()){
    $res = array();
    $ca = def($add,'clear_after',FALSE);
    $rev = def($add,'reverse',FALSE);
    $fil = def($add,'filter');
    foreach($this->np($piles) as $pkey) {
      $tmp = $pkey=='*'?$this->__val:$this->piles[$pkey]->getn(NULL);
      $tmp = $this->filter($tmp,$fil);
      $res = $rev?array_merge($res,$tmp):array_merge($tmp,$res);
      if($ca) $this->piles[$pkey]->clear();
    }
    return $res;
  }

  /* combination of getn and setn with piles
   * add: remove: will unset the values (in all sources)
   * hinbt: first wins!
   */
  function move($keys,$source,$target,$add=array()){
    $values = $this->piles_getn($source,$keys);
    if(def($add,'remove')) $this->piles_remove($source,array_keys($values));
    $this->piles_setn($target,$values);
  }

  /* combination of getn and setn with piles
   * add: remove: will unset the values (in all sources)
   * hint: first wins!
   */
  function movep($pat,$source,$target,$add=array()){
    $values = $this->piles_getp($source,$pat);
    if(def($add,'remove')) $this->piles_remove($source,array_keys($values));
    $this->piles_setn($target,$values);
  }

  /* exist for piles */
  function piles_exists($piles,$key){
    foreach($this->np($piles) as $pkey) if($this->piles[$pkey]->exists($key)) return TRUE;
    return FALSE;
  }

  /* is_set for piles */
  function piles_is_set($piles,$key){
    foreach($this->np($piles) as $pkey) if($this->piles[$pkey]->is_set($key)) return TRUE;
    return FALSE;
  }

  /* is_used for piles */
  function piles_is_used($piles,$key){
    foreach($this->np($piles) as $pkey) if($this->piles[$pkey]->is_used($key)) return TRUE;
    return FALSE;
  }

  /* remove for piles */
  function piles_remove($piles,$key){
    foreach($this->np($piles) as $pkey) $this->piles[$pkey]->remove($key);
  }
  
  /* search first piles (returns key or NULL) which $mth (is_used, is_set ...) will match 
   * key is one key or an array of keys
   */
  function piles_first($piles,$key,$mth='is_used'){
    $piles = $this->np($piles);
    if(is_array($key)){
      $res = array();
      foreach($key as $ck){
	$res[$ck] = NULL;
	foreach($piles as $pkey){
	  if($this->piles[$pkey]->$mth($ck)){
	    $res[$ck] = $pkey;
	    unset($key[$ck]);
	    break;
	  }
	}
      }
      return $res;
    } else {
      foreach($piles as $pkey) if($this->piles[$pkey]->$mth($key)) return $pkey;
      return NULL;
    }
  }
  /* similar to piles_first but returns all piles which match */
  function piles_all($piles,$key,$mth='is_used'){
    $piles = $this->np($piles);
      $res = array();
    if(is_array($key)){
      foreach($key as $ck){
	$res[$ck] = array();
	foreach($piles as $pkey)
	  if($this->piles[$pkey]->$mth($ck)) $res[$ck][] = $pkey;
      }
    } else foreach($piles as $pkey) if($this->piles[$pkey]->$mth($key)) $res[] = $pkey;
    return $res;
  }

  /* get with piles defined */
  function piles_get($piles,$key,$def=NULL){
    foreach($this->np($piles) as $pkey) 
      if($this->piles[$pkey]->is_used($key)) return $this->piles[$pkey]->_get($key);
    return $def;
  }

  /* getn with piles defined, first wins! */
  function piles_getn($piles,$keys=NULL,$def=array()){
    $res = array();
    foreach($this->np($piles) as $pkey) 
      $res = array_merge($this->piles[$pkey]->getn($keys,$def),$res);
    return array_merge($def,$res);
  }

  /* getp with piles defined, first wins! */
  function piles_getp($piles,$pat,$def=array()){
    $res = array();
    foreach($this->np($piles) as $pkey) 
      if($pkey==='*') $res = array_merge($this->getp($pat),$res);
      else $res = array_merge($this->piles[$pkey]->getp($pat),$res);
    return array_merge($def,$res);
  }

  /* keysp with piles defined */
  function piles_keysp($piles,$pat){
    $res = array();
    foreach($this->np($piles) as $pkey) 
      $res = array_merge($this->piles[$pkey]->keysp($pat),$res);
    return $res;
  }

  /* set with piles defined */
  function piles_set($piles,$key,$val){
    foreach($this->np($piles) as $pkey) $this->piles[$pkey]->set($key,$val);
  }

  /* setn with piles defined */
  function piles_setn($piles,$kv){
    foreach($this->np($piles) as $pkey) $this->piles[$pkey]->setn($kv);
  }

  /* add with piles defined */
  function piles_add($piles,$key,$val){
    foreach($this->np($piles) as $pkey) $this->piles[$pkey]->add($key,$val);
  }

  /* addn with piles defined */
  function piles_addn($piles,$kv){
    foreach($this->np($piles) as $pkey) $this->piles[$pkey]->addn($kv);
  }

  /* extract for piles, removes in all piles, returns only from first! */
  function piles_extract($piles,$keys,$def=array()){
    $res = array();
    foreach($this->np($piles) as $pkey) 
      $res = array_merge($this->piles[$pkey]->extract($keys),$res);
    return array_merge($def,$res);    
  }

  /* extractp for piles, removes in all piles, returns only from first! */
  function piles_extractp($piles,$pat,$def=array()){
    $res = array();
    foreach($this->np($piles) as $pkey) 
      $res = array_merge($this->piles[$pkey]->extract($pat),$res);
    return array_merge($def,$res);    
  }

  /* add filter the asked piles */
  function set_filter($fil,$piles=TRUE){
    if(is_null($piles)) return $this->__filter = $fil;
    foreach($this->np($piles) as $key)
      if($key==='*') $this->__filter = $fil; else $this->piles[$key]->__filter = $fil;
  }

  /* returns a single pile */
  function get_pile($key){ 
    if($key==='*') return $this;
    return isset($this->piles[$key])?$this->piles[$key]:NULL; 
  }

  /* returns keys of all piles */
  function get_piles_keys(){ return array_keys($this->piles);}


  
  
}

?>
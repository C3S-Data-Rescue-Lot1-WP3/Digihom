<?php

/*
 bug
  arrayaccess set over multiple levels
  arrayaccess unset allgemein
 ideas
   count all (sub)childs
   count (sub)levels (deepnes)

   wechselseitig ob unterste Ebene eine item oder childs ebene ist

 search
 sort
 get pathes
 relative pathes (style ./asfasd)
 error-handling
 enforce unique key over all levels Yes/No

 the same for objects using methods (get/set...) or $obj->key = $vale
*/

class opc_dnarray implements arrayAccess{
  var $data = array();    // current data
  var $cld = 'childs';    // name for the child-array, should not be used as item-key
  var $cpath = array();   // current path: array(lev0, leev1 ...)
  var $move_cpath = TRUE; // basic egsr-function will change the current path?


  // state of the last 'update' function: 0: success, 1: invalid path, 2: stopped by fct
  var $result_state = 0;  

  /* the do* function may save their result inside the class (as array)
   methods 'get*' and extract will only differ between 0 and the rest (handled as 1)
   0: never save
   1: allways save
   2: only if result is neither NULL, TRUE or an empty array
   this->sep definies how te results are saved
   NULL: as nested array similar to data
   '' (empty String): using current key (ignoring upper levels)
   [string]: using impldoe with result_sep and path
   */
  var $result = array(); // current result
  var $result_mode = 2;
  var $result_sep = NULL;//'/';

  // variables used for the callbacks
  var $cb_stop = 0;
  var $cb_args = array();
  var $cb_path = array();
  var $cb_sep = '---';


  function opc_dnarray(/*  data-array | unique_ids*/){
    $ar = func_get_args();
    call_user_func_array(array($this,'reset'),$ar);
  }

  /* ================================================================================
   internal
   ================================================================================ */


  function _state($code=0,$result=TRUE){
    if($code==-1){
      $this->result_state = 0; 
      return($this->result);
    }
    $this->result_state = $code;
    return($result);
  }

  function _result_add($res,$cpath,$what){
    //save at all?
    if($this->result_mode==0) return($res);
    if($what!='extract' and substr($what,0,3)!='get'){
      if(is_null($res) or $res===TRUE or $res===0 or $res===array()) return($res);
    }

    // how to save? flat, flat inc path, nested?
    if($this->result_sep==='') 
      $this->result[$cpath[count($cpath)-1]] = $res;
    else if(is_string($this->result_sep)) 
      $this->result[implode($this->result_sep,$cpath)] = $res;
    else {
      $cres = &$this->result;
      while($ce = array_shift($cpath)){
	if(!isset($cres[$ce])) $cres[$ce] = array();
	if(empty($cpath)) break;
	if(!isset($cres[$ce][$this->cld])) $cres[$ce][$this->cld] = array();
	$cres = &$cres[$ce][$this->cld];
      }
      if(is_array($res)) 
	$cres[$ce] = (isset($cres[$ce]) and is_array($cres[$ce]))?array_merge($cres[$ce],$res):$res;
      else $cres[$ce][0]  = $res;
    }
    return($res);
  }

  function _result_init(){
    $this->result = array();
    $this->result_state = 0;
    $this->cb_stop = 0;
    $this->cb_args = array();
    $this->cb_path = array();
  }
  
  function _path($path){
    if(is_null($path)) return($this->cpath);
    if(is_scalar($path)) return(array($path));
    if(!is_array($path)) return(3);
    return($path);
  }

  function reset(/**/){
    $bools = array('maove_path'); // list of boolean-modes
    $ar = func_get_args();
    while($ca = array_shift($ar)){
      if(is_array($ca)) {
	$this->import_data($ca);
      } else if (is_bool($ca)){
	$key = array_shift($bools);
	$this->$key = $ca;
      }
    }
  }

  function import_data($data){
    $this->data = $data;
  }

/* ============================================================
 egsr...
 ============================================================*/

  /* nomralice the key structer of some functions
   mode FALSE returns an path-array
       TRUE array(path-array item-key)

   args is an array (typically the result of func_get_args)
     the exact structer depends from mode
        key/item are strings or integers  
	last may be anything
     0 - key [key [...]] [last*]
       - array(keys) [last]
     1 - item key, [key ,[...]] [last*]
       - item array(keys) [last]
     2 similar to 1 but item is an array
     3 - obj key, [key ,[...]] [last*]
       - obj array(keys) [last]
     4 - obj item key, [key ,[...]] [last*]
       - obj item array(keys) [last]
     last* only if it is neither an integer nor a string
     if (first) key is NULL it will be replaced by this->cpath
   to capture the last value use a referenced variable
   */
  function _norm_args($args,$mode=0,&$last=NULL){
    if($mode==3 or $mode==4) $obj = array_shift($args);
    if($mode!=0 and $mode!=3) $itm = array_shift($args);
    $na = count($args);
    if($na==0 or is_null($args[0])) $args[0] = $this->cpath;
    $path = array_shift($args);
    if(!is_array($path)){
      $path = array($path);
      while($ce = array_shift($args)){
	if(is_string($ce) or is_int($ce)) $path[] = $ce;
	else $last = $ce;
      }
    } else $last = array_shift($args);
    if($this->move_cpath) $this->cpath = $path;
    switch($mode){
    case 0: return($path);
    case 1: case 2: return(array($itm,$path));
    case 3: return(array($obj,$path));
    case 4: return(array($obj,$itm,$path));
    }
  }

  /*
    itm: NULL | key (incl childs) 
    mode 0: exists, 1: get (no default) 2: count 3: keys */
  function _goto($path,$mode=0,$itm=NULL){
    $data = $this->data;
    while($ck = array_shift($path)){
      if(!isset($data[$ck])) return(FALSE);
      $data = $data[$ck]; 
      if(empty($path)) break;
      $data = isset($data[$this->cld])?$data[$this->cld]:NULL;
    } 
    if($mode==0) return(is_null($itm)?TRUE:isset($data[$itm]));
    if(!is_null($itm)) {
      if(!isset($data[$itm])) return(FALSE); 
      $data = $data[$itm];
    }
    switch($mode){
    case 1: return($data);
    case 2: return(is_array($data)?count($data):NULL);
    case 3: return(is_array($data)?array_keys($data):NULL);
    }
  }

// EXISTS ------------------------------------------------------------
  function exists(/* */){
    return($this->_goto($this->_norm_args(func_get_args())));
  }

  function existsChilds(/*.*/){
    return($this->_goto($this->_norm_args(func_get_args()),0,$this->cld));
  }

  function existsItem(/*.*/){
    list($itm,$path) = $this->_norm_args(func_get_args(),1);
    return($this->_goto($path,0,$itm));
  }


// GET ------------------------------------------------------------
  function get(/*.*/){
    $default = NULL; 
    $item = $this->_goto($this->_norm_args(func_get_args(),0,$default),1);
    return($item===FALSE?$default:$item);
  }

  function getChilds(/* */){
    $default = NULL; 
    $path = $this->_norm_args(func_get_args(),0,$default);
    $res = $this->_goto($path,1);
    return((!is_array($res) or !isset($res[$this->cld]))?$default:$res[$this->cld]);
  }

  function cloneChild(/*.*/){
    $default = NULL; 
    $path = $this->_norm_args(func_get_args(),0,$default);
    $res = $this->_goto($path,1);
    $res = new opc_dnarray((!is_array($res) or !isset($res[$this->cld]))?$default:$res[$this->cld]);
    $res->cld = $this->cld;
    $res->move_cpath = $this->move_cpath;
    return $res;
  }

  function getItem(/* */){
    $default = NULL; 
    list($itm,$path) = $this->_norm_args(func_get_args(),1,$default);
    $res = $this->_goto($path,1);
    return(isset($res[$itm])?$res[$itm]:$default);
  }
    
  function getItems(/* */){
    $default = NULL; 
    list($itm,$res) = $this->_norm_args(func_get_args(),2,$default);
    $res = $this->_goto($res,1);
    foreach($itm as $ci) if(isset($res[$ci])) $default[$ci] = $res[$ci];
    return($default);
  }


// DEL ------------------------------------------------------------
  function remove(/*key subkey ...*/){
    return($this->_remove($this->_norm_args(func_get_args())));
  }

  function removeChilds(/*key subkey ...*/){
    return($this->_remove($this->_norm_args(func_get_args()),$this->cld));
  }

  function removeItem(/*key subkey ...*/){
    list($itm,$path) = $this->_norm_args(func_get_args(),1);
    return($this->_remove($path,$itm));
  }

  function removeItems(/*key subkey ...*/){
    list($itm,$path) = $this->_norm_args(func_get_args(),2);
    return($this->_remove($path,$itm));
  }

  //itm = NULL -> node; int/string -> item (even childs)
  function _remove($path,$itm=NULL){
    $data = &$this->data;
    $last = is_null($itm)?array_pop($path):NULL;
    while($ck = array_shift($path)){
      if(!isset($data[$ck])) return(FALSE);
      $data = &$data[$ck]; 
      if(empty($path)) break;
      $data = &$data[$this->cld];
    } 
    if(is_null($itm)){
      if(!isset($data[$this->cld][$last])) return(FALSE);
      unset($data[$this->cld][$last]);
    } else if(is_array($itm)){
      foreach($itm as $ci) unset($data[$ci]);
      return(TRUE);
    } else {
      if(!isset($data[$itm])) return(FALSE);
      unset($data[$itm]);
    }
    return(TRUE);
  }

// SET ------------------------------------------------------------
/* The last argument may be a boolean
   FALSE (default) set value only if structer above already exists
   TRUE create missing structer elements */
  function set(/*key subkey ...*/){
    $enforce = FALSE;
    list($obj,$path) = $this->_norm_args(func_get_args(),3,$enforce);
    return($this->_set($obj,$path,NULL,$enforce));
  }

  function setChilds(/*key subkey ...*/){
    $enforce = FALSE;
    list($obj,$path) = $this->_norm_args(func_get_args(),3,$enforce);
    return($this->_set($obj,$path,$this->cld,$enforce));
  }

  function setItem(/*key subkey ...*/){
    $enforce = FALSE;
    list($obj,$itm,$path) = $this->_norm_args(func_get_args(),4,$enforce);
    return($this->_set($obj,$path,$itm,$enforce));
  }

  function setItems(/*key subkey ...*/){
    $enforce = FALSE;
    list($obj,$path) = $this->_norm_args(func_get_args(),3,$enforce);
    return($this->_set($obj,$path,TRUE,$enforce));
  }

  //itm = NULL -> node; int/string -> item (even cld)
  function _set($obj,$path,$itm=NULL,$enforce=FALSE){
    $data = &$this->data;
    $last = is_null($itm)?array_pop($path):NULL;
    while($ck = array_shift($path)){
      if(!isset($data[$ck])){
	if($enforce!==TRUE) return(FALSE);
	$data[$ck] = array();
      }
      $data = &$data[$ck]; 
      if(empty($path)) break;
      $data = &$data[$this->cld];
    } 
    if(is_null($itm)){
      $data[$this->cld][$last] = $obj;
    } else if($itm===TRUE){
      $data = array_merge($data,$obj);
    } else $data[$itm] = $obj;
    return(TRUE);
  }


// KEYS ------------------------------------------------------------

  function itemCount(/* key subkey ... */){
    return($this->_goto($this->_norm_args(func_get_args()),2));
  }

  function childCount(/* key subkey ... */){
    return($this->_goto($this->_norm_args(func_get_args()),2,$this->cld));
  }

  function hasChilds(/* key subkey ... */){
    return($this->_goto($this->_norm_args(func_get_args()),2,$this->cld)>=1);
  }

  function itemKeys(/* key subkey ... */){
    return($this->_goto($this->_norm_args(func_get_args()),3));
  }

  function childKeys(/* key subkey ... */){
    return($this->_goto($this->_norm_args(func_get_args()),3,$this->cld));
  }

  // checks a path, return TRUE if valid or an integer (number of valid parts)
  function checkPath(/* others */){
    $path = $this->_norm_args(func_get_args());
    $data = $this->data;
    $nl = 0;
    while($ck = array_shift($path)){
      if(!isset($data[$ck])) return($nl);
      if(empty($path)) return(TRUE);
      $nl++;
      $data = $data[$ck][$this->cld];
    } 
  }

  /* INSERT Child
   key: int/string: object is a single node
        NULL: object is an named array of nodes
   at: int/string: insert obj before this one
       FALSE: insert at the beginning
       TRUE:  insert at the end
   */

  function insert($obj,$key,$at=TRUE,$path=NULL/* at path/keys */){
    $path = func_get_args();
    list($obj,$name,$at) = array_splice($path,0,3);
    $enforce = FALSE;
    $path = $this->_norm_args($path,$enforce);

    $data = &$this->data;
    while($ck = array_shift($path)){
      if(!isset($data[$ck])){
	if($enforce!==TRUE) return(FALSE);
	$data[$ck] = array();
      }
      $data = &$data[$ck]; 
      if(empty($path)) break;
      $data = &$data[$this->cld];
    } 
    if(!is_array($data[$this->cld])) $data[$this->cld] = array();
    $data = &$data[$this->cld];
    $nd = count($data);
    if(!is_null($key)) $obj = array($key=>$obj);
    if($nd==0)           $data = $obj;
    else if($at===FALSE) $data = array_merge($obj,$data);
    else if($at===TRUE)  $data = array_merge($data,$obj);
    else {
      $pos = array_search($at,array_keys($data));
      if($pos===FALSE)     $data = array_merge($data,$obj);
      else if($pos==0)     $data = array_merge($obj,$data);
      else if($pos==$nd-1) $data = array_merge($data,$obj);
      else                 $data = array_merge(array_slice($data,0,$pos),
					       $obj,
					       array_slice($data,$pos));
    }
    return(TRUE);
  }

/* ================================================================================
 ArrayAccess
 ================================================================================ */
  function offsetExists($pos)      { return $this->exists($pos);} 
  function offsetGet ($pos)        { return $this->cloneChild($pos);}
  function offsetSet ($pos, $value){ $this->setChilds($value,$pos,TRUE);  }
    
  function offsetUnset ($pos)      { $this->removeChilds($pos);}

  /* ================================================================================
   recursive functions
   ================================================================================ */
  /* reduce the whole item to a single result using two (callback-) functions
   $fct?: callback function
   $dir?: if TRUE fct accept the whole array as argument, FALSE similar to array_reduce
   $init?: init value as used in array_reduce

   *C is to reduce the list of childs, the result replaces the child-array
   *I is to reduce a single item (childs are already reduced to a single value)


   integrate tod do*???
  */

  function reduce($fctI,$fctC,
		  $dirI=FALSE,$dirC=FALSE,
		  $initI=NULL,$initC=NULL){
    $data = $this->data;
    $stack = array();
    $ak = array_keys($data);
    while(count($ak)>0 or count($stack)>0){
      if(count($ak)==0){
	$tdata = $dirC==TRUE?call_user_func($fctC,$data):array_reduce($data,$fctC,$initC);
	list($ck,$ak,$data) = array_splice($stack,0,3);
	$data[$ck][$this->cld] = $tdata;
	$data[$ck] = $dirI==TRUE?call_user_func($fctI,$data[$ck]):array_reduce($data[$ck],$fctI,$initI);
      } else {
	$ck = array_shift($ak);
	if(count($data[$ck][$this->cld])>0){
	  array_unshift($stack,$ck,$ak,$data);
	  $data = $data[$ck][$this->cld];
	  $ak = array_keys($data);
	} else { // no childs -> use function 'internal'
	  $data[$ck] = $dirI==TRUE?call_user_func($fctI,$data[$ck]):array_reduce($data[$ck],$fctI,$initI);
	}
      }
    }
    return($dirC==TRUE?call_user_func($fctC,$data):array_reduce($data,$fctC,$initC));
  }


  /* --------------------------------------------------------------------------------
   user visible function
   -------------------------------------------------------------------------------- */

  function doAt($path,$what/* */){
    $args = array_slice(func_get_args(),2);
    if(is_numeric($path = $this->_path($path))) return($this->_state($path,FALSE));
    return($this->doSingle(0,$path,$this->data,$what,$args));
  }

  function doOnAll($what/* */){
    $args = array_slice(func_get_args(),1);
    return($this->doInnerLast(0,NULL,$this->data,$what,$args));
  }

  function doOnAllRev($what/* */){
    $args = array_slice(func_get_args(),1);
    return($this->doInnerFirst(0,NULL,$this->data,$what,$args));
  }

  function doOnAllParents($what/* */){
    $args = array_slice(func_get_args(),1);
    return($this->doInnerLast(1,NULL,$this->data,$what,$args));
  }

  function doOnAllParentsRev($what/* */){
    $args = array_slice(func_get_args(),1);
    return($this->doInnerFirst(1,NULL,$this->data,$what,$args));
  }

  function doOnLeaves($what/* */){
    $args = array_slice(func_get_args(),1);
    return($this->doInnerLast(2,NULL,$this->data,$what,$args));
  }

  function doOnChilds($what/* */){
    $args = array_slice(func_get_args(),1);
    return($this->doInnerLast(4,NULL,$this->data,$what,$args));
  }

  function doOnLevels($level,$what/* */){
    $args = array_slice(func_get_args(),2);
    return($this->doInnerLast(3,is_array($level)?$level:array($level,$level),
			      $this->data,$what,$args));
  }

  function doOnPath($path,$what/* */){
    $args = array_slice(func_get_args(),2);
    if(is_numeric($path = $this->_path($path))) return($this->_state($path,FALSE));
    return($this->doLineIn(0,$path,$this->data,$what,$args));
  }    

  function doOnPathRev($path,$what/* */){
    $args = array_slice(func_get_args(),2);
    if(is_numeric($path = $this->_path($path))) return($this->_state($path,FALSE));
    return($this->doLineOut(0,$path,$this->data,$what,$args));
  }




  /* --------------------------------------------------------------------------------
   internal loop processors
   -------------------------------------------------------------------------------- */

  function doSingle($case,$path,&$data,$what,$args/* */){
    if(is_array($what)){
      while($ce = array_shift($what))
	if(!isset($data[$ce][$this->cld])) return($this->_state(1,FALSE));
	else $data = &$data[$ce][$this->cld];
      $what = array_shift($args);
    }

    $this->_result_init();
    while($ce = array_shift($path)){
      if(!isset($data[$ce])) return($this->_state(1,FALSE));
      if(empty($path)) break;
      $data = &$data[$ce][$this->cld];
    }
    return($this->_do($data,$ce,$what,$args,$path));
  }



  function doLineIn($case,$path,&$data,$what,$args/* */){
    if(is_array($what)){
      while($ce = array_shift($path)){
	if(!isset($data[$ce][$this->cld])) return($this->_state(1,FALSE));
	else $data = &$data[$ce][$this->cld];
      }
      $path = $what;
      $what = array_shift($args);
    } 

    $this->_result_init();
    $ndone = 0;
    $cpath = array();
    while($ckey = array_shift($path)){
      $cpath[] = $ckey;
      if(!isset($data[$ckey])) return($this->_state(1,$ndone));
      $res = $this->_do($data,$ckey,$what,$args,$cpath);
      if($this->cb_stop==2) return($this->_state(2,$res)); // stopped by user
      if($this->cb_stop==1) return($this->_state());// skip childs -> stop
      if(empty($path)) return($this->_state(-1));
      $ndone++;
      if(!isset($data[$ckey][$this->cld])) return($this->_state(1,$ndone)); // no childs
      $data = &$data[$ckey][$this->cld];
    }
  }



  function doLineOut($case,$path,&$data,$what,$args/* */){
    if(is_array($what)){
      if($this->checkpath(array_merge($path,$what))!==TRUE) 
	return($this->_state(1,FALSE));
      $opath = $what;
      while($ce = array_shift($path))
	if(!isset($data[$ce][$this->cld])) return($this->_state(1,FALSE));
	else $data = &$data[$ce][$this->cld];
      $path = $what;
      $what = array_shift($args);
    } else if($this->checkpath($path)!==TRUE) return($this->_state(1,FALSE));

    $this->_result_init();
    $cpath = $path;
    $stack = array();
    while($ckey = array_shift($path)){
      if(!isset($data[$ckey])) return($this->_state(1,FALSE));
      if(empty($path)) break;
      array_unshift($stack,$ckey,$data);
      $data = $data[$ckey][$this->cld];
    }
    do{
      if($this->cb_stop==0) $res = $this->_do($data,$ckey,$what,$args,$cpath);
      $cdata = $data;
      if(count($stack)==0) break;
      list($ckey,$data) = array_splice($stack,0,2);
      array_pop($cpath);
      $data[$ckey][$this->cld] = $cdata;
    } while(TRUE);
    if($this->cb_stop==2) return($this->_state(2,$res));
    return($this->_state(-1));
  }



  /* case: 0->allways; 1->parents; 2->leaves; 3-> first Level*/
  function doInnerLast($case,$add,&$data,$what,$args/* */){
    if(is_array($what)){
      while($ce = array_shift($what))
	if(!isset($data[$ce][$this->cld])) return($this->_state(1,FALSE));
	else $data = &$data[$ce][$this->cld];
      $what = array_shift($args);
    }

    $this->_result_init();
    $stack = array();
    $keys = array_keys($data);
    $clev = 0;
    $cpath = array();
    while(count($keys)>0 or count($stack)>0){
      if(count($keys)==0){ // go out
	$cdata = $data;
	list($ckey,$keys,$data,$args) = array_splice($stack,0,4);
	$data[$ckey][$this->cld] = $cdata;
	unset($cpath[$clev--]);
      } else {
	$ckey = array_shift($keys);
	$cpath[$clev] = $ckey;
	if($this->cb_stop==2) continue;
	if($case==0
	   or  $case==4
	   or ($case==1 and isset($data[$ckey][$this->cld]) and count($data[$ckey][$this->cld])>0)
	   or ($case==2 and (!isset($data[$ckey][$this->cld]) or count($data[$ckey][$this->cld])==0))
	   or ($case==3 and $clev>=$add[0])){
	  $res = $this->_do($data,$ckey,$what,$args,$cpath);
	}
	if(isset($data[$ckey][$this->cld]) and count($data[$ckey][$this->cld])>0
	   and $this->cb_stop==0
	   and  $case!=4
	   and ($case!=3 or $clev<$add[1])){ // go in
	  array_unshift($stack,$ckey,$keys,$data,$args);
	  $clev++;
	  $data = $data[$ckey][$this->cld];
	  $keys = array_keys($data);
	} 
      }
    }
    if($this->cb_stop==2) return($this->_state(2,$res));
    return($this->_state(-1));
  }



  function doInnerFirst($case,$add,&$data,$what,$args/* */){
    if(is_array($what)){
      while($ce = array_shift($what))
	if(!isset($data[$ce][$this->cld])) return($this->_state(1,FALSE));
	else $data = &$data[$ce][$this->cld];
      $what = array_shift($args);
    }

    $this->_result_init();
    $stack = array();
    $keys = array_keys($data);
    $clev = 0;
    $cpath = array();

    while(count($keys)>0 or count($stack)>0){
      $ckey = array_shift($keys);
      if(is_null($ckey)){ // go out
	$cdata = $data;
	list($ckey,$keys,$data) = array_splice($stack,0,3);
	$data[$ckey][$this->cld] = $cdata;
	unset($cpath[$clev--]);
      } else if(isset($data[$ckey][$this->cld]) and count($data[$ckey][$this->cld])>0
		and $this->cb_stop==0){ // go in
	array_unshift($stack,$ckey,$keys,$data);
	$cpath[$clev++] = $ckey;
	$data = $data[$ckey][$this->cld];
	$keys = array_keys($data);
	continue;
      } else $cpath[$clev] = $ckey;
      if($this->cb_stop==0
	 and ($case==0
	      or ($case==1 and count($data[$ckey][$this->cld])>0))){
	$res = $this->_do($data,$ckey,$what,$args,$cpath);
      }
    }
    if($this->cb_stop==2) return($this->_state(2,$res));
    return($this->_state(-1));
  }



  /* --------------------------------------------------------------------------------
   internal item processors
   -------------------------------------------------------------------------------- */
  /*
   return:
     get*: The asked elements
     exists*: TRUE/FALSE or an array of them
     has*: TRUE/FALSE
     count*: Integer
     key*: array of keys
     [other]: TRUE or an array of skipped keys (empty if all was done)
  */
  function _do(&$data,$ce,$what,&$args,$cpath){
    $this->cb_stop = 0;
    $res = TRUE;
    if(substr($what,0,3)=='get') 
      $res = $this->_do_get($data[$ce],substr($what,3),
			    count($args)>0?$args[0]:NULL,
			    count($args)>1?$args[1]:NULL);
    else if(substr($what,0,3)=='set') 
      $res = $this->_do_set($data,$ce,substr($what,3),$args);
    else if(substr($what,0,6)=='exists') 
      $res = $this->_do_exists($data[$ce],substr($what,6),$args);
    else if(substr($what,0,6)=='remove') 
      $res = $this->_do_remove($data,$ce,substr($what,6),$args);
    else if(substr($what,0,6)=='modify' or substr($what,0,7)=='extract'){
      $res = $this->_do_callback($data,$ce,$what,$args,$cpath);
    } else {
      switch($what){


      case 'replaceAllChilds':  $data[$ce][$this->cld] = $args[0]; break;
      case 'replaceAllItems':
	$args[0][$this->cld] = $data[$ce][$this->cld];
	$data[$ce] = $args[0];
	break;

      case 'hasChilds': $res = isset($data[$ce][$this->cld]); break;
      case 'countChilds': $res = isset($data[$ce][$this->cld])?count($data[$ce][$this->cld]):0; break;
      case 'countItems':
	$ak = array_keys($data[$ce]);
	$res = count($ak)-(in_array($this->cld,$ak)?1:0);
	break;

      case 'keyChilds': case 'keysChilds':  
	$res = (isset($data[$ce][$this->cld]) and is_array($data[$ce][$this->cld]))?array_keys($data[$ce][$this->cld]):FALSE;
	  break;
      case 'keyItems': case 'keysItems':
	$res = array_keys($data[$ce]); 
	if(FALSE!== $pos = array_search($this->cld,$res)) unset($res[$pos]);
	break;

      default:
	$res = 'unknown command: ' . $what;
      }
    }
    return($this->_result_add($res,$cpath,$what));
  }

  function _do_exists(&$data,$what,$args){
    if($what=='') return(TRUE);
    $res  = array();
    switch($what){
    case 'Item': return(isset($data[$args[0]]));
    case 'Items': 
      foreach($args[0] as $ck) $res[$ck] = isset($data[$ck]);
      return($res);
    case 'Child': return(isset($data[$this->cld][$args[0]]));
    case 'Childs': 
      foreach($args[0] as $ck) $res[$ck] = isset($data[$this->cld][$ck]);
      return($res);
    }
    return($this->_state($what,FALSE));
  }



  function _do_get(&$data,$what,$name,$def=NULL){
    if($what=='') return($data);
    $res = array();
    switch($what){
    case 'AllItems': 
      $res = $data;
      if(isset($res[$this->cld])) unset($res[$this->cld]);
      return($res);
    case 'Item':  case 'Items':
      if(is_null($name)) return($data[$this->def]);
      if(is_scalar($name)) return(isset($data[$name])?$data[$name]:$def);
      foreach($name as $cn) 
	if     (isset($data[$cn])) $res[$cn] = $data[$cn];
	else if(isset($def[$cn] )) $res[$cn] = $def[$cn];
	else if(is_scalar($def))   $res[$cn] = $def;
      return($res);
      
    case 'AllChilds': return(isset($data[$this->cld])?$data[$this->cld]:NULL);
    case 'Child':     return($data[$this->cld][$name]);
    case 'Childs': 
      if(!is_array($data[$this->cld])) return(array());
      foreach($name as $cc) $res[$cc] = $data[$this->cld][$cc]; 
      return($res);
    }
    return($this->_state($what,FALSE));
  }


  function _do_set(&$data,$ce,$what,$args){
    if($what==''){$data[$ce] = $args[0]; return(TRUE);}
    $res  = array();
    // fusion between Child/childs and Item/Items
    if(count($args)==2) $args[0] = array($args[0]=>$args[1]);
    // fusion between Child/Item by go downward in $data
    if(substr($what,0,5) == 'Child'){
      if(!isset($data[$ce][$this->cld])) $data[$ce][$this->cld] = array();
      $data = &$data[$ce];
      $ce = $this->cld;
      $what = substr($what,substr($what,5,1)=='s'?6:5);
    } else $what = substr($what,substr($what,4,1)=='s'?5:4);

    switch($what){
    case '': // normal set
      $data[$ce] = array_merge($data[$ce],$args[0]);
      return(TRUE);
    case 'Default': case 'Def': // only if not yet exists
      foreach($args[0] as $key=>$val) 
	if(!isset($data[$ce][$key])) $data[$ce][$key] = $val;
	else $res[] = $key;
      return($res); // only if already exists
    case 'Update':
      foreach($args[0] as $key=>$val) 
	if(isset($data[$ce][$key])) $data[$ce][$key] = $val;
	else $res[] = $key;
      return($res);
    }
    return($this->_state($what,FALSE));
  }


  
  function _do_remove(&$data,$ce,$what,$args){
    $res  = array();
    switch($what){
     case '': unset($data[$ce]); return(TRUE);
    case 'Item': 
      if(isset($data[$ce][$args[0]])) unset($data[$ce][$args[0]]); 
      else $res[] = $args[0];
      return($res);
    case 'Items': 
      foreach($args[0] as $ck) 
	if(isset($data[$ce][$ck])) unset($data[$ce][$ck]); 
	else $res[] = $ck;
      return($res);
    case 'AllItems': 
      if(isset($data[$ce][$this->cld])) $data[$ce] = array($this->cld=>$data[$ce][$this->cld]);
      else $data[$ce]  = array();
      return(TRUE);
    case 'AllChilds': unset($data[$ce][$this->cld]); return(TRUE);
    case 'Child': 
      if(isset($data[$ce][$this->cld][$args[0]])) unset($data[$ce][$this->cld][$args[0]]); 
      else $res[] = $args[0];
      return($res);
    case 'Childs': 
      foreach($args[0] as $ck) 
	if(isset($data[$ce][$this->cld][$ck])) unset($data[$ce][$this->cld][$ck]); 
	else $res[] = $ck;
      return(TRUE);
    }
    return($this->_state($what,FALSE));
  }

  function _do_callback(&$data,$ce,$what,&$args,$cpath){
    $mod = substr($what,0,6)=='modify';
    $what = substr($what,$mod?6:7);
    $this->cb_path = $cpath;
    $cargs = array($data[$ce],&$this);
    if($mod) $cargs[0] = &$data[$ce]; // allow modification
    switch($what){
    case 'PerItem': case '':
      for($ii=1;$ii < count($args);$ii++) $cargs[] = $args[$ii];
      break;
    case 'OverAll':
      for($ii=1;$ii < count($args);$ii++) $cargs[] = &$args[$ii];
      break;
    case 'PerNode':
      array_pop($cpath);
      $par = implode($cpath,$this->cb_sep);
      if(!isset($this->cb_args[$par])) $this->cb_args[$par] = array_slice($args,1);
      for($ii=1;$ii < count($args);$ii++) $cargs[] = &$this->cb_args[$par][$ii-1];
      break;
    }
    $res = call_user_func_array($args[0],$cargs);
    return($res);
  }


  /* --------------------------------------------------------------------------------
   function that can be called by a callback function to get more information about
   the (current) object.
   A callback function will get a object-pointer for this a ssecond argument
   -------------------------------------------------------------------------------- */
  /* current saved argument states (to be call by a callback function)
   path array: normal path array
        int: use cb_path and go path level up
   */
  function cb_get($pos=0,$path=0){
    if(!is_array($path)) {
      $cpath = $this->cb_path;
      while($path-- > 0) 
	if(count($cpath)==0) return(NULL);
	else array_pop($cpath);
      $path = $cpath;
    }
    $path = implode($this->cb_sep,$path);
    if(!isset($this->cb_args[$path])) return(NULL);
    $res = $this->cb_args[$path];
    if(is_numeric($pos)) return($res[$pos]);
    return($res);
  }


  function cb_set($val,$pos=0,$path=0){
    if(!is_array($path)) {
      $cpath = $this->cb_path;
      while($path-->0) array_pop($cpath);
      $path = $cpath;
    }
    $path = implode($this->cb_sep,$path);
    $this->cb_args[$path][$pos] = $val;
  }



}

?>
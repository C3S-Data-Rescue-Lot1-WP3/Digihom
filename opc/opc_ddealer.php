<?php

/* idea
     default source[-sequenze]
     default init: post-get-session-default
     transport ruels with race conditions

*/

class opc_ddealer{
  var $stores = array(); // named list of items like opc_dsrc

  /* target/source_mode define the exact behaviour by moving data
   target_mode is used for the target
       0: add or overwrite
       1: add only
       2: overwrite only
       3: replace complete
     Limitations:
       3 is only used in extract/compact
   source_mode is used for the source(s)
       0: keep in source
       1: remove from source
       2: remove only if value was really used
       3: remove from source including subsequent sources
     Limitations:
       2 is not used in extract
       3 is only used in copy
  */
  var $target_mode = 0;
  var $source_mode = 0;

  var $trace = FALSE; // usefull for debuging, see also function log_values

  var $err = NULL;

  function opc_ddealer($tmode=0,$smode=0){
    $this->target_mode = $tmode;
    $this->source_mode = $smode;
    $this->err = new opc_err();
  }

  /* adds a new storage to the system
   obj is a named array or an instance of opc_dsrc (or deeper)
   if an other class see "STORAGE functions" below if it will fit */
  function add($name,&$obj){
    $this->stores[$name] = &$obj;
  }

  function add_std(/* */){
    $ar = func_get_args();
    foreach($ar as $ce){
      switch($ce){
      case  'get': case 'post': case 'session':
	$this->stores[$ce] = new opc_dsrc_glob($ce);
	break;
      default:
	if(substr($ce,0,8)=='session.')
	  $this->stores[substr($ce,8)] = new opc_dsrc_glob('session',substr($ce,8));
	else if(substr($ce,0,8)=='session:')
	  $this->stores[$ce] = new opc_dsrc_glob('session',substr($ce,8));
	else
	  $this->stores[$ce] = new opc_dsrc($ce);
      }
    }
  }


  /* copy (or moves) variables between sources
   target: NULL -> returns array of results
           string -> saves them in this store and returns array of source names
   varlist: (string mode)
              string -> this variable
	      array of strings -> this variables
	      named array of strings -> allows renaming; key = target name; val = source name
	    (pattern mode, recogniced by a string starting with '/')
              string -> take all variables which match this pattern
	      array(Pattern, replacement), allows renaming
	         -> take all variables which match pattern and use
		    replacement for preg_replace to get the new name
		    eg array('/^(nav_)/','NAV_'); nav_Ab -> NAV_Ab
   sources: names of elements in stores (if multiple, first will win)
   t/smode: 0, 1 or 2 numbers after the sources, used to overdrive target/source_mode
   */

  function copy($target,$varlist,$source /* [source, ... [[t/]smode]]*/){
    // read in arguments, sources will remain in al
    $al = func_get_args();
    $target = array_shift($al);
    $varlist = array_shift($al);// uniform to an array
    
    $smode = is_numeric($al[count($al)-1])?array_pop($al):$this->source_mode;
    $tmode = is_numeric($al[count($al)-1])?array_pop($al):$this->target_mode;

    $vlist = $this->_resolve_names($varlist,$al);          // prepare name-list
    $tkeys = is_null($target)?array():$this->keys($target);// existing keys
    $nval = array();                                       // new values
    $res = array();                                        // where they come from


    // collect from sources and remove there if asked ==============================
    foreach($vlist as $nkey=>$okey){
      foreach($al as $src){
	if(!$this->exists($src,$okey)) continue; // not in this source
	if(!isset($nval[$nkey])){ // not yet found
	  switch($tmode){// should it be really used
	  case 0: $use = TRUE; break;
	  case 1: $use = !in_array($nkey,$tkeys); break;
	  case 2: $use =  in_array($nkey,$tkeys); break;
	  case 3: $use = TRUE; break;
	  }
	  if($use){ // use this value
	    $nval[$nkey] = $this->get($src,$okey);
	    $res[$nkey] = $src;
	    if($smode!=0) $this->remove($src,$okey); // remove it?
	  } elseif($smode==3 or $smode==1){ // remove it anyway
	    $this->remove($src,$okey);
	  }
	} elseif($smode==3) $this->remove($src,$okey); // remove in all sources
      }
    }
    // Finishing ======================================================================
    // activetd traceing?
    if($this->trace) 
      foreach($res as $key=>$val) $this->err->log($key . '@' . $val . ' -&gt;' . $target);
    // save to target or use new values as result
    if(!is_null($target)) $this->set_arr($target,$nval); else $res = $nval;
    // return single item if it was asked at the beginning
    if(is_string($varlist) and substr($varlist,0,1)!='/') return(array_shift($res));
    return($res);
  }

  /* similar to copy but the the value of the first source containig the variable will
   be written to all other. renaming will not be done. Neither target_ nor
   source_mode is used*/
  function propagate($varlist,$source /* source ... */){
    $al = func_get_args();
    $varlist = array_shift($al);// uniform to an array
    $vlist = $this->_resolve_names($varlist,$al);          // prepare name-list
    $res = array();                                        // where they come from

    // collect from sources and remove there if asked ==============================
    foreach($vlist as $okey){
      foreach($al as $src){
	if(!$this->exists($src,$okey)) continue; // not in this source
	$val = $this->get($src,$okey);
	$res[$nkey] = $src;
	break;
      }
      foreach($al as $src2) if($src2!=$src) $this->set($src2,$okey,$val);
    }
    if(!is_array($varlist)) $res = array_shift($res);
    return($res);
  }



  /* similar to copy but will copy all variables of source as one array in target */
  function compact($target,$varname,$source,$tmode=NULL,$smode=NULL){
    if(is_null($tmode)) $tmode = $this->target_mode;
    if(is_null($smode)) $smode = $this->source_mode;
    $nval = $this->get_arr($source);
    if($tmode!=3){
      $val = $this->get($target,$varname);
      switch($tmode){
      case 0: $keys = array_keys($nval); break;
      case 1: $keys = array_diff(array_keys($nval),array_keys($val)); break;
      case 2: $keys = array_intersect(array_keys($nval),array_keys($val)); break;
      }
      foreach($keys as $ck) $val[$ck] = $nval[$ck];
      switch($smode){
      case 1: $this->remove($source,array_keys($nval));
      case 2: $this->remove($source,$keys);
      }
    } else {
      if($smode!=0) $this->remove($source);
      $val = $nval;
    }
    $this->set($target,$varname,$val);
  }

  /* similar to copy but will copy each element of the source array as single item to target */
  function extract($target,$varname,$source,$tmode=NULL,$smode=NULL){
    if(is_null($tmode)) $tmode = $this->target_mode;
    if(is_null($smode)) $smode = $this->source_mode;
    $nval = $this->get($source,$varname);
    if(!is_array($nval)) $nval = array();
    if($smode!=0) $this->remove($source,$varname);

    switch($tmode){
    case 3: $this->reset($target); // no break
    case 0: $this->set_arr($target,$nval); break;
    default:
      $nkey = array_keys($nval);
      $tkey = $this->keys($target);
      $keys = $tmode==1?array_diff($tkey,$nkey):array_intersect($tkey,$nkey);
      foreach($keys as $ck) $this->set($target,$ck,$nval[$ck]);
    }
  }
  
  // convert variable (or pattern) to a list for copy/propagate)
  function _resolve_names($vars,$sources){
    if(!is_array($vars)) $vars = array($vars);
    $keys = array_keys($vars);
    $vlist = array(); // this new varlist

    if(substr($vars[$keys[0]],0,1)=='/'){ // ---------------------- Pattern - Mode
      foreach($sources as $src){
	$skeys = preg_grep($vars[0],$this->keys($src));
	if(count($vars)>1)
	  foreach($skeys as $ck) $vlist[preg_replace($vars[0],$vars[1],$ck)] = $ck;
	else 
	  foreach($skeys as $ck) $vlist[$ck] = $ck;
      }
    } else { // -------------------------------------------------- String - Mode
      foreach($vars as $key=>$ck) $vlist[is_numeric($key)?$ck:$key] = $ck;
    }
    return($vlist);
  }


  function log_values($store=NULL){
    if(is_null($store)) $store = array_keys($this->stores);
    else if(!is_array($store)) $store = array($stores);
    foreach($store as $cs)
      foreach($this->get_arr($cs) as $key=>$val)
	$this->err->log("state of $cs: $key = $val");
  }

  /* STORAGE functions --------------------------------------------------
   replacement for an simple array as store
   and shortcuts to the store-methods
   first arg is always the store name
   Use this methods to access the stores! Otherwise array will not work
   -------------------------------------------------- */

  function reset($store /* ... */){
    if(!is_array($this->stores[$store])){
      $al = func_get_args();
      $store = array_shift($al);
      return(call_user_func_array(array(&$this->stores[$store],'reset'),$al));  
    } else $this->stores[$store] = array();
  }

  function exists($store, $key /* ... */){
    if(!isset($this->stores[$store])){
      return(FALSE);
    } else if(!is_array($this->stores[$store])){
      $al = func_get_args();
      $store = array_shift($al);
      return(call_user_func_array(array(&$this->stores[$store],'exists'),$al));  
    } else return(array_key_exists($key,$this->stores[$store]));
  }

  function remove($store, $key=NULL /* ... */){
    if(!is_array($this->stores[$store])){
      $al = func_get_args();
      $store = array_shift($al);
      return(call_user_func_array(array(&$this->stores[$store],'remove'),$al));  
    } elseif(is_array($key)) {
      foreach($key as $ck) unset($this->stores[$store][$ck]);
    } else unset($this->stores[$store][$key]);
  }
  
  function keys($store /* ... */){
    if(!is_array($this->stores[$store])){
      $al = func_get_args();
      $store = array_shift($al);
      return(call_user_func_array(array(&$this->stores[$store],'keys'),$al));
    } else return(array_keys($this->stores[$store]));
  }

  function set($store, $var, $val /* ... */){
    if(!is_array($this->stores[$store])){
      $al = func_get_args();
      $store = array_shift($al);
      return(call_user_func_array(array(&$this->stores[$store],'set'),$al)); 
    } else $this->stores[$store][$var] = $val;

  }

  function setweak($store, $var, $val /* ... */){
    if(!is_array($this->stores[$store])) {
      $al = func_get_args();
      $store = array_shift($al);
      return(call_user_func_array(array(&$this->stores[$store],'setweak'),$al)); 
    } elseif(!isset($this->stores[$store][$var])) $this->stores[$store][$var] = $val;
  }

  function set_arr($store, $values /* ... */){
    if(!is_array($this->stores[$store])) {
      $al = func_get_args();
      $store = array_shift($al);
      return(call_user_func_array(array(&$this->stores[$store],'set_arr'),$al)); 
    } else {
      foreach($value as $key=>$val) $this->stores[$store][$key] = $val;
    }
  }

  function get($store, $var /* ... */){
    if(!is_array($this->stores[$store])){
      $al = func_get_args();
      $store = array_shift($al);
      return(call_user_func_array(array(&$this->stores[$store],'get'),$al));
    } else return($this->stores[$store][$var]);
  }

  function get_arr($store, $var=NULL /* ... */){
    if(!isset($this->stores[$store]) or !is_array($this->stores[$store])){
      $al = func_get_args();
      $store = array_shift($al);
      return(call_user_func_array(array(&$this->stores[$store],'get_arr'),$al));
    } elseif(!is_null($var)){
      $res = array();
      foreach($var as $ck) $res[$ck] = $this->stores[$store][$ck];
    } else return($this->stores[$store]);
  }

}

?>
<?php
  /* --------------------------------------------------
   nested arrays
   -------------------------------------------------- */


  /* ideas
 ideas
   transpose a nested array
   dnested: set/get/exists/del (with standard subframe eg: childs)



   n: nested
   dn: double-nested
   f:  flat array; key represents structure
   fs: flat array; subitems representsstructure
       -> scalar: parent key
       -> array: child keys
   fl: flat array; structure is represendet by level (integer)
   */
class ops_narray{




/* ============================================================
 set-get-... for n-arrays
 ============================================================*/

  /* gets a nested value of an array
   two possible arguments combination
   arg2 is an array ->
     arg2 is an array of keys (top to down)
     arg3 (opt) is the default value (def=NULL)
   otherwise
     arg2 ... arg# are the keys from top to down
   */
  static function get($data /*key subkey ...*/){
    $ar = func_get_args();
    array_shift($ar);
    if(is_array($ar[0])) {
      $def = def($ar,1);
      $ar = $ar[0];
    } else $def = NULL;
    while($ck = array_shift($ar)){
      if(isset($data[$ck])) $data = $data[$ck]; 
      else return($def);
    } 
    return($data);
  }

  static function exists($data /*key subkey ...*/){
    $ar = func_get_args();
    array_shift($ar);
    if(is_array($ar[0])) $ar = $ar[0];
    while($ck = array_shift($ar)){
      if(isset($data[$ck])) $data = $data[$ck]; 
      else return(FALSE);
    } 
    return(TRUE);
  }

  static function del(&$data/*key subkey ...*/){
    $ar = func_get_args();
    array_shift($ar);
    $dat = $data; // local copy
    if(is_array($ar[0])) $ar = $ar[0];
    $nc = count($ar);
    if($nc==0){$data = NULL;return($dat);} // special case
    $dstack = array();
    for($ii=0;$ii<$nc-1;$ii++){
      $dstack[] = $dat;
      if(!isset($dat[$ar[$ii]])) return(NULL);
      $dat = $dat[$ar[$ii]];
    }
    if(!isset($dat[$ar[$ii]])) return(NULL);
    $res = $dat[$ar[$ii]];
    unset($dat[$ar[$ii--]]);
    while($ii>=0){
      $swap = $dat;
      $dat = array_pop($dstack);
      $dat[$ar[$ii--]] = $swap;
    }
    $data = $dat;
    return($res);
  }

/* Set a given value 
 key/subkeys may be given as single values or as array
 if last argument id boolean it defines if non existing levels will be created or not
   default FALSE
*/
  static function set(&$data,$value/*key subkey ... [TF]*/){
    $ar = func_get_args();
    array_shift($ar);
    array_shift($ar);
    $dat = $data; // local copy
    $nc = count($ar);
    if($nc>0 and is_bool($ar[$nc-1])){
      $create = array_pop($ar);
      $nc--;
    } else $create = FALSE;
    if(is_array($ar[0])) {
      $ar = $ar[0];
      $nc = count($ar);
    }
    if($nc==0){$data = $value; return(TRUE);} // special case
    $dstack = array();
    for($ii=0;$ii<$nc-1;$ii++){
      $dstack[] = $dat;
      if(!isset($dat[$ar[$ii]])){
	if($create) $dat = array(); else return(FALSE);
      }
      $dat = count($dat)>0?$dat[$ar[$ii]]:NULL;
    }
    if(!is_array($dat)){
      if($create) $dat = array(); else return(FALSE);
    }
    $dat[$ar[$ii--]] = $value;
    for(;$ii>=0;$ii--){
      $swap = $dat;
      $dat = array_pop($dstack);
      $dat[$ar[$ii]] = $swap;
    }
    $data = $dat;
    return(TRUE);
  }

  /* similar to set bit will create missing levels as array
   deprecated since last argument in set may be a boolean -> same effect if TRUE */
  static function setC(&$data,$value/*key subkey ...*/){
    $ar = array_slice(func_get_args(),2);
    if(is_array($ar[0])) $ar = $ar[0];
    return(call_user_func_array(array('ops_narray','set'),array(&$data,$value,$ar,TRUE)));
  }


  static function keys($data,$sep=NULL,$mlev=NULL){
    if(!is_array($data)) return(NULL);
    $stack = array();
    $clev = 0;
    $cpath = '';

    $keys = array_keys($data);
    $res = array();
    while(count($data)>0 or count($stack)>0){
      if(count($data)==0){
	list($ckey,$tres,$data,$keys,$cpath)= array_pop($stack);
	if(is_null($sep)){
	  $tres[$ckey] = $res;
	  $res = $tres;
	} else if($sep===FALSE) {
	  $res = array_merge($tres,$res);
	} else {
	  array_walk($res,
		     create_function('&$a,$k,$p','$a=$p.$a;'),
		     $cpath);
	  $res = array_merge($tres,array($cpath),$res);
	}
	$clev--;
      } else {
	$ckey = array_shift($keys);
	$cdat = array_shift($data);
	
	if(is_array($cdat) and (is_null($mlev) or $clev<$mlev)){
	  $cpath = $ckey . $sep;
	  $clev++;
	  $stack[] = array($ckey,$res,$data,$keys,$cpath);
	  $cpath .= $sep . $ckey;
	  $keys = array_keys($cdat);
	  $data = $cdat;
	  $res = array();
	} else {
	  if(is_null($sep)) $res[$ckey] = NULL; else $res[] = $ckey;
	}
      }
    }
    return($res);
  }
  
/* ============================================================
 set-get-... for n-arrays/Objects
 ============================================================*/

  /* similar to get
   but works also with objects
   if data (or sub of if) is an object
     1) takes result of method with the current key, if exists
     2) takes class var with the current key, if exists
   otherwise
     3) same as get itself
  */
  static function getO($data /*key subkey ...*/){
    $ar = func_get_args();
    $data = array_shift($ar);
    if(is_array($ar[0])) {
      $def = $ar[1];
      $ar = $ar[0];
    } else $def = NULL;
    while($ck = array_shift($ar)){
      if(is_array($data)){
	if(isset($data[$ck])) $data = $data[$ck]; 
	else return($def);
      } else if(is_object($data)){
	if(method_exists($data,$ck)) $data = $data->$ck();
	else if(isset($data->$ck)) $data = $data->$ck;
	else return($def);
      } else return($def);
    } 
    return($data);
  }


  static function existsO($data /*key subkey ...*/){
    $ar = func_get_args();
    $data = array_shift($ar);
    if(is_array($ar[0])) $ar = $ar[0];
    while($ck = array_shift($ar)){
      if(is_array($data)){
	if(isset($data[$ck])) $data = $data[$ck]; 
	else return(FALSE);
      } else if(is_object($data)){
	if(method_exists($data,$ck)) $data = &$data->$ck();
	else if(isset($data->$ck)) $data = $data->$ck;
	else return(FALSE);
      } else return(FALSE);
    } 
    return(TRUE);
  }

/* ============================================================
 various
 ============================================================ */

  static function transpose($data,$setdef=FALSE,$def=NULL){
    if(!is_array($data)) return(NULL);
    if(!array_reduce($data,create_function('$z,$x','return($z and is_array($x));'),TRUE))
      return(NULL);
    $ikeys = array_reduce($data,create_function('$z,$x','return(array_merge(array_keys($x)));'),array());
    $okeys = array_keys($data);
    $res = array();
    foreach($ikeys as $key){
      foreach($okeys as $ck){
	if(isset($data[$ck][$key])) 
	  $res[$key][$ck] = $data[$ck][$key];
	else if($setdef)
	  $res[$key][$ck] = $def;
      }
    }
    return($res);
  }


  /* similar to array_merge_recursive but result has no numeric keys (remove, or last one) */
  static function merge(/*...*/){
    $ar = array_filter(func_get_args(),'is_array'); // remove non-arrays
    $nc = count($ar);
    if($nc==0) return(NULL);
    if($nc==1) return($ar[0]);
    $res = call_user_func_array('array_merge_recursive',$ar);
    $res = ops_narray::_merge_reduce($res);
    return($res);
  }

  static function _merge_reduce($arr){
    $nnk = array_reduce(array_keys($arr),create_function('$res,$cur','return($res+=is_numeric($cur));'));
    $nel = count($arr);
    if($nel==$nnk) return(array_pop($arr)); // only numeric keys -> get last one
    // remove numeric keys
    if($nnk>0) foreach(array_filter(array_keys($arr),'is_numeric') as $ck) unset($arr[$ck]);
    $ak = array_keys($arr);
    foreach($ak as $ck)
      $arr[$ck] = (is_array($arr[$ck])?ops_narray::_merge_reduce($arr[$ck]):$arr[$ck]);
    return($arr);
  }

  static function updateAt($orgdata,$fct,$path/* others */){
    $data = &$orgdata;
    $ar = array_slice(func_get_args(),1);
    while($ck = array_shift($path)){
      if(!array_key_exists($ck,$data)) return(FALSE);
      $data = &$data[$ck]; 
      if(empty($path)) break;
    } 
    $ar[0] = &$data;
    call_user_func_array($fct,$ar);
    return($orgdata);
  }

  //by henrique@webcoder.com.br
  static function nmap( $func, $arr ){
    $res = array();
    foreach($arr as $ck=>$cv)
      $res[$ck] = (is_array($cv)?ops_narray::nmap($func,$cv):(is_array($func)?call_user_func_array($func,$cv):$func($cv)));
    return($res);
  }

/* ============================================================
 Conversion
 ============================================================*/

  static function f2n($data,$sep=':'){
    if(!is_array($data)) return($data);
    if(strlen($sep)<1) return(NULL);
    $res = array();
    foreach($data as $key=>$val)
      ops_narray::setC($res,$val,explode($sep,$key));
    return($res);
  }

  static function n2f($data,$sep=':',$mlev=NULL){
    if(!is_array($data)) return(NULL);
    $stack = array();
    $clev = 0;
    $cpath = '';
    $keys = array_keys($data);
    $res = array();
    
    while(count($data)>0 or count($stack)>0){
      if(count($data)==0){
	list($ckey,$tres,$data,$keys,$cpath)= array_pop($stack);
	$res = array_merge($tres,$res);
	$clev--;
      } else {
	$ckey = array_shift($keys);
	$cdat = array_shift($data);
	
	if(is_array($cdat) and (is_null($mlev) or $clev<$mlev)){
	  $clev++;
	  $stack[] = array($ckey,$res,$data,$keys,$cpath);
	  $cpath .= strlen($cpath)>0?($sep . $ckey):$ckey;
	  $keys = array_keys($cdat);
	  $data = $cdat;
	  $res = array();
	} else $res[(strlen($cpath)>0?($cpath . $sep):'') . $ckey] = $cdat;
      }
    }
    return($res);
  }

  /* ============================================================
   Double nested
   ============================================================ */
  /* double nested means each array may contain different values wehre
   one of them represents the childs of the current one
   settings:
     sep: character to separate the ids of the different levels
     set: character to separate the item from the (last) id
     def: if no item name is given in the flat array and the value
          is not an array (containing the items) this îtem name
	  is used as default. Not used in dn2f
     childs: item name where the childs are saved

     pos: item name where the position (0,1..) is saved, NULL -> ignore
     lev: item name where the level (0,1..) is saved, NULL -> ignore
     cns: item name where the name of the childs are saved, NULL -> ignore
     par: item name where the id of the parent is saved, NULL -> ignore
     pth: item name where the path (array(top, ... id)  is saved, NULL -> ignore
     key: item name where the id  is saved, NULL -> ignore
  */

  /* creates from a flat array a double-nested one
   the array-keys do include path, key an optional the item
   consequently the argument set has to contain at least a sep value
     eg: key is country:state:city>address set=array('sep'=>':','set'=>'>')
   the settings lev,pos,cns and par do not affect the result. But if given
   the corresponding items will NOT appear in the result
   items may be given each allone or as array (see dn2f argument set['set'])
   */
  static function f2dn($data,$set=array()){
    if(!is_array($data)) return($data);
    $_int = array('pos','lev','cns','par','pth','key');
    $def = array('sep'=>':','set'=>'=','childs'=>'childs','def'=>'label');
    foreach($_int as $ci) $def[$ci] = NULL;		 
    $set = ops_array::Setdefault($set,$def);
    if(strlen($set['sep'])<1 or strlen($set['set'])<1
       or($set['sep']==$set['set'])) return(NULL);
    $res = array();
    foreach($data as $key=>$val){
      $keys = array();
      foreach(explode($set['sep'],$key) as $ck)
	array_push($keys,$ck,$set['childs']);
      array_pop($keys);
      if(is_null($set['set'])){
	$lkey = array_pop($keys);
	$item = NULL;
      } else list($lkey,$item) =  ops_narray::expl2($set['set'],array_pop($keys));
      if(!is_null($item)){
	foreach($_int as $ci)
	  if(!is_null($set[$ci]) and $item===$set[$ci]) 
	    continue 2;
	array_push($keys,$lkey,$item);
      } else if(is_array($val)){
	ops_array::remove($val,$_int);
	array_push($keys,$lkey);
      } else {
	$val = array($set['def']=>$val);
	array_push($keys,$lkey);
      }
      ops_narray::setC($res,$val,$keys);
    }
    return($res);
  }
  
  /* creates from a flat array with strucutre (fs) a double-nested one
   the array-keys do NOT include the path; only key an optional the item
   struct is the key of an item name 
      if this value is a array it will be interpreted as list of childs key
      if not it will be interpreted as the key of the parent item
   beside this it has the same behaviour as f2dn
   */
  static function fs2dn($data,$struct='childs',$set=array()){
    $_int = array('pos','lev','cns','par','pth','key');
    $def = array('set'=>'=','def'=>'label','childs'=>'childs');
    foreach($_int as $ci) $def[$ci] = NULL;		 
    $set = ops_array::Setdefault($set,$def);
    $res = array(); 
    $par = array();
    foreach($data as $key=>$val){
      if(is_null($set['set'])) $item = NULL;
      else list($key,$item) = ops_narray::expl2($set['set'],$key);
      if(!is_null($item)){
	if($item!=$struct)
	  $res[$key][$item] = $val;
	else if(is_array($val))
	  foreach($val as $cv) $par[$cv] = $key;
	else 
	  $par[$key] = $val;
      } else {
	if(!is_array($val))
	  $res[$key][$set['def']] = $val;
	else {
	  foreach($val as $ck=>$cv){
	    if($ck!=$struct)
	      $res[$key][$ck] = $cv;
	    else if(is_array($cv))
	      foreach($cv as $sv) $par[$sv] = $key;
	    else 
	      $par[$key] = $cv;
	  }
	}
      }
    }
    while(count($par)>0){
      $ak = array_keys($par);
      foreach($ak as $ck){
	if(!in_array($ck,$par)){
	  $res[$par[$ck]][$set['childs']][$ck] = isset($res[$ck])?$res[$ck]:array();
	  unset($res[$ck]);
	  unset($par[$ck]);
	}
      }
    }
    return($res);
  }

  /* similar to fs2dn but the item which defines the structer contains
   the current level (as integer). Consequently, the items have
   to appear in the right order inside data
  */
  static function fl2dn($data,$level='level',$set=array()){
    $_int = array('pos','lev','cns','par','pth','key');
    $def = array('set'=>'=','def'=>'label','childs'=>'childs');
    foreach($_int as $ci) $def[$ci] = NULL;		 

    $set = ops_array::Setdefault($set,$def);
    $res = array(); 
    $par = array();
    $pars = array(); // parrent key of the i'th-level
    foreach($data as $key=>$val){
      if(is_null($set['set'])) $item = NULL;
      else list($key,$item) = ops_narray::expl2($set['set'],$key);
      if(!is_null($item)){
	if($item==$level){
	  $pars[(int)$val] = $key;
	  $par[$key] = isset($pars[$val-1])?$pars[$val-1]:NULL;
	} else $res[$key][$item] = $val;
      } else {
	if(!is_array($val))
	  $res[$key][$set['def']] = $val;
	else {
	  foreach($val as $ck=>$cv){
	    if($ck==$level){
	      $pars[(int)$cv] = $key;
	      $par[$key] = isset($pars[$cv-1])?$pars[$cv-1]:NULL;
	    } else $res[$key][$ck] = $cv;
	  }
	}
      }
    }
    while(count($par)>0){
      $ak = array_keys($par);
      foreach($ak as $ck){
	if(is_null($par[$ck])){
	  unset($par[$ck]);
	} else if(!in_array($ck,$par)){
	  $res[$par[$ck]][$set['childs']][$ck] = is_null($res[$ck])?array():$res[$ck];
	  unset($res[$ck]);
	  unset($par[$ck]);
	}
      }
    }
    return($res);
  }

  /* reverse of f2dn
   $set['set'] = NULL -> array('b:b1'=>array('item1'=>val1,'item2'=>val2))
            otherwise -> array('b:b1=item1'=>val1,'b:b1=item2'=>val2)
   $set['sep'] = NULL -> keys of the result do not include the path
                         therefore all keys should be unique over all levels
   */
  static function dn2f($data,$set=array()){
    if(!is_array($data)) return($data);
    $_int = array('pos','lev','cns','par','pth','key');
    $def = array('sep'=>':','set'=>'=','childs'=>'childs');
    foreach($_int as $ci) $def[$ci] = NULL;		 

    $set = ops_array::setdefault($set,$def);
    $stack = array();
    $clev = 0;
    $cpos = 0;
    $cpath = '';
    $keys = array_keys($data);
    $res = array();

    while(count($data)>0 or count($stack)>0){
      if(count($data)==0){
	list($ckey,$tres,$data,$keys,$cpath,$cpos)= array_pop($stack);
	$res = array_merge($tres,$res);
	$clev--;
      } else {
	$ckey = array_shift($keys);
	$cdat = array_shift($data);

	if(!empty($set['pos'])) $cdat[$set['pos']] = $cpos;
	if(!empty($set['lev'])) $cdat[$set['lev']] = $clev;
	if(!empty($set['key'])) $cdat[$set['key']] = $ckey;
	if(!empty($set['par'])) {
	  $nc = count($stack)-1;
	  $cdat[$set['par']] = $nc>=0?$stack[$nc][0]:NULL;
	}
	if(!empty($set['cns']))
	  if(isset($cdat[$set['childs']]) and  is_array($cdat[$set['childs']]))
	    $cdat[$set['cns']] = array_keys($cdat[$set['childs']]);
	
	if(!empty($set['pth'])){
	  foreach($stack as $cs) $cdat[$set['pth']][] = $cs[0];
	  $cdat[$set['pth']][] = $ckey;
	}

	if(is_null($set['sep'])) $cp = $ckey;
	else $cp = (strlen($cpath)>0?($cpath . $set['sep']):'') . $ckey;

	if(is_null($set['set'])){
	  $res[$cp] = $cdat;
	  unset($res[$cp][$set['childs']]);
	} else {
	  foreach($cdat as $ck=>$cv)
	    if($ck!=$set['childs']) 
	      $res[$cp . $set['set'] . $ck] = $cv;
	}
	$cpos++;
	if(isset($cdat[$set['childs']]) and is_array($cdat[$set['childs']])){
	  $clev++;
	  $stack[] = array($ckey,$res,$data,$keys,$cpath,$cpos);
	  $cpath .= strlen($cpath)>0?( $set['sep'] . $ckey):$ckey;
	  $data = $cdat[$set['childs']];
	  $keys = array_keys($data);
	  $res = array();
	  $cpos = 0;
	} 
      }
    }
    return($res);
 
 }

  /* converts a nested to a double nested array
   the only used settings are 'childs' and 'def' */
  static function n2dn($data,$set=array()){ 
    if(!is_array($data)) return(NULL);
    $def = array('def'=>'label','childs'=>'childs');
    $set = ops_array::Setdefault($set,$def);
    $res = array();
    $stack = array();
    $keys = array_keys($data);
    while(count($data)>0 or count($stack)>0){
      if(count($data)==0){
	list($ckey,$tres,$data,$keys)= array_pop($stack);
	$tres[$ckey][$set['childs']] = $res;
	$res = $tres;
      } else {
	$ckey = array_shift($keys);
	$cdat = array_shift($data);
	if(is_array($cdat)){
	  $stack[] = array($ckey,$res,$data,$keys);
	  $keys = array_keys($cdat);
	  $data = $cdat;
	  $res = array();
	} else $res[$ckey] = array($set['def']=>$cdat);
      }
    }
    return($res);
  }

  /* converts a double nested to a nested array
   the only used settings are 'childs' and 'def' */
  static function dn2n($data,$set=array()){
    if(!is_array($data)) return($data);
    $def = array('def'=>'label','childs'=>'childs');
    $set = ops_array::setdefault($set,$def);
    $stack = array();
    $keys = array_keys($data);
    $res = array();
    while(count($data)>0 or count($stack)>0){
      if(count($data)==0){
	list($ckey,$tres,$data,$keys)= array_pop($stack);
	$tres[$ckey] = $res;
	$res = $tres;
      } else {
	$ckey = array_shift($keys);
	$cdat = array_shift($data);

	if(isset($cdat[$set['childs']]) and is_array($cdat[$set['childs']])){
	  $stack[] = array($ckey,$res,$data,$keys);
	  $data = $cdat[$set['childs']];
	  $keys = array_keys($data);
	  $res = array();
	} else $res[$ckey] = $cdat[$set['def']];
      }
    }
    return($res);
 
 }



  static function keysDN($data,$sep=NULL,$mlev=NULL,$set=array()){
    $def = array('childs'=>'childs');
    $set = ops_array::Setdefault($set,$def);

    if(!is_array($data)) return(NULL);
    $stack = array();
    $clev = 0;
    $cpath = '';

    $keys = array_keys($data);
    $res = array();
    while(count($data)>0 or count($stack)>0){
      if(count($data)==0){
	list($ckey,$tres,$data,$keys,$cpath)= array_pop($stack);
	if(is_null($sep)){
	  $tres[$ckey] = $res;
	  $res = $tres;
	} else if($sep===FALSE) {
	  $res = array_merge($tres,$res);
	} else {
	  array_walk($res,
		     create_function('&$a,$k,$p','$a=$p.$a;'),
		     $cpath);
	  $res = array_merge($tres,array($cpath),$res);
	}
	$clev--;
      } else {
	$ckey = array_shift($keys);
	$cdat = array_shift($data);
	
	if(isset($cdat[$set['childs']]) and $cdat[$set['childs']]
	   and (is_null($mlev) or $clev<$mlev)){
	  $cpath = $ckey . $sep;
	  $clev++;
	  $stack[] = array($ckey,$res,$data,$keys,$cpath);
	  $cpath .= $sep . $ckey;
	  $data = $cdat[$set['childs']];
	  $keys = array_keys($data);
	  $res = array();
	} else {
	  if(is_null($sep)) $res[$ckey] = NULL; else $res[] = $ckey;
	}
      }
    }
    return($res);
  }

  /* the function get set setc exists and del will be reproduced
   using _mixin which inserts the childs-key to key-chain/item */
  static function _mixin($keys,$item,$chld){
    $res = array();
    $nc = count($keys);
    for($ii=0;$ii<$nc-1;$ii++) array_push($res,$keys[$ii],$chld);
    $res[] = $keys[$nc-1];
    if(!is_null($item)) $res[] = $item;
    return($res);
  }

  static function getDN($data,$keys,$item='label',$set=array()){
    $def = array('childs'=>'childs','default'=>NULL);
    $set = ops_array::Setdefault($set,$def);
    $def = $set['default'];
    return(ops_narray::get($data,
			   ops_narray::_mixin($keys,$item,$set['childs']),
			   $set['default']));
  }

  static function existsDN($data,$keys,$item='label',$set=array()){
    $def = array('childs'=>'childs');
    $set = ops_array::Setdefault($set,$def);
    return(ops_narray::exists($data,
			      ops_narray::_mixin($keys,$item,$set['childs'])));
  }


  static function delDN(&$data,$keys,$item='label',$set=array()){
    $def = array('childs'=>'childs');
    $set = ops_array::Setdefault($set,$def);
    $def = isset($set['default'])?$set['default']:NULL;
    return(ops_narray::del($data,
			   ops_narray::_mixin($keys,$item,$set['childs'])));
  }


  static function setDN(&$data,$value,$keys,$item='label',$set=array()){
    $def = array('childs'=>'childs');
    $set = ops_array::Setdefault($set,$def);
    $def = isset($set['default'])?$set['default']:NULL;
    return(ops_narray::set($data,$value,
			   ops_narray::_mixin($keys,$item,$set['childs'])));
  }

  static function setDNC(&$data,$value,$keys,$item,$set=array()){
    $def = array('childs'=>'childs');
    $set = ops_array::Setdefault($set,$def);
    $def = isset($set['default'])?$set['default']:NULL;
    return(ops_narray::setC($data,$value,
			    ops_narray::_mixin($keys,$item,$set['childs'])));
  }

  /* create from a fs a path
   settings
    self: if TRUE the key is included to the path as last element
    sep: if given the result is a string (sep used for implode) otherwise a array
    prefix/sufix: used as pre/suffix for a string path
    set/def: used as usual
  */
  static function fs2p($data,$struct='childs',$set=array()){
    $def = array('set'=>'=','def'=>'label','self'=>FALSE,
		 'sep'=>NULL,'prefix'=>'','suffix'=>'');
    $set = ops_array::Setdefault($set,$def);
    $res = array(); 
    $par = array();
    foreach($data as $key=>$val){
      if(!isset($res[$key])) $res[$key] = array();
      if(is_null($set['set'])) $item = NULL;
      else list($key,$item) = ops_narray::expl2($set['set'],$key);
      if(!is_null($item)){
	if($item!=$strcut) continue;
      } else if(is_array($val)){
	if(isset($val[$struct])) $val = $val[$struct]; 
	else continue;
      } else {
	if($set['def']!=$strcut) continue;
      }

      if(is_array($val)){ // list of childs
	foreach($val as $cv)
	  $res[$cv] = array_merge($res[$key],array($key));
      } else { // parent key
	$res[$key] = array_merge($res[$val],array($val));
      }
    }
    $ak = array_keys($res);
    if($set['self']==TRUE) foreach($ak as $ck) 
      $res[$ck][] = $ck;
    if(!is_null($set['sep'])) foreach($ak as $ck) 
      $res[$ck] = $set['prefix'] . implode($set['sep'],$res[$ck]) . $set['suffix'];
    return($res);
  }



  /* ================================================================================
   dn-Function
   ================================================================================ */

  /* array_reduce for double nested array
   $fct?: callback function
   $dir?: if TRUE fct accept the whole array as argument, FALSE similar to array_reduce
   $init?: init value as used in array_reduce

   *C is to reduce the list of childs, the result replaces the child-array
   *I is to reduce a single item (childs are already reduced to a single value)
  */
  static function dn_reduce($data,$childs,
		     $fctI,$fctC,
		     $dirI=FALSE,$dirC=FALSE,
		     $initI=NULL,$initC=NULL){
    $stack = array();
    $ak = array_keys($data);
    while(count($ak)>0 or count($stack)>0){
      if(count($ak)==0){
	$res = $dirC==TRUE?call_user_func($fctC,$data):array_reduce($data,$fctC,$initC);
	list($ck,$ak,$data) = array_splice($stack,0,3);
	$data[$ck][$childs] = $res;
	$data[$ck] = $dirI==TRUE?call_user_func($fctI,$data[$ck]):array_reduce($data[$ck],$fctI,$initI);
      } else {
	$ck = array_shift($ak);
	if(isset($data[$ck][$childs]) and count($data[$ck][$childs])>0){
	  array_unshift($stack,$ck,$ak,$data);
	  $data = $data[$ck][$childs];
	  $ak = array_keys($data);
	} else { // no childs -> use function 'internal'
	  $data[$ck] = $dirI==TRUE?call_user_func($fctI,$data[$ck]):array_reduce($data[$ck],$fctI,$initI);
	}
      }
    }
    return($dirC==TRUE?call_user_func($fctC,$data):array_reduce($data,$fctC,$initC));
  }
  
  static function expl2($sep,$data){
    $res = explode($sep,$data,2);
    if(count($res)==1) $res[] = NULL;
    return($res);
  }

}
?>
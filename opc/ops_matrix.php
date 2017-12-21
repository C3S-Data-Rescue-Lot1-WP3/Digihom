<?php

  /* static funtions for arrays of array which represents matrices */

class ops_matrix {
  static function extract(&$arr,$keys,$remove=FALSE){
    $res = array();
    $ak = array_keys($arr);
    if(is_array($keys)){
      foreach($keys as $key){
	$cl = array();
	foreach($ak as $ck){
	  $cl[$ck] = $arr[$ck][$key];
	  if($remove) unset($arr[$ck][$key]);
	}
	$res[$key] = $cl;
      }
    } else {
      foreach($ak as $ck){
	$res[$ck] = $arr[$ck][$keys];
	if($remove) unset($arr[$ck][$keys]);
      }
    }
    return($res);
  }

  static function mat2arr($mat,$valfield=0,$keyfield=NULL){
    $res = array();
    foreach($mat as $key=>$val){
      $nval = $val[$valfield];
      if(is_null($keyfield)) $res[$key] = $nval; 
      else $res[$val[$keyfield]] = $nval;
    }
    return($res);
  }

  static function arr2mat($arr,$valfield=0,$keyfield=NULL){
    $res = array();
    foreach($arr as $key=>$val){
      $val = array($valfield=>$val);
      if(!is_null($keyfield)) {
	$val[$keyfield] = $key;
	$res[] = $val;
      } else $res[$key] = $val;
    }
    return($res);
  }


  /* arr is an array of array and the field given by keyfield of the inner
   arrays will be used as new key of the outer levels
   if remove the field of the keys will be removed from the inner arrays
  */
  static function field2key($arr,$keyfield=0,$remove=TRUE){
    $res = array();
    foreach($arr as $key=>$val){
      $nkey = $val[$keyfield];
      if($remove) unset($val[$keyfield]);
      $res[$nkey] = $val;
    }
    return($res);
  }

  /*
   will write the keys of an array to its elements (with key given by keyfield)
   if the elements are not array the will be converted to arrays
   remove: if true the results ha numerical keys, if false the keys will remain
   */
  static function key2field($arr,$keyfield='key',$remove=TRUE){
    $res = array();
    foreach($arr as $key=>$val){
      if(is_array($val)) $val[$keyfield] = $key; 
      else $val = array($val,$keyfield=>$key);
      if($remove) $res[] = $val; else $res[$key] = $val;
    }
    return($res);
  }

  /* uniforms the keys
   key: array of the keys which shiould appear everywhere
     if null it will calculated 
   */
  static function uniform($mat,$keys=NULL,$def=NULL){
    if(is_null($keys)) $keys = ops_matrix::keys($mat,FALSE);
    $res = array();
    foreach($mat as $key=>$val){
      $cl = array();
      foreach($keys as$ck){
	if(isset($val[$ck])) $cl[$ck] = $val[$ck];
	else if(is_array($def)) $cl[$ck] = isset($def[$ck])?$def[$ck]:NULL;
	else $cl[$ck] = $def;
      }
      $res[$key] = $cl;
    }
    return($res);
  }

  /* if intersection = TRUE  returns an array of keys which appers in all items of mat
   = FALSE returns an array of all keys which appers the items of mat */
  static function keys($mat,$intersection=FALSE){
    $res = array_keys(array_shift($mat));
    if($intersection)
      foreach($mat as $ci) $res = array_intersect($res,array_keys($ci));
    else
      foreach($mat as $ci) $res = array_unique(array_merge($res,array_keys($ci)));
    return($res);
  }

  static function t($mat){
    $ik = ops_matrix::keys($mat,FALSE);
    $ak = array_keys($mat); 
    $res = array();
    foreach($ik as $ck) $res[$ck] = array();
    foreach($ak as $ack) foreach($ik as $ick) $res[$ick][$ack] = $mat[$ack][$ick];
    return($res);
  }

}

?>
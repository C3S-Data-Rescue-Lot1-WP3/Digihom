<?php
class ops_arguments {
  /*
   explode a argument string as used inside a tag or function heads to a (named) array
   sepc: character to separate items
   quote: Stringquoting allowed? NULL: no; single: woth '; double: with "; both: " and '
   mask: character to mask the following character (typicaly a backslash
   setc: character to separate between name and value or NULL for no named values
   static calls allowed
  */
  static function argexploder($value,$sepc=' ',$quote=NULL,$mask=NULL,$setc=NULL){
    if(is_array($value)) return($value);
    $value = is_string($value)?trim($value):strval($value);
    if(is_null($quote) or $quote===FALSE){ // no quotes allowed
      if(!is_null($setc)) $value = preg_replace("/$sepc*$setc$sepc*/",$setc,$value);
      if($sepc==' ') $value = preg_replace('/ +/',' ',$value);
      if(is_null($mask)) $value = explode($sepc,$value);
      else $value = ops_arguments::_explodemasked($sepc,$mask,$value);
      if(!is_null($setc)) $value = ops_arguments::_explodename($setc,$value);
      else $value = array_values($value);
      array_walk($value, create_function('&$v,$k','$v=trim($v);'));
    } else {
      // explode by quotes mask and separator
      if($quote=='single') $pat = "'";
      else if($quote=='double') $pat = '"';
      else $pat = '"|\'';
      if(is_null($setc)) $pat = '/(' . $pat . '|' . $sepc . ')/';
      else  $pat = '/(' . $pat . '|' . $sepc . '|' . $setc . ')/';
      $sep = array(); preg_match_all($pat,$value,$sep); $sep = $sep[0];
      $value = preg_split($pat,$value);
      // recombine if it was masked
      $cn = count($value); 
      for($ci=$cn-2;$ci>=0;$ci--){
	if(substr($value[$ci],-1,1)==$mask){
	  $value[$ci] = substr($value[$ci],0,-1) . $sep[$ci] . $value[$ci+1];
	  unset($value[$ci+1]); unset($sep[$ci]);
	}
      }
      $value = array_values($value); $sep = array_values($sep);
      //recombine quoted
      $cm = NULL; $ci = 0; $cn = count($sep);
      for($cp=0;$cp<$cn;$cp++){
	if(is_null($cm)){
	  $ci = $cp+1;
	  if($sep[$cp]=="'") {$sep[$cp] = FALSE; $cm = FALSE; }
	  else if($sep[$cp]=='"') {$sep[$cp] = FALSE; $cm = TRUE;}
	} else {
	  if(($cm===TRUE and $sep[$cp]==='"') or ($cm===FALSE and $sep[$cp]==="'")){
	    $cm = NULL;
	    $sep[$cp] = '';
	  } else {
	    $value[$ci] .= $sep[$cp] . $value[$cp+1];
	    unset($value[$cp+1]);
	    unset($sep[$cp]);
	  }
	}
      }
      $value = array_values($value); $sep = array_values($sep);

      //special trim since it should not be done inside a quoted part!
      if($sepc==' '){//trim if sapce is the separator
	foreach(array_keys($value,'') as $ci){
	  if($sep[$ci-1]==' ' and $sep[$ci]==' '){
	    unset($sep[$ci-1]); 
	    unset($value[$ci]);
	  } else if(isset($sep[$ci]) and $sep[$ci]==' ' and $sep[$ci-1]==$setc){
	    $sep[$ci] = $sep[$ci-1]; // move setchat to the right position for next round
	    unset($sep[$ci-1]); 
	    unset($value[$ci]);
	  } else if($sep[$ci-1]==' ' and $sep[$ci]==$setc){
	    unset($sep[$ci-1]); 
	    unset($value[$ci]);
	  }
	}
      } else { //trim if space is not the separator
	for($ci=0;$ci<count($sep);$ci++) 
	  if($sep[$ci]!==FALSE) 
	    if($ci>0) $value[$ci-1] = trim($value[$ci-1]);
      }
      $value = array_values($value); $sep = array_values($sep); 

      //collect items
      $sepp = array_keys($sep,$sepc); $res = array(); $cp = 0;
      if(count($sepp)>0){
	for($ci=0;$ci<$cn;$ci++){
	  $res[$cp][0][] = isset($value[$ci])?$value[$ci]:NULL;
	  if(isset($sepp[$cp]) and $ci==$sepp[$cp]) $cp++; 
	  else $res[$cp][1][] = isset($sep[$ci])?$sep[$ci]:NULL;
	}
	$res[$cp][0][] = isset($value[$ci])?$value[$ci]:NULL; //the last element!
      } else $res = array(array($value,$sep));

      //get names and refusion values
      $value = array(); $cn = count($res); $cj = 0;
      for($ci=0;$ci<$cn;$ci++){
	if(!is_null($setc) and isset($res[$ci][1]) and $res[$ci][1][0]==$setc) {
	  $cnam = trim(array_shift($res[$ci][0])); 
	  array_shift($res[$ci][1]);
	} else $cnam = $cj++;
	$cval = array_shift($res[$ci][0]);
	while(count($res[$ci][0])>0){
	  if(isset($res[$ci][1]) and  count($res[$ci][1])>0) $cval .= array_shift($res[$ci][1]);
	  $cval .= array_shift($res[$ci][0]);
	}
	$value[$cnam] = $cval;
      }
    }
    return($value);
  }

  //like explode but not if separator is masked with mask
  static function _explodemasked($sep,$mask,$value){
    $value = explode($sep,$value);
    $cn = count($value);
    $ak = array_keys($value);
    for($ci=$cn-2;$ci>=0;$ci--){
      if(substr($value[$ak[$ci]],-1,1)==$mask){
	$value[$ak[$ci]] = substr($value[$ak[$ci]],0,-1) . $value[$ak[$ci+1]];
	unset($value[$ak[$ci+1]]);
      }
    }
    return($value);
  }

  /*explodes the items of an array to name and value, includng trimming
   array('a=2','b = 4') -> array('a'=>'2','b'=>'4') */
  static function _explodename($sep,$value){
    $res = array();
    foreach($value as $cv){
      $cv = explode($sep,$cv,2);
      if(count($cv)==1) $res[] = trim($cv[0]); else $res[trim($cv[0])]= trim($cv[1]);
    }
    return($res);
  }

  /* attribute given value to possible values given in pot
   if pot is not an array value will be returned directly
   if pot is an empty array NULL is returned

   the macthing is based on a numeric hit-quote. Where level defines the maximal
   allowed value (see below for more details)

   level: The matching is based on a numerical hit-quote. The argument level definies the 
     maximal hit-quote which is used for matching
     level 1-6 are common comparisons 50 and above using levensthein
     1: exact match (pattern: "/^key$/")
     2: as 1 ignoring case
     3: value is the beginnig of a potential value  (pattern: "/^key/")
     4: as 3 ignoring case
     5: value is a part of a potential value  (pattern: "/key/")
     6: as 5 ignoring case
     >50: uses function levenshtein (penaöty terms: insert=1, replace=5, delete=10)
        the result of levensthein will enlarged by 50 to separate from the cases above

   mode: Mode 0 is the default and is used by the others
     0: if value is not an array if find the best matching value in pot and returns this
        if value is an array it matches all values, whereas the closest matches (see level)
	will be used first. Therefore no identical values may be returned
	keys of value will be lost!
     1: same as 0 but the keys of value are preserved 
     2: value is a named array a his keys will be mathced against the values of pot
     3: both are named array. First the keys of value will be matched to those of pot. 
        Second the same will be done with the values
     4: same as 3, but the first step has to be done already

   */
  static function expected($value,$pot,$level=49,$mode=0){
    if(!is_array($pot)) return($value);
    if(count($pot)==0) return(NULL);
    if($mode>0 and !is_array($value)) return(FALSE);
    $max = 9999;
    if(is_null($level) or $level<0) $level = $max;
    switch($mode){
    case 1:
      $keys = array_keys($value);
      $tres = ops_arguments::expected($value,$pot,$level,0);
      $res = array();
      for($ci=0;$ci<count($keys);$ci++) $res[$keys[$ci]] = $tres[$ci];
      return($res);
    case 2:
      $vals = array_values($value);
      $tres = ops_arguments::expected(array_keys($value),$pot,$level,0);
      $res = array();
      foreach($tres as $ck) $res[$ck] = array_shift($vals);
      return($res);
    case 3:
      $res = ops_arguments::expected($value,array_keys($pot),$level,2);
      while(list($ak,$av)=each($res)) 
	$res[$ak] = ops_arguments::expected($av,$pot[$ak],$level,0);
      return($res);
    case 4:
      $res = $value;
      while(list($ak,$av)=each($res)) 
	$res[$ak] = ops_arguments::expected($av,$pot[$ak],$level,0);
      return($res);
    default:
      $pot = array_values($pot);
      $val = is_array($value)?array_values($value):array($value);
      $np = count($pot);
      $nv = count($val);
      if($nv==0) return(array());
      if(count(array_unique($val))!=$nv) return(FALSE);
      if(count(array_unique($pot))!=$np) return(FALSE);
      $grp = array(array('/^','$/'),array('/^','$/i'),//complete match
		   array('/^','/'),array('/^','/i'),//at the beginnig
		   array('/','$/'),array('/','$/i'));//somewhere
      $ng = count($grp);
      $mat = array_fill(0,$nv*$np,$max);//pseudo 2dim matrix for the match points
      for($cv=0;$cv<$nv;$cv++){
	for($cg=0;$cg<$ng;$cg++){
	  if($cg>$level) break; // don not use this or higher levels
	  $ak = preg_grep($grp[$cg][0] . preg_quote($val[$cv]) . $grp[$cg][1],$pot);
	  if(count($gr)==1){
	    $ak = array_keys($ak);
	    if($mat[$ak[0]*$nv+$cv]>=$max) $mat[$ak[0]*$nv+$cv] = $cg;
	  }
	}
      } 
      if($level>50){ // levenshtein
	for($cv=0;$cv<$nv;$cv++)
	  for($cp=0;$cp<$np;$cp++)
	    if($mat[$cp*$nv+$cv]>=$max)
	      $mat[$cp*$nv+$cv] = 50 + levenshtein($val[$cv],$pot[$cp],1,5,10);
      }
      $res = array_fill(0,$nv,FALSE);
      $nrs = array_fill(0,$nv,$max); //not yet used: numerical criteria used
      while(min($mat)<$max){
	$min = min($mat);
	if($min>$level) break; //test here once again for cases above 50 (levenshtein)
	$pos = array_shift(array_keys($mat,$min)); $posv = $pos % $nv;
	$res[$posv] = $pot[floor($pos/$nv)];
	$nrs[$posv] = $min;
	for($cv=0;$cv<$nv;$cv++) $mat[floor($pos/$nv)*$nv+$cv] = $max; // reset col
	for($cp=0;$cp<$np;$cp++) $mat[$cp*$nv+$posv] = $max;           // reset row
      }
      return(is_array($value)?$res:$res[0]);
    }
  }


  /* Match arguments by type
   This function allows flexibility by function with various combination
   of arguments. The first argument (value) is an array with the given arguments
   (typically a result of func_get_args). The second argument (def) is a named
   array with the default values. The elements of value will replace the (n-th)
   element in def with the same type.

   types: named array (same key as def) with the type of defs. If a key from def
          is not used in types, function gettype is used to definie the type
	  remember the use of gettype is slow
    standard type
       string, integer, float, boolean
    special type:
       any: match everything
       resource: uses is_resource
       object: uses is_object
       scalar: uses is_scalar
       number: integer or float
       numeric: uses is_numeric
       filename: uses is_string and file_exists

   to restrict values to a given list use the array with all values in def
   and set its type in types to the right type-name
   eg def = array('gender'=>array('male','female'),'name'=>NULL,'country'=>'Canda')
      types = array('gender'=>'string','name'=>'string')
      values = array('Smith','Italy','male')
      will produce the expected result.

   there is no separation inside objects or ressources   

   $mode 
     0: takes the first element of value an searches the corresponding element
        in def/types.
     1: takes  the first element in def/types and searches the corresponding
        element in value

   NULL-values in value will be ignored
   matched values will be removed from value (sideeffects)
   */
  static function setargs(&$value,$def,$types=array(),$mode=0){
    if(!is_array($value)) $value = array($value);
    $valkeys = array_keys($value);
    $defkeys = array_keys($def);
    foreach($def as $key=>$val) if(!isset($types[$key])) $types[$key] = gettype($val);
    switch($mode){
    case 0:
      foreach($valkeys as $ckey){
	$cval = $value[$ckey];
	if(is_null($cval)) continue;
	foreach($defkeys as $dkey){
	  if(!ops_arguments::_setargs_type($types[$dkey],$cval)) continue;
	  if(is_array($def[$dkey]) and ($types[$dkey]!='any' and $types[$dkey]!='array'))
	    if(!in_array($cval,$def[$dkey])) continue;
	  $def[$dkey] = $cval;
	  $types[$dkey] = '-';
	  unset($value[$ckey]);
	  break;
	}
      }
    case 1:
      foreach($defkeys as $dkey){
	foreach($valkeys as $ckey){
	  if(!isset($value[$ckey])) continue;
	  $cval = $value[$ckey];
	  if(is_null($cval)) continue;
	  if(!ops_arguments::_setargs_type($types[$dkey],$cval)) continue;
	  if(is_array($def[$dkey]) and ($types[$dkey]!='any' and $types[$dkey]!='array'))
	    if(!in_array($cval,$def[$dkey])) continue;
	  $def[$dkey] = $cval;
	  $types[$dkey] = '-';
	  unset($value[$ckey]);
	  break;
	}
      }
      break;
    }
    
    // set selection to first element if not captured
    foreach($defkeys as $dkey)
      if(is_array($def[$dkey]) and !in_array($types[$dkey],array('any','array','-')))
	$def[$dkey] = array_shift($def[$dkey]);
    return($def);
  }

  static function _setargs_type($typ,$cval){
    switch($typ){
    case 'any':       case '*': return(9);
    case 'array':     case 'a': return(is_array($cval)?1:0);
    case 'string':    case 's': return(is_string($cval)?1:0);
    case 'integer':   case 'i': return(is_integer($cval)?1:0);
    case 'float':     case 'f': return(is_float($cval)?1:0);
    case 'boolean':   case 'b': return(is_bool($cval)?2:0);
    case 'number':    case 'n': return(is_int($cval) or is_float($cval)?3:0);
    case 'numeric':   case 'N': return(is_numeric($cval)?1:0);
    case 'scalar':    case 'S': return(is_scalar($cval)?1:0);
    case 'ressource': case 'r': return(is_resource($cval)?1:0);
    case 'object':    case 'o': return(is_object($cval)?1:0);
    case 'filename':
      if(!is_string($cval)) return(0);
      return(file_exists($cval)?1:0);
    }
    return(FALSE);
  }


  /* data is an array with numerical and string keys
   numerical keys will be replaced by the elements of keys (if not yet given in data)
   skip  0: allow elements in data which are not listed in keys
	 1: reject elements in data which are not listed in keys
   check 0: nothing
         1: return FALSE if result has not all elements of keys
	 2: return FALSE if result has more than the elemnts listed in keys
	 3: combination of 1&2
  */

  static function complete_keys($data,$keys,$skip=1,$check=0){
    $res = array(); 
    $nk = count($keys);
    for($ii=0;$ii<$nk;$ii++){
      if(isset($data[$keys[$ii]])){
	$res[$keys[$ii]] = $data[$keys[$ii]];
	unset($keys[$ii]);
	unset($data[$keys[$ii]]);
      }
    }
    foreach($data as $key=>$val){
      if(!is_numeric($key)){ // surplus with a string key
	if($skip==1) continue;
	if($check>1) return(FALSE);
	$res[$key] = $val;
      } else if(count($keys)==0){ // surplus with a numeric key
	if($skip==1) break;
	if($check>1) return(FALSE);
	$res[$key] = $val;
      } else $res[array_shift($keys)] = $val;
    }
    if(($check==1 or $check==3) and count($keys)>0) return(FALSE);
    return($res);
  }

  

  /** simpler version of setargs
      $ar array of elements which will be matched to a name
      $types: array($key=>typesting[,$key=>$ty...])
        where type string is a combination of single characters (see $cb)
      $def: potential array of defaults
      $cb optional callback function which gets one arguments and returns one character
        default (NULL) returns N:NULL, B:Bool; i:int; f:float; n:numeric string; s: other strings
	                       A: array; O: Object; R: resource; -:others (?)
  */
  static function args_set(&$args,$types,$def=array(),$cb=NULL){
    $unused = array();
    foreach($args as $ca){
      if(is_callable($cb)){
	$typ = $cb($ca);
      } else {
	if(is_null($ca)) $typ = 'N';
	else if(is_bool($ca)) $typ = 'B';
	else if(is_integer($ca)) $typ = 'i';
	else if(is_float($ca)) $typ = 'f';
	else if(is_numeric($ca)) $typ = 'n';
	else if(is_string($ca)) $typ = 's';
	else if(is_array($ca)) $typ = 'A';
	else if(is_resource($ca)) $typ = 'R';
	else if(is_object($ca)) $typ = 'O';
	$typ = '-';
      }
      foreach($types as $ck=>$ct){
	if(strpos($ct,$typ)===FALSE) continue;
	$def[$ck] = $ca;
	unset($types[$ck]);
	continue 2;
      }
      $unused[] = $ca;
    }
    $args = $unused;
    return $def;
  }

}

?>
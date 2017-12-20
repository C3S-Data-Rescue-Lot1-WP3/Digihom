<?php
/*
 special functions for array
 the class contains only functions which may be called static

 idea
   deep implode??

*/
  //============================================================
  // set and get
  //============================================================
  
  /*
   bykey: FALSE -> value is search in data
          TRUE -> value is searched in keys of data
   retkey: FALSE -> return data-value if success
           TRUE -> return data-key if success
	   if no success return
   defmode 0: def
           1: nth key of data
	   2: nth value of datra
	   3: key in data-value def
	   4: value in data with key def
  */
class ops_array{

  static function ifset($data,$value,$def=NULL,$bykey=FALSE,$retkey=FALSE,$defmode=0){
    if($bykey==FALSE and in_array($value,$data)){
      return($retkey?array_search($value,$data):$value);
    } else if($bykey==TRUE and array_key_exists($value,$data)){
      return($retkey?$value:$data[$value]);
    }
    switch($defmode){
    case 0: return($def);
    case 1: return(def(array_keys($data),$def));
    case 2: return(def(array_values($data),$def));
    case 3: return(array_search($def,$data));
    case 4: return(def($data,$key));
    }
  }

  /* returns the n'th element of an array (or object) (ignoring the 'real' keys')
   0 points to the first element
   a negative number will be counted backward (-1 -> last element)*/
  static function nth($data,$nth=0,$def=NULL){
    if(!is_int($nth)) return($def);
    if(is_object($data)) $data = get_object_vars($data);
    if(!is_array($data)) return($def);
    $ak = array_keys($data);
    if($nth<0) $nth += count($ak);
    if($nth>=count($ak) or $nth<0) return($def);
    return($data[$ak[$nth]]);
  }

  // gets the nth key of an array (or object)
  static function keyn($data,$nth=0,$def=NULL){
    if(!is_int($nth)) return($def);
    if(is_object($data)) $data = get_object_vars($data);
    if(!is_array($data)) return($def);
    $ak = array_keys($data);
    if($nth<0) $nth += count($ak);
    if($nth>=count($ak) or $nth<0) return($def);
    return($ak[$nth]);
  }

  /** will remove the elements definied by keys (string or array of) 
   * if keys is an array, the return value is it too otherwise just the item
   * defaults (single value or named array) are used if key is not definied in data 
   */
  static function extract(&$data,$keys,$defaults=NULL){
    return(self::key_extract($data,$keys,$defaults));
  }
  static function key_extract(&$data,$keys,$defaults=NULL){
    $ks = is_array($keys)?$keys:array($keys);
    $res = array();
    if(!is_array($defaults)) foreach($ks as $ck) $res[$ck] = $defaults;
    else foreach($ks as $ck) $res[$ck] = def($defaults,$ck,NULL);
    if($data instanceof ArrayAccess){
      foreach($ks as $ck){
	if(isset($data[$ck])){
	  $res[$ck] = $data[$ck];
	  unset($data[$ck]);
	}
      }
    } else if(is_object($data) or is_array($data)){
      foreach($ks as $ck){
	if(array_key_exists($ck,$data)){
	  $res[$ck] = $data[$ck];
	  unset($data[$ck]);
	}
      }
    } else trigger_error('Neither an array, an object');
    return(is_array($keys)?$res:$res[$keys]);
  }

  /* similar to key_extract but returns an array which is
   * usable for list
   * list($a,$b,$rest) = ops_array::extract2list($data,array('a','b'))
   */
  static function extract2list($data,$keys,$default=NULL){
    $val = array_values(self::key_extract($data,(array)$keys,$default));
    $val[] = $data;
    return $val;
  }


  /*similar to key_extract but if keys is an array
   the first key which is used will be returned
   if remove TRUE all keys will be removed from the array
   */
  static function firstkey_extract(&$data,$keys,$default=NULL,$remove=FALSE){
    $ks = is_array($keys)?$keys:array($keys);
    $res = $default; 
    $found = FALSE;
    foreach($ks as $ck){
      if($found){
	if($remove) unset($data[$ck]); else break;
      } else if(array_key_exists($ck,$data)){
	$res = $data[$ck];
	$found = TRUE;
	unset($data[$ck]);
      } 
    }
    return($res);
  }

  /* similar to key_extract but will save the results in target
   returns an named array with a numeric status for each variable: 
       0: used value from source, 1: used default, 2: used named default 3: used NULL
   source/target may be arrays or objects. 
   if objects methods like exists($name) get($name) for source  and set($name,$value) for target
      will be used id they exists; otherwise a direct access using ->$key is tried
   if source is an array an remove is true the element will be removed afterwards
   */
  static function move(&$source,&$target,$keys,$defaults=NULL,$remove=TRUE){
    $sisa = is_array($source);
    if($sisa){
      $exi = create_function('$o,$n','return(isset($o[$n]));');
      $get = create_function('$o,$n','return($o[$n]);');
    } else if(is_object($source)){
      if(method_exists($source,'get')) $get = create_function('$o,$n','return($o->get($n));');
      else                             $get = create_function('$o,$n','return($o->$n);');
      if(method_exists($source,'exists')) $exi = create_function('$o,$n','return($o->exitst($n));');
      else                                $exi = create_function('$o,$n','return(property_exists($o,$n));');
    } else die("key_move: source is neitehr a array nor an object");

    if(is_array($target)){
      $set = create_function('&$o,$n,$v','$o[$n] = $v;');
    } else if(is_object($target)){
      if(method_exists($target,'set')) $set = create_function('$o,$n,$v','$o->set($n,$v);');
      else                             $set = create_function('$o,$n,$v','$o->$n = $v;');
    } else die("key_move: target is neitehr a array nor an object");

    $ks = is_array($keys)?$keys:array($keys);
    $res = array();
    foreach($ks as $ck){
      if($exi($source,$ck)){
	$set($target,$ck,$get($source,$ck));
	$res[$ck] = 0;
	if($sisa and $remove) unset($source[$ck]);
      } else if(!is_array($defaults)){
	$set($target,$ck,$defaults);
	$res[$ck] = 1;
      } else if(array_key_exists($ck,$defaults)){
	$set($target,$ck,$defaults[$ck]);
	$res[$ck] = 2;
      } else {
	$set($target,$ck,NULL);
	$res[$ck] = 3;
      }
    }
    return(is_array($keys)?$res:$res[$keys]);
  }


  //set element only if not yet set; sideeffects, returns TRUE/FALSE
  static function setweak(&$data,$key,$value){
    if(array_key_exists($key,$data)) return(FALSE);
    $data[$key] = $value;
    return(TRUE);
  }

  /* extended set for named arrays
   * key: 
   *  is scalar -> use key/val as normal pair
   *  is array
   *    val is null: use key as named array
   *    val is array: use array_combine of key/val
   *    val is scalar: reuse val for each key in key
   *  [other] -> error
   * mode: see merge extended where data is first array
   */
  static function set_ext(&$data,$key,$val=NULL,$mode=0){
    if(is_scalar($key))      $ndat = array($key=>$val);
    else if(!is_array($key)) return trigger_error('non valid key for ops_array::set_ext');
    else if(is_array($val))  $ndat = array_combine($key,$val);
    else if(is_null($val))   $ndat = $key;
    else if(is_scalar($val)) $ndat = array_combine($key,array_fill(0,count($key),$val));
    else                     return trigger_error('non valid value for ops_array::set_ext');
    $tmp = @self::merge_ext($data,$ndat,$mode);
    if(!is_array($tmp))      return trigger_error('non valid value for ops_array::set_ext');
    $data = $tmp;
    return 0;
  }

  static function merge_preserve(){
    $args = func_get_args();
    if(count($args)==0) return array();
    $res = array_shift($args);
    foreach($args as $ca) foreach((array)$ca as $ck=>$cv) $res[$ck] = $cv;
    return $res;
  }


  /* extended merge between two array
   *  mode (odd modes are equal to the even ones with swap meaning of a/b)
   *  0: normal merge (b overwrites same keys in a)
   *  2: overwrite only if b-value is not null
   *  4: ignore a, use b
   */
  static function merge_ext($a,$b,$mode=0){
    switch($mode){
    case 0: return array_merge($a,$b); 
    case 1: return array_merge($b,$a); 
    case 2: 
      return array_merge($a,array_filter($b,create_function('$x','return !is_null($x);')));
    case 3: 
      return array_merge($b,array_filter($a,create_function('$x','return !is_null($x);')));
    case 4: return $b;
    case 5: return $a;
    }
    return trigger_error('non valid mode for ops_array::merge_ext');
    
  }


  static function swap($data){
    return(ops_array::combine(array_keys($data),array_values($data)));
  }

  // element or default, key may also be an array of keys (default should it be too)
  static function get($data,$key,$default=NULL){
    if(!is_array($data)) return $default;
    if(is_array($key)){
      $res = array();
      foreach($key as $ck) $res[$ck] = array_key_exists($ck,$data)?$data[$ck]:$default[$ck];
      return $res;
    } else return array_key_exists($key,$data)?$data[$key]:$default;
  }

  // deep get (used for array of arrays)
  static function getd($data,$key,$default=NULL){
    $code = "return(def(\$x,'$key',"  . var_export($default,TRUE) . '));';
    return(array_map(create_function('$x',$code),$data));
  }


  /* get cyclic with an numeric key */
  static function getc($data,$index){
    return($data[$index % count($index)]);
  }

  static function getn($data,$keys,$defaults=NULL){
    $ks = is_array($keys)?$keys:array($keys);
    $res = array();
    foreach($ks as $ck){
      if(array_key_exists($ck,$data))	       $res[$ck] = $data[$ck];
      else if(!is_array($defaults))            $res[$ck] = $defaults;
      else if(array_key_exists($ck,$defaults)) $res[$ck] = $defaults[$ck];
      else                                     $res[$ck] = NULL;
    }
    return(is_array($keys)?$res:$res[$keys]);
  }

  /* get the first non null value*/
  static function get_first($data,$keys,$def=NULL){
    foreach($keys as $ck) if(isset($data[$ck])) return($data[$ck]);
    return($def);
  }
  /* get key of the first non null value*/
  static function get_firstkey($data,$keys,$def=NULL){
    foreach($keys as $ck) if(isset($data[$ck])) return($ck);
    return($def);
  }

  /* sets elements
   Allowed argument combinations
    name(string) - value(any)
    array of names - array of values
    named array (name=>value)
   */
  static function set(&$data,$key,$val){
    if(!is_array($key))     $data[$key] = $val;
    else if(is_array($val)) foreach($name as $ck)     $data[$ck] = array_shift($val);
    else                    foreach($key as $ck=>$cv) $data[$ck] = $cv;
  }


  //============================================================
  // various
  //============================================================


  // renames one or more elements (may change the order!) 
  // returns number of renamed elements or FALSE; side-effects
  static function rename(&$data,$oldkey,$newkey,$overwrite=TRUE){
    if(!is_array($data)) return(FALSE);
    if(!is_array($oldkey)) $oldkey = array($oldkey);
    if(!is_array($newkey)) $newkey = array($newkey);
    if(count($oldkey)!=count($newkey)) return(FALSE);
    $nr = 0;
    for($ci=0;$ci<count($oldkey);$ci++){
      if(isset($data[$oldkey[$ci]]) and (!isset($data[$newkey[$ci]]) or $overwrite)){
	$data[$newkey[$ci]] = $data[$oldkey[$ci]];
	unset($data[$oldkey[$ci]]);
	$nr++;
      }
    }
    return($nr);
  }

  // shuffle with preserving the keys
  static function shuffle($data){
    $ak = array_keys($data);
    shuffle($ak);
    $re = array();
    foreach($ak as $ck) $re[$ck] = $data[$ck];
    return($re);
  }


  // removes multiple keys from an array, returns number of removed items
  static function remove(&$data,$keys){
    $ne = count($data);
    if(!is_array($keys)) $keys = array($keys);
    foreach($keys as $ck) unset($data[$ck]);
    return($ne - count($data));
  }

  // as preg_grep but using the keys not the values
  static function grep_key($pattern,$data,$flags=0){
    if(!is_array($data)) return($data);
    $res = array();
    foreach(preg_grep($pattern,array_keys($data),$flags) as $ck)
      $res[$ck] = $data[$ck];
    return($res);
  }

  // as preg_replace but using the keys not the values
  static function replace_key($pattern,$replacement,$data,$limit=-1){
    if(!is_array($data)) return($data);
    $res = array();
    $ak = preg_replace($pattern,$replacement,array_keys($data),$limit);
    $av = array_values($data);
    return(array_combine($ak,$av));
  }
  
  
  // sets to each element a pro- and epilog
  static function embedd($data,$prolog,$epilog){
    while(list($ak,$av)=each($data)) $data[$ak] = $prolog . $av . $epilog;
    return($data);
  }

  // ============================================================
  // Variations and extensions of implode =======================
  //============================================================

  /* similar to normal implode:
   + if arr is an object it will uses get_object_vars
   + if arr is neither an array nor an object it will return $arr directly
   + recursive if an element is once again an array
   + uses strval to prevent warnings
   + allows to inlcude key using keysep
  */
  static function implode($sep,$arr,$keysep=NULL){
    $res = '';
    if(is_object($arr)) $arr = get_object_vars($arr);
    else if(!is_array($arr)) return($arr);
    if(count($arr)==0) return('');
    foreach($arr as $key=>$val){
      $res .= $sep;
      if(!is_null($keysep)) $res .= $key . $keysep;
      $res .= (is_array($val) or is_object($val))?self::implode($sep,$val,$keysep):strval($val);
    }
    return(substr($res,strlen($sep)));
  }

  /* extended implode where sep is a named array
     whereas the keys of sep defines where the text of it is used
     if sep is not an array it will used as like array('is'=>...)
       possible items (other are ignored)
       kh -> header for the keys
       kf -> footer for the key
       kv -> separator between key and value
       vh -> header for the values
       vf -> footer for the values
       ih -> header for the item (key + value)
       if -> footer for the item (key + value)
       is -> separator between items
       gh -> global header (all items together)
       gf -> global footer (all items together)
       na -> returned if data is not an array (default: NULL)
       ea -> returned if data is an empty array (count==0)

     keys of data are not used if non of (kv, kh, kf) is defined in sep (all are null)
     values of data are not used if non of (kv, vh, vf) is defined in sep (all are null)
     the result is an array if non of (is, gh, gv) is defined (all are null)

     Example for a full structer if everything is set
       [gh][ih][kh]KEY1[kf][kv][vh]VALUE1[vf][if][is][ih][kh]KEY2[kf][kv][vh]VALUE2[vf][if][gf]
  */
  static function eimplode($data,$sep=array(),$recursive=TRUE){ 
    if(!is_array($sep)) $sep = array('is'=>$sep);
    if($recursive) $osep = $sep;

    // is it an array??
    if(is_object($data)) {
      $data = get_object_vars($data);
    } else if(!is_array($data)){
      return(isset($sep['na'])?$sep['na']:NULL);
    } else if(isset($sep['na'])) {
      unset($sep['na']);
    }

    // empty array and ea set
    if(isset($sep['ea'])){
      if(count($data)==0) return($sep['ea']);
      else unset($sep['ea']);
    }

    //clean up separators
    $keys = array('kh','kf','kv','vh','vf','ih','if','is','gh','gf');
    foreach($keys as $ck) if(!array_key_exists($ck,$sep)) $sep[$ck] = null; // set missing sep to NULL
    $mode = 0; // binary value:which options are set in sep!
    for($ci=0;$ci<10;$ci++) if(!is_null($sep[$keys[$ci]])) $mode += pow(2,$ci); else $sep[$keys[$ci]] = '';
    $nd = count($data);
    $ak = array_keys($data);
    $av = array_values($data);
    $res = array();
    //embedd keys and values itself if necessary
    if(($mode &  3)!=0) 
      for($ci=0;$ci<$nd;$ci++) 
	$ak[$ci] = $sep['kh'] . $ak[$ci] . $sep['kf'];
    if(($mode & 24)!=0) {
      for($ci=0;$ci<$nd;$ci++) {
	if((is_array($av[$ci])  or is_object($av[$ci])) and $recursive==TRUE)
	  $av[$ci] = $sep['vh'] . self::eimplode($av[$ci],$osep,TRUE) . $sep['vf'];
	else
	  $av[$ci] = $sep['vh'] . strval($av[$ci]) . $sep['vf'];
      }
    } else if($recursive==TRUE){
      for($ci=0;$ci<$nd;$ci++)
	if(is_array($av[$ci]) or is_object($av[$ci]))
	  $av[$ci] = self::eimplode($av[$ci],$osep,TRUE);
    }
      
    // key or values or both used?
    if(($mode & 7)==0)       $res = $av;
    else if(($mode & 28)==0) $res = $ak;
    else                     for($ci=0;$ci<$nd;$ci++) $res[$ci] = $ak[$ci] . $sep['kv'] . $av[$ci];
    // embedd items
    if(($mode & 96)!=0) for($ci=0;$ci<$nd;$ci++) $res[$ci] = $sep['ih'] . $res[$ci] . $sep['if'];
    //global implode
    if(($mode & 896)!=0) $res = $sep['gh'] . implode($sep['is'],$res) . $sep['gf']; // implode??
    return($res);
  }
  

  // ------------------------------------------------------------




  //============================================================
  // enhaned functions
  //============================================================



  /*
   sets value not given in data to default value given in def
   both are named arrays, where def should only contain string-keys and data may
   use string and numerical keys.

   first the string keys are used to match the elements. Aftwerwards the remaining elements of
   data will be matched to the remaining elements in def (using the numerical key)

   mode
     0: flat (default)
     1: default value is used and it is an array it will got the first element of it
     2: recursiv call if both values in data and def are arrays

   remove: if true values in data with keys not used in def will be removed
  */

  static function setdefault($data,$def=array(),$mode=0,$remove=TRUE){
    $res = array(); // will contain the final result

    // set those which have the same key in value and def
    foreach(array_intersect(array_keys($def),array_keys($data)) as $ck){
      if(is_array($data[$ck]) and is_array($def[$ck]) and $mode==2) 
	$res[$ck] = ops_array::setdefault($data[$ck],$def[$ck],2,$remove);
      else 
	$res[$ck] = $data[$ck];
      unset($data[$ck]);
      unset($def[$ck]);
    }

    // replace numeric keys by those from def and set (if asked) other data in res
    $kd = array_keys($def);
    foreach(array_keys($data) as $ck) {
      if(is_numeric($ck)) {
	$res[$kd[$ck]]= $data[$ck]; 
	unset($def[$kd[$ck]]);
      } else if(!$remove) $res[$ck] = $data[$ck];
    }

    // move the remainig default values
    if($mode==1) while(list($ak,$av)=each($def)) if(is_array($av)) $def[$ak] = array_shift($av);
    $res = array_merge($res,$def);
    return($res);
  }


  static function filter_val($arr,$key,$val){
    if(!is_array($arr)) return(array());
    $res = array();
    foreach($arr as $ck=>$cv)
      if(def($cv,$key,NULL)==$val)
	$res[$ck] = $cv;
    return($res);    
  }


  /* Blow array up to find all sub elements
   * $arr defines a (directed) graph
   *   array(keyA=>array(eleA1,eleA2,...), keyB=>...)
   * where the eleX may point to an existing keyY
   * the results  array (keyA=>resA,keyB=>resB, ...)
   * where the res is an array with all elements belwo the key
   * the order inside the res is not defined
   * mode: 0: result includes the 'main' key too
   *       1: results includes sub elements only
   *       2: results includes only the elements at the bottom
   */
  static function blowup($arr,$mode=1){
    $keys = array_keys($arr);
    do {
      $found = FALSE;
      foreach($keys as $key){
	$tmp = $arr[$key];
	foreach($arr[$key] as $ck) $tmp = array_merge($tmp,def($arr,$ck,array()));
	$tmp =  array_unique($tmp);
	if(count($tmp)!=count($arr[$key])) {
	  $found = TRUE;
	  $arr[$key] = array_values($tmp);
	}
      }
    } while($found);
    switch($mode){
    case 0: foreach($keys as $key) $arr[$key][] = $key; break;
    case 2: foreach($keys as $key) $arr[$key] = array_values(array_diff($arr[$key],$keys)); break;
    }
    return $arr;
  }


  static function sprinta($frmt,$data){
    foreach($data as $ck=>$cv)
      $frmt = str_replace('%' . $ck . '%',$cv,$frmt);
    return $frmt;
  }

  static function sprintaf($frmts,$data){
    foreach((array)$frmts as $frmt){
      foreach($data as $ck=>$cv)
	$frmt = str_replace('%' . $ck . '%',$cv,$frmt);
      if(strpos($frmt,'%')===FALSE) return $frmt;
    }
    return NULL;
  }
  }

?>
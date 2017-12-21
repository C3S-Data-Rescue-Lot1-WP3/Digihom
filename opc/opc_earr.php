<?php
include_once('ops_array.php');
class opc_earr {
  var $data = array(); // data array
  var $key = null; // current key
  var $pos = null; // current pos

  /*

   slice etc ausbauen

   std arguments ============================================================
       data: null: uses internal data, depending on function the result is saved back
             true: uses internal data and saves the result back (not allways possible)
             array: will be used instead of the internal data
             
	     sometime true is allowed which mean internal data is used
       keys: one ar more keys (as array) using the name directly or patterns
       $pos/$length: <0 counts from the end where -1 is the last element!
       regmode
            grep                       replace
         0: match data return array    match and change data
	 1: match keys return array    match and change keys
	 2: match data return keys
	 3: match keys return keys



     constructer
       opc_earr(array())
     iterator
       iter($how='next',$key=null,$set=true)
       iter_key(...) := same as iter
       iter_val(...) := returns value if possible or null
       iter_keyval(...) := returns array(key,value) value if possible or null
 
  */
  // Constructer ------------------------------------------------------------
  function opc_earr($arr = array()){
    if(!is_array($arr)) $arr = array($arr);
    $this->data = $arr;
    if(count($arr)>0){
      $ak = array_keys($arr);
      $this->key = $ak[0];
      $this->pos = 0;
    } else {
      $this->key = null;
      $this->pos = null;
    }
  }

  // Iteration ------------------------------------------------------------
  function get_curpos(){
    return $this->pos;
  }

  function set_curpos($npos){
    $nk = count($this->data);
    if($npos<0) $npos = $nk + $npos;
    if($npos>=$nk) return false;
    $this->pos = $npos;
    $ak = array_keys($this->data);
    $this->key = $ak[$npos];
    return true;
  }

  function get_curkey(){
    return $this->key;
  }

  function set_curkey($newkey){
    $ak = array_keys($this->data);
    $pos = array_search($newkey,$ak);
    if($pos===false) return(false);
    $this->pos = $pos;
    $this->key = $newkey;
    return(true);
  }

  function get_keypos($key=null){
    $ak = array_keys($this->data);
    if(is_null($key)) {// use current key
      if($ak[$this->pos]==$this->key) // test if pos is still valid
	return(array($this->key,$this->pos)); 
      $key = $this->key;
    }
    $pos = array_search($key,$ak);
    if($pos===false) return(false);
    return(array($key,$pos));
  }

  // short for iter_key
  function iter($how='next',$key=null,$set=true){
    return($this->iter_key($how,$key,$set));
  }

  function iter_key($how='next',$key=null,$set=true){
    $nk = count($this->data);    if($nk==0) return(false);
    $ak = array_keys($this->data); 
    list($key,$pos) = $this->get_keypos($key);
    switch($how){
    case 'this':  break;
    case 'first': $pos = 0; break;
    case 'last':  $pos = $nk-1; break;
    case 'next':  $pos++; break;
    case 'prev':  --$pos; break;
    default: 
      if(is_numeric($how)) $pos += $how;
      else                 return(false);
    }
    if($pos<0) return(false);
    if($pos>=$nk) return(false);
    if($set){
      $this->pos = $pos;
      $this->key = $ak[$pos];
    }
    return($ak[$pos]);
  }

  function iter_val($how='next',$key=null,$set=true){
    $ck = $this->iter($how,$key,$set);
    if(is_null($ck)) return(null); else return($this->data[$ck]);
  }

  function iter_keyval($how='next',$key=null,$set=true){
    $ck = $this->iter($how,$key,$set);
    if(is_null($ck)) return(null); else return(array($ck,$this->data[$ck]));
  }


  // Set and Get ============================================================

  /* returns an array of keys
     null -> all
     string/array(strings....)
       string is a key directly or a pattern as used in perg_grep
   */
  function keys($keys=null,$data=null){
    $arr = is_null($data)?$this->data:$data;

    if(is_null($keys)) return(array_keys($arr));
    $res = array();
    if(!is_array($keys)) $keys = array($keys);
    foreach($keys as $key){
      if(array_key_exists($key,$arr)) {
	$res[] = $key;
      } elseif(preg_match('|^/.*/$|',$key) or preg_match('/^|.*|$/',$key)){
	$ck = @preg_grep($key,array_keys($arr));
	if(is_array($ck) and count($ck)>0) $res = array_merge($res,$ck);
      }
    }
    return(array_unique($res));    
  }


  // get one (or more) element and return its value or def if it is not defined
  function get($key=null,$def=null,$data=null){
    $arr = is_null($data)?$this->data:$data;
    if(is_array($key)){
      $res = array();
      foreach($key as $ck) $res[$ck] = $this->get($ck,$def,$arr);
      return($res);
    } else if(is_null($key)) {
      $key = $this->key;
    } else {
      return(array_key_exists($key,$arr)?$arr[$key]:$def);
    }
  }
  

  // set one element
  function set($key,$value){
    if(is_null($key)) $key = $this->key;
    $this->data[$key] = $value; 
    return(true);
  }
  
  // set one element and return the old value or NULL
  function getset($key,$value){
    if(is_null($key)) $key = $this->key;
    $res = $this->get($key,NULL);
    $this->data[$key] = $value; 
    return($res);
  }
  

  // adds/set an array to the current one
  function setn($arr){
    $this->data = array_merge($this->data,$arr);
  }


  // gets more than one element; returns array; see keys() for details
  function getn($keys,$def=array(),$data=null){
    $arr = is_null($data)?$this->data:$data;
    $keys = $this->keys($keys,$arr);
    foreach($keys as $key) $def[$key] = $arr[$key];
    return($def);
  }
  

  // *bypos ------------------------------------------------------------
  // uses nuemrical position which directly counts the elments
  // ignoring if the keys are strings or numerical

  // pos also allows arrays
  function getkeybypos($pos=0,$data=null){
    $ak = array_keys(is_null($data)?$this->data:$data); 
    $nk = count($ak);
    if(is_array($pos)){
      $re = array();
      foreach($pos as $cp) $re[] = $ak[$cp+($cp<0?$nk:0)];
    } else{
      $re = $ak[$pos+($pos<0?$nk:0)];
    }
    return($re);
  }

  // pos also allows arrays
  function getbypos($pos,$data=null){
    $arr = is_null($data)?$this->data:$data;
    $ak = array_keys($arr); 
    $nk = count($ak);
    if(is_array($pos)){
      $re = array();
      foreach($pos as $cp) 
	 if($cp<0)
	   $re[$ak[$cp+$nk]] = $arr[$ak[$cp+$nk]];
	 else
	   $re[$ak[$cp]] = $arr[$ak[$cp]];
    } else {
      $re = $arr[$ak[$pos+($pos<0?$nk:0)]];
    }
    return($re);
  }

  // returns if data is nll the replaced value otherwise the whole new array
  function setbypos($pos,$value,$data=null){
    $arr = is_null($data)?$this->data:$data;
    $ak = array_keys($arr); 
    $nk = count($ak);
    if($pos<0) $pos += $nk;
    $ck = $ak[$pos];
    $re = $arr[$ck];
    $arr[$ck] = $value;
    if(is_null($data)) {
      $this->data = $arr;
      return($re);
    } else return($arr);
  }

  

  /* removes a single item
     if data is null, save the data and returns the removed value
     if data is an array it returns the result
  */
  function del($key=null,$data=null){
    $arr = is_null($data)?$this->data:$data;
    if(is_null($key)) $key = $this->key;
    if(array_key_exists($key,$arr)) {
      $re = $this->data[$key];
      unset($arr[$key]);
    } else $re = null;
    if(!is_null($data)) return($arr);
    $this->data = $arr;
    return($re);
  }
  

  /* removes multiple items
     if data is null, save the data and returns the removed value
     if data is an array it returns the result
  */
  function deln($keys,$data=null){
    $arr = is_null($data)?$this->data:$data;
    $keys = $this->keys($keys,$arr);
    $re = array();
    foreach($keys as $key){
      $re[$key] = $arr[$key];
      unset($arr[$key]);
    }
    if(!is_null($data)) return($arr);
    $this->data = $arr;
    return($re);
  }


  // regex functions ============================================================

  function grep($pattern,$regmode=0,$data=null){
    $arr = is_null($data)?$this->data:$data;
    switch($regmode){
    case 0: return(preg_grep($pattern,$arr));
    case 1: 
      $res = array();
      $ak = preg_grep($pattern,array_keys($arr));
      foreach($ak as $ck) $res[$ck] = $arr[$ck];
      return($res);
    case 2: return(array_keys(preg_grep($pattern,$arr)));
    case 3: return(preg_grep($pattern,array_keys($arr)));
    }    
    return(null);
  }
  
  // data = true allowed
  function replace($pattern,$replace,$regmode=0,$data=null){
    $arr = !is_array($data)?$this->data:$data;
    switch($regmode){
    case 0: $res = preg_replace($pattern,$replace,$arr); break;
    case 1:
      $ak = preg_replace($pattern,$replace,array_keys($arr));
      $res = $this->combine($ak,array_values($arr),false);
      break;
    }
    if(!is_array($data) and $data===true) $this->data = $res;
    return($res);
  }

  // misc ============================================================
  // is defined in php5 but not yet in php 4
  function combine($keys,$values,$save=false){
    $re = array();
    $values = array_values($values); $keys = array_values($keys);
    $nk = count($keys); $nv = count($values);
    if($nk==0 or $nv==0) return(null);
    for($ci=0;$ci<$nk;$ci++){
      $re[$keys[$ci]] = $values[$ci % $nv];
    }
    if($save) $this->data = $re;
    return($re);	
  }

  // inserts array arr after key (numerical or string)
  function insert($arr,$key){
    $dk = array_keys($this->data); 
    $dv = array_values($this->data);
    $dn = count($dk);
    if(!is_numeric($key)){
      $key = array_keys($dk,$key);
      if($key==false) $key = $nd-1; else $key = $key[0];
    } else $key = $key;
    $nd = array();
    for($ci=0;$ci<=$key;$ci++) $nd[$dk[$ci]] = $dv[$ci];
    while(list($ak,$av)=each($arr)) $nd[$ak] = $av;
    for(;$ci<$dn;$ci++) $nd[$dk[$ci]] = $dv[$ci];
    $this->data = $nd;
  }

  /* array_slice with preserving keys
   allows true for data (saves resukt back)
   specials: 
    negative values: -1 -> last element; -2 -> second last element
    offset is array 
     and length is null -> offset contains the numerical position of the asked items (-1 = last key)
     and length is not null -> offset contains the keys of the asked items
   */
  function slice($offset=0,$length=null,$data=null){
    $arr = !is_array($data)?$this->data:$data;
    $nk = count($arr); 
    if(is_array($offset)){
      $re = array();
      if(is_null($length)){
	$ak = array_keys($arr); 
	$av = array_values($arr);
	foreach($offset as $ck){
	  if(is_numeric($ck) and $ck<0) $ck += $nk;
	  $re[$ak[$ck]] = $av[$ck];
	}
      } else {
	foreach($offset as $ck) $re[$ck]= isset($arr[$ck])?$arr[$ck]:NULL;
      }
      if(!is_array($data) and $data===true) $this->data = $re;
      return($re);
    } 
    if($offset<0) $offset += $nk;
    if(is_null($length)){
      $ak = array_slice(array_keys($arr),$offset);
      $av = array_slice($arr,$offset);
    } else {
      if($length<0) $length = max(0, $length + 1 + $nk - $offset);
      $ak = array_slice(array_keys($arr),$offset,$length);
      $av = array_slice($arr,$offset,$length);
    }
    $ar = $this->combine($ak,$av);
    if(!is_array($data) and $data===true) $this->data = $re;
    return($ar);
  }



  // as array_splice with key preserving special like array_slice (no $rep allowed)
  function splice(&$arr,$offset=0,$length=null,$rep=null){
    $ok = array_keys($arr);
    $ov = array_values($arr);
    if(is_array($offset)){
      $re = array();
      if(is_null($length)){
	$nk = count($arr); 
	$ak = array_keys($arr); 
	$av = array_values($arr);
	foreach($offset as $ck){
	  if(is_numeric($ck) and $ck<0) $ck += $nk;
	  $re[$ak[$ck]] = $av[$ck];
	  unset($arr[$ak[$ck]]);
	}
      } else {
	foreach($offset as $ck) {$re[$ck] = $arr[$ck]; unset($arr[$ck]);}
      }
      return($re);
    } 
    if(!is_numeric($offset)) {
      $offset = array_search($offset,$ok);
      if($offset===FALSE) $offset = 0; else $offset++;
    }
    if(is_null($length)){
      $ak = array_splice($ok,$ov,$offset);
      $av = array_splice($ov,$offset);
    } else if(is_null($rep)){
      $ak = array_splice($ok,$offset,$length);
      $av = array_splice($ov,$offset,$length);
    } else {
      $ak = array_splice($ok,$offset,$length,array_keys($rep));
      $av = array_splice($ov,$offset,$length,array_values($rep));
    }
    $arr = array_combine($ok,$ov);
    return count($av)>0?array_combine($ak,$av):array();
  }

  // like array_splice but with data as source (and internal sink)
  function splice_data($offset=0,$length=null,$rep=null){
    return $this->splice($this->data,$offset,$length,$rep);
  }


  function flush($arr=array()){
    if(is_Object($arr) and 
       (get_class($arr)=='opc_earr' or is_subclass_of($arr,'opc_earr'))){
      $this->data = $arr->data;
      $this->key = $arr->key;
      $this->pos = $arr->pos;
    } else {
      if(!is_array($arr)) $arr = array($arr);
      $this->data = $arr;
      if(count($arr)==0){
	$this->key = null;
	$this->pos = null;
      } else {
	$ak = array_keys($arr);
	$this->key = $ak[0];
	$this->pos = 0;
      }
    }
    return($this->data);
  }

  function reverse(){$this->data = array_reverse($this->data,true); return($this->data);}
  function values(){$this->flush(array_values($this->data)); return($this->data);}


}

?>
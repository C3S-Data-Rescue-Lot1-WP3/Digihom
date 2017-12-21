<?php
/* ================================================================================
 baisc composite
 ================================================================================ */
abstract class opc_itemc extends opc_item implements ArrayAccess, Countable, Iterator{
  static public $val_init = array();
  public $accept = array('null'=>1,'array'=>1);
  
  function _get(&$key){
    switch($key){
    case 'size': $key = $this->count();          return TRUE;
    case 'keys': $key = $this->keys($this->val); return TRUE;
    }
    return parent::_get($key);
  }
}


/* ================================================================================
 standard Array
 ================================================================================ */
class opc_item_array extends opc_itemc{  
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = array();
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  public $accept = array('null'=>1,'array'=>1);
  
  function keys() {return array_keys($this->val);}
  function count(){return count($this->val);}

  public function rewind()  { reset($this->val); }
  public function key()     { return key($this->val);}
  public function next()    { return next($this->val);}
  public function current() { return current($this->val);}
  public function valid()   { return $this->current() !== false;}

  function offsetExists($pos)     {
    if(!is_array($pos)) return isset($this->val[$pos]); 
    foreach($pos as $cp) if(!isset($this->val[$cp])) return FALSE;
    $this->TRUE;
  }
  function offsetGet($pos){
    if(!is_array($pos)) return $this->val[$pos];  
    $res = array(); 
    foreach($pos as $cp) $res[$cp] = def($this->val,$cp,$this->ele_default);
    return $res;
  }
  function offsetSet($pos, $value){
    if(is_array($pos)) foreach($pos as $cp) $this->val[$cp] = $value;
    else $this->val[$pos] = $value;
    $this->isset = TRUE;
    $this->_ok();
  }
  function offsetUnset($pos){
    if(is_array($pos)) foreach($pos as $cp) unset($this->val[$cp]);
    else unset($this->val[$pos]);
  }

  function imp_array($arr,$mode=NULL){
    if(!is_array($arr)) return $this->_err(53);
    $this->val = $mode=='add'?array_merge($this->val,$value):$value;
    $this->isset = TRUE;
    return $this->_ok();
  }


}






/*================================================================================
 Vector
 ================================================================================*/
/* similar to array but
 + only integer-keys (1,2 ...) are allowed
 + size restriction possible
 + default value (for not yet set fields)
*/

class opc_item_vector extends opc_itemc{
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = array();
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~


  public $ele_default = NULL;
  protected $exp_mode_default = 'array';
  public $accept = array('integer'=>2,'array'=>1);

  protected $size = 0;
  protected $pos = 1;
  
  function check_integer($data){return $data>=0;}

  function _init_msgs(){
    $msg = array(50=>'negative size not allowed',
		 51=>'invalid key (not an integer or out of range)',
		 53=>'set allows only arrays',
		 );
    $res = parent::_init_msgs();
    foreach($msg as $key=>$val) $res[$key] = $val;
    return $res;
  }

  function set_array($value){
    $this->size = count($value);
    if($this->size==0) $this->val = array();
    else $this->val = array_combine(range(1,$this->size),array_values($value));
    return $this->_ok();
  }
  
  // empty vector of asked size
  function set_integer($value){
    if($value<0) return $this->_err(50);
    $this->size = $value;
    $this->val = array();
    return $this->_ok();
  }

  /* proofs the position
   returns NULL (invalid) FALSE (valid but not used) TRUE (valid and used)
   limit = 0: as in set['limit']
   limit = 1: dont check
   limit = 2: check allways
  */
  function check_pos($pos,$limit=0){
    if(is_string($pos) and ctype_digit($pos)) $pos = (int)$pos;
    if(!is_int($pos)) return NULL;
    if($pos<1) return NULL;
    if($pos<=$this->size) return isset($this->val[$pos]);
    switch($limit){
    case 0: return def($this->set,'limit',FALSE)==TRUE?NULL:FALSE;
    case 2: return NULL;
    }
    return FALSE;
  }

  /* Array Access ============================================================
   pos may also be an array of multiple positions
    get: returns an array with all items, status is allways 0 afterwards
    unset: status is o or -1 (if none was removed)
    set: will save the same value to all asked items, status is allways 0
    exists: returns TRUE if all of them exists FALSE otherwise
    
 */

  function offsetExists($pos) {
    if(!is_array($pos)) return !is_null($this->check_pos($pos)); 
    foreach($pos as $cp) if(is_null($this->check_pos($cp))) return FALSE;
    return TRUE;
  }

  function offsetUnset($pos)      {
    if(is_array($pos)){
      $nu = 0;
      foreach($pos as $cp) if(isset($this->val[$cp])) unset($this->val[$cp]); else $nu++;
      return $this->_okC($nu==count($pos)?-1:0);
    } else {
      if($this->check_pos($pos)!==TRUE) return $this->_okC(-1);
      unset($this->val[$pos]);
      return $this->_ok();
    }
  }
  function offsetGet($pos)        {
    if(is_array($pos)){
      $res = array();
      foreach($pos as $cp){
	$stat = $this->check_pos($cp);
	if($stat===TRUE)       $res[$cp] = $this->val[$cp]; 
	else if($stat===FALSE) $res[$cp] = $this->ele_default; 
      }
      $this->_ok();
      return $res;
    } else {
      if(is_null($this->check_pos($pos))) return $this->_err(51);
      $this->_ok();
      return def($this->val,$pos,$this->ele_default);
    }
  }

  function offsetSet($pos, $value){
    if(is_array($pos)){
      foreach($pos as $cp) if(!is_null($this->check_pos($cp))) $this->val[$cp] = $value;
    } else {
      if(is_null($this->check_pos($pos))) return $this->_err(51);
      $this->val[$pos] = $value; 
      if($pos>$this->size) $this->size=$pos;
    }
    $this->isset = TRUE;
    return $this->_ok();
  }

  function count(){ return $this->size;}
  function keys() { return range(1,$this->size,1);}


  public function rewind()  { $this->pos = 1; }
  public function key()     { return $this->pos;}
  public function valid()   { return !is_null($this->check_pos($this->pos,2));}
  public function current() { return def($this->val,$this->pos,$this->ele_default);}
  public function next()    { 
    if(is_null($this->check_pos($this->pos+1))) return NULL;
    $this->pos++;
    return def($this->val,$this->pos,$this->ele_default);
  }

  function imp_array($arr,$mode=NULL){
    if(!is_array($arr)) return $this->_err(53);
    $res = $mode=='add'?$this->val:array();
    foreach($arr as $key=>$val){
      if(is_null($this->check_pos($key,1))) return $this->_err(array(51,$kex));
      $res[(int)$key] = $val;
    }
    $this->val = $res;
    $this->isset = TRUE;
    $this->size_recalc();
    return $this->_ok();
  }

  function exp_array($submode=NULL){
    if(is_null($this->val)) return array();
    $res = $this->size>0?array_fill(1,$this->size,$this->ele_default):array();
    foreach($this->val as $key=>$val) $res[$key] = $val;
    return $res;
  }

  function isused($pos){ 
    if(!is_array($pos)) return $this->check_pos($pos);
    $res = array();
    foreach($pos as $cp) $res[$cp] = $this->check_pos($cp);
    return $res;
  }

  function size_recalc(){
    $this->size = max(array_keys($this->val));
  }
}



















/*================================================================================
 Matrix
 ================================================================================*/
/* 2dim of vector 
 pos is a integer (->row) array of to integer (row/col) or a string "row/col"
*/

class opc_item_matrix extends opc_itemc{
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = array();
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~


  public $ele_default = NULL;
  protected $exp_mode_default = 'array';
  public $accept = array('integer'=>2,'array'=>1);

  protected $size = 0;
  protected $rsize = 0;
  protected $csize = 0;
  protected $pos = array(1,1);
  
  function _get(&$key){
    switch($key){
    case 'size': $key = $this->rsize*$this->csize;       return TRUE;
    case 'dim': $key = array($this->rsize,$this->csize); return TRUE;
    case 'rowsize': $key = $this->rsize;                 return TRUE;
    case 'colsize': $key = $this->csize;                 return TRUE;
    case 'keys': $key = $this->keys($this->val);         return TRUE;
    }
    return parent::_get($key);
  }

  function _init_msgs(){
    $msg = array(50=>'negative size not allowed',
		 51=>'invalid key (not an integer or out of range)',
		 53=>'set allows only arrays for rows',
		 54=>'set allows only 2dim-arrays',
		 );
    $res = parent::_init_msgs();
    foreach($msg as $key=>$val) $res[$key] = $val;
    return $res;
  }


  /* 2-dim array (of NULL, arrays or oi_vector) or array with 2 integers (dim) */
  function set_array($value){
    $value = array_values($value);
    $this->val = array();
    if(count($value)==2 and is_int($value[0]) and is_int($value[1])){
      if($value[0]<1 or $value[1]<1) return $this->_err(50);
      $this->rsize = $value[0];
      $this->csize = $value[1];
    } else {
      $cr = 0;
      $this->rsize = 0;
      $this->csize = 0;
      foreach($value as $crow){
	$ne = $this->_set_row(++$cr,$crow);
	if($ne===FALSE) return $this->_err(54);
	if($ne>$this->csize) $this->csize = $ne;
      }
      $this->rsize = $cr;
    }
    return $this->_ok();
  }
  // sub used by set_array
  protected function _set_row($row,$value){
    if(is_null($value)) {
      return 0;
    } else if(is_array($value)) {
      $ne = count($value);
      if($ne>0) $this->val[$row] = array_combine(range(1,$ne),$value);
      return $ne;
    } else if(is_object($value) and $value instanceof opc_item_vector){
      $this->val[$row] = $value->get();
      return $value->size;
    } else return FALSE;
  }

  // cretaes an empty  square-matrix
  function set_integer($value){ return $this->set_array(array($value,$value));}

  /* proofs the position
   returns NULL (invalid) FALSE (valid but not used) TRUE (valid and used)
   limit = 0: as in set['limit']
   limit = 1: dont check
   limit = 2: check allways
  */
  function check_pos(&$pos,$limit=0){
    if(is_string($pos)){
      if(ctype_digit($pos)) 
	$pos = (int)$pos;
      else if(preg_match('|^ *\+?\d+ */ *\+?\d+ *$|',$pos)) 
	$pos = explode('/',$pos);
      else 
	return NULL;
    }
    if(is_int($pos)){
      $res = $this->check_onepos($pos,TRUE,$limit);
      if($res===FALSE) return NULL;
      return isset($this->val[$pos]);
    }
    if(!is_array($pos) or count($pos)!=2) return NULL;
    if($this->check_onepos($pos[0],TRUE,$limit)===FALSE) return NULL;
    if($this->check_onepos($pos[1],FALSE,$limit)===FALSE) return NULL;
    if(!isset($this->val[$pos[0]])) return FALSE;
    return isset($this->val[$pos[0]][$pos[1]]);
  }

    // checks one of the two dimensions returns T/F for vaild or not
  function check_onepos($pos,$row=TRUE,$limit=0){
    if(is_string($pos) and ctype_digit($pos)) $pos = (int)$pos;
    if(!is_int($pos)) return FALSE;
    if($pos<1) return FALSE;
    if($pos<=($row?$this->rsize:$this->csize)) return TRUE;
    switch($limit){
    case 0: return !def($this->set,'limit',FALSE);
    case 2: return FALSE;
    }
    return TRUE;
  }

  /* Array Access ============================================================
   pos may also be an array of multiple positions
    get: returns an array with all items, status is allways 0 afterwards
    unset: status is o or -1 (if none was removed)
    set: will save the same value to all asked items, status is allways 0
    exists: returns TRUE if all of them exists FALSE otherwise
    
 */

  function offsetExists($pos) {return !is_null($this->check_pos($pos)); }

  function offsetUnset($pos)      {
    if($this->check_pos($pos)!==TRUE) return $this->_okC(-1);
    if(is_array($pos)){
      unset($this->val[$pos[0]][$pos[1]]);
      if(count($this->val[$pos[0]])==0) unset($this->val[$pos[0]]);
    } else unset($this->val[$pos]);
    return $this->_ok();
  }
  
  function offsetGet($pos){
    $iss = $this->check_pos($pos);
    if(is_null($iss))  {qm();return $this->_errM(51,strval($pos));}
    $this->_ok();
    if(is_array($pos)) return $iss==TRUE?$this->val[$pos[0]][$pos[1]]:$this->ele_default;

    // return a complete row as oi_vector
    $res = new opc_item_vector($this->csize);
    if(def($this->set,'limit',FALSE)==TRUE) $res->set_settings(array('limit'=>TRUE));
    if($iss===TRUE) $res->imp($this->val[$pos]);
    return $res;
  }

  // TUDU cp
  function offsetSet($pos, $value){
    $iss = $this->check_pos($pos);
    if(is_null($iss))  return $this->_err(51);
    if(is_array($pos)){
      if(!isset($this->val[$pos[0]])) $this->val[$pos[0]] = array();
      $this->val[$pos[0]][$pos[1]] = $value;
      if($pos[0]>$this->rsize) $this->rsize = (int)$pos[0];
      if($pos[1]>$this->csize) $this->csize = (int)$pos[1];
    } else {
      $res = $this->_set_row($pos,$value);
      if($res===FALSE) return $this->_err(53);
      if($res>$this->csize) $this->csize = (int)$res;
      if($pos>$this->rsize) $this->rsize = (int)$pos;
    }
    $this->isset = TRUE;
    return $this->_ok();
  }

  function count(){ return $this->rsize*$this->csize;}
  function keys() { return array(range(1,$this->rsize,1),range(1,$this->csize,1));}
  

  public function rewind()  { $this->pos = array(1,1); }
  public function key()     { return implode('/',$this->pos);}
  public function valid()   { return !is_null($this->check_pos($this->pos,2));}
  public function current() { 
    $iss = $this->check_pos($this->pos);
    if(is_null($iss)) return $this->_err(51);
    if($this->check_pos($this->pos))
      return $this->val[$this->pos[0]][$this->pos[1]];
    else
      return $this->ele_default;
  }
  public function next()    { 
    if($this->pos[1]==$this->csize) {
      $this->pos[0]++; 
      $this->pos[1] = 1;
    } else  $this->pos[1]++; 
    if($this->pos[0]>$this->rsize) return NULL;
    if($this->check_pos($this->pos))
      return $this->val[$this->pos[0]][$this->pos[1]];
    else
      return $this->ele_default;
  }

  function imp_array($arr,$mode=NULL){
    if(!is_array($arr)) return $this->_err(53);
    $res = $mode=='add'?$this->val:array();
    foreach($arr as $row=>$val){
      if($this->check_onepos($row,TRUE,1)==FALSE) return $this->_err(array(51,$row));
      $cres = array();
      if(is_array($val)){
	foreach($val as $col=>$cv){
	  if($this->check_onepos($col,FALSE,1)==FALSE) return $this->_err(array(51,$col));
	  $cres[$col] = $cv;
	}
      } else if(is_null($val)){
	continue;
      } else if(is_object($val) and $val instanceof opc_item_vector){
	$cres = $val->get();
      } else return $this->_err(53);
      $res[$row] = $cres;
    }
    $this->val = $res;
    $this->isset = TRUE;
    $this->size_recalc();
    return $this->_ok();
  }

  function exp_array($submode=NULL){
    $res = array();
    for($ii=1;$ii<=$this->rsize;$ii++){
      if(isset($this->val[$ii])){
	$cres = array();
	for($jj=1;$jj<=$this->csize;$jj++) 
	  $cres[$jj] = def($this->val[$ii],$jj,$this->ele_default);
	$res[$ii] = $cres;
      } else $res[$ii] = array_fill(1,$this->csize,$this->ele_default);
    }
    return $res;
  }

  function isused($pos){ 
    return $this->check_pos($pos)===TRUE;
  }

  function size_recalc(){
    $ak = array_keys($this->val);
    if(count($ak)>0){
      foreach($ak as $ck) if(count($this->val[$ck])==0) unset($this->val[$ck]);
      $this->rsize = max(array_keys($this->val));
      $this->csize = max(array_map(create_function('$x','return max(array_keys($x));'),$this->val));
    } else {
      $this->rsize = 0;
      $this->csize = 0;
    }
  }

}



?>
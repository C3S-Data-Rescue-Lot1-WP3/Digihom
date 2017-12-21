<?php

interface opi_object{
  function add($data=NULL);
}

/* ================================================================================
 baisc object
 ================================================================================ */
abstract class opc_itemo extends opc_item implements opi_object{
  static public $val_init = array();
  public $accept = array('null'=>1);

  function _init_msgs(){
    $msg = array(50=>'invalid value',
		 51=>'invalid key',
		 );
    $res = parent::_init_msgs();
    foreach($msg as $key=>$val) $res[$key] = $val;
    return $res;
  }

}


/* ================================================================================
 List (as used for dl/ul/ol in html)
 ================================================================================ */
class opc_item_list extends opc_itemo {
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = array();
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~


  function pos2index($pos){
    if(is_string($pos)){
    }
    if($pos<0) return 0;
    if($pos>=count($this->val)) return count($this->val)-1;
    return $pos;
  }

  function add($data=NULL,$label=NULL,$pos=NULL,$after=TRUE){
    $new = array($this->toitem($data),
		 $this->toitem($label));
    if(is_null($pos)){
      if($after==TRUE) $this->val[] = $new;
      else if($after==FALSE) array_unshift($this->val,$new);
    } else {
      if($after==TRUE) array_splice($this->val,$pos+1,0,array($new));
      else if($after==FALSE) array_splice($this->val,$pos,0,array($new));
    }
    $this->isset = TRUE;
  }

}


/* ================================================================================
 table 
 ================================================================================ */
class opc_item_table extends opc_itemo implements ArrayAccess{
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = array();
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~



  function init(){
    $this->val = array('cell'=>new opc_item_matrix(),
		       'cellhead'=>new opc_item_matrix(),
		       'colhead'=>new opc_item_vector(),
		       'rowhead'=>new opc_item_vector());
    $ar = func_get_args();
    call_user_func_array(array($this, 'parent::init'),$ar); 
  }

  function add($data=NULL,$pos=NULL,$as='cell'){
    if(is_array($pos)) $pos = "$pos[0]/$pos[1]";
    $this->val[$as][$pos] = $this->toitem($data);
    $this->isset = TRUE;
  }

  function dim(){
    return array(max($this->val['cell']->rowsize,
		     $this->val['cellhead']->rowsize,
		     $this->val['rowhead']->size),
		 max($this->val['cell']->colsize,
		     $this->val['cellhead']->colsize,
		     $this->val['colhead']->size));
  }

  function offsetGet($pos){ 
    if(!isset($this->val[$pos])) return $this->_err(51);
    else  return $this->val[$pos];
  }
  function offsetSet($pos, $value){
    switch($pos){
    case 'cell': case 'cellhead': 
      if(!($value instanceof opc_item_matrix)) return $this->_err(51);
      $this->val[$pos] = $value;
      break;
    case 'colhead': case 'rowhead': 
      if(!($value instanceof opc_item_vector)) return $this->_err(51);
      $this->val[$pos] = $value;
      break;
    default: return $this->_err(51);
    }
    $this->isset = TRUE;
    return $this->_ok();
  }
  function offsetExists($pos){ return isset($this->val[$pos]);}
  function offsetUnset($pos){
    switch($pos){
    case 'cell': case 'cellhead': $this->val[$pos] = new opc_item_matrix(); return;
    case 'colhead': case 'rowhead': $this->val[$pos] = new opc_item_vector(); return;
    default: return $this->_err(51);
    }
  }

}


?>
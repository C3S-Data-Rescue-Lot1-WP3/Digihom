<?php
/* tested: basics of in/out mark/jumpback
*/

class opc_cstack {
  var $obj = NULL;      // The 'real' object (or a link to it)
  var $stack = array(); // the standard stack
  var $store = array(); // additional store fpr snapshot/dump ...
  var $vlist = array(); // a single name or an array of them
  var $_vlia = TRUE;    // is vlist an array or not

  function opc_cstack(&$obj,$varlist=array()){
    $this->reset($varlist);
    foreach($this->vlist as $ck) $this->obj[$ck] = &$obj->$ck;
  }

  /* ==================================================
   The following functions should be the only which dirctly access the object
   _get: get a single value
   _set: set a single value
   _setn: set multiple vlaues using a named array
  */

  function _get($var){
    return($this->obj[$var]);
  }

  function _set($var,$val){
    $res = $this->obj[$var];
    $this->obj[$var] = $val;
    return($res);
  }

  function _setn($data){
    foreach($this->vlist as $ck) $this->obj[$ck] = $data[$ck];
    return(TRUE);
  }
  //============================================================


  function getByPos($var,$pos=0){
    if($pos===0)                  return($this->_get($var));
    if(is_numeric($pos))          return($this->stack[$this->_pos2key($pos)][$var]);
    if(isset($this->stack[$pos])) return($this->stack[$pos][$var]);
    if(isset($this->store[$pos])) return($this->store[$pos][$var]);
    return(NULL);
  }

  function getAllByPos($pos=0){
    if($pos===0)                  return($this->current(TRUE));
    if(is_numeric($pos))          return($this->stack[$this->_pos2key($pos)]);
    if(isset($this->stack[$pos])) return($this->stack[$pos]);
    if(isset($this->store[$pos])) return($this->store[$pos]);
    return(NULL);
  }

  function _pos2key($pos){
    $ak = array_keys($this->stack);
    return($ak[$pos>0?(count($ak)-$pos):(-1-$pos)]);
  }

  /* reset the current instance
   if varlist is not null it will be set to the new given value
   otherwise it will stay the same */
  function reset($varlist=NULL){
    $this->stack = array();
    $this->store = array();
    if(!is_null($varlist)){
      $this->_vlia = is_array($varlist);
      $this->vlist = $this->_vlia?$varlist:array($varlist);
    }
  }

  /* inti the real object usinge the $init value
   init is corresponding to varlist a named array or a single value */
  function _init($init){
    if($this->_vlia) $this->_setn($init); 
    else $this->_set($this->vlist,$init);
  }

  // moves the asked variables to stack, and inits the values
  function in(/* $init */){
    $this->stack[] = $this->current(TRUE);
    if(func_num_args()>0) $this->_init(func_get_arg(0));
    return(TRUE);
  }

  // restores the last setting, if ret is TRUE returns the current value as named array
  function out($ret=TRUE){
    $res = $ret?$this->current(FALSE):NULL;
    if(count($this->stack)==0) return(FALSE);
    $this->_setn(array_pop($this->stack));
    return($res);
  }

  /* ==================================================
   special variation which allow named in/out

   mark: as 'in' but uses additional a name for the saved state
      return FALSE if name is already used

   revert: restores a named state saved by 'mark'
      all state which are saved after the asked one, will be rejected
      return FALSE if state does not exist

   snapshot: uses an own store to save the current state
      the stack is not affected

   dump: as 'snapshot' but includes the current stack too

   restore: restores a state saved with 'snapshot' or 'dump'

   remove: removes a state saved by 'snapshot' or 'dump'

   exist: test if a named stack exists from 'amrk', 'snapshot' or 'dump'

   keys: list the names of the saved states either from 'mark' (TRUE)
      or from 'snapshot' and 'dump' (FALSE)
   ================================================== */
  function snapshot($name/*,$init*/){
    $this->store[$name] = $this->current(TRUE);
    if(func_num_args()>1) $this->_init(func_get_arg(1));
    return(TRUE);
  }

  function dump($name/*,$init*/){
    $this->store[$name] = $this->current(TRUE);
    if(func_num_args()>1) $this->_init(func_get_arg(1));
    $this->store[$name][0] = $this->stack;
    return(TRUE);
  }

  function restore($name,$ret=TRUE){
    $val = isset($this->store[$name])?$this->store[$name]:NULL;
    if(!is_array($val)) return(FALSE);
    unset($this->store[$name]);
    $res = $ret?$this->current(FALSE):NULL;
    if(isset($val[0])) {
      $this->stack = $val[0];
      unset($val[0]);
    }
    $this->_setn($val);
    return($res);
  }

  function remove($name){
    if(isset($this->store[$name])) return(FALSE);
    unset($this->store[$name]);
    return(TRUE);
  }

  function mark($name/*,$init*/){
    if(isset($this->stack[$name])) return(FALSE);
    $this->stack[$name] = $this->current(TRUE);
    if(func_num_args()>1) $this->_init(func_get_arg(1));
    return(TRUE);
  }

  function revert($name,$ret=TRUE){
    $pos = array_search($name,array_keys($this->stack),TRUE);
    if($pos===FALSE) return(FALSE);
    while(count($this->stack)>$pos+1) array_pop($this->stack);
    $res = $ret?$this->current(FALSE):NULL;
    $this->_setn(array_pop($this->stack));
    return($ret==TRUE?$res:TRUE);
  }

  function exists($name,$stdstack=TRUE){
    return($stdstack?isset($this->stack[$name]):isset($this->store[$name]));
  }

  function keys($stdstack=TRUE){
    if(!$stdstack) return(array_keys($this->store));
    $res = array_keys($this->stack);
    $res = array_filter($res,create_function('$x','return(!is_numeric($x));'));
    return($res);
  }
  // ================================================================================ 


  // discard the newest stack, if ret it will be returned as array
  function discard($ret=TRUE){
    if(count($this->stack)==0) return(FALSE);
    if($ret) return(array_pop($this->stack));
    array_pop($this->stack);
    return(TRUE);
  }

  /* compares to states
   details: TRUE -> returns TRUE if both are equal otherwise FALSE
   FALSE -> returns array of names which are not equal */
  function equal($details=FALSE,$stateA=0,$stateB=1){
    $res = array();
    foreach($this->vlist as $cv){
      if($this->getByPos($cv,$stateA)!==$this->getByPos($cv,$stateB)){
	if($details==TRUE) $res[] = $cv; else return(FALSE);
      }
    }
    return($details==FALSE?TRUE:$res);
  }

  /* return the current state of the object
   direct = TRUE: the result is allways a named array
   FALSE: a name darray or a sinlge value (depending on varlist) */
  function current($direct=FALSE){
    $res = array();
    foreach($this->vlist as $cv) $res[$cv] = $this->_get($cv);
    return(($direct or $this->_vlia)?$res:array_shift($res));
  }

  // number of saved states (using 'in' or 'mark')
  function count(){
    return(count($this->stack));
  }

  // get states: 0 current, 1 last save, 2 second last sav... , -1 first save, -2 econd...
  function get($pos=0){
    if($pos==0) return($this->current(FALSE));
    $res = $this->stack[$this->_pos2key($pos)];
    return($this->_vlia?$res:array_shift($res));
  }
}


class opc_cstack_glob extends opc_cstack{

  function opc_cstack_glob($varlist=array()){
    $this->reset($varlist);
  }

  function _get($var){
    return($GLOBALS[$var]);
  }

  function _set($var,$val){
    $res = $GLOBALS[$var];
    $GLOBALS[$var] = $val;
    return($res);
  }

  function _setn($data){
    foreach($this->vlist as $ck) $GLOBALS[$ck] = $data[$ck];
    return(TRUE);
  }

}





class opc_cstack_arr extends opc_cstack{

  function opc_cstack_arr(&$obj,$var=NULL){
    $this->reset(is_null($var)?array_keys($obj):$var);
    $this->obj = &$obj;
  }

}


class opc_cstack_int extends opc_cstack{

  function opc_cstack_int($data,$one=FALSE){
    if($one or !is_array($data)){
      $this->obj = array($data);
      $this->reset(0);
    } else {
      $this->obj = $data;
      $this->reset(array_keys($data));
    }
  }

  function get($var=0){ 
    return($this->_get($var));
  }

  function set($val/* | var, val */){
    if(func_num_args()==1) return($this->_set(0,$val));
    $value = func_get_arg(1);
    return($this->_set($val,$value));
  }

}



?>
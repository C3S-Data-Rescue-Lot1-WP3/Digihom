<?php

/*
 This is basic class which should only figure as parent of sub classes
 
 */

class opc_var {
  //objects, function and priority order
  var $value =NULL; // set/get: v-obj, v-var
  var $name =NULL;  // name_set/_get: v-obj, n-var (string)
  var $type = NULL; // type_set/_get: v-obj, t-var (string)
  var $meta = array(); // meta_set/_get; v-obj, m-var (assoz. array)
  var $io = NULL; // load/save: v-obj, i-obj
  var $disp = NULL; // disp: v-obj, d-obj, print_r
  var $check = NULL; // check: v-obj, c-obj, !is_null

  var $others = array(); //free to use


  function opc_var($name,$value=NULL,$type=NULL){
    $this->name_set($name);
    $this->set($value);
    $this->type_set($type);
  }

  /* get value
   Prio 1: method get in value-obj; args: as given
   Prio 2: return class variable value; args: [none]
   */
  function get(){
    if(is_object($this->value) and in_array('get',get_class_methods($this->value)))
      return(call_user_func_array(array(&$this->value,'get'),func_get_args()));
    else 
      return($this->value);
  }

  /* set value
   Prio 1: method set in value-obj; args: as given
   Prio 2: save in class variable value; args: new value
   */
  function set(){
    if(is_object($this->value) and in_array('set',get_class_methods($this->value)))
      return(call_user_func_array(array(&$this->value,'set'),func_get_args()));
    else 
      return($this->value = func_get_arg(0));
  }

  /* get type
   Prio 1: method type_get in value-obj; args: as given
   Prio 2: return class variable type; args: [none]
   */
  function type_get(){
    if(is_object($this->value) and in_array('type_get',get_class_methods($this->value)))
      return(call_user_func_array(array(&$this->value,'type_get'),func_get_args()));
    else 
      return($this->type);
  }

  /* set type
   Prio 1: method type_set in value-obj; args: as given
   Prio 2: save in class variable type; args: new type
   */
  function type_set(){
    if(is_object($this->value) and in_array('type_set',get_class_methods($this->value)))
      return(call_user_func_array(array(&$this->value,'type_set'),func_get_args()));
    else 
      return($this->value = func_get_arg(0));
  }

  /* get name
   Prio 1: method name_get in value-obj; args: as given
   Prio 2: return class variable name; args: [none]
   */
  function name_get(){
    if(is_object($this->value) and in_array('name_get',get_class_methods($this->value)))
      return(call_user_func_array(array(&$this->value,'name_get'),func_get_args()));
    else 
      return($this->name);
  }

  /* set name
   Prio 1: method name_set in value-obj; args: as given
   Prio 2: save in class variable name; args: new name
   */
  function name_set(){
    if(is_object($this->value) and in_array('name_set',get_class_methods($this->value)))
      return(call_user_func_array(array(&$this->value,'name_set'),func_get_args()));
    else 
      return($this->value = func_get_arg(0));
  }





  /* get meat-data
   Prio 1: method meta_get in value-obj; args: as given
   Prio 2: use array meta of this class; args: name
   */
  function meta_get(){
    if(is_object($this->value) and in_array('meta_get',get_class_methods($this->value)))
      return(call_user_func_array(array(&$this->value,'meta_get'),func_get_args()));
    else 
      return($this->meta[func_get_arg(0)]);
  }


  /* set meat-data
   Prio 1: method meta_set in value-obj; args: as given
   Prio 2: use array meta of this class; args: name, value
   */
  function meta_set(){
    if(is_object($this->value) and in_array('meta_set',get_class_methods($this->value)))
      return(call_user_func_array(array(&$this->value,'meta_set'),func_get_args()));
    else 
      return($this->meta[func_get_arg(0)] = func_get_arg(1));
  }


  /* load from external element (file, xml, database ...)
   Prio 1: method load in value-object; args: as given
           result is sent to methid set
   Prio 2: method load in io-object; args: this, others
           result is sent to methid set
   Prio 3: FALSE
  */
  function load(){
    if(is_object($this->value) and in_array('load',get_class_methods($this->value)))
      return($this->set(call_user_func_array(array(&$this->value,'load'),func_get_args())));
    if(is_object($this->io) and in_array('load',get_class_methods($this->io))){
      $args = func_get_args();
      array_unshift($args,$this);
      return($this->set(call_user_func_array(array(&$this->io,'load'),$args)));
    }
    return(FALSE);
  }

  /* save to external element (file, xml, database ...)
   Prio 1: method save in value-object; args: as given
   Prio 2: method save in io-object; args: result of method get, other args
   Prio 3: FALSE
  */
  function save(){
    if(is_object($this->value) and in_array('save',get_class_methods($this->value)))
      return(call_user_func_array(array(&$this->value,'save'),func_get_args()));
    if(is_object($this->io) and in_array('save',get_class_methods($this->io))){
      $args = func_get_args();
      array_unshift($args,$this);
      return(call_user_func_array(array(&$this->io,'save'),$args));
    }
    return(FALSE);
  }

  /* displays the current value
   Prio 1: method disp in value-object; args: meta-object, other args
   Prio 2: method disp in disp-object; args: result of method get, meta-object, other args
           if value-object has a method meta (which should return an array of meta-items)
	   this will be called first an merged with the current meta-array of this class
   Prio 3: print_r of value
  */
  function disp(){
    if(is_object($this->value) and in_array('disp',get_class_methods($this->value))){
      $args = func_get_args();
      array_unshift($this,$args);
      return(call_user_func_array(array(&$this->value,'disp'),func_get_args()));
    }
    if(is_object($this->disp) and in_array('disp',get_class_methods($this->disp))){
      $args = func_get_args();
      array_unshift($args,$this);
      return(call_user_func_array(array(&$this->disp,'disp'),$args));
    }
    return(print_r($this->get()));
  }

  /* chekcs the content 
   Prio 1: method check in value-object; args: as given
   Prio 2: method check in check-object; args: result of method get, other args
   Prio 3: function is_null to value
   the return value is saved in meat too. If it is an array thw single
   items will be saved in meta under their names, if not the value
   is saved in meta under 'status'
  */
  function check(){
    if(is_object($this->value) and in_array('check',get_class_methods($this->value)))
      $res = call_user_func_array(array(&$this->value,'check'),func_get_args());
    else if(is_object($this->check) and in_array('check',get_class_methods($this->check))){
      $args = func_get_args();
      array_unshift($this,$args);
      $res = call_user_func_array(array(&$this->check,'disp'),$args);
    } else if(is_null($this->get())) 
      $res = array('status'=>1);
    else
      $res = array('status'=>0);
    if(!array($res)) $res = array('status'=>$res);
    while(list($ak,$av)=each($res)) $this->meta_set($ak,$av);
    return($res);
  }

}


class opc_var_ioweb {
  
  var $data = array();

  function opc_var_ioweb($source='get'){
    $this->source($source,FALSE);
  }

  function source($source='auto',$add=FALSE){
    if(is_array($source)){
      $data = $source;
    } else {
      switch($source){
      case 'put': $data = $_POST; break;
      case 'get': $data = $_GET; break;
      case 'auto':
	$data = array();
	if(session_id()!='')
	  $data = array_merge($data,$_SESSION);
	if(!empty($_POST)>0) 
	  $data = array_merge($data,$_POST);
	if(!empty($_GET)>0) 
	  $data = array_merge($_GET);
	if(!empty($_FILES))
	  $data = array_merge($data,$_FILES);
      }
    }
    $this->data = $add?array_merge($data,$this->data):$data;
  }

  function load(&$opcvar){
    $name = $opcvar->name;
    if(array_key_exists($name,$this->data)) return($this->data[$name]);
    $opcvar->meta_set('load_error',1);
    return(FALSE);
  }

  function save(&$opcvar,&$sink){
    $args = func_get_args();
    $val = $opcvar->get();
    $this->data[$opcvar->name] = $val;
    switch($args[1]){
    case 'htform':
      $opcvar->others['htform']->def_value[$opcvar->name] = $val;
      return(TRUE);
    }
  }
}
?>
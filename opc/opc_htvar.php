<?php

require_once('opc_ht.php');
require_once('ops_array.php');

class opc_htvar extends opc_htdiv{

  var $value = NULL;
  var $name = NULL;
  var $edit = FALSE;

  /* array of meta data, a name of a function to get the metadata
   or an object with method meta (read) and meta_set (write)*/
  var $meta = array();

  /* object which may replace some function
    -> disp, label, item and so on
    gets as first argument this as reference followed by the others
   */
  var $disp = NULL; 

  function opc_htvar(/* name, value, edit, meta, disp */){
    $ar = func_get_args();
    switch(count($ar)){
    case 5: $this->disp = $ar[4];
    case 4: $this->meta = $ar[3];
    case 3: $this->edit = $ar[2];
    case 2: $this->set($ar[0],$ar[1]); break;
    case 1: $this->set($ar[0]); break;
    }
  }

  function set($name,$value=NULL){
    $this->name = $name;
    $this->value = $value;
    return(TRUE);
  }

  function name_split(&$name){
    $en = explode('.',$name,2);
    $name = $en[1];
    return($en[0]);
  }

  function name(){
    if(is_object($this->value) and method_exists($this->value,'name'))
      return($this->value->name());
    else 
      return($this->name);
  }

  function subnames(){
    return(NULL);
  }

  function value(){
    if(is_object($this->value) and method_exists($this->value,'value'))
      return($this->value());
    else 
      return($this->value);
  }

  function editable(){
    return($this->edit);
  }

  /* search fct in a wide range and returns it
   1) does a method with this name exits -> return its result
   2) does a class var with this name exists -> return its value
   3) does a meta data with this name exists -> return its value
   4) does value has such a method -> return its result
   5) does value has such a class var  -> return its value
   6) return def
   */
  function _gc($fct,$ar=NULL,$def=NULL){
    if(method_exists($this,$fct)){
      if(is_null($ar)) return($this->$fct());
      else return(call_user_func_array(array(&$this,$fct),$ar));
    } else if(isset($this->$fct)){
      return($this->$fct);
    } else if($this->meta_exists($fct)){
      return($this->meta_get($fct));
    } else if(is_object($this->value) and method_exists($this->value,$fct)){
      if(is_null($ar)) return($this->value->$fct());
      else return(call_user_func_array(array(&$this->value,$fct),$ar));
    } else if(is_object($this->value) and isset($this->value->$fct)){
      return($this->value->fct);
    } else {
      return($def);
    }
  }


  /* similar to _gc but including a key to drill down */
  function _gc_sub($fct,$key,$ar=NULL,$def=NULL){
    if(method_exists($this,$fct)){
      if(is_null($ar)) return($this->$fct($key));
      else {
	array_unshift($ar,$key);
	return(call_user_func_array(array(&$this,$fct),$ar));
      }
    } else if(isset($this->$fct)){
      return($this->$fct);
    } else if($this->meta_exists($fct)){
      return($this->meta_get($fct));
    } else if(is_object($this->value) and method_exists($this->value,$fct)){
      if(is_null($ar)) return($this->value->$fct());
      else {
	array_unshift($ar,$key);
	return(call_user_func_array(array(&$this->value,$fct),$ar));
      }
    } else if(is_object($this->value) and isset($this->value->$fct)){
      return($this->value->fct);
    } else {
      return($def);
    }
  }



  /* sets meta data; allowed are
   name(string) - value(any)
   array of names - array of values
   named array (name=>value)
   */
  function meta_set($name,$value=NULL){
    if(is_array($this->meta)){
      ops_array::set($this->meta,$name,$value);
      return(TRUE);
    } else if(is_object($this->meta) and method_exists($this->meta,'set')){
      $ar = func_get_args();
      array_unshift($ar,$this);
      return(call_user_func_array(array(&$this->meta,'set'),$ar));
    } else return(FALSE);
  }

  function meta_get($name,$default=NULL){
    if(is_array($this->meta)) {
      return(ops_array::get($this->meta,$name,$default));
    } else if(!is_object($this->meta)){
      return($default);
    } else if(method_exists($this->meta,'get')){
      $ar = func_get_args();
      array_unshift($ar,$this);
      return(call_user_func_array(array(&$this->meta,'get'),$ar));
    }
  }

  function meta_exists($name){
    
  }

  function display2($settings=array())   {$this->add($this->display2arr($settings));}
  function display2str($settings=array()){return($this->_implode2str($this->display2arr($settings)));}
  function display2arr($settings=array()){
    if(!is_null($this->disp)){
      $ar = func_get_args();
      array_unshift($ar,$this);
      return(call_user_func_array(array(&$this->disp,'display2arr'),$ar));
    }

    $def = array('label'=>array(),'item'=>array());
    $set = ops_array::setdefault($settings,$def,0,FALSE);
    
    $lab = $this->label2arr(ops_array::key_extract($set,'label'));
    $itm = $this->item2arr(ops_array::key_extract($set,'item'));
    if(!is_null($lab)) $res = array('tag'=>'span',$lab,':&nbsp;',$itm);
    else               $res = array('tag'=>'span',$itm);

    return($this->implode2arr($res,$set));
  }


  function item    ($settings=array()){$this->add($this->item2arr($settings));}
  function item2str($settings=array()){return($this->_implode2str($this->item2arr($settings)));}
  function item2arr($settings=array()){
    if(!is_null($this->disp)){
      $ar = func_get_args();
      array_unshift($ar,$this);
      return(call_user_func_array(array(&$this->disp,'item2arr'),$ar));
    }
    $set = $settings;
    if($this->edit){
      $res = array('tag'=>'input','name'=>$this->name(),'value'=>$this->value());
    } else {
      $res = array('tag'=>'span',$this->value);
    }
    return($this->implode2arr($res,$set));
  }


  function label    ($settings=array()){$this->add($this->label2arr($settings));}
  function label2str($settings=array()){return($this->_implode2str($this->label2arr($settings)));}
  function label2arr($settings=array()){
    if(!is_null($this->disp)){
      $ar = func_get_args();
      return(call_user_func_array(array(&$this->disp,'label2arr'),$ar));
    }
    if(is_string($settings)) $settings = array('label'=>$settings);
    $def = array('label'=>'label','tooltip'=>'tooltip');
    $set = ops_array::setdefault($settings,$def,0,FALSE);
    
    $lab = $this->meta_get(ops_array::key_extract($set,'label'));
    if(is_null($lab)) $lab = $this->name();
    $res = array('tag'=>'span',$lab);
    $tit = $this->meta_get(ops_array::key_extract($set,'tooltip'));
    if(!is_null($tit)) $res['title'] = $tit; 
    return($this->implode2arr($res,$set));
  }

  function meta    ($name,$settings=array()){$this->add($this->meta2arr($name,$settings));}
  function meta2str($name,$settings=array()){return($this->_implode2str($this->meta2arr($name,$settings)));}
  function meta2arr($name,$settings=array()){
    if(!is_null($this->disp)){
      $ar = func_get_args();
      return(call_user_func_array(array(&$this->disp,'meta2arr'),$ar));
    }
    $def = array();
    $set = ops_array::setdefault($settings,$def,0,FALSE);
    $tag = ops_array::key_extract($set,'tag','span');
    $mtd = $this->meta_get($name);
    return($this->implode2arr(array('tag'=>$tag,$mtd),$set));
  }
  
  function tr(){return('ttrr');}
  
}











class opc_htvar_list extends opc_htvar{

  var $value = array();

  function set($name,$subname=NULLx,$value=NULL){
    $this->name = $name;
    ops_array::set($this->value,$subname,$value);
    return(TRUE);
  }

  function editable(){
    if(is_bool($this->edit)) return($this->edit);
    if(is_array($this->edit)) return(array_sum($this->edit)!=0);
    return(NULL);
  }

  function subnames(){
    $res = array();
    $ii = 0;
    foreach($this->value as $key=>$value){
      if(is_object($value) and method_exists($value,'name')){
	$ck = is_numeric($key)?$value->name():$key;
      } else $ck = is_numeric($key)?($this->name() . '_' . $ii++):$key;
      if(is_object($value) and method_exists($value,'subnames')){
	$cv = $value->subnames();
	if(is_null($cv)) $cv = $ck;
      } else $cv = $ck;
      $res[$ck] = $cv;
    }
    return($res);
  }

  function value($name=NULL){
    $res = array();
    $ii = 0;
    foreach($this->value as $key=>$value){
      if(is_object($value) and method_exists($value,'name')){
	$ck = is_numeric($key)?$value->name():$key;
      } else $ck = is_numeric($key)?($this->name() . '_' . $ii++):$key;
      if(is_object($value) and method_exists($value,'value')){
	$cv = $value->value();
      } else $cv = $value;
      $res[$ck] = $cv;
    }
    return($res);
  }


  function display2($settings=array())   {$this->add($this->display2arr($settings));}
  function display2str($settings=array()){return($this->_implode2str($this->display2arr($settings)));}
  function display2arr($settings=array()){
    $ar = func_get_args();
    if(!is_null($this->disp)){
      array_unshift($ar,$this);
      return(call_user_func_array(array(&$this->disp,'display2arr'),$ar));
    }
    $labels = array();
    $items = array();
    $keys = array();
    $ii = 0;
    $res = array('tag'=>'dl');
    foreach($this->value as $key=>$value){
      if(is_object($value) and method_exists($value,'name')){
	$ck = is_numeric($key)?$value->name():$key;
      } else $ck = is_numeric($key)?($this->name() . '_' . $ii++):$key;
      $keys[] = $ck;

      if(is_object($value) and method_exists($value,'label2arr')){
	$cv = call_user_func_array(array($value,'label2arr'),$ar);
      } else $cv = $ck;
      $label[$ck] = $cv;

      if(is_object($value) and method_exists($value,'label2arr')){
	$cv = call_user_func_array(array($value,'label2arr'),$ar);
      } else $cv = $ck;
      $items[$ck] = $cv;

    }
  }
}


?>
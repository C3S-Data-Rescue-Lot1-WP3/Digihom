<?php

class opc_test extends opc_tmpl1{
  /* current state (=last result) */
  protected $state = NULL;
  /* current command (needs a method named eval__CMD)*/
  protected $cmd = NULL;
  /* items to be tesetd by cmd */
  protected $items = array();

  /* read only attributes of this class (->accepted by ___get but not by ___set)*/
  protected $attr_ro = array('state','cmd','items');

  protected $cls = array();

  public $data = NULL;

  function obj_add(&$obj,$key='obj'){
    if(is_object($obj)) $this->cls [$key] = &$obj;
  }

  function evaluate(){
    if(count($this->items)==0 or is_null($this->cmd)) return $this->state = NULL;
    $this->status_reset();
    $mth = 'eval__' . $this->cmd;
    $res = $this->$mth();
    return $this->state = $this->status_cur<=0?$res:NULL;
  }

  function ___get($key,&$res){
    if(in_array($key,$this->attr_ro))   { $res = $this->$key; return 0;}
    if($key=='eval') { $this->evaluate(); $res = $this->state; return 0;}
    return parent::___get($key,$res);
  }

  function __construct($cmd /* */){
    $this->tmpl_init();
    $ar = is_array($cmd)?$cmd:func_get_args();
    $cmd = array_shift($ar);
    $mth = 'eval__' . $cmd;
    if(!method_exists($this,$mth)) return trigger_error('Unkown test statement: ' .$cmd);
    $this->cmd = $cmd;
    $this->items = $ar;
  }


  /* repalces ?-array with their result */
  protected function solve(&$arg){
    foreach($arg as $ck=>$cv) if(is_array($cv) and isset($cv['?'])) $arg[$ck] = $this->exec($arg[$ck]);
  }

  protected function exec($arg){
    $this->solve($arg);
    $mth = 'exec_' . $arg['?'];
    $res = $this->$mth($arg);
    return $res;
  }

  protected function exec_m($arg){
    return call_user_func_array(array($this->cls[def($arg,'obj','obj')],$arg['fct']),$arg['args']);
  }

  protected function exec_f($arg){
    $fct = $arg['fct'];
    switch($fct){
    case 'preg_match': return preg_match($arg['add'][0],$arg['arg1']);
    }
    qx($fct);
    return $this->err(3,NULL);
  }

  protected function exec_i($arg){
    $fct = $arg['fct'];
    switch($fct){
    case 'range':
      $a1 = $arg['arg1'];
      $add = $arg['add'];
      if($a1<$add[0] or ($a1==$add[0] and !$add[2])) return FALSE;
      if($a1>$add[1] or ($a1==$add[1] and !$add[3])) return FALSE;
      return TRUE;

    case 'compare':
      $a1 = $arg['arg1'];
      $a2 = $arg['arg2'];
      switch($arg['cmp']){
      case '=': case '==':  return $a1==$a2;
      case '===':           return $a1===$a2;
      case '!=': case '<>': return $a1!=$a2;
      case '!==':           return $a1!==$a2;
      case '>':             return $a1>$a2;
      case '>=':            return $a1>=$a2;
      case '<':             return $a1<$a2;
      case '<=':            return $a1<=$a2;
      }
      break;
    }
    qx($fct);
    return $this->err(3,NULL);
  }

  function to_bool($arg){
    if($arg instanceof opi_test) 
      $tmp = $arg->evaluate();
    else if(is_array($arg) and isset($arg['?']))
      $tmp = $this->exec($arg);
    else 
      $tmp = $arg;

    if(is_bool($tmp) or is_null($tmp)) return $tmp;
    if(is_scalar($tmp)) return $tmp==TRUE;
    return $this->err(2,NULL);
  }

  function eval__and(){
    foreach($this->items as $ci){
      $res = $this->to_bool($ci);
      if($res===FALSE) return FALSE;
      if($res!==TRUE)  return $this->err(1,NULL);
    }
    return TRUE;
  }

  function eval__or(){
    foreach($this->items as $ci){
      $res = $this->to_bool($ci);
      if($res===TRUE) return TRUE;
      if($res!==FALSE) return $this->err(1,NULL);
    }
    return FALSE;
  }

  function eval_not(){
    if(count($this->items)>0) return $this->failed = 2;
    $res = $this->to_bool($ci);
    return is_bool($res)?!$res:$res;
  }
  }


?>
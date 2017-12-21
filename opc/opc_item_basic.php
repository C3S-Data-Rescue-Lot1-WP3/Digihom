<?php
/* ================================================================================
 Text
 ================================================================================ */
class opc_item_text extends opc_item{
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = NULL;
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  public $accept = array('null'=>1,'bool'=>1,'string'=>1,
			 'numeric'=>1,'integer'=>1,'float'=>1,'array'=>0);
  function set_default($value){ $this->val = strval($value); return $this->_ok();}
  function exp_string(){return $this->val;}
}

/* ================================================================================
 Boolean
 ================================================================================ */
class opc_item_bool extends opc_item{
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = NULL;
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  public $dt_string = array(0=>'no',1=>'yes',''=>'null');
  public $et_string = array('null'=>NULL,''=>NULL,
			    'y'=>TRUE,'yes'=>TRUE,'on'=>TRUE,'ein'=>TRUE,'j'=>TRUE,'ja'=>TRUE,
			    'true'=>TRUE,'t'=>TRUE,
			    'n'=>FALSE,'no'=>FALSE,'off'=>FALSE,'aus'=>FALSE,'nein'=>FALSE,
			    'false'=>FALSE,'f'=>FALSE);

  public $accept = array('null'=>1,'bool'=>1,'string'=>1,
			 'numeric'=>1,'integer'=>1,'float'=>1,'array'=>0);

  function set_string($value){
    $val = strtolower($value);
    if(array_key_exists($val,$this->et_string)==TRUE) {
      $this->val = $this->et_string[$val];
      return $this->_ok();
    } else return $this->_errC(array(3,$value));
  }

  function set_bool($value){
    $this->val = $value;
    return $this->_ok();
  }

  function set_numeric($value) {return $this->set_bool((float)$value!=0);}
  function set_integer($value) {return $this->set_bool($value!=0);}
  function set_float($value)   {return $this->set_bool($value!=0);}

  function exp_string(){return $this->dt_string[is_null($this->val)?'':($this->val?1:0)];}

}

/* ================================================================================
 Integer
 ================================================================================ */
class opc_item_integer extends opc_item{
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = NULL;
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  public $accept = array('null'=>1,'bool'=>1,'numeric'=>1,'integer'=>1,'float'=>1);
  function set_default($value){ $this->val = (int)round($value); return $this->_ok();}
}


/* ================================================================================
 Float
 ================================================================================ */
class opc_item_float extends opc_item{
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = NULL;
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  public $accept = array('null'=>1,'bool'=>1,'numeric'=>1,'integer'=>1,'float'=>1);
  function set_default($value){ $this->val = (float)$value; return $this->_ok();}
}

/* ================================================================================
 NULL
 ================================================================================ */
class opc_item_null extends opc_item{
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = NULL;
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  public $dt_string = '[null]';
  public $accept = array('null'=>1);
  function set_default($value){ $this->val = NULL; return $this->_ok();}
  function exp_string(){return $this->dt_string;}
}



?>
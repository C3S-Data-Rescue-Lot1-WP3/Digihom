<?php


abstract class opc_item_web extends opc_item{
  public $pat_domain = '([_a-z][_-\w]*\.)+[_a-z]{2}[_-\w]*';
  public $pat_email_head = '[_a-z][_-\w]*(\.[_a-z][_-\w]*)*';
  
  }

/* ================================================================================
 Text
 ================================================================================ */
class opc_item_email extends opc_item_web{
  /* static variables and access, repeat on all childs, see opc_item for more details */
  static public $val_init = array('email'=>NULL,
				  'label'=>NULL,
				  'subject'=>NULL,
				  'body'=>NULL);
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~


  public $accept = array('null'=>1,'string'=>2,'array'=>2);


  public $pat_email = NULL;// set during construcion

  function __construct(){
    $this->pat_email = $this->pat_email_head . '@' . $this->pat_domain;
    $ar = func_get_args();
    call_user_func_array(array('parent','__construct'),$ar);
  }


  function check_string($txt){
    if(!preg_match("/^$this->pat_email$/",$txt)) return FALSE;
    return TRUE;
  }


  function set_string($txt){
    $res = get_class_vars($this);
    $this->val['email'] = $txt;
  }
}

?>
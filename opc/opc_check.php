<?php

abstract class opc_check {
  static $cls = NULL;
  public $obj = NULL;
  public $sink = NULL;

  static $ht2attr = 'border: solid 3px red; padding: 3px; background-color: yellow; color: black;';

  static function init($obj,$sink){
    $cls = preg_grep('/^opc_check_/',get_declared_classes());
    $ocls = 'opc_check_' . get_class($obj);
    if(in_array($ocls,$cls)) return new  $ocls($obj,$sink);
    trigger_error('no check class found ' . $ocls);
  }
}

class opc_check_opc_ht2 extends opc_check{
  static $cls = 'opc_ht2';

  function __construct($obj,$sink){
    $this->obj = $obj;
    $this->sink = $sink;
  }

  /* ================================================================================
     Check routines
     ================================================================================ */


  /** checks if the saved structers are ok
   * @param array of settings $add <br>
   *  - trigger (TRUE): trigger error if errors found
   */
  function check($add=array()){
    $res = array();
    $ar = array('par','fcl','lcl','nxt','prv');
    foreach($this->obj->str as $key=>$val){
      // basic check
      foreach($ar as $ck){
	if(!is_null($val[$ck]) and !isset($this->obj->str[$val[$ck]])){
	  $res[] = "invalid $ck $val[$ck] @$val[key]";
	  continue 2;
	}
      }

      // standard call using check__[type]
      $mth = 'check_' . $val['typ'];
      if($key!=$val['key']) $res[] ="key-error @$key";
      else $res = array_merge($res,$this->$mth($key,$val));
    }

    //check data -> str (opposite was done before)
    $dkeys = array_keys($this->obj->data);
    $skeys = array_keys($this->obj->str);
    $int = array_diff($dkeys,$skeys);
    if(count($int)>0) $res[] = 'not used in str: ' . implode(', ',$int);
    

    // remove non-errors
    $res = array_values(array_filter($res));
    if(def($add,'trigger',TRUE) and count($res)>0){
      if($this->sink instanceof opi_ptr)
	$this->sink->tag('p','structer not well defined: ' . var_export($res,TRUE),self::$ht2attr);
      else
	trigger_error('structer not well defined: ' . var_export($res,TRUE),E_USER_WARNING);
    }
    return empty($res)?TRUE:$res;
  }
    
  /**#@+ subroutins for {@link check} named by the data-type 
   * @param string/int $key: key from {@link $str}
   * @param array $val: value from {@link $str}
   * @return array of error messages (NULL-elements means ok)
   */
  function check_root($key,$val){
    return array($this->check__first_last($key,$val),
		 $this->check__first($key,$val),
		 $this->check__last($key,$val),
		 $this->check__top($key,$val),
		 );
  }
  
  function check_orph($key,$val){
    return array($this->check__first_last($key,$val),
		 $this->check__first($key,$val),
		 $this->check__last($key,$val),
		 $this->check__top($key,$val),
		 );
  }
  

  function check_txt($key,$val){
    return array($this->check__empty($key,$val),
		 $this->check__next($key,$val),
		 $this->check__prev($key,$val),
		 $this->check__data($key),
		 );
  }
  
  function check_tag($key,$val){
    return array($this->check__first_last($key,$val),
		 $this->check__first($key,$val),
		 $this->check__last($key,$val),
		 $this->check__next($key,$val),
		 $this->check__prev($key,$val),
		 $this->check__data($key),
		 );
  }

  function check_ph($key,$val){
    return array($this->check__next($key,$val),
		 $this->check__prev($key,$val),
		 );
  }

  function check_rem($key,$val){
    return array($this->check__next($key,$val),
		 $this->check__prev($key,$val),
		 );
  }

  /**#@- */


  /**#@+ subroutins for {@link check}_??? to check a single aspect
   * @return: string-msg (error) or NULL (no error)
   */
  function check__data($key){
    if(!isset($this->obj->data[$key])) return "unknown data-key @$key";
  }

  function check__prev($key,$val){
    if(is_null($val['prv'])){
      if(is_null($val['par'])){
	if($val['state']!=-1) return "orphan not marked @key";
      } else if($this->obj->str[$val['par']]['fcl']!=$key) return "par-fcl @$key";
    } else {
      if($this->obj->str[$val['prv']]['nxt']!==$key) return "prv-nxt @$key";
    }
  }
  
  function check__next($key,$val){
    if(is_null($val['nxt'])){
      if(is_null($val['par'])){
	if($val['state']!=-1) return "orphan not marked  @key";
      } else if($this->obj->str[$val['par']]['lcl']!=$key) return "par-lcl @$key";
    } else {
      if($this->obj->str[$val['nxt']]['prv']!=$key) return "nxt-prv @$key";
      if($this->obj->str[$val['nxt']]['par']!=$val['par']) return "par-par @$key";
    }
  }

  function check__first_last($key,$val){
    if(is_null($val['fcl'])!==is_null($val['lcl'])) return "fcl-lcl @$key";
  }
  
  function check__first($key,$val){
    if(!is_null($val['fcl']))
      if($this->obj->str[$val['fcl']]['par']!=$key) return "fcl-par @$key";
  }
  
  function check__last($key,$val){
    if(!is_null($val['lcl']))
      if($this->obj->str[$val['lcl']]['par']!=$key) return "lcl-par @$key";
  }
  
  function check__empty($key,$val){
    if(!is_null($val['lcl'])) return "nonempty @$key";
    if(!is_null($val['fcl'])) return "nonempty @$key";
  }

  function check__top($key,$val){
    if(!is_null($val['par'])) return "not top level @$key";
  }
  /**#@- */

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  /** debug only */

}

?>
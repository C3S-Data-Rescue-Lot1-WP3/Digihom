<?php

interface opi_dist{
  static function newobj($type);
}

class opc_dist_item implements opi_dist{

  static public $decode_table = array(); // allows direct mapping between type and target class

  static function newobj($type /* ... */){
    if(is_null($type)) return;

    $ar = func_get_args();
    $type = array_shift($ar); // remove type/object
    
    try{
      // get the asked class
      if(is_string($type))       $cls = defnz(self::$decode_table,$type,'opc_item_' . $type);
      else if (is_object($type)) $cls = get_class($type);
      else throw new Exception('invalid type: ' . strval($type));
      
      // check this class 8exists, implements opi_item
      if(!class_exists($cls)) 
	throw new Exception('unknown class: ' . $cls);
      else if(!in_array('opi_item',class_implements($cls)))
	throw new Exception('not able to handle: ' . $cls);
      
    } catch (Exception $ex) {
      trigger_error('error creating a opc_item: ' . $ex->getMessage(),E_USER_WARNING);
      return NULL;
    }
    
    // crate and call init with the other arguments
    $res = new $cls();
    call_user_func_array(array(&$res,'init'),$ar);
    return $res;
  }

}

/* 
 Ideen:
  analog zu set/imp eine compare funcktion?
 */
interface opi_item{
  function get_value();
  function get_key();
  function get_settings();

  function get();
  function set($value);
  function set_default($value);
  function reset($compl=FALSE);

  // deprecated
  function ele_IsValid($key);
  function ele_IsSet($key);
  function ele_Get($key,$def=NULL);
  function ele_Set($key,$val=NULL);

  function exp($mode=NULL);
  function imp($value,$mode=NULL);

  function info($what);
  function init();
}



abstract class opc_item implements opi_item{
  /* ================================================================================
     static variables and inherit-save acces to them (repeat this section on all subclasses)
     ================================================================================ */
  static public $val_init = NULL;
  function s_get($key) {return self::$$key;}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  protected $val = NULL;
  protected $key = NULL;
  protected $set = array();

  public    $key_init = NULL;
  protected $set_init = array();

  protected $isset = FALSE;

  protected $exp_mode_default = 'raw';

  /* defines what is accepted (for function set/init)
  numeric code for key see types
  value: 0: not accepted (default)
         1: accepted (all values)
         2: use check_*
  an object of the same class is always accepted!
  default: everything except objects and resources
*/

  static $types = array(1=>'null','bool','string',
			4=>'numeric','integer','float',
			7=>'array',
			11=>'resource',12=>'object');

  public $accept = array('null'=>1,'bool'=>1,'string'=>1,
			 'numeric'=>1,'integer'=>1,'float'=>1,'array'=>1);

  protected $err = NULL;
  public $err_level = E_USER_ERROR;

  /* ================================================================================
   Magic
   ================================================================================ */

  function __construct(/*$value=NULL,$key=NULL,$settings=array()*/){
    $this->err = new opc_status($this->_init_msgs());
    $this->val = $this->s_get('val_init');
    $this->err->mode_success = 2;
    $this->err->lev_base = 2;
    $ar = func_get_args();
    call_user_func_array(array(&$this,'init'),$ar);
  }

  function _init_msgs(){
    return array(-1=>array('no changes','ok'),
		 0=>array('ok','ok'),
		 1=>array('no read access','type'=>'notice'),
		 2=>array('no write access','notice'),
		 3=>array('type of input value not acceptable','notice'),
		 4=>array('unknown import format','notice'),
		 5=>array('unknown export format','notice'),
		 6=>array('invalid subelement','notice'),
		 7=>array('invalid input format','notice'),
		 8=>array('value not set','notice'),
		 9=>array('settings is not an array','notice'),
		 );
  }
  
  /* overload _get instead of this */
  function __get($key){
    if($this->_get($key)==TRUE) return $key;    
    return $this->_err(array(1,$key));
  }

  /* overload this instead of __get
   if read-access allowed -> save it in key and return TRUE
   if not return FALSE
   call parent::_get($key) if necessary */
  function _get(&$key){
    switch($key){
    case 'value': case 'val':    $key = $this->get();  return TRUE;
    case 'settings':             $key = $this->set;    return TRUE;
    case 'isset': case 'key':    $key = $this->$key;   return TRUE;
    case 'status': case 'isok': case 'msg': 
      $key = $this->$key(); return TRUE;
    case 'val_init':             
      $key = $this->s_get($key);   return TRUE;

    }
    return FALSE;
  }

  /* overload _set instead of this */
  function __set($key,$val){
    if($this->_set($key,$val)==TRUE) return $this->_ok();
    return $this->_err(array(2,$key));
  }

  /* overload this instead of __set
   if write-access allowed -> do it and return TRUE
   if not return FALSE
   call parent::_set($key) if necessary */
  function _set($key,$value){
    switch($key){
    case 'value': case 'val': $this->set($value);      return TRUE;
    case 'settings':          $this->set_settings($value); return TRUE;
    case 'key':               $this->set_key($value);      return TRUE;
    }
    return FALSE;
  }

  function __tostring(){
    return $this->key . '[' . get_class($this) . ']: ' . strval($this->val); 
  }

  function __sleep(){ return array('val','key','set');}

  function XXX__clone(){
    if(is_object($this->val)){
      $this->val = clone($this->val);
    } else if(is_array($this->val)){
      foreach($this->val as $key=>$val){
	if(is_object($val)) $this->val[$key] = clone($val);
      }
    }
  }

  /* ================================================================================
   Short cut for the status
   ================================================================================ */
  /**#@+ shortcuts to the status-object */
  function status()                { return $this->err->status;}
  function isok()                  { return $this->err->is_success();}
  function msg()                   { return $this->err->msg();}
  function _ok($ret=TRUE)          { return $this->err->ok($ret);}
  function _okA($code=0,$ret=TRUE) { return $this->err->okA($code,$ret);}
  function _okC($code=0)           { return $this->err->okC($code);}
  function _err($code,$ret=NULL)   { return $this->err->err($code,$ret);}
  function _errC($code)            { return $this->err->errC($code);}
  function _errM($code/* */)       { return $this->err->errM(func_get_args());}
  /**#@- ________________________________________________________________________________ */



  /* ================================================================================
   Set Get
   ================================================================================ */

  // Principals ============================================================
  final function get()   {
    if(!$this->isset) return $this->_err(8,$this->s_get('val_init'));
    return $this->_ok($this->val);
  }

  function reset($compl=FALSE) {
    $this->val = $this->s_get('val_init0');
    $this->isset = FALSE; 
    if($compl==TRUE){
      $this->key = $this->key_init;
      $this->set = $this->set_init();
    }
    return $this->_ok();
  }

  final function set($value){
    // accept objects of the same type allways!
    if(is_object($value) and get_class($value)===get_class($this)){
      $this->value = $value->get();
      $this->isset = TRUE;
      return $this->_ok();
    }

    // use accept for type-check
    $this->isset = FALSE;
    $typ = $this->get_type($value,FALSE,FALSE);
    if($this->accept($value,$typ)!==TRUE) 
      return $this->_err(array(3,strval($value)),FALSE);

    // get method responisble for this type
    $mth = 'set_' . (method_exists($this,'set_' . $typ)?$typ:'default');
    $res = $this->$mth($value);
    $this->isset = $this->isok();
    return $res;
  }

  function is_null(){return is_null($this->val);}

  function set_null($value)   {$this->val = $this->s_get('val_init'); return $this->_ok();}
  function set_default($value){$this->val = $value;          return $this->_ok();}

  function init(/*$value=NULL,$key=NULL,$set=array()*/){
    $na = func_num_args();
    if($na>0) $this->set(func_get_arg(0));
    if($na>1) $this->set_key(func_get_arg(1));
    if($na>2) $this->set_settings(func_get_arg(2));
  }



  // Subelements ============================================================
  protected function _pos($pos,$valid_only=FALSE){ return FALSE; }

  function ele_isvalid($key,$val=NULL){return $this->_pos($pos)!==FALSE;}
  function ele_isset($key,$val=NULL)  {return !is_bool($this->_pos($pos));}
  function ele_get($key,$val=NULL)    {$this->_err(6);}
  function ele_set($key,$val=NULL)    {$this->_err(6);}

  // Shortcuts and side infos ===============================================
  function get_value()   {return $this->get();}
  function get_key()     {return $this->key;}
  function get_settings(){return $this->set;}

  function set_value($value) {$this->set($value);}
  function set_key($key){
    if(is_string($key)) $this->key = $key;
    else trg_err(1,"Key is not a string",E_USER_ERROR);
  }
  function set_settings($set,$add=TRUE){
    if(!is_array($set)) return $this->_err(9);
    if($add) $this->set = array_merge($set,$set);
    else     $this->set = $set;
  }


  /* ================================================================================
   Import/Export
   ================================================================================ */
  function _exp_mode($mode=NULL){
    $mode = 'exp_' . nz($mode,$this->exp_mode_default);
    return method_exists($this,$mode)?$mode:FALSE;
  }

  final function exp($mode=NULL,$submode=NULL){
    $mode = $this->_exp_mode($mode);
    if($mode===FALSE) return $this->_err(array(5,$mode));
    return call_user_func(array(&$this,$mode),$submode);
  }

  function imp($value,$submode=NULL){
    $mode = $mth = 'imp_' . $this->get_type($value,FALSE,FALSE);
    if(!method_exists($this,$mode)) return $this->_err(array(6,$mode));
    return call_user_func_array(array(&$this,$mode),array($value,$submode));
  }

  function exp_raw(){return $this->get();}
  function exp_string(){return strval($this->val);}

  // Serialize ==================================================
  function exp_serial($compl=TRUE){
    if($compl){
      $res = array('isset'=>$this->isset,
		   'set'=>$this->set,
		   'key'=>$this->key);
    } else $res = array();
    $res['val'] = serialize($this->val);
    return serialize($res);
  }

  function imp_serial($serial){
    $data = unserialize($serial);
    foreach($data as $key=>$val) $this->$key = $val;
    return $this->_ok();
  }

  /* ================================================================================
   Side infos
   ================================================================================ */

  function info($what){
    if(isset($this->set[$what])) return $this->set[$what];
    return $this->key;
  }



  /* ================================================================================
   Type handling
   ================================================================================ */
  function accept($data,$type=NULL){
    if(is_object($data) and get_class($data)===get_class($this)) return TRUE;
    if(is_null($type)) $type = $this->get_type($data,FALSE,FALSE);
    else if(is_int($type)) $type = def(self::$types,$type,'null');
    $acc = def($this->accept,$type,0);
    if($acc<2) return $acc==1;
    $mth = 'check_' . $type;
    return $this->$mth($data);
  }

  function get_type($data,$num=FALSE,$deep=FALSE){
    if(is_null($data))         $res = 1;
    else if(is_bool($data))    $res = 2;
    else if(is_float($data))   $res = 6;
    else if(is_integer($data)) $res = 5;
    else if(is_numeric($data)) $res = 4;
    else if(is_string($data))  $res = 3;
    else if(is_array($data))   $res = 7;
    else if(is_object($data))  $res = 12;
    else if(is_resource($data))$res = 13;
    else                       $res = 0;
    if(!$deep and $num) return $res;
    if(!$deep) return self::$types[$res];
    $mth = 'get_subtype_' . self::$types[$res];
    if(method_exists($this,$mth)) return $this->$mth($data,$num);
    return $num?$res:self::$types[$res];
  }

  function get_owntype($num=FALSE,$deep=FALSE){
    return $this->get_type($this->val);
  }

  function get_ownclass(){
    $cls = get_class($this);
    return substr($cls,strrpos($cls,'_'));
  }

  function toitem($data){
    $type = $this->get_type($data,FALSE,FALSE);
    switch($type){
    case 'numeric': case 'string': $type = 'text'; //nobreak
    case 'null': case 'bool': case 'integer': case 'float':
      $cls = 'opc_item_' . $type;
      break;
    case 'array': $cls = 'opc_itemc_' . $type; break;
    case 'object':
      if($data instanceof opc_item) return $data;
      else if($data instanceof opc_hto) return $data->output();
      // nobreak
    default: // objects and ressources
      $data = strval($data); $type = 'string'; 
    }
    return new $cls($data);
  }

  function check_null()          {return TRUE;}
  function check_item($data)     {return TRUE;}
  function check_object($data)   {return FALSE;}
  function check_resource($data) {return FALSE;}
  function check_array($data)    {return TRUE;}
  function check_bool($data)     {return TRUE;}
  function check_float($data)    {return TRUE;}
  function check_integer($data)  {return TRUE;}
  function check_numeric($data)  {return TRUE;}
  function check_string($data)   {return TRUE;}

}

?>
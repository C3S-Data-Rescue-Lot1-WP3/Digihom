<?php
/* Ideen/Offen
 list_sim: findet bestpassenden Listeneintrag
 date: in diversen arten und Varianten
 string: mit SDtandards wie Name, E-Mail, URL etc.
 */

  /* principal behaviour

  short description

  the class will prepare a given value, check it and transform it to the final form.

  the class saves the given values in three variations
    $this->org: orginal value as given to set
    $this->pre: prepared value (after method prepare)
    $this->val: final value (after method transform)

  the result of checking is saved in $this->status (and $this->errcode, $this->errmsg)
  three main cases may occur
    valid standard case: the normal behaviour. $this->val contains the final value
      and $this->status is set to NULL
    valid special case: allows to catch special cases like NULL, empty String, out of range ...
      (depending from the subclass). The cases are defined in $this->except as named array
      where the key describes the case and value is used for $this->val
      the check method of the basis class (by using the default of $this->except) will check
      for NULL and empyt strings. To include more exceptions overload check and except.
    invalid value:
      $this->status: 'error'
      $this->errcode: code > 0; 
      $this->errmsg: a short description
      $this->val: $this->errval

   Some Details
      method prepare: by default it will trim a string (if $this->trim is TRUE)
        Overload this method for further options (like strtolower and so on)

      method check: 
        should check if the given value is valid at all and if a special case occurs ($this->except)
	if overloaded it will typically call the check of the parent class and do further
	checks if $this->status is still NULL
	
        rem: should not change the value given as argument
        
      method transform:	Will create the final value (if $this->status is NULL)
        by default the function is empty. A typicall function inside this method would be
	intval for transformation to a numeric value.

   Details the real things hapens by the call of method set:
   1) the value given to set is saved as it is in $this->org
      In the most cases value will be a string (like given by a form).
   2) as next method prepare is called, which can be overloaded (result saved in $this->pre)
      In the basis class prepare only trims the value if it is a string and $this->trim is TRUE.
   3) Now the method check is used (check should not change the value itself)
      In the basis class checks does the following things
      i) proofs the value againts $this->except (a named array)
         if a key 'NULL' exists and value is NULL the value in except will be returned
	 if a key 'empty' exists and the value is an empty string the value ...
      ii) if value is a string and $this->checkpat is TRUE it will call method check_pattern
         check_pattern proofs if value at least fits one pattern of $this->pat (if not NULL)
	 and none of $this->ipat. pat and ipat has to be array of patterns for preg_match or NULL
    4) if status is still ok (=NULL) the method transform is called (result saved in $this->val)
       In the basic class transform is empty. A typically use would be intval
    5) Set returns a numeric code (0: success; see $this->errcode & $this->errmsg)

    $this->status will be set by method set and can be used to check the success of set
      NULL -> value is valid and no situation of $this->except occurred
      'error' -> value is neiter valid nor an exception described in $this->except
      [exception] -> value caused an exception

    settings:
      method settingnames returns an array of the available settings
      method settings allows to set/get one or more settings (by an array)
      method str_settings similar but based on as string
      method _setting is called by settings and can be overloaded for checks and similar things
   */

class opc_varcheck{
  // the value
  var $org = NULL; // as given to function set
  var $pre = NULL; // including some basic preparations (like trim)
  var $val = NULL; // return value

  // success?
  var $status = NULL; // NULL or string like NULL; empty, error ... (see except too)
  var $errcode = 0;
  var $errmsg = '';

  // overload the following if necessary --------------------------------------------------
  // basic settings
  var $trim = TRUE; // trim value in set if it is a string
  var $checkpat = FALSE; // call check_pattern?
  //values used by non NULL stauts (=exception)
  var $except = array('NULL'=>NULL,'empty'=>'');
  //value returned if an error occured
  var $errval = NULL;
  // patterns (used inside check_pattern, called by check, if checkpat is TRUE)
  var $pat = NULL; // acceptable patterns in an array or NULL to ignore this
  var $ipat = NULL; // inacceptable patterns in an array or NULL to ignore this
  //overlaod to add additional checks and transformations ==================================================

  // will be called before check used at the beginning of check (include here things like strtolower)
  function prepare($value){ 
    return((is_string($value) and $this->trim)?trim($value):$value);
  }

  function transform($value){ return($value);}

  function check($value){
    $this->status = NULL;
    if(is_null($value)){
      if($this->_exception('NULL',2)<>0) return(2);
    } else if($value===''){
      if($this->_exception('empty',3)<>0) return(3);
    } else if(is_string($value) and $this->checkpat){
      $res = $this->check_pattern($value); if($res<>0) return($res);
    } else $this->status = NULL;
    return($this->error(0));
  }

  /*
   returns a array of valid setting names (used my method settings)
   rem: except and errval are allways accpted
  */
  static function settingnames(){
    return(array('trim','checkpat','pat','ipat'));
  }

  //set a single setting; overload to check them in advance
  function _setting($var,$val){
    $this->$var = $val;
  }

  function error($id=0){
    $this->_error_int($id);
    return($id);
  }

  // here just a dummy
  function str_settings($settings){ }

  // This functions shoud work without overloading ==================================================

  function opc_varcheck($settings=array()){
    if(!is_array($settings)) $this->str_settings($settings);
    else if(count($settings)>0) $this->settings($settings);
  }

  //similar as set but returns the saved value instead of the error-code
  function setget($value){ $this->set($value); return($this->get());}

  /*
   direct is similar to setget but can be called static (will create itself temporary)
   settings: array as used in the constructor
   return:
     0/FALSE: the value
     1/TRUE: array with value, status and errorcode
     2: errorcode
  */
  static function direct($value,$settings=array(),$return=FALSE,$class='opc_varcheck'){
    return(opc_varcheck::_generic($value,$class,$settings,$return));
  }

  /* similar to direct, but the the additional argument class
   is the name of a defined subclass of opc_varcheck (the 'opc_ ' at the 
   beginnig may be missing */
  static function generic($value,$class,$settings=array(),$return=FALSE){
    if(substr($class,0,4)!='opc_') $class = 'opc_' . $class;
    if(class_exists($class)) 
      return(opc_varcheck::_generic($value,$class,$settings,$return));
    else
      return(NULL);
  }

  static function _generic($value,$class,$settings,$return){
    $tc = new $class($settings);
    $tc->set($value);
    switch((int)$return){
    case 2: return($tc->errcode);
    case 1: return(array($tc->get(),$tc->status,$tc->errcode));
    default:
      return($tc->get());
    }
  }

  function check_pattern($value){
    if(is_array($this->pat)){
      $ok = false;
      foreach($this->pat as $cpat) if(preg_match($cpat,$value)) $ok = true;
      if($ok==false) return($this->error(9));
    }
    if(is_array($this->ipat))
      foreach($this->ipat as $cpat)
	if(preg_match($cpat,$value)) return($this->error(9));
    return(0);
  }

  function set($value){
    $this->org = $value;
    $this->pre = $this->prepare($this->org);
    if($this->check($this->pre)<>0) return($this->errcode);
    $this->val = is_null($this->status)?$this->transform($this->pre):$this->except[$this->status];
    return(0);
  }

  /* function to set/get one or more settings
   val & var IS NULL -> returns a named array of all settings
   val IS NULL -> returns the value of the setting in var
   var IS NULL -> val is a named array containing the new settings
       returns the number of not succesfull set variables
   var IS NOT NULL -> val is the new value of setting var; returns 0: success; 1 otherwise
   only settings 'except', 'errval' and those listed in settingnames() are accepted
   */
  function settings($val=NULL,$var=NULL){
    $vars = array_merge(array('except','errval'),$this->settingnames());
    if(is_null($val)){
      if(is_string($var)) return(in_array($var,$vars)?$this->$var:NULL);
      else {
	if(is_array($var)) $vars = array_intersect($vars,$var);
	$res = array();
	foreach($vars as $cv) $res[$cv] = $this->$cv;
	return($res);
      }
    } else {
      if(!is_null($var)){
	if(!in_array($var,$vars)) return(1);
	$this->_setting($var,$val);
	return(0);
      } else {
	$ec = 0;
	while(list($ak,$av)=each($val)) 
	  if(!in_array($ak,$vars)) $ec++; else $this->_setting($ak,$av);
	return($ec);
      }
    }
  }

  
  function get(){ return($this->val);}
  function status(){return($this->status);}
  function errcode(){return($this->errcode);}
  function errmsg(){return($this->errmsg);}


  // internal use only --------------------------------------------------
  /*
   typ is a potential key in except. If yes status will be set to typ a 0 is returned
   oterwise error given in err (default 8) will be set and given back
   */
  function _exception($typ,$err=8){
    if(array_key_exists($typ,$this->except)) {
      $this->status = $typ; 
      return(0);
    } else return($this->error($err));
  }

  /*
   should be called by funtion error only
   returns true if the function knows the error id, otherwise false,
   which should be captured by sub classes.
  */
  function _error_int($id){
    $this->errcode = $id;
    if($id==0) {
      $this->errmsg = NULL;
      return(true);
    }
    $this->status = 'error';
    $this->val = $this->errval;
    switch($id){
    case 2: $this->errmsg = 'NULL not allowed'; ; break;
    case 3: $this->errmsg = 'Empty string not allowed'; break;
    case 4: $this->errmsg = 'Out of range'; break;
    case 5: $this->errmsg = 'Invalid size'; break;
    case 6: $this->errmsg = 'Invalid characters'; break;
    case 7: $this->errmsg = 'Invalid data type'; break;
    case 8: $this->errmsg = 'Invalid value'; break;
    case 9: $this->errmsg = 'Invalid syntax'; break;
    case 10: $this->errmsg = 'Too small'; break;
    case 11: $this->errmsg = 'Too large'; break;
    case 13: $this->errmsg = 'Unknown class'; break;
    default:
      $this->errmsg = 'Unknown error'; return(false);
    }
    return(true);
  }
}

/* =====================================================================================================
 ======================================== Sub-Classes ==================================================
 ===================================================================================================== */

class opc_vc_bool extends opc_varcheck{

  var $except = array('NULL'=>FALSE,'empty'=>FALSE);
  var $false = array('false','f','n','no','non','nein','off','-');
  var $true = array('true','t','y','yes','on','ja','j','+');

  function prepare($value){ return(is_string($value)?strtolower($value):$value);}
  
  function transform($value){
    if(is_bool($value)) return($value);
    if(is_numeric($value)) return($value <> 0);
    if(is_string($value)) return(in_array($value,$this->true));
    return($value);
  }

  static function direct($value,$settings=array(),$return=FALSE,$class='opc_vc_bool'){
    return(opc_varcheck::_generic($value,$class,$settings,$return));
  }

  function check($value){
    if(parent::check($value)<>0) return($this->errcode);
    if(is_bool($value)) return(0);
    if(is_string($value)){
      if(in_array($value,$this->true)) return(0);
      if(in_array($value,$this->false)) return(0);
      return($this->error(8));
    }
    return($this->error(7));
  }

  static function settingnames(){
    $res = array('true','false');
    return($res);
  }

}



class opc_vc_numeric extends opc_varcheck{
  // 2 numbers or NULL (lower and upper limit) 2 booleans (including excluding)
  var $range = array(NULL,NULL,FALSE,FALSE);
  var $except = array('NULL'=>NULL,'empty'=>NULL,'low'=>NULL,'high'=>NULL);
  var $trim = TRUE;
  var $checkpat = TRUE;
  var $pat = array('/^[-+]?[0-9]+([.,][0-9]*)?([Ee][-+]?[0-9]+)?$/',
		   '/^[-+]?[.,][0-9]+([Ee][-+]?[0-9]+)?$/');

  static function settingnames(){
    return(array('checkpat','pat','ipat','range'));
  }

  static function direct($value,$settings=array(),$return=FALSE,$class='opc_vc_numeric'){
    return(opc_varcheck::_generic($value,$class,$settings,$return));
  }

  function check_range($value){
    if(is_numeric($value)){
      if(!is_null($this->range[0])){
	if($this->range[2]?($value < $this->range[0]):($value <= $this->range[0])){
	  if($this->_exception('low',4)<>0) return(10);
	}
      }
      if(!is_null($this->range[1])){
	if($this->range[3]?($value > $this->range[1]):($value >= $this->range[1])){
	  if($this->_exception('high',4)<>0) return(11);
	}
      }
    }
    return(0);
  }

  function prepare($value){ return(is_string($value)?strtolower(trim($value)):$value);}

  function transform($value){
    if(is_bool($value)) return($value?1:0);
    if(is_string($value)) return(floatval(str_replace(',','.',$value)));
    return($value);
  }

  function check($value){
    if(parent::check($value)<>0) return($this->errcode);
    if(!is_numeric($value) and !is_string($value)) return($this->error(7));
    return($this->check_range($value));
  }
}

class opc_vc_int extends opc_vc_numeric{
  // how to round non integers: 'ceil', 'floor', 'round', NULL (leads to an error)
  var $roundmode = 'round';

  static function settingnames(){
    return(array_merge(parent::settingnames(),array('roundmode')));
  }

  static function direct($value,$settings=array(),$return=FALSE,$class='opc_vc_int'){
    return(opc_varcheck::_generic($value,$class,$settings,$return));
  }


  function check($value){
    if(parent::check($value)<>0) return($this->errcode);
    if(!is_numeric($value) and !is_string($value)) return($this->error(7));
    $value = $this->transform(floatval($value));
    return($this->check_range($value));
  }

  function transform($value){
    switch($this->roundmode){
    case 'round': return(round($value));
    case 'ceil': return(ceil($value)); 
    case 'floor': return(floor($value));
    }
    return($value);
  }
}

class opc_vc_numid extends opc_vc_int{
  // how to round non integers: 'ceil', 'floor', 'round', NULL (leads to an error)
  var $roundmode = 'floor';

  static function direct($value,$settings=array(),$return=FALSE,$class='opc_vc_numid'){
    return(opc_varcheck::_generic($value,$class,$settings,$return));
  }

  function check($value){
    if(parent::check($value)<>0) return($this->errcode);
    return($this->val<0 ? $this->error(4) : 0);
  }
}


// reads an integer also in non decimal systems
class opc_vc_numsystem extends opc_vc_numeric{
  /*
   2 numbers or NULL (lower and upper limit) 2 booleans (including excluding)
   first two may be strings if $this->base is not NULL
  */
  var $range = array(NULL,NULL,FALSE,FALSE); 
  var $except = array('NULL'=>NULL,'empty'=>NULL,'low'=>NULL,'high'=>NULL);
  /*
   Defines the base of the given value (2-36) or NULL for auto rec.
   auto:
     base 16 if: starting with  & &# &h &H # x X \x \X or end with h H x X
     base 8  if:  starting with  &o &O o O \o \O or end with o O
     others style: like value(base)  value[base] value:base
  */
  var $base = NULL;

  // how the result shoul appear
  // integer (2-36) for the result or a string used for sprintf (ignoring the other options)
  var $result_base = 10;   
  var $result_size = NULL;// if numeric >0  '0' chars are added to fit the size (if shorter)
  var $result_case = TRUE; //if letters are used they are lower(FALSE) or upper(TRUE) case

  // internal use only, will be set by prepare
  var $_base = 10;
  var $pat = array();
  var $checkpat = FALSE; // will done manually here

  static function settingnames(){
    return(array('base','range','result_base','result_size','result_case'));
  }

  static function direct($value,$settings=array(),$return=FALSE,$class='opc_vc_numsystem'){
    return(opc_varcheck::_generic($value,$class,$settings,$return));
  }


  function prepare($value){
    if(is_string($value)){
      $value = trim(strtolower($value));
      if(is_null($this->base)){
	$pat = '/^(.*) *(\([0-9]+\)|\[[0-9]+\]|:[0-9]+)$/';
	if(preg_match($pat,$value)){
	  $value = explode(' ',preg_replace($pat,'$1 $2',$value));
	  $this->_base = intval(substr($value[1],1));
	  $value = $value[0];
	} else if(preg_match('/^(o|\x26o|\x5Co)/',$value)){
	  $this->_base = 8; $value = substr($value,strpos($value,'o')+1);
	} else if(substr($value,-1)=='o'){
	  $this->_base = 8; $value = substr($value,0,-1);
	} else if(substr($value,-1)=='x' or substr($value,-1)=='h'){
	  $this->_base = 16; $value = substr($value,0,-1);
	} else if(preg_match('/^(\x26|\x5C)(\x23|h|x)/',$value)){
	  $this->_base = 16; $value = substr($value,2);
	} else if(preg_match('/^(\x26|\x23|x|h)/',$value)){
	  $this->_base = 16; $value = substr($value,1);
	}
      } else $this->_base = $this->base;
      if($this->_base>10) $this->pat = '/^[0-9a-' . chr($this->_base+86) . ']+$/';
      else                $this->pat = '/^[0-' . ($this->_base-1) . ']+$/';
    }
    return($value);
  }

  function check($value){
    if(parent::check($value)<>0) return($this->errcode);
    if(is_string($this->range[0])) $this->range[0] = intval($this->range[0],$this->base);
    if(is_string($this->range[1])) $this->range[1] = intval($this->range[1],$this->base);
    if(is_string($value)){
      if(!preg_match($this->pat,$value))  return($this->error(9));
      $value = intval($value,$this->_base);
    } else if(!is_numeric($value)) return($this->error(7));
    return($this->check_range($value));
  }

  function transform($value){
    if(is_string($value)) $value = intval($value,$this->_base);
    if(is_string($this->result_base)) return(sprintf($this->result_base,$value));
    if($this->result_base<>10 and $this->result_base>1 and $this->result_base<=36){
      $base = floor($this->result_base);
      $res = '';
      while($value){
	$mod = $value % $base;
	$value = floor($value/$base);
	$res = ($mod<10?$mod:chr($mod+55)) . $res;
      }
      $value = $res;
    }
    if(!is_null($this->result_size)){
      if(is_numeric($value)) $value = strval($value);
      if(strlen($value)<$this->result_size) 
	$value = str_repeat('0',$this->result_size-strlen($value)) . $value;
    }
    if($this->result_base>10 and !$this->result_case) $value = strtolower($value);
    return($value);
  }
}


/*
 Generic type where value is an array or string of single items. Returns an array

 note: the function check will do check and tarnsform; therefore transform does not exists
*/
class opc_vc_list extends opc_varcheck{
  /* Type Settings
   opc_vc_list needs to know the type of each possible item
   if type is as string it allows a unknown number of this type (with the same settings)
   if type is an array of strings types/settings may vary but the number is fixed
   the type-string is the class name without the leading 'opc_'
   settings has to be similar to type: an array, containing the settings or
   an array (same size as the type array) containing arrays of settings
   a NULL type will only process the syntax part and return an array of resulting strings
  */
  var $type = NULL; 
  var $settings = NULL;

  /* Syntax Settings
   the default settings are optimized for speed
   the typically enhanced setting would be $quote='both' $quote='\\' $named=TRUE
   remark: $quote = '\\' needs a doubleslash since php will interpret this a single slahs

   Sepatartor: string to separate between the elements, if it is a space multiple
   spaces would be handeld like a single one

   Quoting: Allows to use the sparator inside the value, primary used for strings
   NULL: no quotings
   single or s: using ', inside a '-part " is allowed
   double od d: using ", inside a "-part ' is allowed
   both or b: using " and '; the other is allowed inside

   Masking: Allows to use spearator inside the value or the quotes inside the area
   if masking is used they will be removed for the result
   NULL: no masking
   [single character]: typically as backslash

   Names: allows to use name which are used in the resulting array too
   setchar defines the string between name and element
   if a name occurs more than once, the last value is used

   names and values will be trimed if not quoted
   */

  var $separator = " ";
  var $emptyok = FALSE;
  var $quote = NULL;
  var $mask = NULL;
  var $setchar = NULL;

  /* other settings */
  var $stop = FALSE;//stop after first error (faster) or try all (comfortable)

  static function settingnames(){
    return(array('type','settings','separator','quote','mask','setchar'));
  }


  static function direct($value,$settings=array(),$return=FALSE,$class='opc_vc_list'){
    return(opc_varcheck::_generic($value,$class,$settings,$return));
  }

  function _error_int($id){
    if(!parent::_error_int($id)){
      switch($id){
      case 101: $this->errmsg = 'number of arguments, types and settings are not equal'; break;
      default:
	return(false);
      }
    }
    return(TRUE);
  }

  function set($value){
    $this->org = $value;
    $this->pre = $this->prepare($this->org);
    return($this->check($this->pre));
  }

  function prepare($value){
    if(is_array($value)) return($value);
    return(ops_arguments::argexploder($value,$this->separator,$this->quote,
				  $this->mask,$this->setchar));
  }

  function check($value){
    $attrs = array('val','status','errcode','errmsg');//those vars will be saved as array
    foreach($attrs as $ca) $this->$ca = array();
    
    if(is_array($this->type)){ //multiple types/settings; known number
      if(is_null($this->settings)) $this->settings = array_fill(0,count($this->type),NULL);
      if(count($this->type)<>count($value) or count($this->settings)<>count($value))
	return($this->error(101));
      $ci = 0;
      while(list($ak,$av)=each($value)){
	$vc = 'opc_' . (is_null($this->type[$ci])?'varcheck':$this->type[$ci]);
	$vc = new $vc($this->settings[$ci]);
	$cerr = $vc->set($av);
	foreach($attrs as $ca){$cv = $this->$ca; $cv[$ak] = $vc->$ca; $this->$ca = $cv;}
	if($this->stop and $cerr>0) return($cerr);
	$ci++;
      } 
    } else {// one type and setting; unknown number
      $vc = 'opc_' . (is_null($this->type)?'varcheck':$this->type);
      $vc = new $vc($this->settings);
      while(list($ak,$av)=each($value)){
	$cerr = $vc->set($av);
	foreach($attrs as $ca){$cv = $this->$ca; $cv[$ak] = $vc->$ca; $this->$ca = $cv;}
	if($this->stop and $cerr>0) return($cerr);
      }
    }
    return($this->overallerrcode());
  }

  // returns an array of stati or one if all are equal
  function overallstatus(){
    $cstat = array_unique($this->status);
    return(count($cstat)==1?$cstat:$this->status);
  }

  // returns an array of error codes or one if all are equal
  function overallerrcode(){
    $cerr = array_unique($this->errcode);
    return(count($cerr)==1?$cerr:$this->errcode);
  }


}



// allows only items given in a list
class opc_vc_item extends opc_varcheck{
  var $usekeys = FALSE; //If true the keys of allowed will define the final value
  var $caseins = TRUE; // If true case insensitive, please use therfore only lowercase in allowed
  var $allowed = array();
  var $except = array('notinlist'=>NULL,'NULL'=>NULL,'empty'=>'');  

  static function settingnames(){
    return(array('trim','caseins','usekeys','allowed'));
  }

  function prepare($value){
    if(!is_string($value)) return($value);
    if($this->trim) $value = trim($value);
    if($this->caseins) $value = strtolower($value);
    return($value);
  }

  static function direct($value,$settings=array(),$return=FALSE,$class='opc_vc_item'){
    return(opc_varcheck::_generic($value,$class,$settings,$return));
  }

  function check($value){
    if(parent::check($value)<>0) return($this->errcode);
    if(!is_null($this->status)) return(0);
    if(in_array($value,$this->allowed)) return(0);
    return($this->_exception('notinlist',8)<>0?8:0);
  }
  
  function transform($value){
    return($this->usekeys?array_search($value,$this->allowed):$value);
  }

}


class opc_vc_string extends opc_varcheck{
  var $case = NULL; //TRUE -> to uppercase, FALSE -> to lower
  var $size = NULL; // integer -> max size; array(int,int) -> min/max size
  var $except = array('NULL'=>NULL,'empty'=>'','size'=>NULL);
  var $checkpat = TRUE;

  //can be used inside a pattern with %%name%% (but not alone)
  var $patpart = array('name'=>'[_a-zA-Z][_a-zA-Z0-9]*',//typically name rectriction
		       'domain'=>'([_a-zA-Z][-_a-zA-Z0-9]*[.])+[a-zA-Z]{2,4}+', // in urls or mails after @
		       'mailname'=>'([_a-zA-Z][-_a-zA-Z0-9]*[.])*[_a-zA-Z][-_a-zA-Z0-9]*', //part befor @
		       'urlprot'=>'(ftp|http|https|ftps):\/\/)',
		       'urlfile'=>'(%[0-9a-fA-F]{2}|[-~\._0-9a-zA-Z])+',
		       'urlpath'=>'%%urlfile%%',//(%%urlfile%%\/)%%urlfile%%\/?)',
		       'd256'=>'(25[0-5]|2[0-4][0-9]|[01]?[0-9]?[0-9])',//digit 0-255 incl leading 0
		       'dFF'=>'[0-9a-fA-F]{1,2}', //1-2 hex chars
		       'dFFFF'=>'[0-9a-fA-F]{1,4}' //1-4 hex chars
		       );
  //some predefined patterns
  var $pattern = array('nonempty'=>'/^.+$/',
		       'name'=>'/^%%name%%$/',
		       'mail'=>'/^%%mailname%%@%%domain%%$/',
		       'ip4'=>'/^(%%d256%%\.){3}%%d256%%$/',
		       'ip4hex'=>'/^(%%dFF%%:){3}%%dFF%%$/',
		       'ip6'=>'/^(%%dFFFF%%\:){7}%%dFF%%$/',
		       'url'=>'/^%%urlprot%%?%%domain%%(\/%%urlfile%%)?$/'
		       );

  static function settingnames(){
    return(array('trim','case','size','pat','ipat','checkpat'));
  }

  static function direct($value,$settings=array(),$return=FALSE,$class='opc_vc_string'){
    return(opc_varcheck::_generic($value,$class,$settings,$return));
  }

  function str_settings($setting){
    $this->settings(array('pat'=>array($setting)));
  }

  function prepare($value){
    if(is_object($value)) return('');
    if(!is_string($value)) $value = strval($value);
    if($this->trim) $value = trim($value);
    if($this->case===TRUE) $value = strtoupper($value);
    else if($this->case===FALSE) $value = strtolower($value);
    return($value);
  }

  function _prepare_pattern($pats){
    if(is_string($pats)) $pats = array($pats);
    else if(!is_array($pats)) return(NULL);
    while(list($ak,$av)=each($pats)){
      if(preg_match('/^[_a-z][_a-z0-9]+$/i',$av) and isset($this->pattern[$av]))
	$av = $this->pattern[$av];
      $nv = $av;
      do {
	reset($this->patpart);
	while(list($bk,$bv)=each($this->patpart)) $nv = str_replace('%%' . $bk . '%%',$bv,$nv);
	$xx = $nv<>$av;
	$av = $nv;
      } while($xx);
      $pats[$ak] = $av;
    }
    return($pats);
  }

  function check_pattern($value){
    $pat = $this->_prepare_pattern($this->pat);
    if(is_array($pat)){
      $ok = false;
      foreach($pat as $cpat) if(preg_match($cpat,$value)) $ok = true;
      if($ok==false) return($this->error(9));
    }
    $pat = $this->_prepare_pattern($this->ipat);
    if(is_array($pat)) foreach($pat as $cpat) if(preg_match($cpat,$value)) return($this->error(9));
    return(0);
  }

  function check($value){
    if(parent::check($value)<>0) return($this->errcode);
    if(is_numeric($this->size)){
      if(strlen($value)>$this->size) if($this->_exception('NULL',11)<>0) return(11);
    } else if(is_array($this->size)){
      if(!is_null($this->size[0])) 
	if(strlen($value)<$this->size[0]) if($this->_exception('NULL',10)<>0) return(10);
      if(!is_null($this->size[1])) 
	if(strlen($value)>$this->size[1]) if($this->_exception('NULL',11)<>0) return(11);
    }
    return(0);
  }
}









/* ============================================================
OLD STUFF
============================================================ */







class opc_inputcheck{

  var $mem; //memory of this class (named array)
  var $keys; // Array-Keys of mem
  
  //variable list (set by limitvar)
  var $varl;

  /*
   named array with limitations for the variables
   each item is one of the following (name=>value)

   case=>TRUE/false // is the value case sensitiv or not
   ... if not, everything is rewritten in lower cases!

   null=>TRUE/false: NULL values or empty strings are OK

   pattern=>pattern or array(pattern1,...) //as used in preg_grep
   ... or one of the following predefined names
   ... id -> '/^[0-9]+$/'
   ... int -> '/^[-+]?[0-9]+$/'
   ... num -> '/^[-+]?[0-9]+([.,][0-9]*)?([Ee][-+]?[0-9]+)?$/',
   ... .......'/^[-+]?[.,][0-9]+([Ee][-+]?[0-9]+)?$/'
   ... name -> '/^[a-zA-Z_][a-zA-Z0-9_]?$/'
   ... text -> any text but no linefeeds (default)
   ... memo -> accepts all (including line feed and so on)

   npattern=>pattern or array(pattern1,...) //as pattern but opposite result

   size=>array(min,max) //strlen between min and max (including)
   ... if array has only one element it min is set to 1

   allow=>array(value1,value2,....) //list of possible values

   deny=>array(value1,value2,....) //list of not allowed values

   char=>one of (all is default) // allowed chars
   ... all
   ... asc: only characters with ascii-code 32 to 126 are accepted

   range(min,max,TRUE/false,TRUE/false)//limit for numbers
   ...the 3th and 4rd element defines if the limit is including (<= >=) or not

   html=>one off
   ... no: a text with tags inside will be denied (default)
   ... noevents: a text with a tag with an 'on???' attribute is denied
   ... array(tag1,tag2): only the listed tags with no attributes are allowed!

   list=>character: if set character is used for explode

   elist=>character: if set character is used for explode_save (Allows '")


   if pattern is id/int/num the result is a number
   ... and only range is used as limitation too

   if list/elist is used the result is a list
   ... and only allow and deny are used

   in any other case the result is a string
   ... and range is ignored

   p is the short of pattern

  */
  var $lim; 

  var $erc; // last error/warning code
  var $erm; // last error/warning message
  var $erl; // array of all errors/warnings

  /* 
   warning mode
   return: just return error code (default)
   nothing: ignore all errors (if possible)
   warn: print a warning and return error code
   die: print warning and stop all
  */
  var $wmode; 
 
  //constructer
  function ipc_inputcheck(){
    $this->flush();
    return(0);
  }

  // reset all except $wmode
  function flush(){
    $this->mem = array();
    $this->keys = array();
    $this->varl = array();
    $this->lim = array();
    $this->erl = array();
    $this->erm = NULL;
    $this->erc = 0;
    $this->wmode = 'warn';
    return(0);
  }

  // flush memory and array_keys
  function flushmem(){
    $this->mem = array();
    $this->keys = array();
    return(0);
  }

  // reset error related variables
  function errflush(){
    $this->erl = array();
    $this->erm = NULL;
    $this->erc = 0;
    return(0);
  }

  function errlist(){
    $re = array();
    foreach($this->erl as $ce) if($ce[0]>0) $re[] = $ce;
    return($re);
  }

  /* transform one or moe variables in mem
   transform is a list of short codes how the variables should be transformed
   it will be processed from left to right
   Uppercaseletters a re single character codes others have to be separated by spaces
   varname: one or more varnem from mem or NULL for complete mem
   save: if true the transformed values are saved back
  */

  function transform($trans='',$varname=NULL,$save=FALSE,$add=NULL){
    if(is_null($varname)) $vns = $this->keys;
    elseif(is_string($vns)) $vns = array($varname);
    elseif(is_array($vns)) $vns = $varname;
    else return($this->af_wmode(110,NULL,'varname'));
    $cv = array();
    foreach($vns as $vn) $cv[$vn] = isset($this->mem[$vn])?$this->mem[$vn]:NULL;
    if($save) foreach($vns as $vn) $this->mem[$vn] = $cb[$vn];
    $trans = explode(' ',$trans);
    foreach($trans as $ct){
      switch($ct){
      case 'trim': foreach($vns as $vn) $cv[$vn] = trim($dv[$vn]); break;// trim
      case 'ltrim': foreach($vns as $vn) $cv[$vn] = ltrim($dv[$vn]); break;// left trim
      case 'rtrim': foreach($vns as $vn) $cv[$vn] = rtrim($dv[$vn]); break;// right trim
      case 'nl2br': foreach($vns as $vn) $cv[$vn] = nl2br($dv[$vn]); break;// newline to br
      case 'br2nl': foreach($vns as $vn) $cv[$vn] = preg_replace('/<br( [^>]*>/c',"\n",$dv[$vn]); break; // <br> to newline
      case 'nl2spc': foreach($vns as $vn) $cv[$vn] = preg_replace('/(\n\r|\r\n|\r|\n)/',' ',$dv[$vn]); break; // newline to space
      case 'nspc': foreach($vns as $vn) $cv[$vn] = preg_replace('/ +',' ',$dv[$vn]); break; // muliple spaces
      case 'striptags': foreach($vns as $vn) $cv[$vn] = strp_tags($dv[$vn]); break;// strip tags (php bild in)
      case 'html':
      case 'htmlall': foreach($vns as $vn) $cv[$vn] = htmlentities($dv[$vn]); break;// newline to br
      }
    }
    if(is_string($varname)) return($cv[$varname]); else return($cv);
  }

  /* loads a named array or if what is a string
      get: $_GET
      post: $_POST
      both: merge from $_GET and $_POST (post preffered)
      query: QUERY_STRING in $_SERVER['QUERY_STRING']
      [other]: a user string read as query

   all values will be trimmed in advance
   if query_string is used and a variable name appears more than once
    a number (1,2, ...) will be added to identify them: eg person_13
    for such situations fusion_pattern would be a useful next function
   */
  function load($what='get',$transform='t'){
    if(is_array($what)) {
      $this->mem = array_merge($this->mem,$what);
    } else {
      switch(strtolower($what)){
      case 'get': $va = array_merge($this->mem,$_GET); break;
      case 'post': $va = array_merge($this->mem,$_POST); break;
      case 'both': $va = array_merge($this->mem,$_GET,$_POST); break;
      case 'query': $what = $_SERVER['QUERY_STRING']; //no break
      default:
	$vb = array();	$vc = array();
	$qs = explode('&',$what);
	foreach($qs as $qi){
	  $qi = explode('=',$qi);
	  if(!isset($vc[$qi[0]])){
	    $vb[$qi[0]] = urldecode($qi[1]);
	    $vc[$qi[0]] = 1;
	  } else{
	    if($vc[$qi[0]]==1){
	      $vb[$qi[0] . '_0'] = $vb[$qi[0]];
	      unset($vb[$qi[0]]);
	    }
	    $vb[$qi[0] . '_' . $vc[$qi[0]]] = urldecode($qi[1]);
	    $vc[$qi[0]] += 1;
	  }
	}
	$va = array_merge($this->mem,$vb);
	break;
      default:
	return(110);
      }
      if(count($va)==0) $this->mem = array(); else while(list($ak,$av)=each($va)) $this->mem[$ak] = trim($av);
    }    
    $this->keys = array_keys($this->mem);
    return(0);
  }

  /* 
   gets one or more value out of mem
   limits: if NULL the default from lim is used (otherwise it will set it)
   def: default value if the value does not match the limits (NULL)
   varname: a single varname, a array of varnames or a named array
   ... in the same style as lim
  */
  function get($varname,$limits=NULL,$def=NULL){
    if(is_array($varname)){
      if(is_null($limit)) $limits = $this->lim;
      $re = array();
      foreach($varname as $va){
	$re[$va] = $this->get($va,isset($lim[$va])?$lim[$va]:NULL,
			      isset($default[$va])?$default[$va]:NULL);
      }
      return($re);
    }
    $limdef = array('pattern'=>'text','case'=>TRUE,
		    'null'=>TRUE,'html'=>'no',
		    'char'=>'all');
    if(!is_null($limits)){
      if(!is_array($limits)) $limits = array('pattern'=>$limits);
      $this->lim[$varname] = $limits;
      $lim = array_merge($limdef,$limits);
    } elseif(isset($this->lim[$varname])) 
      $lim = array_merge($limdef,$this->lim[$varname]);
    else
      $lim = $limdef;

    $short = array('p'=>'pattern');
    while(list($ak,$av)=each($short))
      if(isset($lim[$ak])) {
	$lim[$av] = $lim[$ak]; 
	unset($lim[$ak]);
      }

    if(is_null($def) and isset($lim['def'])) $def = $lim['def'];

    if(!isset($this->mem[$varname]) or strlen($this->mem[$varname])==0)
      return($this->af_wmodeN($def,$lim['null']?0:156,NULL,$varname));

    $val = $this->mem[$varname];
    if(is_array($val)){ $this->af_wmode(0); return($val);}

    if(is_array($lim['pattern'])){
      $typ = 'text';
      $pa = $lim['pattern']; 
    } elseif(substr($lim['pattern'],0,1)=='/') {
      $typ = 'text';
      $pa = array($lim['pattern']);
    } else {
      $typ = $lim['pattern'];
      switch($typ){
      case 'id': $pa = array('/^[0-9]+$/'); break;
      case 'int': $pa = array('/^[-+]?[0-9]+$/'); break;
      case 'num': 
	$pa = array('/^[-+]?[0-9]+([.,][0-9]*)?([Ee][-+]?[0-9]+)?$/',
		    '/^[-+]?[.,][0-9]+([Ee][-+]?[0-9]+)?$/',
		    '/^[-+]?[0-9]+$/');
	break;
      case 'name': $pa = array('/^[a-zA-Z_][a-zA-Z0-9_]?$/'); break;
      case 'text': $pa = array(); break;
      case 'memo': $pa = array(); break;
      default:
	return($this->af_wmodeN($def,110,NULL,$varname));
      }
    }
    if(!$lim['case']){
      $val = strtolower($val);
      while(list($ak,$av)=each($pa)) $pa[$ak] = strtolower($av);
    }
    
    foreach($pa as $cp)
      if(!preg_match($cp,$val))
	return($this->af_wmodeN($def,150,NULL,$varname));
    switch($typ){
      
    case 'num': case 'id': case 'int': 
      if($typ=='num'){
	$val = explode('e',strtolower(str_replace(',','.',$val)));
	if(count($val)==1) $val = (float)$val[0];
	else $val = (float)$val[0] * pow(10,(int)$val[1]);
      } else 	$val = (int)$val;
      if(isset($lim['range'])){
	$ra = $lim['range'];
	if(count($ra)==2) $ra = array($ra[0],$ra[1],TRUE,TRUE);
	if(count($ra)!=4) return($this->af_wmodeN($def,200,NULL,$varname));

	if($ra[2] and $val < $ra[0]) 
	  return($this->af_wmodeN($def,154,NULL,$varname));
	if(!$ra[2] and $val <= $ra[0]) 
	  return($this->af_wmodeN($def,154,NULL,$varname));
	if($ra[3] and $val > $ra[1]) 
	  return($this->af_wmodeN($def,154,NULL,$varname));
	if(!$ra[3] and $val >= $ra[1]) 
	  return($this->af_wmodeN($def,154,NULL,$varname));
      }
      return($val);
      break;
    case 'text':case 'name': case 'memo':
      if($typ!='memo' and preg_match('/[\x0A\x0D\n]/',$val))
	return($this->af_wmodeN($def,159,NULL,$varname));
      
      $val = str_replace('\"','"',$val);
      $val = str_replace('\\\'','\'',$val);
      
      //Anti-Pattern
      if(isset($lim['npattern'])){
	if(is_array($lim['npattern']))
	  $pa = $lim['npattern'];
	else
	  $pa = array($lim['npattern']);
	foreach($pa as $cp)
	  if(preg_match($cp,$val)) 
	    return($this->af_wmodeN($def,155,NULL,$varname));
      }
      
      //Size
      if(isset($lim['size'])){
	if(!is_array($lim['size'])) $lim['size'] = array($lim['size']);
	switch(count($lim['size'])){
	case 1:
	  if(strlen($val)>$lim['size'][0])
	    return($this->af_wmodeN($def,151,NULL,$varname));
	  break;
	case 2:
	  if(strlen($val)<$lim['size'][0])
	    return($this->af_wmodeN($def,151,NULL,$varname));
	  if(strlen($val)>$lim['size'][1])
	    return($this->af_wmodeN($def,151,NULL,$varname));
	  break;
	default:
	  return($this->af_wmodeN($def,200,NULL,$varname));
	}
      }
      
      switch($lim['char']){
      case 'asc':
	$nc = strlen($val);
	for($cp=0;$cp<$nc;$cp++) 
	  if(ord($val[$cp])<32 or ord($val[$cp])>126)
	    return($this->af_wmodeN($def,157,NULL,$varname));
      }

      //HTML Tags
      switch(is_array($lim['html'])?'tag':$lim['html']){
      case 'tag': //but no attributes
	$tval = preg_replace('/[\x0A\x0D\n]/',' ',$val);
	$tag = '<' . implode('><',$lim['html']) . '>';
	if(strip_tags($tval,$tag)!=$tval)
	  return($this->af_wmodeN($def,158,NULL,$varname));
	if(preg_match('/<[a-z0-9] +[^>]+>/s',$tval))
	  return($this->af_wmodeN($def,158,NULL,$varname));
	break;
      case 'no': //no tags at all
	if(strip_tags($val)!=$val)
	  return($this->af_wmodeN($def,158,NULL,$varname));
	break;
      case 'noevents':
	$tval = preg_replace('/[\x0A\x0D\n]/',' ',$val);
	if(preg_match('/<[a-z0-9]( +[^>]*)* +on[^>]+>/si',$tval))
	  return($this->af_wmodeN($def,158,NULL,$varname));
	break;
      default:
	
	}
      //list/elist
      if(isset($lim['list']))      $val = explode($lim['list'],$val);
      elseif(isset($lim['elist'])) $val = explode_save($lim['elist'],$val);
      $ia = is_array($val);
      if(!$ia) $val = array($val);
      else while(list($ak,$av)=each($val)) $val[$ak] = trim($av);

      //deny allow
      if(isset($lim['allow']))
	foreach($val as $va)
	  if(array_search($va,$lim['allow'])===FALSE)
	    return($this->af_wmodeN($def,152,NULL,$varname));
      if(isset($lim['deny']))
	foreach($val as $va)
	  if(!(array_search($va,$lim['deny'])===FALSE))
	    return($this->af_wmodeN($def,153,NULL,$varname));
      $this->af_wmode(0);
      return($ia?$val:$val[0]);
    }
  }

  function getall($varnames=NULL){
    if(is_null($varnames)) $varnames = $this->varl;
    $re = array();
    foreach($varnames as $var) $re[$var] = $this->get($var);
    return($re);
  }

  //aplies function fnkt to all variables of mem (or those given in varnames)
  //the result is saved back into mem
  function walk($fnkt,$varname=NULL){
    if(is_null($varnames)) $varnames = $this->varl;
    foreach($varnames as $var) 
      if(is_array($this->mem[$var]))
	while(list($ak,$av)=each($this->mem[$var]))
	  $this->mem[$var][$ak] = $fnkt($this->mem[$var][$ak]);
      else
	$this->mem[$var] = $fnkt($this->mem[$var]);
    return(0);
  }

  /*
   fusions single values to an arry if their name fits the pattern
   newname: all matching values will be saved as array under this name
    they will also be removed from the original mem-array
   mode
    value: the named array will contain the values
    exist: the named array will contain only a TRUE indicator
    name: the array will contain the names
   pattern: default '_'
    a pattern as is it used in preg_grep (starting with /)
    otherwise '/^$newname$pattern/' will be used as pattern
    in the second case the array keys of the result are only the
    later part of the names (without the $newname$pattern part)
   def: default resulting array, useful for default values

   the function will return the resulting array too

   eg 1)
    mem: array('color'=>'red','value_A'=>4,'value_C'=>6','size'=>12)
    fusion_pattern('value')
    -> mem: array('color'=>'red','value'=>array('A'=>4,'C'=>6),'size'=>12)

   eg 1)
    mem: array('ABand'=>'Toto','BBand'=>'U2','year='2005')
    fusion_pattern('Bands','value','/Band$/')
    -> mem: array('Band'=>array('ABand'=>'Toto','BBand'=>'U2'),year=>2005)
  */
  function fusion_pattern($newname,$mode='value',$pattern='_',$def=array()){
    if(substr($pattern,0,1)!='/') {
      $nm = strlen($newname) + strlen($pattern);
      $pattern = '/^' . $newname . '(' . $pattern . '.+)?/';
    } else $nm = 0;
    $kl = preg_grep($pattern,$this->keys);
    foreach($kl as $key){
      switch($mode){
      case 'exist': $def[substr($key,$nm)] = TRUE; break;
      case 'name': $def[] = substr($key,$nm); break;
      default:
	$def[substr($key,$nm)] = $this->mem[$key]; break;
      }
      unset($this->mem[$key]);
    }
    $this->mem[$newname] = $def;
    $this->keys = array_keys($this->mem);
    return($def);
  }

  /* looks for the newest value 
   useful if a value can be given in different fields

   $newname: where the final value should be saved
   $oldname: under this name, the old value is saved
   $varlist: list of potential field for the new value
   
   goes through varlist and saves the first value which is different from
   oldname under newname and removes afterward all variables in $varlist
   hint: newname is allowed to appear in varlist
   
   if you use this function for a checkbox use setdefault before to set
   not-checked boxes to a given value (eg no)
  */
   
  function newest($newname,$oldname,$varlist){
    $dv = $this->mem[$oldname];
    foreach($varlist as $cv) {
      if(isset($this->mem[$cv]) and $this->mem[$cv]!=$dv){ 
	$dv = $this->mem[$cv];
	break;
      }
    }
    foreach($varlist as $cv) if(isset($this->mem[$cv])) unset($this->mem[$cv]);
    $this->mem[$newname] = $dv;
    $this->keys = array_keys($this->mem);
    return($dv);
  }

  /* 
   $varlist: list of varnames
   saves the first non null or non "" value in varlist under the first
   name in varlist
  */
  function priority($varlist){
    foreach($varlist as $cv) {
      if(isset($this->mem[$cv])
	 and !is_null($this->mem[$cv])
	 and $this->mem[$cv]!=''){ 
	$dv = $this->mem[$cv];
	break;
      }
    }
    foreach($varlist as $cv) if(isset($this->mem[$cv])) unset($this->mem[$cv]);
    $this->mem[$varlist[0]] = $dv;
    $this->keys = array_keys($this->mem);
    return($dv);
  }

  /* limits the used variables by those given in varnames
   complete: only variables in varnames are allowed, and they must be comlete
   restrict: variables not listed in varnames will be removed
   [default]: do nothing
  */
  function limitvar($varnames=NULL,$mode='open',$wmode=NULL){
    if(is_null($varnames)) $varnames = $this->varl;
    else $this->varl = $varnames;
    if(!is_array($varnames)) return(110);
    $rs = 0;

    switch($mode){
    case 'complete':
    case 'restrict':
      $l1 = array();
      foreach($this->keys as $key){
	if(array_search($key,$varnames)===FALSE){
	  $l1[] = $key;
	  unset($this->mem[$key]);
	} 
      }
      if(count($l1)) $rs = $this->af_wmode(100,$wmode,implode(', ',$l1));
      $this->keys = array_keys($this->mem);
      if($mode=='complete' and count($varnames)!=count($this->mem)){
	$l1 = array();
	foreach($varnames as $key)
	  if(array_search($key,$this->keys)===FALSE) $l1[] = $key;
	$rs = $this->af_wmode(101,$wmode,implode(', ',$l1));
      }
      break;
    }
    return($rs);
  }

  // does a variabel exist in mem if varname is an array test all (AND)
  function exist($varname){
    if(!is_array($varname)) return(isset($this->mem[$varname]));
    foreach($varname as $vn) if(!isset($this->mem[$va])) return(FALSE);
    return(TRUE);
  }

  //recalculates array_keys and returns them
  function keys(){$this->keys = array_keys($this->mem); return($this->keys);}

  // sets not yet defined variables to a default (named array)
  function setdefault($arr){
    while(list($ak,$av)=each($arr)){
      if(!array_key_exists($ak,$this->mem)) $this->mem[$ak] = $av;
    }
    return;
  }

  //auxilliary function for error-codes
  function af_wmode($code,$wmode=NULL,$txt=''){
    if(is_null($wmode)) $wmode = $this->wmode;
    switch($code){
    case 0:   $em = 'OK'; ;break;
    case 100: $em = 'not allowed variables are used'; break;
    case 101: $em = 'not all necessary variables are used'; break;
    case 110: $em = 'invalid arguments in your php code'; break;
    case 150: $em = 'value does not match the pattern'; break;
    case 151: $em = 'value is too long/short'; break;
    case 152: $em = 'value is not in list'; break;
    case 153: $em = 'value is not allowed'; break;
    case 154: $em = 'value is too small/large'; break;
    case 155: $em = 'value matchs a forbidden pattern'; break;
    case 156: $em = 'empty values are not allowed'; break;
    case 157: $em = 'value contains not allowed characters'; break;
    case 158: $em = 'value contains not allowed html code'; break;
    case 159: $em = 'value contains line feeds'; break;
    case 200: $em = 'invalid setting'; break;
    default:
      $em = 'unknown error'; break;
    }

    $em = 'Error in class ipc_inputcheck: [' . $code . '] '
      . $em . (empty($txt)?'':('; ' . $txt));
    $this->erm = $em;
    $this->erc = $code;
    $this->erl[] = array($code,$em);

    if($code==0) return(0);

    switch(strtolower($wmode)){
    case 'nothing': return(0);
    case 'die': case 'exit': exit($em); break;
    case 'warn': echo '<hr>'; var_dump($em); echo '<hr>'; break;
    }
    return($code);
  }

  function af_wmodeN($def,$code,$wmode=NULL,$txt=''){
    $this->af_wmode($code,$wmode,$txt);
    return($def);
  }
}
?>
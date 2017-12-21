<?php
class xx{

  /* setting manager
   Allows static and non-static use
   allows stacked settings (hide/restore of settings)
   uses class-method setting_keys to get an array of the setting keys (array of strings)
   Arguments behaviour
   1)
   '+'                    shift all parameters to stack
   '-'                    restore all parameters (from stack)
   '--'                   reset all parameters to inital value, reset stack too
   TRUE                   returns complet setting list as named array
   FALSE                  returns keys of the setting parameters
   string                 returns the asked setting parameter
   array(keys)            returns the asked setting parameters as array
   array(keys=>values)    completes array (=user values) with current settings 
   2)
   string any                          sets the given element
   '-' string                          restore one value from stack
   '-' array(keys)                     restore multiple values from stack
   '#' string                          returns current stack size for asked element
   '$' string                          returns stack for asked element
   array(keys=>values), array(keys)    completes array only with those given in the second
   array(keys=>values), FALSE          sets multiple values
   array(keys=>values), TRUE           sets multiple values, stack is used
   3)
   string any FALSE    sets the given element (FALSE is optional)
   string any TRUE     sets the given element, stack is used
  */

  function setting($key=TRUE /* ... */){
    static $me = NULL;       // me is used in static enviroment
    static $stack = array(); // saves temporary overdriven settings
    // cme is a pointer to $me (static situation) or this
    if(!get_class(&$this)=='opc_exttext' and !is_subclass_of(&$this,'opc_exttext')){
      if(is_null($me)) $me = new opc_exttext(); // happens only once since $me is static
      $cme = &$me;
    } else $cme = &$this; // use this if ok
    switch(func_num_args()){
    case 1:      
      if($key===FALSE) return($cme->setting_keys());
      if($key==='--'){ // complete init/reset
	foreach($stack as $key=>$val) $cme->$key = $val[0];
	$stack = array();
	return(TRUE);
      } else if($key==='+'){ // save one level in stack
	foreach($cme->setting_keys() as $ck=>$cv) $stack[$ck][] = $cme->$ck;
	return(TRUE);
      } else if($key==='-'){ // restore one level from stack
	foreach(array_keys($stack) as $ck) $cme->$ck = array_pop($stack[$ck]);
	return(TRUE);
      } else if(is_string($key)){// get value
	return($cme->$key); 
      }
      // multiple values asked
      if($key===TRUE) $key = $cme->setting_keys(); // shirtcut to all settings
      if(is_numeric(array_shift(array_keys($key)))){//numeric keys? -> get settings
	$set = array();
	foreach($key as $ck) $set[$ck] = $cme->$ck;
	return($set);
      } else { // -> complete user settings
	foreach($cme->setting_keys() as $ck) if(!isset($key[$ck])) $key[$ck] = $cme->$ck;
	return($key);
      }
    case 2:
      $arg2 = func_get_arg(1);
      if($key==='-'){ // restore from stack
	if(is_array($arg2)){ // multiple values
	  foreach($arg2 as $ck) if(is_array($stack[$ck])) $cme->$ck = array_pop($stack[$ck]);
	} else { // one value
	  if(is_array($stack[$arg2])) $cme->$arg2 = array_pop($stack[$arg2]);
	}
	return(NULL);	
      } else if($key==='#'){ // return stack-size for asked element
	return(is_array($stack[$arg2])?0:count($stack[$arg2]));
      } else if($key==='$'){ // return stack for asked element
	return($stack[$arg2]); 
      } else if(!is_array($key)) { // save arg2 to key
	return($cme->$key = func_get_arg(1));
      } else if($arg2===FALSE){
	foreach($key as $ck=>$cv) $cme->$ck = $cv;
	return(TRUE);
      } else if($arg2===TRUE){
	foreach($key as $ck=>$cv){
	  $stack[$ck][] = $cme->$ck;
	  $cme->$ck = $cv;
	}
	return(TRUE);
      } else if(is_array($arg2)){
	foreach($arg2 as $ck) if(!isset($key[$ck])) $key[$ck] = $cme->$ck;
	return($key);
      }
    case 3:
      if(func_get_arg(2)==TRUE) $stack[$key][] = $cme->$key;
      return($cme->$key = func_get_arg(1));
    }
  }

}
?>
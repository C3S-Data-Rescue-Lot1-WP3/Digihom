<?php

class opc_err {
  var $eid = NULL;
  var $show = 0; // if <>0, needs class opc_debug and default object dg
  var $msg = NULL;
  var $msgs = array(0=>'OK',
		    1=>'unnown error');

  /* array of array(level/sbj,msg,trace,microtime)*/
  var $log = array();

  // internal
  var $_t_init_A = NULL;
  var $_t_init_B = NULL;
  

  function opc_err($arr=NULL){
    if(is_array($arr)) $this->msgs($arr);
    $tim = explode(' ',microtime());
    $this->_t_init_A = (int)$tim[1];
    $this->_t_init_B = (float)$tim[0];
  }

  /* set the err and returns a given value, allows hints
   1 arg: err code; return value: err code
   2 arg: string numeric -> hint, err code; return value: err code
          numeric any -> err code, return value: any
   3 arg str numeric any: hint, err code, return value: any
   */
  function ret(/* ... */){
    $al = func_get_args();
    if(func_num_args()==0) $al = array(0);
    else if(func_num_args()==1 and is_array($al[0])) $al = $al[0];
    switch(count($al)){
    case 1: $this->set($al[0]);        $res = $al[0]; break;
    case 3: $this->set($al[1],$al[0]); $res = $al[2]; break;
    case 2:
      if(is_string($al[0])) $this->set($al[1],$al[0]);
      else $this->set($al[0]);
      $res = $al[1];
      break;
    default:
      $this->set(1);
      $res = NULL;
    }
    if($this->show>0 and $this->eid>0) dg('m',$this->eid . ': ' . $this->msg);
    return($res);
  }

  function set($id,$hint=''){
    if(!isset($this->msgs[$id])){ 
      $hint = 'unknown err code: ' . $id . '; ' . $hint;
      $id = 1; 
    }
    $this->eid = $id;
    $msg = $this->msgs[$id];
    if(strpos($msg,'%h')===FALSE and strlen($hint)>0)  $msg .= ' (' . $hint . ')';
    else $msg = str_replace('%h',$hint,$msg);
    $this->msg = $msg;
    $this->log("error $this->eid $this->msg",1);
    return($id);
  }

  function html($always=TRUE,$tag='p',$cls='err',$style=NULL){
    $style = isnull($style)?'':"style='$style'";
    if(!$always and $this->eid==0) return(NULL);
    return("<$tag class='$cls' $style>(" . $this->eid . ') ' . $this->msg . "</$tag>");
  }

  function text($always=TRUE,$inline=FALSE){
    if(!$always and $this->eid==0) return(NULL);
    if($inline) return('(' . $this->eid  . ') ' . $this->msg);
    $res = "\n====================================\n("
      . $this->eid  . ') ' . $this->msg
      . "\n====================================\n";
    return($res);
  }

  function msgs($msgs){
    // merge does not work since numerical keys will be destroyed
    foreach($msgs as $key=>$val) $this->msgs[$key] = $val;
  }

  function trace(){
    $dbt = debug_backtrace();
    $sfile = $dbt[count($dbt)-1]['file'];
    $res = array();
    while(count($dbt)>0){
      $dbc = array_shift($dbt);
      $cf = isset($dbc['file'])?$dbc['file']:NULL;
      if(substr($cf,-13)=='opc_debug.php') continue;
      if(substr($cf,-11)=='opc_err.php') continue;
      $cp = $this->str_like($cf,$sfile,0,0);
      if($cp>0) $cf = '~' . substr($cf,$cp);
      if(substr($cf,-4,4)=='.php') $cf = substr($cf,0,-4);
      $res[] = $cf . '@' . (isset($dbc['line'])?$dbc['line']:NULL);
    }
    return($res);
  }

  function log($msg,$lev=0){
    $tim = explode(' ',microtime());
    $tim = (int)$tim[1] - $this->_t_init_A + (float)$tim[0] - $this->_t_init_B;
    $this->log[] = array($lev,$msg,implode(' ',$this->trace()),$tim);
  }
  
  protected function str_like($strA,$strB,$dir=0,$ret){
    if($dir===1) { $strA = strrev($strA); $strB = strrev($strB); }
    $cp = 0; $la = strlen($strA); $lb = strlen($strB);
    while(substr($strA,$cp,1)===substr($strB,$cp,1) and $cp<$la and $cp<$lb) $cp++;
    if(is_string($ret)){
      $strA = ($dir==1?strrev($ret):$ret) . substr($strA,$cp);
      return($dir==1?strrev($strA):$strA);
    } else {
      switch($ret){
      case 0: return($cp); break;
      case 1: return($dir==1?strrev(substr($strA,0,$cp)):substr($strA,0,$cp)); break;
      case 2: return($dir==1?strrev(substr($strA,$cp)):substr($strA,$cp)); break;
      }
    }
}

  }
?>
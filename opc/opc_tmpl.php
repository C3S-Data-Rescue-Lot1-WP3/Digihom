<?php

  /* das mit messages und status besser! -> kleine klasse für beides?*/

interface opi_msg_handler{
  function announce($msg,$typ='ok',$level=0,$announce=0);
  }

class opc_msg_handler implements opi_msg_handler{
  function announce($msg,$typ='ok',$level=0,$announce=0){
    qy();
  }
}

/* bag to save results (and quests and ..) including a state of it
 * add reference too code table (which are collected in msgs directly or as array-item code)


 * DO NOT USE Capitalized class variables on your own
 *  the following have 'read-access': Ok Finished Known Changed Skipped
 *  they will return a bool depending on state (K/C/S work only in mode 10)
 */
class opc_result implements ArrayAccess{
  protected $Result;         // the result
  protected $State = NULL;   // current state
  protected $Msgs = array(); // Messages
  static $Mode = 10;         // definies the codinf of mode see below
  
  function offsetExists($key){   return isset($this->$key);}
  function offsetGet($key){      return $this->$key;}
  function offsetSet($key,$val){ $this->$key = $val;}
  function offsetUnset($key){    unset($this->$key);}
  function __isset($key){ return isset($this->$key);}
  function __unset($key){ $this->$key = NULL; }

  /* mode (of state) ============================================================
   * 0: <=0 -> success; >=1 failure
   * 1: (bool) 0: failure; <>0: ok
   * 2: even: ok; odd: not ok
   * 10: extended codes the two last digits are coded: ------------------------------
   *  last digit (even: good/ok; odd: bad)
   *   0: job finished                                             OFKC 
   *   1: job skipped, why?                                          K S
   *   2: job skipped, cool no work!                               OFK S
   *   3: job did not start, caused by error/failure/check ...       K  
   *   4: job did not start, but may                               O K  
   *   5: job partial executed, stopt by error                       KC 
   *   6: job partial executed, still ok (I did my part)           O KC 
   *   7: job not known at all -> thats not ok for me                  S
   *   8: job not known at all -> thats ok for me                  O   S
   *   9: job finished with errors                                  FK  

   * you may ask this states using, the result corresponds to the char codes above
   *  OK (o) in the sense of still running
   *  Finished (f) in the sense of there is nothing more to do
   *  Known (k) in the sense of I kown what you want (but if i can do taht?)
   *  Changed (c) At least I changed some things (finished or not, I don't know)
   *  Skipped (s) in the sense of nothing has changed
   
   *  second last digit (place of the problem)
   *   1: not ready to work (missing connection, not initalized or so)
   *   2: external instances not working (at the moment)
   *   3: necessary access/rights are not given
   *   4: given arguments are invalid (wrong type/syntax; basic checks per item)
   *   5: given combination of arguments is invalid
   *   6: external instance says error
   *   7: 
   *   8: unexpected results (post control)
   *   9: unexpected/unkonw error
   *
   */

  function __construct($code=4,$result=NULL){
    $this->set($code,$result);
  }
  
  function set($state,$result){ $this->State = $state; $this->Result = $result; }
  function set_state($state){   $this->State = $state; }
  function set_result($result){ $this->Result = $result; }
  function ret($ret,$state,$result){   $this->set($state,$result); return $ret; }
  function ret_state($state,$result){  $this->set($state,$result); return $state; }
  function ret_result($state,$result){ $this->set($state,$result); return $result;}

  function data_add($arr){
    if(is_array($arr)) foreach($arr as $ck=>$cv) $this->$ck = $cv;
  }

  function msg_add($data){  $this->Msgs[] = $data;  }
  function msgs_add($data){ $this->Msgs = array_merge($this->Msgs,(array)$data); }

  function __get($key){
    switch($key){
    case 'Result':   return $this->Result;
    case 'Mode':     return self::$Mode;
    case 'State':    return $this->State;
    case 'Msgs':     return $this->Msgs;
    case 'Changed':  return $this->changed();
    case 'Ok':       return $this->ok();
    case 'Known':    return $this->known();
    case 'Skipped':  return $this->skipped();
    case 'Finished': return $this->finished();
    }
    
    if(preg_match('/^[A-Z]/',$key)){
      trg_err(-1,"Unknown (uppercase) instance variable in opc_result: '$key'");
    } else if(isset($this->$key)){
      return $this->$key;
    }
  }

  function __set($key,$val){
    switch($key){
    case 'Mode':     self::$Mode = $val; return;
    case 'Result':   $this->Result = $val; return;
    case 'State':    $this->State = $val; return;
    case 'Msgs':     
    case 'Changed':  
    case 'Ok':       
    case 'Known':    
    case 'Skipped':  
    case 'Finished': 
      trg_err(-1,"Not allowed to set '$key' directly in opc_result");
    }
    
    if(preg_match('/^[A-Z]/',$key)){
      trg_err(-1,"Unknown (uppercase) instance variable in opc_result: '$key'");
    } else $this->$key = $val;
  }

  function finished(){
    switch(self::$Mode){
    case 0:  return $this->State<=0;
    case 1:  return $this->State!=0;
    case 2:  return ($this->State%2)==1;
    case 10: return in_array($this->State%10,array(0,2));
    }
  }

  function ok(){
    switch(self::$Mode){
    case 0:  return $this->State<=0;
    case 1:  return $this->State!=0;
    case 2:  return ($this->State%2)==0;
    case 10: return ($this->State%2)==0;
    }
  }

  function changed(){
    if(self::$Mode!=10) return NULL;
    return in_array($this->State%10,array(0,5,6));
  }

  function kown(){
    if(self::$Mode!=10) return NULL;
    return in_array($this->State%10,array(0,1,2,3,4,5,6,9));
  }

  function skipped(){
    if(self::$Mode!=10) return NULL;
    return in_array($this->State%10,array(0,1,2,3,4,5,6,9));
  }


  function val(){return $this->val;}
  function mode(){return self::$Mode;}
  function state(){return $this->State;}

}



class opc_tmpl1 {
  protected $status_cur = 0;
  protected $status_add = array();
  protected $status_msgs = array();

  protected $status_handler = NULL;
  protected $status_handler_isset = FALSE;

  public $_status_msgs = array(-1=>'ok: no action necessary',
			       00=>'ok: successfull',
			       200=>'warn%c: common warning',
			       201=>'warn%c: no read access for \'%1\'',
			       202=>'warn%c: no write access for \'%1\'',
			       210=>'warn%c:invalid syntax',
			       600=>'err%c: common error',
			    );
  

  function __construct(){
    $this->tmpl_init();
  }

  function tmpl_init(){
    $this->status_msgs = defca('_status_msgs',$this,array('fct'=>'array_merge_numkeys'));
  }
  
  function __get($key){
    $code = $this->___get($key,$res);
    if($code==0) return $res;
    return $this->err($code,NULL,$key);
  }

  function __set($key,$value){
    $code = $this->___set($key,$value);
    if($code>0) $this->err($code,NULL,$key,$value);
  }

  function ___get($key,&$res){ 
    switch($key){
    case 'status': $res = $this->status_cur; return 0;
    case 'status_msgs': $res = $this->status_msgs; return 0;
    }
    return 201; 
  }

  function ___set($key,$val) { 
    return 202;
  }

  protected function err($code,$ret=NULL/*, ... */ ){
    $this->status_cur = $code;
    if($code==0){
      $this->status_add = array();
      return $ret;
    }
    $this->status_add = func_get_args();
    if($code==0){
    } else if($code>0) {
      $this->status_msg(1);
    } else {
      $this->status_msg(1);
    }
    
    return $ret;
  }

  public function status_reset($code=0){
    $this->status_cur = $code;
  }

  protected function status_msg($announce=0){
    $msg = def($this->status_msgs,$this->status_cur,$this->status_msgs[600]);
    $add = array_slice($this->status_add,2);
    $i=1;
    $msg = str_replace('%c','(' . $this->status_cur .')',$msg);
    foreach($add as $ca){
      if(is_scalar($ca)){
	if(strpos($msg,'%' . $i)==FALSE) $msg .= '; ' . $ca;
	else $msg = str_replace('%' . $i,$ca,$msg);
      } else $msg = str_replace('%' . $i,get_class($ca),$msg);
      $i++;
    }
    if(substr($msg,0,3)==='ok:') return $this->status_handler_isset?$this->status_handler->announce('ok'):NULL;
    preg_match_all('/^([^-:]*)(?:-(\d+))?:(.*)$/',$msg,$hits,PREG_SET_ORDER);
    $parts = array_slice($hits[0],1);
    if($this->status_handler_isset) 
      return $this->status_handler->announce($parts[2],$parts[0],$parts[1],$announce);
    if($parts[0]=='suc') 
      return NULL;
    if(isset($GLOBALS['_tool_']) and $GLOBALS['_tool_']->mode=='devel') {qk();
      $msg .= ' [' . opt::bt_line() .']';}
    echo $msg;
  }

  function status_handler_set($hnd){
    if($hnd instanceof opi_msg_handler){
      $this->status_handler_isset = TRUE;
      $this->status_handler = &$hnd;
    } else {
      $this->status_handler_isset = FALSE;
      $this->status_handler = NULL;
    }
    return $this->status_handler_isset;
  }

  function defca($name,$add=array()){
    $cname = def($add,'class',get_class($this));
    $chain = array(def($add,'postdef',array()));
    while($cname){
      array_unshift($chain,def(get_class_vars($cname),$name,array()));
      $cname = get_parent_class($cname);
    }
    array_unshift($chain,def($add,'predef',array()));
    return call_user_func_array(def($add,'fct','array_merge'),$chain);
  }
}


interface opi_xquest_user {
  function quest($quest,$args=array());
  function qst__int($quest,$add,$args);
}


class opc_xquest {
  protected $obj = NULL;
  protected $xat = NULL;

  function __construct(&$obj,$fcts){
    $this->obj = $obj;
    if(is_array($fcts)) 
      $this->xat = array('{/(' . implode('|',$fcts) . ')$}');
  }

  function quest($quest,$args){
    $xml = new opc_sxml($this->xat);
    $xml->read_string($quest);
    return $this->qst($xml,'/' . $xml->basekey,$args);
  }

  function questm($quest,$args){
    $xml = new opc_sxml($this->xat);
    $xml->read_string('<c>' . $quest . '</c>');
    return $this->qst($xml,'/' . $xml->basekey,$args);
  }

  function qst($xml,$key,$args){
    $tag = $xml->node_name_get($key);
    switch($tag){
    case 'or':
      $pkeys = $xml->search('ppat',"{^$key%P%N$}");
      foreach($pkeys as $ck) if($this->qst($xml,$ck,$args)) return TRUE;
      return FALSE;

    case 'and':
      $pkeys = $xml->search('ppat',"{^$key%P%N$}");
      foreach($pkeys as $ck) if(!$this->qst($xml,$ck,$args)) return FALSE;
      return TRUE;

    case 'not':
      $pkeys = $xml->search('ppat',"{^$key%P%N$}");
      return !$this->qst($xml,array_shift($pkeys),$args);

    case 'c':
      $pkeys = $xml->search('ppat',"{^$key%P%N$}");
      $res = array();
      foreach($pkeys as $ck) $res[] = $this->qst($xml,$ck,$args);
      return $res;

    case 'min':
      $pkeys = $xml->search('ppat',"{^$key%P%N$}");
      $res = array();
      foreach($pkeys as $ck) $res[] = $this->qst($xml,$ck,$args);
      return min($res);

    case 'max':
      $pkeys = $xml->search('ppat',"{^$key%P%N$}");
      $res = array();
      foreach($pkeys as $ck) $res[] = $this->qst($xml,$ck,$args);
      return max($res);

    default:
      $add = $xml->attr_geta($key);
      $add[''] = $xml->text_get($key);
      return $this->obj->qst__int($tag,$add,$args);
    }
  }
}



class opc_tmpl2 {
  protected $map_iv_get = array();
  protected $map_iv_set = array();

  function err($code,$ret/* ,$add...*/){}

  function __get($key){
    if(in_array($key,$this->map_iv_get)) return $this->$key;
    $code = $this->___get($key,$res);
    if($code==0) return $res;
    return $this->err($code,NULL,$key);
  }

  function __set($key,$value){
    if(in_array($key,$this->map_iv_set)) return $this->$key = $value;
    $code = $this->___set($key,$value);
    if($code>0) $this->err($code,NULL,$key,$value);
  }

  function ___get($key,&$res){ 
    return 201; 
  }

  function ___set($key,$val) { 
    return 202;
  }

}


class opc_ditem {
  public $value = NULL;
  public $add = array();

  function __construct(&$val,$add=array()){
    $this->value = $val;
    $this->add = $add;
  }

}




abstract class opc_classA implements opi_classA {
  protected $map_get_dir = array();

  protected $init_class = 'nyd';
  public static $init_mode_static = array();

  /* how to init object by constructing (using init_classA)
   * 0 (default): nothing, skip all arguments
   * 1: first - ByArray - last
   * 2: only if args not empty: first - ByArray - last
   */
  protected $init_mode = 2;

  function __construct(/* */){
    $args = func_get_args();
    $tmp = $this->init_classA($args);
    if(!is_int($tmp) or $tmp>0)
      opt::e("Error: during init $this->init_class ($tmp)");
  }
    
  function init_classA($args){
    $mod = def(self::$init_mode_static,$this->init_class,$this->init_mode);
    $mth = 'init_class__' . $this->init_mode;
    if(method_exists($this,$mth)) 
      return $this->$mth($args);
    else
      return 21001;
  }
      
  function init_first(){ return 0;}
  function init_last(){ return 0;}

  function init_class__0($args){ return 0;}
  function init_class__1($args){
    if(0!== $tmp = $this->init_first()) return $tmp;
    $tmp = $this->initByArray($args,FALSE);
    foreach($tmp as $ck) if(!is_int($ck) or $ck>0) return $ck;
    if(0!== $tmp = $this->init_last()) return $tmp;
    return 0;
  }

  function init_class__2($args){
    if(empty($args)) return -1;
    return $this->init_class__1($args);
  }

  function initByArray(&$args,$named=FALSE){
    $res = array(-1);
    foreach($args as $key=>$arg)
      $res[$key] = $this->initOne($key,$arg);
    return $res;
  }

  function initOne($key,$val){
    return 10011;
  }


  function __get($key){
    if(in_array($key,$this->map_get_dir))
      return $this->$key;
    return opt::r(10011,"%err-get%$key");

  }
}

?>
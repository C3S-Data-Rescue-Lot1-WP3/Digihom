<?php

class opc_status {
  static $nl = '<br/>';
  
  /** current staus */
  protected $status = 0;
  /** additional information */
  protected $add = array();

  protected $sink = 'hide';

  /** array of all messages<br>
   array(id=>array('id'=>id,'msg'=>Message,'type'=>Errortype)[,id=>...])
  */
  protected $msgs = array();
  /** default error message if current error not found in {@link $msgs} */
  public $err_default = array('id'=>9999,'msg'=>'undefined error','type'=>'die');
  /** default error type. Used in {@link load_msg} */
  public $err_type_default = 'notice';

  /** match-table between error type (of this class) and error-style by php.<br>
   NULL means no error */
  public $err_level = array('ok'=>NULL,
			    'int'=>NULL,
			    'notice'=>E_USER_NOTICE,
			    'warn'=>E_USER_WARNING,
			    'die'=>E_USER_ERROR);

  // debug backtrace elemnts to ignore
  public $dbt_ignore = array('function'=>array('__get','__set','___get','___set'),
			     'file'=>array(__FILE__),
			     'class'=>array(__CLASS__));


  /** default error-style if local eroor-type was not found in {@link $err_level} */
  public $err_level_default = E_USER_NOTICE;


  /** external object for loggin (not used yet) */
  protected $logger = NULL;
  /** external object for warning (not used yet) */
  protected $warner = NULL;


  /** mode success. Defines how {@link is_success} interprets {@link $status}<br>
   * 0: status == 0 means OK<br>
   * 1: status <= 0 means OK<br>
   * other -> msg-type ok means OK
   */
  public $mode_success = 0; 

  /** mode log. Defines whicha ction should be logged<br>
   * 0: do not log<br>
   * 1: log only if method log is used<br>
   * 2: log method log and ret<br>
   */
  public $mode_log = 0; 

  /** constructor. Optinal argument: array of messages (see {@link laod_msg}) */
  function __construct($msgs=NULL,$sink='hide'){
    $this->load_msg($msgs);
    $this->logger = new opc_status_logger();
  }

  /** reset this object */
  function reset(){
    $this->status = 0;
  }

  /** load messages
   * @param array $msg: array of messages. Each element is an array or a string.
   * If it is a string it will be transformed to array('msg'=>string).
   * if $msg is neither an array nor a string, the current message table is 
   * removed.
   * The $msg-array may have the following 2 elements:<br>
   * 0/msg: error message; default: undefined error<br>
   * 1/type: see {@link $err_level}; default: see {@link $err_level_default}<br>
   * The keys of $msgs are used for the message id's
   */
  function load_msg($msgs){
    if(is_array($msgs)){
      foreach($msgs as $key=>$val){
	if(!is_array($val)) $val = array('msg'=>$val);
	$this->msgs[$key] = array('id'=>$key,
				  'msg'=>deff($val,'msg',0,'undefined error'),
				  'type'=>deff($val,'type',1,$this->err_type_default));
      }
    } else  $this->msgs = array();
  }

  function get_msgs(){
    return $this->msgs;
  }

  /** defines the reda-only cases */
  function __get($key){
    switch($key){
    case 'status': return $this->status;
    case 'id':     return $this->status;
    case 'msg':    return $this->msg();
    case 'msgs':   return $this->msgs;
    case 'ok':     return $this->is_success();
    }
    trg_err(1,"access denied to: $key");
  }

  /** returns TRUw if the current state represents a success or not otherwise FALSE.
   * See {@link $mode_success} 
   */
  function is_success(){
    switch($this->mode_success){
    case 0: return($this->status==0);
    case 1: return($this->status<=0);
    }
    return def(def($this->msgs,$this->status,$this->err_default),'type')==='ok';
  }
  
  /** set the current status
   * @param int|array $status: new status
   * @param any $ret: return-value of this metjod (default: NULL)
   * @return: $ret
   * if $status is an array the first element is the 'real' status 
   * followed by additional informations (strings)
   */
  function set($status,$ret=NULL){
    if(is_array($status)){
      $this->status = $status[0];
      unset($status[0]);
      $this->add = $status;
    } else {
      $this->status = $status;
      $this->add = array();
    }
    if(!$this->is_success())
      $this->trg(2,$this->msg(),def(def($this->msgs,$this->status,$this->err_default),
				    'type',$this->err_level_default));
    return $ret;
  }
    
  /** creates a message based on the status
   * if the (first) argument is NULL $this->status and $this->add are used
   * if the (first) argument is an array it will be handled like in {@link set}
   * otherwise the first argument is the status an all (optional) following
   * arguments are additional informations
   */
  function msg($status=NULL){
    if(is_null($status)){
      $add = $this->add;
      $sta = $this->status;
    } else if(is_array($status)){
      $sta = array_shift($status);
      $add = $status;
    } else {
      $sta = $status;
      if(func_num_args()>1){
	$add = func_get_args();
	array_shift($add);
      } else $add = array();
    }
    $msg = def(def($this->msgs,$sta,$this->err_default),'msg');
    foreach($add as $key=>$val){
      if(strpos($msg,"%$key%")!==FALSE) $msg = str_repalce("%$key%",$val,$msg);
      else if(is_numeric($key))         $msg .= '; ' . $val;
      else                              $msg .= '; ' . $key . ': ' . $val;
    }
    return("[$sta]: $msg");
  }

  /** shortcut to set: code is 0, ret-default is TRUE*/
  function ok ($ret=TRUE)        { return $this->set(0,$ret);  }
  /** shortcut to set: code-default is 0, ret is same as code */
  function okC($code=0)          { return $this->set($code,$code);  }
  /** shortcut to set: code-default is 0, ret is FALSE */
  function okF($code=0)          { return $this->set($code,FALSE);  }
  /** shortcut to set: code-default is 0, ret is TRUE */
  function okT($code=0)          { return $this->set($code,TRUE);  }
  /** shortcut to set: code-default is 0, ret-default is NULL */
  function okA($code=0,$ret=NULL){ return $this->set($code,$ret);  }

  /** shortcut to set: ret-default is NULL */
  function err($code,$ret=NULL){ return $this->set($code,$ret);  }
  /** shortcut to set: ret is same as code */
  function errC($code)         { return $this->set($code,$code);  }
  /** shortcut to set: ret is FALSE */
  function errF($code)         { return $this->set($code,FALSE);  }
  /** shortcut to set: ret is TRUE */
  function errT($code)         { return $this->set($code,TRUE);  }
  /** shortcut to set: 
   * if the first argument is an array, only this will be used. otherwise
   * all arguments are used (as one array). This array is used for set.
   * if the last array element is a string, null is used for the ret argument
   * in set, otherwise this last array element 
   */
  function errM($ar /* add inf, ... retvalue*/){
    if(!is_array($ar)) $ar = func_get_args();
    $ret = is_string($ar[count($ar)-1])?NULL:array_pop($ar);
    return $this->set($ar,$ret);
  }


  /** internal trigger function */
  protected function trg($lev,$msg=NULL,$type=E_USER_NOTICE,$ret=NULL){
    if($this->sink=='hide') return $ret;
    if(is_string($type)) $type = def($this->err_level,$type,$this->err_level_default);
    if(is_null($type)) return $ret;
    if(is_null($msg)) $msg = $this->msg();
    if(is_object(def($GLOBALS,'_tool_')) and $GLOBALS['_tool_']->mode==='devel')
      echo self::$nl . "======== $msg ===========" . self::$nl . implode(self::$nl,$this->dbt('fl',-99)) . self::$nl;
    else
      trigger_error($msg . ' #' . $this->dbt('fileline'),$type);
    return($ret);
  }
  // Logging ================================================================================
  function set_logger(&$logger){
  }


  /* 
   * dbt_ignore saves 'lines' which will be ignored/skipped
   *  where file would took the frist line with none of the saved values
   *  and class/function took the last line with one of this values
   * which
   *  0: first occurence
   *  >0: skip n further lines (still regarding dbt_ignore)
   *  <0: collect n lines  (still regarding dbt_ignore)
   */
  function dbt($what='fileline',$which=0){
    $dbt = debug_backtrace();
    $dbt[] = array(); // necessary since some ignore rules should return the line before
    $res = array();
    $keys = array_keys($this->dbt_ignore);
    $line = array();
    while(count($dbt)>0){
      $lline = $line;
      $line = array_shift($dbt);
      
      $tres = $line;
      if(in_array(def($line,'file','-'),    $this->dbt_ignore['file']))     continue;
      $tres = $lline;
      if(in_array(def($line,'class','-'),   $this->dbt_ignore['class']))    continue;
      if(in_array(def($line,'function','-'),$this->dbt_ignore['function'])) continue;
      $tmp = $this->dbt_show($tres,$what);
      if($tmp===FALSE)      continue;
      else if($which==0)    return $tmp;
      else if($which>0)   { $which--; continue;}
      else if($which==-1) { $res[] = $tmp;  return $res;}
      else                { $res[] = $tmp; $which++; }
    }
    return $res;
  }

  function dbt_show($line,$what){
    //if($what!='full') qq($this->dbt_show($line,'full'));
    switch($what){
    case 'full':
      return def($line,'class','C') . '->' . def($line,'function','f') . ' ---- '
	. def($line,'file','F') . '@' . def($line,'line','L');
    
    case 'fl': 
      if(!isset($line['line'])) return FALSE;
      $res = def($line,'file','?') . '@' . $line['line'];
      return str_replace($_SERVER['DOCUMENT_ROOT'],'~',$res);

    case 'fileline': 
      if(!isset($line['line'])) return FALSE;
      return def($line,'file','?') . '@' . $line['line'];
    }
    return 'unkown task: ' . $what;
  }
}







interface opi_logger{
  public function log($msg,$type=NULL,$time=NULL,$caller=NULL);
  public function newest();
}

class opc_status_logger implements opi_logger{
  protected $log = array();

  public function log($msg,$type=NULL,$time=NULL,$caller=NULL){
    if(is_null($time)) $time = microtime(TRUE);
    $log[] = array('msg'=>$msg,'type'=>$type,'time'=>$time,'caller'=>$caller);
  }
  public function newest(){
    return($this->log[count($this->log)-1]);
  }

}

?>
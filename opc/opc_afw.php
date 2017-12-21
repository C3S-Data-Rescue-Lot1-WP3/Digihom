<?php
  /* variation of opc_fw for ajax results
     -> only one pointer, no head or such things, always xhtml!
   idea: add session handling (using __destruct to save them
         prop_est: weitere Quellen (sobj_files/xml/db)
	           nicht nur in ses registrieren

     markierung nach art verbessern!
     Standardkontakt für Fehler etc
   */

class opc_afw {
  /* link to the tool-object which was used to init the object */
  public $tool = NULL;
  /* error object */
  public $err = NULL;
  /* potential db object */
  public $db = NULL;
  public $ticket = NULL;

  /* the main data object */
  public $data = NULL;
  /* some standard pointers */
  public $compl = NULL; // special one which collects at the end all

  /* all properties */
  public $prop = array();


  /* named array (kind=>class); defines which class is used for which request
   * it will be filled with the _def_cls array through the complete class tree 
   * where the child class wins over the parent one
   */
  public $_cls = array(); 
  public $_def_cls = array('data'=>'opc_ht2',
			   'pointer'=>'opc_ptr_ht2',
			   'form'=>'opc_ptr_ht2form',
			   );

  /* uservalues which will be saved like sessionvariable while destructing */
  protected $uservalues = array();
  

  /** settings
   * charset: one of utf-8 or iso-8859-1
   */
  static $prop_def = array('type'=>'strict',
			   'charset'=>'UTF-8',
			   'key'=>'[-]',
			   );
  

  /**   function __get
   * @access private
   * @param $key name of the asked variable
   * @return aksed value or error 103 is triggered
   */
  function __get($key){
    if($this->___get($key,$tmp)) return $this->err->ok($tmp);
    return $this->err->errM(103,$key);    
  }

  /** subfunction of magic method __get to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return bool: returns TRUE if key was accepted (result is saved in $res) otherwise FALSE
   */
  protected function ___get($key,&$res){
    if(!array_key_exists($key,$this->prop)) return FALSE;
    $res = $this->prop[$key];
    return TRUE;
  }

  /**   function __set
   * @access private
   * @param $key name of the asked variable
   * @param mixed $value new value
   * @return aksed value or error 103 is triggered
   */
  function __set($key,$value){
    $tmp = $this->___set($key,$value);
    if($tmp>0) $this->err->errM($tmp,$key,$value);
  }

  /** subfunction of magic method __set to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return int err-code (0=success)
   */
  protected function ___set($key,$value){
    $this->prop[$key] = $value;
    return 0;
  }


  function __construct(/* ... */){
    $ar = func_get_args();
    // catch first a tool-object
    $this->init_tool($ar);
    // intialize err object
    $this->err = new opc_status($this->_msgs());
    // initialize settings (except those from tool; see above)
    $res = $this->init($ar);

    $this->_cls = defca('_def_cls',$this);
    if(def($res,'auto_prepare',FALSE)) $this->prepare();
  }



  function __destruct(){
    foreach($this->uservalues as $key=>$val){
      $save = def($val,'s',FALSE);
      if($save===FALSE) continue;
      if($save===TRUE) $save = 's';
      $n = strlen($save);
      $i = 0;
      while($i<$n) $this->_prop_set($key,def($val,'v'),substr($save,$i++,1));
    }
  }

  protected function _msgs(){
    return array(0=>array('ok','ok'),
		 103=>array('read access denied','notice'),
		 104=>array('write access denied','notice'),
		 105=>array('invalid value','notice'),
		 106=>array('unkown target','notice'),
		 );
  }



  function init_tool($ar){
    $this->tool = NULL;
    foreach($ar as $ca) if($ca instanceof _tools_) $this->tool = $ca; // search for tool in init args
    if(is_null($this->tool)){ // or try it from global
      if(isset($GLOBALS['_tool_']) ) $this->tool = $GLOBALS['_tool_'];
      $def = array();;
    } else { // if one was given take over the settings
      $def = $this->tool->get_all();
      
    }
    $this->tool->req_files('opl_basic');
    if($this->tool->mode=='devel') $this->tool->req_files('opc_debug');
    $this->prop = defca('prop_def',$this,$def);
  }
  
  function init($ar){
    $res = array();
    foreach($ar as $ca){
      if(is_array($ca)){
	$this->prop = array_merge($this->prop,$ca);
      } else if(is_object($ca)){ // if ht2 take over charset
	if($ca instanceof opc_ht2){
	  $this->prop['charset'] = $ca->charset;
	} else if($ca instanceof _tools_){ // done above
	} else trigger_error("unkown object to init framework: " . get_class($ca),E_USER_NOTICE);
      } else if(is_bool($ca)){
	$res['auto_prepare'] = $ca;
      } else trigger_error("unkown setting for init framework: " . var_export($ca,TRUE),E_USER_NOTICE);
    }
    return $res;
  }


  function prepare(){
    foreach($this->prop as $key=>$val){
      if(!$val) continue;
      $mth = 'prepare_obj_' . $key;
      if(method_exists($this,$mth)) $this->$mth($val);
    }

    $cls = $this->_cls['data'];
    $this->data = new $cls($this->prop);

    $cls = $this->_cls['pointer'];
    $this->compl = new $cls($this->data,NULL,NULL,'compl');
  }


  protected function prepare_obj_ticket($args){
    $this->tool->req_files('@ticket');
    $this->ticket = $this->tool->create_external(array('ticket',$args[0]),$args[1]);
  }

  function prepare_obj_db($val){
    $this->tool->req_files('@pgdb');
    $obj = $this->tool->create_external('pgdb',$val);
    if(!is_resource($obj)) die('Error: connecting database failed');
    $this->db = new opc_pg($obj);
  }

  /** cretae a new ht2 pointer
   * kind is a key from instance array _cls or the pointer class name itself
   * key,next an tag: see construcor of pointer itself
   * the instance ht2 object data is used to construct the new object
   */
  function ptr_new($kind='pointer',$key=NULL,$next=NULL,$tag=NULL){
    if(is_null($kind)) $kind = 'pointer';
    $cls = def($this->_cls,$kind,$kind);
    if(in_array('opi_ptr',class_implements($cls))){
      return new $cls($this->data,$key,$next,$tag);
    } else trigger_error("Class $cls does not implement opi_ptr");
  }


  function output(){
    return trim($this->data->exp2html($this->compl,FALSE));
  }

  /* ================================================================================
     session
     ================================================================================ */
  // reads one value from session, needs a default!
  function ses_get($key,$def){
    if(!isset($_SESSION[$this->tool->key])) return $def;
    return def($_SESSION[$this->tool->key],$key,$def);
  }

  // reads the complete session array
  function ses_get_all(){
    if(!isset($_SESSION[$this->tool->key])) return array();
    return $_SESSION[$this->tool->key];
  }

  // sets one session value
  function ses_set($key,$val){
    if(isset($_SESSION[$this->tool->key]))
      $_SESSION[$this->tool->key][$key] = $val;
    else
      $_SESSION[$this->tool->key]= array($key=>$val);
  }

  // reset the session with a new array
  function ses_set_all($arr){
    $_SESSION[$this->tool->key]= $arr;
  }

  // adds an array of values to session, overwrites existing ones
  function ses_unset($key){
    if(!isset($_SESSION[$this->tool->key])) return;
    if(is_array($key)) foreach($key as $ck) unset($_SESSION[$this->tool->key][$ck]);
    else unset($_SESSION[$this->tool->key][$key]);
  }

  // reset the current session of this project
  function ses_reset(){
    if(!isset($_SESSION[$this->tool->key])) return;
    $_SESSION[$this->tool->key] = array();
  }

  // destroys the complete session!
  function ses_destroy(){
    $_SESSION = array();
    session_destroy();
    if(isset($_COOKIE[session_name()])) setcookie(session_name(), '', time()-42000, '/');
    session_start();
  }


  // adds an array of values to session, overwrites existing ones
  function ses_add($arr){
    if(isset($_SESSION[$this->tool->key]))
      $_SESSION[$this->tool->key] = array_merge($_SESSION[$this->tool->key],$arr);
    else
      $_SESSION[$this->tool->key]= $arr;
  }

  // adds an array of values to session, does not overwrites existing ones
  function ses_ensure($arr){
    if(isset($_SESSION[$this->tool->key]))
      $_SESSION[$this->tool->key] = array_merge($arr,$_SESSION[$this->tool->key]);
    else
      $_SESSION[$this->tool->key]= $arr;
  }

  /* ================================================================================
     properties
     ================================================================================ */
  function prop_exists($key){
    if(!isset($this->uservalues[$key])) return FALSE;
    return isset($this->uservalues[$key]['v']);
  }

  function prop_get($key,$def=NULL){
    if(!isset($this->uservalues[$key])) return $def;
    if(!isset($this->uservalues[$key]['v'])) return $def;
    return $this->uservalues[$key]['v'];
  }

  function prop_set($key,$val){
    if(!isset($this->uservalues[$key])) $this->uservalues[$key] = array('v'=>$val);
    else $this->uservalues[$key]['v'] = $val;
  }

  /** estimate property 
   * key: key of the asked element
   * def: default value
   * seq: char-sequence which defines the sequence of storage
   *   to look for the asked value. First wins!
   *   c=class (this->uservalues); s=session[tool->key];
   *   p=post;                     g=get
   *   typically for forms is pgs: post>get>session
   * register: sequence to save the value while destruction (default none; typically: 's')
   * save: sequence to save the value now (default: 'c')
   */
  function prop_est($key,$def,$seq='c',$register=FALSE,$save='c'){
    $res = $def;

    // estimate
    $n = strlen($seq);
    $i = 0;
    while($i<$n) if($this->_prop_get($key,substr($seq,$i++,1),$res)==0) break;

    // save now
    $n = strlen($save);
    $i = 0;
    while($i<$n) $this->_prop_set($key,$res,substr($save,$i++,1));
    
    $this->uservalues[$key]['s'] = $register;

    return $res;
  }

  /* overload to add more staorages
   * if key found, save result in res and returns 0
   * return: 1: unkown storage, 2: value not found in storage
   *         3: no read access
   */
  protected function _prop_get($key,$storage,&$res){
    switch($storage){
    case 'c': 
      if(!isset($this->uservalues[$key])) return 2;
      if(!isset($this->uservalues[$key]['v'])) return 2;
      $res = $this->uservalues[$key]['v'];
      return 0;

    case 's':
      if(!isset($_SESSION[$this->tool->key])) return 2;
      if(!isset($_SESSION[$this->tool->key][$key])) return 2;
      $res = $_SESSION[$this->tool->key][$key];
      return 0;

    case 'p':
      if(!isset($_POST[$key])) return 2;
      $res = $_POST[$key];
      return 0;

    case 'g':
      if(!isset($_GET[$key])) return 2;
      $res = $_GET[$key];
      return 0;
    }
    return 1;
    
  }

  /* overload to add more staorages
   * return: 0: success; 1: unkown storage, 2: inavlid key/value;
   *  3: no write access
   */
  protected function _prop_set($key,$val,$storage){
    switch($storage){
    case 'c': 
      if(!isset($this->uservalues[$key])) $this->uservalues[$key] = array('v'=>$val);
      else $this->uservalues[$key]['v'] = $val;
      return 0;

    case 's':
      if(!isset($_SESSION[$this->tool->key])) 
	$_SESSION[$this->tool->key] = array($key=>$val);
      else 
	$_SESSION[$this->tool->key][$key] = $val;
      return 0;

    case 'p': return 3;
    case 'g': return 3;
    }
    return 1;
    
  }

  /* ================================================================================
     various helper
     ================================================================================ */
  /** returns the name of the start php script
   * @param bincoded-int $flag: defines which elements should be returned
   * - 0/1: name only (eg. index.php)
   * - 1/2: path  (eg /tools/coding/)
   * - 2/4: server (eg domain.org)
   * - 3/8: protocol (eg http://)
   */
  function myself($flag=1){
    $ser = $_SERVER;
    $file = (($flag & 1)==1)?substr($ser['PHP_SELF'],strrpos($ser['PHP_SELF'],'/')+1):'';
    $path = (($flag & 2)==2)?substr($ser['PHP_SELF'],0,strrpos($ser['PHP_SELF'],'/')+1):'';
    $host = (($flag & 4)==4)?$ser['HTTP_HOST']:'';
    if(($flag & 8)==8){
      $prot = strtolower(substr(def($_SERVER,'SERVER_PROTOCOL'),0,
				strpos(def($_SERVER,'SERVER_PROTOCOL'),'/'))) . '://';
    } else $prot = '';
    return $prot . $host . $path . $file;
  }

  /** Add message, echo output and die
   * msg: if not empty will added to the page as span
   * $target: name of an fw-pointer (to place the message there)
   * $style: used to format the message)
   */
  function msgdie($msg=NULL,$target='compl',$style='border: solid 5px red; margin: 10px; padding: 5px; color: black; font-weight: bold; background-color: #8f0;'){
    if($msg) $this->$target->span($msg,$style);
    echo $this->output();
    die;
  }

  function msgret($msg=NULL,$target='compl',$style=NULL){
    if($msg) $this->$target->span($msg,$style);
    echo $this->output();
    return NULL;
  }

}
?>
<?php
  /*
   idea: add session handling (using __destruct to save them
         prop_est: weitere Quellen (sobj_files/xml/db)
	           nicht nur in ses registrieren
         autoload of some css or js files after head initialized?
	 ensure that http and https are not mixed for image sources

     markierung nach art verbessern!
     Standardkontakt für Fehler etc
     
     session_start automatically?

     output via template mit Platzhaltern für die pointers

     layout aufräumen
   */
interface opi_fw{}

interface opi_fw_ext {
  public function fw_prepare_pre();
  public function fw_prepare_layout();
  public function fw_prepare_post();
  public function fw_output_pre();
  public function fw_output_layout();
  public function fw_output_post();
}

class opc_fw implements opi_fw{
  public $txp_cls = 'fw';

  /* link to the tool-object which was used to init the object */
  public $tool = NULL;
  /* error object */
  public $err = NULL;

  /* external object; used by layout ext */
  protected $ext = NULL;

  /* potential db object */
  public $db = NULL; // database
  public $ticket = NULL; // ticket system
  public $um = NULL; // user management
  public $ht2d_um = NULL; // user management displayer
  public $logit = NULL; // logger
  public $ht2d_logit = NULL; // log displayer
  public $txp = NULL; // textprovider
  public $sb = NULL; // site buolder
  public $extsb = array(); // used by sb


  public $text = NULL; // replaced by txp
  public $auth = NULL; // authentification (replaced by um)


  public $args = NULL;

  public $msg = NULL; // messages
  public $nav = NULL; // navigation 
  public $setbag = NULL; // setbag system

  /* the main data object */
  public $data = NULL;
  /* the object for the head-tag */
  public $head = NULL;
  /* some standard pointers */
  public $main = NULL;
  public $header = NULL;
  public $footer = NULL;
  public $side = NULL;
  public $left = NULL;
  public $right = NULL;
  public $compl = NULL; // special one which collects at the end all

  public $pmsg = NULL; // will intialized by showmsg

  /* list of pointers to initialize during prepare*/
  public $pointers = array('main');

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
			   'head'=>'opc_head',
			   'nav'=>'opc_ht2o_nav',
			   'msg'=>'opc_ht2o_msg',
			   'sb'=>'opc_sb',
			   'args'=>'opc_args',
			   'text'=>'opc_textData',
			   'um'=>'opc_um',
			   'ht2d_um'=>'opc_ht2d_um',
			   'ht2d_logit'=>'opc_ht2d_logit',
			   );
  public $_obj = array();
  public $_def_obj = array('db'=>array('incl'=>'@pgdb'),
			   'ticket'=>array('incl'=>'@ticket'),
			   'auth'=>array('incl'=>'@auth'),
			   'um'=>array('incl'=>'@um'),
			   'ht2d_um'=>array('incl'=>array('opc_ht2d','opc_ht2d_um')),
			   'ht2d_logit'=>array('incl'=>array('opc_ht2d','opc_ht2d_logit')),
			   'sb'=>array('incl'=>'opc_sb'),
			   'text'=>array('incl'=>'opc_textData'),
			   'args'=>array('incl'=>'opc_args'),
			   'nav'=>array('incl'=>'opc_ht2o(_nav)?'),
			   'msg'=>array('incl'=>'opc_ht2o(_msg)?'),
			   'setbag'=>array('incl'=>'opc_setbag(_.*)?'),
			   );

  /* will call function incl_XXX for al values in this array during output_post */
  public $_incl = array();
  /* default includes */
  public $_def_incl = array('msg','nav');

  /** chain of methods which will be processed during construction
   * args all: use all arguments of constrcution as argument of the function (as single array)
   *      res: use the current result (default is an empty array) as argument
   *      var: use part of constructing set argument (name given in key) called only if that exists!
   *       - : call the method without arguments
   * res save: save the return value as new current result (replace the old one)
   */
  static $constr_chain_def = array('init_tool'=>array('args'=>'all'),
				   'init_txp'=>array('args'=>'def','key'=>'txp'),
				   'init_logit'=>array('args'=>'def','key'=>'log'),
				   'init_err'=>array(),
				   'init_sesarg'=>array(),
				   'init_arg_a'=>array(),
				   'init'=>array('args'=>'all','res'=>'save'),
				   'init_defca'=>array(),
				   'init_arg_b'=>array(),
				   'init_auto_prepare'=>array('args'=>'res'),
				   );
  

  /* uservalues which will be saved like sessionvariable while destructing */
  protected $uservalues = array();
  
  /** the following two arguments may help if some saved values ins ession blocks your project
   * both will be proofen during construction (between init_tool and init_arg
   * and destroy the complete session respectively the session-part of the current project 
   * using the key property of the tool
   */
  public $getarg_clear_session_all = 'clearsessionall';
  public $getarg_clear_session_this = 'clearsessionthis';


  /** settings
   * xhtml: use xhtml instead of html
   * charset: one of utf-8 or iso-8859-1
   * body_head: will be used while creating head-tag. Allow to trigger the creation of a standard header inside the body
   *            the real work has to be done in opc_head (or subclasses)
   * send_header: if boolean it will decide if function header is used
   *              if 'auto': header is used if env does not contain the word devel 
   *                         this helps if xhtml is on any you use (debug-)fucntions which destroy the xml-structure
   */
  static $prop_def = array('xhtml'=>FALSE,//TRUE,
			   'type'=>'strict',
			   'charset'=>'UTF-8',
			   'body_head'=>FALSE,
			   'send_header'=>'auto',
			   'layout'=>'single',
			   'key'=>'[-]',
			   'title'=>NULL,
			   'date'=>NULL,
			   'version'=>NULL,
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
    switch($key){
    case 'layout': 
      if(!preg_match('/^std_[hlmrf]+$/',$value)
	 and !method_exists($this,'output_layout__' . $value)) return 105;
      $this->prop['layout'] = $value;
      $this->prepare_layout($value);
      if(is_object($this->data)) $this->prepare_pointers();
      return 0;
    default:
      $this->prop[$key] = $value;
      return 0;
    }
    return 106;
  }


  /*
   * free arguments
   *  a class implementing _tools_ (recommended)
   *  a boolean (if TRUE prepare is called at the end)
   *  a named array
   *    xhtml: TF; used for teh data
   *    charset: utf-8 or iso-8859-1; used for the data
   *    db or pgdb: to set the db instance variable
   *    layout: one of single, indexleft, col2l, col2r, col3
   */
  function __construct(/* ... */){
    $ar = func_get_args();
    $res = array();
    $chain = defca('constr_chain_def',$this);
    foreach($chain as $key=>$val){
      $tres = NULL;
      switch(def($val,'args','none')){
      case 'all': $tres = $this->$key($ar); break;
      case 'res': $tres = $this->$key($res); break;
      case 'var': $tres = $this->init_var($key,$val,$ar); break;
      case 'def': $tres = $this->init_def($key,$val,$ar); break;
      default:
	$tres = $this->$key();
      }
      if(is_null($tres)) continue;
      
      switch(def($val,'res','discard')){
      case 'save': $res = $tres;
      }
    }
  }

  protected function init_var($mth,$set,$args){
    $key = $set['key'];
    foreach($args as $ca){
      if(is_array($ca) and isset($ca[$key])) 
	return $this->$mth($ca[$key]);
    }
  }

  protected function init_def($mth,$set,$args){
    $key = $set['key'];
    foreach($args as $ca){
      if(is_array($ca) and isset($ca[$key])) 
	return $this->$mth($ca[$key]);
    }
    return $this->$mth();
  }


  /* sets the error object */
  function init_err() { 
    $this->err = new opc_status($this->_msgs());
  }

  /* sets the textprovider object */
  function init_txp($set=NULL) { 
    $this->txp = $this->tool->create_external('txp',$set);
  }

  /* sets the textprovider object */
  function init_logit($set=NULL) { 
    $set = (array)$set;
    $cls = array(def($set,'cls','logit'),def($set,'subcls'));
    $this->logit = $this->tool->create_external($cls,$set);
  }

  
  function init_arg_a() { 
    $this->err = new opc_status($this->_msgs());
  }

  function init_arg_b() { 
    $this->err = new opc_status($this->_msgs());
  }

  /* determines some default values using defca */
  function init_defca() { 
    $this->_cls = defca('_def_cls',$this);
    $this->_obj = defca('_def_obj',$this);
    // unique necessary since it works with numeric key
    $this->_incl = array_unique(defca('_def_incl',$this));
  }

  /* if in arg the key auto_prepare is TRUE method prepare is called */
  function init_auto_prepare($arg) { 
    if(def($arg,'auto_prepare',FALSE)) $this->prepare();
  }

// session destrcuting if asked
  function init_sesarg() {
    if($this->getarg_clear_session_all and isset($_GET[$this->getarg_clear_session_all]))
      $this->ses_destroy();
    else if($this->getarg_clear_session_this and isset($_GET[$this->getarg_clear_session_this]))
      $this->ses_reset();
    // initialize settings (except those from tool; see above)
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

  public function ext_set(&$ext){
    if($ext instanceof opi_fw_ext){
      $this->ext = &$ext;
    } else {
      qd('not an opi_fw_ext');
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
      $def = array();
    } else { // if one was given take over the settings
      $def = $this->tool->get_all();
    }
    $this->tool->req_files('opl_basic');
    if($this->tool->mode=='devel') $this->tool->req_files('opc_debug');
    $this->prop = defca('prop_def',$this);
    foreach($this->prop as $ck=>$cv){
      if((is_null($cv) or $cv=='[-]') and isset($def[$ck]))
	$this->prop[$ck] = $def[$ck];
    }
    $this->tool->init__fw($this);
  }
  
  function init($ar){
    $res = array();
    foreach($ar as $ca){
      if(is_array($ca)){
	$this->prop = array_merge($this->prop,$ca);
      } else if(is_object($ca)){ // if ht2 take over xhtml and charset
	if($ca instanceof opc_ht2){
	  $this->prop['xhtml'] = $ca->xhtml;
	  $this->prop['charset'] = $ca->charset;
	} else if($ca instanceof _tools_){ // done above
	} else trigger_error("unkown object to init framework: " . get_class($ca),E_USER_NOTICE);
      } else if(is_bool($ca)){
	$res['auto_prepare'] = $ca;
      } else trigger_error("unkown setting for init framework: " . var_export($ca,TRUE),E_USER_NOTICE);
    }
    return $res;
  }


  function prepare($layout=NULL){
    $lay = is_null($layout)?$this->prop['layout']:$layout;

    if($lay=='ext'){
      $this->ext->fw_prepare_pre();
      $this->ext->fw_prepare_layout();
      $this->ext->fw_prepare_post();
    } else {
      $this->prepare_pre();
      $this->prepare_layout($lay);
      $this->prepare_post();
    }
  }

  function prepare_pre(){
    $this->prepare_ht2();
    $this->prepare_head();
    $this->prepare_obj();
  }

  function prepare_layout($lay){
    if(preg_match('/^std_h?l?m?r?f?$/',$lay)){
      $this->prepare_layout_hlmrf(substr($lay,4));
    } else {
      $mth = 'prepare_layout__' . $lay;
      if(method_exists($this,$mth)) $this->$mth();
    }
  }

  function prepare_post(){
    $this->prepare_pointers();
    $this->head->set('myself',$this->data->myself());
  }


  /* creates one or more object and saves them under their key
   * prop is a named array (obj-key=>settings)
   *  settings is an array (with details) or 
   *  a string refering to laod_connections from tool
   * only object listet in $this->_obj are created
   * returns sets with all created objects removed
   */

  function prepare_obj_internal($sets){
    foreach($this->_obj as $key=>$val){
      if(!isset($sets[$key]) or $sets[$key]===FALSE) continue;
      $set = $sets[$key];

      $this->tool->req_files(def($val,'incl',array()));
      // settings saved in connection ?
      if(is_string($set) and preg_match('#^\w+>#',$set)){
	$set = $this->tool->load_connection('con',$set);
	if(is_numeric($set)){
	  trg_err(1,"Failed load settings for '$key', code: '$set'");
	  continue;
	}
      }

      $mth = 'prepare_obj__' . $key;
      $this->$key = $this->$mth($set);
      unset($sets[$key]);
    }
    return $sets;
  }
  /* public version of prepare_obj_internal using the prop array as argument */
  function prepare_obj(){
    $this->prop = $this->prepare_obj_internal($this->prop);
  }

  protected function prepare_obj__ticket($args){
    if(!is_array($args) or count($args)!=2) return 6;
    return $this->tool->create_external(array('ticket',$args[0]),$args[1]);
  }

  protected function prepare_obj__db($val){
    return $this->tool->create_external('pg',$val);
  }

  protected function prepare_obj__setbag($val){
    return $this->tool->create_external(array('setbag',$val['bagtype']),
					$val['con']);
  }

  protected function prepare_obj__txp($set){
    return $this->tool->create_external('txp',$set);
  }

  protected function prepare_obj__auth($set){
    return $this->tool->create_external('auth',def($set,'seq',NULL),$set);
  }

  protected function prepare_obj__um($set){
    $set['fw'] = $this;
    return $this->tool->create_external('um',$set);
  }

  protected function prepare_obj__sb($set){
    $cls = $this->_cls['sb'];
    return new $cls($this);
  }

  protected function prepare_obj__ht2d_um($set){
    $cls = $this->_cls['ht2d_um'];
    if($set===TRUE) $set = array();
    if(!isset($set['object'])) $set['object'] = $this->um;
    return new $cls($this,$set);
  }

  protected function prepare_obj__ht2d_logit($set){
    $cls = $this->_cls['ht2d_logit'];
    if($set===TRUE) $set = array();
    if(!isset($set['object'])) $set['object'] = $this->logit;
    return new $cls($this,$set);
  }

  protected function prepare_obj__text($set){
    if(!is_array($set)) $set = array($set);
    $cls = $this->_cls['text'];
    return new $cls($set);
  }

  protected function prepare_obj__msg($set){
    $this->head->css($this->tool->pat2file('opd_msg.css'));
    $cls = $this->_cls['msg'];
    return new $cls();
  }

  protected function prepare_obj__nav($set){
    if(!is_array($set) or def($set,'css',TRUE)!==FALSE)
      $this->head->css($this->tool->pat2file('opd_nav.css'));
    if(is_array($set)) unset($set['css']);
    $cls = $this->_cls['nav'];
    $res = new $cls();
    if(is_array($set)) $res->init_one($set);
    return $res;
  }

  protected function prepare_obj__args($set){
    $cls = $this->_cls['args'];
    $res = new $cls(def($set,'key',$this->tool->key),
		    def($set,'sink'));
    return $res;
  }

  protected function prepare_pointers(){
    $cls = $this->_cls['pointer'];
    new $cls($this->data,0,array('tag'=>'head')); // necessary to set key 0 in opc_ht2!!
    $this->compl = new $cls($this->data,NULL,array('tag'=>'compl'));
    foreach($this->pointers as $ptr) 
      if(is_null($this->$ptr))
	$this->$ptr = new $cls($this->data,NULL,array('tag'=>$ptr));
    if(is_object($this->left) and !is_object($this->right)) $this->side = &$this->left;
    else if(is_object($this->right) and !is_object($this->left)) $this->side = &$this->right;
  }

  protected function prepare_ht2(){
    if(is_object($this->data)) return -1;
    $cls = $this->_cls['data'];
    $this->data = new $cls($this);
    return 0;
  }

  public function prepare_head(){
    if(!is_null($this->head)) return -1;
    $cls = $this->_cls['head'];
    $this->head = new $cls($this);
    $this->head->set('type',$this->prop['type']);
    $this->head->css($this->tool->pat2file('opd_fw.css','rel'));
    return 0;
  }


  /** cretae a new ht2 pointer
   * kind is a key from instance array _cls or the pointer class name itself
   * key, next and tag: see constructor of pointer itself
   * the instance ht2 object data is used to construct the new object
   * deprecated
   */
  function ptr_new($kind='pointer',$key=NULL,$add=array()){
    if(is_null($kind)) $kind = 'pointer';
    $cls = def($this->_cls,$kind,$kind);
    if(in_array('opi_ptr',class_implements($cls))){
      return new $cls($this->data,$key,$add);
    } else trigger_error("Class $cls does not implement opi_ptr");
  }
  
  /* replaces ptr_new */
  function ptr($add='pointer'){
    if(is_numeric($add)) 
      $add = array('kind'=>'pointer','key'=>$add);
    elseif(is_string($add)) 
      $add = array('kind'=>$add,'key'=>NULL);
    else 
      $add = array_merge(array('kind'=>'pointer','key'=>NULL),$add);
    $cls = def($this->_cls,$add['kind'],$add['kind']);
    if(in_array('opi_ptr',class_implements($cls)))
      return new $cls($this->data,$add['key'],$add);
    trigger_error("Class $cls does not implement opi_ptr");
  }


  function get_class($type,$def=NULL){
    return def($this->_cls,$type,$def);
  }

  function send_headers(){
    if(!headers_sent()){
      $res = $this->prop['xhtml']?'text/xml':'text/html';
      $send = $this->prop['send_header']==='auto'?$this->tool->mode=='devel':$this->prop['send_header'];
      if($send) header('Content-type: ' . $res .  '; charset=' . $this->prop['charset'],TRUE);
      return TRUE;
    } else return FALSE;
  }    


  function output($layout=NULL){
    $lay = is_null($layout)?$this->prop['layout']:$layout;
    if($lay=='ext'){
      $this->ext->fw_output_pre();
      $this->ext->fw_output_layout();
      $this->ext->fw_output_post();
    } else {
      $this->output_pre();
      $this->output_layout($lay);
      $this->output_post();
    }
    return $this->data->exp2html($this->compl);
  }

  function output_layout($lay){
    if(preg_match('/^std_h?l?m?r?f?$/',$lay)){
      $this->output_layout_hlmrf(substr($lay,4));
    } else {
      $mth = 'output_layout__' . $this->prop['layout'];
      $this->$mth();
    }
  }

  /* overload for post modifications
   *  by default the inlc-function listed in _incl are called
   */
  function output_post(){
    $this->incl();
  }

  function output_pre(){
    $this->send_headers();
    $this->head->exp2ht($this->data,$this->prop['body_head']);
  }

  protected function incl($what=NULL){
    if(is_null($what)) $what = $this->_incl;
    else if(is_scalar($what)) $what = array($what);
    foreach($what as $ci){
      $mth = 'incl_' . $ci;
      $this->$mth();
    }
  }

  protected function incl_nav(){
    // Show navigation if visible
    if(count($this->nav)>0){
      if(!is_object($this->nav->ht)){
	if(is_object($this->side))       { $key = $this->side->root;  $bef = TRUE;}
	else if(is_object($this->left))  { $key = $this->left->root;  $bef = FALSE;}
	else if(is_object($this->right)) { $key = $this->right->root; $bef = FALSE;}
	else if(is_object($this->main))  { $key = $this->main->root;  $bef = FALSE;}
	else                             { $key = $this->compl->root; $bef = FALSE;}
	$tkey = $this->ptr_new('pointer',NULL,array('tag'=>'fw_msg'));
	$tkey->set($key,$bef?'fcl':'lcl');
	$this->nav->ht = &$tkey;
	$this->nav->output();
      } else $this->nav->output();
    }
  }


  protected function incl_msg(){
    // Add messages if some are visible
    if(count($this->msg)>0){
      if(!is_object($this->msg->ht)){
	if(is_object($this->main))       { $key = $this->main->root;   $bef = TRUE;}
	else if(is_object($this->header)){ $key = $this->header->root; $bef = FALSE;}
	else                             { $key = $this->compl->root;  $bef = FALSE;}
	$tkey = $this->ptr_new('pointer',NULL,array('tag'=>'fw_msg'));
	$tkey->set($key,$bef?'fcl':'lcl');
	$tkey->open('div','#fw_msg');
	$tkey->set(FALSE,'lcl');
	$this->msg->ht = &$tkey;
	$this->msg->output();
      } else $this->msg->output();
    }
  }



  protected function prepare_layout_hlmrf($layout){
    if(strpos($layout,'h')!==FALSE) $this->pointers[] = 'header';
    if(strpos($layout,'l')!==FALSE) $this->pointers[] = 'left';
    if(strpos($layout,'m')!==FALSE) $this->pointers[] = 'main';
    if(strpos($layout,'r')!==FALSE) $this->pointers[] = 'right';
    if(strpos($layout,'f')!==FALSE) $this->pointers[] = 'footer';
    $this->pointers = array_unique($this->pointers);
  }

  protected function output_layout_hlmrf($layout){
    if(is_object($this->header) and strpos($layout,'h')!==FALSE)
      $this->compl->incl_tag($this->header->root,'div','fw-header');

    $lay = preg_replace('/[fh]/','',$layout);
    switch($lay){
    case 'l':
      $this->compl->incl_tag($this->left->root,'div','fw-left1');
      break;
    case 'r':
      $this->compl->incl_tag($this->right->root,'div','fw-right1');
      break;
    case 'm':
      $this->compl->incl_tag($this->main->root,'div','fw-main1');
      break;
    case 'lm':
      $this->compl->incl_tag($this->left->root,'div','fw-left2');
      $this->compl->incl_tag($this->main->root,'div','fw-main2l');
      break;
    case 'mr':
      $this->compl->incl_tag($this->main->root,'div','fw-main2r');
      $this->compl->incl_tag($this->right->root,'div','fw-right2');
      break;
    case 'lmr':
      $this->compl->incl_tag($this->left->root,'div','fw-left3');
      $this->compl->open('div','fw-main3a');
      $this->compl->incl_tag($this->main->root,'div','fw-main3');
      $this->compl->incl_tag($this->right->root,'div','fw-right3');
      $this->compl->close();
      break;
    case 'lr':
      $this->compl->incl_tag($this->left->root,'div','fw-left5');
      $this->compl->incl_tag($this->right->root,'div','fw-right5');
      break;
    }

    if(is_object($this->footer) and strpos($layout,'f')!==FALSE)
      $this->compl->incl_tag($this->footer->root,'div','fw-footer');
  }


  protected function output_layout__single(){
    if(is_object($this->pmsg)) $this->compl->incl($this->pmsg->root);
    $this->compl->incl($this->main->root);
  }

  protected function output_layout__indexleft(){
    if(method_exists($this->main,'set_cb_open')) 
      $this->main->set_cb_open(array($this,'cb_open'),array('h1'));

    $this->compl->rem('Start index on left side',2);
    $this->compl->open('div',array('class'=>'index'));
    $this->compl->incl($this->left->root);
    $this->compl->close();
    $this->compl->rem('End index on left side',2);

    if(is_object($this->pmsg)) $this->compl->incl($this->pmsg->root);
    $this->compl->open('div',array('class'=>'main'));
    $this->compl->incl($this->main->root);
    $this->compl->close();
  }

  protected function prepare_layout__indexleft(){
    $this->left = NULL;
    $this->pointers[] = 'left';
  }





  protected function prepare_layout__col3(){
    $this->pointers[] = 'left';
    $this->pointers[] = 'right';
  }



  protected function output_layout__col3(){
    if(method_exists($this->main,'set_cb_open')) 
      $this->main->set_cb_open(array($this,'cb_open'),array('h1'));

    $this->compl->incl_tag($this->left->root,'div','column_left');

    $this->compl->add('<div class="clearrright"></div>');

    $this->compl->incl_tag($this->right->root,'div','column_right');

    $this->compl->incl_tag($this->main->root,'div','column_main');

    $this->compl->rem('Start column on right side',2);
    $this->compl->rem('End column on right side',2);
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

  // reads the complete session array
  function ses_get_keys(){
    if(!isset($_SESSION[$this->tool->key])) return array();
    return array_keys($_SESSION[$this->tool->key]);
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

  /* see prop_est for details */
  function prop_set($key,$val,$register=FALSE,$save='c'){
    $n = strlen($save);
    $i = 0;
    while($i<$n) $this->_prop_set($key,$val,substr($save,$i++,1));
    $this->uservalues[$key]['s'] = $register;
  }

  /** estimate property 
   * key: key of the asked element
   * def: default value
   * seq: char-sequence which defines the sequence of storage (first win)
   *   to look for the asked value. First wins!
   *   c=class (this->uservalues); s=session[tool->key];
   *   p=post;                     g=get
   *   typically for forms is pgs: post>get>session
   * register: sequence to save the value while destruction (default none; typically: 's')
   * save: sequence to save the value now (default: 'c')
   */
  function prop_est($key,$def,$seq='c',$register=FALSE,$save='c'){
    $n = strlen($seq);
    $i = 0;
    while($i<$n) if($this->_prop_get($key,substr($seq,$i++,1),$def)==0) break;
    $this->prop_set($key,$def,$register,$save);
    return $def;
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
  function msgdie($msg=NULL,$target='compl',$style=NULL){
    if(is_null($style)) $style = 'border: solid 5px red; margin: 10px; padding: 5px; color: black; font-weight: bold; background-color: #8f0;';
    if($msg) {
      if(substr($msg,0,1)=='@') $msg = $this->txp->t(substr($msg,1));
      $this->$target->span($msg,$style);
    }
    echo $this->output();
    die;
  }

  function msgclose($msg=NULL,$target='main'){
    if($msg) {
      if(substr($msg,0,1)=='@') $msg = $this->txp->t(substr($msg,1));
      $this->$target->add($msg);
    }
    echo $this->output();
    die;
  }

  function log(){
    $args = opc_logit::args_prep(func_get_args(),$this->txp_cls);
    if(is_object($this->logit)) return $this->logit->log($args);
    return deff($args,'ret','code',NULL);
  }
}

?>
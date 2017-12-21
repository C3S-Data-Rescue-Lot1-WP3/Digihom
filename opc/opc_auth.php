<?php
/* Missing features
   doku: nopwd/admins/info/quest/restrictions ...
   beautiful names for services (and additional infos at all)
   um allgemein (inkl ausgabe der gespeichrten infos und so wieter)
   schnittstelle zum passwort aendern?!

   login mit domain (im Formular -> unterscheidung wo anmelden, hat auswirkung wenn $auth=NULL als argument in quest etc)

   rechtesystem (direkt in opc_auth resp als eigene Klasse), untersysteme nur zum validieren des users
*/


interface opi_auth{
  /** initalize the system (returns T/F) */
  public function connect($connection,$tool);

  /** last (error)message of the system */
  public function msg();

  /** returns T/F if the quest may be answerd
   * user-exists -> checkuser
   * user-login -> checkpwd/login
   * user-acc -> access
   * info-XXX
   * quest-XXX
   */
  public function provide($info=NULL);

  /** test if user exists returns T/F*/
  public function checkuser($user);

  /** test user and password return TF */
  public function checkpwd($user,$pwd);

  /** return infos to this user */
  public function info($info,$user=NULL);

  /** return the result of a quest to this user */
  public function quest($user,$quest,$add=array());

  /** return infos about a realmto this user */
  //public function rinfo($info,$user=NULL);

  /** return general inforamtion */
  //public function ginfo($info,$user=NULL);

  /** returns a list of groups which a user is related to, used for access rights! */
  public function get_groups($user);

  /** returns a list of access-right for the current user (and only this) 
   * should be called after get_groups() if also group-rights should be used
   */
  public function get_access();
}

/** 
 * OPC authentification
 * 
 * A class to manage multiple authentification (sub)systems.
 * 
 * A auth-subsystem is definied by the following parameters
 *  key:        a key to identify the system
 *  class:      the php-class of the system 
 *              implement the opi_auth Interface
 *  connection: parameter to upset the subsystem
 *              will be used for the method connect
 *  required:   how to react if upsetting the auth-system fails:
 *              0: no reaction (=default); 1: warn; 2: die
 */
class opc_auth {
  /** allows to distinguish between different webservices */
  protected $ses_var = 'opc.auth';

  /** name of the current user */
  protected $cur_user = NULL;
  protected $cur_groups = array();

  /** system which the current user was auth. */
  protected $cur_auth = NULL; 

  /** cached infos about the current user */
  protected $cur_info = NULL;

  /* access/rights availablkefor this user (incl group rights)*/
  protected $cur_access = array();

  /** time in minutes for autologout */
  protected $def_timeout = 30;
  protected $timeout = 30;

  /** no password required */
  protected $nopwd = FALSE;

  /** array of request who need to retrun TRU for login/checkuser 
   * Syntax: array(questname=>array(add arguments),...)
   */
  protected $request = array();

  /** admin login */
  protected $adm_login = FALSE;

  /** admin users */
  protected $admins = array();

  /** cache for provide(_all) */
  protected $_provide = array(); 

  /** opc-tool */
  protected $tool = NULL;

  /** last message */
  protected $msg = '';

  protected $htpoints = array();

  /** list of authetification systems (as named array)
   *
   * will be initalized by {@link $def_seq} 
   * key: unique identifier
   * class: class name of the system
   * connection: used for method connect
   */
  protected $auth = array();

  /** default for {@link auth} */
  protected $def_seq = array(array('key'=>'opc-db',
				   'class'=>'opc_auth_pgdb',
				   'connection'=>'local-webview'),
			     );

  function __construct(&$tool,$seq=NULL,$add=array()){
    @session_start();
    $this->tool = &$tool;
    $this->ses_var = strval($this->tool->key);
    if(!is_array($seq)) $seq = $this->def_seq;
    foreach($seq as $cs) $this->add($cs);
    $this->admins = (array)def($add,'admins',array());
    $this->nopwd = (bool)def($add,'nopwd',FALSE);
    $this->request = (array)def($add,'request',array());
    $this->timeout = def($add,'timeout',$this->def_timeout);

    if(isset($add['ses_var'])) $this->ses_var = $add['ses_var'];
    if(def($add,'autologin',TRUE)) $this->autologin();
    if(def($add,'autoecho',FALSE)) echo $this->html($dummy);
    if(def($add,'autodie',FALSE) and !$this->loggedin()) die;
  }

  function write_user2session($logout=FALSE){
    if(isset($_SESSION[$this->ses_var . 'login']))
      $cur = $_SESSION[$this->ses_var . 'login'];
    else if($logout)
      $cur = array();
    else
      $cur = array('first_login'=>time());

    $new = array('user'=>$this->cur_user,
		 'adm_login'=>$this->adm_login,
		 'time'=>time());
    $_SESSION[$this->ses_var . 'login'] = array_merge($cur,$new);
  }

  function __destruct(){
    $this->write_user2session();    
  }

  function __get($key){
    switch($key){
    case 'ses_var': case 'def_seq': case 'htpoints': case 'msg':
      return($this->$key);
    case 'user': return $this->cur_user;
    case 'groups': return $this->cur_groups;
    case 'access': return $this->cur_access;
    }
    trigger_error("Error: read-access denied for '$key'");
  }

  function autologin(){
    if(defm($this->ses_var . '_logout',$_POST,$_GET,'no')=='yes'){
      return $this->logout();
    } else {
      $un = def($_POST,$this->ses_var . '_uname');
      $pw = def($_POST,$this->ses_var . '_pwd');
      if($this->login($un,$pw)) return TRUE;

      $ses = def($_SESSION,$this->ses_var . 'login',array());
      if(is_string(def($ses,'user'))){
	if(time()-$ses['time']>60*$this->timeout){
	  $this->msg = 'Err-1: logout by timeout';
	  return $this->logout();
	} else if($this->login_byses($ses['user'])){
	  $this->adm_login = def($ses,'adm_login',FALSE);
	  $this->msg = 'OK-4: autologin';
	  return TRUE;
	} 
      }
      $this->msg = 'Err-5: autologin failed';
      return FALSE;
    }
  }

  /** adds a new subsytem to the current one (only if successfull).
   * see {@link def_seq} for syntax
   */
  function add($set){
    $this->_provide = array(); // reset cache
    try {
      $cls = $set['class'];
      if(!@class_exists($cls)) 
	throw new Exception("Class $cls does not exist");
      if(!in_array('opi_auth',class_implements($cls))) 
	throw new Exception("Class $cls does not implement opc_intf_auth");

      $cauth = new $cls();

      $con = def($set,'connection');
      if(is_string($con) and preg_match('#^\w+>#',$con))
	$con = $this->tool->load_connection('con',$con);

      if($cauth->connect($con,$this->tool)!==TRUE)
	throw new Exception($cauth->msg());

      $this->auth[def($set,'key','OAS' . count($this->auth))] = &$cauth;
      return(TRUE);

    } catch (Exception $ex){
      $req = def($set,'required',1);
      if($req===0) return(FALSE);
      $msg = 'Error: unable to upset opc-auth-system \'' . $set['key'] . '\': '
	. ' *** '	. $ex->getMessage() . ' ***';
      trg_err(1,$msg,$req===1?E_USER_WARNING:E_USER_ERROR);
      return(FALSE);
    }
  }

  /** returns the keys of all (working) subsytems */
  function list_auth(){ return(array_keys($this->auth));}

  /** returns the key of the first subsystem which provides the asked info
   *   user-exists -> checkuser
   *   user-login -> checkpwd/login
   *   info-XXX -> info
   * @return: system-name or FALSE
   */
  public function provide($info=NULL,$auth=NULL){
    if(is_null($info)){
      if(is_null($auth)){
	$res = array();
	$ak = array_keys($this->auth);
	foreach($ak as $ck) $res = array_merge($res,$this->auth[$ck]->provide(NULL));
	return array_unique($res);
      } else return $auth->provide(NULL);
    } else if(is_null($auth)){
      if(isset($this->_provide[$info])) return(def($this->_provide[$info],0,FALSE)); // cached data
      
      $ak = array_keys($this->auth);
      foreach($ak as $ck) if($this->auth[$ck]->provide($info)) return($ck);
      return(FALSE);
    } else {
      return $this->auth[$auth]->provide($info);
    }
  }

  /** similar to {@link provide} but returns an array with all system */
  public function provide_all($info=NULL){
    if(is_null($info)){
      $res = array();
      foreach($ak as $ck) $res[$ck] = $this->auth[$ck]->provide(NULL);
      return $res;
    } else {
      if(isset($this->_provide[$info])) return($this->_provide[$info]); // cached!
      
      $res = array();
      $ak = array_keys($this->auth);
      foreach($ak as $ck) if($this->auth[$ck]->provide($info)) $res[] = $ck;
      return($res);
    }
  }

  /** test if a user exists, returns TF */
  public function checkuser($user,$auth=NULL){
    if(is_null($user)) return FALSE;
    if(is_null($auth)){
      $sys = $this->provide_all('user-exists');
      $res = FALSE;
      foreach($sys as $cs){
	if(@$this->auth[$cs]->checkuser($user)===TRUE) {$res = TRUE; break;}
      }
      if($res===FALSE) return FALSE;
    } else if(!$this->auth[$auth]->checkuser($user)) return FALSE;
    if(empty($this->request)) return TRUE;
    return $this->test_quest($this->request,$user,'and')!==FALSE;
  }

  /** test user and password, returns TF */
  public function checkpwd($user,$pwd,$auth=NULL){
    if(is_null($auth)){
      $sys = $this->provide_all('user-login');
      foreach($sys as $cs){
	if($this->auth[$cs]->checkpwd($user,$pwd)){
	  $this->cur_auth = $cs; 
	  return TRUE;
	}
      }
      return(FALSE);
    } else return $this->auth[$auth]->checkpwd($user,$pwd);
  }

  /* checks if the asked  right is goven for the current user
   * if non user is logged in the result is allways FALSE (since cur_access is empty)
   */
  public function access($acc_key){
    return in_array($acc_key,$this->cur_access);
  }

  /** test user and password and make him to the current user, returns TF */
  public function login($user,$pwd){
    if(is_null($user)) {
      $this->msg = 'Err-2: no user';
      return FALSE;
    }
    if(!$this->checkuser($user)){
      $this->msg = 'Err-3: invalid user';
      return FALSE;
    }

    $this->logout();
    try{ // this time a login-success will use the throw
      if($this->nopwd){
	$msg = 'OK-1: login without password';
	throw new Exception();
      } else if($this->checkpwd($user,$pwd)) {
	$msg = 'OK-2: login with password';
	throw new Exception();
      } else {
	if(in_array($user,$this->admins)) return FALSE;
	foreach($this->admins as $cadmin){
	  if($this->checkpwd($cadmin,$pwd)) {
	    $this->adm_login = TRUE;
	    $msg = 'OK-3: login with admin password';
	    throw new Exception();
	  }
	}
      }
      $this->msg = 'Err-4: invalid login';
      return FALSE;
    } catch (Exception $exp){}
    $this->set_user($user);
    $this->write_user2session();    
    $this->msg = $msg;
    return TRUE;
  }


  protected function login_byses($user){
    $this->logout();
    $this->set_user($user);
    $ses = def($_SESSION,$this->ses_var . 'login',array());
    $this->adm_login = def($ses,'adm_login',FALSE);
    foreach($this->auth as $ca) $ca->set_user($user);
    $this->write_user2session();
    return TRUE;
  }

  protected function set_user($user){
    $this->cur_user = $user;
    $grp = array();
    $acc = array();
    foreach($this->auth as $ca){
      $ca->set_user($user);
      $grp = array_merge($grp,$ca->get_groups($user));
      $acc = array_merge($acc,$ca->get_access($user));
    }
    $this->cur_groups = array_unique($grp);
    $this->cur_access = array_unique($acc);
  }

  protected function unset_user(){
    $this->cur_user = NULL;
    $this->cur_groups = array();
    $this->cur_access = array();
  }

  /** logout the current user */
  public function logout(){
    $this->unset_user();
    $this->adm_login = FALSE;
    foreach($this->auth as $ca) $ca->set_user(NULL);
    $this->cur_auth = NULL;
    $this->cur_info = array();
    $this->write_user2session(TRUE);
    $this->msg = 'OK-0: logout';
    return NULL;
  }

  /** TF if someone is logged in */
  public function loggedin(){
    return !is_null($this->cur_user);
  }

  public function isadmin($user=NULL){
    if(is_null($user)) $user = $this->cur_user;
    if(is_null($user)) return NULL;
    return in_array($user,$this->admins);
  }


  /** return infos to the current user */
  public function info($info,$user=NULL,$auth=NULL){
    if(is_null($auth)){
      $sys = $this->provide_all('info-' . $info);
      foreach($sys as $cs){
	$res = $this->auth[$cs]->info($info,$user);
	if($this->auth[$cs]->ok()) return $res;
      }
      return(FALSE);
    } else return $this->auth[$auth]->info($info,$user);
  }


  /** provides html result login/logoout ; $hid is a named array for hidden input fields*/
  function html(&$ht,$add=array(),$hid=array()){
    if($this->loggedin()) return $this->html_logout($ht,$add,$hid);
    else                  return $this->html_login($ht,$add,$hid);
  }

  /** provides html result login 
   * $ht: target object
   * $add: details for the form
   * $hid: named array with values that should be submitted too using hidden input fields
   */
  public function html_login(&$ht,$add=array(),$hid=array()){
    $isht2 = $ht instanceof opc_ptr_ht2;
    if($isht2) $hf = new opc_ptr_ht2form($ht);
    else $hf = opc_ht2::get_instance('form');

    $vun = $this->ses_var . '_uname';
    $vpw = $this->ses_var . '_pwd';
    $hf->def[$vun] = def($_POST,$vun,'');
    $hf->def[$vpw] = def($_POST,$vpw,'');

    $lu = def($add,'label_uname','username');
    $lp = def($add,'label_pwd','password');
    $su = array('value'=>'login','type'=>'submit','tag'=>'input');

    $this->htpoints['form'] = $hf->fopen($hid,array('class'=>$this->ses_var . '.login_form'));
    
    switch(def($add,'style')){
    case 'line':
      $hf->open('div');
      $hf->tag('span',$lu,'padding: 0 5px 0 0;');
      $this->htpoints['username'] = $hf->tag('span');
      if(!$this->nopwd){
	$hf->tag('span',$lp,'padding: 0 5px 0 10px;');
	$this->htpoints['password'] = $hf->tag('span');
      } else $this->htpoints['password'] = NULL;
      $this->htpoints['submit'] = $hf->atag($su);
      $hf->close(); // div
      break;

    default:
      $this->htpoints['table'] = $hf->open('table'); 
      $hf->open('tr'); 
      $hf->tag('th',$lu);
      $this->htpoints['username'] = $hf->tag('td'); 
      if(!$this->nopwd){
	$hf->next();
	$hf->tag('th',$lp);
	$this->htpoints['password'] = $hf->tag('td');
      } else $this->htpoints['password'] = NULL;
      $hf->next();
      $hf->open('th',array('colspan'=>2));  $this->htpoints['submit'] = $hf->atag($su);
      $hf->close(3);
      break;
    }
    
    $hf->in($this->htpoints['username']); // set pointer temporary to the prepare places
    $hf->text($vun,NULL,array('size'=>8));
    if(!is_null($this->htpoints['password'])){
      $hf->set($this->htpoints['password']);
      $hf->password($vpw,NULL,array('size'=>8));
    }
    $hf->out(); // and back to the point before, otherwise fclose would start at the wrong place
    $hf->fclose();
    if(!$isht2) return $hf->exp(FALSE);
    $ht->incl($hf->root);
    return $this->htpoints['form'];
  }

  /** provides html result login
   * $ht: target object
   * $add: details for the form
   * $hid: named array with values that should be submitted too using hidden input fields
   */

  public function html_logout(&$ht,$add=array(),$hid){
    $isht2 = $ht instanceof opc_ptr_ht2;
    if(!$isht2) $ht = opc_ht2::get_instance();

    $this->htpoints['logout'] = $ht->open(def($add,'tag','span'),array('class'=>$this->ses_var . ' logout'));
    if($this->adm_login){
      $att = array('style'=>'background-color:#FF1493; color: #FFD700; padding: 0 2px;',
		   'title'=>'ADMIN LOGIN');
    } else if($this->nopwd){
      $att = array('style'=>'background-color:#228B22; color: white; padding: 0 2px;',
		   'title'=>'UNPROVEN LOGIN');      
    } else $att = array('title'=>$this->msg);
    $ht->span(str_replace(' ','&nbsp;',$this->info(def($add,'show','flname'))),$att);
    $lt = def($add,'logout',' [logout]');
    $at = array_merge($hid,array($this->ses_var . '_logout'=>'yes'));
    $cl = 'logout';
    $ht->a($lt,$at,$cl);
    $ht->close();
    if(!$isht2) return $ht->exp(FALSE);
    return $this->htpoints['logout'];
  }

  function quest($auth=NULL,$user=NULL,$quest,$add){
    if(is_null($auth)){
      $res = $this->test_quest(array($quest=>$add),$user,'and');
      if($res===FALSE) return FALSE;
      $this->auth[$res]->ok();
      return TRUE;
    } else return $this->auth[$auth]->quest($user,$quest,$add);
  }


  /* 
   * quest is an array with quest=>args, where quest may be also an and:XXX or or:XXX for subquests
   * returns FALSE, the AS-name which matched first or TRUE (for an and quest)
   */
  protected function test_quest($quests,$user,$kind='and'){
    foreach($quests as $key=>$quest){
      if(preg_match('/^(and|or):/',$key)){
	$res = $this->test_quest($quest,$user,substr($key,0,strpos($key,':')));
      } else {
	$sys = $this->provide_all('quest-' . $key);
	$res = FALSE;
	foreach($sys as $cs)
	  if($res = call_user_func_array(array($this->auth[$cs],'quest'),array($user,$key,$quest))) break;
      }
      switch($kind){
      case 'and': if(!$res) return FALSE; else break;
      case 'or':  if($res) return $cs;    else break;
      }
    }
    switch($kind){
    case 'and': return isset($cs)?$cs:TRUE;
    case 'or':  return FALSE;
    }
  }

  /** checks if the subsystems are running
   * if subsytem given returns T/F
   * otherwise: mode: 'all' T/F, 'any' T/F, count: integer
   */
  public function running($auth=NULL,$mode='all'){
    if(is_null($auth)){
      $res = 0;
      foreach($this->auth as $ca) if($ca->running()) $res++;
      switch($mode){
      case 'all': return $res==count($this->auth);
      case 'any': return $res>0;
      }
      return $res;
    } else return $this->auth[$auth]->running();
  }

}







/* A generic base class for the auth-subsystems
 * handles the request from opc_auth
 */

abstract class opc_auth_generic implements opi_auth{
  protected $status = NULL;
  protected $sys_status = 1;
  protected $set = array();
  protected $srv = NULL;
  protected $srv_list = array();
  protected $cur_user = NULL;
  protected $cur_groups = array();

  static $error_table = array(0=>'success',
			      1=>'not initialized',
			      2=>'file with connection parameter missed',
			      3=>'invalid connection parameters',
			      8=>'Connection failed',
			      9=>'Unable to validation server',
			      20=>'Invalid user name',
			      21=>'Invalid password',
			      22=>'unkonwn user',
			      23=>'unknown info',
			      24=>'unknown quest',
			      );
  
  public function __construct(){
    $this->status = new opc_status();
    $this->status->load_msg(self::$error_table);
  }
  public function running(){return($this->sys_status==0); }
  public function msg(){return($this->status->msg());}
  public function ok(){return($this->status->is_success());}

  public function set_user($user){$this->cur_user = $user;}
  public function get_groups($user) { return array();}
  public function get_access() { return array();}

  public function provide($info=NULL){ 
    return is_null($info)?$this->srv_list:in_array($info,$this->srv_list);
  }

  
  final function quest($user,$quest,$add=array()){
    if(is_null($user)) $user = $this->cur_user;
    if(is_null($user)) return $this->status->errF(20);
    $res = $this->_quest($user,$quest,$add);
    if(is_bool($res)) return $this->status->ok($res);
    if(is_int($res))  return $this->status->errF($res);
    return $this->status->errF(24);
  }

  protected function _quest($user,$quest,$add){
    switch($quest){
    case 'list_member':
      if(is_array($add)){
	if(isset($add['allow'])) return in_array($user,$add['allow']);
	if(isset($add['deny']))  return !in_array($user,$add['deny']);
      } else if(is_string($add)){
	$inv = substr($add,0,1)=='-';
	$add = explode(' ',preg_replace('/^[-+]\s*/','',$add));
	if($inv) return !in_array($user,$add);
	else     return in_array($user,$add);
      } 
      return FALSE;
    }
    return NULL;
  }
}




?>
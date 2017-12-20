<?php

  /*
   * lables ausrotten
   * xml aufrüsten
   * occurence regeln definieren/durchsetzen 
   *
   * gruppen mit Baumstruktur (parents)
   * vererbete gruppenzugehörigkeit wie genau
   *   gruppe sagt, dass member einer anderen auch dabei sind!
   *
   * sichtbarkeit von gruppen und rechten ?!
   * login mit pass by argumenten (oder auch weiteren)
   *
   * wenn nur ein umd da ist, ohne prefix arbeiten?!
   * d:quest erlaubt nochmals, im umd testen ob erlaubt!
   */


  /*
   * vdn: identifier using validation-domain and username
   * rdi: identifier using responsible-domain and internal id
   */

  /* Oberfläche zur Userverwaltung
   * Die eigentliche Schnittstelle zu den Daten liegt bei opc_auth
   * 
   * + Alles ist via rechte abgesichert
   * + User können andere user erzeugen 
   * + User können Gruppen erzeugen und diesen Leute zuordnen
   * + Es gibt (ugl)
   *   - User: 1 (echte) Person resp Funktion, kann Passwort ändern etc
   *   - Gruppen: Sammlung von anderen (ugl) wird primär für die Rechteverwaltung geprägt
   *   - Login: Kombination von name/passwort. Für allgemeine Zwecke (keine echte Person funktion)
   * + Anzeige der Personeninformationen (inkl Quelle)
   * + Möglichkeit die gespeicherten Daten zu ändern (restriktiv), ie: Passwort
   * + Einzelbereiche können auch von externen Seiten einbezogen werden (via ht)

   * auth
   * + Ein Hauptsystem mit untergeordneten domain
   * + pro domain-typ (ldap, db, files etc) gibt es eine domain-klasse
   * + Beim starten des Hauptsystems werden die einzelnen domains mit den nötigen
   *   Parametern (ldap verbindung) geladen (auch mehrere des glecihen Typs möglich)
   * + Jeder Domain gibt an, was er kann:
   *   User validierung/login, welche Userinformationen, Abfragen pro user
   *   wobei das Hauptsystem diese 'vordefiniert' und die domains diese auf die spezifischen
   *   systeme umsetzen müssen.
   * + Ein Domain stellt die Verbindung zu einer Datenquelle her (ldap, db, De
   * + User kann beim anmelden einen domain wählen (Bsp ETH-ldap IAC-Database etc)
   
   *
   */

interface opi_um_basics {
}

interface opi_um extends opi_um_basics{
  function loggedin();
}

class opc_um extends opc_tmpl1 implements opi_um {

  protected $txp_cls = 'um';

  protected $sdef = ':'; // separator default (for 'external' groups/user)
  protected $ddef = NULL; // default domain

  protected $svdn = '::'; // seperator for vdn identifiers
  protected $srdi = '!!'; // seperator for rdi identifiers
  
  protected $pat_uname = '{^[a-zA-Z][a-zA-Z0-9_]{2}[a-zA-Z0-9_]*$}';
  protected $pat_pwd = '/^[-a-zA-Z0-9:.,;_+@#%]{6,50}$/';
  protected $pat_email = '/^\w+[\+\.\w-]*@([\w-]+\.)*\w+[\w-]*\.([a-z]{2,4}|\d+)$/i';

  /* domain --------------------------------------------------
   * domains: link list to opc_umd intances array(key=>&pbj)
   * domain_lables: labels of those array(key=>label)
   * domain_seq: default sequence of domains
   */
  protected $domains = array();
  protected $rdomains = array();
  public $domain_labels = array();
  protected $domain_seq = array();


  /* current login state ------------------------------
   * 0 not logged in
   * 1: normal login
   * 2: with admin password
   * 3: without password, 
   */
  protected $login_state = 0;

  public $login_quest = NULL;

  /*-1: there was no login at all
   * 0: login ok
   * 1: unkown reason
   * 2: no validation possible
   * 3: invalid username
   * 4: unkown username
   * 5: invalid password
   * 6: Wrong combination user/password
   * 7: Not allowed to log in
   */
  protected $login_failed = -1;

  /* current user --------------------------------------------------
   * cuser: opc_umu object
   * ctime: time of the 'first' login of the recent connection
   */
  protected $cuser = NULL;
  protected $ctime = NULL;

  /* Structer --------------------------------------------------
   * arrays about existing groups, rights and quests
   * will be filled using method add
   * id=>array('label'=>'TEXT','subs'=>array(of other ids)
   *           ':DOMAIN'=>array('did'=>Domain internal id,
   *           'acc'=>acces to this quest: allow, deny, restrict [QUESTS only]
   *           ....
   *           )
   */
  protected $groups = NULL; // where the user may be member of
  protected $rights = NULL; // which the user may have
  protected $fields = NULL; // quest which the domains may answer






  /* various -------------------------------------------------- */

  // fields which may be constructed based on other fields
  public $field_spez = array('shownname','fname','lname','mname','uname');

  // how field 'shownname' is build up from other fields. First complete wins
  public $shownname_styles = array('%dispname%',
				   '%firstname% %mname%. %lastname%',
				   '%firstname% %lastname%',
				   '%lastname%',
				   '%firstname%',
				   '%company%',
				   '%uname%');

  public $chartrans = array('ä'=>'ae','ö'=>'oe','ü'=>'ue',
			    'Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue',
			    'èéê'=>'e','àáâ'=>'a','úùû'=>'u',
			    'ç'=>'c','Ç'=>'C');


  protected $cat_mtyp = array('owner','admin','member');

  protected $cls_map = array();
  public $cls_map_def = array('umu'=>'opc_umu');

  // identifier to distinguish between different logins
  protected $key = NULL;

  // link to the framework (if knwon)
  protected $fw = NULL;

  // text table
  protected  $txp = NULL;


  // link to the framework (if knwon)
  protected $tool = NULL;

  // deny auto login after #minutes of no activity
  public $logout_auto = 20;

  // is password needed for login? 
  protected $pwd_mandatory = TRUE;

  /* list of admins which are allowed for an admin login (uname=>array('domain'),...)
   * uname has to be unique over all domains!
   */
  protected $admins = array();

  /* things to be done during method start (in this order)
   * named array
   * calls um-method start__KEY with value as argument
   * method starts with :: will use umd-methods instead
   * if key starts with :: it will be 
   */
  protected $start = array('::init'=>NULL,
			   '::load_access_rules'=>NULL,
			   'load_rights'=>NULL,
			   'load_groups'=>NULL,
			   'load_fields'=>NULL,
			   'load_quests'=>NULL,
			   'autologin'=>FALSE,);


  // was system started (method start)
  protected $running = FALSE;

  protected $cache_vdn2res = array();
  protected $cache_infoumd = array();
  protected $cache_disp = array();

  // basic settings
  public $codes = NULL;  


  // keys which are allowed to access over magic get
  protected $__get_keys = array('key','login_state','login_failed',
				'pwd_mandatory','running',
				'groups','rights','quests','fields',
				'cuser',
				'svdn','srdi',
				'cat_mtyp','fw',
				'admins');
  protected $__get_umu = array('cname','cuname',
			       'crights','cgroups',
			       'cumd','cumd_key');

  // which variables can be set directly during init (done by init_rest)
  protected $init_var = array('login_quest');




  function umu_get($vdn){
    if(!$this->user_exists($vdn)) return NULL;
    $cls = def($this->cls_map,'umu','opc_umu');
    return new $cls($this,$vdn);
  }


   
  /* ================================================================================
    ================================== METHODS ======================================
    ================================================================================ */

  /* ================================================================================
   Construction, initalization and starting
   ================================================================================ */

  /* Arguments will be determined by type
   * bool: Password mandatory?
   * string (readable file) used for settings_load
   * string (others) key for this instance (used for session)
   * opc_fw: swt ffw
   */

  function __construct(){
    @session_start();
    $this->tmpl_init();
    $this->rights = new opc_um_rights($this);
    $this->groups = new opc_um_groups($this);
    $this->fields = new opc_um_fields($this);
    foreach(func_get_args() as $ar){
      if(is_string($ar) and is_readable($ar))
	$this->init__um_settings($ar);
      else if(is_string($ar))
	$this->init__um_key($ar); 
      else if(is_bool($ar))  
	$this->init__pwd_mandatory($ar);
      else if($ar instanceof opc_fw)
	$this->init__fw($ar);
      else if($ar instanceof _tools_)
	$this->init__tool($ar);
      else if(is_array($ar))
	$this->init__array($ar);
      else 
	trigger_error('unkown construct-argument for opc_um' . var_export($ar,TRUE));
    }
    if(is_null($this->key)) $this->key = session_id();
    $this->cls_map = defca('cls_map_def',$this);
  }


  function init__array($arr){
    $first = array('umds');
    foreach($first as $key){
      if(!isset($arr[$key])) continue;
      $mth = 'init__' . str_replace('-','_',$key);
      if(method_exists($this,$mth)) $this->$mth($arr[$key]);
      else trigger_error('unkown construct-argument for opc_um: ' . $key);
      unset($arr[$key]);
    }
    foreach($arr as $key=>$val){
      $mth = 'init__' . str_replace('-','_',$key);
      $tmp = method_exists($this,$mth)?$this->$mth($val):$this->init_rest($key,$val);
      if(is_string($tmp)) trigger_error($tmp);
    }
  }

  function init__sdef($val){
    if(!is_string($val)) return 9990;
    $this->sdef = $val;
    return 0;
  }

  function init__ddef($val){
    if(!is_string($val)) return 9990;
    $this->ddef = $val;
    return 0;
  }

  function init__fw(&$fw){
    if(!($fw instanceof opc_fw)) return 2;
    $this->fw = $fw; 
    $this->txp = &$fw->txp;
    if(is_null($this->key)) $this->key = $fw->key . '--um';
    if(!is_object($this->tool)) $this->tool = &$fw->tool;
    return 0;
  }

  function init__tool(&$tool){
    if(!($tool instanceof _tools_)) return 2;
    $this->tool = $tool;
    if(is_null($this->key)) $this->key = $tool->key . '--um';
    return 0;
  }

  function init__pwd_mandatory($bool){
    if(!is_bool($bool)) return 2;
    $this->pwd_mandatory = $bool;
    return 0;
  }

  function init__autostart($bool){
    if(!is_bool($bool)) return 2;
    if($bool) $this->start();
    return 0;
  }

  function init__autologin($bool){
    if(!is_bool($bool)) return 2;
    if($this->running) $this->start__autologin($bool);
    else $this->start['autologin'] = $bool;
    return 0;
  }

  function init_rest($key,$val){
    if(in_array($key,$this->init_var)){
      $this->$key = $val;
      return 0;
    } 
    return 'Error: Unkown argument to init a ' . __CLASS__ . ' object: ' . $key;
  }

  function init__admins($arr){
    if(!is_array($arr)) return 2;
    if($this->running) $this->start__admins($arr);
    else $this->start['admins'] = $arr;
    return 0;
  }


  function init__um_key($key){
    if(!is_string($key) or !preg_match('/^[a-zA-z0-9-:_]+$/',$key)) return 2;
    $this->key = $key;
    return 0;
  }

  function init__um_settings($file){
    $file = (array)$file;
    if(is_object($this->tool))
      foreach($file as $ck=>$cv) $file[$ck] = $this->tool->det_file($cv,0,'abs');
    $cfile = str_replace('-','_','umd__' . $this->key . '__settings');
    $cache = opt::cache(11,$cfile,$file,'');
    if(is_null($cache) or 2){ // TUDU
      $this->codes = array();
      foreach((array)$file as $cf){
	$xml = new opc_sxml($cf);
	$keys = $xml->nodes_search('{%H%P/codes%P/code$}');
	$this->codes = array_merge($this->codes,$xml->attrs_list($keys,'text','key'));
      }
      $cache = array('codes'=>$this->codes);
      opt::cache(19,$cfile,$cache,'');
    } else {
      $this->codes = $cache['codes'];
    }
    return 0;
  }

  /* init domains (given by a named array
   * string: is a xml file
   */
  function init__umds($umds){
    foreach($umds as $key=>$val){
      if(is_string($val))     $res = $this->domain_add_xmlfile($key,$val);
      else if(is_array($val)) $res = $this->domain_add_arr($key,$val);
      else                    $res = 1;
      if($res>0) trigger_error("Failed adding domain '$key' to user management ($res)");
    }
    return 0;
  }


  function start(/* ... */){
    // Basic preparation
    if(!isset($_SESSION[$this->key])) $_SESSION[$this->key] = array();

    // handle arguments
    $order = $this->start;
    $ar = func_get_args();
    foreach($ar as $ca){
      if(is_string($ca)) 
	$order[$ca] = TRUE;
      else if(is_array($ca))
	$order = array_merge($order,$ca);
    }


    foreach(array_keys($this->domains) as $ck)
      if($this->domains[$ck]->connected()) $this->rdomains[$ck] = &$this->domains[$ck];
    // process start chain
    foreach($order as $ck=>$cv){
      if(is_numeric($ck)) continue;
      if(substr($ck,0,2)=='::'){
	$mth = 'start__' . substr($ck,2);
	foreach($this->rdomains as $cd) {
	  if(method_exists($cd,$mth)) $cd->$mth($cv);
	  else trigger_error("Unkown umd start option $ck");
	}
      } else {
	$mth = 'start__' . $ck;
	if(method_exists($this,$mth)) $this->$mth($cv);
	else trigger_error("Unkown um start option $ck");
      }
    }

    // finished
    $this->running = TRUE;
  }

  function start__autologin($val){
    if($val) $this->login_auto();
  }

  function start__admins($arr){
    foreach($arr as $cadmin) $this->admin_add($cadmin);
  }

  function start__load_rights(){
    foreach($this->rdomains as $cd)
      $this->rights->add($cd->load_rights(),$cd->key);
  }

  function start__load_groups(){
    foreach($this->rdomains as $cd)
      $this->groups->add($cd->load_groups(),$cd->key);
  }

  function start__load_fields(){
    foreach($this->rdomains as $cd)
      $this->fields->add($cd->load_fields(),$cd->key);
  }

  function start__load_quests(){
    foreach($this->rdomains as $cd) $cd->load_quests();
  }

  // END ================================================================================



  /* ================================================================================
     Login and Logout
     ================================================================================ */
  function login_auto(){
    $dat = $_SESSION[$this->key];
    try {
      if(empty($dat)) 
	throw new Exception('login_auto_failed'); 
      if(time()-$dat['refresh']>$this->logout_auto*60)
	throw new Exception('login_auto_timeout'); 
      $_SESSION[$this->key]['refresh'] = time();
      if($this->login_load($_SESSION[$this->key])!=0)
	throw new Exception('login_auto_unkown'); 
      $this->login_finish();
    } catch (Exception $ex){
      $this->logout();
      return 1;
    } 
  }

  function login($uname,$pwd,$add=array()){
    if(!isset($add['umd'])){
      if(count($this->domains)!=1) {
	$this->login_failed = 2;
	return 2003;
      }
      $dom = def(array_keys($this->domains));
    } else $dom = $add['umd'];

    if(!isset($this->domains[$dom])){
      $this->login_failed = 2;
      return 2002;
    }
    
    $cdom = $this->domains[$dom];
    if(!$cdom->connected){
      $this->login_failed = 2;
      return 2004;
    }

    // check user/pwd, dom may be reloacted to the responsible domain (key)
    $dom_val = $dom;
    $vdn = $this->vdn_make($uname,$dom);
    if($this->pwd_mandatory){
      $tmp = $cdom->user_validate($vdn,$pwd); 
      if($tmp>0) {
	  $this->login_failed = 6;
	  return $tmp;
      }
      $state = 1-$tmp;
    } else {
      $tmp = $cdom->user_validate_nopwd($vdn);
      if($tmp>0) {
	$this->login_failed = $tmp==4002?4:7;
	return 1;
      }
      $state = 3;
    }
    if(!is_null($this->login_quest)){
      $uo = $this->umu_get($vdn);
      if(!$uo->quest($this->login_quest)){
	$this->login_failed = 7;
	return 1;
      }
    }
    return $this->login_proceed($vdn,$state);
  }

  function login_proceed($vdn,$state){
    $res = $this->login_save($vdn,$state);
    if(!is_array($res)){
      $this->login_failed = 8;
      return $res;
    }
    $_SESSION[$this->key] = $res;
    $res = $this->login_finish();
    $this->login_failed = $res>0?8:0;
    return $res;
  }

  /* log out the current user */
  function logout(){
    foreach($this->domains as $cd) $cd->logout();
    $this->cuser = NULL;
    $this->ctime = NULL;
    $this->login_state = 0;
    $_SESSION[$this->key] = array();
  }


  /* load cuser from array */
  protected function login_load($dat){
    $uo = $this->umu_get($dat['user']);
    if(!is_object($uo)) return 1;
    $this->login_state = $dat['login_state'];
    $this->cuser = $uo;
    $this->ctime = $dat['datetime'];
    return 0;
  }

  function login_finish(){
    $this->cuser->dres->set_as_primary();
    foreach($this->domains as $cd) $cd->login();
    return 0;
  }

  /* set (new) current user and return array to save for auto_login */
  protected function login_save($vdn,$log_state){
    foreach($this->domains as $cd) $cd->logout();
    $this->cuser = $this->umu_get($vdn);
    $this->login_state = $log_state;
    $this->ctime = time();

    $this->cuser->dres->set_as_primary();

    $res = array('login_state'=>$this->login_state,
		 'user'=>$vdn,
		 'datetime'=>$this->ctime,
		 'refresh'=>time());
    return $res;
  }


  function cuser_reload(){
    if(!is_object($this->cuser)) return 1;
    $vdn = $this->cuser->vdn;
    $this->cuser = $this->umu_get($vdn);
    return 0;
  }

  /* ================================================================================
     Domain management
     ================================================================================ */
  /* returns the lsit of available domains
   * 0: just the key list:
   * 1: class name of the domain (key=>classname)
   * 2: is the domain connected (key=>T/F)
   * 3: user friendly name of the domain (using label)
   */
  
  function domains_list($how){
    $res = array();
    switch($how){
    case 0: return array_keys($this->domains);
    case 1: 
      foreach($this->domains as $key=>$dom) $res[$key] = get_class($dom);
      return $res;

    case 2: 
      foreach($this->domains as $key=>$dom) $res[$key] = $dom->connected();
      return $res;

    case 3: 
      foreach($this->domains as $key=>$dom) $res[$key] = $dom->label();
      return $res;

    }
  }

  // returns the asked domain (object)
  function domain_get($key=NULL){
    if(is_null($key)) return NULL;
    if(is_null($key)) $key = $this->cumd;
    return isset($this->domains[$key])?$this->domains[$key]:NULL;
  }

  // add a domain (object) using key as identifier
  function domain_add($key,&$obj){
    if(!($obj instanceof opc_umd)) return 1;
    $this->domains[$key] = $obj;
    $this->domain_labels[$key] = $obj->label();
    if(!in_array($key,$this->domain_seq)) $this->domain_seq[] = $key;
    return 0;
  }

  // multiple domain_add using a named array
  function domains_add($files){
    $res = array();
    foreach($files as $key=>$file) $res[$key] = $this->domain_add($key,$file);
    return $res;
  }



  // add domain using an xmlfile with settings
  function domain_add_xmlfile($key,$file){
    if(is_object($this->tool)) $file = $this->tool->det_file($file,0,'abs');
    if(!file_exists($file) or !is_readable($file)) return 20;
    $xml = new opc_sxml($file);
    if(!is_null($xml->error_msg)) return 21;
    return $this->domain_add_xml($key,$xml);
  }

  // multiple domain_add_xmlfile using a named array
  function domains_add_xmlfile($files){
    $res = array();
    foreach($files as $key=>$file) $res[$key] = $this->domain_add_xmlfile($key,$file);
    return $res;
  }

  // add domain using an xml-object (opc_sxml) with settings
  function domain_add_xml($key,$xml){
    $xkey = $xml->node_search('{%H%P/connection$}');
    if(is_null($xkey)) return 11;
    $basics = $xml->attr_geta('%H');
    $cls = def($basics,'class','-');
    if(!class_exists($cls)) return 10;
    $obj = new $cls($this,$key);
    $obj->connect($xml->attr_geta($xkey));
    if(isset($basics['label'])) $obj->label_set($basics['label']);
    return $this->domain_add($key,$obj);
  }

  // multiple domain_add_xml using a named array
  function domains_add_xml($files){
    $res = array();
    foreach($files as $key=>$file) $res[$key] = $this->domain_add_xml($key,$file);
    return $res;
  }


  function domain_add_arr($key,$arr){
    $cls = def($arr,'cls','-');
    if(!class_exists($cls)) return 10;
    $obj = new $cls($this,$key);
    $obj->connect(def($arr,'connect',array()));
    if(isset($arr['label'])) 
      $obj->label_set($arr['label']);
    return $this->domain_add($key,$obj);
  }


  /* ================================================================================
     current user and domain
     ================================================================================ */
  /* called by the responsible domain; sets the cuser related things */
  function cuser_set($disp,$grps,$rights){
    $this->cname = $disp;
    $this->cgroups = $grps;
    $this->crights = $rights;
  }

  /* ================================================================================
   group
     ================================================================================ */
  function group_create($data){
    $dval = $data['umd_val'];
    if(!isset($this->domains[$dval])) return 2;
    if(!in_array($dval,$this->quest_provider('d:group','create',TRUE,FALSE)))
      return 3;
    $tmp = $this->domains[$dval]->group_create($data);
    if($tmp>0) return $tmp;
    return $this->gdn_make($data['gname'],$dval);
  }

  function group_remove($gdn){
    list($dval,$id) = $this->gdn_split($gdn);
    if(!isset($this->domains[$dval])) return 2;
    foreach((array)$this->group_user_list($gdn) as $vdn)
      $this->group_user_remove($gdn,$vdn);
    return $this->domains[$dval]->group_remove($gdn);
  }

  /* ================================================================================
     user
     ================================================================================ */
  function user_create($data){
    if(isset($data['umd_val'])){
      $dval = $data['umd_val'];
    } else if(count($this->domain_seq)){
      $dval = $this->domain_seq[0];
    } else {
      return 2;
    }
    if(!isset($this->domains[$dval])) return 2;
    if(!in_array($dval,$this->quest_provider('d:user','create',TRUE,FALSE)))
      return 3;
    $tmp = $this->domains[$dval]->user_create($data);
    if($tmp>0) return $tmp;
    foreach($this->domains as $cd) $cd->cache_clear();
    return $this->vdn_make($data['uname'],$dval);
  }

  function user_remove($vdn){
    $res = array();
    foreach($this->domains as $ck=>$cd)
      $res[$ck] = $cd->user_remove($vdn);
    return $res;
  }

  function user_validate($vdn,$pwd){
    if(!is_object($val = $this->vdn2val($vdn))) return $val;
    return $val->user_validate($vdn,$pwd);
  }


  function users_count($how=0){
    $res = array();
    foreach($this->domains as $ck=>$cd){
      $res[$ck] = $cd->users_count();
    }
    return $res;
  }

  // offlim: offset & limit as array(100,50)
  function users_list($how=0,$offlim=NULL){
    $res = array();
    if(!is_null($offlim) and count($this->domains)>1) qz();
    foreach($this->domains as $ck=>$cd){
      $tmp = array();
      foreach((array)$cd->user_list($offlim) as $vdn)
	$tmp[$vdn] = $this->user_disp($vdn);
      if(empty($tmp)) continue;
      if($how==1) $res[$ck] = $tmp; 
      else $res = array_merge($res,$tmp);
    }
    return $res;
  }

  // offlim: offset & limit as array(100,50)
  function users_list_raw($offlim=NULL){
    $res = array();
    if(!is_null($offlim) and count($this->domains)>1) qz();
    foreach($this->domains as $ck=>$cd){
      $tmp = array();
      foreach((array)$cd->user_list($offlim) as $vdn)
	$tmp[$vdn] = $vdn;
      $res[$ck] = $tmp;
    }
    return $res;
  }


  /* returns a list of all users which do have none of the
   * information defined in kinds
   * kinds is an array of field names,
   *  where '@grp' means any kind of group relation
   * retun value is defined by how
   *  0: array for each domain
   *  1: list of all with true in all domains
   *  2: list of all with true in one or more domains
   *  3: list of all with false (or missing) in all domains
   *  4: list of all with false (or missing) in one or more domains
   */
  function users_list_hasdata($kinds,$how=0){
    $res = array();
    foreach($this->domains as $ck=>$cd){
      $tmp = $cd->user_list_hasdata((array)$kinds);
      if(is_array($tmp)) $res[$ck] = $tmp;
    }
    if(empty($res)) return NULL;
    switch($how){
    case 0:
      return $res;
    case 1:
      $all = array_keys(array_filter(array_shift($res)));
      foreach($res as $cres)
	$all = array_intersect($all,array_keys(array_filter($cres)));
      return array_values($all);
    case 2:
      $all = array();
      foreach($res as $cres)
	$all = array_merge($all,array_keys(array_filter($cres)));
      return array_values(array_unique($all));
    case 3: 
      $all = array(); $ok = array();
      foreach($res as $cres) {
	$all = array_merge($all,array_keys($cres));
	$ok = array_merge($ok,array_keys(array_filter($cres)));
      }
      return array_values(array_diff(array_unique($all),$ok));
    case 4:
      $all = array(); $ok = array();
      foreach($res as $cres) {
	$all = array_merge($all,array_keys($cres));
	$ok = array_intersect($ok,array_keys(array_filter($cres)));
      }
      return array_values(array_diff(array_unique($all),$ok));
    default:
      return NULL;
    }
  }    


  function group_user_add($gdn,$vdn,$mtyp){
    if(!is_object($val = $this->gdn2val($gdn))) return $val;
    if(!in_array($mtyp,$this->cat_mtyp)) return 1;
    if(is_array($vdn)){
      $res = array();
      foreach($vdn as $cvdn) 
	$res[$cvdn] = $this->user_group_add($cvdn,$gdn,$mtyp);
      return $res;
    } else return $this->user_group_add($vdn,$gdn,$mtyp);
  }

  function group_user_remove($gdn,$vdn){
    if(!is_object($val = $this->gdn2val($gdn))) return $val;
    if(is_array($vdn)){
      $res = array();
      foreach($vdn as $cvdn) 
	$res[$cvdn] = $this->user_group_remove($cvdn,$gdn);
      return $res;
    } else return $this->user_group_remove($vdn,$gdn);
  }


  function group_user_change($gdn,$vdn,$add){
    if(!is_object($val = $this->gdn2val($gdn))) return $val;
    if(is_array($vdn)){
      $res = array();
      foreach($vdn as $cvdn) 
	$res[$cvdn] = $this->user_group_change($cvdn,$gdn,$add);
      return $res;
    } else return $this->user_group_change($vdn,$gdn,$add);
  }

  function group_user_list($gdn,$mtyp=NULL){ 
    if(!is_object($val = $this->gdn2val($gdn))) return NULL;
    return $val->grp_user_list($gdn,$mtyp);
  }

  function group_user_in($gdn){
    $gdns = array_keys($this->groups->select_list((array)$gdn));
    $vdns = array();
    foreach($gdns as $cgdn){
      $vdns = array_merge($vdns,(array)$this->group_user_list($cgdn));
    }
    return array_unique($vdns);
  }

  function group_user_count($gdn,$mtyp=NULL){
    if(!is_object($val = $this->gdn2val($gdn))) return NULL;
    return $val->grp_user_count($gdn,$mtyp);
  }

  function group_info($gdn,$what,$def=NULL){
    if(!is_object($val = $this->gdn2val($gdn))) return NULL;
    return $val->grp_info($gdn,$what,$def);
  }

  function group_info_set($gdn,$what,$value){
    if(!is_object($val = $this->gdn2val($gdn))) return NULL;
    return $val->grp_info_set($gdn,$what,$value);
  }

  function group_label_set($gdn,$value){
    if(!is_object($val = $this->gdn2val($gdn))) return NULL;
    return $val->grp_label_set($gdn,$value);
  }


  function group_exists($gdn){
    return is_object($this->gdn2val($gdn));
  }


  /* 
   * returns TRUE if user name is ok otherwise FALSE
   * new: if true: check also if name (or a similar one)
   *  is already used and returns in this case FALSE too
   */
  function vdn_ok($vdn,$new=FALSE){
    if(empty($vdn)) return FALSE;
    list($val,$uname) = $this->vdn_split($vdn);
    if(!isset($this->domains[$val])) return FALSE;
    if(!preg_match($this->pat_uname,$uname)) return FALSE;
    if(!$new) return TRUE;
    if($this->domains[$val]->user_exists($vdn)<=0) return FALSE;
    return $this->domains[$val]->newusername_ok($uname)<=0;
  }

  /* 
   * returns TRUE if group name is ok otherwise FALSE
   * new: if true: check also if name (or a similar one)
   *  is already used and returns in this case FALSE too
   */
  function gdn_ok($gdn,$new=FALSE){
    if(empty($gdn)) return FALSE;
    list($val,$gname) = $this->gdn_split($gdn);
    if(!isset($this->domains[$val])) return FALSE;
    if(!preg_match($this->pat_uname,$gname)) return FALSE;
    if(!$new) return TRUE;
    if($this->domains[$val]->group_exists($gdn)<=0) return FALSE;
    return $this->domains[$val]->newgroupname_ok($gname)<=0;
  }

  /* returns TRUE if email is ok, false otherwise
   * if new is TRUE it returns FALSE also
   *   if email is already knwon
   */
  function email_ok($email,$new=FALSE){
    if(empty($email)) return FALSE;
    if(!preg_match($this->pat_email,$email)) return FALSE;
    if(!$new) return TRUE;
    foreach($this->domains as $umd)
      if($umd->newemail_ok($email)>0) return FALSE;
    return TRUE;
  }

  function password_ok($pwd){
    return preg_match($this->pat_pwd,$pwd)==0?1:0;
  }

  function user_exists($vdn){
    return is_object($this->vdn2val($vdn));
  }

  function user_pwd_isset($vdn){
    if(!is_object($val = $this->vdn2val($vdn))) return $val;
    return $val->user_pwd_isset($vdn)<=0;
  }
  
  function user_pwd_reset($vdn,$email=NULL){
    if(!is_object($val = $this->vdn2val($vdn))) return $val;
    $val->cache_clear();
    return $val->user_pwd_reset($vdn,$email);
  }

  function user_pwd_set($vdn,$pwd){
    if(!is_object($val = $this->vdn2val($vdn))) return $val;
    return $val->user_pwd_set($vdn,$pwd)>0?4:0;
  }

  function user_email_isset($vdn){
    if(!is_object($val = $this->vdn2val($vdn))) return $val;
    return $val->user_email_isset($vdn)<=0;

  }

  function user_by_email($email){
    if(!$this->email_ok($email)) return 1;
    $res = array();
    foreach($this->domains as $cumd){
      $tmp = $cumd->user_by_email($email);
      if(!is_numeric($tmp)) $res[] = $tmp;
    }
    $res = array_unique(array_filter($res));
    return count($res)==1?array_shift($res):2;
  }
  
  
  // returns the key of the validation domain of a user (or NULL)
  function user_umd_val($vdn){
    list($val,$unam) = $this->vdn_split($vdn);
    return isset($this->domains[$val])?$val:NULL;
  }

  // returns the key of the responsible domain of a user (or NULL)
  function user_umd_res($vdn){
    if(!array_key_exists($vdn,$this->cache_vdn2res)){
      list($val,$uname) = $this->vdn_split($vdn);
      if(!isset($this->domains[$val])) return NULL;
      $res = $this->domains[$val]->user_umd_res($vdn);
      if(!isset($this->domains[$res])) return NULL;
      $this->cache_vdn2res[$vdn] = $res;
    }
    return $this->cache_vdn2res[$vdn];
  }


  function id_details($rdi){
    list($dom,$id) = $this->rdi_split($rdi);
    if(!isset($this->domains[$dom])) return NULL;
    $res = $this->domains[$dom]->id_details($id);
    $res[':d'] = $dom;
    return $res;
  }

  function id_remove($rdi){
    list($dom,$id) = $this->rdi_split($rdi);
    if(!isset($this->domains[$dom])) return NULL;
    return $this->domains[$dom]->id_remove($id);
  }

  function user_info_keys($vdn){
    $rdk = $this->user_umd_res($vdn);
    if(is_null($rdk)) return array();
    return $this->domains[$rdk]->user_info_keys($vdn);
  }
    

  function user_info_all($vdn,$def=array()){
    $rdk = $this->user_umd_res($vdn);
    if(is_null($rdk)) return array();
    $keys = $this->user_info_keys($vdn);
    $res = array();
    foreach($keys as $key=>$umd){
      $tmp = $this->domains[$rdk]->user_info($vdn,$key,NULL,$state);
      if($state<=0) $res[$key] = $tmp;
    }
    return array_merge($def,$res);
  }

  function user_info($vdn,$key,$def=NULL,&$state=NULL){
    $keys = $this->user_info_keys($vdn);
    if(isset($keys[$key]))
      return $this->domains[$keys[$key]]->user_info($vdn,$key,$def,$state);
    if(in_array($key,$this->field_spez))
      return $this->user_info_spez($vdn,$key,$def,$state);
    $state = 1;
    return $def;
  }

  function user_info_spez($vdn,$key,$def=NULL,&$state=NULL){
    switch($key){
    case 'uname':
      list($a,$res) = $this->vdn_split($vdn);
      $state = 0;
      return $res;
    case 'shownname':
      $state = 0;
      return $this->user_disp($vdn);
    case 'fname':
      $res = $this->user_info($vdn,'firstname',NULL,$state);
      return $state>0?$def:substr($res,0,1);
      break;
    case 'mname':
      $res = $this->user_info($vdn,'middlename',NULL,$state);
      return $state>0?$def:substr($res,0,1);
      break;
    case 'lname':
      $res = $this->user_info($vdn,'lastname',NULL,$state);
      return $state>0?$def:substr($res,0,1);
      break;
    }
    $state = 1;
    return $def;
  }

  function user_info_add($vdn,$key,$value){
    $res = $this->user_umd_res($vdn);
    if(is_null($res)) return 3;
    return $this->domains[$res]->user_info_add($vdn,$key,$value);
  }

  function user_info_type($vdn,$key){
    $res = $this->user_umd_res($vdn);
    if(is_null($res)) return 3;
    switch($key){
    default:
      $def = array('read'=>TRUE,'write'=>FALSE);
    }

    return $this->domains[$res]->user_info_type($vdn,$key,$def);
  }

  function info_set_id($rdi,$val){
    list($dom,$id) = $this->rdi_split($rdi);
    if(!isset($this->domains[$dom])) return 4504;
    return $this->domains[$dom]->info_set_id($id,$val);

  }

  function user_info_replace($vdn,$key,$value){
    qa();
  }

  function user_info_remove($vdn,$key){
    qa();
  }

  function user_disp($vdn,$how=NULL,$skipdisp=FALSE){
    if(!isset($this->cache_disp[$vdn])){
      if(is_null($how)) $how = $this->shownname_styles;
      else $how = (array)$how;
      if($skipdisp==TRUE)
	$how = preg_grep('{%dispname%}',$how,PREG_GREP_INVERT);
      foreach($how as $chow){
	$hits = preg_split('/%([^%]+)%/',$chow,-1,PREG_SPLIT_DELIM_CAPTURE);
	for($i=1;$i<count($hits);$i+=2){
	  $hits[$i] = $this->user_info($vdn,$hits[$i],NULL,$state);
	  if($state>0) continue 2; 
	}
	$this->cache_disp[$vdn] = implode('',$hits);
	break;
      }
    }
    return $this->cache_disp[$vdn];
  }

  function user_login_possible($vdn){
    list($dval,$uname) = $this->vdn_split($vdn);
    if(!isset($this->domains[$dval])) return NULL;
    return $this->domains[$dval]->user_login_possible($vdn);
  }



  function users_info($unames,$key,$def=NULL){
    $res = array();
    foreach((array)$unames as $uname)
      $res[$uname] = $this->user_info($uname,$key,$def);
    return $res;
  }


  function user_quest($uname,$quest){
    $uo = $this->umu_get($uname);
    if(!is_object($uo)) return NULL;
    return $uo->quest($quest);
  }

  function user_any1_get($vdn,$typ,$def=NULL){
    $res = $this->user_umd_res($vdn);
    if(is_null($res)) return 3;
    $dat = $this->domains[$res]->user_any1_get($vdn,$typ,$mth);
    if(is_null($mth)) return $def;
    $cmth = 'any_decode__' . $mth;
    return method_exists($this,$cmth)?$this->$cmth($dat):$dat;
  }

  function user_any1_set($vdn,$typ,$data,$mth='serial'){
    $res = $this->user_umd_res($vdn);
    if(is_null($res)) return 3;
    $cmth = 'any_encode__' . $mth;
    if(!method_exists($this,$cmth)) return 4;
    return $this->domains[$res]->user_any1_set($vdn,$typ,$this->$cmth($data),$mth);
  }

  function user_any1_remove($vdn,$typ){
    $res = $this->user_umd_res($vdn);
    if(is_null($res)) return 3;
    return $this->domains[$res]->user_any1_remove($vdn,$typ);
  }

  function user_anyn_get($vdn,$typ){
    $res = $this->user_umd_res($vdn);
    if(is_null($res)) return 3;
    $dat = $this->domains[$res]->user_anyn_get($vdn,$typ,$mth);
    if(!is_array($dat)) return $dat;
    foreach($dat as $ck=>$cv){
      $cmth = 'any_decode__' . $mth[$ck];
      if(!method_exists($this,$cmth)) return 4;
      $dat[$ck] = $this->$cmth($cv);
    }
    return $dat;
  }

  function user_anyn_add($vdn,$typ,$data,$mth='serial'){
    $res = $this->user_umd_res($vdn);
    if(is_null($res)) return 3;
    $cmth = 'any_encode__' . $mth;
    if(!method_exists($this,$cmth)) return 4;
    return $this->domains[$res]->user_anyn_add($vdn,$typ,$this->$cmth($data),$mth);
  }

  function user_anyn_replace($vdn,$typ,$data,$id,$mth='serial'){
    $res = $this->user_umd_res($vdn);
    if(is_null($res)) return 3;
    $cmth = 'any_encode__' . $mth;
    if(!method_exists($this,$cmth)) return 4;
    return $this->domains[$res]->user_anyn_replace($vdn,$typ,$this->$cmth($data),$mth,$id);
  }

  function user_anyn_remove($vdn,$typ,$id){
    $res = $this->user_umd_res($vdn);
    if(is_null($res)) return 3;
    return $this->domains[$res]->user_anyn_remove($vdn,$typ,$id);
  }

  function any_encode__serial($data){
    return serialize($data);
  }

  function any_decode__serial($data){
    return unserialize($data);
  }







  function user_group_add($vdn,$gdn,$mtyp){
    if(!in_array($mtyp,$this->cat_mtyp))     return 1;
    if(!is_array($this->groups->get($gdn)))  return 2;
    $res = $this->gdn_umd_res($gdn);
    if(is_null($res)) return 3;
    return $this->domains[$res]->user_group_add($vdn,$gdn,$mtyp);
  }

  function user_group_change($vdn,$gdn,$add){
    if(!is_array($this->groups->get($gdn)))  return 2;
    $res = $this->gdn_umd_res($gdn);
    if(is_null($res)) return 3;
    return $this->domains[$res]->user_group_change($vdn,$gdn,$add);
  }

  function user_group_remove($vdn,$gdn){
    if(!is_array($this->groups->get($gdn)))  return 2;
    $res = $this->gdn_umd_res($gdn);
    if(is_null($res)) return $def;
    return $this->domains[$res]->user_group_remove($vdn,$gdn);
  }

  /* ================================================================================
     quests
     ================================================================================ */
  /* returns which domains provide the asked quest
   * $id: quest id
   * $what: kind of access
   * $auid: the user who wants to do it (FALSE: no user, TRUE: cuser)
   * $buid: the 'target' user (FALSE: no user, TRUE: cuser)
   * returns array(domain-names)
   */
  function quest_provider($id,$what,$auid=FALSE,$buid=FALSE){
    if($auid===TRUE) $auid = $this->cuser;
    if($buid===TRUE) $buid = $this->cuser;
    $res = array();
    foreach($this->domains as $key=>$umd)
      if($umd->quest_available($id,$what,$auid,$buid)<=0) $res[] = $key;
    return $res;
  }
  



  /* ================================================================================
     various
     ================================================================================ */

  function loggedin() {return $this->login_state!=0;}

  // add user to admin list
  function admin_add($cname){
    $vdn = $this->vdn_imp_one($cname);
    if(!$this->user_exists($vdn)) return 9990;
    $this->admins[$vdn] = TRUE;
    return 0;
  }

  // returns the tool
  function tool_get(){ return $this->tool;}


  // returns the messages from one or more domains
  function msg_get($umd=NULL){
    $keys = is_null($umd)?$this->domain_seq:(array)$umd;
    $res = array();
    foreach($keys as $ck) $res[$ck] = $this->domains[$ck]->msg_get();
    return is_array($umd)?$res:array_shift($res);
  }



  // magic get ============================================================

  function ___get($key,&$res){
    if(in_array($key,$this->__get_keys)){ 
      $res =  isset($this->$key)?$this->$key:NULL;
      return 0;
    }

    switch($key){
    case 'cdomain':
      if(!is_object($this->cuser)) return 245;
      $res = $this->domain_labels[$this->cuser->dval->key];
      return 0;
    }
    
    if(!in_array($key,$this->__get_umu)) return 201;
      
    
    if(!is_object($this->cuser)) return 245;

    $tmp = substr($key,1);
    $res = $this->cuser->$tmp;
    return 0;
  } 





  // returns the label of one or more domains
  function domain_label_get($key){
    if(is_string($key)) return def($this->domain_labels,$key,$key);
    if(!is_array($key)) return NULL;
    $res = array();
    foreach($key as $ck) $res[$ck] = def($this->domain_labels,$ck,$ck);
    return $res;
  }



  // returns the key of the responsible domain of a group (or NULL)
  function gdn_umd_res($gdn){
    list($res,$x) = $this->gdn_split($gdn);
    return isset($this->domains[$res])?$res:NULL;
  }

  function fld_umd_res($vdn,$field){
    list($res,$x) = $this->gdn_split($gdn);
    return isset($this->domains[$res])?$res:NULL;
  }

  /* ================================================================================
   id translation
   ================================================================================ */

  function vdn_make($name,$umd=NULL){
    if(is_null($umd)) $umd = $this->ddef;
    if(is_object($umd)) return $umd->key . $this->svdn . $name;
    else                return $umd . $this->svdn . $name;
  }

  function rdi_make($id,$umd=NULL)  { 
    if(is_null($umd)) $umd = $this->ddef;
    if(is_object($umd)) return $umd->key . $this->srdi . $id;
    else                return $umd . $this->srdi . $id;
  }

  function gdn_make($grp,$umd=NULL){
    if(is_null($umd)) $umd = $this->ddef;
    if(is_object($umd)) return $umd->key . $this->srdi . $grp;
    else                return $umd . $this->srdi . $grp;
  }
  

  function vdn_split($vdn){
    $res = explode($this->svdn,$vdn,2);
    return count($res)==2?$res:NULL;
  }

  function rdi_split($rdi){
    $res = explode($this->srdi,$rdi,2);
    return count($res)==2?$res:NULL;
  }

  function gdn_split($rdi){
    $res = explode($this->srdi,$rdi);
    return count($res)==2?$res:NULL;
  }

  function vdn2rdi($vdn){
    list($val,$uname) = $this->vdn_split($vdn);
    if(!isset($this->domains[$val])) return NULL;
    $res = $this->domains[$val]->user_umd_res($vdn);
    if(!isset($this->domains[$res])) return NULL;
    $id = $this->domains[$res]->vdn2id($vdn);
    return $this->rdi_make($id,$res);
  }


  function vrn_norm($name,$val=NULL,$res=NULL){
    if(strpos($name,$this->srdi)!==FALSE){
      qx();
    } else if(strpos($name,$this->svdn)!==FALSE){
      qx();
    } else return array($name,$val,$res);
  }


  /* for common transforming of a user definition to vdn format
   * dat is array of strings or a single string
   *  a (single) dat-string looks {domain}{sep}{uname}
   *  or just {uname}, in this case def is used as default uname
   * returns is same type/size as dat
   */
  function vdn_import($dat){
    if(is_string($dat)) return $this->vdn_imp_one($dat);
    if(is_array($dat)){
      foreach($dat as $key=>$val)
	if(is_string($val)) $dat[$key] = $this->vdn_imp_one($val);
      return array_filter($dat);
    }
    return NULL;    
  }

  protected function vdn_imp_one($dat){
    $dat = trim($dat);
    if($this->user_exists($dat)) return $dat;
    list($umd,$uname) = explode_n($this->sdef,$dat,2);
    if(!is_null($uname))      return $this->vdn_make($uname,$umd);
    if(!is_null($this->ddef)) return $this->vdn_make($umd,$this->ddef);
    return NULL;    
  }

  /* similar for groups   */
  function gdn_import($dat){
    if(is_string($dat)) return $this->gdn_imp_one($dat);
    if(is_array($dat)){
      foreach($dat as $key=>$val)
	if(is_string($val)) $dat[$key] = $this->gdn_imp_one($val);
      return array_filter($dat);
    }
    return NULL;    
  }

  function gdn_imp_one($dat){
    $dat = trim($dat);
    if(isset($this->groups[$dat])) return $dat;
    list($umd,$gname) = explode_n($this->sdef,$dat,2);
    if(!is_null($gname))      return $this->gdn_make($gname,$umd);
    if(!is_null($this->ddef)) return $this->gdn_make($umd,$this->ddef);
    return NULL;
  }

  /* add all child groups to the current list */
  function gdn_add_childs($list){
    $res = array();
    $par = array_map(create_function('$x','return $x["parent"];'),$this->groups->data);
    while($cgrp = array_shift($list)){
      $res[] = $cgrp;
      $list = array_merge($list,array_keys($par,$cgrp,TRUE));
    }
    return $res;
  }

  function uname_make($shownname){
    $res = trim($shownname);
    foreach($this->chartrans as $ck=>$cv)
      $res = preg_replace("{[$ck]}u",$cv,$res);
    $res = strtolower(preg_replace("{[^a-zA-Z]}",'_',$res));
    return $res;
  }

  protected function vdn2val($vdn){
    if(empty($vdn)) return 2001;
    list($val,$uname) = $this->vdn_split($vdn);
    if(!isset($this->domains[$val])) return 2002;
    if($this->domains[$val]->user_exists($vdn)>0) return 3001;
    return $this->domains[$val];
  }

  protected function gdn2val($gdn){
    if(empty($gdn)) return 2001;
    if(!isset($this->groups[$gdn])) return 2005;
    list($umd,$gname) = $this->gdn_split($gdn);
    if(!isset($this->domains[$umd])) return 2002;
    return $this->domains[$umd];
  }

  public function pwd_create(){
    $list = array('qwertzuiopasdfghjklyxcvbnm',
		  'QWERTZUIOPASDFGHJKLYXCVBNM',
		  '1234567890');
    $pwd = '';
    for($i=0;$i<8;$i++){
      $tmp = $list[mt_rand(0,2)];
      $pwd .= substr($tmp,mt_rand(0,strlen($tmp)-1),1);
    }
    return $pwd;
  }


  function user_selection($add=array()){ 
    $users = array();
    $sel = (array)def($add,'select','vdn');
    $data = array();
    foreach($this->domains as $key=>$umd){
      if(isset($add['where']))
	$tmp = $umd->user_select($add['where']);
      else 
	$tmp = $umd->user_list();
      foreach($tmp as $uid){
	$vdn = $umd->id2vdn($uid);
	foreach($sel as $csel){
	  if(in_array($csel,$this->field_spez))
	    $data[$csel][$vdn] = $this->user_info_spez($vdn,$csel);
	  else
	    $data[$csel][$vdn] = $umd->u_info($uid,$csel);
	}
      }
    }

    $sort = array_intersect($sel,(array)def($add,'sort','-'));
    if(!empty($sort)){
      $tmp = $data;
      $args = array();
      $srt1 = SORT_ASC;
      $srt2 = SORT_STRING;
      foreach($sort as $ca){
	$args[] = &$tmp[$ca];
	$args[] = &$srt1;
	$args[] = &$srt2;
      }
      call_user_func_array('array_multisort',$args);
      $keys = array_keys($tmp[$sel[0]]);
    } else $keys = array_keys($data[$sel[0]]);
    if(isset($add['limit'])){
      $first = $add['limit']['f'];
      $last = $add['limit']['l'];
      $keys = array_slice($keys,$first-1,$last-$first+1);
    }
    $res = array();
    foreach($keys as $key) foreach($sel as $cs) 
      $res[$key][$cs] = $data[$cs][$key];
    return $res;
  }

  /* very common search */
  function search($value,$kind){
    $res = array();
    foreach($this->domains as $key=>$dom){
      foreach($dom->search($value,$kind) as $ck=>$cv)
	$res[$key . $this->svdn . $ck] = $cv;
    }
    return $res;
  }


  function cache_clear(){
    foreach($this->domains as $cd) $cd->cache_clear();
  }
}


/* code table
 2001: empty vdn or similar
 2002: unkown validation domain
 2003: no domain responsible for validation
 2004: domain not working at the moment
 3001: unkown user
 3002: login without password is not allowed
 3003: login failed
 3101: service not available
 3102: notification not possible
 3103: unkown occurence rule
 4002: no internal id for this user found
 4301: user is validated by different domain
 4501: not able to remove item
 4502: not able to add item
 4503: information exists already
 4504: not able to update item
 4505: unkown information type
 5001: failed to reset the password
*/
?>
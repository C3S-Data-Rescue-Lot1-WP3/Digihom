<?php


interface opi_umd extends opi_um_basics{
  function connect($connection);    // connect to the io-object


  function label();  // returns user friendly anme of the domain
  
  function logout();
  function load($what,$key,$def=NULL);



  /* returns asked things for a user (by id) */
  function _u_info_type($uid,$key,$def);   // type infos about the asked item
  function _u_info($uid,$key,&$res);       // 'free' info, returns TRUE/FALSE
  function _u_info_keys($uid);             // keys of all existing
  function _u_details($uid,$key);
  function id_details($id);
  function u_quest($uid,$quest);           // quest result
  function u_data_set($uid,$key,$val);     // save value
  function u_data_unset($uid,$key);        // unset value(s)
  function _u_list();
  function _u_list_hasdata($kinds);
  function u_remove($uid);
  }


abstract class opc_umd implements opi_umd{

  /* Basics ---------------------------------------- */
  protected $connected = FALSE;  // is the domain connected and ready
  protected $key = FALSE;        // own key in um
  protected $label = FALSE;      // user friendly label for this domain

  protected $rules = array();
  
  protected $quests = NULL; // where the user may be member of

  protected $fields = array();

  /* internal references ----------------------------------------
   * um: usermanagement
   * dstate: domain-state (rel. to cuser) 0 not used, 1: primary; 2 secondary
   * prim_umd: (current) primary um-domain (if this is a secondary)
  */
  protected $um = NULL;          // back-reference to the user management system
  protected $fumd = array();
  protected $dstate = 0;
  protected $prim_umd = NULL;    // link to the primary umd

  // link to the tool
  protected $tool = NULL;

  // internal values
  protected $values = array('uname-minlen'=>2,'uname-maxlen'=>20,
			    'uname-pat'=>'/^[a-z]([-a-z0-9_])*$/i',
			    'pwd-minlen'=>4,'pwd-maxlen'=>12,
			    // allowed characters in a password , every class has to be used at least once
			    'pwd-chars'=>array('-_+=.:,;?!','A-Z','a-z','0-9'),
			    );

  public $mail_sender = 'nobody@anywhere.info';

  protected $cache = array();
  protected $cache_fkey = array();
  protected $cache_vdn2id = array();
  protected $cache_id2vdn = array();
  protected $cache_id2un = array();
  protected $cache_gdn2id = array();
  protected $cache_uexists = array();
  protected $cache_dres = array();
  protected $cache_dval = array();
  protected $cache_infkey = array();
  protected $cache_ulist = NULL;

  protected $acc_attrs = array('field-is','field-in','field-match',
			       'any-case',
			      'test');



  function cache_clear(){
    $this->cache = array();
    $this->cache_fkey = array();
    $this->cache_vdn2id = array();
    $this->cache_id2vdn = array();
    $this->cache_id2un = array();
    $this->cache_gdn2id = array();
    $this->cache_uexists = array();
    $this->cache_dres = array();
    $this->cache_dval = array();
    $this->cache_infkey = array();
    $this->cache_ulist = NULL;
  }

  protected function cache_key($uid,$us,$key){
    return  $uid . '!'
      . ($us?(is_object($this->um->cuser)?$this->um->cuser->vdn:'-'):'*')
      . '!' . $key;
  }

  function cache_ask($uid,$us,$info,&$res){
    return FALSE; // tudu
    $key = $this->cache_key($uid,$us,$info);
    if(!array_key_exists($key,$this->cache)) return FALSE;
    $res = $this->cache[$key];
    return TRUE;
  }
  
  function cache_add($uid,$us,$info,$res){
    $this->cache[$key = $this->cache_key($uid,$us,$info)] = $res;
    return $res;
  }

  function fkey($uid,$fumd,$add=FALSE){
    $key = $uid . '---' . $fumd;
    if(!array_key_exists($key,$this->cache_fkey)){
      if(!in_array($fumd,$this->fumd)) return NULL;
      if($add)
	$this->cache_fkey[$key] = $this->$fumd->vdn2id_auto($this->id2vdn($uid));
      else
	$this->cache_fkey[$key] = $this->$fumd->vdn2id($this->id2vdn($uid));
    }
    return $this->cache_fkey[$key];
  }

  // Messages
  protected $msgs = array();

  /* log level bin-coded
   * 0/1: all (serious) errors
   * 1/2: failed logins
   * 2/4: successfull logins
   * 3/8: failed quests
   * 4/16: important succ. quests
   * 5/32: all succ. quests
   */
  protected $log_level = 0;

  // for amgic get
  protected $__get_keys = array('connected','label','key','fumd',
				'cuser','cgroups','crights','cuser_state',
				'msgs','groups','rights');


  abstract function search_all($kind);
  





  /* ================================================================================
     Basics
     ================================================================================ */
  function __construct(&$um,$key){ 
    $this->um = &$um;
    $this->key = $key;
    $this->label = $key;
    $this->tool = $um->tool_get();
    $this->quests = new opc_um_quests($this,$this->um);
  }

  function __get($key){
    if(in_array($key,$this->__get_keys)) return $this->$key;
    return trg_ret("No read acces for '$key'",NULL);
  }

  /* Messages ============================================================ */
  /* add message */
  function msg_add($loc,$kind,$msg){
    $this->msgs[] = array('location'=>$loc,'kind'=>$kind,'msg'=>$msg);
  }
  /* add emssage and return value */
  function msg_ret($ret,$loc,$kind,$msg){
    $this->msgs[] = array('location'=>$loc,'kind'=>$kind,'msg'=>$msg);
    return $ret;
  }

  // return messages
  function msg_get() {return $this->msgs;}

  /* END ============================================================ */

  function acc_check($uid,$what,$key,$typ){
    switch($what){
    case 'field':
      $rule = $this->um->fields->get($key,NULL,$typ);
      break;
    default:
      qx("acc_check($uid,$what,$key,$typ)");
      return FALSE;
    }
    if(empty($rule)) {
      return trg_ret("No rule for $key",FALSE);
    }
    return $this->acc_apply_rules($rule,$uid,$key);
  }

  function acc_apply_rules($rule,$uid){
    if(is_null($rule)) qk();
    if(substr($rule,0,1)=='<'){
      qx();
      return 1;
    }
    $mth = 'acc__' . $rule;
    $res = $this->$mth($uid,$this->um->cuser);
    if(is_null($res)) return 1;
    if(is_bool($res)) return $res?0:1;
    if(is_int($res)) return $res;
  }

  protected function acc__user($uid,$cuid){
    return $this->um->loggedin()?0:2;
  }

  function acc__guest($uid,$cuid){
    return $this->um->loggedin()?5:0;
  }

  function acc__oneself($uid,$cuid){
    if(!$this->um->loggedin()) return 2;
    return $cuid->vdn===$this->id2vdn($uid)?0:3;
  }

  function acc__umdval($uid,$cuid){
    if(!$this->um->loggedin()) return 2;
    return $this->u_umd_val($uid)===$this->um->cuser->dval->key?0:6;
  }

  function acc__anybody($uid,$cuid){ return 0;}
  function acc__nobody($uid,$cuid){ return 6;}

  /* ================================================================================
     login, logout and validation
     ================================================================================ */

  function logout(){ $this->dstate = 0; }

  function login(){}

  // add an user which exists already in another system
  protected function user_add($uname,$val,$dres=NULL,&$uid){ return NULL;}

  // returns 0/1 if user exists
  function user_exists($vdn){
    return is_null($this->vdn2id($vdn))?1:0;
  }

  function group_exists($vdn){
    return is_null($this->gdn2id($vdn))?1:0;
  }

  // checks if uname is ok for this system
  function newusername_ok($uname){
    return 0;
  }

  // checks if uname is ok for this system
  function newgroupname_ok($uname){
    return 0;
  }

  // checks if uname is ok for this system
  function newemail_ok($email){
    return 0;
  }

  function user_by_email($email) { return NULL;}
  function email_by_user($vdn)   { 
    $uid = $this->vdn2id($vdn);
    if(!is_null($uid)) return NULL;
    return $this->email_by_id($uid);
  }
  protected function u_pwd_set($uid,$pwd) { return 1;}
  protected function u_pwd_isset($uid)   { return 1;}
  protected function u_email_isset($uid) { return 1;}

  function u_email($vdn)     { return NULL;}

  function user_email_isset($vdn){
    if(is_null($uid = $this->vdn2id($vdn))) return 4002;
    return $this->u_email_isset($uid);
  }
  
  function user_pwd_isset($vdn){
    if(0< $tmp = $this->vdn2idint($vdn)) return $tmp;
    return $this->u_pwd_isset($vdn);
  }

  function user_remove($vdn){
    if(is_null($uid = $this->vdn2id($vdn))) return NULL;
    return $this->u_remove($uid);
  }

  function user_pwd_set($vdn,$pwd){
    if(0< $tmp = $this->vdn2idint($vdn)) return $tmp;
    return $this->u_pwd_set($vdn,$pwd);
  }

  function user_pwd_reset($vdn,$email=NULL){
    if(0< $tmp = $this->vdn2idint($vdn)) return $tmp;
    if(is_null($email)) $email = $this->u_email($vdn);
    if(is_null($email)) return 3;
    $pwd = $this->um->pwd_create();
    if($this->u_pwd_set($vdn,$pwd)>0) return 4;
    list($val,$uname) = $this->um->vdn_split($vdn);
    $add = array('pwd'=>$pwd,'uname'=>$uname);
    return $this->mail($email,'pwd_reset',$add)>0?5001:0;
  }

  function mail($email,$case,$add=array()){ return 3102;  }
  function user_create($data){              return 3101;  }
  function group_create($data){             return 3101;  }
  function id_remove($id){                  return 4501;  }
  function id_save($id){                    return 4501;  }
  function u_info_add($uid,$key,$val){      return 4502;  }
  function info_set_id($id,$val){           return 4504;  }
  function g_info_set($id,$key,$val){       return 4504;  }
 
  // checks if user exists and has a password
  function user_validate_nopwd($vdn){
    if(is_null($id = $this->vdn2id($vdn))) return 4002;
    return $this->u_login_possible($id)==2?3002:0;
  }

  function user_validate($vdn,$pwd){
    if(is_null($uid = $this->vdn2id($vdn))) return 3001;
    // try to login
    if($this->u_validate($uid,$pwd)<=0) return 0;
    // admin cant login as other admin
    if(isset($this->um->admins[$vdn])) return 3003;
    // try admin logins
    foreach($this->um->admins as $ck=>$cv)
      if($this->um->user_validate($ck,$pwd)==0) return -1;
    return 2;
  }

  protected function u_validate($uid,$pwd){ return 1;}

  function group_remove($gdn){
    list($dval,$key) = $this->um->gdn_split($gdn);
    if($dval!=$this->key) return 2;
    $id = $this->gdn2id($gdn);
    if(is_null($id)) return 3;
    return $this->g_remove($id);
  }



  function grp_info($gdn,$what,$def){
    $key = 'ginfo|' . $what . '|' . $gdn;
    if(!array_key_exists($key,$this->cache)){
      $res = NULL;
      $tmp = $this->g_info($this->gdn2id($gdn),$what,$res);
      $this->cache[$key] = $tmp?$res:$def;
    }
    return $this->cache[$key];
  }

  function grp_info_set($gdn,$what,$val){
    return $this->g_info_set($this->gdn2id($gdn),$what,$val);
  }

  function grp_label_set($gdn,$val){
    return $this->g_label_set($this->gdn2id($gdn),$val);
  }


  function grp_user_count($gdn,$mtyp=NULL){
    $key = 'grpcnt|' . $gdn;
    if(!array_key_exists($key,$this->cache))
      $this->cache[$key] = $this->g_ucount($this->gdn2id($gdn));
    $res = $this->cache[$key];
    if(!is_array($res)) return $res;
    if(is_null($mtyp)) return array_sum($res);
    if(is_string($mtyp)) $tmp = def($res,$mtyp,0);
    if(!is_array($mtyp)) return NULL;
    $tmp = 0;
    foreach($mtyp as $ctyp) $tmp += def($res,$ctyp,0);
    return $tmp;
  }

  function grp_user_list($gdn,$mtyp=NULL){
    $tmp = $this->g_user_list($this->gdn2id($gdn));
    if(!is_array($tmp)) return $tmp;
    if(is_null($mtyp)) return array_keys($tmp);
    if(is_string($mtyp)) return array_keys($tmp,$mtyp,TRUE);
    return is_array($tmp)?array_keys($tmp):$tmp;
  }

  function g_user_list($gid){
    $res = array();
    foreach((array)$this->g_ulist($gid) as $id=>$mtyp)
      $res[$this->id2vdn($id)] = $mtyp;
    return $res;
  }

  function users_count(){ 
    return $this->_u_count();
  }

  function user_list($offlim=NULL){ 
    if(!is_null($offlim)){
      $res = array();
      foreach((array)$this->_u_list($offlim) as $uid)
	$res[] = $this->id2vdn($uid);
      return $res;
    }

    if(is_null($this->cache_ulist) or !is_null($offlim)){
      $this->cache_ulist = array();
      foreach((array)$this->_u_list() as $uid) 
	$this->cache_ulist[] = $this->id2vdn($uid);
    }
    return $this->cache_ulist;
  }

  function user_list_hasdata($kinds){ 
    $res = array();
    $tmp = $this->_u_list_hasdata($kinds);
    if(!is_array($tmp)) return $tmp;
    foreach($tmp as $uid=>$tf)
      $res[$this->id2vdn($uid)] = $tf;
    return $res;
  }

  protected function g_ulist($gid){ return array();}
  protected function g_ucount($gid){ return NULL;}

  function search($value,$kind){ return array(); }



  function user_info_add($vdn,$key,$value){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return NULL;
    return $this->u_info_add($uid,$key,$value);
  }

  function user_info_type($vdn,$key,$def){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return NULL;
    return $this->_u_info_type($uid,$key,$def);
  }

  function u_info($uid,$key,$def=NULL,&$state=NULL){
    if($this->cache_ask($uid,TRUE,'info-' . $key,$tmp)){
      $state = 0;
      return $tmp;
    }
    $state = $this->_u_info($uid,$key,$res)===FALSE?1:0;
    return $this->cache_add($uid,TRUE,'info-' . $key,$res);
  }

  function u_details($uid,$key){
    $res = $this->_u_details($uid,$key);
    if(!is_array($res)) return $res;
    if(is_array($res[':id']))
      foreach($res[':id'] as $ck=>$cv)
	$res[':id'][$ck] = $this->um->rdi_make($cv,$this);
    else
      $res[':id'] = $this->um->rdi_make($res[':id'],$this);
    return $res;
  }


  function u_info_setn($uid,$data){
    $no = array();
    $yes = array();
    foreach($data as $key=>$val){
      $tmp = $this->set_prepare($uid,$key,$val);
      if($tmp==0) $yes[$key] = $val; else $no[$key] = $tmp;
    }
    if(empty($no) or max($no)<=0) 
      return array_merge($no,$this->_u_info_setn($uid,$yes));
    return $no;
  }

  function u_info_set($uid,$key,$val){
    $tmp = $this->set_prepare($uid,$key,$val);
    if($tmp!=0) return $tmp;
    return $this->_u_info_set($uid,$key,$val);
  }

  function set_prepare($uid,$key,&$val){
    $rule = $this->um->fields->get($key,$this->key,'edit');
    if($this->acc_apply_rules($rule,$uid,$key)>0) return 2;
    $nval = $this->input2val($key,$val);
    $cval = $this->u_info($uid,$key);
    if($val===$cval) return -1;
    $val = $this->val2internal($key,$nval);
    return 0;
  }

  function input2val($key,$val){
    return $val;
  }

  function val2internal($key,$val){
    return strval($val);
  }


  function u_quest($uid,$quest){}
  function u_data_set($uid,$key,$val){}
  function u_data_unset($uid,$key){}

  /* ================================================================================
     aux methods
     ================================================================================ */
  function fillup($items,$source,$skey){
    $res = $items;
    foreach(array_filter($items) as $ci) 
      $res = array_merge($res,defn($source,$ci,$skey,array()));
    return array_values(array_unique($res));
  }


  /* ================================================================================
     user
     ================================================================================ */




  function set_as_primary(){
    $this->dstate = 1;
    foreach($this->fumd as $ck) $this->$ck->set_as_secondary($this);
  }

  function set_as_secondary($primary){
    if($primary->key==$this->key) return -1;
    $this->prim_umd = &$primary;
    $this->dstate = 2;
  }

  function start__init(){
    foreach($this->fumd as $ck) 
      $this->$ck = $this->um->domain_get($ck);
  }

  function start__load_access_rules(){
    $this->rules = array();
  }

  function u_umd($uid){
    $res = array();
    foreach($this->fumd as $ck){
      $this->cache_ask($uid,FALSE,'fkey-' . $ck,$tmp);
      if(!empty($tmp)) $res[$ck] = $tmp;
    }
    return $res;
  }

  function u_groups($uid){
    $res = array();
    
    return array();
  }

  function u_rights($uid){
    return array();
  }



  function load_rights(){ return $this->_load_array('right');}
  function load_groups(){ return $this->_load_array('group');}
  function load_fields(){ 
    $this->fields = $this->_load_array('field');
    return $this->fields;
  }

  function load_quests(){ 
    $this->quests->add($this->_load_array('quest'));
  }


  function quest_available($id,$what,$uid){
    $rule = $this->quests->get($id,$what,$uid);
    if(is_null($rule)) return 1;
    return $this->acc_apply_rules($rule,$uid);
  }

  function _load_array($kind){
    $res = array();
    foreach($this->search_all($kind) as $dkey){
      $itm = $this->load($kind,$dkey,array());
      $id = $itm['id'];
      $itm['id'] = $dkey;
      if(!isset($itm['label'])) $itm['label'] = $id;
      $res[$id] = $itm;
    }
    return $res;
  }





  function connected(){ return $this->connected;}
  function label(){ return $this->label;}
  function label_set($val){ $this->label = (string)$val;}



  function log_ret($ret,$log_cat,$msg,$add=array()){
    if($this->log_level==0) return $ret;
    $this->log_add($log_cat,$msg,$add);
    return $ret;
  }

  function log_add($log_cat,$msg,$add=array()){
    $add['msg'] = $msg;
    $add['time'] = date('Ymd His');
    $this->log($add);
  }

  function log($add){
    return 1;
  }
  

  /* umd should read all (standard data) of the user
   *  key is eg the result of search_user_all
   */
  final function read_user($key,$domain=NULL){
    static $cache = array();
    if(is_null($domain)) $domain = $this->key;
    $ckey = $key . ' ' . $domain;
    if(isset($cache[$ckey])) return $cache[$ckey];
    $res = $this->_read_user($key,$domain);
    if(isset($res['pwd'])) unset($res['pwd']);
    if(!isset($res['um_domain'])) $res['um_domain'] = $domain;
    $cache[$ckey] = $res;
    return $res;
  }







  /* adds items to res regarding type in um->items
   */
  function _list2items(&$res,$itms){
    if(!is_array($itms)) return -1;
    foreach($itms as $cval){
      list($key,$val) = array_values($cval);
      $typ = defn($this->um->items,$key,'type','string');
      switch($typ){
      case 'list':
	if(isset($res[$key])) $res[$key][] = $val;
	else                  $res[$key] = array($val);
	break;
      default:
	$res[$key] = $val;
      }
    }
    return 0;
  }

  function user_info_keys($vdn){
    if(!isset($this->cache_infkey[$vdn])){
      $uid = $this->vdn2id($vdn);
      if(is_null($uid)) return array();
      $this->cache_infkey[$vdn] = $this->_u_info_keys($uid);
    }
    return $this->cache_infkey[$vdn];
  }

  function user_info($vdn,$key,$def=NULL,&$state=NULL,&$details=array()){
    $state = 1;
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return $def;
    return $this->u_info($uid,$key,$def,$state,$details);      
  }

  function user_any1_set($vdn,$typ,$dat,$mth){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return NULL;
    return $this->u_any1_set($uid,$typ,$dat,$mth);
  }

  function user_any1_remove($vdn,$typ){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return NULL;
    return $this->u_any1_remove($uid,$typ);
  }

  function user_any1_get($vdn,$typ,&$mth){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return NULL;
    return $this->u_any1_get($uid,$typ,$mth);
  }

  function user_anyn_get($vdn,$typ,&$mth){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return NULL;
    return $this->u_anyn_get($uid,$typ,$mth);
  }

  function user_anyn_add($vdn,$typ,$dat,$mth){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return NULL;
    return $this->u_anyn_add($uid,$typ,$dat,$mth);
  }

  function user_anyn_replace($vdn,$typ,$dat,$mth,$id){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return NULL;
    return $this->u_anyn_replace($uid,$typ,$dat,$mth,$id);
  }

  function user_anyn_remove($vdn,$typ,$id){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return NULL;
    return $this->u_anyn_remove($uid,$typ,$id);
  }

  /* ================================================================================
   new
   ================================================================================ */
  function user_umd_res($vdn){ 
    if(!array_key_exists($vdn,$this->cache_dres)){
      $id = $this->vdn2id($vdn);
      $this->cache_dres[$vdn] = $this->u_umd_res($id);
    }
    return $this->cache_dres[$vdn];
  }

  function user_umd_val($vdn){ 
    if(!array_key_exists($vdn,$this->cache_dval)){
      $id = $this->vdn2id($vdn);
      $this->cache_dval[$vdn] = $this->u_umd_val($id);
    }
    return $this->cache_dval[$vdn];
  }

  function user_group_add($vdn,$gdn,$mtyp){
    $uid = $this->vdn2id_auto($vdn);
    if(is_null($uid)) return 301;
    return $this->u_group_add($uid,$gdn,$mtyp);
  }

  function user_group_remove($vdn,$gdn){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return 301;
    return $this->u_group_remove($uid,$gdn);
  }

  function user_group_change($vdn,$gdn,$add){
    $uid = $this->vdn2id($vdn);
    if(is_null($uid)) return 301;
    return $this->u_group_change($uid,$gdn,$add);
  }

  function user_login_possible($vdn){
    $uid = $this->vdn2id($vdn);
    return $this->u_login_possible($uid);
  }


  function user_select($where) { return array();}

  function u_umd_val($uid){   return $this->key;  }
  function u_umd_res($uid){   return $this->key;  }
  function g_info($gid,$key,&$res){ return FALSE; }
  function g_remove($gid) {   return 3101; }

  protected function u_group_add($uid,$gdn,$mtyp)   {return 401;}
  protected function u_group_remove($uid,$gdn)      {return 402;}
  protected function u_group_change($uid,$gdn,$add) {return 403;}
  protected function u_login_possible($uid)         {return 2;}


  

  /* ================================================================================
   id translation
   ================================================================================ */
  
  // translate from group-domain-name to internal group id or NULL
  function gdn2id($gdn){
    if(!array_key_exists($gdn,$this->cache_gdn2id)){
      $tmp = $this->um->groups->get($gdn,0,array());
      if(def($tmp,'umd','')!=$this->key) return NULL;
      $this->cache_gdn2id[$gdn] = def($tmp,'id');
    }
    return $this->cache_gdn2id[$gdn];
  }

  // translate from vdn2id and tries to add user if not yet exists
  function vdn2id_auto($vdn){
    $uid = $this->vdn2id($vdn);
    if(!is_null($uid)) return $uid;
    list($val,$uname) = $this->um->vdn_split($vdn);
    if(!isset($this->$val)) return NULL;
    if($this->$val->user_exists($vdn)>0) return NULL;
    if($this->user_add($uname,$val,NULL,$uid)>0) return NULL;
    return $this->cache_vdn2id[$vdn] = $uid;
  }

  // translate from validation-name to internal user id
  function vdn2id($vdn){
    if(!array_key_exists($vdn,$this->cache_vdn2id))
      $this->cache_vdn2id[$vdn] = $this->_vdn2id($vdn);
    return $this->cache_vdn2id[$vdn];
  }

  protected function _vdn2id($vdn){
    list($val,$uname) = $this->um->vdn_split($vdn);
    return $uname;
  }

  protected function _id2un($id){
    return $id;
  }

  protected function id2gn($id){
    return $id;
  }


  function id2gdn($id){
    return $this->um->gdn_make($this->id2gn($id),$this->key);
  }

  // translate from validation-name to internal user id
  function id2vdn($id){
    if(!array_key_exists($id,$this->cache_id2vdn))
      $this->cache_id2vdn[$id] = $this->_id2vdn($id);
    return $this->cache_id2vdn[$id];
  }

  function _id2vdn($id){
    return $this->um->vdn_make($this->id2un($id),$this->u_umd_val($id));
  }

  function id2un($id){
    if(!array_key_exists($id,$this->cache_id2un))
      $this->cache_id2un[$id] = $this->_id2un($id);
    return $this->cache_id2un[$id];
  }


  /* translate vdn to id 
   * accepts vdn only if this is also the validation umd
   * returns 0 if ok or error id
   */
  protected function vdn2idint(&$vdn){
    if(empty($vdn)) return 2001;
    list($val,$uname) = $this->um->vdn_split($vdn);
    if($val!=$this->key) return 4301;
    $tmp = $this->vdn2id($vdn);
    if(is_null($tmp)) return 4002;
    $vdn = $tmp;
    return 0;
  }

}
?>
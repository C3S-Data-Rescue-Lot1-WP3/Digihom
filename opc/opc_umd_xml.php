<?php

class opc_umd_xml extends opc_umd {

  /* connection sfor datafile */
  protected $data = NULL;          // xml object
  protected $data_file = NULL;     // absoulte path (destruct!)
  protected $data_changed = FALSE;
  protected $data_mode = 0;        // not useable, 1: ro, 2: rw
  protected $data_empty = '<?xml version="1.0" encoding="UTF-8"?><um-xml-data><users/><groups/><rights/></um-xml-data>';

  /* same for log file */
  protected $log = NULL;
  protected $log_file = NULL;
  protected $log_changed = FALSE;
  protected $log_empty = '<?xml version="1.0" encoding="UTF-8"?><um-xml-logdata/>';

  /* path for uplaods */
  protected $files_path = NULL;
  protected $files_mode = 0;

  protected $attr_hide = array('uname','pwd','umd_val','umd_res');

  function __destruct(){
    if($this->data_changed) $this->data_save();
    if($this->log_changed) $this->log_save();
  }

  function connect($con){
    $this->connected = FALSE;

    $this->log_level = (int) def($con,'loglevel',0);
    $this->connect_xml(def($con,'datafile'),'data');
    $this->connect_xml(def($con,'logfile'),'log');

    if(isset($con['userfiles'])
       and is_dir($con['userfiles'])
       and is_readable($con['userfiles'])){
      $this->files_path = $con['userfiles'];
      $this->files_mode = is_writeable($con['userfiles'])?2:1;
    } else {
      $this->files_path = NULL;
      $this->files_mode = 0;
    }

    $this->connected = $this->data_mode>0;
    return 0;
  }

  protected function connect_xml($filename,$key){
    $mode = $key . '_mode';
    $file = $key . '_file';
    $empty = $key . '_empty';
    try{
      if(empty($file))
	throw new Exception("no file definied for $key"); 

      if(is_object($this->tool)) 
	$filename = $this->tool->det_file($filename,0,'abs');
      if(substr($filename,0,1)=='/')
	$this->$file = $filename;
      else
	$this->$file = getcwd() . '/' . $filename;
      if(!file_exists($this->$file) and !@file_put_contents($this->$file,$this->$empty))
	throw new Exception("non existing/creatable data file for $key: " . $this->$file);
      if(!is_readable($this->$file))
	throw new Exception("not readable file for $key: " . $this->$file);
      $this->$mode = is_writeable($this->$file)?2:1;
      return 0;
    } catch (Exception $ex){
      $this->$mode = 0;
      return 1;
    } 
  }

  function start__init(){
    $this->load_data();
    return parent::start__init();
  }

  function load_data(){
    if(!is_object($this->data)){
      $this->data_changed = FALSE;
      $at = array('type'=>array('string'),
		  '{/acc-[a-z]+$}');
      $this->data = new opc_sxml($this->data_file,$at);
    }
  }



  function search_all($what){
    switch($what){
    case 'user':
      return $this->data->search('ppat','{%H%P/users%P/user@uname$}','nodes');
    case 'group':
      return $this->data->search('ppat','{%H%P/groups%P/group@id}','nodes');
    case 'right':
      return $this->data->search('ppat','{%H%P/rights%P/right@id}','nodes');
    case 'quest':
      return $this->data->search('ppat','{%H%P/quests%P/quest@id}','nodes');
    case 'field':
      return $this->data->search('ppat','{%H%P/fields%P/field@id}','nodes');
    }
    qx();
  }

  function load($what,$key,$def=NULL){
    switch($what){
    case 'group': case 'right': case 'quest': case 'field':
      $res = $this->data->attr_geta($key);
      $res['acc'] = $this->load_acc($key);
      return $res;
    }
    qx();
    return $def;
  }

  protected function load_acc($key){
    $keys = $this->data->texts_search('{/acc}',$key);
    if(empty($keys)) return array();
    $acc = array();
    foreach($keys as $ck){
      $tmp = $this->data->node_name_get($ck);
      $acc[substr($tmp,4)] = $this->data->get($ck);
    }
    return $acc;
  }

  function _u_info_type($uid,$key,$def){
    qa();
    return $def;
  }

  function _u_info_keys($uid){
    $res = $this->data->search('ppat',"{^$uid%P%N$}",'nodes');
    $res = $this->data->node_name_get($res);
    $res = array_merge($res,$this->data->attr_key_list($uid));
    $res = array_diff(array_unique($res),$this->attr_hide);
    return array_fill_keys($res,$this->key);
  }

  function id_details($id){
    qa();
  }

  function _u_details($uid,$key){
    qa();
  }

  function _u_info($uid,$key,&$res){
    if($this->data->attr_exists($key,$uid)){
      $tmp = $this->data->attr_get($key,$uid);
      return $this->data_decode($res,$key)<=0;
    } 
    $res = $this->data->texts_search($key,$uid);
    if(count($res)==0) return FALSE;
    $res = $this->data->getm($res);
    return $this->data_decode($res,$key)<=0;
  }

  function data_encode(&$data,$key){
    return TRUE;
  }

  function data_decode(&$data,$key){
    if(is_null($data)) return 1;
    if(is_array($data)) $data = array_shift($data);
    return 0;
  }


  function _u_list(){
    return $this->data->nodes_search('{%H%P/users%P/user$}');
  }

  function _u_list_hasdata($kinds){
    qa();
    return array();
  }

  function u_umd_val($uid){
    return $this->data->attr_get('umd_val',$uid,$this->key);
  }

  function u_umd_res($uid){
    return $this->data->attr_get('umd_res',$uid,$this->key);
  }


  protected function u_login_possible($uid){
    if(!$this->data->attr_exists('pwd',$uid)) return 2;
    return $this->data->attr_get('pwd',$uid)=='*'?1:0;
  }

  /* ================================================================================
   id translation
   ================================================================================ */

  protected function _vdn2id($vdn){
    list($val,$uname) = $this->um->vdn_split($vdn);
    $res = $this->data->search('ppat','{%H%P/users%P/user@uname$}','value',$uname,'nodes');
    $res = $this->data->attrs_list($res,'umd_val',NULL,$this->key);
    $res = array_keys($res,$val,TRUE);
    if(count($res)==1) return array_shift($res);
    trigger_error("Non unique user: $vdn");
  }

  function _id2un($id){
    return $this->data->attr_get('uname',$id);
  }

  function id2gn($id){
    return $this->data->attr_get('id',$id);
  }


  function u_remove($uid){
    return 996;
  }

  }

class asdf {

  function g_user_list($gid){qx(); return NULL;}



  function user_key($uname,$dom){
    $tmp = $this->data->search('ppat','{%H%P/users%P/user@uname$}','value',$uname);
    $tmp = $this->data->attrs_list($tmp,'umd_val',NULL,$this->key);
    $tmp = array_keys($tmp,$dom);
    return count($tmp)==1?$this->data->iter(array_shift($tmp),'n'):NULL;
  }

  protected function pwd_bykey($key){
    return $this->data->attr_get('pwd',$key);
  }



  function u_name($uid){
    return $this->data->attr_get('uname',$uid);
  }

  function u_groups($uid){
    $pkeys = $this->data->nodes_search('group',$uid);
    return $this->data->attrs_array(NULL,$pkeys,array(),'key');
  }

  function u_rights($uid){
    $tmp = $this->data->attr_get('rights',$uid);
    if(empty($tmp)) return array();
    return explode(' ',$tmp);
  }


  function data_get($key){
    $typ = $this->data->attr_get('type',$key,'string');
    switch($typ){
    case 'string':
      return $this->data->text_get($key);
    }
    qx("get type '$typ' from opc_umd_xml");
  }


  protected function pwd_get($uname,$domain=NULL){
    if(is_null($domain)) $domain = $this->key;
    $xkey = $this->data->search('ppat','{%H%P/users%P/user@uname$}',
				'value',$uname,'nodes');
    if(empty($xkey)) return 1;
    $xkey = $this->data->attrs_list($xkey,'um_domain',NULL,$this->key);
    $xkey = array_keys($xkey,$domain,TRUE);
    if(count($xkey)!=1) return 1;
    return $this->data->attr_get('pwd',array_shift($xkey),'-');
  }















  function log_load(){
    if(!is_object($this->log)){
      $this->log_changed = FALSE;
      
      $this->log = new opc_xml($this->log_file);
      
    }
    $this->log->iter('first');
    return 0;
  }

  function data_save(){
    if(!is_object($this->data) or !$this->data_changed) return -1;
    $this->data->sort();
    q7($this->data->data,$this->data_file,-4);  return 1;
    return $this->data->write_file($this->data_file)?0:1031;
  }

  function log_save(){
    if(!is_object($this->log) or !$this->log_changed) return -1;
    $this->log->sort();
    //q7($this->log->data,$this->log_file,-4);  return 1;
    return $this->log->write_file($this->log_file)?0:1032;
  }
  




  function log($add){
    if($this->log_load()>0) return 1;
    $this->log->node_insert('#>/log',$add);
    $this->log_changed = TRUE;
    return 0;
  }


  function start__load_access_rules(){
    $def = $this->data->search('ppat','{%H%P/access@default$}','value');
    if(!is_null($def)) $this->acc = $def=='allow';
    $pkeys =  $this->data->search('ppat','{%H%P/access%P/rule}');

    if(empty($pkeys)) 
      $this->rules = array();
    else
      $this->rules = $this->data->attrs_array($this->acc_attrs,$pkeys);
    return 0;
  }
  
  function user_create($data){
    return 996;
  }


  /* ================================================================================
   id translation
   ================================================================================ */
  
  function _id2un($id){
    qa();
  }




}
?>
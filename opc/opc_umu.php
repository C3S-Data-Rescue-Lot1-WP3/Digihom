<?php

  /* function validate me / is validated? */

class opc_umu extends opc_tmpl1 implements opi_xquest_user{
  protected $um = NULL;
  protected $dres = NULL;
  protected $dval = NULL;
  protected $umds = array();

  protected $id = NULL;
  protected $uname = NULL;

  protected $rdi = NULL;
  protected $vdn = NULL;

  protected $name = NULL;

  protected $groups = array(); // where the user may be member of
  protected $rights = array(); // which the user may have

  protected $cache = array();
  
  protected $__get_keys = array('dres','dval',
				'id','uname','rdi','vdn','name',
				'groups','rights');

  protected $quest_function = array('memberof','adminof','ownerof',
				    'ingroup',
				    'hasright',
				    'val',
				    'is');

  protected $qst = NULL;


  // is user in this group (any mtype)
  function ingroup($gdn){
    $grps = array_keys($this->groups);
    foreach((array)$gdn as $tmp)
      if(array_intersect($grps,$this->um->gdn_import(explode(';',$tmp)))) return True;
    return False;
  }

  /* ================================================================================
   Quests
   ================================================================================ */

  function quest($quest,$args=array()){
    if(empty($quest)) return NULL;
    return $this->qst->quest($quest,$args);
  }

  // do not forget to register the quests! (see __construct)
  function qst__int($quest,$add,$args){
    switch($quest){

      // test status of group membership, on ehit is enough
    case 'ownerof': case 'adminof': case 'memberof':
      $gdns = $this->um->gdn_import(explode(';',$add['']));
      foreach($gdns as $gdn){
	if(!isset($this->groups[$gdn])) continue;
	$status = def($this->groups[$gdn],'mtyp','member');
	if($status==substr($quest,0,-2)) return TRUE;
      } 
      return FALSE;

      // is related to the groups or childgroups of them
    case 'ingroup':
      $gdns = $this->um->gdn_import(explode(';',$add['']));
      $gdns = $this->um->gdn_add_childs($gdns);
      $cgrps = array_keys($this->groups);
      return count(array_intersect($cgrps,$gdns))>0;

    case 'hasright':
      $tmpA = explode(';',str_replace('{\s*;\s*}',';',$add['']));
      $tmpB = array_keys($this->rights);
      return count(array_intersect($tmpA,$tmpB))>0;

    case 'is':
      $tmpA = explode(';',str_replace('{\s*;\s*}',';',$add['']));
      return in_array($this->vdn,$tmpA);

    case 'val':
      $tmpA = explode(';',str_replace('{\s*;\s*}',';',$add['']));
      return in_array($this->dval->key,$tmpA);

    }
    return NULL;
  }

  function start__load_rights(){
    foreach($this->dres->u_rights($this->id) as $tmp)
      $this->right_add($tmp,$this->dres);

    foreach($this->umds as $umd){
      $fid = $umd->vdn2id($this->vdn);
      if(is_null($fid)) continue;
      foreach($umd->u_rights($fid) as $tmp)      
	$this->right_add($tmp,$umd);
    }
  }

  function start__load_groups(){
    foreach($this->dres->u_groups($this->id) as $tmp)
      $this->group_add($tmp,$this->dres);

    foreach($this->umds as $umd){
      $fid = $umd->vdn2id($this->vdn);
      if(is_null($fid)) continue;
      foreach($umd->u_groups($fid) as $tmp)      
	$this->group_add($tmp,$umd);
    }
  }

  protected function group_add($dat,$umd){
    $key = $this->um->gdn_make($dat['key'],$umd);
    $this->groups[$key] = $dat;
    $rig = def($this->um->groups->get($key,0,array()),'rights',array());
    if(empty($rig)) return 0;
    qx();qq($rig);
    //$this->rights_add(array_combine($rig,array_fill(0,$n,TRUE)),$umd);
  }

  protected function right_add($dat,$umd){
    $key = $this->um->gdn_make($dat['key'],$umd);
    $this->rights[$key] = $dat;
  }

  function __construct(&$um,$vdn){
    $this->tmpl_init();
    $this->um = $um;

    $this->vdn = $vdn;
    $this->rdi = $this->um->vdn2rdi($vdn);

    if(is_null($this->rdi))
      return trigger_error("Error user managment: unkown resp domain for $vdn");

    list($dres,$this->id) = $this->um->rdi_split($this->rdi);
    list($dval,$this->uname) = $this->um->vdn_split($this->vdn);

    $this->dres = $this->um->domain_get($dres);
    $this->dval = $this->um->domain_get($dval);
    if(is_null($this->dval)) 
      return trigger_error("Error user managment: unkown domain $dval for $vdn");

    $this->name = $this->um->user_info($this->vdn,'shownname');

    foreach($this->dres->fumd as $cumd)
      $this->umds[$cumd] = $this->um->domain_get($cumd);

    $this->start__load_groups();
    $this->start__load_rights();

    $this->qst = new opc_xquest($this,$this->quest_function);
  }

  function ___get($key,&$res){
    if(array_key_exists($key,$this->cache)){
      $res = $this->cache[$key];
      return 0;
    }  else if(in_array($key,$this->__get_keys)){
      $res = $this->$key;
      return 0;
    }  
    return 201; 
  }


  function info_all($def=array()){
    $res = $this->um->user_info_all($this->vdn,$def);
    $this->cache = array_merge($this->cache,$res);
    return $res;
  }

  function info_get($key,$def=NULL,&$details=array()){
    // tudu: check allowed
    $res = $this->dres->u_info($this->id,$key,$def,$details);
    $found = TRUE;
    return $res;
  }

  function details_get($key){
    // tudu: check allowed
    return $this->dres->u_details($this->id,$key);
  }

  function details_get_id($id){
    // tudu: check allowed
    return $this->um->id_details($id);
  }

  function details_remove_id($id){
    // tudu: check allowed
    return $this->um->id_remove($id);
  }

  function details_save_id($id,$val){
    // tudu: check allowed
    return $this->um->id_save($id,$val);
  }

  function infos_get($key,$def=NULL,&$found=FALSE){
    // tudu: check allowed
    $res = $this->dres->u_infos($this->id,$key,$def);
    $found = TRUE;
    return $res;
  }

  function info_set($key,$val){
    return $this->dres->u_info_set($this->id,$key,$val);
  }

  function info_set_id($rdi,$val){
    return $this->um->info_set_id($rdi,$val);
  }

  function info_add($key,$val){
    return $this->dres->u_info_add($this->id,$key,$val);
  }

  function info_setn($data,&$res){
    $res = $this->dres->u_info_setn($this->id,$data);
    return max($res);
  }

  // tudu
  function acc_check($what,$key,$typ){
    return $this->dres->acc_check($this->id,$what,$key,$typ);
  }
  
}

?>
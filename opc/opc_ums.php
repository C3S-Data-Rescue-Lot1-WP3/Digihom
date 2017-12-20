<?php

class opc_ums implements ArrayAccess {
  protected $data = array();
  protected $um = NULL;
  protected $acc = array('view'=>'anybody',
			 'create'=>'oneself',
			 'edit'=>'oneself',
			 'delete'=>'oneself',
			 'execute'=>'nobody',
			 'list'=>'anybody',
			 'entry'=>'nobody',
			 'entry-auto'=>'nobody',
			 'entry-admin'=>'nobody',
			 'leave'=>'oneself',

			 );

  function __construct(&$um){
    $this->um = &$um;
  }

  function __get($key){
    if($key==='data') return $this->data;
    qk();
    trigger_error("No read access for " . __CLASS__  . "::\$$key");
  }

  function offsetExists($key){ return isset($this->data[$key]);}
  function offsetGet($key){ return $this->data[$key];}
  function offsetUnset($key){ trigger_error("Read only");}
  function offsetSet($key,$val){ trigger_error("Read only");}

}

class opc_um_rights extends opc_ums{

  protected $str = array();
  protected $field_names = array('label','id','description','parent');

  function get($key,$what=0,$def=NULL){
    if(is_null($key)) $key = array_keys($this->data);
    if(is_array($key)){
      $res = array();
      foreach($key as $ck) $res[$ck] = $this->get($ck,$what);
      return $res;
    } 
    switch($what){
    case 1: return defn($this->data,$key,'label',$def);
    }
    return def($this->data,$key,$def);
  }

  function add($res,$umd){
    if(!is_array($res)) return -1;
    foreach($res as $key=>$val){
      $key = $this->um->gdn_make($key,$umd);
      $tres = $this->add_one($val,$key,$umd);
      if(empty($tres['label'])) $tres['label'] = $key;
      if(!isset($tres['acc'])) $tres['acc'] = $this->acc;
      else $tres['acc'] = array_merge($this->acc,$tres['acc']);
      $this->data[$key] = $tres;
    }
    foreach(ops_array::blowup($this->str,0) as $ck=>$cv)
      $this->data[$ck]['incl'] = array_unique($cv);
  }

  protected function add_one($val,$key,$umd){
    $tres = ops_array::extract($val,$this->field_names);
    $tmp = array($key);
    $all = def($val,'include',array());
    if(!is_array($all)) $all = explode(' ',$all);
    foreach($all as $ck)
      if(!empty($ck)) $tmp[] = $this->um->gdn_make($ck,$umd);
    $this->str[$key] = array_unique($tmp);
    return $tres;
  }
}

class opc_um_groups extends opc_um_rights {

  function add_one($val,$key,$umd){
    $tres = parent::add_one($val,$key,$umd);
    if(is_null($tres['parent'])) $tres['parent'] = $umd;
    $tres['gtype'] = (int)def($val,'gtype',0);
    $tmp = array($key);
    $all = def($val,'include',array());
    if(!is_array($all)) $all = explode(' ',$all);
    foreach($all as $ck)
      if(!empty($ck)) $tmp[] = $this->um->gdn_make($ck,$umd);
    $this->str[$key] = array_unique($tmp);
    
    $tmp = array();
    $all = def($val,'rights',array());
    if(!is_array($all)) $all = explode(' ',$all);
    foreach($all as $ck)
      if(!empty($ck)) $tmp[] = $ck;
    $tres['rights'] = array_unique($tmp);
    $tres['umd'] = $umd;
    return $tres;
  }

  function select_list($par=NULL,$indent=' '){
    $gpar = array_map(create_function('$x','return $x["parent"];'),$this->data);
    $glab = array_merge(array_map(create_function('$x','return $x["label"];'),$this->data),
			$this->um->domains_list(3));
    asort($glab);

    if(is_null($par)) $par = $this->um->domains_list(0);
    else if(is_scalar($par)) $par = array_keys($gpar,$par,TRUE);

    $par = array_intersect(array_keys($glab),$par);
    $res = array();
    $stack = array();
    while($cpar = array_shift($par)){
      $res[$cpar] = str_repeat($indent,count($stack)) . $glab[$cpar];
      array_unshift($stack,$par);
      $par = array_keys($gpar,$cpar,TRUE);
      while(empty($par)){
	if(empty($stack)) return $res;
	$par = array_shift($stack);
      }
    }
  }
}

class opc_um_fields extends opc_ums {
  protected $data = array();
  protected $um = NULL;
  protected $extract = array('label'=>'label',
			     'type'=>'string',
			     'disp'=>'raw');

  function get($key,$umd=NULL,$what=0){
    if(!isset($this->data[$key])) return NULL;
    $res = $this->data[$key];
    if(is_null($umd)) $umd = $res['umd'];
    $res = def($res,$umd . '::data');
    if($what===0) return $res;
    if(isset($this->acc[$what])) 
      return $res['acc'][$what];
    qx("$key $what");
  }



  function add($res,$umd){
    if(!is_array($res)) return -1;
    foreach($res as $key=>$val){
      $tmpA = def($this->data,$key,array('umd'=>$umd));
      $tmpB = ops_array::extract($val,array_keys($this->extract),$this->extract);
      $tres = array_merge($tmpA,$tmpB);
      $tres['umd'] = $tmpA['umd'];
      if(!isset($val['acc'])) $val['acc'] = $this->acc;
      else $val['acc'] = array_merge($this->acc,$val['acc']);
      if(!empty($umd)) $tres[$umd . '::data'] = $val;
      $this->data[$key] = $tres;
    }
  }
}


class opc_um_quests extends opc_ums{
  protected $extract = array('label'=>'label');
  protected $umd = NULL;

  function __construct(&$umd,&$um){
    $this->umd = &$umd;
    $this->um = &$um;
  }

  function add($res){
    if(!is_array($res)) return -1;
    foreach($res as $key=>$val){
      if(!isset($val['acc'])) $val['acc'] = $this->acc;
      else $val['acc'] = array_merge($this->acc,$val['acc']);
      $this->data[$key] = $val;
    }
  }

  function get($key,$what=0){
    if(!isset($this->data[$key])) return NULL;
    $res = $this->data[$key];
    if($what===0) return $res;
    if(isset($this->acc[$what])) 
      return $res['acc'][$what];
    qx("$key $what");
  }


}


?>
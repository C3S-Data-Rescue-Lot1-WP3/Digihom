<?php

class opc_estring {

  protected $inf = NULL;

  public $citem = NULL;
  public $side_data = array();
  public $field_info = array();
  public $defkey = '.';

  // Distributor fo objects and viewers
  public $dist_obj = 'opc_dist_obj';
  public $dist_obj_init = array();

  public $dist_viewer = 'opc_dist_hto';
  public $dist_viewer_init = array('xhtml'=>TRUE);

  public $dist_item = 'opc_dist_item';
  public $dist_item_init = array();

  public $def_fieldlist = array('type'=>'dl',
				'class'=>'details',
				'skip-empty'=>FALSE);
  public $def_table = array('iter'=>'none',
			    'class'=>'columns',
			    'class-colheader'=>'colhead',
			    'class-rowheader'=>'rowhead',
			    'class-cellheader'=>'cellhead',
			    'equal'=>0);

  function __construct($set=array()){
    $this->set_settings($set);
  }
  
  function set_settings($arr){
    if(!is_array($arr) or count($arr)==0) return;

    // do this first --------------------------------------------------
    // info system
    $this->inf = opc_info::init(def($arr,'info_sys_key','global'));

    // all the other settings
    foreach($arr as $key=>$val){
      switch($key){
      case 'info_sys_key': break; // done before
      }
    }
  }

  function block($block,$typ='std'){
    if(isset($block[$this->defkey])) $block = array($block);
    $res = array();
    foreach($block as $cline){
      if(!is_array($cline)) $cline = array($this->defkey=>$cline);
      $head = ops_array::key_extract($cline,$this->defkey);
      $cmd = explode('--',$this->split_front($head));
      $ccmd = array_shift($cmd);
      if(method_exists($this,'_line_' . $ccmd)){
	$mth = '_line_' . $ccmd;
	$cres = $this->$mth($cmd,$head);
      } else if(method_exists($this,'_block_' . $ccmd)){
	$mth = '_block_' . $ccmd;
	$cres = $this->$mth($cmd,$head,$cline);
      } else{
	switch($ccmd){
	case 'first': 
	  $cres = $this->block($cline,'first'); 
	  break;
	default:
	  trg_err(0,"unknown block: '$ccmd'",E_USER_ERROR);
	  $cres = NULL;
	}
      }
      switch($typ){
      case 'std':
	$res[] = $cres;
	break;
      case 'first':
	if(!is_null($cres)) return($cres);
	break;
      default:
	trg_err(0,"unknown block-type: '$typ'",E_USER_ERROR);
	qw($cmd,$head,$cline,$cres);echo '<hr>';
      }
      
    }
    return(implode("\n",$res));
  }
  
  function _block_if($cmd,$line){
    switch($cmd[0]){
    case 'nonemptyfield':
      $cval = $this->get($cmd[1]);
      if(is_null($cval) or $cval=='') return(NULL);
      if($line==='')
	return($cval);
      else
	return($this->_line_replace('replace',$line));

    default:
      trg_err(0,"open point: block_if $cmd[0]",E_USER_ERROR);
    }
  }

  function _block_table($cmd,$txt,$lines){
    $obj = $this->_create_object('table',$lines);
    $hto = $this->_create_viewer($obj,$lines);
    $set = $this->_get_set('table',$lines);

    // special settings ----------------------------------------
    foreach($set as $key=>$val){
      switch($key){
      case 'equal': $hto->equal = $val; break;
      }
    }
    
    // data ------------------------------------------------------------
    $ak = preg_grep('/^(cell|(cell|col|row)head)-\d+(-\d+)?$/',array_keys($lines));
    foreach($ak as $key){
      if(is_string($lines[$key]))
	$val = $this->_line_replace('replace',$lines[$key]);
      else
	$val = $this->block($lines[$key]);
      $ck = explode('-',$key);
      switch($ck[0]){
      case 'cell': case 'cellhead': 
	$obj->add($val,array($ck[1],$ck[2]),$ck[0]); 
	break;
      case 'colhead':  case 'rowhead':  
	$obj->add($val,$ck[1],$ck[0]); 
	break;
      }

    }
    $hto->load($obj);
    return $hto->output();
  }



  /* rewrite to obj/viewer */

  function _block_fieldlist($cmd,$txt,$lines){
    $flds = def($lines,'fields');
    $flds = ops_estring::explode_trim(';',$flds);
    if(!is_array($flds) or count($flds)==0) return(FALSE);

    $obj = $this->_create_object('list',$lines);
    $hto = $this->_create_viewer($obj,$lines);
    $set = $this->_get_set('fieldlist',$lines);
    // special settings ----------------------------------------
    $set['skip-empty'] = (bool)$set['skip-empty'];
    
    // data ------------------------------------------------------------
    foreach($flds as $cf){
      $cval = $this->get($cf);
      if($set['skip-empty']==TRUE and (is_null($cval) or $cval=='')) continue;
      $type = def(def($this->field_info,$cf,array()),'type','text');
      switch($type){
      case 'bool': case 'email': case 'text':
	$cval =  $this->_create_item($type,$cf,$cval,array());
	$obj->add($cval,$this->info($cf,'label'));
	break;

      default:
	qq($cval);
	trigger_error('unknown type ' . $type);
      }
    }
    
    $hto->load($obj);
    return($hto->output());
  }

  /* ================================================================================
   Line functions
   ================================================================================ */


  function _line_replace($cmd,$txt){
    $split = ops_estring::Explode2dn($txt,NULL,array($this,'_cb'));
    return(implode('',$split));
  }

  function _cb($key,$line){
    switch($key){
    case 'F': return($this->get($line[0]));
    }
    trigger_error("unknown %KEY%: $key");
  }

  /* ================================================================================
   Various functions
   ================================================================================ */

  private function split_front(&$str,$sep=' '){
    $pos = strpos($str,$sep);
    if($pos===FALSE){
      $res = $str;
      $str = '';
    } else {
      $res = rtrim(substr($str,0,$pos));
      $str = ltrim(substr($str,$pos+strlen($sep)));
    }
    return($res);
  }
  
  private function get($key){
    $res = def($this->citem,$key,NULL);
    if(!is_object($res)) return($res);
    if(in_array('opi_item',class_implements($res)))
      return($res->get());
    return(NULL);
  }

  private function exp($key,$mode='raw'){
    $res = def($this->citem,$key,NULL);
    if(!is_object($res)) return($res);
    if(in_array('opi_item',class_implements($res)))
      return($res->exp($mode));
    return(NULL);
  }

  private function info($key,$info){
    $res = def($this->citem,$key,NULL);
    if(is_object($res) and in_array('opi_item',class_implements($res)))
      return($res->info($info));    
    else 
      return(def(def($this->side_data,$key,array()),$info,$key));
  }

  protected function _create_item($cls,$key,$val,$set){
    $init = array_merge(array('class'=>$cls,'value'=>$val,'key'=>$key,'set'=>$set),$this->dist_item_init);
    $res = call_user_func_array(array($this->dist_item,'newobj'),$init);
    if(!is_object($res)) trg_err(2,"Creating a '$cls' failed");
    return($res);
  }

  protected function _create_object($cls,&$lines){
    $init = array_merge(array($cls),array_values($this->dist_obj_init));
    $res = call_user_func_array(array($this->dist_item,'newobj'),$init);
    if(!is_object($res)) trg_err(2,"Creating a '$cls' failed");
    return($res);
  }

  protected function _create_viewer($cls,&$lines){
    $init = array($cls,$this->dist_viewer_init);
    $res = call_user_func_array(array($this->dist_viewer,'newobj'),$init);
    if(!is_object($res)) trg_err(2,"Creating a '$cls' failed");
    $ak = array_keys($lines);
    foreach($ak as $ck){
      if(substr($ck,0,5)=='class') {
	$key = str_replace('-','_',$ck);
	$res->$key = $lines[$ck];
	unset($lines[$ck]);
      }
    }
    return($res);
  }
   
  protected function _get_set($cls,&$lines){
    $def = 'def_' . $cls;
    $res =ops_array::key_extract($lines,array_keys($this->$def),$this->$def);
    return($res);
  }
}
?>
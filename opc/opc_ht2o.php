<?php
  /*
   * ht2o_list: incrementel set, what when values and keys differs in size/keys? 
   * auf step umstellen! (analog tabledeluxe)
   */

include_once('opc_tmpl.php');

/** incrementelles durchlaufen durch einzelelemente (sowohl setzten alks auch lesen */

class opc_ht2o extends opc_tmpl1 implements ArrayAccess{
  /* separator used in ArrayAccess */
  public $sep = '/'; 

  /* default set/get target */
  public $def_set = 'data';


  /* save the real content */
  public $data = array();

  /* target pointer used by output */
  public $ht = NULL;
  // link to the framework (if knwon)
  protected $fw = NULL;
  // link to the framework (if knwon)
  protected $tool = NULL;


  /* pointers createt during output: array(identifier=>ht2-key,...) */
  protected $pointers = array();
  
  /* steps to be proceeded during output
   * syntax array(key=>array('subs'=>array(subkey [,subkey ...]))
   * calls method step__[key] (in key - is replaced by _)
   *  the keys are also used for pointers
   */
  public $steps = array(0=>array('subs'=>array()));

  public $step = NULL;
  public $p_row = NULL;
  public $p_col = NULL;


  public $str = NULL;

  /* basic class for this object */
  public $class = '';

  protected $style = NULL;
  protected $style_def = NULL;
  protected $style_set = array();


  /*  incremental setting ------------------------------------------------------------ */
  /* current pointer */

  public $ptr = NULL;
  /* list of pointers to walk through */
  public $ptrchain = array();

  /* special status messages additional to tmpl */
  public $_status_msgs = array();

  function ___get($key,&$res){
    switch($key){
    case 'pointers':
      $res = $this->$key;
      return 0;
    }
    return 501;
  }


  function __construct(/* */){
    $this->str = new opc_tstore();
    $this->tmpl_init();
    $this->ht2o_init();
    $ar = func_get_args();
    call_user_func_array(array($this,'init'),$ar);
    $this->ht2o_init_finish();
  }

  function ht2o_init(){
    return 0;
  }

  function ht2o_init_finish(){
    if(is_null($this->style) and !is_null($this->style_def))
      $this->style_set($this->style_def);
    return 0;
  }

  function style_set($style,$add=array()){
    $mth = 'style_set__' . $style;
    return method_exists($this,$mth)?$this->$mth($add):FALSE;
  }

  function init(/* */){
    $ar = func_get_args();
    foreach($ar as $ca) $this->init_one($ca);
  }

  

  function init_one($ca){
    if($ca instanceof opc_ht2){
      $this->ht = new opc_ptr_ht2($ca);
    } else if($ca instanceof opc_fw){
      $this->fw = $ca;
      $this->tool = $this->fw->tool;
      if(is_null($this->ht)) $this->ht = $ca->ptr(NULL);
    } else if($ca instanceof opc_ptr_ht2){
      $this->ht = &$ca;
    } else if($ca instanceof opc_ht2o){
      $this->ht = &$ca;
    } else if(is_string($ca)){
      return $this->style_set($ca);
    } else if(is_array($ca)){
      foreach($ca as $ck=>$cv) $this->init_key($ck,$cv);
    } else return FALSE;
    return TRUE;
  }


  function init_key($key,$val){
    switch($key){
    case 'style':
      $this->style_set($val);
      return TRUE;
    }
    return FALSE;
  }

  /* combines the current class (or cls if not null) with all elements in list
   * syntax cls cls-listitem1 cls-listitem2 ....
   */
  function make_class($list=array(),$cls=NULL,$add=array()){
    if(is_null($cls)) $cls = $this->class;
    $res = $cls;
    foreach($list as $ci) $res .= ' ' . $cls . '-' . $ci;
    foreach($add as $ck=>$cv)
      $res = str_replace('%' . $ck . '%',$cv,$res);
    return $res;
  }

  function set(/* */){
    $ar = func_get_args();
    switch(count($ar)){
    case 0: return ;
    case 1: return $this->_set($ar[0],array($this->def_set));
    }
    if(is_array($ar[1])) 
      return $this->_set($ar[0],$ar[1]);
    else if(is_string($ar[1]) and strpos($ar[1],$this->sep)!==FALSE) 
      return $this->_set($ar[0],explode($this->sep,$ar[1]));
    $val = array_shift($ar);
    return $this->_set($val,$ar);
  }

  /* saves $value in $this->data at the position given by the oter elements */
  protected function _set($value,$pos){
    $this->data = $value;
  }

  /* similar to set except to the first argument
   * first argument is a ht2p-object which is used to construct the data
   * after constructing the method out (with the ht2p as argument)
   * should be called to reset the pointer to the right place
   */
  function in($ht2p/* */){
    $ar = func_get_args();
    array_shift($ar);
    $ht2e = new opc_ht2e($ht2p->in(NULL));
    $this->set($ht2e,$ar);
    return $ht2p->key;
  }

  /* opposite to in */
  function out($ht2p){ return $ht2p->out();}


  /* similar to in but uses set instead 
   * therefore there is no out like function
   */
  function set_ht($ht2p/* */){
    $ar = func_get_args();
    array_shift($ar);
    $ht2e = new opc_ht2e($ht2p->set(NULL));
    $this->_set($ht2e,$ar);
    return $ht2p->key;
  }

  /* creates a new pointer using the target saved in ht 
   * deprecated??
   */
  function ptr_new($key=NULL,$add=array()){
    if($this->ht instanceof opc_ht2){
      return new opc_ptr_ht2($this->ht,$key);
    } else if($this->ht instanceof opc_fw){
      return $this->ht->ptr_new(def($add,'kind','pointer'),$key);
    } else if($this->ht instanceof opc_ptr_ht2){
      return $this->ht->new_ptr($key);
    } else if($this->ht instanceof opc_ht2o){
      return $this->ht->ptr_new($key);
    } else {
      trigger_error('Cant return pointer since target is not defined');
      return FALSE;
    }
  }

  
  /* creates the ouptut inside $this->ht 
   * subclasses should only overwrite method _output and not this one directly!
   * if the target is a ht2o object too the arguments refer
   *  to those of the method 'set' of the target ($this->ht)
   *  excluding the first one.
   */
  function output(/* details for target */){
    $this->step = new opc_ht2s();
    $args = func_get_args();
    $tmp = $this->steps($args);
    if($tmp>0) return $this->old_output($args);
    if(count($this->str)>1){
      if($this->ht instanceof opc_ht2o)
	return $this->output__str_ht2o($this->ht,$args);
      if($this->ht instanceof opc_ptr_ht2)
	return $this->output__str_ht2p($this->ht,$args);
      return trigger_error("Unkown target object");
    } else {
      if($this->ht instanceof opc_ht2o)
	return $this->output__ht2o($this->ht,$args);
      if($this->ht instanceof opc_ptr_ht2)
	return $this->output__ht2p($this->ht,$args);
      return trigger_error("Unkown target object");
    }
  }

  // just output a single step!
  function output_step(&$ht,$step=NULL/* details for target */){
    $this->step = new opc_ht2s();
    $this->steps();
    $args = func_get_args();
    $step = array_shift($args);
    if($this->ht instanceof opc_ht2o)
      return $this->output__ht2o($this->ht,$args,$step);
    if($this->ht instanceof opc_ptr_ht2)
      return $this->output__ht2p($this->ht,$args,$step);
    return trigger_error("Unkown target object");
  }

  // output to a ht2p object
  function output__str_ht2p(&$ht,$args,$step=NULL){
    $res = array();
    foreach($this->str->seq2oac($step) as $key=>$val){
      $tag = $this->str->data($val['id']);
      if($val['lev']==0) continue;
      switch($val['op']){
      case 'close': $ht->close(); break;
      case 'open':  $ht->aopen($tag); break;
      case 'add':   $ht->atag($tag); break;
      }
    }
    $this->pointers = $res;
    return 0;
  }


  function output__str_ht2o(&$ht,$args,$step=NULL){
    $ht = $obj->ptr_new();
    $tmp = $this->output__str_ht2p($ht,$args,$step);
    if($tmp>0) return $tmp;
    array_unshift($args,new opc_ht2e($ht->key));
    return call_user_func_array(array($obj,'set'),$args);
  } 

  // output to a ht2p object
  function output__ht2p(&$ht,$args,$step=NULL){
    if(is_null($step)) $tmp = $this->step->out($ht);
    else $tmp = $this->step->out_onestep($ht,$step);
    $this->pointers = $tmp<=0?$this->step->ptrs:array();
    return $tmp;
  }

  // output to a ht2o object
  function output__ht2o(&$obj,$args,$step=NULL){
    $ht = $obj->ptr_new();
    $tmp = $this->output__ht2p($ht,$args,$step);
    if($tmp>0) return $tmp;
    array_unshift($args,new opc_ht2e($ht->key));
    return call_user_func_array(array($obj,'set'),$args);
  } 


  function old_output($args){
    $this->pointers = array();
    if(!is_object($this->ht)) return trigger_error("Unkown target object");
    if($this->ht instanceof opc_ht2o){
      $ht = $this->ht->ptr_new();
      $res = $ht->key;
      $this->_output($ht);
      array_unshift($args,new opc_ht2e($res));
      return call_user_func_array(array($this->ht,'set'),$args);
    } else return $this->_output($this->ht);
  }

  function steps($add=array()){
    $mth = 'steps__' . $this->style;
    if(method_exists($this,$mth)) 
      return $this->$mth($this->style_set,$add);
    return 1;
  }

  function steps_subs($key){
    if(!isset($this->steps[$key])) return 1;
    if(!isset($this->steps[$key]['subs'])) return -1;

    foreach($this->steps[$key]['subs'] as $cs) 
      $this->step_sub(array('step'=>$cs));
    $this->step->add('close');
    if($key=='table') qt($this->step->data);
    return 0;
  }

  function step_sub($add){
    $step = def($add,'step','*');
    $set = def($this->steps,$step,array());
    if(def($set,'skip')) return -1;
    // Create Element ..................................................
    $mth = 's__' . str_replace('-','_',$step);
    $res = $this->$mth($add);
    if($res===FALSE) return -1; // empty?
    return $this->steps_subs($step);
  }

  // should be overwritten by real ht2o objects
  function _output(&$ht){
    $ht->add(var_export($this->data,TRUE));
  }

  function step_subs(&$ht,$key){
    if(!isset($this->steps[$key])) return 1;
    if(!isset($this->steps[$key]['subs'])) return -1;
    foreach($this->steps[$key]['subs'] as $cs) 
      $this->step($ht,array('step'=>$cs));
    return 0;
  }

  function step(&$ht,$add){
    $step = def($add,'step','*');
    $set = def($this->steps,$step,array());
    if(def($set,'skip')) return -1;

    // Create Element ..................................................
    $mth = 'step__' . str_replace('-','_',$step);
    $res = $this->$mth($ht,$add);
    if($res==0) return -1; // empty?
    // save results to structer ...........................................
    if(is_array($res)){
      $ids = array_values($res);
      $res = array_combine($ids,array_keys($res));
    } else $ids = def($add,'id');
    $this->pointers[$step] = isset($this->pointers[$step])?array_merge($this->pointers[$step],$res):$res;
    
    // Childs ..................................................
    // no childs or skipchilds was set
    if(!isset($set['subs']) or def($set,'skipchilds')) return 0;
    $ht->in();
    $subs = def(def($this->steps,$step,array()),'subs',array());
    if(is_array($ids)){
      foreach($ids as $cid){
	if(isset($this->pointers[$step][$cid])){
	  $ht->set($this->pointers[$step][$cid]);
	  foreach($this->steps[$step]['subs'] as $cs){
	    $this->step($ht,array('step'=>$cs,'id'=>$cid));
	  }
	}
      }
    } else {
      $ht->set($this->pointers[$step]);
      foreach($this->steps[$step]['subs'] as $cs){
	$this->step($ht,array('step'=>$cs,'id'=>$ids));
      }
    }
    $ht->out();
    return 0;
  }

  /* normalize path (used by subinstances) */
  function path_norm($ar){
    $key = def($ar,0);
    if(is_array($key)) return $key;
    if(is_string($key) and strpos($key,$this->sep)) return explode($this->sep,$key);
    return $ar;
  }


  /** Magic */
  function offsetExists($key){ return $this->exists($key); }
  function offsetUnset($key) { return $this->remove($key);}
  function offsetGet($key)   { return $this->get($key);}
  function offsetSet($key,$value){    $this->set($value,$key);}


  }


?>
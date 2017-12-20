<?php

class opc_ht2o_list extends opc_ht2o implements Countable{
  public $def_set = 'kv';

  public $style = NULL;

  /* how to react by output if the keys in keys and values are different
   * 0: trigger notice
   * 1: use only keys that appear in both
   * 2: use keys in keys
   * 3: use keys in values
   * 4: use keys of both
   * Hint the order is defined by keys first!
   */
  public $diff_mode = 4;

  /* tag definitions one of:
   *   array('tag'=>tag,'class'=> ... )
   *   NULL: show without a tag (add)
   *   FALSE: do not show at all
   * see fucntion set_style for predefined variations
   */ 
  public $tag_outer = NULL; // the big all embeding tag
  public $tag_inner = NULL; // embeds a complete item (key and value)
  public $tag_key = NULL;   // embeds tag
  public $tag_val = NULL;   // embeds value
  public $tag_kv = NULL;    // shown between key and value  
  public $tag_sep = NULL;   // shown between two elements
  public $tag_empty = NULL; // shown if list is empty

  protected $values = array();
  protected $keys = array();
  protected $sorts = array();

  /* use one of
   * sort__none, sort__keys, sort__values, sort__sort
   * or set to a callable function
   */
  public $sort_method = 'sort__none';

  function count(){return count($this->values);}

  function ___set($key,$value){
    switch($key){
    case 'values': $this->set($value,'values'); return 0;
    case 'keys': $this->set($value,'keys'); return 0;
    case 'kv': $this->set($value,'kv'); return 0;
    }
    return 4;
  }

  function ___get($key,&$res){
    switch($key){
    case 'keys': case 'values': case 'sorts':
      $res = $this->$key;
      return 0;
    }
    return parent::___get($key,$res);
  }

  /* possible combinations
   * array (keys|values|sorts)(_add)?
   * any key|value|sort pos
   * any (key|value|sort)_add
   * assoc-array kv (combination of keys and values in one array)
   */
  protected function _set($data,$pos){
    switch(def($pos,0,$this->def_set)){
    case 'keys': $this->keys = $data; ;break;
    case 'values': $this->values = $data; break;
    case 'sorts': $this->sorts = $data; break;
    case 'kv':
      $this->values = array_values($data);
      $this->keys = array_keys($data);
      break;
    case 'key_add':   $this->keys[] = $data; break;
    case 'value_add': $this->values[] = $data; break;
    case 'sort_add':  $this->sorts[] = $data; break;
    case 'keys_add':  $this->keys = array_merge($this->keys,$data); break;
    case 'values_add': $this->values = array_merge($this->values,$data); break;
    case 'sorts_add': $this->sorts = array_merge($this->sorts,$data); break;
    case 'kv_add':
      $this->values = array_merge($this->values,array_values($data));
      $this->keys = array_merge($this->keys,array_keys($data));
      break;
    case 'key':   $this->keys[$pos[1]] = $data; break;
    case 'value': $this->values[$pos[1]] = $data; break;
    case 'sort':  $this->sorts[$pos[1]] = $data; break;
    }
  }

  function _get($path){
    $path = $this->path_norm(func_get_args());
    switch($path[0]){
    case 'value':  return def($this->values,$path[1]);
    case 'key':    return def($this->keys,$path[1]);
    case 'sort':   return def($this->sorts,$path[1]);
    case 'keys':   return $this->keys;
    case 'values': return $this->values;
    case 'sorts':  return $this->sorts;
    }
  }

  function exists($path){
    $path = $this->path_norm(func_get_args());
    switch($path[0]){
    case 'value':  return isset($this->values[$path[1]]);
    case 'key':    return isset($this->keys[$path[1]]);
    case 'sort':   return isset($this->sorts[$path[1]]);
    case 'keys':   return count($this->keys)>0;
    case 'values': return count($this->values)>0;
    case 'sorts':  return count($this->sorts)>0;
    }
  }

  function remove($path){
    $path = $this->path_norm(func_get_args());
    switch($path[0]){
    case 'value':  unset($this->values[$path[1]]); return ;
    case 'key':    unset($this->keys[$path[1]]); return ;
    case 'sort':   unset($this->sorts[$path[1]]); return ;
    case 'keys':   $this->keys = array(); return ;
    case 'values': $this->values = array(); return ;
    case 'sorts':  $this->sorts = array(); return ;
    case '*':      $this->keys = array(); $this->values = array(); $this->sorts = array(); return;
    }
  }

  /* set style of output
   * predefined: dl ol ul vt (vertical table) ht (hor. table)
   *             ol-key (ol with key included) ul-key (ul ...)
   *             embsp (simple span list with emsp inbetween no keys)
   * or '!' separated list with up to 6 elements
   * order: 0: outer tag, 1: inner tag, 2: key tag, 3: value tag, 4: key-val sep, 5: item-item sep
   * if part is neiter 0,1 nor 2 a further argument in arg-list is expected.
   */

  function set_style($style/* */){
    $this->style = $style;
    $tags = array('tag_outer','tag_inner','tag_key','tag_val','tag_kv','tag_sep');
    switch($style){ // 0 means not possible, 1 hide, 2 show without tag; keep them as astring
    case 'dl': $sty = array('dl','0','dt','dd','0','0'); break;
    case 'ol': $sty = array('ol','li','1','2','1','0'); break;
    case 'ul': $sty = array('ul','li','1','2','1','0'); break;
    case 'vt': $sty = array('table','tr','th','td','0','0'); break;
    case 'ht': $sty = array('table','tr','th','td','0','0'); break;
    case 'ol-key': $sty = array('ol','li','b','2',array(1=>': '),'0'); break;
    case 'ul-key': $sty = array('ul','li','b','2',array(1=>': '),'0'); break;
    case 'emsp': $sty = array('div','span','1','2','1','&emsp;'); break;
    default:
      $sty = explode('!',$style,6);
    }
    $ar = func_get_args();
    array_shift($ar); // already used
    for($i=0;$i<count($sty);$i++){
      $tag = $tags[$i];
      $val = def($sty,$i,0);
      switch($val){
      case '0': $this->$tag = NULL; break;
      case '1': $this->$tag = FALSE; break;
      case '2': $this->$tag = TRUE; break;
      default:
	$ca = array_shift($ar);
	if($i<4)                             $this->$tag = new opc_attrs($val,$ca);
	else if(is_array($ca))               $this->$tag = new opc_attrs(def($ca,'tag','span'),$ca);
	else if(is_null($ca) or $ca===FALSE) $this->$tag = $val;
	else                                 $this->$tag = $ca;
      }
    }
  }

  function init_one($ca){
    if(is_string($ca)){
      $this->set_style($ca);
    } else return parent::init_one($ca);
  }

  protected function _which_keys(){
    $kk = array_keys($this->keys);
    $vk = array_keys($this->values);
    $ik = array_intersect($kk,$vk);
    $ck = array_unique(array_merge($kk,$vk));

    if(count($vk)==0) return $kk;
    if(count($kk)==0) return $vk;
    if(count($ck)==count($ik)) return $kk;

    switch($this->diff_mode){
    case 0: trigger_error("Keys and values differ in size/keys"); return array();
    case 1: return $ik;
    case 2: return $kk; 
    case 3: return array_merge($ik,array_diff($vk,$ik)); 
    case 4: return array_merge($kk,array_diff($vk,$ik)); 
    }
    return array();
  }

  protected function sort($keys,$mth){
    switch($mth){
    case 'sort__none': return $keys;
    case 'sort__id': $sa = ac($keys); asort($sa); break;
    case 'sort__keys': $sa = $this->keys; asort($sa); break;
    case 'sort__values': $sa = $this->values; asort($sa); break;
    case 'sort__sort': case 'sort__sorts': $sa = $this->sorts;  asort($sa);break;
    default:
      if(is_callable($mth)){
	$sa = $this->sorts;
	uasort($sa,$mth);
      } else {
	trigger_error("Unkown sort method for ht2o_list: '$mth'",E_USER_WARNING);
	return $keys;
      }
    }
    $sk = array_keys($sa);
    $res = array_merge(array_intersect($sk,$keys),array_diff($keys,$sk));
    return $res;
  }

  /* direct output
   * variations:
   *  ht array -> ht array-keys array-values dl
   *  ht array ol/ul -> ht array-values ol/ul
   *  ht array other -> ht array-keys array-values other
   *  ht keys values style
   */
  static function d(/* */){
    $ar = func_get_args();
    $na = count($ar);
    switch($na){
    case 0: case 1: return NULL;
    case 2: $ar[2] = array_values($ar[1]); $ar[1] = array_keys($ar[1]); $ar[3] = 'dl'; break;
    case 3:
      if(is_string($ar[2])){
	$ar[3] = $ar[2];
	if(in_array($ar[2],array('ol','ul'))){ $ar[2] = array_values($ar[1]); $ar[1] = NULL; }
	else { $ar[2] = array_values($ar[1]); $ar[1] = array_keys($ar[1]);}
      } else $ar[3] = 'dl';
      break;
    }
    $tmp = new opc_ht2o_list();
    $tmp->init_one($ar[0]);
    $tmp->init_one($ar[3]);
    $tmp->set($ar[1],'keys');
    $tmp->set($ar[2],'values');
    $tmp->output();
    return $tmp->pointers;
  }
  
  function steps($add=array()){
    $steps = array();
    // which elements should be shwon in which order?
    $keys = $this->_which_keys();
    if(empty($keys)) {
      $this->step->add('atag','main',$this->tag_empty);
      return -1;
    }
    
    $keys = $this->sort($keys,$this->sort_method);
    // some basic properties for constructing
    $c_hasouter = !empty($this->tag_outer); 
    $c_hasinner = !empty($this->tag_inner); 
    $c_haskv    = !empty($this->tag_kv); 
    $c_hassep   = !empty($this->tag_sep); 
    $c_haskey   = count($this->keys)>0; 
    if($c_hasouter)
      $this->step->add('aopen','main',$this->tag_outer);

    // doit ----------------------g----------------------------------------------------------------
    switch($this->style){
    case 'ht': // horizontal table --------------------------------------------------
      if($c_haskey){
	$this->step->add('aopen','keys',$this->tag_inner);
	foreach($keys as $ckey) 
	  $this->step->add('atag','key--' . $ckey,$this->tag_key,$this->keys[$ckey]);
	$this->step->add('close');
      }
      $this->step->add('aopen','vals',$this->tag_inner);
      foreach($keys as $ckey) 
	$this->step->add('atag','val--' . $ckey,$this->tag_val,$this->values[$ckey]);
      $this->step->add('close');
      break;

      // others ----------------------------------------------------------------------
    default: 
      $n = count($keys);
      for($i=0;$i<$n;$i++){
	$ckey = $keys[$i];
	if($i>0 and $c_hassep)
	  $this->step->add(is_object($this->tag_sep)?'atag':'add',TRUE,$this->tag_sep);
	if($c_hasinner) 
	  $this->step->add('aopen','ele--' . $ckey,$this->tag_inner);
	try{
	  if(!$c_haskey) throw new Exception; 
	  if(!isset($this->keys[$ckey])) throw new Exception; 
	  if(is_null($this->keys[$ckey])) throw new Exception; 
	  if(is_string($this->keys[$ckey]) and trim($this->keys[$ckey])=='') throw new Exception; 
	  $this->step->add('atag','key--' . $ckey,$this->tag_key,def($this->keys,$ckey));
	} catch (Exception $ex){} 
	  
	if($c_haskv){
	  $this->step->add(is_object($this->tag_kv)?'atag':'add',TRUE,$this->tag_kv);
	}
	$this->step->add('atag','val--' . $ckey,$this->tag_val,def($this->values,$ckey));
	if($c_hasinner) $this->step->add('close');
      }
    }
    if($c_hasouter) $this->step->add('close');
    return 0;
  }

  }

?>
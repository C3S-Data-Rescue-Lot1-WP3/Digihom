<?php

abstract class opc_ht3a{
  /** the real data */
  protected $data = NULL;
  
  public static $subclasses = array('*'=>'opc_ht3a_std',
				    'class'=>'opc_ht3a_class',
				    'style'=>'opc_ht3a_style',
				    );

  /** static function to create a sub-opc_ht3a depending the key */
  static function newitem($key,$val){
    $cls = def(self::$subclasses,$key,self::$subclasses['*']);
    return new $cls($val,$key);
  }
  
  private function __construct(){}

  /** set to a new value */
  function set($val,$mode='replace'){
    switch($mode){
    case 'replace': $this->data = $val; break;
    case 'add': $this->data .= ' ' . $val; break;
    default:
      trigger_error("unkown set-mode: $mode");
    }
  }

  function __get($key){
    if($key=='type') return $this->type;
    trigger_error("unallowd acces to '$key' on opc_ht3a");
  }

  /** get the current value */
  function get(){return $this->data;}

  /** magic for string-conversion */
  function __toString(){return strval($this->get());}
  
}


class opc_ht3a_std extends opc_ht3a{
  protected $type = NULL;
  function __construct($data,$type){ $this->set($data); $this->type = $type;}
}

class opc_ht3a_class extends opc_ht3a{
  protected $data = array();
  protected $type = 'class';

  function __construct($data){ $this->set($data);}

  /**
   * replace: replaces the current by the new classes
   * add: add the new to the current calsses (verifies uniquess)
   * reduce: use only classes which apper in the old and new values
   * remove: remove those classes from old which appear in new
   */
  function set($val,$mode='replace'){
    $val = is_array($val)?$val:explode(' ',$val);
    switch($mode){
    case 'replace': $this->data = $val; break;
    case 'add':     $this->data = array_unique(array_merge($this->data,$val)); break;
    case 'prepend': $this->data = array_unique(array_merge($val,$this->data)); break;
    case 'append':  $this->data = array_unique(array_merge($this->data,$val)); break;
    case 'reduce':  $this->data = array_intersect($this->data,$val); break;
    case 'remove':  $this->data = array_diff($this->data,$val); break;
    default:
      trigger_error("unkown set-mode: $mode");
    }
  }

  function get(){return implode(' ',$this->data); }
}

class opc_ht3a_style extends opc_ht3a{
  protected $data = array();
  protected $type = 'style';

  function __construct($data){ $this->set($data);}
  function set($val,$mode='replace'){
    if(is_string($val)){
      $nval = array();
      foreach(explode(';',$val) as $cval){
	$cval = trim($cval);
	if(empty($cval)) continue;
	$cvals = explode(':',$cval);
	if(count($cvals)==2) $nval[trim($cvals[0])] = trim($cvals[1]);
	else trigger_error("invalid style-setting: $cval");
      }
    } else $nval = $val;
    foreach($nval as $key=>$val)$this->set_one($key,$val,$mode);
    $this->data = $nval;
  }

  function set_one($key,$val,$mode='replace'){
    $val = is_array($val)?$val:explode(' ',$val);
    // in term of a style item add & replace is the same!
    switch($mode){
    case 'replace': case 'add': $this->data[$key] = $val; break;
    default:
      trigger_error("unkown set-mode: $mode");
    }
  }

  function get(){
    $res = array();
    foreach($this->data as $key=>$val) $res[] = $key . ': ' . $val .';';
    return implode(' ',$res);
  }
}





/** container class to manage multiple opc_ht3a instances plus a tag-name
 * (hint) all non capitalized object variables are interpreted as attributes
 */
class opc_ht3t implements opi_h3i_use, ArrayAccess, Countable{
  /** current tag (handled separately) */
  protected $Tag = NULL;
  /** pattern for correct attribute names */
  protected $PatLoc = '/^@(key|mode)$/';
  /** pattern for correct attribute names */
  protected $Pat = '/^[*_a-z][-:_\w]*$/';
  /** pattern for correct  tag name */
  protected $PatTag = '/^[_a-z][:_\w]*$/';

  protected $Loc_key = NULL;
  protected $Loc_mode = NULL;

  protected $Text = '';
  protected $Prefix = ''; 
  protected $Suffix = '';
  protected $Header = '';
  protected $Footer = '';

  function __construct(/* */){
    $this->init_tda(func_get_args());
  }

  function init_ta($args){
    if(empty($args)) return -1;
    $tag  = array_shift($args);
    if(0 > $tmp = $this->init_t($tag))  return $tmp;
    foreach($args as $ca)
      if(0 > $tmp = $this->init_a($ca)) return $tmp;
  }


  function init_tda($args){
    if(empty($args)) return -1;
    $tag  = array_shift($args);
    $data = array_shift($args);
    if(0 < $tmp = $this->init_t($tag))  return $tmp;
    if(0 < $tmp = $this->init_d($data)) return $tmp;
    foreach($args as $ca)
      if(0 > $tmp = $this->init_a($ca)) return $tmp;
  }

  function set_attr($key,$val,$mode){
    if(!preg_match($this->Pat,$key)) return 9990;
    if(!isset($this->$key)){
      $this->$key = opc_ht3a::newitem($key,$val);
      return 0;
    }
    qx("tag3 set_attr");
    return 9998;
  }

  function init_a($arg){
    if(is_string($arg)) return $this->init_as($arg);
    foreach((array)$arg as $key=>$val){
      if(is_numeric($key)){
	$this->init_as($val);
      } else if(preg_match($this->PatLoc,$key)){
	$key = 'Loc_' . substr($key,1);
	$this->$key = $val;
      } else if(!preg_match($this->Pat,$key)){
	return 9990;
      } else if(!isset($this->$key)){
	$this->$key = opc_ht3a::newitem($key,$val);
      } else qx("tag3 init_a add style");
    }
    return 0;
  }

  /* automatic attr recognition by content
   * starts with #: use as id (part behind)
   * starts with =: use as name (part behind)
   * contains ;:    use as style
   * [else]:        use as class
   */
  function init_as($arg){
    if(substr($arg,0,1)=='#')
      return $this->set_attr('id',substr($arg,1),'replace');
    if(substr($arg,0,1)=='=')
      return $this->set_attr('name',substr($arg,1),'replace');
    if(strpos($arg,';')!==FALSE)
      return $this->set_attr('style',$arg,'add');
    return $this->set_attr('class',$arg,'add');
  }

  function init_d($arg){
    if(is_null($arg) or $arg==='') return -1;
    if(is_string($arg)){
      $this->set('Text',$arg,'add');
      return 0;
    }
    qx("tag3 init_d");
    return 9998;
  }

  function init_t($arg){
    if(is_null($arg) or $arg=='' or $arg==='-'){
      $this->Tag = NULL;
      return 0;
    } else if(is_string($arg)){
      if(!preg_match($this->PatTag,$arg)) return 9990;
      $this->Tag = $arg;
      return 0;
    } else if(is_array($arg)){
      if(!isset($arg['tag'])) return 9990;
      $tag = ops_array::extract($arg,'tag');
      if(0 > $tmp = $this->init_t($tag))  return $tmp;
      return init_a($arg);
    }
    qx("tag3 init_t");
    return 9998;
  }


  function __clone(){
    $keys = $this->get_keys();
    foreach($keys as $ck) 
      $this->$ck = clone $this->$ck;
  }

  function __get($key){
    if($key=='tag') return $this->Tag;
    return $this->$key;
  }


  /* ================================================================================
     Array Access / Countable (implement)
     ================================================================================ */
  function offsetExists($key)  { return $this->exists($key);}
  function offsetUnset($key)   { return $this->remove($key);}
  function offsetGet($key)     { return $this->get($key);}
  function offsetSet($key,$val){ return $this->set($key,$val);}
  function count(){return count($this->get_keys());}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~


  /* ================================================================================
     egsr
     ================================================================================*/ 
  /** returns if agiven attribute exits (non-empty) 'tag' exists allways */
  function exists($key){
    if($key==='tag') return !is_null($this->Tag);
    if(!preg_match($this->Pat,$key)) return NULL;
    return isset($this->$key);
  }

  /** removes an attribute */
  function remove($key){
    if($key==='tag') {
      if(is_null($this->Tag)) return -1;
      $this->Tag = NULL;
    } else {
      if(!preg_match($this->Pat,$key)) return 1;
      if(!isset($this->$key)) return -1;
      unset($this->$key);
    }
    return 0;
  }

  /** retunrs an array of all attribute keys */
  function get_keys(){
    return preg_grep($this->Pat,array_keys(get_object_vars($this)));
  }

  /** returns the current value ofg a attribute or a default */
  function get($key,$def=NULL){
    if($key==='tag')                  
      return $this->Tag;
    if(!preg_match($this->Pat,$key) or !isset($this->$key)) 
      return $def;
    return $this->$key->get();
  }

  // as get but removes it too
  function get_x($key,$def=NULL){
    if($key==='tag')
      return $this->Tag;
    if(!preg_match($this->Pat,$key) or !isset($this->$key)) 
      return $def;
    $res = $this->$key->get();
    $this->remove($key);
    return $res;
    
  }

  /** sets a attribute or tag
   * a NULL or '' value will unset the attribute (but not the tag)
   */
  function set($key,$val,$mode='replace'){
    if($key==='tag' or $key==='Tag')
      return $this->set_t($val);
    if(in_array($key,array('Text','Prefix','Suffix','Header','Footer')))
      return $this->set_text($key,$val,$mode);
    if(is_nuemric($key) or $key==='.')
      return $this->set_text('Text',$val,'add');
    if(preg_match($this->Pat,$key))
      $this->set_attr($key,$val,$mode);
    return 10012;
  }

  function set_text($key,$val,$mode){
    switch($mode){
    case 'replace': $this->$key = $val; return 0;
    case 'add': $this->$key .= $val; return 0;
    case 'prefix': $this->$key = $val . $this->$key; return 0;
    }
    return 9990;
  }

  
  /** returns multiple attributes
   * @param mixed $keys: if an array only the asked attriubutes are returned otherwise all
   */
  function getn($keys=NULL){
    if(is_null($keys)){
      $res = array();
      $keys = $this->get_keys();
      foreach($keys as $ck) $res[$ck] = $this->$ck->get();
    } else if(is_array($keys)){
      $res = array();
      foreach($keys as $ck) if(isset($this->$kc)) $res[$ck] = $this->$ck->get();
    } else if($keys=='Post'){
      $res = $this->NumPost;
      ksort($res);
    } else if($keys=='Pre'){
      $res = $this->NumPre;
      krsort($res);
    } 
    return $res;
  }

  /** multiple set using an named array (or a string for shortcut to class/style) */
  function setn($attrs,$mode='replace'){
    if(is_null($attrs)){
      return ;
    } else if(is_array($attrs)){
      foreach($attrs as $key=>$val) $this->set($key,$val,$mode);
    } else if($attrs instanceof opc_ht3t){
      foreach($attrs->getn() as $key=>$val) $this->set($key,$val,$mode);
      $this->NumPre = $attrs->NumPre;
      $this->NumPost = $attrs->NumPost;
    } else if(is_string($attrs)){
      if(strpos($attrs,';')!==FALSE)
	$this->set('style',$attrs,$mode);
      else
	$this->set('class',$attrs,$mode);
    } else {
      trigger_error('only strings and array allowed',E_USER_WARNING);
    } 
  }
    

  /** implodes an array of attributes all attribute using thei strval function
   * @param named-array $attr: array of attributes (nam=>opc_ht3a)
   * @param named-array $set: detail settings<br>
   *  xhtml (def: TRUE) : xhtml or html syntax?<br>
   *  implode (def: ' '): string to implode the pre-final array
   *  prefix (def:'')   : used as prefix in case of a string result
   * @return: array if set[implode] is not a string or a string
   */
  static function implode($attr,$set=array()){
    $xhtml = def($set,'xhtml',TRUE);
    if(!is_array($attr)) return $attr;
    $res = array();
    while(list($ak,$av)=each($attr)){
      if(is_null($av) or $av===FALSE) continue; // skip this
      if(is_numeric($av))     $res[] = $ak . '="' . $av .'"';
      else if(is_string($av)) $res[] = $ak . '="' . htmlspecialchars($av,ENT_COMPAT) .'"';
      else if($xhtml)         $res[] = $ak . '="' . $ak .'"';
      else                    $res[] = $ak;
    }
    if(count($res)==0) return '';
    $impl = def($set,'implode',' ');
    if(!is_string($impl)) return $res;
    return def($set,'prefix','') . implode($impl,$res) . def($set,'postfix','');
  }

  /** preapre data for output */
  function finalize($set=array()){
    $res = array();
    $keys = $this->get_keys();
    foreach($keys as $ck) $res[$ck] = $this->$ck->get();
    return self::implode($res,$set);
  }

  function loc_extract(){
    return array($this->Loc_key,$this->Loc_mode);
  }


  /* export to a 3_i */
  function exp_open(&$res){
    return $this->exp_add($res);
  }

  function exp_close(&$res){
  }

  function exp_add(&$res){
    $res->type = 'tag';
    $res->head['.'] = $this->Tag;
    foreach($this->get_keys() as $ck)
      $res->head[$ck] = $this->$ck->get();
    $res->raw = $this->Header . $this->Text;
    return 0;
  }

}
?>
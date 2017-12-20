<?php

abstract class opc_attr{
  /** the real data */
  protected $data = NULL;
  
  public static $subclasses = array('*'=>'opc_attr_std',
				    'class'=>'opc_attr_class',
				    'style'=>'opc_attr_style',
				    );

  /** static function to create a sub-opc_attr depending the key */
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
    trigger_error("unallowd acces to '$key' on opc_attr");
  }

  /** get the current value */
  function get(){return $this->data;}

  /** magic for string-conversion */
  function __toString(){return strval($this->get());}
  
}


class opc_attr_std extends opc_attr{
  protected $type = NULL;
  function __construct($data,$type){ $this->set($data); $this->type = $type;}
}

class opc_attr_class extends opc_attr{
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

class opc_attr_style extends opc_attr{
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





/** container class to manage multiple opc_attr instances plus a tag-name
 * (hint) all non capitalized object variables are interpreted as attributes
 */
class opc_attrs implements ArrayAccess, Countable{
  /** current tag (handled separately) */
  protected $Tag = NULL;
  /** pattern for correct attribute names */
  protected $Pat = '/^[*_a-z][-:_\w]*$/';
  /** pattern for correct  tag name */
  protected $PatTag = '/^[_a-z][:_\w]*$/';


  protected $Data = '';

  protected $NumPre = array();
  protected $NumPost = array();

  static function attr_fusion(/* $args */){
    $ar = func_get_args();
    $cls = __CLASS__;
    $res = new $cls();
    foreach($ar as $ca){
      if($ca instanceof opc_attrs){  $res->setn($ca);
      } else if(is_array($ca)){      $res->setn($ca);
      } else if(is_string($ca)){
	if(strpos($ca,';')!==FALSE)  $res->set('style',$ca,'add');
	elseif(substr($ca,0,1)=='=') $res->set('id',substr($ca,1),'replace');
	else                         $res->set('class',$ca,'add');
      } 
    }
    return $res;
  }

  function __construct($tag=NULL,$data=array()){
    $this->set('tag',$tag);
    $this->setn($data);
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
      if(is_null($this->Tag)) return FALSE;
      $this->Tag = NULL;
    } else {
      if(!preg_match($this->Pat,$key)) return NULL;
      if(!isset($this->$key)) return FALSE;
      unset($this->$key);
    }
    return TRUE;
  }

  /** retunrs an array of all attribute keys */
  function get_keys(){
    return preg_grep($this->Pat,array_keys(get_object_vars($this)));
  }

  /** return the current tag */
  function tag(){return $this->Tag;}

  /** returns the current value ofg a attribute or a default */
  function get($key,$def=NULL){
    if($key==='tag')
      return $this->Tag;
    if(!preg_match($this->Pat,$key)) 
      return trg_err(1,'invalid attribute name: ' . $key);
    return isset($this->$key)?$this->$key->get():$def;
  }

  // as get but removes it too
  function get_x($key,$def=NULL){
    if($key==='tag')
      return $this->Tag;
    if(!preg_match($this->Pat,$key)) 
      return trg_err(1,'invalid attribute name: ' . $key);
    if(!isset($this->$key))
      return $def;
    $res = $this->$key->get();
    $this->remove($key);
    return $res;
    
  }

  /** sets a attribute or tag
   * a NULL or '' value will unset the attribute (but not the tag)
   */
  function set($key,$val,$mode='replace'){
    if($key==='tag' or $key==='Tag'){
      if(is_string($val) and preg_match($this->PatTag,$val)) 
	$this->Tag = $val;
      else if(is_null($val))
	$this->Tag = $val;
      else 
	return trg_err(1,'invalid tag name: ' . $val,E_USER_NOTICE,FALSE);
    } else if($key==='.'){
      $this->Data .= $val;
    } else if(is_numeric($key)){
      if((int)$key<0) $this->NumPre[abs((int)$key)] = $val;
      else $this->NumPost[(int)$key] = $val;
    } else if($key==='Pre_in'){
      array_unshift($this->NumPre,$val);
    } else if($key==='Pre_out'){
      array_push($this->NumPre,$val);
    } else if($key==='Post_in'){
      array_unshift($this->NumPost,$val);
    } else if($key==='Post_out'){
      array_push($this->NumPost,$val);
    } else if(!preg_match($this->Pat,$key)){
      return FALSE;
    } else if(is_null($val) or $val===''){
      if(isset($this->$key)) unset($this->$key);
    } else if(isset($this->$key)){
      $this->$key->set($val,$mode);
    } else if(in_array($mode,array('replace','add','prepend','append'))){
      $this->$key = opc_attr::newitem($key,$val);
    } else qz();
    return TRUE;
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
    } else if($attrs instanceof opc_attrs){
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
   * @param named-array $attr: array of attributes (nam=>opc_attr)
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

}

//  1 XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
//  2 %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
//  3 **********************************************************************
//  4 xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
//  5 ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
//  6 ======================================================================
//  7 ::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
//  8 ----------------------------------------------------------------------
//  9 ......................................................................
// 10                                                                       
class opc_comment {
  public $text = NULL;
  protected $level = 1;
  protected $style = 'html';
  protected $substyle = 'default';
  protected $width = 80;
  protected $short = 40; // if shorter -> one line
  protected $indent = '  ';
  protected $chars =      array(1=>'X','%','*','x','+','=',':','-','.',' ');
  protected $line_chars = array(1=>'~','~','~','~',':',':','.','.',' ',' ');
  
  

  function __construct($text,$level=1,$add=array()){
    $add['level'] = (int)$level;
    $this->text($text);
    $this->set_settings($add);
  }

  function text($text){
    if(is_string($text)) 
      $this->text = $text;
    else if(is_array($text))
      $this->text = implode("\n" . $this->indent,$text);
  }

  function set_settings($arr){
    foreach($arr as $key=>$val) $this->set($key,$val);
  }

  function set($key,$val){
    switch($key){
    case 'width':
      if(!is_int($val) or $val<10 or $val>200) return FALSE;
      break;

    case 'level':
      if($val!==0  and !isset($this->chars[$val])) return FALSE;
      break;

    case 'indent':
      if(!is_string($val)) return FALSE;
      break;

    case 'style':
      if(!in_array($val,array('html'))) return FALSE;
      break;

    case 'substyle':
      if(!in_array($val,array('default'))) return FALSE;
      break;

    case 'chars': case 'linechars':
      if(!is_array($val) or count($val)==0) return FALSE;
      $val = array_combine(range(1,count($val)),$val);
      break;

    default:
      return FALSE;
    }
    $this->$key = $val;
    return TRUE;
  }

  function single($add=NULL){
    return $this->head($add);
  }

  /** creares the head block:
   */
  function head($add=NULL){
    $short =  strlen($this->text) < $this->short;
    $lines = $short?array($this->text):explode('@#+*',wordwrap($this->text,$this->width-10,'@#+*'));

    if(!empty($add)){
      if($short) $lines = array_merge($lines,(array)$add);
      else       $lines = array_merge($lines,array('- - -'),(array)$add);
    }

    if($this->level==0) return '<!-- ' . implode(' ',$lines) . ' -->';

    $char = $this->chars[$this->level];
    
    if(count($lines)==1){
      $bl = $this->repeat($char,$this->width-10);
      return "\n<!-- " . $this->mid($bl,$lines[0]) . "  -->\n";
    }

    if($char=='') return "\n<!-- " . implode("\n     ",$lines) . "\n-->\n";

    $lchar = $this->line_chars[$this->level];

    $fl = $this->repeat($char,$this->width-10);
    $el = $fl;
    if($short) $fl = $this->left($fl,array_shift($lines));

    $res = array_map(create_function('$x','return $x . str_repeat(\' \','
				     . ($this->width-10) . '-strlen($x));'),
		     $lines);
    array_unshift($res,$fl);
    $res[] = $el;
    $impl = ' ' . $this->repeat($lchar,4) . "\n" . $this->repeat($lchar,4) . ' ';
    return "\n<!-- " . implode($impl,$res) . "  -->\n";
  }

  function foot($txt='END'){
    if($this->level==0) return "<!-- $txt -->";
    $char = $this->chars[$this->level];
    $bl = $this->repeat($char,$this->width-10);
    return "\n<!-- " . $this->left($bl,$txt) . "  -->\n";
  }

  function left($line,$repl,$space=' '){
    $res = $repl . $space;
    return $res . substr($line,strlen($res));
  }

  function right($line,$repl,$space=' '){
    $res = $space . $repl;
    return substr($line,0,-strlen($res)) . $res;
  }

  function mid($line,$repl,$space=' '){
    $pos = (strlen($line)-strlen($repl)-2*strlen($space))/2;
    return substr($line,0,ceil($pos)) . $space . $repl . $space
      . substr($line,-$pos);
  }

  /* used for what??
  function line($char=NULL,$used=0){
    if(is_null($char)) $char = $this->chars[$this->level];
    return $this->repeat($char,$this->width-$used);
  }
  */

  function repeat($str,$len){
    return substr(str_repeat($str,ceil($len/strlen($str))),0,$len);
  }
}

?>
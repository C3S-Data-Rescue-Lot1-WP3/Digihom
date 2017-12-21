<?php
  /*
   bug: siehe tst_item beispiel nested table fals regel für tab-rahmen
        auf table//table gestellt wird, erhält auch die äussere einen!

    n: mit ops_estring::pages2array ausbauen -> konkrete Zahlen

  opc_info is used to share information between different classes on a weak
  base. Information can be saved under a simple name, but also using a complex
  structer similar to xml/xhtml/html. That means if a class asked for information
  it may give opc_info its current state (position in xhtml) and opc_info
  returns all information which are suitable.

  opc_info is per name singleton. opc_info can not be constructed using new.
  You have to call the static function init, which needs a key as argument.
  all calls of init with the same key will return the same instance. therefore
  this key can be used to prevent conflicts between different instances.

  opc_info has two main  usages: Set and Get.
  There are different ways how set and get write/access thei data

  key based: Set and Get using a unique key to the asked information

  path based: Similar to a (double) nested array

  cue based: similar to path but the order does not care (key words)

  pattern based: instead of a key a grep style pattern is used.

  structer based: In this case Set uses a complex string which represents a ruel
    Get on the otherside submits instead of key information about his current
    position (structer information). opc_info will return all information
    saved using a path if their ruel-string matchs the current structer
 
    

    /tag/tag

    adds: #pos $name %class &id style @attr
    


  non unique information:
    if set transmitt a array as value, you may define if this value
    replaces the current or how it will be merged.

    since multiple rules/pattern may match a single information, they may
    be unclear situations. To solve this ituation two things are implemented.
    On one hand Set may submit a priority for each value. And on the other side
    Get may submit a behaviour for such a situation. 
    As example: priority first last merge biggest paste ... (or a combination)
  

  key and pattern may be mixed. That means a informations aved using a key
  can be read ou uisng a pattern and vice versa.

  Load:

  info enthält eine Ansammlung von Regeln.
  Eine Regeln besteht aus einem test und einer Reaktion
  
  info ist eine pseudo-singleton
  der konstru
  


  der folgende Syntax dient dazu zu erkennen welche regeln am aktuellen Ort
  benutzt werden müssen.

  Trennzeichen
  /  als eben trenner, Als Startzeichen: von absoluter pfad
  #  als positionstrenner (optional, default: akzeptiert jede Position)
  @  als Attributtrenner
  "  als Text-Parser innerhalb \\ und \" benutzen!
  $  als Prefix zu " -> unserialize auf den text anwenden

  () Gruppierung

  Vergleiche und Logik
  !  kehrt bedeutung um
  = < > >= <= standardvergleiche
  | & logisches 'oder' und 'und'

  ~  Testet ob B in A enthalten
  ~< Testet ob der Anfang von A ist
  ~> Testet ob B das Ende von A ist

  mode-XXX[Vergleich] spezialvarianten
   XXX str:  keine umwandlungen
       stri: umwandlung in lowercase
       size: umwandlung mit count/strlen
       int:  umwandlung in integer
       num:  umwandlung in float
       date: umwandlung in date
  

  */

interface opi_info{
  function set($val,$mode='replace');
  function get($def=NULL);
  function set_add($val,$mode='replace');
  function get_add($def=array());
  function match($pat);
  }

class opc_info implements opi_info{
  protected $data = NULL;
  protected $add = array();

  function __construct($data=NULL){
    $this->set($data,'replace');
  }

  function auto($typ=NULL,$val=NULL){
    $cls = 'opc_info_' . $typ;
    if(!class_exists($cls)) $cls = 'opc_info';
    return new $cls($val);
  }

  function set($val,$mode='replace'){
    switch($mode){
    case 'replace': $this->data = $val; return TRUE;
    }
  }

  function get($def=NULL){
    return is_null($this->data)?$def:$this->data;
  }

  function set_add($add,$mode='replace'){
    $this->add = $add;
  }

  function get_add($def=NULL){
    return is_null($this->add)?$def:$this->add;
  }


  function match($pat){
    if($pat===$this->data) return TRUE;
    if(substr($pat,0,1)=='!'){
      $pat = explode(' ',substr($pat,1),2);
      $mth = '_match_' . $pat[1];
      if(!method_exists($this,$mth)) return FALSE;
      return $this->$mth($pat[1]);
    }
    return FALSE;
  }

}

class opc_infopointer{
  protected $path = array();
  protected $key = NULL;
  protected $target = NULL;
  protected $offset = 0;
  
  function __construct(&$target,$key=NULL,$offset=0){
    $this->target = &$target;
    $this->key = $key;
    $this->path = array();
    $this->offset = 0;
  }
  function __get($key){
    if($key=='level') return count($this->path)+$this->offset;
    return isset($this->$key)?$this->$key:NULL;
  }
  

  function root(){$this->path = array();}

  function open($tag,$attr=array()){
    if(!is_array($attr) and !($attr instanceof opc_attrs))
      return trg_err(1,'not usable as attribute: ' . var_export($attr,TRUE));
    if(!isset($attr['tag']) or is_null($this->attr['tag'])) $attr['tag'] = $tag;
    if(is_array($attr)) $attr = new opc_attrs($tag,$attr);
    $this->path[] = $attr;
  }

  function close(){if(count($this->path)>0) array_pop($this->path); else return FALSE;}

  function down(/* ..*/){
    $ar = func_get_args();
    foreach($ar as $cr) $this->path[] = $cr;
  }

  function up($n=1){
    while(count($this->path)>0 and $n-->0) array_pop($this->path);
  }

  function next($element){
    $this->up(1);
    $this->down($element);
  }
}

class opc_infosystem{
  protected static $instance;
  protected $key = 'global';

  /** the real data storage */
  protected $data = array();
  /** data using a simple id */
  protected $id = array();
  /** data using a double-nested aray */
  protected $path = array();
  /** data using keywords */
  protected $cue = array();
  /** data using a pattern */
  protected $pat = array();
  /** data using a structer-rule */
  protected $sruel = array();
  /** list of cue-keys (array(int-key=>cue-key-array)) */
  protected $cue_keys = array();//saves non numeric/string keys
  /** list of ruel-definitions */
  protected $sruels = array();//saves non numeric/string keys

  /** current (internal) key */
  protected $ckey = 0; // used for data

  /** default behaviour if a key is already used */
  protected $def_merge = 'replace';

  protected $cls_data = 'opc_info';

  protected $pointer = array();

  /** singleton constructor */
  public static function init($key='global'){
    if(is_object($key)){
      if($key instanceof opc_info) $key = $key->key;
      else trigger_error("not able to create an opc_info from a "  . get_class($key),E_USER_WARNING);
    }
    if(!isset(self::$instance[$key])){
      $cls = __CLASS__;
      self::$instance[$key] = new $cls();
      self::$instance[$key]->key = $key;
    }
    return self::$instance[$key];
  }
    
  /** denies cloning (since it is a singleton */
  public function __clone(){ trigger_error(__CLASS__ . ' is a singleton.', E_USER_ERROR);}

  /** returns the next (internal) key */
  protected function nkey() {return $this->ckey++;}

  /** read-only access to some variables */
  function __get($key){
    if(in_array($key,array('key','ckey','data',
			   'id','path','cue','sruel',
			   'sruels'))) return $this->$key;
    trg_err(0,'Unallowed read-access to opc_info: ' . $key);
  }

  /** resets one or mor info-sections */
  function reset($which=NULL){
    if(is_null($which)){
      $this->ckey = 0;
      $this->data = array();
      $this->id = array();
      $this->path = array();
      $this->cue = array();
      $this->cue_keys = array();
      $this->pat = array();
      $this->str = array();
      $this->sruel = array();
      $this->sruels = array();
    } else {
      $which = array_intersect(array('id','path','cue','pat','sruel'),(array)$which);
      foreach($which as $cc){
	foreach($this->$cc as $ck) unset($this->data[$cc]);
	$this->$cc = array();
	if($cc=='cue')$this->cue_keys = array();
	else if($cc=='sruel') $this->srules    = array();
      }
    }
    
  }

  function get_pointer($key=NULL){
    if(is_null($key)){
      do{$key = rand();} while(isset($this->pointer[$key]));
    } else if(isset($this->pointer[$key])){
      return $this->pointer[$key];
    }
    $this->pointer[$key] = new opc_infopointer($this,$key);
    return $this->pointer[$key];
  }
  
  function close_pointer($key){
    if(isset($this->pointer[$key])) unset($this->pointer[$key]);
  }

  /** saves a new item to a given store */
  function _set($store,$key,$val,$att){
    if(!isset($this->{$store}[$key])){
      $ck = $this->nkey();
      $this->{$store}[$key] = $ck;
    } else $ck = $this->{$store}[$key];
    if(!uses_interface($val,'opi_info')) $val = new $this->cls_data($val);
    $this->data[$ck] = $val;
    if(!is_null($att) and $att!==array()) 
      $this->data[$ck]->set_add($att);
    return $ck;
  }

  /** set new value to the id-store */
  function setID($key,$val,$merge='replace',$att=array()){
    if(isset($this->id[$key])) return $this->data[$this->id[$key]]->set($val,$merge,$att);
    return $this->_set('id',$key,$val,$att);
  }
  
  /** same as {@link setID} using a named array */
  function setnID($arr,$merge='replace',$att=array()){
    foreach($arr as $key=>$val) $this->setID($key,$val,$merge,$att);
  }

  /** get a value from the id-store (or pat-store if not found in id) */
  function getID($key,$def=NULL,$obj=FALSE){
    if(!isset($this->id[$key])) 
      return $this->get_from_pattern($key,$def,$obj);
    else if($obj)
      return $this->data[$this->id[$key]];
    else
      return $this->data[$this->id[$key]]->get();
    
  }

  function unsetID($key){
    if(!isset($this->id[$key])) return FALSE;
    unset($this->data[$this->id[$key]]);
    unset($this->id[$key]);
    return TRUE;
  }

  /** set new value to the path-store */
  function setPath($key,$val,$merge='replace',$att=array()){
    if(is_array($key)) $key = implode('/',$key);
    if(isset($this->path[$key])) return $this->data[$this->path[$key]]->set($val,$merge,$att);
    return $this->_set('path',$key,$val,$att);
  }

  /** get a value from the path-store */
  function getPath($key,$def=NULL,$obj=FALSE){
    if(is_array($key)) $key = implode('/',$key);
    if(!isset($this->path[$key])) 
      return $def;
    else if($obj)
      return $this->data[$this->path[$key]];
    else
      return $this->data[$this->path[$key]]->get();
    
  }

  /** set new value to the cue-store */
  function setCue($cue,$val,$merge='replace',$att=array()){
    if(!is_array($cue)) $cue = array($cue);
    else sort($cue);
    $key = implode('/',$cue);
    if(isset($this->cue[$key])) return $this->data[$this->cue[$key]]->set($val,$merge,$att);
    $ck = $this->_set('cue',$key,$val,$att,$cue);
    $this->cue_keys[$ck] = $cue;
    return $ck;
  }

  /** get a value from the cue-store
   * @param int $mode: 0: cue == saved 1: cue smalle than saved 2: saved is smaller than cue
   */
  function getCue($cue,$def=NULL,$mode=0,$obj=FALSE){
    if(!is_array($cue)) $cue = array($cue);
    switch($mode){
    case 0:
      sort($cue);
      $key = implode('/',$cue);
      if(!isset($this->cue[$key])) 
	return $def;
      else if($obj)
	return $this->data[$this->cue[$key]];
      else
	return $this->data[$this->cue[$key]]->get();
      break;

    case 1:
      $res = array();
      foreach($this->cue_keys as $ck=>$cv){
	if(count(array_diff($cue,$cv))==0){
	  $tk = array_search($ck,$this->cue);
	  if($obj)
	    $res[$tk] = $this->data[$ck];
	  else
	    $res[$tk] = $this->data[$ck]->get();
	}
      }
      break;

    case 2:
      $res = array();
      foreach($this->cue_keys as $ck=>$cv){
	if(count(array_diff($cv,$cue))==0){
	  $tk = array_search($ck,$this->cue);
	  if($obj)
	    $res[$tk] = $this->data[$ck];
	  else
	    $res[$tk] = $this->data[$ck]->get();
	}
      }
      break;
    }
    return count($res)==0?$def:$res;
  }

   /** set new value to the pat-store */
  function setPat($pat,$val,$merge='replace',$att=array()){
    if(isset($this->pat[$pat])) return $this->data[$this->pat[$key]]->set($val,$merge,$att);
    return $this->_set('pat',$pat,$val,$att);
  }

  /** get all values from the id-/path-/pat-store using a key */
  function getPat($key,$def=NULL,$obj=FALSE){
    $res = array();
    if(isset($this->id[$key])){
      if($obj)
	$res[$key] = $this->data[$this->id[$key]];
      else
	$res[$key] = $this->data[$this->id[$key]]->get();
    }
    if(isset($this->path[$key])) $res[$key] = $this->data[$this->path[$key]]->get();
    foreach($this->pat as $pat=>$dkey){
      if(preg_match($pat,$key)){
	if($obj)
	  $res[$pat] = $this->data[$dkey];
	else
	  $res[$pat] = $this->data[$dkey]->get();
      }
    }
    return count($res)==0?$def:$res;
  }

  /** checks if key matchs one of the saved patterns */
  protected function get_from_pattern($key,$def){
    $res = array();
    foreach($this->pat as $pat=>$dkey)
      if(preg_match($pat,$key)) 
	$res[$pat] = $this->data[$dkey]->get();
    return count($res)==0?$def:$res;
  }

  /** get all values from the id- or path-store using a pattern */
  function getByPat($pat,$def=NULL,$obj=FALSE){
    $res = array();
    foreach($this->id as $key=>$dkey){
      if(preg_match($pat,$key)){
	if($obj)
	  $res[$key] = $this->data[$dkey];
	else 
	  $res[$key] = $this->data[$dkey]->get();
      }
    }
    foreach($this->path as $key=>$dkey){
      if(preg_match($pat,$key)){
	if($obj)
	  $res[$key] = $this->data[$dkey];
	else
	  $res[$key] = $this->data[$dkey]->get();
      }
    }
    return count($res)==0?$def:$res;
  }

  /** get all keys from the id- or path-store using a pattern */
  function getKeyByPat($pat,$def=NULL){
    $res = array();
    foreach($this->id as $key=>$dkey)   if(preg_match($pat,$key)) $res[] = $key;
    foreach($this->path as $key=>$dkey) if(preg_match($pat,$key)) $res[] = $key;
    return count($res)==0?$def:$res;
  }

  /** get all int-ids from the id- or path-store using a pattern */
  function getIDbyPat($pat,$def=NULL){
    $res = array();
    foreach($this->id as $key=>$dkey)   if(preg_match($pat,$key)) $res[] = $dkey;
    foreach($this->path as $key=>$dkey) if(preg_match($pat,$key)) $res[] = $dkey;
    return count($res)==0?$def:$res;
  }


  function setSRuel($ruel,$val,$merge='replace',$att=array()){
    if(isset($this->sruel[$ruel])) return $this->data[$this->sruel[$ruel]]->set($val,$merge,$att);
    if($this->prepare_sruel($ruel)) return $this->_set('sruel',$ruel,$val,$att);  
    trg_err(1,'Invalid ruel: ' . $ruel);
    return FALSE;
  }

  function unsetSRuel($ruel){
    if(!isset($this->sruel[$ruel])) return FALSE;
    unset($this->sruels[$this->sruel[$ruel]]);
    unset($this->sruel[$ruel]);
    return TRUE;  
  }


  function getSRuel($str,$def=NULL){
    $str = array_values($str);
    $res = array();
    foreach($this->sruel as $key=>$val)
      if($this->check_sruel($str,$this->sruels[$key])) 
	$res[$key] = $this->data[$val];
    return count($res)==0?$def:$res;
  }

  /** standard textual replacments for ruels */
  protected function std_replace_sruel($ruel){
    $ruel = str_replace('//','/n:*/',$ruel);
    $ruel = str_replace('/*/','/n:*/',$ruel);
    return $ruel;
  }

  protected function prepare_sruel($key){
    $strings = ops_estring::extract_string($key,'$.$');
    $ns = count($strings);
    $ruel = $this->std_replace_sruel($strings[0]);
    if(substr($ruel,-1,1)=='/') $ruel = substr($ruel,0,-1);
    if(substr($ruel,0,1)=='/') $ruel = substr($ruel,1);
    else $ruel = 'n:*/' . $ruel;
    
    $ruel = ops_estring::explode(array('/',' '),$ruel,0,TRUE,FALSE);
    $res = array();
    foreach($ruel as $ck=>$cv){
      foreach($cv as $tv){
	$tv = explode(':',$tv,2);
	$tk = count($tv)==1?'tag':trim(array_shift($tv));
	$tv = trim($tv[0]);
	for($i=1;$i<$ns;$i++) $tv = str_replace('$' . $i . '$',$strings[$i],$tv);
	$res[$ck][$tk] = $tv;
      }
      if(!isset($res[$ck]['n'])) $res[$ck]['n'] = 1;
    }
    $this->sruels[$key] = $res;
    return TRUE;
  }


  protected function check_sruel($str,$ruel){
    $crl = count($ruel);         // current sub-ruel to use
    $csl = array(count($str)-1); // current structer levels to check
    while($crl-->0){             // start at the innerst ruel!
      $cr = $ruel[$crl];
      $n = $cr['n'];             // number of occurence
      if($n==='0') continue;     // 0 means igonre this part of the ruel
      if(count($cr)>1){
	$res = array();
	foreach($csl as $cp){
	  list($min,$tp) = $this->_sruel_tpos($cp,$n);
	  $hits = 0;
	  foreach($tp as $ctp){
	    if($this->_check_sruel(def($str,$ctp),$cr)) $hits++;
	    else continue 2;
	  } 
	  if($hits<$min) break;

	  $tp = array_slice($tp,min($min-1,0),$hits);
	  if($min==0) $res[] = $tp[0];
	  foreach($tp as $ctp) $res[] = $ctp-1;
	}
	$csl = array_unique($res);
      } else { // count $cr==0
	$csl = $this->_sruel_npos($csl,$n); // anything
	if($crl==0 and min($csl)==0) return TRUE;
      }
      if(count($csl)==0) return FALSE;
    }
    return min($csl)<0;
  }

  protected function _check_sruel($str,$ruel){
    foreach($ruel as $key=>$val){
      if($key==='n') continue;
      $obj = def($str,$key);
      if($obj instanceof opi_info){
	if($obj->match($val)==FALSE) return FALSE;
      } else {
	$mth = '_check_sruel_' . $key;
	if(!method_exists($this,$mth)) $mth = '_check_sruelBase';
	if($this->$mth($obj,$val,$key)==FALSE) return FALSE;
      }
    }
    return TRUE;
  }

  protected function _check_sruelBase($str,$ruel,$key){
    if($str===$ruel) return TRUE;
    return FALSE;
  }

  /** defines which structer-levels has to be checked depending from the current level and ruel-n 
   * @return: array(minimum of hits, array of structer-levels to test)
   */
  protected function _sruel_tpos($csl,$n){
    if($n=='*') return array(0,range($csl,0,-1));
    if($n=='+') return array(1,range($csl,0,-1));
    if($n=='?') return array(0,array($csl));
    if(strpos($n,'-')===FALSE) return array((int)$n,range($csl,$csl+1-$n,-1));
    $n = explode('-',$n);
    return array((int)$n[0],range($csl,$csl+1-$n[1],-1));
  }

  /** defines the next possible structer levels for a ruel-n only*/
  protected function _sruel_npos($csl,$n){
    if($n=='*') return range(max($csl),0,-1);
    if($n=='+'){
      $csl = max($csl);
      return $csl==0?array():range($csl-1,0,-1);
    }
    if($n=='?'){
      $res = $csl;
      foreach($csl as $cl) if($cl>0) $res[] = $cl-1;
      return array_unique($res);
    }
    if(strpos($n,'-')===FALSE){
      $n = (int)$n;
      $res = array();      
      foreach($csl as $cl) if($cl>=$n) $res[] = $cl-$n;
      return $res;
    }
    $n = explode('-',$n);
    $m = (int)$n[1];
    $n = (int)$n[0];
    $res = array();
    $res = array();      
    for($i=$n;$i<=$m;$i++)
      foreach($csl as $cl) if($cl>=$i) $res[] = $cl-$i;
    return array_unique($res);
  }
    
}


?>
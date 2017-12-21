<?php
/* php-Class for xml data and files
  Created on 25.05.2006

  bug remove can lead to negative numbers!
  bug write produces amny blank lines before first element

   im/export nested arrays
   im/export dom

   method charset for for writing

   BUG: bei einigen schreibprozessen ist ein sort vorher noetig! (siehe tst_java.php/tajax_c.php ajax2a)

 */

  /* common notes
     argument keys:
       null -> all
       [/string/] -> uses preg_match
       [string] -> uses ==
       array(strings) -> multiple !
   */

include_once('opc_earr.php');
class opc_xml extends opc_earr {
  
  public $data = array();

  // will be set by constructor or read_file
  var $basekey = NULL; // bas key (eg /xml#0/data)
  var $basetag = NULL; // base tag (eg data)
  
  // errorlog
  var $nerr = 10;       // max size of err
  var $err = array();	// last occured errors
  var $errs = array(1=>'file does not exist',
		    5=>'operation not possible',
		    10=>'invalid name',
		    100=>'non valid xml');
 
  var $die_on_error = TRUE;

  public $filename = NULL;

  //constants
  var $namepat = '/^[_a-zA-Z][-_a-zA-Z0-9]*$/';
  var $nameipat = '[_a-zA-Z][-_a-zA-Z0-9]*';
  var $pathpatterns = array(0=>'/\/[_a-zA-Z][-_a-zA-Z0-9]*$/',//node
			    1=>'/#[0-9]+\/$/',                //data
			    2=>'/@[_a-zA-Z][-_a-zA-Z0-9]*$/', //attr
			    -1=>'//');                        //dummy for all the rest  
  var $pospat = '#[0-9]+/';
  var $pospat_ext = '#([><]|[><]?[0-9]+)/';

  // for parsing only (internal)
  var $xmlp = null; // the parser itself
  var $xmlcharset = 'UTF-8'; // or ISO-8859-1 or ...
  var $xmlpath = array('/xml');	//current path
  var $xmlmode = array(0); // current mode
  var $xmlpos = array(0); // current position inside a node
  var $xmllast = null; // last text data

  var $phpcharset = 'UTF-8';

  var $readmodes = array('/./'=>0); //modeselection
  var $writemodes = array('/./'=>0); //modeselection
  var $indent = '   '; // used for showing structure by indents
  var $width = 80; // prefered line width; <20 -> no limit

  /* readmodes: defines how to read/write the (text-)data   
   defined as named array where the key is regex and value a number
   the positive values can be used additional (but not all combinations of course)
   they options will be checked/used in the same order a listed below
    READ
     -3: read the childs  flat node (useful for xhtml) attributes stay as usual
     -2: read as if it it a CDATA-Structure. Attention if subnodes exists, this may change the content
     -1: read content as flat node (useful for xhtml) the result is a single child textdata

      0: default: Read in as it is (means: ignore nothing!)
      1: use this mode for all subnodes too
      2: ignore string data 
      4: ignore child nodes 
      8: ignore attributes
    256: replace tabs by spaces  (9 -> 32)
     16: adjust newlines (Ascii 10/13 -> 10)
    512: removes multiple spaces at the beginnig and end of lines
     32: remove empty lines (10 only!) at the beginning and end of textdata
   4096: replace single newlines by spaces multiple newlines by double newlines ( <-> 64/128)
     64: remove empty lines (10 only!) inside textdata
    128: replace newline by spaces (10 -> 32)
   1024: replace multiple spaces inside the textdata by a sinlge one
   2048: each line as an item (instead one for all)
  */
  
  /*
    WRITE // not yet completed
     -2: Write text-data as CDATA-Structer (allways)
     -1: Write text directly (it should be xml/xhtml conform!)
      0: default (write everything as it is)
      1: use this mode for subnodes too.
      2: do not use the short from for an empty tag (<b></b> instead of <b/>)
      4: one attribute per line
      
   2048: one item per line (no ident)
      8: use indent inside the node (in attributes allways used)
     16: short nodes (without childnodes) on one line
     32: start child on a new line
     64: start text-data on a new line
    128: set endtag on a new line
    256: break textdata to multiple lines
    512: use indent inside multiple lines
   additionaly there are the following global settings for writting
   maxlinesize: default: 80, 0->unlimited (this is not a hard limit!)
   ident: default: 2 spaces
   */
  
  //Constructor
  function __construct($root=null,$settings=array()){
    if(is_string($settings)) $settings = array('phpcharset'=>$settings);

    if(is_string($root)){
      $settings[substr($root,0,5)==='<?xml'?'xmlstring':'filename'] = $root;
    } else if(is_array($root)){
      $settings['data'] = $root;
    }

    $this->data = array();

    $keys = array('phpcharset','width','indent','writemodes','readmodes','filename',
		  'key','basekey','basetag');
    foreach($settings as $key=>$val) if(in_array($key,$keys)) $this->$key = $val;

    if(isset($settings['data']) and is_array($settings['data']) and count($settings['data'])>0){
      $this->data = $settings['data'];
    } else if(isset($settings['xmlstring'])){
      $this->read_array($settings['xmlstring']);
    } else if(isset($settings['filename']) and file_exists($settings['filename'])){
      $this->read_file($settings['filename']);
    }
    if(count($this->data)>0){ 
      $ak = array_keys($this->data);
      $this->basekey = $ak[0];
      $this->basetag = preg_replace('|.*/|','',$this->basekey);
    } else if(!empty($this->basekey)){
      $this->basetag = preg_replace('|.*/|','',$this->basekey);
      $this->data = array($this->basekey=>0);
    } else if(!empty($this->basetag)){
      $this->basekey = '/xml#0/' . $this->basetag;
      $this->data = array($this->basekey=>0);
    } else {
      $this->basetag = 'data';
      $this->basekey = '/xml#0/' . $this->basetag;
      $this->data = array($this->basekey=>0);
    }
    $this->key = $this->basekey;
    $this->pos = 0;
  }

  function isempty(){return count($this->data)>0;}
  
  // Misc

  /* returns the type of the path
     0 => node
     1 => data
     2 => attr
     if $extended is true the part after the decimal is constructed by
     .1 => node has attributes
     .2 => node has text data
     .4 => node has child nodes
   */
  function type($path = null,$extended = false){
    $path = $this->cpath($path);
    if(empty($path)) return(false);
    reset($this->pathpatterns);
    while(list($ak,$av)=each($this->pathpatterns))
      if(preg_match($av,$path)) break;
    if(!$extended or $ak!=0) return($ak);
    // extended
    if(count($this->grep($qp . '@/',1)))          $ak += .1;
    if(count($this->grep($qp . '#[0-9]+\/$/',1))) $ak += .2;
    if(count($this->grep($qp . '#[0-9]+\/./',1))) $ak += .4;
    return($ak);
  }

  /* get the current pos of path (pos of child at the parents) */ 
  function pos_get($path = null){
    $path = $this->cpath($path);
    if(empty($path)) return(false);
    switch($this->type($path)){
    case 0:
      $tv = substr($path,0,strrpos($path,'/'));
      return((int)substr($tv,strrpos($tv,'#')+1));
    case 1: return((int)substr($path,strrpos($path,'#')+1));
    }
    return(false);	
  }
  
   /* get the level of current path */ 
  function level_get($path = null){
    $path = $this->cpath($path);
    if(empty($path)) return(false);
    $tv = str_replace('/','',$path);
    return(strlen($path)-strlen($tv)-2); 
  }
  
  /* get the current node name of path */ 
  function name_get($path=null){
    $path = $this->cpath($path);
    if(empty($path)) return(false);
    switch($this->type($path)){
    case 0: $path = substr($path,strrpos($path,'/')+1);	break;
    case 1:
      $path = substr($path,0,strrpos($path,'#'));
      $path = substr($path,strrpos($path,'/')+1);
      break;
    case 2:	$path = substr($path,strrpos($path,'@')+1);	break;
    default: return(false);	
    }
    return($path); 
  }

  function name_set($newname,$path=null){
    if(!$this->check_name($newname,true)) return(false);
    $path = $this->cpath($path);
    if(empty($path)) return(false);
    switch($this->type($path)){
    case 0: 
      $npath = preg_replace('/\/[^\/]*$/','/' . $newname,$path);
      $path = preg_quote($path,'/');
      $this->replace('/^' . $path . '/', $npath,1);
      break;
    case 1: $this->err_add(5,$path); return(false);
    case 2: 
      $npath = preg_replace('/@[^@]*$/','@' . $newname,$path);
      $path = preg_quote($path,'/');
      $this->replace('/^' . $path . '$/', $npath,1);
      break;
    default: return(false);	
    }
    return(true);
  }

  // reads a single attribute
  function attr_get($name,$path=null,$default=null){
    if(!$this->check_name($name,true)) return(false);
    if(is_null($path)) $path = './@' . $name; 
    else if(substr($path,0,1)=='$') $path = $this->cpath($path) . '@' . $name;
    else $path .= '@' . $name;
    
    return($this->value_get($path,$default));
  }

  // reads multiple attributes
  // keys: one name or pattern (incl //) or an array of theme
  function attrs_get($keys=null,$path=null,$def=array()){
    $keys = $this->path_attr($keys,$path);
    if(!is_array($keys)) return(null);
    $attr = $this->getn($keys);
    if(count($attr)==0) return($def);
    $ak = array_keys($attr);
    $pn = strpos(array_shift($ak),'@')+1;
    while(list($ak,$av)=each($attr)) $def[substr($ak,$pn)] = $av;
    return($def);
  }


  // removes attributes
  function attrs_del($keys=null,$path=null){
    $keys = $this->path_attr($keys,$path);
    if(!is_array($keys)) return(null);
    $attr = $this->deln($keys);
  }

  //returns the name of childs
  function attrs_keys($path=null){
    $path = $this->cpath($path);
    $cn = $this->grep($this->ppath('/%C@%N$/'),3);
    $cn = preg_replace('/^.*@(.*)$/','$1',$cn);
    return(array_values($cn));
  }

  //returns the name of childs
  function attr_exists($name,$path=null){
    $path = $this->cpath($path);
    switch($this->type($path)){
    case 1: return(FALSE);
    case 2: $path = substr($path,0,strpos($path,'@')+1) . $nam; break;
    case 0: $path .= '@' . $name; break;
    }
    return(array_key_exists($path,$this->data));
  }

  function attrs_set($arr,$path=null){
    $path = $this->cpath(is_null($path)?'./':$path);
    if(empty($path)) return(null);
    if($this->type($path)!=0) return(null);
    $pat = '/^' . preg_quote($path,'/') . '(@' . $this->nameipat . ')?$/';
    $lk = array_keys(preg_grep($pat,array_keys($this->data)));
    $lk = array_pop($lk);
    $old = array();
    while(list($ak,$av)=each($arr)){
      $apath = $path . '@' . $ak; 
      if(!array_key_exists($apath,$this->data)){
	if(!is_null($av)){
	  $this->insert(array($apath=>$av),$lk);
	}
	$old[$ak] = null;
      } else{
	$old[$ak] = $this->data[$apath];
	if(is_null($av)){
	  unset($this->data[$apath]);
	  $tk = array_keys(preg_grep($pat,array_keys($this->data)));
	  $lk = array_pop($tk);
	} else {
	  $this->data[$apath] = $av;
	}
      }
    }
    return($old);
  }


  // sets an attribute; value=null -> remove
  function attr_set($name,$value=null,$path=null){
    return($this->attrs_set(array($name=>$value),$path));
  }

  // removes attributes
  function attr_del($name,$path=null){
    $path = $this->cpath($path);
    switch($this->type($path)){
    case 1: return(FALSE);
    case 2: $path = substr($path,0,strpos($path,'@')+1) . $nam; break;
    case 0: $path .= '@' . $name; break;
    }
    $this->del($path);
    return TRUE;
  }


  // gets a single value
  function value_get($path=null,$default=null){
    $path = $this->cpath($path,FALSE);
    if(empty($path)) return($default);
    if(!array_key_exists($path,$this->data)) return($default);
    switch($this->type($path)){
    case 0: 
      $nm = new opc_xml();
      $ar = array();
      $ak = $this->grep('/^' . preg_quote($path,'/') . '/',1);
      foreach($ak as $bk) $ar[substr($bk,strlen($path))] = $this->data[$bk];
      $nm->flush($ar);
      return($nm);
      break;
    case 1:
    case 2:
      return($this->data[$path]);
    }
    return(null);
  }
  
  // get a single value by key-pattern
  function value_getp($path=null,$default=null){
    $path = $this->ppath($path);
    $keys = $this->keys_get($path);
    return(count($keys)==1?$this->value_get($keys[0]):$default);
  }

  //search existing values by one or more key patterns (returns a named array)
  function values_get($pathes){
    $pathes = $this->ppath($pathes);
    return($this->getn($pathes));
  }

  /** search existing values
   * @param pattern $pathes
   * @param mixed $value: searched value
   * @param int $mode: 0: normal, 1: $value is a pattern, -1: ignore $value
   * @param string $iter: if not null retuns the key of the result after iteration
   */
  function values_search($pathes,$value=0,$mode=0,$iter=NULL){
    $pathes = $this->ppath($pathes);
    $vals = $this->getn($pathes);
    switch($mode){
    case 0: 
      $res = array();
      foreach(array_keys($vals,$value,TRUE) as $ck) $res[$ck] = $value;
      break;
    case 1: $res = preg_grep($value,$vals); break;
    default:
      $res = $vals;
    } 
    if(is_null($iter) or !is_array($res) or count($res)==0) return $res;
    $tmp = array();
    foreach(array_keys($res) as $cr) $tmp[] = $this->iter($iter,$cr,FALSE);
    return $tmp;
  }

  function value_search($pathes,$value,$def=NULL,$mode=0){
    $key = $this->values_search($pathes,$value,$mode);
    if(count($key)!=1) return($def);
    $key = array_keys($key);
    return($this->data[$key[0]]);
  }
  
  // search an attr and returns all of them
  function attrs_search($pathes,$value,$attr=NULL,$def=NULL){
    $key = $this->values_search($pathes,$value);
    if(count($key)!=1) return($def);
    $key = array_keys($key);
    $key = $this->iter('node',$key[0],FALSE);
    return($this->attrs_get($attr,$key,$def));
  }

  // search an attr and returns a different one
  function attr_search($pathes,$value,$attr=NULL,$def=NULL){
    $key = $this->values_search($pathes,$value);
    if(count($key)!=1) return($def);
    if(is_null($attr)) return array_shift($key);
    $key = array_keys($key);
    $key = $this->iter('node',$key[0],FALSE);
    return($this->attr_get($attr,$key,$def));
  }

  function keys_search($pathes,$iter=NULL){
    $pathes = $this->ppath($pathes);
    $res = $this->keys($pathes);
    if(is_null($iter) or !is_array($res) or count($res)==0) return $res;
    $tmp = array();
    foreach($res as $cr) $tmp[] = $this->iter($iter,$cr,FALSE);
    return $tmp;
  }

  
  function key_search($pathes,$value=0,$iter=NULL,$set=FALSE){
    $key = $this->values_search($pathes,$value);
    if(count($key)!=1) return(NULL);
    $key = array_keys($key);
    $key = $key[0];
    if(!is_null($iter)) $key = $this->iter($iter,$key,FALSE);
    if($set) $this->set_curkey($key);
    return($key);
  }


  //search existing keys by one or more patterns (returns an array)
  function keys_get($pathes){
    $ar = array();
    if(!is_array($pathes)) $pathes = array($pathes);
    foreach($pathes as $cp){
      if(array_key_exists($cp,$this->data))
	$ar[] = $cp;
      else
	$ar = array_merge($ar,preg_grep($this->ppath($cp),array_keys($this->data)));
    }
    return(array_unique($ar));
  }

  /** array version of attrs_get
   * @param string|NULL $keyattr: if null path is used as array key otherwise the given attribute itself
   * @return: named array of attributes
   */
  function attrs_array($keys,$pathes,$def=array(),$keyattr=NULL){
    $res = array();
    foreach($pathes as $cp) 
      if(is_null($keyattr))
	$res[$cp] = $this->attrs_get($keys,$cp,$def);
      else if($keyattr===TRUE)
	$res[$this->name_get($cp)] = $this->attrs_get($keys,$cp,$def);
      else
	$res[$this->attr_get($keyattr,$cp,$cp)] = $this->attrs_get($keys,$cp,$def);
    return $res;
  }

  function attrs_list($pathes,$key_val,$key_key=NULL,$def=NULL){
    $res = array();
    foreach($pathes as $cp) 
      $res[is_null($key_key)?$cp:$this->attr_get($key_key,$cp,$cp)] = $this->attr_get($key_val,$cp,$def);
    return $res;
  }

  function remove($path=null,$renumber=TRUE){
    $path = $this->cpath($path);
    if(empty($path)) return(false);
    if(!array_key_exists($path,$this->data)) return;
    switch($this->type($path)){
    case 0: 
      $ar = preg_grep('/^' . preg_quote($path,'/') . '/',array_keys($this->data));
      foreach($ar as $ck) unset($this->data[$ck]);
      break;
    case 1:
      unset($this->data[$path]);
      break;
    case 2:
      unset($this->data[$path]);
      return(true);
    default:
      return(false);
    }
    $px = strrpos($path,'#');
    if($renumber) $this->renumber_part(substr($path,0,$px),(int)substr($path,$px+1)+1,-1);
    return(true);
  }

  /* extract a node and all his subitems (as array)
   mode 0=> as it is, 1=>insert parent nodes to complete structer, 2=> simplify structer
   save (doe not work in mode=0)
         TRUE/1: replace current data
         2: creates a clone
  */ 
  function node_extract($path=null,$mode=0,$save=false){
    $path = $this->cpath($path);
    if(empty($path)) return(false);
    
    $pat = $this->ppath('/^' . preg_quote($path,'/') . '(@%N|%P|%P=.*)?$/');
    $cc = $this->grep($pat,1);
    if($mode==0) return($cc);
    $ak = array_keys($cc);
    $res = array();
    switch($mode){
    case 1:
      $bk = substr($ak[0],0,strrpos($ak[0],'/'));
      $bl = strlen($bk);
      $bk = explode('/',preg_replace('/#[0-9]+/','',$bk));
      if($bk[0]=='') array_shift($bk);
      for($ci=0;$ci<count($bk);$ci++) $res['/' . implode('#0/',array_slice($bk,0,$ci+1))] = 0;
      $bk = '/' . implode('#0/',$bk) . '#0';
      while(list($ak,$av)=each($cc)) $res[$bk . substr($ak,$bl)] = $av;
      break;
    case 2:
      if(!isset($ak[0])) break;
      $bk = strlen(substr($ak[0],0,strrpos($ak[0],'/')));
      while(list($ak,$av)=each($cc)) $res[substr($ak,$bk)] = $av;
      break;
    }
    if($save===TRUE or $save===1) {
      $this->data = $res;
      $this->iter('first');
    } else if($save==2){
      $xres = clone $this;
      $xres->data = $res;
      $ak = array_keys($res);
      $xres->key = def($ak,0);
      if(count($ak)>0){
	$xres->basekey = $ak[0];
	$xres->basetag = preg_replace('|.*/|','',$ak[0]);
      } 
      
      $res = $xres;
    }
    return($res);
  }

  //  what is additive: 1: attributes, 2: childs, 4: textdata
  function node_item_getkeys($path=null,$what=6){
    $res = $this->node_item_get($path,$what);
    return(is_array($res)?array_keys($res):array());
  }

  function node_item_get($path=null,$what=6){
    $path = $this->cpath($path);
    if($this->type($path)!=0) return(false);
    $pat = array(1=>'@%N',   2=>'%P=%N',   3=>'(@%N|%P=%N)',
		 4=>'%P',    5=>'(@%N|%P)',6=>'%P=(%N)?',      7=>'(@%N|%P|%P=%N)');
    $pat = $this->ppath('|^' . preg_quote($path) . $pat[$what] . '$|');
    return($this->grep($pat,1));
  }

  function node_item_count($path=null,$what=6){
    return(count($this->node_item_getkeys($path,$what)));
  }

  function text_replace($text,$path=null){
    $path = $this->cpath($path);
    if($this->type($path)!=1) return FALSE;
    $this->data[$path] = $text;
    return TRUE;
  }

  /* get used child positions
   * mode: 0: all as array (sorted)
   *       1: min or NULL
   *       2: max or NULL
   *       3: first free (default 0)
   *       4: max+1 or 0
   */
  function node_getnumber($path=NULL,$mode=0){
    $path = $this->iter('node',$this->cpath($path),FALSE);
    $cc = preg_grep('|^' . $path . '#\d+(/[^#/@]*)?$|',array_keys($this->data));
    if(count($cc)==0){
      switch($mode){
      case 0: return(array());
      case 1: case 2: return null;
      case 3: case 4: return 0;
      }
    }
    $ct = array();
    $pl = strlen($path)+1;
    foreach($cc as $ci) $ct[] = (int)substr($ci,$pl);
    switch($mode){
    case 0: sort($ct); return $ct;
    case 1: return min($ct);
    case 2: return max($ct);
    case 4: return max($ct)+1;
    case 3: 
      $nc = count($ct);
      if($nc==max($ct)+1) return($nc);
      for($ii=0;$ii<$nc;$ii++) if($ct[$ii]!=$ii) return($ii+1);
    }
  }

  //returns the name of childs
  function node_childs_names($path=null){
    $path = $this->cpath($path);
    $cn = $this->grep($this->ppath('/%C%P\/%N$/'),3);
    $cn = preg_replace('/^.*\/(.*)$/','$1',$cn);
    return(array_values($cn));
  }

  function xhtml_get($path=NULL,$def=NULL){
    $path = $this->cpath($path);
    if(empty($path)) return($def);
    return def($this->data,$path . '#0/',$def);
  }

  function node_childs_get($path=null){
    $path = $this->cpath($path);
    if(empty($path)) return(false);
    yy();
  }

  /* mpath path to insert the new node
   * other arguments: scalar and array
   * scalars -> text data
   * array values with numeric key -> text data scalar
   * array values with string key -> attributes
   */
  function node_insert($mpath/* */){
    $path = $this->mpath($mpath);
    if(is_null($path)) return NULL;
    $ar = array_slice(func_get_args(),1);
    $nar = count($ar);
    if($nar>0 and is_bool($ar[$nar-1])) $set = array_pop($ar);
    $set = FALSE;
    if(substr($path,-1)=='/'){
      $tmp = array();
      foreach($ar as $val){
	if(is_scalar($val)) $tmp[] = $val;
	else $tmp = array_merge($tmp,$val);
      }
      $this->data[$path] = implode("\n",$tmp);
    } else {
      $nc = 0;
      foreach($ar as $val){
	if(is_scalar($val))
	  $this->data[$path . '#' . $nc++ . '/'] = $val;
	else if(is_array($val)){
	  foreach($val as $ck=>$cv)
	    if(is_numeric($ck)) $this->data[$path . '#' . $nc++ . '/'] = $cv;
	    else                $this->data[$path . '@' . $ck] = $cv;
	}
      }
    }
    if($set) $this->set_curkey($path);
    return($path);
  }

  function node_node_insert($name,$path=null,$attr=array(),$childs=array(),$set=FALSE){
    $path = $this->iter('node',$this->cpath($path),FALSE);
    if(empty($path)) return(false);
    $cc = $this->node_getnumber($path,4);
    $ak = preg_grep("|^$path#|",array_keys($this->data));
    $nk = $path . '#' . $cc . '/' . $name;
    $nd = array($nk=>0);
    foreach($attr as $ck=>$cv) $nd[$nk . '@' . $ck] = $cv;
    $ii=0;
    foreach($childs as $cc) $nd[$nk . '#' . $ii++ . '/'] = $cc;
    $this->splice_data(array_pop($ak),0,$nd);
    if($set) $this->set_curkey($nk);
    return($nk);
  }
  
  function node_text_insert($text,$path=null,$set=FALSE){
    $path = $this->iter('node',$this->cpath($path),FALSE);
    if(empty($path)) return(false);
    $cc = $this->node_getnumber($path,4);
    $ak = preg_grep("|^$path#|",array_keys($this->data));
    $nk = $path . '#' . $cc . '/';
    $nd = array($nk=>$text);
    $this->splice_data(array_pop($ak),0,$nd);
    if($set) $this->set_curkey($nk);
    return($nk);
  }

  /* NAVIGATION
   * search the next asked path 
   * $path: if null (default) current path is used
   * $set: if true: currentpath will be set to the result (if found)
   *       default: true if path is null, false otherwise
   * $how: 
   *   global (all items) absolute:
   *     first, last
   *   global (all items) relative
   *     next, prev, this
   *   attributes 
   *     firstattr, nextattr, lastattr, prevattr
   *   childs (nodes and text-data)
   *     firstchild, lastchild
   *     firstsibling, nextsibling, lastsibling, prevsibling
   *   childnodes (nodes only, no text-data)
   *     firstchildnode, lastchildnode
   *     firstsiblingnode, nextsiblingnode, lastsiblingnode, prevsiblingnode
   *   others
   *     node: if path is an attr or data it will find the parent node
   *     parent: will find the parent node (also for nodes itself)  
   */
  function iter($how='next', $path=null, $set=true){
    $ak = array_keys($this->data); $nk = count($ak);
    if($nk==0) return(false);
    $path = $this->cpath($path);
    $typ = $this->type($path);
    switch($how){
    case 'first':  case 'last': case 'this': case 'next': case 'prev':
      return(parent::iter($how,$path,$set));

      // global (all items!) absolute -----------------------------
    case 'node':   
      switch($typ){
      case 1: $path = substr($path,0,strrpos($path,'#')); break;
      case 2: $path = substr($path,0,strrpos($path,'@')); break;
      } 
      break;
    case 'parent': $path = substr($path,0,strrpos($path,'#')); break;

      // Childs & Attributes ------------------
    case 'firstchild':  case 'lastchild':
    case 'firstsibling':  case 'lastsibling':
    case 'nextsibling':   case 'prevsibling':
    case 'firstchildnode':  case 'lastchildnode':
    case 'firstsiblingnode':  case 'lastsiblingnode':
    case 'nextsiblingnode':   case 'prevsiblingnode':
    case 'firstattr':  case 'lastattr':
    case 'nextattr':   case 'prevattr':
      $how = preg_replace('/(sibling|child|attr)/',' $1 ',$how);
      $how = explode(' ',$how);

      // catch things that not work
      if(($typ==2 and $how[1]!='attr') or ($typ==1 and $how[1]=='attr')
	 or ($typ!=0 and $how[1]=='child')) return(false);

      if($typ==0 and $how[1]!='sibling') 
	$tv = $path;
      else 
	$tv = substr($path,0,strrpos($path,$how[1]=='attr'?'@':'#'));
      $pat = preg_quote($tv,'/') . ($how[1]=='attr'?'@':'#[0-9]+\/');
      if($how[2]=='')	$pat .= '(' . $this->nameipat . ')?';
      else              $pat .= $this->nameipat;
      $tv = array_values(preg_grep('/^' . $pat . '$/',$ak));
      $nt = count($tv); if($nt==0) return(false);
      switch($how[0]){
      case 'first': $path = $tv[0]; break;
      case 'last':  $path = $tv[$nt-1]; break;
      case 'next':
	$path = array_search($path,$tv);
	if($path===false) $path = $tv[0];  
	elseif($path==$nt-1) return(false);
	else $path = $tv[$path+1];
	break; 
      case 'prev':
	$path = array_search($path,$tv);
	if($path===false) $path = $tv[$nt-1];  
	elseif($path==0) return(false);
	else $path = $tv[$path-1];
	break; 
      }
      break;
    default:       // captured by parent class opc_earr
      return(parent::iter($how,$path,$set));
    }
    if($set) $this->set_curkey($path);
    return($path); 	
  }
  
  function iter_val($how='next',$path=null,$set=true){
    $ck = $this->iter($how,$path,$set);
    if(is_null($ck)) return(null); else return($this->data[$ck]);
  }

  function iter_keyval($how='next',$path=null,$set=true){
    $ck = $this->iter($how,$path,$set);
    if(is_null($ck)) return(null); else return(array($ck,$this->data[$ck]));
  }

  /* jumps to key if it exists
   if key is a pattern it jumps only if just one element in data fits the pattern
   returns the key or FALSE
  */
  function jump($key,$set=TRUE){
    $key = $this->ppath($key);
    $key = $this->keys($key);
    if(count($key)==0) return(FALSE);
    if($set) $this->set_curkey($key[0]);
    return($key[0]);
    
  }
  
  // Error ======================================================================
  // resets the err-array
  function err_flush(){ $this->err = array();}
  
  // adds an errcode to the err-array, additional text allowed 
  function err_add($code,$text=''){
    while(count($this->err)>=$this->nerr) array_pop($this->err);
    $text = $code . ': ' . $this->errs[$code] . ' ' . $text;
    array_unshift($this->err,$text);
    return($code);
  } 

  // Input ======================================================================
  function write_file($filename=null,$sort=TRUE){
    $this->sort();
    if(is_null($filename)) $filename = $this->filename;
    $this->iter('first');
    $res = '<?xml version="1.0" encoding="' . $this->xmlcharset . '"?>'
      . "\n" .  $this->write_node();
    if(empty($filename)) return($res);
    $fi = @fopen($filename,'w');
    if($fi===false) return FALSE;
    fwrite($fi,$res);
    fclose($fi);
    return TRUE;
  }

  /* schriebt die child elemente von path (nicht aber die attribute)
   mode wird weitergereciht and write_node */
  function write_childs($path=null,$mode=0){
    $res = array();
    $items =$this->node_item_get($path,6);
    if(!is_array($items)) return(NULL);
    foreach(array_keys($items) as $ci){
      switch($this->type($ci)){
      case 0: $res[] = $this->write_node($ci,$mode); break;
      case 1: $res[] = $this->data[$ci]; break;
      } 
    }
    return($res);
  }

  function write_node($path=null,$mode=0,$level=0){
    $path = $this->cpath($path);
    if(empty($path)) return(false);
    if($this->type($path)!=0) return(false);

    // get all lines (but no sublevel data)
    $pat = $this->ppath('/^' . preg_quote($path,'/') . '(%P\/|%P\/%N|@%N)?$/');
    $data = $this->grep($pat,1);
    if(count($data)==0) return(null);

    // preparation & read tag-name
    $cmode = $this->aux_defmode($path,$this->writemodes,$mode);
    list($key,$dummy) = each($data);
    $tagname = $this->name_get($key);
    $res = '<' . $tagname;
    
    //empty node with no attributes
    if(count($data)==1) return($res .= ($cmode & 2?"></$tagname>":'/>'));
    $cindent = str_repeat($this->indent,$level);
    $ilen = strlen($cindent);
    $clen = strlen($res); 

    // Attributes::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
    while(list($key,$val)=each($data)){
      if(strpos($key,'@')===false) break;
      $ca = $this->attr_write(substr($key,strrpos($key,'@')+1),$val);
      $cl = strlen($ca);
      if($this->width<20) { // no limit line width
	$res .= ' ' . $ca;
	$clen += 1 + $cl;
      } else if($cmode & 4){ // each attr a line
	$res .= "\n" . $cindent . $ca;
	$clen = $cl + $ilen;
      } else if($clen + 1 + $cl > $this->width){ // new line necessary?
	$res .= "\n" . $cindent . $ca;
	$clen = $cl + $ilen;
      } else { // just add the attribute
	$res .= ' ' . $ca;
	$clen += 1 + $cl;
      }
    }

    //empty node (only attributes)
    if(is_null($key)) return($res . ($cmode & 2?'></' . $tagname . '>':'/>'));
    
    $res .= '>';
    $clen++;
    
    // child and text-data :::::::::::::::::::::::::::::::::::::::::::::::::::
    $celes = array(); $clens = array(); $ctyps = array();
    $newline = "\n" . (($cmode & 8)?$cindent:''); // newline with indent?
    //collect them
    do{
      $ctyp = $this->type($key);
      if($ctyp == 0)       $cres = $this->write_node($key,$cmode,$level+1);
      else if($cmode== -1) $cres = trim($val); // has to be already xhtml-style!
      else if($cmode== -2) $cres = '<![CDATA[' . $val . ']]>';
      else                 $cres = $this->aux_layouttext($val,$newline,$cmode);
      $celes[] = $cres; $clens[] = strlen($cres); $ctyps[] = $ctyp;
    } while(list($key,$val)=each($data));

    // layout them
    if($cmode & 2048){ // each item a line
      foreach($celes as $ce) $res .= $newline . $ce;
      $res .= (($cmode & 128)?$newline:'') . '</' . $tagname . '>';
    } elseif($cmode & 16 and $clen+array_sum($clens)+3<$this->width) { // short items
      $res .= implode('',$celes) . '</' . $tagname . '>';
    } else {
      for($ci=0;$ci<count($celes);$ci++){ 
	if(strlen(trim($celes[$ci]))==0) continue;
	if($cmode & ($ctyps[$ci]==0?32:64)) $res .= $newline . $cindent; // add a new line?
	$res .= $celes[$ci];
      }
      $res .= (($cmode & 128)?$newline:'') . '</' . $tagname . '>';
    }
    return($res);
  }


  
  /* returns the realtion of pathB relativ to pathA
   pathB: default: next item in data
   pathA: default: current path
   returns an array with
     A: pathA
     B: pathB
     P: node where A and B are childs of (or identical with)
     up: how many levels you have to go up from A to P
     down: how many  levels to go down from P to B
     step: difference in position if A & B are childs of the same node otherwise null
     typ:  >0 is additional
       -1: identical; 
        0: no directly connection
        1: A is attribute
        2: B is attribute
        4: A & B have the same (directly) parent node 
        8: B is child of A (is a subtype of 32)
       16: A is child of B (is a subtype of 64)
       32: B is below A (1 or more level deeper)
       64: A is below B (1 or more level deeper)
  */
  function relation($pathB=null,$pathA=null){
    if(is_null($pathA)) $pathA = $this->cpath($pathA);
    if(is_null($pathB)) $pathB = $this->iter('next',$pathA,false);
    if($pathA==$pathB) 
      return(array('A'=>$pathA,'B'=>$pathB,'P'=>$pathA,'up'=>0,'down'=>0,'step'=>null,'typ'=>-1));
    $typ = 0;
    $ta = $this->type($pathA); 
    $tb = $this->type($pathB);
    if($ta==2) { $typ+=1; $pa = substr($pathA,0,strrpos($pathA,'@')); } else $pa = $pathA;
    if($tb==2) { $typ+=2; $pb = substr($pathB,0,strrpos($pathB,'@')); } else $pb = $pathB;

    $aa = explode('#',$pa);     $ab = explode('#',$pb); 
    $co = 0; while($aa[$co]==$ab[$co] and $co<count($aa)) $co++;
    $ap = array_slice($aa,0,$co); $pp = implode('#',$ap);
    $up = count($aa)-count($ap);
    $down = count($ab)-count($ap);
    $step = null;
    if($up==0 and $down==0){
      $typ +=4;
    } else if($up==1 and $down==1){
      $typ += 4;
      if($ta!=2 and $tb!=2) $step = $this->pos_get($pb)-$this->pos_get($pa);
    } else if($up==0){
      $typ += $down==1?40:32;
    } else if($down==0){
      $typ += $up==1?80:64;
    }
    return(array('A'=>$pathA,'B'=>$pathB,'P'=>$pp,'up'=>$up,'down'=>$down,'step'=>$step,'typ'=>$typ));
  }

 	
  // Auxilliary functions =========================================
  /* makes a path
     null -> current path
     otherwise the path may start with one or more of the following (in this order)
     ./ : if attr or data moves to the parent node
     ../: moves to the parent node (may occure more than once)
     $ as trailler will search the rest as id attribute and then go to the node
     /: use as absolute path
     [other]: use togethher  with current path

     # or @ at the end will set to the first child/attr
     a missing / at the end will be added by default ( ...#3 -> ...#3/)
     if th path ends like ...#3/ and the this is a node the name will be added autmatically (eg -> ...#3/mynode)

     check: 0: only syntax checks, but not if it exists
            1: returns NULL if path does not exists
	    2: no checks at all (used by mpath)
   */
  function cpath($path=null,$check=1){
    $tpath = $this->key;
    if(is_null($path) or $path === '') return($tpath);
    if($path==='/') return $this->basekey;

    if(substr($path,0,1)=='$'){
      $keys = $this->values_search('/@id$/',substr($path,1),0,'node');
      if(count($keys)==1) return array_shift($keys);
      return NULL;
    }
    if(is_int($path)) $path = $tpath . '#' . $path;
    if(substr($path,0,1)!='/') {
      if(substr($path,0,2)=='./') {
	switch($this->type($tpath)){
	case 1: $tpath = substr($tpath,0,strrpos($tpath,'#')); break;
	case 2: $tpath = substr($tpath,0,strrpos($tpath,'@')); break;
	}
	$path = substr($path,2);
      }
      while(substr($path,0,3)=='../'){
	switch($this->type($tpath)){
	case 1: case 0: $tpath = substr($tpath,0,strrpos($tpath,'#')); break;
	case 2: $tpath = substr($tpath,0,strrpos($tpath,'@')); break;
	}
	$path = substr($path,3);
      }
      $path = $tpath . $path;
    }
    if(substr($path,-1)=='@' or substr($path,-1)=='#'){
      $ak = preg_grep('/^' . preg_quote($path,'/') . '/',array_keys($this->data));
      if(is_array($ak)) $path = array_shift($ak);
    } else {
      $path = preg_replace('/(#[0-9]+)$/','$1/',$path);
    }
    if($check==2) return $path;
    $pat = "{^/($this->nameipat$this->pospat)*$this->nameipat($this->pospat|@$this->nameipat)?$}";
    if(!preg_match($pat,$path)) return(null);
    if($check==0) return($path);
    if(!array_key_exists($path,$this->data)) {
      if(substr($path,-1,1)!='/') return(null);
      $re = $this->grep('/^' . preg_quote($path,'/') . '/',3);
      return(array_shift($re));
    }
    return($path);
  }
  
  /* like cpath but will create missing parents and allows insertion
   * instead of the position-number the following syntax may be used
   * >   insert after last child
   * <   insert before first child
   * >?  insert after position ? (or at if not yet used)
   * <?  insert before position ? (or at if not yet used)
   * after the first position using < or > the other position numbers are allways 0 (since its a new node)
   * missing nodes will be inserted to data
   * returns (last) path
   */
  function mpath($path){
    $path = $this->cpath($path,2);
    $pat = "{^/($this->nameipat$this->pospat_ext)*$this->nameipat($this->pospat_ext|@$this->nameipat)?$}";
    if(!preg_match($pat,$path)) return(null);
    // explode path and move all up to the first tag-name to top
    $path = explode('/',str_replace('#','/',$path));
    $top = $this->pimplode(array_splice($path,0,4));
    // loop as long the nodes exists ..................................................
    while(count($path)>0){
      $cpe = array_shift($path);
      // is an insert asked?
      if(substr($cpe,0,1)=='<' or substr($cpe,0,1)=='>') break;
      // invalid path
      if(!is_numeric($cpe)) return NULL;
      // unkown position -> similar to insert
      if(!$this->path_used($top . '#' . $cpe)) break;
      // know position -> add to top
      $top .= '#' . $cpe;

      // last tag-name -> remove existing data
      if(count($path)==1) {
	$this->remove($top,FALSE);
	$cpe = array_shift($path);
	if(!empty($cpe)){
	  $top .= '/' . $cpe;
	  $this->data[$top] = 0;
	} else $top .= '/';
	return $top;
      }

      $cpe = array_shift($path);
      // known position but unkown tag-name -> remove old position
      if(!$this->path_exists($top . '/' . $cpe)){
	$this->remove($top,FALSE);
	$top .= '/' . $cpe;
	$this->data[$top] = 0;
	array_shift($path);
	$cpe = '>';
	break;
      }

      // known tag-name -> add to top
      $top .= '/' . $cpe;
    }

    // make insert part 
    if($cpe==='<'){
      $cc = $this->node_getnumber($top,1);
      if(is_null($cc)) $cc = 0;
      else $this->renumber_part($top,$cc,1);
    } else if($cpe==='>'){
      $cc = $this->node_getnumber($top,4);
    } else if(substr($cpe,0,1)=='>'){
      $cc = (int)substr($cpe,1)+1;
      $this->renumber_part($top,$cc,1);
    } else if(substr($cpe,0,1)=='<'){
      $cc = (int)substr($cpe,1);
      $this->renumber_part($top,$cc,1);
    } else {
      $cc = (int)substr($cpe,1);
    }
    $top .= '#' . $cc;


    // add rest of path using 0 as position (since its new!)
    $n = count($path);
    for($i=0;$i<$n;$i++){
      if($i%2){
	$top .= '#0';
      } else {
	$top .= '/' . $path[$i];
	$this->data[$top] = 0;
      }
    }
    $this->data[$top] = 0;
    return $top;
  }

  function pimplode($path){
    $res = '';
    $i = 0;
    foreach($path as $ck=>$cv) $res .= $cv. ($i++%2?'#':'/');
    return substr($res,0,-1);
  }

  // like cpath but will use the result as new current path
  function spath($path=null){
    $npath = $this->cpath($path);
    if(is_null($npath)) return(null);
    $this->key = $this->cpath($path);
    return($npath);
  }
  
  function path_exists($path=null){
    $npath = $this->cpath($path);
    return(array_key_exists($npath,$this->data));
  }

  /* opposite to path_exist returns true if path is just a part of an existing one */
  function path_used($path=null){
    $npath = $this->cpath($path);
    if(array_key_exists($npath,$this->data)) return TRUE;
    return count(preg_grep('|^' . $npath . '([#/].*)$|',array_keys($this->data)))>0;
  }


  /*
    replaces special pattern in path's
    =  : short for '\/' (escaped /)

    %C : short for the current path (including ^ at the beginning)

    %H : short for the base tag '^[$this->basekey]'

    %N : name; short for '[_a-zA-Z][-_a-zA-Z0-9]*'
    %P : position; short for '#[0-9]+'

    %T : tag (name & position); short for '(\/[_a-zA-Z][-_a-zA-Z0-9]*#[0-9]+)' (including braces)
    %S : subtag (position & name); short for '(#[0-9]+\/[_a-zA-Z][-_a-zA-Z0-9]*)' (including braces)
    %D : data; short for '#[0-9]+\/$/' (including $/ at the end)
    %A : attribute; short for '@[_a-zA-Z][-_a-zA-Z0-9]*$/' (including $/ at the end)
    %E : end pattern for anything; short for '(@[_a-zA-Z][-_a-zA-Z0-9]*|#[0-9]+\/)?$/'

    eg
    text: /xml#0/sites#1/index#0/: Hello
    node: /xml#0/sites#1/index#1/page: 0
    attr: /xml#0/sites#1/index#1/page@name: home
  */
  function ppath($path){
    $path = str_replace('=','\/',$path);
    $path = str_replace('%C','^' . preg_quote($this->key,'/'),$path);
    $path = str_replace('%H','^' . preg_quote($this->basekey,'/'),$path);
    $path = str_replace('%N','[_a-zA-Z][-_a-zA-Z0-9]*',$path);
    $path = str_replace('%P','#[0-9]+',$path);
    $path = str_replace('%T','(\/[_a-zA-Z][-_a-zA-Z0-9]*#[0-9]+)',$path);
    $path = str_replace('%S','(#[0-9]+\/[_a-zA-Z][-_a-zA-Z0-9]*)',$path);
    $path = str_replace('%D','#[0-9]+\/$/',$path);
    $path = str_replace('%A','@[_a-zA-Z][-_a-zA-Z0-9]*$/',$path);
    $path = str_replace('%E','(@[_a-zA-Z][-_a-zA-Z0-9]*|#[0-9]+\/)?$/',$path);
    return($path);
  }

  // prepares keys for attribute search
  function path_attr($keys=null,$path=null){
    $path = $this->cpath(is_null($path)?'./':$path);
    if($this->type($path)!=0) return(null);
    $qpath = preg_quote($path,'/');//quoted path

    if(is_null($keys)) return array('/^' . $qpath . '@' . $this->nameipat . '$/');
    $res = array();
    foreach((array)$keys as $ck){
      if(substr($ck,0,2)=='/^')
	$res[] = '/^' . $qpath . '@' . substr($ck,2);
      else if(substr($ck,0,1)=='/')
	$res[] = '/^' . $qpath . '@' . $this->nameipat . substr($ck,1);
      else
	$res[] = '/^' . $qpath . '@' . $ck . '$/';
    }
    return($res);
  }
 	
  // Auxilliary static functions =========================================
  // checks if $newname is a correct name (or an array of)
  // if not and seterr is true will add error 10 to the errstack
  function check_name($newname,$seterr=false){
    if(is_array($newname)){
      $tv = preg_grep($this->namepat,$newname);
      $tv = count($newname)==count($tv);
    } elseif(is_string($newname)) {
      $tf = preg_match($this->namepat,$newname);
    } else $tf = false;
    if($tf===false and $seterr) $this->err_add(10,$newname);
    return($tf!=0);
  }

  // creates the attribute ready for writting. Escapes ' and " if necessary
  function attr_write($name,$value){
    if(is_array($value)) {qa();qk();}
    if(strpos($value,'"')===false) 
      $val = '"' . htmlspecialchars($value,ENT_NOQUOTES,$this->xmlcharset) . '"';
    else if(strpos($value,"'")===false) 
      $val = "'" . htmlspecialchars($value,ENT_NOQUOTES,$this->xmlcharset) . "'";
    else
      $val = '"' . htmlspecialchars($value,ENT_COMPAT,$this->xmlcharset) . '"';
    return($name . '=' . $val);
  }

  function transformData($data,$mode){
    if($mode<0) return($data);
    if($mode & 2) return;
    if($mode & 256) $data = str_replace("\t",' ',$data);
    if($mode & 16){
      $data = str_replace("\r\n","\n",$data);
      $data = str_replace("\r","\n",$data);
    }
    if($mode & 512) $data = preg_replace('/^ +/m','',preg_replace('/ +$/m','',$data));
    if($mode & 32) {
      $data = preg_replace("/^( *\n)+/",'',$data);
      $data = preg_replace("/(\n *)+$/",'',$data);
    }
    if($mode & 4096) {
      $data = preg_replace("/\n{2,}/","\r",$data);
      $data = preg_replace("/\n/",' ',$data);
      $data = preg_replace("/\r/","\n\n",$data);
    } else {
      if($mode & 64) $data = preg_replace("/\n{2,}/","\n",$data);
      if($mode & 128) $data = str_replace("\n",' ',$data);
    }
    if($mode & 1024) $data = preg_replace('/  */',' ',$data);
    if($mode & 2048) return(explode(chr(10),$data));
    return($data);
  }


  // Auxilliars internal ================================================
  // idea repair function??
  function renumber($data=null){ 
    $arr = is_null($data)?$this->data:$data;
    $ark = preg_replace('/#[0-9]+/','',array_keys($arr)); 
    $ak = array_keys($arr);
    $ark = preg_replace('/^\/xml/','',$ark);  
    $ak = preg_replace('/^\/xml#[0-9]+/','',$ak);
    $arv = array_values($arr);
    $res = array();
  }

  // renumber a part of data
  // path to the node-tag, start first pos to renumber, $step in/dekrement
  function renumber_part($path=null,$start,$step){
    $path = $this->cpath($path); 
    if(empty($path)) return(false);
    $lp = strlen($path);
    $ckeys = array_keys($this->data);
    $okeys = preg_grep('|^' . $path . '#|',$ckeys);
    foreach($okeys as $pos=>$okey){
      $cpos = (int)substr($okey,$lp+1);
      if($cpos>=$start){
	if(is_numeric(substr($okey,$lp+1)))
	  $ckeys[$pos] = $path . '#' . ($step+$cpos);
	else
	  $ckeys[$pos] = $path . '#' . ($step+$cpos) .  substr($okey,strpos($okey,'/',$lp));
      }
    }
    $this->data = $this->combine($ckeys,$this->data);
    return(true);
  }

  // faster!!!!
  function aux_sort_new($keyA,$keyB){
    $keyA = explode('@',$keyA,2);
    $keyB = explode('@',$keyB,2);
    if($keyA[0]!=$keyB[0]){ // not the same path
      if(substr($keyA[0],0,strlen($keyB[0]))==$keyB[0]) return(1);
      if(substr($keyB[0],0,strlen($keyA[0]))==$keyA[0]) return(-1);
      $cp = 0;
      while(substr($keyA[0],$cp,1)==substr($keyB[0],$cp,1)) $cp++;
      return(intval(substr($keyA[0],$cp))<intval(substr($keyB[0],$cp))?-1:1);
    }
    if(!isset($keyA[1])) return(-1);
    if(!isset($keyB[1])) return(1);
    if($keyA[1]=='id') return(-1);
    if($keyB[1]=='id') return(1);
    if($keyA[1]=='title') return(-1);
    if($keyB[1]=='title') return(1);
    return($keyA[1]<$keyB[1]?-1:1);
    return(-1);
  }

  function aux_sort($ea,$eb){
    $ea = explode('#',$ea);
    $eb = explode('#',$eb);
    while(count($ea)>0 and count($eb)>0 and $ea[0]===$eb[0]){
      array_shift($ea);
      array_shift($eb);
    }
    if(count($ea)==0) return(-1);
    if(count($eb)==0) return(1);
    $na = (int)$ea[0];
    $nb = (int)$eb[0];
    if($na==$nb){
      if(count($ea)==1) $na = str_replace('@','@',$ea[0]); else $na .= '~';
      if(count($eb)==1) $nb = str_replace('@','@',$eb[0]); else $nb .= '~';
    }
    return($na<$nb?-1:1);
  }

  /* which mode (depending from path) in modelist
   should be used. Default is 0 or $parentmode
   if this is odd. 
  */
  function aux_defmode($path,$modelist,$parentmode=0){
    $def = ($parentmode%2)?$parentmode:0;
    reset($modelist);
    while(list($ak,$av)=each($modelist))
      if(preg_match($this->ppath($ak),$path)) 
	return($av);
    return($def);
  }

  function aux_layouttext($text,$newline,$cmode){
    $text = htmlspecialchars($text,ENT_NOQUOTES,$this->xmlcharset);
    if(($cmode & 256)==0) return($text);
    if($this->width<20) return($text);
    if(($cmode & 512)==0) $newline = "\n";
    $len = max(20,$this->width-strlen($newline));
    $text = explode("\n\n",$text); // keep double newlines as own paragraphs
    $res = array();
    foreach($text as $ctext){
      $ctext = str_replace("\n",' ',$ctext);
      $cres = array();
      while(strlen($ctext)>$len){
	while(($np=strpos($ctext,' ',$cp+1))<$len) {
	  if($np===false) break;
	  $cp = $np;
	}
	$cres [] = substr($ctext,0,$np);
	$ctext = substr($ctext,$np+1);
      }
      $cres [] = $ctext;
      $res [] = implode($newline,$cres);
    }
    $text = implode("\n" . $newline,$res);
    return($text);
  }

  function sort($data=null){
    $ar = is_null($data)?$this->data:$data;
    uksort($ar,array($this,'aux_sort'));
    if(is_null($data)) $this->data = $ar;
    return($ar);
  }
  

  function _die($txt){
    if($this->die_on_error) die($txt);
    $this->err_add(100,$txt);
  }

  /* ================================================================================
   Read Functions
   ================================================================================ */
 // reads a file into a node object 
  function read_file($filename=NULL){
    if(is_null($filename)) $filename = $this->filename;
    if(!file_exists($filename)) return($this->err_add(1,$filename));
    if(0!=($cres = $this->_read_prepare())) return($cres); 
    $fp = fopen($filename,'r');
    while ($data = fread($fp, 4096)) {
      if(!xml_parse($this->xmlp, $data, feof($fp))){
	$this->_die(sprintf("XML error: %s at line %d in file $filename",
			    xml_error_string(xml_get_error_code($this->xmlp)),
			    xml_get_current_line_number($this->xmlp)));
      }
    }
    fclose($fp);
    if(0!=($cres = $this->_read_finish())) return($cres);
    return(0);
  }

 // reads a file into a node object 
  function read_array($data_array){
    if(is_string($data_array))       $data_array = array($data_array);
    else if(!is_array($data_array))  return($this->err_add(1,'array'));

    if(0!=($cres = $this->_read_prepare())) return($cres); 
    while ($data = array_shift($data_array)) {
      if (!xml_parse($this->xmlp, $data, count($data_array)==0)) {
	$this->_die(sprintf("XML error: %s at line %d in data-array",
			    xml_error_string(xml_get_error_code($this->xmlp)),
			    xml_get_current_line_number($this->xmlp)));
      }
    }
    if(0!=($cres = $this->_read_finish())) return($cres);
    return(0);
  }

  function _read_prepare(){
    $cm = $this->readmodes; $this->readmodes = array();
    while(list($ak,$av)=each($cm)) $this->readmodes[$this->ppath($ak)] = $av;
    $this->flush();
    $this->xmlp = xml_parser_create($this->phpcharset);
    $this->xmlcharset = xml_parser_get_Option($this->xmlp,XML_OPTION_TARGET_ENCODING);
    xml_parser_set_option($this->xmlp,XML_OPTION_CASE_FOLDING,0);
    xml_set_object($this->xmlp,$this);
    xml_set_element_handler($this->xmlp, "xmlp_start", "xmlp_end");
    xml_set_character_data_handler($this->xmlp,"xmlp_cdata");
    return(0);
  }

  function _read_finish(){
    xml_parser_free($this->xmlp);
    $this->key = $this->xmlpath[0] . '#' . ($this->xmlpos[0]-1);
    $ak = array_keys($this->data);
    if(count($ak)>0){
      $this->basekey = $ak[0];
      $this->basetag = preg_replace('|.*/|','',$ak[0]);
    } else {
      $this->basekey = NULL;
      $this->basetag = NULL;
    }
    return(0);
  }

  // --------------------------------------------------------------------------------




  // transform charset from xml to php (or reverse if rec is TRUE)
  function charset($data,$rev){
    if($this->xmlcharset===$this->phpcharset) return($data);
    if($rev!=TRUE) return($data);
    if($rev) $case = $this->phpcharset .  ' > ' . $this->xmlcharset;
    else $case = $this->xmlcharset . ' > ' . $this->phpcharset;
    switch($case){
    case 'UTF-8 > ISO-8859-1': return(utf8_decode($data));
    case 'ISO-8859-1 > UTF-8': return(utf8_encode($data));
    }
    return($data);
  }


  function getn($keys,$def=array(),$data=null){
    $arr = is_null($data)?$this->data:$data;
    $keys = $this->keys($keys,$arr);
    foreach($keys as $key) $def[$key] = $this->charset($arr[$key],FALSE);
    return($def);
  }




  // PARSING ============================================================

  function xmlp_addstringdata(){
    if(is_null($this->xmllast)) return;
    $cm = $this->xmlmode[0];
    $cd = $this->transformData($this->xmllast,$this->xmlmode[0]);
    $this->xmllast = null;
    if($cd=='') return;
    if(is_array($cd)){
      foreach($cd as $ci)
	$this->data[$this->xmlpath[0] . '#' . ($this->xmlpos[0]++) . '/'] = $this->charset($ci,FALSE);
    } else {
      $this->data[$this->xmlpath[0] . '#' . ($this->xmlpos[0]++) . '/'] = $this->charset($cd,FALSE);
    }
  }

  function xmlp_start($parser, $name, $attrs){
    $cmode = $this->xmlmode[0];
    if($cmode<0){
      $this->xmlpos[0]++;
      $cn = $this->xmlpath[0] . '#' . ($this->xmlpos[0]++) . '/' . $name;
      $nd = '<' . $name;
      while(list($ak,$av)=each($attrs)) $nd .= ' ' . $this->attr_write($ak,$av);
      $this->xmllast .= $nd . '>';
      $cmode = -1;
    } else {
      $this->xmlp_addstringdata();
      $cn = $this->xmlpath[0] . '#' . ($this->xmlpos[0]++) . '/' . $name;
      $cmode = $this->aux_defmode($cn,$this->readmodes,$cmode);
      $this->data[$cn] = $cmode;
      if(!($cmode & 8) || $cmode==-3)
	while(list($ak,$av)=each($attrs)) 
	  $this->data[$cn . '@' . $ak] = $this->charset($av,FALSE);
    }
    array_unshift($this->xmlpath,$cn);
    array_unshift($this->xmlpos,0);
    array_unshift($this->xmlmode,$cmode);
  }  	
  
  function xmlp_end($parser, $name){
    if($this->xmlmode[0]>=0) {
      $this->xmlp_addstringdata();
    } else {
      if($this->xmlmode[1]>=0) {
	$this->xmlpos[0] = 0;
	$this->xmlp_addstringdata();
      } else {
	if($this->xmlpos[0]==0){
	  $this->xmllast = substr($this->xmllast,0,-1) . '/>';
	} else {
	  $this->xmllast .= '</' . $name . '>';
	}
      }
    }
    array_shift($this->xmlpath);
    array_shift($this->xmlpos);
    array_shift($this->xmlmode);
  }
  
  function xmlp_cdata($parser, $data){
    switch($this->xmlmode[0]){
    case -1: case -3:
      $this->xmlpos[0]++;
      $data = htmlspecialchars($data,ENT_NOQUOTES,$this->xmlcharset);
      break;
    case -2:
      $this->xmlpos[0]++;
      break;
    }
    $this->xmllast .= $data;
  }
}

?>
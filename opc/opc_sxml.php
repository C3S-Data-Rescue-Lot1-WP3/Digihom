<?php

  /* open things/ideas
   * sort
   * iter
   * function last_changes (set date/user ...)
   * this->filename bei neuen Dateien
   * xmlcharset bei neuen Dateien
   */

  /* Simplified xml which is suitable for most situations

   * reads in a (restricted) xml-file and parse it to a data-array (path=>value)

   * Restriction to normal xml-files
   *  comments and other non-tag parts are ignored
   *  A node may have (beside attributes) one single text (text-node) or 
   *  any number of sub-nodes (non-text node) as childs, but not a mix of it
   *  How is a text-nodec recogniced
   *  - attribute _sx:nt is "xhtml"
   *  - criterias in array xhtml_at
   *    numeric key: key pattern
   *    string value: value pattern for attribute (with key name)
   *    array value: value in list
   *   

   * Therefore each data-elemtent is of one of the following four types
   *  non-text node   key-pattern: {^[^@]+$}   value: 0
   *  text-node       key-pattern: {^[^@]+$}   value: 1
   *  attribute       key-pattern: {^.*@.+$}   value: [string]
   *  text-element    key-pattern: {^.*@$}     value: [string]
   * The 'real' values ares saved in the attributes and text-elements
   * The two node types are godd as entry-point and for searching by path

   * The class may also write the data using some layout options
   *  please remind, that any kind of comments are lost
   */ 

class opc_sxml implements ArrayAccess{
  /* ================================================================================
   ==================================== BASICS  =====================================
   ================================================================================*/

  public $data = array();        // data array
  public $basekey = 'sxml';      // top most tag name
  public $phpcharset = 'UTF-8';  // convert data to
  // allowed charsets
  public $charsets = array('UTF-8','ISO-8859-1');
  public $filename = NULL;
  
  /* list of path-patterns that indicate a xhtml content
   *  eg if all 'text' and 'slogan' tags contain xhtml use '{/(slogan|text)$}'
   * by default: no node contains (xhtml) text
   */
  public $xhtml_at = array();

  /* even attributes may call method. To identify them, they have to start with
   * the following prefix. Thean method sxatt__[restofname] is called
   * predefined
   *  _sx:nt: will overdrive the result if xmlp_type_get
   *  _sx:xhtmlat: defines a new pattern for xhtml_at (used for subchilds)
   */
  public $sx_attr_prefix = '_sx:'; 

  /* how to handle a xml parse error
   *  -1: die with message
   *  -2: die silent
   *  0: just continue
   *  >0: trigger error using as error_type
   */
  public $error_mode = E_USER_NOTICE;
  public $error_msg = NULL;



  /* Layout for writing ==================================================
   * indent: indent used for new lines, repeatet depending on level
   * indent_attr: additional indent if attr starts on a new line
   *
   * layout_ncol: max number of cols per line
   *   used if layout_attr=2
   * layout_attr how to write attributes
   *   0: all in the same line as the tag
   *   1: all on its own line
   *   2: as 0 if line length is below layout_ncol, as 1 otherwise
   * layout_imod: to prevent extreme long indents
   *   to calculate indent level%layout_imod is used insted oof lev
   */

  public $indent = ' '; // indent per level
  public $indent_attr = '   '; //addition indent for attributes

  public $layout_ncol = 80; 
  public $layout_imod = 10; 
  public $layout_attr = 2;




  /* internal variables ================================================== */
  protected $xhtml_data = array();
  protected $xhtml_depth = 0;
  protected $xhtmlat = array();
  protected $xmlpath = array();	//current path
  protected $xmlpos = array(); // current position inside a node
  protected $xmlnt = array(); // current node type
  protected $xmlcharset = 'UTF-8';

  // Pattern
  protected $pat_tag = '[_a-zA-Z][-_a-zA-Z0-9]*';
  protected $pat_attr = '[_a-zA-Z][-_a-zA-Z0-9]*';
  protected $pat_pos = '#[0-9]+';
  protected $pat_epos = '#([><]|[><]?\d+)/';

  protected $msg_na = 'Message not found: %id';

  /* Constructor with variable arguments
   *  array: used for xhtml_at
   *  string: 
   *    - used as phpcharset (if in charsets)
   *    - used as filename (if exists)
   *    - used as basekey if match pattern
   *    - ignored else
   *  integer: used for error_mode
   */
  function __construct(){
    $ar = func_get_args();
    foreach($ar as $val){
      if(is_array($val)) 
	$this->xhtml_at = $val;
      else if(is_int($val))
	$this->error_mode = $val;
      else if(!is_string($val))
	$this->err("unkown argument for creating an opc_sxml");
      else if(is_string($val)){
	if(in_array($val,$this->charsets))
	  $this->phpcharset = $val;
	else if(file_exists($val))
	  $this->filename = $val;
	else if(preg_match("{^$this->pat_tag$}",$val))
	  $this->basekey = $val;
      }
    }
    if(is_null($this->filename)) 
      $this->data = array('/' . $this->basekey=>0);
    else
      $this->read($this->filename);
  }


  /* Array Access ================================================== */
  function offsetGet($key){return $this->get($key); }
  function offsetSet($key,$val){ $this->set($key,$val);  }
  function offsetExists($key){return isset($this->data[$key]); }
  function offsetUnset($key){
    if(!isset($this->data[$key])) return;
    if(is_int($this->data[$key])){
      foreach($this->keys_get($path,127) as $ck)
	unset($this->data[$ck]);
    } else unset($this->data[$key]);
  }


  /* checks if a key is well formed
   * returns FALSE if not 
   *  and 1: for a node; 2 : attribute; 3: text-element
   * if check_exists is TRUE; FALSE is also returned if key does not exist in data
   */
  function key_check($key,$check_exists=FALSE){
    if($check_exists and !isset($this->data[$key])) return FALSE;
    $pat = '{^/' . $this->pat_tag . '(#\d+/' . $this->pat_tag . ')*(@(' . $this->pat_attr . ')?)?$}';
    if(!preg_match($pat,$key)) return FALSE;
    if(substr($key,-1,1)=='@') return 3;
    return strpos($key,'@')===FALSE?1:2;
  }

  /* returns FALSE for an non existing key
   *  and 0: for a non-text node; 1: text-node; 2 : attribute; 3: text-element
   */
  function key_type($key){
    $key = $this->key_prep($key);
    if(!isset($this->data[$key])) return FALSE;
    if(substr($key,-1,1)=='@') return 3;
    if(strpos($key,'@')!==FALSE) return 2;
    return $this->data[$key];
  }

  function node_name_get($key){
    if(is_array($key)){
      $res = array();
      foreach($key as $ck=>$cv) $res[$ck] = $this->node_name_get($cv);
      return $res;
    } 
    $key = $this->key_prep($key);
    return substr($key,strrpos($key,'/')+1);
  }

  function attr_name_get($key){
    if(is_array($key)){
      $res = array();
      foreach($key as $ck=>$cv) $res[$ck] = $this->attr_name_get($cv);
      return $res;
    }     $tmp = strpos($key,'@');
    return $tmp===FALSE?NULL:substr($key,$tmp+1);
  }

  /* ================================================================================
   ====================================== ESGR ======================================
   ================================================================================*/

  function key_exists($key){
    return array_key_exists($this->key_prep($key),$this->data);
  }

  function attr_exists($attr,$key){
    return isset($this->data[$this->key_prep($key) . '@' . $attr]);
  }

  function attr_key_list($key){
    $key = $this->key_prep($key);
    $keys = preg_grep('{^' . $key .'@.+$}',array_keys($this->data));
    return preg_replace('{.*@}','',$keys);
  }

  function text_exists($key){
    return isset($this->data[$this->key_prep($key) . '@']);
  }

  function attr_get($attr,$key,$def=NULL){
    return def($this->data,$this->key_prep($key) . '@' . $attr,$def);    
  }
  
  function text_get($key,$def=NULL){
    return def($this->data,$this->key_prep($key) . '@',$def);    
  }

  function text_set($text,$key){
    $key = $this->key_prep($key);
    if(!isset($this->data[$key]) or $this->data[$key]!==1) return 1;
    if(!is_string($text)) return 2;
    $this->data[$key . '@'] = $text;
    return 0;
  }


  /* returns
   *  FALSE if id attribute with this value exists
   *  TRUE  if exact one attribute with this value exists
   *  NULL otherwise
   */
  function id_exists($value){
    $tmp = preg_grep('{@id$}',array_keys($this->data,$value,TRUE));
    return count($tmp)==0?FALSE:(count($tmp)==1?TRUE:NULL);
  }

  /* returns if at least on name attribute with this vlaue exists */
  function name_exists($value){
    $tmp = preg_grep('{@name$}',array_keys($this->data,$value,TRUE));
    return count($tmp)>0;
  }

  function id2node($value){
    $tmp = preg_grep('{@id$}',array_keys($this->data,$value,TRUE));
    switch(count($tmp)){
    case 0: return FALSE;
    case 1: return preg_replace('{@.*$}','',array_shift($tmp));
    }
    return NULL;
  }

  function name2nodes($value){
    return preg_replace('{@.*$}','',preg_grep('{@name$}',array_keys($this->data,$value,TRUE)));
  }

  //suppose ther is only on text-tag below key with that name
  function child_text_get($tag,$key,$def=NULL){
    $key = $this->key_prep($key);
    $tmp = $this->text_search($tag,$key);
    return is_string($tmp)?$this->data[$tmp . '@']:$def;
  }


  /* returns the asked attributes of the key 
   * default is either an (named) array or a single value for all
   */
  function attr_getm($attrnames,$key,$def=NULL){
    $key = $this->key_prep($key);
    if(is_null($attrnames)) return $this->attr_geta($key,$def);
    else if(is_string($attrnames)) $attrnames = array($attrnames);

    $res = array();
    foreach($attrnames as $ck){
      if(isset($this->data[$key . '@' . $ck]))
	$res[$ck] = $this->data[$key . '@' . $ck];
      else if(is_array($def))
	$res[$ck] = def($def,$ck);
      else
	$res[$ck] = $def;
    }
    return $res;
  }

  /* returns first set attribute */
  function attr_getf($key,$attr /* $attr ... */,$def=NULL){
    $ar = func_get_args();
    $key = $this->key_prep(array_shift($ar));
    $def = array_pop($ar);
    if(count($ar)==0) return $def;
    if(is_array($ar[0])) $ar = $ar[0];
    while(count($ar)>0){
      $ck = array_shift($ar);
      if(array_key_exists($key . '@' . $ck,$this->data))
	return $this->data[$key . '@' . $ck];
    }
    return $def;
  }



  function attr_setm($data,$key){
    $key = $this->key_prep($key);
    if(!isset($this->data[$key]) or !is_int($this->data[$key])) return 1;
    foreach($data as $ck=>$cv) {
      if(!is_string($cv)) return 2;
      if(!preg_match('{^' . $this->pat_attr .'$}',$ck)) return 3;
    }
    foreach($data as $ck=>$cv) $this->data[$key . '@' . $ck] = $cv;
    return 0;
  }

  /* returns all attributes of the asked key */
  function attr_geta($key,$def=array()){
    $key = $this->key_prep($key);
    $keys = preg_grep('{^' . $key .'@.+$}',array_keys($this->data));
    $res = array();
    foreach($keys as $ck)
      $res[substr($ck,strpos($ck,'@')+1)] = $this->data[$ck];
    return array_merge($def,$res);
  }


  /* removes the asked attribute, NULL means all */
  function attr_unset($attr,$key){
    $key = $this->key_prep($key);
    if(is_null($attr)){
      foreach($this->search('ppat',"{^$key@.+}") as $ck) unset($this->data[$ck]);
    } else if(is_array($attr)){
      foreach($attr as $ck) unset($this->data[$key . '@' . $ck]);
    } else unset($this->data[$key . '@' . $attr]);
  }

  /** array version of attrs_get
   * @param string|NULL $keyattr: if null path is used as array key otherwise the given attribute itself
   * @return: named array of attributes
   */
  function attrs_array($keys,$pathes,$def=array(),$keyattr=NULL){
    $res = array();
    foreach($pathes as $cp){
      $tmp = $this->attr_getm($keys,$cp,$def);
      if(is_null($keyattr))     $res[$cp] = $tmp;
      else if($keyattr===TRUE)  $res[$this->node_name_get($cp)] = $tmp;
      else                      $res[$this->attr_get($keyattr,$cp,$cp)] = $tmp;
    }
    return $res;
  }

  /* read in attributes as list */
  function attrs_list($pathes,$key_val,$key_key=NULL,$def=NULL){
    $res = array();
    foreach($pathes as $cp) 
      $res[is_null($key_key)?$cp:$this->attr_get($key_key,$cp,$cp)] = $this->attr_get($key_val,$cp,$def);
    return $res;
  }

  /* reset a text-node (to an empty string)
   * returns FALSE if key is neither a text-node nor an attribute or text-element
   */
  function text_reset($key){
    $key = $this->key_prep($key);
    if($this->data[$key]!==1) return FALSE;
    $this->data[$key . '@'] = '';
    return TRUE;
  }


  /* unset a node or parts of it
   * what: 0: complete node (= remove completly)
   *       1: all attributes
   *       2: all sub elements (text or data, but not attributes)
   *       3: combination of 1 and 2 (leaves an empty node)
   */
  function node_unset($key,$what=0){
    $key = $this->key_prep($key);
    if(!isset($this->data[$key])) return FALSE;
    switch($what){
    case 0: $pat = '{^' . $key . '([@#].*)?$}'; break;
    case 1: $pat = '{^' . $key . '@.+$}'; break;
    case 2: $pat = '{^' . $key . '(@|#.*)$}'; break;
    case 3: $pat = '{^' . $key . '[@#]}'; break;
    default:
      return FALSE;
    }
    $keys = preg_grep($pat,array_keys($this->data));
    foreach($keys as $ck) unset($this->data[$ck]);
    return TRUE;
  }

  /* returns a text or an attribute value
   * or (if path points to a node) all attributes (but no childs)
   */
  function get($path,$def=NULL){
    $path = $this->key_prep($path,FALSE);
    if(!isset($this->data[$path]))    return $def;
    if($this->data[$path]===1)        return $this->data[$path . '@'];
    if(is_string($this->data[$path])) return $this->data[$path];
    $res = array();
    foreach(preg_grep('{^' . $path . '@}',array_keys($this->data)) as $ck)
      $res[substr($ck,strpos($ck,'@')+1)] = $this->data[$ck];
    return $res;
  }

  function getm($pathes,$def=array()){
    $res = array();
    foreach($pathes as $path)
      $res[$path] = $this->get($path,def($def,$path));
    return $res;
  }

  function set($val,$key){
    switch($this->key_check($key)){
    case 2: $this->data[$key] = $val; return 0;
    case 3: $this->data[$key] = $val; return 0;
    }
    return 1;
  }

  function setm($values){
    foreach($values as $key=>$val)
      $this->set($val,$key);
  }


  /* returns keys below path, bincoded
   * 0/1: own key too
   * 1/2: own attributes
   * 2/4: own text element
   * 3/8: own non-text child nodes (but not grand childs)
   * 4/16: own text child nodes (but not grand childs)
   * 5/32: all attributes below
   * 6/64: all text elements
   * 7/128: all non-text child elements
   * 8/256: all text child elements
   * attention 
   *  5/32 includes all results from 1/2 (and similar)
   *  + crosscombination 3/8 and 8/256 and similar do not work!
   */
  function keys_get($path=NULL,$mode=511){
    if(is_null($path) and $mode==127) return array_keys($this->data);
    if(is_null($path)) $path = '/' . $this->basekey;
    $path = $this->key_prep($path);
    if($mode==0 or !isset($this->data[$path])) return array();
    $pat = array();
    if($mode & 1)       $pat[] = '';
    if($mode & 32)      $pat[] = '(#[^@]*)?@.+';
    else if($mode & 2)  $pat[] = '@.+';
    if($mode & 64)      $pat[] = '(#[^@]*)?@';
    else if($mode & 4)  $pat[] = '@';
    if($mode & 384)     $pat[] = '#[^@]+';
    else if($mode & 24) $pat[] = '#[^#@]+';
    $pat = '{^' . $path . '(' . implode('|',$pat) . ')$}';
    $res = preg_grep($pat,array_keys($this->data));

    // remove (non-)text nodes if not asked
    if((($mode & 8)==8 or ($mode & 128)==128)
       and !(($mode & 16)==16 or ($mode & 256)==256))
      $res = array_diff($res,array_keys($this->data,1,TRUE));
    elseif(!(($mode & 8)==8 or ($mode & 128)==128)
       and (($mode & 16)==16 or ($mode & 256)==256))
      $res = array_diff($res,array_keys($this->data,0,TRUE));
    return array_values($res);
    
  }


  /* ================================================================================
     ===================================== Iter =====================================
     ================================================================================ */
  function iterm($keys,$how,$n=1){
    $res = array();
    foreach($keys as $ck=>$cv) $res[$ck] = $this->iter($cv,$how,$n);
    return $res;
  }
  function iter($key,$how,$n=1){
    switch($how){
    case 'n': 
      if($tmp = strpos($key,'@')) return substr($key,0,$tmp);
      return $key;
    case 'h':
      return '/' . $this->basekey;
    case 'u':
      while($n-- > 0) if($tmp = strrpos($key,'#')) $key = substr($key,0,$tmp);
      return $key;
    case 'c':
      if($tmp = strpos($key,'@')) $key = substr($key,0,$tmp);
      if(def($this->data,$key)!==0) return NULL;
      $tmp = preg_grep("{^$key#[^@]*$}",array_keys($this->data));
      return count($tmp)?array_shift($tmp):NULL;
    case 'f':
      switch($this->key_type($key)){
      case 0: 
	
      }
    }
  }


  /* ================================================================================
     ===================================== Sort =====================================
     ================================================================================ */
  function sort(){
    $ar = func_get_args();
    if(count($ar)==0) 
      return uksort($this->data,array($this,'aux_sort'));
  }

  // standard sort function
  protected function aux_sort($ea,$eb){
    $ea = substr($ea,strlen($this->basekey)+2);
    $eb = substr($eb,strlen($this->basekey)+2);
    do{
      if(strlen($ea)==0) return -1;
      if(strlen($eb)==0) return 1;
      $pa = strpos($ea,'/');
      $pb = strpos($eb,'/');
      if($pa===FALSE and $pb===FALSE) 
	return $ea<$eb?-1:1;
      else if($pa===FALSE) return -1;
      else if($pb===FALSE) return 1;

      $pa = (int)$ea;
      $pb = (int)$eb;
      if($pa!=$pb) return $pa<$pb?-1:1;

      $pa = strpos($ea,'#');
      $pb = strpos($eb,'#');
      if($pa===FALSE and $pb===FALSE){
	$pa = strpos($ea,'@');
	$pb = strpos($eb,'@');
	if($pa===FALSE and $pb===FALSE)
	  return $ea<$eb?-1:1; // does not happen
	else if($pa===FALSE) return -1;
	else if($pb===FALSE) return  1;
	else if(substr($ea,-1,1)=='@') return 1;
	else if(substr($eb,-1,1)=='@') return -1;
	else return $ea<$eb?-1:1;
      } else if($pa===FALSE){ return -1;
      } else if($pb===FALSE){ return  1;}
      $ea = substr($ea,$pa+1);
      $eb = substr($eb,$pb+1);
    } while(TRUE);
  }



  /* ================================================================================
  ===================================== Search =====================================
  ================================================================================*/
  
  /* free search: 
   * Allways two arguments belong to each other,
   *   they will be handled in the order they appear
   *   where the first defines what the second means
   *  path: defines to which part of the data the search is limited
   *        needs an (existing) xml-path; default: NULL (everything)
   *  type: which kind of elements are allowed; bincoded (default: 15; all)
   *        0/1: non-text nodes; 1/2: text-nodes; 2/4: attributes 3/8: text-element
   *  value: value to match exact limits result to attributes and text-elements
   *        needs a string or a array of strings
   *  values: find only items which match one of the given values
   *        needs an array of strings
   *  ppat: path pattern to match (regexp)
   *  vpat: value pattern limits result to attributes and text-elements
   *  def: default to return if a single hit was asked but not found

   * An additional last argument defines the kind of the result
   *  keys: (default): return the found keys
   *  key: returns the one key or NULL (if none or multiple keys where found)
   *  values: returns the found part of data
   *  value: returns the one value or NULL (if none or multiple keys where found)
   *  nodes: if attribute or text keys where found: return their node-keys
   *  node: same as nodes but only if there is only one (final) hit


   * if the first argument is an array, this will be expanded
   *   in the sense that it will be handled if every array element
   *   were submitted as own argument. The non-numeric keys are used too.
   *   Attention: amixture of (non) numeric keys may be confusing
   */

  function search(/* */){
    $keys = array_keys($this->data);
    $def = NULL;
    $ar = func_get_args();
    if(count($ar)==0) return $keys;
    if(is_array($ar[0])){
      $tmp = array_shift($ar);
      foreach($tmp as $ck=>$cv){
	if(!is_numeric($ck)) array_unshift($ar,$ck,$cv);
	else                 array_unshift($ar,$cv);
      }
    }
    while(count($ar)>1){
      $typ = array_shift($ar);
      $val = array_shift($ar);
      switch($typ){
      case 'path': 
	$keys = preg_grep('{^' . $val . '([#@].*)?$}',$keys); 
	break;
      case 'ppat':
	$keys = preg_grep($this->pat($val),$keys);
	break;
      case 'vpat':
	$keys = array_intersect($keys,array_keys(preg_grep($val,$this->data)));
	break;
      case 'value':
	$keys = array_intersect($keys,array_keys($this->data,$val,TRUE));
	break;
      case 'values':
	foreach($keys as $ck=>$cv) if(!in_array($this->data[$cv],$val)) unset($keys[$ck]);
	break;
      case 'type':
	if(($val & 15)==0) { $keys = array(); break;}
	$pat = array();
	if($val & 3) $pat[] = '[^@]+';
	if($val & 4) $pat[] = '.*@.+';
	if($val & 8) $pat[] = '.*@';
	$keys = preg_grep('{^(' . implode('|',$pat) . ')$}',$keys);
	// remove (non-)text nodes if necessary (same pattern)
	if(($val & 2) and !($val & 1))
	  $keys = array_diff($keys,array_keys($this->data,0,TRUE));
	else if(!($val & 2) and ($val & 1))
	  $keys = array_diff($keys,array_keys($this->data,1,TRUE));
	break;
      case 'def':
	$def = $val;
	break;
      }
    }

    $keys = array_values($keys);
    switch(count($ar)==0?'keys':$ar[0]){
    case 'keys':
      return $keys;
    case 'key':
      return count($keys)==1?$keys[0]:$def;
    case 'value':
      return count($keys)==1?$this->data[$keys[0]]:$def;
    case 'values': 
      $res = array();
      foreach($keys as $ck) $res[$ck] = $this->data[$ck];
      return $res;
    case 'nodes':
      return array_values(array_unique(preg_replace('/@.*$/','',$keys)));
    case 'node':
      $keys = array_values(array_unique(preg_replace('/@.*$/','',$keys)));
      return count($keys)==1?$keys[0]:$def;
    case 'attrs':
      return array_values(array_unique(preg_replace('/^.*@/','',$keys)));
    case 'attr':
      $keys = array_values(array_unique(preg_replace('/^.*@/','',$keys)));
      return count($keys)==1?$keys[0]:$def;
    }
  }

  function id_search($value){
    $tmp = preg_grep('{@id$}',array_keys($this->data,$value,TRUE));
    return count($tmp)==1?substr(array_shift($tmp),0,-3):NULL;
  }


  /* search by values$
   * in: search in: 0: text/attr; 1: text only; 2: attr only
   * keys: limit to this keys
   *  NULL: no limitation
   *  string: used as pattern
   * 1: text elements only
   * 2: attributes only
   * string: this attribute only (single name or pattern)
   */
  function values_search($val,$path=NULL,$in=2,$allowed=NULL){
    $keys = array_keys($this->data,(string)$val,TRUE);
    if(!is_null($path)) $keys = preg_grep('{^' . $path . '([#@].*)?$}',$keys);
    switch($in){
    case 1: $keys = preg_grep('/@$/',$keys); break;
    case 2: $keys = preg_grep('/@./',$keys); break;
    }
    $keys = array_values($keys);
    if(is_null($allowed)) return $keys;
    $names = $this->names_get($keys);
    if(is_array($allowed)) 
      $sk = array_keys(array_intersect($names,$allowed));
    else if(preg_match('/^\w/',$allowed))
      $sk = array_keys($names,$allowed,TRUE);
    else
      $sk = array_keys(preg_grep($allowed,$names));
    $res = array();
    foreach($sk as $ck) $res[] = $keys[$ck];
    return $res;
  }


  function nodes_search($key,$path=NULL){
    return $this->search_pattern($key,$path,0);
  }

  function attrs_search($key,$path=NULL){
    return $this->search_pattern($key,$path,-2);
  }

  function texts_search($key,$path=NULL){
    return $this->search_pattern($key,$path,1);
  }


  /* The same as above but will return only a single-result or NULL */
  function value_search($key,$path=NULL,$in=2,$keys=NULL){
    $res = $this->values_search($key,$path,$in);
    return count($res)==1?array_shift($res):NULL;
  }

  function node_search($key,$path=NULL){
    $res = $this->nodes_search($key,$path);
    return count($res)==1?array_shift($res):NULL;
  }

  function attr_search($key,$path=NULL){
    $res = $this->attrs_search($key,$path);
    return count($res)==1?array_shift($res):NULL;
  }

  function text_search($key,$path=NULL){
    $res = $this->texts_search($key,$path);
    return count($res)==1?array_shift($res):NULL;
  }





  /* ================================================================================
   ====================================== Read ======================================
   ================================================================================*/

  // attention old data is not removed!
  function read($filename=NULL){
    if(is_null($filename)) $filename = $this->filename;
    if(!file_exists($filename)) return $this->err("Unkown file: $filename");
    $this->filename = $filename;
    $this->error_msg = NULL;
    if(0!==($cres = $this->read_prepare())) return $this->err($cres);
    $fp = fopen($filename,'r');
    while($data = fread($fp, 4096)) {
      if(!xml_parse($this->xmlp, $data, feof($fp))){
	$this->err(sprintf("XML error: %s at line %d in file $filename",
			   xml_error_string(xml_get_error_code($this->xmlp)),
			   xml_get_current_line_number($this->xmlp)));
      }
    }
    fclose($fp);
    xml_parser_free($this->xmlp);
    return 0;
  }

  function read_string($data){
    $this->error_msg = NULL;
    if(0!==($cres = $this->read_prepare())) 
      return $this->err($cres);
    if(!xml_parse($this->xmlp, $data, TRUE)){
      $this->err(sprintf("XML error: %s at line %d in xml-String",
			 xml_error_string(xml_get_error_code($this->xmlp)),
			 xml_get_current_line_number($this->xmlp)));
    }
    xml_parser_free($this->xmlp);
    return 0;
  }

  protected function read_prepare(){
    $this->xmlp = xml_parser_create($this->phpcharset);
    $this->xmlcharset = xml_parser_get_Option($this->xmlp,XML_OPTION_TARGET_ENCODING);
    xml_parser_set_option($this->xmlp,XML_OPTION_CASE_FOLDING,0);
    xml_set_object($this->xmlp,$this);
    xml_set_element_handler($this->xmlp, "xmlp_start", "xmlp_end");
    xml_set_character_data_handler($this->xmlp,"xmlp_cdata");
    return 0;
  }

   /* does the current node contain xhtml (TRUE) or subnodes (FALSE)
    * sideffects!!
    */
  protected function xmlp_type_get($path,&$attrs,$name=NULL){
    if($this->match_criteria($this->xhtml_at,$path,$attrs)) return 'xhtml';
    return 'node';
  }

  protected function match_criteria($patterns,$path,$attrs=array()){
    foreach($patterns as $key=>$val){
      if(is_numeric($key)){
	if(preg_match($this->pat($val),$path)) return TRUE; 
      } else if(is_string($val)){
	if(preg_match($val,def($attrs,$key,''))) return TRUE; 
      } else if(is_array($val)){
	if(in_array(def($attrs,$key,''),$val)) return TRUE; 
      }
    }
    return FALSE;
  }



  /* ================================================================================
   ====================================== Aux  ======================================
   ================================================================================*/
  
  function key_prep($key,$cutattr=TRUE){
    // using id-value (if not unique retuen NULL);
    if(substr($key,0,1)=='#') return $this->id_search(substr($key,1));

    // remove attribute/text-part
    if($cutattr and FALSE !== $tmp=strpos($key,'@'))
      $key = substr($key,0,$tmp);
    // replace %H structer
    $key = str_replace('%H','/' . $this->basekey,$key);
    return $key;
  }

  /* returns a valid key
   * instead of the position-number the following syntax may be used
   * >   insert after last child
   * <   insert before first child
   * >?  insert after position ? (or at if not yet used)
   * <?  insert before position ? (or at if not yet used)
   * after the first position using < or > the other position
   * numbers are set allways to 0 (since its a new node)
   * returns: valid path or numeric error code
   *  1: invalid path
   *  2: wrong basekey
   *  3: position already used
   *  4: wrong node-type
   * missing nodes will be inserted to data
   */
  function key($path){
    $pat = "{^/($this->pat_tag$this->pat_epos)*$this->pat_tag(@($this->pat_attr)?)?$}";
    if(!preg_match($pat,$path)) return 1;
    if($tmp = strpos($path,'@')){
      $attr = substr($path,$tmp);
      $path = substr($path,0,$tmp);
    } else $attr = NULL;
    if(isset($this->data[$path])){
      if(is_null($attr)) return $path;
      if($attr=='@' and $this->data[$path]==0) return 4;
      return $path . $attr;
    }

    $path = explode('#',$path);
    $top = array_shift($path);
    if(!isset($this->data[$top])) return 2;

    while(count($path)>0){
      $cp = array_shift($path);
      if(!isset($this->data[$top . '#' . $cp])) break;
      $top .= '#' . $cp;
    }
    if(empty($cp)) return $top . $attr;

    $ckeys = array_keys($this->data);
    list($pos,$tag) = explode('/',$cp);
    
    if($pos=='>'){
      $pos = $this->pos_max($top,-1)+1;
    } else if($pos=='<'){
      if(preg_grep("{^$top#0/}",$ckeys))
	$this->renumber($top,0,1);
      $pos = 0;
    } else if(is_numeric($pos)){
      if(preg_grep("{^$top#$pos/}",$ckeys)) return 3;
    } else {
      $aft = substr($pos,0,1)=='>';
      $pos = substr($pos,1);
      if(preg_grep("{^$top#$pos/}",$ckeys)){
	if($aft) $this->renumber($top,++$pos,1);
	else     $this->renumber($top,$pos,1);
      } 
    }
    $top .= '#' . $pos . '/' . $tag;
    $this->data[$top] = 0;
    foreach($path as $ck){
      $top .= '#0/' . substr($ck,strpos($ck,'/')+1);
      $this->data[$top] = 0;
    }
    if($attr==='@') {
      $this->data[$top] = 1;
      $this->data[$top . '@'] = '';
    }
    return $top . $attr;
  }

  protected function _cut_pos($key,$n){
  }

  function pos_list($key){
    $res = array();
    $n = strlen($key)+1;
    foreach(preg_grep("{^$key#}",array_keys($this->data)) as $ck){
      $tmp = (int)substr($ck,$n);
      if(!in_array($tmp,$res)) $res[] = $tmp;
    }
    return $res;
  }

  function pos_max($key,$empty=0){
    $tmp = $this->pos_list($key);
    return empty($tmp)?$empty:max($tmp);
  }


  /* renumber a part of the data for insertion or so
   * path: path of an existing non-text node
   * start: first affected node number
   * step: offset for the new number
   */
  function renumber($path,$start,$step){
    if(def($this->data,$path,-1)!==0) return FALSE;
    $ckeys = array_keys($this->data);
    $keys = preg_grep("{^$path#}",$ckeys);
    $lp = strlen($path);
    foreach($keys as $pos=>$okey){
      $cpos = (int)substr($okey,$lp+1);
      if($cpos>=$start){
	if(is_numeric(substr($okey,$lp+1)))
	  $ckeys[$pos] = $path . '#' . ($step+$cpos);
	else
	  $ckeys[$pos] = $path . '#' . ($step+$cpos) .  substr($okey,strpos($okey,'/',$lp));
      }
    }
    $this->data = array_combine($ckeys,$this->data);
    return TRUE;
  }

  function number_reset($sort=TRUE){
    if($sort) $this->sort();
    $keys = array_keys($this->data);
    $res = array();
    $pos = array();
    foreach($this->data as $key=>$val){
      $parts = explode('#',$key);
      $n = count($parts)-1;
      $m = count($pos);
      if(strpos($key,'@')===FALSE and $n>0){
	if($n>$m) {
	  $pos[] = 0;
	} else if($n==$m){
	  $pos[$n-1]++;
	} else {
	  $pos = array_slice($pos,0,$n);
	  $pos[$n-1]++;
	}
      }
      $tkey = array_shift($parts);
      for($i=0;$i<count($pos);$i++) 
	$tkey .= '#' . $pos[$i] . '/' . substr($parts[$i],1+strpos($parts[$i],'/'));
      
      $res[$tkey] = $val;
    }
    $this->data = $res;
    return 0;
  }


  function names_get($keys){
    $res = array();
    foreach($keys as $ck)
      if(substr($ck,-1,1)=='@')
	$res[] = substr($ck,strrpos($ck,'/')+1,-1);
      else if(strpos($ck,'@')===FALSE)
	$res[] = substr($ck,strrpos($ck,'/')+1);
      else 
	$res[] = substr($ck,strrpos($ck,'@')+1);
    return $res;
  }

  /* search keys in data
   * key: name or pattern
   * path: exitsing path or NULL, limit result to path and below
   * pre:
   * typ: type restriction: 
   *    -1: none;           -2: attributes
   *     0: non-text-nodes;  1: text-nodes
   */
  protected function search_pattern($key,$path,$typ){
    switch($typ){
    case  0: $keys = array_keys($this->data,0,TRUE); $pre = '/'; break;
    case  1: $keys = array_keys($this->data,1,TRUE); $pre = '/'; break;
    case -1: $keys = array_keys($this->data);        $pre = '';  break;
    case -2: 
      $keys = preg_grep('/@/',array_keys($this->data));
      $pre = '@'; 
      break;
    }
    if(!is_null($path))
      $keys = preg_grep($this->pat('{^' . $path . '([#@].*)?$}'),$keys);
    $pat = preg_match('/^\w/',$key)?('{' . $pre . $key . '$}'):$key;
    return array_values(preg_grep($this->pat($pat),$keys));
  }

  /* replaces special pattern in pat
   * %H : short for the base  '^[$this->basekey]' (including ^)
   * %N : (tag)name;  see pat_tag
   * %P : position; 
   * %A : attribute; see pat_attr (including $)
   */
  function pat($pat){
    $pat = preg_replace('{%([0-9]+)}','^(%N%P){$1}',$pat);
    $pat = preg_replace('{%N([0-9]+)}','(%N%P){$1}',$pat);
    $pat = preg_replace('{%P([0-9]+)}','(%P%N){$1}',$pat);
    $pat = str_replace('%H','^/' . $this->basekey,$pat);
    $pat = str_replace('%N','/' . $this->pat_tag,$pat);
    $pat = str_replace('%P',$this->pat_pos,$pat);
    $pat = str_replace('%A','@' . $this->pat_attr . '$',$pat);
    return $pat;
  }

  // create line ident (incl linefeed)
  protected function indent($lev){
    return "\n" . str_repeat($this->indent,$lev % $this->layout_imod);
  }

  // how an error will be handled
  protected function err($txt){
    $this->error_msg = $txt; 
    switch($this->error_mode){
    case -1: die($txt);
    case -2: die();
    case 0: break;
    default:
      trigger_error($txt,$this->error_mode);
    }
  }

  // prepare attribute for write
  protected function write_attr($name,$value){
    if(strpos($value,'"')===false) 
      $val = '"' . htmlspecialchars($value,ENT_NOQUOTES,$this->xmlcharset) . '"';
    else if(strpos($value,"'")===false) 
      $val = "'" . htmlspecialchars($value,ENT_NOQUOTES,$this->xmlcharset) . "'";
    else
      $val = '"' . htmlspecialchars($value,ENT_COMPAT,$this->xmlcharset) . '"';
    return $name . '=' . $val;
  }

  // charset translations
  protected function charset($data,$rev){
    if($this->xmlcharset===$this->phpcharset) return $data ;
    if($rev) $case = $this->phpcharset .  ' > ' . $this->xmlcharset;
    else $case = $this->xmlcharset . ' > ' . $this->phpcharset;
    switch($case){
    case 'UTF-8 > ISO-8859-1': return utf8_decode($data);
    case 'ISO-8859-1 > UTF-8': return utf8_encode($data);
    }
    return $data;
  }


  /* ================================================================================
   ===================================== Write  =====================================
   ================================================================================*/

  /* the main write function
   *  filename: NULL (use $this->filename, same as on read), '' -> return string
   */
  function write($filename=null,$sort=TRUE){
    if($sort) $this->sort();
    if(is_null($filename)) $filename = $this->filename;
    $list = array();
    $cpath = 0;
    $tags = array();;
    $lev = 0;
    foreach($this->data as $key=>$val){
      $key = explode('@',$key,2);
      if(count($key)==1){
	array_unshift($tags,preg_replace('|.*/|','',$key[0]));
	$tmp = explode('/',preg_replace('|#\d+/|','/',$key[0]));
	array_shift($tmp); // empty element at the start
	array_pop($tmp);   // own node
	$list[$key[0]] = array('tag'=>$tags[0],'lev'=>count($tmp),
			       'attr'=>array(),'text'=>'',
			       'path'=>$tmp);
      } else if(strlen($key[1])==0){
	$list[$key[0]]['text'] = $val;
      } else {
	$list[$key[0]]['attr'][$key[1]] = $val;
      }
    }

    $list[] = array('lev'=>-1,'path'=>array());
    $list = array_values($list);

    $res = '<?xml version="1.0" encoding="' . $this->xmlcharset . '"?>';
    while($val = array_shift($list)){
      $res .= $this->write_open($val);
      if($val['text']!='')
	$res .= '>' . $val['text'] . '</' . $val['tag'] . '>';
      else if($val['lev']<$list[0]['lev'])
	$res .= '>';
      else 
	$res .= '/>';
      if($val['lev']<=$list[0]['lev']) continue;
      for($n=$val['lev']-1;$n>=$list[0]['lev'];$n--){
	if($n<0) break 2;
	$res .= $this->indent($n) . '</' . $val['path'][$n] . '>';
      }
    }
    if(empty($filename)) return $res;
    
    $fi = @fopen($filename,'w');
    if($fi===false) return FALSE;
    fwrite($fi,$res);
    fclose($fi);
    return TRUE;
  }

  protected function write_open($val){
    $res = $this->indent($val['lev']) . '<' . $val['tag'];
    if(count($val['attr'])==0) return $res;
    switch($this->layout_attr){
    case 0: $ind = ' '; break;
    case 1: $ind = $this->indent($val['lev']) . $this->indent_attr; break;
    default:
      $n = array_sum(array_map(create_function('$x','return strlen($x);'),$val['attr']))
	+ array_sum(array_map(create_function('$x','return strlen($x);'),array_keys($val['attr'])))
	+ 4*count($val['attr']) + strlen($res)-2;
      $ind = $n<$this->layout_ncol?' ':$this->indent($val['lev']) . $this->indent_attr;
    }
    foreach($val['attr'] as $ck=>$cv) 
      $res .= $ind . $this->write_attr($ck,$cv);
    return $res;
  }

  /* ================================================================================
   ==================================== Pasing  =====================================
   ================================================================================*/


  // Start ================================================================================
  protected function xmlp_start($parser, $name, $attrs){
    // inside a xhtml?
    if($this->xhtml_depth>0){
      $dat = array('name'=>$name,'attr'=>$attrs,'text'=>'');
      array_unshift($this->xhtml_data,$dat);
      $this->xhtml_depth++;
      return;
    } 

    // define current path
    if(count($this->xmlpath)==0){
      $this->basekey = $name;
      $cn = '/' . $name;
    } else $cn = $this->xmlpath[0] . '#' . ($this->xmlpos[0]++) . '/' . $name;

    $xat = $this->xhtml_at;

    // get type for this node
    $typ = $this->xmlp_type_get($cn,$attrs,$name);

    // are there special attributes ?
    foreach(preg_grep('{^' . $this->sx_attr_prefix . '}',array_keys($attrs)) as $ckey){
      $mth = 'sxatt__' . substr($ckey,strlen($this->sx_attr_prefix));
      if(method_exists($this,$mth)) $this->$mth($cn,$attrs[$ckey],$typ,$name,$attrs);
      unset($attrs[$ckey]);
    }
    
    // call type specific oepration to open node
    $mth = 'xmlp_start__' . $typ;
    array_unshift($this->xmlnt,$typ);
    if($this->$mth($cn,$name,$attrs)){
      array_unshift($this->xmlpath,$cn);
      array_unshift($this->xmlpos,0);
      array_unshift($this->xhtmlat,$xat);
    }
    $this->xmlp_attrs_add($cn,$attrs);
  }
  
  
  protected function xmlp_start__node($cn,$name,&$attrs){
    $this->data[$cn] = 0;
    return 1;
  }
  
  
  protected function xmlp_start__xhtml($cn,$name,&$attrs){
    $this->data[$cn] = 1;
    $this->xhtml_depth = 1;
    $dat = array('path'=>$cn,'name'=>$name,'attr'=>$attrs,'text'=>'');
    $this->xhtml_data = array(0=>$dat);
    return 0;
  }

  protected function xmlp_attrs_add($key,$attrs){
    foreach($attrs as $ak=>$av) 
      $this->data[$key . '@' . $ak] = $this->charset($av,FALSE);
  }
  


  // Spez attributes ============================================================
  protected function sxatt__xhtmlat($cn,$value,&$typ,$name,&$attrs){
    $this->xhtml_at = array($value);
  }

  protected function sxatt__nt($cn,$value,&$typ,$name,&$attrs){
    $typ = $value;
  }
  


  // Close ================================================================================
  protected function xmlp_end($parser, $name){
    if($this->xhtml_depth<=1){
      $mth = 'xmlp_end__' . array_shift($this->xmlnt);
      $this->$mth($name);
    } else $this->xmlp_end__xhtml_part($name);
  }

  protected function xmlp_end__node($name){
    array_shift($this->xmlpath);
    array_shift($this->xmlpos);
    $this->xhtml_at = array_shift($this->xhtmlat);
  }
  
  protected function xmlp_end__xhtml($name){
    $this->xhtml_depth = 0;
    $dat = array_shift($this->xhtml_data);
    $this->data[$dat['path'] . '@'] = trim($dat['text']);
  }

  protected function xmlp_end__xhtml_part($name){
    $this->xhtml_depth--;
    $dat = array_shift($this->xhtml_data);
    $nd = '<' . $dat['name'];
    foreach($dat['attr'] as $ak=>$av)
      $nd .= ' ' . $this->write_attr($ak,$av);
    if($dat['text']==='') $nd .= '/>';
    else $nd .= '>' . $dat['text'] . '</' . $dat['name'] . '>';
    $this->xhtml_data[0]['text'] .= $nd;
  }

  protected function xmlp_cdata($parser, $data){
    if($this->xhtml_depth>0) $this->xhtml_data[0]['text'] .= $data;
  }

  /* ================================================================================
   Language specials
   ================================================================================ 
   if an xml contains language dependet values the following function
   allow to uniform them for a faster access afterward by using
   a sequence of langauges (first wins)
   this functions add data and may also remove it
   

   Common things:
   $lngs: array of language code (en, de-ch ...). First avaiable will be used
   $sort: sort data at the end (since new elements are added at the end)
   $pat: pattern to find the language dependet values
         %A is replace by argument $attr
	 %P is replace by argument $prefix
	 %L is replace by the regex-list of lngs; eg (en|de|de-ch)
     if your attribute and/or languages are unique
     the default pattern works well
     Otherwise you have to adopt it, so that only the right nodes are used	 

   Afterwards a typically comand to read out the items as named array would be
    $xml->attrs_list($xd->search('ppat','{@value$}'),'val','id');
    $xml->attrs_list($xd->search('ppat','{/text$}'),'','id');
  */
  
  /* the language dependet values are saved as attribute
   * new_attr: name of the new attribute for the value (def: value)
   * prefix: if yout attributes have a prefix; eg word_en -> word_ is prefix; default ''
   * pat: pattern to find the attributes; defalut {@%P%L$}
   * remove: remove all found attributes (lmgs) afterwards
   */
  function reduce_lng_attr($lngs,$new_attr='value',$prefix='',$pat=NULL,
			   $remove=FALSE,$sort=TRUE){
    $pat = str_replace(array('%L','%P'),
		       array('(' . implode('|',$lngs) . ')',is_string($prefix)?$prefix:''),
		       is_string($pat)?$pat:'{@%P%L$}');
    $keys = array();
    foreach($this->search('ppat',$pat) as $ckey){
      list($ck,$cl) = explode('@',$ckey);
      if(isset($keys[$ck])) $keys[$ck][] = $cl;
      else $keys[$ck] = array($cl);
    }
    foreach($keys as $key=>$ll){
      $tmp = array_intersect($lngs,$ll);
      $cval = $this->attr_get(array_shift($tmp),$key);
      if($remove) $this->attr_unset($ll,$key);
      $this->set($cval,$key . '@' . (is_string($new_attr)?$new_attr:'value'));
    }
    if($sort) $this->sort();
  }



  /* returns text message (by id) or def
   * if def is null msg_na is used (and %id replaced by $id)
   */
  function msg($id,$def=NULL){
    try {
      $key = $this->id_search($id);
      if(is_null($key)) throw new Exception();
      if($this->data[$key]!=1) throw new Exception();
      return $this->data[$key . '@'];
    } catch (Exception $ex){
      if(is_null($def)) $def = $this->msg_na;
      if(substr($def,0,1)=='#') return $this->msg(substr($def,1));
      return str_replace('%id',$id,$def);
    }
  }


}
  
?>
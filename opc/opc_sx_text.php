<?php

  /* Variation of opc_sxml specialiced for text libraries
   allows using of multiple languages
     + load only best match
     + load all and use lng argument
   replacements inside the results
   late reading of values (from other sources or so)
  */

class opc_sx_text extends opc_sxml implements opi_multilng {

  /* defines how is read in
   * 0: read: all is saved
   * 1: best result from lng_order is saved only
   */
  public $mode = 1;

  protected $prefix_sep = '-';

  public $subfiles = array();

  public $ext_use = TRUE;
  public $ext_prefix = '@:';

  public $repl_use = TRUE;
  public $repl_fct = 'replace';
  public $repl_lim = '%';
  public $repl_dict = array();

  /* anguages --------------------------------------------------
   * list: list of languages key=>array(label)
   * order: list of lng-ids first if preferd
   */
  public $lng_list = array('en'=>array('label'=>'English'));
  public $lng_order = array('en');

  /* temporary values for reading file
   * lng_file: language given as argument to read
   * lng_file_int: language set in main-tag attribute lng
   * prefix_file: prefix  given as argument to read
   */
  protected $lng_file = NULL;
  protected $lng_file_int = NULL;
  protected $prefix_file = NULL;
  

  // which nodes do contain text data ( node-name=>type )
  public $lng_nodes = array('text'=>'text',
			    'word'=>'word',
			    'mltext'=>'mltext',
			    'group'=>'group');
  
  // pattren to recognice the single items of a multi lingual node, set by method read
  protected $pat_ml = NULL; 

  // where the texts are saved!
  public $txts = array();


  // allow magic get if leading _
  function __get($key){ 
    if(substr($key,0,1)=='_') return $this->text(substr($key,1),NULL);
    return NULL;
  }

  // the default access functions
  function t($key,$def=NULL)   { 
    return $this->text($key,$def);
  }
 
  function text($key,$def=NULL){ 
    $res = def($this->txts,$key,$def);
    if($this->ext_use and substr($res,0,strlen($this->ext_prefix))==$this->ext_prefix)
      $res = $this->readlate(explode(':',substr($res,strlen($this->ext_prefix))));
    if($this->repl_use and method_exists($this,$this->repl_fct))
      $res = $this->{$this->repl_fct}($res);
    return $res;
  }

  /* tries multiple langauges (lngs is one or more lng-ids)
   * found is set to
   *  1: key unkown
   *  2: not in ml mode
   *  3: none of the languages not found
   *  [lng-key]
   */
  function text_lng($key,$lngs,$def=NULL,&$found=NULL){
    if(!isset($this->txts[$key])) { $found = 1; return $def;}
    $res = $this->txts[$key];
    if(is_scalar($res)) {$found = 2; return $res;}
    foreach((array)$lngs as  $found) 
      if(isset($res[$found])) return $res[$found];
    $found = 3;
    return $def;
  }


  // add langauge to lng_list and lng_order
  function lng_add($code,$label=NULL){
    if(isset($this->lng_list[$code])) return -1;
    if(!in_array($code,$this->lng_order)) $this->lng_order[] = $code;
    $this->lng_list[$code] = array('label'=>is_null($label)?$code:$label);
    return 0;
  }


  // hooked method, takes care of pat_ml and lng_nodes
  protected function xmlp_type_get($path,&$attrs,$name=NULL){
    $tmp = parent::xmlp_type_get($path,$attrs,$name);
    if($tmp=='xhtml') return $tmp;
    if($name=='languages') return 'lngs';
    if(!is_null($this->pat_ml) and preg_match($this->pat_ml,$path)) return 'xhtml';
    if(!isset($this->lng_nodes[$name])) return 'node';
    return $this->lng_nodes[$name];
  }

  protected function xmlp_start__node($cn,$name,&$attrs){
    if(count($this->xmlpath)==0 and isset($attrs['lng'])) 
      $this->lng_file = $attrs['lng'];
    return parent::xmlp_start__node($cn,$name,$attrs);
  }

  protected function xmlp_start__group($cn,$name,&$attrs){
    $id = def($attrs,'id');
    if(is_null($this->prefix_file)) $this->prefix_file = $id;
    else $this->prefix_file .= $id;
    $this->prefix_file .= $this->prefix_sep;
    return parent::xmlp_start__node($cn,$name,$attrs);
  }


  protected function xmlp_start__lngs($cn,$name,&$attrs){
    return parent::xmlp_start__node($cn,$name,$attrs);
  }

  protected function xmlp_start__word($cn,$name,&$attrs){
    return parent::xmlp_start__node($cn,$name,$attrs);
  }

  protected function xmlp_start__text($cn,$name,&$attrs){
    return parent::xmlp_start__xhtml($cn,$name,$attrs);
  }

  protected function xmlp_start__mltext($cn,$name,&$attrs){
    return parent::xmlp_start__node($cn,$name,$attrs);
  }


  /* adds the lng_list definied in the given block
   * called if a lng_list block (on top level) in a xml ends */
  protected function xmlp_end__lngs($name){
    $key = $this->xmlpath[0];
    foreach($this->search('ppat',"{^$key%P/language$}") as $ck)
      $this->lng_add($this->attr_get('id',$ck),$this->attr_get('label',$ck));
    $this->node_unset($key);
    $this->pat_ml_make();
    parent::xmlp_end__node($name);
  }

  // update pat_ml for xhtml recognition
  protected function pat_ml_make(){
    $tmp = array_keys($this->lng_nodes,'mltext');
    if(empty($tmp)){ $this->pat_ml = NULL; return -1; }
    $lng = array_keys($this->lng_list);
    $this->pat_ml = $this->pat('{/(' . implode('|',$tmp) .')%P'
			       . '/(' . implode('|',$lng) . ')$}');
    return 0;
  }


  // add text to txts using id, language and content
  function text_add($id,$lng,$cont){
    static $best = array();
    $key = $this->prefix_file  . $id;
    switch($this->mode){
    case 0: 
      $this->txts[$key][$lng] = $cont; 
      break;
    case 1:
      $pos = array_search($lng,$this->lng_order);
      if($pos!==FALSE and (!isset($best[$key]) or $best[$key]>$pos)){
	$best[$key] = $pos;
	$this->txts[$key] = $cont;
      } else if(!isset($this->txts[$key])){
	$this->txts[$key] = $cont;
      }
    }
  }
  

  /* hooked and extended read
   * prefix: will be used by every id as prefix
   *  useful to prevent name conflicts if multiple files are used
   * lng: default language for this file
   *   ignored if in the root tag an attribute lng is given
   */
  function read($filename=NULL,$lng='en',$prefix=NULL){
    $this->prefix_file = $prefix;
    if(!is_null($prefix)) $this->prefix_file .= $this->prefix_sep;
    $this->lng_file = $lng;
    $this->pat_ml_make();
    $tmp = parent::read($filename);
    return $tmp;
  }

  function readn($files,$lng='en'){
    $res = array();
    foreach($files as $key=>$file){
      $res[$key] = $this->read($file,$lng,is_numeric($key)?NULL:$key);
    }
    return $res;
  }

  function read_subfile($sub,$lng){
    if(is_null($lng)) $lng = $this->lng_order[0];
    $this->read($this->subfiles[$sub],$lng,$sub);
  }

  // hooked method write (disabale it)
  function write($filename=null,$sort=TRUE){
    trigger_error('opc_sx_text works only with read only data');
    return FALSE;
  }


  // reduces txt (which is still a table to a single language
  function reduce($ord=NULL,$save=TRUE){
    if($this->mode!=0) return 1;
    if(is_null($ord)) $ord = $this->lng_order; else $this->lng_order = $ord;
    $res = array();
    foreach($this->txts as $key=>$values){
      foreach($ord as $cl) if(isset($values[$cl])) { $res[$key] = $values[$cl]; break;}
    }
    if($save){
      $this->txts = $res;
      $this->mode = 1;
      return 0;
    } else return $res;
  }


  /* ============================================================
   close functions
   ============================================================
   called by xmlp_end as definied in lng_nodes[?][fct]
  */

  function xmlp_end__group($name){
    $tmp = explode($this->prefix_sep,$this->prefix_file);
    array_pop($tmp);
    array_pop($tmp);
    $this->prefix_file = implode($this->prefix_sep,$tmp);
    $this->prefix_file .= $this->prefix_sep;
    parent::xmlp_end__node($name);
  }

  // text in attributes, named by their language
  function xmlp_end__word($name){
    $dat = $this->attr_geta($this->xmlpath[0]);
    $id = def($dat,'id');
    if(is_null($id)) return 1;
    foreach($this->lng_list as $lng=>$dum)
      if(isset($dat[$lng])) $this->text_add($id,$lng,$dat[$lng]);
    parent::xmlp_end__node($name);
  }

  // text as child data, language defined by attribute lng
  function xmlp_end__text($name){
    $dat = $this->xhtml_data[0];
    $id = def($dat['attr'],'id');
    if(!is_null($id)){ 
      $lng = def($dat['attr'],'lng',$this->lng_file);
      $this->text_add($id,$lng,$dat['text']);
    }
    parent::xmlp_end__xhtml($name);
  }
  
  // text in subchild-data, child-nodes named by their language
  function xmlp_end__mltext($name){
    $pat = '{^' . $this->xmlpath[0] . '%P/('
      .implode('|',array_keys($this->lng_list)) . ')$}';
    $id = $this->attr_get('id',$this->xmlpath[0]);
    if(empty($id)) return 1;
    foreach($this->search('ppat',$pat) as $ckey){
      $lng = $this->node_name_get($ckey);
      $txt = $this->text_get($ckey);
      $this->text_add($id,$lng,$txt);
    }
    parent::xmlp_end__node($name);
  }

  /* ============================================================
   Load from other sources
   ============================================================ */

  protected function readlate($args){
    $mth = 'readlate__' . array_shift($args);
    if(method_exists($this,$mth)) return $this->$mth($args);
    return substr($mth,10) . ':' . implode(':',$args);
  }

  protected function readlate__file_xml($args){
    $file = array_shift($args);
    $cls = get_class($this);
    $con = new $cls($file);
    if(count($args)==0) return array_shift($con->txts);
    qy('readlate__file_xml with additional arguments');
  }

  /* ============================================================
   Replacements
   ============================================================ */
  protected function replace($res){
    foreach($this->repl_dict as $key=>$val)
      $res = str_replace($this->repl_lim . $key . $this->repl_lim,$val,$res);
    return $res;
  }
  
  function array_adjust(&$data,$lng='en',$sub=NULL){
    if(isset($this->subfiles[$sub])) {
      $this->read_subfile($sub,$lng);
      $pre = $sub . $this->prefix_sep;
    } else $pre = '';
    foreach($data as $key=>$val)
      $data[$key] = $this->text($pre . $key,$val);
    return 0;
  }

  }
?>
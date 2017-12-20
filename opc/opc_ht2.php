<?php
interface opi_ht2 {}
/*
 
  idea: man kann was unter einem key-word ablegen und dann vai incl_internal einziehen
    bsp process generiert meldung, weiss aer nciht wo anzeigen, page-builder
    weiss wo, nicht aber was.
    


  bug:
    Src-Layout: wo sind genau zeilenumbrueche etc erlaubt!!!

  methods for clone/copy a node (inkl subnodes and so on)

  special attr: will add the new key to list with this attr as name (usefull for link them later)
                optional with not link this item to the current list (for not using in/out all the time)

  funktionlitaet in pointer auslagern! 
    links; copy-paste etc.

  pseudo-tag chapter which embeds its content and allwos relativ levels

  info-system: als post-processor?
  
  das mit nxt besser koordinieren: standardfall direkt (aus tag/add rausnehmen)
                                   spezialfall analog d/da or tag_at add_at

				   

  externe daten analog zu iframe				   

  idea add mit htmlspecialchars and co

  caluclated style-values (repalced as late as possible)

  speziall attr embed: damit man einen tag einfach umfassen kann?

  function which walk through structer (eg uppercase to all content, test all attributes)
   probaly as external class to keep ht2 lean

  copy_paste ein p -> wieder offen -> close nötig???

  dummy objekte, die bei export löschungen oder reaktionen hervorbringen können
     wenn bsp: keine Kindelemente, kein nachfolger oder ähnliches

  pointer zeigt auf element in struktur plus wo nächstes
   -> fcl, lcl, prv, prv-prv, nxt, nxt-nxt fcl-nxt (2. meint wie bewegt sich der zeiger)

  hint: tag wrap is a invisible cover for its (visible) content

  idea: auto-attr =NAME -> 'id'=>NAME
  idea: auto-attr mit numerischen Schluessel: gleiche Erkennung wie 'direkt'
  
  import/export: xml, nested-array

  h with relative arguments (auto adjust to h?)

  nopen

  links: ftp / skype

  callbacks bei eregnissen (wie h inserted etc)

  renume clone und ähnliches auslagern 8selten gebruacht)
  pointer-move via externe eigene klasse?
  suchen (auch als externe eigene klasse?)
  
  speed __call *2s versus method ...2s
  garbage collection???
  deep copy / clone (out/in)
  sortieren
  import/export
  hto objekte einbinden?
  style-management
  source-layout
  haeder-objekt/framework
  performance meassuering
  h? dynamisch/auto-adjust (beim import wird dieses angepasst/verschoben)
  trace functionality (to construct an index or so)
  serialize in/out
  
  pseudotypen ausbauen
  def-class/style bedeutet genau? nur wenn leer, hinzufügen?
  move: end geht möglichst weit nach hinten/unten (beachtet open==0 aber)
*/

/**
   ================================================================================
   * class to build html documents using php class functionality
   *
   ================================================================================ 
*/
class opc_ht2 implements opi_ht2, countable, ArrayAccess{
  /** settings of the class 
   * 
   * Elements
   *  - xhtml bool: create xhtml (TRUE) or html (FALSE) conform
   *  srclayout: for source: 0: no, 1: basic
   *  - empty array(tag-name=>mode) how handle empty tags<br>
   *    + 0(def): just the open-part, in xhtml including the /<br>
   *    + 1: skip completly (return an empty string)<br>
   *    + 2: use open- and close-part with nothing inbetween<br>
   *    + [string]: use open- and close-part with 'mode' inbetween
   */
  protected $set = array('xhtml'=>TRUE,
			 'type'=>'strict',
			 'srclayout'=>1,
			 'charset'=>'UTF-8',
			 'charset_source'=>NULL,

			 // what if tag is empty: 0: standard, 1: skip, 2: use <tag></tag>
			 'empty'=>array('dl'=>1,'dt'=>1,'dd'=>1,'ul'=>1,'ol'=>1,'li'=>1,
					'span'=>1,'div'=>1,'p'=>1,'b'=>1,'i'=>1,'tt'=>1,
					'button'=>1,
					'table'=>1,'tr'=>1,'colgroup'=>2,
					'td'=>2,'th'=>2,
					'select'=>2,
					'script'=>2,'a'=>2,'textarea'=>2),

			 'def'=>array('a:ext'=>array('target'=>'_blank',
						     'title'=>'external page'),
				      'a:mail'=>array('title'=>'Send mail'),
				      'a:broken'=>array('title'=>'page not available',
							'class'=>'link_broken')),
			 // number of newlines
			 'nl'=>array('head'=>1,'link'=>1,'style'=>1,'meta'=>1,
				     'br'=>1,'hr'=>1,
				     'td'=>1,'th'=>1,'li'=>1,'dt'=>1,'dd'=>1,
				     'option'=>1,'optgroup'=>1,
				     'script'=>2,
				     'h4'=>2,'p'=>2,'div'=>2,'pre'=>2,
				     'tr'=>2,'dl'=>2,'ul'=>2,'ol'=>2,
				     'fieldset'=>2,
				     'h3'=>3,'form'=>3,'table'=>3,'body'=>3,
				     'h2'=>4,
				     'h1'=>5),
			 );

  

  /** default arguments used in method link */
  public $args = array();

  public $z = 0;

  /** saves the structer of the current html (only keys of data)  
   * array(key=>array(details), ...)<br>
   * details: named-array<br>
   * typ: type of saved data (current: root, tag, txt)<br>
   * tag: subtype (typically NULL or tag-name)<br>
   *   in export the tag-information of the attributes overruels this one <br>
   * key: same as array key itself<br>
   * par: key of the parent node<br>
   * prv: key of the previous sibling<br>
   * nxt: key of the next sibling<br>
   * fcl: key of the first child<br>
   * lcl: key of the last child<br>
   */
  protected $str = array();

  /** array of all items (each of class opc_ht2item) */
  protected $data = array();

  /** external object for current status and error handling */
  protected $err = NULL;


  /** next key for str/data*/
  protected $skey = 0;

  /** class names used for subobjects */
  protected $extcls = array('err'=>'opc_status',
			    'attrs'=>'opc_attrs',
			    'ptr'=>'opc_ptr_ht2',
			    'form'=>'opc_ptr_ht2form',
			    'rem'=>'opc_comment');

  /** class names used for subobjects */
  protected static $statcls = array('err'=>'opc_status',
				    'attrs'=>'opc_attrs',
				    'ptr'=>'opc_ptr_ht2',
				    'form'=>'opc_ptr_ht2form',
				    'rem'=>'opc_comment');

  /** current exort region */
  protected $expres = array(); 
  /** stack for expres to allow nested exports */
  protected $expstack = array(); 
  /** current depth over all! (not set to 0 by stacking)*/
  protected $explev = 0;
  /** last new line level during export, used for layout */
  protected $explastnl = 0;

  protected $cb_open = NULL;
  protected $cbargs_open = NULL;

  protected $marks = array();

  protected $fw = NULL;

  static function get_instance($typ='ptr'){
    if(!isset(self::$statcls[$typ])) trigger_error("unkown class aked in get_temp_instance: $typ",E_USER_ERROR);
    do {
      $key = '_opc_ht2o_' . rand();
    } while(isset($GLOBALS[$key]));
    $cls = self::$statcls[$typ];
    $GLOBALS[$key] = new opc_ht2();
    return new $cls($GLOBALS[$key]);
  }

  /* ================================================================================
   Initialize and managment
   ================================================================================ */

  /** constructer
   * @param array $set used for {@link set_settings}
   */
  function __construct(/* ... */){
    $this->err = new $this->extcls['err']($this->_msgs());
    $this->reset();
    $set = array();
    foreach(func_get_args() as $ca){
      if(is_array($ca)) 
	$set = array_merge($set,$ca);
      else if(is_bool($ca)) 
	$set['xhtml'] = $ca;
      else if(is_string($ca)) 
	$set['charset'] = $ca;
      else if(is_object($ca)){
	if($ca instanceof opc_ht2){
	  $set = array_merge($set,$ca->set);
	} else if($ca instanceof opc_fw){
	  $this->fw = $ca;
	  $set = array_merge($set,$ca->prop);
	}
      }
    }
    $this->set_settings($set);
  }

     /** Reset the current object */
  function reset(){
    $this->str = array();
    $this->data = array();
    $this->skey = 0;
    $this->implant('_orph_','orph',NULL,NULL,'orph');
  }

  /** Definies the current message-table for {@link status} */
  protected function _msgs(){
    return array(1=>array('no read access','warnigns'),
		 2=>array('no write access','warnigns'),
		 10=>array('invalid settings','warning'),
		 50=>array('unkown element','warning'));
  }

  /** set settings
   * @param named-array|opc_ht2 $set: array(setting-key=>value)
   * @return null
   */
  function set_settings($set){
    if(!is_array($set)) return $this->err->errM(10,'set is not an array');

    // loop through the rest --------------------------------------------------
    foreach($set as $key=>$val) $this->set($key,$val);
  }


  /* ================================================================================
     Array Access: key is one of the data array
     Countable (implement)
     ================================================================================ */
  /** checks if the key is given in data */
  function offsetExists($key){ 
    return isset($this->data[$key]);
  }

  /** reset the data element to an empty opc_attrs with the same tag */
  function offsetUnset($key){ 
    if(isset($this->data[$key]))
      $this->data[$key] = new opc_attrs($this->str[$key]['tag']);
  }

  /** returns the data element */
  function offsetGet($key){
    return isset($this->data[$key])?$this->data[$key]:NULL;
  }

  /** replace the data element */
  function offsetSet($key,$val){
    if(!isset($this->data[$key])){
      trigger_error('cant create a new data object');
    } else if($val instanceof opc_attrs){
      $this->data[$key] = $val;
      $this->str[$key]['tag'] = $val['tag'];
    } else if(is_string($val) and $this->str[$key]['typ']=='txt'){
      $this->data[$key] = $val;
    } else trigger_error('invalid new attribute');
  }
  /** count number of saved items */
  function count(){return count($this->str);}
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  /** returns the type of a saved item
   * @param $key: key of an (existing) item or NULL for current item
   * @param int $mode: defines what exactley is returned
   * @return: mode 1: tag-name, [other]: array('typ'=>typ,'tag'=>tag)
   */
  function get_type($key,$mode=0){
    if(!isset($this->str[$key])) return FALSE;
    switch($mode){
    case 1: return $this->str[$key]['tag'];
    default:
      return array('typ'=>$this->str[$key]['typ'],'tag'=>$this->str[$key]['tag']);
    }
  }

  function quest($key,$quest){
    switch($quest){
    case 'is_empty': return is_null($this->str[$key]['fcl']);
    }
    
  }

  /* ________________________________________________________________________________ */



  /* ================================================================================
   Magic
   ================================================================================ */
  /** Magic function for to-string conversion*/
  function __toString(){return $this->exp2html(0);}

  /** overload {@link ___get} instead */
  final function __get($key){
    if($this->___get($key)==TRUE) return $key;
    return $this->err->err(array(1,$key));
  }

  /**
   * read-only access to certain variabels
   *
   * should be overlaoded instead of __get
   * key accepted: save results in $key (side effect) and return TRUE<br>
   * if not: call parent (or return FALSE)
   */
  protected function ___get(&$key){
    switch($key){
    case 'set': 
      $key = $this->set; return TRUE;
    case 'xhtml': case 'charset': case 'charset_source': case 'type':
      $key = $this->set[$key]; return TRUE;
    case 'data': case 'str': 
      $key = $this->$key; return TRUE;
    }
    return FALSE;
  }

  /** overload {@link ___set} instead */
  function __set($key,$val){
    if($this->___set($key,$val)==TRUE) return $this->err->ok();
    return $this->err->err(array(2,$key));
  }

  /**
   * restricted write access to certain variabels
   *
   * should be overlaoded instead of __set
   * key accepted: save results and return TRUE<br>
   * otherwise call aprent or return FALSE
   */
  function ___set($key,$value){
    switch($key){
    case 'xhtml': case 'srclyout': case 'charset': case 'charset_source':
      return $this->set($key,$value); 
    case 'value': case 'val': $this->set($value);          return TRUE;
    case 'settings':          $this->set_settings($value); return TRUE;
    case 'key':               $this->set_key($value);      return TRUE;
    }
    return FALSE;
  }


  function set($key,$val){
    switch($key){
    case 'srclayout':
      if(is_int($val) and $val>=0) $this->set[$key] = $val;
      else return $this->err->errM(10,'not integer (>=0)',$key);
      break;
    case 'charset_source':
      if(is_string($val) or is_null($val)) $this->set[$key] = $val;
      else return $this->err->errM(10,'neither a string nor NULL',$key);
      break;
    case 'charset': case 'type':
      if(is_string($val)) $this->set[$key] = $val;
      else return $this->err->errM(10,'not a string',$key);
      break;
    case 'xhtml': 
      if(is_bool($val)) $this->set[$key] = $val;
      else return $this->err->errM(10,'not a boolean',$key);
      break;
    }
    return TRUE;
  }

  function get($key,$def=NULL){
    return def($this->set,$key,$def);
  }

  /** next possible key for string/data*/
  protected function skey(){return $this->skey++;}

  function set_single($text,$key=NULL,$tag=NULL){
    if(!is_object($key)) 
      $id = $key;
    else if($key instanceof opc_ht2e) 
      $id = $key->root;
    else
      return NULL;
    $nkey = $this->ptr_new($key,$tag,'single');
    $this->data[$nkey] = $text;
    return $nkey;
  }



  function ptr_new($key=NULL,$tag=NULL,$typ='root'){
    if(is_null($key)){
      $key = $this->skey();
    } else if(is_numeric($key) and $this->skey<=$key){
      $this->skey = $key+1;
    }
    if(!isset($this->str[$key])) {
      $this->str[$key] = array('typ'=>$typ,'tag'=>$tag,'key'=>$key,
			       'par'=>NULL,'prv'=>NULL,'nxt'=>NULL,
			       'fcl'=>NULL,'lcl'=>NULL);
    }
    return $key;    
  }

  function ptr($add=array()){
    if($this->fw instanceof opc_fw)
      return $this->fw->ptr($add);
    trigger_error('Cant return pointer');
    return FALSE;
  }  

  /* 
   * mode: behaviour if key is already knwon
   *  0: use it
   *  1: return NULL
   */
  function key_get($key,$mode=0){
    
    
  }


  /* ================================================================================
   Building
   ================================================================================ */

  /** Sets a given item at the asked position. One of the working horses of this class
   *
   * @param string $typ will be saved in {@link $str}
   * @param string $tag will be saved in {@link $str}
   * @param mixed $data will be saved in {@link $data}
   * @param string $rel defines where the item is set relative to $at (see {@link get_rel})
   * @param string $at position, current position is used if NULL
   * @param string $key to for str/data, if NULL a new one is generated
   */
  function insert($typ,$tag,$obj,$rel,$at=NULL,$key=NULL){
    if(isset($this->cb_open) and $typ=='tag'){
      if(in_array($tag,$this->cbargs_open))
	 call_user_func($this->cb_open,$tag,$obj);
    }

    if(is_null($key)) $key = $this->skey();
    else if(!isset($this->str[$key])) return FALSE;

    else if(is_scalar($at)){
      if(!isset($this->str[$at])) return FALSE;
      else $at = $this->str[$at];
    }
    $this->data[$key] = $obj;

    if($rel=='emb') $res = $this->implant_embed($key,$typ,$tag,$at);
    else $res = $this->implant($key,$typ,$tag,$at,$rel);
    return $res===FALSE?FALSE:$key;
  }
  
  /** sub of insert
   *
   * @param string $key to for str/data
   * @param string $typ will be saved in {@link $str}
   * @param string $tag will be saved in {@link $str}
   * @param string $rel defines where the item is set relative to $at (see {@link get_rel})
   * @param string $at position, current position is used if NULL
   */
  function implant($key,$typ,$tag,$at,$rel){
    list($par,$prv,$nxt) = $this->get_rel($at,$rel);
    $this->str[$key] = array('key'=>$key,'typ'=>$typ,'tag'=>$tag,
			     'par'=>$par,'prv'=>$prv,'nxt'=>$nxt,
			     'fcl'=>NULL,'lcl'=>NULL);

    if(!is_null($prv)) $this->str[$prv]['nxt'] = $key;
    else if(!is_null($par)) $this->str[$par]['fcl'] = $key;

    if(!is_null($nxt)) $this->str[$nxt]['prv'] = $key;
    else if(!is_null($par)) $this->str[$par]['lcl'] = $key;

    return TRUE;
  }

  function implant_embed($key,$typ,$tag,$at){
    $par = $at['par'];
    if(is_null($par)){ // is a root-key
      $this->str[$key] = array('key'=>$key,'typ'=>$typ,'tag'=>$tag,
			       'par'=>$at['key'],'prv'=>NULL,'nxt'=>NULL,
			       'fcl'=>$at['fcl'],'lcl'=>$at['lcl']);
      $this->str[$at['fcl']]['par'] = $key;
      $this->str[$at['lcl']]['par'] = $key;
      $this->str[$at['key']]['fcl'] = $key;
      $this->str[$at['key']]['lcl'] = $key;

    } else { // default case
      $prv = $at['prv'];
      $nxt = $at['nxt'];
      $this->str[$key] = array('key'=>$key,'typ'=>$typ,'tag'=>$tag,
			       'par'=>$par,'prv'=>$prv,'nxt'=>$nxt,
			       'fcl'=>$at['key'],'lcl'=>$at['key']);
      
      if(!is_null($prv)) $this->str[$prv]['nxt'] = $key;
      else $this->str[$par]['fcl'] = $key;
      
      if(!is_null($nxt)) $this->str[$nxt]['prv'] = $key;
      else $this->str[$par]['lcl'] = $key;
      
      $this->str[$at['key']]['par'] = $key;
      $this->str[$at['key']]['nxt'] = NULL;
      $this->str[$at['key']]['prv'] = NULL;
    }
    return TRUE;
  }
  

  /** return array of relatives
   * @param key $cp pointer
   * @param string $where return pointer to ...
   *   - nxt: ... insert after the given pointer
   *   - lcl: ... insert as last child of the given pointer
   *   - prv: ... insert before the given pointer
   *   - fcl: ... insert as first child of the given pointer
   *   - emb: ... embed the given pointer
   *   - orph: .. a new orphan (not linked to a root)
   * @return array with three pointer: parent, previous, next
   */
  protected function get_rel($skey,$where){
    if(is_null($skey) or $where=='orph') return array(NULL,NULL,NULL);

    switch($where){
    case 'nxt': return array($skey['par'],$skey['key'],$skey['nxt']);
    case 'lcl':	return array($skey['key'],$skey['lcl'],NULL);
    case 'prv': return array($skey['par'],$skey['prv'],$skey['key']);
    case 'fcl':	return array($skey['key'],NULL,$skey['fcl']);
    case 'emb': return array($skey['par'],$skey['prv'],$skey['nxt']);
    }
    qk();
    trigger_error('Unknown relation: ' . $where,E_USER_ERROR);
    return NULL;
  }

  /** embeds multiple elements into multiple 
   * @param  key-array $keys: elements to embed
   * @param  key-array $at: targets for elements
   * @return T/F
   */
  function embed_m($keys,$at){
    $ne = count($keys);
    if($ne!=count($at)) return FALSE;
    if($ne==0) return TRUE;
    // cut $at from current str
    $this->adj_cut($at[0],$at[$ne-1]);
    // adjust env-str from keys to at
    $par = $this->str[$keys[0]]['par'];
    if(is_null($prv = $this->str[$keys[0]]['prv']))     $this->str[$par]['fcl'] = $at[0];
    else                                                $this->str[$prv]['nxt'] = $at[0];
    if(is_null($nxt = $this->str[$keys[$ne-1]]['nxt'])) $this->str[$par]['lcl'] = $at[$ne-1];
    else                                                $this->str[$nxt]['prv'] = $at[$ne-1];
    // adjust at to env-str
    $this->str[$at[0]]['prv'] = $this->str[$keys[0]]['prv'];
    $this->str[$at[$ne-1]]['nxt'] = $this->str[$keys[$ne-1]]['nxt'];
    foreach($at as $ca) $this->str[$ca]['par'] = $par;
    // adjust keys/at items
    while($ck = array_shift($keys)){
      $ca = array_shift($at);
      $this->str[$ck]['prv'] = NULL;
      $this->str[$ck]['nxt'] = NULL;
      $this->str[$ck]['par'] = $ca;
      $this->str[$ca]['fcl'] = $ck;
      $this->str[$ca]['lcl'] = $ck;
    }
    return TRUE;
  }

  /** embeds multiple elements into a single one
   * @param  key-array $keys: elements to embed
   * @param  key $at: target for elements
   * @return T/F
   */
  function embed_n($keys,$at){
    $ne = count($keys);
    if($ne==0) return TRUE;
    // cut $at from current str
    $this->adj_cut($at);
    // adjust enviroment
    $par = $this->str[$keys[0]]['par'];
    if(is_null($prv = $this->str[$keys[0]]['prv']))     $this->str[$par]['fcl'] = $at;
    else                                                $this->str[$prv]['nxt'] = $at;
    if(is_null($nxt = $this->str[$keys[$ne-1]]['nxt'])) $this->str[$par]['lcl'] = $at;
    else                                                $this->str[$nxt]['prv'] = $at;
    // adjust at to env-str
    $this->str[$at]['prv'] = $this->str[$keys[0]]['prv'];
    $this->str[$at]['nxt'] = $this->str[$keys[$ne-1]]['nxt'];
    $this->str[$at]['par'] = $par;
    // adjust at intern
    $this->str[$at]['fcl'] = $keys[0];
    $this->str[$at]['lcl'] = $keys[$ne-1];
    // adjust keys
    $this->str[$keys[0]]['prv'] = NULL;
    $this->str[$keys[$ne-1]]['nxt'] = NULL;
    foreach($keys as $ck)  $this->str[$ck]['par'] = $at;
    return TRUE;
  }





  /** translate all inputs to utf8 (if not already) */
  function enc2utf8($text,$enc){
    switch(strtolower($enc)){
    case 'utf-8': return $text;
    case 'iso-8859-1': return utf8_encode($text);
    default:
      trigger_error("unkown encoding translation from $enc to utf-8",E_USER_WARNING);
      return $text;
    }
  }

  /** final translation of the result to the target charset */
  function enc2final($text){
    switch(strtolower($this->set['charset'])){
    case 'utf-8': return $text;
    case 'iso-8859-1': return utf8_decode($text);
    default:
      trigger_error('unkown encoding translation from utf8 to ' . $this->set['charset'],E_USER_WARNING);
      return $text;
    }
  }

  /* ================================================================================
     export
     ================================================================================ */
  
  /** export a pointer including header saved in 0 */
  function exp2html($ptr,$head=0){
    $parkey = ($ptr instanceof opc_ptr_ht2)?$ptr->root:$ptr;

    if(is_null($head)) $head = 0;
    if($head!==FALSE){
      if(!($head instanceof opc_ptr_ht2)) $head = new opc_ptr_ht2($this,$head);
      if($head->search_child('html','tag')===FALSE) { 
	qz($this);
	die;
	return FALSE; 
      }
      if($head->search_child('body','tag')===FALSE) { 
	$bkey = $head->open('body');
	$head->incl($parkey);
      } else $bkey = $head->incl($parkey);
      $parkey = $head->root;
    } else $bkey = NULL;



    $res = $this->exp__key($parkey);
    if(!is_null($bkey)) $this->remove($bkey);
    return $this->enc2final($res);
  }


  protected function exp__explistitem($str,$case){
    $str['ikey'] = array();
    $str['case'] = $case;
    $str['mth'] = 'export_' . $str['typ'] . '_' . $case;
    return $str;
  }

  // creates an array with all nodes that have to be used for an export in the right order
  protected function exp__explist($key){
    $first = $key;
    $list = array();
    do{
      $str = $this->str[$key];
      if(is_null($str['fcl'])){ // ------------------------------------------------ no childs -> direct
	$list[] = $this->exp__explistitem($str,'direct');
	if($key===$first) return $list; // finished
	if(is_null($str['nxt'])){  // -------------------------------------------- last child -> close
	  do{
	    $key = $str['par'];
	    $str = $this->str[$key];
	    $list[] = $this->exp__explistitem($str,'close');
	    if($key===$first) return $list; // finished
	  } while(is_null($str['nxt'])); // are there no further childs? -> close next level
	} 
	$key = $str['nxt'];
      } else { //  ------------------------------------------------------------ open node
	$list[] = $this->exp__explistitem($str,'open');
	$key = $str['fcl'];
      }
    } while(TRUE);
  }

  protected function exp__key($key){
    if(is_null($key)) return array();
    $first = $key;
    array_unshift($this->expstack,$this->expres);
    $list = $this->exp__explist($key);

    $this->expres = array();
    $opk = array(); // list of keys of open parents, key refers to $this->expres;
    foreach($list as $elkey=>$citem){
      switch($citem['case']){
      case 'open':
	$citem['res'] = $this->$citem['mth']($citem['key'],$opk);
	$opk[] = count($this->expres); 
	$this->explev++;
	break;

      case 'direct':
	$citem['res'] = $this->$citem['mth']($citem['key'],$opk);
	break;

      case 'close':
	$con = array_map(create_function('$x','return $x["res"];'),
			 array_splice($this->expres,array_pop($opk)+1));
	$citem['res'] = $this->$citem['mth']($citem['key'],$opk,array_pop($this->expres),$con);
	$this->explev--;
	break;
      }
      $this->expres[] = $citem;
    }
    $res =  $this->expres[0]['res'];
    $this->expres = array_shift($this->expstack);
    return $res;
  }   


  /* ================================================================================
   export per type
   ================================================================================*/ 


  /** 
   * $key: key in structer/data
   * $pek: key in expres
   */

  function export_root_direct($key,$pek){ }
  function export_root_open($key,$pek){ }
  function export_root_close($key,$pek,$open,$content){
    return implode('',$content);
  }

  function export_single_direct($key,$pek){
    if(is_null($this->data[$key])) return '';
    if(is_scalar($this->data[$key])) return $this->data[$key];
    qx();
  }


  function export_ph_direct($key,$pek){
    switch($this->str[$key]['tag']){
    case 'incl': 
      $res = $this->exp__key($this->data[$key]); 
      return $res;
    }
    trigger_error('unknown placeholder: ' . $this->str[$key]['tag'],E_USER_WARNING);
  }
  function export_ph_open($key,$pek){ }
  function export_ph_close($key,$pek,$open,$content){}


  function export_rem_direct($key,$pek){
    return $this->data[$key]->single();
  }
  function export_rem_open($key,$pek){
    return array('rem'=>clone $this->data[$key]);
  }

  function export_rem_close($key,$pek,$open,$content){
    $con = implode('',$content);
    if($con==='') return $open['res']['rem']->head();
    return $open['res']['rem']->head() . $con . $this->data[$key]->foot();
  }


  function export_txt_direct($key,$pek){
    if(is_null($this->str[$key]['tag'])) $enc = def($this->set,'charset_source');
    else $enc = $this->str[$key]['tag'];
    if(is_null($enc)) return $this->data[$key];
    return $this->enc2utf8($this->data[$key],$enc);
  }
  function export_txt_open($key,$pek){ return "should not happen or overload it: export_txt_open";}
  function export_txt_close($key,$pek,$open,$content){ return "should not happen or overload it: export_txt_close";}


  function export_tag_direct($key,$pek){
    return $this->export_tag_close($key,$pek,array('res'=>array('att'=>clone $this->data[$key])),array());
  }
  function export_tag_open($key,$pek){ 
    $dat = $this->data[$key];
    if($dat instanceof opc_attrs) return array('att'=>clone $this->data[$key]);
    if(is_array($dat)) return array('att'=>new opc_attrs(NULL,$dat));
    if(is_null($dat)) return array('att'=>new opc_attrs());
    qk();
    trigger_error('Invalid attr-object');
    return;
  }
  function export_tag_close($key,$pek,$open,$content){
    $con = implode('',$content);
    $att = $open['res']['att'];
    $tag = $att->tag();
    $this->export_tag_close_prepare($tag,$att,$con); // sideffects
    if(is_null($tag)) return $con;
    if(strpos($tag,':')!==FALSE) $tag = $this->export_pseudo($tag,$att,$con);

    $mth = 'exp__tag__close' . $tag;
    if(method_exists($this,$mth))
      return $this->$mth($tag,$att,$con,$key,$pek);
    else
      return $this->exp__layout($tag,$att,$con,$this->set['srclayout']);
  }

  // wrap is a invisible cover for its (visible) content
  function exp__tag__close_wrap($tag,$att,$con){
    return $con;
  }

  function exp__tag__close_mark($tag,$att,$con,$key,$pek){
    $set = array('implode'=>NULL,'xhtml'=>$this->xhtml);
    $tmp = new $this->extcls['rem']('Start MARK ' . $key,6);
    if(empty($con)) return $tmp->head($att->finalize($set));
    return $tmp->head($att->finalize($set)) . $con . $tmp->foot('End MARK ' . $key);
  }

  function exp__tag__close_hide($tag,$att,$con,$key,$pek){
    $set = array('implode'=>NULL,'xhtml'=>$this->xhtml);
    $tmp = new $this->extcls['rem']('Start HIDE ' . $key,6);
    if(empty($con)) return $tmp->head($att->finalize($set));
    $head = $tmp->head($att->finalize($set));
    $foot = $tmp->foot('End HIDE ' . $key);
    $con = str_replace('-->','-- >',str_replace('<!--','< !--',$con)); // to prevent nested comments
    return substr($head,0,strrpos($head,'-->')) ."\n$con\n" . substr($foot,strpos($foot,'<!--')+4);
  }

  function exp__tag__close_drop($tag,$att,$con,$key,$pek){
    return NULL;
  }

  function exp__tag__close_alone($tag,$att,$con,$key,$pek){
    $pkey = $this->str[$key]['par'];
    if($this->str[$pkey]['fcl'] == $key and $this->str[$pkey]['lcl'] == $key) return $con;
    return NULL;
  }


  /** sideeffects only */
  function export_tag_close_prepare(&$tag,&$att,&$con){
    // Add pro/epilog to content
    $con = $this->auto_imp($att->getn('Pre')) . $con . $this->auto_imp($att->getn('Post'));
    // default encoding
    $enc = def($this->set,'charset_source',NULL);

    // loop through attributes and handle special one
    $ak = $att->get_keys();
    foreach($ak as $ck){
      switch($ck){
      case '*enc': $enc = $att[$ck]; break; // specific encoding set
      case '*trans': 
	$rules = is_array($att[$ck])?$att[$ck]:explode(' ',$att[$ck]);
      foreach($rules as $cruel) $con = $this->transform($con,$cruel); 
      break;
      default: 
	continue 2;
      }
      unset($att[$ck]);
    }

    // Take care or encoding
    if(!is_null($enc)) $con = $this->enc2utf8($con,$enc);
  }

  function transform($text,$ruel){
    switch($ruel){
    case 't': return trim($text);
    case 'rev': return strrev($text);
    case 'hsc': return htmlspecialchars($text);
    case 'n2b': return nl2br($text);
    case 'uc': return strtoupper($text);
    case 'lc': return strtolower($text);
    case 'muc': return mb_strtoupper($text);
    case 'mlc': return mb_strtolower($text);
    case 'mtc': return mb_convert_case($text,MB_CASE_TITLE);
    }
    return $text;
  }

  function exp__layout($tag,$att,$con,$layout){

    // attributes to string
    $set = array('prefix'=>' ','implode'=>' ','xhtml'=>$this->xhtml);
    $attstr = $att->finalize($set);

    switch($layout){
    case 1:
      $nl = def($this->set['nl'],$tag,0); // newlines
      $in = $nl>0?str_repeat(' ',$this->explev):'';
      if($con=='')             
	$res =  $this->exp__arr2str_empty($tag,$attstr);
      else if($nl==0 or strlen($con)<50)          
	$res =  $res = '<' . $tag . $attstr . '>' . $con . '</' . $tag . '>';
      else                     
	$res =  $res = $in . '<' . $tag . $attstr . '>' . $con . "\n" . $in . '</' . $tag . '>';

      $pre = $nl==0?'':(str_repeat("\n",$nl) . $in);
      if($this->explastnl>0) $pre .= "\n";
      $res = $pre . $res;
      $this->explastnl = $nl;
      break;
    default:
      if($con=='') $res =  $this->exp__arr2str_empty($tag,$attstr);
      else         $res =  $res = '<' . $tag . $attstr . '>' . $con . '</' . $tag . '>';
    }
    return $res;

  }


  function auto_imp($data){
    if(is_null($data) or $data==='' or $data===array())   return '';
    if(is_scalar($data)) return strval($data);
    if(is_array($data)){
      if(isset($data['tag'])){
	$tag = $data['tag'];
	$con = isset($data[0])?array($data[0]):array();
	$att = new opc_attrs($tag,$data);
	return $this->export_tag_close(NULL,NULL,array('res'=>array('tag'=>$tag,'att'=>$att)),$con);
      } else {
	$res = '';
	foreach($data as $cd) $res .= $this->auto_imp($cd);
	return $res;
      }
    }
    qz('auto_imp');
    qq($data);
    qk();
  }

  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~


  function exp__close($arr,$key){
    $mth = 'exp_' . $this->str[$key]['typ'] . '_close';
    return $this->$mth($arr,$key);
  }


  function exp__arr2str_empty($tag,$att){
    $empty = def($this->set['empty'],$tag,0);
    if(is_int($empty)){
      switch($empty){
      case 0:  $res = '<' . $tag . $att . ($this->set['xhtml']?'/>':'>'); break;
      case 1:  $res =  ''; break;
      case 2:  $res =  '<' . $tag . $att . '></' . $tag . '>'; break;
      default: 
	trigger_error("unknown mode '$empty' for empty tags",E_USER_NOTICE);
      }
    } else $res = '<' . $tag . $att . '>' . $empty . '</' . $tag . '>';
    return $res;
  } 
  
  function export_pseudo(&$tag,&$att,&$con){
    $res = explode(':',$tag,2);
    $mth = 'pseudo_' . $res[0] . '_' . $res[1];
    if(method_exists($this,$mth)) return $this->$mth($res[0],$res[1],$att,$con);

    $mth = 'pseudo_' . $res[0];
    if(method_exists($this,$mth)) return $this->$mth($res[0],$res[1],$att,$con);

    $mth = 'pseudo_' . $res[1];
    if(method_exists($this,$mth)) return $this->$mth($res[0],$res[1],$att,$con);
    
    return $res[0];
  }


  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  function pseudo_a($tag,$typ,&$att,&$con){
    $att = $this->attr_add_def('a:' . $typ,$att);
    switch($typ){
    case 'auto':
      $href = $att->get('href',NULL);
      $args = $att->get_x('urlargs','');
      $anch = $att->get_x('anchor');
      $att['href'] = $this->href_make($href,$args,$anch);
      return 'a';
    case 'broken': return 'span';
    }
    return 'a';
  }

  /** add default attributes to $att if not yet set
   *
   * @param string {@link set}['def'][$key] is named array with defaults
   * @param named-array $att current attributes
   * @return: modified attribute array
   */
  function attr_add_def($key,$att){
    if(!isset($this->set['def'][$key])) return $att;
    foreach($this->set['def'][$key] as $ck=>$cv)
      if(!isset($att[$ck])) $att->set($ck,$cv);
    return $att;
  }


  function attr_merge(/* */){
    $res = array();
    $ar = func_get_args();
    foreach($ar as $ca) $res = array_merge($res,array_filter($ca));
    return $res;
  }




  /** creates from an array an url-encode line
   * 
   * @param array $args array of data, string keys will be used as argument name
   * @param string|null $implode used for implode the result, default: &
   * @param int $enc: see {@link encode}
   * @param bool $filter if TRUE (default) NULL values are ignored
   * @return string or array (if implode is not a string)
   */
  function implode_urlargs($args=NULL,$implode='&',$enc=1,$filter=TRUE){
    if(!is_array($args) or count($args)==0) return NULL;
    $res = array();
    foreach($args as $ak=>$av){
      if(is_null($av) and $filter)
	 continue;
      else if(is_numeric($ak)) 
	$res[] = $this->encode($av,$enc);
      elseif(!is_array($av)) 
	$res[] = $ak . '=' . $this->encode($av,$enc);
      else 
	foreach($av as $cv)  $res[] = $ak . '[]=' . $this->encode($cv,$enc);
    }
    return is_string($implode)?implode($implode,$res):$res;
  }

  /** variation of {@link implode_urlargs} optimized for href-attributes including anchor-part
   * 
   * @param array|string $args
   *    array: string keys will be used as argument names, except anchor<br>
   *    string: simple anchor link        
   * @return valid string for a href-attribute
   */
  function implode_href($args=NULL){
    if(is_null($args)) return NULL;
    if(is_string($args)) return '#' . $args;
    if(!isset($args['#'])) return $this->implode_urlargs($args);
    $anch = $args['#'];
    unset($args['#']);
    return $this->implode_urlargs($args) . '#' . $anch;
  }

  function href_make($href,$urlargs=array(),$anchor=NULL){
    if(is_int($href)) $href = $this->myself($href);
    if(is_array($urlargs)) $urlargs = $this->implode_href($urlargs);
    if(!empty($urlargs)) {
      if(empty($href)) $href = $this->myself();
      $href .= (strpos($href,'?')?'&':'?') . $urlargs;
    }
    if(!empty($anchor)) $href .= '#' . $anchor;
    else if(empty($href)) $href = $this->myself();
    return $href;
  }



  /** variation of {@link implode_urlargs} optimized for href-attributes using mailto
   * 
   * @param array|string $args
   *    array: string keys will be used as argument names,
   *    string: simple anchor link        
   * @return valid string for a href-attribute
   */
  function implode_mailargs($args=NULL,$implode='&'){
    if(!is_array($args) or count($args)==0) return NULL;
    $res = array();
    foreach($args as $ak=>$av){
      if(is_null($av))         continue;
      else if(is_numeric($ak)) continue;
      elseif(!is_array($av))   $res[] = $ak . '=' . $this->encode($av,2);
      else                     foreach($av as $cv)  $res[] = $ak . '=' . $this->encode($cv,2);
    }
    return is_string($implode)?implode($implode,$res):$res;
  }

  /** encoding of a string
   * @param string $str text to encode
   * @param int $mode: 0: urlencode; 1: rawurlencode; 2: suitable for mailto-link
   */
  function encode($str,$mode=0){
    switch($mode){
    case 0: return urlencode($str);
    case 1: return rawurlencode($str);
    case 2: return str_replace(array(' ','%40'),array('%20','@'),rawurlencode($str));
    }
    return $str;
  }

  /** similar to {@link tag} but tag is definied in attr */
  function utag($data,$attr=array(),$deftag=NULL){
    return $this->tag(def($attr,'tag',$deftag),$data,$attr);
  }


  /** changes all keys by adding a constant
   * @param int $add value to to the current key
   */
  function renum($add){
    yy('renum');
    if(is_int($add)) $fct = create_function('$x','return is_int($x)?$x+' . $add . ':$x;');
    else if(is_callable($add,FALSE)) $fct = $add;
    else return trg_err(0,'invalid offset for re-number: ' . strval($add));

    $arr = array(); // --------------------------------------------------  str
    $keys = array('key','par','prv','nxt','fcl','lcl');
    foreach($this->str as $key=>$val){
      foreach($keys as $ck) $val[$ck] = $fct($val[$ck]);
      $arr[$fct($key)] = $val;
      if($val['typ']=='ph' and $val['tag']=='copy'){// change reference saved in data too
	$this->data[$key] = fct($this->data[$key]);
      }
    }
    $this->str = $arr;

    $this->skey = $fct($this->skey); // ---------------------------------- next key

    $arr = array(); // -------------------------------------------------- data
    foreach($this->data as $key=>$val) $arr[$fct($key)] = $val;
    $this->data = $arr;

    return TRUE;
  }
     

  /** returns the name of the start php script
   * @param bincoded-int $flag: defines which elements should be returned
   * - 0/1: name only (eg. index.php)
   * - 1/2: path  (eg /tools/coding/)
   * - 2/4: server (eg domain.org)
   * - 3/8: protocol (eg http://)
   */
  function myself($flag=1){
    $ser = $_SERVER;
    $file = (($flag & 1)==1)?substr($ser['PHP_SELF'],strrpos($ser['PHP_SELF'],'/')+1):'';
    $path = (($flag & 2)==2)?substr($ser['PHP_SELF'],0,strrpos($ser['PHP_SELF'],'/')+1):'';
    $host = (($flag & 4)==4)?$ser['HTTP_HOST']:'';
    if(($flag & 8)==8){
      $prot = strtolower(substr(def($_SERVER,'SERVER_PROTOCOL'),0,
				strpos(def($_SERVER,'SERVER_PROTOCOL'),'/'))) . '://';
    } else $prot = '';
    return $prot . $host . $path . $file;
  }

  /** return all child-keys (below key)
   * @param int $mode: 
   * 0: only this level, 
   * 1: flat array parent first
   * 2: flat array parent last
   * 3: nested array
   */
  function get_childs($key,$mode=0){
    if(!isset($this->str[$key])) return NULL;
    if(is_null($this->str[$key]['fcl'])) return array();
    $ck = $this->str[$key]['fcl'];
    $res = array();
    $stack = array();
    do{
      if($mode>0 and !is_null($this->str[$ck]['fcl'])){ // go in
	array_unshift($stack,$ck,$res);
	$res = array();
	$ck = $this->str[$ck]['fcl'];
      } else { // no childs
	switch($mode){
	case 3: $res[$ck] = array(); break;
	default: 
	  $res[] = $ck; break;
	}

	// what is next
	while(is_null($this->str[$ck]['nxt'])){
	  if(count($stack)==0) return $res;
	  $pk = array_shift($stack);
	  $ores = array_shift($stack);
	  switch($mode){
	  case 1: $res = array_merge($ores,array($pk),$res); break;
	  case 2: $res = array_merge($ores,$res,array($pk)); break;
	  case 3: $ores[$pk] = $res; $res = $ores; break;
	  }
	  $ck = $pk;
	}
	$ck = $this->str[$ck]['nxt'];
      }
    } while(TRUE);
    return NULL; // should not be reached    
  }


  // XXX
  function replace($tar,$src,$keep){
    $ts = $this->str[$tar];
    $ss = $this->str[$src];

    $this->cut($src);
    $this->paste($src,'auto',$ts['par'],$ts['nxt']);

    if($keep){
      $this->cut($tar);
      $fcl = $this->str['_orph_']['fcl'];
      if(isset($fcl)){
	$this->str[$fcl]['prv'] = $tar;
	$this->str[$tar]['nxt'] = $fcl;
      } else $this->str['_orph_']['lcl'] = $tar;
      $this->str['_orph_']['fcl'] = $tar;
      
    } else $this->remove($tar);
  }

  function remove($skey){
    if(!isset($this->str[$skey])) return NULL;

    $this->clean($skey);
    $this->cut($skey);
    if(isset($this->data[$skey])) unset($this->data[$skey]);
    unset($this->str[$skey]);
    return TRUE;
  }

  /** removes all sub-childs (makes the node empty)*/
  function clean($skey){
    $ckey = $this->get_childs($skey,2);
    foreach($ckey as $ck){
      if(isset($this->data[$ck])) unset($this->data[$ck]);
      unset($this->str[$ck]);
    }
    $this->str[$skey]['fcl'] = NULL;
    $this->str[$skey]['lcl'] = NULL;
  }

  /** removes an element from the structer */
  function cut($skey){
    if(!isset($this->str[$skey])) return FALSE;
    if(!is_null($this->str[$skey]['prv']))
      $this->str[$this->str[$skey]['prv']]['nxt'] = $this->str[$skey]['nxt'];
    else if(!is_null($this->str[$skey]['par']))
      $this->str[$this->str[$skey]['par']]['fcl'] = $this->str[$skey]['nxt'];

    if(!is_null($this->str[$skey]['nxt']))
      $this->str[$this->str[$skey]['nxt']]['prv'] = $this->str[$skey]['prv'];
    else if(!is_null($this->str[$skey]['par']))
      $this->str[$this->str[$skey]['par']]['lcl'] = $this->str[$skey]['prv'];

    $this->str[$skey]['par'] = NULL;
    $this->str[$skey]['prv'] = NULL;
    $this->str[$skey]['nxt'] = NULL;
    return TRUE;
  }

  /*
   * $skex has to be cut-out before 
   */
  function paste($skey,$mode,$key,$nxt=NULL){
    if($mode=='auto'){
      if(!is_null($nxt)){
	$mode = 'prv';
	$key = $nxt;
      } else $mode = 'lcl';
    } 
    $rel = $this->get_rel($this->str[$key],$mode);
    $ns = $this->str[$skey];
    $ns['par'] = $rel[0];
    $ns['prv'] = $rel[1];
    $ns['nxt'] = $rel[2];
    if(is_null($rel[1]) and is_null($rel[2])){ // only new child
      $this->str[$rel[0]]['fcl'] = $skey;
      $this->str[$rel[0]]['lcl'] = $skey;
    } else if(is_null($rel[1])){ // new first child
      $this->str[$rel[2]]['prv'] = $skey;
      $this->str[$rel[0]]['fcl'] = $skey;
    } else if(is_null($rel[2])){ // new last child
      $this->str[$rel[1]]['nxt'] = $skey;
      $this->str[$rel[0]]['lcl'] = $skey;
    } else { // inbetween
      $this->str[$rel[1]]['nxt'] = $skey;
      $this->str[$rel[2]]['prv'] = $skey;
    }
    $this->str[$skey] = $ns;
  }

  /** imports an external object to the internal structer */
  function import(&$obj){
    yy('opc_ht2 import');
    if(is_null($obj) or $obj==='') 
      return NULL;
    else if(is_object($obj)) 
      return $this->import_object($obj);
    else if(is_scalar($obj))
      return $this->insert('txt',NULL,$obj);
    else if(is_array($obj) and isset($obj['tag']))
      return $this->tag(NULL,NULL,$obj);
    else if(is_array($obj))
      foreach($obj as $co) $this->import($co);
    else
      trigger_error('new case for method import in opc_ht2');
  }

  /** sub of {@link import} */
  function import_object(&$obj){
    if($obj instanceof opc_ht2){
      $this->add($obj->exp());
    } else if($obj instanceof opc_ht){
      $this->add($obj->output());
    } else if($obj instanceof opc_head){
      $obj->exp2ht($this);
    } else if($obj instanceof opc_ptr_ht2){
      $this->insert('ph','incl',$obj->key);
    } else {
      trigger_error('new case for method import_object in opc_ht2: \'' . get_class($obj) . '\'');
    }
  }

  /** wrapper to opc_ht */
  function in(&$obj){ return $this->import($obj);}

  /** wrapper to opc_ht */
  function in_tag(&$obj,$tag,$attrs=array()){
    $res = $this->open($tag,$attrs);
    $this->import($obj);
    $this->close();
    return $res;
  }

  /** wrapper to opc_ht */
  function output(){ return $this->exp();}



  function set_cb_open($cb,$args=NULL){
    yz();
    if(is_callable($cb)){
      $this->cb_open = $cb;
      $this->cbargs_open = $args;
    } else if(is_null($cb))       
      $this->cb_open = NULL;
  }

  /* auto conversion for attributes
   * array -> no conversion
   * string with ';' inside: use as style
   * string starting with '=': using as id
   * string starting with '*': using as _part (nyu)
   * other strings: using as class
   */
  function auto_attr($attr){
    if(is_array($attr)) return $attr;
    if(is_object($attr)) return $attr;
    if(strpos($attr,';')!==FALSE) return array('style'=>$attr);
    switch(ord($attr)){
    case 61: return array('id'=>substr($attr,1)); // =
    case 42: return array('*part'=>substr($attr,1)); // *
    }
    return array('class'=>$attr);
  }

  function auto_attr_obj($attr){
    if($attr instanceof opc_attrs) return $attr;
    if(is_string($attr)) $attr = $this->auto_attr($attr);
    return new opc_attrs(NULL,$attr);
  }

  function mod_attr($id,$how,$key='repalce',$val=NULL){
    if(!isset($this->data[$id])) return FALSE;
    $attrs = &$this->data[$id];
    switch($how){
    case 'repalce':
    default: 
      $attrs->set($key,$val);
    }
  }

  /** adjust links for key on both side
   * if end is also given key & end define a range in the same level
   */
  protected function adj_cut($key,$end=NULL){
    if(is_null($end)){
      $this->adj_cut_left($key);
      $this->adj_cut_right($key);
    } else {
      $this->adj_cut_left($key,$this->str[$end]['nxt']);
      $this->adj_cut_right($end,$this->str[$key]['prv']);
    }
  }

  protected function adj_cut_left($key,$nxt=FALSE){
    if($nxt===FALSE) $nxt = $this->str[$key]['nxt'];
    if     (!is_null($prv=$this->str[$key]['prv'])) $this->str[$prv]['nxt'] = $nxt;
    else if(!is_null($par=$this->str[$key]['par'])) $this->str[$par]['fcl'] = $nxt;
  }

  protected function adj_cut_right($key,$prv=FALSE){
    if($prv===FALSE) $prv = $this->str[$key]['prv'];
    if     (!is_null($nxt=$this->str[$key]['nxt'])) $this->str[$nxt]['prv'] = $prv;
    else if(!is_null($par=$this->str[$key]['par'])) $this->str[$par]['lcl'] = $prv;
  }


  function mark_set($key,$name){
    $this->marks[$name] = $key;
  }

  function mark_get($name){
    return def($this->marks,$name);
  }

  function mark_list(){
    return $this->marks;
  }


}





?>
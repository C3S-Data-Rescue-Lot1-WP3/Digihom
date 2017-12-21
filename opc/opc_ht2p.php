<?php

  /* modify tag-data of an existing item through ht2p 
   * idea special attribute '_embed'=>array('tag'=>'div','prolog'=>'asdfasdf','epilog'=>'asdfasdf')
   * weiter links functionen (oder varianten): links mit Bildern und/oder texten
   */

interface opi_ptr{
}

class opc_ptr_ht2 implements opi_ptr{

  protected $obj = NULL;
  protected $key = NULL;
  protected $root = NULL;
  protected $nxt = NULL;
  static protected $nxt_allowed = array('fcl','lcl','nxt','NXT','prv','PRV','par','PAR');
  protected $stack  = array();

  /** level of the las h-function */
  protected $hlevel = 0;

  /** function was not called by da/d/*2s */
  protected $ndcall = TRUE;

  /**
   * since this is themost often used empty tag a shortcut
   * will be set to the correct value during construction of the pointer
   */
  public $br = '<br/>';

  /** external object for current status and error handling */
  protected $err = NULL;

  /** overload {@link ___get} instead */
  final function __get($key){
    if($this->___get($key)==TRUE) return $key;
    return $this->err->err(array(0,$key));
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
    case 'key': case 'root': case 'nxt': case 'obj':
      $key = $this->$key; return TRUE;
    case 'xhtml': case 'charset': case 'data': case 'str': case 'args':
      $key = $this->obj->$key; return TRUE;
    case 'croot':
      $key = $this->key;
      while(!is_null($this->obj->str[$key]['par'])) $key = $this->obj->str[$ck]['par'];
      return TRUE;
    }
    return FALSE;
  }

  protected $extcls = array('err'=>'opc_status',
			    'attrs'=>'opc_attrs',
			    'rem'=>'opc_comment');

  /*
   * opi_ht2 -> obj
   * string/int -> key
   * array with: 
   *   next,tag -> used by set
   */
  function __construct($obj=NULL,$key=NULL,$next=NULL,$tag=NULL){
    $this->init(func_get_args());
  }

  function init($set){
    if(is_null($this->err)) $this->err = new $this->extcls['err']($this->_msgs());
    $res = array();
    foreach($set as $key=>$val) {
      if(is_numeric($key)){
	list($ck,$cv) = $this->init_match($key,$val);
	if($ck=='*')      $res = array_merge($res,$cv); 
	else if($ck!='-') $res[$ck] = $cv;
      } else $set['key'] = $val;
    }
    $this->set_obj(def($res,'obj',NULL));
    $this->set(def($res,'key'),def($res,'next','lcl'),def($res,'tag'));
    $this->root = $this->key;
    return $res;
  }

  function init_match(&$key,$val){
    if($key==='ht2'
       or ($val instanceof opi_ht2) 
       or ($val instanceof opi_ptr) 
       or ($val instanceof opi_fw)) 
      return array('obj',$val);
    if($key==='key' or is_string($val) or is_int($val))
      return array('key',$val);
    if(is_array($val)) 
      return array('*',$val);
    return array('-',$val);
  }
  
  protected function _msgs(){
    return array(0=>array('success','ok'),
		 -1=>array('set to null','ok'),
		 1=>array('invalid object','error'),
		 2=>array('invalid setting','warnings'),
		 3=>array('no object/pointer set','warnigns'),
		 4=>array('invalid command','warnings'),
		 5=>array('non existing key/element','warnings'),
		 6=>array('top level reached','warnings'),
		 7=>array('invalid arguments','warnings'),
		 8=>array('failed','warnings'),
		 );
  }

  /** set the pointer to a new place
   * @param misc $key: NULL -> new pointer
   * @param string $next: if not null will set the next-mode
   */
  function set($key=NULL,$next=NULL,$tag=NULL){
    //if(!is_object($this->obj)) return $this->err->err(3);
    if(!is_object($key)) 
      $id = $key;
    else if($key instanceof opc_ht2e) 
      $id = $key->root;
    else
      return $this->err->errC(3546);
    if($id!==FALSE) $this->key = $this->obj->ptr_new($id,$tag);
    if(!is_null($next)){
      if(!in_array($next,self::$nxt_allowed))  return $this->err->errC(2);
      $this->nxt = $next; 
    }
    return $this->err->ok($this->key);
  }

  function set_obj($obj){
    if($obj instanceof opi_ht2) {
      $this->obj = &$obj; 
    } else if($obj instanceof opi_ptr){
      $this->obj = &$obj->obj; 
    } else if($obj instanceof opi_fw){
      $this->obj = &$obj->data; 
    } else if(is_null($obj)) {
      $this->obj = NULL;
      $this->key = NULL; 
      return $this->err->okC(-1);
    } else return $this->err->errC(1);
    $this->key = NULL;
    $this->br = $this->obj->xhtml?'<br/>':'<br>';
    return $this->err->okC(0);
  }


  /*
   * returns
   *  -1 if result does not need a tag (simple add)
   *  -2 if there is no output at all (no tag, data NULL or empty)
   */
  protected function prepare(&$tag,&$data,&$attr){
    if(is_string($attr)) $attr = $this->obj->auto_attr($attr);

    if(!is_string($tag) or $tag==''){
      if($attr instanceof opc_attrs) $tag = $attr->tag;
      else if(is_array($attr))       $tag = def($attr,'tag','');
      else                           $tag = '';
    }
    
    if($tag==='-' or $tag=='') return is_null($data)?-2:-1;
    
    // create attributes object
    if($attr instanceof opc_attrs){
      if(!is_null($tag)) $attr->set('tag',$tag);
    } else if(is_null($attr)){
      $attr = new $this->extcls['attrs']($tag);
    } else if(is_array($attr)){
      $tmp = new $this->extcls['attrs']($tag);
      foreach($attr as $key=>$val){
	if(!is_numeric($key) or $key!=0) $tmp[$key] = $val;
	else $data = $val;
      }
      $attr = $tmp;
    } else return trg_err(1,'Invalid attr: ' . var_export($attr,TRUE));
    return 0;
  }

  function insert($typ,$tag,$obj,$rel='auto',$tkey=NULL,$key=NULL){
    $at = $this->obj->str[$tkey];
    if($rel=='auto') $rel = $this->nxt;
    // if root is current only flc/lcl make sense
    if($at['typ']=='root'){ 
      if($rel=='nxt') $rel = 'lcl';
      else if($rel=='prv') $rel = 'fcl';
    }
    return $this->obj->insert($typ,$tag,$obj,$rel,$at,$key);
  }

  function add_file($filename,$mode=1){
    if(!file_exists($filename)){
      if($mode>0) 
	$this->tag('div','Unkown file: ' . $filename,'opc-error');
      return NULL;
    }
    switch(abs($mode)){
    default:
      $dat = file_get_contents($filename);
      $this->add($dat);
    }
  }

  function add($data,$add=NULL){
    if(is_null($data)) return NULL;

    if(is_object($data)){
      $res = $this->add_obj($data,$add);
    } else if(is_scalar($data)){
      $res = $this->insert('txt',$add,strval($data),strtolower($this->nxt),$this->key);
    } else if(is_array($data)) {
      $res = $this->add_array($data);
    } else qz();
    if(empty($res)) return $this->err->err(50);
    if($this->nxt=='nxt' or $this->nxt=='prv') $this->key = $res;
    return $this->err->ok($res);
  }


  protected function add_obj($obj,$add=array()){
    if($obj instanceof opc_ht2e){
      $key = $obj->key;
      if(!isset($this->obj->str[$key])) return FALSE;
      return $this->insert('ph','incl',$key,strtolower($this->nxt),$this->key);
    } else if($obj instanceof opc_attrs){
      return $this->tag($obj->get('tag'),NULL,$obj);
    } else if($obj instanceof opc_tsptr){
      $ts = $obj->get_store();
      $root = $ts->root($obj->get_key());
      return $this->add_tstore($ts,array('root'=>$root));
    } else if($obj instanceof opc_tstore){
      return $this->add_tstore($obj,$add);
    } else if($obj instanceof opc_ptr_ht2){
      return $this->incl($obj);
    }
    qx('Add object to ht2p');
    qq($obj,get_class($obj));
    qk();
    qd();
  }
  
  protected function add_tstore($obj,$arr){
    if(is_null($arr)) $arr = array();
    else if(!is_array($arr)) $arr = array('root'=>$arr);
    $root = def($arr,'root',$obj->root_key);
    if(!isset($obj[$root])) return ;
    $seq = $obj->seq2oac($root);
    foreach($seq as $step){
      $dat = $obj[$step['id']];
      if(is_null($dat)) continue;
      //qq($dat,"$step[id] $step[op] $step[lev]/$step[pos]");
      switch($step['op']){
      case 'open':  $this->aopen($dat); break;
      case 'add':   $this->add($dat); break;
      case 'close': $this->close(); break;
      default:
	aw($step,$dat);
      }
    }
  }
  
  protected function add_array($arr){
    if(count($arr)==0) return -1;
    if(isset($arr['call'])) return call_user_func_array(array($this,$arr['call']),$arr['args']);
    if(isset($arr['tag']))  return $this->tag($arr['tag'],def($arr,0),$arr);
    $res = NULL;
    foreach($arr as $cv) $res = $this->add($cv);
    return $res;
  }

  /* creates a tag using primary the attribute array */
  function atag($attr=array(),$data=NULL,$tag=NULL){ 
    return $this->tag($tag,$data,$attr);
  }

  /* inserts an empty tag, only attributes */
  function etag($tag,$attr=array()){ 
    return $this->tag($tag,NULL,$attr);
  }

  function tag($tag,$data=NULL,$attr=array()){
    $tmp = $this->prepare($tag,$data,$attr);
    if($tmp==-2) return NULL;
    if($tmp==-1) return $this->add($data);

    $res = $this->insert('tag',$tag,$attr,
			 strtolower($this->nxt),
			 $this->key);
    if(!is_null($data)){
      $this->_in();
      $this->key = $res;
      $this->nxt = 'lcl';
      $this->add($data);
      $this->out();
    }
    if($this->nxt=='nxt' or $this->nxt=='prv') $this->key = $res;
    return $this->err->ok($res);
  }

  function open($tag,$attr=array()){
    //if(!$this->ok()) return $this->err->err(3);
    $this->prepare($tag,$data,$attr);
    $res = $this->insert('tag',$tag,$attr,strtolower($this->nxt),$this->key);
    $this->_in();
    $this->key = $res;
    $this->add($data);
    return $this->err->ok($res);
  }

  function aopen($attr=array(),$tag=NULL,$data=NULL){
    $this->prepare($tag,$data,$attr);
    $res = $this->insert('tag',$tag,$attr,strtolower($this->nxt),$this->key);
    $this->_in();
    $this->key = $res;
    $this->add($data);
    return $this->err->ok($res);
  }

  function close($n=1){
    //if(!$this->ok()) return $this->err->err(3);
    $this->_out($n);
    return $this->err->ok($this->key);
  }

  function close_to($tag,$typ='tag',$incl=TRUE){
    //if(!$this->ok()) return $this->err->err(3);
    if(is_bool($typ)) { $incl = $typ; $typ = 'tag';}
    $ck = $this->key;
    $n = $incl?1:0;
    while($this->obj->str[$ck]['tag']!==$tag or $this->obj->str[$ck]['typ']!==$typ){
      $n++;
      $ck = $this->obj->str[$ck]['par'];
      if(is_null($ck)) return $this->err->err(5);
    }
    return $this->close($n);
  }

  /** closes and reopens n levels
   * additional arguments allow to modify the reopend tag (inner first)
   */
  function next($n=1/* */){
    if($n<=0) return $this->err->ok();
    //if(!$this->ok()) return $this->err->err(3);
    $ar = func_get_args();
    $n = count($ar)>0?$ar[0]:1;
    $cattrs = array();
    for($i=0;$i<$n;$i++){
      $cattrs[] = $this->obj->data[$this->key];
      $this->_out(1);
    }
    for($i=$n-1;$i>=0;$i--){
      $ca = clone($cattrs[$i]);
      if(isset($ar[$i+1])) $ca->setn($ar[$i+1]);
      $this->open($ca['tag'],$ca);
    }
  }

  /** embedd with a new tag
   * ne>0: embed the last n objects together
   * ne<0: embed the last |n| objects separately
   */
  function embed($tag='span',$attr=array(),$ne=1,$move_ptr=TRUE){
    //if(!$this->ok()) return $this->err->err(3);
    $this->prepare($tag,$data,$attr);
    if(is_int($ne)){
      if($ne==0)      return $this->err->ok($this->key);
      else if($ne==1) $res = $this->embed_1($tag,$attr);
      else if($ne>1)  $res = $this->embed_n($tag,$attr,$ne);
      else            $res = $this->embed_m($tag,$attr,-$ne);
    } else return $this->err->err(7);
    if(is_null($res) or $res===FALSE) return $this->err->err(8);
    if(is_array($res))
      $res = in_array($this->nxt,array('lcl','nxt','PRV'))?array_pop($res):array_shift($res);

    if($move_ptr){ // the next operation acts to the new created tag
      if(in_array($this->nxt,array('lcl','fcl'))) $res = $this->obj->str[$res]['par']; 
    } 
    $this->key = $res;
    return $this->err->ok();
  }

  /** embeds one element */
  function embed_1($tag,$attr){
    switch($this->nxt){
    case 'lcl': $cp = $this->move('lc',1,FALSE); $mv = 'p'; break;
    case 'fcl': $cp = $this->move('fc',1,FALSE); $mv = 'n'; break;
    default:
      $cp = $this->key;
    }
    return $this->insert('tag',$tag,$attr,'emb',$cp);
  }

  /** embeds n elements together */
  function embed_n($tag,$attr,$ne){
    $keys = $this->_embed_getkeys($ne);
    $at = $this->tag($tag,NULL,$attr);
    return $this->obj->embed_n($keys,$at)?$at:NULL;
  }

  /** embeds m elements separately */
  function embed_m($tag,$attr,$ne){
    $keys = $this->_embed_getkeys($ne);
    $at = array();
    while($ne-->0) $at[] = $this->tag($tag,NULL,$attr);
    return $this->obj->embed_m($keys,$at)?$at:NULL;
  }

  protected function _embed_getkeys($ne,$cp=NULL,$move=NULL){
    if(is_null($cp)) {
      switch($this->nxt){
      case 'lcl': $cp = $this->move('lc',1,FALSE); $mv = 'p'; break;
      case 'fcl': $cp = $this->move('fc',1,FALSE); $mv = 'n'; break;
      default:
	$cp = $this->key;
      }
    }
    if(is_null($move)) $move = in_array($this->nxt,array('lcl','PRV','nxt'))?'p':'n';
    $res = array();
    while($ne-->0){
      array_unshift($res,$cp);
      $cp = $this->move($move,1,FALSE,$cp);
      if(is_null($cp)) break;
    }
    return $res;
  }

  /** allows to change the next-style for a while (finish it with out) 
   */
  function in($key=FALSE,$next=NULL){
    $this->_in();
    return $this->set($key,$next);
  }

  protected function _in(){
    array_unshift($this->stack,array($this->nxt,$this->key));
  }

  function out($n=1){
    if($n<1) return $this->err->ok();
    if($this->_out($n)) return $this->err->ok();
    return $this->err->err(6);
  }
  
  protected function _out($n){
    if(count($this->stack)<$n) return FALSE;
    while($n-->0) list($this->nxt,$this->key) = array_shift($this->stack);
    return TRUE;
  }

  function output(){ 
    //if(!$this->ok()) return $this->err->err(3);
    return $this->obj->exp2html($this->root,FALSE);
  }

  /* to send the current key to a ht2o instance
   * arg1: key in this object (NULL -> $this->key)
   * arg2: ht2o-instance
   * ... : will be used as arguments for the method 'set' of the ht2o
   * Mention: Changes (incl additions) to this object will appeear in the target object too!
   */
  function put($key,$obj/* further agrs fo set-method in obj */){ 
    $ar = func_get_args();
    $key = array_shift($ar); if(is_null($key)) $key = $this->key;
    $tar = array_shift($ar);
    if($tar instanceof opc_ht2o){
      array_unshift($ar,new opc_ht2e($key));
      call_user_func_array(array($tar,'set'),$ar);
    } else qy();
  }

  function exp($head=NULL){
    //if(!$this->ok()) return $this->err->err(3);
    $this->hadj(1,FALSE,TRUE);
    return $this->obj->exp2html($this->root,$head);
  }

  function exppart($key){
    //if(!$this->ok()) return $this->err->err(3);
    $this->hadj(1,FALSE,TRUE);
    return $this->obj->exp2html($key,FALSE);
  }

  function ok(){
    return is_object($this->obj) and !is_null($this->key);
  }

  /** move pointer
   * args is a string with one or more commands separated by spaces
   * numbers will multiply the previous command (default=1)
   * h: init key of the pointer
   * p/n: previous/next
   * fc/lc: first/last child
   * fs/ls: first/last sibling
   * par: parent
   * mode defines behaviour if a non existing key was asked
   *  0: throw error (returns 1 or NULL)
   *  1: no error, dont change anything (returns 1 or NULL)
   *  2: no error, use last valid step as result (returns -1 or res)
   *  3: no error, goto next step
   * @param bool $set: set result to key (returns Code) or just return it (or NULL)
   * @param key $key: start key (use current if NULL)
   * @return: 0 or key (if a non existing key was aksed see mode)
   */
  function move($how='nxt',$mode=0,$set=TRUE,$key=NULL){
    $how = explode(' ',$how);
    $cp = is_null($key)?$this->key:$key;
    $add = 0;
    while(count($how)>0){
      if($add<=0) { // read next command
	$cm = array_shift($how);
	$add = (count($how)>0 and is_numeric($how[0]))?(int)array_shift($how):1;
      } 
      $add--;
      switch($cm){
      case 'h':   $res = $this->root; break;
      case 'n':   $res = $this->obj->str[$cp]['nxt']; break;
      case 'p':   $res = $this->obj->str[$cp]['prv']; break;
      case 'fc':  $res = $this->obj->str[$cp]['fcl']; break;
      case 'lc':  $res = $this->obj->str[$cp]['lcl']; break;
      case 'par': $res = $this->obj->str[$cp]['par']; break;
      case 'fs':  $res = $this->obj->str[$this->obj->str[$cp]['par']]['fcl']; break;
      case 'ls':  $res = $this->obj->str[$this->obj->str[$cp]['par']]['lcl']; break;

      case 'i':
	if(is_null($this->obj->str[$cp]['fcl'])){
	  while(is_null($this->obj->str[$cp]['nxt'])){
	    if(is_null($this->obj->str[$cp]['par'])) break;
	    $cp = $this->obj->str[$cp]['par'];
	  }
	  $res = $this->obj->str[$cp]['nxt'];
	} else $res = $this->obj->str[$cp]['fcl'];
	break;

      default:
	return $set?$this->err->errM(4,$cm):NULL;
      }

      if(is_null($res)){
	switch($mode){
	case 0:	return $set?$this->err->errM(5,1):NULL;
	case 1: return $set?1:NULL;
	case 3: $add = 0; break;
	case 2: 
	  if($set) {
	    $this->key = $cp; 
	    return -1;
	  } else return $cp;
	}
      } else $cp = $res;
    }
    if($set){
      $this->key = $cp;
      return $this->err->ok(0);
    } else return $cp;
  }

  function get_type(){ return $this->get('typ');}
  function get_tag(){ return $this->get('tag');}

  function get_data($key=NULL){ 
    if(is_null($key)) $key = $this->key;
    if(!isset($this->obj->str[$key])) return $this->err->err(5);
    return def($this->obj->data,$key,NULL);
  }

  function get($what){
    if(!isset($this->obj->str[$this->key])) return $this->err->err(5);
    return $this->obj->str[$this->key][$what];
  }

  function pair($tagA,$tagB,$textA,$textB,$attrA=array(),$attrB=array()){
    $res = $this->open('_wrap');
    $this->tag($tagA,$textA,$attrA);
    $this->tag($tagB,$textB,$attrB);
    $this->close();
    return $res;
  }

  /**#@+ shortcut for common tags */
  function span($data,$attr=array()){return $this->tag(def($attr,'tag','span' ),$data,$attr);}
  function div ($data,$attr=array()){return $this->tag(def($attr,'tag','div'  ),$data,$attr);}
  function p   ($data,$attr=array()){return $this->tag(def($attr,'tag','p'    ),$data,$attr);}
  function b   ($data,$attr=array()){return $this->tag(def($attr,'tag','b'    ),$data,$attr);}
  function i   ($data,$attr=array()){return $this->tag(def($attr,'tag','i'    ),$data,$attr);}

  function hr  ($attr=array()){return $this->tag('hr',   NULL,$attr);}
  function br  ($attr=array()){return $this->tag('br',   NULL,$attr);}
  /**#@- */


  function modify_borderbypictures($id,$file,$gap,$add='borderbypictures'){
    $cls = $this->extcls['attrs'];
    $key = $id;

    // without this a gap appears
    $key = $this->insert('tag','div',new $cls('div','padding: 1px;'),'emb',$key);

    $cf = str_replace('%','tr',$file);
    $attr = "background: url('$cf') no-repeat top right; margin: 0; padding: $gap $gap 0 0;";
    $key = $this->insert('tag','div',new $cls('div',$attr),'emb',$key);

    $cf = str_replace('%','tl',$file);
    $attr = "background: url('$cf') no-repeat top left; margin: 0; padding: 0 0 0 $gap;";
    $key = $this->insert('tag','div',new $cls('div',$attr),'emb',$key);

    $key = $this->insert('tag','div',new $cls('div',$add),'emb',$key);
    $res = $key;

    $cf = str_replace('%','bl',$file);
    $attr = "background: url('$cf') no-repeat bottom left; margin: 0; padding: 0 0 0 $gap;";
    $key = $this->insert('tag','div',new $cls('div',$attr),'lcl',$key);

    $cf = str_replace('%','br',$file);
    $attr = "background: url('$cf') no-repeat bottom right; margin: 0; padding: $gap 0 0 0; font-size: 1px;";
    $key = $this->insert('tag','div',new $cls('div',$attr),'lcl',$key);

    $this->insert('txt',NULL,'&nbsp;','lcl',$key);

    return $res;
  }

  /* ================================================================================
     chapter structer
     ================================================================================ */
  function h($level,$data,$key=NULL,$attr=array()){
    $level = (int)$level;
    if($level<0) return;
    if($level==0) $level = $this->hlevel;

    $tag = 'h';
    $this->prepare($tag,$data,$attr);

    $this->hadj($level,FALSE,TRUE);
    if($this->ndcall) $this->cbo1_h($level,$data,$key,$attr);

    if($level>6) {
      $tag = 'div';
      $attr->class->set('h' . $level,'add');
    } else $tag = 'h' . $level;
    if(!is_null($key)){
      $res = $this->open($tag,$attr);
      if($key===TRUE) $key = preg_replace('/\W/','',$data);
      $this->tag('a',NULL,array('name'=>$key));
      $this->add($data);
      $this->close();
    } else $res = $this->tag($tag,$data,$attr);

    if($this->ndcall) $this->cbo2_h($level,$data,$key,$attr);
    return $res;
  }

  /** adjust the current h-level without using h itself, 
   * eg: close a level but continue with text and not a h-tag
   * @param $n: new level or offset
   * @param $rel=TRUE: is n a offset (T; default) or a level (F)
   * @param $close=FALSE: if current level is deeper, close the current to
   * @return new level
   */
  function hadj($n=1,$rel=TRUE,$close=FALSE){
    if($rel) $n = $this->hlevel+$n;
    $n = max(1,$n);
    if($this->ndcall){
      if($this->hlevel>=$n){
	for($i=$this->hlevel;$i>$n;$i--) $this->cbc_h($i);
	if($close) $this->cbc_h($n);
      } else if($this->hlevel<$n-1)
	for($i=$this->hlevel+1;$i<$n;$i++) $this->cbo_h($i);
    }
    return $this->hlevel = $n;
  }



  /** to overload; will be called consequently to h-events (method h & hadj)
   * use them to construct an index, add jump-links to the titles, add hr at the end ...
   */
  function cbo_h($level){} // level was skipped
  function cbo1_h($level,&$data,&$key,&$attr){} // befor data was included
  function cbo2_h($level,$data,$key,$attr){} // after data was included
  function cbc_h($level){} // when h is closed
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~



  /* --------------------------------------------------------------------------------
     Links
     -------------------------------------------------------------------------------- */

  /** creates a link back to this page */
  function a($text,$args=array(),$attr=array()){
    $this->prepare($tag,$data,$attr);
    $href = $this->obj->myself();
    if(count($args)>0) $href .= '?' . $this->obj->implode_href($args);
    $attr['href'] = $href;
    return $this->tag('a:self',$text,$attr);
  }

  /** similar to a but uses class variable args as default for args*/
  function link($text,$args=array(),$attr=array()){
    $args = array_merge((array)$this->args,$args);
    return $this->a($text,$args,$attr);
  }

  /** creates a link to another page on this server checks if target exists*/
  function page($page,$text=NULL,$args=array(),$attr=array(),$test=FALSE){
    $this->prepare($tag,$data,$attr);
    if(is_null($text)) $text = $page;
    if($test===FALSE or file_exists($page)){
      if(count($args)>0) $page .= '?' . $this->obj->implode_href($args);
      $attr['href'] = $page;
      return $this->tag('a:page',$text,$attr);
    } else return $this->tag('a:broken',$text,$attr);
  }

  /** creates an external link (proofs for protocol)  */
  function www($href,$text=NULL,$args=array(),$attr=array()){
    $this->prepare($tag,$data,$attr);
    if(is_null($text)) $text = $href;
    if(strpos($href,'://')===FALSE) $href = 'http://' . $href;
    if(count($args)>0) $href .= '?' . $this->obj->implode_href($args);
    $attr['href'] = $href;
    return $this->tag('a:ext',$text,$attr);
  }

  /** to link a file/document; new window, no arguments */
  function document($href,$text=NULL,$attr=array()){
    $this->prepare($tag,$data,$attr);
    if(is_null($text)) $text = $href;
    $attr['href'] = $href;
    return $this->tag('a:ext',$text,$attr);
  }

  /** creates a page inside link */
  function anchor($text,$key=NULL,$attr=array()){
    if(is_null($key)) $key = $text;
    $this->prepare($tag,$data,$attr);
    $attr['href'] = '#' . $key;
    return $this->tag('a:anchor',$text,$attr);
  }

  /** creates a mail-link */
  function mail($mail,$text=NULL,$args=array(),$attr=array()){
    $this->prepare($tag,$text,$attr);
    if(is_null($text)) $text = $mail;
    if(substr($mail,0,7)!='mailto:') $mail = 'mailto:' . $mail;
    if(count($args)>0) $mail .= '?' . $this->obj->implode_mailargs($args);
    $attr['href'] = $mail;
    return $this->tag('a:mail',$text,$attr);
  }

  /** creates a java script link */
  function jslink($code,$text=NULL,$attr=array()){
    $this->prepare($tag,$text,$attr);
    if(is_null($text)) $text = $code;
    if(substr($code,0,11)!='javascript:') 
      $code = 'javascript:' . $code;
    $attr['href'] = $code;
    return $this->tag('a:js',$text,$attr);
  }

  function button($name,$text=NULL,$attr=array()){
    if(is_null($text)) $text = $name;
    $attr['name'] = $name;
    if(!array_key_exists('value',$attr)) $attr['value'] = $text;
    if(!array_key_exists('type',$attr)) $attr['type'] = 'button';
    return $this->tag('button',$text,$attr);
  }

  
  function ref($ref,$text=NULL,$attr=array()){
    $this->prepare($tag,$data,$attr);
    if(is_null($text)) $text = $ref;
    $attr['href'] = $ref;
    return $this->tag('a:link',$text,$attr);
  }

  function name($key){
    return $this->tag('a:name',NULL,array('name'=>$key));
  }

  /* autodetection between a/page/www/mail depending on href in args */
  function autolink($text,$args=array(),$attr=array()){
    if(!isset($args['href']))          return $this->a($text,$args,$attr);
    $href = $args['href'];
    if(strpos($href,'://')!==FALSE)    return $this->www($href,$text,$args,$attr);
    if(strpos($href,'@')!==FALSE)      return $this->mail($href,$text,$args,$attr);
                                       return $this->page($href,$text,$args,$attr);
  }
  // ................................................................................


  function iframe($src,$name,$alt,$attr=array()){
    $this->obj->set('type','transitional');
    $this->prepare($tag,$data,$attr);
    $attr = array_merge(array('width'=>'100%','height'=>'400px;','name'=>$name,'src'=>$src),
			$attr);
    $this->tag('iframe',$alt,$attr);
  }

  /** creates an image */
  function img($src,$alt,$attr=array()){
    $this->prepare($tag,$data,$attr);
    if(isset($attr['thumb'])){
      $tmb = ops_array::key_extract($attr,'thumb');
      if(strpos($tmb,'%')!==FALSE){
	$pi = pathinfo($src);
	$tmb = str_replace('%d',$pi['dirname'],$tmb);
	$tmb = str_replace('%e',$pi['extension'],$tmb);
	$tmb = str_replace('%f',$pi['basename'],$tmb);
	$tmb = str_replace('%n',substr($pi['basename'],0,-1-strlen($pi['extension'])),$tmb);
      }
      $attr['src'] = $tmb;
      $attr['alt'] = $alt;
      if(isset($attr['java'])){
	$java = explode(' ',ops_array::key_extract($attr,'java'),2);
	unset($attr['java']);
	$java = "_opc_ht_$java[0] = window.open('$src','$java[0]','$java[1]');_opc_ht_$java[0].focus();";
	$link = array('target'=>'_blank','onclick'=>'javascript:' . $java);
      } else $link = array('target'=>'_blank','href'=>$src);
      $this->open('a',$link);
      $this->tag('img',NULL,$attr);
      $res = $this->close();
    } else {
      $attr['src'] = $src;
      $attr['alt'] = $alt;
      return $this->tag('img',NULL,$attr);
    }
  }

  function js($code){
    $this->tag('script',$code,array('type'=>'text/javascript','style'=>'display: none;'));
  }

  /* --------------------------------------------------------------------------------
     Multi-tags
     -------------------------------------------------------------------------------- */

  /** produce a sequence of tags
   * args: free sequence of tag-names, text and attr-arrays
   */
  function sequence(/* ... */){
    $ar = $this->_multi(func_get_args(),2);
    foreach($ar as $cr){
      list($txt,$args) = $cr;
      switch(count($txt)){
      case 2: $res = $this->tag($txt[0],$txt[1],$args); break;
      case 1: $res = $this->tag(def($args,'tag'),$txt[0],$args); break;
      case 0: $res = $this->tag(def($args,'tag'),def($args,0),$args); break;
      }
    }
    return $res;
  }

  /** creates a nested tag structer
   * args: text, free sequence of tag-names and attr-arrays (inner first)
   */
  function nested(/* ... */){
    $ar = func_get_args();
    $txt = array_shift($ar);
    $ar = array_reverse($this->_multi($ar,1));
    foreach($ar as $cr) $res = $this->open($cr[0][0],$cr[1]);
    $this->add($txt);
    return $this->close(count($ar));
  }

  /** creates a nested tag structer
   * args: text, free sequence of tag-names and attr-arrays (outer first)
   */
  function nestedO(/* ... */){
    $ar = func_get_args();
    $txt = array_shift($ar);
    $ar = $this->_multi($ar,1);
    foreach($ar as $cr) $res = $this->open($cr[0][0],$cr[1]);
    $this->add($txt);
    return $this->close(count($ar));
  }

  /** creates a nested tag structer
   * args: array, inner tag, outer tag
   */
  function chain(/* ... */){
    $ar = func_get_args();
    $txt = array_shift($ar);
    $ar = $this->_multi($ar,1);
    $it = array_shift($ar);
    $ar = array_reverse($ar);
    $res = array();
    foreach($ar as $cr) $res[] = $this->open(def($cr[0],0,NULL),$cr[1]);
    foreach($txt as $ct) $this->tag(def($it[0],0,NULL),$ct,$it[1]);
    $this->close(count($ar));
    return $res[0];
  }

  /** creates a nested tag structer
   * args: text, free sequence of tag-names and attr-arrays (outer first)
   */
  function nopen(/* ... */){
    $ar = $this->_multi(func_get_args(),1);
    foreach($ar as $cr) $res = $this->open($cr[0][0],$cr[1]);
    return $res;
  }

  /** divides an array in parts useable for tag/open and similar functions
   * a NULL is used as default divider
   * an array is always the last part
   * up to $ntxt non array are accepted
   * @return array(array(array(txt1,txt2 ...),args-array),array(...))
   */
  protected function _multi($ar,$ntxt){
    $ce = NULL;     // current ender
    $res = array(); //result
    $ar[] = NULL;   // add default stop
    while(count($ar)){
      $txt = array();
      $args = array();
      if(is_null($ce)) $ce = array_shift($ar);
      while(!is_null($ce)){
	if(is_array($ce)){
	  $args = $ce; 
	  $ce = NULL;
	  break;
	} 
	if(count($txt)==$ntxt) break;
	$txt[] = $ce;
	$ce = array_shift($ar);
      }
      if(!empty($txt) or !empty($args)) $res[] = array($txt,$args);
    }
    return $res;
  }

  // ................................................................................


  /** add a remark to the stack */
  function rem($text,$level=1,$open=FALSE,$add=array()){
    //if(!$this->ok()) return $this->err->err(3);
    $obj = new $this->extcls['rem']($text,$level,$add);
    if($open){
      $res = $this->insert('rem',NULL,$obj,strtolower($this->nxt),$this->key);
      $this->_in();
      $this->key = $res;
    } else $res = $this->insert('rem',NULL,$obj,strtolower($this->nxt),$this->key);
    return $this->err->ok($res);

  }

  function incl($key,$nxt=NULL){
    if($key instanceof opc_ptr_ht2)
      $id = $key->root;
    else if(is_scalar($key) and isset($this->obj->str[$key]))
      $id = $key;
    else 
      return FALSE;
    if(is_null($nxt)) $nxt = $this->nxt;
    $res = $this->insert('ph','incl',$id,strtolower($nxt),$this->key);
    if($nxt=='nxt' or $nxt=='prv') $this->key = $res;
    return $this->err->ok($res);
  }
  
  /** same as incl but embbed by a tag */
  function incl_tag($obj,$tag,$attr=array()){
    if($obj instanceof opi_ptr) $key = $obj->root;
    else if(is_scalar($obj)) $key = $obj;
    else return $this->err->err(1);
    if(!isset($this->obj->str[$key])) return FALSE;
    $res = $this->open($tag,$attr);
    $this->insert('ph','incl',$key,strtolower($this->nxt),$this->key);
    $this->close();
    if($this->nxt=='nxt' or $this->nxt=='prv') $this->key = $res;
    return $this->err->ok($res);
  }


  function search_child($tag,$kind='tag',$set=TRUE){
    $cp = $this->obj->str[$this->key]['fcl'];
    while(!is_null($cp)){
      if($this->obj->str[$cp]['typ']==$kind and $this->obj->str[$cp]['tag']==$tag){
	if($set){
	  $this->key = $cp;
	  return TRUE;
	} else return $cp;
      } else $cp = $this->obj->str[$cp]['nxt'];
    }
    return FALSE;
  }

  /** returns the name of the start php script; see opc_ht2 fore more details */
  function myself($flag=1){ return $this->obj->myself($flag);}


  /**   function in_tag: includes an object (using method output of it)
   * @param &$obj
   * @param $tag to embedd
   * @param $attrs=array
   * @return NULL
   */
  function in_tag(&$obj,$tag,$attrs=array()){
    $this->tag($tag,$obj->output(),$attrs);
  }

  
  /**   function d: returns result insterad of adding to stack
   * @param string $fct
   * @param  ... other arguments
   * @return string
   */
    function d($fct/*, ... */){
    $ar = func_get_args();
    $fct = array_shift($ar);
    return $this->da($fct,$ar);
  }

  /**   function da: returns result insterad of adding to stack
   * @param string $fct
   * @param array $arg other arguments
   * @return string
   */
  function da($fct,$args=array()){
    if(!method_exists($this,$fct)) 
      trg_err(2,'Call to undefined method ' . get_class($this) . '::' . $fct,E_USER_ERROR);
    $this->ndcall = TRUE;
    $ptr = call_user_func_array(array($this,$fct),$args);
    $res = $this->obj->exp2html($ptr,FALSE);
    $this->obj->remove($ptr);
    $this->ndcall = FALSE;
    return $res;
  }

  function __call($fct,$args){
    if(substr($fct,-2,2)==='2s'){
      return $this->da(substr($fct,0,-2),$args);
    } else trg_err(1,'Call to undefined method ' . get_class($this) . '::' . $fct,E_USER_ERROR);
  }


  function mark_set($name,$key=NULL){
    $this->obj->mark_set(is_null($key)?$this->key:$key,$name);
  }

  function mark_get($name){
    $res = $this->obj->mark_get($name);
    if(is_null($res)) return FALSE;
    $this->key = $res;
    return TRUE;
  }

  /* ================================================================================
   some functions
   ================================================================================ */

  /** set an array element if it doesnt yet exist
   * returns true if new value was set 
   * side effect
   */
  static function setweak(&$arr,$key,$val=NULL){
    $res = !array_key_exists($key,$arr);
    if($res) $arr[$key] = $val;
    return $res;
  }

  /** set an array element if it doesnt exist yet or is NULL
   * and the new value is not null too
   * returns true if new value was set 
   * side effect
   */
  static function setweakN(&$arr,$key,$val){
    $res = !(isset($arr[$key]) or is_null($val));
    if($res) $arr[$key] = $val;
    return $res;
  }

  /** set an array element if the new value is not null 
   * returns true if new value was set 
   * side effect
   */
  static function setIfNN(&$arr,$key,$val){
    $res = is_null($val);
    if(!$res) $arr[$key] = $val;
    return $res;
  }

  /** ensure the existenz of an element in array $arr (side effects)
   * $val not null: use this value
   * $key is not used and its value is NULL:  use def
   * else: no changes
   * VED: Meas Value > Element > Default
   */
  static function setVED(&$arr,$key,$val,$def){
    if(!is_null($val)) $arr[$key] = $val;
    else if(!isset($arr[$key])) $arr[$key] = $def;
  }

  /** similar to setVED but other priorities   */
  static function setEVD(&$arr,$key,$val,$def){
    if(isset($arr[$key])) return;
    $arr[$key] = is_null($val)?$def:$val;
  }
  /** simpler version of setargs
      $ar array of elements which will be matched to a name
      $types: array($key=>typesting[,$key=>$ty...])
        where type string is a combination of single characters (see $cb)
      $def: potential array of defaults
      $cb optional callback function which gets one arguments and returns one character
        default (NULL) returns N:NULL, B:Bool; i:int; f:float; n:numeric string; s: other strings
	                       A: array; O: Object; R: resource; -:others (?)
  */
  static function args_set(&$args,$types,$def=array(),$cb=NULL){
    $unused = array();
    foreach($args as $ca){
      if(is_callable($cb)){
	$typ = $cb($ca);
      } else {
	if(is_null($ca)) $typ = 'N';
	else if(is_bool($ca)) $typ = 'B';
	else if(is_integer($ca)) $typ = 'i';
	else if(is_float($ca)) $typ = 'f';
	else if(is_numeric($ca)) $typ = 'n';
	else if(is_string($ca)) $typ = 's';
	else if(is_array($ca)) $typ = 'A';
	else if(is_resource($ca)) $typ = 'R';
	else if(is_object($ca)) $typ = 'O';
	else $typ = '-';
      }
      foreach($types as $ck=>$ct){
	if(strpos($ct,$typ)===FALSE) continue;
	$def[$ck] = $ca;
	unset($types[$ck]);
	continue 2;
      }
      $unused[] = $ca;
    }
    $args = $unused;
    return $def;
  }

  function new_ptr($key=NULL){
    $res = clone $this;
    $res->set($res->obj->ptr_new($key));
    return $res;
  }

  function ptr($add=array()){
    return $this->obj->ptr($add);
  }

  function clean($skey){
    $this->obj->clean($skey);
  }
  
  function replace($tar,$src,$keep=TRUE){
    $this->obj->replace($tar,$src,$keep);
  }

}


/** dummy klasse um einen key aus ht2 als objekt zu kennzeichnen */
class opc_ht2e {
  public $key = NULL;
  function __construct($key){ $this->key = $key;  }
  function __get($key){ return $this->key;}
  function __set($key,$val) {$this->key = $val;}
}

/** dient als auffangbecken für ht2-elemente, die sequentiell abgearbeitet werden
 * achtung next ist doppelt belegt!!! Immer mit Argument verwenden!
 */

class opc_ptr_ht2a extends opc_ptr_ht2 implements countable, Iterator{
  protected $aa_ptr = NULL;
  /* ================================================================================
     Array Access: key is one of the data array
     Countable (implement)
     ================================================================================ */
  public function rewind()  { $this->aa_ptr = $this->obj->str[$this->root]['fcl']; }
  public function next($n=NULL)  { 
    if(is_null($n)){ // act like the Iterator-next
      $this->aa_ptr = $this->obj->str[$this->aa_ptr]['nxt'];
      return is_null($this->aa_ptr)?FALSE:$this->aa_ptr;
    } else return parent::next($n); // act like ht2-next
  }
  public function key()     { return $this->aa_ptr;}
  public function current() { return new opc_ht2e($this->obj->str[$this->aa_ptr]['key']);}
  public function valid()   { return !is_null($this->aa_ptr);}

  /** count number of saved items */
  function count(){
    if(is_null($this->obj->str[$this->root]['fcl'])) return 0;
    $ck = $this->obj->str[$this->root]['fcl'];
    $i = 0;
    while(!is_null($ck)){
      $i++;
      $ck = $this->obj->str[$ck]['nxt'];
    }
    return $i;
  }
  //~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}

class opc_ht2s {
  
  public $mth_open = array('aopen','open');
  public $mth_close = array('close');

  public $data = array();
  public $ptrs = array();

  function add(/* ... */){
    $this->data[] = func_get_args();
  }

  function step_out(&$ht,&$lev,$mth,$data,$key){
    if(in_array($mth,$this->mth_open)) $lev++;
    if($ht instanceof opi_ptr){
      $res = call_user_func_array(array($ht,$mth),$data);
    } else if($ht instanceof opc_ht2o){
      $res = call_user_func_array(array($ht->ht,$mth),$data);
    } else qz();
    if(in_array($mth,$this->mth_close)) $lev--;
    return $res;
  }

  function out(&$ht){
    $this->ptrs = array();
    $lev = 0;
    foreach($this->data as $step){
      $mth = array_shift($step);
      $key = array_shift($step);
      $ptr = $this->step_out($ht,$lev,$mth,$step,$key);
      if(is_string($key)) $this->ptrs[$key] = $ptr;
      if($lev<0) return -1;
    }
    return 0;
  }

  function out_onestep(&$ht,$step){
    $this->ptrs = array();
    $lev = 0;
    $skip = TRUE;
    foreach($this->data as $step){
      $mth = array_shift($step);
      $key = array_shift($step);
      if($skip and $step==$key) $skip = FALSE;
      if(!$skip)
	$this->ptrs[$key] = $this->step_out($ht,$lev,$mth,$step,$key);
      if($lev<0) return 0;
    }
    return 0;
  }

  
}


?>
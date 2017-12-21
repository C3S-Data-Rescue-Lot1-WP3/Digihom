<?php

  /* import (incl name transf)
   * rename (prio 3)
   * paste (only from root-nodes, including root?, how)
   *
   */
class opc_tstore extends opc_classA implements countable, ArrayAccess, Iterator{
  // structer and data
  public $str = array();
  public $data = array();

  /* default pointer and his mode ============================================================ */
  public $pi = NULL;
  public $pm = 'f';

  // basic structer element
  public $root_key = '*';
  protected $root_ele = array('u'=>NULL, 'l'=>0,
			      'b'=>NULL, 'f'=>NULL,
			      'df'=>NULL,'dl'=>NULL);


  /* function which is responsible to save the real data
   * called after a new node is inserted
   */

  public $fct_data_add = 'data_add_one';

  /* opi_classA ---------------------------------------------------------- */
  protected $init_class = 'tstore';
  protected $init_mode = 1;

  /* countable -------------------------------------------------- */
  function count(){ return count($this->str);}

  /* Array-Access -------------------------------------------------- */
  function offsetExists($key){return isset($this->str[$key]);}
  function offsetGet($key){return $this->data[$key];}
  function offsetSet($key,$val){
    if(isset($this->str[$key])) $this->data[$key] = $val;
    else trigger_error("Undefined offset $key");
  }
  function offsetUnset($key){ 
    if(isset($this->str[$key])) $this->data[$key] = NULL;
    else trigger_error("Undefined offset $key");
  }
    

  /* Iterator -------------------------------------------------- */
  protected $ii = NULL;
  public function current(){ return $this->data[$this->ii];}
  public function key(){ return $this->ii;}
  public function next(){
    $ak = array_keys($this->str);
    $this->ii = def($ak,array_search($this->ii,$ak,TRUE)+1);
  }
  public function rewind(){
    $ak = array_keys($this->str);
    $this->ii = $ak[0];
  }
  public function valid(){ return isset($this->str[$this->ii]);}


  function init_first(){
    $this->str = array($this->root_key=>$this->root_ele);
    $this->data = array($this->root_key=>NULL);
    $this->pi = $this->root_key;
    $this->pm = 'd';
    return 0;
  }

  function nkey($key){
    static $n=0;
    if(is_null($key)) return ++$n;
    if(isset($this->str[$key])) return $key . '::' . ++$n;
    return $key;
  }


  /* creates a new root node (level=0)
   * if pi==0 pi and pm will set to this node
   */
  function create($key=NULL,$data=NULL,$pi=0){
    $key = $this->nkey($key);
    $this->str[$key] = $this->root_ele;
    $this->data[$key] = $data;
    if($pi===0){
      $this->pi = $key;
      $this->pm = 'd';
    }
    return $key;
  }

  /* removes a node and his childs from current place
   * if kill: removes completly
   * if not: moves away as new root-node
   */
  function remove($key,$kill=TRUE){
    if(!isset($this->str[$key])) return FALSE;
    $str = &$this->str[$key];
    if(is_null($str['u'])) return FALSE;

    $this->stoat($str);
    if($kill===TRUE){
      foreach($this->seq($key) as $ck) 
	unset($this->str[$ck],$this->data[$ck]);
    } else {
      $str['u'] = NULL; $str['b'] = NULL; $str['f'] = NULL;
      $l = $str['l']; 
      foreach($this->seq($key) as $ck) $this->str[$ck]['l'] -= $l;
    }
  }
  
  /* reorders childs of par
   * keys: child ids in the new order
   * attention: missing childs will be added at the end
   * keys in $keys which are not a (direct) child of par are ignored
   */
  function reorder($par,$keys){
    if(!is_array($keys)) return 1;
    $chlds = $this->childs($par,FALSE,FALSE);
    if(!is_array($chlds)) return 1;
    $ord = array_merge(array_intersect($keys,$chlds),array_diff($chlds,$keys));
    if(count($ord)<1 or $ord===$chlds) return -1;
    $n = count($ord);
    $this->str[$par]['df'] = $ord[0];
    $this->str[$par]['dl'] = $ord[$n-1];
    $this->str[$ord[0]]['b'] = NULL;
    $this->str[$ord[$n-1]]['f'] = NULL;
    for($i=0;$i<$n-1;$i++) $this->str[$ord[$i]]['f'] = $ord[$i+1];
    for($i=1;$i<$n;$i++) $this->str[$ord[$i]]['b'] = $ord[$i-1];
    return 0;
  }

  // just the stoat the env around $str (points to a row in $this->str)
  protected function stoat(&$str){
    if(is_null($str['b'])) $this->str[$str['u']]['df'] = $str['f'];
    else                   $this->str[$str['b']]['f']  = $str['f'];
    if(is_null($str['f'])) $this->str[$str['u']]['dl'] = $str['b'];
    else                   $this->str[$str['f']]['b']  = $str['b'];
  }


  /* ============================================================
   data functions
   the var $fct_data_add defines which function does save teh real data
   the function get 2 arguments: 
   1) key of a (new) valid element (or FALSE)
   2) array with the remaining args from add/open/embed ....
   the function should return the 
  */

  // take care of fct_data_add
  function data_add($key,$data){
    if($key===FALSE) return $key;
    $mth = $this->fct_data_add;
    $tmp = $this->$mth($key,$data);
    return $key;
  }
  
  // saves only the first element of $data
  function data_add_one($key,$data){
    $this->data[$key] = array_shift($data);
  }

  // saves the array itself
  function data_add_all($key,$data){
    $this->data[$key] = $data;
  }


  /* default functions for default pointer  */
  function add($key=NULL,$data=NULL){ 
    $ar = func_get_args();
    return $this->data_add($this->_add($this->pi,$this->pm,array_shift($ar)),$ar);
  }

  function open($key=NULL,$data=NULL){ 
    $ar = func_get_args();
    return $this->data_add($this->_open($this->pi,$this->pm,array_shift($ar)),$ar);
  }

  function embed($key=NULL,$data=NULL){ 
    $ar = func_get_args();
    return $this->data_add($this->_embed($this->pi,$this->pm,array_shift($ar)),$ar);
  }


  function add_last($key,$par,$data){
    $key = $this->nkey($key);
    if(is_null($par)) $par = $this->root_key;
    $pm = 'dl';
    $this->add__dl($par,$pm,$key);
    return $this->data_add($key,$data);
  }


  function open_p()             { return $this->_open_p ($this->pi,$this->pm);}
  function close()              { return $this->_close  ($this->pi,$this->pm);}
  function close_n($n=1)        { return $this->_close_n($this->pi,$this->pm,$n);}
  function close_l($l=1)        { return $this->_close_l($this->pi,$this->pm,$l);}
  function set($key,$mode=NULL) { return $this->_set    ($this->pi,$this->pm,$key,$mode);}

  
  /* ================================================================================
   default functions
   ================================================================================ */

  function _add(&$pi,&$pm,$key){
    $key = $this->nkey($key);
    $mth = 'add__' . $pm;
    $this->$mth($pi,$pm,$key);
    $pi = $key;
    return $key;
  }

  function _open(&$pi,&$pm,$key=NULL){
    $key = $this->nkey($key);
    $mth = 'add__' . $pm;
    $this->$mth($pi,$pm,$key);
    $pi = $key;
    $pm = 'd';
    return $key;
  }

  function _embed(&$pi,&$pm,$key=NULL){
    $key = $this->nkey($key);
    $str = &$this->str[$pi];
    $sub = $this->seq($pi);
    $nstr = array('u'=>$str['u'],'l'=>$str['l'],
		  'b'=>$str['b'],'f'=>$str['f'],
		  'df'=>$pi,'dl'=>$pi);
    if(!is_null($str['u'])) {
      if(is_null($str['b'])) $this->str[$str['u']]['df'] = $key;
      else                   $this->str[$str['b']]['f']  = $key;
      if(is_null($str['f'])) $this->str[$str['u']]['dl'] = $key;
      else                   $this->str[$str['f']]['b']  = $key;
    }
    $str['u'] = $key;
    $str['f'] = NULL;
    $str['b'] = NULL;
    foreach($sub as $ck) $this->str[$ck]['l']++;

    $this->str[$key] = $nstr;
    $pi = $key;
    return $key;
  }


  function _close(&$pi,&$pm){
    if(!isset($this->str[$pi])) return FALSE;
    if(!isset($this->str[$pi]['u'])) return FALSE;
    $pi = $this->str[$pi]['u'];
    $pm = is_null($this->str[$pi]['u'])?'d':'f';
    return $pi;
  }

  // tries to close up to n nodes, set pm back to n
  function _close_n(&$pi,&$pm,$n=1){
    $pm = 'f';
    for($i=0;$i<$n;$i++){
      $tmp = $this->str[$pi]['u'];
      if(is_null($tmp)) return FALSE;
      $pi = $tmp;
    }
  }

  function _close_l(&$pi,&$pm,$l=1){
    while($this->str[$pi]['l']>$l){
      $tmp = $this->str[$pi]['u'];
      if(is_null($tmp)) return FALSE;
      $pi = $tmp;
    }
    $pm = 'f';
  }


  function _open_p(){
    $this->pm = 'd';
  }

  function _set(&$pi,&$pm,$key,$mode=NULL){
    if(!isset($this->str[$key])) return FALSE;
    $this->pi = $key;
    if(!is_null($mode)) $this->pm = $mode;
  }

  /* subs of them ============================================================ */
  protected function add__d(&$pi,&$pm,$key){
    $this->add__dl($pi,$pm,$key);
    $pm = 'f';
  }

  protected function add__df(&$pi,&$pm,$key){
    $str = &$this->str[$pi];
    $nstr = array('u'=>$pi,'l'=>$str['l']+1,
		  'b'=>NULL,'f'=>$str['df'],
		  'df'=>NULL,'dl'=>NULL);
    if(is_null($str['df'])) $str['dl'] = $key;
    else   $this->str[$str['df']]['b'] = $key;
    $str['df'] = $key;
    $this->str[$key] = $nstr;
  }

  protected function add__dl(&$pi,&$pm,$key){
    $str = &$this->str[$pi];
    $nstr = array('u'=>$pi,'l'=>$str['l']+1,
		  'b'=>$str['dl'],'f'=>NULL,
		  'df'=>NULL,'dl'=>NULL);
    if(is_null($str['dl'])) $str['df'] = $key;
    else   $this->str[$str['dl']]['f'] = $key;
    $str['dl'] = $key;
    $this->str[$key] = $nstr;
  }

  protected function add__f(&$pi,&$pm,$key){
    $str = &$this->str[$pi];
    if(is_null($str['u'])) return FALSE;
    $nstr = array('u'=>$str['u'],'l'=>$str['l'],'b'=>$pi,
		  'df'=>NULL,'dl'=>NULL,'f'=>$str['f']);
    if(is_null($str['f']))
      $this->str[$str['u']]['dl'] = $key;
    else
      $this->str[$str['f']]['b'] = $key;
    $str['f'] = $key;
    $this->str[$key] = $nstr;
  }

  protected function add__b(&$pi,&$pm,$key){
    $str = &$this->str[$pi];
    if(is_null($str['u'])) return FALSE;
    $nstr = array('u'=>$str['u'],'l'=>$str['l'],'f'=>$pi,
		  'df'=>NULL,'dl'=>NULL,'b'=>$str['b']);
    if(is_null($str['b']))
      $this->str[$str['u']]['df'] = $key;
    else
      $this->str[$str['b']]['f'] = $key;
    $str['b'] = $key;
    $this->str[$key] = $nstr;
  }


  /* ================================================================================
   moving function 
   ================================================================================ */
  /* up, forward, backward, down-first, down-last,
   * next (all levels), previous (all levels), home (=root)
   * sibling-last, sibling-first
   */

  // using default pointer
  function u() { return $this->move('u',$this->pi);}
  function f() { return $this->move('f',$this->pi);}
  function b() { return $this->move('b',$this->pi);}
  function df(){ return $this->move('df',$this->pi);}
  function dl(){ return $this->move('dl',$this->pi);}
  function n() { return $this->nxt($this->pi);}
  function p() { return $this->prv($this->pi);}
  function h() { return $this->home($this->pi);}
  function sf(){ return $this->sibf('df',$this->pi);}
  function sl(){ return $this->sibl('dl',$this->pi);}

  // using given pointer
  function pu (&$pi){ return $this->move('u',$pi);}
  function pf (&$pi){ return $this->move('f',$pi);}
  function pb (&$pi){ return $this->move('b',$pi);}
  function pdf(&$pi){ return $this->move('df',$pi);}
  function pdl(&$pi){ return $this->move('dl',$pi);}
  function pn (&$pi){ return $this->nxt($pi);}
  function pp (&$pi){ return $this->prv($pi);}
  function ph (&$pi){ return $this->home($pi);}
  function psf(&$pi){ return $this->sibf($pi);}
  function psl(&$pi){ return $this->sibl($pi);}


  /* working horses of them ------------------------------------------------------------ */

  // first sibling
  function sibf(&$key){
    if(is_null($this->str[$pi]['u'])) return FALSE;
    return $pi = $this->str[$this->str[$pi]['u']]['df'];
  }

  // last sibling
  function sibl(&$key){
    if(is_null($this->str[$pi]['u'])) return FALSE;
    return $pi = $this->str[$this->str[$pi]['u']]['dl'];
  }

  // next element (all levels, down: allowed to go dwon into the structer?)
  function nxt(&$pi,$down=TRUE){
    if($down and isset($this->str[$pi]['df']))
      return $pi = $this->str[$pi]['df'];
    $tmp = $pi;
    while(is_null($this->str[$tmp]['f'])){
      if(isset($this->str[$tmp]['u']))
	$tmp = $this->str[$tmp]['u'];
      else
	return FALSE;
    }
    return $pi = $this->str[$tmp]['f'];
  }

  // prev element (all levels)
  function prv(&$pi){
    if(isset($this->str[$pi]['b'])){
      $pi = $this->str[$pi]['b'];
      while(isset($this->str[$pi]['dl']))
	$pi = $this->str[$pi]['dl'];
      return $pi;
    } 
    if(isset($this->str[$pi]['u']))
      return $pi = $this->str[$pi]['u'];
    return FALSE;
  }

  // home of this element (on level 0)
  function home(&$pi){
    while(isset($this->str[$pi]['u']))
      $pi = $this->str[$pi]['u'];
    return $pi;
  }

  // move (u,b,f,df,dl)
  function move($m,&$pi){
    if(is_null($this->str[$pi][$m])) return FALSE;
    return $pi = $this->str[$pi][$m];
  }


  /* ================================================================================
   Extract sequences
   ================================================================================ */

  // list of ids of this node and all subchildes
  function seq($pi=NULL,$root=TRUE){
    if(is_null($pi)) $pi = $this->root_key;
    if(!isset($this->str[$pi])) return FALSE;
    $l = $this->str[$pi]['l'];
    $res = $root?array($pi):array();
    while(FALSE!== $this->nxt($pi)){
      if($this->str[$pi]['l']==$l) break; 
      else $res[] = $pi;
    } 
    return $res;
  }


  // similar to seq but with max depth
  function seq_depth($pi,$depth=1){
    if(!isset($this->str[$pi])) return FALSE;
    $l = $this->str[$pi]['l'];
    $lm = $l + $depth;
    $res = array($pi);
    while(FALSE!==$this->nxt($pi,$this->str[$pi]['l']<$lm)){
      if($this->str[$pi]['l']<=$l) break; else $res[] = $pi;
    } 
    return $res;
  }

  // all ids between level min and max (both including)
  function layers($min,$max,$pi=NULL){
    if(is_null($pi)) $pi = $this->root_key;
    if(!isset($this->str[$pi])) return FALSE;
    $res = $this->str[$pi]['l']>=$min?array($pi):array();
    while(FALSE!==$this->nxt($pi,$this->str[$pi]['l']<$max)){
      if($this->str[$pi]['l']>=$min) $res[] = $pi;
    } 
    return $res;
  }


  /* ================================================================================
   get/set something
   ================================================================================ */
  function lev($key=NULL){
    return defn($this->str,is_null($key)?$this->pi:$key,'l',FALSE);
  }


  function data($key=NULL,$def=NULL){
    return def($this->data,is_null($key)?$this->pi:$key,$def);
  }

  function data_nz($key=NULL,$def=NULL){
    return defnz($this->data,is_null($key)?$this->pi:$key,$def);
  }

  function root($key=NULL){
    if(is_null($key)) $key = $this->pi;
    while(!is_null($this->str[$key]['u']))
      $key = $this->str[$key]['u'];
    return $key;
  }

  /* returns for a sequence of ids the corresponding level */
  function seq2lev($seq=NULL){
    if(is_null($seq))        $seq = $this->seq($this->root_key);
    else if(is_scalar($seq)) $seq = $this->seq($seq);

    $res = array();
    foreach($seq as $key) $res[$key] = $this->str[$key]['l'];
    return $res;
  }


  /* returns for a sequence of ids the corresponding structer */
  function seq2str($seq){
    $res = array();
    foreach($seq as $key) $res[$key] = $this->str[$key];
    return $res;
  }

  /* expects a valid array as returned by seq2lev (id=>level,id=>lev...)
   * and returns an table of operation steps to process this tree
   * n=>array(id=>id,op=>operation,lev=>level,pos=>branch-pos)
   *  where oepration is one of open, add or close
   * An open an the coresponding close have 
   *  the same id, level and branch-position!
   * if sequence is not valid an integer is returned
   *  + (1) missing level inbetween
   *  + (2) first element is not on the lowest level at all
   * if not valid an integer is 
   */
  function seq2oac($seq=NULL){
    if(is_null($seq))
      $seq = $this->seq2lev($this->seq($this->root_key));
    else if(is_scalar($seq))
      $seq = $this->seq2lev($this->seq($seq));
    else if(!is_array($seq))
      return FALSE;

    if(count($seq)==0)
      return array();
    else if(count($seq)==1)
      return array(array('id'=>def(array_keys($seq),0),'op'=>'add','lev'=>min($seq),'pos'=>0));

    $res = array(); // result
    $fl = min($seq); // minimum level
    $ll = $fl; // last used level
    $p = 0; // position per branch
    $n = 0; // over all position
    $b = array(); // array(x=>n): n opend level x

    foreach($seq as $ele=>$cl){
      if($cl<$fl){ // first is not at the bottom (2)
	return 2;
      } else if($cl>$ll) { // change from add to open if we are deeper
	if($n==0) return 2; // first is not at the bottom (2)
	$res[$n-1]['op'] = 'open';
	$b[$cl] = $n-1;
	if($cl>++$ll) return 1; // too large steps (1)
	$p = 0;
      } else if($cl<$ll){
	do {
	  $res[$n++] = array('id'=>$res[$b[$ll]]['id'],'op'=>'close',
			     'lev'=>$ll-1,'pos'=>$res[$b[$ll]]['pos']);
	} while(--$ll>$cl);
	$p = $res[$n-1]['pos']+1;
      }

      // add current element as add
      $res[$n++] = array('id'=>$ele,'op'=>'add','lev'=>$cl,'pos'=>$p++);
    }
    // close remaining levels
    do {
      $res[$n++] = array('id'=>$res[$b[$ll]]['id'],'op'=>'close',
			 'lev'=>$ll-1,'pos'=>$res[$b[$ll]]['pos']);
    } while(--$ll>$fl);
    return $res;
  }

  /* returns for a sequence of ids the corresponding data */
  function seq2data($seq){
    $res = array();
    foreach($seq as $key) $res[$key] = $this->data[$key];
    return $res;
  }

  // returns all root-ids (=levle 0)
  function roots(){
    $res = array();
    foreach($this->str as $ck=>$cv)
      if($cv['l']==0) $res[] = $ck;
    return $res;
  }


  function keys(){
    return array_keys($this->str);
  }

  /* returns path of key as array
   * self = FALSE: add key as last element
   * root = T/F: starts with level 0/1
   */
  function path($key=NULl,$self=FALSE,$root=TRUE){
    if(is_null($key)) 
      $key = $this->pi;
    else if(!isset($this->str[$key])) 
      return array();
    $res = $self?array($key):array();
    while(!is_null($this->str[$key]['u']))
      array_unshift($res,$key = $this->str[$key]['u']);
    if(!$root) array_shift($res);
    return $res;
  }

  /* returns array of all childs of key (in logical order)
   * all=FALSE no grand-childs
   * self=TRUE include key to the list too
   */

  function childs($key=NULL,$all=TRUE,$self=TRUE){
    if(is_null($key)) 
      $key = $this->pi;
    else if(!isset($this->str[$key])) 
      return array();
    $res = $self?array($key):array();
    $cl = $this->str[$key]['l'];
    $this->nxt($key,TRUE);
    while($this->str[$key]['l']>$cl){
      $res[] = $key;
      if($this->nxt($key,$all)===FALSE) break;
    }
    return $res;
  }

  function child_lists($root=NULL,$empty=TRUE){
    if(is_null($root)) $roots = $this->roots();
    else $roots = (array)$root;
    $res = array();
    while(count($roots)){
      $root = array_shift($roots);
      $tmp = $this->childs($root,FALSE,FALSE);
      if($empty or !empty($tmp)) $res[$root] = $tmp;
      if(is_array($tmp)) $roots = array_merge($roots,$tmp);
    }
    return $res;
  }
  /* imports data as childs of par
   * par: parent element
   *  if not known it will be created
   * seq is an array of id=>level
   *  the items will be imported in this order!
   *  missing levels will be completed
   * data: if an array used for setting data (or NULL if key is missing)
   * pmode: mode how to add the first elemnt rel to par (f b d dl df)
   */

  function import_keylev($par,$seq,$data=array(),$pmode='d'){
    if(!is_array($seq)) return 1;
    if(empty($seq)) return -1;
    if(is_null($par)) $par = $this->pi;
    if(!isset($this->str[$par])) $par = $this->create($par,NULL,FALSE);

    $ll = min($seq) - (in_array($mode,array('f','b'))?0:1);
    $n = 0;
    foreach($seq as $ck=>$tl){     
      if($tl>$ll){
	for($j=$ll+1;$j<$tl;$j++) $this->_open($par,$mode);
	$mode = 'd';
      } else $this->_close_n($par,$mode,$ll-$tl);
      if($n++==0) $mode = $pmode;
      $this->_add($par,$mode,$ck);
      if(is_array($data)) $this->data_add_all($par,def($data,$ck));
      $ll = $tl;
    }	
    return 0;
  }

  //debug
  function q1($x=NULL){
    if(is_null($x)){
      $tmp = $this->str;
    } else if(is_scalar($x)){
      $tmp = $this->seq2oac($this->root($x));
    } else if(is_array($x)){
      $tmp = $x;
    }
    $res = array();
    foreach($tmp as $ck=>$cv){
      if(is_array($cv) and isset($cv['id'])){
	$ck = $cv['id'];
      }
      $d = $this->data[$ck];
      $cv['d'] = is_object($d)?$d->Tag:$d;
      $res[] = $cv;
    }
    qt($res);
  }

  }

class opc_tsptr extends opc_classA  {
  protected $map_class_tstore = 'opc_tstore';
  protected $map_get_dir = array('ts','key','mode');

  protected $ts = NULL;
  protected $key = NULL;
  protected $mode = 'd';

  protected $tool = NULL;
  protected $fw = NULL;

  function init_last(){
    if(!is_object($this->ts)){
      $cls = $this->map_class_tstore;
      $this->ts = new $cls();
      if(!($this->ts instanceof opc_tstore)) return 10010;
    }
    if(is_null($this->key)) $this->key = $this->ts->create();
    return 0;
  }
  
  /* classA ============================================================ */
  protected $init_class = 'ts-pointer';

  function initOne($key,$val){
    if(is_null($val)){
      $cls = $this->map_class_tstore;
      $this->ts = new $cls();
      return ($this->ts instanceof opc_tstore)?0:10010;
    } else if(is_string($val)){
      if(is_object($this->ts)){
	if(isset($this->ts[$val])) $this->key = $val;
	else $this->key = $this->ts->create($val,NULL,0);
      } else if(class_exists($val)){
	$this->ts = new $val();
	return ($this->ts instanceof opc_tstore)?0:10010;
      } else return 10010;
    } else if($val instanceof opc_tstore){
      $this->ts = &$val;
    } else if($val instanceof opc_tsptr){
      $this->ts = $ts->get_store();
    } else if($val instanceof _tools_){
      $this->tool = $val;
    } else if($val instanceof opc_fw3){
      $this->fw = $val;
      if(!is_object($this->tool)) 
	$this->tool = $val->tool;
    } else return parent::initOne($key,$val);
    return 0;
  }
  

  function add($key=NULL,$data=NULL){
    $ar = func_get_args();
    $nkey = $this->ts->_add($this->key,$this->mode,array_shift($ar));
    return $this->ts->data_add($nkey,$ar);
  }

  function add_at(&$at_key,&$at_mode,$key=NULL,$data=NULL){
    $ar = array_slice(func_get_args(),3);
    $nkey = $this->ts->_add($at_key,$at_mode,$key);
    return $this->ts->data_add($nkey,$ar);
  }

  function open($key=NULL,$data=NULL){
    $ar = func_get_args();
    $nkey = $this->ts->_open($this->key,$this->mode,array_shift($ar));
    return $this->ts->data_add($nkey,$ar);
  }

  function close(){ 
    return $this->ts->_close($this->key,$this->mode);
  }

  function get_store(){
    return $this->ts;
  }

  function get_key(){
    return $this->key;
  }

  function t($tag,$data=NULL,$add=array()){
    return $this->prep($tag,$data,$add);
  }

  function te($tag,$add=array()){
    return $this->t($tag,NULL,$add);
  }

  function a(){
    $res = new opc_attrs('wrap');
    $ar = func_get_args();
    foreach($ar as $ca) $res['.'] = $ca;
    return $res;
  }

  protected function prep($tag,$data,$attr){
    
    if(is_string($attr)) $attr = $this->auto_attr($attr);

    if(!is_string($tag) or $tag==''){
      if($attr instanceof opc_attrs) $tag = $attr->tag;
      else if(is_array($attr))       $tag = def($attr,'tag','');
      else                           $tag = '';
    }
    
    if($tag==='-' or $tag==''){
      $attr =new opc_attrs('wrap');
    } else if(is_null($attr)){
      $attr = new opc_attrs($tag);
    } else if($attr instanceof opc_attrs){
      if(!is_null($tag)) $attr->set('tag',$tag);
    } else if(is_array($attr)){
      $tmp = new opc_attrs($tag);
      foreach($attr as $key=>$val)
	$tmp[is_numeric($key)?'.':$key] = $val;
    } else return trg_err(1,'Invalid attr: ' . var_export($attr,TRUE));
    if(!is_null($data)) $attr['.'] = $data;
    return $attr;
  }

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

  function root(){
    return $this->ts->root($this->key);
  }


  // level of the current position
  function lev(){
    return $this->ts->lev($this->key);
  }

  // level of the next inserted element
  function lev_next(){
    $lev = $this->ts->lev($this->key);
    if(in_array($this->mode,array('d','dl','df'))) $lev++;
    return $lev;

  }


  function data(){
    return $this->ts->data($this->key);
  }

  // debug 
  function q1(){ $this->ts->q1();}
  function q2(){ $this->ts->q1($this->key);}


}
?>
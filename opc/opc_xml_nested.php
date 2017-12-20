<?php 
  /*

  INTRODUCTION ========================================
  possible implementation of opc_xml_basic
  uses nested objects of opc_xmlnode


  wieso erscheint auf allen ebenen errcode, movepos etc.
  -> hüllklasse ganz oben??

   */

include_once('opc_xml_basic.php');
include_once('ops_arguments.php');


class opc_nxml extends opc_xml_basic{
  //xml data
  var $tag = '';
  var $attrs = array();
  var $childs = array();
  
  //pointer
  var $cpos = array();

  var $err = array('mode'=>0,'code'=>0,'msg'=>NULL);

  // search settings default
  var $search_settings = array('direction'=>1,'levels'=>2);

  function opc_nxml($name='xmlnode'){
    $this->init();
    $this->tag = $name;
  }


  function path($pos=NULL,$info=1){
    if($pos===FALSE) return("-");
    $pos = $this->extpos($pos);
    $knd = $this->_kind($pos);
    $cl = count($pos);
    $nam = $cl>0?$pos[$cl-1]:NULL;

    switch($info){
    case 2:
      if($cl>0){
	$res = '';
	for($ii=0;$ii<$cl-1;$ii++)
	  $res .= ' / ' . $this->name(array_slice($pos,0,$ii+1))
	    . '[' . (1+$pos[$ii]) . '/'
	    . $this->childs_count(array_slice($pos,0,$ii)) .']';
	switch($knd){
	case 1:
	  $nc = $this->childs_count(array_slice($pos,0,$cl-1));
	  $np = $nam+1;
	  $res .= ' / ' . $this->name($pos) . "[$np/$nc] = Node ("
	    . $this->attrs_count($pos) . ' attr, '
	    . $this->childs_count($pos) . ' childs)';
	  break;
	case 2: 
	  $att = $this->attrs($pos);
	  $np = array_search($nam,array_keys($att))+1;
	  $nc = count($att);
	  $val = $att[$nam];
	  $vl = strlen($val);
	  $val = htmlspecialchars(substr($val,0,30));
	  if($vl>30) $val .= '...';
	  $res .= " / @[$np/$nc] = Attribute ($vl chars): $nam = $val";
	  break; 
	case 3:
	  $nc = $this->childs_count(array_slice($pos,0,$cl-1));
	  $np = $nam+1;
	  $val = $this->get($pos);
	  $vl = strlen($val);
	  $val = htmlspecialchars(substr($val,0,30));
	  if($vl>30) $val .= '...';
	  $res .= " / \$[$np/$nc] = Text ($vl chars): $val";
	}
      } else {
	$res = '/[root: ' . $this->name(array())
	  . '; ' . $this->attrs_count($pos) . 'attr;'
	  . $this->childs_count($pos) . 'childs]';

      }
      break;
    default:
      $res = '/';
      if($cl>0) $res .= implode('/',$pos);
      switch($knd){
      case 1: $res .= ':' . $this->name($pos); break;
      case 2: $res .= ':@' . strlen($this->get($pos)); break;
      case 3: $res .= ':$' . strlen($this->get($pos)); break;
      }
    }
    return($res);
  }

  function init($tagname='',$attrs=array(),$childs=array()){
    $this->tag = $tagname;
    $this->attrs = $attrs;
    $this->childs = is_array($childs)?array_values($childs):array($childs);
    $this->cpos = array();
  }

  /* ======================= test functions ========================= */
  //checks if the complete xml structer is empty (or not valid)
  function is_emptyxml(){
    return(count($this->attrs)==0 and count($this->childs)==0);
  }

  // checks if the asked attr exsits (pos is a node or an attr; key may be an array
  function attr_exists($key,$pos=NULL){
    $pos = $this->iter('t',$pos,FALSE);
    $ci = $this->get($pos);
    if(!$this->thisclass($ci)) return($this->_err(-11));
    if(is_array($key)){
      $res = array();
      foreach($key as $ck) $res[$ck] = array_key_exists($ck,$ci->attrs);
      return($res);
    } else return(array_key_exists($key,$ci->attrs));
  }

  /* ------------------------------------------------------------*/



  /* ========================= structer ========================= */

  //returns the current level (top level is 0)
  function level($pos=NULL){//TUDU TEST
    $pos = $this->extpos($pos);
    $lev = count($pos);
    if($lev==0) return(0);
    return(is_string($pos[--$lev])?$lev--:$lev);
  }

  //returns the position (node/textdata -> n'th child; attr -> n'th attr)
  function n($pos=NULL){// TUDU TEST
    $pos = $this->extpos($pos);
    if(count($pos)==0) return(0);
    if(is_string($pos[count($pos)-1])){
      $ak = array_pop($pos);
      $ci = $this->get($pos);
      return(array_search($ak,array_keys($ci->attrs)));
    } else return(array_pop($pos));
  }

  // count the number of attr (pos is a node or an attr)
  function attrs_count($pos=NULL){
    $pos = $this->iter('t',$pos,FALSE);
    $ci = $this->get($pos);
    return($this->thisclass($ci)?count($ci->attrs):NULL);
  }

  //counts the number of childs (pos is the parant node)
  function childs_count($pos=NULL){
    $ci = $this->get($pos);
    return($this->thisclass($ci)?count($ci->childs):NULL);
  }

  //counts the number of textdata childs
  // if trim is true, only textdata with length > 0 after a trim are counted
  function textdata_count($trim=FALSE,$pos=NULL){
    $ci = $this->get($pos);
    if(!$this->thisclass($ci)) return($this->_err(-10));
    $res = 0;
    foreach($ci->childs as $cc) if(!$this->thisclass($cc)) $res++;
    return($res);
  }

  // counts the number of subnodes (no textdata, pos is the parent node)
  // if key is given it counts only subnodes of this type
  function nodes_count($key=NULL,$pos=NULL){
    $ci = $this->get($pos);
    if(!$this->thisclass($ci)) return($this->_err(-10));
    $res = 0;
    foreach($ci->childs as $cc) if($this->thisclass($cc)) $res++;
    return($res);
  }

  /* ------------------------------------------------------------*/

  /* ========================= element operation ================== */

  // returns the current item (attr/text -> string; node -> a object)
  function get($pos=NULL){
    $pos = $this->extpos($pos);
    if(count($pos)==0) return($this);
    $ci = array_shift($pos);
    if(is_string($ci)) 
      return($this->attrs[$ci]);
    if(count($pos)==0) 
      return(isset($this->childs[$ci])?$this->childs[$ci]:NULL);
    return($this->childs[$ci]->get($pos));
  }

  function set($value,$pos=NULL){
    $pos = $this->extpos($pos);
    if(count($pos)==0) return(FALSE);
    $ci = array_shift($pos);
    if(is_string($ci)) {
      $this->attr_set($ci,$value);
    } else if(count($pos)==0) {
      $this->childs[$ci] = $value;
    } else return($this->childs[$ci]->set($value,$pos));
    return(TRUE);
  }

  // returns the name (attr -> attrname, node -> tagname, textdata -> NULL)
  function name($pos=NULL){// TUDU TEST
    $pos = $this->extpos($pos);
    switch($this->_kind($pos)){
    case 1: $ci = $this->get($pos); return($ci->tag);
    case 2: return(array_pop($pos));
    }
    return($this->_err(-11));    
  }

  // shortcut to return the attribute id
  function id($pos=NULL){return($this->attr('id'));}

  // returns a array (numeric keys) of all childs (pos is a node)
  function childs($pos=NULL){
    $pos = $this->iter('t',$pos,FALSE);
    $ci = $this->get($pos);
    return($this->thisclass($ci)?$ci->childs:NULL);
  }

  // returns an array (string keys) of all attributes  (pos is a node or an attr)
  function attrs($pos=NULL){
    $pos = $this->iter('t',$pos,FALSE);
    $ci = $this->get($pos);
    return(is_object($ci)?$ci->attrs:NULL);
  }

  // returns an array with attribute names
  function attr_keys($pos=NULL){
    $pos = $this->iter('t',$pos,FALSE);
    $ci = $this->get($pos);
    return(is_object($ci)?array_keys($ci->attrs):NULL);
  }

  // returns the value of an attribute (pos is a node or an attr)
  // key may be an array (-> result is a named array)
  function attr($key,$pos=NULL){
    $pos = $this->iter('t',$pos,FALSE);
    $ci = $this->get($pos);
    if(is_array($key)){
      $res = array();
      foreach($key as $ck) $res[$ck] = isset($ci->attrs[$ck])?$ci->attrs[$ck]:NULL;
      return($res);
    } else return(isset($ci->attrs[$key])?$ci->attrs[$key]:NULL);
  }
  
  function attr_def($key,$def=NULL,$pos=NULL){
    $pos = $this->iter('t',$pos,FALSE);
    $ci = $this->get($pos);
    return(isset($ci->attrs[$key])?$ci->attrs[$key]:$def);
  }

  //removes a element
  function remove($pos=NULL){
    $pos = $this->extpos($pos);
    if(count($pos)==0){
      return($this->_err(20));
    } else if(count($pos)>1){
      $cpos = array_shift($pos);
      if(!$this->thisclass($this->childs[$cpos])) return($this->_err(-9));
      return($this->childs[$cpos]->remove($pos));
    } else if(is_string($pos[0])){
      $ak = $this->attrs;
      if(!array_key_exists($pos[0],$ak)) return($this->_err(-9));
      $res = $ak[$pos[0]];
      unset($ak[$pos[0]]);
      $this->attrs = $ak;
      return($res);
    } else {
      $ak = $this->childs;
      if(!array_key_exists($pos[0],$ak)) return($this->_err(-9));
      $res = $ak[$pos[0]];
      unset($ak[$pos[0]]);
      $this->childs = array_values($ak);
      return($res);
    }
  }

  // renames the given position (pos is a node or an attr)
  function rename($newname,$pos=NULL){//TODO CODE
    $pos = $this->extpos($pos);
    if(!is_string($newname)) return($this->_err(30));
    if(count($pos)==0){
      $res = $this->tag;
      $this->tag = $newname;
      return($res);
    } else if(count($pos)==1 and is_string($pos[0])){
      $ak = $this->attrs;
      if(!array_key_exists($pos[0],$ak)) return($this->_err(-9));
      $ak[$newname] = $ak[$pos[0]];
      unset($ak[$pos[0]]);
      $this->attrs = $ak;
      return($pos[0]);
    } else {
      $cpos = array_shift($pos);
      if(!$this->thisclass($this->childs[$cpos])) return($this->_err(-9));
      return($this->childs[$cpos]->rename($newname,$pos));
    }
  }
  
  /* inserts/update an attribute 
   pos: points to a node or an attribute of a node  
  */
  function attr_set($key,$value,$pos=NULL){
    $pos = $this->iter('t',$pos,FALSE);
    if(count($pos)>0) {
      $ci = array_shift($pos);
      if($this->thisclass($this->childs[$ci])) $this->childs[$ci]->attr_set($key,$value,$pos);
    } else {
      $this->attrs[$key] = $value; 
    }
  }

  function attrs_set($attrs,$pos=NULL){
    $pos = $this->iter('t',$pos,FALSE);
    if(count($pos)>0) {
      $ci = array_shift($pos);
      if($this->_kind($ci)==1 and isset($this->childs[$ci])) 
	$this->childs[$ci]->attrs_set($attrs,$pos);
    } else {
      $this->attrs = array_merge($this->attrs,$attrs); 
    }
  }

  /* removes an attribute
   key may be an array of keys, NULL means all
   pos: points to a node or an attribute of a node  
  */
  function attr_remove($key=NULL,$pos=NULL){
    $pos = $this->extpos($pos);
    $knd = $this->_kind($pos);
    if(!is_array($key)) return($this->_err(30));
    if($knd!=1) return($this->_err(10));
    if(count($pos)>0){
      $cpos = array_shift($pos);
      if(!$this->thisclass($this->childs[$cpos])) return($this->_err(-9));
      return($this->childs[$cpos]->attr_remove($key,$pos));
    } else {
      $res = array();
      $ak = $this->attrs;
      foreach($key as $ck){
	$res[$ck] = isset($ak[$ck])?$ak[$ck]:NULL;
	unset($ak[$ck]);
      }
      $this->attrs = $ak;
      return($res);
    }
  }


  /* standards to child operations
   pos should point to a node or textdata
   inside: 
     TRUE: pos is a node and the operation will create/remove a child of it
     FALSE: pos is a node or textdata and the operation will create/remove a sibling of it
   nth: absolute position
     >= 0: counting from the first child (=0) to the last
     <  0: counting backward where -1 is the last element
     by inserting >= 0 means before this item, < 0 means after this item
  */

  /* insert a new child, pointer is not moved*/
  function child_insert($value,$nth=NULL,$inside=TRUE,$pos=NULL){//TODU TEST
    $pos = $this->iter('t',$pos,FALSE);
    if($inside===FALSE){
      if(count($pos)==0) return($this->_err(-20)); else array_pop($pos);
    }
    if(!$this->thisclass($value) and !is_string($value) and !is_array($value))
      return($this->_err(-31));
    return($this->_cinsert($value,is_null($nth)?-1:$nth,0,$pos));
  }


  /* replaces a existing child element (or add it at the end) */
  function child_replace($value,$nth=NULL,$inside=TRUE,$pos=NULL){//TODU TEST
    $pos = $this->iter('t',$pos,FALSE);
    if($inside===FALSE){
      if(count($pos)==0) return($this->_err(-20)); else array_pop($pos);
    }
    if(!$this->thisclass($value) and !is_string($value)) return($this->_err(-31));
    return($this->_cinsert($value,is_null($nth)?-1:$nth,1,$pos));
  }

  /* inserts a child as sibling relativ to position (rel: TRUE -> behind; FALSE -> before)*/
  function child_insertrel($value,$rel=TRUE,$pos=NULL){//TODU TEST
    if(is_null($rel)) $rel = TRUE;
    $pos = $this->extpos($pos);
    $knd = $this->_kind($pos);
    if($knd!=1 and $knd!=3) return($this->_err(-12));
    if(count($pos)==0) return($this->_err(-20));
    if(!$this->thisclass($value) and !is_string($value)) return($this->_err(-31));
    $cpos = array_pop($pos);
    if($rel) $cpos++;
    return($this->_cinsert($value,$cpos,0,$pos));
  }


  /* removes child
   if inside is FALSE it removes the current position (ignoring nth)
   if necessary the current position is updated too (even if save is false)
   */
  function child_remove($nth=NULL,$inside=TRUE,$pos=NULL,$move=FALSE){//TODU TEST
    $pos = $this->iter('t',$pos,FALSE);
    if($inside===FALSE){
      if(count($pos)==0) return($this->_err(-20)); else array_pop($pos);
    }
    if(!$this->thisclass($value) and !is_string($value)) return($this->_err(-31));
    $npos = ($this->_cinsert(array(),is_null($nth)?-1:$nth,1,$pos));
    $cp = array_pop($npos);
    if($cp<0) $cp = 0;
    //TUDU set new pos

  }

  /* inserts multiple child elements
   similar to child_insert
   value is an array of childs
   they will be inserted at the end
  */
  function childs_insert($value,$inside=TRUE,$pos=NULL){//TODU CODE
    $pos = $this->extpos($pos);
    if(is_null($inside)) $inside = TRUE;
  }

  /* removes all childs
   similar to child_remove
  */
  function childs_reset($inside=TRUE,$pos=NULL,$move=TRUE){//TODU CODE
    $pos = $this->extpos($pos);
  }

  /* replaces all childs by the given one
   similar to child_replace
   value is an array of childs
   current childs will be removed and the new one inserted
  */
  function childs_replace($value,$inside=TRUE,$pos=NULL){//TODU CODE
    $pos = $this->extpos($pos);
    if(is_null($inside)) $inside = TRUE;
  }

  /* similar to child_insert but with a new created subnode */
  function node_open($tagname,$nth=NULL,$inside=TRUE,$pos=NULL,$move=TRUE){
    $pos = $this->iter('t',$pos,FALSE);
    if($inside==FALSE){
      if(count($pos)==0) return($this->_err(-20)); else array_pop($pos);
    }
    if(empty($tagname)) return($this->_err(-30));
    $nn = new opc_nxml($tagname);
    $npos = $this->_cinsert($nn,is_null($nth)?-1:$nth,0,$pos);
    if(!is_null($npos) and $move) $this->cpos = $npos;
    return($npos);
  }

  /* similar to child_insertrel but with a new empty node */
  function node_openrel($tagname,$rel=NULL,$pos=NULL){//TODU CODE
    $pos = $this->extpos($pos);
    if(is_null($rel)) $rel = TRUE;
  }


  /* ------------------------------------------------------------*/

  
  /* ============================================================
   ===                                                        ===
   ===             non standard functions                     ===
   ===                                                        ===
   ============================================================ */
  
  /* 
   returns the kind of the position
   0: invalid position
   1: node
   2: attribute
   3: text data
  */
  function kind($pos=NULL){//TODU TEST
    return($this->_kind($this->extpos($pos)));
  }

  function _kind($pos){
    $ni = count($pos);
    if($ni==0) return(1);
    if(is_string($pos[$ni-1])) return(2);
    return(is_string($this->get($pos))?3:1);
  }


  /*flexibel function for iterations
   how is a coded string
   first Letter defines the kind of movement
    inside current level/node
     n: next
     p: previous
     l: last
     f: first

    inside the node
     r: rest           -> stays (no movement at all)
     t: this           attr -> parent node; node/text -> stays
     m: me             attr/text -> parent node; node -> stays
     a: attributes     node -> first attr; attr -> stays; text -> FALSE

    between levels
     h: home           -> root-node

     u: up             attr/text -> parent; node; node -> parent-node
     d: down           node -> first child; attr/text -> FALSE
     b: bottom         node -> last child;  attr/text -> FALSE
     c: child          node -> first child-node or FALSE; attr/text -> FALSE

     s: successing     -> next element (goes down/up if necessary);


     r t m h: are used without modificators and work on every position
     u d b c: are used without modificators and work only on nodes
     n p l f: need a modificator (N, A, T or S, see below)
              work only if the position is of the same kind
               except N and T may start on the opposite too
              they do not leave the current level or attributes
     s      : needs a modificator (any, see below)
              goes through everything ignoring items/levels and so on

   Elements to consider
     N: Nodes                         
     A: Attribute
     T: Textdata
     D: Data     (attr + text)
     S: Sibling  (node + text)
     L: Labled   (node + attr)
     E: Elements (node + attr + text)


   */
  function iter($how,$pos=NULL,$move=NULL){//TODU TEST/CODE
    if(!is_bool($move)) $move = ($this->movepos===TRUE);
    $pos = $this->extpos($pos);
    if(is_string($how)){
      if(strpos($how,' ')===FALSE) 
	return($this->_retpos($this->_iter($how,$pos),$move));
      $how = explode(' ',preg_replace('/ +/',' ',trim($how)));
    }

    // loop through the single commands
    foreach($how as $mc) 
      if(is_null($pos = $this->_iter($mc,$pos))) 
	return(NULL);
    return($this->_retpos($pos,$move));
  }

  /* internal version, pos has to be an array, move is not used*/
  function _iter($how,$pos){//TODU TEST/CODE
    // without modificators ----------------------------------------
    switch($how){
    case 'h': return(array()); 
    case 'r': return($pos);
    case 't': if($this->_kind($pos)==2) array_pop($pos); return($pos);
    case 'm': if($this->_kind($pos) >1) array_pop($pos); return($pos);
    case 'a': 
      switch($this->_kind($pos)){
      case 1:
	$ak = array_keys($this->attrs($pos));
	if(count($ak)==0) return($this->_err(-41));
	$pos[] = $ak[0];
	return($pos);
      case 2: return($pos);
      case 3: return($this->_err(-11));
      }
    case 'u':
      if(count($pos)==0) return($this->_err(-20));
      array_pop($pos); 
      return($pos);
    case 'd': case 'b': case 'c':
      $knd = $this->_kind($pos);
      if($knd!=1) return($this->_err(-10));
      $ni = $this->childs_count($pos);
      if($ni==0) return($this->_err(-40));
      switch($how){
      case 'd': $pos[] = 0; break;  
      case 'b': $pos[] = $ni-1; break;  
      case 'c': 
	$cl = count($pos);
	for($ci=0;$ci<$ni;$ci++){
	  $pos[$cl] = $ci;
	  if($this->_kind($pos)==1) break 2;
	}
	return($this->_err(-42));
      }
      return($pos);
    }

    // preparation for modificators  --------------------------------------------------
    $knd = $this->_kind($pos);
    $ele = substr($how,1,1); 
    $how = substr($how,0,1);
    $cl = count($pos);
    
    // s --------------------------------------------------
    if($how=='s') return($this->_iter_s($pos,$how,$ele,$knd));

    if(!$this->_match($knd,$ele,TRUE)) return($this->_err(-44)); 

    // f l n p  for Attributes -----------------------------------
    if($ele=='A'){
      $ak = array_keys($this->attrs($pos));
      $na = count($ak);
      if($na==0) return($this->_err(-41));
      $cp = array_search($pos[$cl-1],$ak);
      if($cp===FALSE) return($this->_err(-9));
      switch($how){ 
      case'f': $pos[$cl-1] = $ak[0]; return($pos); break;
      case'l': $pos[$cl-1] = $ak[$na-1]; return($pos); break;
      case 'n': 
	if(++$cp==$na) return($this->_err(-21)); 
	$pos[$cl-1] = $ak[$cp];
	return($pos);
      case 'p':
	if($cp==0) return($this->_err(-21)); 
	$pos[$cl-1] = $cp-1;
	return($pos);
      }
    }

    // f l n p inside current node ----------------------------------------
    if($cl==0){//on top-level
      if($how=='f' or $how=='l') return(array()); else return($this->_err(-21));
    }
    $cp = array_pop($pos); 
    $nc = $this->childs_count($pos);
    switch($how){ // which kind elements should be proofed if they match the asked element type
    case 'f': $ci = 0;     $li = $nc; $si = 1;  break;
    case 'l': $ci = $nc-1; $li = -1;  $si = -1; break;
    case 'n': $ci = $cp+1; $li = $nc; $si = 1;  break;
    case 'p': $ci = $cp-1; $li = -1;  $si = -1; break;
    default:
      return($this->_err(-1));
    }
    while($ci!=$li){
      $pos[$cl-1] = $ci;
      if($this->_match($this->_kind($pos),$ele)) return($pos);
      $ci += $si;
    }
    return($this->_err(-21));

  
  }

  function _iter_s($pos,$how,$ele,$knd){
    $searching = TRUE;
    while($searching){
      $cl = count($pos);
      switch($knd){
      case -1: // one up (or leaving attr) and search the next; internal only
	while($cl-- > 0){
	  $cp = array_pop($pos)+1; 
	  $nc = $this->childs_count($pos);
	  if($cp<$nc){ $pos[] = $cp; break 2;}
	}
	return($this->_err(-21)); // top node reached!
      case 1:
	if($ele=='A' or $ele=='D' or $ele=='L' or $ele=='E'){ // check attr too?
	  $ak = $this->attrs($pos);
	  $kk = array_keys($ak);
	  if(count($ak)>0) {$pos[] = array_shift($kk); break;} // are there attr?
	}
	if($this->childs_count($pos)>0) { $pos[] = 0; break;} // are ther childs?
	$knd = -1;
	continue 2;
      case 2:
	$ak = array_keys($this->attrs($pos));
	$cp = array_search($pos[$cl-1],$ak);
	if($cp===FALSE) return($this->_err(-9));
	if(++$cp==count($ak)) {$pos[$cl-1] = -1; $knd = -1; continue 2; }
	$pos[$cl-1] = $ak[$cp];
	break 2; // it is of the same type at all! -> saerch ends here
      case 3:
	$cp = array_pop($pos) + 1; 
	$nc = $this->childs_count($pos);
	$pos[] = $cp;
	if($cp==$nc){array_pop($pos); $knd = -1; continue 2;}
	break;
      }
      $knd = $this->_kind($pos);
      $searching = !$this->_match($knd,$ele);
    }
    return($pos);
  }

// does kind and ele (as used as Mod in iter) match?
// if weak Text and Nodes are equal!
  function _match($knd,$ele,$weak=FALSE){
    switch($ele){
    case 'N': return($knd==1 or ($weak and $knd==3));
    case 'A': return($knd==2);
    case 'T': return($knd==3 or ($weak and $knd==1));
    case 'D': return($knd!=1);
    case 'S': return($knd!=2);
    case 'L': return($knd!=3);
    case 'E': return(TRUE);
    }
  }

  /*
   search a node which fits the given criteria
   criteria:
      string: next node with this tag name
      array('key'=>'value,...): next node with this attributes (all)
              where a numeric key means node-name
   pos / move: as usual
   other arguments
       first boolean:  forward? default = TRUE (ignored at the moment)
       first integer: how move through the levels (lmode)
         0: use all levels (up and down), ignore current position
	 1: use all levels (up and down), accept current position (if a node)
         2: current and sublevels (down), ignore current position 
         3: current and sublevels (down), accept current position (if a node)
	 4: current level only, ignore current position (def)
	 5: current level only, accept current position (if a node)
	 6: all child levels (but not the current one, pos is a node)
	 7: next child level only (pos is a node)
       second integer: lower most accepted level (for lmode 0, 1, 2, 3 and 6)
       third integer:  upper most accepted level (for lmode 1 & 2)
   */
  function search($criteria,$pos=NULL,$move=NULL/* ... */){
    $ar = func_get_args();
    return(call_user_func_array(array(&$this,'search_node'),$ar));
  }
  function search_node($criteria,$pos=NULL,$move=NULL/*  further args */){
    $ar = func_get_args();
    $crit = array_shift($ar);
    $pos = $this->_iter('t',$this->extpos(array_shift($ar),FALSE));
    if(is_null($pos)) return(FALSE);
    $move = array_shift($ar);
    if(is_null($move)) $move = ($this->movepos===TRUE);    
    $def = array('forward'=>TRUE,'lmode'=>4,
		 'lmax'=>NULL,'lmin'=>NULL);
    $typ = array('forward'=>'boolean','lmode'=>'numeric',
		 'lmax'=>'numeric','lmin'=>'numeric');
    $ar = ops_arguments::setargs($ar,$def,$typ);
    extract($ar);

    $cl = count($pos);

    $init = array('sN','r','sN','r','nN','r','c','c'); // initial iter
    $pos = $this->_iter($init[$lmode],$pos);
    $cmove = array('sN','sN','sN','sN','nN','nN','sN','nN'); // looping iter
    $cmove = $cmove[$lmode];
    
    switch($lmode){
    case 2: case 3: $lmin = $cl; break;
    case 6: $lmin = $cl+1; break;
    }
    $kind = $this->_kind($pos);
    while(is_array($pos)){
      if($this->_kind($pos)==1 and $this->test_node($crit,$pos))
	return($this->_retpos($pos,$move)); // hit
      $cm = (is_null($lmax) or $lmax<=count($pos))?$cmove:'nN';
      $pos = $this->_iter($cm,$pos);
      if(!is_null($lmin) and $lmin>count($pos)) return(FALSE);
    }
    return(FALSE);
  }

  function test_node($crit,$pos=NULL){
    $tag = $this->name($pos);
    if(is_string($crit))  return($tag==$crit);
    $att = $this->attrs($pos);
    foreach($crit as $key=>$val){
      if(!is_string($key)) { // tagname
	if(is_array($val)){
	  if(!in_array($tag,$val)) return(FALSE);
	} else if($tag!=$val) return(FALSE);
      } else { // attribute value
	if(is_bool($val)){ // check only if set or not
	  if($val and !isset($att[$key])) return(FALSE);
	  if(!$val and isset($att[$key])) return(FALSE);
	} else {
	  if(!isset($att[$key])) return(FALSE);
	  if(is_array($val) and !in_array($att[$key],$val)) return(FALSE);
	  if($att[$key]!=$val) return(FALSE);
	}
      }
    }
    return(TRUE);
  }



  function extpos($pos=NULL,$move=FALSE){
    if(!is_array($pos)) {
      if(is_null($pos)) 
	$pos = $this->cpos;
      else if(is_numeric($pos))
	$pos = array_merge($this->cpos,array($pos));
      else if(!is_string($pos))
	$pos = array();
      else if(substr($pos,0,2)=='->')
	$pos = $this->iter(substr($pos,2),NULL,FALSE);
      else if($this->attr_exists($pos,$this->cpos))
	$pos = array_merge($this->cpos,array($pos));
      else
	$pos = FALSE;
    }
    if($pos===FALSE) return(FALSE);
    if($move) $this->cpos = $pos;
    return($pos);
  }

  function thisclass($ci){return(get_class($ci)=='opc_nxml');}

  function    pos($pos=NULL,$move=FALSE){return($this->extpos($pos,$move));}
  function getpos($pos=NULL,$move=FALSE){return($this->extpos($pos,$move));}
  function setpos($pos=NULL,$move=TRUE ){return($this->extpos($pos,$move));}

  function _retpos($pos,$move=TRUE){
    if(is_null($pos)) return(FALSE);
    if($move) $this->cpos = $pos;
    return($pos);
  }

  /* internal version of child_insert
   $obj: text data, node, or array of them
   $nth: 0...count(childs) -> insert before this item (or last iid same as count)
         -1 ... -count(childs) -> insert after
   $rem: number of elements to remove
   $pos: array pointer to a node
   */
  function _cinsert($obj,$nth,$rem,$pos){
    if(count($pos)>0){
      $cpos = array_shift($pos);
      if(!$this->thisclass($this->childs[$cpos])) return($this->_err(-10));
      $res = $this->childs[$cpos]->_cinsert($obj,$nth,$rem,$pos);
      if(is_array($res)) array_unshift($res,$cpos);
      return($res);
    } else {
      $nc = $this->childs_count(array());
      if($nth>$nc) return($this->_err(-21));
      if($nth<0) $nth += $nc + 1;
      if(!is_array($obj)) $obj = array($obj);
      array_splice($this->childs,$nth,$rem,$obj);
      return(array($nth-1+count($obj)));
    }
  }

  function _err($code,$txt=''){
    $this->err['code'] = abs($code);
    switch(abs($code)){
    case 0:  $msg = 'OK'; break;
    case 1:  $msg = 'invalid option'; break;
    case 9:  $msg = 'invalid pointer'; break;
    case 10: $msg = 'element is not a node'; break;
    case 11: $msg = 'element is neither a node nor an attribute'; break;
    case 12: $msg = 'element is neither a node nor text data'; break;
    case 20: $msg = 'toplevel reached'; break;
    case 21: $msg = 'out of range'; break;
    case 30: $mgs = 'invalid tag/attribute name'; break;
    case 31: $mgs = 'invalid textdata/node'; break;
    case 40: $msg = 'no childs'; break;
    case 41: $msg = 'no attributes'; break;
    case 42: $msg = 'no sub nodes'; break;
    case 43: $msg = 'no text data'; break;
    case 44: $msg = 'different types'; break;
    case 50: $msg = 'no match'; break;
    default:
      if($txt=='') $msg = 'unknown error'; else {$msg = $txt; $txt = '';}
    }
    if($txt!='') $msg .= ': ' . $txt;
    if($code!=0){
      switch($this->err['mode']){
      case 0: $this->err['msg'] = $code . ': ' . $msg; break;
      case 1: trigger_error($msg); break;
      case 2: trigger_error($msg); die;
      }
    }
    return($code<0?NULL:$code);
  }
    
}
?>
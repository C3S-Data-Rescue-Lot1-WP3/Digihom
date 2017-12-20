<?php
include_once('opc_xml_nested.php');
include_once('opc_xml_read.php');
include_once('opc_cstack.php');



/* ================================================================================
 single parse item
 ================================================================================ */

// Common ================================================================================
class opc_parseitem {
  var $typ = 'nil';
  var $stypes = array();
  var $con = NULL;

  function opc_parseitem(/* con, typ , styp*/){
    $ar = func_get_args();
    call_user_func_array(array(&$this,'construct'),$ar);
  }
  
  function construct(/* */){
    $ar = func_get_args();
    if(count($ar)==0) return;
    $this->con = array_shift($ar);
    if(count($ar)==0) return;
    $this->typ = array_shift($ar);
    if(count($ar)==0) return;
    $this->stypes = $ar;
  }
}

// Remark ================================================================================
class opc_parseitem_rem extends opc_parseitem{
  function opc_parseitem_rem($txt){
    return;
    $ar = func_get_args();
    if(count($ar)>0) $ar[0] = explode("\n",$ar[0]);
    call_user_func_array(array(&$this,'construct'),$ar);
  }
}

// Indent ================================================================================
class opc_parseitem_ind extends opc_parseitem{
  var $len = 0;
  var $typ = 'i';
  var $stypes = array('u');
  function opc_parseitem_ind($txt){
    switch($txt[0]){
    case ' ' : parent::construct(NULL,'i','s'); $this->len = strlen($txt); break;
    case "\n": parent::construct(NULL,'i','n'); $this->len = strlen($txt); break;
    case "\r": parent::construct(NULL,'i','r'); $this->len = strlen($txt); break;
    case "\t": parent::construct(NULL,'i','t'); $this->len = strlen($txt); break;
    }
  }
}




/* ================================================================================
 parse item collection
 ================================================================================ */
class opc_parseitems {
  var $items = array();
  var $typ = NULL;
  var $stack = NULL;
  
  function opc_parseitems($typ=NULL){
    $this->stack = new opc_cstack($this,array('items','typ'));
    $this->reset($typ,TRUE);
  }

  function reset($typ=NULL,$stack=FALSE){
    $this->items = array();
    $this->typ = $typ;
    if($stack==TRUE) $this->stack->reset();
  }

  function in($typ){
    $this->stack->in();
    $this->reset($typ);
  }

  function out(){
    $cstate = $this->stack->out();
    foreach($cstate['items'] as $ci) $this->add($ci);
    return($cstate['typ']);
  }

  function add($obj){
    if(is_null($obj)) return;
    if(!is_subclass_of($obj,'opc_parseitem') and !get_class($obj)=='opc_parseitem') {
      qa($obj,'add unknown ¦##');
      return(FALSE);
    }
    $this->items[] = $obj;
  }

  function count(){return(count($this->items));}

  function get($pos=-1){
    return($this->items[$pos>=0?$pos:(count($this->items)+$pos)]);
  }

  function push($obj){return($this->add($obj));}

  function pop(){return(array_pop($this->items));}

  function popn($nitems){
    $res = array();
    while($nitems-- > 0 and count($this->items)>0) 
      array_unshift($res,array_pop($this->items));
    return($res);
  }

  //find n-last position of the last (newest) $typ-element, retNULL=FALSE returns size+1
  function countNonTyp($typ){
    $ni = $this->count();
    for($ii=$ni-1;$ii>=0;$ii--) if($this->items[$ii]->typ==$typ) return($ni-$ii-1); 
    return($ni);
  }
  
  // fusion the current items to a single one, including ri-conversion
  function fusion_all($typ){
    $this->items = array($this->_fusion(new opc_parseitem(array(),$typ),$this->items));
  }

  // fusion the last part (up to the next f-item) of items to a single 'f'; incl- ri-conv
  function fusion_f(){
    $res = $this->popn($this->countNonTyp('f'));
    $obj = new opc_parseitem(array(),'f');
    $this->add($this->_fusion($obj,$res));
  }

  // fusion all of con to obj->con including ri-conversion
  function _fusion($obj,$con){
    $ci = ''; $cr = array();
    foreach($con as $ce){
      switch($ce->typ){
      case 'i':	$ci .= $ce->stypes[0] . $ce->len; break;
      case 'r': $cr[] = $ce; break;
      default:
	  if($ci!='')      $ce->ind .= $ci;
	  if(count($cr)>0) $ce->rem = $cr;
	  $ci = ''; $cr = array();
	  $obj->con[] = $ce;
      }
    }
    if($ci!='')      $obj->asp .= $ci;
    if(count($cr)>0) $obj->rem[] = $cr;

    return($obj);
  }

  /* merge last element in items with all preceeding indents and comments
   remove the new object from items and return it */
  function merge_ri(){
    $obj = array_pop($this->items);
    if($this->count()==0) return($obj);
    $ni = $this->count();
    for($ii=$ni-1;$ii>=0;$ii--) 
      if($this->items[$ii]->typ!='i' or $this->items[$ii]->typ!='e')
	break;
    $res = $this->popn($ni-$ii);    
    return($this->_fusion($obj,$res));
  }

}



/* ================================================================================
 parse php
 ================================================================================ */


class opc_parser_php {
  var $con = array();

  var $src = NULL;
  var $src_pos = 0;

  var $op = array('set_2'=>array('=','+=','-=','*=','/=','=>'),
		  'set_1'=>array('++','--'),
		  'cmp'=>array('>','<','>=','<=','==','!=','===','!=='), 
		  'lgc_1'=>array('!'),
		  'lgc_2'=>array('and','or','xor','|','||','&','&&'),
		  'str_1'=>array('if','elseif','else'),
		  'str_2'=>array('for','foreach','do','while','switch'),
		  'str_E'=>array('endfor','endwhile','endforeach','endif','endswitch'),
		  'str_I'=>array('as','case','default','break','continue'),
		  'err'=>array('exception','try','catch','throw'),
		  'fct'=>array('die','echo','empty','eval','exit','isset','print','return','unset'),
		  'icf'=>array('include','include_once','require','require_once'),
		  'cls'=>array('class','extends','var','declare','interface','implements','enddeclare',
			       'new','clone','this',
			       '::','->'),
 		  'mth_2'=>array('^','+','-','*','/','%'),
		  'oth_2'=>array('.'),
		  'con'=>array('array','function','list'),
		  'dec'=>array('global','static','const','final','public','private','protected','abstract'),
		  'int'=>array('__FILE__',' __LINE__',' __FUNCTION__',' __CLASS__','__METHOD__'),
		  'div'=>array('cfunction','old_function','php_user_filter','use'));
/*
keine Richtung  	new
rechts 	[
rechts 	! ~ ++ -- (int) (float) (string) (array) (object) @
links 	* / %
links 	+ - .
links 	<< >>
keine Richtung 	< <= > >=
keine Richtung 	== != === !==
links 	&
links 	^
links 	|
links 	&&
links 	||
links 	? :
rechts 	= += -= *= /= .= %= &= |= ^= <<= >>=
rechts 	print
links 	and
links 	xor
links 	or
links 	,

weiteres
  instanceof
  ``
  @
*/
  function opc_parser_php(){
    $this->con = new opc_parseitems();
    $this->reset();
  }

  function reset(){
    $this->con->reset('html');
  }

  function string2hxml($data){
    return($this->_2hxml(highlight_string($data,TRUE)));
  }
  function file2hxml($filename){
    return($this->_2hxml(highlight_file($filename,TRUE)));
  }
  function _2hxml($res){
    $hits = preg_split('|(<font color="#[A-F0-9]{6}">)|',$res,-1,PREG_SPLIT_DELIM_CAPTURE);
    $nh = count($hits);
    for($ii=1;$ii<$nh;$ii+=2){
      if(strpos('#DD0000',$hits[$ii])===FALSE){
	$hits[$ii+1] = preg_replace('|&nbsp;|',' ',$hits[$ii+1]);
	$hits[$ii+1] = preg_replace('|<br />|',"\n",$hits[$ii+1]);
      }
    }
    $res = implode('',$hits);
    $xml = new opc_nxml();
    $res = '<?xml version="1.0" encoding="ISO-8859-1"?>' . $res;
    new opc_xml_read($xml,$res);
    $xml->home();
    $xml->iter(array('d','d'));
    return($xml);
  }

  function parseH($xml){
    $this->reset();
    while(FALSE!==$xml->iter('nN')){
      switch($xml->attr('color')){
      case '#007700': $cres = $this->_parseH_S($xml); break;
      case '#FF8000': $cres = $this->_parseH_R($xml); break;
      case '#DD0000': $cres = $this->_parseH_T($xml); break;
      case '#0000BB': $cres = $this->_parseH_C($xml); break;
      default:
	$cres = NULL;
      }
      if(is_string($cres)) return($cres);
    }
    return($this->con);
  }

  function _parseH_C(&$xml){
    $nc = $xml->childs_count();
    $cobj = $xml->get($nc-1);
    $cobj = preg_split("/(\n+| +|\t+)/",$cobj,-1,PREG_SPLIT_DELIM_CAPTURE);
    foreach($cobj as $co){
      if(strlen($co)==0) continue;
      else if(strlen(trim($co))==0) $this->con->add(new opc_parseitem_ind($co));
      else {
	switch($co){
	case '<?php': $this->con->in('php'); break;
	case '?>':
	  if($this->con->typ!='php') return("Error: found '$typ' instead of '?&gt;' to close '&lt;?php'");
	  $this->con->fusion_all('p');
	  $this->con->out();
	  break;
	default:
	  $this->con->add(new opc_parseitem($co,$co[0]=='$'?'v':'c'));
	}
      }
    }
  }

  function _parseH_R(&$xml){
    $this->con->add(new opc_parseitem_rem($xml->get(0),'r'));
  }

  function _parseH_T(&$xml){
    $this->con->add(new opc_parseitem($xml->get(0),'t'));
  }

  function _parseH_S(&$xml){
    $cobj = array();
    $nc = $xml->childs_count();
    for($jj=0;$jj < $nc;$jj++) $cobj[] = $xml->get($jj);
    $cobj = preg_split("/(?:( +|;+|\n+|\t+|[()[\]{}]))/",implode('',$cobj),-1,PREG_SPLIT_DELIM_CAPTURE);
    foreach($cobj as $co){
      if(strlen($co)==0) continue;
      else if(strlen(trim($co))==0) $this->con->add(new opc_parseitem_ind($co));
      else {
	switch($co){
	case '(': case '[': case '{':	  $this->con->in($co); break;
	case ')': case ']': case '}': // closing brackets -> match to last structure
	  $typ = $this->con->typ . $co;
	  if(strpos('()[]{}',$typ)===FALSE) return("Error: wrong closing '$typ'");
	  $this->con->fusion_all($typ);
	  $this->con->out();
	  $res = $this->con->merge_ri();
	  $obj = new opc_parseitem($res,'s',$typ);
	  $this->con->add($res);
	  break;
	case ';': $this->con->fusion_f(); break;
	default:
	  while(strlen($co)>0){
	    $this->con->add(new opc_parseitem($co,'s',$this->typeS($co)));
	    break; // yes at the moment the loop is not a real one!
	  }
	}
      }
    }
  }

  function typeS($itm){
    foreach($this->op as $ck=>$cl) if(in_array($itm,$cl)) return($ck);
    return('not');
  }

 

  function file2src($filename){
    $this->src = fopen($filename,'r');
    $this->src_pos = 0;
  }

  function string2src($data){
    $this->src = $data;
    $this->src_pos = 0;
  }

  function src_c(){
    if(is_resource($this->src)) return(fgetc($this->src));
    if(isstring($src)) return(substr($this->src,$this->src_pos++,1));
    return(NULL);
  }

  function parse(){
    for($i=0;$i<100;$i++) $res .= $this->src_c();
    return($res);
  }
 
}
?>
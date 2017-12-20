<?php
  /* OPEN POINTS
   write to xml (with callback)
   */
include_once('ops_array.php');

class opc_xml_write {
  /* class to write xml data from a xml object (to string or file)

  the following methods have to be defined in this class (eg by extending opc_xml_basic)
    is_node: returns TRUE if current position is a node, FALSE if it is textdata
    childs_count: returns the number of childs of the current node
    up: moves pointer one level up returns NULL if on top level
    next: moves pointer to the next siblings (same parent node) or returns NULL
    down: moves pointer to the first child of the current node or returns NULL
    name: returns the name of the current pointer (node or attribute)
    attrs: reurns a named array with all attributes of the current node
    get: returns the object of the current position (used for textdata)

  callback functions
    You may define callback methodes/functions for certain situations.
    To do this just set the parser variable to the name of it
    Arguments are given by reference
    callback_textdata: used before textdata is inserted; 
      args: text, parser
      you may return a temporary mode-value which is used for this textdta only
      or -91 to you your changes directly (without additional changes)
    callback_open: called before a new node will be prepared for outpu
      args: tagname, attributes (named array), parser
      return value may a mode for this node (and its childs)
    callback_close: called after the node was written
      args: parser
  */

  var $xml = NULL; //the xml object
  var $xmlcharset = 'UTF-8'; // default cahrset
  var $xmlversion = '1.0';

  var $callback_textdata = NULL;
  var $callback_open = NULL;
  var $callback_close = NULL;

  /* mode: how the xml should be written
   mode >= 0 additive depending on the following parts
   mode < 0 special modes (see at the bottom)
   00-49: additional from three components
    child-nodes (one of)
     0: add nodes directly
     1: start new line with child nodes
     2: as 1 and set closing tab on a new line (if not really short)
    attributes (one of)
     0: all attributes behind the tag name on the same line
     3: line breaks between attributes possible
     6: each attribute on a single line
    textdata (one of)
     0: add textdata directly
     9: if long: start on a new line and use line breaks (no indent)
     18: as 9 but use the indent on all lines
     27: use CDATA-Structer
     36: use CDATA-Structer and start/end on a new line
    ignoring (multiple allowed)
     100: ignore attributes
     200: ignore textdata
     400: ignore child-nodes
   special modes
   -11: return array, attrs and childs on the same level; uses class variable names
   -12: return array, attrs and childs in own sub arrays; uses class variable names
   -51 ... -55: convert to html-code; uses class variable sep
      -51 is the most compact -55 the most extended version
   -91; reserved for callback function cbt   
   */
  var $mode = 22; //current mode
  var $modes = array();//mode of the upper levels
  var $level = 0;//current level


  // used for xml output
  var $indent = ' ';// used n-times at newlines per level
  var $linewidth = 80;//optimal linewidth

  // names used as keys if write to array (modes 4?)
  var $names = array('tag'=>'_tag','attrs'=>'_attr','childs'=>'_childs');

  /* spezial settings used in html-code if write to html (modes -51 to -55)
   child: separator between two childs
   atto: separator between two attributes
   attI: separator between attribute name and key
   hint: style/class attribute (inc name and all) for space, newlines and length
   */
  var $set = array('child'=>'&nbsp;&nbsp;::&nbsp;&nbsp;',
		   'attO'=>';&nbsp;&nbsp;',
		   'attI'=>':&nbsp;',
		   'hint'=>'style="color:#FF8800; font-weight: bold;"');

  function opc_xml_write($xml,$charset='UTF-8'){
    $this->xmlcharset = $charset;
    $this->xml = $xml;
  }

  function write($mode=NULL){
    $cmode = $this->mode;
    $this->modes = array();
    $this->level = 0;
    if(!is_null($mode)) $this->mode = $mode;
    while(FALSE!==($this->xml->up())){}
    $res = $this->_node();
    if($this->mode>=0){
      if($this->mode>0) $res = "\n" . $res; 
      $res = '<?xml version="' . $this->xmlversion . '" encoding="'
	. $this->xmlcharset . '"?>' . $res;
    }
    $this->mode = $cmode;
    return($res);
  }

  function _node(){
    // collect data
    $tag = $this->xml->name();
    $attrs = $this->xml->attrs();
    $nc = $this->xml->childs_count();
    $this->modes[] = $this->mode;
    $this->level++;
    if(!is_null($this->callback_open)) $this->mode = $this->cbo($name,$attrs);
    $attrs = $this->_attrs($attrs); //collect
    $childs = $this->_childs($nc);  //collect
    $this->mode = array_pop($this->modes);
    $this->level--;

    // childs finalize/fusion (rem mode may be different than during collecting above)
    switch($this->mode){
    case -51:
      $sep = array('gh'=>'<span class="childs">','gf'=>'</span>',
		   'is'=>$this->set['child'],'ea'=>'');
      $childs = ops_array::eimplode($childs,$sep);
      break;
    case -52: case -53: case -54: case -55:
      $x = rand(0,1000);
      $sep = array('gh'=>'<ol class="childs">','gf'=>'</ol>',
		   'ih'=>"\n" . '<li>','if'=>'</li>' .  "\n", 'ea'=>'');
      if(count($childs)==0) $childs = '';
      else $childs = ops_array::eimplode($childs,$sep);
      break;
    default:
      if($this->mode>=0) $childs = implode('',$childs);
    }

    // connect the single parts together
    $hl = ''; $fl = '';
    $cmode = $this->mode<0?$this->mode:($this->mode % 3);
    switch($cmode){
    case 2: $fl = "\n" . str_repeat($this->indent,$this->level); // no break;
    case 1: $hl = "\n" . str_repeat($this->indent,$this->level); // no break;
    case 0:
      $res = $hl . '<' . $tag . $attrs;
      if(empty($childs)) $res .='/>'; else $res .= '>' . $childs . $fl . '</' . $tag . '>';
      break;
    case -11:
      $res = array_merge(array($this->names['tag']=>$tag),$attrs,$childs);
      break;
    case -12:
      $res = array($this->names['tag']=>$tag,
		   $this->names['attrs']=>$attrs,
		   $this->names['childs']=>$childs);
      break;
    case -51: $res = $tag .' (' . $attrs . ')[' . $childs . ']'; break;
    case -52: $res = $tag ."\n(" . $attrs . ")\n" . $childs;; break;
    case -53: $res = $tag ."\n<br>" . $attrs . "\n" . $childs; break;
    case -54: case -55:  $res = $tag ."\n" . $attrs . "\n" . $childs; break;
    }

    if(!is_null($this->callback_close)) $this->cbc();
    return($res);
  }

  //collect childs and return them as array (single item are finalized)
  function _childs($nc){
    //collect
    $childs = array();
    if($nc>0){
      $this->xml->down();
      $cmode = $this->mode<0?0:(floor($this->mode/100));
      for($ci=0;$ci<$nc;$ci++){
	if($this->xml->is_node()){
	  if(($cmode & 4)!=4) $childs[] = $this->_node();
	} else {
	  if(($cmode & 2)!=2) $childs[] = $this->_textdata($this->xml->get());
	}
	if($ci+1<$nc) $this->xml->next();
      }	
      $this->xml->up();
    }
    return($childs);
  }

  function _attrs($attrs){
    if($this->mode < -10 and $this->mode > -20) return($attrs);
    if(count($attrs)==0) return('');
    if($this->mode>0 and (floor($this->mode/100) & 1)==1) return('');
    while(list($ak,$av)=each($attrs)) $attrs[$ak] = htmlspecialchars($av,ENT_COMPAT,$this->xmlcharset);
    $cmode = $this->mode<0?$this->mode:(floor($this->mode/3) % 3);
    switch($cmode){
    case -51: case -52: case -53:
      $sep = array('gh'=>'<span class="attrs">','gf'=>'</span>','kh'=>'<b>','kf'=>'</b>',
		   'kv'=>$this->set['attI'],'is'=>$this->set['attO']);
      break;
    case -54: 
      $sep = array('gh'=>'<ul class="attrs">','gf'=>'</ul>',  'ih'=>'<li>','if'=>'</li>',
		   'kh'=>'<b>','kf'=>'</b>','kv'=>$this->set['attI']);
      break;
    case -55: 
      $sep = array('gh'=>'<dl class="attrs">','gf'=>'</dl>',
		   'kh'=>'<dt>','kf'=>'</dt>','vh'=>'<dd>','vf'=>'</dd>');
      break;
    case 0:
      $sep = array('gh'=>' ','is'=>' ','kv'=>'=','vh'=>'"','vf'=>'"');
      break;
    case 1:
      $sep = array('kv'=>'=','vh'=>'"','vf'=>'"');
      return($this->_fill_line(ops_array::eimplode($attrs,$sep),TRUE,FALSE));
    case 2:
      $ind = "\n" . str_repeat($this->indent,$this->level);
      $sep = array('gh'=>$ind,'is'=>$ind,'kv'=>'=','vh'=>'"','vf'=>'"');
      break;
    }
    return(ops_array::eimplode($attrs,$sep));
  }
 
  //Text preparation done by callbak or default?
  function _textdata($txt){
    if(!is_null($this->callback_textdata)) $this->cbtd($txt);
    else $cmode = $this->mode;
    $cmode = $cmode<0?$cmode:(floor($cmode/9) % 100);
    switch($cmode){
    case 0:
      if(($this->mode % 3)!=0) $txt = trim($txt);
      return(htmlspecialchars($txt,ENT_NOQUOTES,$this->xmlcharset));
    case 1: case 2:
      $txt = htmlspecialchars(trim($txt),ENT_NOQUOTES,$this->xmlcharset);
      if(strlen($txt)+strlen($this->indent)*$this->level <= $this->linewidth) return($txt);
      return($this->_fill_line(explode(' ',$txt),$cmode==2,TRUE));
    case 3: return('<![CDATA[' . $txt . ']]>');
    case 4: return("\n<![CDATA[\n$txt\n]]>\n");
    case -51: case -52: case -53: case -54: case -55: //Text-Layout for for html
      $len = strlen($txt);
      $txt = htmlspecialchars($txt,ENT_NOQUOTES,$this->xmlcharset);
      $txt = str_replace(' ','<span ' . $this->set['hint'] . '>_</span>',$txt);
      if($cmode==-55) $txt = nl2br($txt); 
      else $txt = str_replace("\n",'<span ' . $this->set['hint'] . '>\\n</span>',$txt);
      $txt = str_replace("\t",'<span ' . $this->set['hint'] . '>\\t</span>',$txt);
      $txt = str_replace("\f",'<span ' . $this->set['hint'] . '>\\f</span>',$txt);
      $txt = str_replace("\r",'<span ' . $this->set['hint'] . '>\\r</span>',$txt);
      if($cmode==-55 or $cmode==-54) 
	$txt = '<span ' . $this->set['hint'] . '>[' . $len .']&nbsp;</span>' . $txt;
      else if($txt=='') 
	$txt = '<span ' . $this->set['hint'] . '></span>';
      return('<span class="textdata">' . $txt . '</span>');
    default:
      return($txt);
    }
  }
  
  function _fill_line($arr,$indent,$textdata){
    $indent = $indent?str_repeat($this->indent,$this->level):'';
    $res = $textdata?("\n" . $indent):'';
    $maxlen = max(20,$this->linewidth-strlen($indent));
    $linelen = 0;
    $sep = $textdata?'':' ';
    foreach($arr as $cval){
      $curlen = strlen($cval);
      if($linelen+$curlen<$maxlen or $linelen==0){
	$res .= $sep . $cval;
	$linelen += 1 + $curlen;
      } else {
	$res .= "\n" . $indent . $cval;
	$linelen = $curlen;
      }
      $sep = ' ';
    }
    return($res);
  }


  // CALLBACK ============================================================

  function cbtd(&$txt){
    if(method_exists($this->xml,$this->callback_textdata)) {
      $tm = call_user_func_array(array($this->xml,$this->callback_textdata),
				 array(&$txt,&$this));
    } else if(function_exists($this->callback_textdata)) {
      $tm = call_user_func_arary($this->callback_textdata,
				 array(&$txt,&$this));
    }
    return(is_numeric($tm)?$tm:$this->mode);
  }

  function cbo(&$tag,&$attr){
    if(method_exists($this->xml,$this->callback_open)) {
      $nm = call_user_func(array_array($this->xml,$this->callback_open),
			   array(&$tag,&$attr,&$this));
    } else if(function_exists($this->callback_open)) {
      $nm = call_user_func_array($this->callback_open,
				 array(&$tag,&$attr,&$this));
    }
    return(is_numeric($nm)?$nm:$this->mode);
  }

  function cbc(){
    if(method_exists($this->xml,$this->callback_close)) {
      call_user_func_array(array($this->xml,$this->callback_close),array(&$this));
    } else if(function_exists($this->callback_close)) {
      call_user_func_array($this->callback_close,array(&$this));
    }
  }



}
?>
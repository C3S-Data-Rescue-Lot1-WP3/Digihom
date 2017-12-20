<?php
  /* OPEN POINTS
   read from array (mode 41,42 from -write)
   */

class opc_xml_read {
  /* class to read xml data (from string or file) into a xml object

  the following methods have to be defined in this class (eg by extending opc_xml_basic)
    init: init the xml node; Args: tagname(string), attr(named array)
      is used for the toplevel node. pointer should show on this node afterwards
      If method not defined node_open and attrs_set are used instead
    node_open: open a new node using a tag-name; Args: tagname(string)
       The internal pointer of the xml-object should point afterwards to the new node
    attrs_set: set attributes of the current node; Args: attr(named array)
       Should not change the internal pointer of the xml-object.
    child_insert: used to insert textdata as last child; Args: text(string)
       Should not change the internal pointer of the xml-object.
    up: The internal pointer of the xml-object should go one level up; Args: [none]

  callback functions
    You may define callback methodes/functions for certain situations.
    To do this just set the parser variable to the name of it
    callback_textdata: used before textdata is inserted; Args: text (by ref) parser
      usefull to trim textdat
    callback_close: before a node is closed; Args: parser
      usefull for finishing the current node
    callback_open: before a ne node is opend; Args: name (by ref) attrs (by ref) parser
      this functions expects an integer as return value. See 'read modes' below.
      Insead of a function/method a array may be given which defines the mode to read
      array(mode=>items,mode=>item) wher item is one of the following
         string -> tested against the new tag-name
	 array -> array(key=>value)
            numeric key: value is tested against tag-name
            string key: value is tetsted against the attribute defined by key
                       if it is a boolean ->  defined or not?
                       if it is a string  -> are they equal
		       if it is an array  -> is it equal to one of the element

	                in this case value may be an array of potential values
       a typicall use would be array(51=>array('div','span',p'),
                                     52=>array('description','child_data'=>'xhtml'),
				      1=>'list')
         where: nodes like div,span and p are recogniced as xhtml-items
                the content of node description is regarded as xhtml
                the content of any node with an attribute child_data=xhtml too
                textdata inside the node list is ignored
           

  read modes
   The return value of a callback_open method/function allows you to define how it is read
   If you set a value different from 0 there will be no callback functionality until the
   node is closed (aftwerward mode is set back to 0). Read modes does not work on the top node.
   0: standard reading (using the methods explained above)
   1: ignores text data inside this node (and subnodes)
   2: ignores child nodes
   3: ignores text-data and child nodes
   51: The childs of the node contain xhtml-style data. This data will be saved as string
      instead of a (complex) xml-structer.
   52: Similar to 1, but the current node (tagname and attributes) are already part of the
      xhtml-style data. The result is saved as child of the current parent node.
   91: ignore this node completly
   99: stop reading (method up will be called as often as necessary to close the nodes)

  Remark: Please remeber that a xml-parser is not able to distinguish between <br/> and <br></br>

  */


  var $xml = NULL; // an external object which accepts xml data
  
  // internal objects (can be used inside callbacks)
  var $source = ''; //filename 
  var $level = 0; // current level
  var $mode = 0; // see above
  var $modes = array();//modes of the upper level (if mode < 50

  // parser object
  var $xmlp = null; // the parser itself
  var $xmlcharset = 'UTF-8'; // default charset [ISO-8859-1]
  var $xmltext = null; // last text data

  //Error
  var $errcode = 0;
  var $errmsg = NULL;


  /* the following vars (cb_*) contain names of methods (of xml-class)
   or names of global function. They are called to allow transforming
   the given data
  */
  // is called befor textdata is saved; Args: string
  var $callback_textdata = NULL; 
  var $callback_open = NULL; 
  var $callback_close = NULL; 


  function opc_xml_read(&$object,$data=NULL,$charset='UTF-8'){
    $this->xml = &$object;
    $this->xmlcharset = $charset;
    if(!is_null($data)) $this->read($data);
  }


  // reads a file or a xml-string into a node object 
  function read($data){
    $this->reset();
    $this->parser_set();
    if(substr($data,0,6)=='<?xml '){
      $this->source = 'string(len: '  . strlen($data) . ')';
      while(strlen($data)>0){
	$cl = substr($data,0,4096); $data = substr($data,4096);
	if($this->mode==99) return($this->_err(0));
	if(!xml_parse($this->xmlp,$cl,strlen($data)==0)) return($this->_err(10));
      }
    } else {
      $this->source = 'file: ' . $data;
      if(!file_exists($data)) return($this->_err(1,$data));
      $fp = fopen($data,'r');
      while ($data = fread($fp, 4096)){
	if($this->mode==99) return($this->_err(0));
	if(!xml_parse($this->xmlp,$data,feof($fp))) return($this->_err(10));
      }
      fclose($fp);
    }
    while($this->level-- > 0) $this->xml->up(); //necessary to close to nodes after a stop
    $this->mode = 0; $this->modes = array();
    xml_parser_free($this->xmlp);
    return($this->_err(0));
  }

  //reset including a reset call to the xml object
  function reset($mode=FALSE){
    $this->errcode = 0;
    $this->errmsg = NULL;
    $this->level = 0;
    if($mode) $this->mode = 0;
    $this->modes = array();
    $this->source = '';
    if(method_exists($this->xml,'init')) $this->xml->init(); 
    else $this->level = 1;
  }

  //sets a error message 
  function _err($code,$txt=NULL){
    $this->errcode = $code;
    switch($code){
    case 0: $msg = NULL;
    case 1: $msg = 'file not found'; break;
    case 10: 
      $msg = 'XML syntax error';
      $txt .= sprintf("%s at line %d in " . $this->source,
		      xml_error_string(xml_get_error_code($this->xmlp)),
		      xml_get_current_line_number($this->xmlp));
      break;
    default:
      if(is_null($txt)) $msg = 'unknown error';
      else { 
	$msg = $txt; 
	$txt = NULL;
      }
    }
    if(!is_null($txt)) $msg .= ': ' . $txt;
    $this->errmsg = $msg;
    return($code);
  }

  // PARSING ============================================================
  function parser_set(){
    $this->xmlp = xml_parser_create($this->xmlcharset);
    xml_parser_set_option($this->xmlp,XML_OPTION_CASE_FOLDING,0);
    xml_set_object($this->xmlp,$this);
    xml_set_element_handler($this->xmlp, "xmlp_start", "xmlp_end");
    xml_set_character_data_handler($this->xmlp,"xmlp_cdata");
  }

  function xmlp_addtextdata(){
    if(is_null($this->xmltext)) return;
    $cd = $this->xmltext;
    $this->xmltext = null;
    if(!is_null($this->callback_textdata) and $this->mode==0) {$this->cbtd($cd);}
    if($cd!='') $this->xml->child_insert($cd);
  }

  function xmlp_start($parser, $name, $attrs){
    array_unshift($this->modes,$this->mode);
    switch($this->mode){ // still the mode of the upper level!
    case 0: $this->xmlp_addtextdata(); // no break!
    case 1:
      if(!is_null($this->callback_open)) $this->mode = $this->cbo($name,$attrs);
      break;
    case 2:  $this->xmlp_addtextdata(); // no break!
    case 3:  $this->mode = 91; break;
    case 51: $this->mode = 52; break; //inside 51 equal inside 52 -> simpler fo next switch
    }
    //attention mode may have changed in the switch-statement above
    switch($this->mode){
    case 0: case 1: case 2: case 3: case 51: //open node in the xml-structer
      if($this->level>0){
	$this->xml->node_open($name);
	if(count($attrs)>0) $this->xml->attrs_set($attrs);
      } else $this->xml->init($name,$attrs); //init on highest level
      break;
    case 52: //open node in the xmltext
      $this->xmltext .= '<' . $name;
      while(list($ak,$av)=each($attrs))
	$this->xmltext .= ' ' . $ak . '="' . htmlspecialchars($av) . '"';
      $this->xmltext .= '>';
      break;
    }
    $this->level++;
  }  	
  
  function xmlp_end($parser, $name){
    $cmode = $this->mode;
    switch($this->mode){
    case 0: case 2:
      $this->xmlp_addtextdata(); // no break!
    case 1: case 3:  
      if(!is_null($this->callback_close)) $this->cbc();
      $this->xml->up();      
      break;
    case 51:
      if($this->modes[0]!=51) {//on starting node of mode 51 close normal!
	$this->xmlp_addtextdata(); 
	if(!is_null($this->callback_close)) $this->cbc();
	$this->xml->up();
	break;
      } // no break inthe else part!
    case 52:
      $this->xmltext .= '</' . $name . '>';
      break;
    }
    $this->mode = array_shift($this->modes);
    $this->level--;
  }
  
  function xmlp_cdata($parser, $data){
    switch($this->mode){
    case 0: case 2:
    case 51: case 52:
      $this->xmltext .= $data;
    }
  }

  // CALLBACK ============================================================
  function cbtd(&$txt){
    if(method_exists($this->xml,$this->callback_textdata)) {
      call_user_func_array(array($this->xml,$this->callback_textdata),array(&$txt,$this));
    } else if(function_exists($this->callback_textdata)) {
      call_user_func_array($this->callback_textdata,array(&$txt,$this));
    }
  }

  function cbo(&$tag,&$attr){
    if(is_array($this->callback_open)){ // use array to get mode!
      foreach($this->callback_open as $key=>$val){
	if(is_string($val) and $tag==$val) return($key);
	if(is_array($val)){
	  foreach($val as $sk=>$sv){
	    if(is_numeric($sk) and $tag==$sv) return($key);
	    if(is_bool($sv)){
	      if($sv===TRUE and   isset($attr[$ak])) return($key);
	      if($sv===FALSE and !isset($attr[$ak])) return($key);
	    }
	    if(!isset($attr[$sk])) continue;
	    if(is_array($sv) and in_array($attr[$sk],$sv)) return($key);
	    if($attr[$sk]==$sv) return($key);
	  }
	}
      }
      $nm = NULL;
    } else if(method_exists($this->xml,$this->callback_open)) { // call method
      $nm = call_user_func_array(array($this->xml,$this->callback_open),array(&$tag,&$attr,$this));
    } else if(function_exists($this->callback_open)) { // call function
      $nm = call_user_func_array($this->callback_open,array(&$tag,&$attr,$this));
    } else $nm = NULL;
    return(is_numeric($nm)?$nm:$this->mode);
  }

  function cbc(){
    if(method_exists($this->xml,$this->callback_close)) {
      call_user_func(array($this->xml,$this->callback_close),$this);
    } else if(function_exists($this->callback_close)) {
      call_user_func($this->callback_close,$this);
    }
  }


}
?>
<?php

  /*
   html using h? and not pure line-style
   more types like vector table and so on
   ersetzungen \t \N \n \r etc

   repalce method setting by a object or something similar

   
  */


include_once('ops_estring.php');
include_once('ops_arguments.php');

class opc_textData {
  
  var $data = array();
  var $info = array();
  var $_fn = NULL;

  var $_path = array();

  /* allows to use callback function
   cb_line is called on every line (after basic trasformation like trim and multiline)
   if the next line has a larger ident, cb_open is called reusing the parent-line
   if the next line has smaller ident, cb_close is used using the key of the parent line
   -> a line introducing a subsection (=parent) triggers 3 callbacks

   argumnts send to the cb (path is an array with all parent keys)
   open: arguments: key, value, path
   value is null if the starting line does not use the char_set
   close: arguments: key, data, path
   data: is an array including all data of this item
   line: arguments: key, value, path
   key is null if the current line does not use the char_set

   cb a re used in modes
   ident, section: all
   raw: line (path is allways empty)
   html_tag: close (path is allways empty)
   html_line: close, open (third argument is the current line, not the path!)
  */
  var $cb_open = NULL;
  var $cb_close = NULL;
  var $cb_line = NULL;

  protected $is_cb_open = FALSE;
  protected $is_cb_close = FALSE;
  protected $is_cb_line = FALSE;
  
  /* settings ============================================================
   Lines
   mode_trim
   0: no trim     1: rtrim     2: ltrim     3: both
   mode_mline
   0: no multi lines possible
   1: _ multline signature, optionaly followed by one non word character and whitespaces
   mode_mlc: how to connect between two multiline lines
   0: join           1: trim & join     
   2: space between  2: trim & space between
   3: \n between     3: trim & \n between

   mode_use:
   0: use all lines
   1: skip empty and pure whitespace lines
   Comments
   There are different styles of comments
   fullline: the complete line is a comment
   partline: the part behind a given sequence (including it) is a comment
   inline  : a part inside the line is a comment
   all three may be extended by a multline signature
   mode_rem:
   0: no comments possiböe
   1: hash  : starts a fullline comment. only valid if trailled only by whitespace characters
   double-hash : starts a partline comment
   2: html-comments <!-- ... -->
   3: C-Comments
   /.* ... *./ for inline / multiline comment
   // for part line comments
   
   */
  // read settings
  var $mode = 'raw';
  var $mode_use = 0;
  var $mode_mline = 0;
  var $mode_mlc = 0;
  var $mode_rem = 0;
  var $mode_trim = 0;

  /* html_* settings
   [all]
   html_nameattr: defines which attributes sets the name
   the result is allways a flat array (no nesting)
   html
   read tag-like (but not on a perfect way)
   html_in : definies the starting tag (will be part of the result)
   html_out: definies the end tag. Is not part of the result
   _in/_out should only appear at the start/end end of the item and not inside
   if html_out starts with a / the item will be closed.
   html_line (alias: html_snippet)
   reads line-like
   a new item starts on a new line, its content on the next
   html_tag defines the tag to identify the start and end
   the starting line has to start with <[html_tag] ....
   the closing line has to start with </[html_tag] ....
   both are not part of the result

  */
  var $html_in  = 'h1';
  var $html_out = 'hr';
  var $html_tag = 'n';
  var $html_nameattr = 'name';

  /* xml_* setting*/
  var $xml_mlng_order = array('de','en');



  // parse settings
  var $char_set = NULL;
  var $value_trim = 3; // used if char_set matches the line to trim the value alone

  //charset settings
  var $charset_in = 'ISO-8859-1';  // file
  var $charset_out = 'ISO-8859-1'; // result

  /* allows to define defaults and aliases
   defaults are handled in method close only
   aliases in lin2keyval only
  */
  var $def_data = NULL;
  var $ali_data = NULL;

  /* how tabs should count for ident
   >  1 : a tab counts like n spaces
   < -1 : a tab sets the ident to the next lower multiple of n
   [other]: a tab counts as one space*/
  var $tabsize = 1; 

  // patterns used
  var $pattern = array('name'=>'[_\w][-:._\w]*');

  // used if a parent line fits the char_set too; value moved to this key
  var $defkey = '.'; 

  protected $_data = array(); // temporary data, used during read process
  protected $_back = array(); // lines wrote back to item

  protected $_subs = array();  // some state-variables
  protected $_stack = array(); // for pseudo-recursive calls


  function init($ar){
    if(count($ar)==1 and isset($ar[0])) $ar = $ar[0];
    $def = array('set'=>array(),'filename'=>NULL,'mode'=>NULL,'method'=>NULL,'read'=>FALSE);
    $types = array('set'=>'array','read'=>'boolean','filename'=>'filename','mode'=>'string',
		   'method'=>'string',);
    $args = ops_arguments::setargs($ar,$def,$types); // $ar should now be empty
    if(!is_null($args['mode'])) $this->set_mode($args['mode']); // set standard mode first
    $this->setting($args['set'],FALSE); // adjust other settings
    if(!is_null($args['filename'])) $this->_fn = $args['filename'];
    if(!is_null($args['method'])) {
      $method = $args['method'];
      if(method_exists($this,$method)) $this->$method(); 
    }
    if($args['read']===TRUE) $this->file2array();
  }

  function __construct(/*  ... */){
    $ar = func_get_args();
    $this->init($ar);
  }





  function charset($in=NULL,$out=NULL){
    if(is_null($in) and is_null($out)) return;
    if(!is_null($in)) {
      $this->charset_in = $in;
      $this->charset_out = is_null($out)?$in:$out;
    } else $this->charset_out = $out;
  }

  function setting_keys(){
    return(array('mode',
		 'mode_trim','mode_mline','mode_mlc','mode_rem','mode_use',
		 'value_trim',
		 'char_set'));
  }
	   


  /* setting manager
   Allows static and non-static use
   allows stacked settings (hide/restore of settings)
   uses class-method setting_keys to get an array of the setting keys (array of strings)
   Arguments behaviour
   1)
   '+'                    shift all parameters to stack
   '-'                    restore all parameters (from stack)
   '--'                   reset all parameters to inital value, reset stack too
   TRUE                   returns complet setting list as named array
   FALSE                  returns keys of the setting parameters
   string                 returns the asked setting parameter as single value
   array(keys)            returns the asked setting parameters as array(keys=>values)
   array(keys=>values)    returns array (=user values) completed with current settings 
   2)
   string any                          sets the given element
   '-' string                          restore one value from stack
   '-' array(keys)                     restore multiple values from stack
   'hash' string                       returns current stack size for asked element
   '$' string                          returns stack for asked element
   array(keys=>values), array(keys)    returns array completed with asked settings 
   array(keys=>values), FALSE          sets multiple values
   array(keys=>values), TRUE           sets multiple values, stack is used
   3)
   string any FALSE    sets the given element (FALSE is optional)
   string any TRUE     sets the given element, stack is used
  */
  
  function setting($key=TRUE /* ... */){
    static $stack = array(); // saves temporary overdriven settings
    switch(func_num_args()){
    case 1:      
      if($key===FALSE){
	return($this->setting_keys());
      } else if($key==='--'){ // complete init/reset
	foreach($stack as $key=>$val) $this->$key = $val[0];
	$stack = array();
      } else if($key==='+'){ // save one level in stack
	foreach($this->setting_keys() as $ck) $stack[$ck][] = $this->$ck;
      } else if($key==='-'){ // restore one level from stack
	foreach(array_keys($stack) as $ck) $this->$ck = array_pop($stack[$ck]);
      } else if(is_string($key)){// get value
	return($this->$key); 
      } else {
	$ta = array_keys($key);
	if(is_array($key) and !is_numeric(array_shift($ta))){
	  return($this->setting($key,FALSE));
	} else {
	  if(!is_array($key)) $key = $this->setting_keys();
	  $res = array();
	  foreach($key as $ck) $res[$ck] = $this->$ck;
	  return($res);
	}
      }
      break;
    case 2:
      $arg2 = func_get_arg(1);
      if($key==='-'){ // restore from stack
	if(is_array($arg2)){ // multiple values
	  foreach($arg2 as $ck) if(is_array($stack[$ck])) $this->$ck = array_pop($stack[$ck]);
	} else { // one value
	  if(is_array($stack[$arg2])) $this->$arg2 = array_pop($stack[$arg2]);
	}
      } else if($key==='#'){ // return stack-size for asked element
			     return(is_array($stack[$arg2])?0:count($stack[$arg2]));
      } else if($key==='$'){ // return stack for asked element
	return($stack[$arg2]); 
      } else if(!is_array($key)) { // save arg2 to key
	return($this->$key = func_get_arg(1));
      } else if($arg2===FALSE){
	foreach($key as $ck=>$cv) $this->$ck = $cv;
      } else if($arg2===TRUE){
	foreach($key as $ck=>$cv){
	  $stack[$ck][] = $this->$ck;
	  $this->$ck = $cv;
	}
      } else if(is_array($arg2)){
	foreach($arg2 as $ck) if(!isset($key[$ck])) $key[$ck] = $this->$ck;
	return($key);
      }
      break;
    case 3:
      if(func_get_arg(2)==TRUE) $stack[$key][] = $this->$key;
      return($this->$key = func_get_arg(1));
    }
  }



  // get next just the next line (excluding newline) or next item from an array
  function _get_singleline(&$data){
    if(count($this->_back)>0) {
      return(array_shift($this->_back)); // no translation (already done!)
    } else if(is_array($data)){
      $res = array_shift($data);
    } else if(is_resource($data)){
      switch(get_resource_type($data)){
      case 'stream': 
	if(feof($data)) return(NULL);
	$res = preg_replace("|[\n\r]$|",'',fgets($data)); 
	break;
      default:
	return(NULL);
      }
    }
    return($this->trans_charset($res));
  }

  function trans_charset($data){
    if($this->charset_in==$this->charset_out) return($data);
    $case = $this->charset_in . ' > ' . $this->charset_out;
    switch($case){
    case 'UTF-8 > ISO-8859-1': return(utf8_decode($data));
    case 'ISO-8859-1 > UTF-8': return(utf8_encode($data));
    }
    return($data);
  }


  // allows to put lines 'back'. write them to _back and use them preffered
  function put_line_back($line){
    array_unshift($this->_back,$line);
  }
  
  // get next line; calls get_singlline, adjust_mode; minds mode_mline and mode_mlc
  function _get_nextline(&$data){
    $res = NULL;
    do{
      $line = $this->_get_singleline($data);
      if(is_null($line)) return($res);
      $this->adjust_mode($line);
      $mline = $this->is_mline($line);
      if($mline===FALSE) return(is_null($res)?$line:$this->mlc($res,$line));
      $res = $this->mlc($res,substr($line,0,$mline));
    } while(TRUE);
  }

  // get next line, minds: mode_rem, mode_mline, mode_mlc, mode_use
  function get_nextline(&$data){
    do {
      $nl = $this->_get_nextline($data);
      if(is_null($nl)) return(NULL);
      $rem_start = $this->comment_start($nl);
      if($rem_start!==FALSE){ // remove comment
	do {
	  $rem_end = $this->comment_end(substr($nl,$rem_start));
	  if($rem_end!==FALSE){
	    $nl = substr($nl,0,$rem_start) . substr($nl,$rem_start+$rem_end);
	    break;
	  } else {
	    if(is_null($sl = $this->_get_singleline($data))) break;
	    $nl .= $sl;
	  }
	} while(TRUE);
      }
      $nl = $this->trim($nl,NULL);
      switch($this->use_it($nl)){
      case 0: return($nl);
      case 1: break;
      case 2: $this->data[count($this->data)-1] .= $nl; break;
      case 3: $this->data[count($this->data)-1] .= "\n" . $nl; break;
      }
    } while(TRUE);
    return(NULL);
  }
  
  /* behaviour tests. To add more modes
   if this methods return a NULL it was called with a unknown mode_* so yout overloaded
   function should capture the situation */

  /* returns FALSE if no comment is present or the position of the first comment charatcer */
  function comment_start($line){
    switch($this->mode_rem){
    case 0: return(FALSE); // no comment possible
    case 1: return(preg_match('/^\s*#/',$line)?0:strpos($line,'##'));
    case 2: return(strpos($line,'<!--'));
    case 3:
      $pos = strpos($line,'/*');
      if(FALSE!==$pos){
	$this->_subs['crem'] = '/*';
      } else {
	$pos = strpos($line,'//');
	if($pos!==FALSE) $this->_subs['crem'] = '//'; else return(FALSE);
      }
      return($pos);
    }
    return(NULL);
  }

  /* returns FALSE if comment continious or the position of the last comment character */
  function comment_end($line){
    switch($this->mode_rem){
    case 0: return(0); // should never happen at all
    case 1: return($this->is_mline($line)?FALSE:strlen($line));
    case 2: 
      $pos = strpos($line,'-->');
      return($pos===FALSE?FALSE:$pos+3);
    case 3:
      switch($this->_subs['crem']){
      case '//': return($this->is_mline($line)?FALSE:strlen($line));
      case '/*':
	$pos = strpos($line,'*/');
	return($pos===FALSE?FALSE:$pos+2);
      }
    }
    return(NULL);
  }

  function trim($line,$mode=NULL){
    switch(is_null($mode)?$this->mode_trim:$mode){
    case 0: return($line);
    case 1: return(rtrim($line));
    case 2: return(ltrim($line));
    case 3: return(trim($line));
    }
    return(NULL);
  }

  function mlc($lineA,$lineB){
    if(is_null($lineA)) return($lineB); // old code remove indent: return(($this->mode_mlc%2)?$lineB:ltrim($lineB));
    switch($this->mode_mlc){
    case 0: return($lineA . $lineB);
    case 1: return(rtrim($lineA) . ltrim($lineB));
    case 2: return($lineA . ' ' . $lineB);
    case 3: return(rtrim($lineA) . ' ' . ltrim($lineB));
    case 4: return($lineA . "\n" . $lineB);
    case 5: return(rtrim($lineA) . "\n" . ltrim($lineB));
    }
    return(NULL);
  }

  /* returns FALSE if no multiline is present or the position of the first non (real) line character */
  function is_mline($line){
    switch($this->mode_mline){
    case 0: return(FALSE); // no multline possible
    case 1: 
      if(!preg_match('/_\W?\s*$/',$line)) return(FALSE);
      return(strlen(preg_replace('/_\W?\s*$/','',$line)));
    }
    return(NULL);
  }

  /* overload this function to switch between different mode's
   and to adjust the other   settings 
   sideeeffects to line are allowed
   returns NULL if current mode is not known (capture this by overloading)
   should (optional) return TRUE if somethings has changed FALSE if not 
  */
  function adjust_mode(&$line){
    switch($this->mode){
    case 'html_line': case 'html_snippet': return(FALSE);
    case 'html_tag': return(FALSE);
    case 'html_rem': return(FALSE);
    case 'xml_mlng': return(FALSE);
    case 'indent': return(FALSE);
    case 'section': return(FALSE);
    case 'raw': return(FALSE);
    }
    return(NULL);
  }

  /* decides how the current line is used
   0 -> use as normal
   1 -> use this line (read next one)
   2 -> add this line to the last (read next)
   3 -> add this line to the last separated by a \n (read next)
  */
  function use_it($line){
    switch($this->mode_use){
    case 1: return(strlen(trim($line))==0?1:0);
    }
    return(0);
  }

  /* parse line and save it to _data and/or change structer/stack etc*/
  function parse($line){
    $mth = 'parse_' . $this->mode;
    if(method_exists($this,$mth)){
      $line = $this->$mth($line);
      if($line==FALSE) return(FALSE);
    }

    list($key,$val) = $this->line2keyval($line);
    if(is_null($key)) $this->_data[] = $val;
    else $this->_data[$key] = $val;
    return($line);
  }

  // ================================================================================
  function parse_html_tag($line){
    if(strlen($line)==0) return(FALSE);
    $splitC = preg_split('|(<' . $this->html_out . '\W)|',$line,2,PREG_SPLIT_DELIM_CAPTURE);
    $splitO = preg_split('|(<' . $this->html_in . '\W)|',$line,2,PREG_SPLIT_DELIM_CAPTURE);
    $lc = strlen($splitC[0]);
    $lo = strlen($splitO[0]);
    if($lc==$lo){ // nothing found
      if(isset($this->_subs['h'])) $this->_subs['h'] .= $line;
      return(FALSE);
    } 
    $split = $lc<$lo?$splitC:$splitO;
    if(isset($this->_subs['h'])){ // add parts before to last element and close it
      $this->_subs['h'] .= $split[0];
      $this->html_tag_close();
    } 
    if($lo<$lc) $this->_subs['h'] = $split[1]; // start a new element
    $this->parse_html_tag($split[2]);
    return(FALSE); // parse rest of the line
  }
   
  function html_tag_close(){
    $val = $this->_subs['h'];
    if(substr($this->html_out,0,1)=='/') $val .= '</' . $this->html_out . '>';
    $key = preg_replace('|<[^<]* ' . $this->html_nameattr . '\s*=\s*["\']([- :.\w]+)["\'].*|','$1',$val);
    if($key==$val) $key = NULL;
    if($this->is_cb_close) 
      call_user_func_array($this->cb_close,array(&$key,&$val,array()));
    if(is_null($key)) $this->_data[] = $val;
    else $this->_data[$key] = $val;
    unset($this->_subs['h']);
  }


  // ================================================================================
  function parse_html_rem($line){
    if(strlen($line)==0) return(FALSE);
    $splitC = preg_split('|(<!-- ::)|',$line,2,PREG_SPLIT_DELIM_CAPTURE);
    $splitO = preg_split('|(<!-- :\w)|',$line,2,PREG_SPLIT_DELIM_CAPTURE);
    $lc = strlen($splitC[0]);
    $lo = strlen($splitO[0]);
    if($lc==$lo){ // nothing found
      if(isset($this->_subs['h'])) $this->_subs['h'] .= $line;
      return(FALSE);
    } 
    $split = $lc<$lo?$splitC:$splitO;
    if(isset($this->_subs['h'])){ // add parts before to last element and close it
      $this->_subs['h'] .= $split[0];
      $this->html_rem_close();
    } 
    if($lo<$lc) $this->_subs['h'] = $split[1]; // start a new element
    $this->parse_html_rem($split[2]);
    return(FALSE); // parse rest of the line
  }
   
  function html_rem_close(){
    $val = $this->_subs['h'];
    $key = preg_replace('|<!-- :([\w-_:]+).*|','$1',$val);
    if(substr($key,-2)=='--') $key = substr($key,0,-2);
    if($key==$val) $key = NULL;
    if($this->is_cb_close) 
      call_user_func_array($this->cb_close,array(&$key,&$val,array()));
    if(is_null($key)) $this->_data[] = $val;
    else $this->_data[$key] = $val;
    unset($this->_subs['h']);
  }


  // ================================================================================
  function parse_html_snippet($line) {return($this->parse_html_line($line));}

  function parse_html_line($line){
    if(preg_match('|^\s*<' . $this->html_tag . '\W|',$line)){// open a new entry
      if(isset($this->_subs['hse'])) $this->html_line_close('');
      $this->html_line_open($line);
    } else if(preg_match('|^\s*</' . $this->html_tag . '[> ]|',$line)){ // close current entry
      $this->html_line_close($line);
    } else if(isset($this->_subs['hse'])){
      $this->_subs['hse'] .= $line . "\n";
    } // no entry open -> reject data
    return(FALSE);
  }

  function html_line_open($line){
    $key = preg_replace('|^.*' . $this->html_nameattr . '\s*=\s*[\'"]([- :.\w]+)\W.*$|','$1',$line);
    if($key==$line) $key = NULL;
    $val = '';
    if($this->is_cb_open)
      call_user_func_array($this->cb_open,array(&$key,&$val,$line));
    $this->_subs['hsk'] = $key;
    $this->_subs['hse'] = $val;
  }

  function html_line_close($line){
    $key = $this->_subs['hsk'];
    $val = $this->_subs['hse'];
    if($this->is_cb_close) 
      call_user_func_array($this->cb_close,array(&$key,&$val,$line));
    if(is_null($key)) $this->_data[] = $val;
    else $this->_data[$key] = $val;
    unset($this->_subs['hse']);
    unset($this->_subs['hsk']);
  }


  // ================================================================================
  function parse_indent($line){
    $cin = $this->indent($line); // sideeffects to line!
    if(!isset($this->_subs['ind']) || !is_array($this->_subs['ind'])){
      $this->_subs['ind'] = array();
      $lin = $cin;
    } else $lin = array_shift($this->_subs['ind']);
    if($cin>$lin){
      if(is_null($this->char_set)) $lkey = NULL;
      else $lkey = ops_array::nth(array_keys($this->_data),-1);
      if(is_null($lkey) or is_int($lkey))
	$this->open(array_pop($this->_data),NULL);
      else
	$this->open($lkey,array_pop($this->_data));
      array_unshift($this->_subs['ind'],$lin);
    } else {
      while($cin<$lin){
	$lin = array_shift($this->_subs['ind']);
	$this->close(FALSE);
      }
    }
    array_unshift($this->_subs['ind'],$cin);
    return($line);
  }

  // ================================================================================
  function parse_section($line){
    if(preg_match('|^\s*\[|',$line)){
      $this->close(TRUE);
      $this->open(preg_replace('|^.*\[(.*)].*$|','$1',$line),NULL);
      return(FALSE); // structure only line
    } else return($line);    
  }









  function cast($value){
    if(preg_match('|^\s*[-+]?\d+\s*$|',$value))
      return((int)$value);
    if(is_numeric($value))
      return((float)$value);
    return($value);
  }

  function read_prepare($set){
    $this->setting('+');
    if(count($set)>0) $this->setting($set,FALSE);

    $this->is_cb_open = is_callable($this->cb_open);
    $this->is_cb_close = is_callable($this->cb_close);
    $this->is_cb_line = is_callable($this->cb_line);
  }

  function read_finish(){
    switch($this->mode){
    case 'indent': case 'section':
      if(count($this->_stack)>0) $this->close(TRUE);
      break;
    case 'html_tag': case 'html_rem':
      if(isset($this->_subs['h'])) $this->html_tag_close(); 
      break;
    case 'html_line': case 'html_snippet': 
      if(isset($this->_subs['hse'])) $this->html_line_close(''); 
      break;
    case 'raw':  // do nothing
      break;
    default:
      qq($this->mode,2);
    }
    $this->data = array_merge($this->data,$this->_data);
    $res = $this->_data;
    $this->_data = array();
    $this->setting('-');
    return($res);
  }

  protected function xml2array($filename,$set=array()){
    $xml = new opc_xml();
    $xml->phpcharset = $this->charset_out;
    $mth = '_' . $this->mode;
    if(method_exists($this,$mth)) 
      $this->data = array_merge($this->data,$this->$mth($xml,$filename));
    else trg_err(1,'unknown read mode: ' . $this->mode);
  }

  function _xml_mlng($xml,$filename){
    $xml->readmodes = array('|\d/msg#\d+/text$|'=>-3);
    $xml->read_file($filename);
    $res = array();
    
    foreach($xml->values_search('|/word@name$|',0,-1) as $key=>$val){
      $lngs = $xml->attrs_get('/^[a-z][a-z]$/',substr($key,0,-5));
      if(count($lngs)>0){
	$best = array_intersect($this->xml_mlng_order,array_keys($lngs));
	$res[$val] = count($best)>0?$lngs[array_shift($best)]:array_shift($lngs);
      } else $res[$val] = $val;
    }
    foreach($xml->values_search('|/msg@name$|',0,-1) as $key=>$val){
      $xml->spath(substr($key,0,-5));
      $lngs = $xml->values_search('|%C%P=text@lng$|',0,-1);
      if(count($lngs)>0){
	$best = array_intersect($this->xml_mlng_order,$lngs);
	foreach($this->xml_mlng_order as $clng) if(in_array($clng,$lngs)) break;
	$ck = array_search($clng,$lngs);
	if($ck===FALSE)  list($ck,$dummy) = each($lngs);
	$xml->spath(substr($ck,0,-4));
	$res[$val] = $xml->value_get(0);
      } else $res[$val] = $val;
    }
    return $res;
  }


  /* reads msg-tags on first level
   *  if att file is used and points to an existing file -> use this content
   *  if att text is used us this content
   *  read xhtml-content else
   *  name-attr is used as identifier
   */
  function _xml_flat($xml,$filename){
    $xml->readmodes = array('|\d/text$|'=>-3);
    $xml->read_file($filename);
    $res = array();
    foreach($xml->keys_search('|%H%P=text$|') as $key){
      $att = $xml->attrs_get(NULL,$key);
      $this->info[$att['name']] = $att;
      if(isset($att['file'])){
	$fn = str_replace(array('%key%'),
			  $att['name'],
			  $att['file']);
	if(file_exists($fn = dirname($this->_fn) . '/' . $fn)){
	  $res[$att['name']] = file_get_contents($fn);
	  continue;
	} else $res[$att['name']] = 'unkown file: ' . $fn;
      } else if(isset($att['text'])) {
	$res[$att['name']] = $att['text'];
      } else  $res[$att['name']] = $xml->xhtml_get($key);
    }
    return $res;
  }

  function _xml_nested($xml,$filename){
    $xml->readmodes = array('|\d/msg$|'=>-3);
    $xml->read_file($filename);
    $settings = $xml->attrs_get();
    $sep = def($settings,'sep','_');
    
    $res = array();
    foreach($xml->values_search('|/msg@name$|',0,-1) as $path=>$key){
      $val = $xml->value_get(substr($path,0,-5) . '#0');
      $path = array_slice(explode('#',$path),2,-1);
      while(count($path)>0){
	$tmp = '/xml#0/messages#' . implode('#',$path);
	$key = $xml->attr_get('name',$tmp) . $sep . $key;
	array_pop($path);
      }
      $res[$key] = $val;
    }
    return $res;
  }

  function file2array($filename=NULL,$set=array()){
    if(is_null($filename)) $filename = $this->_fn;
    $this->_fn = $filename;
    if(substr($this->mode,0,4)=='xml_') return $this->xml2array($filename,$set);
    if(!file_exists($filename)){qk();
      trigger_error("unknown file: $filename");
      return(NULL);
    }
    
    $this->read_prepare($set);
    $data = fopen($filename,'r');
    $this->_data = array();
    do {
      $cl = $this->get_nextline($data);
      if(is_null($cl)) break; 
      else $this->parse($cl);
    } while(TRUE);
    fclose($data);
    return($this->read_finish());
  }

  function t($key,$default=NULL){
    return(isset($this->data[$key])?$this->data[$key]:$default);
  }

  // t-nested
  function tn(/* ... */){
    $ar = func_get_args();
    return(ops_narray::get($this->data,$ar));
  }

  // t-extended syntax: [[...]] structers
  function te($id){
    return $this->extsynt($this->t($id));
  }

  function extsynt($txt){
    $res = preg_split('{(\[\[|\]\])}',$txt,-1,PREG_SPLIT_DELIM_CAPTURE);
    while($tmp = array_search(']]',$res)){
      if($res[$tmp-2]!=='[[') return 'SHIT in textData show';
      array_splice($res,$tmp-2,3,array($this->extsynt_one($res[$tmp-1])));
    }
    return implode('',$res);
  }

  function extsynt_one($txt){
    list($key,$arg) = explode_n(':',$txt,2);
    switch($key){
    case 't': return $this->t($arg);
    }
    return '{' . $txt . '}?';
  }

  // returns indent, ltrim line
  function indent(&$line){
    if($this->tabsize>1){
      $nlin = ltrim($line);
      $res = strlen(str_replace("\t",
				str_repeat(' ',$this->tabsize),
				substr($line,0,strlen($line)-strlen($nlin))));
      $line = $nlin;
      return($res);
    } else if($this->tabsize < -1){
      $ind = 0; 
      while(strlen($line)>0){
	switch(ord($line)){
	case 32: $ind++; break;
	case 9: $ind = floor(($ind+1)/$this->tabsize)*$this->tabsize; break;
	default: return($ind);
	}
	$line = substr($line,1);
      }
    } else {
      $olen = strlen($line);
      $line = ltrim($line);
      return($olen-strlen($line));
    }
  }

  
  /* tryies to interprete a line as name = value or similar
   return value is an array(name=>value) or the original line */
  function line2keyval($line){
    if(!empty($this->char_set)) {
      $pat = '/^\s*(' . $this->pattern['name'] . ')\s*' . $this->char_set . '(.*)$/';
      if(preg_match($pat,$line,$match)>0){
	$key = $match[1];
	$line = $this->trim($this->cast($match[2]),$this->value_trim);
      } else $key = NULL;
    } else $key = NULL;
    if(is_array($this->ali_data) and strpos($line,'%:')!==FALSE){
      foreach($this->ali_data as $ck=>$cv)
	$line = str_replace('%:' . $ck . ':%',$cv,$line);
    }
    if($this->is_cb_line)
      call_user_func_array($this->cb_line,array(&$key,&$line,$this->_path));
    return(array($key,$line));
  }

  function open($key,$value){
    $this->_path[] = $key;
    if($this->is_cb_open) 
      call_user_func_array($this->cb_open,array(&$key,&$value,$this->_path));
    array_unshift($this->_stack,$key,$this->_data);
    $this->_data = is_null($value)?array():array($this->defkey=>$value);
  }

  function close($all=FALSE){
    while(count($this->_stack)>0){
      list($key,$data) = array_splice($this->_stack,0,2);
      array_pop($this->_path);
      if(count($this->_path)==0 and $key===$this->def_data){
	$this->def_data = array();
	foreach($this->_data as $key=>$val){
	  $key = str_replace('%N',$this->pattern['name'],preg_quote($key,'/'));
	  $this->def_data['/^' . $key . '$/'] = $val;
	}
      } else if(count($this->_path)==0 and $key===$this->ali_data){
	$this->ali_data = $this->_data;
      } else {
	if(is_array($this->def_data) and 5<9){
	  $cpath = implode('/',$this->_path);
	  foreach($this->def_data as $ckey=>$val){
	    if(preg_match($ckey,$cpath)){
	      foreach($val as $ck=>$cv){
		if(!isset($this->_data[$ck])) $this->_data[$ck] = $cv;
	      }
	    }
	  }
	}
	if($this->is_cb_close) 
	  call_user_func_array($this->cb_close,array(&$key,&$this->_data,$this->_path));
	if(is_null($key)) $data[] = $this->_data; 
	else $data[$key] = $this->_data;
      }
      $this->_data = $data;
      if($all==FALSE) return;
    }
  }

  function reset(){
    $this->data = array();
    $this->_data = array();
    $this->_subs = array();
    $this->_stack = array();
  }

  function __get($key){
    return(isset($this->data[$key])?$this->data[$key]:$key);
  }


  function set_mode($mode){
    $this->setting(array('mode_trim'=>0,
			 'mode_mline'=>0,
			 'mode_mlc'=>0,
			 'mode_use'=>0,
			 'mode_rem'=>0,
			 'value_trim'=>3));
    switch($mode){
    case 'html_tag': case 'html_line': case 'html_snippet':
      $this->setting(array('mode'=>$mode,
			   'mode_rem'=>2));				 
      break;

    case 'html_rem':
      $this->setting(array('mode'=>$mode,
			   'mode_rem'=>0));				 
      break;

    case 'raw': 
      $this->setting(array('mode'=>$mode));
      break;

    case 'xml_mlng': case 'xml_nested':
      $this->setting(array('mode'=>$mode));
      break;

    case 'section':
      $this->setting(array('mode'=>$mode,
			   'mode_mline'=>1,
			   'mode_trim'=>2,
			   'mode_use'=>1,
			   'mode_rem'=>1,
			   'char_set'=>'='));
      break;
    case 'indent':
      $this->setting(array('mode'=>$mode,
			   'mode_mline'=>1,
			   'mode_trim'=>1,
			   'mode_use'=>1,
			   'mode_rem'=>1));
      break;
    }
    $this->mode = $mode;
    return(NULL);
  }
  
  function info($key,$what='label',$def=NULL){
    return def(def($this->info,$key,array()),$what,$def);
  }

  function get_keys(){   return array_keys($this->data);}
  function exists($key){ return isset($this->data[$key]);}


}
?>
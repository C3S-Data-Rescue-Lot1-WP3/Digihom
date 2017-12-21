<?php

  /** 
   * idea: 
   *   escape depending on mode`?
   *   how to handle attributes in a xml-string (open-delim has a nested contend)
   */

class opc_strparser {
  protected $input = NULL;

  protected $result = array();
  protected $adds = array();
  
  /** current mode (0 or a key from delim) */
  protected $mode = NULL;
  /** current position in current before the actual delim */
  protected $posA = 0;
  /** current position in current after the actual delim */
  protected $posB = 0;
  /** current string which will be deparsed */
  protected $current = NULL;


  /** internal */
  protected $stack = array();

  /** remove escapes in the result */
  public $keepesc = FALSE;

  /** defines the delimite: array(key=>array(open-delim,close-delim),...)
   * the close-delim may contain a '$1' which will be repalced by the currently used open-delim
   *  useful for typically quoting with " or '
   */
  protected $delim = array();

  /** defines the occurence: array(current-key=>array(potential sub-key,...)
   * at least a item with key 0 (=global) has to be defined
   */
  protected $occur = array();

  /** internal: named array of patterns that may occur (=close of current mode, open of subs) */
  protected $dlist = array();
  /** internal: pattern version of dlist */
  protected $dpat = array();

  /** a set of predefinied delim/occur settings */
  protected $lib = array('quoted'=>array('delim'=>array('txt'=>array('["\']','$1')),
					 'occur'=>array(0=>array('txt'),'txt'=>array())),
			 'oew'=>array('delim'=>array('ocps'=>array('\[\[[\w_-]+:','\]\]')),
				      'occur'=>array(0=>array('ocps'),'ocps'=>array('ocps'))),
			 );
					      
					      

  public $err = NULL;
  
  /**   function __get
   * @access private
   * @param $key name of the asked variable
   * @return aksed value or error 103 is triggered
   */
  function __get($key){
    $tmp = NULL;
    if($this->___get($key,$tmp)) return $this->err->ok($tmp);
    return $this->err->err(103);    
  }
  /** magic variables (ro) 
   * level: level of nesting
   * head/trailer string before/after the current delimite
   */


  /** subfunction of magic method __get to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return bool: returns TRUE if key was accepted (result is saved in $res) otherwise FALSE
   */
  protected function ___get($key,&$res){
    switch($key){
    case 'level': $res = count($this->stack); return TRUE;
    case 'head': $res = substr($this->current,0,$this->posA); return TRUE;
    case 'trailer': $res = substr($this->current,$this->posB); return TRUE;
    case 'delim':  case 'occur': case 'result': case 'mode':
      $res = $this->$key; return TRUE;
    }
    return FALSE;
  }

  /**   function __set
   * @access private
   * @param $key name of the asked variable
   * @param mixed $value new value
   * @return aksed value or error 103 is triggered
   */
  function __set($key,$value){
    $tmp = NULL;
    $tmp = $this->___set($key,$value);
    if($tmp>0) return $this->err->err($tmp);
    return $this->err->ok();
  }

  /** subfunction of magic method __set to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return bool: returns TRUE if key was accepted (result is saved in $res) otherwise FALSE
   */
  protected function ___set($key,$value){
    switch($key){
    case 'input': 
      if(!is_string($key)) return 10;
      $this->input = $value;
      return 0;
    }
    return 0;
  }

  public function __construct(){
    $this->err = new opc_status($this->_msgs());
  }

  protected function _msgs(){
    return array(0=>array('ok','ok'),
		 10=>array('input is not a string','warning'),
		 11=>array('unkown library content','warning'),
		 12=>array('invalid delim setting','warning'),
		 13=>array('invalid occur setting','warning'),
		 14=>array('input is not properly closed','warning'),
		 103=>array('access denied','int'),
		 );
  }

  function load($key){
    if(!isset($this->lib[$key])) return $this->err->err(11);
    $this->delim = $this->lib[$key]['delim'];
    $this->occur = $this->lib[$key]['occur'];
    return $this->err->ok();
  }

  function set_delim($delim,$key=NULL){
    if(is_null($key)){
      if(!is_array($delim)) return $this->err->errC(12);
      $this->delim = array();
      foreach($delim as $ck=>$cv) if($this->set_delim($cv,$ck)>0) return $this->err->errC(12);
      return $this->err->okC();
    } else {
      if(!is_string($key))         return $this->err->errC(12);
      else if(is_string($delim))   $this->delim[$key] = array($delim,$delim);
      else if(!is_array($delim))   return $this->err->errC(12);
      else if(count($delim)==1)    $this->delim[$key] = array($delim[0],$delim[0]);
      else if(count($delim)==2)    $this->delim[$key] = array($delim[0],$delim[1]);
      else                         return $this->err->errC(12);
      return $this->err->okC();
    }
  }

  function set_occur($occur,$key=NULL){
    if(is_null($key)){
      if(!is_array($occur)) return $this->err->errC(13);
      if(!isset($occur[0])) return $this->err->errC(13);
      $this->occur = array();
      foreach($occur as $ck=>$cv) if($this->set_occur($cv,$ck)>0) return $this->err->errC(13);
      return $this->err->okT();
    } else {
      if($key!==0 and !isset($this->delim[$key])) return $this->err->errC(13);
      if(!is_array($occur)) return $this->err->errC(13);
      if(count(array_diff($occur,array_keys($this->delim)))>0) return $this->err->errC(13);
      $this->occur[$key] = $occur;      
      return $this->err->okC();
    }
  }

  /** 
   * @return a double nested array
   */
  function deparse($txt=NULL){
    if(is_null($txt)){
      if(!is_string($this->input)) return $this->err->err(10);
      $txt = $this->input;
    } else if(!is_string($txt)) return $this->err->err(10);

    // init
    $this->posA = 0;
    $this->posB = 0;
    $this->level = 0;
    $this->pos = 0;
    $this->mode = 0;
    $this->add = array();
    $this->result = array();
    $this->current = $txt;

    // preparation
    $this->dlist = array(0=>array());
    foreach(array_keys($this->delim) as $ck) $this->dlist[$ck] = array(0=>$this->delim[$ck][1]);
    foreach($this->occur as $ckey=>$subs) foreach($subs as $cs) $this->dlist[$ckey][$cs] = $this->delim[$cs][0];
    $this->dpat = array_map(create_function('$x','return "#(\\\\\|" . implode("|",$x) . ")#";'),$this->dlist);


    $cpat = $this->dpat[$this->mode];

    while(strlen($txt)>0){
      $cpart = preg_split($cpat,$txt,2,PREG_SPLIT_DELIM_CAPTURE);
      if(count($cpart)==1) break;

      if($cpart[1]=='\\') {// escaped sequqnce --------------------------------------------------
	$this->result[] = $cpart[0] . ($this->keepesc?'\\':'') . substr($cpart[2],0,1);
	$this->posA += strlen($cpart[0])+2;
	$this->posB = $this->posA;
	$txt = substr($cpart[2],1);

      } else { // delimiter sequnce --------------------------------------------------

	$this->result[] = $cpart[0];
	$this->posA = $this->posB + strlen($cpart[0]);
	$this->posB = $this->posA + strlen($cpart[1]);
	$delim = $cpart[1];
	$txt = $cpart[2];

	$nmode = $this->identify_mode($delim);

	if($nmode!==0){
	  $this->open_stack($nmode,$cpat);
	  $this->open(&$delim);
	  $cpat = str_replace('$1',$delim,$this->dpat[$this->mode]);
	} else{
	  $this->close($delim);
	  $cpat = $this->close_stack();
	}
      }
    }
    $this->result[] = $txt;

    if(count($this->stack)>0){
      while(count($this->stack)>0) $this->close_stack();
      $this->finishing();
      return $this->err->errC(14);
    }
    $this->finishing();
    return $this->result;

  }

  /** identifies the new mode */
  protected function identify_mode($delim){
    foreach($this->dlist[$this->mode] as $ck=>$cv) if(preg_match('#^' . $cv . '$#',$delim)) return $ck;  
    return 0;
  }

  protected function open_stack($nmode,$cpat){
    $this->stack[] = array($this->mode,$this->result,$cpat,$this->adds);
    $this->mode = $nmode;
    $this->result = array();
    $this->adds = array();
  }

  protected function close_stack(){
    list($nmode,$nres,$npat,$nadd) = array_pop($this->stack);
    $nres[] = array('type'=>$this->mode,'add'=>$this->adds,'content'=>$this->result);
    $this->adds = $nadd;
    $this->mode = $nmode;
    $this->result = $this->finishing($nres);
    return $npat;
  }

  /** finishing of a (sub)-result */
  function finishing($result){
    $res = array();
    $cpos = -1;
    foreach($result as $cres){
      if(is_array($cres)){
      }
    }
    return $result;
  }



  /** overload this to react on open-events */
  function open($delim){
    switch($this->mode){
    case 'ocps':
      $this->adds['item'] = preg_replace('#[^-\w]#','',$delim);
      break;
    }
  }

  /** overload this to react on close-events */
  function close($delim){
  }

  /** allows a static call, delim may also be a key from lib */
  static function direct($txt,$delim,$occur=NULL){
    $tmp = new opc_strparser();
    if(is_array($delim)){
      $tmp->set_delim($delim);
      $tmp->set_occur($occur);
    } else $tmp->laod($delim); 
    return $tmp->deparse($txt);
  }
  }

?>
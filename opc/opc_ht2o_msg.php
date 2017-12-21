<?php

  /*

  */

class opc_ht2o_msg extends opc_ht2o implements countable{

  /* array of all messages 
   * used keys
   *  code: numeric/text identifier (to search in a msg-code table, lng independent)
   *  msg: Textual message
   *  msg-dev: extended message for devel mode
   *  level: see instance variable levels
   *  situation: in the sense of an tiltle (saving values, claculating ...) -> groups messages
   *  code-line (calculated if not given): for the devloper
   */
  public $msgs = array();

  public $levels = array('crit'=>array('title'=>'Critical Errors','class'=>'crit'),
			 'err'=>array('title'=>'Errors','class'=>'err'),
			 'warn'=>array('title'=>'Warnings','class'=>'warn'),
			 'conf'=>array('title'=>'Confirmations','class'=>'conf'),
			 'hint'=>array('title'=>'Hints','class'=>'hint'),
			 'trace'=>array('title'=>'Tracers','class'=>'trace'),
			 'tudu'=>array('title'=>'Tudu','class'=>'hint'),
			 );
				       
  public $dbt_ignore = array('function'=>array(),
			     'file'=>array(__FILE__),
			     'class'=>array(__CLASS__));


  /* style of output
   * available: ul
   * needs method output__[style] and output__one__[style]
   */
  public $style = 'sl';
  
  /* Which elemets are visible
   * available: vis__cursel (all selected and the current one and their parents
   *            vis__all (all items, regarding depth!)
   */
  public $filter = 'vis__cursel'; 

  public $verbose = 1;

  /* How is the list sorted
   * avaialble: sort__not, sort__pos or a callable function
   */
  public $group = 'sl';

  /* basic class */
  public $class = 'msg_%style%';

  /* includes who sees what! */
  function count(){ return count($this->collect()); }

  function ___set($key,$val) { 
    switch($key){
    case 'style': $this->set_style($val); return 0;
    }
    return parent::___set($key,$val);
  }

  /* args 2+ are the same as msg_add, the first defines the return value */
  function msg_ret($ret/* msg_add */){
    $ar = func_get_args();
    $ret = array_shift($ar);
    call_user_func_array(array($this,'msg_add'),$ar);
    return $ret;
  }

  /* add a new item to the sites array
   * each argument may be the value itself or an named array
   */
  function msg_add($situation,$level=NULL,$msg=NULL,$code=NULL){
    $ar = array('situation','level','msg','code');
    $set = array('count'=>1);
    foreach($ar as $ck) if(is_array($$ck)) $set = array_merge($set,$$ck);
    foreach($ar as $ck) if(!is_array($$ck) and !is_null($$ck)) $set[$ck] = $$ck;

    if(!isset($set['time'])) $set['time'] = microtime(TRUE);
    $line = $this->dbt('fileline');
    if(is_int(def($set,'line')) 
       or (is_object(def($GLOBALS,'_tool_')) and $GLOBALS['_tool_']->mode==='devel')){
      $set['devel'] = $this->dbt('fileline');
    }
    if(!isset($set['msg'])) $set['msg'] = def($set,'devel','unkown message');
    $key = $set['level'] . ':' . $line . ':' .  $set['msg'];
    if(isset($this->msgs[$key])) $this->msgs[$key]['count']++;
    else $this->msgs[$key] = $set;
    return 0;
  }
  
  /* 
   * dbt_ignore saves 'lines' which will be ignored/skipped
   *  where file would took the frist line with none of the saved values
   *  and class/function took the last line with one of this values
   * which
   *  0: first occurence
   *  >0: skip n further lines (still regarding dbt_ignore)
   *  <0: collect n lines  (still regarding dbt_ignore)
   */
  function dbt($what='fileline',$which=0){
    $dbt = debug_backtrace();
    $dbt[] = array(); // necessary since some ignore rules should return the line before
    $res = array();
    $keys = array_keys($this->dbt_ignore);
    $line = array();
    while(count($dbt)>0){
      $lline = $line;
      $line = array_shift($dbt);
      //qq($this->dbt_show($line,'full'));
      
      $tres = $line;
      if(in_array(def($line,'file','-'),    $this->dbt_ignore['file']))     continue;
      $tres = $lline;
      if(in_array(def($line,'class','-'),   $this->dbt_ignore['class']))    continue;
      if(in_array(def($line,'function','-'),$this->dbt_ignore['function'])) continue;

      $tmp = $this->dbt_show($tres,$what);
      if($tmp===FALSE)      continue;
      else if($which==0)    return $tmp;
      else if($which>0)   { $which--; continue;}
      else if($which==-1) { $res[] = $tmp;  return $res;}
      else                { $res[] = $tmp; $which++; }
    }
    return $res;
  }

  function dbt_show($line,$what){
    //if($what!='full') qq($this->dbt_show($line,'full'));
    switch($what){
    case 'full':
      return def($line,'class','C') . '->' . def($line,'function','f') . ' ---- '
	. def($line,'file','F') . '@' . def($line,'line','L');
    
    case 'fileline': 
      if(!isset($line['line'])) return FALSE;
      return def($line,'file','?') . '@' . $line['line'];
    }
    return 'unkown task: ' . $what;
  }

  function getById($id){
    foreach($this->msgs as $cmsg) if(def($cmsg,'id')===$id) return $cmsg;
  }

  function getByCase($case){
    $res = array();
    foreach($this->msgs as $key=>$cmsg){
      if(!isset($cmsg['case'])) continue;
      else if(is_string($cmsg['case']) and $cmsg['case']!=$case) continue;
      else if(is_array($cmsg['case']) and !in_array($case,$cmsg['case'])) continue;
      $res[$key] = $cmsg;
    }
    return $res;
  }

  /* creates a whole bunch of classes for detailed layouting with css 
   * should be used in this class instead of 'make_class'
   */
  function msg_make_class($list=array(),$ele=array()){
    if(is_array($list)){
      $res = $this->make_class($list,$this->_make_class());
    } else {
      switch($list){
      case 'lev':
	$cls = preg_replace('/(_?%[^%]*%)/','',$this->class);
	$res = $cls . '-level ' . $cls . '-level-' . $ele;
	break;
      }
    }
    if(isset($ele['class'])) $res .= ' ' . $ele['class'];
    return $res;
  }

  /* repalces a %style% in class by the current style -> better class-name for css */
  function _make_class(){
    return str_replace('%style%',$this->style,$this->class);
  }

  /* ================================================================================
   preparation before output
   ================================================================================ */

  /* call prep-details */
  function collect(){
    $res = array();
    foreach($this->levels as $key=>$val){
      $tmp = array_filter($this->msgs,create_function('$x','return $x["level"]==\'' . $key . '\';'));
      $res = array_merge($res,$tmp);
    }
    return $res;
  }

  
  /* ================================================================================
   output generation
   ================================================================================ */

  /*   calls root__[style] */
  function _output(&$ht){
    $this->pointers = array();
    $msgs = $this->collect();
    switch(count($msgs)){
    case 0: return FALSE;
    case 1:
      $mth = 'output_one__' . $this->style;
      return $this->$mth($ht,array_shift($msgs));
    }
    $mth = 'output__' . $this->style;
    return $this->$mth($ht,$msgs);
  }
  

  /* --------------------------------------------------------------------------------
     situation - level; using div and ul
   -------------------------------------------------------------------------------- */
  function output_one__sl(&$ht,$msg){
    $cl = $msg['level'];
    $lev = def($this->levels,$cl,array());
    $ht->open('div',$this->msg_make_class(array('single')));
    $ht->span($msg['situation'] . ' | ' . def($lev,'title',$cl) . ':',$this->msg_make_class('lev',def($lev,'class',$cl)));
    $this->msg($ht,$msg);
    $ht->close();
  }
  function output__sl(&$ht,$msgs){
    $ht->open('div',$this->msg_make_class(array('whole')));
    $sit = array_unique(array_map(create_function('$x','return $x["situation"];'),$msgs));
    foreach($sit as $cs){
      $ht->div($cs,$this->msg_make_class(array('situation')));
      $m1 = array_filter($msgs,create_function('$x','return $x["situation"]==\'' . $cs . '\';'));
      $lev = array_unique(array_map(create_function('$x','return $x["level"];'),$m1));
      foreach($lev as $cl){
	$lev = def($this->levels,$cl,array());
	$m2 = array_filter($m1,create_function('$x','return $x["level"]==\'' . $cl . '\';'));
	$ht->div(def($lev,'title',$cl),$this->msg_make_class('lev',def($lev,'class',$cl)));
	$ht->open('ul',$this->msg_make_class(array('msg')));
	foreach($m2 as $cm){
	  $ht->open('li',$this->msg_make_class(array('msg')));
	  $this->msg($ht,$cm);
	  $ht->close(); // li
	}
	$ht->close(); // ul
      }
    }
    $ht->close(); // div
  }

  /* --------------------------------------------------------------------------------
     level - situation; using div and ul
   -------------------------------------------------------------------------------- */
  function output_one__ls(&$ht,$msg){
    $cl = $msg['level'];
    $lev = def($this->levels,$cl,array());
    $ht->open('div',$this->msg_make_class(array('single')));
    $ht->span(def($lev,'title',$cl) . ' | ' . $msg['situation'] . ':',$this->msg_make_class('lev',def($lev,'class',$cl)));
    $this->msg($ht,$msg);
    $ht->close();

  }

  function output__ls(&$ht,$msgs){
    $ht->open('div',$this->msg_make_class(array('whole')));
    $lev = array_unique(array_map(create_function('$x','return $x["level"];'),$msgs));
    foreach($lev as $cl){
      $lev = def($this->levels,$cl,array());
      $ht->div(def($lev,'title',$cl),$this->msg_make_class('lev',def($lev,'class',$cl)));
      $m1 = array_filter($msgs,create_function('$x','return $x["level"]==\'' . $cl . '\';'));
      $sit = array_unique(array_map(create_function('$x','return $x["situation"];'),$m1));
      foreach($sit as $cs){
	$m2 = array_filter($m1,create_function('$x','return $x["situation"]==\'' . $cs . '\';'));
	$ht->div($cs,$this->msg_make_class(array('situation')));
	$ht->open('ul',$this->msg_make_class(array('msg')));
	foreach($m2 as $cm){
	  $ht->open('li',$this->msg_make_class(array('msg')));
	  $this->msg($ht,$cm);
	  $ht->close(); // li
	}
	$ht->close(); // ul
      }
    }
    $ht->close(); // div
  }


  function msg(&$ht,$msg){
    $txt = $msg['msg'];
    if($msg['count']>1) $txt .= ' (' . $msg['count'] . 'x)';
    $ht->add($txt);
    if(isset($msg['devel'])) {
      $ht->span('&otimes;',array('title'=>$msg['devel'],
				 'style'=>'color: blue; background-color: #dff;'));
    }
  }

  function set_style($style){
    $this->style = $style;
  }

  function msg_get($add=array()){
    $res = $this->msgs;
    if(isset($add['code'])) $res = $this->msg_filter($res,'code',$add['code']);
    return $res;
  }

  protected function msg_filter($arr,$field,$filter){
    if(in_array(substr($filter,0,1),array('/','|','{','$'))){
      return array_filter($arr,create_function('$x',"return preg_match('$filter',\$x['$field']);"));
    } else {
      return array_filter($arr,create_function('$x',"return def(\$x,'$field','-')==='$filter';"));
    }
  }

  }

?>
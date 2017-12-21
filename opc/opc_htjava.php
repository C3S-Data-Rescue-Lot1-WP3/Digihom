<?php


class opc_htjava {

  var $collect = NULL;
  var $files = array();

  var $body_elements = array();

  function opc_htjava($mode=1){
    switch($mode){
    case 1:
      $this->collect('jlib_A.js','oj_XHRequest'); 
      $this->collect('jlib_A.js','oj_nonexitst'); 
      break;
    }
    
  }

  function files(){
    $res = array();
    foreach($this->files as $fn)
      $res[] = '<script type="text/javascript" src="' . $fn  . '"></script>';
    return("\n" . implode("\n",$res) . "\n");
  }
  
  function collect($file,$block=NULL){
    $res = $this->load($file,$block);
    if(!empty($res)) $this->collect .= "\n\n" . $res;
  }

  function collected($mode=0,$reset=TRUE){
    switch($mode){
    case 0:
      $res = "\n<script type='text/javascript'>\n<!--\n\n"
	. $this->collect . "\n//-->\n</script>\n"
	. $this->files() . "\n\n";
    }
    if($reset) {
      $this->collect = NULL;
      $this->files = array();
    }
    return($res);
  }

  function noscript($with,$without,$sec=5){
    $res = '<meta http-equiv="refresh" content="' . $sec. ';URL='
      . $without . '">'
      . "\n\n<script type='text/javascript'>\n<!--\n"
      .  " location.href=\"$with\";\n//-->\n</script>\n";
    $this->body_elements['refresh'] = '<p>Please go to <a href="' . $with
      . '">' . $with . '</a> which does not require JavaScript</p>';
    return($res); 
  }

  function load($file,$block=NULL){
    if(!file_exists($file)) return(NULL);
    $cont = file($file);
    if(is_null($block)) return(implode('',$cont));
    $sfct = preg_grep('/^[ \t]*function +/i',$cont);
    $lfct = array_keys($sfct);
    $tfct = preg_grep('/ ' . $block . '\(/i',array_values($sfct));
    if(count($tfct)==1){
      $tlin = array_shift(array_keys($tfct));
      $cont = array_slice($cont,$lfct[$tlin],$lfct[$tlin+1]-$lfct[$tlin]);
      return(implode('',$cont));
    }
    return(NULL);
  }


  function inline($code,$usediv=FALSE){
    $res = "<script type='text/javascript'>\n<!--\n$code\n//--></script>";
    if($usediv) $res = "\n<div style='display:none;'>$res</div>\n";
    return($res);
  }

  function gf_setfocus($name,$fctname='setfocus'){
    if(is_array($name)) $name = implode('.',$name);
    $this->collect .= "\n\nfunction $fctname(){ document.$name.focus();}";
  }
  
}

?>
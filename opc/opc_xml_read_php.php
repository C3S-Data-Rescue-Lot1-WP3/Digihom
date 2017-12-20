<?php

class opc_xml_read_php extends opc_xml_read{

  var $callback_open = FALSE;
  var $callback_close = FALSE;
  var $callback_textdata = FALSE;

  var $colors = array();
/*
highlight.comment	#FF8000	#FF8000
highlight.default	#0000BB	#0000BB
highlight.html	#000000	#000000
highlight.keyword	#007700	#007700
highlight.string	#DD0000	#DD0000
*/
  function read($data){
    $ar = array('bg','comment','default','html','keyword','string');
    foreach($ar as $ck)
      $this->colors[ini_get('highlight.' . $ck)] = $ck;
    if(strpos($data,"<?")!==FALSE){
      ob_start();
      highlight_string($data);
      $res = ob_get_contents();
    } else $res = highlight_file($data,TRUE);
    $res = str_replace('&nbsp;',' ',$res);
    $res = parent::read("<?xml version='1.0' encoding='$this->xmlcharset'?>\n$res");
    return($res);
  }

  function cbo(&$tag,&$attr){
    if($tag=='br')  return(0);
    if(isset($this->colors[$attr['color']])) $tag = $this->colors[$attr['color']];
    $attr = array();
    switch($tag){
    case 'keyword':
    case 'default':
    case 'comment': 
      $res = 51;
      break;
    default:
      $res = 0;
    }
    return($res);
  }

  function cbtd(&$txt){
  }

  function cbc(){
  }

  function xmlp_addtextdata(){
    switch($this->xml->name()){
    case 'keyword':
    case 'default':
    case 'comment':
      $this->xmltext = str_replace('<br></br>',"\n",$this->xmltext);
      break;
    }
    parent::xmlp_addtextdata();
  }

}
/*
function cbo($tag,$attr){
}
*/
?>
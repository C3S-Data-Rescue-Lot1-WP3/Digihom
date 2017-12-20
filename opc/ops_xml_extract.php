<?php
require_once('ops_array.php');
class ops_xml_extract {

  function toarray(&$xml,$settings=array()){
    $res = array();
    $def = array('match'=>'/./','name'=>'<node>','attr'=>TRUE);
    $set = ops_array::setdefault($settings,$def);
    $cp = $xml->iter('d');
    if(!is_array($cp)) return($res);
    while(is_array($cp)){
      $cn = $xml->name();
      if(preg_match($set['match'],$cn)){
	switch($set['name']){
	case '<node>': $rn = $cn; break;
	case '<num>':  $rn = count($res)+1; break;
	default:
	  $rn = $xml->attr($set['name']);
	}
	$res[$rn] = $xml->attrs();
      }
      $cp = $xml->iter('nN');
    }
    $cp = $xml->iter('u');
    return($res);
  }

}
?>
<?php


class ops_cast {

  static function cast($value){
    if(preg_match('/^\s*[-+]?\d+\s*$/',$value)) return((int)$value);
    if(is_numeric($value)) return((float)$value);
    return($value);
  }

  static function type($value){
    if(preg_match('/^\s*[-+]?\d+\s*$/',$value)) return('integer');
    if(is_numeric($value)) return('float');
    return('text');
  }

  static function is_mail($value){
    $value = trim($value);
    $partA = "[a-z][-_a-z0-9]*";
    $pattern = "/^$partA(\\.$partA)*@($partA\\.)+[a-z][a-z0-9]+$/i";
    return(preg_match($pattern,$value)>0);
  }

  static function is_domain($value){
    $value = trim($value);
    $partA = "[a-z][-_a-z0-9]*";
    $pattern = "|^(https?://)?($partA\\.)+[a-z][a-z0-9]+$|i";
    return(preg_match($pattern,$value)>0);
  }

  static function is_name($value){
    return(preg_match('/^\s*[_a-z][_a-z0-9]*\s*$/i',$value)>0);
  }



  static function is_integer($value){
    return(preg_match('|^\s*[+-]?\d+\s*|')>0);
  }

  static function to_integer($value){
    return(ops_cast::is_integer($value)?((int)$value):FALSE);
  }

  static function is_float($value){
    return(is_numeric($value));
  }

  static function to_float($value){
    return(ops_cast::is_float($value)?((float)$value):FALSE);
  }

  static function is_boolean($value){
    if(preg_match('/^\s*(yes|y|ja|j|oui|si|s|1|true|t|on|+)\s*$/i',$value)) return(TRUE);
    if(preg_match('/^\s*(no|non|n|nein|0|false|f|off|-)\s*$/i',$value)) return(TRUE);
    return(FALSE);
  }

  static function to_boolean($value){
    if(preg_match('/^\s*(yes|y|ja|j|oui|si|s|1|true|t|on|+)\s*$/i',$value)) return(TRUE);
    if(preg_match('/^\s*(no|non|n|nein|0|false|f|off|-)\s*$/i',$value)) return(FALSE);
    return(NULL);
  }
}

?>
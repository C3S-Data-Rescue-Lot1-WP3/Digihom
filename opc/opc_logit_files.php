<?php
/**
 * @package logit
 */

  /** try include necessary files if basic classes does not exist yet*/

if(!(class_exists('opc_logit'))) require(str_replace('_files.php','.php',__FILE__));

class opc_logit_files extends opc_logit {

  public function testsink($sink){
    $cp = strrpos($sink,'/');
    if($cp===FALSE) return 1;
    $dir = substr($sink,0,$cp);
    $pre = substr($sink,$cp+1);
    if(!$this->test_key($pre)) return 1;
    if(!file_exists($dir)) return 2;
    if(!is_readable($dir)) return 10;
    if(!is_writeable($dir)) return 11;
    return array($dir,$pre);
  }

}

?>
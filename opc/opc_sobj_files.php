<?php
/**
 * @package logit
 */

  /** try include necessary files if basic classes does not exist yet*/

if(!(class_exists('opc_sobj'))) require(str_replace('_files.php','.php',__FILE__));

class opc_sobj_files extends opc_sobj {

  protected $syntax = array('data-type'=>array('opc_sobj','files'),'version'=>1);

  public function testsource($source){
    $cp = strrpos($source,'/');
    if($cp===FALSE) return 1;
    $dir = substr($source,0,$cp);
    $pre = substr($source,$cp+1);
    if(!preg_match('/^[a-z0-9_]+$/i',$pre)) return 1;
    if(!file_exists($dir)) return 2;
    if(!is_readable($dir)) return 10;
    if(!is_writeable($dir)) return 11;
    return array(getcwd() . '/' . $dir,$pre);
  }


  function id2key($id){
    if(!$this->running) return NULL;
    $l0 = strlen($this->source[0]);
    $l1 = strlen($this->source[1]);
    if(substr($id,0,$l0+$l1+2)==$this->source[0] . '/' . $this->source[1] . '_')
      return substr($id,$l0+$l1+2);
    if(substr($id,0,$l1+1)==$this->source[1] . '_')
      return substr($id,$l1+1);
    return NULL;
  }

  function key2id($key){
    return $this->source[0] . '/' .$this->source[1] . '_' . $key;
  }

  protected function _save($id,$key,$data,$attrs){
    if(is_null($id)) return NULL;
    $fi = fopen($id,'w');
    if(!isset($GLOBALS['_tool_'])){
      $syn = array();
      foreach($this->syntax as $ck=>$cv) $syn[] = $ck .':' . implode('.',(array)$cv);
      $syn = '[[' . implode(' ',$syn) . ']]';
    } else $syn = $GLOBALS['_tool_']->syntax_str($this->syntax);
    fwrite($fi,$syn . "\n");
    fwrite($fi,'id:' . $id . "\n");
    fwrite($fi,'key:' . $key . "\n");
    foreach($attrs as $ck=>$cv) fwrite($fi,'+' . $ck . ':' . $this->attr_encode($cv) . "\n");
    fwrite($fi,"----- data serialized -----\n");
    fwrite($fi,$this->data_encode($data));
    fclose($fi);
  }

  function exists($key){
    $id = $this->key2id($key);
    if(file_exists($id)) return $id;
    return FALSE;
  }

  function load($key){
    $this->unload();
    $id = $this->exists($key);
    if($id===FALSE) return NULL;
    $dat = file($id);
    $syn = array_shift($dat);
    // test syntax if _tool_ is available otherwise: just hope
    if(isset($GLOBALS['_tool_']) and $GLOBALS['_tool_']->syntax_test($syn,$this->syntax)!==TRUE) return NULL;
    while(count($dat)>0){
      $ck = explode(':',array_shift($dat),2);
      if(count($ck)==2) list($ck,$cv) = $ck; else $ck = $ck[0];
      if($ck=='key') 
	$this->$ck = trim($cv); 
      else if(substr($ck,0,1)=='+')
	$this->attrs[trim(substr($ck,1))] = $this->attr_decode($cv);
      else if(substr($ck,0,4)=='----')
	break;
    } 
    $this->data = $this->data_decode(implode('',$dat));
    $this->loaded = TRUE;
  }
  
  function get($key){
    if(!$this->running) return NULL;
    $id = $this->exists($key);
    if($id===FALSE) return NULL;
    $dat = file($id);
    $syn = array_shift($dat);
    // test syntax if _tool_ is available otherwise: just hope
    if(isset($GLOBALS['_tool_']) and $GLOBALS['_tool_']->syntax_test($syn,$this->syntax)!==TRUE) return NULL;
    while(substr(array_shift($dat),0,4)!='----');
    return $this->data_decode(implode('',$dat));
  }



  function remove($key){
    $id = $this->exists($key);
    if($id===FALSE) return NULL;
    unlink($id);
  }


  function listall(){
    $fn = strlen($this->source[1])+1;
    $res = array();
    foreach(scandir($this->source[0]) as $cf)
      if(substr($cf,0,$fn)==$this->source[1] . '_') 
	$res[] = $this->id2key($cf);
    return $res;
  }
  }

?>
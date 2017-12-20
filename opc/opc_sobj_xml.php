<?php
/**
 * @package sobj
 */

  /** try include necessary files if basic classes does not exist yet*/
if(!(class_exists('opc_xml'))) require('opc_fxml.php');
if(!(class_exists('opc_sobj'))) require(str_replace('_xml.php','.php',__FILE__));


// check syntax if source is tested!
class opc_sobj_xml extends opc_sobj {

  protected $syntax = array('data-type'=>array('opc_sobj','xml'),'version'=>1);

  public function testsource($source){
    $cp = strrpos($source,'/');
    if($cp===FALSE) return 1;
    $dir = substr($source,0,$cp);
    if(!file_exists($dir)) return 2;
    if(!is_readable($dir)) return 10;
    if(!is_writeable($dir)) return 11;
    if(!file_exists($source)){
      if(!isset($GLOBALS['_tool_'])){
	$syn = array();
	foreach($this->syntax as $ck=>$cv) $syn[] = $ck .':' . implode('.',(array)$cv);
	$syn = '[[' . implode(' ',$syn) . ']]';
      } else $syn = $GLOBALS['_tool_']->syntax_str($this->syntax);
      $syn = "<?xml version='1.0' encoding='UTF-8'?>\n<sobjcollection syntax='$syn'>\n</sobjcollection>";
      file_put_contents($source,$syn);
    }
    $xml = new opc_xml($source);
    return $xml;
  }


  function id2key($id){
    if(!$this->running) return NULL;
    return $this->source->attr_get('key',$id);
  }

  function key2id($key){
    if(!$this->running) return NULL;
    return $this->source->key_search('|%H%P/sobj@key|',$key,'node');
  }

  protected function _save($id,$key,$data,$attrs){
    $ak = array_keys($attrs);
    foreach($ak as $ck) $attrs[$ck] = $this->attr_encode($attrs[$ck]);
    $dat = $this->data_encode($data);
    $attrs['key'] = $key;
    if(is_null($id)){
      $nk = $this->source->node_node_insert('sobj',NULL,$attrs,array("\n$dat\n"));
      $nk = $this->source->node_text_insert("\n");
    } else {
      $this->source->attrs_del(NULL,$id);
      $this->source->attrs_set($attrs,$id);
      $this->source->text_replace($dat,$id . '#0/');
    }
    $this->source->write_file($this->source_def);
    return;
  }

  function exists($key){
    $tmp = $this->source->key_search('|%H%P/sobj@key|',$key,'node');
    return is_null($tmp)?FALSE:$tmp;
  }

  function load($key){
    $this->unload();
    $id = $this->exists($key);
    if($id===FALSE) return NULL;
    $attrs = $this->source->attrs_get(NULL,$id);
    unset($attrs['key']);
    $ak = array_keys($attrs);
    foreach($ak as $ck) $attrs[$ck] = $this->attr_decode($attrs[$ck]);
    $this->attrs = $attrs;
    $this->key = $key;    
    $dat = $this->source->node_item_get($id,6);
    $this->data = $this->data_decode(trim(array_shift($dat)));
    $this->loaded = TRUE;
  }
  
  function get($key){
    if(!$this->running) return NULL;
    $id = $this->exists($key);
    if($id===FALSE) return NULL;
    $dat = $this->source->node_item_get($id,6);
    return $this->data_decode(trim(array_shift($dat)));
  }



  function remove($key){
    $id = $this->exists($key);
    if($id===FALSE) return NULL;
    $tmp = $this->source->iter('nextsibling',$id,FALSE);
    if($this->source->get($tmp)==="\n") $this->source->remove($tmp,FALSE);//remove newline below
    $this->source->remove($id,FALSE);
    if($this->fm==0) $this->source->write_file($this->source_def);
  }


  function listall(){
    $res = array_values($this->source->values_search('|%H%P/sobj@key$|',NULL,-1));
    return $res;
  }
}

?>
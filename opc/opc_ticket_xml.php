<?php
/**
 * @package ticket
 */

  /** try include necessary files if basic classes does not exist yet*/

if(!(class_exists('opc_xml'))) require('opc_fxml.php');
if(!(class_exists('opc_ticket'))) require(str_replace('_xml.php','.php',__FILE__));


class opc_ticket_xml extends opc_ticket {

  protected $src = NULL;

  public function testsource($source){
    $cp = strrpos($source,'/');
    if($cp===FALSE) return 1;
    $dir = substr($source,0,$cp);
    if(!file_exists($dir)) return 2;
    if(!is_readable($dir)) return 10;
    if(!is_writeable($dir)) return 11;
    if(!file_exists($source)){
      $syn = "<?xml version='1.0' encoding='UTF-8'?>\n<tickets>\n</tickets>";
      file_put_contents($source,$syn);
    }
    $xml = new opc_xml($source);
    return $xml;
  }

  /** decode attribute value after read in the raw data */
  function attr_decode($txt){ return trim($txt);}
  /** decode attribute value before write */
  function attr_encode($txt){ return $txt;}

  /** decode data value after read in the raw data */
  protected function data_decode($txt){ return unserialize($txt);}
  /** decode data value before write */
  protected function data_encode($txt){ return serialize($txt);}

  protected function _save($key,$data,$attrs){
    $id = $this->source->key_search('|%H%P/ticket@key|',$key,'node');
    $ak = array_keys($attrs);
    foreach($ak as $ck) $attrs[$ck] = $this->attr_encode($attrs[$ck]);
    $dat = $this->data_encode($data);
    $attrs = array_merge(array('key'=>$key),$attrs);
    if(is_null($id)){
      $nk = $this->source->node_node_insert('ticket',NULL,$attrs,array("\n$dat\n"));
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
    $tmp = $this->source->key_search('|%H%P/ticket@key|',$key,'node');
    return is_null($tmp)?FALSE:$tmp;
  }

  function clean(){
    $cd = date('Y-m-d H:i:s',time()-$this->remove_after*60);
    $ak = $this->source->values_search('|%H%P/ticket@dat_expire$|',NULL,-1);
    $write = FALSE;
    foreach($ak as $ck=>$cv){
      if($cv<$cd){
	$this->_remove($this->source->iter('node',$ck,FALSE),$ck);
	$write= TRUE;
      }
    }

    $cd = date('Y-m-d H:i:s');
    $ak = $this->source->values_search('|%H%P/ticket@status$|','created',0,'node');
    foreach($ak as $ck){
      if($cd>$this->source->attr_get('dat_expire',$ck)){
	$this->source->attr_set('status','expired',$ck);
	$this->source->attr_set('dat_modified',$cd,$ck);
	$write = TRUE;
      }
    }
    if($write) $this->source->write_file($this->source_def);
  }


  protected function getfield($key,$field){
    $id = $this->source->key_search('|%H%P/ticket@key|',$key,'node');
    return $this->source->attr_get($field,$id);
  }

  protected function setfield($key,$field,$value){
    $id = $this->source->key_search('|%H%P/ticket@key|',$key,'node');
    $this->source->attr_set($field,$value,$id);
    $this->source->attr_set('dat_modiofied',date('Y-m-d H:i:s'),$id);
    $this->source->write_file($this->source_def);
  }

  function clear(){
    if(!is_string($this->source_def)) return;
    unlink($this->source_def);
    $syn = "<?xml version='1.0' encoding='UTF-8'?>\n<tickets>\n</tickets>";
    file_put_contents($this->source_def,$syn);
    $this->source = new opc_xml($this->source_def);
  }

  function remove($key){
    if(!$this->test_key($key)) return $this->err->err(91);
    $this->_remove($this->source->key_search('|%H%P/ticket@key|',$key,'node'));
    return $this->err->ok();
  }

  function _remove($key){
    $tmp = $this->source->iter('nextsibling',$key,FALSE);
    if($this->source->get($tmp)==="\n") $this->source->remove($tmp,FALSE);//remove newline below
    $this->source->remove($key,FALSE);
    $this->source->write_file($this->source_def);
  }

  function _get($key){
    $id = $this->source->key_search('|%H%P/ticket@key|',$key,'node');
    $dat = $this->source->node_item_get($id,6);
    return $this->data_decode(trim(array_shift($dat)));
  }

}
?>
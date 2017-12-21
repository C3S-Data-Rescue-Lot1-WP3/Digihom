<?php

if(!(class_exists('opc_sxml'))) require('opc_sxml.php');
if(!(class_exists('opc_ticket'))) require(str_replace('_sxml.php','.php',__FILE__));


class opc_ticket_sxml extends opc_ticket {

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
    $xml = new opc_sxml($source,array('{/ticket$}'));
    return $xml;
  }

  protected function getfield($key,$field){
    $id = $this->key_search($key);
    return $this->source->attr_get($field,$id);
  }

  protected function setfield($key,$field,$value){
    $id = $this->key_search($key);
    $attr = array($field=>$value,'dat_modiofied'=>date('Y-m-d H:i:s'));
    $this->source->attr_setm($attr,$id);
    $this->source->write($this->source_def);
  }



  protected function _save($key,$data,$attrs){
    $id = $this->key_search($key);
    $ak = array_keys($attrs);
    foreach($ak as $ck) $attrs[$ck] = $this->attr_encode($attrs[$ck]);
    $dat = $this->data_encode($data);
    $attrs = array_merge(array('key'=>$key),$attrs);
    $attrs['type'] = strval($attrs['type']);
    if(is_null($id))
      $id = $this->source->key('/tickets#>/ticket@');
    else
      $this->source->attr_unset(NULL,$id);
    $this->source->attr_setm($attrs,$id);
    $this->source->text_set("\n$dat\n",$id);
    $this->source->write($this->source_def);
    return;
  }

  function _remove($key){
    $id = $this->key_search($key);
    $this->source->node_unset($id);
    $this->source->write($this->source_def);
  }

  function _get($key){
    $id = $this->key_search($key);
    $dat = $this->source->text_get($id,6);
    return $this->data_decode(trim($dat));
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
    if($write) $this->source->write($this->source_def);
  }


  function exists($key){
    $tmp = $this->key_search($key);
    return is_null($tmp)?FALSE:$tmp;
  }

  function clear(){
    if(!is_string($this->source_def)) return;
    unlink($this->source_def);
    $syn = "<?xml version='1.0' encoding='UTF-8'?>\n<tickets>\n</tickets>";
    file_put_contents($this->source_def,$syn);
    $xml = new opc_sxml($this->source_def,array('{/ticket$}'));
  }




  /** decode attribute value after read in the raw data */
  protected function attr_decode($txt){ return trim($txt);}
  /** decode attribute value before write */
  protected function attr_encode($txt){ return $txt;}

  /** decode data value after read in the raw data */
  protected function data_decode($txt){ return unserialize($txt);}
  /** decode data value before write */
  protected function data_encode($txt){ return serialize($txt);}


  protected function key_search($key){
    return $this->source->search('ppat','{^%H%P/ticket@key$}','value',$key,'node');
  }

}
?>
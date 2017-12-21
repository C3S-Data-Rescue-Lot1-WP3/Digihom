<?php
/**
 * @package logit
 */

  /** try include necessary files if basic classes does not exist yet*/

if(!(class_exists('opc_pg'))) require('opc_pg.php');
if(!(class_exists('opc_sobj'))) require(str_replace('_pgdb.php','.php',__FILE__));

class opc_sobj_pgdb extends opc_sobj {

  /** if some attributes may be saved in own table fields this is defined here
   * array(sobjkey=>fieldname,....)
   */
  public $attr_direct = array();

  protected $syntax = array('data-type'=>array('opc_sobj','pgdb'),'version'=>1);

  public function testsource($source){
    $con = NULL;
    if(is_array($source)){
      if(isset($source['pgcon']))
	$con = $GLOBALS['_tool_']->load_connection('pgdb',$source['pgcon']);
      $tab = def($source,'table','ticket');
    } else {
      $tab = $source;
      $con = $GLOBALS['_tool_']->load_connection('pgdb',$this->source_def['pgcon']);
    }
    if(!is_string($con)) return 1;
    $db = new opc_pg($con);
    if(is_null($db->db)) return 2;
    if(!$db->table_exists($tab)) return 2;
    return array($db,$tab);
  }

  function id2key($id){
    if(!$this->running) return NULL;
    return $this->source[0]->load_field($this->source[1],'key',array('id'=>$id));
  }

  function key2id($key){
    if(!$this->running) return NULL;
    return $this->source[0]->load_field($this->source[1],'id',array('key'=>$key));
  }

  protected function _save($id,$key,$data,$attrs){
    $dat = array('key'=>$key,'data'=>$this->data_encode($data));
    foreach($this->attr_direct as $ck=>$cv){
      if(isset($attrs[$ck])){
	$dat[$cv] = $this->attr_encode($attrs[$ck]);
	unset($attrs[$ck]);
      } else $dat[$cv] = NULL;
    }
    $dat['attr'] = serialize($attrs);
    if(is_null($id))
      $this->source[0]->write_row($this->source[1],$dat);
    else
      $this->source[0]->write_row($this->source[1],$dat,array('id'=>$id));
  }

  function exists($key){
    if(!$this->running) return NULL;
    $res = $this->source[0]->load_field($this->source[1],'id',array('key'=>$key));
    return is_null($res)?FALSE:$res;
  }

  function load($key){
    $this->unload();
    $id = $this->exists($key);
    if($id===FALSE) return NULL;
    $dat = $this->source[0]->load_row($this->source[1],array('id'=>$id));
    $this->key = $dat['key'];
    $this->attrs = unserialize($dat['attr']);
    foreach($this->attr_direct as $ck=>$cv)
      if(!is_null($dat[$cv])) $this->attrs[$ck] = $this->attr_decode($dat[$cv]);
    $this->data = $this->data_decode($dat['data']);
    $this->loaded = TRUE;
  }
  
  function get($key){
    if(!$this->running) return NULL;
    $id = $this->exists($key);
    if($id===FALSE) return NULL;
    return $this->data_decode($this->source[0]->load_field($this->source[1],'data',array('id'=>$id)));
  }



  function remove($key){
    $this->source[0]->remove($this->source[1],array('key'=>$key));
  }


  function listall(){
    return (array)$this->source[0]->load_column($this->source[1],'key','id');
  }
}

?>
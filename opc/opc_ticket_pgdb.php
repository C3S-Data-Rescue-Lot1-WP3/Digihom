<?php
/**
 * @package ticket
 */

  /** try include necessary files if basic classes does not exist yet*/

if(!(class_exists('opc_pg'))) require('opc_pg.php');
if(!(class_exists('opc_ticket'))) require(str_replace('_pgdb.php','.php',__FILE__));

class opc_ticket_pgdb extends opc_ticket {

  protected $db = NULL;
  protected $table = NULL;

  protected function testsource($con,$silent=FALSE){
    $src = $GLOBALS['_tool_']->create_external(array('sobj','pgdb'),$con);
    if(!is_object($src)) return NULL;
    $src->attr_direct = array('type'=>'type','status'=>'status',
			      'dat_created'=>'dat_created','dat_modified'=>'dat_modified',
			      'dat_expire'=>'dat_expire');
    try{
      $msg = 'Connection for ticket system: ';
      $con = def($src->source_def,'pgcon');
      if(is_null($con)) 
	throw new Exception($msg . "no 'pgdb' settings");
      $con = $GLOBALS['_tool_']->load_connection('pgdb',$con);
      if(is_null($con)) 
	throw new Exception($msg . "invalid 'pgdb' settings");
      $this->db = new opc_pg($con);
      if(!is_object($this->db)) 
	throw new Exception($msg . 'not able to create db instance');
      $this->table = def($src->source_def,'table','ticket');
      return $src;
    } catch (Exception $ex){
      if(!$silent) 
	trigger_error($ex->getMessage());
      return 1;
    }
  }

  function clean(){
    $sql = 'DELETE FROM ' . $this->table . ' WHERE dat_expire<\''
      . date('Y-m-d H:i:s',time()-$this->remove_after*60) . '\'';
    $this->db->sql_execute($sql);
    $sql = 'UPDATE ' . $this->table
      . ' SET status=\'expired\','
      . ' dat_modified=\'' .  date('Y-m-d H:i:s') . '\''
      . ' WHERE dat_expire<\'' . date('Y-m-d H:i:s') . '\''
      . ' AND status=\'created\'';
    $this->db->sql_execute($sql);
  }


  protected function getfield($key,$field){
    return $this->db->load_field($this->table,$field,array('key'=>$key));
  }

  protected function setfield($key,$field,$value){
    $sql = 'UPDATE ' . $this->table . ' SET status=\'' . pg_escape_string($value) . '\','
      . ' dat_modified=\'' .  date('Y-m-d H:i:s') . '\''
      . ' WHERE key=\'' . pg_escape_string($key) . '\'';
      return $this->db->sql_execute($sql);
  }

  function clear(){
    return $this->db->sql_execute('DELETE FROM ' . $this->table);
  }

  protected function _save($key,$data,$attrs){
    $this->source->save($key,$data,$attrs);
  }

  function exists($key){
    return $this->source->exists($key)!==FALSE;
  }

  function _remove($key){
    if(!$this->test_key($key)) return $this->err->err(91);
    $this->source->remove($key);
    return $this->err->ok();
  }

  protected function _get($key){
    return $this->source->get($key);
  }

  public function str2time($date){
    return $this->db->str2time($date);
  }
}
?>
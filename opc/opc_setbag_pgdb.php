<?php
/**
 * @package setbag
 */

  /** try include necessary files if basic classes does not exist yet*/

if(!(class_exists('opc_pg'))) require('opc_pg.php');
if(!(class_exists('opc_setbag'))) require(str_replace('_pgdb.php','.php',__FILE__));

/**
 * setbag subclass for using a postgres database as target
 * @author Joerg Maeder joerg@toolcase.org
 * @version 1.0
 * @package setbag
 * @subpackage pgdb_class
 */
class opc_setbag_pgdb extends opc_setbag {

  protected $syntax = 'setbag_pgdb:1.0';

  /**  test the bag-connection 
   * @param $bag array('pcon'=>PG-Connection,'table'=>Table-Name)
   *   or (if beg-def already exists) string (tablename)
   * @return object to save in bag(logbag or numeric error code
   */
  public function testbag($bag){
    $con = NULL;
    if(is_array($bag)){
      if(isset($bag['pgcon'])){
	$con = $GLOBALS['_tool_']->load_connection('pgdb',$bag['pgcon']);
      }
      $tab = $bag['table'];
    } else {
      $tab = $bag;
      $con = $GLOBALS['_tool_']->load_connection('pgdb',$this->bag_def['pgcon']);
    }
    if(!is_string($con)) return 1;
    $db = new opc_pg($con);
    if(is_null($db->db)) return 2;
    if(!$db->table_exists($tab)) return 3;
    return array($db,$tab);
  }

  /** returns FALSE or the internal key */
  protected function _exists($key,$typ,$uname){
    if($this->bag[0]->count($this->bag[1],array('key'=>$key,'type'=>$typ,'uname'=>$uname))!=1) return FALSE;
    return $this->bag[0]->load_field($this->bag[1],'id',array('key'=>$key,'type'=>$typ,'uname'=>$uname));
  }

  public function set($value,$key,$typ=NULL,$uname=NULL,$comment=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    if(is_resource($value)) return $this->err->err(22);
    $val = serialize($value);
    if(!$this->test_comment($comment)) return $this->err->err(23);
    $fkey = $this->id_ktu2int($key,$typ,$uname);
    $new = array('syntax'=>$this->syntax,
		 'key'=>is_null($key)?'':$key,
		 'type'=>is_null($typ)?'':$typ,
		 'uname'=>is_null($uname)?'':$uname,
		 'date_created'=>date('Ymd His'),
		 'uname_created'=>$uname,
		 'date_modified'=>date('Ymd His'),
		 'comment'=>$comment,
		 'data'=>$val);
    if(is_numeric($fkey)){
      $fkey = (int)$fkey;
      $old = $this->bag[0]->load_row($this->bag[1],array('id'=>$fkey));
      if($old['syntax']==$this->syntax){
	unset($old['id']);
	$new['date_created'] = $old['date_created']; // intial value -> no change allowed
	$new['uname_created'] = $old['uname_created']; // intial value -> no change allowed
	$old['date_modified'] = $new['date_modified']; // change is not 'important'
	if($old===$new) return $this->err->ok(-1);
      }
      $this->log('m',$key,$typ,$uname);
      $this->bag[0]->write_row($this->bag[1],$new,array('id'=>$fkey));
    } else {
      $this->log('c',$key,$typ,$uname);
      $this->bag[0]->write_row($this->bag[1],$new);
    }
    return $this->err->ok();
  }
  public function get($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $res = $this->read_field($fn,'data');
    return unserialize($res);
}

  public function delete($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $this->log('d',$key,$typ,$uname);
    $this->bag[0]->remove($this->bag[1],array('id'=>$fn));
  }

  function read_item($key,$typ,$uname,$what){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    return $this->read_field($fn,$what);
  }

  protected function read_field($fn,$field){
    return $this->bag[0]->load_field($this->bag[1],$field,array('id'=>$fn));
  }

  public function complete($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    return $this->bag[0]->load_row($this->bag[1],array('id'=>$fn));
  }

  public function listitems($key=FALSE,$typ=FALSE,$uname=FALSE){
    $tmp = $this->_listitems_prepare($key,$typ,$uname);
    if(is_null($tmp)) $this->err->err(21);
    list($key,$typ,$uname) = $tmp;
    $ids = array();
    if($key!==FALSE) $ids['key'] = $key;
    if($typ!==FALSE) $ids['type'] = $typ;
    if($uname!==FALSE) $ids['uname'] = $uname;
    
    $qa = $this->bag[0]->load_array($this->bag[1],array('key','type','uname','id'),$ids,'id');
    if(is_null($qa)) return array(); 
    $ak = array_keys($qa);
    foreach($ak as $ck){
      if($qa[$ck]['uname']=='') $qa[$ck]['uname'] = NULL;
      if($qa[$ck]['type']=='') $qa[$ck]['type'] = NULL;
    }
    return $qa;
  }

  public function listlogs($key=FALSE,$typ=FALSE,$uname=FALSE){
    $tmp = $this->_listitems_prepare($key,$typ,$uname);
    if(is_null($tmp)) $this->err->err(21);
    list($key,$typ,$uname) = $tmp;
    $ids = array();
    if($key!==FALSE) $ids['key'] = $key;
    if($typ!==FALSE) $ids['type'] = $typ;
    if($uname!==FALSE) $ids['uname'] = $uname;
    
    $qa = $this->logbag[0]->load_array($this->logbag[1],array('key','type','uname','id','mode','date_logged'),$ids,'id');
    if(is_null($qa)) return array(); else return $qa;
  }

  protected function log($mode,$key,$typ,$uname){
    if(strpos($this->logmode,$mode)!==FALSE) return;
    if(is_null($this->logbag)) return;
    $oid = $this->id_ktu2int($key,$typ,$uname);
    if(is_null($typ)) $typ = '';
    if(is_null($uname)) $uname = '';
    switch($mode){
    case 'a': case 'c':
      $this->logbag[0]->write_row($this->logbag[1],array('mode'=>$mode,'oid'=>$oid,
							 'key'=>$key,'type'=>$typ,'uname'=>$uname));
      break;
    case 'd': case 'm':
      $org = serialize($this->complete($key,$typ,$uname));
      $this->logbag[0]->write_row($this->logbag[1],array('mode'=>$mode,'oid'=>$oid,'original'=>$org,
							 'key'=>$key,'type'=>$typ,'uname'=>$uname));
      break;
    }
  }

  public function id_str2int($key){
    $res = $this->id_str2ktu($key);
    return $this->id_ktu2int($res['key'],$res['type'],$res['uname']);
  }
  public function id_ktu2int($key,$typ=NULL,$uname=NULL){
    $ct = is_null($typ)?'':$typ;
    $cu = is_null($uname)?'':$uname;
    return $this->bag[0]->load_field($this->bag[1],array('key'=>$key,'type'=>$ct,'uname'=>$cu));
  }
  public function id_int2str($key){
    $tmp = $this->bag[0]->load_row($this->bag[1],array('key','type','uname'),array('id'=>$key));
    return "K$tmp[key]/T$tmp[type]/U$tmp[uname]";
  }
  public function id_int2ktu($key){
    return $this->bag[0]->load_row($this->bag[1],array('key','type','uname'),array('id'=>$key));
  }

  public function id_2ktu($key,$typ=NULL,$uname=NULL){    
    if(strpos($key,'/')!==FALSE){
      $ktu = $this->id_str2ktu($key);
      return array($ktu['key'],def($ktu,'type',NULL),def($ktu,'uname',NULL));
    } else if(is_numeric($key)){
      $ktu = $this->id_int2ktu($key);
      return array($ktu['key'],def($ktu,'type',NULL),def($ktu,'uname',NULL));
    } else return array($key,$typ,$uname);
  }

  protected function id_ktu2log($mode,$key,$typ=NULL,$uname=NULL,$date=NULL){
    if(!$this->test_key($key)) return 22;
    if(!$this->test_type($typ)) return 24;
    if(!$this->test_uname($uname)) return 25;
    $fm = is_null($date)?date('YmdHis'):$date;
    return $this->logbag[0] . '/' . $this->logbag[1] . "___${mode}${fm}___K${key}___T${typ}___U${uname}";
  }

  public function getlog($logid){
    if(!is_numeric($logid)) return $this->err->err(31);
    $row = $this->logbag[0]->load_row($this->logbag[1],array('id'=>$logid));
    switch($row['mode']){
    case 'd': case 'm':
      $res = unserialize($row['original']);
      $res['data'] = unserialize($res['data']);
      $res['mode'] = $row['mode'];
      $res['date_logged'] = $row['date_logged'];
      return $res;

    case 'a': case 'c': 
      unset($row['original']);
      return $row;
    }
  }
  function dellog($logid){
    if(!is_numeric($logid)) return $this->err->err(31);
    $row = $this->logbag[0]->remove($this->logbag[1],array('id'=>$logid));
  }

}

?>
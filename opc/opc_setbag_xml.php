<?php
/**
 * @package setbag
 */

  /** try include necessary files if basic classes does not exist yet*/
if(!(class_exists('opc_xml'))) require('opc_fxml.php');
if(!(class_exists('opc_setbag'))) require(str_replace('_xml.php','.php',__FILE__));

/**
 * setbag subclass for using xml-files as target
 * @author Joerg Maeder joerg@toolcase.org
 * @version 1.0
 * @package setbag
 * @subpackage xml_class
 */
class opc_setbag_xml extends opc_setbag {

  protected $syntax = 'setbag_xml:1.0';
  protected $flush = TRUE;

  public function flush($which){
    if(($which & 1)==1) $this->bag->write_file($this->bag_def);
    if(($which & 2)==2) $this->logbag->write_file($this->logbag_def);
  }

  public function flush_mode($mode=NULL){
    if(is_null($mode)) return $this->flush;
    if(!is_bool($mode)) return $this->err->err(20);
    $this->flush = $mode;
    return $this->err->ok();
  }


  protected function _exists($key,$typ,$uname){
    $id = $this->id_ktu2str($key,$typ,$uname);
    $ok = $this->bag->key_search('|%H%P/value@id$|',$id,'node');
    if(!is_string($ok)) return FALSE;
    return $ok;
  }

  function read_item($key,$typ,$uname,$what){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $res = $this->bag->attr_get($what,$fn);
    switch($what){
    case 'comment': return str_replace('$$$',"\n",$res);}
    return $res;

  }

  public function testbag($bag){
    $cp = strrpos($bag,'/');
    if($cp===FALSE) return 1;
    $dir = substr($bag,0,$cp);
    if(!file_exists($dir)) return 2;
    if(!is_readable($dir)) return 10;
    if(!is_writeable($dir)) return 11;
    if(!file_exists($bag)) file_put_contents($bag,"<?xml version='1.0' encoding='UTF-8'?>\n<bag>\n</bag>");
    $xml = new opc_xml($bag);
    return $xml;
  }

  public function set($value,$key,$typ=NULL,$uname=NULL,$comment=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    if(is_resource($value)) return $this->err->err(22);
    $val = serialize($value);
    if(!$this->test_comment($comment)) return $this->err->err(23);
    $fn = $this->id_ktu2str($key,$typ,$uname);
    if(is_int($fn)) return $this->err->err($fn);
    $new = array('syntax'=>$this->syntax,
		 'id'=>$fn,
		 'key'=>$key,
		 'type'=>$typ,
		 'uname'=>$uname,
		 'date_created'=>date('Ymd His'),
		 'uname_created'=>$uname,
		 'date_modified'=>date('Ymd His'),
		 'comment'=>preg_replace('/[\r\n]/','$$$',$comment));
    $new = array_filter($new);

    $ok = $this->bag->key_search('|%H%P/value@id$|',$fn,'node');
    if(!is_null($ok)){
      $old = $this->bag->attrs_get(NULL,$ok);
      $od = $this->bag->node_item_get($ok,6);
      $od = array_shift($od);
      if($old['syntax']==$this->syntax){
	$new['date_created'] = $old['date_created']; // intial value -> no change allowed
	$new['uname_created'] = def($old,'uname_created'); // intial value -> no change allowed
	$old['date_modified'] = $new['date_modified']; // change is not 'important'
	if($old===$new and $od==$val) return $this->err->ok(-1);
      }
      $this->bag->attrs_set($new,$ok);
      if(!isset($new['comment'])) $this->bag->attrs_del(array('comment'),$ok);
      $this->bag->text_replace($val,$ok . '#0/');
      $this->log('m',$key,$typ,$uname);
    } else {
      $this->log('c',$key,$typ,$uname);
      $nk = $this->bag->node_node_insert('value',NULL,$new,array("\n$val\n"));
      $nk = $this->bag->node_text_insert("\n");
      $this->bag->attrs_set($new,$nk);// ?? necessary??
    }
    if($this->flush) $this->flush(1);
    return $this->err->ok();	   
  }

  public function get($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $res = $this->bag->node_item_get($fn,6);
    return unserialize(trim(array_shift($res)));
  }


  public function delete($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $this->log('d',$key,$typ,$uname);
    $this->bag->remove($fn,FALSE);
    if($this->flush) $this->flush(1);
  }



  public function complete($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $res = $this->bag->attrs_get(NULL,$fn,array('type'=>'','uname'=>''));
    $val = $this->bag->node_item_get($fn,6);
    $res['data'] = unserialize(trim(array_shift($val)));
    return $res;
  }

  public function listitems($key=FALSE,$typ=FALSE,$uname=FALSE){
    $tmp = $this->_listitems_prepare($key,$typ,$uname);
    if(is_null($tmp)) $this->err->err(21);
    list($key,$typ,$uname) = $tmp;
    $keys = $this->bag->keys_search('|%H%P/value$|');
    $res = array();
    foreach($keys as $cf){
      $ca = $this->bag->attrs_get(array('key','type','uname','id'),$cf,array('type'=>'','uname'=>''));
      if($key!==FALSE and $ca['key']!==$key) continue;
      if($typ!==FALSE and $ca['type']!==$typ) continue;
      if($uname!==FALSE and $ca['uname']!==$uname) continue;
      $res[$ca['id']] = $ca;
    }
    return $res;
  }

  public function listlogs($key=FALSE,$typ=FALSE,$uname=FALSE){
    $tmp = $this->_listitems_prepare($key,$typ,$uname);
    if(is_null($tmp)) $this->err->err(21);
    list($key,$typ,$uname) = $tmp;
    $keys = $this->logbag->keys_search('|%H%P/log$|');
    $res = array();
    foreach($keys as $cf){
      $ca = $this->logbag->attrs_get(array('key','type','uname','id','mode','date_logged'),
				  $cf,
				  array('type'=>NULL,'uname'=>NULL));
      $res[$ca['id']] = $ca;
    }
    return $res;
  }

  protected function log($mode,$key,$typ,$uname){
    if(strpos($this->logmode,$mode)!==FALSE) return;
    if(is_null($this->logbag)) return;
    $fl = $this->id_ktu2log($mode,$key,$typ,$uname);
    $new = array('id'=>$fl,'mode'=>$mode,
		 'key'=>$key,'type'=>$typ,'uname'=>$uname);
    switch($mode){
    case 'a': case 'c': $ser = ''; break;
    case 'd': case 'm': $ser = serialize($this->complete($key,$typ,$uname)); break;
    }
    $nk = $this->logbag->node_node_insert('log',NULL,$new,array($ser));
    $nk = $this->logbag->node_text_insert("\n");
    $this->flush(2);
  }





  public function id_str2int($key){
    return $this->bag[1] . '___' . str_repalce('/','___',$key);
  }

  public function id_ktu2int($key,$typ=NULL,$uname=NULL){
    if(!$this->test_key($key)) return 22;
    if(!$this->test_type($typ)) return 24;
    if(!$this->test_uname($uname)) return 25;
    return "K${key}___T${typ}___U${uname}";
  }
  public function id_int2str($key){
    return str_replace('___','/',$key);
  }

  public function id_int2ktu($key){
    $res = array();
    $parts = explode('___',$key);
    foreach($parts as $ci) $res[$this->chars[substr($ci,0,1)]] = substr($ci,1);
    return $res;
  }
  
  public function id_2ktu($key,$typ=NULL,$uname=NULL){
    if(strpos($key,'/')!==FALSE){
      $ktu = $this->id_str2ktu($key);
      return array($ktu['key'],def($ktu,'type',NULL),def($ktu,'uname',NULL));
    } else if(strpos($key,'___')!==FALSE){
      $ktu = $this->id_int2ktu($key);
      return array($ktu['key'],def($ktu,'type',NULL),def($ktu,'uname',NULL));
    } else return array($key,$typ,$uname);
  }

  protected function id_ktu2log($mode,$key,$typ=NULL,$uname=NULL,$date=NULL){
    if(!$this->test_key($key)) return 22;
    if(!$this->test_type($typ)) return 24;
    if(!$this->test_uname($uname)) return 25;
    $fm = is_null($date)?date('YmdHis'):$date;
    return "${mode}${fm}___K${key}___T${typ}___U${uname}";
  }

  protected function id_log2ktu($key){
    $res = array();
    $parts = explode('___',$key);
    $cm = array_shift($parts);
    $res['mode'] = substr($cm,0,1);
    $res['date'] = substr($cm,1);
    foreach($parts as $ci) $res[$this->chars[substr($ci,0,1)]] = substr($ci,1);
    return $res;


  }

  public function getlog($logid){
    yy(); return;
    $ktu = array();
    $parts = explode('/',$logid);
    $ktu['mode'] = array_shift($parts);
    $ktu['date_logged'] = array_shift($parts);
    foreach($parts as $ci) $ktu[$this->chars[substr($ci,0,1)]] = substr($ci,1);
    $fl = $this->id_ktu2log($ktu['mode'],$ktu['key'],$ktu['type'],$ktu['uname'],$ktu['date_logged']);
    if(file_exists($fl)){
      switch($ktu['mode']){
      case 'd': case 'm':

	$dat = file($fl);
	$res = array();
	while(substr($cl=array_shift($dat),0,4)!=='data'){
	  $cl = explode(':',$cl,2);
	  $res[trim($cl[0])] = trim($cl[1]);
	}
	array_shift($dat);
	$res['data'] = unserialize(implode('',$dat));
	$res['mode'] = $ktu['mode'];
	$res['date_logged'] = $ktu['date_logged'];
	return $res;
      
      case 'a': case 'c': return $ktu;
      } 
    } else return $this->err->err(31);
  }

  function dellog($logid){
    yy(); return;
    $ktu = array();
    $parts = explode('/',$logid);
    $ktu['mode'] = array_shift($parts);
    $ktu['date_logged'] = array_shift($parts);
    foreach($parts as $ci) $ktu[$this->chars[substr($ci,0,1)]] = substr($ci,1);
    $fl = $this->id_ktu2log($ktu['mode'],$ktu['key'],$ktu['type'],$ktu['uname'],$ktu['date_logged']);
    if(file_exists($fl)) unlink($fl);
    else return $this->err->err(31);
  }
  
  function __destruct(){
    if(!$this->flush) $this->flush(3);
  }
}

?>
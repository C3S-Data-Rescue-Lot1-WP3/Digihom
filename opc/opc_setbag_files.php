<?php
/**
 * @package setbag
 */

  /** try include necessary files if basic classes does not exist yet*/

if(!(class_exists('opc_setbag'))) require(str_replace('_files.php','.php',__FILE__));

/**
 * setbag subclass for using single files in a directory as target
 * @author Joerg Maeder joerg@toolcase.org
 * @version 1.0
 * @package setbag
 * @subpackage files_class
 */
class opc_setbag_files extends opc_setbag {

  protected $syntax = 'setbag_files:1.0';

  protected $lineorder = array('syntax'=>0,
			       'date_created'=>1,
			       'uname_created'=>2,
			       'date_modified'=>3,
			       'key'=>4,
			       'uname'=>5,
			       'type'=>6,
			       'comment'=>7);

  public function test_key($key){
    if(parent::test_key($key)===FALSE) return FALSE;
    return strpos($key,'___')===FALSE;
  }

  public function test_uname($uname){
    if(parent::test_uname($uname)===FALSE) return FALSE;
    return strpos($uname,'___')===FALSE;
  }

  public function test_type($typ){
    if(parent::test_type($typ)===FALSE) return FALSE;
    return strpos($typ,'___')===FALSE;
  }


  protected function _exists($key,$typ,$uname){
    $files = scandir($this->bag[0]);
    $pat = '/^' . $this->bag[1] . '___K' . $key . '___T' . $typ . '___U' . $uname . '$/';
    $cf = preg_grep($pat,$files);
    if(count($cf)!=1) return FALSE;
    return $this->bag[0] . '/' . array_shift($cf);
  }    

  function read_item($key,$typ,$uname,$what){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $res = $this->read_line($fn,is_string($what)?$this->lineorder[$what]:$what,TRUE);
    switch($what){
    case 'comment': return str_replace('$$$',"\n",$res);}
    return $res;
  }

  protected function read_line($fn,$n=1,$close){
    $fi = fopen($fn,'r');
    while($n-->0) fgets($fi);
    $res = fgets($fi);
    if($close){
      fclose($fi);
      return trim(substr($res,strpos($res,':')+1));
    } else return $fi;
  }

  public function testbag($bag){
    $cp = strrpos($bag,'/');
    if($cp===FALSE) return 1;
    $dir = substr($bag,0,$cp);
    $pre = substr($bag,$cp+1);
    if(!$this->test_key($pre)) return 1;
    if(!file_exists($dir)) return 2;
    if(!is_readable($dir)) return 10;
    if(!is_writeable($dir)) return 11;
    return array($dir,$pre);
  }

  public function set($value,$key,$typ=NULL,$uname=NULL,$comment=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    if(is_resource($value)) return $this->err->err(22);
    $val = serialize($value);
    if(!$this->test_comment($comment)) return $this->err->err(23);
    $fn = $this->id_ktu2int($key,$typ,$uname);
    if(is_int($fn)) return $this->err->err($fn);
    $new = array('syntax: ' . $this->syntax . "\n",
		 'date_created: ' . date('Y-m-d H:i:s') . "\n",
		 'uname_created: ' . $uname . "\n",
		 'date_modified: ' . date('Y-m-d H:i:s') . "\n",
		 'key: ' . $key . "\n", 
		 'uname: ' . $uname . "\n",
		 'type: ' . $typ . "\n",
		 'comment: ' . preg_replace('/[\r\n]/','$$$',$comment) . "\n",
		 'data: ' . "\n",
		 "\n",
		 $val);
    if(file_exists($fn)){
      $old = file($fn);
      if($old[0]=='syntax: ' . $this->syntax . "\n"){
	$new[1] = $old[1]; // intial value -> no change allowed
	$new[2] = $old[2]; // intial value -> no change allowed
	$old[3] = $new[3]; // change is not 'important'
	while(count($old)>11){
	  $x = array_pop($old);
	  $old[count($old)-1] .= $x;
	}
	if($old===$new) return $this->err->ok(-1);
      }

      $this->log('m',$key,$typ,$uname);
    } else $this->log('c',$key,$typ,$uname);

    $fi = fopen($fn,'w');
    foreach($new as $cl) fwrite($fi,$cl);
    fclose($fi);
    return $this->err->ok();	   
  }

  public function get($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $fi = $this->read_line($fn,8,FALSE);
    $res = '';
    while($nl = fgets($fi)) $res .= $nl;
    $this->log('a',$key,$typ,$uname);
    return unserialize(trim($res));
  }


  public function delete($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $this->log('d',$key,$typ,$uname);
    unlink($fn);
  }



  public function complete($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2ktu($key,$typ,$uname);
    $fn = $this->_exists($key,$typ,$uname);
    if($fn===FALSE) return $this->err->err(5);
    $dat = file($fn);
    $res = array();
    while(substr($cl=array_shift($dat),0,4)!=='data'){
      $cl = explode(':',$cl,2);
      $res[trim($cl[0])] = trim($cl[1]);
    }
    array_shift($dat);
    $res['data'] = unserialize(implode('',$dat));
    return $res;
  }

  public function listitems($key=FALSE,$typ=FALSE,$uname=FALSE){
    $tmp = $this->_listitems_prepare($key,$typ,$uname);
    if(is_null($tmp)) $this->err->err(21);
    list($key,$typ,$uname) = $tmp;

    $files = scandir($this->bag[0]);
    $files = preg_grep('/^' . $this->bag[1] . '___K.*___T.*___U.*$/',$files);
    if(empty($files)) return array();
    $res = array();
    foreach($files as $cf){
      list($ck,$ct,$cu) = array_values($this->id_int2ktu($cf));
      if($key!==FALSE and $ck!=$key) continue;
      if($typ!==FALSE and $ct!=$typ) continue;
      if($uname!==FALSE and $cu!=$uname) continue;
      $cf = $this->id_int2str($cf);
      $res[$cf] = array('key'=>$ck,'type'=>$ct===FALSE?NULL:$ct,'uname'=>$cu===FALSE?NULL:$cu,'id'=>$cf);
    }
    return $res;
  }

  public function listlogs($key=FALSE,$typ=FALSE,$uname=FALSE){
    $tmp = $this->_listitems_prepare($key,$typ,$uname);
    if(is_null($tmp)) $this->err->err(21);
    list($key,$typ,$uname) = $tmp;

    $files = scandir($this->logbag[0]);
    $files = preg_grep('/^' . $this->logbag[1] . '___[dcma]\d{14}___K.*___T.*___U.*$/',$files);
    if(empty($files)) return array();
    $res = array();
    foreach($files as $cf){
      list($cm,$cd,$ck,$ct,$cu) = array_values($this->id_log2ktu($cf));
      if($key!==FALSE and $ck!=$key) continue;
      if($typ!==FALSE and $ct!=$typ) continue;
      if($uname!==FALSE and $cu!=$uname) continue;
      $id = $cm . '/' . $cd . '/' . $this->id_ktu2str($ck,$ct,$cu);
      $res[$id] = array('key'=>$ck,'type'=>$ct,'uname'=>$cu,'id'=>$id,
			'mode'=>$cm,'date_logged'=>$cd);
    }
    return $res;
  }

  protected function log($mode,$key,$typ,$uname){
    if(strpos($this->logmode,$mode)!==FALSE) return;
    if(is_null($this->logbag)) return;
    $fl = $this->id_ktu2log($mode,$key,$typ,$uname);
    switch($mode){
    case 'a': touch($fl); break;
    case 'c': touch($fl); break;
    case 'd': copy($this->id_ktu2int($key,$typ,$uname),$fl); break;
    case 'm': copy($this->id_ktu2int($key,$typ,$uname),$fl); break;
    }
  }





  public function id_str2int($key){
    return $this->bag[0] . '/' . $this->bag[1] . '___' . str_repalce('/','___',$key);
  }

  public function id_ktu2int($key,$typ=NULL,$uname=NULL){
    if(!$this->test_key($key)) return 22;
    if(!$this->test_type($typ)) return 24;
    if(!$this->test_uname($uname)) return 25;
    return $this->bag[0] . '/' . $this->bag[1] . "___K${key}___T${typ}___U${uname}";
  }
  public function id_int2str($key){
    return str_replace('___','/',substr($key,3+strpos($key,'___')));
  }

  public function id_int2ktu($key){
    $res = array();
    $parts = explode('___',$key);
    array_shift($parts);
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
    return $this->logbag[0] . '/' . $this->logbag[1] . "___${mode}${fm}___K${key}___T${typ}___U${uname}";
  }

  protected function id_log2ktu($key){
    $res = array();
    $parts = explode('___',$key);
    array_shift($parts);
    $cm = array_shift($parts);
    $res['mode'] = substr($cm,0,1);
    $res['date'] = substr($cm,1);
    foreach($parts as $ci) $res[$this->chars[substr($ci,0,1)]] = substr($ci,1);
    return $res;


  }

  public function getlog($logid){
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
    $ktu = array();
    $parts = explode('/',$logid);
    $ktu['mode'] = array_shift($parts);
    $ktu['date_logged'] = array_shift($parts);
    foreach($parts as $ci) $ktu[$this->chars[substr($ci,0,1)]] = substr($ci,1);
    $fl = $this->id_ktu2log($ktu['mode'],$ktu['key'],$ktu['type'],$ktu['uname'],$ktu['date_logged']);
    if(file_exists($fl)) unlink($fl);
    else return $this->err->err(31);
  }
}

?>
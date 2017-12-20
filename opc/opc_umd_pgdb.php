<?php
  /* member_list including all includes! */

class opc_umd_db_pg extends opc_umd {
  protected $db = NULL;
  

  protected $pg_typ = array();
  protected $pg_fields = array();
  protected $pg_free = array();
  protected $pg_quest = array();
  /* access: view, create, edit, delete, execute, list, entry, entry-auto, entry-admin, leave
   *  ced is short for create, edit, delete */
  protected $pg_acc = array();

  public $seq = 'seq_um';

  function connect($con){
    $this->connected = FALSE;
    $this->tool->req_files('opc_pg');
    $dbcon = $this->tool->load_connection('pgdb',def($con,'db'));
    if(is_numeric($dbcon)) return 31;
    $this->db = new opc_pg($dbcon);
    if(!$this->db->is_connected()) return 32;
    $tmp = $this->db_test();
    if($tmp>0) return $tmp;
    $this->connected = TRUE;
    return 0;
  }

  function db_test(){
    $sql = 'SELECT count(*) FROM pg_tables WHERE schemaname=\'public\''
      . ' AND tablename=\'um\'';
    if($this->db->read_field($sql)!=1) return 33;
    $sql = 'SELECT uval, uid FROM um WHERE ufa IS NULL';
    $this->pg_typ = $this->db->read_column($sql,1,0);
    $sql = 'SELECT uval, uid FROM um WHERE ufa=' . $this->pg_typ['field'];
    $this->pg_fields = $this->db->read_column($sql,1,0);
    $sql = 'SELECT uval, uid FROM um WHERE ufa=' . $this->pg_typ['freevalue'];
    $this->pg_free = $this->db->read_column($sql,1,0);
    $sql = 'SELECT uval, uid FROM um WHERE ufa=' . $this->pg_typ['quest'];
    $this->pg_quest = $this->db->read_column($sql,1,0);
    $sql = 'SELECT uval, uid FROM um WHERE ufa=' . $this->pg_typ['acc'];
    $this->pg_acc = $this->db->read_column($sql,1,0);
    return 0;
  }


  function log($add){
    trigger_error('umd_pgsb: log not yet working');
  }


  function user_key($uname,$dom){
    if($dom!=$this->key) return NULL;
    $tmp = 'SELECT uid FROM um WHERE ufa=' . $this->pg_typ['user']
      . ' AND uval=\'' . pg_escape_string($uname) . '\'';
    $tmp = $this->db->read_column($tmp);
    return count($tmp)==1?$tmp[0]:NULL;
  }

  function _u_count(){
    $res = array();
    $sql = 'SELECT count(*) FROM um WHERE ufa=' . $this->pg_typ['user'];
    return $this->db->read_field($sql);
  }


  function _u_list($offlim=NULL){
    $res = array();
    $sql = 'SELECT uid FROM um WHERE ufa=' . $this->pg_typ['user'];
    if(is_array($offlim)) 
      $sql .= ' ORDER BY uval OFFSET ' . (int)$offlim[0] . ' LIMIT ' . (int)$offlim[1];
    return $this->db->read_column($sql);
  }
  
  function _u_list_hasdata($kinds){
    $ufb = array();
    foreach($kinds as $ckind){
      if($ckind=='@grp')
	$ufb = array_merge($ufb,$this->list_ab('u',$this->pg_typ['group'],NULL));
      else if(isset($this->pg_fields[$ckind]))
	$ufb[] = $this->pg_fields[$ckind];
    }
    $tid = $this->pg_typ['user'];
    $sql = "SELECT uid, ufa IS NOT NULL FROM (SELECT uid FROM um WHERE ufa=$tid) a"
      . ' LEFT JOIN (SELECT DISTINCT ufa FROM um WHERE ufb IN (' 
      . implode(',',$ufb) . ')) b ON a.uid = b.ufa';
    $res = $this->db->read_column($sql,1,0);
    return array_map(create_function('$x','return $x==\'t\';'),$res);
  }
  
  function u_umd_val($uid){
    $sql = 'SELECT uval FROM um WHERE ufb=12 AND ufa=' . (int)$uid;
    $tmp = $this->db->read_field($sql);
    return empty($tmp)?$this->key:$tmp;
  }

  // ??
  function g_name2id($grp){
    $tmp = $this->um->groups->get($this->um->gdn_make($grp,$this->key));
    return $tmp['id'];
  }

  protected function g_ulist($gid){
    $gid = (int)$gid;
    $sql = 'SELECT a.ufa, a.uval  FROM um a JOIN um b ON a.ufa=b.uid'
      . ' WHERE b.ufa=' . $this->pg_typ['user'] . ' AND a.ufb=' . $gid;
    $tmp = $this->db->read_column($sql,1,0);
    return $tmp;
  }

  protected function g_ucount($gid){
    $gid = (int)$gid;
    $sql = 'SELECT a.uval, count(*)  FROM um a JOIN um b ON a.ufa=b.uid'
      . '  WHERE b.ufa=' . $this->pg_typ['user']
      . ' AND a.ufb=' . $gid . ' GROUP by a.uval';
    return $this->db->read_column($sql,1,0);
  }

  function pwd_byKey($key){
    $sql = 'SELECT uval FROM um WHERE ufb=11 AND ufa=' . (int)$key;
    return $this->db->read_field($sql);
  }


  protected function u_pwd_isset($uid)   { 
    return $this->count_at($uid,'pwd')>0?0:7;
  }

  protected function u_email_isset($uid) {
    return $this->count_af($uid,'email')>0?0:7;
  }

  function _u_info_type($uid,$key,$def){
    return $def;
  }

  function id_remove($id){
    $sql = 'SELECT * FROM um WHERE uid=' . (int)$id;
    $row = $this->db->read_row($sql);
    if(empty($row) or is_null($row['ufb'])) return 4501;
    $sqls = $this->sqls_delete_deep($id);
    return $this->db->sqls_execute($sqls)===FALSE?4501:0;
  }

  function info_set_id($id,$val){
    return $this->update_iv($id,$val)===FALSE?4504:0;
  }

  function id_details($id){
    $sql = 'SELECT * FROM um WHERE uid=' . (int)$id;
    $row = $this->db->read_row($sql);
    if(empty($row)) return array();
    $res = array(':v'=>$row['uval']);
    if(is_null($row['ufa'])){
      $res[':t'] = 'internal';
    } else if(is_null($row['ufb'])){
      $res[':t'] = $this->get_i('v',$row['ufa']);
    } else {
      $res[':t'] = $this->get_i('v',$row['ufb']);
    }
    return $res;
  }

  function _u_details($uid,$key){
    if($key=='uname'){
      $res = $this->get_at($uid,'user');
      return array(':m'=>FALSE,':f'=>1,':a'=>'v',
		   ':id'=>$uid,':v'=>$res,':c'=>FALSE);
    } else {
      $tid = def($this->pg_fields,$key);
      if(is_null($tid)) return NULL;
      $res = $this->list_af($uid,$key);
      $ids = is_array($res)?array_keys($res):array();
      $tmp =  $this->data_decode($res,$key)<=0;
      $occ = def(def($this->fields,$key,array()),'occurence','an');
      if(substr($occ,1,1)=='1'){
	if($tmp){
	  return array(':m'=>FALSE,':f'=>1,':a'=>'ved',
		       ':id'=>array_shift($ids),':v'=>$res,':c'=>FALSE);
	} else {
	  return array(':m'=>FALSE,':f'=>0,':a'=>'ved',
		       ':id'=>NULL,':v'=>NULL,':c'=>TRUE);
	}
      } else {
	if($tmp){
	  return array(':m'=>TRUE,':f'=>count($ids),
		       ':a'=>array_fill(0,count($ids),'ved'),
		       ':id'=>$ids,':v'=>array_values($res),':c'=>TRUE);
	} else {
	  return array(':m'=>TRUE,':f'=>0,':a'=>array(),
		       ':id'=>array(),':v'=>array(),':c'=>TRUE);
	}
      }
    }
  }

  function _u_info($uid,$key,&$res){
    if($key=='uname'){
      $res = $this->get_at($uid,'user');
      return !is_null($res);
    } else if($key=='dispname' and $this->count_af($uid,$key)==0){
      $res = $this->get_at($uid,'label');
      if(!is_null($res)) return TRUE;
      $res = $this->um->user_disp($this->id2vdn($uid),NULL,TRUE);
      if(is_null($res)) return FALSE;
      $tid = $this->pg_typ['label'];
      $this->insert_abv($uid,$tid,$res);
      return TRUE;
    } else {
      $res = $this->list_af($uid,$key);
      return $this->data_decode($res,$key)<=0;
    }
  }

  function g_info($gid,$key,&$res){
    if(!isset($this->pg_fields[$key])) return FALSE;
    $fid = $this->pg_fields[$key];
    $res = $this->list_ab('v',$gid,$fid);
    return $this->data_decode($res,$key)<=0;
  }

  function g_label_set($gid,$val){
    if(!isset($this->pg_typ['label'])) return 2;
    $fid = $this->pg_typ['label'];
    $id = $this->get_av('i',$gid,$fid);
    if(is_null($id)) 
      return $this->insert_abv($gid,$fid,$val)===FALSE?4502:0;
    return $this->update_iv($id,$val)===FALSE?4502:0;
  }


  function g_info_set($gid,$key,$val){
    if(!isset($this->pg_fields[$key])) return 2;
    $fid = $this->pg_fields[$key];
    $id = $this->get_av('i',$gid,$fid);
    if(is_null($id)) 
      return $this->insert_abv($gid,$fid,$val)===FALSE?4502:0;
    return $this->update_iv($id,$val)===FALSE?4502:0;
  }


  function data_encode(&$data,$key){
    return TRUE;
  }

  function data_decode(&$data,$key){
    if(is_null($data)) return 1;
    $set = def($this->fields,$key,array());
    $occ = def($set,'occurence','an');
    $typ = def($set,'dtype','string');
    $mth = 'data_dec__' . $typ;

    switch(substr($occ,1,1)){
    case '1':
      $data = array_shift($data);
      return $this->$mth($data,$set);

    case 'n':
      if(count($data)==0) return -1;
      $tmp = array();
      $ak = array_keys($data);
      foreach($ak as $ck) $tmp[$ck] = $this->$mth($data[$ck],$set);
      return max($tmp);
    }
    return 2;
  }

  protected function data_dec__string(&$data,$set){
    return 0;
  }

  protected function uval2id($uval,$ufa=NULL,$ufb=NULL,$multi=TRUE){
    $sql = 'SELECT uid FROM um WHERE uval=\'' . pg_escape_string($uval) . '\'';
    if(!is_null($ufa)) $sql .= ' AND ufa=' . (int)$ufa;
    if(!is_null($ufb)) $sql .= ' AND ufb=' . (int)$ufb;
    $res = $this->db->read_column($sql);
    if($multi) return (array)$res;
    if(count($res)>1) return FALSE;
    return array_shift($res);
  }
  
  
  function _u_info_keys($uid){
    $sql = 'select i.uval from um u JOIN um i ON u.ufb=i.uid WHERE i.ufa=8 AND u.ufa=' . (int)$uid;
    $res = (array)$this->db->read_column($sql);
    return array_fill_keys($res,$this->key);
  }

  function load($what,$key,$def=NULL){
    $key = (int)$key;
    switch($what){
    case 'right':
      $res = array('id'=>$this->get_i('v',$key),
		   'label'=>$this->get_at($key,'label'),
		   'description'=>$this->get_at($key,'description'),
		   'incl'=>$this->db->read_column('SELECT rv FROM umcp WHERE lt=6 and rt=6 AND ufa=' . $key),
		   'acc'=>$this->load_acc($key));
      break;

    case 'group':
      $res = array('id'=>$this->get_i('v',$key),
		   'label'=>$this->get_at($key,'label'),
		   'description'=>$this->get_at($key,'description'),
		   'rights'=>$this->db->read_column('SELECT rv FROM umcp WHERE lt=5 and rt=6 AND ufa=' . $key),
		   'incl'=>$this->db->read_column('SELECT rv FROM umcp WHERE lt=5 and rt=5 AND ufa=' . $key),
		   'gtype'=>$this->get_ab($key,17),
		   'acc'=>$this->load_acc($key));
      $par = $this->get_auto(FALSE,$key,TRUE,'parent',0);
      $res['parent'] = is_null($par)?NULL:$this->id2gdn($par);
      break;

    case 'field':
      $res = array('id'=>$this->get_i('v',$key),
		   'label'=>$this->get_at($key,'label'),
		   'occurence'=>$this->get_at($key,'occurence'),
		   'acc'=>$this->load_acc($key));
      break;

    case 'quest':
      $res = array('id'=>$this->db->read_field('SELECT uval FROM um WHERE uid=' . $key),
		   'label'=>$this->db->read_field('SELECT uval FROM umc WHERE ufb=9 and ufa=' . $key),
		   'acc'=>$this->load_acc($key));
      break;

    default:
      qx();
      qa();
      $res = $def;
    }
    return array_filter($res);
  }
  
  protected function load_acc($key){
    $sql = 'SELECT uval, ufb FROM umc WHERE ufb IN (' . implode(',',$this->pg_acc) . ')'
      . ' AND ufa=' . $key;
    $tmp = $this->db->read_column($sql,0,1);
    if(empty($tmp)){
      if(in_array($key,$this->pg_fields) and isset($this->pg_quest['u:value'])){
	$sql = 'SELECT uval, ufb FROM umc WHERE ufb IN (' . implode(',',$this->pg_acc) . ')'
	  . ' AND ufa=' . $this->pg_quest['u:value'];
	$tmp = (array)$this->db->read_column($sql,0,1);
      } else $tmp = array();
    }
    if(isset($tmp[$this->pg_acc['ced']])){
      $tmp[$this->pg_acc['create']] = $tmp[$this->pg_acc['ced']];
      $tmp[$this->pg_acc['edit']] = $tmp[$this->pg_acc['ced']];
      $tmp[$this->pg_acc['delete']] = $tmp[$this->pg_acc['ced']];
    }
    $acc = array();
    foreach($this->pg_acc as $ck=>$cv) 
      if($ck!='ced') $acc[$ck] = def($tmp,$cv,'nobody');
    return $acc;
  }

  function u_groups($uid){
    $sql = 'select u.uval as mt, i.uval as id from um u JOIN um i ON u.ufb=i.uid WHERE i.ufa=5'
      . ' AND u.ufa=' . (int)$uid;
    $res = (array)$this->db->read_column($sql,0,1);
    foreach($res as $key=>$val)
      $res[$key] = array('key'=>$key,'mtyp'=>$val);
    return $res;
  }

  function u_rights($uid){
    $sql = 'select i.uval from um u JOIN um i ON u.ufb=i.uid WHERE i.ufa=6 AND u.ufa=' . (int)$uid;
    $tmp = $this->db->read_column($sql);
    if(empty($tmp)) return array();
    return array_combine($tmp,array_fill(0,count($tmp),TRUE));
  }


  protected function u_group_add($uid,$gdn,$mtyp){
    $gid = $this->gdn2id($gdn);
    if(is_null($gid)) return 402;
    switch($this->count_ab($uid,$gid)){
    case 0:
      return FALSE===$this->insert_abv($uid,$gid,$mtyp)?404:0;
    case 1:
      return -1;
    default:
      trigger_error("umd-$this->key: user $uid is more than once in $gdn");
      return 901;
    }
  }

  protected function u_group_remove($uid,$gdn){
    $gid = $this->gdn2id($gdn);
    if(is_null($gid)) return 402;
    $this->delete_ab($uid,$gid);
    return 0;
  }

  protected function u_group_change($uid,$gdn,$add){
    $gid = $this->gdn2id($gdn);
    if(is_null($gid)) return 402;
    if($this->count_ab($uid,$gid)==0) return 405;
    if(isset($add['mtyp'])){
      $mtyp = $add['mtyp'];
      if(!in_array($mtyp,$this->um->cat_mtyp)) return 1;
      return $this->update_abv($uid,$gid,$mtyp)===FALSE?406:0;
    }
    return 12;
    return 0;
  }


  protected function sqlp_select($what){
    switch($what){
    case 'i':  return 'uid';
    case 'v':  return 'uval';
    case 'a':  return 'ufa';
    case 'b':  return 'ufb';
    case 'iv': return 'uid, uval';
    }
    return '*';
  }


  protected function sqls_delete_deep($id){
    $chld = array();
    $tmp = array((int)$id);
    $sqls = array('DELETE FROM um WHERE uid=' . (int)$id);
    do{
      $sql = 'SELECT uid FROM um WHERE ufa IN (' . implode(',',$tmp) . ')';
      $tmp = (array)$this->db->read_column($sql);
      $tmp = array_diff($tmp,$chld);
      $chld = array_merge($tmp,$chld);
      if($tmp)
	array_unshift($sqls,'DELETE FROM um WHERE uid IN (' . implode(',',$tmp) . ')');
    } while($tmp);
    return $sqls;
  }

  protected function sql_where_ab($ufa,$ufb,$prefix=' WHERE '){
    if(is_null($ufa)) $a = 'IS NULL'; else $a = '=' . (int)$ufa;
    if(is_null($ufb)) $b = 'IS NULL'; else $b = '=' . (int)$ufb;
    return $prefix . ' ufa ' . $a . ' AND ufb ' . $b;
  }

  protected function sql_update_iv($id,$uval){
    return 'UPDATE um SET uval=\'' . pg_escape_string($uval) . '\''
      . ' WHERE uid=' . (int)$id;
  }

  protected function sql_update_iabv($id,$ufa,$ufb,$uval){
    return 'UPDATE um SET uval=\'' . pg_escape_string($uval) . '\''
      . ', ufa = ' . (is_null($ufa)?'NULL':(int)$ufa)
      . ', ufb = ' . (is_null($ufb)?'NULL':(int)$ufb)
      . ' WHERE uid=' . (int)$id;
  }

  protected function sql_update_abv($ufa,$ufb,$uval){
    return 'UPDATE um SET uval=\'' . pg_escape_string($uval) . '\''
      . $this->sql_where_ab($ufa,$ufb);
  }

  protected function sql_delete_id($id){
    return 'DELETE FROM UM WHERE uid=' . (int)$id;
  }

  protected function sql_delete_ab($ufa,$ufb){
    return 'DELETE FROM UM' . $this->sql_where_ab($ufa,$ufb);
  }

  protected function sql_delete_a($ufa){
    return 'DELETE FROM UM WHERE ufa=' . (int)$ufa;
  }

  protected function sql_delete_b($ufb){
    return 'DELETE FROM UM WHERE ufb=' . (int)$ufb;
  }

  protected function sql_insert_abv($ufa,$ufb,$uval){
    $a = is_null($ufa)?'NULL':((int)$ufa);
    $b = is_null($ufb)?'NULL':((int)$ufb);
    return 'INSERT INTO um(ufa,ufb,uval) VALUES(' . $a . ','
      . $b . ',\'' . pg_escape_string($uval) . '\')';
  }

  protected function sql_insert_iabv($uid,$ufa,$ufb,$uval){
    $i = is_null($uid)?$this->id_next():((int)$uid);
    $a = is_null($ufa)?'NULL':((int)$ufa);
    $b = is_null($ufb)?'NULL':((int)$ufb);
    return 'INSERT INTO um(uid,ufa,ufb,uval) VALUES(' . $i . ','
      . $a . ',' . $b . ',\'' . pg_escape_string($uval) . '\')';
  }

  protected function update_iv($id,$uval){
    return $this->db->sql_execute($this->sql_update_iv($id,$uval));
  }

  protected function update_iabv($id,$ufa,$ufb,$uval){
    return $this->db->sql_execute($this->sql_update_iabv($id,$ufa,$ufb,$uval));
  }

  protected function update_abv($ufa,$ufb,$uval){
    return $this->db->sql_execute($this->sql_update_abv($ufa,$ufb,$uval));
  }

  protected function delete_id($id){
    return $this->db->sql_execute($this->sql_delete_id($id));
  }

  protected function delete_ab($ufa,$ufb){
    return $this->db->sql_execute($this->sql_delete_ab($ufa,$ufb));
  }

  protected function delete_a($ufa){
    return $this->db->sql_execute($this->sql_delete_a($ufa));
  }

  protected function delete_b($ufa){
    return $this->db->sql_execute($this->sql_delete_a($ufa));
  }

  protected function insert_abv($ufa,$ufb,$uval){
    return $this->db->sql_execute($this->sql_insert_abv($ufa,$ufb,$uval));
  }

  protected function insert_iabv($uid,$ufa,$ufb,$uval){
    return $this->db->sql_execute($this->sql_insert_iabv($uid,$ufa,$ufb,$uval));
  }

  protected function count_ab($ufa,$ufb){
    $sql = 'SELECT count(*) FROM um' . $this->sql_where_ab($ufa,$ufb);
    return $this->db->read_field($sql);
  }

  protected function count_abv($ufa,$ufb,$uval){
    $sql = 'SELECT count(*) FROM um' . $this->sql_where_ab($ufa,$ufb)
      . ' AND uval=\'' . pg_escape_string($uval) . '\'';
    return $this->db->read_field($sql);
  }

  protected function count_bv($ufb,$uval){
    $sql = 'SELECT count(*) FROM um WHERE ufb=' . ((int)$ufb)
      . ' AND uval=\'' . pg_escape_string($uval) . '\'';
    return $this->db->read_field($sql);
  }

  protected function list_ab($what,$ufa,$ufb){
    $wk = $this->sql_where_ab($ufa,$ufb);
    $sl = $this->sqlp_select($what);
    $sql = 'SELECT ' . $sl  . ' FROM um' . $wk;
    if(strpos($sl,',')===FALSE)
      return (array)$this->db->read_column($sql);
    else
      return (array)$this->db->read_column($sql,1,0);
  }

  protected function list_abv($ufa,$ufb,$uval){
    $sql = 'SELECT uid FROM um' . $this->sql_where_ab($ufa,$ufb)
      . ' AND uval=\'' . pg_escape_string($uval) . '\'';
    return (array)$this->db->read_column($sql);
  }

  protected function get_ab($ufa,$ufb){
    $sql = 'SELECT uval FROM um' . $this->sql_where_ab($ufa,$ufb);
    return $this->db->read_field($sql);
  }

  protected function get_abd($ufa,$ufb,$def=NULL){
    $sql = 'SELECT uval FROM um' . $this->sql_where_ab($ufa,$ufb);
    $tmp = $this->db->read_field($sql);
    return is_null($tmp)?$def:$tmp;
  }

  protected function get_auto_wk($key,$val,$isnum=TRUE){
    if(is_bool($val))    return NULL;
    if(is_null($val))    return $key . ' IS NULL';
    if(!is_scalar($val)) return NULL;
    if($isnum)           return $key . ' = ' . $val;
    return $key . ' = \'' . pg_escape_string($val) . '\'';
  }

  protected function get_auto($uid=FALSE,$ufa=FALSE,$ufb=FALSE,$uval=FALSE,
			      $how=0){
    $sql = 'SELECT ';
    if($uid===TRUE)  $sql .= 'uid, ';
    if($ufa===TRUE)  $sql .= 'ufa, ';
    if($ufb===TRUE)  $sql .= 'ufb, ';
    if($uval===TRUE) $sql .= 'uval, ';
    $sql = substr($sql,0,-2) . ' FROM um WHERE 1=1';
    if(!is_bool($uid))  $sql .= ' AND ' . $this->get_auto_wk('uid',$uid,TRUE);
    if(!is_bool($ufa))  $sql .= ' AND ' . $this->get_auto_wk('ufa',$ufa,TRUE);
    if(!is_bool($ufb))  $sql .= ' AND ' . $this->get_auto_wk('ufb',$ufb,TRUE);
    if(!is_bool($uval)) $sql .= ' AND ' . $this->get_auto_wk('uval',$uval,FALSE);
    switch($how){
    case 0: return $this->db->read_field($sql);
    }
  }


  protected function get_av($what,$ufa,$ufb){
    $wk = $this->sql_where_ab($ufa,$ufb);
    $sl = $this->sqlp_select($what);
    $sql = 'SELECT ' . $sl  . ' FROM um' . $wk;
    return $this->db->read_field($sql);
  }

  protected function get_i($what,$uid){
    switch($what){
    case 'a': $sql = 'ufa'; break;
    case 'b': $sql = 'ufb'; break;
    case 'v': $sql = 'uval'; break;
    default:
      return NULL;
    }
    $sql = 'SELECT ' . $sql . ' FROM um WHERE uid=' . (int)$uid;
    return $this->db->read_field($sql);
  }

  // get by ufa id and type of ufb
  protected function get_at($ufa,$typ){
    if(!isset($this->pg_typ[$typ])) return NULL;
    $sql = 'SELECT uval FROM um WHERE ufb=' . $this->pg_typ[$typ]
      . ' AND ufa=' . (int)$ufa;
    return $this->db->read_field($sql);
  }

  // get by ufa id and type of ufb
  protected function count_at($ufa,$typ){
    if(!isset($this->pg_typ[$typ])) return 0;
    $sql = 'SELECT count(*) FROM um WHERE ufb=' . $this->pg_typ[$typ]
      . ' AND ufa=' . (int)$ufa;
    return $this->db->read_field($sql);
  }

  // get by ufa id and type of ufb
  protected function list_af($ufa,$field){
    if(!isset($this->pg_fields[$field])) return NULL;
    $sql = 'SELECT uval, uid FROM um WHERE ufb=' . $this->pg_fields[$field]
      . ' AND ufa=' . (int)$ufa;
    return $this->db->read_column($sql,0,1);
  }

  // get by ufa id and type of ufb
  protected function count_af($ufa,$field){
    if(!isset($this->pg_fields[$field])) return 0;
    $sql = 'SELECT count(*) FROM um WHERE ufb=' . $this->pg_fields[$field]
      . ' AND ufa=' . (int)$ufa;
    return $this->db->read_field($sql);
  }

  protected function replace_abv($ufa,$ufb,$uval){
    $ids = $this->list_ab('i',$ufa,$ufb);
    if(empty($ids))
      return $this->insert_abv($ufa,$ufb,$uval);
    else if(count($ids)>1) 
      return FALSE;
    else
      return $this->update_iv(array_shift($ids),$uval);
  }

  protected function replace_abvs($ufa,$ufb,$uvals){
    if(!is_array($uvals)) $uvals = array($uvals);
    $ids = $this->list_ab('i',$ufa,$ufb);
    $n = count($uvals);
    $m = count($ids);
    for($i=0;$i<min($n,$m);$i++)
      return $this->update_iv(array_shift($ids),array_shift($uvals));
    foreach($ids as $id) $this->delete_id($id);
    foreach($uvals as $uval)
      return $this->insert_abv($ufa,$ufb,$uval);
    return 0;
  }



  function search_all($kind){
    if(isset($this->pg_typ[$kind]))
      return (array)$this->db->read_column('SELECT uid FROM um WHERE ufa=' . $this->pg_typ[$kind]);
    qx("search all: $kind");
  }

  /* returns 0: success, 1: unkown user, 2: wrong password */
  protected function pwd_get($uname,$case='login'){
    qa();
    $sql = 'SELECT * FROM umc WHERE typ=4';
    return $this->db->read_field($sql);
  }

  function start__load_access_rules(){
    $res = array();
    $sql = 'SELECT * FROM umc WHERE typ=4';
    foreach((array)$this->db->read_array($sql) as $cval){
      if(!isset($res[$cval['ufa']])) $res[$cval['ufa']] = array();
      $res[$cval['ufa']][$cval['cat']] = $cval['uval'];
    }
    $this->rules = $res;
    return 0;
  }


  protected function user_add($uname,$dval,$dres=NULL,&$uid){
    // check if no duplicate
    if(!is_null($this->vdn_search($uname,$dval))) return 6;
    $uid = $this->id_next();
    if(is_null($uid)) return 7;

    $sqls = array($this->sql_insert_iabv($uid,2,NULL,$uname));
    if($dval!=$this->key) 
      $sqls[] = $this->sql_insert_abv($uid,12,$dval);
    if(!is_null($dres) and $dres!=$this->key)
      $sqls[] = $this->sql_insert_abv($uid,13,$dres);
    $tmp = $this->db->sqls_execute($sqls);
    return $tmp===FALSE?8:0;
  }

  function user_create($data){
    $def = array('uname'=>NULL,'groups'=>array(),
		 'pwd'=>NULL,
		 'umd_val'=>$this->key,'umd_res'=>$this->key);
    $tmp = ops_array::extract($data,array_keys($def),$def);
    list($uname,$grps,$pwd,$dval,$dres) = array_values($tmp);
    if(empty($uname)) return 5;

    // check if no duplicate
    if(!is_null($this->vdn_search($uname,$dval))) return 6;
    $uid = $this->id_next();
    if(is_null($uid)) return 7;

    if($dres!=$this->key){
      qx('user create: dval!=dres');
      return 12;
    }

    // coolect all sqls to execute as commit-block
    $sqls = array();

    if(is_null($tid = def($this->pg_typ,'user'))) return 6;
    $sqls[] = $this->sql_insert_iabv($uid,$tid,NULL,$uname);

    if(!empty($pwd)){
      if(is_null($tid = def($this->pg_typ,'pwd'))) return 6;
      $sqls[] = $this->sql_insert_abv($uid,$tid,md5($pwd));
    }

    foreach($grps as $cgrp){
      $gid = $this->gdn2id($cgrp);
      if(is_null($gid)){
	qx('create user add external group or nonexisting group');
      } else $sqls[] = $this->sql_insert_abv($uid,$gid,'member');
    }

    foreach($data as $key=>$val){
      if(isset($this->pg_fields[$key])){
	$sqls[] = $this->sql_insert_abv($uid,$this->pg_fields[$key],$val);
      } else qx("create user - unkwon fields: $key");
    }
    //q7($sqls); return 9999;
    $tmp = $this->db->sqls_execute($sqls);
    return $tmp===FALSE?8:0;
  }

  function group_create($data){
    $def = array('gname'=>NULL,'gtype'=>NULL,'parent'=>NULL,
		 'owner'=>NULL,'umd_val'=>$this->key,'label'=>NULL);
    $tmp = ops_array::extract($data,array_keys($def),$def);
    list($gname,$gtype,$par,$owner,$dval,$lab) = array_values($tmp);
    if(empty($gname)) return 5;
    // check if no duplicate
    if(!is_null($this->gdn_search($gname,$dval))) return 6;
    $gid = $this->id_next();
    if(is_null($gid)) return 7;

    $tid = def($this->pg_typ,'group');
    if(is_null($tid)) return 6;
    $sqls = array($this->sql_insert_iabv($gid,$tid,NULL,$gname));

    if(!empty($lab))
      $sqls[] = $this->sql_insert_abv($gid,9,$lab);

    if(!empty($owner)){
      foreach((array)$owner as $vdn){
	$uid = $this->vdn2id_auto($vdn);
	if(!is_null($uid)) $sqls[] = $this->sql_insert_abv($uid,$gid,'owner');
      }
    }

    if(!empty($par)){
      $pid = $this->gdn2id($par);
      if(!is_null($pid)) $sqls[] = $this->sql_insert_abv($gid,$pid,'parent');
    }

    if(!is_null($gtype)){
      $tid = def($this->pg_typ,'gtype');
      if(!is_null($tid)) $sqls[] = $this->sql_insert_abv($gid,$tid,$gtype);
    }

    foreach($data as $key=>$val){
      if(isset($this->pg_fields[$key])){
	$sqls[] = $this->sql_insert_abv($gid,$this->pg_fields[$key],$val);
      } else qx('create group add unkwon field: ' . $key);
    }
    //q7($sqls);return 1975;
    $tmp = $this->db->sqls_execute($sqls);
    return $tmp===FALSE?8:0;
  }


  function g_remove($gid){
    if($this->delete_b($gid)===FALSE) return 3;
    if($this->delete_a($gid)===FALSE) return 3;
    if($this->delete_id($gid)===FALSE) return 3;
    return 0;
  }

  // returns uid if this user is known here otherwise NULL
  protected function vdn_search($uname,$val){
    $un = pg_escape_string($uname);
    $dv = pg_escape_string($val);
    $sql = "SELECT uid FROM um_dval WHERE uname='$un' AND dval='$dv'";
    return $this->db->read_field($sql);
  }

  // returns uid if this user is known here otherwise NULL
  protected function gdn_search($gname,$val){
    $gn = pg_escape_string($gname);
    $dv = pg_escape_string($val);
    $sql = "SELECT uid FROM um_gval WHERE gname='$gn' AND dval='$dv'";
    return $this->db->read_field($sql);
  }

  protected function _u_info_set($uid,$key,$val){
    $uid = (int)$uid;
    $sql = 'SELECT uid FROM um WHERE ufa=8 AND uval=\'' . pg_escape_string($key) . '\'';
    $fid = $this->db->read_field($sql);
    if(empty($fid)) return 4505;
    $id = $this->get_av('i',$uid,$fid);
    if(is_null($id)) 
      return $this->insert_abv($uid,$fid,$val)===FALSE?4502:0;

    $occ = def($this->pg_typ,'occurence','an');
    if(is_numeric($occ)) $occ = $this->get_abd($fid,$occ,'an');
    switch($occ){
    case 'an': 
      return $this->insert_abv($uid,$fid,$val)===FALSE?4502:0;
    case 'a1': case 'u1':
      return $this->update_iv($id,$val)===FALSE?4502:0;
    }
    return 3103;
  }

  protected function _u_info_setn($uid,$data){
    $uid = (int)$uid;
    $sql = array();
    $res = array();
    $sav = array();
    foreach($data as $key=>$val){
      $fid = $this->db->read_field('SELECT uid FROM um WHERE ufa=8 AND uval=\'' . pg_escape_string($key) . '\'');
      if(!empty($fid)){
	$vid = $this->db->read_field('SELECT uid FROM um WHERE ufa=' . $uid . ' AND ufb=' . $fid);
	$cval = pg_escape_string($val);
	if(empty($vid)){
	  $sql[] = 'INSERT INTO um(ufa,ufb,uval) VALUES(' . $uid . ',' . $fid . ',\'' . $cval . '\')';
	} else {
	  $sql[] = 'UPDATE um SET uval=\'' . $cval . '\' WHERE ufa=' . $uid . ' AND ufb=' . $fid;
	}
	$sav[] = $key;
      } else  $res[$key] = 45;
    }
    if(empty($sql)) return $res;
    if(empty($res)){
      if(count($sql)==1) $sql = array_shift($sql);
      else $sql = 'BEGIN; ' . implode('; ',$sql) . '; COMMIT;';
      if(FALSE===$this->db->sql_execute($sql))
	foreach($sav as $ck) $res[$ck] = 47;
      else
	foreach($sav as $ck) $res[$ck] = 0;
    } else {
      foreach($sav as $ck) $res[$ck] = 46;
    }
    return $res;
   }



  /* ================================================================================
   id translation
   ================================================================================ */

  protected function _vdn2id($vdn){
    list($val,$uname) = $this->um->vdn_split($vdn);
    $sql = 'SELECT uid FROM um_dval'
      . ' WHERE uname=\'' . pg_escape_string($uname) . '\'';
    if($val===$this->key) 
      $sql .= ' AND (dval=\'' . pg_escape_string($val) . '\''
	. ' OR dval=\'*this*\')';
    else
      $sql . ' AND dval=\'' . pg_escape_string($val) . '\'';
    $res = $this->db->read_column($sql);
    if(is_null($res)) return NULL;
    if(count($res)==1) return array_shift($res);
    trigger_error("Non unique user: $vdn");
  }

  function _id2un($id){
    $sql = 'SELECT uval FROM um WHERE ufa=' . $this->pg_typ['user']
      . ' AND uid=' . (int)$id;
    return $this->db->read_field($sql);
  }

  function id2gn($id){
    $sql = 'SELECT uval FROM um WHERE ufa=' . $this->pg_typ['group']
      . ' AND uid=' . (int)$id;
    return $this->db->read_field($sql);
  }

  function id_next(){
    return $this->db->read_field('SELECT nextval(\'' . $this->seq .'\')');
  }

  protected function u_login_possible($uid){
    if(!isset($this->pg_typ['pwd'])) return 2;
    $id = $this->pg_typ['pwd'];
    if($this->count_ab($uid,$id)!=1) return 2;
    return $this->get_ab($uid,$id)=='*'?1:0;
  }

  // checks if new email is ok for this system (return 0 if yes >0 otherwise)
  function newemail_ok($email){
    $fid = $this->pg_fields['email'];
    if(empty($fid)) return 0;
    $tmp = pg_escape_string(trim(strtolower($email)));
    $sql = 'SELECT count(*) FROM um WHERE ufb=' . $fid
      . ' AND trim(lower(uval))=\'' . $tmp . '\'';
    return $this->db->read_field($sql)>0?1:0;
  }


  function user_by_email($email){
    $fid = $this->pg_fields['email'];
    if(empty($fid)) return 1;
    $tmp = pg_escape_string(trim(strtolower($email)));
    $sql = 'SELECT ufa FROM umc WHERE typ=' . $this->pg_typ['user']
      . ' AND ufb=' . $fid . ' AND trim(lower(uval))=\'' . $tmp . '\'';
    $uid = $this->db->read_column($sql);
    if(empty($uid)) return 2;
    if(count($uid)>1) return 3;
    return $this->id2vdn(array_shift($uid));
  }

  function u_email($uid){
    $fid = $this->pg_fields['email'];
    if(empty($fid)) return 1;
    return $this->get_ab($uid,$fid);
  }

  protected function u_pwd_set($uid,$pwd) {
    $fid = $this->pg_typ['user'];
    if(empty($fid)) return 6;
    if($fid!=$this->get_i('a',$uid)) return 7;
    $fid = $this->pg_typ['pwd'];
    if(empty($fid)) return 8;
    return $this->replace_abv($uid,$fid,md5($pwd))===FALSE?9:0;
  }

  protected function u_validate($uid,$pwd){
    $fid = $this->pg_typ['user'];
    if(empty($fid)) return 1;
    if($fid!=$this->get_i('a',$uid)) return 2;
    $fid = $this->pg_typ['pwd'];
    if(empty($fid)) return 3;
    $cpwd = $this->get_ab($uid,$fid);
    return $cpwd===md5($pwd)?0:4;
  }
  
  function admin_proof_doubles(){
    qa();
  }


  function user_select($where){
    $wk = array('1=1');
    foreach($where as $type=>$args){
      $mth = 'user_select__' . $type;
      if(method_exists($this,$mth)) $wk[] = $this->$mth($args);
    }
    $wk = array_unique(array_filter($wk));
    $sql = 'SELECT DISTINCT ufa FROM umc WHERE ' . implode(' AND ',$wk);
    return (array)$this->db->read_column($sql);
  }

  protected function user_select__group($groups){
    if(is_string($groups)) $groups = array($groups);
    else if(!is_array($groups)) return array();

    $tmp = array('-1');
    foreach($groups as $gdn){
      $gid = $this->gdn2id($gdn);
      if(!is_null($gid)) $tmp[] = $gid;
    }
    return 'ufb IN (' . implode(', ',$tmp) . ')';
  }

  function search($value,$kind){
    $val = pg_escape_string(trim(strtolower($value)));
    switch($kind){
    case 'exact': $wk = "lower(uval) =    '$val'"; break;
    case 'start': $wk = "lower(uval) LIKE '$val%'"; break;
    case 'end':   $wk = "lower(uval) LIKE '%$val'"; break;
    case 'part':  $wk = "lower(uval) LIKE '%$val%'"; break;
    case 'reg':   $wk = "lower(uval) ~*   '$val'"; break;
    default:
      return array();
    }

    $sql = 'SELECT uid, ufa, ufb, uval FROM um WHERE ' . $wk . ' ORDER BY uval';
    $all = $this->db->read_array($sql);
    if(empty($all)) return array();
    $res = array();
    foreach($all as $line){
      extract($line); // -> uid ufa ufb uval

      if(in_array($ufb,$this->pg_fields)){ // ======================================= Field
	$rel = $this->search_rel_get($ufa);
	if(is_null($rel)) continue;
	$res[] = array_merge($rel,
			     array('hit-kind'=>'field',
				   'hit-type'=>array_search($ufb,$this->pg_fields),
				   'hit-value'=>$uval,
				   'hit-id'=>$uid));

      } else if($this->pg_typ['label']==$ufb){
	$rel = $this->search_rel_get($ufa);
	if(is_null($rel)) continue;
	$res[] = array_merge($rel,
			     array('hit-kind'=>'basic',
				   'hit-type'=>'label',
				   'hit-value'=>$uval,
				   'hit-id'=>$uid));
      }
    }
    return $res;
  }

  protected function search_rel_get($id){
    $tmp = $this->get_i('a',$id);
    if($tmp==$this->pg_typ['user'])
      return array('rel-kind'=>'user','rel-vdn'=>$this->id2vdn($id));
    if($tmp==$this->pg_typ['group'])
      return array('rel-kind'=>'group','rel-gdn'=>$this->id2gdn($id));
    return NULL;
  }



  function u_any1_remove($uid,$typ){
    $tid = $this->pg_typ['freevalue'];
    $mid = $this->pg_typ['io-method'];
    $did = $this->pg_typ['io-data'];
    if(is_null($tid) or is_null($mid) or is_null($did)) return NULL;

    $ids = 
    $del = array();
    foreach($this->list_ab('i',$uid,$tid) as $cid){
      $del[] = $this->sql_delete_a($cid);
      $del[] = $this->sql_delete_id($cid);
    }
    $tmp = $this->db->sqls_execute($del);
    return $tmp===FALSE?8:0;
  }

  function u_any1_get($uid,$typ,&$mth){
    $tid = $this->pg_typ['freevalue'];
    $mid = $this->pg_typ['io-method'];
    $did = $this->pg_typ['io-data'];
    if(is_null($tid) or is_null($mid) or is_null($did)) return NULL;

    $ids = $this->list_ab('i',$uid,$tid);
    if(count($ids)!=1) return NULL;
    $ids = array_shift($ids);
    $mth = $this->get_ab($ids,$mid);
    return $this->get_ab($ids,$did);
  }

  function u_any1_set($uid,$typ,$dat,$mth){
    $tid = $this->pg_typ['freevalue'];
    $mid = $this->pg_typ['io-method'];
    $did = $this->pg_typ['io-data'];
    if(is_null($tid) or is_null($mid) or is_null($did)) return NULL;

    $ids = $this->list_ab('i',$uid,$tid);
    if(empty($ids)){
      $nid = $this->id_next();
      $sqls = array($this->sql_insert_iabv($nid,$uid,$tid,$typ),
		    $this->sql_insert_abv($nid,$mid,$mth),
		    $this->sql_insert_abv($nid,$did,$dat),
		    );
    } else if(count($ids)==1){
      $nid = array_shift($ids);
      $sqls = array($this->sql_update_iabv($nid,$uid,$tid,$typ),
		    $this->sql_update_abv($nid,$mid,$mth),
		    $this->sql_update_abv($nid,$did,$dat),
		    );
    } else return NULL;
    $tmp = $this->db->sqls_execute($sqls);
    return $tmp===FALSE?8:0;
  }

  function u_anyn_get($uid,$typ,&$mth){
    $tid = $this->pg_typ['freevalue'];
    $mid = $this->pg_typ['io-method'];
    $did = $this->pg_typ['io-data'];
    if(is_null($tid) or is_null($mid) or is_null($did)) return NULL;

    $ids = $this->list_ab('i',$uid,$tid);
    $res = array();
    $mth = array();
    foreach($ids as $cid){
      $mth[$cid] = $this->get_ab($cid,$mid);
      $res[$cid] = $this->get_ab($cid,$did);
    }
    return $res;
  }

  function u_anyn_replace($uid,$typ,$dat,$mth,$id){
    $tid = $this->pg_typ['freevalue'];
    $mid = $this->pg_typ['io-method'];
    $did = $this->pg_typ['io-data'];
    if(is_null($tid) or is_null($mid) or is_null($did)) return NULL;

    $pid = $this->get_i('b',$id);
    if($pid!=$tid) return NULL;
    if($this->get_i('v',$id)!=$typ) return NULL;

    $sqls = array();
    $tmp =  $this->get_ab($id,$mid);
    if(is_null($tmp))
      $sqls[] = $this->sql_insert_abv($id,$mid,$mth);
    else if($tmp!=$mth)
      $sqls[] = $this->sql_update_abv($id,$mid,$mth);
	
    $tmp =  $this->get_ab($id,$did);
    if(is_null($tmp))
      $sqls[] = $this->sql_insert_abv($id,$did,$mth);
    else if($tmp!=$dat)
      $sqls[] = $this->sql_update_abv($id,$did,$dat);
	
    $tmp = $this->db->sqls_execute($sqls);
    return $tmp===FALSE?8:0;
  }

  function u_anyn_remove($uid,$typ,$id){
    $tid = $this->pg_typ['freevalue'];
    if(is_null($tid)) return NULL;

    $pid = $this->get_i('b',$id);
    if($pid!=$tid) return NULL;
    if($this->get_i('v',$id)!=$typ) return NULL;

    $sqls = array($this->sql_delete_a($id),
		  $this->sql_delete_id($id),
		  );
    $tmp = $this->db->sqls_execute($sqls);
    return $tmp===FALSE?8:0;
  }

  function u_anyn_add($uid,$typ,$dat,$mth){
    $tid = $this->pg_typ['freevalue'];
    $mid = $this->pg_typ['io-method'];
    $did = $this->pg_typ['io-data'];
    if(is_null($tid) or is_null($mid) or is_null($did)) return NULL;
    $nid = $this->id_next();
    $sqls = array($this->sql_insert_iabv($nid,$uid,$tid,$typ),
		  $this->sql_insert_abv($nid,$mid,$mth),
		  $this->sql_insert_abv($nid,$did,$dat),
		  );
    $tmp = $this->db->sqls_execute($sqls);
    return $tmp===FALSE?8:0;
  }



  function u_info_add($uid,$key,$val){
    $tid = def($this->pg_fields,$key);
    if(is_null($tid)) return NULL;
    $occ = $this->get_abd($tid,$this->pg_typ['occurence'],'an');
    switch($occ){
    case 'a1':
      if($this->count_ab($uid,$tid)==0)
	return $this->insert_abv($uid,$tid,$val)===FALSE?4502:0;
      else
	return $this->update_abv($uid,$tid,$val)===FALSE?4504:0;

    case 'an':
      return $this->insert_abv($uid,$tid,$val)===FALSE?4502:0;

    case 'un':
      if($this->count_bv($tid,$val)>0) return NULL;
      return $this->insert_abv($uid,$tid,$val)===FALSE?4503:0;

    default:
      qx('occurence: ' . $occ);
      return NULL;
    }
  }


  function u_remove($uid){
    $sqls = $this->sqls_delete_deep($uid);
    return $this->db->sqls_execute($sqls)===FALSE?1:0;
  }
  }


?>
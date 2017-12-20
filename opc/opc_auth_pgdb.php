<?php
if(!(class_exists('opc_pg'))) require('opc_pg.php');
if(!(class_exists('opc_auth_generic'))) require(str_replace('_pgdb.php','.php',__FILE__));

class opc_auth_pgdb  extends opc_auth_generic {
  protected $srv_list = array('user-exists','user-login','user-access',
			      'info-email',
			      'info-fname','info-lname','info-lfname','info-flname',
			      'info-birthday','info-groups',
			      'quest-groupmember',
			      );

  static $um_groups = array('all'=>array('label'=>'Public'),
			    'webadmin'=>array('label'=>'Web adminstrator',
					      'list'=>array('maederj','dluethi')),
			    'eduadmin'=>array('label'=>'Education administrator',
					      'list'=>array('martiuso','maederj')),
			    'secretar'=>array('label'=>'Secretar staff'),
			    );
  

  public function uid($name){
    static $uids = array();
    if(!isset($uids[$name])) $uids[$name] = $this->srv->load_field('um','uid',array('uname'=>$name));
    return (int)$uids[$name];
  }

  public function connect($con,$tool){
    if(!preg_match('#dbname\s*=#',$con)) $con = $tool->load_connection('pgdb',$con);
    $this->srv = new opc_pg($con);
    if(is_null($this->srv->db)) 
      return($this->status->errF(8));
    $this->sys_status = 0;
    return($this->status->okT());

  }
  
  /** valid name syntax? */
  function _check_name($user){ return preg_match('/^[a-zA-Z0-9-_]+$/',$user);}

  public function checkuser($user){
    if(empty($user)) return FALSE;
    if(!$this->running())           return $this->status->errF(1);
    if(!$this->_check_name($user))  return $this->status->errF(20);
    $sql = 'SELECT count(*) FROM um WHERE utyp=\'user\' AND uname=\'' . $user . '\'';
    return $this->srv->read_field($sql)>0;
  }


  public function checkpwd($user,$pwd){
    if(!$this->running())           return $this->status->errF(1);
    if(!$this->_check_name($user))  return $this->status->errF(20);
    $sql = 'SELECT count(*) FROM um WHERE utyp=\'user\' AND uname=\'' . $user . '\' AND upwd=\'' . md5($pwd) . '\'';
    return $this->srv->read_field($sql)>0;
    
  }

  public function get_groups($user){
    $this->cur_groups = (array)$this->info('groups',$user);
    return $this->cur_groups;
  }
  
  public function get_access(){
    $sql = 'SELECT uid FROM um WHERE (utyp=\'user\' AND uname=\'' . $this->cur_user . '\')'
      . ' OR (utyp=\'group\' AND uname IN (\'' . implode('\',\'',$this->cur_groups) . '\'))';
    $sql = 'SELECT ua_ckey FROM um_acc WHERE ua_uid IN (' . $sql . ')';
    return array_unique((array)$this->srv->read_column($sql));
  }


  public function info($info,$user=NULL){
    if(is_null($user)) $user = $this->cur_user;
    if(is_null($user))              return $this->status->errF(20);
    if(!$this->running())           return $this->status->errF(1);
    if(!$this->_check_name($user))  return $this->status->errF(20);
    $uid = $this->uid($user);
    if(!is_int($uid)) return $this->status->errF(23);
    switch($info){
    case 'fname':          $fld = 'ui_fname'; break;
    case 'lname':          $fld = 'ui_lname'; break;
    case 'email':          $fld = 'ui_email'; break;
    case 'birthday':       $fld = 'ui_birthday'; break;

    case 'flname':
      $sqlA = 'SELECT ui_val FROM um_inf WHERE ui_ckey=\'ui_fname\' AND ui_uid=' . $uid;
      $sqlB = 'SELECT ui_val FROM um_inf WHERE ui_ckey=\'ui_lname\' AND ui_uid=' . $uid;
      return $this->srv->read_field($sqlA) . ' ' . $this->srv->read_field($sqlB);
    case 'lfname':
      $sqlB = 'SELECT ui_val FROM um_inf WHERE ui_ckey=\'ui_fname\' AND ui_uid=' . $uid;
      $sqlA = 'SELECT ui_val FROM um_inf WHERE ui_ckey=\'ui_lname\' AND ui_uid=' . $uid;
      return $this->srv->read_field($sqlA) . ' ' . $this->srv->read_field($sqlB);

    case 'groups':
      return $this->srv->load_column('um_inf','ui_val',array('ui_uid'=>$uid,'ui_ckey'=>'ui_group'));

    default:
      return $this->status->errF(23);
    }
    $sql = 'SELECT ui_val FROM um_inf WHERE ui_ckey=\'' . $fld . '\' AND ui_uid=' . $uid;
    return $this->srv->read_field($sql);
  }

  public function _quest($user,$quest,$add){
    $res = parent::_quest($user,$quest,$add);
    if(!is_null($res)) return $res;

    switch($quest){
    case 'groupmember':
      $agrp = $this->info('groups',$user);
      if(!is_array($agrp) or count($agrp)==0) return FALSE;
      if(is_array($add))                      return count(array_intersect($add,$agrp))>0;
      return in_array($add,$agrp);
    }
    return NULL;
  }

}

?>
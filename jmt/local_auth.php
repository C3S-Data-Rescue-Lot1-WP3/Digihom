<?php
class loacal_auth_pg  extends iac_auth_generic {
  protected $srv_list = array('user-exists','user-login','info-email','info-fullname');

  public function connect($connection){
    $this->srv = new iac_pg(NULL,$connection);
    if(is_null($this->srv->db)) 
      return($this->status->errF(8));
    $this->sys_status = 0;
    return($this->status->okT());

  }
  function _check_name($user){ return preg_match('/^[a-f0-9]{32}$/',$user);}

  public function checkuser($user){
    if(!$this->running())           return $this->status->errF(1);
    if(!$this->_check_name($user))  return $this->status->errF(20);
    $sql = 'SELECT count(*) FROM users WHERE md5(legi)=\'' . $user . '\'';
    return $this->srv->read_field($sql)>0;
  }


  public function checkpwd($user,$pwd){
    return md5($pwd)==$user;
  }

  public function quest($user=NULL,$quest/*....*/){
    trigger_error("unknown quest: $quest");
  }


  public function info($info,$user=NULL){
    if(is_null($user)) $user = $this->cur_user;
    if(is_null($user))              return $this->status->errF(20);
    if(!$this->running())           return $this->status->errF(1);
    if(!$this->_check_name($user))  return $this->status->errF(20);
    switch($info){
    case 'fullname':       $fld = "lname || ' ' || fname"; break;
    case 'phone-business': $fld = 'phone'; break;
    case 'email':          $fld = 'email'; break;


    default:
      return $this->status->errF(23);
    }
    $sql = "SELECT $fld FROM users WHERE md5(legi)='$user'";
    return $this->srv->read_field($sql);
  }

  public function access($service,$user=NULL){
  }


}

?>
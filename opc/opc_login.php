<?php
include_once('opc_err.php');
include_once('opc_dsrc.php');
class opc_login {
  
  var $loggedin = FALSE;
  var $allow_reset = FALSE; //aut
  var $mail_mode = 0; // 0: no mail needed; 1: mail as separate field; 2: uname is mail

  // texttable for mails
  var $texttab = array('mail_from'=>NULL,
		       'mail_reset_sbj'=>'Password reset',
		       'mail_reset_text'=>"Hello %u\n\nYour password was reset to %p. Please change it as soon as possible",
		       'mail_set_sbj'=>'Password changed',
		       'mail_set_text'=>"Hello %u\n\nYour password/mail has changed. If this was not you contact the admins.",
		       'mail_new_sbj'=>'Account created',
		       'mail_new_text'=>"Hello %u\n\nYour account is now ready.");

  // System-classes
  var $err = NULL; // opc_err for errors
  var $store = NULL; // opc_dsrc for login data (input)
  var $user = NULL; // opc_dsrc for user data of the current user (result)
  var $uas = NULL; //user auth system (where the user data is saved), class depends

  function opc_login(&$user,&$store){
    if(get_class($this)=='opc_login') die('please use only subvlasses of opc_login');
    $msgs = array(2=>'Login system not available',
		  3=>'Invalid password or unknown user <i>%h</i>',
		  4=>'Wrong password', // for confirmation used only if logged in
		  5=>'E-mail is not known',
		  6=>'E-mail is not unique',
		  7=>'Invalid Mail',
		  8=>'Non unique user',
		  9=>'Internal error of the login system (%h)',
		  10=>'No user name given',
		  11=>'No password given',
		  12=>'Neither user nor password given (guest login)',
		  13=>'New password send to %h',
		  15=>'Logout performed',
		  16=>'Not logged in',
		  17=>'Non allowed username',
		  18=>'Password not accepted',
		  19=>'Confirmation is not identical',
		  20=>'Function not available',);
    $this->err = new opc_err($msgs);
    if(!is_null($user)) $this->user = &$user;
    if(!is_null($store)) $this->store = &$store;
  }

  // the 'main' function
  function login(){
    $this->user->remove();
    $this->loggedin = FALSE;
    if(is_null($this->uas)) return($this->err->ret(2));
    $un = $this->store->get('ua_name');
    $pw = $this->store->get('ua_pwd');
    $lo = $this->store->get('logout');
    if(!empty($lo)){ // ---------------------------------------- logout
      $this->store->remove();
      return($this->err->ret(15));
    } else if(empty($pw) and empty($un)){ // ----------------- no login try
      return($this->err->ret(12));
    } else if(!empty($un) and !empty($pw)){ //----------------- try login
      if(FALSE === $spwd = $this->get_userpwd($un)) return($this->err->ret(3));
      if($spwd!==md5($pw)) return($this->err->ret(3));
      $this->user->set_arr($this->get_userdata($un,'*'));
      $this->user->reset_changed();
      $this->loggedin = TRUE;
      return($this->err->ret());
    } else if(empty($un)){ // --------------------------------- no pwd
      return($this->err->ret(10));
    } else if(!$this->allow_reset){ // ------------------------- no reset possible
      return($this->err->ret(11));
    } else { // try to reset password
      $res = $this->reset_pwd($un);
      return($this->err->ret($res==0?13:$res));
    }
  }
  
  function process_form(){
    $un = $this->store->get('ua_name');
    switch($this->store->get('ua_request')){
    case 'pwd':
      $po = $this->store->get('ua_pwdOld');
      $pa = $this->store->get('ua_pwdA');
      $pb = $this->store->get('ua_pwdB');
      return($this->set_newpwd($po,$pa,$pb));
    case 'mail':
      $pw = $this->store->get('ua_pwdA');
      $ma = $this->store->get('ua_mail');
      if(empty($pw) or 0!=$this->reproof($pw)) return($this->err->ret(4));
      return($this->set_newmail($ma,$un));
    case 'new':
      $pa = $this->store->get('ua_pwdA');
      $pb = $this->store->get('ua_pwdB');
      $ma = $this->store->get('ua_mail');
      if($pa!==$pb) return($this->err->ret(19));      
      if(0!= $res = $this->new_user($un,$pa,$ma)) return($res); // otherwise login
      $this->store->set('ua_pwd',$pb);
      // no break here -> login
    default:
      return($this->login());
    }
  }


  //usefull if you ask for a password confirmation inside a form
  function reproof($pwd){
    if(!$this->loggedin) return($this->err->ret(16));
    $un = $this->store->get('ua_name');
    $spwd = $this->get_userpwd($un);
    if(is_numeric($spwd)) return($this->err->ret($un,$spwd));
    return($this->err->ret($spwd==md5($pwd)?0:4));
  }

  // for current user, including confirmation
  function set_newpwd($pwdOld,$pwdNewA,$pwdNewB,$un=NULL){ 
    if(0!= $res = $this->reproof($pwdOld)) return($res);
    if(!$this->check_pwd($pwdNewA)) return($this->err->ret(18));
    if($pwdNewA!==$pwdNewB) return($this->err->ret(19));
    if($this->set_pwd($pwdNewA,$un)===FALSE) return($this->err->ret(9));
    return($this->err->ret());
  }

  // for current user, including confirmation
  function set_newmail($newmail){ 
    if(!$this->loggedin) return($this->err->ret(16));
    if($this->mail_mode != 1) return($this->err->ret(20));
    if(!$this->check_mail($newmail)) return($this->err->ret(7));
    if(FALSE !== $this->get_userbymail($newmail)) return($this->err->ret(6));
    $un = $this->store->get('ua_name');
    $this->mail($this->get_usermail($un),'set');
    if(FALSE === $this->_set_mail($newmail,$un))  return($this->err->ret(9));
    $this->mail($this->get_usermail($un),'set');
    return($this->err->ret());
  }

  //creates a new user
  function new_user($name,$pwd=NULL,$mail=NULL){
    if($this->check_name($name)==FALSE) return($this->err->ret(17));
    if(FALSE!==$this->get_userdata($name)) return($this->err->ret(8));
    if(is_null($pwd) and is_null($mail)) return($this->err->ret(20));
    if(!is_null($mail)){
      if($this->check_mail($mail)==FALSE) return($this->err->ret(7));
      if(FALSE!==$this->get_userbymail($mail)) return($this->err->ret(6));
    }
    if(is_null($pwd)){
      $send_pwd = TRUE; 
      $pwd = $this->random_pwd();
    } else {
      $send_pwd = FALSE;
      if(FALSE===$this->check_pwd($pwd)) return($this->err->ret(18));
    }
    if(FALSE === $this->_new_user($name,$pwd,$mail)) return($this->err->ret(9));
    $this->mail($name,'new');
    if($send_pwd) $this->$this->reset_pwd($un);
    return($this->err->ret());
  }

  function set_pwd($pwd,$un=NULL){
    if(is_null($un)) $un = $this->store->get('ua_name');
    if(FALSE === $this->_set_pwd($pwd,$un)) return($this->err->ret(9));
    $this->mail($name,'set');
    $this->store->set('ua_pwd',$pwd);
    return($this->err->ret());
  }
    
  //reset by mail
  function reset_pwd($mail){
    if($this->mail_mode==0) return($this->err->ret(20));
    if($this->mail_mode==2) $name = $mail;
    else $name = $this->get_userbymail($mail);
    if(FALSE === $this->get_userbymail($mail)) return($this->err->ret(5));
    $npwd = $this->random_pwd();
    if(FALSE === $this->_set_pwd($npwd,$name)) return($this->err->ret(9));
    $this->mail($name,'reset',$npwd);
    return($this->err->ret());
  }


  // sned a notification mail, which: situation (=middle of the keys in texttab) 
  function mail($name=NULL,$which='reset',$pwd=NULL){
    if($this->mail_mode==0) return(FALSE);
    if(is_null($name)) $this->store->get('ua_name');
    $mail = $this->mail_mode==2?$name:$this->get_usermail($name);
    if(empty($mail)) return(FALSE);
    if(is_null($this->texttab['mail_from']))  $frm = NULL;
    else $frm = "From:" . $this->texttab['mail_from'] . "\r\n";
    $sbj = str_replace(array('%u','%p'),array($name,$pwd),$this->texttab["mail_${which}_sbj"]);
    $txt = str_replace(array('%u','%p'),array($name,$pwd),$this->texttab["mail_${which}_text"]);
    mail($mail,$sbj,$txt,$frm);
    return(TRUE);
  }


  /* override to increase security */
  function random_pwd(){return(substr(md5(rand()),0,12));} // random password

  function check_pwd($pwd){return(TRUE);}  // is it a good password?
  function check_name($pwd){return(TRUE);} // is it a good name?
  function check_mail($pwd){return(TRUE);} // is it a valid mail?

  function get_userpwd($un=NULL){die('overload this method');} // Error code or string (pwd)
  function get_userdata($un=NULL){die('overload this method');}// FALSE or array (named)
  function get_usermail($un=NULL){die('overload this method');}// FALSE or string (mail)
  function get_userbymail($mail){die('overload this method');} //FALSE or name/name

  function _set_pwd($pwd,$un=NULL){die('overload this method');}// T/F
  function _set_mail($mail,$un=NULL){die('overload this method');}// T/F

  function _new_user($name,$pwd,$mail=NULL){die('overload this method');} // T/F
}




class opc_login_pg extends opc_login{
  var $allow_reset = TRUE;
  var $mail_field = NULL; // mail field in db

  // db: opc_pg_value (or similar)
  function opc_login_pg(&$db,&$user,&$store){
    parent::opc_login(&$user,&$store);
    $this->uas = &$db;
  }

  function get_userpwd($un=NULL){ // get the (md5) password (string) or FALSE
    if(is_null($un)) $un = $this->store->get('ua_name');
    $res = $this->uas->get($un);
    return(is_string($res)?$res:FALSE);
  }

  function get_userdata($un=NULL){ //returns an array of data to save in user 8excluding pwd) or FALSE
    if(is_null($un)) $un = $this->store->get('ua_name');
    $res = $this->uas->get($un,'*');
    unset($res[$this->uas->valfield]);
    return($res);
  }

  function get_usermail($un=NULL){ // get mail (for information)
    if(is_null($un)) $un = $this->store->get('ua_name');
    switch($this->mail_mode){
    case 0: return(NULL);
    case 1: return(@$this->uas->get($un,$this->mail_field));
    case 2: return($un);
    }
  }
  
  function get_userbymail($mail){
    return(@$this->uas->get($mail,$this->uas->idfield,$this->mail_field));
  }

  // return postgres error, no error setting
  function _set_pwd($pwd,$un=NULL){
    if(is_null($un)) $un = $this->store->get('ua_name');
    $sql = 'UPDATE ' . $this->uas->table . ' SET ' . $this->uas->valfield . '=\''
      . md5($pwd) . '\' WHERE ' . $this->uas->idfield . '=\'' . $un . '\'';
    return(pg_query($this->uas->db,$sql));
  }

  function _set_mail($mail,$un=NULL){
    if($this->mail_mode!=1) return(FALSE);
    if(is_null($un)) $un = $this->store->get('ua_name');
    $sql = 'UPDATE ' . $this->uas->table . ' SET ' . $this->mail_field . '=\''
      . $mail . '\' WHERE ' . $this->uas->idfield . '=\'' . $un . '\'';
    return(pg_query($this->uas->db,$sql));
  }

  function _new_user($un,$pwd,$mail=NULL){
    $sql = 'INSERT INTO ' . $this->uas->table . '(' . $this->uas->idfield . ', '
      . $this->uas->valfield;
    if($this->mail_mode==1) $sql .= ', ' . $this->mail_field;
    $sql .= ') VALUES(\'' . $un . '\',\'' . md5($pwd) . '\'';
    if($this->mail_mode==1) $sql .= ', \'' . $mail . '\'';
    $sql .= ')';
    return(pg_query($this->uas->db,$sql));
  }

}

?>
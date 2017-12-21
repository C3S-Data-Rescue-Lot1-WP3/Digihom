<?php

/*
 open things
   change password: login stirbt nach zweitem Versuch
   change pwd: passwort in store neu setzen damit login überlebt
*/

class opc_hyb_htf_login {

  //hidden arguments for the forms
  var $hidden = array();

  //arguments for the logout (as named array
  var $args_logout = array('logout'=>'yes');

  /* prefix for the form field names
     login: name, pwd
     new: name, pwdA, pwdB, mail
     pwd: name pwdOld, pwdA, pwdB
     mail: mail, pwdA */
  var $fldprefix = 'ua_';

  /* internal classes */
  var $htf = NULL; // (sub)lcass of opc_htform
  var $uas = NULL; // (sub)class of opc_login

/*   labels for the form fields
     see prefix plus 'title' and 'btn' for the button*/
  var $labels = array('login_title'=>'Please log in',
		      'login_name'=>'user name',
		      'login_pwd'=>'password',
		      'login_btn'=>'login',
		      'new_title'=>'Register',
		      'new_name'=>'user name',
		      'new_pwdA'=>'password',
		      'new_pwdB'=>'confirmation',
		      'new_mail'=>'e-mail',
		      'new_btn'=>'register',
		      'pwd_title'=>'Change password',
		      'pwd_pwdOld'=>'current password',
		      'pwd_pwdA'=>'new password',
		      'pwd_pwdB'=>'confirmation',
		      'pwd_btn'=>'save',
		      'mail_title'=>'Change e-mail',
		      'mail_mail'=>'new e-mail',
		      'mail_pwdA'=>'password',
		      'mail_btn'=>'save');

  /* various textes*/
  var $texttab = array('salutation'=>'Hello %u',
		       'reset'=>'Use your e-mail as user name to reset your password',
		       'logout'=>'logout?');

  // internal/temporary
  var $htd = NULL;

  function opc_hyb_htf_login($htf,$uas,$attrs=array()){
    $this->htf = &$htf;
    $this->uas = &$uas;
    $this->htd = new opc_htdiv();
  }

  function auto($attr=NULL){
    if(is_null($attr)) $attr = 'login';
    $attr = $this->htf->_attr_auto($attr);
    if($this->uas->loggedin){
      $res = array('tag'=>'div',
		   $this->salutation(NULL,'div',NULL),
		   $this->logout(NULL,'div',NULL));
      $res = $this->htf->implode($res,$attr);
    } else {
      $res = $this->form('login',$attr);
    }
    return($res);
  }

  function _replace($txt){
    $txt = str_replace('%u',$this->uas->user->get('uname'),$txt);
    return($txt);
  }

  function form($which,$attr=NULL){
    if(is_null($attr)) $attr = array('class'=>$which);
    else $attr = $this->htf->_attr_auto($attr);
    $type = ops_array::key_extract($attr,'type','vtable');

    $fld = array('tag'=>'input','type'=>'text','size'=>12);
    $arr = array();

    $title = $this->labels[$which . '_title'];
    if($this->uas->err->eid!=0) $title .= (empty($title)?'':' - ') . $this->uas->err->msg;
    switch($which){
    case 'pwd':
      if(!$this->uas->loggedin) return(NULL);
      $na = $this->fldprefix . 'pwd';
      $cf = array_merge($fld,array('name'=>$na . 'Old','type'=>'password'));
      $arr[$this->labels[$which . '_pwdOld']] = $cf;
      $cf = array_merge($fld,array('name'=>$na . 'A','type'=>'password'));
      $arr[$this->labels[$which . '_pwdA']] = $cf;
      $cf = array_merge($fld,array('name'=>$na . 'B','type'=>'password'));
      $arr[$this->labels[$which . '_pwdB']] = $cf;
      break;
    case 'mail':
      if(!$this->uas->loggedin or $this->uas->mail_mode==0) return(NULL);
      $na = $this->fldprefix . 'mail';
      $va = $this->uas->store->get($na);
      if(empty($va)) $va = $this->uas->get_usermail();
      $cf = array_merge($fld,array('name'=>$na,'value'=>$va,'size'=>24));
      $arr[$this->labels[$which . '_mail']] = $cf;
      $na = $this->fldprefix . 'pwdA';
      $cf = array_merge($fld,array('name'=>$na,'type'=>'password'));
      $arr[$this->labels[$which . '_pwdA']] = $cf;
      break;
    case 'new':
      if($this->uas->loggedin) return(NULL);
      $na = $this->fldprefix . 'name';
      $cf = array_merge($fld,array('name'=>$na,'value'=>$this->uas->store->get($na)));
      $arr[$this->labels[$which . '_name']] = $cf;
      $na = $this->fldprefix . 'pwdA';
      $cf = array_merge($fld,array('name'=>$na,'type'=>'password'));
      $arr[$this->labels[$which . '_pwdA']] = $cf;
      $na = $this->fldprefix . 'pwdB';
      $cf = array_merge($fld,array('name'=>$na,'type'=>'password'));
      $arr[$this->labels[$which . '_pwdB']] = $cf;
      if($this->uas->mail_mode==1){
	$na = $this->fldprefix . 'mail';
	$cf = array_merge($fld,array('name'=>$na,'size'=>24,'value'=>$this->uas->store->get($na)));
	$arr[$this->labels[$which . '_mail']] = $cf;
      }
      if($this->uas->err->eid==12) $title = $this->labels[$which . '_title'];
      break;
    default:
      if($this->uas->loggedin) return(NULL);
      $na = $this->fldprefix . 'name';
      $cf = array_merge($fld,array('name'=>$na,'value'=>$this->uas->store->get($na)));
      $arr[$this->labels[$which . '_name']] = $cf;
      $na = $this->fldprefix . 'pwd';
      $cf = array_merge($fld,array('name'=>$na,'type'=>'password'));
      $arr[$this->labels[$which . '_pwd']] = $cf;
      if($this->uas->err->eid==12) $title = $this->labels[$which . '_title'];
    }
    //button
    $btn = $this->htd->tag('input',NULL,array('type'=>'submit',
					      'value'=>$this->labels[$which . '_btn']));
    //hidden field to define the sense of this form
    $hid = $this->htd->tag('input',NULL,array('type'=>'hidden',
					      'name'=>$this->fldprefix . 'request',
					      'value'=>$which));
    foreach($this->hidden as $key=>$val)
      $hid .= $this->htd->tag('input',NULL,
			      array('type'=>'hidden','name'=>$key,'value'=>$val));
    $arr[' '] = $btn . $hid;
    //construct table and extract vom htd
    $this->htd->array2list($arr,array('type'=>$type,'nametag'=>'th'));
    $res = $this->htd->return_last();
    //title/hint
    $cs = $type=='htable'?count($arr):2; //colspan
    $res[-1] = array('tag'=>'tr',array('tag'=>'td','colspan'=>$cs,$title));
    //final construct
    $res = array('tag'=>'div',array('tag'=>'form','method'=>'post','name'=>$which,
				    'action'=>$this->htf->myself(),$res));
    return($this->htf->implode($res,$attr));
  }

  function salutation($txt=NULL,$emb_tag='div',$attr=array()){
    if(!is_null($attr)) $attr = $this->htf->_attr_auto($attr);
    $txt = $this->_replace(is_null($txt)?$this->texttab['salutation']:$txt);
    $res = array('tag'=>$emb_tag,$txt);
    return(is_null($attr)?$res:$this->implode($res,$attr));
  }

  function logout($txt=NULL,$emb_tag='div',$attr=array()){
    $txt = $this->_replace(is_null($txt)?$this->texttab['logout']:$txt);
    $res = array('tag'=>'a',
		 'href'=>$this->htf->myself(0) . '?'
		 . implode('&',$this->htf->implode_urlargs($this->args_logout)),
		 $txt);
    if(!is_null($emb_tag)) $res = array('tag'=>$emb_tag,$res);
    return(is_null($attr)?$res:$this->implode($res,$attr));
  }
  
}

?>
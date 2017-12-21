<?php 
if(!class_exists('opc_ht2d') and class_exists('opt')) 
  opt::req('opc_ht2d');

class opc_ht2d_um extends opc_ht2d {

  protected $txp_cls = 'um';

  // password needed for loign
  protected $pwd_mandatory = TRUE;

  // autologin?
  protected $autologin = FALSE;

  // form data
  protected $fdata = array();

  // text table
  static $_def_txt = array('lab_un'=>'User name',
			   'lab_domain'=>'Domain',
			   'lab_pwd'=>'Password',
			   'lab_pwd_cur'=>'Current Password',
			   'lab_pwd_first'=>'Password (six characters at least. Allowed are: a-z A-Z 0-9 :.,;_+@#%)',
			   'lab_pwd_new'=>'New Password',
			   'lab_pwd_conf'=>'Confirm password',
			   'btn_login'=>'login',
			   'btn_logout'=>'logout',
			   'btn_create'=>'create',
			   'btn_register'=>'Register',
			   'btn_change'=>'change',
			   'tit_pwd_change'=>'Change password',
			   
			   'msg_login_na'=>'No login possible',
			   'msg_login_admin'=>'loggedin with ADMIN password',
			   'msg_login_nopwd'=>'logged in with NO PASSWORD',

			   2301=>'Please login first',
			   2302=>'Wrong password',
			   2303=>'Invalid password syntax (6 to 50 characters: - a-z A-Z 0-9 : . , ; _ + @ # %)',
			   2304=>'Non identical password confirmation',
			   2305=>'New password is identical to current password',
			   2306=>'Failed changing password',
			   2307=>'Failed changing e-mail',
			   9001=>'Password changed',
			   9002=>'E-Mail changed',
			   9003=>'Account created',
			   9900=>'Nothing has changed',
			   
			   );

  protected $msg = array();

  function ret_msg($ret,$id,$typ){
    $this->msg[] = array('id'=>$id,'typ'=>$typ);
    return $ret;
  }

  function init__object(&$obj){
    if(!($obj instanceof opc_um)) return 2;
    $this->obj = $obj;
    $this->obj->init__pwd_mandatory($this->pwd_mandatory);
    if($this->autologin)  $this->login_auto();
    return 0;
  }

  function init_byType($ar){
    if(is_bool($ar)) return $this->init__pwd_mandatory($ar);
    return parent::init_byType($ar);
  }

  function init__pwd_mandatory($bool){
    if(!is_bool($bool)) return 2;
    $this->pwd_mandatory = $bool;
    if(is_object($this->obj)) $this->obj->init__pwd_mandatory($bool);
    return 0;
  }


  function init__autologin($bool){
    if(!is_bool($bool)) return 2;
    $this->autologin = $bool;
    if(is_object($this->obj)) $this->login_auto();
    return 0;
  }

  function login_auto(){
    if(def($_POST,'umd__case','-')=='login'){
      $this->fdata_add($_POST);  
      return $this->proc('process__login', 'login');
    }
    if(def($_POST,'umd__case','-')=='logout'){
      $this->fdata_add($_POST);  
      return $this->proc('process__logout','logout');
    }
    if(def($_GET,'umd__case','-')=='logout'){
      $this->fdata_add($_GET);  
      return $this->proc('process__logout','logout');
    }
    return $this->obj->login_auto();
  }

  /* import array-data to internal array
   * key/val may also be array itself
   * primary thought to include form-data
   * uses ops_arra::set_ext/merge_ext
   * mode:
   *  mode (odd modes are equal to the even ones with swap meaning)
   *  0: normal merge (new data overwrites same keys in fdata)
   *  2: overwrite only if new value is not null
   *  4: ignore internal completly, use new
   */
  function fdata_add($key,$val=NULL,$mode=0){
    return ops_array::set_ext($this->fdata,$key,$val,$mode);
  }
  
  function process(){
    if(!isset($this->fdata['umd__case'])) return -1;
    $mth = 'process__' . $this->fdata['umd__case'];
    if(method_exists($this,$mth)){
      return $this->proc($mth,$this->fdata['umd__case']);
    } else {
      trigger_error("Unkown process job '" . $this->fdata['umd__case'] . "'");
      return 1;
    }
  }

  function proc($mth,$case){
    $tmp = '/umd__' . $case . '__/';
    $var = ops_array::grep_key($tmp,$this->fdata);
    if(empty($var)) return $this->$mth();
    else            return $this->$mth(ops_array::replace_key($tmp,'',$var));
  }


  function process__login($data){
    return $this->obj->login($data['uname'],def($data,'pwd'),$data);
  }

  function process__logout(){
    return $this->obj->logout();
  }

  function process__create_user($data){
    if($data['pwd']!==$data['pwdR']) 
      return $this->ret_msg(1,'cu_pdw_notequal','stop');
    unset($data['pwdR']);
    $tmp = $this->obj->user_create($data);
    return $tmp;
  }

  function process__pwd_change($data){
    if(!$this->obj->loggedin())
      return $this->log(2301,$this->logE,array('placeat'=>'pwd_change'));
    if($this->obj->user_validate($this->obj->cuser->vdn,$data['cur'])>0)
      return $this->log(2302,$this->logE,array('placeat'=>'pwd_change'));
    if($this->obj->password_ok($data['new'])>0)
      return $this->log(2303,$this->logE,array('placeat'=>'pwd_change'));
    if($data['new']!==$data['conf'])
      return $this->log(2304,$this->logE,array('placeat'=>'pwd_change'));
    if($data['new']===$data['cur'])
      return $this->log(2305,$this->logH,array('placeat'=>'front'));
    if($this->obj->user_pwd_set($this->obj->cuser->vdn,$data['new'])>0)
      return $this->log(2306,$this->logE,array('placeat'=>'pwd_change'));
    return $this->log(9001,$this->logS,array('placeat'=>'front'));
  }

  function process__uef($data){
    $uo = $this->obj->umu_get($data['vdn']);
    unset($data['vdn']);
    $res = $uo->info_setn($data,$details);
    return max($details);
  }


  function output__loginout(&$ht,$add=array()){
    if(is_null($this->obj->cuser)){
      $res = $this->output__login($ht,$add);
      return is_null($res)?$ht->div($this->txp('msg_login_na'),'ht2d_error'):$res;
    } else return $this->output__logout($ht,$add);
  }

  function output__logout(&$ht,$add=array()){
    switch($this->obj->login_state){
    case 0: return NULL;
    case 1:
      $cls = 'umd_loggedin umd_stdloggedin';
      $txt = NULL;
      break;
    case 2: 
      $cls = 'umd_loggedin umd_adminloggedin';
      $txt = $this->txp('msg_login_admin');
      break;
    case 3: 
      $cls = 'umd_loggedin umd_trustloggedin';
      $txt = $this->txp('msg_login_nopwd');
      break;
    }
    $ht->open('span',$cls);
    
    $this->uname($ht,$txt);
    $ht->a($this->txp('btn_logout'),array('umd__case'=>'logout'),
	   array('class'=>'umd_logout','title'=>'logout'));
    $ht->close();
  }

  function uname(&$ht,$txt){
    $ht->span($this->obj->cname,array('title'=>$txt));
  }

  /* creates small form for login
   * add: form-style: used by opc_ht2o_list, default ht (other vt, dl ol, ul)
   */
  function output__login(&$ht,$add=array()){
    // prepare ..................................................
    if(!isset($add['domains']))
      $add['domains'] = $this->obj->quest_provider('d:user-validate','execute');
    if(count($add['domains'])==0) return NULL;

    // create form ..................................................
    $hf = $ht->ptr('form');
    $hid = array_merge(def($add,'redirect',array()),array('umd__case'=>'login'));
    $hf->fopen($hid);
    $hf->def_add_preg('/^umd__login_/',$this->fdata);

    // show form as list ........................................
    $lis = new opc_ht2o_list($hf);
    call_user_func_array(array($lis,'set_style'),(array)def($add,'form-style','ht'));
    $hf->in();
    $this->list_field($hf,$lis,$this->txp('lab_un'),'umd__login__uname');
    if($this->pwd_mandatory)
      $this->list_field($hf,$lis,$this->txp('lab_pwd') . ' ' ,'umd__login__pwd',array('ftype'=>'password'));
    
    $tmp = $this->obj->domain_label_get($add['domains']);
    $this->list_field($hf,$lis,$this->txp('lab_domain'),'umd__login__umd',array('ftype'=>'select-opt','list'=>$tmp));
    $this->list_send($hf,$lis,array($this->txp('btn_login')));
    $hf->out();
    $lis->output($hf);

    // finish ..................................................
    $hf->fclose();
    return $ht->incl($hf->root);
  }
  /* creates small form for creation of a new user
   * add: form-style: used by opc_ht2o_list, default ht (other vt, dl ol, ul)
   */
  function output__create_user(&$ht,$add=array()){
    // prepare ..................................................
    if(!isset($add['domains']))
      $add['domains'] = $this->obj->quest_provider('d:user','create');
    if(count($add['domains'])==0) return NULL;

    // create form ..................................................
    $hf = $ht->ptr('form');
    $hf->fopen(array('umd__case'=>'create_user'));
    $hf->def_add_preg('/^umd__create_user_/',$this->fdata);

    // show form as list ........................................
    $lis = new opc_ht2o_list($hf);
    call_user_func_array(array($lis,'set_style'),(array)def($add,'form-style','ht'));
    $hf->in();
    $this->list_field($hf,$lis,$this->txp('lab_un'),'umd__create_user__uname');
    $this->list_field($hf,$lis,$this->txp('lab_pwd'),'umd__create_user__pwd',array('ftype'=>'password'));
    $this->list_field($hf,$lis,$this->txp('lab_pwd_conf'),'umd__create_user__pwdR',array('ftype'=>'password'));
    $tmp = $this->obj->domain_label_get($add['domains']);
    $this->list_field($hf,$lis,$this->txp('lab_domain'),'umd__create_user__umd_val',
		      array('ftype'=>'select-opt','list'=>$tmp));
    $this->list_send($hf,$lis,array($this->txp('btn_create')));
    $hf->out();
    $lis->output($hf);

    // finish ..................................................
    $hf->fclose();
    return $ht->incl($hf->root);
  }

  protected function list_send(&$ht,&$lis,$ar=array()){
    $lis->set('','key','c');
    $lis->set_ht($ht,'value','c');
    call_user_func_array(array($ht,'send'),$ar);    
  }



  protected function list_field(&$ht,&$lis,$label,$fname,$add=array()){
    $lkey = def($add,'lkey',$fname);
    $mth = def($add,'ftype','text');
    switch($mth){
    case 'select-opt': // select but only if more than 1 option 
      if(count($add['list'])>1){
	$lis->set($label,'key',$lkey);
	$lis->set_ht($ht,'value',$lkey);
	$ht->select($fname,$add['list']);
      } else if(count($add['list'])==1){
	$tmp = array_keys($add['list']);
	$ht->hidden($fname,$tmp[0]);
      }
      break;

    default:
      $lis->set($label,'key',$lkey);
      $lis->set_ht($ht,'value',$lkey);
      $ht->$mth($fname);
    }
  }
  
  function output__user_groups(&$ht,$add=array()){
    if(!$this->obj->loggedin()) 
      return $ht->div('Nobody is logged in','hint');

    $grps = $this->user_groups($add);
    if(empty($grps))
      return $ht->div('User is not related to any group','hint');

    $ht->open('div','umd-groups');
    foreach($grps as $key=>$val)
      $ht->div("$val[label] ($val[mtyp])",'umd-group');
    return $ht->close();
  }

  function user_groups($add){
    $grps = $this->obj->groups->get(NULL,0);
    if(isset($add['parent'])){
      $gdn = $this->obj->gdn_import($add['parent']);
      $grps = array_filter($grps,create_function('$x',"return \$x['parent']=='$gdn';"));
    }

    $res = array();
    foreach($this->obj->cuser->groups as $key=>$val){
      if(!isset($grps[$key])) continue;
      $res[$key] = array('mtyp'=>$val['mtyp'],
			 'label'=>$grps[$key]['label']);
    }
    return $res;
  }

  function output__user_edit_fields(&$ht,$add=array()){
    $flds = $add['fields'];
    $uo = $add['uo'];
    $task =  def($add,'task','uef');
    $prefix = 'umd__' . $task . '__';

    $hid = def($add,'hidden',array());
    $hid['umd__case'] = $task;
    $hid['umd__' . $task . '__vdn'] = $uo->vdn;

    $hf = $ht->obj->ptr('form');
    $hf->fopen($hid);

    foreach($flds as $cf){
      $ck = $prefix . $cf;
      $hf->def_add($ck,def($add['pg'],$ck,$uo->info_get($cf)));
    }


    foreach($flds as $cf){
      $ck = $prefix . $cf;
      $hf->div($this->txp($cf));
      $this->out__field_input($hf,$ck);
    }
    $hf->send('save');
    $hf->embed('div');
    $hf->fclose();
    $ht->incl($hf->root);
  }

  function out__field_input(&$hf,$ck){
      $hf->text($ck);
  }



  function output__user_list_short(&$ht,$add=array()){
    $ulist = $this->obj->users_list();
    asort($ulist);
    $tab = new opc_ht2o_list($ht,'ul');
    $ht->in();
    
    switch(def($add,'style','-')){
    case 'linked':
      $lnk = $add['link'];
      $href = ops_array::extract($lnk,'url',$ht->myself());
      $args = ops_array::extract($lnk,'args');
      $uarg = ops_array::extract($lnk,'arg_username');
      foreach($ulist as $ck=>$cv){
	$tab->set($ck,'key_add');
	$tab->set_ht($ht,'value_add');
	$args[$uarg] = $ck;
	$ht->page($href,$cv,$args,$lnk);
      }
      break;

    default:
      $tab->set($ulist,'kv_add');
    }
    $ht->out();
    $tab->output();
  } 



  function output__pwd_change(&$ht,$add=array()){
    $hf = $ht->ptr('form');
    $args = array('site'=>'user','ssite'=>'usr_info','umd__case'=>'pwd_change');
    $hf->fopen($args);

    $add = array('filter'=>array('level'=>'success hint error','scope'=>'user','placeat'=>'pwd_change'));
    $this->fw->ht2d_logit->output($hf,'inside',$add);

    $hf->open('dl','umd_pwd_change');
    $hf->tag('dt',$this->txp('lab_pwd_cur'));
    $hf->password('umd__pwd_change__cur');
    $hf->embed('dd');
    $hf->tag('dt',$this->txp('lab_pwd_new'));
    $hf->password('umd__pwd_change__new');
    $hf->embed('dd');
    $hf->tag('dt',$this->txp('lab_pwd_conf'));
    $hf->password('umd__pwd_change__conf');
    $hf->embed('dd');
    $hf->close();
    $hf->send($this->txp('btn_change'));
    $hf->fclose();
    $ht->incl($hf->root);
  }

  static function css_static(){
    return array('table.umd_listtable'=>'border-collapse: collapse; border-spacing: 0;',
		 'table.umd_listtable td'=>'border: solid 1px black; padding: 2px 4px;',
		 'table.umd_listtable th'=>'background-color: #333; color: white; padding: 2px 4px;',
		 'span.umd_loggedin'=>'margin: 2px 5px; padding: 2px 5px; border: solid 1px black;',
		 '.umd_loggedin'=>'background-color: #CCCCCC; color:black;',
		 '.umd_adminloggedin'=>'background-color: #FFA500; color:black;',
		 '.umd_trustloggedin'=>'background-color: #ADFF2F; color:black;',
		 'a.umd_logout'=>'text-decoration: none; color: black; padding: 1px 5px;',
		 );
    
  }

}



class opc_ht2d_umu extends opc_ht2d {

  function init__object(&$obj){
    if(!($obj instanceof opc_umu)) return 2;
    $this->obj = $obj;
    return 0;
  }


}
?>
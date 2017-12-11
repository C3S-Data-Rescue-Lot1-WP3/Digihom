<?php

class lcl_fw extends opc_fw {

  public $cuser = NULL; // as logged in
  public $user = NULL;  // as selected by admin

  public $users = array();
  public $users_sel = array();
  public $admins = array();

  protected $nkey = 2;
  public $keys = array();
  public $keys_sel = array();

  public $pv = array();

  // delete a zip file if it s older than X seconds
  protected $zip_delay = 3600;

  /* --------------------------------------------------------------------------------
   * Users vars
   * Fields to show and how 
   * write: who is allowed to edit: admin, user, none (default)
   * disp: how to diplay the value: email, text (default)
   * get: isadmin (->T/F), um (from user management, default)
   * label: [key from msg.xml], NULL [um-fld-key from msg.xml], NULL 
   */
  protected $user_infos = array('uname'=>array('write'=>'none'),
				'firstname'=>array(),
				'lastname'=>array(),
				'email'=>array('disp'=>'email'),
				'group'=>array('label'=>'lab-group','write'=>'admin',
					       'get'=>'isadmin','type'=>'isadmin','edit'=>'isadmin'));





  protected $job_cols = array('id','key1','key2','remark','status',
			      'dig_user','dig_dateout','dig_datein','dig_file','dig_time','dig_remark',
			      'chk_user','chk_dateout','chk_datein','chk_file','chk_time','chk_remark');
  


  protected function dirs_set(){
    // Directory where user uploads are saved (upload),
    // this jobs are move durong import (import), and where
    // the results are saved (jobs)
    $ar = array('upload'=>'uploads','jobs'=>'jobs'); // ,'imp'=>'import'
    foreach($ar as $ck=>$cv){
      $tmp = 'dir_' . $ck; 
      $this->$tmp = $this->tool->dir($cv);
    }
    $this->dir_imp = $this->ftp . '/new/';
  }

  protected function lists_set(){
    $this->um->cache_clear();
 
    // keywords
    $sql = 'SELECT id, keyword FROM key% ORDER BY keyword';
    for($i=1;$i<=$this->nkey;$i++){
      $this->keys[$i] = (array)$this->db->read_column(str_replace('%',$i,$sql),1,0);
      $this->keys_sel[$i] = $this->keys[$i];
      $this->keys_sel[$i][0] = ' ' . $this->txp->t('lab-select'); // space is for sorting!
      asort($this->keys_sel[$i]);
      asort($this->keys[$i]);
    }

    // users and admins
    $this->um->cache_clear();
    $this->admins = $this->um->group_user_list($this->um->gdn_make('admin')); // array(vdn)
    $this->users = $this->um->users_list(); // array(vdn=>disp)
    asort($this->users);
    $this->users_sel = $this->users;
    $this->users_sel[0] = ' ' . $this->txp->t('lab-select');
    asort($this->users_sel);
  }

  /* --------------------------------------------------
   * Test mandatory services (and die if one fails)
   * -------------------------------------------------- */
  function test_running(){
    if(!is_object($this->db))      return '@err-dbcon';
    if(!is_object($this->um))      return '@err-authcon';
    if($this->um->running!==TRUE)  return '@err-authrun';

    $ar = array($this->dir_upload=>'up',$this->dir_jobs=>'jobs'); // dir-imp not testet allways (ftp!)
    foreach($ar as $ck=>$cv)
      if(!is_dir($ck) or !is_writeable($ck)) return $this->txp->t('err-dir-' . $cv) . $ck;
    return TRUE;
  }

  function site(){
    $file = $this->tool->dir('jmt') . 'site_' . $this->nav->cur_get() . '.php';
    if(!file_exists($file)) $this->msgclose($this->txp->t('err-unknownsite'));
    include($file);
  }

  function navigation(){
    // add available sites
    $sites = array('usrjobs','personal');
    if($this->cuser->ingroup('admin'))
      $sites = array_merge(array('jobs','import','users','admin'),$sites);
    foreach($sites as $ck){
      $this->nav->site_add($ck,'root',$this->txp->t('nav-' . $ck));
    }

    // get current site (first: POST, GET as alst SESSION)
    $tmp = array_keys($sites);
    $this->nav->cur_set($this->prop_est('site',$tmp[0],'pgs'));

    // place-holder for navigation
    $ptr = $this->ptr();
    $this->main->add($ptr);
    $this->nav->ht = $ptr;
  }

  /* --------------------------------------------------
   * Process form data
   * -------------------------------------------------- */
  function process(){
    $file = $this->tool->dir('jmt') . 'proc_' . def($this->pv,'target') . '.php';
    if(file_exists($file)) 
      include($file); 
    else if(!is_null(def($this->pv,'target')))
      $this->main->div($this->txp->t('err-unknownrequest') . ': ' . $this->pv['target'],'hint');
  }

  function prepare_pre(){
    $this->pv = array_merge($_GET,$_POST);
    return parent::prepare_pre();
  }

  function prepare_post(){
    $this->dirs_set();
    $this->lists_set();
    
    // adjust sequence of um
    $tmp = $this->um->domain_get('digihom');
    $tmp->seq = 'um_seq';
    $this->head->set('title',$this->txp->_title);
    $this->head->css($this->tool->dir('jmt') . 'layout.css');

    // default things
    $res =  parent::prepare_post();

    // everything ok
    $tmp = $this->test_running(); 
    if($tmp!==TRUE) $this->msgclose($tmp);

    // logo plus login/out
    $this->ptr_um = $this->ptr();
    $this->ptr_um->open('div','oc');

    $tmp = 'Version: ' . $this->prop['version'] . ' (' . 
      preg_replace('/(....)(..)(..)/','$1-$2-$3',$this->prop['date'] . ')');
    $this->ptr_um->www('http://www.oeschger.unibe.ch/index_de.html',
		       $this->txp->t('oc'),array(),array('title'=>$tmp,'class'=>'oc'));
    $this->ptr_um->close();

    if(!$this->um->loggedin()) {
      $this->ht2d_um->output($this->ptr_um,'login',array('redirect'=>$_GET));
      return $this->msgclose('@hint-loginfirst');
    }


    $this->ht2d_um->output($this->ptr_um,'logout');

    $this->cuser = $this->um->cuser;
    $this->is_admin = in_array($this->cuser->vdn,$this->admins);

    return $res;
  }


  function output_layout($lay){
    // header title
    $this->header->open('div','float: left;');
    $this->header->h(1,$this->txp->_title);
    $this->header->close();

    // header login/out
    $this->header->open('div','float: right;');
    $this->header->incl($this->ptr_um);
    $this->header->close();

    // footer
    $this->footer->add($this->txp->t('hint-contact'));

    // defaults
    return parent::output_layout($lay);
  }


  /* ================================================================================
   * Site jobs
   * ================================================================================ */
  function job_basics($ht,$job,$vdn=NULL){
    $ht->open('dl',array('class'=>'user','style'=>'float: left;'));
    foreach($this->job_cols as $ck){
      if(is_null($job[$ck])) continue;

      if(preg_match('/^(dig_|chk_)/',$ck) and !is_null($vdn)){
	if($job[substr($ck,0,4) . 'user'] != $vdn) continue;
      }
      $ht->open('dt');
      if(preg_match('/^(dig_|chk_)/',$ck)){
	$ht->add($this->txp->t('col-' . substr($ck,0,3))
		 . ' ' . $this->txp->t('col-' . substr($ck,4)));
      } else $ht->add($this->txp->t('col-' . $ck));
      $ht->close();
      
      $ht->open('dd');
      if(substr($ck,0,3)=='key'){
	$ht->add($this->keys[(int)substr($ck,3)][$job[$ck]]);
      } else if($ck=='status'){
	$ht->add($this->txp->t('st-' . $job[$ck]));
      } else if(substr($ck,-4)=='user'){
	$ht->a($this->users[$job[$ck]],array('site'=>'users','vdn'=>$job[$ck]));
      } else if(substr($ck,-4)=='file'){
	$ht->page($this->dir_upload . $job[$ck],$job[$ck]);
      } else $ht->add($job[$ck]);
      $ht->close();
    }
    $ht->close();
  }

  function job_files($ht,$job){
    $dir = $this->dir_jobs . $job['dir'];
    if(!is_dir($dir)){
      $ht->div($this->txp->t('hint-nojobdir') . '<br/>' . $dir,'hint');
      return ;
    }

    $files = scandir($dir);
    sort($files);
    $ht->open('ol');
    foreach($files as $file){
      if(substr($file,0,1)=='.') continue;
      $ht->open('li');
      $ht->page($dir . '/' . $file,$file);
      $siz = filesize($dir . '/' . $file);
      if($siz < 1024) $siz .= 'Bytes';
      else if($siz < 1024*1024) $siz = sprintf('%0.1f kB',$siz/1024);
      else $siz = sprintf('%0.1f MB',$siz/1024/1024);
      $ht->add('&emsp;' . $siz);
      $ht->close();
    }
    $ht->close(); // ol
    return;

    // Old Option create zip file
    $ht->div($this->txp->t('tit-zip'),'font-weight: bold; text-align: center;');
    $file = $this->dir_jobs . $this->cuser->uname . '_' . $job['dir'] . '.zip';
    if(file_exists($file)){
      $ht->page($file,$this->txp->t('lab-download'),'cmd easy');
    } else {
      $add = array('target'=>'div','task'=>'zip','dir'=>$job['dir'],'user'=>$this->cuser->vdn,'id'=>$job['id']);
      $ht->a($this->txp->t('lab-create'),$add,'cmd hint');
      $ht->div($this->txp->t('hint-zipcreate'),'margin-top: 10px;');
    }
  }


  function job_edit(&$ht,$job){
    if(in_array($job['status'],array(0,1,2,3))){
      $typ = $job['status']>1?'chk':'dig';
      $hf = $this->ptr('form');
      $hid = array('target'=>'jobuser','id'=>$job['id'],'typ'=>$typ);
      $hf->fopen($hid);
      $hf->div($this->txp->t("lab-{$typ}user"));
      $tmp = $this->users_sel;
      if($typ=='chk') unset($tmp[$job['dig_user']]);
      $hf->select('jobuser__user',$tmp);
      $hf->send($this->txp->t('lab-save'));
      $hf->fclose();
      $ht->incl($hf->root);
    }

    $ask = def($this->pv,'ask','-');
    $ht->open('p');
    if($ask=='pictdel' or $ask=='jobdel'){
      $ht->span($this->txp->t('lab-' . $ask));
      $ht->a($this->txp->t('lab-yes'),array('id'=>$job['id'],'target'=>'admin','task'=>$ask),'cmd danger');
      $ht->a($this->txp->t('lab-no'),array('id'=>$job['id']),'cmd easy');
    } else {
      $ht->a($this->txp->t('lab-pictdel'),array('id'=>$job['id'],'ask'=>'pictdel'),'cmd hint');
      $ht->next();
      $ht->a($this->txp->t('lab-jobdel'),array('id'=>$job['id'],'ask'=>'jobdel'),'cmd hint');
    }
    $ht->close();
  }

  /* ================================================================================
   * Site users
   * ================================================================================ */
  protected function user_info_labels($infos){
    $res = array();
    foreach($infos as $key=>$set){
      $tmp = def($set,'label','um');
      switch($tmp){
      case 'um': 
	$res[$key] = $this->txp->t('um-fld-' . $key); 
	break;
      default:
      $res[$key] = $this->txp->t($tmp);
      }
    }
    return $res;
  }

  protected function user_values($vdn,$infos){
    $res = array();
    foreach($infos as $key=>$set){
      switch(def($set,'get','um')){
      case 'um':
	$res[$key] = $this->um->user_info($vdn,$key);
	break;
      case 'isadmin':
	$res[$key] = in_array($vdn,$this->admins)?'admin':'user';
	break;
      default:
	$res[$key] = $key;
      }
    }
    return $res;
  }


  protected function user_new(){
    $hf = $this->ptr('form');
    $add = array('target'=>'new_user','site'=>$this->nav->cur_get());
    $hf->fopen($add);
    $hf->add($this->txp->t('um-fld-uname') . ': ');
    $hf->text('usernew__uname');
    $hf->br();
    $hf->send($this->txp->t('lab-create'),array('class'=>'cmd hint'));
    $hf->fclose();
    return $hf;
  }

  function user_tab($vdn_filter=NULL){

    $sql = "    SELECT dig_user || '--1', count(*) FROM job_view WHERE status=1 GROUP BY dig_user"
      . " UNION SELECT dig_user || '--2', count(*) FROM job_view WHERE status>1 GROUP BY dig_user"
      . " UNION SELECT chk_user || '--3', count(*) FROM job_view WHERE status=3 GROUP BY chk_user"
      . " UNION SELECT chk_user || '--4', count(*) FROM job_view WHERE status>3 GROUP BY chk_user";
    $stat = $this->db->read_column($sql,1,0);

    $ht = $this->ptr();
    $tab = new opc_ht2o_table($ht);
    $tab->tag_table = array('class'=>'user-tab');

    foreach($this->user_info_labels($this->user_infos) as $ck=>$cv) 
      $tab->set($cv,'colkey',$ck);


    $tab->set($this->txp->t('lab-cmds'),'colkey','edit');
    $tab->set($this->txp->t('lab-stat'),'colkey','stat');
    $tab->show_rown = FALSE;
    
    $ht->in();
    foreach($this->users as $vdn=>$user){
      if(!is_null($vdn_filter) and $vdn!=$vdn_filter) continue;
      $values = $this->user_values($vdn,$this->user_infos);
      foreach($this->user_infos as $key=>$set){
	$value = $values[$key];
	
	if(!is_null($value)){
	  $tab->set_ht($ht,'cell',$vdn,$key);
	  switch(def($set,'disp','text')){
	  case 'email':
	    if($vdn!=$this->cuser->vdn)
	      $ht->mail($value,NULL,array('subject'=>$this->txp->_title));
	    else
	      $ht->add($value);
	    break;
	  default:
	    $ht->add($value);
	  }
	} else $tab->set('-','cell',$vdn,$key);
      }

      //statistics
      $tab->set_ht($ht,'cell',$vdn,'stat');
      $n = 0;
      for($i=1;$i<5;$i++){
	$x = def($stat,$vdn . '--' . $i,0);
	$n += $x;
	$ht->span($x,array('class'=>'cnt st-' . $i,'title'=>$this->txp->t('st-' . $i)));
      }	

      //commands
      $tab->set_ht($ht,'cell',$vdn,'edit');
      $add = array('site'=>$this->nav->cur_get(),'vdn'=>$vdn);
      $add['cmd'] = 'show';
      $ht->a($this->txp->t('lab-show'),$add,'cmd easy'); 
      $add['cmd'] = 'edit';
      $ht->a($this->txp->t('lab-edit'),$add,'cmd hint'); 
      $add = array('target'=>'admin','task'=>'del_user','vdn'=>$vdn);
      if($this->is_admin and $n==0) 
	$ht->a($this->txp->t('lab-del'),$add,'cmd danger'); 
      
      

      
    }
    $tab->set(TRUE,'rowkeys');
    
    $ht->out();
    $tab->output();
    return $ht;
  }
  
  }

?>
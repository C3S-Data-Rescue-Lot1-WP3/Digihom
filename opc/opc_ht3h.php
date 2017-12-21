<?php

class opc_ht3h extends opc_ht3p {

  protected $xhtml   = TRUE;
  protected $charset = 'UTF-8';

  protected $title = NULL;
  protected $favicon = FALSE;

  /* Meta data
   * syntax key=>val or key=>array(lng=>val)
   * a integer dat is transformed with date('c')
   */
  protected $meta = array();
  
  protected $rkey = NULL;
  protected $ckey = NULL;

  // variables to import directly
  protected $import_dir = array('title',
				'xhtml','charset',
				'favicon',
				);
  protected $import_meta = array('date','author',
				 'keywords',
				 'expires','refresh',
				 );
  // meat which works with http-equiv instead of name
  protected $meta_equiv = array('expires','refresh',
				'PICS-Label',
				 );

  function init_last(){
    $this->xhtml   = $this->ts->xhtml;
    $this->charset = $this->ts->charset;
    $this->rkey = $this->otag('head');
    $this->ckey = $this->aobj(new opc_ht3str($this->ts,'wrap'));
    return 0;
  }

  function import_data($arr){
    $res = array();
    foreach($arr as $key=>$val) 
      $res[$key] = $this->set($key,$val);
    return $res;
  }

  function set($key,$val){
    if(in_array($key,$this->import_dir)){
      $this->$key = $val;
    } else if(in_array($key,$this->import_meta)){
      $this->meta[$key] = $val;
    } else return FALSE;
    return TRUE;
  }

  // sets pointer to insert at the end of head
  function goto_0(){
    $this->key = $this->rkey;
    $this->mode = 'dl';
  }

  // if pointr alread in tag/type nothings happens
  // otherwise such a tag is opened at the end of head
  function goto_tag($tag=NULL,$type=NULL){
    if($this->lev_next()==1){
      $this->otag($tag,array('type'=>$type));
    } else if($this->lev_next()>1){
      $tmp = $this->ts->data($this->ts->str[$this->key]['u']);
      if(!($tmp instanceof opc_ht3t)
	 or $tmp->Tag!=$tag
	 or $tmp->get('type')!=$type){
	$this->goto_0();
	$this->otag($tag,array('type'=>$type));
      }
    } else qx();
  }

  function css($css){
    if(is_string($css) and strpos($css,'{')===FALSE){
      $this->goto_0();
      $add = array('lin'=>'stylesheet',
		   'type'=>'text/css',
		   'href'=>$css);
      $this->atag('link',NULL,$add);
    } else if (is_string($css)){
      $this->goto_tag('style','text/css');
      $this->line($css);
    } else if(is_array($css)){
      $this->goto_tag('style','text/css');
      foreach($css as $key=>$val) $this->line($key  . ' {' . $val . '}');
    }
  }

  function complete(){
    static $done = FALSE;
    if($done) return -1;
    $this->key = $this->ckey;
    $this->mode = 'd';

    // title
    if(!empty($this->title)) 
      $this->atag('title',$this->title);

    // favicon
    if($this->favicon!==FALSE){
      $icon = $this->favicon===TRUE?'favicon.ico':$this->favicon;
      $this->aetag('link',array('rel'=>'shortcut icon',
				'type'=>'image/x-icon',
				'href'=>$icon));
    }

    // charset 
    $this->aetag('meta',array('http-equiv'=>'content-type',
			      'content'=>'text/html; charset=' . $this->charset));
    

    $this->complete_meta();
    $done = TRUE;
  }

  function complete_meta(){
    // polishing ..................................................
    // date: integer to date
    if(isset($this->meta['date']) and is_int($this->meta['date']))
      $this->meta['date'] = date('c',$this->meta['date']);
    
    // refresh: string without URL= -> 0 sec; array(time,url)
    if(isset($this->meta['refresh'])){
      $tmp = &$this->meta['refresh'];
      if(is_array($tmp)){
	$tmp = $tmp[1] . '; URL=' . $tmp[0];
      } else if(strpos($tmp,'URL=')===FALSE){
	$tmp = '0; URL=' . $tmp;
      }
    }

    // special cases ..................................................

    // show the rest ..................................................
    foreach($this->meta as $key=>$val){
      if(in_array($key,$this->meta_equiv)){
	$this->aetag('meta',array('http-equiv'=>$key,'content'=>$val));
      } else if(is_array($val)){
	foreach($val as $ck=>$cv)
	  $this->aetag('meta',array('name'=>$key,'lang'=>$ck,'content'=>$cv));
      } else if(is_numeric($val) or trim($val)!=''){
	$this->aetag('meta',array('name'=>$key,'content'=>$val));
      }
    }
  }

  function export($root=NULL){
    $this->complete();
    return parent::export($this->rkey);
  }

  }

?>
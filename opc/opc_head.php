<?php
  /* 
   bei debug -> direkt css für opc_ht_debug (oder zumnidest bis und mit head teil
   import von opc_attr_style
  */

class opc_head {

  /** basic settings (incl. defaults and checks) */
  protected $prop = array();

  /** links settings */
  protected $link = array();

  /** links settings */
  protected $tool = NULL;

  /**meta settings (additional to prop) */
  protected $meta = array();

  /** dublin core settings */
  protected $dc = array();

  /** other data, where the order is important (like css and js) */
  protected $others = array(); 

  /** defaults for prop */
  protected $def = array('xhtml'=>TRUE,
			 'html-version'=>'4.01',
			 'xhtml-version'=>'1.0',
			 'type'=>'strict',
			 'charset'=>'UTF-8', // or ISO-8859-1, ...
			 'title'=>NULL,
			 'favicon'=>FALSE,
			 'robots'=>'index',
			 'profile'=>NULL,
			 'myself'=>NULL,
			 );


  /** option list for prop */
  protected $options = array('type'=>array('strict','transitional','frameset'),
			     'robots'=>array('noindex','index','follow','nofollow','all'),
			     );

  function reset($which=15){
    if(($which & 1)==1) $this->prop = $this->def;
    if(($which & 2)==2) $this->meta = array();
    if(($which & 4)==4) $this->dc = array();
    if(($which & 8)==8) $this->others = array();
  }

  /** constructor
   * first string -> title
   * second string -> charset
   * bool -> xhtml
   * opc_ht2-object -> uses xhtml/charset from there
   */
  function __construct(/* ... */){
    $this->reset(14);
    $ar = func_get_args();
    $str = array('title','charset');
    $bool = array('xhtml');
    foreach($ar as $ca){
      if(is_bool($ca))         $this->prop[array_shift($bool)] = $ca;
      else if(is_string($ca))  $this->prop[array_shift($str)] = $ca;
      else if(is_array($ca))   foreach($ca as $key=>$val) $this->set($key,$val);
      else if(is_object($ca)){
	if     ($ca instanceof opc_fw)  $this->imply_fw($ca);
	else if($ca instanceof _tools_) $this->imply_tool($ca);
	else if($ca instanceof opc_ht2) $this->imply_ht2($ca);
      }
    }
    foreach($this->def as $key=>$val) if(!isset($this->prop[$key])) $this->prop[$key] = $val;
  }

  protected function imply_fw($obj){
    if(!is_null($obj->tool)) $this->imply_tool($obj->tool);
    if(!is_null($obj->data)) $this->imply_ht2($obj->data);
    $ar = array('title','xhtml','charset');
    foreach($ar as $ca) if(!@is_null($obj->$ca)) $this->prop[$ca] = $obj->$ca;
  }

  protected function imply_tool($obj){
    $this->tool = $obj;
    $ar = array('title','xhtml','charset');
    foreach($ar as $ca) if(!isset($this->prop[$ca])) $this->prop[$ca] = $obj->$ca;
  }

  protected function imply_ht2($obj){
    $ar = array('charset','xhtml');
    foreach($ar as $ca) if(!isset($this->prop[$ca])) $this->prop[$ca] = $obj->$ca;
    $this->prop['myself'] = $obj->myself();
  }

  function __get($key){
    if(in_array($key,array('others','prop','meta','dc'))) return $this->$key;
    if(isset($this->prop[$key])) return $this->prop[$key];
    if(isset($this->meta[$key])) return $this->meta[$key];
    if(isset($this->dc[$key]))   return $this->dc[$key];
    return NULL;
  }

  function set($key,$val){
    if(!array_key_exists($key,$this->prop)){
      if(!is_string($key)) return trg_err(1,'invalid call');
      switch($key){
      case 'js':  $this->js($val);  break;
      case 'rem': $this->rem($val); break;
      case 'css': $this->css($val); break;
      case 'div': $this->div($val); break;
      case 'keywords': $this->keywords($val); break;
      default:
	$this->meta($key,$val);
      }
    } else $this->set_prop($key,$val);
  }

  function div($data) {
    $this->others[] = array('div',$data); 
  }

  function rem($data,$lev=0) {
    $this->others[] = array('rem',array($data,$lev)); 
  }

  function rss($url,$tit='RSS'){
    $this->prop['rss'] = array($url,$tit);
  }

  function link($rel,$href,$type=NULL){
    $this->others[] = array('link',array($href,$type));
  }

  function js($data) {
    $this->others[] = array(strpos($data,';')?'js':'jsf',$data);
  }

  function css($data) {
    if(is_array($data)){
      foreach($data as $ck=>$cv){
	if($ck==='!')
	  $this->others[] = array('css',"<!--\n$cv\n-->");
	else
	  $this->others[] = array('css',$ck . ' {' . $cv . ' }');
      }
    } else if(strpos($data,';')!==FALSE){
      $this->others[] = array('css',$data);
    } else $this->others[] = array('csf',$data);
  }

  function meta($key,$val,$lang=NULL,$schema=NULL){
    $rkey = $key . '|' . $lang . '|' . $schema;
    $this->meta[$rkey] = $val;
  }
  
  function keywords($new,$lang=NULL,$schema=NULL){
    $rkey = 'keywords|' . $lang . '|' . $schema;
    if(is_array($new)) $new = implode(', ',$new);
    if(isset($this->meta[$rkey]))
      $this->meta[$rkey] .= ', ' . $new;
    else
      $this->meta[$rkey] = $new;
  }

  /** set properties including checking them */
  function set_prop($key,$val){
    try{
      switch($key){
      case 'robots':
	if(is_bool($val)) {$val = $val?'index':'noindex'; break;}
	// no break here!;
      case 'type': 
	if(is_null($val)) $val = $this->options[$key][0];
	if(in_array($val,$this->options[$key])) break;
	throw new Exception(NULL);

      case 'favicon':
	if(is_string($val) or is_bool($val)) break;
	throw new Exception(NULL);

      case 'xhtml': 
	if(is_bool($val)) break;
	throw new Exception(NULL);
	
      case 'html-version': case 'xhtml-version':
	if(preg_match('/^\d+(\.\d+)?$/',$val)) break;
	throw new Exception(NULL);
	
      case 'charset': case  'title': case 'profile': case 'myself':
	if(is_string($val)) break;
	throw new Exception(NULL);
	
      default:
	throw new Exception('unknown property: ' . $key);
      }
      $this->prop[$key] = $val;
    } catch (Exception $exc) {
      $msg = $exc->getMessage();
      trg_err(0,empty($msg)?"invalid option for $key: $val":$msg);
    }
  }



  function __set($key,$val){ $this->set($key,$val); }


  function exp_doctype(){
    if($this->prop['xhtml']){
      $res = '<?xml version="' . $this->prop['xhtml-version']
	. '" encoding="' . $this->prop['charset'] . '" ?>'
	. '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML '
	. $this->prop['xhtml-version']
	. ' ' . ucfirst($this->prop['type']) . '//EN"'
	. ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-' . strtolower($this->prop['type']) . '.dtd">';
    } else {
      $res = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML ' . $this->prop['html-version'] . '//EN"';

      switch(strtolower($this->prop['type'])){
      case 'strict':$res .=' "http://www.w3.org/TR/html' . floor($this->prop['html-version']) . '/strict.dtd">'; break;
      case 'transitional':$res .=' "http://www.w3.org/TR/html' . floor($this->prop['html-version']) . '/loose.dtd">'; break;
      case 'frameset':$res .=' "http://www.w3.org/TR/html' . floor($this->prop['html-version']) . '/frameset.dtd">'; break;
      }
    }
    return($res);
  }

  /* ================================================================================
     ==================================== Export ====================================
     ================================================================================ */

  /** the main export function of this class, ht2 is the target object, body a mode-switch */
  function exp2ht($ht2,$body=FALSE){
    // basic checks
    if(!($ht2 instanceof opc_ht2))              return trg_err(1,'not a ht2-object');
    if($ht2->xhtml != $this->prop['xhtml'])     trg_err(1,'head and ht2 differ in (x)html');
    if($ht2->charset != $this->prop['charset']) trg_err(1,'head and ht2 differ in charset');
    if($this->prop['type'] != $ht2->set['type']) $this->prop['type'] = $ht2->set['type'];

    $hp = new opc_ptr_ht2($ht2,0);

    // doctype
    $hp->add($this->exp_doctype() . "\n");
    $attr = $this->prop['xhtml']?array('xmlns'=>'http://www.w3.org/1999/xhtml'):array();

    // basics 
    $hp->open('html',$attr);
    if(isset($this->prop['profile'])){
      $hp->open('head',array('profile'=>$this->prop['profile']));
    } else $hp->open('head');
    if(isset($this->prop['title'])) $hp->tag('title',$this->prop['title']);

    // Meta
    $this->exp_meta($hp);
    // others (css, java ... )
    $this->exp_other($hp,$this->others);

    $hp->close();
    $this->expbody($hp,$body);
  }

  /* exports the metza data to the given pointer */
  function exp_meta($hp){
    // favicon
    if(!empty($this->prop['favicon'])){
      $fn = $this->prop['favicon']===TRUE?'favicon.ico':$this->prop['favicon'];
      $hp->tag('link',NULL,array('rel'=>'shortcut icon','type'=>'image/x-icon','href'=>$fn));
    }

    // rss
    if(!empty($this->prop['rss'])){
      $att = array('rel'=>'alternate',
		   'type'=>'application/rss+xml',
		   'title'=>$this->prop['rss'][1],
		   'href'=>$this->prop['rss'][0]);
      $hp->tag('link',NULL,$att);
    }


    // charset
    $hp->tag('meta',NULL,array('http-equiv'=>'content-type',
			       'content'=>'text/html; charset=' . $this->prop['charset']));
    // robots
    $hp->tag('meta',NULL,array('name'=>'robots','content'=>$this->prop['robots']));
    
    // standard meta data
    foreach($this->meta as $key=>$val){
      list($key,$lang,$schema) = explode('|',$key);
      $this->exp_meta_single($hp,$key,$lang,$schema,$val);    
    }      
  }

  /** export a single (standard) meta item */
  function exp_meta_single($hp,$key,$lang,$schema,$val){
    switch($key){
    case 'refresh':
      $attr = array('http-equiv'=>'refresh','content'=>$val);
      break;
    default:
      $attr = array('name'=>$key,'lang'=>$lang,'schema'=>$schema,'content'=>$val,);
    }
    $hp->tag('meta',NULL,$attr);
  }

  /* exports all items saved in others to the given pointer */
  function exp_other($hp,$others){
    $open = '-';
    foreach($others as $cother){
      list($item,$data) = $cother;
      if($item!=$open){
	if($item=='css')      $hp->open('style',array('type'=>'text/css')); 
	else if($open=='css') $hp->close(); 
	$open = $item;
      } 
      $this->exp_other_single($hp,$item,$data);
    }
    if($open=='css') $hp->close();
  }

  function exp_other_single(&$hp,$item,$data){
    switch($item){
    case 'css':  case 'div':
      $hp->add("\n  $data\n");
      break;
      
    case 'csf':
      if(strpos($data,'@')!==FALSE) $data = $this->tool->det_file($data,1);
      $hp->tag('link',null,array('rel'=>'stylesheet','href'=>$data,'type'=>'text/css'));
      break;
	
    case 'js':   
      $hp->tag('script',$data,array('type'=>'text/javascript')); 
      break;

    case 'jsf':  
      if(strpos($data,'@')!==FALSE) $data = $this->tool->det_file($data,1);
      $hp->tag('script',null,array('src'=>$data,'type'=>'text/javascript')); 
      break;

    case 'rem':
      $hp->rem($data[0],$data[1]);
      break;

    case 'link':
      if(strpos($data[1],'@')!==FALSE) $data[1] = $this->tool->det_file($data);
      $attr = array('rel'=>$data[0],'href'=>$data[1]);
      if(isset($data[2])) $attr['type'] = $data[2];
      $hp->tag('link',null,$attr);
      break;
    }
  }

  

  function expbody($hp,$body){
    if(is_array($body)) return $hp->open('body',$body);
    if($body===TRUE)    return $hp->open('body');
  }

}
?>
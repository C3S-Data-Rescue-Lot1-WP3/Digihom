<?php

  /*
css block verbessern: ganz ersetzen, ergänzen intelligent ersetzen
  
   */

require_once('opc_ht.php');

class opc_hthead extends opc_ht{
  //  basic settings
  var $variation = NULL;//allowed 'strict','transitional','frameset'
  var $version = "4.01";
  var $xversion = "1.0";

    // standard data
  var $title = NULL;

  var $meta = array(); //meta data
  var $dc = array(); //dublincore
  var $css = array(); //external and internal mixed
  var $js = array(); //external and internal mixed

  //special item (handeld separtely(
  var $keywords = NULL;// as array or as string
  var $robots = 'index'; // -> used in meta index|noindex|folloe|nofollow|all
  var $charset = 'ISO-8859-1';//allowed are also 'UTF-8' and so on -> used in meta
  var $favicon = FALSE;// TRUE for favicon.ico or the name itself -> used in rel

  // additional stuff
  var $other = NULL;


  //order definies the order of the outputs
  var $order = array();
  var $opento = 'html';//

  /* default items/keys 
   ..keys is used if dc or meta gets numeric keys in the array
   ..short is used for aliases
  */
  var $dcshort = array('c'=>'creator','descr'=>'description','d'=>'date');
  var $dckeys = array('title','creator','subject','description','date');
  var $metashort = array('descr'=>'description','a'=>'author','d'=>'date');
  var $metakeys = array('author','description','date');

  function opc_hthead($title='',$xhtml=FALSE){
    $this->reset();
    $this->url = $this->myself();
    $ar = func_get_args();
    foreach($ar as $ca){
      if(is_bool($ca)){
	$this->xhtml = $ca;
      } else if(is_string($ca)){
	if(is_null($this->title)) $this->title = $ca; else $this->opento = $ca;
      } 
    }
  }

  function reset(){
    parent::reset();
    $this->variation = NULL;
    $this->version = "4.01";
    $this->xversion = "1.0";
    $this->title = NULL;
    $this->meta = array();
    $this->dc = array(); 
    $this->css = array();
    $this->js = array();
    $this->keywords = NULL;
    $this->robots = 'index';
    $this->charset = 'ISO-8859-1';
    $this->favicon = FALSE;
    $this->other = NULL;
    $this->order = array('title','meta','dc','rel','css','js','other','comment');
  }
  /* 
    if you use an opc_ht-object to create your site
      use openhtml and afterwards sink to move the head data to your site-object
      or use directly the extend sink of this sublcass
    if you don't use an opc_ht-object
      use openhtml2str together with echo for example
    do not use method output like in the most other opc_ht-subclasses

    ~2arr makes no sense

    items: array of elements shich should be used (in this order)
    opento: if one of html, head, body it defines the tag which is not closed
            otherwise the html-tag will be closed too
    if opento is head: body-items are ignored
    if opento is is not body and no body items exist, body will be skipped
   */
  function openhtml($items=NULL,$opento=NULL){
    if(is_null($opento)) $opento = $this->opento;
    if(is_null($items)) $items = $this->order;
    else if(!is_array($items)) $items = array($items);
    $items = $this->collect_elements($items);
    $this->add($items['doctype'] . "\n");
    $this->open('html',$items['html']);
    
    switch($opento){
    case 'body':
      $this->add($items['head']);
      $this->open('body',$items['body']);
      break;
    case 'head':
      $this->open('head',$items['head']);
      break;
    default: 
      $this->add($items['head']);
      if(count($items['body'])>1) $this->add($items['body']);
      if($opento!='html') $this->close();
      break;
    }
  }

  function openhtml2str($items=NULL,$opento=NULL){
    if(is_null($opento)) $opento = $this->opento;
    if(is_null($items)) $items = $this->order;
    else if(!is_array($items)) $items = array($items);
    $items = $this->collect_elements($items);
    $res = $items['doctype'] . "\n";
    $struc = $items['html'];
    $struc[] = $items['head'];
    
    switch($opento){
    case 'body':
      $res .= $this->_implode2str($struc,1);
      $res .= $this->_implode2str($items['body'],1);
      break;
    case 'head':
      $res .= $this->_implode2str($struc,2);
      break;
    default: 
      if(count($items['body'])>1) $struc[] = $items['body'];
      $res .= $this->_implode2str($struc,$opento=='html'?1:0);
    }
      
    return($res);
  }

  /* extended sink
   if arg2 is a string 
     openhtml(NULL,arg2) is called in advance and arg3 is the reset argument
   otherwise
     arg2 is the reset argument and arg3 is ignored
  */
  function sink(&$obj,$arg2=TRUE,$arg3=TRUE){
    if(is_string($arg2)) $this->openhtml(NULL,$arg2);
    else $arg3 = $arg2;
    parent::sink($obj,$arg3);
  }

  /* collect all element in item to an array
   with doctype, html-tag, head- and body-array */
  function collect_elements($items){
    $html = array('tag'=>'html');
    if($this->xhtml) $html['xmlns'] = 'http://www.w3.org/1999/xhtml';
    $head = array('tag'=>'head');
    $body = $this->get_bodyattr();
    $body['tag'] = 'body';
    foreach($items as $item){
      $citem = $this->element2arr($item);
      if(empty($citem)) continue;
      if(isset($citem['_target_'])){
	$target = $citem['_target_'];
	unset($citem['_target_']);
      } else $target = 'head';
      if(isset($citem['tag'])) array_push($$target,$citem);
      else foreach($citem as $ci) array_push($$target,$ci);
    }
    return(array('doctype'=>$this->doctype2str(),'html'=>$html,
		 'head'=>$head,'body'=>$body));
  }


  function get_bodyattr(){
    return(array()); // overdrive for attributes of the body tag
  }

  /* creates a single item */
  function element    ($item){$this->add($this->element2arr($item));}
  function element2str($item){return($this->_implode2str($this->element2arr($item)));}
  function element2arr($item){
    $res = array();
    switch($item){
    case 'title':
      if(!empty($this->title)) $res[] = $this->tag2arr('title',$this->title);
      break;
      
    case 'meta':
      if(!is_null($this->charset)) 
	$res[] = $this->stag2arr('meta',array('http-equiv'=>'content-type',
					 'content'=>'text/html; charset=' . $this->charset));
      if(is_string($this->robots))   $this->meta['robots'] = $this->robots;
      else if($this->robots===TRUE)  $this->meta['robots'] = 'index';
      else if($this->robots===FALSE) $this->meta['robots'] = 'noindex';
      if(is_string($this->keywords))
	$this->meta['keywords'] = $this->keywords;
      else if(is_array($this->keywords))
	$this->meta['keywords'] = implode(', ',$this->keywords);
      while(list($ak,$av)=each($this->meta)) 
	$res[] = $this->stag2arr('meta',array('name'=>$ak,'content'=>$av));
      break;
      
    case 'dc':
      while(list($ak,$av)=each($this->dc)) 
	$res[] = $this->stag2arr('meta',array('name'=>'DC.' . $ak,'content'=>'$av'));
      break;
      
    case 'rel':
      if($this->favicon===TRUE) 
	$res[] = $this->stag2arr('link',array('rel'=>'shortcut icon','href'=>'favicon.ico'));
      else if(is_string($this->favicon))
	$res[] = $this->stag2arr('link',array('rel'=>'shortcut icon','href'=>$this->favicon));
      break;
      
    case 'css': 
      if(is_array($this->css)) reset($this->css); else $this->css = array($this->css);
      $rx = array();
      while(list($ak,$av)=each($this->css)){
	if(is_numeric($ak)){
	  if(count($rx)>0) $res[] = $this->tag2arr('style',implode("\n",$rx),array('type'=>'text/css'));
	  $rx = array();
	  $res[] = $this->stag2arr('link',array('rel'=>'stylesheet','type'=>'text/css','href'=>$av));
	} else if(substr($ak,0,1)=='!'){
	  $rx[] = "<!--\n$av\n-->";
	} else if(is_array($av)){
	  $cs = array();
	  foreach($av as $bk=>$bv) $cs[] = $bk . ':' . (is_array($bv)?implode(' ',$bv):$bv) . ';';
	  $rx[] = "   $ak {\n    " . preg_replace('/;+/',';',implode("\n    ",$cs)) ."\n}";
	} else $rx[] = "   $ak { $av}";
      }
      if(count($rx)>0)
	$res[] = $this->tag2arr('style',implode("\n",$rx),array('type'=>'text/css'));
      break;

    case 'js': // js internal
      if(is_array($this->js)) reset($this->js); else $this->js = array($this->js);
      $rx = array();
      foreach($this->js as $cjs){
	if(!preg_match('/^[-:\\/\w.]+$/',$cjs))
	  $res[] = $this->tag2arr('script',"\n$cjs\n",array('type'=>'text/javascript'));
	else if(file_exists($cjs))
	  $res[] = $this->stag2arr('script',array('src'=>$cjs,'type'=>'text/javascript'));
	else
	  $res[] = "\n\n<!-- pc_hthead Error: file not found: $cjs -->\n\n";
      }
      break;

    case 'other': 
      $res[] = $this->other;
      break;
            
    case 'comment':
      if(class_exists('opc_htdiv')){
	$ht = new opc_htdiv($this->xhtml);
	$res[] = $ht->rem2str('head created with php-class opc_hthead',': ',
			      array('delim'=>'h','addline'=>0,'align'=>'center'));
      }
      break;
    }
    return($res);
  }

  /* creates the doctype; ~2arr makes no sense */
  function doctype    (){$this->add($this->doctype2str());}
  function doctype2str(){
    if(!$this->xhtml){
      $cs = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML ' . $this->version . '//EN"';
      switch(strtolower($this->variation)){
      case 'strict':$cs .=' "http://www.w3.org/TR/html' . floor($this->version) . '/strict.dtd">'; break;
      case 'transitional':$cs .=' "http://www.w3.org/TR/html' . floor($this->version) . '/loose.dtd">'; break;
      case 'frameset':$cs .=' "http://www.w3.org/TR/html' . floor($this->version) . '/frameset.dtd">'; break;
      default: 
	$cs .= '>';
      }
    } else {
      if(is_null($this->variation)) $this->variation = 'strict';
      $cs = '<?xml version="' . $this->xversion . '" encoding="' . $this->charset . '" ?>'
	. '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML ' . $this->xversion . ' ' . ucfirst($this->variation) . '//EN"'
	. ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-' . strtolower($this->variation) . '.dtd">';
    }
    return($cs);
  }


  //set get the version (typically 4.01 for html, 1.0 for xhtml)
  function version($ver=NULL){
    if(is_null($ver)) return($this->xhtml?$this->xversion:$this->version);
    if($this->xhtml) $this->xversion = $ver; else $this->version = $ver;
  }


  /* 
   how the following functions work
   NULL: reset the thing
   array(...): add this item, override if already exists
   
   keys will looked up in ...keys to replace the short by the long version
   numeric keys will be replaced by n-th item in ..keys which is not yet given

   additional remarks
   css: accepts strings too and will use them for external files
   dc: use 'title' instead of 'DC.title' and so on
   meta: for charste and keywords use the additional functions

  */

  function keywords($data){$this->set_keywords($data);}
  function set_keywords($data){
    if(is_null($data)) $this->keywords = array();
    else if(is_string($data)) $this->keywords[] = $data;
    else if(!is_array($this->keywords)) $this->keywords = $data;
    else $this->keywords = array_merge($this->keywords,$data);
  }

  function css($data){$this->set_css($data);}
  function set_css($data){
    if(is_null($data)) $this->css = array();
    else if(is_string($data)) $this->css[] = $data;
    else $this->css = array_merge($this->css,$data);
  }
  
  function js($data){$this->set_js($data);}
  function set_js($data){
    if(is_null($data)) $this->js = array();
    else $this->js[] = $data;
  }

  function dc($data){$this->set_dublincore($data);}
  function set_dublincore($data){
    if(is_null($data)){$this->dc = array(); return(NULL);}
    if(!is_array($data)) return(FALSE);
    $data = $this->_adjustkeys($data,$this->dcshort,$this->dckeys);
    $this->dc = array_merge($this->dc,$data);
  }

  function meta($data){$this->set_meta($data);}
  function set_meta($data){
    if(is_null($data)){$this->meta = array(); return(NULL);}
    if(!is_array($data)) return(FALSE);
    $data = $this->_adjustkeys($data,$this->metashort,$this->metakeys);
    $this->meta = array_merge($this->meta,$data);
  }

  function _adjustkeys($data,$shortkeys=array(),$defkeys=array(),$defvalues=array()){
    $resA = array();
    while(list($ak,$av)=each($data))
      if(is_string($ak) and array_key_exists($ak,$shortkeys))
	$resA[$shortkeys[$ak]] = $av;
      else 
	$resA[$ak] = $av;
    $ok = array_intersect(array_keys($resA),array_keys($defkeys));
    foreach($ok as $ck) unset($defkeys[$ck]);
    $resB = array();
    while(list($ak,$av)=each($resA))
      if(!is_string($ak) and in_array($ak,$shortkeys))
	$resB[array_shift($shortkeys)] = $av;
      else 
	$resB[$ak] = $av;
    return(array_merge($defvalues,$resB));
  }

}

?>
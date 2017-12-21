<?php
require_once('opc_dnarray.php');

class opc_stree extends opc_dnarray {
  var $result_sep='';// if your id's are not unique over the whole structure use here something else!
  var $pathes = array();
  var $cid = NULL;

  /*
  var $_vis= 'visible';
  var $_open = 'open';
  */

  function opc_stree($data){
    parent::opc_dnarray($data);
    $this->vis_init();
  }

  function vis_init(){
    $this->doOnAll('setItem','visible',FALSE);
    $this->doOnAllParents('setItem','open',FALSE);
    foreach(array_keys($this->data) as $ck) $this->data[$ck]['visible'] = TRUE;
    $this->pathes = $this->doOnAll('extract',create_function('$x,$c','return($c->cb_path);'));
  }

  /* sets an element visible (including parents and siblings on all levels
   enforce=TRUE: closes also all subitems to be sure */
  function voc_close($key,$enforce=FALSE){
    $path = $this->pathes[$key];
    if(!$this->hasChilds($path)) return(FALSE); // has no childs -> nothing to do
    $isvis = $this->getitem('visible',$path);
    $this->doOnAll($path,'setItem','visible',FALSE); //set all subchilds invisible
    if($isvis) $this->setItem(TRUE,'visible',$path); // restore own visibility
    if($enforce==TRUE) $this->doOnAllParents($path,'setItems','open',FALSE); // close all sub-parents
    $this->setItem(FALSE,'open',$path); // close node
    return(TRUE);
  }

  /* sets an element visible (including parents and siblings on all levels
   enforce TRUE: open all subchilds */
  function voc_open($key,$enforce=FALSE){
    $path = $this->pathes[$key];
    if(!$this->hasChilds($path)) return(FALSE); // has no childs -> nothing to do
    $isvis = $this->getitem('visible',$path);
    if($enforce==TRUE) $this->doOnAllParents($path,'setItem','open',TRUE);	
    else               $this->setItem(TRUE,'open',$path);
    $this->vis_check($key,FALSE);
    return(TRUE);
  }

  /* sets an element visible (including parents and siblings on all levels
   enforce=TRUE: closes also all subitems to be sure */
  function voc_close_all($enforce=TRUE){
    $this->doOnAll('setItem','visible',FALSE); 
    $ak = array_keys($this->data);
    if($enforce==TRUE){
      $this->doOnAllParents('setItem','open',FALSE); // close all sub-parents
      foreach($ak as $ck)
	$this->data[$ck]['visible'] = TRUE; // top nodes are always visible
    } else {
      foreach($ak as $ck){
	$this->data[$ck]['visible'] = TRUE; // top nodes are always visible
	if(isset($this->data[$ck]['open'])) $this->data[$ck]['open'] = FALSE; 
      }
    }
    return(TRUE);
  }

  /* sets an element visible (including parents and siblings on all levels
   enforce TRUE: open all subchilds */
  function voc_open_all($enforce=TRUE){
    $ak = array_keys($this->data);
    if($enforce==TRUE){
      $this->doOnAllParents('setItem','open',TRUE); // close all sub-parents
      $this->doOnAll('setItem','visible',TRUE); // close all sub-parents
      foreach($ak as $ck)
	$this->data[$ck]['visible'] = TRUE; // top nodes are always visible
    } else {
      foreach($ak as $ck){
	$this->data[$ck]['visible'] = TRUE; // top nodes are always visible
	if(isset($this->data[$ck]['open'])) $this->data[$ck]['open'] = TRUE; 
      }
      $this->vis_check();
    }
  }

  /* sets an element visible (including parents and siblings on all levels
    enforce=FALSE: only if parent item is already visible (or on toplevel itself) */
  function voc_show($key,$enforce=TRUE){
    $path = $this->pathes[$key];
    array_pop($path);
    if($enforce===FALSE and count($path)>1)// check first if parent is already visible
      if($this->getitem('visible',$path)==FALSE) 
	return(FALSE);
    $this->doOnPathRev($path,'setItem','open',TRUE);
    $this->vis_check(NULL,TRUE);
    return(TRUE);
  }

  /* sets an element invisble (by closing parent element if necessary) */
  function voc_hide($key){
    $path = $this->pathes[$key];
    if(count($path)==0) return(FALSE);
    if($this->getItem('visible',$path)==FALSE) return(FALSE);
    array_pop($path);
    $this->setItem(FALSE,'open',$path);
    $this->doOnAll($path,'setItem','visible',FALSE); 
    $this->setItem(TRUE,'visible',$path);
    return(TRUE);
  }

  /* usefull as input from get or so
   arr looks like array('select|show|hide|open|close'=>id|array(id), ...) */
  function voc_arr($arr){
    if(!is_array($arr) or count($arr)==0) return;
    foreach($arr as $key=>$val){
      foreach(is_array($val)?$val:array($val) as $cv){
	switch($key){
	case 'select': $this->cid = $cv; // no break;
	case 'show': $this->voc_show($cv); break;
	case 'hide': $this->voc_hide($cv); break;
	case 'open': $this->voc_open($cv); break;
	case 'close': $this->voc_close($cv); break;
	}
      }
    }

  }

  function vis_check($key=NULL,$all=TRUE){
    if(is_null($key)) $this->doOnAll('modify',array(&$this,'_cb_vischeck'),$all);
    else $this->doOnAll($this->pathes[$key],'modify',array(&$this,'_cb_vischeck'),$all);
  }

  // get all visible nodes
  function vis_get($all=FALSE){
    return($this->doOnAll('extract',array($this,'_cb_getvis'),$all));
  }

  function _cb_vischeck(&$item,&$caller,$all){
    $cv = $item['visible'];
    $item['visible'] = TRUE;
    if(!isset($item['open']) or $item['open']===FALSE or ($cv==TRUE and $all==FALSE))
      $caller->cb_stop = 1;
  }
  
  function _cb_getvis($item,&$caller,$all){
    if(isset($item[$caller->cld])) unset($item[$caller->cld]);
    if(!isset($item['open'])) $item['open'] = NULL;
    if($item['open']!==TRUE and $all===FALSE) $caller->cb_stop = 1;
    return($item);
  }

}
?>
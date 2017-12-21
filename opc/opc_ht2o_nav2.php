<?php

  /*
   * add at position
   * status:  (deactivated: not clickable)
   *  0: node and all childs are hidden
   *  1: node is visible but deactivated, childs are hidden
   *  2: node is visible but deactivated, no effects to childs
   *  3: node is active, no effects to childs
   */

class opc_ht2o_nav2 extends opc_ht2o implements countable, ArrayAccess, Iterator {
  public $class = 'nav2';
  protected $style_def = 'list';

  public $sites = NULL;
  protected $site_def = array('open'=>TRUE,'group'=>NULL,
			      'status'=>3,'urlargs'=>array());

  protected $site_cur = NULL;


  /* countable -------------------------------------------------- */
  function count(){ return $this->sites->cout();}

  /* Array-Access -------------------------------------------------- */
  function offsetExists($key){return $this->sites->offsetExists($key);}
  function offsetGet($key){return $this->sites->offsetGet($key);}
  function offsetSet($key,$val){ return $this->sites->offsetSet($key,$val);}
  function offsetUnset($key){ return $this->sites->offsetUnset($key);}

  /* Iterator -------------------------------------------------- */
  public function current(){ return $this->sites->current();}
  public function key(){     return $this->sites->key();}
  public function next(){    return $this->sites->next();}
  public function rewind(){  return $this->sites->rewind();}
  public function valid(){   return $this->sites->valid();}



  function ht2o_init(){
    $this->sites = new opc_tstore_nav2();
  }

  function style_set__nested(){
    $this->style = 'nested';
    $this->style_set = array('tobj'=>'opc_ht2o_dn',
			     'tstyle'=>'ul',
			     'depth'=>999,
			     'start'=>NULL,
			     );
    return TRUE;
  }

  function style_set__list(){
    $this->style = 'list';
    $this->style_set = array('tobj'=>'opc_ht2o_d1',
			     'tstyle'=>'ul',
			     'depth'=>1,
			     'start'=>NULL,
			     );
    return TRUE;
  }



  function site_add($key,$par=NULL,$add=array()){
    if(is_array($key))
      list($key,$par,$add) = ops_array::extract2list($key,array('key','par'));
    else if(is_array($par))
      list($par,$add) = ops_array::extract2list($par,array('par'));
    else if(!is_array($add))
      $add = array('label'=>$add);
    if(is_null($par)) $par = $this->sites->root_key;
    $this->sites->add_last($key,$par,array_merge($this->site_def,$add));
  }


  function steps__list($set){
    $obj = $set['tobj'];
    $obj = new $obj($this->ht);
    $seq = $this->sites->seq_vis($set['start'],$set['depth']);

    $path = array(0=>NULL);
    foreach($this->sites->seq2str($seq) as $key=>$str){
      $cl = $str['l'];
      if($cl==0) continue;
      $path[$cl] = $key;
      $lnk = $this->link_make($key,$str,$this->sites->data($key));
      $obj->add_path(array_slice($path,1,$cl),$lnk);
    }
    $this->str = &$obj->str;
    return $obj->steps();
  }

  function steps__nested($set){
    $obj = $set['tobj'];
    $obj = new $obj($this->ht);
    $seq = $this->sites->seq_vis($set['start'],$set['depth']);

    $path = array(0=>NULL);
    foreach($this->sites->seq2str($seq) as $key=>$str){
      $cl = $str['l'];
      if($cl==0) continue;
      $path[$cl] = $key;
      $lnk = $this->link_make($key,$str,$this->sites->data($key));
      $obj->add_path(array_slice($path,1,$cl),$lnk);
    }
    $this->str = &$obj->str;
    return $obj->steps();
  }

  function link_make($key,$str,$data){
    $cls = $this->make_class(array(sprintf('lev%02d',$str['l'])));
    $args = $data['urlargs'];
    if(is_array($args)) $args['site'] = $key;
    else $args .= '&site=' . $key;
    $res = array('class'=>$cls,
		 'tag'=>$str['s']==3?'a:auto':'a:broken',
		 'href'=>def($data,'href'),
		 'anchor'=>def($data,'anchor'),
		 'urlargs'=>$args,
		 0=>$data['label']);
    return $res;
  }


  function sites_close($keys=NULL,$deep=FALSE){
    $this->sites->items_close($keys,$deep);
  }

  function sites_open($keys=NULL,$par=TRUE){
    $this->sites->items_open($keys,$par);
  }

  function sites_show($keys=NULL){
    $this->sites->items_show($keys);
  }

  function a(){
    $this->sites->remove('wien',FALSE);
  }
  }

class opc_tstore_nav2 extends opc_tstore {
  public $root_key = 'root';

  // o: open? s: status; c: has childs?
  protected $root_ele = array('u'=>NULL, 'l'=>0,
			      'b'=>NULL, 'f'=>NULL,
			      'df'=>NULL,'dl'=>NULL,
			      'o'=>TRUE,'s'=>3,'c'=>FALSE);

  // data elements which are saved in str rather than data
  protected $str_ext = array('o'=>'open','s'=>'status');

  function data_add($key,$data){
    foreach($this->str_ext as $ck=>$cv)
      $this->str[$key][$ck] = ops_array::key_extract($data,$cv);
    $this->str[$key]['c'] = FALSE;
    if(isset($this->str[$key]['u']))
      $this->str[$this->str[$key]['u']]['c'] = TRUE;
    $this->data[$key] = $data;
  }

  // similar to seq but with max depth
  function seq_vis($pi=NULL,$depth=1,$root=TRUE){
    if(is_null($pi)) $pi = $this->root_key;
    if(!isset($this->str[$pi])) return FALSE;
    $l = $this->str[$pi]['l'];
    $lm = $l + $depth;
    
    $res = $root?array($pi):array();
    if($this->str[$pi]['s']==0) return array();
    if($this->str[$pi]['s']==1) return $res;

    do{
      $down = ($this->str[$pi]['o']
	       and ($this->str[$pi]['l']<$lm)
	       and ($this->str[$pi]['s']>1));
      if($this->nxt($pi,$down)===FALSE) break;
      if($this->str[$pi]['l']<=$l) break;
      if($this->str[$pi]['s']>0) $res[] = $pi;
    } while(TRUE);
    return $res;
  }

  /* close all sites given by keys (exept on level 0)
   * keys = NULL: means all keys
   * deep = TRUE: close childs too
   */
  function items_close($keys,$deep=FALSE){
    if(is_null($keys)){
      foreach($this->str as $ck=>$cv)
	$this->str[$ck]['o'] = $cv['l']==0;
    } else if($deep){
      foreach((array)$keys as $ck){
	foreach($this->childs($ck,TRUE,TRUE) as $ck)
	  $this->str[$ck]['o'] = $this->str[$ck]['o']==0;
      }
    } else {
      foreach((array)$keys as $ck)
	if(isset($this->str[$ck]) and $this->str[$ck]['l']>0)
	  $this->str[$ck]['o'] = $this->str[$ck]['o']==0;
    }
  }

  /* opens all sites given by keys
   * keys = NULL: means all keys
   * par = TRUE: open parents too
   */
  function items_open($keys=NULL,$par=TRUE){
    if(is_null($keys)){
      foreach($this->str as $ck=>$cv)
	$this->str[$ck]['o'] = TRUE;
    } else if($par){
      foreach((array)$keys as $key){
	foreach($this->path($key,TRUE,FALSE) as $ck) 
	  $this->str[$ck]['o'] = TRUE;
      }
    } else {
      foreach((array)$keys as $key)
	if(isset($this->str[$key])) $this->str[$key]['o'] = TRUE;
    }
  }

  /* opens all sites above those given in keys */
  function items_show($keys=NULL){
    if(is_null($keys)){
      foreach($this->str as $ck=>$cv)
	$this->str[$ck]['o'] = TRUE;
    } else {
      foreach((array)$keys as $key){
	$tmp = $this->path($key,FALSE,FALSE);
	if(is_array($tmp)) 
	  foreach($tmp as $ck) $this->str[$ck]['o'] = TRUE;
      }
    }
  }
  protected function cut(&$str){
    $this->str[$str['u']]['c'] = !(is_null($str['b']) and is_null($str['f']));
    return parent::cut($str);
      
  }
}



?>
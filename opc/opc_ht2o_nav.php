<?php

  /*
   more layouts (css, ajax hide/close, simple list)
   more inputs (tables, nested arrays)
   more vis-methods
   more sort-methods
   navigation-visibility <-> htable
   class including level??
   by site_add urlarg-name mitgeben 
   nützlich falls sich dieses über die stufen ändert
   in der navigation über die hierarchie zusammenstellen
   # würde bedeuten als Anker
*/

class opc_ht2o_nav extends opc_ht2o{

  /* array of all sites 
   * used keys
   *  key: identifier (unique)
   *  label: shown text (default: key)
   *  pos: position (user value or calculated by sort, not necessary a proper sequence!)
   *  title (optional): used as tool-tipp text
   *  href (optional): used for the link, default: none (same page)
   *  par: key of the parent node (default: root)
   *  cur T/F: is this the current item (calculated in 'complete')
   *  sel T/F: is this item selected (definied by user)
   *  path T/F: is this item part of the path (calculatd after complete, before sorting)
   *  haschilds T/F: has this node childs (claculated by complete)
   *  open T/F: are the childs of this node visible (claculated by complete/visibility)
   *  ncols (int): aux-value used by htable
   *  navigation-visibility: skip item if set to 'hide, default: 'show'
   */
  protected $sites = array();
  /* current site */
  protected $current_site = 'root';
  /* selected sites */
  protected $selected_sites = array();
  /* path to the current element (excluding the current), calculated during prepare */
  protected $path_sites = array();


  /* current parent node for output (only childs are shown) */
  public $root = 'root';
  /* toplevel: level starting at $this->root;  useful to control the layout */
  public $toplevel = 0;
  /* maxmum number of levels shown; <1 mean no limit */
  public $depth = 0;

  /* style of output
   * available: ul, htable, vtable, nvtable
   * needs method level__[style] and root__[style]
   */
  public $style = 'ul';
  
  /* Which elemets are visible
   * available: vis__cursel (all selected and the current one and their parents)
   * available: vis__cur (current one and his parents)
   *            vis__all (all items, regarding depth!)
   */
  public $visibility = 'vis__cursel'; 

  /* How is the list sorted
   * avaialble: sort__not, sort__pos or a callable function
   */
  public $sorting = 'sort__not';

  /* argument name for the main-value (key) */
  public $urlarg_site = 'site';
  public $urlargs = array();
  /* basic class */
  public $class = 'nav_%lev% nav_%style%';

  /* standard kind of the single links
   * needs a methid link__[kind]
   */
  public $link_kind = 'a';

  function init_key($key,$val){
    switch($key){
    case 'style':
      $this->set_style($val);
      return TRUE;
    }
    return parent::init_key($key,$val);
  }

  function sites_get(){ return $this->sites;}
  function sites_set($sites,$clear=FALSE){ 
    $res = $clear?array():$this->sites;
    foreach($sites as $site) $this->site_add($site);
  }

  function site_exists($key){
    return isset($this->sites[$key]);
  }

  function site_get($key=NULL,$what=NULL,$def=NULL){
    if(is_null($key)) $key = $this->current_site;
    $tmp = def($this->sites,$key);
    if(is_null($what)) return $tmp;
    return def($tmp,$what,$def);
  }

  function site_unset($key){ 
    if(isset($this->sites[$key])){
      unset($this->sites[$key]);
      $chld = array_filter($this->sites,create_function('$x','return $x["par"]==\'' . $key . '\';'));
      foreach($chld as $cc) $this->site_unset($cc);
    }
  }

  function subsites_get($par='root'){ 
    $res = array();
    foreach($this->sites as $key=>$val) if($val['par']===$par) $res[$key] = $val;
    return $res;
  }

  function cur_get() { return $this->current_site;}
  function cur_unset() { $this->current_site = NULL;}
  function cur_set($site=NULL,$def=NULL){
    if(count($this->sites)==0) return NULL;

    // selection is ok
    if(isset($this->sites[$site]))
      return $this->current_site = $site;

    if(isset($this->sites[$def]))
      return $this->current_site = $def;
    // search first visible site on top level
    foreach($this->sites as $ck=>$cs){
      if($cs['par']!=$this->root) continue;
      $this->current_site = $ck;
      return $this->current_site;
    }
    return NULL;
  }

  function path_get() { return $this->path_sites;}

  function sel_get() { return $this->selected_sites;}
  function sel_set($keys=array(),$add=TRUE){
    if(count($this->sites)==0) return;
    $ak = array_keys($this->sites);
    $keys = (array)$keys;
    if($add)
      $this->selected_sites = array_merge($this->selected_sites,array_intersect($ak,$keys));
    else
      $this->selected_sites = array_intersect($ak,$keys);
  }

  function sel_unset($keys=array()){
    if(count($this->sites)==0) return;
    $this->selected_sites = array_diff($this->selected_sites,(array)$keys);
  }

  function ___get($key,&$res){
    if(substr($key,0,2)==='s_'){
      $res = def($this->sites,substr($key,2));
      return 0;
    }
    switch($key){
    case 'current_site': case 'cur': $res = $this->cur_get(); return 0;
    case 'selected_sites': case 'sel': $res = $this->sel_get(); return 0;
    case 'sites': $res = $this->sites; return 0;
    case 'path': case 'path_sites': $res = $this->path_sites; return 0;
    }
    return parent::___get($key,$res);
  }

  function ___set($key,$val) { 
    switch($key){
    case 'current_site': $this->cur_set($val); return 0;
    case 'selected_sites': $this->sel_set($val,FALSE); return 0;
    case 'style': $this->set_style($val); return 0;
    }
    return parent::___set($key,$val);
  }



  /* add a new item to the sites array
   * each argument may be the value itself or an named array
   * additional keys for the array
   *  href: link to an external page, or if starting with hash: define an anchor
   *  class/style/title/id/name -> used as for every other tag
   *  urlargs (array): used as additional arguments for the link
   *  link_kind
   *
   */
  function site_add($key,$par=NULL,$label=NULL,$pos=NULL,$state=NULL,$grp=NULL){
    $ar = array('key','par','label','pos','state','grp');
    $set = array();
    foreach($ar as $ck){
      if(is_array($$ck)) $set = array_merge($set,$$ck);
      else if(is_object($$ck)) $set = array_merge($set,$$ck->attr);
    }
    foreach($ar as $ck) 
      if(!is_array($$ck) and !is_null($$ck) and !is_object($$ck)) $set[$ck] = $$ck;
    $this->sites[$set['key']] = $set;
  }
  

  // post modification of sites
  function site_mod($site,$key,$val){
    if(!isset($this->sites[$site])) return 1;
    $this->sites[$site][$key] = $val;
  }


  /* creates a whole bunch of classes for detailed layouting with css 
   * should be used in this class instead of 'make_class'
   */
  function nav_make_class($ele,$lev,$list=array()){
    if(is_array($ele)){
      if(isset($ele['link_kind'])) $list[] = $ele['link_kind'];
      $list[] = $ele['cur']?'this':'those';
      if($ele['sel']) $list[] = 'selected';
      if($ele['path']) $list[] = 'path';
    }
    $res = $this->make_class($list,$this->_make_class($lev));
    if(isset($ele['class'])) $res .= ' ' . $ele['class'];
    return $res;
  }

  /* repalces a %style% in class by the current style -> better class-name for css */
  function _make_class($lev){
    return str_replace(array('%style%','%lev%'),array($this->style,$lev),$this->class);
  }

  /* ================================================================================
   preparation before output
   ================================================================================ */

  /* call prep-details */
  function prepare(){
    $this->complete();
    $ak = array_keys($this->sites);
    foreach($ak as $ck) 
      if(def($this->sites[$ck],'state','ok')=='na')
	unset($this->sites[$ck]);

    // caluclate path_sites
    $this->path_sites = array();
    if(isset($this->sites[$this->current_site])){
      $tmp = $this->sites[$this->current_site];
      while(def($tmp,'par','root')!='root'){
	array_unshift($this->path_sites,$tmp['par']);
	$this->sites[$tmp['par']]['path'] = TRUE;
	$tmp = $this->sites[$tmp['par']];
      }
    }

    $this->sort();

    $mth = $this->visibility;
    $this->$mth();
  }

  /* ensure the completness of all sites */
  function complete(){
    $res = array();
    $par = array_diff(array_unique(array_map(create_function('$x','return def($x,"par","root");'),$this->sites)),array('root'));
    foreach($this->sites as $site){
      if(!isset($site['par'])) $site['par'] = 'root';
      if(!isset($site['label'])) $site['label'] = $site['key'];
      $def = array('cur'=>$site['key']===$this->current_site,
		   'sel'=>in_array($site['key'],$this->selected_sites),
		   'path'=>FALSE,
		   'haschilds'=>in_array($site['key'],$par),
		   'open'=>FALSE,
		   'ncols'=>0,
		   );
      $res[$site['key']] = array_merge($def,$site);
    }
    $this->sites = $res;
  }
  
  /* --------------------------------------------------------------------------------
   Sorting
   -------------------------------------------------------------------------------- */
  function sort($mth=NULL){
    if(is_null($mth)) $mth = $this->sorting;
    if(is_callable($mth)) return uasort($this->sites,$mth);
    $this->$mth();
  }

  /* no sorting at all */
  function sort__not(){}

  /* using field key */
  function sort__key(){
    uasort($this->sites,create_function('$x,$y','return $x["key"]>$y["key"]?1:-1;'));
  }

  /* using field label */
  function sort__label(){
    uasort($this->sites,create_function('$x,$y','return $x["label"]>$y["label"]?1:-1;'));
  }

  /* using the value 'pos' of the pages
   * pos >=0: as the position defines it
   * pos NULL/missing: after the elements with pos>0
   * pos < 0: after all others, where -1 is the last element at all
   * if two elements have the same position the result is not defnied (or same as order when added?)
   */
  function sort__pos(){
    $maxpos = array_reduce($this->sites,create_function('$o,$n','return def($n,"pos",0)>$o?$n["pos"]:$o;'),0);
    foreach(array_keys($this->sites) as $ck){
      if(!isset($this->sites[$ck]['pos'])) $this->sites[$ck]['pos'] = ++$maxpos;
      else if($this->sites[$ck]['pos']<0) $this->sites[$ck]['pos']+=100000;
      else $this->sites[$ck]['pos'] = (int)$this->sites[$ck]['pos'];
    }
    uasort($this->sites,create_function('$x,$y','return $x["pos"]>$y["pos"]?1:-1;'));
  }
  

  /* --------------------------------------------------------------------------------
   Visibility
   -------------------------------------------------------------------------------- */

  function vis__all(){
    foreach(array_keys($this->sites) as $ck){
      $this->sites[$ck]['open'] = $this->sites[$ck]['haschilds'];
    }
  }

  /* sets some nodes to open so that the current and all selected nodes are visible */
  function vis__cursel(){
    $sites = $this->sites;
    $vissites = $this->selected_sites;
    if(isset($sites[$this->current_site])) {
      $sites[$this->current_site]['open'] = TRUE;
      $vissites[] = $this->current_site;
    }
    $this->sites = $this->show_them($sites,$vissites);
  }

  /* sets some nodes to open so that the current node is visible */
  function vis__cur(){
    $sites = $this->sites;
    if(isset($sites[$this->current_site])) {
      $sites[$this->current_site]['open'] = TRUE;
      $vissites = array($this->current_site);
    } else $vissites = array();
    $this->sites = $this->show_them($sites,$vissites);
  }

  /* modifies sites so that those in vissites will be visible */
  function show_them($sites,$vissites){
    $open = array_keys(array_filter($sites,create_function('$x','return $x["open"];')));
    $open[] = 'root';
    $nxt = array();
    foreach($vissites as $key) $nxt[] = $sites[$key]['par'];
    $doo = array_diff(array_unique($nxt),$open);
    while(count($doo)>0){
      $nxt = array();
      foreach($doo as $ck) {$sites[$ck]['open'] = TRUE; $nxt[] = $sites[$ck]['par'];}
      $open = array_merge($open,$doo);
      $doo = array_diff(array_unique($nxt),$open);
    }
    return $sites;
  }


  /* ================================================================================
   output generation
   ================================================================================ */

  /*   calls root__[style] */
  function _output(&$ht){
    $this->prepare();
    $mth = 'root__' . $this->style;
    if(method_exists($this,$mth))
      return $this->$mth($ht,$this->root,$this->toplevel);
    trigger_error("Unkown method $mth in __CLASS__");
    return NULL;

  }

  /* select the childs of par and calls level__[style] */
  protected function level($ht,$par,$level){
    if($this->depth>0 and 1+$level>$this->toplevel+$this->depth) return;
    $subs = array();
    foreach($this->sites as $ck=>$cv){
      if($cv['par']!=$par or def($cv,'state','ok')=='hidden') continue;
      if(def($cv,'navigation-visibility','show')=='hide') continue;
      $subs[$ck] = $cv;
    }
    if(count($subs)==0) return;
    $mth = 'level__' . $this->style;
    return $this->$mth($ht,$subs,$level,$par);
  }

  /* --------------------------------------------------------------------------------
   list layout (using ul)
   -------------------------------------------------------------------------------- */
  function root__ul(&$ht,$root,$lev){
    return $this->level($ht,$root,$lev);
  }

  function level__ul(&$ht,$subs,$lev,$par){
    $this->pointers['childs-' . $par] = $ht->open('ul',$this->nav_make_class(def($this->sites,$par),$lev,array('childs')));
    foreach($subs as $val){
      $this->pointers['item-' . $val['key']] = $ht->open('li',$this->nav_make_class($val,$lev,array('item')));
      $this->pointers['link-' . $val['key']] = $this->link($ht,$val,$lev);
      if($val['open']) $this->level($ht,$val['key'],$lev+1);
      $ht->close();
    }
    return $ht->close();
  }


  /* --------------------------------------------------------------------------------
   list layout (using div)
   -------------------------------------------------------------------------------- */
  function root__div(&$ht,$root,$lev){
    return $this->level($ht,$root,$lev);
  }

  function level__div(&$ht,$subs,$lev,$par){
    $this->pointers['childs-' . $par] = $ht->open('div',$this->nav_make_class(def($this->sites,$par),$lev,array('childs')));
    foreach($subs as $val){
      $this->pointers['item-' . $val['key']] = $ht->open('div',$this->nav_make_class($val,$lev,array('item')));
      $this->pointers['link-' . $val['key']] = $this->link($ht,$val,$lev);
      $ht->close();
      if($val['open']) $this->level($ht,$val['key'],$lev+1);
    }
    return $ht->close();
  }

  /* --------------------------------------------------------------------------------
   list layout (using div)
   -------------------------------------------------------------------------------- */
  function root__divspan(&$ht,$root,$lev){
    $this->level($ht,$root,$lev);
  }

  function level__divspan(&$ht,$subs,$lev,$par){
    $this->pointers['childs-' . $par] = $ht->open('div',$this->nav_make_class(def($this->sites,$par),$lev,array('childs')));
    foreach($subs as $val){
      $this->pointers['item-' . $val['key']] = $ht->open('span',$this->nav_make_class($val,$lev,array('item')));
      $this->pointers['link-' . $val['key']] = $this->link($ht,$val,$lev);
      $ht->close();
      if($val['open']) $this->level($ht,$val['key'],$lev+1);
    }
    return $ht->close();
  }


  /* --------------------------------------------------------------------------------
   vertical table (1-dimensional, nested)
   -------------------------------------------------------------------------------- */
  function root__nvtable(&$ht,$root,$lev){
    return $this->level($ht,$root,$lev);
  }

  function level__nvtable(&$ht,$subs,$lev,$par){
    $this->pointers['childs-' . $par] = $ht->open('table',$this->nav_make_class(def($this->sites,$par),$lev,array('childs')));
    foreach($subs as $val){
      $this->pointers['item-' . $val['key']] = $ht->open('tr',$this->nav_make_class($val,$lev,array('row')));
      $ht->open('td',$this->nav_make_class($val,$lev,array('item')));
      $this->pointers['link-' . $val['key']] = $this->link($ht,$val,$lev);
      if($val['open']) $this->level($ht,$val['key'],$lev+1);
      $ht->close(2);
    }
    return $ht->close();
  }

  /* --------------------------------------------------------------------------------
   vertical table (1 dimensional not nested)
   -------------------------------------------------------------------------------- */
  function root__vtable(&$ht,$root,$lev){
    $this->pointers['root'] = $ht->open('table',$this->make_class(array('main'),$this->_make_class($lev)));
    $this->level($ht,$root,$lev);
    return $ht->close();
  }

  function level__vtable(&$ht,$subs,$lev,$par){
    foreach($subs as $val){
      $this->pointers['item-' . $val['key']] = $ht->open('tr',$this->nav_make_class($val,$lev,array('row')));
      $ht->open('td',array('class'=>$this->nav_make_class($val,$lev,array('item'))));
      $ht->add(str_repeat('&ensp;',$lev));
      $this->pointers['link-' . $val['key']] = $this->link($ht,$val,$lev);
      $ht->close(2);
      if($val['open']) $this->level($ht,$val['key'],$lev+1);
    }
  }

  /* --------------------------------------------------------------------------------
   horizontal table (not nested, 2 dimensional, using col/rowspan)
   -------------------------------------------------------------------------------- */
  function root__htable(&$ht,$root,$lev){
    $this->pointers['root'] = $ht->open('table',$this->make_class(array('main'),$this->_make_class($lev)));
    $this->level($ht,$root,$lev);
    $res = $ht->close();
    // set col/rowspan to finish the table
    $max = array_reduce($this->sites,create_function('$o,$n','return max($o,def($n,"lev",0));'),0);
    $min = array_reduce($this->sites,create_function('$o,$n','return min($o,def($n,"lev",0));'),0);
    for($i=$max;$i>=$min;$i--){
      $subs = array_filter($this->sites,create_function('$x','return def($x,"lev",-1)==' . $i . ';'));
      foreach($subs as $val){
	if($i>$min and $val['par']!='root') $this->sites[$val['par']]['ncols'] += max(1,$val['ncols']);
	if($i<$max){
	  $rows = ($val['open'] and $val['haschilds'])?1:(1+$max-$i);
	  if($rows>1) $ht->obj->data[$this->pointers['item-' . $val['key']]]->set('rowspan',$rows);
	  if($val['ncols']>1) $ht->obj->data[$this->pointers['item-' . $val['key']]]->set('colspan',$val['ncols']);
	} 
      }
    }
    return $res;
  }

  function level__htable(&$ht,$subs,$lev,$par){
    // search right row or create it
    $new = !isset($this->pointers['row-' . $lev]);
    if($new){
      $ht->in($this->pointers['root'],'lcl');
      $this->pointers['row-' . $lev] = $ht->open('tr',$this->nav_make_class(def($this->sites,$par),$lev,array('row-' . $lev)));
    } else $ht->in($this->pointers['row-' . $lev],'lcl');

    // add table cells with links
    foreach($subs as $val){
      $this->sites[$val['key']]['ncols'] = 0;
      $this->sites[$val['key']]['lev'] = $lev;
      $this->pointers['item-' . $val['key']] = $ht->open('td',$this->nav_make_class($val,$lev,array('item')));
      $this->pointers['link-' . $val['key']] = $this->link($ht,$val,$lev);
      $ht->close(1);
    }
    if($new) $ht->close(); // close a new create line properly
    $ht->out(); // cl,ose temporary pointer
    // go one level deeper
    foreach($subs as $val) if($val['open']) $this->level($ht,$val['key'],$lev+1);
  }


  /* ================================================================================
   common link function (typically used by level__xxx
   ================================================================================ */

  function link(&$ht,$dat,$lev){
    $add = array('title'=>def($dat,'title',$dat['label']),
		 'style'=>def($dat,'style',NULL),
		 'id'=>def($dat,'id',NULL),
		 'name'=>def($dat,'name',NULL),
		 'class'=>$this->nav_make_class($dat,$lev,array('link')),
		 );
    $href = def($dat,'href');
    $args = array_merge(def($dat,'args',array()),def($dat,'urlargs',$this->urlargs));
    if(!is_null($this->urlarg_site) and !isset($args[$this->urlarg_site]))
      $args[$this->urlarg_site] = $dat['key'];

    $mth = 'link__' . def($dat,'link_kind',$dat['cur']?'cur':$this->link_kind);
    return $this->$mth($ht,$dat['label'],$href,$args,$add);
  }

  /* Standard link */
  function link__a(&$ht,$label,$href,$args,$add){
    if(is_null($href))              return $ht->a($label,$args,$add);
    if(substr($href,0,1)==='#')     return $ht->anchor($label,substr($href,1),$add);
    if(strpos($href,'://')===FALSE) return $ht->page($href,$label,$args,$add);
    return $ht->www($href,$label,$args,$add);
  }

  /* Standard link for the current item */
  function link__cur(&$ht,$label,$href,$args,$add){
    return $this->link__a($ht,$label,$href,$args,$add);
  }

  /* Standard link for a not yet available feature (cs: coming soon)*/
  function link__cs(&$ht,$label,$href,$args,$add){
    $add['class'] .= ' ' .  $this->class . '-cs';
    $add['title'] = def($add,'title','') . ': coming soon';
    return $ht->span($label,$add);
  }

  function set_style($style){
    $this->style = $style;
  }
  }
?>
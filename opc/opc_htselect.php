<?php
  /* 
   page erzeugt leere id [-> < .... id>]
   page funzt nicht mehr
   */
require_once('ops_arguments.php');

class opc_htselect extends opc_ht{
  //list of available items, named array
  var $items = array();
  // current item, the current element will get the class [class]_this
  var $cid = NULL;
  //default id used if not null and cid is not set in items
  var $cid_def = NULL;

  // ========================= Standard Output layout =========================
  /*
   0/htable: horizontal one row table
   1/vtable: vertical one column table
   2/[tag name]: simple text construction inside a tag (default: p)
   3/list/ul/ol: ul(default) or ol list
   */
  var $layout = 'p';
  // specialities
  var $layout_tab_equal_size = FALSE;// used by htable -> width=100%
  var $layout_tab_full_width = FALSE;// used by htable -> colgroup equal width
  var $layout_text_sep = '&nbsp; &#124;&nbsp;'; // used for layout 2 as separator


  // ========================= Classes and Styles =========================
  // standard class or class prefix (nav_...) useful if multiple slection elements are present
  var $class = 'sel'; // used for class attribute, usefull for css
  var $id = NULL; // used for id attribute, usefull for ajax; if NULL class is used


  // ========================= Linking =========================
  /* link pattern used for the a-tag
   url: base url including protocol (eg: http://index.php)
   argname_id: variable name for the id
   args: additional arguments as named array
       if you need multiple arguments, use an array for args
       the element with key 0 is used as name for the id
       all other arguments should be of syntax key=>valuevar resObj;
   */
  var $url = NULL; // will be initaliced inside the constructor
  var $argname_id = NULL; // argument name for the url; if null id is used
  var $args = array();

  // AJAX Settings ------------------------------
  var $ajax = FALSE; // FALSE -> use href; TRUE -> use javascript
  var $ajax_obj = NULL; // js-name of this object, if NULL id is used
  var $ajax_php = NULL; // php-file which should answer (or NULL)

  /* array with hint-textes which are used for the tilte attribute of a-tag ...
   key correspond to cid
   */
  var $hints = array();




  //internal --------------------------------------------------
  var $_items = NULL;// array of arrays (mand: item, class)
  var $_nitems = NULL;//number of items

  // copy from above but with proofed values
  var $_id = NULL;
  var $_argname_id = NULL;
  var $_ajax_obj = NULL;

  /* constructor
   boolean -> used for xhtml
   array -> used for items
   scalar -> used as current id
  */
  function opc_htselect(/* ... */){
    $val = func_get_args();
    $def = array(FALSE,NULL,array());
    $typ = array('boolean','scalar','array');
    list($xhtml,$this->cid,$this->items) = ops_arguments::setargs($val,$def,$typ);

    parent::opc_ht($xhtml);
    $this->url = $this->myself();
  }

  
  /* create a link   */
  function _link_a($text,$id,$attr=array()){
    $attr = $this->_attr_auto($attr);
    if(is_float($id)) $id = (int)$id;
    if(array_key_exists($id,$this->hints))  $attr['title'] = $this->hints[$id];
    if($this->ajax){
      if(!is_null($this->ajax_php)){
	$phpcall  = $this->ajax_php . '?' .  $this->_argname_id . '=' . $id;
      } else $phpcall = '';
      $attr['href'] = 'javascript:' . $this->_ajax_obj . '.select(\'' . $id . '\','
	. '\'' .  $phpcall . '\')';
    } else {
      $url = $this->_link_args($id);
      if($url[0]!='#') $attr['href'] = $this->url . '?' .  $url;
      else $attr['href'] = $url;
    }
    $res = '<a' . $this->implode_attr($attr) . '>' . $text . '</a>';
    return($res);
  }

  /* subfunction of _link_a, creates the href-part$ */
  function _link_args($id){
    $args = $this->args;
    if(isset($args['#'])){
      $anch = '#' . $args['#'];
      unset($args['#']);
    } else $anch = '';
    if($this->_argname_id[0]=='#') $anch = $this->_argname_id . $id;
    else $args[$this->_argname_id] = $id;
    if(empty($args)) return($anch);
    return($this->implode_urlargs($args,'&') . $anch);
  }
  
  function collect(){die('Please overload this function');}


  function prepare(){
    $this->_id = is_null($this->id)?$this->class:$this->id;
    $this->_argname_id = is_null($this->argname_id)?$this->_id:$this->argname_id;
    $this->_ajax_obj = is_null($this->ajax_obj)?$this->_id:$this->ajax_obj;
    $this->_nitems = count($this->items);
    $keys = array_keys($this->items);
    // default key if defined
    if(!in_array($this->cid,$keys) and !is_null($this->cid_def)){
      if(in_array($this->cid_def,$keys)) $this->cid = $this->cid_def;
      else $this->cid = $keys[0];
    }
    $this->_items = $this->collect();
  }
  
  /* returns NULL if it does not known the asked layout -> overload*/
  function select    (){$this->add($this->select2arr());}
  function select2str(){return($this->_implode2str($this->select2arr()));}
  function select2arr(){
    $this->prepare();
    switch($this->layout){
    case 'htable': $res = $this->_htable(); break;
    case 'vtable': $res = $this->_vtable(); break;
    case 'ul': case 'ol': $res = $this->_list($this->layout); break;
    case 'list': case 'li': $res = $this->_list('ul'); break;
    case 'text': case 'span': case 'div': case 'p': $res = $this->_text($this->layout); break;
    default:
      return(NULL);
    }
    return($res);
  }

  function _text($layout,$sep=NULL){
    $res = array('tag'=>$layout=='text'?'div':$layout,'class'=>$this->class);
    $ii = 0;
    foreach($this->_items as $val){
      if($ii++>0) $res[] = $this->layout_text_sep;
      $att = array();
      if(!is_null($val['class'])) $att['class'] = $val['class'];
      if(isset($val['id']) and !is_null($val['id'])) $att['id'] = $val['id'];
      $res[] = $this->tag2arr('span',$val[0],$att);
    }
    return($res);
  }

  function _list($layout){     
    return($this->chain2arr($this->_items,'li',
			    array('tag'=>$layout,
				  'class'=>$this->class)));
  }

  function _htable(){
    $res = $this->opentable2arr($this->layout_tab_full_width,
				$this->layout_tab_equal_size?$this->_nitems:NULL,
				array('class'=>$this->class));
    $res[] = $this->chain2arr($this->_items,'td','tr');
    return($res);
  }

  function _vtable(){
    $res = array('tag'=>'table','class'=>$this->class);
    foreach($this->_items as $val){
      $res[] = array('tag'=>'tr',array('tag'=>'td','class'=>$val['class'],
				       'id'=>isset($val['id'])?$val['id']:NULL,
				       $val[0]));
    }
    return($res);
  }

}



/* ============================================================
 ==============================================================
 ========================== L I S T ===========================
 ==============================================================
 ============================================================*/


class opc_htselect_list extends opc_htselect {

  // items may be a named array of named array in this case definie the following vars
  var $key_text = NULL; // field name of the text to display
  var $key_tip = NULL; // used as tooltip
  var $layout = 'li';


  function collect(){
    //collect items
    $items = array();
    foreach($this->items as $key=>$val){
      $attr = array('class'=>$this->class,'id'=>$this->_id . '_' . $key);
      if($this->cid===$key) $attr['class'] .= ' ' . $this->class . '_this';
      $txt = $val;
      if(is_array($val)){
	$txt = $val[$this->key_text];
	if(!empty($val[$this->key_tip])) $attr['title'] = $val[$this->key_tip];
      }
      $items[$key] = array($this->_link_a($txt,$key,$attr),
			   'class'=>$this->class,
			   'id'=>$this->_id . '_par_' . $key);
    }
    if(array_key_exists($this->cid,$items))
      $items[$this->cid]['class']  .= ' ' . $this->class . '_this';
    
    return($items);
  }

  function select2arr(){
    $ar = func_get_args();
    $res = parent::select2arr();
    if(!is_null($res)) return($res);
    return(call_user_func_array(array($this,'_' . $this->layout),$ar));
  }

  function _block(){
    if(!array_key_exists($this->cid,$this->items)) return($this->_htable());
    $ni = $this->_nitems - 1;
    $res = $this->opentable2arr($this->layout_tab_full_width,
				$this->layout_tab_equal_size?$ni:NULL,
				array('class'=>$this->class));
    $ci = $this->_items;
    $citem = $ci[$this->cid];
    unset($ci[$this->cid]);
    $res[] = $this->chain2arr($ci,'td','tr');
    $res[] = array('tag'=>'tr',array('tag'=>'td','class'=>$citem['class'],
			      'id'=>$citem['id'],'colspan'=>$ni,$citem[0]));
    return($res);
  }
  
}










class opc_htselect_array extends opc_htselect_list{
  var $class = 'selarray';

  /* layout settings
   ncol: number of collumns; NULL -> auto
   nrow: number of rows; NULL -> auto
   optim: 0: no optimization
          1: ncol will be optimised
	  2: nrwo will be optimised
   ratio: ration between width and height for an idealistic cell
          used if ncol and nrow are NULL to calculate them	  
   byrow: If TRUE (default) the elements will appear rowwise
   raster: 0: the width of the cells may vary everywhere
           1: cells in the same column have the same width
	   2: all cells have the same width
	   3: uses inst-var sizes

   rotate: 0: no rotation
           1: line with current item is on top, rest stays
	   2: line with current item is on bottom, rest stays
	   3: lines are rotate until the current item is on top
	   4: lines are rotate until the current item is on bottom
	   if rotate is set the top (bottom) row gets the class ledger
  */
  var $ncol = NULL;
  var $nrow = NULL;
  var $ratio = 4;
  var $optim = 1;
  var $byrow = TRUE;
  var $raster = 1;
  var $sizes = array(); // used for raster 3, array of number of cells per row!
  var $layout = 'simple'; // at the moment the one and only!
  var $rotate = 0;
  
  


  
  function select2arr(/* ... */){
    $ar = func_get_args();
    $this->prepare();
    $ni = $this->_nitems;
    if($this->raster==3){//uses this->sizes
      $nc = $this->sizes;
      $nr = count($nc);
    } else {
      // calculate optimal size
      $nc = $this->ncol;
      $nr = $this->nrow;
      if(!is_null($nc)) $nr = ceil($ni/$nc);
      else if(!is_null($nr)) $nc = ceil($ni/$nr);
      else {
	$nr = ceil(sqrt($ni/$this->ratio));
	$nc = ceil($ni/$nr);
      }
      switch($this->optim){
      case 1: if($nc*$nr-$ni>$nr) $nc = ceil($ni/$nr); break;
      case 2: if($nc*$nr-$ni>$nc) $nr = ceil($ni/$nc); break;
      }
    }
    array_unshift($ar,$ni,$nr,$nc);
    $res = call_user_func_array(array($this,'_' . $this->layout),$ar);
    return($res);
  }

  function _simple($ni,$nr,$nc /* */){
    if(is_array($nc)){
      $ncc = $nc;
      $nc = ceil(array_sum($ncc)/count($ncc));
    } else $ncc = array_fill(0,$nr,$nc);
    $res = array('tag'=>'table','class'=>$this->class);
    switch($this->raster){
    case 0: case 3:$res['class'] .= '_outer'; break;
    case 2:
      $cw = floor(100/$nc) . '%';
      $res[] = $this->stag2arr('colgroup',array('span'=>$nc,'width'=>$cw),FALSE);
      break;
    }

    $cells = array();
    foreach($this->_items as $ci) $cells[] = array_merge(array('tag'=>'td'),$ci);
    $rows = ops_array::split($cells,$ncc,!$this->byrow); // create subarrays
    $tl = -1;//line with the current item
    $cf = create_function('$xx,$yy','return($xx or strpos($yy["class"],"_this"));');
      
    for($ii=0;$ii<$nr;$ii++){      
      if($tl<0 and $this->rotate>0 and array_reduce($rows[$ii],$cf,FALSE)) $tl = $ii;
      if($this->raster==1 or $this->raster==2)// add empty cells
	for($jj=count($rows[$ii]);$jj<$ncc[$ii];$jj++) 
	  $rows[$ii][$jj] = array('tag'=>'td','class'=>$this->class . '_empty','&nbsp;');
    }
    if($tl>=0 and $this->rotate>0){ //rotate if necessary
      switch($this->rotate){
      case 1: $ext = array_splice($rows,$tl,1); $rows = array_merge($ext,$rows); break;
      case 2: $ext = array_splice($rows,$tl,1); $rows = array_merge($rows,$ext); break;
      case 3: $rows = array_merge(array_splice($rows,$tl),$rows);   break;
      case 4: $rows = array_merge(array_splice($rows,$tl+1),$rows); break;
      }
    }

    // construct them
    for($cr=0;$cr<$nr;$cr++){
      if($this->raster==0 or $this->raster==3){
	$crow = array('tag'=>'td',
		      array('tag'=>'table','class'=>$this->class . '_inner ' . $this->class,
			    'width'=>'100%',
			    array_merge(array('tag'=>'tr'),$rows[$cr])));
	$res[] = array('tag'=>'tr','id'=>$this->id . '_row' . $cr,$crow);
      } else {
	$crow = $rows[$cr];
	$res[] = array_merge(array('tag'=>'tr','id'=>$this->id . '_row' . $cr),$crow);
      }
    }
    if($this->rotate>0){
      $ak = array_keys($res);
      if($this->rotate==2 or $this->rotate==4) $ak = array_reverse($ak);
      foreach($ak as $ck){
	if(is_string($ck)) continue;
	if($res[$ck]['tag']!='tr') continue;
	$res[$ck]['class'] = 'ledger';
	break;
      }
    }
    return($res);
  }
}




/* ============================================================
 ==============================================================
 ========================== P A G E ===========================
 ==============================================================
 ============================================================*/

// At the moment this class does not support Ajax

class opc_htselect_page extends opc_htselect {
  var $class = 'page';
  var $argname_unit = 'select_unit';//used to identfiy the mode of the form

  // numbering
  var $ipp = 10; // items per page
  var $id_unit = 1; // cid is (0) the item number, (1) page number
  var $disp_unit = 0; // show (0) item number/range, (1) page number
  var $min_item = 1; // lowest item number (typically 0 or 1)
  var $max_item = 1;// highest item number (#items = max_item - min_item + 1)
  var $min_page = 1; // max page will be calculated!, step is allways 1! 
  
  /* text pattern: how to display the item/page numbers
   _item: %1% inside the pattern will be replaced by the item/page number
   _first: same as _item but used only for the first item/page
   _last: same as _item but used only for the last item/page
   _one: same as _item but used only if only one item/page exists
   _range: how to connect the two items in a range
  */
  var $pat_item = '%1%';
  var $pat_first = '[%1%';
  var $pat_last = '%1%]';
  var $pat_one = '[%1%]';
  var $pat_range = '%1%&nbsp;&minus;&nbsp;%2%';

  

  /* 
   disp_mode: how to handle if there are many pages to select
   0: show all
   1: show middle part only (disp_max)
   2: show middle part and first/last (class = [class]_end)
   3: show middle part and first/last and half distance  (class = [class]_half)
   arrow_mode
   0: no arrows
   1: left & right side
   2: left side
   3: right side
   */

  var $disp_mode = 3;
  var $disp_max = 10;
  var $arrow_mode = 1;
  var $jump_text = '';//if non empty a form for direct jumps will be shown

  /* arrow definitions (NULL values will hide them */
  var $arrow_first = '&#124;&lt;'; // to the first item/page
  var $arrow_last = '&gt;&#124;'; // to the last item/page
  var $arrow_left = '&lt;'; // one page backward
  var $arrow_right = '&gt;'; // one page forward
  var $arrow_left_n = '&lt;&lt;'; // disp_max pages backward
  var $arrow_right_n = '&gt;&gt;'; // disp_max pages forward
  var $arrow_sep = ''; // separator between the arrows, you may use css too!

  
  /* internal variables */
  var $_npages = NULL;
  var $_cpage = NULL;


  /* sets the new id
   if byform is true it will adjust a potential differnece between
   the displayed unit and the unit of the id */
  function set_id($cid,$byform=FALSE){
    if($byform){
      if($this->disp_unit==0 and $this->id_unit==1){
	$cid = floor(($cid-$this->min_item)/$this->ipp)+$this->min_page;
      } else if($this->disp_unit==1 and $this->id_unit==0){
	$cid = $this->min_item+$this->ipp*($cid-$this->min_page);
      }
    }
    $this->cid = $cid;
  }

  /* will replace %1% in pat by the second argument, %2% by the third .... */
  function _pat($pat/*...*/){
    $na = func_num_args();
    $args = func_get_args();
    for($ca=1;$ca<$na;$ca++) $pat = str_replace('%' . $ca . '%',$args[$ca],$pat);
    return($pat);
  }

  // Collect the different things ========================================
  function collect(){
    $this->_npages = ceil(($this->max_item-$this->min_item+1)/$this->ipp);
    if($this->_npages<=0) return(array());
    if($this->id_unit==1) $cp = $this->cid - $this->min_page;
    else $cp = floor(($this->cid-$this->min_item)/$this->ipp);
    $this->_cpage = max(0,min($cp,$this->_npages-1));
    $this->_get_pages();
    $this->_set_classes();
    $this->_set_ids();
    $this->_set_elements();
    $this->_make_links();
    $this->_set_arrows();
    $this->_make_jump();
    $col = $this->_items;
    $items = array();
    while(count($col['item'])>0)
      $items[] = array(array_shift($col['item']),'class'=>array_shift($col['class']));
    return($items);
  }

  // Get the pages which shopuld be displayed ==============================
  function _get_pages(){
    $md = max(7,(int)$this->disp_max);
    $np = $this->_npages;
    $cp = $this->_cpage;
    if($this->disp_mode>0 and $this->_npages>$md){//show subrange
      $fi = max(0,ceil($cp - $md/2));
      $li = $fi + $md-1;
      if($li+1>$np){$li = $np - 1; $fi = max(0,$li-$md+1);}
      $prange = range($fi,$li);
      if($this->disp_mode>1){ // special cases at the end
	$prange[0] = 0; 
	$prange[$md-1] = (int)$np-1; 
	if($this->disp_mode>2){ // half way for faster jumps
	  if($prange[1]>2)         $prange[1] = (int)ceil($prange[1]/2);
	  if($np-$prange[$md-2]>3) $prange[$md-2] = (int)floor($np-($np-$prange[$md-2])/2); 
	}
      }
    } else $prange = range(0,$np-1);// show all
    $this->_items['page'] = $prange;
    $this->_nitems = count($prange);
  }

  // which class is asked  ========================================
  function _set_classes(){
    $pl = $this->_items['page'];
    $np = $this->_nitems;
    $cls = array_fill(0,$np,$this->class . '_page');
    if((isset($pl[2])?$pl[2]:0)-$pl[1]>1) {
      $cls[1] .= ' ' . $this->class . '_half';
      $cls[0] .= ' ' . $this->class . '_end';
    } else if($pl[1]-$pl[0]>1) {
      $cls[0] .= ' ' . $this->class . '_end';
    }
    if($pl[$np-2]-(isset($pl[$np-3])?$pl[$np-3]:0)>1){
      $cls[$np-2] .= ' ' . $this->class . '_half';
      $cls[$np-1] .= ' ' . $this->class . '_end';
    } else if($pl[$np-1]-$pl[$np-2]>1) {
      $cls[$np-1] .= ' ' . $this->class . '_end';
    }
    $cls[array_search($this->_cpage,$pl)] .= ' ' . $this->class . '_this';
    $this->_items['class'] = $cls;
  }

  // sets the ids of the elements ===========================================
  function _set_ids(){
    $ids = array();
    if($this->id_unit==0) {
      foreach($this->_items['page'] as $pi) 
	$ids[] = $pi*$this->ipp + $this->min_item;
    } else {
      foreach($this->_items['page'] as $pi) 
	$ids[] = $pi + $this->min_page;
    }
    $this->_items['id'] = $ids;
  }

  // Which label is asked ==================================================
  function _set_elements(){
    $ele = array();
    $np = $this->_npages;
    switch($this->disp_unit){
    case 1:
      foreach($this->_items['page'] as $pi){
	$dp = $pi + $this->min_page;
	if($pi==0 and $np==1) $cele= $this->_pat($this->pat_one,$dp);
	else if($pi==0)       $cele= $this->_pat($this->pat_first,$dp);
	else if($pi+1==$np)   $cele= $this->_pat($this->pat_last,$dp);
	else                  $cele= $this->_pat($this->pat_item,$dp);
	$ele[] = $cele;
      }
      break;
    default:
      foreach($this->_items['page'] as $pi){
	$fi = $pi*$this->ipp+$this->min_item;
	$li = min($this->max_item,$pi*$this->ipp+$this->min_item+$this->ipp-1);
	if($fi==$li){
	  if($fi==0 and $np==1)         $cele= $this->_pat($this->pat_one,$fi);
	  else if($fi==$this->min_item) $cele= $this->_pat($this->pat_first,$fi);
	  else if($fi==$this->max_item) $cele= $this->_pat($this->pat_last,$fi);
	  else                          $cele= $this->_pat($this->pat_item,$fi);
	} else {
	  if($fi==$this->min_item) $fi = $this->_pat($this->pat_first,$fi);
	  if($li==$this->max_item) $li = $this->_pat($this->pat_last,$li);
	  $cele= $this->_pat($this->pat_range,$fi,$li);
	}
	$ele[] = $cele;
      }
    }
    $this->_items['element'] =  $ele;
  }

  // The links based on element, id and class ==============================
  function _make_links(){
    $ni = $this->disp_mode==0?$this->_npages:min($this->disp_max,$this->_npages);
    $txt = array();
    for($ci=0;$ci<$ni;$ci++){
      $txt[] = $this->_link_a($this->_items['element'][$ci],
			      $this->_items['id'][$ci],
			      $this->_items['class'][$ci] . ' ' . $this->class);
    }
    $this->_items['item'] = $txt;
  }

  // add arrows if asked ==================================================
  function _set_arrows(){
    if($this->arrow_mode==0) return;
    $pl = $this->_items['page'];
    $np = $this->_nitems;

    $ci = $this->cid;
    $ds = $this->id_unit==0?$this->ipp:1;
    $dl = $ds*$this->disp_max;
    $mi = $this->id_unit==0?$this->min_item:$this->min_page;
    $ma = $this->id_unit==0?$this->max_item:($this->min_page+$this->_npages-1);
    $cls = $this->class . ' ' . $this->class . '_page ' . $this->class . '_arrow';
    $clse = $this->class . ' ' . $this->class . '_arrow ' . $this->class . '_arrow_enabled';
    $clsd = $this->class . ' ' . $this->class . '_arrow ' . $this->class . '_arrow_disabled';

    $left = array();
    if(!is_null($this->arrow_first))
      $left[] = $this->_link_a($this->arrow_first,$mi,$ci==$mi?$clsd:$clse);
    if(!is_null($this->arrow_left_n))
      $left[] = $this->_link_a($this->arrow_left_n,max($mi,$ci-$dl),$ci-$dl<$mi?$clsd:$clse);
    if(!is_null($this->arrow_left))
      $left[] = $this->_link_a($this->arrow_left,max($mi,$ci-$ds),$ci-$ds<$mi?$clsd:$clse);
    $left = implode($this->arrow_sep,$left);
    $right = array();
    if(!is_null($this->arrow_right))
      $right[] = $this->_link_a($this->arrow_right,min($ma,$ci+$ds),$ci+$ds>$ma?$clsd:$clse);
    if(!is_null($this->arrow_right_n))
      $right[] = $this->_link_a($this->arrow_right_n,min($ma,$ci+$dl),$ci+$dl>$ma?$clsd:$clse);
    if(!is_null($this->arrow_last))
      $right[] = $this->_link_a($this->arrow_last,$ma,$ci==$ma?$clsd:$clse);
    $right = implode($this->arrow_sep,$right);

    switch($this->arrow_mode){
    case 1:
      array_unshift($this->_items['item'],$left);
      array_unshift($this->_items['class'],$cls);
      array_push($this->_items['item'],$right);
      array_push($this->_items['class'],$cls);
      break;
    case 2:
      $left .= $this->arrow_sep . $right;
      array_unshift($this->_items['item'],$left);
      array_unshift($this->_items['class'],$cls);
      break;
    case 3:
      $left .= $this->arrow_sep . $right;
      array_push($this->_items['item'],$left);
      array_push($this->_items['class'],$cls);
      break;
    }
    unset($this->_items['page']);
  }

  function _make_jump(){
    if(empty($this->jump_text)) return;
    $htf = new opc_htform('get',$this->url,$this->xhtml);
    $args = $this->args;
    $args[$this->argname_unit] = $this->disp_unit;
    $htf->fopen(array(),$args);
    $htf->text($this->_argname_id,NULL,array('size'=>4));
    $htf->submit($this->jump_text);
    array_push($this->_items['item'],$htf->output());
    $cls = $this->class . ' ' . $this->class . '_page ' . $this->class . '_arrow';
    array_push($this->_items['class'],$cls);
  }



}


/* ============================================================
 ==============================================================
 =========================== N A V ============================
 ==============================================================
 ============================================================*/

// At the moment this class does not support Ajax

class opc_htselect_nav extends opc_htselect {
}

?>
<?php

  /* 

   select <=> open/close
   multiselect (mit checkbox oder so, mehrere klassen!)
   select etc neu machen, direktverbindung nach aussen (array, dsrc)
   wie genau regieren, was schliessen etc
     cid adjust
       auf 1. falls NULL
       auf parent falls dieser geschlossen wurde
       anchor(#) mitgeben ?
   Anzeigeoption geschachtelt mit Pfas 1.1 1.2 1.2.1 / 1.i 2.iv.B ect oder Pfad
   table -> mit Frontlinie (zu 'topid')??
   */

require_once('opc_tree.php');
require_once('opc_stree.php');

require_once('ops_arguments.php');
require_once('ops_narray.php');

class opc_httree extends opc_ht{

  var $cid = NULL;// id of the current item
  var $class = 'tree'; // used for class attribute, usefull for css
  var $id  = NULL;// used for id attribute, usefull for ajax; NULL -> uses $class

  /* Principal Layout
   0: as nested lists (ol)
   1: as nested lists (ul)
   2: as double-nested div-tags (additional div around all childs)
   3: as nested div-tags (childs at the end of the parent-div)
   4: sequence of div-tag
   5: single div-tag (with br, current in a span)
   the subclass opc_httree_table defines the layouts 101-104
   */
  var $layout = 0;

  /* Detail Layout 
   elements:     0: nothing;      1: text given in tree;     2: invisible (&nbsp;)
                 [other]: callback-function
   anchor        0: nothing;      1: text (symbol_anchor);   2: invisible (&nbsp;)
                 3: picture ($pict_anchor)                   4: bg-picture ($pict_anchor)
		 [other]: callback function

   element_link: 0: no link;   1: allways;     2: leaves only;        3: non-leaves like an anchor
   anchor_link:  0: no link;   1: allways (leaves like an element);   2: non-leaves only
   line:         used only in layout 2-5, 101
                 layout 102-104 draw allways lines (using cell-borders)
                 0: nothing  1: characters ($symbol_lines, layout 101 only)    
                 2: picture  3: bg-pictures (both $pict_lines)
   line_base     if true there will be an intial root line at the left
                 only used in layout 2-5 if line_mode is not 0
   pict_lines is an array with 4 elements (key 0 to 3)
	 	 0: no line
		 1: straight line
		 2: straight line with branch
		 3: final branch of the current level

   */


  var $element = 1;
  var $element_link = 1;
  var $anchor = 1;
  var $anchor_link = 1; 
  var $line = 0;
  var $line_base = TRUE;
  var $pict_lines = array();

  

  /* Details to the three basic events: select (0), open (1) and close (2)
   symbol_anchor: used for textual representations
   pict_anchor: used for graphical representations (empty by default)
   hints: used for the tooltip (title attribute in the links 
   */
  var $symbol_anchor = array(0=>'&nbsp;&bull;&nbsp;',
			     1=>'&nbsp;&minus;&nbsp;',
			     2=>'&nbsp;+&nbsp;');
  var $pict_anchor = array(); 
  var $pict_align = 'center center'; // used for background pictures
  var $hints = array(0=>'show details',
		     1=>'click to close this element',
		     2=>'click to open this element');
  // internal event names. 
  // If you change them update also the php file which answers the ajax-call
  var $_actions = array(0=>'select',1=>'close',2=>'open');


  /* The main data source of this class is an opc_tree object
    label_key -> which field contains the label
    tip_key   -> which field contains the tooltip (title of the a-tag)
  */
  var $tree = NULL;// the main data object
  var $label_key = 'label';
  var $tip_key = 'tip';

  var $space = '&nbsp;&nbsp;&nbsp;'; // used for layout 5 as indent

  /*  AJAX Settings ------------------------------
   you may use ajax for the anchor and element seperately
       -> 0 neither, 1 -> anchor, 2 -> element, 3 -> both
       some layouts will only accept values 0 and 2!
  */
  var $ajax = 0; 
  var $ajax_obj = NULL; // js-name of this object, if NULL id is used
  var $ajax_php = NULL; // php-file which should answer (or NULL)



  /* 
   link_anchor is typically used to close/open the node
   link_element is typiocally used to select the element (show it)
   see parent class for explanatiopn
  */

  var $url = NULL; // will be initaliced by the class itself
  var $args = array(); // additional args used for this site in every a-tag



  /* Selection (which nodes are visible and how they are handled)
   selection: array of nodes (which is defined by selection_mode)
   selection_mode: 
      0: visible nodes(parents should be in the list too)
      2: closed nodes (node is visible, childs not; none of its parent should be in the list too)
      4: open nodes (node an childs are visible; parents do not to be in the list)
  */
  var $selection = array();
  var $selection_mode = 2;


  // internal variables ------------------------------------------------------------------
  // settings after proofing
  var $_id = NULL;
  var $_ajax_obj = NULL;
  var $_ajax = NULL;

  // aux-objects
  var $_cache = array(); //cache for displaying
  var $_max_level = 0; // maximal number of level, used in opc_httree_table



  function opc_httree(/* tree-object -  xhtml(T/F) */){
    $ar = func_get_args();
    $def = array(NULL,FALSE);
    $typ = array('object','boolean');
    list($tree,$xhtml) = ops_arguments::setargs($ar,$def,$typ,1);
    $this->tree = $tree;
    parent::opc_ht($xhtml);
    $this->url = $this->myself();
  }
  

  // callbak for stree object
  function _cb_getvis($item,&$caller,$all){
    if(isset($item[$caller->cld])) unset($item[$caller->cld]);
    $lev = count($caller->cb_path)-1;
    if($this->label_key!='label'){
      if(isset($item[$this->label_key])){
	$item['label'] = $item[$this->label_key];
	unset($item[$this->label_key]);
      } else $item['label'] = $caller->cb_path[$lev];
    }
    $item['id'] = $caller->cb_path[$lev];
    $item['pid'] = $lev==0?NULL:$caller->cb_path[$lev-1];
    $item['_lev'] = $lev;
    $item['tagid'] = $this->_id . '_' . $item['id'];
    $item['_vis'] = $item['visible'];
    $item['sel'] = $this->cid==$item['id'];
    if(isset($item['open'])){
      $item['_kind'] = $item['open']==TRUE?1:2;
      unset($item['open']);
    } else $item['_kind'] = 0;
    unset($item['visible']);
    if($item['_kind']!==1 and $all===FALSE) $caller->cb_stop = 1;
    return($item);
  }
 


  /* will add values about the treestructer to the given array
   _lev: level starting at 0 in every case
   _dlev: level difference to the item before
   _kind: node without childs (0), open (1), closed (2) 
   _struc: for each level above the line specification (used in tables only)
   _rep: how often to repeat the level-n line (used in coltable only)
   _vis: visible (T/F; used for ajax)
   
   class: for the class-attribute
   tagid: id-attribute fot the html tags
   pid: parent id
   anchor: complete with text/link/image
   element: complete text/link
   bimg: background image for anchor (if asked, used in opc_httree_table)

  */
 function visibility(){
   $mlev = 0;
   $arr = $this->tree->doOnAll('extractOverAll',
			       array($this,'_cb_getvis'),
			       ($this->_ajax & 1)==1);
   $ak = array_keys($arr);
   $llev = -1;
   foreach($ak as $ck){
     $clev = $arr[$ck]['_lev'];
     $arr[$ck]['_dlev'] = $clev - $llev;
     if(!isset($arr[$ck]['tip'])) $arr[$ck]['tip'] = NULL;
     $arr[$ck]['_rep'] = 0;
     $mlev = max($clev,$mlev);
     $llev = $clev;
   }

   $this->_max_level = $mlev;

   $rak = array_reverse($ak);
   $ctyp = $llev>0?array_fill(0,$llev,0):array();
   $ctyp[] = 3;
   $arr[array_shift($rak)]['_struc'] = $ctyp; // last node directly
   foreach($rak as $ck){ // go through and find out the relation between them
     $pk = $arr[$ck]['pid'];
     while(!is_null($pk)){
       $arr[$pk]['_rep']++;
       $pk = $arr[$pk]['pid'];
     }
     $clev = $arr[$ck]['_lev'];
     if($clev==$llev){ // sister
       $ctyp[$clev] = 2;
     } else if($clev<$llev){ // parent
       unset($ctyp[$llev--]);
       $ctyp[$llev] = $ctyp[$llev]==0?3:2; // . -> L; | -> t
     } else { // nephew
       for($ii=0;$ii<=$llev;$ii++) if($ctyp[$ii]!=0) $ctyp[$ii] = 1;
       for($ii=$llev+1;$ii<$clev;$ii++) $ctyp[$ii] = 0;
       $ctyp[$clev] = 3;
       $llev = $clev;
     }
     $arr[$ck]['_struc'] = $ctyp;
   }
   
   // forward loop for anchor element and bimg
   foreach($ak as $ck){
     $cline = $arr[$ck];
     if($this->anchor==4) {
       $cline['bimg'] = 'background: url('
	 . $this->pict_anchor[$cline['_kind']] . ')'
	 .'  no-repeat ' . $this->pict_align . ';';
     }
     $cline['element'] = $this->_link($cline,FALSE);
     $cline['anchor']  = $this->_link($cline,TRUE);
     $arr[$ck] = $cline;
   }
   return($arr);
 }


  // creates anchor or element
  function _link($cline,$isanchor=FALSE){
    // tagidden in all cases
    if(($isanchor and $this->anchor===0)
       or (!$isanchor and $this->element===0)) return(NULL);
    // default setting for the tag
    $attr = array('class'=>$this->_class($cline,$isanchor?'a':'e'),
		  'id'=>$this->_id . '_' . $cline['id'] . ($isanchor?'_anchor':'_element'));
    // how to link at all
    $linkas = $this->_link_as($cline,$isanchor);
    // get the visible part
    $attr = $this->_link_txt($cline,$attr,$isanchor,$linkas);
    return($this->_link_create($cline,$attr,$isanchor,$linkas));
  }

  // How to link: 0 -> no link; 1 -> as anchor (open/close); 2 -> as element (select)
  function _link_as($cline,$isanchor){
    if($isanchor){ // ------------------------------------------------------------ anchors
      if($this->anchor_link==0) return(0);// no link asked
      if($cline['_kind']!=0)    return(1);// parent node
      if($this->anchor_link==1) return(2);// element link
      return(0);
    } else {// ------------------------------------------------------------------ elements
      if($this->element_link==0) return(0);// no link asked
      if($cline['_kind']==0)     return(2);// leave node
      if($this->element_link==1) return(2);// parent selectable
      if($this->element_link==2) return(0);// parent non-selectable
      return(1);
    } 
  }

  // adds (at least) the 'text' to the attr argument (key 0)
  function _link_txt($cline,$attr,$isanchor,$linkas){
    if($isanchor){
      switch($this->anchor){
      case 1: $attr[0] = $this->symbol_anchor[$cline['_kind']]; break;
      case 4: $attr['style'] = $cline['bimg']; // no break
      case 2: $attr[0] = '&nbsp;'; break;
      case 3:
	$attr[0] = $this->img2str($this->pict_anchor[$cline['_kind']],
			      $this->symbol_anchor[$cline['_kind']],
			      $this->_class($cline,'img'));
	break;
      default:
	if(is_callable($this->anchor)) $attr[0] = call_user_func($this->anchor,$cline);
      }
      $attr['title'] = $this->hints[$cline['_kind']];
    } else {
      switch($this->element){
      case 1: $attr[0] = $cline['label']; break;
      case 2: $attr[0] = '&nbsp;'; break;
      default:
	if(is_callable($this->element)) $attr[0] = call_user_func($this->element,$cline);
      }
      $attr['title'] = is_null($cline['tip'])?$this->hints[0]:$cline['tip'];
    }
    return($attr);
  }

  function _link_create($cline,$attr,$isanchor,$linkas){
    if($linkas==0) return($this->tag2str('span',NULL,$attr));
    $kind = $linkas==1?$cline['_kind']:0;
    if(($linkas == 1 and ($this->_ajax & 1)) or ($linkas == 2 and ($this->_ajax & 2)))
      $attr['href'] = $this->_link_ajax($cline,$kind);
    else
      $attr['href'] = $this->_link_http($cline,$kind);
    return($this->tag2str('a',NULL,$attr));
  }
  
  // subfunction of _link
  function _link_http($cline,$kind){
    $args = $this->args;
    $args[$this->_id . '_' . $this->_actions[$kind]] = $cline['id'];
    $link = $this->url . '?' . $this->implode_urlargs($args,'&');
    return($link);
  }

  // subfunction of _link
  function _link_ajax($cline,$kind){
    if(is_null($this->ajax_php)) $phpcall = '';
    else $phpcall  = "$this->ajax_php?{$this->_id}_" . $this->_actions[$kind] . "=$cline[id]";
    $link = "javascript:$this->_ajax_obj.click('$cline[id]',$kind,'$phpcall')";
    return($link);
  }

  // =========================== T R E E - F U N C T I O N S ===========================

  // THE MAIN FUNCTION
  function tree    (){$this->add($this->tree2arr());}
  function tree2str(){return($this->_implode2str($this->tree2arr()));}
  function tree2arr(){
    if($this->tree instanceof opc_stree){
      if(count($this->tree->data)==0) return(NULL);
    } else if($this->tree instanceof opc_tree){
      if(count($this->tree->parents)==0) return(NULL);
    } else return(NULL);
    $this->_proof_settings();
    $this->_cache = $this->visibility();
    return($this->_tree($this->layout));
  }

  function _proof_settings(){
    $this->_id = is_null($this->id)?$this->class:$this->id;
    $this->_ajax = $this->ajax & ($this->layout<3?3:2);
    $this->_ajax_obj = is_null($this->ajax_obj)?$this->_id:$this->ajax_obj;
    if(!in_array($this->layout,array(2,3,4,5,101,102))) 
      $this->line = 0;
  }

  // should be called after preparation (mostly calld by tree of this or a sub-class
  function _tree($layout){
    switch($layout){
    case 4: return($this->_tree_divlist());
    case 5: return($this->_tree_spanlist());
    default:
      return($this->_tree_list($this->layout));
    }
  }

  function _class($cline,$typ){
    $res = $this->class . ' ' . $this->class . '_' . $typ;
    switch($typ){
    case 'o': case 'i': // outer and inner embedding tag
      $res .= ' ' . $this->class . '_L' . $cline['_lev'];
      // no break here
    case 'a': case 'e':
      $res .= ' ' . $this->class . '_' . $typ . '_K' . $cline['_kind'];
      break;
    default:
      if(substr($typ,0,2)=='LT')
	$res .= ' ' . $this->class . '_line';
    }
    if($cline['sel'] and $typ!='o') 
      $res .= ' ' . $this->class . '_this ' . $this->class . '_' . $typ . '_this';
    else
      $res .= ' ' . $this->class . '_notthis ' . $this->class . '_' . $typ . '_notthis';
    if(isset($cline['class'])) $res .= ' ' . $cline['class'];
    return($res);
  }

  // for layout 0-3
  function _tree_list($lay){
    $outertag = array('ol','ul','div',NULL);
    $innertag = array('li','li','div','div');
    $same = $outertag[$lay]===$innertag[$lay];
    $noout = is_null($outertag[$lay]);
    $clev = -1;
    $stack = array();
    foreach($this->_cache as $cline){
      if($cline['_dlev']<=0){
	$this->_tmpstack_close($stack,$res);
	while($cline['_dlev']++<0) $this->_tmpstack_close($stack,$res,$noout?1:2); 
      } else if($cline['_dlev']>0 and !$noout){
	$ar = array('tag'=>$outertag[$lay],
		    'id'=>$this->_id . '_childsof_' . $cline['pid'],
		    'class'=>$this->_class($cline,'o'));
	if(isset($ar['style']))
	  $ar['style'] .= $cline['_vis']===FALSE?' display: none;':' display: block;';
	else
	  $ar['style'] = $cline['_vis']===FALSE?' display: none;':' display: block;';
	$this->_tmpstack_open($stack,$res,$ar);
      }
      $ar = array('tag'=>$innertag[$lay],
		  'id'=>$cline['tagid'],
		  'class'=>$this->_class($cline,'i'));
      $this->_tmpstack_open($stack,$res,$ar);
      if($this->layout>1 and $this->line!=0)
	$res[] = $this->_line($cline['_struc'],$this->line==2);
      $res[] = $cline['anchor'];
      $res[] = $cline['element'];
    }
    $this->_tmpstack_close($stack,$res,NULL);
    return($noout?$res:$res[0]);
  }

  // for layout 4
  function _tree_divlist(){
    $res = array();
    foreach($this->_cache as $cline){
      $res[] = array('tag'=>'div','id'=>$cline['tagid'],
		     'class'=>$this->_class($cline,'i'),
		     $this->_line($cline['_struc'],$this->line==2),
		     $cline['anchor'],$cline['element']);
    }
    return($res);
  }

  // for layout 5
  function _tree_spanlist(){
    $res = array('tag'=>'div','class'=>$this->_class(NULL,'o'));
    $ni = count($this->_cache); $ci = 1;
    foreach($this->_cache as $cline){
      switch($this->line){
      case 2:$txt = $this->_line($cline['_struc'],TRUE); break;
      case 3:$txt = $this->_line($cline['_struc'],FALSE); break;
      default:
	$txt = str_repeat($this->space,$cline['_lev']);
      }
      $txt .=  $cline['anchor'] . $cline['element'];
      $ar = array('tag'=>'span','id'=>$cline['tagid'],'class'=>$this->_class($cline,'i'),$txt);
      $res[] = $ar;
      if($ci++ < $ni) $res[] = $this->br2arr();
    }
    return($res);
  }

  function _line($struc,$pict){
    if(!$this->line_base) array_shift($struc);
    $txt = '';
    foreach($struc as $cs){
      $ar = array('class'=>"{$this->class}_LT {$this->class}_LT$cs");
      if($pict){ 
	if(isset($ar['style'])) $ar['style'] .= ' '; else $ar['style'] = '';
	$ar['style'] .= 'background: url(' . $this->pict_lines[$cs] . ')'
	  . ' no-repeat ' . $this->pict_align . ';';
      }
      $txt .= $this->tag2str('span',$this->space,$ar);
    }
    return($txt);
  }

  /* resturns a javascript code snippet to initalize for ajax */
  function ajax_init(){
    $this->_id = is_null($this->id)?$this->class:$this->id;
    $this->_ajax_obj = is_null($this->ajax_obj)?$this->_id:$this->ajax_obj;
    $res = " /* initialice the corresponding tree object of $this->_id in ajax */\n"
      . "$this->_ajax_obj = new httree('$this->_id','$this->cid');\n"
      . "$this->_ajax_obj.class = '$this->class';\n"
      . "$this->_ajax_obj.layout = '$this->layout';\n"
      . "$this->_ajax_obj.anchor = '$this->anchor';\n"
      . "$this->_ajax_obj.element = '$this->element';\n"
      . "$this->_ajax_obj.element_link = '$this->element_link';\n"
      . "$this->_ajax_obj.symbol_anchor = new Array('" . implode("','",$this->symbol_anchor) . "');\n"
      . "$this->_ajax_obj.pict_anchor = new Array('" . implode("','",$this->pict_anchor) . "');\n" 
      . "$this->_ajax_obj.actions = new Array('" . implode("','",$this->_actions) . "');\n" 
      . "$this->_ajax_obj.hints = new Array('" . implode("','",$this->hints) . "');\n";
    return($res);
  }

 }
?>
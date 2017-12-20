<?php

require_once('opc_httree.php');

class opc_httree_table extends opc_httree{
  /* Additional settings

     layout: additional value; ajax for the anchrs works only with typ 
              101: table; using character or pictures for lines
	      102: table; using cell-borders lines
	      103: coltable; using cell-borders lines;
	      104: nested table; using cell-borders lines
	symbol_lines similar to pict_lines
  */
  var $line = 2;
  var $symbol_lines = array(0=>'&nbsp;',1=>'&brvbar;',2=>'&raquo;',3=>'&raquo;');


  // line style for table line use
  var $disp_line = 'solid 2px #888888;';
  // empty cell for table line use
  var $disp_line_width = '10px';

  function _proof_settings(){
    parent::_proof_settings();
    if($this->layout==104) 
      $this->_ajax = $this->ajax & 3; // overdrive from parent 
  }

  // should be called after preparation (mostly calld by tree of this or a sub-class
  function _tree($layout){
    switch($this->layout){
    case 101: return($this->_tree_celltable());
    case 102: return($this->_tree_celltableL());
    case 103: return($this->_tree_coltable());
    case 104: return($this->_tree_nestedtable());
    default:
      return(parent::_tree());
    }
  }

  function _tree_celltable(){
    $res = array('tag'=>'table','class'=>$this->class);
    foreach($this->_cache as $cline){
      $ctr = array('tag'=>'tr','id'=>$cline['tagid']);
      foreach(array_slice($cline['_struc'],$this->line_base?0:1) as $typ){
	//lines
	$cld = array('tag'=>'td','style'=>'text-align:center; vertical-align: middle;',
		     'class'=>$this->_class($cline,'LT' . $typ));
	switch($this->line){
	case 3: 
	  $cld['style'] .= ' background: url(' . $this->pict_lines[$typ] . ')'
	    . ' no-repeat ' . $this->pict_align . ';';
	  // no break;
	case 0: $cld[] = $this->space; break; 
	case 1: $cld[] = $this->symbol_lines[$typ]; break;
	case 2:
	  $cld[0] = array('tag'=>'img','src'=>$this->pict_lines[$typ],
			  'alt'=>$this->symbol_lines[$typ],
			  'class'=>$this->_class($cline,'line'));
	  break;
	}
	$ctr[] = $cld;
      }
      //Anchor & Element
      if($this->anchor_mode>0) 
	$ctr[] = $this->_anchorcell($cline,1,1);
      if($this->element_mode>0)
	$ctr[] = $this->_elementcell($cline,1,($this->_max_level-$cline['_lev'])+1);
      $res[] = $ctr;
    }
    return($res);
  }

  function _tree_celltableL(){
    $res = array('tag'=>'table','class'=>$this->class);
    foreach($this->_cache as $cline){
      $ctr = array('tag'=>'tr','id'=>$cline['tagid']);
      //line part A
      $lcells = array();
      foreach(array_slice($cline['_struc'],$this->line_base?0:1) as $cc){
	$lcell = $this->_linecells($cc);
	$ctr[] = $lcell[0];
	$ctr[] = isset($lcell[1])?$lcell[1]:NULL;
	$lcells[] = isset($lcell[2])?$lcell[2]:NULL;
      }
      $tl = count($lcells)>0;
      //Anchor & Element
      if($this->anchor_mode>0) 
	$ctr[] = $this->_anchorcell($cline,$tl?2:1,2);
      if($this->element_mode>0)
	$ctr[] = $this->_elementcell($cline,$tl?2:1,2*($this->_max_level-$cline['_lev'])+1);
      $res[] = $ctr;
      //Line part B
      if($tl){
	$lcells['tag'] = 'tr';
	$lcells['id'] = $cline['tagid'] . '_b';
	$res[] = $lcells;
      }
    }
    return($res);
  }


  function _tree_coltable(){
    $ak = array_keys($this->_cache);
    $lk = array_shift($ak);
    foreach($ak as $ck){
      if($this->_cache[$ck]['_lev']==0) $lk = $ck;
      else $this->_cache[$lk]['_rep'] ++;
    }
    $res = array('tag'=>'table','class'=>$this->class);
    foreach($this->_cache as $cline){
      $ctr = array('tag'=>'tr','id'=>$cline['tagid']);
      $sl = ($cline['_lev']>0) | $this->line_base; // first row with no line_base is special in col/rowspan
      //Line Part A
      if($sl){
	$lcells = $this->_linecells($cline['_struc'][$cline['_lev']],$cline['_rep']*2);
	$ctr[] = $lcells[0];
	$ctr[] = $lcells[1];
      }
      //Anchor & Element
      if($this->anchor_mode>0) 
	$ctr[] = $this->_anchorcell($cline,$sl?2:1,2);
      if($this->element_mode>0)
	$ctr[] = $this->_elementcell($cline,$sl?2:1,2*($this->_max_level-$cline['_lev'])+1);
      $res[] = $ctr;
      //Line part B

      if($sl){
	$ctr = array('tag'=>'tr','id'=>$cline['tagid'] . '_b');
	$ctr[] = $lcells[2]; 
	$res[] = $ctr;
      }
    }
    return($res);
  }


  function _tree_nestedtable(){
    $stack = array();
    $res = NULL;
    $clev = -1;
    foreach($this->_cache as $cline){
      $sl = ($cline['_lev']>0) | $this->line_base; // first row with no line_base is special in col/rowspan
      if($cline['_lev']>$clev){//open level
	$ar = array('tag'=>'table','id'=>$this->_id . '_childsof_' . $cline['pid'],
		    'class'=>$this->class);
	$ar['style'] = 'display: ' . ($cline['_vis']===FALSE?'none;':'block;');
	$ar[] = array('tag'=>'colgroup',
		      array('tag'=>'col','span'=>$sl?4:2,'width'=>$this->disp_line_width),
		      array('tag'=>'col','width'=>'*'));
	$this->_tmpstack_open($stack,$res,$ar);
	$clev = $cline['_lev'];
      } else {
	while($cline['_lev']<$clev){//close levels
	  $res = array('tag'=>'tr',array('tag'=>'td','colspan'=>4,$res));
	  $this->_tmpstack_close($stack,$res,1);
	  $clev--;
	}
      }
      if($sl) $lcells = $this->_linecells($cline['_struc'][$cline['_lev']],($cline['_kind']==1)?1:0);
      else $lcells = array();
      $res[] = array('tag'=>'tr','id'=>$cline['tagid'],
		     isset($lcells[0])?$lcells[0]:NULL,
		     isset($lcells[1])?$lcells[1]:NULL,
		     $this->_anchorcell($cline,$sl?2:1,2),
		     $this->_elementcell($cline,$sl?2:1,1));
      if($sl) $res[] = array('tag'=>'tr',$lcells[2]);
    }
    while(0<$clev){
      $res = array('tag'=>'tr',array('tag'=>'td','colspan'=>$sl?3:2,$res));
      $this->_tmpstack_close($stack,$res,1);
      $clev--;
    }
    return($res);
  }

  function _anchorcell($cline,$rspan,$cspan){
    $cad = array('tag'=>'td','rowspan'=>$rspan,'colspan'=>$cspan,
		 'class'=>$this->_class($cline,'ta'),
		 'id'=>$cline['tagid'] . '_atd',$cline['anchor']);
    if($this->anchor_mode>4) $cad['style'] = isset($cline['bimg'])?$cline['bimg']:NULL;
    return($cad);
  }

  function _elementcell($cline,$rspan,$cspan){
    $ced = array('tag'=>'td','rowspan'=>$rspan,'colspan'=>$cspan,
		 'class'=>$this->_class($cline,'te'),
		 'id'=>$cline['tagid'] . '_etd',$cline['element']);
    return($ced);
  }

  // creates 1-3 cells for the line-part, add: additional rowspan 
  function _linecells($typ,$add=0){
    $cld = array('tag'=>'td','rowspan'=>2+$add,'colspan'=>1,
		 'style'=>'text-align:center; vertical-align: middle;',
		 'class'=>$this->_class(NULL,'halfline '),
		 $this->space);
    $res = array(0=>$cld);
    switch($typ){
    case 0:
      $res[0]['colspan'] = 2; 
      break;
    case 1:
      $res[1] = $res[0];
      $res[1]['style'] .= ' border-left: ' . $this->disp_line;
      break;
    case 2:
      $res[1] = $res[0];
      $res[1]['rowspan'] = 1;
      $res[1]['style'] .= ' border-left: ' . $this->disp_line;
      $res[2] = $res[1];
      $res[2]['rowspan'] = 1+$add;
      $res[1]['style'] .= ' border-bottom: ' . $this->disp_line;
      break;
    case 3:
      $res[1] = $res[0];
      $res[1]['rowspan'] = 1;
      $res[2] = $res[1];
      $res[2]['rowspan'] = 1+$add;
      $res[1]['style'] .= ' border-left: ' . $this->disp_line
	. ' border-bottom: ' . $this->disp_line;
      break;
    }
    return($res);
  }

}

?>
<?php

require_once('opc_htnav.php');

class opc_htnav_htree extends opc_htnav{
  var $str_details = array();
  var $str_tree = array();
  var $str_par = array();
  
  var $store = NULL;

  function set_structure($struc,$parfield=NULL,$sortfield=NULL){
    $this->str_details = $struc;
    $this->str_tree    = ops_array::tree_flat2nested($struc,1,$parfield,$sortfield);
    $this->str_par     = ops_array::tree_nested2flat($this->str_tree,4);
  }

  function navigation(){
    $cp = $this->store->get('page');
    if(empty($cp)) $cp = array_shift(array_keys($this->str_par));
    $path = $this->str_par[$cp];
    $pos = array_pop($path);
    $node = $this->str_tree;
    $block = array(); $nitem = array();
    while(count($path)>0){
      $block[] = array_keys($node);
      $nitem[] = count($node);
      $node = $node[array_shift($path)];
    }
    $chld = $node[$cp];
    $block[] = array_keys($node);
    $nitem[] = count($node);
    if(count($chld)>0) {
      $block[] = array_keys($chld);
      $nitem[] = count($chld);
    }

    $nitems = $this->kgv($nitem);
    $nblocks = count($block);
    $ii = 0;
    $path = $this->str_par[$cp];
    $path[count($path)-1] = $cp;


    $this->open('table','nav_block');
    $this->open('tr');
    $this->tag('td',$cp,array('rowspan'=>$nitems,'class'=>'title'));
    for($cc=0;$cc<$nblocks;$cc++){
      $this->tag('td','&nbsp;',array('rowspan'=>$nitems,'class'=>'none'));
      $add = array('rowspan'=>$nitems/$nitem[$cc],'class'=>'L' . $cc);
      $txt = $block[$cc][$cr/$nitems*$nitem[$cc]];
      if($txt==$path[$cc]) $add['class'] .= ' this';
      $txt = "<a href='tst_web.php?nav_page=$txt'>$txt</a>";
      $this->tag('td',$txt,$add);
    }
    $this->close();

    for($cr=1;$cr<$nitems;$cr++){
      $this->open('tr');
      for($cc=0;$cc<$nblocks;$cc++){
	if(($cr % ($nitems/$nitem[$cc]))==0) {
	  $add = array('rowspan'=>$nitems/$nitem[$cc],'class'=>'L' . $cc);
	  $txt = $block[$cc][$cr/$nitems*$nitem[$cc]];
	  if($txt==$path[$cc]) $add['class'] .= ' this';
	  $txt = "<a href='tst_web.php?nav_page=$txt'>$txt</a>";
	  $this->tag('td',$txt,$add);
	}
      }
      $this->close();
    }
    $this->close();
  }

  function ggT(/*...*/){
    $arr = func_get_args();
    if(count($arr)==1 and is_array($arr[0])) $arr = $arr[0];
    $aa = array_shift($arr);
    $ggT = $aa;
    while(count($arr)>0){
      $kk = 0;
      $bb = array_shift($arr);
      while(($aa & 1)==0 and ($bb & 1)==0){
	$aa = $aa >> 1;
	$bb = $bb >> 1;
	$kk++;	
      } 
      $tt = ($aa & 1)==0?-$bb:$aa;
      while($tt!=0){
	while(($tt & 1)==0) $tt = $tt >> 1;
	if($tt>0) $aa = $tt; else $bb = -$tt;
	$tt = $aa - $bb;
      }
      $aa *= pow(2,$kk);
    }
    return($aa);
  }

  function kgV(/*...*/){
    $arr = func_get_args();
    if(count($arr)==1 and is_array($arr[0])) $arr = $arr[0];
    $kgv = array_shift($arr);
    while(count($arr)>0){
      $bb = array_shift($arr);
      $kgv *= $bb / $this->ggt($kgv,$bb);;
    }
    return($kgv);
  }
}

?>
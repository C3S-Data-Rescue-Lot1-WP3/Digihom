<?php

class opc_ht2o_dn extends opc_ht2o {

  protected $style_def = 'ul';

  function ht2o_init(){
    $this->data = new opc_tstore();
    $this->data->fct_data_add = 'data_add_all';
  }

  function add_path($path,$tag,$add=array()){
    if(count($path)==1){
      $par = NULL;
      $key = array_shift($path);
    } else {
      $cur = array_pop($path);
      $par = implode($this->sep,$path);
      $key = $par . $this->sep . $cur;
    }
    $this->data->add_last($key,$par,array($tag,$add));
  }

  function style_set__ul(){ 
    $this->style = 'ul';
    $this->style_set = array('tags'=>array('tout','tin'),
			     'tout'=>array('tag'=>'ul'),
			     'tin'=>array('tag'=>'li'));
    return TRUE;
  }

  function steps__ul($set,$add=array()){
    foreach($set['tags'] as $ck) $$ck = def($set,$ck,NULL);
    $seq = $this->data->seq2oac();
    $this->str->open('main',$tout);
    foreach($seq as $pos=>$step){
      $cl = $step['lev'];
      if($cl==0) continue;
      list($tag,$add) = $this->data->data($step['id']);
      switch($step['op']){
      case 'close':
	$this->str->close_n(2);
	break;
      case 'open':  
	$this->str->open('ele-' . $step['id'],$tin);
	$this->str->add('itm-' . $step['id'],$tag);
	$this->str->open('lis-' . $step['id'],$tout);
	break;
      case 'add':   
	$this->str->add('itm-' . $step['id'],$tag);
	$this->str->embed('ele-' . $step['id'],$tin);
	break;
      }
    }
    $this->str->close();
  }
  }
?>

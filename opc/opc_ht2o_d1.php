<?php

class opc_ht2o_d1 extends opc_ht2o {

  protected $style_def = 'list';

  function ht2o_init(){
    $this->data = new opc_tstore();
    $this->data->fct_add_data = 'add_data_all';
  }

  function style_set__list(){
    $this->style = 'list';
    $this->style_set = array('tags'=>array('tout','titm','tkey','tele','tske','tsii'),
			     'tout'=>array('tag'=>'ul'),
			     'titm'=>array('tag'=>'li'),
			     'tkey'=>FALSE,
			     'tele'=>array('tag'=>''),
			     'tske'=>NULL,
			     'tsii'=>NULL,
			     );
    return TRUE;
  }

  function add_path($path,$tag,$add=array()){
    $this->data->add(implode($this->sep,$path),array($tag,$add));
  }

  function steps__list($set){
    foreach($set['tags'] as $ck) $$ck = def($set,$ck,NULL);

    if($tout) $this->str->open('main',$tout);
    $n = 0;
    foreach($this->data->seq2oac() as $step){
      if($step['lev']==0 or $step['op']=='close') continue;
      list($tag,$add) = $this->data->data($step['id']);
      $key = def($add,'key',$n);

      if($n>0 and $tsii) $this->str->add('sep-' . $key,$tsii);

      if($titm) $this->str->open('item-' . $n,$titm);
      if($tkey and isset($add['key'])){
	$tkey[0] = $add['key'];
	$this->str->add('key-' . $key,$tkey);
	if($tske) $this->str->add(NULL,$tske);
      }
      $this->str->add('item-' . $key,array_merge($tele,$tag));
      if($titm) $this->str->close();
      $n++;
    }
    if($tout) $this->str->close();
    return 0;
  }

  }

?>
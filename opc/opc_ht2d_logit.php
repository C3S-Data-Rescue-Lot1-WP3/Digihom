<?php
if(!class_exists('opc_ht2d') and class_exists('opt')) 
  opt::req('opc_ht2d');

class opc_ht2d_logit extends opc_ht2d {

  protected $txp_cls = 'logit';

  // text table
  static $_def_txt = array('success'=>'Success',
			   'hint'=>'Hint',
			   'error'=>'Error');

  function init__object(&$obj){
    if(!($obj instanceof opc_logit)) return 2;
    $this->obj = $obj;
    return 0;
  }

  function usort_time($a,$b){
    return $a['time']<$b['time']?-1:1;
  }

  function levels($msgs){
    $res = array_map(create_function('$x','return def($x,"level","notice");'),$msgs);
    return array_unique($res);
  }

  function output__empty(&$ht,$add=array()){
    return;
  }

  function output__inside(&$ht,$add=array()){
    $msgs = $this->obj->getn(def($add,'filter',array()));
    if(empty($msgs)) return $this->output__empty($ht);
    $ht->open('div','logit_list_inside');
    foreach($msgs as $msg){
      $txt = def($msg,'msg') . ' [' . def($msg,'code','?') . ']';
      $ht->div($txt,'logit_inside logit_level__' . def($msg,'level','notice'));
    }
    $ht->close();
  }

  function output__list(&$ht,$add=array()){
    $msgs = $this->obj->getn(def($add,'filter',array()));
    if(empty($msgs)) return $this->output__empty($ht);

    usort($msgs,array($this,'usort_time'));
    $levels = $this->levels($msgs);

    $ht->open('div','logit_list_standard');
    foreach($levels as $level){
      $ht->open('div','logit_level logit_level__' . $level);
      if(def($add,'title_mode')!=='hide')
	$ht->div($this->txp->text($level,$level,'logit'),'logit_level_title');
      foreach($msgs as $msg){
	if(def($msg,'level','notice')!=$level) continue;
	$txt = def($msg,'msg') . '<span class="log-code">[' . def($msg,'code','?') . ']</span>';
	$ht->div($txt,'logit_level__' . $level);
      }
      $ht->close();
    }
    $ht->close();
  }
}

?>


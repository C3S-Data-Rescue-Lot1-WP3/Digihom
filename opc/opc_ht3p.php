<?php 

class opc_ht3p extends opc_tsptr {

  function text($text){
    return $this->add(NULL,strval($text));
  }

  function line($text){
    if(is_string($text))
      return $this->add(NULL,array(strval($text)));
    if(is_array($text))
      return $this->add(NULL,$text);
    qx();
  }

  function otag(/* tag attr */){
    $attr = new opc_ht3t();
    $attr->init_ta(func_get_args());
    return $this->open(NULL,$attr);
  }

  function ctag(){
    return $this->close();
  }

  function atag($tag,$data=NULL,$attr=NULL){
    $attr = new opc_ht3t();
    $attr->init_tda(func_get_args());
    return $this->add(NULL,$attr);
  }

  function aetag($tag,$attr=NULL){
    $attr = new opc_ht3t();
    $attr->init_ta(func_get_args());
    return $this->add(NULL,$attr);
  }

  function itag($tag,$data=NULL,$attr=NULL){
    $attr = new opc_ht3t();
    $attr->init_tda(func_get_args());
    list($key,$mode) = $attr->loc_extract();
    if(!isset($this->ts[$key])) return FALSE;
    return $this->add_at($key,$mode,NULL,$attr);
  }



  function oobj($obj){
    return $this->open(NULL,$obj);
  }

  function cobj(){
    return $this->close();
  }

  function aobj($obj){
    return $this->add(NULL,$obj);
  }


  function export($root=NULL){
    if(is_null($root))
      $root = $this->ts->root($this->key);
    return $this->ts->export($root);
  }


  }


?>
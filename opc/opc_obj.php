<?php


class opc_dist_obj implements opi_dist{
  static function newobj($type /* ... */){
    $cls = 'opc_obj_' . $type;
    if(!class_exists($cls)) return(1);
    if(!in_array('opi_obj',class_implements($cls))) return(2);
    $res = new $cls();
    $ar = func_get_args();
    array_shift($ar);
    call_user_func_array(array(&$res,'init'),$ar);
    return($res);
  }

}

interface opi_obj{
  function init();
}

abstract class opc_obj implements opi_obj{
  function __construct(){
    $this->init($key=NULL,$value=NULL,$settings=array());
  }
  
}

class opc_mat_num{
  public $data = array();

  protected $colsize = 0;
  protected $rowsize = 0;

  public $err_level = E_USER_ERROR;

  protected function _pos($row,$col=NULL,$range=FALSE){
    if(is_array($row)) {
      $col = (int)$row[1]; 
      $row = (int)$row[0];
    } else {
      $col = (int)$col;
      $row = (int)$row;
    }
    if($col<=0 or $row<=0) trg_err(1,'Out of rangekey below 1',$this->err_level);
    if($range){
      if($col>$this->colsize) $this->colsize = $col;
      if($row>$this->rowsize) $this->rowsize = $row;
    }
    return(array($row,$col));
  }

  function getrow($row) {
    return(isset($this->data[$row])?$this->data[$row]:(new opc_vec_num()));
  }

  function get($row,$col=NULL,$def) {
    list($row,$col) = $this->_pos($row,$col,FALSE);
    if(!isset($this->data[$row])) return($def);
    return($this->data[$row]->get($col));
  }

  function set($row,$col,$val) {
    list($row,$col) = $this->_pos($row,$col,TRUE);
    if(!isset($this->data[$row]))
      $this->data[$row]  = new opc_vec_num();
    $this->data[$row]->set($col,$val);
  }

  function __get($key){
    switch($key){
    case 'size': 
      return($this->rowsize*$this->colsize);
    case 'colsize': case'rowsize':
      return($this->$key);
    }
    trg_err(2,"Access not allowed '$key'",$this->err_level);
  }

  function create(){
    
  }
}

class opc_vec_num{
  public $data = array();
  protected $size = 0;
  public $err_level = E_USER_ERROR;

  function __isset($key){ 
    if(is_numeric($key))
      return(isset($this->data[$key]));  
    else return(NULL);
  }

  function is_set($pos){
    return(isset($this->data[$pos]));  
  }

  function get($pos,$def=NULL){ 
    return(def($this->data,(int)$pos,$def)); 
  }

  function set($pos,$val) {
    if(!is_numeric($pos)) trg_err(1,"non numeric key: $pos",$this->err_level);
    if($pos<=0) trg_err(1,"key below 1",$this->err_level);
    $pos = (int)$pos;
    $this->data[$pos] = $val;
    $this->size = max($this->size,$pos);
  }

  function __get($key){
    switch($key){
    case 'size': return($this->size);
    }
    trg_err(2,"Access not allowed '$key'",$this->err_level);
  }
  
}

class opc_obj_list extends opc_obj implements Iterator{
  protected $data = array();

  function init($key=NULL,$value=NULL,$settings=array()){
    $this->data = array();
  }

  // Iterator
  public function rewind()  { reset($this->data); }
  public function current() { return(current($this->data));}
  public function key()     { return(key($this->data));}
  public function next()    { return(next($this->data));}
  public function valid()   { return($this->current() !== false);}

  function get($key,$def=NULL){
    return(isset($this->data[$key])?$this->data[$key]:$def);
  }

  function set($key,$val){
    $this->data[$key] = $val;
  }

  function __get($key){
    switch($key){
    case 'size': return(count($this->data));
    trg_err(2,"Access not allowed '$key'",E_USER_ERROR);
    }
  }
}

class opc_obj_table extends opc_obj{

  protected $colsize = 0;
  protected $rowsize = 0;

  protected $cells = NULL;
  protected $cellheaders = NULL;
  protected $rowheaders = NULL;
  protected $colheaders = NULL;

  
  function init($key=NULL,$value=NULL,$settings=array()){
    $this->cells = new opc_mat_num();
    $this->cellheaders = new opc_mat_num();
    $this->rowheaders = new opc_vec_num();
    $this->colheaders = new opc_vec_num();
  }

  function get_cell($row,$col)     {return($this->cells->get($row,$col));     }
  function set_cell($row,$col,$val){
    if($row>$this->rowsize) $this->rowsize = $row;
    if($col>$this->colsize) $this->colsize = $col;
    return($this->cells->set($row,$col,$val));
  }

  function get_cellheader($row,$col)     {return($this->cellheaders->get($row,$col));     }
  function set_cellheader($row,$col,$val){
    if($row>$this->rowsize) $this->rowsize = $row;
    if($col>$this->colsize) $this->colsize = $col;
    return($this->cellheaders->set($row,$col,$val));
  }

  function get_colheader($col)     {return($this->colheaders->get($col));     }
  function set_colheader($col,$val){
    if($col>$this->colsize) $this->colsize = $col;
    return($this->colheaders->set($col,$val));
  }

  function get_rowheader($row)     {return($this->rowheaders->get($row));     }
  function set_rowheader($row,$val){
    if($row>$this->rowsize) $this->rowsize = $row;
    return($this->rowheaders->set($row,$val));
  }


  function __get($key){
    switch($key){
    case 'size': 
      return($this->colsize*$this->rowsize);
    case 'colsize': case'rowsize':
      return($this->$key);
    }
    if(isset($this->$key)) return($this->$key);
    trg_err(2,"Access not allowed '$key'",E_USER_ERROR);
  }

}
?>
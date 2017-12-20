<?php

class opc_layout_orth{
  public $sizes = array();
  public $keys = array();
  public $widths = array();
  public $heights = array();


  function define_sizes($siz){
    $keys = array();
    if(!is_array($siz)) return 110;
    $nr = 0;
    $nc = 0;
    foreach($siz as $key=>$val){
      if(!isset($val['c']) or !isset($val['r'])) return 111;
      if(!isset($val['h'])) $siz[$key]['h'] = 1;
      if(!isset($val['w'])) $siz[$key]['w'] = 1;
      $nr = max($nr,$val['r']+$siz[$key]['h']);
      $nc = max($nc,$val['c']+$siz[$key]['w']);
    }
    foreach($siz as $key=>$val){
      $r = $val['r']; $c = $val['c']; $w = def($val,'w',1); $h = def($val,'h',1);
      if($r<0 or $c<0 or $w<1 or $h <1) return 111;
      for($i=0;$i<$h;$i++) 
	for($j=0;$j<$w;$j++) 
	  if(isset($keys[$r+$i][$c+$j])) return 112; else $keys[$r+$i][$c+$j] = $key;
      
    }
    $nk = array_filter(array_keys($siz),create_function('$x','return is_numeric($x);'));
    $nk = empty($nk)?0:max($nk);
    for($i=0;$i<$nr;$i++){
      for($j=0;$j<$nc;$j++){ 
	if(!isset($keys[$i][$j])){
	  $nk++;
	  $siz[$nk] = array('r'=>$i,'c'=>$j,'w'=>1,'h'=>1);
	  $keys[$i][$j] = $nk;
	}
      }
    }
    $this->sizes = $siz;
    $this->keys = $keys;
    return 0;
  }

  function define_keys($keys,$sepC=' ',$sepR='/'){
    if(is_array($keys)) return $this->define_keys_array($keys);
    if(is_string($keys)) return $this->define_keys_string($keys,$sepC,$sepR);
    return 103;
  }

  protected function define_keys_string($keys,$sepC,$sepR){
    $rows = explode($sepR,$keys);
    if(is_numeric($sepC))
      $rows = array_map(create_function('$x',"return str_split(\$x,$sepC);"),$rows);
    else
      $rows = array_map(create_function('$x',"return explode('$sepC',\$x);"),$rows);
    foreach(array_keys($rows) as $ck)
      $rows[$ck] = array_map(create_function('$x','$x = trim($x); return is_numeric($x)?(int)$x:$x;'),$rows[$ck]);
    return $this->define_keys_array($rows);
  }

  protected function define_keys_array($keys){
    $keys = array_values($keys);
    $nr = count($keys);
    if($nr==0) return 104;
    $nc = @count($keys[0]);
    if($nc==0) return 104;
    $ak = array();
    for($i=0;$i<$nr;$i++){
      if(!is_array($keys[$i])) return 105;
      if(count($keys[$i])!=$nc) return 106;
      $keys[$i] = array_values($keys[$i]);
      $ak = array_merge($ak,$keys[$i]);
    }
    $sizes = array_combine($ak,array_fill(0,count($ak),array('r'=>NULL,'c'=>NULL,'h'=>1,'w'=>1)));
    for($i=0;$i<$nr;$i++) {
      for($j=0;$j<$nc;$j++){
	$cs = $sizes[$keys[$i][$j]];
	if(!isset($cs['r'])) {  // new item
	  $cs['r'] = $i; $cs['c'] = $j;
	} else if($cs['r']==$i){ // first row
	  if($cs['c']+$cs['w']!=$j) return 107; // column gap
	  $cs['w']++;
	} else if($cs['r']+$cs['h']==$i){ // first in the next row
	  if($cs['c']!=$j) return 108; // invalid next column
	  $cs['h']++;
	} else if($cs['r']+$cs['h']==$i+1){ // others in the 2+ row
	  if($cs['c']>$j or $cs['c']+$cs['w']<=$j) return 109;
	} else return 110; // row gap
	$sizes[$keys[$i][$j]] = $cs;
      } 
    }    
    $this->sizes = $sizes;
    $this->keys = $keys;
    return NULL;
  }

  function sort($cb=NULL){ 
    if(is_callable($cb)) uksort($this->sizes,$cb);
    else ksort($this->sizes); 
  }

}


class opc_ht2o_layout extends opc_ht2o{

  public $data = array();
  public $layout = NULL;

  function __construct(/* */){
    $this->tmpl_init();
    $this->layout = new opc_layout_orth();
    $ar = func_get_args();
    call_user_func_array(array($this,'init'),$ar);
  }
  /*
   * table: table tag
   * hrow: tag for the first (header) row
   * drow: tag for the other (data) rows
   * tcell: tag for the top-left (table-head) cell
   * r/ccell: tag for the top/left (row/column head) cells
   * dcell: tag for the inside (data) cells
   * ecell: tag for the inside (data) cells if data is NULL or empty string
   */
  public $tag_table = array('tag'=>'table');
  public $tag_row = array('tag'=>'tr');
  public $tag_cell = array('tag'=>'td');
  public $tag_ecell = array('tag'=>'td');

  protected static $setting_names = array('data','areas',
					  'tag_table','tag_rwo','tag_cell');


  function ___get($key,&$res){
    switch($key){
    case 'sizes': case 'keys': case 'widths': case 'heights':
      $res = $this->layout->$key;
      return 0;
    }
    return parent::___get($key,$res);
  }

  function ___set($key,$res){
    switch($key){
    case 'sizes': case 'keys': case 'widths': case 'heights':
      $this->layout->$key = $res;
      return 0;
    }
    return 501;
  }

  /* pipe to layout ================================================== */
  function define_sizes($sizes){ 
    return $this->layout->define_sizes($sizes);
  }

  function define_keys($keys,$sepC=' ',$sepR='/'){ 
    return $this->layout->define_keys($keys,$sepC,$sepR);
  }

  protected function _set($data,$pos){
    switch(def($pos,0,$this->def_set)){
    case 'data': $this->set_data($data); break;
    case 'item': $this->data[$pos[1]] = $data; break;
    }
  }

  function sort($cb=NULL){ 
    return $this->layout->sort($cb);
  }
  /* ====================================================================== */

  function set_data($data,$key=NULL){
    if(is_null($data)) {
      $this->data = array(); 
      return -1;
    } else if(is_null($key)){
      foreach($data as $key=>$val) $this->set_data($val,$key);
      return 0;
    } else if(is_string($key)){
      if(ord($key)==46){
	if(!isset($this->sizes[$this->ptr])) return 2;
	$this->data[$this->ptr] = $data;
	return $this->set_pointer($key);	
      } else if(isset($this->sizes[$key])){
	$this->data[$key] = $data;
	return 0;
      } else return 1;
    } else return 4;
  }

  function set_pointer($key){
    if(ord($key)==46){
      if(!isset($this->sizes[$this->ptr])) return 2;
      $pos = array_search($this->ptr,$this->ptrchain);
      $np = count($this->ptrchain);
      switch($key){
      case '.': ; return 0;
      case '.[': $this->ptr = $this->ptrchain[0]; return 0;
      case '.]': $this->ptr = $this->ptrchain[$np-1]; return 0;
      case '.+': 
	if(++$pos>=$np) return -1; else $this->ptr = $this->ptrchain[$pos];
	return 0;
      case '.-': 
	if(--$pos<0) return -1; else $this->ptr = $this->ptrchain[$pos];
	return 0;
      }
      return 3;
    } else if(isset($this->sizes[$key])){
      $this->ptr = $key;
      return 0;
    } else return 1;
  }

  function _output(&$ht){
    if(!is_array($this->data)) return -1;
    $done = $this->keys;
    $nr = count($this->keys);
    $nc = count($this->keys[0]);
    $this->pointers['main'] = $ht->aopen($this->tag_table);
    if(is_array($this->widths)){
      $this->pointers['colgroup'] = $ht->open('colgroup');
      for($i=0;$i<$nr;$i++) 
	if(isset($this->widths[$i])) 
	  $this->pointers['colgroup--' . $i] = $ht->etag('col',array('width'=>$this->widths[$i]));
      $ht->close();
    }
    for($i=0;$i<$nr;$i++){
      $row = $this->tag_row;
      if(isset($this->heights[$i])) $row['height'] = $this->heights[$i];
      $this->pointers['row--' . $i] = $ht->aopen($row);
      for($j=0;$j<$nc;$j++){
	if(is_null($done[$i][$j])) continue;
	$key = $this->keys[$i][$j];
	if(isset($this->data[$key])){
	  $ccell = $this->tag_cell;
	  $dat = $this->data[$key];
	} else {
	  $ccell = $this->tag_ecell;
	  $dat = def($ccell,0,'');
	}

	$ccell['colspan'] = $this->sizes[$key]['w'];
	$ccell['rowspan'] = $this->sizes[$key]['h'];
	$this->pointers['item--' . $key] = $ht->atag($ccell,$dat);
	for($x=0;$x<$ccell['rowspan'];$x++)
	  for($y=0;$y<$ccell['colspan'];$y++)
	    $done[$i+$x][$j+$y] = NULL;
      }
      $ht->close();
    }
    $ht->close();
    return 0;
  }

 
  function ptr_reset(){
    $this->ptrchain = array_keys($this->sizes);
    $this->ptr = $this->ptrchain[0];
  }

  static function d($ht,$data,$add=array()){
    $tmp = new opc_ht2o_layout($ht);
    $tmp->set_data($data);
    foreach($add as $key=>$val){
      if(!in_array($key,opc_ht2o_layout::$setting_names)) continue;
      $tmp->$key = $val;
    }
    $tmp->output();
  }
}  
?>
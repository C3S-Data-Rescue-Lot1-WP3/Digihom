<?php

  /* sorting by ... 
   * nur ausschnitt (subpages)
   * classen für spalten/Zeilen
   * colgroups ausbauen
   * was bei leeren zellen (automtisch füllen)
   * warnen falls zelle besetz
   * col/rowspan
   */
class opc_ht2o_table extends opc_ht2o implements Countable{
  public $def_set = 'array';

  public $data = NULL;
  public $rows = NULL;
  public $cols = NULL;
  public $colwidths = NULL;
  public $transp = FALSE;
  
  public $adds = array();

  public $_rows = array();
  public $_cols = array();

  public $show_coln = TRUE;
  public $show_rown = TRUE;

  protected $crow = NULL;
  protected $ccol = NULL;


  public $steps = array('*'=>array('subs'=>array('table')),
			'table'=>array('subs'=>array('row-head','row-data')),
			'row-head'=>array('subs'=>array('cell-table','cell-col')),
			'cell-table'=>array('subs'=>array('cell-table-content')),
			'cell-col'=>array('subs'=>array('cell-col-content')),
			'row-data'=>array('subs'=>array('cell-row','cell-data')),
			'cell-row'=>array('subs'=>array('cell-row-content')),
			'cell-data'=>array('subs'=>array('cell-data-content')),
			);

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
  public $tag_hrow = array('tag'=>'tr');
  public $tag_drow = array('tag'=>'tr');
  public $tag_tcell = array('tag'=>'th',0=>'&nbsp;');
  public $tag_rcell = array('tag'=>'th');
  public $tag_ccell = array('tag'=>'th');
  public $tag_dcell = array('tag'=>'td');
  public $tag_ecell = array('tag'=>'td',0=>'&nbsp;');

  protected static $setting_names = array('data','row','cols','show_rown','show_coln',
					  'tag_table','tag_hrwo','tag_drow','tag_tcell',
					  'tag_rcell','tag_ccell','tag_dcell','tag_ecell');


  function count(){ return count($this->rows);}

  function flush(){
    $this->data = NULL;
    $this->rows = NULL;
    $this->cols = NULL;
    return 0;
  }

  function init_one($ca){
    if(is_array($ca)){
      foreach($ca as $key=>$val)
	if(in_array($key,self::$setting_names)) $this->$key = $val;
    } else return parent::init_one($ca);
  }



  /* possible combinations
   * array,   'rowkeys' -> sets the rowkeys incl label (array(key=>label,...)
   * array,   'colkeys' -> sets the colkeys incl label (array(key=>label,...)
   * !array , 'rowkey'  -> recalc the row-keys and labels using the saved data
   * !array,  'colkey'  -> recalc the col-keys and labels using the saved data
   * label,   'rowkey', row -> sets one row-label
   * label,   'colkey,  col -> sets one col-label
   * value,   'cell',   row, col -> set one cell
   * array,   'cells'    -> sets n-cells, loops through array in row/col
   * array,   'cellst'   -> stes n-cells, loops through array in col/row
   * array,   'row', row -> sets a row 
   * array,   'col', col -> sets a col 
   * array,   'data' or 'array' -> repalces data completly and recalcs the keys
   */
  
  protected function _set($data,$pos){
    $what = def($pos,0,$this->def_set);
    switch($what){
    case 'cells-clear':
      $this->data = array();
      break;
    case 'cols-clear':
      $this->cols = array();
      break;
    case 'rows-clear':
      $this->rows = array();
      break;
    case 'clear':
      $this->data = array();
      $this->cols = array();
      $this->rows = array();
      break;

    case 'array': case 'data':
      $this->data = $data;
      $this->rows = ac($this->get_keys($data,1));
      $this->cols = ac($this->get_keys($data,2));
      break;

    case 'colwidths':
      $this->colwidths = $data;
      break;

    case 'rowkeys':
      $this->rows = is_array($data)?$data:$this->get_keys(FALSE,1,TRUE);
    break;
    case 'colkeys':
      $this->cols = is_array($data)?$data:$this->get_keys(FALSE,2,TRUE);
    break;
    case 'rowkey': $this->rows[def($pos,1,$data)] = $data; break;
    case 'colkey': $this->cols[def($pos,1,$data)] = $data; break;
    case 'cell':
      if(!isset($this->data[$pos[1]])) $this->data[$pos[1]] = array($pos[2]=>$data);
      else $this->data[$pos[1]][$pos[2]] = $data;
      break;
    case 'cells':
      foreach($data as $key=>$val)
	if(!isset($this->data[$key])) $this->data[$key] = $val;
	else foreach($val as $ck=>$cv) $this->data[$key][$ck] = $cv;
      break;
    case 'cellst':
      foreach($data as $key=>$val)
	foreach($val as $ck=>$cv) 
	if(!isset($this->data[$ck])) $this->data[$ck] = array($key=>$val);
	else $this->data[$ck][$key] = $cv;
      break;
    case 'row':
      if(!isset($this->data[$pos[1]])) $this->data[$pos[1]] = $data;
      else foreach($data as $ck=>$cv) $this->data[$pos[1]][$ck] = $cv;
      break;
    case 'col':
      foreach($data as $key=>$val)
	if(!isset($this->data[$key])) $this->data[$key] = array($pos[1]=>$val);
	else $this->data[$key][$pos[1]] = $val;
      break;
      

    case 'add':
      array_shift($pos);
      $this->adds[implode('>>',$pos)] = $data;
      break;

    default:
      qk();
      trg_err(1,'Unkonown set style for ht2o_table: ' . $what);
    }
  }
 

  function _get($path){
    $path = $this->path_norm(func_get_args());
    switch($path[0]){
    case 'array': case 'data': return $this->data;
    case 'cols':   return $this->cols;
    case 'rows':   return $this->rows;
    case 'cell':   return def(def($this->data,$path[1],array()),$path[2]);
    }
  }

  function exists($path){
    $path = $this->path_norm(func_get_args());
    switch($path[0]){
    case 'cell':  return isset($this->data[$path[1]])?isset($this->data[$path[1]][$path[2]]):FALSE;
    case 'row':   return isset($this->rows[$path[1]]);
    case 'col':   return isset($this->cols[$path[1]]);
    }
  }


  function data_crow_set($row){
    if(isset($this->rows[$row])) $this->crow = $row;
  }

  function data_ccol_set($col){
    if(isset($this->cols[$col])) $this->ccol = $col;
  }

  function data_incrow_get($col,$def=NULL){
    if(is_null($this->crow)) return $def;
    return def($this->data[$this->crow],$col,$def);
  }


  function remove($path){
    $path = $this->path_norm(func_get_args());
    switch($path[0]){
    case 'cell':  if(isset($this->data[$path[1]])) unset($this->data[$path[1]][$path[2]]); return;
    case 'row':   unset($this->rows[$path[1]]); return;
    case 'col':   unset($this->cols[$path[1]]); return;
    }
  }


  /* 
   * which: 1: rows only, 2 cols only, 3 both
   * data
   *   NULL -> return the current keys
   *   FALSE -> use saved data
   *   array
   */
  protected function get_keys($data=NULL,$which=3,$ac=FALSE){
    if(is_null($data)){
      switch($which){
      case 1: return $this->rows;
      case 2: return $this->cols;
      case 3: return array($this->rows,$this->cols);
      }
      return NULL;
    } else {
      if($data===FALSE) $data = $this->data;
      if(0!= $res = $this->check_data($data)) return $res;
      if($which==1) return $ac?ac(array_keys($data)):array_keys($data);
      $keys = array();
      foreach($data as $cele) $keys = array_merge($keys,array_keys($cele));
      $keys = array_unique($keys);
      if($which==2) return $ac?ac($keys):$keys;
      return $ac?array(ac(array_keys($data)),ac($keys)):array(array_keys($data),$keys);
    }
  }

  function check_data($array,$mode=0){
    if(!is_array($array)) return 1;
    foreach($array as $cele) if(!is_array($cele)) return 2;
    return 0;
  }

  /* returns if a data (cell) exists as 
   *  0: yes (non NULL)
   * -1: yes (NULL)
   *  1: no (row does not exists)
   *  2: no (col does not exist)
   */
  function data_state($row,$col){
    if(!isset($this->data[$row])) return 1;
    if(!isset($this->data[$row][$col])) return 2;
    return is_null($this->data[$row][$col])?-1:0;
  }

  function adds_tag($tag,$key){
    $ak = preg_grep('{^cell-(class|style)>>' . preg_quote($key) . '$}',array_keys($this->adds));
    foreach($ak as $ck){
      $tk = preg_replace('{^cell-(class|style)?>>' . $key . '$}','$1',$ck);
      $tag[$tk] = $this->adds[$ck];
    }
    
    return $tag;
  }

  function _output(&$ht){
    if(!is_array($this->data)) return -1;
    if($this->transp){
      $cn = $this->cols;
      $rn = $this->rows;
      $this->cols = $rn;
      $this->rows = $cn;
    }
    $this->_cols = array_keys($this->cols);
    $this->_rows = array_keys($this->rows);
    $this->step_subs($ht,'*');
    if($this->transp){
      $this->cols = $cn;
      $this->rows = $rn;
    }
  }

  function _output_cell($ht,$rkey,$ckey,$row,$col,$data){
    $this->pointers['cell--' . $rkey . '--' . $ckey] = $ht->atag($this->tag_dcell,$data,'td');
  }

  function _output_cell_empty($ht,$rkey,$ckey,$row,$col){
    $this->pointers['cell--' . $rkey . '--' . $ckey] = $ht->atag($this->tag_ecell,'td');
  }

  function _output_colnames($ht,$cn){
    $ht->aopen($this->tag_hrow,'tr');
    if($this->show_rown) $this->pointers['tcell'] = $ht->atag($this->tag_tcell,'th');
    foreach($cn as $ckey=>$cval) 
      $this->pointers['ccell--' . $ckey] = $ht->atag($this->tag_ccell,$cval,'th');
    $ht->close();
  }

  function _output_rowname($ht,$rkey,$row,$rlabel){
    $this->pointers['rcell--' . $rkey] = $ht->atag($this->tag_rcell,$rlabel,'th');
  }


  function step__table(&$ht){
    return $ht->atag($this->tag_table,NULL,'table');
  }

  function step__row_head(&$ht){
    if(!is_null($this->colwidths)){
      if($this->colwidths==='='){
	$n = count($this->_cols);
	if($this->show_rown) $n++;
	$ht->tag('colgroup',NULL,array('span'=>$n,'width'=>(int)(100/$n) . '%'));
      } else trg_err(1,'nyc: columnwidths');
    }

    if(!$this->show_coln) return 0;
    return $ht->atag($this->tag_hrow,NULL,'tr');
  }

  function step__cell_table(&$ht){
    if(!$this->show_rown) return 0;
    return $ht->atag($this->tag_tcell,NULL,'th');
  }

  function step__cell_table_content(&$ht){
    return 0;
  }

  function step__cell_col(&$ht,$add){
    $res = array();
    foreach($this->_cols as $ck){
      $res[$ht->atag($this->tag_ccell,NULL,'th')] = $ck;
    }
    return $res;
  }

  function step__cell_col_content(&$ht,$add){
    return array($ht->tag('_wrap',$this->cols[$add['id']])=>$add['id']);
  }

  function step__row_data(&$ht,$add){
    $res = array();
    foreach($this->_rows as $ck){
      $res[$ht->atag($this->tag_drow,NULL,'tr')] = $ck;
    }
    return $res;
  }

  function step__cell_row(&$ht,$add){
    if(!$this->show_rown) return 0;
    return array($ht->atag($this->tag_rcell,NULL,'th')=>$add['id']);
  }

  function step__cell_row_content(&$ht,$add){
    return array($ht->tag('_wrap',$this->rows[$add['id']])=>$add['id']);
  }

  function step__cell_data(&$ht,$add){
    $res = array();
    foreach($this->_cols as $ck){
      if($this->data_state($add['id'],$ck)==0){
	$tag = $this->adds_tag($this->tag_dcell,$add['id'] . '>>' . $ck);
      } else $tag = $this->tag_ecell;
      $res[$ht->atag($tag,NULL,'td')] = $add['id'] . '--' . $ck;
    }
    return $res;
  
  }

  function step__cell_data_content(&$ht,$add){
    $id = explode('--',$add['id']);
    if($this->data_state($id[0],$id[1])!=0) return 0;
    return array($ht->tag('_wrap',$this->data[$id[0]][$id[1]])=>$add['id']);
  }


  static function d($ht,$data,$add=array()){
    $tmp = new opc_ht2o_table($ht);
    $tmp->set($data,'data');
    foreach($add as $key=>$val){
      if(!in_array($key,opc_ht2o_table::$setting_names)) continue;
      $tmp->$key = $val;
    }
    $tmp->output();
    return $tmp->pointers;
  }
} 



?>
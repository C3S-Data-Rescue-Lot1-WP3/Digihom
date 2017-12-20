<?php

  /* does not work with numeric row-key !!
   *
   * add featuires
   *   filter -> sorting -> page-selection
   *   some can be done by the user some by ht20
   *    but it has to be done in the order shown above
   *    -> no mixture who does what
   *    filter-form done external
   *    sort-form may be done by this class
   *    page-form may be done by this class (nyc)
   */

class opc_ht2o_tableplus extends opc_ht2o_table{
  /* fsp - handling (filter, sorting, page-selection)
   *
   * settings --------------------------------------------------
   * filter_current: object of type opc_logic (nyc)
   *  
   * sort_current: definies the current sort order
   *  array(col=>sort-style,col=>sor...)
   *  where sort-style is an integer (see array_multisort 'arg')
   *
   * page_current:
   *  integer: = 0 (no pages);  >0 (items per page); <0 (pages)
   *  array(int, int ...): number of items on nth page (not necessary equal)
   *   allows non numeric page-keys
   *   the page_counter starts with 1!
   * 
   * input state --------------------------------------------------
   * user_data_state: what kind of data does the user fill into this class
   *  0: all, not sorted
   *  1: filtered, not sorted and no size limitation
   *  2: filtered and sorted, but no size limitation
   *  3: filtered, sorted and cutted to the right size
   *
   * current values --------------------------------------------------
   * filter_values: values used (instead of the shown items) for filtering
   *     filter_complete fills missing values with the displayed value
   * sort_values: values used (instead of the shown items) for sorting
   *     sort_complete fills missing values with the displayed value
   * page_value: on which page does an item appear
   *     array(row=>page,row=>page,...)
   *     page_complete fills missing using page_setting
   *
   * -> hint filter/sort_values are transposed compared to data
   *     array(col=>array(row=>val,row=>val,..),col=>...)
   *
   */

  public $user_data_state = 0;

  public $filter_current = array();
  public $sort_current = array();
  public $page_current = 1;

  public $sort_values = array();
  public $filter_values = array();
  public $page_value = array();


  /* current sort order
   * key: column-key
   * val: sort-index like SORT_ASC ...
   */

  /* details for function colname_sort
   * label: named array with arrow-key and label for link
   * ord: similar 
   */
  public $tag_arrow_label = array('up'=>'&uArr;','down'=>'&dArr;');
  public $tag_arrow_ord = array('up'=>SORT_ASC,'down'=>SORT_DESC,);
  public $tag_arrow_add = array('class'=>'sort sort%pos%');
  public $tag_arrow_urlargs = array('sort_key'=>'%key%','sort_ord'=>'%ord%');
  public $sort_n = 3;

  /* should be an array similar arrow_link-urlargs */
  function sort_add($key,$method=SORT_ASC){
    if(isset($this->sort_current[$key])) {
      unset($this->sort_current[$key]);
    } else if(count($this->sort_current)>=$this->sort_n){
      $tmp = array_keys($this->sort_current);
      unset($this->sort_current[array_pop($tmp)]);
    }
    $this->sort_current = array_merge(array($key=>$method),$this->sort_current);
  }

  protected function _set($data,$pos){
    $what = def($pos,0,$this->def_set);
    switch($what){
    case 'sort': 
      if(!isset($this->sort_values[$pos[2]]))
	$this->sort_values[$pos[2]] = array($pos[1]=>$data);
      else $this->sort_values[$pos[2]][$pos[1]] = $data;
      break;

    default:
      return parent::_set($data,$pos);
    }
  }

  function sort_complete($def=array()){
    if(!is_array($this->rows)) $this->rows = $this->get_keys(FALSE,1);
    if(!is_array($this->cols)) $this->cols = $this->get_keys(FALSE,2);

    foreach(array_keys($this->cols) as $ckey){
      foreach(array_keys($this->rows) as $rkey){
	if(!isset($this->sort_values[$ckey]))  $this->sort_values[$ckey] = array();
	if(isset($this->sort_values[$ckey][$rkey])) continue;
	if(isset($this->data[$rkey]) and isset($this->data[$rkey][$ckey]) and is_scalar($this->data[$rkey][$ckey]))
	  $this->sort_values[$ckey][$rkey] = $this->data[$rkey][$ckey];
	else
	  $this->sort_values[$ckey][$rkey] = def($def,$ckey);
      }
    }
  }

  function colname_sort(&$ht,$key,$label){
    $ht->in();
    $res = $this->set_ht($ht,'colkey',$key);
    $ht->add($label);
    $pos = array_search($key,array_keys($this->sort_current));
    $pos += is_int($pos)?1:0;
    $arg = str_replace('%key%',$key,$this->tag_arrow_urlargs);
    $add = str_replace('%key%',$key,$this->tag_arrow_add);
    foreach($this->tag_arrow_label as $akey=>$alab){
      $ord = $this->tag_arrow_ord[$akey];
      $carg = str_replace('%ord%',$ord,$arg);
      $cadd = str_replace('%ord%',$ord,$add);
      if($pos>0 and $this->sort_current[$key]==$ord)
	$cadd = str_replace('%pos%',$pos,$cadd);
      if(!isset($carg['href']))
	$ht->a($alab,$carg,$cadd);
      else if(strpos($carg['href'],'://')===FALSE)
	$ht->page($carg['href'],$alab,$carg,$cadd);
      else
	$ht->www($carg['href'],$alab,$carg,$cadd);
    }
    $ht->out();
    return $res;
  }

  function sort($sort=NULL){
    if(is_null($sort)) $sort = $this->sort_current;
    $ms = array();
    foreach($sort as $key=>$val){
      if(!isset($this->sort_values[$key])) continue;
      $ms[] = &$this->sort_values[$key];
      $ms[] = &$val;
    }
    if(count($ms)==0) return ;
    $ms[] = &$this->rows;
    call_user_func_array('array_multisort',$ms);
  }

  function sort_pos($key){ return def($this->sort_pos,$key,NULL);  }
  function sort_ord($key){ return def($this->sort_ord,$key,NULL);  }

  }
?>
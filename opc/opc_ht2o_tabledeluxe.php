<?php

  /* does not work with numeric row-key !!
   *
   * bug: why does step__cell_row_content (and similar) need a _wrap, and add(...) fails?!
   *
   * allow user sort functions ( -> troubles with array_multisort)
   *
   * add features
   *   filter -> sorting -> page-selection
   *   some can be done by the user some by ht20
   *    but it has to be done in the order shown above
   *    -> no mixture who does what
   *    filter-form done external
   *    sort-form may be done by this class
   *    page-form may be done by this class (nyc)
   */

class opc_ht2o_tabledeluxe extends opc_ht2o_table{
  /* fsp - handling (filter, sorting, page-selection)
   *
   * settings --------------------------------------------------
   * filter_current: object of type opc_logic (nyc)
   *  
   * sort_current: definies the current sort order
   *  array(col=>sort-style,col=>sor...)
   *  where sort-style is an integer (see array_multisort 'arg')
   *
   * page_current: current page (1,2...)
   * page_size
   *  integer: = 0 (no pages);  >0 (items per page); <0 (pages)
   *  array(int, int ...): number of items on nth page (not necessary equal)
   *   allows non numeric page-keys
   *   the page_counter starts with 1!
   * 
   * state --------------------------------------------------
   * user_data_state: what kind of data does the user fill into this class
   * class_data_state: how good are the data prepared
   *  0: not filtred, not sorted and no size limitation
   *  1: filtered, not sorted and no size limitation
   *  2: filtered and sorted, but no size limitation
   *  3: filtered, sorted and cutted to the right size
   *
   * current values --------------------------------------------------
   * filter_values: values used (instead of the shown items) for filtering
   *     filter_complete fills missing values with the displayed value
   * sort_values: values used (instead of the shown items) for sorting
   *     sort_complete fills missing values with the displayed value
   *     hint sort_values are transposed compared to data
   *     array(col=>array(row=>val,row=>val,..),col=>...)
   * page_values: on which page does an item appear
   *     array(row=>page,row=>page,...)
   *     page_complete fills missing using page_setting
   *
   */

  public $id = 'opc_ht2o_tabledeluxe';

  public $user_data_state = 0;
  public $class_data_state = 0;

  public $filter_current = array();
  public $sort_current = array();

  public $filter_data = array();

  public $page_current = 1;
  public $page_size = 0;
  public $page_count = 0;
  public $page_content = array();
  public $page_labels = array(); // to overdrive the default values by user
  
  /* which columns should get sort-arrows in the column row
   * a non array value means all, otherwise only those listed
   */
  public $sort_cols = array();

  public $sort_values = array();
  public $filter_values = array();
  public $page_values = array();


  public $_rows = array();
  public $_cols = array();

  public $tag_pages = array('tag'=>'div','class'=>'pages');
  public $tag_page = array('tag'=>'span','class'=>'page');
  public $tag_tpage = array('tag'=>'span','class'=>'page page-this');


  /** steps additional/different from parent class */
  public $_steps = array('*'=>array('subs'=>array('pages','table')),
			'pages'=>array('subs'=>array('page')),
			'page'=>array('subs'=>array('page-content')),
			 'cell-col'=>array('subs'=>array('cell-col-label','cell-col-arrows')),
			 );

  /* Data provider
   * in: to read the current settings from (eg SESSION)
   * out: to write the (new) current setings (eg SESSION)
   * new: to read the new (changing) settings) (eg GET)
   */
  protected $dp_in = NULL;
  protected $dp_out = NULL;
  protected $dp_new = NULL;


  protected static $_setting_names = array('id');
  protected static $dp_vars = array('filter_data','sort_current','page_current');

  /* details for function colname_sort
   * label: named array with arrow-key and label for link
   * ord: similar 
   */
  public $tag_arrow_label = array('up'=>'&uArr;','down'=>'&dArr;');
  public $tag_arrow_ord = array('up'=>SORT_ASC,'down'=>SORT_DESC,);
  public $tag_arrow_add = array('class'=>'sort sort%pos%');
  public $tag_arrow_urlargs = array('%id%_sort_key'=>'%key%','%id%_sort_ord'=>'%ord%');

  public $tag_page_urlargs = array('%id%_page'=>'%page%');
  public $tag_page_add = array();
  public $tag_page_label = array('%f% - %l%','%f%'); // second if f==l

  /* max numbers of sorting colums */
  public $sort_n = 3;


  function tmpl_init(/* */){
    self::$setting_names = array_merge(self::$setting_names,self::$_setting_names);
    $this->steps = array_merge($this->steps,$this->_steps);
    parent::tmpl_init();
  }
    
  /* adds a new column (plus sort method) to sort_current 
   * regards sort_n and keeps a meaningful order of all sort columns
   */
  function sort_add($key,$method=SORT_ASC,$save=TRUE){
    if(isset($this->sort_current[$key])) {
      unset($this->sort_current[$key]);
    } else if(count($this->sort_current)>=$this->sort_n){
      $tmp = array_keys($this->sort_current);
      unset($this->sort_current[array_pop($tmp)]);
    }
    $this->sort_current = array_merge(array($key=>$method),$this->sort_current);
    if($save and is_object($this->dp_out)) $this->dp_out();
  }

  function sort_clear($save=TRUE){
    $this->sort_current = array();
    if($save and is_object($this->dp_out)) $this->dp_out();
  }

  function filter_set($filter,$save=TRUE){
    $this->filter_current = $filter;
    if($filter instanceof opc_test) $this->filter_data = $filter->data;
    else if(is_array($filter))      $this->filter_data = $filter;
    else                            $this->filter_data = NULL;
    if($save and is_object($this->dp_out)) $this->dp_out();
  }

  function filter_clear($save=TRUE){
    $this->filter_current = array();
    if($save and is_object($this->dp_out)) $this->dp_out();
  }

  /* overlaod of _set
   *  new pos-styles
   *   sort: add value to the cell as sorting value (if differs from displayed value)
   */ 
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


  /* ================================================================================
   ======================================= FSP ======================================
   ================================================================================ */
  /*
   * the [name] function makes the real job, returns the rowkey-array
   */
  

  /* ================================================================================
   Filtering
   ================================================================================ */
  function _filter(){
    if(!empty($this->filter_current)){
      $this->filter_complete($this->filter_values);
      $res = $this->filter();
    } else $res = -1;
    $this->class_data_state = 1;
    return $res;
  }

  function filter_complete(&$arr){
    foreach($this->_rows as $rkey){
      if(!isset($arr[$rkey])) $arr[$rkey] = array();
      foreach($this->_cols as $ckey){
	if(isset($arr[$rkey][$ckey])) continue;
	if(isset($this->data[$rkey]) and isset($this->data[$rkey][$ckey]) and is_scalar($this->data[$rkey][$ckey]))
	  $arr[$rkey][$ckey] = $this->data[$rkey][$ckey];
	else
	  $arr[$rkey][$ckey] = NULL;
      }
    }
    return 0;
  }

  function filter(){
    if(is_array($this->filter_current) and count($this->filter_current)>0){
      $this->_rows = array_intersect($this->_rows,$this->filter_current);
      return 0;
    } else if($this->filter_current instanceof opc_test){
      $tmp = $this->data;
      $this->data = $this->filter_values;
      $this->filter_current->obj_add($this,'data');
      $res = array();
      foreach($this->_rows as $cr){
	$this->data_crow_set($cr);
	if($this->filter_current->evaluate()===TRUE) $res[] = $cr;
      }
      $this->_rows = $res;
      $this->data = $tmp;
    }
    return -1;
  }

  /* ================================================================================
   Sorting
   ================================================================================ */
  function _sort(){
    if(!empty($this->sort_current)){
      $this->sort_complete($this->sort_values);
      $res = $this->sort();
    } else $res = -1;
    $this->class_data_state = 2;
    return $res;
  }

  function sort_complete(&$arr){
    foreach($this->_cols as $ckey){
      foreach($this->_rows as $rkey){
	if(!isset($arr[$ckey]))              $arr[$ckey] = array();
	else if(isset($arr[$ckey][$rkey]))   continue;
	if(isset($this->data[$rkey]) and isset($this->data[$rkey][$ckey]) and is_scalar($this->data[$rkey][$ckey]))
	  $arr[$ckey][$rkey] = $this->data[$rkey][$ckey];
	else
	  $arr[$ckey][$rkey] = NULL;
      }
    }
    return 0;
  }

  function sort($sort=NULL){
    if(is_null($sort)) $sort = $this->sort_current;
    $ms = array();
    foreach($sort as $key=>$val){
      if(!isset($this->sort_values[$key])) continue;
      $tmp = array();
      foreach($this->_rows as $ck)
	$tmp[$ck] = def($this->sort_values[$key],$ck);
      $ms[] = $tmp;
      $ms[] = (int)$val;
    }
    if(count($ms)==0) return -1;
    $ms[] = &$this->_rows;
    return call_user_func_array('array_multisort',$ms)?0:1;
  }


  /* ================================================================================
   Paging
   ================================================================================ */
  function _page(){
    if(is_numeric($this->page_size)) $this->page_size = (int)$this->page_size;
    if($this->page_size!==0){
      $this->page_complete();
      $res = $this->page();
    } else $res = -1;
    $this->class_data_state = 3;
    return $res;
  }

  function page_complete(){
    $ps = $this->page_size;
    if($ps<0) $ps = ceil(count($this->_rows)/-$ps);
    $i = 0;
    $this->page_values = array();
    $this->page_content = array();
    if(count($this->_rows)==0) return -1;
    foreach($this->_rows as $cr) $this->page_values[$cr] = 1+floor($i++/$ps);
    $this->page_count = $this->page_values[$cr];
    for($i=0;$i<$this->page_count;$i++){
      $this->page_content[$i+1] = array('f'=>$i*$ps+1,'l'=>($i+1)*$ps);
    }
    $this->page_content[$i]['l'] = count($this->_rows);
    return 0;
  }

  function page(){ 
    $this->page_current = (int)$this->page_current;
    if($this->page_current<1) $this->page_current = 1;
    else if($this->page_current>$this->page_count) $this->page_current = $this->page_count;
    $this->_rows = array_keys($this->page_values,$this->page_current);
    return -1;
  }





  /* ================================================================================
   aux
   ================================================================================ */
  /* repalceing %-structers in txt (string or array (key and value!))
   * repl: array(search=>replacement)
   */
  function strrep($txt,$repl){
    if(is_string($txt)){
      $txt = str_replace('%id%',def($repl,'id',$this->id),$txt);
      foreach($repl as $ck=>$cv) $txt = str_replace('%' . $ck . '%',$cv,$txt);
      return $txt;
    } else if(is_array($txt)){
      $res = array();
      foreach($txt as $ck=>$cv) 
	$res[$this->strrep($ck,$repl)] = $this->strrep($cv,$repl);
      return $res;
    } else return NULL;
  }


  /* returns the sorting position of the asked column */
  function sort_pos($key){ return def($this->sort_pos,$key,NULL);  }
  /* returns the sorting method of the asked column */
  function sort_ord($key){ return def($this->sort_ord,$key,NULL);  }

  /* ================================================================================
   user interaction
   ================================================================================ */

  /* the dp (dataproviders) simplify the handling of fsp-handling
   * dp_in  is used to restore the settings from last call (typically something like SESSION)
   * dp_out is used to save the (new) settings for the next call (typically something like SESSION)
   * dp_new is used to get changes for the current settings (typically something like GET)
   *        dp_new uses the keys in tag_arrow_urlargs (do not change them)
   * $args:
   *   instance of opc_args
   *   -> add has three keys: in/out/new which denotes the pile in $args
   *      the defaults are s/s/g
   */
  function dp_set(&$args,$add=array()){
    if($args instanceof opc_args){
      $this->dp_in  = $args->get_pile(def($add,'in', 's'));
      $this->dp_out = $args->get_pile(def($add,'out','s'));
      $this->dp_new = $args->get_pile(def($add,'new','g'));
    } else {
      trigger_error('Unkown arguments to set the dataprovider for opc_ht2o_tabledeluxe');
      return 1;
    }
    if(is_object($this->dp_in))  $this->dp_in();
    if(is_object($this->dp_new)) $this->dp_new();
    if(is_object($this->dp_out)) $this->dp_out();
  }

  function dp_in(){
    $in = $this->dp_in->get($this->id,array());
    foreach(self::$dp_vars as $ck) if(isset($in[$ck])) $this->$ck = unserialize($in[$ck]);
  }

  function dp_out(){
    $out = array();
    foreach(self::$dp_vars as $ck) $out[$ck] = serialize($this->$ck);
    $this->dp_out->set($this->id,$out);
  }


  function dp_new(){
    $args = $this->dp_new->getp('/^' . $this->id . '_/');
    if(isset($args[$this->id . '_sort_key']))
      $this->sort_add($args[$this->id . '_sort_key'],def($args,$this->id . '_sort_ord'),FALSE);
    if(isset($args[$this->id . '_page']))
      $this->page_current = $args[$this->id . '_page'];
  }
  // END -------------------------------------------------------------------------------- ]

  function _output(&$ht,$step=array()){
    if(!is_array($this->rows)) $rows = $this->get_keys($data,1);
    if(!is_array($this->cols)) $cols = $this->get_keys($data,2);
    $this->_rows = array_keys($this->rows);
    $this->_cols = array_keys($this->cols);

    if($this->class_data_state==0) $this->_filter();
    if($this->class_data_state==1) $this->_sort();
    if($this->class_data_state==2) $this->_page();
    if($this->class_data_state==3) {
      if(count($this->_rows)==0){
	$this->step__empty($ht);
      } else {
	$tmp = $this->rows;
	$this->rows = array();
	foreach($this->_rows as $ck) $this->rows[$ck] = def($tmp,$ck);
	$this->step_subs($ht,'*');
	$this->rows = $tmp;
      }
    }
  }

  function step__empty(&$ht){
    return $ht->div('no data found');
  }

  function step__pages(&$ht){
    if($this->page_size===0) return 0;
    return $ht->atag($this->tag_pages,NULL,'div');
  }

  function step__page(&$ht){
    $res = array();
    foreach($this->page_content as $ck=>$cv){
      $tag = $ck==$this->page_current?$this->tag_tpage:$this->tag_page;
      $res[$ht->atag($tag,NULL,'span')] = $ck;
    }
    return $res;
  }

  function step__page_content(&$ht,$add){
    $page = $add['id'];
    if(isset($this->page_labels[$page])){
      $lab = $this->page_labels[$page];
    } else {
      $tmp = $this->page_content[$page];
      $lab = $this->tag_page_label[$tmp['f']==$tmp['l']?1:0];
      $lab = $this->strrep($lab,$tmp);
    }
    $rep = array('page'=>$add['id']);
    $carg = $this->strrep($this->tag_page_urlargs,$rep);
    $cadd = $this->strrep($this->tag_page_add,$rep);
    return array($ht->autolink($lab,$carg,$cadd)=>$add['id']);
  }

  function step__cell_col_label(&$ht,$add){
    return array($ht->tag('_wrap',$this->cols[$add['id']])=>$add['id']);
  }

  function step__cell_col_arrows(&$ht,$add){
    $key = $add['id'];
    if(!is_array($this->sort_cols) or in_array($key,$this->sort_cols)){
      $res = $ht->open('_wrap');
      $pos = array_search($key,array_keys($this->sort_current));
      $pos += is_int($pos)?1:0;
      foreach($this->tag_arrow_label as $akey=>$alab){
	$ord = $this->tag_arrow_ord[$akey];
	$rep = array('key'=>$key,'ord'=>$ord);
	if($pos>0 and $this->sort_current[$key]==$ord) $rep['pos'] = $pos;
	$carg = $this->strrep($this->tag_arrow_urlargs,$rep);
	$cadd = $this->strrep($this->tag_arrow_add,$rep);
	$ht->autolink($alab,$carg,$cadd);
      }
      $ht->close();
      return array($res=>$add['id']);
    } else return 0;
  }

  }
?>
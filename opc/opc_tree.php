<?php
/*
export: plus topid (nur ein kind)
export_visibility mode open/show string close weak (nur wenn alle eltern mitdabei); 
 */

class opc_tree {
  /* id of the topmost node (if NULL key will be '')
   as key it will only appear in $this->childs
   if not NULL it will appear in parents and details['_par']
  */
  var $topid = NULL;
  

  var $tree = array();    // nested array; node: id=>array(child-nodes)
  var $childs = array();  // array of childs   : id=>array(first child, sec....)
  var $parents = array(); // array of parents  : id=>array(parent, grand parent ....)

  var $details = array(); // named array with further details

  // additional fields that will be created in details (if NULL: not used)
  var $pos_key = '_pos'; // position 
  var $lev_key = '_lev'; // level
  var $par_key = '_par'; // id of parent
  var $lev_start = 0; //start index of position (typically 0 or 1)
  var $pos_start = 0; //start index of level  (typically 0 or 1)

  /*
   set: named array for topid, pos_key and so on, if not an array it will 
              be used as topid
   load!=0 -> will call a load_* method: 1->tree 2->childs 3->parent
  */
  function opc_tree($settings=array(),$load=0,$arr=NULL/* ... see load_* */){
    $ar = func_get_args();
    $this->init();
    if(!is_array($settings)) $settings = array('topid'=>$settings);
    $ak = array_intersect(array_keys($settings),array_keys(get_object_vars($this)));
    foreach($ak as $ck){
      switch($ck){
      case 'topid':     $this->set_topid($settings[$ck]); break;
      default:
	$this->$ck = $set[$ck];
      } 
    }
    switch($load){
    case 1: $this->load_tree($ar[2],$ar[3],count($ar)>4?$ar[4]:TRUE); break;
    case 2: $this->load_childs($ar[2]); break;
    case 3: $this->load_parent($ar[2],$ar[3],isset($ar[4])?$ar[4]:NULL); break;
    }
  }

  // 0 -> all, 1-> tree,childs, parent and details
  function init($mode=0){
    if($mode==0){
      $this->pos_key = '_pos'; 
      $this->lev_key = '_lev'; 
      $this->par_key = '_par'; 
      $this->lev_start = 0; 
      $this->pos_start = 0; 
      $this->topid = NULL;
    }
    $this->tree = array(); 
    $this->childs = array($this->topid=>array()); 
    $this->parents = array();
    $this->details = array();
  }

  function set_topid($nid){
    $oid = $this->topid;
    if(is_null($oid) and is_null($nid)) return(TRUE);
    if($oid === $nid) return(TRUE);
    if(!is_null($nid) and isset($this->childs[$nid])) return(FALSE);
    $ak = array_keys($this->parents);
    $this->childs[$nid] = $this->childs[$oid]; unset($this->childs[$oid]);
    if(!is_null($oid) and !is_null($nid))
      foreach($ak as $ck) $this->parents[$ck][count($this->parents[$ck])-1] = $nid;
    else if(!is_null($nid))
      foreach($ak as $ck) $this->parents[$ck][] = $nid;
    else
      foreach($ak as $ck) array_pop($this->parents[$ck]);
    if(!is_null($this->par_key))
      foreach($this->childs[$nid] as $ck) $this->details[$ck][$this->par_key] = $nid;
    $this->topid = $nid;
    return(TRUE);
  }

  /* Loads a nested array (similar to $this->tree)
   structer of arr (if childkey is NULL)
     nested array; key refers the node-id, value is the array of childs
     eg: array(id=>array(id=>array(),id=>array() ...), id=> .... )
   short version for empty (sub-)nodes
     instead of key and empty array use the id as value
     eg: array(id,id,id=>array(...)) here the first two childs are empty
   structer id childkey is given
     allows to include additional information (like title and so on)
     they will be saved in this->details. In thos case the value
     is an array, where the child-array is one element of it (name
     given in childkey)
     eg: array(id=array('title'=>'The Node','childs'=>array(id=> ... )));
   unique_key: IF true and a key is used twice, function will return(FALSE)
   */
  function load_tree($arr,$childkey=NULL,$unique_key=TRUE){
    if(!is_array($arr)) return(FALSE);

    // init
    $childs = array();
    $parents = array();
    $details = array();
    $tree = array();
    $stock = array(); //stock to jump through the levels
    $lev = $this->lev_start; 
    $pos = array($this->pos_start); 
    $par = is_null($this->topid)?array():array($this->topid);
    $ak = array_keys($arr);
    //do not use array_shift on $arr, it will destroy numerical keys
    while(count($ak)>0){
      // uniform the shortcut: empty node represented by id as value instead of id=>array()
      $ck = array_shift($ak);
      if(!is_array($arr[$ck])){ 
	$ck = $this->_extract($ck,$arr);
	$cv = array();
      } else $cv = $this->_extract($ck,$arr);
      if(!is_null($childkey)){
	if(!isset($cv[$childkey])) $var = array();
	else $var = $this->_extract($childkey,$cv);
	$details[$ck] = $cv;
	$cv = $var;
      }

      // set additional details
      if(!is_null($this->par_key)) $details[$ck][$this->par_key] = isset($par[0])?$par[0]:NULL;
      if(!is_null($this->pos_key)) $details[$ck][$this->pos_key] = $pos[0]++;
      if(!is_null($this->lev_key)) $details[$ck][$this->lev_key] = $lev;

      // set structer
      if($unique_key and array_key_exists($ck,$parents)) return(FALSE);
      $parents[$ck] = $par;
      $childs[isset($par[0])?$par[0]:NULL][] = $ck;
      $tree[$ck] = array();

      // go one level deeper if necessary
      if(count($cv)>0){
	array_unshift($pos,$this->pos_start);
	array_unshift($par,$ck);
	$lev++;
	array_unshift($stock,array($arr,$ak,$ck,$tree));
	$tree = array();
	$arr = $cv;
	$ak = array_keys($arr);
      }

      // go one level up if necessary
      while(count($ak)==0 and count($stock)>0){
	array_shift($pos);
	array_shift($par);
	$lev--;
	list($arr,$ak,$ck,$ptree) = array_shift($stock);
	$ptree[$ck] = $tree;
	$tree = $ptree;
      }
    }

    //complete childs-array by empty childs
    foreach(array_keys($parents) as $ck) if(!isset($childs[$ck])) $childs[$ck] = array();

    // save
    $this->childs = $childs;
    $this->parents = $parents;
    $this->details = $details;
    $this->tree = $tree;
    return(TRUE);
  }

  /* arr is an array similar to $this->childs
   eg: array('id'=>array(child-id,child-id=>array(details),child-id, ...))
   topid: id of the root-node (will appear only childs), has to appear in $arr
   */
  function load_childs($arr){
    if(!is_array($arr) or !array_key_exists($this->topid,$arr)) return(FALSE);
    $childs = array();
    $details = array();
    $tree = array();

    //search all childs and parents, count childs and set detail-parent
    $par = array();// child=>parent
    $nchld = array();//parent=>#childs
    foreach($arr as $key=>$val){
      if(is_array($val) and count($val)>0){
	foreach($val as $ck=>$cv){
	  if(is_array($cv)){ $details[$ck] = $cv; $cv = $ck;} //shortcut or not?
	  if(!isset($nchld[$cv])) $nchld[$cv] = 0;
	  if(!isset($childs[$cv])) $childs[$cv] = array();
	  $childs[$key][] = $cv;
	  $par[$cv] = $key;
	  if(isset($nchld[$key])) $nchld[$key]++; else $nchld[$key] = 1;
	}
      } 
    }

    // construct tree
    $hit = TRUE;//found something in this round?
    $ttree = array();//temporary tree
    while($hit and count($nchld)>1){//last will be key topid
      $hit = FALSE;
      $dk = preg_grep('/./',array_keys($nchld));
      foreach($dk as $ck){
	if($nchld[$ck]>0) continue;
	$pk = isset($par[$ck])?$par[$ck]:NULL;
	if(is_array($childs[$ck])){
	  $ttress[$pk][$ck] = array();
	  foreach($childs[$ck] as $bk) 
	    $ttree[$pk][$ck][$bk] = isset($ttree[$ck][$bk])?$ttree[$ck][$bk]:array(); //reorder
	} else $ttree[$par[$ck]][$ck] = array();
	if(isset($nchld[$pk])) $nchld[$pk]--; else $nchld[$pk] = -1;
	unset($nchld[$ck]);
	$hit = TRUE;
      }
    }
    foreach($childs[$this->topid] as $bk)
      $tree[$bk] = isset($ttree[$this->topid][$bk])?$ttree[$this->topid][$bk]:array(); //reorder

    $this->childs = $childs;
    $this->parents = $this->_childs2parents($childs,$details);
    $this->details = $details;
    $this->tree = $tree;
    return(TRUE);
  }

  /* arr is an array
     + array(id=>parent-id,...) (similar to $this->parent)
     + if parentkey is set also possible
       array(id=>array('parent'=>parent-id,title'=>'My ID'),...)
     if poskey given it will be sorted by this field (numeric) 
       poskey is only used if parentkey is set too!
   */
  function load_parent($arr,$parentkey=NULL,$poskey=NULL){
    $childs = array();
    $details = array();
    $tree = array();

    //extract details, especially pos for sorting
    $ak = array_keys($arr);
    $pos = array($this->topid); $cp = 0;
    foreach($ak as $ck){
      $pos[$ck] = $cp++;
      if(!is_null($parentkey)){
	$cv = $arr[$ck];
	if(is_array($cv)){
	  $par = $this->_extract($parentkey,$cv,$this->topid);
	  if(!is_null($poskey) and isset($cv[$poskey]))
	    $pos[$ck] = $this->_extract($poskey,$cv);
	  $details[$ck] = $cv;
	  $arr[$ck] = $par;
	}
      }
    }
    asort($pos); $pos = array_keys($pos);

    // construct tree
    $ttree = array();
    $ak = array_keys($arr);
    while(count($ak)>0){
      $ck = array_shift($ak);
      if(!in_array($ck,$arr)){
	if(isset($ttree[$ck])){
	  $ttree[$arr[$ck]][$ck] = array();
	  $ctk = array_intersect($pos,array_keys($ttree[$ck])); 
	  foreach($ctk as $cck) $ttree[$arr[$ck]][$ck][$cck] = $ttree[$ck][$cck]; //reorder
	  $childs[$ck] = array_keys($ttree[$arr[$ck]][$ck]);
	  unset($ttree[$ck]);
	} else {
	  $ttree[$arr[$ck]][$ck] = array();
	  $childs[$ck] = array();
	}
	unset($arr[$ck]);
      } else array_push($ak,$ck);
    }

    // sort top level
    $ctk = array_intersect($pos,array_keys($ttree[$this->topid])); 
    foreach($ctk as $cck) $tree[$cck] = $ttree[$this->topid][$cck];
    unset($ttree[$this->topid]);
    // bring down the rest (id not used in arr, will be added at top level)
    foreach($ttree as $key=>$val){ $childs[$key] = array_keys($val); $tree[$key] = $val;}
    $childs[$this->topid] = array_keys($tree);

    $this->parents = $this->_childs2parents($childs,$details);
    $this->childs = $childs;
    $this->details = $details;
    $this->tree =  $tree;
    return(TRUE);
  }

  function load_level($arr,$levelkey=NULL){
    $childs = array();
    $details = array();
    $tree = array();
    
    //extract details, especially pos for sorting
    $ak = array_keys($arr);
    if(!is_null($levelkey)){
      foreach($ak as $ck){
	$cv = $arr[$ck];
	if(is_array($cv)){
	  $lev = $this->_extract($levelkey,$cv,$this->topid);
	  $details[$ck] = $cv;
	  $arr[$ck] = $lev;
	}
      }
    }
    $ak = array_keys($arr);
    $par = array($arr[$ak[0]]=>$this->topid);
    foreach($ak as $ck){$par[$arr[$ck]+1] = $ck; $arr[$ck] = $par[$arr[$ck]];}
    $this->load_parent($arr);
    $this->details = $details;
    return(TRUE);
  }

   /* transforms a child to a parent array
   details (NULL or ref to array): if set lev/pos/par will be set
  */
  function _childs2parents($childs,&$details){
    $parents = array();
    $ak = $childs[$this->topid];
    $nk = count($ak);
    $par = array_fill(0,$nk,is_null($this->topid)?array():array($this->topid));
    $lev = array_fill(0,$nk,$this->lev_start);
    $pos = range($this->pos_start,$this->pos_start+$nk-1);
    while(count($ak)>0){
      $ck = array_shift($ak);
      $cp = array_shift($par);
      $cl = array_shift($lev);
      $parents[$ck] = $cp;
      //set details if asked
      if(!is_null($details)){
	if(!is_null($this->lev_key)) $details[$ck][$this->lev_key] = $cl;
	if(!is_null($this->pos_key)) $details[$ck][$this->pos_key] = array_shift($pos);
	if(!is_null($this->par_key)) $details[$ck][$this->par_key] = isset($cp[0])?$cp[0]:NULL;
      }
      //add childs of this node to the job list
      if(isset($childs[$ck]) and is_array($childs[$ck]) and count($childs[$ck])>0){
	$ak = array_merge($ak,$childs[$ck]);
	$nk = count($childs[$ck]);
	array_unshift($cp,$ck);
	$par = array_merge($par,array_fill(0,$nk,$cp));
	$lev = array_merge($lev,array_fill(0,$nk,$cl+1));
	$pos = array_merge($pos,range($this->pos_start,$this->pos_start+$nk-1));
      }
    }
    return($parents);
  }

  //extracts a single item from a array
  function _extract($key,&$arr,$def=NULL){
    if(!isset($arr[$key])) return($def);
    $res = $arr[$key];
    unset($arr[$key]);
    return($res);
  }


  //returns all keys of a given level
  function nodesByLevel($lev=0){
    $res = array();
    if(!is_null($this->lev_key)){
      foreach($this->details as $key=>$val)
	if($val[$this->lev_key]==$lev)
	  $res[] = $key;
    } else {
      $res = $this->childs[$this->topid];
      while($lev-- > $this->lev_start){
	$cres = array();
	foreach($res as $ck) $cres = array_merge($cres,$this->childs[$ck]);
	$res = $cres;
      }
    }
    return($res);
  }

  /* returns an array with all child keys of agiven node
   include me: $id will be in the result too
   bylevel -> TRUE: orderd by level & level-order
           -> FALSE: ordered like in a completly open tree
   */
  function allchilds($id=NULL,$includeme=FALSE,$bylevel=FALSE){
    if(is_null($id)) $id = $this->topid;
    $par = $this->childs[$id];
    $res = $includeme?array($id):array();
    while(count($par)>0){
      $ck = array_shift($par);
      $res[] = $ck;
      if(count($this->childs[$ck])>0){
	if($bylevel) $par = array_merge($par,$this->childs[$ck]);
	else $par = array_merge($this->childs[$ck],$par);
      }
    }
    return($res);
  }

  function exists($id){
    return(array_key_exists($id,$this->childs));
  }

  function child($id){
    if(is_null($id)) $id = $this->topid;
    return($this->childs[$id][0]);
  }

  function childs($id=NULL){
    if(is_null($id)) $id = $this->topid;
    return($this->childs[$id]);
  }

  function parents($id=NULL,$withtopid=FALSE){
    if(is_null($id)) $id = $this->topid;
    $res = $this->parents[$id];
    if($withtopid) array_pop($res);
    return($res);
  }

  function parent($id){
    if($id==$this->topid) return(FALSE);
    return($this->parents[$id][0]);
  }

  function level($id){
    if($id==$this->topid) return($this->lev_start-1);
    return($this->lev_start + $this->_level($id));
  }

  //internal version, ignores lev_start, topid = -1, top level = 0
  function _level($id){
    if($id==$this->topid) return(-1);
    if(!is_null($this->lev_key)) return($this->details[$id][$this->lev_key]);
    $par = $this->parents[$id];
    return(count($par) - (is_null($this->topid)?0:1));
  }

  function pos($id){
    if($id==$this->topid) return($this->pos_start);
    if(!is_null($this->pos_key)) return($this->details[$id][$this->pos_key]);
    $chld = $this->childs[$this->parents[$id][0]];
    return(array_search($id,$chld)+$this->pos_start);
  }

  function count($id=NULL){
    return($this->count_childs($id));
  }

  function count_childs($id=NULL){
    if(is_null($id)) $id = $this->topid;
    return(count($this->childs[$id]));
  }

  function count_siblings($id){
    if(count($this->parents[$id])==0){
      return(count($this->childs[NULL]));
    } else {
      return(count($this->childs[$this->parents[$id][0]]));
    }
  }

  function count_levels(){
    $nk = 0;
    foreach($this->parents as $val) $nk = max($nk,count($val));
    if(is_null($this->topid)) $nk++; // count its own level but not the topid level (if nut null);
    return($nk);
  }

  // reads one or more detail values from one or more elements (ink default)
  function get($field,$key=NULL,$def=NULL){
    if(is_null($key)) $key = $this->allchilds($this->topid);
    $keys = is_array($key)?$key:array($key);
    $flds = is_array($field)?$field:array($field);
    $res = array();
    foreach($keys as $ck){
      $cf = array();
      foreach($flds as $cf){
	if(isset($this->details[$ck][$cf])) $cl[$cf] = $this->details[$ck][$cf];
	else if(isset($def[$cf])) $cl[$cf] = $def[$cf];
	else $cl[$cf] = $def;
      }
      $res[$ck] = is_array($field)?$cl:array_shift($cl);
    }
    return(is_array($key)?$res:array_shift($res));
  }
  /* sets values in details
   mode: 1: value is a named array, key corresponds to the tree-keys
            arg key is ignored!
         2: value is a named array, key correspond to field-names (->merge)
            field is ignored
	 3: value is a nested array: array(key=>array(field=>valueA1,...))  
	    key and field are ignored
         (default): value is used for all items
   */
  function set($value,$field=NULL,$key=NULL,$mode=0){
    if($mode==1 or $mode==3) $key = array_keys($value);
    if(is_null($key)) $key = $this->allchilds($this->topid);
    else if(!is_array($key)) $key = array($key);
    foreach($key as $ck){
      if(!array_key_exists($ck,$this->childs)) continue;
      switch($mode){
      case 1: 
	$this->details[$ck][$field] = $value[$ck]; 
	break;
      case 2: 
	$this->details[$ck] = array_merge($this->details[$ck],$value);
	break;
      case 3:
	$this->details[$ck] = array_merge($this->details[$ck],$value[$ck]);
	break;
      default:
	$this->details[$ck][$field] = $value;
      }
    }
  }

  /* returns id which is located relativ to id
   how: string, only first letter counts
      f / n: forrward/next (same level)
      b / p: backward/previous (same level)
      u: upward
      d: downward (to the first child, step inored)
      h: home (first of this node)
      e: end (last of this node)
      s: step, next node independet from level
   steps works by f,n,b,p,u and s
  */
  function iter($id,$how='f',$step=1){
    if(!$this->exists($id)) return(FALSE);    
    if($step==0) return($id);
    $how = strtolower(substr($how,0,1));
    if($step<0){ 
      $step = -$step; 
      $nhow = array('f'=>'b','n'=>'b','b'=>'f','p'=>'f','d'=>'u','u'=>'d');
      if(isset($nhow[$how])) $how = $nhow[$how];
    }
    switch($how){
    case 'u': $res = $this->parents[$id][$step-1];             break;
    case 'd': $res = $this->childs[$id][0];                    break;
    case 'h': $res = $this->childs[$this->parents[$id][0]][0]; break;
    case 'e': 
      $res = $this->childs[$this->parents[$id][0]];
      $res = $res[count($res)-1];
      break;
    case 'b': case 'p': $step = -$step; // no break, goto n/f
    case 'n': case 'f':
      $cld = $this->childs[$this->parents[$id][0]];
      $pos = array_search($id,$cld)+$step;
      $res =($pos<0 or $pos>=count($cld))?NULL:$cld[$pos];
      break;
    case 's':
      $res = $id;
      while($step-->0){
	if(count($this->childs[$id])>0) {
	  $res = $this->childs[$id][0];
	} else {
	  $cld = $this->childs[$this->parents[$id][0]];
	  $pos = array_search($id,$cld)+1;
	  while($pos==count($cld)){
	    $id = $this->parents[$id][0];
	    if(is_null($id) or $id===$this->topid) return(NULL);
	    $cld = $this->childs[$this->parents[$id][0]];
	    $pos = array_search($id,$cld)+1;
	  }
	  $res = $cld[$pos];
	}
      }
    }
    return($res);
  }

  /* removes one or more nodes */
  function remove($id,$adjpos=TRUE){
    if(is_array($id)){
      foreach($id as $ck) $this->remove($ck);
      return(NULL);
    } else if(is_null($id) or $id==$this->topid){
      $this->init(1);
      return(TRUE);
    } else if(!isset($this->childs[$id])) return(FALSE);

    $par = $this->parents[$id];
    //adjust position in details
    if($adjpos and !is_null($this->pos_key)){
      $cp = $this->details[$id][$this->pos_key];
      if(isset($par[0]) and is_array($this->childs[$par[0]]))
	foreach($this->childs[$par[0]] as $ck)
	  if($this->details[$ck][$this->pos_key]>$cp)
	    $this->details[$ck][$this->pos_key]--;
    }
    // remove in tree
    $tree = $this->tree;
    $stock = $this->_go_down($id,$tree,FALSE);
    if($stock===FALSE) return(FALSE);
    unset($tree[$id]);
    $this->tree = $this->_go_up($stock,$tree);
    // remove in childs,parents and details; incl childs of id
    $chlds = $this->allchilds($id,TRUE);
    foreach($chlds as $ck){
      unset($this->parents[$ck]);
      unset($this->details[$ck]);
      unset($this->childs[$ck]);
    }
    //remove  as childs-item
    $chlds = isset($par[0])?$this->childs[$par[0]]:NULL;
    $key = is_array($chlds)?array_search($id,$chlds):FALSE;
    if($key!==FALSE) unset($this->childs[$par[0]][$key]);
    return(TRUE);
  }

  /*
   returns an new opc_tree object
   adjust: TRUE -> details-level, FALSE -> lev_start
  */
  function export($id=NULL,$adjust=TRUE ){
    if(is_null($id) or $id=== $this->topid) return($this);
    if(!$this->exists($id)) return(FALSE);
    $res = $this;
    $par = $this->parents[$id];
    $chld = $this->allchilds($id);
    $nlev = $this->_level($id);
    $res->_go_down($id,$res->tree,TRUE);
    foreach(array_diff(array_keys($this->details),$chld) as $ck)  unset($res->details[$ck]);
    foreach(array_diff(array_keys($this->childs),$chld) as $ck)  unset($res->childs[$ck]);
    foreach(array_diff(array_keys($this->parents),$chld) as $ck)  unset($res->parents[$ck]);
    $res->topid = $id;
    foreach($chld as $ck) array_splice($res->parents[$ck],-$nlev);
    if($adjust){
      if(!is_null($this->lev_key)){
	$ak = array_keys($res->details);
	foreach($ak as $ck) $res->details[$ck][$this->lev_key] -= $nlev;
      }
    } else $res->lev_start += $nlev;
    $res->childs[$res->topid] = array_keys($res->tree);
    return($res);
  }

  /*
   creates a object based on a level range of this object
   toplevel: topmost level which should appear in result (NULL -> $this->lev_start)
   levels: number of levels (1, 2 ...) or NULL for all
   adjust: TRUE -> details->level will be adjusted; FALSE -> $this->lev_start will be changed
   the topid of the new object is NULL (or ''), the rest is similar to the orginal object
   */
  function export_level($toplevel=NULL,$levels=NULL,$adjust=TRUE){
    if(is_null($toplevel)) $toplevel = $this->lev_start;
    if(!is_null($levels) and $levels<1) return(FALSE);
    $tlev = $toplevel - $this->lev_start;
    if($tlev<0) return(FALSE);

    $res = clone $this;
    // strip high-level items from tree
    $oldt = $res->tree; 
    $keys = array(); // keys which will disappear
    $newt = NULL;
    for($ii=0;$ii<$tlev;$ii++){
      $newt = array(); //array_merge does not work on numeric keys)
      foreach($oldt as $key=>$val) {
	$keys[] = $key;
	foreach($val as $ak=>$av) $newt[$ak] = $av;
      }
      $oldt = $newt;
    } 
    unset($res->childs[$res->topid]);
    if(is_array($newt)) $res->childs[NULL] = array_keys($newt);
    // remove them from the other arrays too
    foreach($keys as $ck){ 
      unset($res->details[$ck]); 
      unset($res->childs[$ck]);
      unset($res->parents[$ck]);
    }
    // remove path-parts from parents
    $nk = $tlev + (is_null($res->topid)?0:1);
    if($nk>0){
      $ak = array_keys($res->parents);
      foreach($ak as $ck) array_splice($res->parents[$ck],-$nk);
    }
    //change details-level or lev_start
    if($adjust){
      if(!is_null($this->lev_key)){
	$ak = array_keys($res->parents);
	foreach($ak as $ck) $res->details[$ck][$this->lev_key] -= $tlev;
      }
    } else $res->lev_start += $tlev;
    // set basic changes
    $res->tree = $newt;
    $res->topid = NULL;
    // set detail-parent new if on new top-level
    if(!is_null($res->par_key))
      foreach($res->parents as $key=>$val)
	if(count($val)==0) $res->details[$key][$res->par_key] = NULL;
    //remove nodes on deeper levels
    if(!is_null($levels)){
      $ak = array_keys($res->parents);
      foreach($ak as $ck) if(count($res->parents[$ck])==$levels) $res->remove($ck,FALSE);
    }
    return($res);
  }

  /*
   mode 0: nodes in ids are visible (including parents and their siblings)
	2: nodes in ids are open
        4: nodes in ids are closed (strict)
   ids: array of nodes (see mode)
   tree: if true a new opc_tree will be returned, 
        otherwise only an array of keys (orderd as in a fully open tree)
   */
  function export_visibility($mode=0,$ids=array(),$tree=TRUE){
    if(!is_array($ids)) $ids = array();
    switch($mode){
    case 2: //no break!
      $ak = $ids;
      foreach($ak as $ck) 
	if(isset($this->childs[$ck]) and is_array($this->childs[$ck]))
	  $ids = array_merge($ids,$this->childs[$ck]);
    case 0:
      $rem = array(); 
      foreach($this->parents as $key=>$val)
	if(in_array($key,$ids)) $ids = array_merge($ids,$val);
	else $rem[] = $key;
      $rem = array_diff($rem,$ids);
      break;
    case 4:
      $rem = array();
      foreach($ids as $id) 
	if(!in_array($id,$rem)) 
	  $rem = array_merge($rem,$this->allchilds($id));
      break;
    default:
      $rem = array();
    }
    if(!$tree) return(array_diff($this->allchilds($this->topid,FALSE,FALSE),$rem));
    $res = $this;
    $res->remove(array_unique($rem));
    return($res);
  }


  /* imports a single node or a complete substructer
   behaviour definied by first argument
   second argument is an existing node in this
   key (string/numeric) -> insert a single node with no childs
   object (class opc_tree or deeper) -> inserts a complete structer
   up to three additional arguments
      array -> used for details of the new node
      T/F: insert as last (true) or first(false) child
      number: insert at position

        
   key, at & optional: details (named array),[, details]
     -> will insert a node (key) as last child of [at]
        key: string or numeric
	at: existing key or null
	details: named array
  */

  function import($what,$at=NULL/* ... */){
    $ar = func_get_args();
    $na = count($ar);
    if($na==0) return(FALSE);
    if(is_null($at)) $at = $this->topid;
    if(!isset($this->childs[$at])) return(FALSE);
    //check if the new id is not yet used
    $id = is_object($what)?$what->topid:$what;
    if(isset($this->childs[$id])) return(FALSE);
    if(is_object($what)){
      foreach(array_keys($what->childs) as $ck)
	if(isset($this->$childs[$ck])) return(FALSE);
      $newtree = $what->tree;
    } else {
      $newtree = array();
    }
    if(isset($ar[2]) and is_array($ar[2])) $this->details[$id] = $ar[2];
    if(!is_null($this->lev_key))
      $this->details[$id][$this->lev_key] = isset($this->details[$at])?($this->details[$at][$this->lev_key]+1):1;
    if(!is_null($this->par_key))
      $this->details[$id][$this->par_key] = $at;
    if(!is_null($this->pos_key)){
      $chld = $this->childs[$at]; 
      if(count($chld)==0) $this->details[$id][$this->pos_key] = $this->pos_start;
      else $this->details[$id][$this->pos_key] = $this->details[array_pop($chld)][$this->pos_key]+1;
    }
    $this->childs[$id] = array();
    $this->childs[$at][] = $id;
    if(is_null($at))            $this->parents[$id] = array();
    else if($at===$this->topid) $this->parents[$id] = array($at);
    else $this->parents[$id] = array_merge(array($at),$this->parents[$at]);
    $tree = $this->tree;
    $stock = $this->_go_down($at,$tree,TRUE);
    $tree[$id] = $newtree;
    $this->tree = $this->_go_up($stock,$tree,TRUE);
    return(TRUE);
  }

  /*
   usefull to go down into tree, returns the stock which is used for go_up
   $id may be an node id or directly the a path similar to parents-items
   if chlds=TRUE it will go to the child-level, otherwise to its own
     ignored if id is a path
   tree: sideeffects, will remain on the asked level
   return: stock for _go_up or FALSE
  */
  function _go_down($id,&$tree,$chlds=FALSE){
    if(!is_array($id)){
      if(!isset($this->childs[$id])) return(FALSE);
      $par = isset($this->parents[$id])?$this->parents[$id]:array();
      if($chlds==TRUE) array_unshift($par,$id);
    } else $par = $id;
    $stock = array();
    if(isset($par[count($par)-1]) and $par[count($par)-1]==$this->topid) 
      array_pop($par); //removes topiod in path
    while(count($par)>0){
      $ck = array_pop($par);
      array_unshift($stock,array($ck,$tree));
      $tree = $tree[$ck];
    }
    return($stock);
  }
  //counterpart of _go_down, returns the reconstructed tree
  function _go_up($stock,$tree){
    if(!is_array($stock)) return($tree);
    while(count($stock)>0){
      list($ck,$ntree) = array_shift($stock);
      $ntree[$ck] = $tree;
      $tree = $ntree;
    }
    return($tree);
  }

  function rename($id,$newid){
    if(!isset($this->childs[$id])) return(FALSE);
    if(isset($this->childs[$newid])) return(FALSE);
    //own values
    $this->childs[$newid] = $this->_extract($id,$this->childs);
    if(isset($this->parents[$id]))
      $this->parents[$newid] = $this->_extract($id,$this->parents);
    if(isset($this->details[$id]))
       $this->details[$newid] = $this->_extract($id,$this->details);
    //tree
    $tree = $this->tree;
    $stock = $this->_go_down($newid,$tree,FALSE);
    $chlds = array(); 
    foreach($tree as $key=>$val) $chlds[$key==$id?$newid:$key] = $val;
    $this->tree = $this->_go_up($stock,$chlds);
    //subitem in childs
    $ak = array_keys($this->childs);
    foreach($ak as $ck)
      if(FALSE !== $key = array_search($id,$this->childs[$ck]))
	$this->childs[$ck][$key] = $newid;
    //subitem in parents
    $ak = array_keys($this->parents);
    foreach($ak as $ck)
      if(FALSE !== $key = array_search($id,$this->parents[$ck]))
	$this->parents[$ck][$key] = $newid;
    if(is_null($this->par_key)) return(TRUE);
    //subitem in details
    $ak = array_keys($this->details);
    foreach($ak as $ck)
      if($this->details[$ck][$this->par_key]==$id)
	$this->details[$ck][$this->par_key] = $newid;
    return(TRUE);
  }

  // renumbers the childs of an id, including childs and tree
  function renum($id=NULL){
    if(is_null($id)) $id = $this->topid;
    if(is_null($this->pos_key)) return(FALSE);
    if(!isset($this->childs[$id])) return(FALSE);
    //get current order
    $chlds = $this->childs[$id]; $pos = array();
    foreach($chlds as $ck) $pos[$ck] = $this->details[$ck][$this->pos_key];
    asort($pos); $ak = array_keys($pos); $ii = $this->pos_start;
    // renumber
    foreach($ak as $ck) $this->details[$ck][$this->pos_key] = $ii++;
    // set childs
    $this->childs[$id] = $ak;
    //set tree
    $tree = $this->tree;
    $stock = $this->_go_down($id,$tree,TRUE);
    $chlds = array();
    foreach($ak as $ck) $chlds[$ck] = $tree[$ck];
    $this->tree = $this->_go_up($stock,$chlds);
    return(TRUE);
  }


  function move(){die('just another open task');}
  function replace(){die('just another open task');}
  function filter(){die('just another open task');}//callback/feld auf details
  function walk(){die('just another open task');}//callback/feld auf details



}

?>
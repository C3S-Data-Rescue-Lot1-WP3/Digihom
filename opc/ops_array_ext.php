<?php

class ops_array_ext {

  /* returns data extended to asked size (integer or array)
   modes
      0/cyclic: reuse the values in data
      1/last: reuses the last value in data (cyclic)
      2/nlast: resuses the n last values in data (cyclic); n is given in value
      3/value: uses the value-argument n-times (n=diff between data and size)
      4/array: uses the n first elements in value (is an array)
      5/rarray: uses the n last elements in value (is an array)
   crop: if true crops data if it is already larger tahn size
   */
  static function extend($data,$size,$mode=0,$value=NULL,$crop=FALSE){
    if(is_array($size)) $size = count($size);
    $nd = count($data);
    $ak = array_keys($data);
    if($nd>=$size){
      if($crop) for($ii=$nd-1;$ii>=$size;$ii--) unset($data[$ak[$ii]]);
      return($data);
    }
    $nn = $size-$nd;
    switch($mode){
    case 0: case 'cyclic':
      for($ii=0;$ii<$nn;$ii++) $data[] = $data[$ak[$ii % $nd]];
      break;
    case 2: case 'nlast': 
      $value = array_slice($data,-$value); // no break
    case 4: case 'array':
      $nv = count($value);
      $value = array_values($value);
      for($ii=0;$ii<$nn;$ii++) $data[] = $value[$ii % $nv];
      break;
    case 5: case 'rarray':
      $nv = count($value);
      $of = max(0,$nv-$nn);
      $value = array_values($value);
      for($ii=0;$ii<$nn;$ii++) $data[] = $value[($of+$ii) % $nv];
      break;
    case 1: case 'last': $value = $data[$ak[$nd-1]]; // no break
    case 3: case 'value';
      for($ii=0;$ii<$nn;$ii++) $data[] = $value;
      break;
      
    }      
    return($data);
    
  }


  /* split an array in subarray
   sizes
      array of sizes; eg array(2,3,2,3)
      integer >0 sizes of the subarrays 
              <0 size of the main array
   trans: True -> transpose
  */
  static function split($data,$sizes,$trans=FALSE,$preserve_keys=FALSE){
    if(is_numeric($sizes)){
      if(!is_array($data)) return(NULL);
      $nele = count($data);
      if($sizes>0) $sizes = array_fill(0,ceil($nele/$sizes),$sizes);
      else if($sizes<0) $sizes = array_fill(0,-$sizes,ceil($nele/-$sizes));
      else return(NULL);
    } else $nele = 0;
    if(array_sum($sizes)<$nele) return(NULL);
    $res = array();
    if($trans){
      $na = count($sizes);
      $ca = 0;
      foreach($data as $key=>$val){
	while($sizes[$ca % $na]===0) $ca++;
	if($preserve_keys) $res[$ca%$na][$key] = $val;
	else $res[$ca%$na][] = $val;
	$sizes[$ca%$na]--;
	$ca++;
      }
    } else {
      if($preserve_keys){
	$ce = 0;
	foreach($data as $key=>$val){
	  if($sizes[0]==0) {array_shift($sizes); $ce++;}
	  $res[$ce][$key] = $val;
	  $sizes[0]--;
	}
      } else foreach($sizes as $cs) $res[] = array_splice($data,0,$cs);
    }
    return($res);
  }

  /* divides an array in nrow subarray so that the cum-size of them are more
   or less equal. It does not devide a single cell! Does not work
   well if some cizes are very large

   sizes: array of the single item sizes
   nrow: number of asked rows
   $data: data array (same size as sizes) or NULL (will use array(0,1,2 ... n))
   $penalty: additional penalty between items (but not at line breaks)
  */
  static function split_proportional($sizes,$nrow,$data=NULL,$penalty=0){
    $nele = count($sizes);
    if(is_null($data)) $data = range(0,$nele-1);
    if($nele<=$nrow){
      $res = array();
      if(is_null($data)) for($ii=0;$ii<$nele;$ii++) $res[] = array($ii);
      else  foreach($data as $key=>$val) $res[] = array($key=>$val);
      return($res);
    } elseif($nrow<2) {
      return(is_null($data)?array(range(0,$nele-1)):array($data));
    } else {
      if(is_null($data)) $data = range(0,$nele-1);
      $llen = (array_sum($sizes)+($nele-$nrow)*$penalty)/$nrow; // idealistic length per line
      $ccs = 0; // current cumsum
      $ii = 0;
      $lb = array_fill(1,$nrow-1,array(1,-1,-1));
      foreach($sizes as $cs){
	$ccs += $cs;
	$quot = $ccs/$llen;
	$rquot = round($quot);
	if($rquot==$nrow) break;
	if($rquot==0) $rquot = 1;//sometimes necessary by very long lines
	$delta = abs($quot-$rquot);
	if($delta<$lb[$rquot][0]) $lb[$rquot] = array($delta,$ii+1,$rquot);
	$ii++;
	$ccs += $penalty;
      }
      if(count($lb)==1) $lb = array_shift($lb);
      else $lb = array_reduce($lb,create_function('$x,$y','return(($y[0]<1 and $y[0]>$x[0])?$y:$x);'),array(1,-1,-1));
      if($lb[1]<$lb[2]) $lb[2] = $lb[1];

      $ldata = array_splice($data,0,$lb[1]);
      $lsizes = array_splice($sizes,0,$lb[1]);
      $ls = ops_array::split_proportional($lsizes,$lb[2],$ldata,$penalty);
      $rs = ops_array::split_proportional($sizes,$nrow-$lb[2],$data,$penalty);
      return(array_merge($ls,$rs));
    }
  }
  /** Splits an array at given positions
   * @param $array array to split
   * @param $pos position to split
   * @param $poskind: how the argument pos is interpreted (default: 0)
   *   0: numeric positions
   *   1: keys of $array
   *   2: use array_keys($pos) as $pos
   *   3: a pattern to be used with preg_grep($pos,$array)
   * @param $sepmode (default: 0)
   *   0: dont use the split-elements in the result
   *   1: use the split-element as first element of the next sub-array
   *   2: use the split-element as the last element of the previous sub-array
   *   3: use the split-element as key for the next sub-array
   * @return array of sub-array
   */
  static function split_at($array,$pos,$poskind=0,$sepmode=0,$noempty=TRUE){
    $n = count($array);
    $ak = array_keys($array);
    switch($poskind){
    case 0: $keys = range(0,$n-1); break;
    case 1: $keys = $ak; break;
    case 2: $pos = array_keys($pos); $kesy = $ak; break;
    case 3: $pos = array_keys(preg_grep($pos,$array)); $keys = $ak; break;
    }

    $res = array();
    $cres = array();
    $rkeys = array('');
    for($i=0;$i<$n;$i++){
      $ck = $keys[$i];
      if(in_array($ck,$pos)){
	switch($sepmode){
	case 0: $res[] = $cres; $cres = array(); break;
	case 1: $res[] = $cres; $cres = array($ak[$ck]=>$array[$ak[$ck]]); break;
	case 2: $cres[$ck] = $array[$ak[$ck]]; $res[] = $cres; $cres = array(); break;
	case 3: $res[] = $cres; $cres = array(); $rkeys[] = $array[$ak[$ck]]; break;
	}
      } else $cres[$ak[$ck]] = $array[$ak[$ck]];
    }
    $res[] = $cres;
    if($sepmode==3) $res = array_combine($rkeys,$res);
    if($noempty) $res = array_filter($res,create_function('$x','return count($x)>0;'));
    return $res;
  }



  /* akzeptiert liste von array 
   gibt eine vollständige Kombinationsliste durch
   optional letztes Argument bool -> TRUE umgekehrte Reihenfolge*/
  static function outer(/* */){
    $ar = func_get_args(); $na = count($ar);
    if($na==0) return(array());
    if(is_bool($ar[$na-1])) {
      $rev = array_pop($ar);
      $na--;
      if($na==0) return(array());
    } else $rev = FALSE;
    $code = 'return(is_array($x)?$x:(is_int($x)?range(0,$x-1):array(NULL)));';
    $ar = array_map(create_function('$x',$code),$ar);
    if($rev==TRUE) $ar = array_reverse($ar);
    $nr = array_reduce($ar,create_function('$s,$x','return($s*count($x));'),1);
    $br = $ar; // current available keys
    $cp = array(); for($ii=0;$ii<$na;$ii++) $cp[$ii] = array_shift($br[$ii]); // starting row
    $res = array($rev?array_reverse($cp):$cp); // result including first row
    for($ii=0;$ii<$nr-1;$ii++){//loop through
      $xp = -1;
      while(count($br[++$xp])==0){ // go to next column if current empty
	$br[$xp] = $ar[$xp]; // reset current col
	$cp[$xp] = array_shift($br[$xp]); // and insert first key of it
      }
      $cp[$xp] = array_shift($br[$xp]);
      $res[] = $rev?array_reverse($cp):$cp;
    }
    return($res);
  }


/*
 ranking of an array
 mode defines the behavior if some values will appear more than once
  mid: the elements will get an mean rank
  min: the elements will get the lowest rank
  max: the elements will get the highest rank
  con:the elements will get the lowest rank, an no rank is skipped

eg:    D   E   C   D   A   B
 mid   4.5 6   3   4.5 1   2 the sum of all ranks is allways n*(n+1)/2
 min   4   6   3   4   1   2
 max   5   6   3   5   1   2
 con   4   5   3   4   1   2 no rank is missing
*/
  static function array_rank($arr,$mode='mid'){
    $ok = array_keys($arr);
    asort($arr);
    $ct = array_count_values($arr); $tt = $ct;
    $cr = 0; $cp = 0;
    while(list($ak,$av)=each($arr)){
      $cp++;
      switch($mode){
      case 'mid':
	$arr[$ak] = $cr + ($ct[$av]+1)/2;
	if($tt[$av]--==1) $cr = $cp;
	break;
      case 'min':
	$arr[$ak] = $cr+1;
	if($tt[$av]--==1) $cr = $cp;
	break;
      case 'max':
	$arr[$ak] = $cr + $ct[$av];
	if($tt[$av]--==1) $cr = $cp;
	break;
      case 'con':
	$arr[$ak] = $cr+1;
	if($tt[$av]--==1) $cr++;
	break;
      default:
	$arr[$ak] = $cp;
      }
    }
    $re = array();
    foreach($ok as $ck) $re[$ck] = $arr[$ck];
    return($re);
  }
  }
?>
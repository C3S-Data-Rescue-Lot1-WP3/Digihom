<?php
class ops_estring{
  static $uml_repl = array('ä'=>'ae','ö'=>'oe','ü'=>'ue',
			   'Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue',
			   'ß'=>'ss','ç'=>'c','Ç'=>'C',
			   'é'=>'e','è'=>'e','É'=>'E','È'=>'E',
			   'â'=>'a','Â'=>'A','à'=>'a','À'=>'A',
			   'ô'=>'o','Ô'=>'O',
			   'ï'=>'i');

  /* 

  ideas 
    + str-repalcement like sprintf but additional options 
      like if var empty hide the brackets around too or so
    + strpos with multpile needles
      returns all hits or first of each needle or first over all needle
  */

/*
   explodes a string with items to an assoz array, where items has a name and a value

   Example 
     name:"John Doe" age: 23 company: 'John\'s pub'
     -> array(name=>"John Doe",age=>"23" company=>"John's pub")

   remarks to names
   + will be trimmed allways
   + has to be a combination of (in this order=
     * simple name; eg "_level9"; pattern: [_a-z][_a-z0-9]*
     * optional subname in []; eg "setting[startvalue]" -> will lead to an array in all cases
     * optional size in {}; eg: "code{120}"
     * optional type signature in (); eg "_level9(n)"
       s: string
       S: uses unserialize (if not at the end, needs size too in characters)
       n: numeric
       N: NULL (ignores value)
       b: boolean
       A: auto 
       a: array
  
   + additional also excpeted are names like name[subname] which will lead to an array
   values 
   + will be trimmed allways (to prevent this embedd them by " or ')
   + if the value contains the separation character (see $add below) it has to be embedded by " or '
   + \" inside "" and \' inside '' is recognized correctly

   $add is a named array with additional settings
    lowercase: if TRUE names will be converted to lowercase
    setchar: character between name and value (without spaces), default: ":"
    sepchar: character to separate two items
    noslash: if TRUE: do not uses slahses inside "" and ''
    emptyok: if TRUE: will accept empty values (and set them to TRUE)
    multi: what happens if a name occurs more than once
      error (default): return NULL
      first: use first value
      last: use last value
      array: create an array
    deftype: set the default type of the values  
  */

  static function explode_attr($str,$add=array()){
    if(!is_string($str)) return(NULL);
    if(!is_array($add)) $add = array('sepchar'=>$add);
    $sep = array_key_exists('sepchar',$add)?$add['sepchar']:' ';
    $set = array_key_exists('setchar',$add)?$add['setchar']:':';
    $nsl = array_key_exists('noslash',$add)?$add['noslash']:FALSE;
    $eok = array_key_exists('emptyok',$add)?$add['emptyok']:FALSE;
    $lcs = array_key_exists('lowercase',$add)?$add['lowercase']:FALSE; // not yet used
    $mlt = array_key_exists('multi',$add)?$add['multi']:'error';
    $dty = array_key_exists('deftype',$add)?$add['deftype']:'s'; // not yet used

    $str = trim($str);

    $res = array();
    while(strlen($str)>0){
      $pt = strpos($str,$set); if($pt==FALSE) $pt=strlen($str);
      $pp = strpos($str,$sep); if($pp==FALSE) $pp=strlen($str);
      $val = NULL;

      // overjump spaces between name and set-char if space is the sep-char
      if($pt>$pp and $sep==' ' and strlen(trim(substr($str,$pp,$pt-$pp)))==0){
	$pp = strpos($str,$sep,$pt); if($pp==FALSE) $pp=strlen($str);
      }
      if($pt>$pp){
	if(!$eok) return(NULL);
	$cnam = trim(substr($str,0,$pp));
	$val = $cnam;
	$str = trim(substr($str,$pp+strlen($sep)));
      } else if ($pt==$pp){
	if(!$eok) return(NULL);
	$cnam = $str;
	$val = $cnam;
	$str = '';
      } else if($pt<$pp) {
	$cnam = trim(substr($str,0,$pt));
	$str = trim(substr($str,$pt+strlen($set)));
	if(ord($str)<>34 and ord($str)<>39){
	  $pa = strpos($str,$sep); if($pa===FALSE) $pa = strlen($str);
	  $val = substr($str,0,$pa);
	  $str = trim(substr($str,$pa+strlen($sep)));
	} else {
	  $pa = $nsl?strpos($str,substr($str,0,1),1):ops_estring::strpos_noslash($str,substr($str,0,1),1);
	  if($pa===FALSE) return(NULL);
	  $val = substr($str,1,$pa-1);
	  $str = trim(substr($str,$pa+1));
	}
      } else break;

      if($val==="\N") $val = NULL;
      else $val = $nsl?$val:stripslashes($val);
      if($lcs==TRUE) $cnam = strtolower($lcs);
      if(preg_match('/^[_a-z][_a-z0-9]*$/i',$cnam)){
	if($lcase) $cnam = strtolower($cnam);		      
	if(array_key_exists($cnam,$res)===FALSE) $res[$cnam] = $val;
	else {
	  switch($mlt){
	  case 'error': return(NULL);
	  case 'first': break;
	  case 'last':  $res[$cnam] = $val; break;
	  case 'array': $res[$cnam] = array_merge($res[$cnam],$val); break;
	  }
	}
      } else if(preg_match('/^[_a-z][_a-z0-9]*\[[_a-z][_a-z0-9]*\]$/i',$cnam)){
	list($xx,$cnam,$snam)=ops_estring::preg_part_first('/^(.*)\[(.*)\]$/',$cnam);
	$res[$cnam][$snam] = $val;
      } else return(NULL);
    }
    return($str==''?$res:NULL);
  }
  
  /** extended explode
   * @param string $str: string to expldoe into single items
   * @param named-array $add additional settings<br>
   *  sepchar (string): separator chracter (default: [SPACE])<br>
   *  noslash (boolean): if TRUE slashes are ignored (default: FALSE)<br>
   *  trim (boolean): if TRUE all elements will be trimmed (default: FALSE)
   * @return: array of strings
   */
  static function explode_list($str,$add=array()){
    if(!is_string($str)) return(NULL);
    if(!is_array($add)) $add = array('sepchar'=>$add);
    $sep = array_key_exists('sepchar',$add)?$add['sepchar']:' ';
    $nsl = array_key_exists('noslash',$add)?$add['noslash']:FALSE;
    $trm = array_key_exists('trim',$add)?$add['trim']:TRUE;
    $ar = array();
    $par = preg_split("/[\"'\\$sep]/",$str);
    preg_match_all("/[\"'$sep]/",$str,$mar);
    $mar = $mar[0];
    // reconnect slashes before " ' or [sep]
    if($nsl===FALSE){
      for($ii=count($mar)-1;$ii>=0;$ii--){
	if(substr($par[$ii],-1)=='\\'){
	  $par[$ii] = substr($par[$ii],0,-1) . $mar[$ii] . $par[$ii+1]; 
	  array_splice($par,$ii+1,1);
	  array_splice($mar,$ii,1);
	}
      }
    }
    $ii = 0; $res = array(); $val = '';
    while(count($mar)>0){
      $cs = array_shift($mar);
      if($cs!=$sep){
	array_shift($par); // should be empty
	$val = array_shift($par); // start new value
	while($cs != $ns = array_shift($mar)) $val .= $ns  . array_shift($par);
	if($sep != array_shift($mar)) return(NULL);
	array_shift($par);
      } else $val = array_shift($par);
      if($val!=="\N"){
	$cval = $nsl?$val:stripslashes($val);
	$res[] = $trm?trim($cval):$cval;
      } else $res[] = NULL;
    }
    $val = array_shift($par);
    if($val!=="\N"){
      $cval = $nsl?$val:stripslashes($val);
      $res[] = $trm?trim($cval):$cval;
    } else $res[] = NULL;
    return($res);
  }
  
  /** explode including triming the results, 0-size will be removed too */
  static function explode_trim($sep,$txt,$lim=NULL){
    $txt = is_null($lim)?explode($sep,$txt):explode($sep,$txt,$lim);
    $res = array();
    foreach($txt as $ct) if(strlen(trim($ct))>0) $res[] = trim($ct);
    return($res);
  }

  /** extended explode
   *
   * an array as $sep allows to explode the results recursive. The result is in this
   * case a nested array.
   *
   * @param string|array of strings $sep: separator or array of them
   * @prar int $lim: similar to limit in explode (0: means no limit; default: 0)
   * @param bool $trim: trim the results afterwards (default: TRUE);
   * @param bool $rem: remove empty results (strlen=0)
   * @retur array of substring (nested if sep i an array with 2 or more sep-strings)
   */
  static function explode($sep,$txt,$lim=NULL,$trim=TRUE,$rem=TRUE){
    if(is_array($sep)){
      if(count($sep)==0) return($txt);
      $csep = array_shift($sep);
      $txt = self::explode($csep,$txt,$lim,$trim,$rem);
      if(count($sep)>0){
	$body = '$x = ops_estring::explode(' . var_export($sep,TRUE) . ',$x,' 
	  . var_export($lim,TRUE) . ',' 
	  . var_export($trim,TRUE) . ',' . var_export($rem,TRUE) . ');';
	array_walk($txt,create_function('&$x',$body));
      }
      return($txt);
    } else if(is_string($sep)) {
      $txt = ($lim==0 or is_null($lim))?explode($sep,$txt):explode($sep,$txt,$lim);
      if($trim) array_walk($txt,create_function('&$x','$x=trim($x);'));
      if($rem)  $txt = array_filter($txt,create_function('$x','return(strlen($x)>0);'));
      return($txt);
    }
  }

  /**  explode which regards quoted areas 
   * similar to explode but ignores the $sep inside a quoted area (" or ')
   *   eg: explode_quoted(' ','"John Bravo" Jones "James O\'Brian" "Juan Smith"')
   *   -> array{"John Bravo","Jones","James O'Brian","Juan Smith")
   * @param string $sep: separator
   * @param string $text: text to explod
   * @return array of strings
   */
  static function explode_quoted($sep,$text){ 
    $pl = array(''); $pn = 0; $sm = 0;
    while(strlen($text)){
      $cc = substr($text,0,1); $text = substr($text,1);
      if($cc==$sep and $sm==0){
	$pl[++$pn] = '';
      } elseif($cc=='\\'){
	$pl[$pn] .= substr($text,0,1); $text = substr($text,1);
      } elseif($sm==0 and ($cc=='"' or $cc=='\'')){
	$sm = ($cc=='"')?1:2;
      } elseif(($sm==1 and $cc=='"') or ($sm==2 and $cc=='\'')){
	$sm = 0;
      } else $pl[$pn] .= $cc;
    }
    $pl = array_values(preg_grep('/./',$pl));
    return($pl);
  }


  /* 
   will return the first matches of preg_match_all into an array (excluding the global)
   var: if not null a list of names for the array items
 */  
  static function preg_part_first($pattern,$string,$var=NULL){
    preg_match_all($pattern,$string,$tres,PREG_PATTERN_ORDER);
    $res = array();
    if(is_null($var)){
      array_shift($tres);
      while(count($tres)) $res[] = array_shift(array_shift($tres));
    } else {
      for($ci=0;$ci<count($var);$ci++){
	$res[$var[$ci]] = isset($tres[$ci+1])?$tres[$ci+1][0]:NULL;
      }
    }
    return($res);
  }

  /** similar to strpos but ignores position if leaded by a slash */
  static function strpos_noslash($hay,$needle=NULL,$offset=0,$slash='\\'){
    $po = strpos($hay,$needle,$offset);
    $sl = strlen($slash);
    while($po!==FALSE){
      if($po<$sl or substr($hay,$po-$sl,$sl)!=$slash) return($po);
      $po = strpos($hay,$needle,$po+1);
    } 
    return(FALSE);
  }


  /* transform a number to an index
   * @param int $value: current number
   * @param char $kind: 'i/I' -> roman number (lower/uppercase); 'a/A' -> alphabetic coding
   */
  static function format_index($value,$kind='I'){
    $value = abs((int) $value);
    if($value==0) return('');
    $lc = TRUE;
    switch($kind){
    case 'I': $lc = FALSE;
    case 'i':
      if($value>=1000){
	$res = str_repeat('M',floor($value/1000));
	$value = $value % 1000;
      } else $res = '';
      $vals = array(900=>'CM',500=>'D',400=>'CD',100=>'C',
		    90=>'XC',50=>'L',40=>'XL',10=>'X',
		    9=>'IX',5=>'V',4=>'IV',1=>'I');
      foreach($vals as $inc=>$chr)
	while($value>=$inc) { $res .= $chr; $value -= $inc;}
      break;
    case 'A': $lc = FALSE;
    case 'a':
      $res = '';
      while($value>0){
	$res = chr(65+($value-1)%26) . $res;
	$value = floor(($value-1)/26);
      }
      break;
    default:
      return(strval($value));
    }
    return($lc?strtolower($res):$res);
  }

  /** nested explode using a pattern which match tag-like parts
   * a tag ranges start with an uppercase letter the closing with the corresponding lowercase
   * @param string $text: text to explode
   * @param pattern $pattern: pattern used to explode (Default '/%(\w)%/';)
   * @param function $cb: callback function (or NULL)
   */
  static function explode2dn($text,$pattern=NULL,$cb=NULL){
    if(is_null($pattern)) $pattern = '/%(\w)%/';
    if(!is_callable($cb)) $cb = NULL;
    $split = preg_split($pattern,$text,-1,PREG_SPLIT_DELIM_CAPTURE);
    if(count($split)==0) return($text);
    $res = array(array_shift($split));
    $stack = array(); $path = array();
    while(count($split)>0){
      $ckey = array_shift($split);
      if(ord($ckey)<95){
	array_unshift($stack,$res,$ckey);
	$res = array();
      } else {
	$tres = $res;
	if(count($stack)==0)
	  return(trigger_error('No match between open- and close-key'));
	list($res,$okey)=array_splice($stack,0,2);
	if(strtolower($okey)!=strtolower($ckey)) 
	  return(trigger_error('No match between open- and close-key'));
	if(is_null($cb))
	  $res[] = array('type'=>$okey,'content'=>$tres);
	else 
	  $res[] = call_user_func($cb,$okey,$tres,$path);
      }
      if(count($split)>0) $res[] = array_shift($split);
    }
    if(count($stack)>0) 
      return(trigger_error('Missing close-key'));
    return($res);
  }


  /**   static function explode_nested
   * \ is used to escape characters
   * @param $txt: text to explode to a nested array
   * @param $sep array of sub-parts definitions
   *  array(key=>x) where x is a pattern or an array of a start and end-pattern
   *   the end pattern may contain a $1 which will be repalce with the current open-part
   *   eg to catch something between '|' use type1=>'[|]'
   *      to catch a typically string use string=>array('["\']','$1') $1 will match either " or ' depending on what was used in txt
   *      to cacth a bracket use brack=>array('(',')')
   * @param $appears array of array: where the sub-parts may defineid. At least an array with key has to be given
   * @param $cb_open callback-function if a new sub is opend cb_open(&mode,&delim,&add);
   *         mode: current mode (a key from $sep), may be modified
   *         delim: the recogniced open-delimiter, may be modified
   *         add is an empty array to be filled with thing s like attributes, may be modified
   * @param $cb_close callback-function if a sub will be closed cb_close(mode,delim,&res,&add);
   *         mode: current mode (a key from $sep)
   *         delim: the recogniced close-delimiter
   *         res is an array with all results, you may return an array or a string
   *         add is the same as used in cb_open
   * @return
   */
  static function explode_nested($txt,$sep,$appears,$cb_open=NULL,$cb_close=NULL){
    $sep = array_map(create_function('$x','return is_array($x)?array($x[0],$x[count($x)>1?1:0]):array($x,$x);'),$sep);
    $asep = array(0=>array());
    foreach(array_keys($sep) as $ck) $asep[$ck] = array(0=>$sep[$ck][1]);
    foreach($appears as $ckey=>$subs) foreach($subs as $cs) $asep[$ckey][$cs] = $sep[$cs][0];
    $psep = array_map(create_function('$x','return "#(\\\\\|" . implode("|",$x) . ")#";'),$asep);
    $res = array();
    $stack = array();
    $add = array();
    $cmode = 0;
    $cpat = $psep[0];
    while(strlen($txt)>0){
      $lr = count($res)-1;
      $cpart = preg_split($cpat,$txt,2,PREG_SPLIT_DELIM_CAPTURE);
      if(count($cpart)==1) break;
      if($cpart[1]=='\\') {
	if($lr>=0 and is_scalar($res[$lr])) $res[$lr] .= $cpart[0] . substr($cpart[2],0,1);
	else $res[] = $cpart[0] . substr($cpart[2],0,1);
	$txt = substr($cpart[2],1);
      } else {
	foreach($asep[$cmode] as $ck=>$cv) if(preg_match('#^' . $cv . '$#',$cpart[1])) break;
	if($lr>=0 and is_scalar($res[$lr])) $res[$lr] .= $cpart[0];
	else $res[] = $cpart[0];
	if($ck!==0){
	  $stack[] = array($cmode,$res,$cpat,$add);
	  $cmode = $ck;
	  $res = array();
	  $add = array();
	  if(is_callable($cb_open)) $cb_open($cmode,$cpart[1],$add);
	  $cpat = str_replace('$1',$cpart[1],$psep[$cmode]);
	} else{
	  if(is_callable($cb_close)) $cb_close($cmode,$cpart[1],$res,$add);
	  list($nmode,$nres,$npat,$nadd) = array_pop($stack);
	  $lr = count($nres)-1;
	  if($lr>=0 and is_scalar($nres[$lr]) and is_scalar($res)) $nres[$lr] .= $res;
	  else $nres[] = array('type'=>$cmode,'add'=>$add,'content'=>$res);
	  $add = $nadd;
	  $cmode = $nmode;
	  $res = $nres;
	  $cpat = $npat;
	}
	$txt = $cpart[2];
      }
    }
    $lr = count($res)-1;
    if($lr>=0 and is_scalar($res[$lr])) $res[$lr] .= $cpart[0] . $txt;
    else $res[] = $txt;
    return $res;
  }

  /** extract all substring embed by " or '
   *
   * extract all suibstings embeded by ' or " and returns them in an array
   * in the orignial string they will be replaced by a placeholder.
   * Escaped quotes (\' and \") will handled correct (incl \\)
   * @param string $str: the original string
   * @param string $pat: placeholder pattern (default $.$) The point inside $pat
   *  will be replaced by the current match counter (starting with 1)
   * @return array where element 0 is the original string with the $pat instead
   *   of the substrings, all oter elements correspond to the m'th substring.
   */
  static function extract_string($str,$pat='$.$'){
    $text = '';
    $strings = array(0=>'');
    $cmod = 0;
    $key = 0;
    $left = 0;
    $r1 = strpos($str,"'");
    $r2 = strpos($str,'"');
    if($r1===$r2) return array($str);
    $right = $r1===FALSE?$r2:($r2===FALSE?$r1:($r1<$r2?$r1:$r2));
    do{
      if(substr($str,$right-1,1)==='\\'){
	$i=2;
	while(substr($str,$right-$i,1)==='\\') $i++;
	$slash = ($i%2)==0;
      } else $slash = FALSE;
      if($slash){
	if($cmod===0) $text .= substr($str,$left,$right-$left-1) . substr($str,$right,1);
	else $strings[$key] .= substr($str,$left,$right-$left-1) . substr($str,$right,1);
      } else if($cmod==0){
	$text .= substr($str,$left,$right-$left);
	$strings[++$key] = '';
	$cmod = substr($str,$right,1)=='"'?1:-1;
      } else if(substr($str,$right,1) != ($cmod==1?"'":'"')){
	$strings[$key] .= substr($str,$left,$right-$left);
	$text .= str_replace('.',$key,$pat);
	$cmod = 0;
      } else $strings[$key] .= substr($str,$left,$right-$left+1);
      $left = $right+1;
      $r1 = strpos($str,"'",$left);
      $r2 = strpos($str,'"',$left);
      if($r1===$r2) break;
      $right = $r1===FALSE?$r2:($r2===FALSE?$r1:($r1<$r2?$r1:$r2));
    } while(TRUE);
    if($cmod!=0) 
      trigger_error("non properly closed sub-string: $str",E_USER_NOTICE);
    $strings[0] = $text . substr($str,$left);
    return $strings;
  }

  static function astring2array($text,$set='=',$sep=' '){
    $parts = self::extract_string($text);
    $eles = self::explode(array($sep,$set),$parts[0]);
    $res = array();
    $ne = count($parts);
    foreach($eles as $val){
      if(count($val)!=2) continue;
      $cv = $val[1];
      for($i=1;$i<$ne;$i++) 
	$cv = str_replace('$' . $i . '$',$parts[$i],$cv);
      $res[$val[0]] = $cv;
    }
    return $res;
  }

  /** extends a string to an array of page numbers
   * 
   * the main argument is  alist of pages and page-ranges
   * eg: 1, 4, 6-8
   * 7- means 7 up to max
   * if max is not NULL -2 means second last page (=max-1)
   * pages outside min/max will be remove
   * @param string $pages page-selection
   * @param str $sep: separator (default: ',')
   * @param int|NULL $max: highest page number
   * @param int $min: lowest page number (default: 1)
   * @param bool $sort: sort and remove duplicates? (default:TRUE)
   * @return array of integers (0-size is possible) or FALSE if $pages was not valid
   */
  static function pages2array($pages,$sep=',',$max=NULL,$min=1,$sort=TRUE){
    $res = array();
    foreach(explode($sep,$pages) as $cp){
      $cp = trim($cp);
      if(ctype_digit($cp)){ // ----------------------------------------------------------  5
	$res[] = (int)$cp;
      } else if(preg_match('/^\d+\s*-\s*\d+$/',$cp)){ // --------------------------------  1-3
	$cp = explode('-',$cp);
	$res = array_merge($res,range($cp[0],$cp[1]));
      } else if(is_int($max)){ // =================================================== max is int
	if($cp==='-'){ // ---------------------------------------------------------------  -
	  $res = range($min,$max);
	} else if(preg_match('/^-\d+$/',$cp)){ // ---------------------------------------  -5
	  $res[] = $max+1+(int)$cp;
	} else if(preg_match('/^-\s+\d+$/',$cp)){ // ------------------------------------  - 5
	  $res = array_merge($res,range($min,(int)substr($cp,1)));
	} else if(preg_match('/^-\s*-\d+$/',$cp)){ // -----------------------------------  --5
	  $res = array_merge($res,range($min,$max+1+(int)substr($cp,1)));
	} else if(preg_match('/^-?\d+\s*-$/',$cp)){ // ----------------------------------  5-
	  $res = array_merge($res,range($cp<0?($max+1+$cp):$cp,$max));
	} else if(preg_match('/^-?\d+\s*-\s*-?\d+$/',$cp)){ // --------------------------  1-3
	  $cp = explode('-',$cp);
	  if(strlen($cp[0]==0)){   array_shift($cp); $cp[0] = $max+1-$cp[0]; }
	  if(strlen($cp[1]==0)){   $cp[1] = $max+1-$cp[2]; }
	  $res = array_merge($res,range($cp[0],$cp[1]));
	} else return FALSE;
      } else { // =================================================================== max is NULL
	if(preg_match('/^-\s*\d+$/',$cp)){ // -------------------------------------------  - 5
	  $res = array_merge($res,range($min,substr($cp,1)));
	} else return FALSE;
      } 
    }
    if($sort){
      $res = array_unique($res);
      sort($res);
      if($min>0) 
	while(count($res)>0 and $res[0]<$min) array_shift($res);
      if(is_int($max) and $max>=$min)
	while(count($res)>0 and $res[count($res)-1]>$max) array_pop($res);
    } else {
      $ak = array_keys($res);
      if($min>0) 
	foreach($ak as $ck) 
	  if($res[$ck]<$min or (is_int($max) and $res[$ck]>$max)) 
	    unset($res[$ck]);
    }
    return $res;
  }


  static function simplify($text,$how=NULL){
    foreach((array)$how as $ch){
      $mth = 'simplify__' . $ch;
      $text = self::$mth($text);
    }
    return $text;
  }

  static function simplify__case_low($text) { 
    return strtolower($text);
  }

  static function simplify__strip_white($text) { 
    return preg_replace('{\s+}','',$text);
  }

  static function simplify__strip_punc($text) { 
    return preg_replace('{[-,.;:!?\']}','',$text);
  }

  static function simplify__repl_schar($text) { 
    return preg_replace('{[^\d\w]}','_',$text);
  }

  static function simplify__uml($text) { 
    return str_replace(array_keys(self::$uml_repl),
		       array_values(self::$uml_repl),
		       $text);
  }


  }
?>
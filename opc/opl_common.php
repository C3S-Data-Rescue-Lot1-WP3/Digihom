<?php


/*
 Common functions to php types
*/

/*
 str2time
 reverse the function date (all not casesensitiv)
 by words: longer forms are preferd (january is matched before jan)
           german words are matched to (including März and Maerz)
 by numeric with non fixed sized is 12 preferd than 1
 the date will not be checked of consistence ( 31. Feb is possible!)
 format is build the following letters
  a: am/pm (pm adds 12 to the hour)
  c: calendar week 2 letter number (1-52)
  C: calendar week 1-2 letter number (1-52)
  d: day of the month 2 letter number
  D: day of the month 1-2 letter number
  h: hour 2 letters
  H: hour 1-2 letters (24-System)
  i: minutes 2 letters
  I: minutes 1-2 letters
  j: day of the year 3 letters 000-365
  J: day of the year 1-3 letters 0-365
  m: month 2 letter number
  M: month 1-2 letter number
  n: month name 3-n letters
  q: season of the year 1 letter number (1-4)
  s: seconds 2 letters
  S: seconds 1-2 letters
  w: weekday 1 letter number
  W: weekday name 2-n lettrs
  y: year 2 letter number
  Y: year 4 letter number
  *: skip any sequence of non alphanumeric characters including äö...
  ?: skip any sequence of non numeric characters
  anything else: skips one letter in date
 
 mode one of (date num text) defines the type of the return value
  date: tries to construct a unix-timestamp
  num: returns a named array with all components as number
  text: same as num, but weekday, month and ampm may be a text
  the possible array elements are
    sec, min, hour, ampm, day, month, year, wday, yday, cweek
 weekstart is one of the following s0 s1 m0 m1
  and defines if the weeks start with sunday or monday an the range (0-6/1-7)
*/
function str2time($format,$date,$mode='date',$weekstart='s0'){
  $z['wday'] = array('sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,
		     'thursday'=>4,'friday'=>5,'saturday'=>6,
		     'sonntag'=>0,'montag'=>1,'dienstag'=>1,'mittwoch'=>3,
		     'donnerstag'=>4,'freitag'=>5,'samstag'=>6,'sonnabend'=>6,
		     'sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,
		     'sat'=>6,
		     'so'=>0,'mo'=>1,'di'=>2,'mi'=>3,'do'=>4,'fr'=>5,'sa'=>6);
  // Order is long names before short names
  $z['month'] = array('january'=>1,'february'=>2,'march'=>3,'april'=>4,
		      'may'=>5,'june'=>6,'july'=>7,'august'=>8,'september'=>9,
		      'october'=>10,'november'=>11,'december'=>12,
		      'januar'=>1,'februar'=>2,'maerz'=>3,'märz'=>3,'april'=>4,
		      'mai'=>5,'juni'=>6,'july'=>7,'september'=>9,
		      'oktober'=>10,'november'=>11,'dezember'=>12,
		      'juli'=>7,'juni'=>6,'märz'=>3,'febr'=>2,'sept'=>10,
		      'jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,
		      'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12,
		      'mär'=>3,'mai'=>5,'okt'=>11,'dez'=>12);
  $defs = array('a'=>'am:letter:-2',
		'c'=>'cweek:num:-2','C'=>'cweek:num:52',
		'd'=>'day:num:-2','D'=>'day:num:31',
		'h'=>'hour:num:-2','H'=>'hour:num:24',
		'i'=>'min:num:-2','I'=>'min:num:59',
		'j'=>'yday:num:-3','J'=>'yday:num:365',
		'm'=>'month:num:-2','M'=>'month:num:12',
		'n'=>'month:letter:0','q'=>'season:num:-1',
		's'=>'sec:num:-2','S'=>'sec:num:59',
		'w'=>'wday:num:-1','W'=>'wday:letter:0',
		'y'=>'year:num:-2','Y'=>'year:num:-4',
		'*'=>'alpha:any:0','?'=>'num:any:0');
  $ch['num'] = "0123456789";
  $ch['alpha'] = "abcdefghijklmnopqrstuvwxyzäüö"; 
  $ch['alpha'] .= strtoupper($ch['alpha']) . $ch['num'];
  $n = strlen($format); $j = 0;
  $d = array('year'=>1970,'month'=>1,'day'=>1,'hour'=>0,'min'=>0,'sec'=>0);
  for($i=0;$i<$n;$i++){
    list($w,$m,$c) = explode(':',$defs[substr($format,$i,1)]); 
    $c = (int)$c; $ci = NULL;
    if($m=='letter' & $c==0) $y = $z[$w];
    switch($m){
    case 'num':
      if($c<0){
	$ci = (int)substr($date,$j,-$c); $j -= $c;
      } else {
	$y = ceil(log10($c)); // max number of digits (by definition)
	$x = 0; // find max number of digits limited by letters in $date
	while(strpos($ch['num'],substr($date,$j+$x,1)) and $x < $y) $x++;
	$y = substr($date,$j,$x); $u = 0;
	while((int)$y > $c) {$y = substr('0' . $y,0,$x); $u++;}
	$ci = (int)$y;
	$j += $x - $u;
      }
      break;
    case 'letter':
      if($c<0) {
	$ci = substr($date,$j,-$c); $j -= $c;
      } elseif($c==0) {
	while(list($k,$v)=each($y)){
	  if(strtolower(substr($date,$j,strlen($k)))==$k){
	    if($mode=='text') $ci = substr($date,$j,strlen($k));
	    else $ci = $v;
	    $j += strlen($k);
	    break;
	  }
	}
      }
      break;
    case 'any': 
      $y = $ch[$w]; while(!strpos($y,substr($date,$j,1))) $j++; 
      echo substr($date,$j) . " $y<br>";
      $ci = NULL;
      break;
    default: $ci = NULL; $j++; break;
    }
    if(!is_null($ci)) $re[$w] = $ci;
  }
  if($mode!='date') return($re);
  elseif(!is_null($re['yday'])){
    $re = mktime($re['hour'],$re['min'],$re['sec'],
		 1,1+$re['yday'],$re['year']);
    return($re);
  } else return(mktime($re['hour'],$re['min'],$re['sec'],
		       $re['month'],$re['day'],$re['year']));
}

/*
 test ob wirklich NULL (ohne 0 und '')
*/
function kill_isnull($val){
  if(!empty($val)) return(FALSE);
  else return(!is_long($val) and !is_string($val));
}
/*
 gibt arrayelement zurück oder arr (falls dieses kein array ist) oder $def
 prefix und postfix nur verwendet falls def nicht zum Zug kommt
*/
function virtArray($arr,$item,$def=NULL,$prefix='',$postfix=''){
  if(!isset($arr)) return($def);
  if(!is_array($arr)) return($prefix . $arr . $postfix);
  if(isset($arr[$item])) return($prefix . $arr[$item] . $postfix);
  return($def);
}

/* gibt Wert Aus Array zurück falls gesetzt sonst den Default */
function issetdef($arr,$key,$def=NULL){
  return(array_key_exists($key,$arr)?$arr[$key]:$def);
}

/*
  füllt ein array mit Standartwerten auf
  def is ein assoziatives array mit den Standartwerten
  arr-elemente mit numerische key erhalten die namen aus def (der Reihe nach),
   die nicht bereits in arr definiert sind.
  recursive: falls das element in $arr und $def ein array ist, wird
   der Prozess für deren Elemente fortgesetzt
*/
function array_default($arr,$def,$recursiv=false){
  $ak = array_keys($arr); $nk = array();
  foreach($ak as $ck){
    if(is_numeric($ck))
      $nk[] = $ck;
    elseif(array_key_exists($ck,$def)){
      if(is_array($def[$ck]) and is_array($arr[$ck]))
	$arr[$ck] = array_default($arr[$ck],$def[$ck],true);
      unset($def[$ck]);
    }
  }
  $dk = array_keys($def);
  foreach($nk as $ck){
    $arr[array_shift($dk)] = $arr[$ck];
    unset($arr[$ck]);
  }
  while(list($ak,$av)=each($def)) $arr[$ak] = $av;
  return($arr);
}


/*
 extrahiert alle Element aus arr die in keys angegeben sind
 und gibt beide array zurück als array(extrakt, others) falls
 both=true sonst nur das extrahierte
*/
function array_split($arr,$keys,$both=false){
  $rev = array();
  foreach($keys as $key)
    if(isset($arr[$key])) { $rev[$key] = $arr[$key]; unset($arr[$key]);}
  if($both) return(array($rev,$arr)); else return($rev);
}

/*
 wandelt geschachteltes Array in assoziatives um
 key gibt an welches Element der arr-Elemente für den Namen verwendet wird
 die ursprünglichen Namen gehen verloren
 falls flat=TRUE wird nach möglichkeit die Arraystruktur aufgebrochen
*/
function array2named($arr,$key,$flat=true){
  $rev = array();
  foreach($arr as $av){
    $nk = $av[$key]; unset($av[$key]);
    if(count($av)==1){$ak = array_keys($av); $av = $av[$ak[0]];}
    $rev[$nk] = $av;
  }
  return($rev);
}


/* matheval is a variation of eval with security restrictions
   it allows only mathematical expressions and numerical variables!

   Version 1.0 (24.5.2006): first running version

   equation: the equation as string 
     hints:
       all spaces and $-signs in the equation will be removed in advance
       variable names may be written with or without the $; eg: "3*a+$b"
       numbers in scientific notation are allowed; eg: "3.2E-5"
       do not forget: ^ is a bit operator, use pow() for the power function!
       you may use a sequence of mathematical statements for the equation
         where all excluding the last are assignements
         eg: "b=b*b; c=c*c; e=b+c+b*c; pow(e,2)" take care of side effects!
       the equation has to fit the following pattern
         '/^[-+*\/%()=<>_?:,;0-9a-zA-Z\x7f-\xff]*$/'

   varallowed: allowed variables inside equation, which may definded on two ways
     value-list: array with key/value; eg array('a'=>4,'b'=>7)
     variable-list: one or more (as array) pattern to proof the variable names
        the pattern is only the inner part without "/^" or "$/"
	therefore varallowed may also be a simple list like array('a','b','ab')
     default: "[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*" 
        this pattern corresponds to the php syntax for variable names
     hints:
        get_defined_vars() may be a good joice for $varallowed
	in all cases only numerical values will be accepted (security!)
	the build in php constants of the MATH sections are allways allowed
        
   functionallowed: an array of allowed function names inside equation
      where '_MATH_' is a short for all the build in functions of the MATH-section
      the ?: statement and the pow function are allways allowed
      default: null; which is equal to array('_MATH_')
      hint:
        be carefull, probably you allow a dangerous function!!

   return-value
      if equation contain not allowed variables or functions: NULL
      if non numeric variables are used in the equation: NULL
      if varallowed is a value-list: the final numerical result
      if varallowed is a variable-list: a string ready for using inside eval
         This step is necessary since matheval does not see all necessary
	 variables (namescope). This string includes a loop which proofs that
	 all used variables are numerical

   examples
   with pattern
     $equation = "a+b+3"; $a = 4; $b = 5;
     echo eval(matheval($equation,'[a-z]{1}')); 
     ->  12
   with varaible list
     $equation = "a+b+3"; $a = 4; $b = 5;
     echo eval(matheval($equation,array('a','b'))); 
     ->  12
   with value list (no eval necessary!)
     $equation = "a+b+3";
     echo matheval($equation,array('a'=>4,'b'=>5)); 
     ->  12

*/

function matheval($equation,
		  $varallowed=null,
		  $functionallowed=array('_MATH_')){
  /* dev-note
     at some place a preg_replace statement is looped this is
     necessary since a second or higher replacement starts after
     the last replacement. Therefore in "a+b" only "a" would be
     reconginced since the "+" is end and start of the expression at
     the same time.
   */
  //preparation --------------------------------------------------
  $vmatch = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*'; // php name syntax
  $vchars = '([^a-zA-Z0-9_\x7f-\xff])'; // no varname characters
  $vcharsP = '([^$a-zA-Z0-9_\x7f-\xff])'; // as vchars excluding $
  // build in math-functions
  $defmath = array('abs','acos','acosh','asin','asinh','atan2','atan',
		   'atanh','base_convert','bindec','ceil','cos','cosh',
		   'decbin','dechex','decoct','deg2rad','exp','expm1',
		   'floor','fmod','getrandmax','hexdec','hypot',
		   'is_finite','is_infinite','is_nan','lcg_value','log10',
		   'log1p','log','max','min','mt_getrandmax','mt_rand',
		   'mt_srand','octdec','pi','pow','rad2deg','rand','round',
		   'sin','sinh','sqrt','srand','tan','tanh');
  // build in math constants
  $const = array('M_PI','M_E','M_LOG2E','M_LOG10E','M_LN2','M_LN10',
		 'M_PI_2','M_PI_4','M_1_PI','M_2_PI','M_2_SQRTPI',
		 'M_SQRT2','M_SQRT1_2');

  if(is_null($varallowed)) $varallowed = $vmatch; //set default
  if(!is_string($equation)) return(NULL);
  if(!is_array($functionallowed)) return(NULL);
  // remove spaces and $-signs and
  $equation = preg_replace('/[ $]/','',$equation);
  // check basic pattern
  if(!preg_match('/^[-+*^\/%()=<>_?:,;.0-9a-zA-Z\x7f-\xff]*$/',$equation))
    return(NULL);
  //remove ; at the end
  if(substr($equation,-1,1)==';') $equation = substr($equation,0,-1);

  // inlcude default functions to functionallowed if necessary
  $mk = array_search('_MATH_',$functionallowed);
  if(!($mk===false)){
    unset($functionallowed[$mk]);
    $functionallowed = array_merge($functionallowed,$defmath);
  }


  //replace numbers with scientific notation ----------------------------------------
  // they will be repalce by a product with a power fucntion
  // necessary otherwise it would be possible to smuggling variables into the equation
  $curpat = '/([0-9]+([.][0-9]*)?|[0-9]*[.][0-9]+)[Ee]([-+]?[0-9]+)/';
  $equation = preg_replace($curpat,'($1*pow(10,$3))',$equation);

  // temporary equation to test if equation fits the rules
  $te = $equation;

  // allowed functions ---------------------------------------------
  $curpat = '/' . $vmatch . ' *[(]/';
  preg_match_all($curpat,$te,$funkeys); //varkys contains the used variables!
  $tv = preg_grep('/^(pow|' . implode('|',$functionallowed) . ') *[(]$/',$funkeys[0]);
  if(count($tv)!=count($funkeys[0])) return(NULL);
  $te = preg_replace($curpat,'(',$te); //remove function names for variable test

  // allowed const --------------------------------------------------
  foreach($const as $cf){
    $curpat = '/' . $vchars . $cf . $vchars . '/i';
    $tz = preg_replace($curpat,'$1 0 $2',$te);
    while($tz!=$te){$te = $tz; $tz = preg_replace($curpat,'$1 0 $2',$te);}
  }

  //allowed variables --------------------------------------------------

  //convert variable/pattern list to pattern only
  $ak = is_array($varallowed)?array_keys($varallowed):array('a'=>0);
  if(is_numeric($ak[0])){ 
    $tv = preg_grep('/^' . $vmatch . '$/',$varallowed);
    if(count($varallowed)!=count($tv)) return(NULL);
    $varallowed = '(' . implode('|',$varallowed) . ')';
  } elseif(is_string($varallowed)) $varallowed = '(' . $varallowed . ')';

  $mode = is_string($varallowed);
  
  //split into single statements and add spaces for simpler regexpr.
  $seq = explode(';',' ' . str_replace(';',';',$equation));
  $tseq = explode(';',' ' . str_replace(';',';',$te));
  $newvar = array(); // new defined variables inside the statements
  $nseq = count($seq);
  for($ci=0;$ci<$nseq;$ci++){
    if($ci==$nseq-1){
      $tseq[$ci] = ' ' . $tseq[$ci] . ' ';
      $seq[$ci] = $mode?"return($seq[$ci]);":" $seq[$ci] ";
    } else {
      $tv = explode('=',$seq[$ci],2);
      if(count($tv)!=2) return(NULL);
      if(!$mode) $seq[$ci] = " $tv[1] ";
      $tv = trim($tv[0]);
      if(!preg_match('/^' . $vmatch . '$/',$tv)) return(NULL); 
      $newvar[] = $tv;
      $tv = explode('=',$tseq[$ci],2);
      $tseq[$ci] = ' ' . $tv[1] . ' ';
    }
  }

  // variable list by pattern 
  if(is_string($varallowed)){ // by pattern
    $equation = implode(';',$seq);
    $varlist = array();
    for($ci=0;$ci<$nseq;$ci++){
      $ctseq = $tseq[$ci];
      //get the allowed variables in this iteration
      preg_match_all($varallowed,$ctseq,$tv); $varlist[] = $tv[0];
      //remove allowed variables in temporary equation
      $curpat = '/' . $vchars . $varallowed . $vchars . '/i';
      $tv = preg_replace($curpat,'$1 0 $3',$ctseq);
      while($tv!=$ctseq){$ctseq = $tv; $tv = preg_replace($curpat,'$1 0 $3',$ctseq);}
      //still variables inside the equation?
      if(preg_match('/[a-zA-Z_\x7f-\xff]/',' ' . $ctseq . ' '))  return(NULL);
      //update varallowed by the new variable name
      $varallowed = '(' . $newvar[$ci] . '|' . substr($varallowed,1);
    }

    //get the list of variables which have to be defined in advance
    $tv = array();
    for($ci=count($varlist)-1;$ci>0;$ci--){
      $tv = array_unique(array_merge($tv,$varlist[$ci]));
      while(!(($ck = array_search($newvar[$ci-1],$tv))===false)) unset($tv[$ck]);
    }
    $varlist = array_unique(array_merge($tv,$varlist[0]));
    //illegal varnames in varlist?
    $tv = preg_grep('/^' . $vmatch . '$/',$varlist);
    if(count($varlist)!=count($tv)) return(NULL);
    //add $ for the variable names (necessary for the eval at the end) 
    $curpat = '(' . implode('|',array_unique(array_merge($varlist,$newvar))) . ')'; 
    $curpat = '/' . $vcharsP . $curpat . $vchars . '/';
    $tz = preg_replace($curpat,'$1\$$2$3',' ' . $equation);
    while($tz!=$equation){$equation = $tz; $tz = preg_replace($curpat,'$1\$$2$3',$equation);}
    // create code for eval
    $prog = 'foreach(array(\'' . implode('\',\'',$varlist) . '\')'
      . ' as $ck) if(!is_numeric($$ck)) return(NULL); ' . $equation;
    return($prog);


    // Value List --------------------------------------------------
  } elseif(is_array($varallowed)){ 
    //illegal varnames in keys of varallowed?
    $ak = array_keys($varallowed);
    $tv = preg_grep('/^' . $vmatch . '$/',$ak);
    if(count($ak)!=count($tv)) return(NULL);

    //split into statements and loop through
    for($ci=0;$ci<$nseq;$ci++){
      $ctseq = $tseq[$ci];
      $cseq = $seq[$ci];
      //repalce variable by their values
      reset($varallowed);
      while(list($ak,$av)=each($varallowed)){
	if(!is_numeric($av)) return(NULL);
	$curpat = '/' . $vchars . $ak . $vchars . '/';
	$currepl = '${1}' . $av . '${2}';
	$tv = preg_replace($curpat,$currepl,$ctseq);
	while($tv!=$ctseq){$ctseq = $tv; $tv = preg_replace($curpat,$currepl,$ctseq);}
	$tv = preg_replace($curpat,$currepl,$cseq);
	while($tv!=$cseq){$cseq = $tv; $tv = preg_replace($curpat,$currepl,$cseq);
	}
      }
      //still variables inside the equation?
      if(preg_match('/[a-zA-Z_\x7f-\xff]/',' ' . $ctseq . ' '))  return(NULL);
      //evaluate the current term
      eval('$tv=' . $cseq . ';');
      if($ci==$nseq-1) return($tv); else $varallowed[$newvar[$ci]] = $tv;
    }


  } else retrun(NULL);

}

/*
 extrahiert Bereich in text und gibt beides zurück
 start/ende Zahl (position) oder text (strpos)
  border fals TRUE werden start/end im text belassen
 BspA: "Ein roter Ball", 4, 9 -> array("Ein  Ball","roter")
 BspB: "5 #farbe# Rosen", "#","#" -> array("5 ## Rosen","farbe")
 BspC: "5 <<farbe>> Rosen", "<<",">>", FALSE -> array("5  Rosen","farbe")
*/
function str_extract($txt,$start,$end,$border=TRUE){
  if(is_string($start)){
    $ps = strpos($txt,$start); $ls = strlen($start);
    $pe = strpos($txt,$end,$ps+$ls); $le = strlen($end);
    if($border) $rev = substr($txt,0,$ps+$ls) . substr($txt,$pe);
    else        $rev = substr($txt,0,$ps) . substr($txt,$pe+$le);

    if((!$ps and $ps!=0) or !$pe) {$start=0; $end=0;} else { $start = $ps+$ls; $end = $pe;}
  } else  $rev = substr($txt,0,$start) . substr($txt,$end);
  return(array($rev,substr($txt,$start,$end-$start)));
}


/* 
   search the largest equal part of strA and strB starting 
   dir: 0: starting at the left; 1: starting at the right
   ret: 0: returns length of the common part
        1: returns the common part
	2: returns the non common part of strA
	[string]: replaces the common part of strA with ret and returns it
 */
function str_like($strA,$strB,$dir=0,$ret){
  if($dir===1) { $strA = strrev($strA); $strB = strrev($strB); }
  $cp = 0; $la = strlen($strA); $lb = strlen($strB);
  while(substr($strA,$cp,1)===substr($strB,$cp,1) and $cp<$la and $cp<$lb) $cp++;
  if(is_string($ret)){
    $strA = ($dir==1?strrev($ret):$ret) . substr($strA,$cp);
    return($dir==1?strrev($strA):$strA);
  } else {
    switch($ret){
    case 0: return($cp); break;
    case 1: return($dir==1?strrev(substr($strA,0,$cp)):substr($strA,0,$cp)); break;
    case 2: return($dir==1?strrev(substr($strA,$cp)):substr($strA,$cp)); break;
    }
  }
}

/* 
   like implode but with pre/postfix for each element
 */
function implode_emb($arr,$prefix,$postfix){
  $re = $prefix . implode($postfix . $prefix,$arr) . $postfix;
  return($re);
}

/* 
   like implode but uses key too
 */
function implode_assoz($arr,$sep,$isep){
  $re = array();
  while(list($ak,$av)=each($arr)) $re[] = $ak . $isep . $av;
  return(implode($sep,$re));
}

/* 
   like implode_emb but uses the keys too ($sep)
 */
function implode_embassoz($arr,$prefix,$postfix,$sep='='){
  $re = '';
  while(list($ak,$av)=each($arr)) $re .= $prefix . $ak . $sep . $av . $postfix;
  return($re);
}



function microtime_float($mod=10000){
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + ((int)$sec % $mod));
}
?>
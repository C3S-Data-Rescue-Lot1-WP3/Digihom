<?php
  /*
   * mtc ausbauen!
   * search colors close to
   * move color to another
   * random for rgb/hsv/hsl
   * sortieren nach farbe
   * something like rot13?
   *
   *
   * Scale sufix: defines the scale of the components
   * D: Decimal [0,255]
   * F: Fraction [0,1]
   * P: Percent [0,100]
   * C: Circle [0,360]
   * S: [0,240]
   */

class opc_colors implements countable, ArrayAccess, Iterator {
  protected $mode = NULL;
  protected $round = 0;
  protected $details = NULL;
  protected $values = array();
  protected $n = 0;
  protected $i = 0;

  protected $_vars_ro = array('mode','cmodes','round',
			      'values','n','i',
			      'cnv','scvale','cnv_cache');

  static $table = NULL;
  protected $tables = array();

  // list of modes
  protected $cmodes = array('hex'=>0,
			    'dec'=>1,
			    'bw'=>2,
			    'rgb'=>100,
			    'hsv'=>100,
			    'hsl'=>100,
			    'yuv'=>4,
			    //'xyz'=>100,
			    //'lab'=>4,
			    'nam'=>5,
			    );

  /* list of convert function 
   * source => list of targets
   * mehtod source__target must be definied
   */
  protected $cnv = array('hex'=>array('rgbD','dec'),
			 'dec'=>array('hex','rgbD'),
			 'rgbD'=>array('hex','dec'),
			 'rgbF'=>array('hsvCF','hslCF','yuv','xyzF'),
			 'hsvCF'=>array('rgbF'),
			 'hslCF'=>array('rgbF'),
			 'bw'=>array('rgbF'),
			 'yuv'=>array('rgbF'),
			 'xyzF'=>array('rgbF','lab'),
			 'lab'=>array('xyzF'),
			 'nam'=>array('hex'),
			 );

  /* list of scale function 
   * source => list of targets
   * mehtod scale__source_target must be definied
   */
  protected $scale = array('D'=>array('F','P','X'),
			   'F'=>array('D','P','CF','S'),
			   'P'=>array('D','F'),
			   'X'=>array('D'),
			   'S'=>array('F'),
			   'CF'=>array('CP','F'),
			   'CP'=>array('CF'),
			   );

  // cache of n-step conversions
  protected $cnv_cache = array();
  // max number of steps to find way from source to targt
  protected $cnv_nstep = 7;
  

  // pattern for automatioc recognition (pat=>mode)
  protected $mtc_pat = array('{^#[0-9a-fA-F]{6}[xhXH;]?$}'=>'hex',
			     '{^[012]?[0-9]?[0-9] [012]?[0-9]?[0-9] [012]?[0-9]?[0-9]$}'=>'rgbD',
			     '{^[0-3]?[0-9]?[0-9]\/[01]?[0-9]?[0-9]\/[01]?[0-9]?[0-9]$}'=>'hsvCP',
			     '{^[0-3]?[0-9]?[0-9]-[01]?[0-9]?[0-9]-[01]?[0-9]?[0-9]$}'=>'hslCP',
			     '{^\d+$}'=>'dec',
			     '{^\+?[01]?\.\d+$}'=>'bw',
			  );

  // how to prepare an input given by a string
  protected $prep = array('D'=>'prep_3i',
			  'S'=>'prep_3i',
			  'F'=>'prep_3f',
			  'P'=>'prep_3f',
			  'CP'=>'prep_3f',
			  'hex'=>'prep_hex',
			  );


  public $fc = array(5,9,2);
  function __construct($mode='hex'){
    if(!is_object(self::$table))
      self::$table = new opc_color_table();
    if($this->mode_set($mode)>0)
      $this->mode_set('hex');
  }
  /* ================================================================================
     Magic
     ================================================================================ */
  function __get($key){
    if($key=='value')  return def($this->values,$this->i);
    if(in_array($key,$this->_vars_ro)) return $this->$key;
    switch($key){
    case 'scale_list': return $this->scale;
    case 'mode_list':  return $this->cmodes;
    }
    return trg_ret("No read access for '$key' in " . __CLASS__,NULL);
    
  }

  function __set($key,$val){
    if($key=='mode') return $this->mode_set($val);
    if($key=='round') return $this->round_set($val);
    return trg_ret("No write access for '$key' in " . __CLASS__,NULL);
  }


  // Countable ------------------------------------------------------------------------
  function count(){ return $this->n;}

  // ArrayAccess ----------------------------------------------------------------------
  function offsetGet($key){ return $this->values[$key];}
  function offsetSet($key,$value){ return $this->set($key,$value);}
  function offsetExists($key){ return array_key_exists($key,$this->values);}
  function offsetUnset($key){ 
    unset($this->values[$key]);
    $this->values = array_values($this->values);
    $this->n--;
  }

  // Iterator -------------------------------------------------------------------------
  function current(){return $this->geti($this->i);}
  function key(){return $this->keyi($this->i);}
  function next(){$this->i++;}
  function rewind() {$this->i = 0;}
  function valid() {return $this->i>=0 and $this->i<$this->n;}



  function round_set($mode){
    if(!is_int($mode) or $mode<0 or $mode>4) return 1;
    $this->round = $mode;
    return 0;
  }

  function mode_set($mode){
    // already current mode 
    if($mode==$this->mode) return -1;

    // test
    $m = preg_replace('{[A-Z]*}','',$mode);
    $s = $m==$mode?'':substr($mode,strlen($m));
    if(!isset($this->cmodes[$m])) return 1;
    switch($this->cmodes[$m]){
    case 100:
      if(!isset($this->scale[$s])) return 2;
      break;
    default:
      if($s!=='') return 3;
    }
    
    // change values
    $omode = $this->mode;
    $this->mode = $mode;
    foreach($this->values as $key=>$val)
      $this->values[$key] = $this->cnv($val,$omode,$mode);

    return 0;
  }


  function geti($i,$mode=NULL){
    $col = $this->values[$this->keyi($i)];
    if(is_null($mode)) return $col;
    return $this->convert($col,$this->mode,$mode);
  }

  function keyi($i){
    return def(array_keys($this->values),$i);
  }

  function add($val,$mode=NULL){
    $val = $this->convert($val,$mode,$this->mode);
    if(is_null($val)) return 1;
    $this->values[] = $val;
    $this->n = count($this->values);
    return 0;
  }

  function set($key,$val,$mode=NULL){
    $val = $this->convert($val,$mode,$this->mode);
    if(is_null($val)) return;
    if(is_null($key)) $this->values[] = $val;
    else              $this->values[$key] = $val;
    $this->n = count($this->values);
  }


  /* ================================================================================
     Convertion
     ================================================================================ */
  function convert($val,$src=NULL,$tar=NULL){
    if(is_null($tar)) $tar = $this->mode;

    // which source is used
    if(is_null($src) or $src=='auto' or $src=='automatic') {
      $src = $this->src_est($val);
      if(is_null($src)){
	$tmp = self::$table->nam__hex($val);
	if(is_null($tmp))
	  return trg_ret('Color not recogniced: ' . $val,NULL);
	$val = $tmp;
	$src = 'hex';
      }
    }

    // prepare source value (eg string->array, clean text)
    $scale = preg_replace('{[^A-Z]}','',$src);
    if(is_string($val)){
      if(in_array($src,$this->tables)){
	$val = self::$table->id__hex($src . ':' . $this->prep_tab($val,$src));
	$src = 'hex';
      } else if(isset($this->prep[$scale])){
	$mth = $this->prep[$scale];
	$val = $this->$mth($val,$src);
      } else if(isset($this->prep[$src])){
	$mth = $this->prep[$src];
	$val = $this->$mth($val,$src);
      }
    }

    // conversion necessary
    if($src==$tar) return $val;
    $res = $this->cnv($val,$src,$tar);
    if(is_null($res))
      return trg_ret("Unkown color conversion $src->$tar",NULL);
    else 
      return $res;
  }


  // internal conversion
  protected function cnv($val,$src,$tar){
    // 1-step conversion
    $key = $src . '__' . $tar;
    if(method_exists($this,$key))
      return $this->$key($val);

    if(!isset($this->cnv_cache[$key])) 
      $this->cnv_cache[$key] = $this->cnv_steps($src,$tar);
    if(empty($this->cnv_cache[$key])) return NULL;
    foreach($this->cnv_cache[$key] as $cs) 
      $val = $this->$cs($val);
    return $val;
  }

  /* tries to find out how to convert from src to tar
   * tries brut forward all combinations
   * from cnv and scale with up to cnv_nstep steps
   * returns a array of method names or NULL
   */
  function cnv_steps($src,$tar){
    $chains = array($src=>array());
    $done = array($src);
    $i = 0;
    for($i=0;$i<$this->cnv_nstep;$i++){
      $nchains = array();
      foreach($chains as $cur=>$ck){
	foreach(def($this->cnv,$cur,array()) as $cnxt){
	  if(in_array($cnxt,$done)) continue;
	  $cf = $cur . '__' . $cnxt;
	  $ck[$i] = $cf;
	  if($cnxt==$tar) return $ck;
	  $nchains[$cnxt] = $ck;
	  $done[] = $cnxt;
	}
	
	$s = preg_replace('{[^A-Z]}','',$cur);
	$c = preg_replace('{[A-Z]}','',$cur);
	foreach(def($this->scale,$s,array()) as $ns){
	  $cnxt = $c . $ns;
	  if(in_array($cnxt,$done)) continue;
	  $cf = 'scale__' . $s . '_' . $ns;
	  $ck[$i] = $cf;
	  if($cnxt==$tar) return $ck;
	  $nchains[$cnxt] = $ck;
	  $done[] = $cnxt;
	}
      }
      $chains = $nchains;
    }
    return NULL;
  }

  function scale__D_F($val){
    return array($val[0]/255,$val[1]/255,$val[2]/255);
  }

  function scale__F_D($val){
    return array($this->rnd($val[0],255),
		 $this->rnd($val[1],255),
		 $this->rnd($val[2],255));
  }

  function scale__S_F($val){
    return array($val[0]/239,$val[1]/239,$val[2]/239);
  }

  function scale__F_S($val){
    return array($this->rnd($val[0],239),
		 $this->rnd($val[1],239),
		 $this->rnd($val[2],239));
  }


  function scale__P_D($val){
    return array($this->rnd($val[0]/100),
		 $this->rnd($val[1]/100),
		 $this->rnd($val[2]/100));
  }

  function scale__D_P($val){
    return array($val[0]/2.55,$val[1]/2.55,$val[2]/2.55);
  }

  function scale__D_X($val){
    return array(sprintf('%02X',$val[0]),
		 sprintf('%02X',$val[1]),
		 sprintf('%02X',$val[2]));
  }

  function scale__X_D($val){
    return array(hexdec($val[0]),
		 hexdec($val[1]),
		 hexdec($val[2]));
  }

  function scale__P_F($val){
    return array($val[0]/100,$val[1]/100,$val[2]/100);
  }

  function scale__F_P($val){
    return array($val[0]*100,$val[1]*100,$val[2]*100);
  }

  function scale__CF_CP($val){
    return array($val[0],$val[1]*100,$val[2]*100);
  }

  function scale__CP_CF($val){
    return array($val[0],$val[1]/100,$val[2]/100);
  }

  function scale__CF_F($val){
    return array($val[0]/360,$val[1],$val[2]);
  }

  function scale__F_CF($val){
    return array($val[0]*360,$val[1],$val[2]);
  }


  function nam__hex($val){
    return self::$table->nam__hex($val);
  }

  function hex__rgbD($val){
    $val = hexdec($val);
    $res = array();
    for($i=0;$i<3;$i++){
      array_unshift($res,$val % 256);
      $val = ($val-$res[0])/256;
    }
    return $res;
  }

  function hex__dec($val){
    return hexdec(substr($val,5,2)
		  . substr($val,3,2)
		  . substr($val,1,2));
  }

  function dec__rgbD($val){
    $res = array();
    for($i=0;$i<3;$i++){
      $res[$i] = $val % 256;
      $val = ($val-$res[$i])/256;
    }
    return $res;
  }

  function dec__hex($val){
    $res = '#';
    for($i=0;$i<3;$i++){
      $tmp = $val % 256;
      $val = ($val-$tmp)/256;
      $res .= sprintf('%02X',$tmp);
    }
    return $res;
  }

  
  function rgbD__dec($val){
    return $val[0]+256*$val[1]+256*256*$val[2];
  }


  function rgbD__hex($val){
    return sprintf('#%02X%02X%02X',$val[0],$val[1],$val[2]);
  }

  function rgbF__hslCF($val){
    list($r,$g,$b) = $val; 
    $max = max($r,$g,$b);
    $min = min($r,$g,$b);
    $d = $max - $min;
    $l = ($max+$min)/2;
    $res = array();
    if($max==$min)    $h = 0;
    else if($max==$r) $h = 60*(0+($g-$b)/$d);
    else if($max==$g) $h = 60*(2+($b-$r)/$d);
    else if($max==$b) $h = 60*(4+($r-$g)/$d);
    if($h<0)          $h += 360;
    if($max==$min)    $s = 0;
    else if($l<=0.5)  $s = $d/2/$l;
    else              $s = $d/2/(1-$l);
    return array($h,$s,$l);
  }

  function hslCF__rgbF($val){
    list($h,$s,$l) = $val;
    if($l<0.5) $q = $l*(1+$s);
    else       $q = $l+$s-$l*$s;
    $p = 2*$l-$q;
    $hk = $h/360; 
    $r = $hk+1/3; if($r<0) $r+=1; else if($r>1) $r-=1;
    $g = $hk;     if($g<0) $g+=1; else if($g>1) $g-=1;
    $b = $hk-1/3; if($b<0) $b+=1; else if($b>1) $b-=1;
    
    return array($r<1/6?($p+6*$r*($q-$p)):($r<=1/2?$q:($r<2/3?($p+(2/3-$r)*6*($q-$p)):$p)),
		 $g<1/6?($p+6*$g*($q-$p)):($g<=1/2?$q:($g<2/3?($p+(2/3-$g)*6*($q-$p)):$p)),
		 $b<1/6?($p+6*$b*($q-$p)):($b<=1/2?$q:($b<2/3?($p+(2/3-$b)*6*($q-$p)):$p)));
  }
  
  function rgbF__hsvCF($val){
    list($r,$g,$b) = $val; 
    $max = max($r,$g,$b);
    $min = min($r,$g,$b);
    $d = $max - $min;
    if($max==$min)    $h = 0;
    else if($max==$r) $h = 60*(0+($g-$b)/$d);
    else if($max==$g) $h = 60*(2+($b-$r)/$d);
    else if($max==$b) $h = 60*(4+($r-$g)/$d);
    if($h<0)          $h += 360;
    if($max==0)       $s = 0;
    else              $s = $d/$max;
    return array($h,$s,$max);
  }

  function hsvCF__rgbF($val){
    list($h,$s,$v) = $val;
    $i = floor($h/60);
    $f =  $h/60 - $i;
    $p = $v*(1-$s);
    $q = $v*(1-$s*$f);
    $t = $v*(1-$s*(1-$f));
    switch($i){
    case 0: return array($v,$t,$p);
    case 1: return array($q,$v,$p);
    case 2: return array($p,$v,$t);
    case 3: return array($p,$q,$v);
    case 4: return array($t,$p,$v);
    case 5: return array($v,$p,$q);
    case 6: return array($v,$t,$p);
    }
  }

  function rgbF__yuv($val){
    list($r,$g,$b) = $val; 
    $y = 0.299*$r+0.587*$g+0.114*$b;
    $u = ($b-$y)*0.493;
    $v = ($r-$y)*0.877;
    return array($y,$u,$v);
  }

  function yuv__rgbF($val){
    list($y,$u,$v) = $val; 
    $b = $y + $u/0.493;
    $r = $y + $v/0.877;
    $g = 1.7*$y - 0.509*$r - 0.194*$b;
    return array(min(1,max(0,$r)),min(1,max(0,$g)),min(1,max(0,$b)));
  }

  function rgbF__xyzF($val){
    list($r,$g,$b) = $val; 
    $x = 0.4124564*$r+0.3575761*$g+0.1804375*$n;
    $y = 0.2126729*$r+0.7151522*$g+0.0721750*$b;
    $z = 0.0193339*$r+0.1191920*$g+0.9503041*$b;
    return array($x,$y,$z);
  }

  function xyzF__rgbF($val){
    list($r,$g,$b) = $val; 
    $x = 3.2410*$r-1.5374*$g-0.4986*$n;
    $y = -0.9692*$r+1.876*$g+0.0416*$b;
    $z = 0.0556*$r-0.204*$g+1.057*$b;
    return array($x,$y,$z);
  }

  function xyzF__lab($val){
    list($x,$y,$z) = $val; 
    $xn = 95;
    $yn = 100;
    $zn = 109;
    $l = 116*$this->r3($x,$xn) - 16;
    $a = 500*($this->r3($x,$xn)-$this->r3($y,$yn));
    $b = 200*($this->r3($y,$yn)-$this->r3($z,$zn));
    return array($l,$a,$b);
  }

  function r3($a,$an){
    if($a/$an<216/24389)
      return (24389/27*$a/$an+16)/116;
    else
      return pow($a/$an,1/3);
  }

  function bw__rgbF($val){
    return array($val,$val,$val);
  }

  function src_est($val){
    if(is_float($val)) return 'bw';
    if(is_int($val))   return 'dec';
    if(is_array($val)) 
      return $this->src_est_arr($val);
    if(is_numeric($val) or is_string($val))
      return $this->src_est_str($val);
    return $this->src_est_oth($val);
  }

  function src_est_num($val){
    return NULL;
  }

  function src_est_str($val){
    foreach($this->mtc_pat as $key=>$src)
      if(preg_match($key,$val)) return $src;
    return self::$table->exists($val);
  }

  function src_est_arr($val){
    return NULL;
  }

  function src_est_oth($val){
    return NULL;
  }

  function prep_tab($val,$src){
    $pat = array_keys($this->mtc_pat,$src,TRUE);
    return preg_replace($pat,'$1',$val);
  }

  function prep_3i($val){
    $val = explode(' ',preg_replace('{[-\/\\]]}',' ',$val));
    $val[0] = (int)$val[0];
    $val[1] = (int)$val[1];
    $val[2] = (int)$val[2];
    return $val;
  }

  function prep_3f($val){
    $val = explode(' ',preg_replace('{[-\/\\]]}',' ',$val));
    $val[0] = (float)$val[0];
    $val[1] = (float)$val[1];
    $val[2] = (float)$val[2];
    return $val;
  }

  function prep_hex($val){
    return strtoupper(preg_replace('{^.*([0-9a-fA-F]{6}).*$}','#$1',$val));
  }

  /* ================================================================================
     Modification
     ================================================================================ */

  // fills values with n equallay distributed values between 0 and 1 (including)
  function fill($n){
    $this->values = range(0,1,1/($n-1));
    $this->n = $n;
    $this->i = 0;
    $this->mode = 'bw';
  }

  // rescales values inbetween min and max
  function zoom($min=0,$max=1){
    if($this->n==0) return -2;
    $cmin = min($this->values);
    $cmax = max($this->values);
    if($min==$cmin and $max==$cmax) return -1;
    $f = ($max-$min)/($cmax-$cmin);
    for($i=0;$i<$this->n;$i++)
      $this->values[$i] = $min+$f*($this->values[$i]-$cmin);
    return 0;
  }

  // values below min / above max are reset to min/max
  function cut($min=0,$max=1){
    if($this->n==0) return -2;
    for($i=0;$i<$this->n;$i++)
      if($this->values[$i]<$min) 
	$this->values[$i] = $min;
      else if($this->values[$i]>$max) 
	$this->values[$i] = $max;
    return 0;
  }

  function shuffle(){
    if($this->n==0) return -2;
    shuffle($this->values);
    return 0;
  }

  function cs_sample($n,$mode,$uniq=TRUE,$cs=NULL){
    if(!is_int($n) or $n<1) return NULL;
    if(is_null($cs)) 
      $cs = preg_replace('{[A-Z]}','',$this->mode);
    $mth = $cs . '_sample';
    if(!method_exists($this,$mth)) return NULL;
    $res = $this->$mth($n,$uniq,$src);
    if($src!=$mode) 
      foreach($res as $ck=>$cv)
	$res[$ck] = $this->cnv($cv,$src,$mode);
    return $n==1?array_shift($res):$res;
  }

  function hex_sample($n,$uniq,&$src){
    $src = 'hex';
    $res = array();
    while(count($res)<$n){
      $tmp = sprintf('#%02X%02X%02X',rand(0,255),rand(0,255),rand(0,255));
      if(!$uniq or !in_array($tmp,$res)) $res[] = $tmp;
    }
    return $res;
  }

  function rgb_sample($n,$uniq,&$src){
    $src = 'rgbD';
    $res = array();
    while(count($res)<$n){
      $tmp = array(rand(0,255),rand(0,255),rand(0,255));
      if(!$uniq or !in_array($tmp,$res)) $res[] = $tmp;
    }
    return $res;
  }

  function hsv_sample($n,$uniq,&$src){
    $src = 'hsvCP';
    $res = array();
    while(count($res)<$n){
      $tmp = array(rand(0,359),rand(0,100),rand(0,100));
      if(!$uniq or !in_array($tmp,$res)) $res[] = $tmp;
    }
    return $res;
  }

  function hsl_sample($n,$uniq,&$src){
    $src = 'hslCP';
    $res = array();
    while(count($res)<$n){
      $tmp = array(rand(0,359),rand(0,100),rand(0,100));
      if(!$uniq or !in_array($tmp,$res)) $res[] = $tmp;
    }
    return $res;
  }

  function bw_sample($n,$uniq,&$src){
    $src = 'bw';
    $res = array();
    while(count($res)<$n){
      $tmp = rand(0,255)/255;
      if(!$uniq or !in_array($tmp,$res)) $res[] = $tmp;
    }
    return $res;
  }

  /* ================================================================================
     Tables
     ================================================================================ */
  function table_get($key,$what=0){
    return self::$table->gett($key,$what);
  }

  /* add a table */
  function table_add($key,$table,$pat=NULL){
    $res = self::$table->table_add($key,$table);
    if($res==0){
      $this->tables[] = $key;
      if(!is_null($pat)) $this->mtc_pat[$pat] = $key;
    }
    return $res;
  }

  function table_sample($n,$what,$uniq=TRUE,$tab=NULL){
    return self::$table->sample($n,$what,$uniq,$tab);
  }

  function hex2ids($hex){
    return self::$table->hex2ids($hex);
  }

  function hex2names($hex){
    return self::$table->hex2names($hex);
  }

  // num is a [0,1] value
  function rnd($num,$max){
    switch($this->round){
    case 0: return (int)floor($num*($max+0.999999999));
    case 1: return (int)round($num*$max,0);
    case 2: return (int)floor($num*$max);
    case 3: return (int)ceil($num*$max);
    case 4: return $num*$max;
    }
  }


  function fc($col,$mode=NULL){
    $rgb = $this->convert($col,$mode,'rgbD');
    if(is_null($rgb)) return NULL;
    $sum = ($this->fc[0]*$rgb[0]+$this->fc[1]*$rgb[1]+$this->fc[2]*$rgb[2])/array_sum($this->fc);
    return $sum<=128?'white':'black';
  }

  function sprintf($format,$values){
    if(is_array($values))
      array_unshift($values,$format);
    else
      $values = array($format,$values);
    return call_user_func_array('sprintf',$values);
  }
  }
  
class opc_color_table {
  protected $tables = array();
  protected $tab = array();
  protected $nam = array();
  protected $hex = array();
  protected $ids = array();

  function table_add($key,$table){
    $nam = array();
    $hex = array();
    $ids = array();

    $n = 0;
    foreach($table as $id=>$color){
      if(is_array($color)){
	$ids[] = $key . ':' . defnz($color,'id',++$n);
	$nam[] = strtolower(def($color,'name',NULL));
	$hex[] = strtoupper(def($color,'hex',NULL));
      } else if(is_numeric($id)){
	$ids[] = $key . ':' . $id;
	$nam[] = NULL;
	$hex[] = strtoupper($color);
      } else {
	$ids[] = $key . ':' . ++$n;
	$nam[] = strtolower($id);
	$hex[] = strtoupper($color);
      }
    }
    $this->tab = array_merge($this->tab,array_fill(0,$n,$key));
    $this->nam = array_merge($this->nam,$nam);
    $this->hex = array_merge($this->hex,$hex);
    $this->ids = array_merge($this->ids,$ids);
    return 0;
  }

  function gett($key,$what=0){
    $res = array();
    $at = array_keys($this->tab,$key,TRUE);
    if(empty($at)) return array();
    $n = strlen($key)+1;
    switch($what){
    case 0: 
      foreach($at as $ci)
	$res[substr($this->ids[$ci],$n)] = $this->hex[$ci];
      return $res;
    case 1: 
      foreach($at as $ci)
	$res[substr($this->ids[$ci],$n)] = $this->nam[$ci];
      return $res;
    case 2: 
      foreach($at as $ci)
	$res[substr($this->ids[$ci],$n)] = array($this->nam[$ci],$this->hex[$ci]);
      return $res;
    }
    return NULL;
  }

  function geth($val){
    $tmp = array_keys($this->ids,$val,TRUE);
    if(count($tmp)>0) return $this->hex[array_shift($tmp)];
    $tmp = array_keys($this->nam,$val,TRUE);
    if(count($tmp)>0) return $this->hex[array_shift($tmp)];
    return NULL;
  }

  function exists($val){
    if(in_array($val,$this->ids)) return 'id';
    if(in_array($val,$this->nam)) return 'nam';
    if(in_array($val,$this->hex)) return 'hex';
    return NULL;    
  }

  function sample($n,$what,$uniq=TRUE,$tab=NULL){
    if(!is_int($n) or $n<1) return NULL;
    if(is_null($tab))
      $keys = array_keys($this->tab);
    else
      $keys = array_keys($this->tab,$tab,TRUE);
    if($what=='name')
      $keys = array_values(array_intersect(array_keys(array_filter($this->nam)),$keys));
    $m = count($keys);
    if($m==0) return $n==1?NULL:array();
    if($n==1){
      switch($what){
      case 'id': return $this->ids[$keys[rand(0,$m-1)]];
      case 'hex': return $this->hex[$keys[rand(0,$m-1)]];
      case 'name': return $this->nam[$keys[rand(0,$m-1)]];
      }
      return NULL;
    } 
    if($uniq){
      shuffle($keys);
      $tmp = array_slice($keys,0,min($n,$m));
    } else {
      $tmp = array();
      for($i=0;$i<$n;$i++) $tmp[] = $keys[rand(0,$m-1)];
    }
    $res = array();
    switch($what){
    case 'id': 
      foreach($tmp as $i) $res[] = $this->ids[$i];
      break;
    case 'hex':
      foreach($tmp as $i) $res[] = $this->hex[$i];
      break;
    case 'name': 
      foreach($tmp as $i) $res[] = $this->nam[$i];
      break;
    }
    return $res;
  }

  function id__hex($val){
    $tmp = array_keys($this->ids,strtolower($val),TRUE);
    if(empty($tmp)) return NULL;
    return $this->hex[array_shift($tmp)];
  }

  function nam__hex($val){
    $tmp = array_keys($this->nam,$val,TRUE);
    if(empty($tmp)) return NULL;
    return $this->hex[array_shift($tmp)];
  }

  function hex2ids($val){
    $tmp = array_keys($this->hex,strtoupper($val),TRUE);
    $res = array();
    foreach($tmp as $ck) $res[] = $this->ids[$ck];
    return $res;
  }

  function hex2names($val){
    $tmp = array_keys($this->hex,strtoupper($val),TRUE);
    $res = array();
    foreach($tmp as $ck) $res[] = $this->nam[$ck];
    return array_filter($res);
  }

}

?>
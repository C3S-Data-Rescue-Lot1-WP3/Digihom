<?php
  /*
   
   idea: funktionen stellen ein (geschachteltes) objekt zusammen, dass am schluss in html umgewandelt wird (oder was/wie auch immer)


    bug: layout 'only' retuns the wrong things (by an array)
    bug: qa funzt gar nicht mehr; vorher: veraendert beispielsweise link auf ein ht
    bug: qq(xml) (aus opc_fxml) -> funny

    qd: sends (optional) css for layout
    qa: auch box rundherum

    nyc: ext add v(verbose) what is really the asked effect? use different raw_X in nested?

    string: detect encoding: pure ascii, iso-8859-1, utf-8, other

    raw better

    ht2-str ausbauen: nur ein ausschnitt (alles unter ...)
                      wer benutz key xxx (al prv, nxt, par, fcl, lcl, incl!)

    conditional output (qi/qv; falls $Q existiert oder so)

    extend strings: charset (utf8 multibyte)

    extend object output: more features and more classes

    more testcase, eg strings objects

    extend counters


  hints:
    %-X-% -> ersetzt durch self::txt_LAYOUT

    q_* -> use emb in the end, result in $this->res
    r_* -> do not use emb, result returned 
    raw_* -> do not use emb

    q1  Typ
    q2  Typ + T/F (B) | Signum (f/i/n) | Size (A/S) | Classname | Ressourcename
    q3  Short1 (may be complet forsmall items)
    q4  Short2 (may be complet forsmall items)
    q5+ complete for scalars/Array (probably diferent layout)
        objects: more and more details (using they own sub-class)
   additional arguments
     target (t): sink (s), echo (e), raw, return
     exist (E): Name of a variable in GLOBALS. IF it not exist (or is ~FALSE)  no output at all
     title:
     col: colorshema
  */
class opc_debug {
  static protected $cnt = array(''=>0);
  static protected $sets = array();

  static $def_v = 7;
  static $def_cls = 'debug';


  static $txt_html = array('s1'=>'&nbsp;',
			   's2'=>' &bull;&nbsp;',
			   's3'=>'&emsp;',
			   's4'=>' &emsp;|&emsp;',
			   'sk1'=>':&nbsp;',
			   'sn'=>'<br/>');

  static $switches = array('hide'=>FALSE);

  static $txt_raw = array('s1'=>' ',
			  's2'=>' * ',
			  's3'=>'   ',
			  's4'=>' | ',
			  'sk1'=>': ',
			  'sn'=>"\n");
 
  static public $def = array('*-ext-embed'=>'div',
			     '*-int-v'=>1,
			     '*-fkt'=>'q_def',
			     '*-typ'=>'single',
			     '*-target'=>'sink',
			     '*-tcls'=>0,

			     'q1-ext-embed'=>'span',
			     'q1-fkt'=>'q_type',
			     
			     'q2-ext-embed'=>'span',
			     'q2-int-v'=>2,
			     'q2-fkt'=>'q_type',
			     
			     'q3-ext-embed'=>'span',
			     'q3-fkt'=>'q_short',
			     
			     'q4-ext-embed'=>'span',
			     'q4-int-v'=>2,
			     'q4-fkt'=>'q_short',

			     'q5-ext-embed'=>'span',
			     
			     'q6-int-v'=>2,
			     'q7-int-v'=>3,
			     'q8-int-v'=>4,
			     'q9-int-v'=>5,

			     'qq-ext-embed'=>'div',

			     'qw-typ'=>'multi',

			     'qp-typ'=>'parts',

			     'qa-typ'=>'call',

			     'qd-target'=>'die',

			     'qe-target'=>'echo',

			     'qh-target'=>'rem',
			     'qh-ext-layout'=>'raw',

			     'qr-target'=>'return',

			     'qc-typ'=>'info',
			     'qc-inf'=>'td',
			     'qc-ext-embed'=>'span',

			     'ql-typ'=>'info',
			     'ql-inf'=>'l',
			     'ql-ext-embed'=>'span',

			     'qk-typ'=>'info',
			     'qk-inf'=>'L',

			     'qm-typ'=>'msg',
			     'qm-ext-embed'=>'span',
			     'qm-inf'=>'%1',
			     'qx-typ'=>'msg',
			     'qx-inf'=>'Todo: %1 [open point to code]',
			     'qx-tcls'=>'todo',
			     'qy-typ'=>'msg',
			     'qy-inf'=>'nyc: %1 [not yet coded]',
			     'qy-tcls'=>'nyc',
			     'qz-typ'=>'msg',
			     'qz-inf'=>'snh: %1 [should not happen]',
			     'qz-tcls'=>'snh',

			     'qs-typ'=>'switch',
			     );
		      

  /* ================================================================================
     Static part (happens primary in the this class (and not in the child-classes)
     ================================================================================ */
  static public $colors = array('std'=>array('b1'=>'#FFDA8C','b2'=>'#E8C77F','b3'=>'#FFEAC0',
					     'r1'=>'#8D3321','r2'=>'#B60025','r3'=>'#000000',
					     'f1'=>'#000000','f2'=>'#888888','f3'=>'#912F1E',
					     'f4'=>'#483D8B',
					     'o1'=>'#00FF00','o2'=>'#00FFFF','o3'=>'#FFFF00',
					     'ftodo'=>'black','btodo'=>'#7fff00',
					     'fnyc'=>'white','bnyc'=>'#228b22',
					     'fsnh'=>'#FFd700','bsnh'=>'#ff0000',
					     
					     ),
				'_bc'=>array('','maroon','red','green','lime',
					     'olive','yellow','navy','blue',
					     'purple','fuchsia','teal','aqua',
					     'black','gray','silver','white',
					     ),
				);

  static $obj = NULL;
  static $args = NULL;
  static $int = NULL;
  static $ext = NULL;


  protected static $ext_short = array('t'=>'target',
				      'c'=>'style-color','col'=>'style-color',
				      'v'=>'verbose', 
				      'l'=>'layout','e'=>'embed','i'=>'inf',
				      );
  protected static $int_short = array('b'=>'base',
				      'c'=>'charset',
				      'm'=>'method');

  static $t_init = NULL;
  static $t_last = NULL;
  static $m_last = NULL;
  static $switch = array('hide'=>FALSE);

  static $dbt = NULL;
  static protected $level = 0;

  /** pointer to an object wchich has a method add(string), eg: ht/ht2 */
  static $sink = NULL;
  static $sink_ptr = 'main';
  static $class = 'debug';
  static $colscheme = 'std';

  /** gets the type of $obj, deep: go inside by obj/res, $num: use is_numeric */
  static function get_type($obj,$deep=FALSE,$num=FALSE){
    if(is_null($obj))     return 'null';
    if(is_string($obj) and $num) return is_numeric($obj)?'numeric':'string';
    if(is_string($obj))   return 'string';
    if(is_int($obj))      return 'integer';
    if(is_float($obj))    return 'float';
    if(is_bool($obj))     return 'bool';
    if(is_array($obj))    return 'array';
    if(is_object($obj))   return $deep?get_class($obj):'object';
    if(is_resource($obj)) return $deep?get_resource_type($obj):'resource';
    qk();
    var_dump($obj);
    trigger_error('what happens here! Unkown object type',E_USER_ERROR);
  }

  static function find_class($obj,$cls=NULL,$create=FALSE){
    if(is_null($cls)) $cls = self::get_type($obj,FALSE,TRUE);
    $pre = 'opc_debug';
    if($cls=='object'){
      $cc = get_class($obj);
      do{
	$cls = $pre . '_obj_' . $cc;
	if(class_exists($cls)) break;
	$cc = get_parent_class($cc);
	if($cc===FALSE) { $cls = $pre . '_obj'; break;}
      } while(TRUE);
    } else {
      $cls = $pre . '_' . $cls;
      if(!class_exists($cls)) $cls = $pre;
    }
    if($create===FALSE) return $cls;
    if(is_array($create)) return new $cls($obj,$create);
    return new $cls($obj,array());
  }

  static protected function maxlev($res,$pat){
    $res = preg_split($pat,$res,-1,PREG_SPLIT_DELIM_CAPTURE);
    return max(array_map(create_function('$x','return is_numeric($x)?(int)$x:0;'),$res));
  }

  static function raster($min,$max,$steps,$maxstep=0,$minstep=0){
    $dir = $max>$min?1:-1;
    $step = abs($max-$min)/$steps;
    if($step>$maxstep and $maxstep>0)      $step = $maxstep;
    else if($step<$minstep and $minstep>0) $step = $minstep;
    $res = array();
    $cur = $min;
    for($i=0;$i<=$steps;$i++){
      $res[$i] = $cur;
      $cur += $dir*$step;
    }
    return $res;
  }

  static function layout_html($res,$otag){
    $parts = preg_split('#{%(-|[^}]*)}#',$res,-1,PREG_SPLIT_DELIM_CAPTURE);
    $n=0;
    while(count($parts)>2){
      $i=1; while(ord($parts[$i])!=45) $i+=2;
      $txt = $parts[$i-1];
      if($i<2) break;
      $tmp = preg_split('#(\w)=#',$parts[$i-2],-1,PREG_SPLIT_DELIM_CAPTURE);
      $open = array();
      for($j=1;$j<count($tmp);$j+=2) $open[$tmp[$j]] = $tmp[$j+1];
      if(ord($open['t'])==45){
	switch($open['t']){
	case '-e': 
	  $tag = 'span'; 
	  $open['c'] = def($open,'c',self::$def_cls);
	  break;
	case '-p':  case '-s':
	  $tag = 'span'; 
	  break;

	}
      } else $tag = $open['t'];
      $cls = isset($open['c'])?" class='$open[c]'":'';
      $sty = isset($open['s'])?" style='$open[s]'":'';
      $tip = isset($open['i'])?" title='$open[i]'":'';
      switch($tag){
      case 'S':
	if($otag==='div') $txt = '<hr/>';
	else if($otag==='span') $txt = '<span class="' . self::$def_cls . '_sep0' . '">&nbsp;</span>';
	else $txt = ' | ';
	break;
	
      default:
	$txt = "<$tag$cls$sty$tip>$txt</$tag>";
      }
      $cr = $parts[$i-3] . $txt . $parts[$i+1];
      array_splice($parts,$i-3,5,array($cr));
    }
    $res = $parts[0];
    foreach(self::$txt_html as $ck=>$cv) $res = str_replace("%-$ck-%",$cv,$res);
    return $res;
  }

  static function layout_pure($res,$otag){
    $res = self::layout_html($res,$otag);
    $res = preg_replace("# (style|class)='[^']*'#",'',$res);
    return trim($res);
  }

  static function layout_only($res){ return self::layout_raw($res);}

  static function layout_raw($res){
    $res = preg_replace('#{%(-|[^}]*)}#','',$res);
    foreach(self::$txt_raw as $ck=>$cv) $res = str_replace("%-$ck-%",$cv,$res);
    return trim($res);
  }

  /* ================================================================================
     q-functions
     ================================================================================*/

  function q_type($args){
    $mth = 'r_type' . def($args,'v',1);
    $this->res = $this->emb(array('typ'=>'[' . $this->$mth($args) . ']'));
  }

  function q_short($args){
    $mth = 'r_short' . def($args,'v',1);
    $mtht = 'r_type' . def($args,'v',1);
    $this->res = $this->emb(array('typ'=>'[' . $this->$mtht($args) . ']',
				  'val'=>$this->$mth($args)));
  }

  function q_def($args){
    $this->res = $this->emb(array('typ'=>'[' . $this->r_type2($args) . ']',
				  'val'=>$this->r_def($args)));
  }

  function q_int($int){
    switch(def($int,'method','e')){
    case 'p': $this->res = print_r($this->val,TRUE); break;
    case 's': $this->res = serialize($this->val); break;
    case 'd': case 'v':
      ob_start();
      var_dump($this->val); 
      $this->res = ob_get_clean();
      break;
    case 'e': 
    default:
      $this->res = var_export($this->val,TRUE); 
    }
    $this->typ = NULL; // not necessary
  }

  function q_raw($args,$v=NULL){
    if(is_null($v)) $v = def(self::$args,'v',4);
    $mth = 'raw_' . $v;
    $this->res = self::$mth($this->val,$v);
  }


  /* still used */
  static function q_msg($int,$ext,$title='Message',$cls='todo'){
    if(!empty($title)){
      $title = "<span title='" . strip_tags(self::get_line(0)) . "'>$title</span>";
      if(isset($ext['title'])) array_unshift($ext['title'],$title);
      else $ext['title'] = array($title);
    }
    $ext['class'] = self::$class . '_msg ' . self::$class . '_' . $cls;
    return array($ext,NULL);
  }

  /* still used */
  static function q_head($int,$ext=array()){
    return array($ext,NULL);
  }

  /** allows to show multiple elemnts in one
   * strings starting with '.-.' ares used as additional arguments for the main result (title, colors etc)
   * strings starting with '-.-' ares used to define the inside behaviour
   *  sep: deafult </hr>
   */

  /* still used */
  static function q_switch($args){
    foreach($args as $ca){
      switch($ca){
      case 'h': self::$switch['hide'] = FALSE; break;
      case 'H': self::$switch['hide'] = TRUE; break;
      case '!h': self::$switch['hide'] = !self::$switch['hide']; break;
      default:
	$ca = explode(':',$ca,2);
	if(count($ca)==2) self::$switch[$ca[0]] = $ca[1];
      }
    }
    return TRUE;
  }

  function q_parts($part,$args){
    return self::type_msg(array("unkown part-method: $part"),'qm');
  }

  /* ================================================================================
     r-functions
     ================================================================================*/
  function r_type1($args){  return $this->type_abr;  }
  function r_type2($args){  return $this->type_abr;  }
  function r_short1($args){ return $this->r_type2($args);  }
  function r_short2($args){ return $this->r_type2($args);  }

  function r_def($args){
    $mth = 'raw_' . def(self::$ext,'verbose',self::$def_v);
    return $this->$mth($this->val);
  }



  static function emb_int($txt,$typ='o',$cls='',$sty='',$lev=NULL,$tip=''){
    if($cls!='') $cls = 'c=' . self::$def_cls . '_' . $cls;
    if($sty!='') $sty = "s=$sty";
    if($tip!='') $tip = "i=$tip";
    if(is_null($lev)) $lev = self::$level;
    $lev = $lev<0?'':"l=$lev";
    return "{% t=$typ$lev$cls$sty$tip}$txt{%-}";
  }

  function emb($in){
    $cls = self::$def_cls;
    if(!is_array($in))                           return self::emb_int($in,'-e');
    if(def(self::$ext,'layout','html')=='only')  return self::emb_int(def($in,'data'),'-e');
    if(count($in)==0)                            return qz('emb an empty array');

    $res = array();
    foreach($in as $ck=>$cv) $res[$ck] = self::emb_int($cv,'-p',$ck);
    if(count($res)==1) return array_shift($res);
    return self::emb_int(implode('',$res),'-e');
  }


  function emb_bracket($in){
    $lev = self::$level;
    $open = array('(','[','&lt;');
    $close = array(')',']','&gt;');
    
    $n = (self::$level-1) % count($open);
    $res = self::emb_int($open[$n],'-s','',"font-size:%%c2:$lev%%%;")
      . $in . self::emb_int($close[$n],'-s','',"font-size:%%c2:$lev%%%;");
    return $res;
  }

  function emb_values($data,$typ='s',$cls='list',$mth='raw_short'){
    $res = array();
    switch($typ){
    case 's':
      foreach($data as $key=>$val)
	$res[] = $this->emb($this->emb_int($key . '%-sk1-%','span','key')
			    .  self::raw_auto($val,$mth),'span','item');
      return $this->emb_int(implode('%-s2-%',$res),'span','list');
    case 'l':
      foreach($data as $key=>$val)
	$res[] = $this->emb($this->emb_int($key . '%-sk1-%','li','key')
			    .  self::raw_auto($val,$mth),'span','item');
      return $this->emb_int(implode('%-s2-%',$res),'ul','list');
    }
    
  }


  static function sep_bylevel($lev,$txt='&nbsp;'){
    return self::emb_int($txt,'-s','',"border: solid %%c1:$lev%%px transparent;");
  }

  static function emb_final($in,$tag,$ext){
    $sty = self::make_style($ext);
    $cls = 'main';
    switch($tag){
    case 'div': case 'span':
      $res = '';
      if(is_array($in)) foreach((array)$in as $cr) $res .= self::emb_int($cr,$tag);
      else $res .= $in;
      $res = self::emb_int($res,$tag,$cls,$sty,NULL,self::get_line(NULL,FALSE));
      $p = strpos($res,'[');
      if($p!==FALSE){
	$res = substr($res,0,$p)
	  . self::emb_int('[','span','','',NULL,self::get_line(0,FALSE))
	  . substr($res,$p+1);
      }
      return $res;

    default:
      $res = $in;
    }
    return $res;
  }


  /** main distributor to display debug information
   * @param array $args: first element is object, other are additional arguments
   * @param string/array $typ: which kind of output is asked (name of method)
   */
  static function auto($args){
    if(self::$switch['hide']) return NULL;
    self::init();

    $fkt = self::$dbt[0]['function'];
    $ctyp = deff(self::$def,"$fkt-typ","*-typ",'single');
    $dtar = deff(self::$def,"$fkt-target","*-target",'sink');
    
    switch($ctyp){
    case 'single': return self::make(self::type_single($args,$fkt),$dtar);
    case 'multi':  return self::make(self::type_multi($args,$fkt),$dtar);
    case 'parts':  return self::make(self::type_parts($args,$fkt),$dtar);
    case 'call':   return self::make(self::type_call($args,$fkt),$dtar);
    case 'info':   return self::make(self::type_info($args,$fkt),$dtar);
    case 'msg':    return self::make(self::type_msg($args,$fkt),$dtar);
    case 'switch': return self::type_switch($args,$fkt); // no output!
    } 
    die("tudu");
  }

  static function type_switch($args,$fkt){
    if((count($args)%2)==1) $args[] = TRUE;
    while(count($args)>0){
      $sw = array_shift($args);
      $val = array_shift($args);
      $mth = 'switch_' . $sw;
      if(method_exists(__CLASS__,$mth)) self::$mth($val);
      else self::$switches[$sw] = $val;
    }
  }

  static function switch_clearsink($val){
    if($val===TRUE) $val = self::$sink;
    if(is_string($val)){
      if(file_exists($val)) unlink($val);
    } else trigger_error("Sink '$val' can not be cleared at the moment.",E_USER_NOTICE);
  }

  static function type_single($args,$fkt){
    if(count($args)==0) return self::type_msg(array(),'qm');
    $obj = array_shift($args);
    list(self::$ext,self::$int) = self::divide($args);
    $obj = self::find_class($obj,NULL,self::$int);
    
    $mth = self::_mth($obj,$fkt,self::$int);
    self::$ext = array_merge(self::_extract_def($fkt,'ext',$obj->cdef),self::$ext);
    self::$int = array_merge(self::_extract_def($fkt,'int',$obj->cdef),self::$int);
    $obj->$mth(self::$int);
    return $obj->res;
  }

  static function type_parts($args,$fkt){
    if(count($args)==0) return self::type_msg(array(),'qm');
    $obj = array_shift($args);
    $part = array_shift($args);
    $mth = 'q_parts_' . $part;
    list(self::$ext,self::$int) = self::divide($args);
    $obj = self::find_class($obj,NULL,self::$int);
    if(method_exists($obj,$mth)){
      self::$ext = array_merge(self::_extract_def($fkt,'ext',$obj->cdef),self::$ext);
      self::$int = array_merge(self::_extract_def($fkt,'int',$obj->cdef),self::$int);
      $obj->$mth(self::$int);
    } else $obj->q_parts($part,$args);
    return $obj->res;
  }

  static function type_multi($args,$fkt){
    if(count($args)==0) return self::type_msg(array(),'qm');
    $objs = array();
    while(count($args)>0 and (!is_string($args[0]) or substr($args[0],0,1)!='-'))
      $objs[] = array_shift($args);
    $ok = array_keys($objs);
    list($ext,$int) = self::divide($args);
    
    foreach($ok as $ck){
      $obj = self::find_class($objs[$ck],NULL,$int);
      $mth = self::_mth($obj,$fkt,$int);
      self::$ext = array_merge(self::_extract_def($fkt,'ext',$obj->cdef),$ext);
      self::$int = array_merge(self::_extract_def($fkt,'int',$obj->cdef),$int);
      $obj->$mth(self::$int);
      $objs[$ck] = $obj->res;
    }
    return implode(self::emb_int('','S'),$objs);
  }

  static function type_call($args,$fkt){
    list($ext,$int) = self::divide($args);
    $i = 0; 
    do {
      $cfct = def(self::$dbt[$i++],'function','');
      if($cfct!='' and !preg_match('#^q.$#',$cfct) and !preg_match('#^call_user_func(_array)?$#',$cfct)) break;
    } while($i<count(self::$dbt));
    $dbt = self::$dbt[$i-1];
    if(isset($dbt['class']))
      $ext['title']['call'] = "called $dbt[class]$dbt[type]$dbt[function] with:";
    else
      $ext['title']['call'] = "called $dbt[function] with:";
    $objs = self::$dbt[$i-1]['args'];
    $ok = array_keys($objs);
    if(count($objs)==0) {
      $ext['title']['call'] .= ' 0 arg';
      self::$ext = $ext;
      self::$int = $int;
      return NULL;
    }

    $ext['title']['call'] .= ' ' . count($ok) . ' arg';
    $res = array();
    foreach($ok as $ck){
      $obj = self::find_class($objs[$ck],NULL,$int);
      $mth = self::_mth($obj,$fkt,$int);
      self::$ext = array_merge(self::_extract_def($fkt,'ext',$obj->cdef),$ext);
      self::$int = array_merge(self::_extract_def($fkt,'int',$obj->cdef),$int);
      $obj->$mth(self::$int);
      $res[$ck] = $obj->res;
    }
    return implode(self::emb_int('','S'),$res);
  }

  static function type_info($args,$fkt){
    list(self::$ext,self::$int) = self::divide($args);
    self::$ext = array_merge(self::_extract_def($fkt,'ext',self::$def),self::$ext);
    self::$ext['inf'] = def(self::$ext,'inf',self::_extract_def($fkt,'inf',self::$def));
    // self::$ext = self::ext_chars($inf,self::$ext); // will be done in make before creating title!
    return NULL;
  }

  static function type_msg($args,$fkt){
    $inf = self::_extract_def($fkt,'inf',self::$def);
    if(count($args)==0)          $inf = str_replace('%1','',$inf);
    else if(is_string($args[0])) $inf = str_replace('%1',array_shift($args),$inf);
    list(self::$ext,self::$int) = self::divide($args);
    self::$ext = array_merge(self::_extract_def($fkt,'ext',self::$def),self::$ext);
    $cls = self::_extract_def($fkt,'tcls',self::$def);
    if(is_string($cls) and in_array($cls,array('tod','nyc','snh'))
       and isset($GLOBALS['_tool_']) and $GLOBALS['_tool_']->mode=='devel') $inf .= ' ' . self::get_line(0,FALSE);

    if(trim($inf)=='') $inf = date('H:i:s') . preg_replace('/^.*@/','@',self::get_line());
    if($cls===0) self::$ext['title'][] = $inf;
    else self::$ext['title'][$cls] = $inf;
    return NULL;
  }

  // mth is normally a q_* function
  static function int_auto($val,$mth,$int=array()){
    $obj = self::find_class($val,NULL,$int);
    $obj->$mth($int);
    $mth = 'layout_' . defm('layout',self::$ext,self::$args,'html');
    return self::$mth($obj->res,NULL);
  }

  // mth is normally a raw_* function
  static function raw_auto($val,$mth){
    $obj = self::find_class($val,NULL,TRUE);
    return $obj->$mth($val);
  }

  static function _mth($obj,$fkt,$int){
    if(isset($int['method'])) return 'q_' . $int['method'];
    return deff($obj->cdef,$fkt . '-fkt','*-fkt','q_def');
  }

  /** used for pixel-calculation %%key:val%%-pattern */
  static function calc($res){
    $max = self::maxlev($res,'#%%c1:(\d+)%%#');
    for($i=$max;$i>=0;$i--) $res = str_replace("%%c1:$i%%",($max-$i+1)*3,$res);
    
    $n = max(1,self::maxlev($res,'#%%c2:(\d+)%%#'));
    $raster = self::raster(120,200,$n,10,0);
    for($i=$n;$i>=0;$i--) $res = str_replace("%%c2:$i%%",$raster[$n-$i],$res);

    return $res;
  }

  /** sub of _auto
   * manages test of GLOBAL variabels
   * returns TRUE if output should be created otherwise FALSE (and NULL if test is unkown)
   */
  protected static function _auto_if($test,$key,$cv){
    $inv = substr($test,0,1)=='!';
    $test = substr($test,$inv?3:2);
    if($key!=''){
      $ex = array_key_exists($key,$GLOBALS)?1:0;
      if($ex) $cv = $GLOBALS[$key];
    } else $ex = 2;
    if($ex){
      switch($test){
      case 't': $res = ($cv==TRUE); break;
      case 'T': $res = ($cv===TRUE); break;
      case 'f': $res = ($cv==FALSE); break;
      case 'F': $res = ($cv===FALSE); break;
      case 'e': $res = empty($cv); break;
      case 'i': $res = is_int($cv); break;
      case 'r': $res = is_float($cv); break;
      case 'n': $res = is_numeric($cv); break;
      case 'nz': $res = (is_numeric($cv) and $cv==0); break;
      case 'nnz': $res = (is_numeric($cv) and $cv!=0); break;
      case 'ngz': $res = (is_numeric($cv) and $cv>0); break;
      case 'ngez': $res = (is_numeric($cv) and $cv>=0); break;
      case 'a': $res = is_array($cv); break;
      case 'ae': $res = (is_array($cv) and count($cv)==0); break;
      case 'ane': $res = (is_array($cv) and count($cv)!=0); break;
      case 'N': $res = is_null($cv); break;
      case 'o': $res = is_object($cv); break;
      case 's': $res = is_string($cv); break;
      case 'se': $res = (is_string($cv) and $cv==''); break;
      case 'sne': $res = (is_string($cv) and $cv!=''); break;
	//case 'S': $res = is_scalar($cv); break; // will never happen here
      case 'R': $res = is_resource($cv); break;
      case '': $res = TRUE; break;
      default:
	return NULL;
      }
    } else $res = FALSE;
    return $inv?!$res:$res;
  }

  protected static function _auto($obj,$ext){
    $obj = self::find_class($obj,NULL,TRUE);
    $mth = def($ext,'mth','q5');
    $ext['_call']['fkt'] = $mth;
    $obj->$mth($ext);
    return is_null($obj->res)?$obj->typ:$obj->res;
  }

  static function make($val,$deftar='sink'){
    $tar = def(self::$ext,'target',$deftar);
    if(preg_match('/^(html|htm|txt)?file /',$tar)){
      $target = explode(' ',$tar,2);
      $sink = $target[1];
      $sinkadd = NULL;
      $tar = 'file';
      $lay = preg_replace('/^(html|htm|txt)?file.*/','$1',$tar);
      if(empty($lay) or $lay=='text' or $lay=='txt') $lay = 'raw';
    } else {
      $sink = self::$sink;
      $sinkadd = self::$sink_ptr;
      $lay = NULL;
    }

    
    if(is_null($lay)) $lay = defm('layout',self::$ext,self::$args,'html');

    $tag = def(self::$ext,'embed','div');

    // add info statements to the title
    if(isset(self::$ext['inf']))
      self::$ext = self::ext_chars(self::$ext['inf'],self::$ext);

    if(isset(self::$ext['title']) and $lay!='only') 
      $val = self::make_title($val,self::$ext,$tag);
    $res = self::emb_final($val,$tag,self::$ext);
    $mth = 'layout_' . $lay;
    $res = self::calc(self::$mth($res,$tag));
    return self::output($res,$tar,$sink,$sinkadd);
  }
    

  static function make_style($def){
    if(is_null($def)) return '';
    $ak = preg_grep('/^style-/',array_keys($def));
    $res = '';
    foreach($ak as $ck){
      $val = $def[$ck];
      switch($ck){
      case 'style-color':
	if(is_numeric($val)) $col = def(self::$colors['_bc'],$val,'red');
	else $col = $val;
	$res .= "border-color: $col; border-width: 2px 15px;";
	break;
      }
    }
    return $res;
  }

  static function make_title($res,$set,$tag){
    $title = $set['title'];
    $cls = def($set,'title-cls','tit');
    // linenumber and time for first title part
    $inf = self::get_line(NULL,FALSE) . ' ' . self::title_char('D',TRUE); 
    $tit = array();
    foreach($title as $key=>$val){
      if(is_numeric($key)){
	$tit[] = self::emb_int($val,$tag,$cls,'',NULL,$inf);
      } else if(!is_array($val)){
	$tit[] = self::emb_int($val,$tag,$cls .' ' . self::$def_cls . '_' . $key,'',NULL,$inf);
      } else {
	foreach($val as $ck=>$cv) {
	  $tit[] = self::emb_int($cv,$tag,$cls . ' ' . self::$def_cls . '_' . $key,'',NULL,$inf);
	  $inf = '';
	}
      }
      $inf = '';
    }
    return implode($tag=='span'?'%-s3-%':'',$tit) . $res;
  }


  /** the main function */
  static function output($res,$target,$sink,$sinkadd){
    //$res .= '<hr/>' . htmlspecialchars($res) .'<hr/>';
    if(self::$switches['hide']==TRUE) return;
    switch($target){
    case 'file':
    case 'sink': case 's': 
      if(self::output_to_sink($res,$sink,$sinkadd)) break;
    case 'echo': case 'e': echo $res; flush(); // no-break 
    case 'return': case 'ret': case 'raw': case 'r': return $res;
    case 'rem':       
      $res = "\n\n<!-- opc_debug " . self::layout_raw(self::get_line(0,FALSE)) . "\n\n$res\n\n-->\n\n";
      if(self::output_to_sink($res,$sink,$sinkadd)) break;
      echo $res;
      break;

    case 'die':
      $msg = 'Script stopped by opc_debug at ' . self::layout_raw(self::get_line(0,FALSE))
	. '; time:' . date('Y-m-d H:i:s');
      if(is_object(self::$sink)) {
	if(self::output_to_sink($res)){
	  if(self::$sink instanceof opc_fw) self::$sink->msgdie($msg);
	  else $res = self::$sink->exp();
	}
      } 
      echo $res;
      die($msg);

    default:
      trigger_error("what happens here! target: '$target'",E_USER_ERROR);    
    }
  }
 
  static function output_to_sink($res,$sink,$add){
    if(is_string($sink)){
      $fi = @fopen($sink,'a'); // to file
      if(!is_resource($fi)) return FALSE;
      fwrite($fi,$res . "\n\n");
      fclose($fi);
    } else if($sink instanceof opc_ptr_ht2){
      $sink->add($res); return $res;;
    } else if($sink instanceof opc_ht){
      $sink->add($res); return $res;;
    } else if($sink instanceof opc_fw){
      $sink->$add->add($res); return $res;
    } else return FALSE;
    return TRUE;
  }

  /*
  static function extract_obj($obj){
    $res = array();
    $add = array();
    foreach($obj as $ca){
      if(!is_string($ca) or substr($ca,0,2)!='-.')
	$res[] = $ca;
      else
	$add[] = substr($ca,2);
    }
    return array($res,$add);
  }
  */
  static function divide($args){
    $ext = array();
    $int = array();
    foreach($args as $ca){
      if(!is_string($ca)) $ca = strval($ca);
      if(ord($ca)==45){ // text starts with a '-'
	if(preg_match('/^-\d+$/',$ca)){
	  $ext['style-color'] = abs((int)$ca);
	} else if(preg_match('/^-[!]?[\w]+:/',$ca)){
	  $ca = explode(':',substr($ca,1));
	  $ext[def(self::$ext_short,$ca[0],$ca[0])] = trim($ca[1]);
	} else if(preg_match('/^-\w+=/',$ca)){
	  $ca = explode('=',substr($ca,1));
	  $int[def(self::$int_short,$ca[0],$ca[0])] = trim($ca[1]);
	} else {
	  $ext = array_merge($ext,self::ext_chars(substr($ca,1),$ext));
	}
      } else if(!isset($ext['title'])){
	$ext['title'][] = $ca;
      } else {
	$ext['title'][] = $ca;
      }
    }
    return array($ext,$int);
  }

  /**
     t: time (h->ms);
     d/D: delta time (ms) since d:last d/D D:script init (opc_debug)
     m/M: memory usage (MB) m: last m/M M: total
     ?Q: show result only if global variable Q is set and not similar to FALSE
     ?e: echo result (ignor sink)
     ?x: die/exit after this command
   */
  protected static function ext_chars($txt,$def){
    $nc = strlen($txt);
    for($ii=0;$ii<$nc;$ii++){
      $cc = substr($txt,$ii,1);
      $cres = self::title_char($cc);
      if(is_array($cres)) $def['title'] = array_merge(def($def,'title',array()),$cres);
      else $def['title'][] = $cres;
    }
    return $def;
  }
  
  // which, use emb_int?
  protected static function title_char($cc,$raw=FALSE){
    switch($cc){
    case 't': 
      $res = date('H:i:s.') . substr(microtime(),2,3);
      return $raw?$res:self::emb_int($res,'span');

    case 'D': case 'd':
      $now = self::mtime();
      $del = $now-($cc=='D'?self::$t_init:self::$t_last);
      if($cc=='d') self::$t_last = $now;
      $res = $cc . sprintf(':%0.3fs',$del);
      return $raw?$res:self::emb_int($res,'span');

    case 'm': case 'M': 
      $now = memory_get_usage();
      $del = $now - ($cc=='m'?self::$m_last:0);
      if($cc=='m') self::$m_last = $now;
      $res = $cc . sprintf(':%0.2fMB',$del/1024/1024);
      return $raw?$res:self::emb_int($res,'span');

    case 'l': 
      $i = isset(self::$dbt[0]['line'])?0:1;
      $res = '@' . self::$dbt[$i]['line'];
      return $raw?$res:self::emb_int($res,'span','fln');

    case 'L':
      $tmp = array();
      foreach(self::$dbt as $cl) if(isset($cl['file'])) $tmp[] = self::fileline($cl);
      return $tmp;

    case 'c': return '{' . ++self::$cnt[''] . '}';

    case 'C':
      $dbt = self::$dbt[0];
      $key = $dbt['line'] . '@' . $dbt['file'];
      if(isset(self::$cnt[$key])) $nt = ++self::$cnt[$key];
      else $nt = self::$cnt[$key] = 1;
      return "<span title='$key'>{{$nt}x@$dbt[line]}</span>";	

    default:
      return $cc;
    }
  }


  static function add2arr(&$arr,$str){
    $str = explode(':',$str,2);
    if(count($str)==1) $arr[] = $str[0];
    else $arr[trim($str[0])] = trim($str[1]);
  }


  static function init(){
    $dbt = debug_backtrace();
    $nl = count($dbt);
    $max = 0;
    for($ii=0;$ii<$nl;$ii++) if(def($dbt[$ii],'file')===__FILE__) $max = $ii;
    self::$dbt = array_splice($dbt,$max+1);
  }

  /** similar to microtime but includes up to 1000 seonds */
  static function mtime(){
    $res = explode(' ',microtime());
    return (float)substr($res[1],-3) + (float)$res[0];
  }


  static function get_lines($nl=0,$simpl=TRUE){
    if($nl<1) $nl = count(self::$dbt);
    $res = array();
    for($ii=0;$ii<$nl;$ii++)
      $res[] = self::fileline(self::$dbt[$ii],$simpl);
    return $res;
  }

  static function get_line($nl=NULL,$simpl=TRUE){
    if(is_null($nl)) $nl = isset(self::$dbt[0]['line'])?0:1;
    return self::fileline(def(self::$dbt,$nl),$simpl);
  }

  static function fileline($dbt,$simpl=TRUE){
    static $last = NULL;
    $line = def($dbt,'line','?');
    if(!isset($dbt['file'])){
      $file = '';
    } else if($last!==$dbt['file'] or $simpl===FALSE){
      $last = $dbt['file'];
      $file = $last;
      $dr = def($_SERVER,'DOCUMENT_ROOT','');
      if($dr!='' and strpos($file,$dr)===0) $file = '+' . substr($file,strlen($dr));
    } else $file = self::emb_int('~','span','','',NULL,$last);
    return "$file@$line";
  }
  
  static function css($typ=NULL){
    if(is_null($typ)) $typ = self::$colscheme;
    $cls = self::$class;
    $col = self::$colors['std'];
    if($typ!='std') $col = array_merge($col,def(self::$colors,$typ,array()));
    $res = array(
		 ".${cls}_main"=>"color: $col[f1]; background-color: $col[b1];"
		 . " border: solid 2px $col[r1]; border-width: 1px 4px;"
		 . " margin: 0 3px; padding: 0 3px; ",
		 "div.${cls}_main"=>"margin: 4px 3px;",
		 ".${cls}_main dl"=>"background-color: $col[b1]; border: solid 2px $col[r1];",
		 ".${cls}_main ol"=>"background-color: $col[b1]; border: solid 2px $col[r1];",
		 ".${cls}_main ul"=>"background-color: $col[b1]; border: solid 2px $col[r1];",
		 // necessary if .cls_main has vert. paddings, margins or thick hor-borders
		 //"span.${cls}_main"=>"line-height: 100%;", 

		 // seperator on top-level (title)
		 ".${cls}_sep0"=>"color: $col[b1]; background-color: $col[f3]; padding: 0 2px; margin: 0 10px;",
		 // type decalration
		 ".${cls}_typ"=>"color: $col[f3]; background-color: $col[b1];",
		 // array/obj keys
		 ".${cls}_key"=>"color: $col[f3]; background-color: $col[b1]; font-weight: bold;",
		 // infos
		 ".${cls}_inf"=>"color: $col[f4]; background-color: $col[b1]; font-weight: bold;",
		 "span.${cls}_invc"=>"color: $col[f2]; background-color: $col[b3];",

		 //title general
		 ".${cls}_tit"=>"color: $col[b1]; background-color: $col[f3];"  
		 . " padding: 0 2px; margin: 0 2px 0 -3px;",
		 // file line number
		 ".${cls}_fln"=>"color: $col[o3]; background-color: $col[f3];",
		 // called with
		 ".${cls}_call"=>"color: $col[o2]; background-color: $col[f3];", 
		 //tudu/nyc/snh-title
		 ".${cls}_todo"=>"color: $col[ftodo]; background-color: $col[btodo];",
		 ".${cls}_nyc"=>"color: $col[fnyc]; background-color: $col[bnyc];",
		 ".${cls}_snh"=>"color: $col[fsnh]; background-color: $col[bsnh];",

		 // for table layouts
		 "table.${cls}_table"=>"border: solid 2px black; margin: 1px;"
		 . ' border-collapse: collapse;',
		 "table.${cls}_table table.${cls}_table"=>"margin: auto;",
		 "table.${cls}_table ul"=>"border: none; margin: 0; padding: 0 3px 0 15px;",
		 "table.${cls}_table th.${cls}_inf"=>"color: $col[f3]; ",
		 "table.${cls}_table td.${cls}_cell"=>"border: solid 1px $col[f2]; vertical-align: middle; text-align: center;",
		 "table.${cls}_table td.${cls}_NA"=>"color: $col[f2]; background-color: $col[b3];",
		 
		 // for text-table layouts
		 "table.${cls}_txt"=>"margin: 1px; border-collapse: collapse;",
		 "table.${cls}_txt th"=>"color: $col[f3]; background-color: $col[b3];",
		 "table.${cls}_txt td.${cls}_odd"=>"color: $col[f1]; background-color: $col[b3];",
		 "table.${cls}_txt tr.${cls}_hr"=>'border-top: solid 1px black;',
		 
		 );
    return $res;
  }



  /* ================================================================================
     non-static part (happens primary in the child-classes)
     ================================================================================*/
  /** the value to show */
  protected $val = NULL; 
  /** the debug result (raw) */
  protected $res = NULL;
  /** type information */
  protected $typ = NULL;
  /** additional information */
  protected $inf = array();

  // still in use?
  function raw_1($data){ return self::raw_2($data);}
  function raw_2($data){ return self::raw_3($data);}
  function raw_3($data){ return self::raw_4($data);}
  function raw_4($data){ return self::raw_exp($data);}
  function raw_5($data){ return self::raw_4($data);}
  function raw_6($data){ return self::raw_5($data);}
  function raw_7($data){ return self::raw_6($data);}
  function raw_8($data){ return self::raw_7($data);}
  function raw_9($data){ return self::raw_8($data);}

  function raw_exp($data){ return var_export($data,TRUE); }
  function raw_print($data){ return print_r($data,TRUE); }
  function raw_ser($data){ return serialize($data); }
  function raw_str($data){ return strval($data); }
  function raw_typ($data){ return gettype($data); }

  function raw_dump($data){ 
    ob_start();
    var_dump($data); 
    return ob_get_clean();
  }

  function raw_cls($data){
    if(is_object($data))   return get_class($data);
    if(is_resource($data)) return get_resource_type($data);
    return gettype($data);
  }

  function raw_short($data){ 
    if(is_null($data)) return '[N]';
    if(is_bool($data)) return $data?'[T]':'[F]';
    if(is_scalar($data)) return strval($data);
    if(is_resource($data)) return '[R-' . get_resource_type($data) . ']';
    if(is_object($data)) return '[O-' . get_class($data) . ']';
    if(is_array($data)){
      return 'A' . substr(var_export($data,TRUE),6);
    }
    return var_export($data,TRUE);
  }    
    
  
  function raw_table($arr){
    if(!is_array($arr)) return $this->raw_def($arr);
    if(count($arr)==0) return $this->r_def($arr);
    $rn = array_keys($arr);
    $cn = array();
    foreach($arr as $cv) if(is_array($cv)) $cn = array_merge($cn,array_keys($cv));
    $cn = array_unique($cn);
    if(count($cn)==0) return $this->r_def(array('v'=>3,'t'=>'l'));

    $cells = $this->emb_int(count($rn) . 'x' . count($cn),'th','inf');
    foreach($cn as $ccn) $cells .= $this->emb_int($ccn,'th','head-col');
    $rows = $this->emb_int($cells,'tr','head-col');
    foreach($arr as $key=>$val){
      $cells = $this->emb_int($key,'th','head-row');
      foreach($cn as $ccn){
	if(isset($val[$ccn])){
	  $cells .= $this->emb_int(self::raw_auto($val[$ccn],
						  is_array($val[$ccn])?'raw_table':'raw_2'),
				   'td','cell');
	} else $cells .= $this->emb_int('&nbsp;','td','NA');
      }
      $rows .= $this->emb_int($cells,'tr','row');
    }
    return $this->emb_int($rows,'table','table');
    
  }


  static function _die($int){
    die(date('Y-m-d- H:i:s') . ' ' . strip_tags(self::get_line(0)));
  }

  static function _get_def($cls){
    $path = array($cls);
    while($cls = get_parent_class($cls)) array_unshift($path,$cls);
    $res = array();
    foreach($path as $cls) {
      $av = get_class_vars($cls);
      $res = array_merge($res,$av['def']);
    }
    return $res;    
  }

  static function _extract_def($pre,$key,$def){
    $ak = array_keys($def);
    if(isset($def["$pre-$key"])) return $def["$pre-$key"];
    if(isset($def["*-$key"])) return $def["*-$key"];
    $sk = preg_replace('/^[^-]*-[^-]*-?/','',preg_grep("/^\*-$key-/",$ak));
    $fk = preg_replace('/^[^-]*-[^-]*-?/','',preg_grep("/^$pre-$key-/",$ak));
    $res = array();
    foreach($fk as $ck) $res[$ck] = $def["$pre-$key-$ck"];
    foreach($fk as $ck) if(!isset($res[$ck])) $res[$ck] = $def["*-$key-$ck"];
    return $res;
  }

  function __construct($obj,$int){
    $this->cdef = self::_get_def(get_class($this));
    $this->val = $obj;
  }

  protected function html($txt) { return htmlspecialchars($txt);}

  /**  showflag
   * @param $val: binary flagged value
   * @param $labels array of labels (bin-0 first) or string (bin-0 = first char)
   * @param $mode (=0) 0 spaces between, 1 no spaces, 2 upper/lowercase incl spaces 3 u/l no spaces
   * @return
   */
  static function showflag($val,$labels,$mode=0){
    if(is_string($labels)) $labels = str_split($labels,1);
    $sep = ($mode & 1)==1?'':' ';
    $ne = count($labels);
    $res = '';
    for($i=0;$i<$ne;$i++){
      $tf = pow(2,$i);
      $tf = ($val & $i)==$i;
      if($mode>1)  $res .= $sep . ($tf?strtoupper($labels[$i]):strtolower($labels[$i]));
      else if($tf) $res .= $sep . $labels[$i];
    }
    return trim($res);
  }

}


opc_debug::$t_init = opc_debug::mtime();
opc_debug::$t_last = opc_debug::$t_init;
opc_debug::$m_last = memory_get_usage();








//class opc_debug_scalar extends opc_debug{ } // not needed

class opc_debug_null extends opc_debug{
  protected $type_abr = 'N';

  function q_type($args) { 
    $this->res = $this->emb(array('typ'=>'[' . self::raw_def(NULL) . ']'));  
  }
  function q_short($args){ 
    $this->res = $this->emb(array('typ'=>'[' . self::raw_def(NULL) . ']'));  
  }
  function q_def($args)  { 
    $this->res = $this->emb(array('typ'=>'[' . self::raw_def(NULL) . ']'));  
  }

  static function raw_def($val){ return 'N';}
}

class opc_debug_bool extends opc_debug{
  protected $type_abr = 'B';

  function q_short($args){ 
    $this->res = $this->emb(array('typ'=>'[' . self::raw_def($this->val) . ']'));  
  }
  function q_def($args)  { 
    $this->res = $this->emb(array('typ'=>'[' . self::raw_def($this->val) . ']'));  
  }

  function r_type2($args){ return self::raw_def($this->val);}

  static function raw_def($val){ return $val?'T':'F';}
  
}

class opc_debug_string extends opc_debug{
  protected $type_abr = 'S';
  static protected $invc = array(" "=>'&bull;', 
				 "\n"=>'n', 
				 "\t"=>'t', 
				 "\r"=>'r',);

  static public $def = array('qt-fkt'=>'q_table',
			     );

  function __construct($obj,$int){
    $this->cdef = self::_get_def(get_class($this));
    switch(def($int,'charset','-')){
    case 'iu': $this->val = utf8_encode($obj); break;
    case 'ui': $this->val = utf8_decode($obj); break;
    default:
      $this->val = $obj;
    }
  }

  function r_type2($args){
    return $this->type_abr . strlen($this->val);
  }


  function r_short1($args){ return self::shorten(strip_tags($this->val));  }
  function r_short2($args){ return self::shorten(htmlspecialchars($this->val));  }

  static function shorten($val,$len=20){
    $sl = strlen($val);
    if($sl<$len) return $val;
    $res = substr($val,0,$len*.7) . '...' . substr($val,$sl-$len*.3);
    return $res;
  }

  function r_def($args){
    switch(def($args,'v',1)){
    case 2: return $this->html($this->val);
    case 3: return $this->invc($this->html($this->val));

    case 4: 
      $res =  $this->nl2br($this->val);
      if(strpos($res,'<br/>')) self::$ext['embed'] = 'div'; 
      return $res;

    case 5: 
      $res =  $this->invc($this->nl2br($this->html($this->val)));
      if(strpos($res,'<br/>')) self::$ext['embed'] = 'div'; 
      return $res;
    }
    return $this->val;
  }

  function nl2br($txt){
    return preg_replace("(\n\r|\r\n|\n|\r)",'$0<br/>',$txt);
  }
  
  function invc($txt) { 
    foreach(self::$invc as $ck=>$cv)
      $txt = str_replace($ck,$this->emb_int($cv,'span','invc'),$txt);
    return $txt;
  }

  function q_table($int){
    self::$ext['embed'] = 'div';
    $this->res = $this->emb(array('val'=>$this->raw_txttable($this->val)));
  }

  function raw_txttable($txt){
    $nc = strlen($txt);
    $hr = $this->emb_int('&gt;','th');
    $tr = $this->emb_int(1,'th');
    $rows = array();
    $cc = 1;
    $cl = 0;
    $cr = 1;
    for($ii=0;$ii< $nc;$ii++){
      $char = substr($this->val,$ii,1);
      $cl++;
      if($cl>$cc) {
	if(     $cc%10==0) $hr .= $this->emb_int(($cc/10)%10,'th');
	else if($cc%5 ==0) $hr .= $this->emb_int('|','th');
	else if($cc%2 ==0) $hr .= $this->emb_int(':','th');
	else               $hr .= $this->emb_int('.','th');
	$cc++;
      }
      $cls = ($cl%10>5 or $cl%10==0)?'odd':'even';
      $ord = sprintf('r%d/c%d p%d: asc:%d/#%X',$cr,$cl,$ii+1,ord($char),ord($char));
      $tr .= $this->emb_int($this->emb($char,'span','','',NULL,$ord),'td',$cls,'',NULL,$ord);
      if($char==="\n"){
	$rows[] = $this->emb_int($tr,'tr',$cr%5==1?'hr':'');
	$tr = $this->emb_int(++$cr,'th');
	$cl = 0;
      }
    }
    $rows[] = $this->emb_int($tr,'tr');
    $cls = self::$class;
    $res = $this->emb_int($hr,'tr') . implode('',$rows);
    return $this->emb_int($res,'table','txt');
  }
}



class opc_debug_integer extends opc_debug{
  protected $type_abr = 'i';

  function q5($args){ $this->q_def(array_merge($args,array('base'=>'\'')));}
  function q6($args){ $this->q_def(array_merge($args,array('base'=>16)));}
  function q7($args){ $this->q_def(array_merge($args,array('base'=>2)));}
  function q8($args){ $this->q_def(array_merge($args,array('base'=>8)));}
  function q9($args){ $this->q_def(array_merge($args,array('base'=>'e')));}
  
  function r_type2($args){
    return $this->type_abr . ($this->val==0?'0':($this->val<0?'-':'+'));
  }

  function r_short1($args){ return $this->val;  }
  function r_short2($args){ return $this->val;  }
  function r_def($args){ 
    $base = def($args,'base');
    if(is_null($base)) return $this->val;
    if($base=='e') return sprintf('%e',$this->val);
    $res = $this->r_base($this->val,$base);
    if(isset($this->inf['base'])) $res = $this->val . ' ' . $this->inf['base'] . ': ' . $res;
    return $res;
  }


  //sideeffects to inf!
  function r_base($val,$base){
    switch($base){
    case '.': case "'": 
      $res = $val; $nc = strlen($res);
      for($i=$nc-3;$i>0;$i-=3) $res = substr($res,0,$i) . $base . substr($res,$i);
      return $res;

    case '2': case 'b': case 'B': 
      $res = sprintf('%b',$val);
      $this->inf = array('base'=>'Bin');
      break;

    case '8': case 'o': case 'O': 
      $res = sprintf('%o',$val);
      $this->inf = array('base'=>'Oct');
      break;

    case '16': case 'H': case 'X': 
      $res = sprintf('%X',$val);
      $this->inf = array('base'=>'Hex');
      break;

    case 'h': case 'x': 
      $res = sprintf('%x',$val);
      $this->inf = array('base'=>'Hex');
      break;

    case '10': case 'D': case 'd': 
      $res = sprintf('%d',$val);
      $this->inf = array('base'=>'Dec');
      break;

    case 'e': 
      return sprintf('%e',$val);

    case 't':
      return date('Y-m-d H:i:s T [D M z\J\D]',$val);

    default:
      if(!is_numeric($base) or (int)$base===0 or (int)$base>36){
	return $val;
      } 
      $base = abs((int)$base);
      $res = '';
      $sgn = $val>0?'':'-';
      $val = abs($val);
      while($val>0){
	$mod = $val % $base;
	$res = ($mod>9?chr($mod+55):$mod) . $res;
	$val = ($val-$mod)/$base;
	$res = $res;
	$this->inf['base'] = "[$base]";
      }
    }
    $len = strlen($res) % 4;
    if($len) $res = str_repeat('0',4-$len) . $res;
    $res = chunk_split($res,4,' ');
    $this->inf['dec-value'] = $val;
    return $res;
  }
}

class opc_debug_float extends opc_debug{
  protected $type_abr = 'f';

  function r_type2($args){
    return $this->type_abr . ($this->val==0?'0':($this->val<0?'-':'+'));
  }
  function r_short1($args){ return sprintf('%0.3f',$this->val);  }
  function r_short2($args){ return sprintf('%0.5f',$this->val);  }
  function r_def($args){
    switch(def($args,'v',1)){
    case 5: return sprintf('%e',$this->val);
    }
    return $this->val;  
  }


}

class opc_debug_numeric extends opc_debug{
  protected $type_abr = 'n';
  function defexp($int){ $this->res = $this->val; }

  function r_type2($args){
    return $this->type_abr . ($this->val==0?'0':($this->val<0?'-':'+'));
  }

  function r_short1($args){ return $this->val;  }
  function r_short2($args){ return $this->val;  }
  function r_def($args)   { return $this->val;  }

}


class opc_debug_array extends opc_debug{
  protected $type_abr = 'A';

  function r_type2($args){ return $this->type_abr . count($this->val);  }

  function q_def($args){
    $this->res = $this->emb(array('typ'=>'[' . $this->r_type2($args) . ']:%-s1-%',
				  'data'=>$this->r_def($args)));
  }
  
  static public $def = array('q3-int-t'=>'t',
			     'q3-fkt'=>'q_def',

			     'q4-int-t'=>'s',
			     'q4-fkt'=>'q_def',
			     'q4-int-v'=>1,
			     'q5-int-t'=>'s',
			     'q5-int-v'=>2,
			     'q6-int-t'=>'s',
			     'q6-int-v'=>3,

			     'q7-int-t'=>'l',
			     'q7-int-v'=>1,
			     'q8-int-t'=>'l',
			     'q8-int-v'=>2,

			     'q9-int-t'=>'d',
			     'q9-int-v'=>1,
			     'qt-fkt'=>'q_table',
			     );

  function r_def($args){
    if(count($this->val)==0) return $this->res = '[A0]';
    $res = array();
    $i = 0;
    self::$level++;
    $arr = $this->val;
    $v = def($args,'v',1);
    
    $sargs = $args;
    switch(def($args,'t','s')){
    case 'l': // ol-list
      switch($v){
      case 1: $sargs['t'] = 's'; break;
      case 2: $sargs['v'] = 1; break;
      }
      foreach($this->val as $key=>$val){
	$cr = self::int_auto($val,'q_def',$sargs);
	if($v>1 or $key!==$i++)
	  $cr = $this->emb_int($key . '%-sk1-%','span','key') . $cr;
	$res[] = $this->emb_int($cr,'li','i','item');
      }
      self::$ext['embed'] = 'div';
      $res = $this->emb_int(implode('',$res),'ul','l','list');
      break;

    case 'd': // dl-list
      foreach($this->val as $key=>$val){
	$res[] = $this->emb_int($key . '%-sk1-%','dt','key') 
	  . $this->emb_int(self::int_auto($val,'q_def',$sargs),'dd','item');
      }
      self::$ext['embed'] = 'div';
      $res = $this->emb_int(implode('',$res),'dl','list');
      break;

    case 't': // typelist only
      $sargs['v'] = 2;
      foreach($this->val as $key=>$val){
	$cr = self::int_auto($val,'q_type',$sargs);
	if($v>1 or $key!==$i++) 
	  $cr = $this->emb_int($key . '%-sk1-%','span','key') . $cr;
	$res[] = $cr;
      }
      $this->val = $arr;
      $res = implode(' ',$res);
      break;

    case 's': // span-list
      if($v<3) $sargs['v'] = $v-1;
      foreach($this->val as $key=>$val){
	$cr = self::int_auto($val,'q_def',$sargs);
	if(is_array($val)) $cr = $this->emb_bracket($cr);
	if($v>1 or $key!==$i++)
	  $cr = $this->emb_int($key . '%-sk1-%','span','key') . $cr;
	$res[] = $cr;
      }
      $this->val = $arr;
      $res = implode(self::sep_bylevel(self::$level,'%-s2-%'),$res);
      break;
    }
    self::$level--;
    return $res;
  }

  function q_table($int){
    self::$ext['embed'] = 'div';
    $this->res = $this->emb(array('val'=>$this->raw_table($this->val)));
  }
 
}

class opc_debug_resource extends opc_debug{
  protected $type_abr = 'R';

  function r_type2($args){ 
    $id = preg_replace('/^.*#/','',strval($this->val));
    return $this->type_abr . '-' . $id . ':' . get_resource_type($this->val);
  }

  function q_short($args){ 
    $this->res = $this->emb(array('typ'=>'[' . $this->r_type2(NULL) . ']'));  
  }

  function q_def($args)  { 
    $this->res = $this->emb(array('typ'=>'[' . $this->r_type2(NULL) . ']'));  
  }

  function r_def($args) {return '';$this->r_type2($args);}

}

class opc_debug_obj extends opc_debug{
  protected $type_abr = 'O';

  function __construct($obj,$int){
    $this->cdef = self::_get_def(get_class($this));
    if(is_object($obj))$this->val = clone $obj;
    else $this->val = $obj;
  }


  function q_parts($part,$args){
    return self::type_msg(array("unkown part-method: $part"),'qm');
  }



  function r_type2($args){
    if($this->type_abr=='O') return 'O-' . get_class($this->val);
    return parent::r_type2($args);
  }

  function r_short1($args){    
    $res = array('cls'=>get_class($this->val));
    $tmp = get_parent_class($this->val); 
    if(!empty($tmp)) $res['parcls'] = $tmp;
    return $this->emb_values($res,'s','vlist');
  }

  function r_short2($args){    return $this->r_short1($args);  }

  function r_def($args){
    $ov = get_object_vars($this->val);
    return self::int_auto($ov,'q_def');
  }

  // collects data form $this->val
  function _stdV($keys,$res=array(),$named=TRUE,$skipnull=TRUE){
    foreach($keys as $ck=>$cv) {
      $key = $named?$ck:$cv;
      $val = $this->val->$key;
      if($skipnull and is_null($val)) continue;
      $res[$cv] = $val;
    }
    return $res;
  }


}

class opc_debug_obj_opc_sxml extends opc_debug_obj{
  protected $type_abr = 'sxml';

  function r_def($args){ 
    $res = $this->val->data;
    return $this->emb_values($res,'l');
  }
}

class opc_debug_obj_opc_ptr_ht2 extends opc_debug_obj{
  protected $type_abr = 'ptr_ht2';

  protected function _pos(){
    switch($this->val->obj->str[$this->val->key]['typ']){
    case 'tag': return 'tag:' . $this->val->obj->str[$this->val->key]['tag']; 
    }
    if(empty($this->val->obj->str[$this->val->key]['tag']))
      return $this->val->obj->str[$this->val->key]['typ'];
    return $this->val->obj->str[$this->val->key]['typ'] . '-' .$this->val->obj->str[$this->val->key]['tag'];
  }

  function r_short1($args){
    return $this->emb_values(array('ckey'=>$this->_pos() . '@' . $this->val->key));
  }

  function r_short2($args){
    return $this->emb_values(array('root'=>$this->val->root,
				   'ckey'=>$this->_pos() . '@' . $this->val->key));
  }

  function r_def($args){ 
    $rkey = $this->val->root;
    $res = array('ckey'=>$this->_pos() . '@' . $this->val->key,
		 'next'=>$this->val->nxt,
		 'root'=>$this->val->obj->str[$rkey]['tag'] . "@$rkey",
		 );
    return $this->emb_values($res);
  }

  function q_parts($part,$args){
    if(is_numeric($part)) { 
      $key = $part; 
      $part = 'element'; 
    } else $key = NULL;
    switch($part){
    case 'str': case 'structure': case 's':
      $this->res = $this->emb(array('typ'=>'ht2-structer','val'=>$this->r_structure()));
      break;
    case 'data': case 'd':
      $this->res = $this->emb(array('typ'=>'ht2-structer','val'=>$this->r_data()));
      break;

    case 'chain': case 'c':
      $this->res = $this->emb(array('typ'=>'ht2-structer','val'=>$this->r_chain()));
      break;

    case 'element': case 'e':
      if(!isset($key)) $key = array_shift($args); //no break;
    default:
      if(!isset($key)) $key = $part;
      $this->res = $this->emb(array('typ'=>'ht2-element: ' . $key,
				    'val'=>$this->r_element($key)));
    }
  }

  function r_structure(){
    return self::int_auto($this->val->obj->str,'q_table');
  }

  function r_data(){
    return self::int_auto($this->val->obj->data,'q_table');
  }

  protected function disp_ele($row){
    if($row['typ']=='tag') $res = $row['tag']; 
    else $res = $row['typ'] . '-' . $row['tag'];
    $res .= '@' . $row['key'];
    return $res;
  }

  function r_chain(){
    $tmp1 = array();
    foreach($this->val->obj->str as $key=>$val){
      $disp = $this->emb_int($this->disp_ele($val),'li');
      if(isset($val['fcl'])) $disp .= $this->emb_int("##$val[fcl]##",'ol');
      $tmp1[$key] = $disp;
    }

    $tmp2 = array();
    foreach($this->val->obj->str as $key=>$val){
      if(!is_null($val['prv'])) continue;
      $tmp2[$key] = $tmp1[$key];
      $ck = $val['nxt'];
      while(!is_null($ck)){
	$tmp2[$key] .= $tmp1[$ck];
	$ck = $this->val->obj->str[$ck]['nxt'];
      }
    }

    $tmp1 = array();
    do{
      $n = 0;
      $ak = array_keys($tmp2);
      foreach($ak as $key){
	if(preg_match('/##[^#]+##/',$tmp2[$key])) continue; // has subs -> wait
	$n++;
	$sub = array_keys(preg_grep("/##$key##/",$tmp2));
	if(count($sub)==0) $tmp1[] = $tmp2[$key];
	else foreach($sub as $ck) $tmp2[$ck] = str_replace("##$key##",$this->emb_int($tmp2[$key]),$tmp2[$ck]);
	unset($tmp2[$key]);
      }
    } while($n>0);
    $res = $this->emb_int(implode('',$tmp1),'ul');
    return $this->emb(array('val'=>$res));
  }


  function r_element($key){
    if(!isset($this->val->obj->str[$key])) 
      return "unkown element: $key";
      $str = $this->val->obj->str[$key];
      $res = array('str'=>$this->emb_values($str));
      if(isset($this->val->obj->data[$str['key']])) 
	$res['data'] = self::int_auto($this->val->obj->data[$str['key']],'q_def');
      else
	$res['data'] = '[no specific data saved]';
      return $this->emb_values($res,'l');
  }




}

class opc_debug_obj_opc_head extends opc_debug_obj{
  protected $type_abr = 'head';
  
  function _r_prolog(){
    return $this->emb_int(($this->val->xhtml?'xhtml':'HTML') . ' ' . $this->val->charset,'span','inf');
  }
   
  function r_short1($args){ 
    return $this->emb_values(array(''=>$this->_r_prolog()));
  }

  function r_short2($args){
    return $this->emb_values(array(''=>$this->_r_prolog()));
  }

  function r_def($args){ 
    $res = array(''=>$this->_r_prolog());
    $keys = array('prop','others','meta','dc');
    $res = $this->_stdV(ac($keys),$res);
    return $this->emb_values($res);
  }
}

class opc_debug_obj_opc_fw extends opc_debug_obj{
  protected $type_abr = 'fw';
  
  function _r_prolog(){
    return $this->emb_int(($this->val->xhtml?'xhtml':'HTML') . ' ' . $this->val->charset,'span','inf');
  }
   
  function r_short1($args){ 
    return $this->emb_values(array(''=>$this->_r_prolog()));
  }

  function r_short2($args){
    return $this->emb_values($this->_stdV(array('key'=>'key'),array(''=>$this->_r_prolog())));
  }

  function r_def($args){ 
    $keys = array('key'=>'key','layout'=>'layout');
    $res = $this->_stdV($keys,array(''=>$this->_r_prolog()));
    switch(def($args,'v',1)){
    case 5:
    case 4:
    case 3:
    case 2: 
      $res['pointers'] = implode('%-s2-%',$this->val->pointers);
      break;
    }
    return $this->emb_values($res);
  }
}

class opc_debug_obj_opc_auth extends opc_debug_obj{
  protected $type_abr = 'OAS';
  
  function r_def($args){
    if($this->val->running()){
      $res = array('user'=>$this->val->user);
    } else $res = array('run'=>'NO');
    return $this->emb_values($res);
  }
}

class opc_debug_obj_opc_textData extends opc_debug_obj{
  protected $type_abr = 'textData';

  function r_data(){
    return self::int_auto($this->val->data,'q_table');
  }
  
  function q_parts($part,$args){
    $this->res = $this->emb(array('typ'=>'Text-Table','val'=>$this->r_data()));
  }
  
  function r_def($args){
    $res = array('mode'=>$this->val->mode,
		 'file'=>$this->val->_fn,
		 'count'=>count($this->val->data),
		 );
    return $this->emb_values($res);
  }
}


class opc_debug_obj_opc_status extends opc_debug_obj{
  protected $type_abr = 'status';
  
  function r_short1($args){
    return $this->emb_values($this->_stdV(array('status'=>'status')));
  }

  function r_def($args){ 
    $keys = array('status'=>'status');
    $res = $this->_stdV($keys);
    switch(def($args,'v',1)){
    case 99:
      $res['msgs'] = $this->raw_table($this->val->get_msgs());
    }
    return $this->emb_values($res);
  }

  function q_parts_msg($args){
    self::$ext['embed'] = 'div'; 
    $res = $this->raw_table($this->val->get_msgs());
    $this->res = $this->emb(array('val'=>$this->type_abr . ': ' . $this->val->status,
				  'add'=>$res));
    return ;
  }
}


class opc_debug_obj_opc_pg extends opc_debug_obj{
  protected $type_abr = 'pgdb';

  function _r_prolog(){
    if(is_null($this->val->db)) return $this->emb_int('[NC]','span','NA');
    return $this->emb_int($this->val->dbuser . '@' . $this->val->dbname,'span','inf');
  }
  
  function r_type2($args){ 
    if(is_null($this->val->db)) return $this->type_abr;
    return $this->type_abr . ': ' . $this->val->dbname;
  }

  function r_short1($args){ 
    return $this->_r_prolog();
  }

  function r_def($args){ 
    if(is_null($this->val->db)) return $this->emb_int('[NC]','span','NA');
    $res = array(''=>$this->_r_prolog());
    $keys = array('dbenc'=>'enc','phpenc'=>'php');
    $res = $this->_stdV($keys,$res);
    return $this->emb_values($res);
  }

  function q_parts_str($args){
    self::$ext['embed'] = 'div'; 
    $res = $this->raw_table($this->val->structure);
    $this->res = $this->emb(array('val'=>$this->type_abr . ': ' . $this->val->status,
				  'add'=>$res));
    return ;
  }

}


class opc_debug_obj_opc_ht2 extends opc_debug_obj{
  protected $type_abr = 'ht2-data';

  function _r_prolog(){
    return $this->emb_int(($this->val->xhtml?'xhtml':'HTML') . ' ' . $this->val->charset,'span','inf');
  }
   
  function r_short1($args){ 
    return $this->emb_values(array(''=>$this->_r_prolog()));
  }

  function r_short2($args){
    return $this->emb_values($this->_stdV(array(),array(''=>$this->_r_prolog())));
  }

  function r_def($args){ 
    $keys = array();
    $res = $this->_stdV($keys,array(''=>$this->_r_prolog()));
    switch(def($args,'v',1)){
    case 5:
    case 4:
    case 3:
    case 2: 
      break;
    }
    return $this->emb_values($res);
  }

  function structer($what='s'){
    if(!is_array($this->val->str) or count($this->val->str)==0) return $this->type_abr . ': empty';
    $coln = array('typ','tag','key','sibl','par','chld','data');
    $res = '<table><tr>';
    foreach($coln as $ck) $res .= "<th>$ck</th>";
    $res .= "</tr>\n";
    foreach($this->val->str as $key=>$crow){
      $res .= '<tr>';
      if($crow['typ']=='txt')
	$crow['data'] = self::_auto($this->val->data[$key],array('mth'=>'q3'));
      else if($crow['typ']=='ph')
	$crow['data'] = self::_auto($this->val->data[$key],array('mth'=>'q3'));
      else 
	$crow['data'] = NULL;
      if(is_null($crow['prv'] and is_null($crow['nxt']))) $crow['sibl'] = NULL;
      else $crow['sibl'] = "$crow[prv]/$crow[nxt]";

      if(is_null($crow['lcl'])) $crow['chld'] = NULL;
      else $crow['chld'] = "$crow[fcl]-$crow[lcl]";

      foreach($coln as $ck) $res .= "<td>$crow[$ck]</td>";
      $res .= "</tr>\n";
    }
    $res .= "</table>\n\n";
    return $res;
  }

}

class opc_debug_obj_opc_sobj extends opc_debug_obj{
  protected $type_abr = 'setbag';

  function r_short1($args){
    if(!$this->val->running) return $this->emb_int('[NC]','span','NA');
    if(!$this->val->loaded)
      return $this->emb_values(array('loaded'=>'[F]'));
    return $this->emb_values(array('loaded'=>$this->val->key));;
  }

  function r_short2($args){
    if(!$this->val->running) return $this->emb_int('[NC]','span','NA');
    if(!$this->val->loaded)
      return $this->emb_values(array('loaded'=>'[F]','src'=>$this->val->source_def));   
    return $this->emb_values(array('loaded'=>$this->val->key,'src'=>$this->val->source_def));   
  }
  
  function r_def($args){ 
    if(!$this->val->running) return $this->emb_int('[NC]','span','NA');
    if($this->val->loaded){
      $res = array('loaded'=>$this->val->key,
		   'data'=>$this->val->data);
    } else $res = array('loaded'=>'[F]');
    switch(def($args,'v',1)){
    case 5:
      $res['syntax'] = $this->val->syntax;
    case 4:
      $res['attrs'] = $this->val->attrs;
    case 3:
      $res['status'] = $this->val->err->status;
      $res['src'] = $this->val->source_def;

    }
    return $this->emb_values($res);  
  }

}

class opc_debug_obj_opc_ht2o_tabledeluxe extends opc_debug_obj{
  protected $type_abr = 'table++';

  function r_short1($args){
    return $this->emb_values(array('id'=>$this->val->id));
  }

  function r_short2($args){
    return $this->r_short1($args);
  }
  
  function r_def($args){ 
    $res = array();
    $ar = array('id'=>'ID',
		'filter_current'=>'Filter',
		'sort_current'=>'Sort',
		'page_current'=>'Page',
		);
    $res = $this->_stdV($ar,$res);
    return $this->emb_values($res);  
  }
}

class opc_debug_obj_opc_test extends opc_debug_obj{
  protected $type_abr = 'test';

  function r_short1($args){
    return $this->emb_values(array('cmd'=>$this->val->cmd,
				   '#items'=>count($this->val->items),));
  }
  function r_def($args){ 
    $res = array('Type'=>$this->val->cmd);
    foreach($this->val->items as $ck=>$cv)
      $res['item-' . $ck] = $cv;
    return $this->emb_values($res);  
  }
}  

class opc_debug_obj_opc_args extends opc_debug_obj{
  protected $type_abr = 'args';

  function r_short1($args){
    return $this->emb_values(array('id'=>$this->val->id()));
  }
  
  function r_def($args){ 
    $res = array('Id'=>$this->val->id(),
		 'data'=>$this->val->getn(),
		 'piles'=>implode(' ',$this->val->get_piles_keys()),
		 );
    $ar = array();
    $res = $this->_stdV($ar,$res);
    switch(def($args,'v',0)){
    case 5:
      self::$ext['embed'] = 'div'; 
      foreach($this->val->get_piles_keys() as $cp)
	$res['pile-' . $cp] = $this->val->piles_get_all($cp);
      break;
    }
    return $this->emb_values($res);  
  }

}

class opc_debug_obj_opc_tmpl1 extends opc_debug_obj{

  function r_def($args){
    $ov = get_object_vars($this->val);
    $cs = array();
    $cls = get_parent_class(get_class($this->val));
    while($cls){
      $cs[] = $cls;
      $cls = get_parent_class($cls);
    }
    if(!empty($cs)) $ov[':class-structer:'] = implode(' - ',$cs);
    unset($ov['_status_msgs']);
    return self::int_auto($ov,'q_def');
  }

}

class opc_debug_obj_opc_argspile_generic extends opc_debug_obj{
  protected $type_abr = 'args-pile';

  function r_short1($args){
    return $this->emb_values(array('ID'=>$this->val->id()));
  }
  
  function r_def($args){ 
    $res = array('id'=>$this->val->id(),
		 'data'=>$this->val->getn());
    $ar = array();
    $res = $this->_stdV($ar,$res);
    return $this->emb_values($res);  
  }
}

class opc_debug_obj_opc_result extends opc_debug_obj{
  protected $type_abr = 'opc_result';

  function r_short1($args){
    $res = array('State'=>$this->val->State,
		 'Res'=>$this->val->Result);
    return $this->emb_values($res);  
  }

  function r_short2($args){
    $res = array('State'=>$this->val->State,
		 'Res'=>$this->val->Result,
		 'Msgs'=>$this->val->Msgs);
    return $this->emb_values($res);  
  }
  
  function r_def($args){ 
    $res = array('State'=>$this->val->State,
		 'Res'=>$this->val->Result,
		 'Msgs'=>$this->val->Msgs);
    $res = array_merge($res,get_object_vars($this->val));
    return $this->emb_values($res);  
  }
}

class opc_debug_obj_opc_attr extends opc_debug_obj{
  protected $type_abr = 'opc_attr';

  function r_short1($args){
    return $this->emb_values(array('type'=>$this->val->type));
  }

  function r_short2($args){
    return $this->r_short1($args);
  }
  
  function r_def($args){ 
    $res = array('type'=>$this->val->type,
		 'value'=>$this->val->get());
    return $this->emb_values($res);  
  }
}

class opc_debug_obj_opc_attrs extends opc_debug_obj{
  protected $type_abr = 'opc_attrs';

  function r_short1($args){
    return $this->emb_values(array('tag'=>$this->val->tag()));
  }

  function r_short2($args){
    return $this->r_short1($args);
  }
  
  function r_def($args){ 
    $res = array('tag'=>$this->val->tag());
    $res = array_merge($res,
		       $this->val->getn(),
		       $this->val->getn('Post'),
		       $this->val->getn('Pre')
		       );
    return $this->emb_values($res);  
  }
}


class opc_debug_obj_opc_ht3t extends opc_debug_obj{
  protected $type_abr = 'tag3';

  function r_short1($args){
    return $this->emb_values(array('tag'=>$this->val->tag));
  }

  function r_short2($args){
    return $this->r_short1($args);
  }
  
  function r_def($args){ 
    $res = array('tag'=>$this->val->tag);
    $res = array_merge($res,
		       $this->val->getn()
		       );
    return $this->emb_values($res);  
  }
}


/** Standards */
function q1(){return opc_debug::auto(func_get_args());}
function q2(){return opc_debug::auto(func_get_args());}
function q3(){return opc_debug::auto(func_get_args());}
function q4(){return opc_debug::auto(func_get_args());}
function q5(){return opc_debug::auto(func_get_args());}
function q6(){return opc_debug::auto(func_get_args());}
function q7(){return opc_debug::auto(func_get_args());}
function q8(){return opc_debug::auto(func_get_args());}
function q9(){return opc_debug::auto(func_get_args());}

/** default */
function qq(){return opc_debug::auto(func_get_args());}
/** table */
function qt(){return opc_debug::auto(func_get_args());}
/** show multiple items */
function qw(){return opc_debug::auto(func_get_args());}
/** show a specified parts (second arg) */
function qp(){return opc_debug::auto(func_get_args());}

/** show and die */
function qd(){return opc_debug::auto(func_get_args());}
/** echo in all cases */
function qe(){return opc_debug::auto(func_get_args());}
/** 'show' as html comment */
function qh(){return opc_debug::auto(func_get_args());}
/** return only */
function qr(){return opc_debug::auto(func_get_args());}

/** shows just the clock and ellasped time*/
function qc(){return opc_debug::auto(func_get_args());}
/** shows just the current line */
function ql(){return opc_debug::auto(func_get_args());}
/** shows all calling lines */
function qk(){return opc_debug::auto(func_get_args());}
/** Arguments of the current function */
function qa(){return opc_debug::auto(func_get_args());}

/** Mesage only */
function qm(){return opc_debug::auto(func_get_args());} // generl message
function qx(){return opc_debug::auto(func_get_args());} // a open 'todo' point
function qy(){return opc_debug::auto(func_get_args());} // feature not yet coded
function qz(){return opc_debug::auto(func_get_args());} // A 'should not happen' situation

/** switch */
function qs(){return opc_debug::auto(func_get_args());} 



?>
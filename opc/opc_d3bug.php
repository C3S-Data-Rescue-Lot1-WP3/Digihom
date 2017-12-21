<?php
  /* aktuelle Baustelle
   *static export -> reines Textergebnis um es anderswo einzubinden
   */

  /* ================================================================================
     interfaces 
     ================================================================================ */

  // opc_d3-Target
interface opi_d3t{
  function receive($res);
  }


/* ================================================================================
   Displayer-Class
   ================================================================================ */
abstract class opc_d3d {

  static protected $css_cls = 'd3';
  static protected $css_style = NULL;
  // border-main bg-main color-main bg-hf color-hf
  static protected $css_colors = array(0=>array('bc'=>'blue',
						'mbg'=>'#9FD854',
						'mfc'=>'black',
						'tbg'=>'black',
						'tfc'=>'#9FD854',
						'sbg'=>'#D2ECAF',
						'sfc'=>'black',
						),
				       1=>array('bc'=>'red',
						'mbg'=>'#040',
						'mfc'=>'white',
						'tbg'=>'grey',
						'tfc'=>'white',
						'sbg'=>'white',
						'sfc'=>'grey',
						));


  protected $d3c = NULL;
  protected $tar = NULL;

  static function css(){
    $cls = self::$css_cls;
    $tmp = array(".$cls"=>'border: solid 2px blue; background-color: #dd0; margin: 4px; padding: 0px 4px;',
		 ".$cls-hf"=>'padding: 0px 4px;',
		 ".$cls-phptype"=>'font-style: italic;',
		 ".$cls-rec"=>'padding-right: 5px; padding-left: 5px;',
		 ".$cls-items_sep"=>'padding-right: 10px; padding-left: 10px;',
		 ".$cls-items_key"=>'padding-right: 5px; padding-left: 3px; font-weight: bold;',
		 ".$cls-atom"=>'padding-right: 5px; padding-left: 5px; margin-left: -3px; margin-right: -3px;',
		 ".$cls-ml"=>'padding-left: 2px; padding-right: 2px; margin-left: 3px; margin-right: 3px;',
		 ".$cls-err"=>'background-color: red; color: white; padding: 0 2px;',
		 ".$cls-key"=>'font-weight: bold;',
		 );
    foreach(self::$css_colors as $ck=>$cv){
      $tmp[".$cls-col$ck"] = "border-color: $cv[bc];" . ($ck>0?' border-width: 2px 12px;':'')
	. "background-color: $cv[mbg]; color: $cv[mfc]";
      $tmp[".$cls-col$ck .$cls-hf"] = "background-color: $cv[tbg]; color: $cv[tfc]";
      $tmp[".$cls-col$ck .$cls-mark"] = "background-color: $cv[sbg]; color: $cv[sfc]";
    }
    return $tmp;
  }

  function __construct(&$d3c,&$tar){
    $this->d3c = $d3c;
    $this->tar = $tar;
  }

  abstract function _output($head,$foot,$main);
  abstract function add_get($key,$def=NULL);

  function output($data){
    $head = array('title'=>$this->add_get('title'),
		  );
    $head = array_filter($head);
    $foot = array();
    $foot  = array_filter($foot);
    $main = is_null($data)?NULL:$this->output_main($data);
    return $this->send($this->tar,$this->_output($head,$main,$foot));
  }

  function output_main($data){
    return $this->outByType($data);
  }

  function outByType($val){
    if(is_array($val) and isset($val['typ'])) $tmp = $val;
    else $tmp = array('typ'=>'val','value'=>$val);
    $mth = 'out__' . $tmp['typ'];
    if(method_exists($this,$mth)) return $this->$mth($tmp);
    return $this->out__def($tmp['value']);
  }

  function out__def($val){
    return var_export($val,TRUE);
  }

  function out__list($val){
    $res = array();
    foreach($val['value'] as $key=>$val){
      $res[] = $this->outByType($val);
    }
    return $this->implode_main($res);
  }

  function out__items($val){
    $cls = self::$css_cls;
    $res = array();
    foreach($val['value'] as $key=>$val){
      $res[] = "<span class='$cls-items_key'>$key</span>: "
	. "<span class='$cls-items_val'>$val</span>: ";
    }
    return implode("<span class='$cls-mark $cls-items_sep'>$this->sep_items</span> ",$res);
  }

  function out__slist($val){
    $cls = self::$css_cls;
    $res = array();
    foreach($val['value'] as $key=>$val){
      $res[] = "<span class='$cls-items_val'>$val</span>";
    }
    return implode($this->sep_nl,$res);
  }



  function out__sep($dat){
    return '----';
  }

  function out__val($dat){
    $res = $dat['value'];
    if(isset($dat['key'])) $res = $dat['key'] . ': ' . $res;
    if(isset($dat['rec'])) $res = '[' . $dat['rec'] . '] ' . $res;
    return $res;
  }

  protected function implode_main($data){
    return implode("\n",$data);
  }

  function send($tar,$res){
    try{
      if(is_string($tar)){
	switch($tar){
	case 'return':
	  return $res;

	case 'echo':
	  echo $res;
	  flush();
	  return $res;

	default:
	  throw new Exception("Unkown opc_d3 target type '$tar'"); 
	}
      } else if($tar instanceof opi_d3t){
	return $tar->receive($res);
      } else if ($tar instanceof opc_ptr_ht2){
	return $tar->add($res);
      } else throw new Exception('Unkown opc_d3 target: ' . get_class($tar));
    } catch (Exception $ex){} 
    trigger_error($ex->getMessage());
  }
  }



// For HTML ================================================================================
class opc_d3d_html extends opc_d3d {
  public $sep_items = '&diams;';
  public $sep_ml = '\n';
  public $sep_tab = '\t';
  public $sep_rf = '\r';
  public $sep_space = '\r';
  public $sep_nl = '<br/>';

  function out__atom($dat){
    $cls = self::$css_cls;
    return "<span class='$cls-atom $cls-mark'>[$dat[value]]</span>";
  }

  function out__ml($dat){
    $cls = self::$css_cls;
    $dat['value'] = implode("<span class='$cls-ml $cls-mark'>$this->sep_ml</span>",$dat['value']);
    return $this->out__val($dat);
  }

  function out__val($dat){
    $cls = self::$css_cls;
    switch(def($dat,'key','-')){
    case 'phptype': $dcls = $cls . '-phptype'; break;
    default:
      $dcls = $cls . '-val';
    }

    if(!is_object($dat['value']) or method_exists($dat['value'],'__toString'))
      return "<span class='$cls-rec $cls-mark'>[$dat[rec]]</span><span class='$dcls'>$dat[value]</span>";
    return "<span class='$cls-rec $cls-mark'>[$dat[rec]]</span><span class='$dcls $cls-err'>NO STRVAL</span>";
  }

  function out__list_flat($dat){
    $cls = self::$css_cls;
    $tmp = array();
    foreach($dat['value'] as $val){
      $tres = isset($val['key'])?"<span class='$cls-atom $cls-key'>($val[key]):&nbsp;</span>":'';
      $tres .= "<span class=''>$val[val]</span>";
      $tmp[] = $tres;
    }
    $dat['value'] = implode("<span class='$cls-ml $cls-mark'>$this->sep_items</span>",$tmp);
    return $this->out__val($dat);
  }

  function _output($head,$res,$foot){
    $cls = self::$css_cls;
    $col = $cls . '-col' . $this->d3c->add_get('color',0);
    $dbt_fl = '@' . $this->d3c->dbt('fileline') . ' - ' . $this->d3c->ts();

    if(is_null($res) and empty($foot) and empty($head)){
      $tmp = '@' . $this->d3c->dbt('line') . ' - ' . $this->d3c->ts();
      return "<span title='$dbt_fl' class='$cls $col'><span class='$cls-hf'>$tmp</span></span>";
    }    

    if(empty($foot) and empty($head)) return "<span title='$dbt_fl' class='$cls $col $cls-data'>$res</span>";

    $res = "<span class='$cls-data'>$res</span>";
    if(!empty($head)){
      $tmp = array();
      foreach($head as $ck=>$cv) $tmp[] = "<span class='$cls-hf-$ck'>$cv</span>";
      $head = "<div class='$cls-hf'>" . implode("\n &emsp;",$tmp) . "</div>\n";
    } else $head = '';
    if(!empty($foot)){
      $tmp = array();
      foreach($foot as $ck=>$cv) $tmp[] = "<span class='$cls-hf-$ck'>$cv</span>";
      $foot = "<div class='$cls-hf'>" . implode("\n &emsp;",$tmp) . "</div>\n";
    } else $foot = '';
    return "<div title='$dbt_fl' class='$cls $col'>\n $head\n $res\n $foot\n</div>";
  }

  function add_get($key,$def=NULL){
    $res = $this->d3c->add_get($key,$def);
    if(is_array($res)) return implode('&emsp; ',$res);
    return $res;
  }
}


// For raw text ================================================================================
class opc_d3d_raw extends opc_d3d {
  static $bar = '----------------------------';
  static $hf = '--- ';
  static $nl = "\n[debug] ";

  function _output($head,$res,$foot){
    $bar = self::$bar;
    $hf = self::$hf;
    $nl = self::$nl;

    $cls = self::$css_cls;

    if(is_null($res) and empty($foot) and empty($head)){
      $tmp = '@' . $this->d3c->dbt('line') . ' - ' . $this->d3c->ts();
      return "$nl-- $tmp\n";
    }

    if(empty($foot) and empty($head)) return "$nl$res\n";

    if(!empty($head)){
      $tmp = array();
      foreach($head as $ck=>$cv) $tmp[] = "$hf$ck: $cv";
      $head = implode("$nl",$tmp) . "$nl";
    } else $head = '';
    if(!empty($foot)){
      $tmp = array();
      foreach($foot as $ck=>$cv) $tmp[] = "$hf$ck: $cv";
      $foot = implode("$nl",$tmp) . "$nl";
    } else $foot = '';

    if(is_null($res))
      return "$nl$bar$nl$head$foot$bar\n";
    return "$nl$bar$nl$head$res$nl$foot$bar\n";
  }

  function add_get($key,$def=NULL){
    $res = $this->d3c->add_get($key,$def);
    if(is_array($res)) return implode(' --- ',$res);
    return $res;
  }

}


/* ================================================================================
   The real Object, embeds the given value
   ================================================================================ */
class opc_d3o {
  public $obj = NULL;
  public $typ = NULL;
  public $kind = NULL;
  public $data = array();
  public $d3c = NULL;
  
  // recognition for object
  protected $rec = '*';
  
  function __construct(&$obj,&$d3c,$typ,$kind){
    $this->obj = &$obj;
    $this->d3c = &$d3c;
    $this->typ = $typ;
    $this->kind = $kind;
    $this->size = NULL;
    $this->init();
  }

  function init(){}

  function collect_data($add,&$found){
    $mth = 'col__' . $add['kind'];
    if(!method_exists($this,$mth)){
      $found = 1;
      return NULL;
    }

    $res = $this->$mth($add,$found);
    $res['rec'] = $this->rec;
    return $res;
  }

  function col__rec($add,&$found){
    return array('typ'=>'atom','value'=>$this->rec);
  }

  function col__phptype($add,&$found){
    return array('typ'=>'val','key'=>'phptype','value'=>$this->typ);
  }

  function col__abr($add,&$found){
    return $this->col__value($add,$found);
  }

  function col__masked($add,&$found){
    return $this->col__value($add,$found);
  }

  function col__value($add,&$found){
    return array('typ'=>'val','value'=>$this->obj);
  }

  function col__callstack($add,&$value){
    $this->d3c->dbt();
    $res = array();
    $tmp = array();
    $keys = array_keys($this->d3c->dbt);
    foreach($keys as $ckey)
      if(!$this->dbt_is_internal($this->d3c->dbt[$ckey])) break;
    foreach($keys as $ckey){
      $cline = $this->d3c->dbt[$ckey];
      $fn = def($cline,'file','');
      if($fn){
	$tres = sprintf('%04d',$cline['line']) . '@' . $this->dbt_filename($fn);
      } else continue;
      $tmp[] = $tres;
    }
    $this->d3c->add_add('title',"Callstack");
    return array('typ'=>'slist','value'=>$tmp);
  }

  function col__callarg($add,&$value){
    $this->d3c->dbt();
    foreach($this->d3c->dbt as $cline){
      if(!$this->dbt_is_internal($cline)) break;
    }
    $fct = def($cline,'function','?');
    $cls = def($cline,'class','');
    $typ = def($cline,'type','');
    $args = def($cline,'args',array());
    $n = count($args);
    $nt = $n==0?' no arguments':($n==1?' 1 argument':" $n arguments");
    $ct = empty($cls)?$fct:"$cls$typ$fct";
    $this->d3c->add_add('title',"called $ct with $nt");
    return array('typ'=>'slist','value'=>$args);
  }

  function dbt_is_internal($cline){
    if(preg_match('{^[wq].$}',def($cline,'function'))) return TRUE;
    $ccls = def($cline,'class');
    if(preg_match('{^opc_d3..?$}',$ccls)) return TRUE;
    if(!empty($ccls) and (is_subclass_of($ccls,'opc_d3d')?1:0
			  or is_subclass_of($ccls,'opc_d3o')?1:0
			  or is_subclass_of($ccls,'opc_d3c')?1:0)) return TRUE;
    return FALSE;
  }

  function dbt_filename($file){
    static $dr = '-';
    static $lfn = array();
    if($dr=='-') $dr = def($_SERVER,'DOCUMENT_ROOT','') . '/';
    if(substr($file,0,strlen($dr))==$dr) $file = '~/' . substr($file,strlen($dr));
    $cfn = explode('/',$file);
    $rfn = $cfn;
    
    for($i=($cfn[0]=='~'?1:0);$i<min(count($lfn)-1,count($cfn)-1);$i++)
      if($cfn[$i]==$lfn[$i]) $rfn[$i] = '.';
    $lfn = $cfn;
    return preg_replace('{\.php$}','',implode('/',$rfn));
  }
}

/* --------------------------------------------------------------------------------
   Objects that can only be shown as atomic
   -------------------------------------------------------------------------------- */
abstract class opc_d3oa extends opc_d3o {
  function col__value($add,&$found){
    return array('typ'=>'atom','value'=>$this->rec);
  }
}

class opc_d3o_null extends opc_d3oa {  
  protected $rec = 'N'; 
}

class opc_d3o_bool extends opc_d3oa {
  protected $rec = 'B';
  function init(){
    $this->rec = $this->obj?'T':'F';
  }
}

class opc_d3o_resource extends opc_d3oa {
  protected $rec = 'R';
  function init(){
    $tmp = strval($this->obj);
    $this->rec .= preg_replace('{^.*(#.*)$}','$1',$tmp);
  }
}


/* --------------------------------------------------------------------------------
   numeric Objects with a sign
   -------------------------------------------------------------------------------- */
abstract class opc_d3on  extends opc_d3o {
  function init(){
    if($this->obj===0)    $this->rec .= '0';
    else if($this->obj>0) $this->rec .= '+';
    else                  $this->rec .= '-';
  }
}

class opc_d3o_int extends opc_d3on {
  protected $rec = 'i';
  
  function block($txt,$s){
    $n = strlen($txt);
    $res = substr($txt,0,$n==$s?$s:($n%$s));
    $txt = substr($txt,$n==$s?$s:($n%$s));
    while(Strlen($txt)){
      $res .= ' ' . substr($txt,0,$s);
      $txt = substr($txt,$s);
    }
    return $res;
  }

  function col__masked($add,&$found){
    $res = array('dec'=>$this->obj,
		 'bin'=>$this->block(sprintf('%s%b',$this->obj<0?'-':'',abs($this->obj)),4),
		 'hex'=>$this->block(sprintf('%s%X',$this->obj<0?'-':'',abs($this->obj)),4));
    return array('typ'=>'items','value'=>$res);
  }
}

class opc_d3o_float extends opc_d3on {
  protected $rec = 'f';

  function col__abr($add,&$found){
    return array('typ'=>'val','value'=>sprintf('%.3f',$this->obj));
  }

  function col__masked($add,&$found){
    return array('typ'=>'val','value'=>sprintf('%e',$this->obj));
  }

}


class opc_d3o_num extends opc_d3on {
  protected $rec = 'n';
}



/* --------------------------------------------------------------------------------
   others
   -------------------------------------------------------------------------------- */


class opc_d3o_string extends opc_d3o {
  protected $rec = 'S';
  function init(){
    $this->size = strlen($this->obj);
    $this->rec .= $this->size;
  }

  function col__value($add,&$found){
    if(strlen($this->obj)==0) 
      return array('typ'=>'atom','value'=>$this->rec);
    return array('typ'=>'val','value'=>$this->obj);
    
  }

  function col__abr($add,&$found){
    if(strlen($this->obj)==0) 
      return array('typ'=>'atom','value'=>$this->rec);
    if(strlen($this->obj)>60)
      return array('typ'=>'val','value'=>substr(htmlspecialchars($this->obj),0,60) . '...');
    return $this->col__value($add,$found);
  }

  function col__masked($add,&$found){
    if(strlen($this->obj)==0) 
      return array('typ'=>'atom','value'=>$this->rec);

    return array('typ'=>'val','value'=>htmlspecialchars($this->obj));    
  }


}


class opc_d3o_array extends opc_d3o {
  protected $rec = 'A';
  protected $obja = array();

  function init(){
    $this->size = count($this->obj);
    $this->rec .= $this->size;
    foreach($this->obj as $ck=>$cv)
      $this->obja[$ck] = $this->d3c->o2d($cv);
  }
  
  function col__phptype($add,&$found){
    $res = array();
    foreach($this->obja as $ck=>$cv) $res[$ck] = $cv->col__phptype($add,$fnd);
    return array('typ'=>'list','value'=>$res,'size'=>count($this->obja));
  }

  function col__abr($add,&$found){
    if(count($this->obj)==0) return $this->col__rec($add,$found);
    $tmp = array();
    $keys = array_keys($this->obj);
    $n = 0;
    foreach($keys as $key){
      $ctmp = array();
      if(!is_numeric($key) or $key!=$n++) $ctmp['key'] = $key;
      $ctmp['val'] = opc_d3c::export($this->obj[$key],'rec');
      $tmp[] = $ctmp;
    }
    return array('typ'=>'list_flat','value'=>$tmp);
  }
}

class opc_d3o_obj extends opc_d3o {
  function init(){
    $this->rec = get_class($this->obj);
    if($this->obj instanceof countable) 
      $this->rec .= '(' . count($this->obj) . ')';
  }

}

/* ================================================================================
   Target
   ================================================================================ */
/*
 * use as first argument of opc_d3c::set-ts
 */


// sends output to a file
class opc_d3t_file implements opi_d3t{
  protected $byname = NULL;
  protected $fi = NULL;

  function __construct($file,$mode='a'){
    if(is_resource($file)){
      $this->byname = FALSE;
      $this->fi = $file;
    } else {
      $this->byname = TRUE;
      $this->fi = fopen($file,$mode);
    }
  }

  function __destrcut(){
    if($this->byname) fclose($this->fi);
  }

  function receive($res){
    fwrite($this->fi,$res . "\n");
  }
}






/* ================================================================================
   main class for handling the call
   ================================================================================ */

abstract class opc_d3c {
  static public $dcls = array('obj'=>'opc_d3o_obj');

  protected static $target = 'echo';
  protected static $syntax = 'html';

  protected $add = array();
  protected $data = array();

  protected $add_typ = array('title'=>'array');

  abstract function split_obj($args);
  abstract function collect_obj_data();
  abstract function data_get(&$n);


  // set target and syntax for output
  static function set_ts($tar,$syn='html'){
    self::$target =&$tar;
    self::$syntax = $syn;
  }

  
  public $dbt = NULL;
  public static $dbt_ignore = array('function'=>array(),
				    'file'=>array(__FILE__),
				    'class'=>array(__CLASS__));


  /* 
   * dbt_ignore saves 'lines' which will be ignored/skipped
   *  where file would took the frist line with none of the saved values
   *  and class/function took the last line with one of this values
   * which
   *  0: first occurence
   *  >0: skip n further lines (still regarding dbt_ignore)
   *  <0: collect n lines  (still regarding dbt_ignore)
   */
  function dbt($what='fileline',$which=0){
    if(is_null($this->dbt)) $this->dbt = debug_backtrace();
    $dbt = $this->dbt;
    $dbt[] = array(); // necessary since some ignore rules should return the line before
    $res = array();
    $keys = array_keys(self::$dbt_ignore);
    $line = array();
    while(count($dbt)>0){
      $lline = $line;
      $line = array_shift($dbt);
      
      $tres = $line;
      if(in_array(def($line,'file','-'),    self::$dbt_ignore['file']))     continue;
      $tres = $lline;
      if(in_array(def($line,'class','-'),   self::$dbt_ignore['class']))    continue;
      if(in_array(def($line,'function','-'),self::$dbt_ignore['function'])) continue;

      $tmp = $this->dbt_show($tres,$what);
      if($tmp===FALSE)      continue;
      else if($which==0)    return $tmp;
      else if($which>0)   { $which--; continue;}
      else if($which==-1) { $res[] = $tmp;  return $res;}
      else                { $res[] = $tmp; $which++; }
    }
    return $res;
  }

  protected function dbt_show($line,$what){
    //if($what!='full') qq($this->dbt_show($line,'full'));
    switch($what){
    case 'full':
      return def($line,'class','C') . '->' . def($line,'function','f') . ' ---- '
	. def($line,'file','F') . '@' . def($line,'line','L');
    
    case 'line': 
      if(!isset($line['line'])) return FALSE;
      return $line['line'];

    case 'fileline': 
      if(!isset($line['line'])) return FALSE;
      return def($line,'file','?') . '@' . $line['line'];
    }
    return 'unkown task: ' . $what;
  }

  function ts(){
    $mt = microtime(TRUE);
    return date('H:i:s.') . substr(round($mt-floor($mt),2),2);
  }

  function __construct($args,$kind,$add=array()){
    $i = $this->split_obj($args);
    $this->add = $this->args2add(array_slice($args,$i),$kind,$add);
    $this->collect_obj_data();
    $this->output();
  }

  static function export($obj,$kind){
    return 21345;
  }

  function output(){
    $cls = def(self::$dcls,'d3d_' . self::$syntax,'opc_d3d_' . self::$syntax);
    $d3d = new $cls($this,self::$target);
    $d3d->output($this->data);
  }

  function add_get($key,$def=NULL){
    if(!isset($this->add[$key])) return $def;
    $res = $this->add[$key];
    switch($key){
    case 'title': return empty($res)?$def:$res;
    }
    return $this->add[$key];
  }

  function add_add($key,$val){
    switch(def($this->add_arrays,$key,'single')){
    case 'single': $this->add[$key] = $val; break;
    case 'array':
      if(isset($this->add[$key])) $this->add[$key][] = $val;
      else                        $this->add[$key] = array($val);
      break;
    }
  }


  function args2add($args,$kind='value',$def=array()){
    $res = array('title'=>array());
    foreach($args as $arg){
      if(is_int($arg) and $arg<0){
	$res['color'] = -$arg;
      } else if(is_string($arg)){
	if(preg_match('{^-\w+:}',$arg)){
	  $this->arg2add(substr($arg),$res);
	} else $res['title'][] = $arg;
      }
    }
    $res = array_merge($def,$res);
    if(!isset($res['kind']) and !is_null($kind)) $res['kind'] = $kind;
    return $res;
  }

  static function o2t($obj,$num=TRUE,$ocls=TRUE){
    if(is_null($obj))     return 'null';
    if(is_bool($obj))     return 'bool';
    if(is_int($obj))      return 'int';
    if(is_float($obj))    return 'float';
    if(is_numeric($obj))  return $num?'num':'string';
    if(is_string($obj))   return 'string';
    if(is_array($obj))    return 'array';
    if(is_resource($obj)) return 'resource';
    if(is_object($obj))   return $ocls?get_class($obj):'obj';
    else                  return 'unkown';
  }

  function o2d($obj){
    $iso = is_object($obj);
    if($iso){
      $typ = get_class($obj);
      do {
	if($typ===FALSE){
	  $cls = def(self::$dcls,'obj','opc_d3o');
	  break;
	} else if(isset(self::$dcls[$typ])){
	  $cls = self::$dcls[$typ];
	  break;
	} else if(class_exists('opc_d3o_' . $typ)){
	  $cls = 'opc_d3o_' . $typ;
	  break;
	}
	$typ = get_parent_class($typ);
      } while(TRUE);
      $typ = get_class($obj);
      $kind = 'obj';
    } else {
      $typ = self::o2t($obj);
      $cls = 'opc_d3o_' . $typ;
      if(!class_exists($cls)) $cls = def(self::$dcls,$typ,'opc_d3o');
      if(is_array($obj)) $kind = 'array';
      else if(is_resource($obj)) $kind = 'resource';
      else $kind = 'scalar';
    }
    return new $cls($obj,$this,$typ,$kind);
  }

}

// For only informations ================================================================================
class opc_d3ci extends opc_d3c{
  function split_obj($args){ 
    return 0; 
  }
  
  function collect_obj_data(){
    $this->data = NULL;
  }

  function data_get(&$n){ 
    $n = -2;
    return NULL;
  }
  
}

// For list informations ================================================================================
class opc_d3cl extends opc_d3c{
  function split_obj($args){ 
    return 0; 
  }
  
  function collect_obj_data(){
    $this->d3 = $this->o2d(array(2,3));
    $this->data = $this->d3->collect_data($this->add,$found);
  }

  function data_get(&$n){ 
    $n = -2;
    return NULL;
  }
  
}


// For simple objects ================================================================================
class opc_d3cs extends opc_d3c{

  protected $obj = NULL;
  protected $d3 = NULL;

  function split_obj($args){
    if(count($args)==0) return 0;
    $this->obj = array_shift($args);
    $this->d3 = $this->o2d($this->obj);
    return 1;
  }

  function collect_obj_data(){ 
    if(is_object($this->d3))
      $this->data = $this->d3->collect_data($this->add,$found);
    else
      $this->data = NULL;
  }

  function data_get(&$n){
    $n = -1;
    return $this->d3->data;
  }


}


// For multiple objects ================================================================================
class opc_d3cm extends opc_d3c{
  protected $objm = array();
  protected $d3m = array();


  function split_obj($args){
    $n = count($args);
    for($i=0;$i<$n;$i++){
      if(is_string($args[$i]) and substr($args[$i],0,2)==='--') break;
      $this->objm[$i] = &$args[$i];
      $this->d3m[$i] = $this->o2d($this->objm[$i]);
    }
    if($i<$n) $args[$i] =  substr($args[$i],1);
    return $i;
  }

  function collect_obj_data(){ 
    if(count($this->d3m)==0) return $this->data = NULL;
    $tmp = array();
    foreach($this->d3m as $ck) {
      $tmp[] = $ck->collect_data($this->add,$found);
      $tmp[] = array('typ'=>'sep');
    }
    array_pop($tmp);
    $this->data = array('typ'=>'list','value'=>$tmp);
  }

  function data_get(&$n){
    if($n>=count($this->d3m)==0){
      $n = -2;
      return NULL;
    }
    $res = $this->d3m[$n]->data;
    if(++$n==count($this->d3m)) $n = -1;
    return $res;
  }


}

/* ================================================================================
   direct calls
   ================================================================================*/

function w1(){ $ar = func_get_args(); new opc_d3cs($ar,'rec');}
function w2(){ $ar = func_get_args(); new opc_d3cs($ar,'abr');}
function w3(){ $ar = func_get_args(); new opc_d3cs($ar,'value');}
function w4(){ $ar = func_get_args(); new opc_d3cs($ar,'masked');}
function ww(){ $ar = func_get_args(); new opc_d3cm($ar,'value');}
function wi(){ $ar = func_get_args(); new opc_d3ci($ar,'info');}
function wa(){ $ar = func_get_args(); new opc_d3cl($ar,'callarg');}
function wk(){ $ar = func_get_args(); new opc_d3cl($ar,'callstack');}

?>
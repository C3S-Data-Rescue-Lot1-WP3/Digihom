<?php
  /*
   Beziehung zwischen tool und fw verbessern, was wo (title, xhtml, charset, author, version, datum, laufort ...)
   title als array setzen (mehrere Längen)
   dir verbessern es gibt viel mehr Varianten (web-url, php-root, ressource etc; inkl iaceth/inhouse beachten)
   */
  /** basic class to encapsulate enviroment and to provide some basic functions */
class _tools implements _tools_, ArrayAccess, IteratorAggregate{

  static protected $fw = NULL;
  
  /** array including all settings 
   * will be filled with the values from the construction array
   * defaults are saved in set_def
   * key: should be a unique identifier for your project (useg eg inisde $_SESSION)
   * title: what it says
   * titles: array of different titles named with their length in form of XS, S, M, L or XL
   *         will be accessed using titles
   * xhtml: boolean: use xhtml instead of html
   * charset: on of utf-8 or iso-8859-1
   * lng: language short (2-letter code)
   * major: greater project
   * minor: sub of major
   * mode: current use (devel/test/oeprational)
   * inclpat: named array for estimating a directory by the filename
   *         array(dir-name=>file-pattern,...)
   * cls_tool: class name of the default tool
   * dirs: list of important dirs. used by method dir, det_file ...
   *    set by default: this (using __FILE__)
   *                    script (using $_SERVER['SCRIPT_FILENAME'])
   * rel: realtiv directory between wr and script
   */


  protected $set = array();

  /** Default values for set(tings) array */
  protected $set_def = array('key'=>'xyz',
			     'title'=>'new project',
			     'titles'=>array(),
			     'xhtml'=>TRUE,
			     'charset'=>'utf-8',
			     'lng'=>'en',
			     );

  /** used in create_external: to distinguish between different type
   * it will be filled with the _def_extobj array through the complete class tree 
   *
   * opc-auto:  class name is like opc_[type]_[subtype] 
   * [other]: method ce_[name] will be called
   */
  public $extobj = array();
  public $_def_extobj = array('ticket'=>'opc-auto',
			      'sobj'=>'opc-auto',
			      'setbag'=>'opc-auto',
			      'auth'=>'opc-auth',
			      'logit'=>'opc-auto',
			      'um'=>'opc-um',
			      'txp'=>'cls:opc_textprovider',
			      'logit'=>'opc-auto',
			      'pg'=>'opc_pg', // -> opc_pg
			      'pgdb'=>'pg_res', // pg-ressource
			      );
  
  /** sets of files which are included together
   * a @-content refers to another item in this list
   */
  public $incl_sets = array('default'=>array('opl_basic','opc_debug','@ht2','opi_basic'),
			    'fw'=>array('@default','opc_fw','opc_info','opc_head','opc_tmpl',
					'opc_textprovider'),
			    'ht3'=>array('opc_tstore','opc_ht3p','opc_ht3.*'),
			    'afw'=>array('@default','opc_afw','opc_info'),
			    'form'=>array('opc_ht2form'),
			    'pgdb'=>array('opc_pg'),
			    'ticket'=>array('opc_sobj(_.*)?','opc_ticket(_.*)?'),
			    'auth'=>array('opc_auth(_.*)?'),
			    'um'=>array('opc_tmpl','ops_array','opc_um[uds]?(_.*)?'),
			    'ht2o_list'=>array('opc_ht2o','opc_ht2o_list','opc_tstore'),
			    'ht2d_um'=>array('@um','opc_ht2d','opc_ht2d_um','@ht2o_list'),
			    'ht2d_log'=>array('@um','opc_ht2d','opc_ht2d_log'),
			    'ht2o_all'=>array('ops_array','opc_tstore','opc_ht2o(_.*)?'),
			    'ht2'=>array('opc_ht2','opc_ht2p','opc_status','opc_attr'),
			    'item'=>array('opc_status','opc_item','opc_item_basic','opc_item_composite'),
			    );

  public $cls_map = array('fw'=>'opc_fw');


  /** just to cache dir-quests */
  protected $cache_dir = array();

  /* filled up by scandir
   * array(key=>array(file=>dir,file=>dir ...))
   */
  public $file_list = array();

  function init__fw(&$fw){
    self::$fw = $fw;
  }

  function __set($key,$val){
    $this->set[$key] = $val;
  }

  function __get($key){

    if(array_key_exists($key,$this->set)) return $this->set[$key];
    if(function_exists('trg_err'))  trg_err(1,"unkown tool variabel $key");
    else                            trigger_error("unkown tool variabel '$key'");
  }
  
  function set($key,$val){
    $this->set[$key] = $val;
  }

  function add_inclpat($key,$pat){
    $this->set['inclpat'][$key] = $pat;
  }

  function add_dir($key,$dir=NULL){
    if(!is_array($key)) $key = array($key=>$dir);
    $dirs = array_merge($this->set['dirs'],$key);
    $rs = array_map(create_function('$x','return "%$x%";'),array_keys($dirs));
    while(array_reduce($dirs,create_function('$n,$x','return $n+(strpos($x,"%")===FALSE?0:1);'),0)){
      $dirs = str_replace($rs,$dirs,$dirs,$hits);
      if($hits==0) return FALSE;
    }
    $this->set['dirs'] = $dirs;
    return TRUE;
  }

  function get($key,$def=NULL){
    return isset($this->set[$key])?$this->set[$key]:$def;
  }

  function get_all(){
    return $this->set;
  }

  /** constructor
   * $set: named array with different settings (will be saved in $set)
   * if you want to use the funtion dir with mode 'rel' you have
   *   to submit here a array named dirs with the webroot directory under key 'wr'
   *    eg.: $set = array('dirs'=>array('wr'=>'/www/'))
   *    dontf forget the closing '/'
   *   this array may contain other key-value pairs which can used by method dir
   *   using their key as what argument.
   */
  function __construct($set){
    if(!is_array($set)) $set = array('key'=>$set);
    $this->set = array_merge($this->set_def,$set);
    $inf = isset($_SERVER['SCRIPT_FILENAME'])?pathinfo($_SERVER['SCRIPT_FILENAME']):array();
    $this->set['dirs']['this'] = substr(__FILE__,0,strrpos(__FILE__,'/')+1);
    $this->set['dirs']['script'] = isset($inf['dirname'])?$inf['dirname']:NULL;
    $dr = explode('/',$this->set['dirs']['wr']);
    $dc = explode('/',$this->set['dirs']['script']);
    while(count($dr)>0 and count($dc)>0 and $dr[0]==$dc[0]){
      array_shift($dc);
      array_shift($dr);
    }

    if(count($dc)>0) $this->set['rel'] = str_repeat('../',count($dc)) . implode('/',$dr);
    else             $this->set['rel'] = './';
    $this->req_files('opl_basic');
    $this->extobj = defca('_def_extobj',$this);
  }

  function title($kind='L'){
    switch($kind){
    case 'XL': return deff($this->set['titles'],'XL','L','M',$this->set['title']);
    case 'L': return deff($this->set['titles'],'L','M',$this->set['title']);
    case 'M': return def($this->set['titles'],'M',$this->set['title']);
    case 'S': return deff($this->set['titles'],'S','M',$this->set['title']);
    case 'XS': return deff($this->set['titles'],'XS','S','M',$this->set['title']);
    }
  }

  
  /** ================================================================================
   * dir/file functions
   * ================================================================================ */

  /** returns the right directory
   * @argument $what str: name as saved in dirs
   * @argument $mode str: 
   *  'rel' -> usable as relative path to the current
   *  'abs' -> usable as absolute path
   *  'web'/'webs' -> usable as url-link (incl http:// or https://';
   * @return string or NULL
   */ 
  function dir($what,$mode='rel'){
    if(!isset($this->set['dirs'][$what])) return NULL;
    $res = $this->set['dirs'][$what];
    $key = $what . '::' . $mode;
    if(isset($this->cache_dir[$key])) return $this->cache_dir[$key];

    switch($mode){
    case 'abs':
      while(strpos($res,'../')!==FALSE)
	$res = preg_replace('|/[^./][^/]*/\.\./|','/',$res);
      break;

    case 'rel': 
      $wr = $this->set['dirs']['wr'];
      $wn = strlen($wr);
      if($what=='wr'){
	$res = $this->set['rel'];
      } else if(substr($res,0,$wn)==$wr){
	$res = $this->set['rel'] . substr($res,$wn);
      } else {
        $rd = explode('/',$res);
	for($i=0;$i<count($rd);$i++){
          $part = implode('/',array_slice($rd,0,$i)) . '/';
	  if(substr($wr,0,strlen($part))!=$part) break;
	}
	$res = str_repeat('../',$i-2) . implode('/',array_slice($rd,$i-1));
      }
      break;

    case 'web':
    default:
      $wr = $this->set['dirs']['wr'];
      $wn = strlen($wr);
      if($what=='wr')  
	$res = '';
      else if(substr($res,0,$wn)==$wr) 
	$res = substr($res,$wn);
      else 
	return NULL;
      $res = $this->set['dirs']['bu'] . $res;
      if(strpos($res,'://')===FALSE){ // there is no protocoll
	$res = ($mode=='web'?'http':$mode) . '://' . $res;
      } else if($mode!='web'){ // mode overdrive protocol
	$res = $mode . substr($res,strpos($res,'://'));
      }
      break;
    }
    $res = $this->strip_2slash($res);
    $this->cache_dir[$key] = $res;
    return $res;
  }

  /* scans a dir using an optional pattern and saves the result in file_list under $key
   * 
   * useful if the same file (name) may exist in multiple directories to define a default
   * see get_file for use the resulting file_list
   * @param string/array(strings) on or more directories to scan
   * @param string $key: key used to save the results in file_lists (default: '*')
   * @param bool $hard: if true a later found file will override an earlier one
   * @param string $pat: pattern to filter the result of a single scandir (default: '/^[^.#].*[^#~]$/')
   * @return: array which is saved under key in file_list
   */
  function scandir($dir,$key='*',$hard=TRUE,$pat=NULL){
    foreach((array)$dir as $cd){
      $files = preg_grep(is_null($pat)?'/^[^.#].*[^#~]$/':$pat,scandir($cd));
      if(count($files)==0) continue;
      $files = array_combine($files,array_map(create_function('$x','return "' . $cd . '/" . $x;'),$files));
      if($hard) 
	$this->file_list[$key] =  array_merge(def($this->file_list,$key,array()),$files);
      else
	$this->file_list[$key] =  array_merge($files,def($this->file_list,$key,array()));
    }
    return def($this->file_list,$key,array());
  }

  /* gets a file from file_list. Returns file name (incl path) or FALSE */
  function get_file($file,$key='*'){
    if(!isset($this->file_list[$key])) return FALSE;
    if(!isset($this->file_list[$key][$file])) return FALSE;
    return $this->file_list[$key][$file];
  }

  /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

  /** repalces a '@NAME/' in $fn with the right directory using method dir */
  function det_file($fn,$check=0,$mode='rel'){
    while(strpos($fn,'@')!==FALSE){
      $path = preg_replace('#^.*@([^/]+)/.*$#','$1',$fn);
      $fn = str_replace('@' . $path . '/',$this->dir($path,$mode),$fn);
    }
    if($check===0 or $mode!='abs') return $fn;
    if(file_exists($fn)) return $fn;
    if($check==1) trg_err(1,"unkown file: $fn",E_USER_WARNING);
    return FALSE;
  }

  function filename_dirincl($fn){
    foreach($this->dirs as $ck=>$cv) $fn = str_replace('%' . $ck . '%',$cv,$fn);
    return $fn;
  }

  /* --------------------------------------------------------------------------------
   * extended require files
   * -------------------------------------------------------------------------------- */

  /** enhance require_file
   * @argument str|array-str:
   *  if it starts with '@' -> use list saved in /** incl_sets
   *  of no '.' is present use dir() to find the directory and add '.php'
   */
  function req_files($filelist='@default',$warn=2){
    foreach((array)$filelist as $fl){
      if(strpos($fl,'%')!==FALSE) $fl = $this->filename_dirincl($fl);

      if(substr($fl,0,1)==='@'){ // ------------------------------ file is just a name of a file list
	$this->req_files($this->incl_sets[substr($fl,1)],$warn);
      } else if(preg_match('/[*+[?]/',$fl)){ // ------------------------------------- file is a pattern
	$dir = $this->pat2dir($fl);
	$dir = $dir==FALSE?'.':$this->dir($dir,'abs');
	foreach(preg_grep('/' . $fl . '\.php$/',scandir($dir)) as $cf) 
	  $this->req_file($dir . $cf,$warn);
      } else if(strpos($fl,'.')!==FALSE){  // -------------------------------- file is given directly
	$this->req_file($fl,$warn);
      } else { // -------------------------------------------------- path is determined by inclpat (.php is added too)
	$dir = $this->pat2dir($fl);
	if($dir===FALSE) $this->req_file($fl,$warn);
	else $this->req_file($this->dir($dir,'abs') . $fl . '.php',$warn);
      }
    }
  }

  /** sub of req_files */
  public function req_file($file,$warn){
    $file = str_replace('//','/',$file);
    if(file_exists($file)) return require_once($file);
    if(strpos($file,'#')!==FALSE) return FALSE;
    if($warn>0) trigger_error("tool->req_files failed for '$file'",$warn!==1111?E_USER_NOTICE:E_USER_ERROR);
    return FALSE;
  }

  public function strip_2slash($file){
    $file = preg_replace('|//+|','/',$file);
    return preg_replace('|(:/)([^/])|','$1/$2',$file);
  }


  /* uses the pattern defined in inclpat to define the directory of the asked file */
  public function pat2dir($file){
    if(!isset($this->set['inclpat'])) return FALSE;
    foreach($this->set['inclpat'] as $key=>$val) if(preg_match($val,$file)) return str_replace('//','/',$key);
    return FALSE;
  }

  /* uses the pattern defined in inclpat to define the directory of the asked file */
  public function pat2file($file,$kind='rel'){
    $res = $this->pat2dir($file);
    if($res===FALSE) return $file;
    $res = $this->dir($res,$kind);
    return $res  . (substr($res,-1,1)=='/'?'':'/') . $file;
  }


  /* ================================================================================ END */

  /**   function load_connection
   * Loads connection setting from a file using a identifier
   * Each connection is on a single line in this file (key: connection)
   * @param $kind string one of: pgdb, ldap
   * @param $key key to identify the asked connection
   * @return connection string or integer (Err-Code)
   */
  function load_connection($kind,$key=NULL,$silent=FALSE){
    $key = str_replace(array('%mode%'),array($this->mode),$key);
    $data = $this->_load_con_data($kind,$key);
    if(is_numeric($data)) return $data;
    switch($kind){
    case 'con': $res = unserialize(trim($data)); break;
    case 'ldap': $res = unserialize(trim($data)); break;
    default:
      return $data;
    }
    if($res!==FALSE) return $res;
    if(!$silent) 
      trigger_error("Invalid connection definition for: '$key'");
    return 5;
  }

  protected function _load_con_data($kind,$key){
    switch($kind){
    case 'con':  return $this->_load_con_data_file($this->dir('hid','abs') . '.connections',$key);
    case 'pgdb': return $this->_load_con_data_file($this->dir('hid','abs') . '.dblogin',$key);
    case 'ldap': return file_get_contents($this->dir('hid','abs') . '.ldapcon',$key);
    }
    return 1;
  }
  
  protected function _load_con_data_file($file,$key,$silent=FALSE){
    if($key=='') qk();
    if(!file_exists($file)) return 3;
    $con = file($file);
    if(is_null($key)){
      if(count($con)==1) return $con[0];
      return $con;
    } else {
      $res = preg_grep('/\s*' . $key . '\s*:/',$con);
      if(count($res)==1){
	$res = array_shift($res);
	return trim(preg_replace('/\s*' . $key . '\s*:/','',$res));
      } else {
	if(!$silent) 
	  trigger_error("Connection definition not found: '$key'");
	return 2;
      }
    }
  }
  

  /**
   * creates external object
   * returns object or integer (err-code)
   *  1: unkown type, 2 create failed
   */
  function create_external($type/*,init [,...] */){
    $ar = func_get_args();
    return $this->create_external_array($type,array_slice($ar,1));
  }

  function create_external_array($type,$add){
    if(is_array($type)) list($type,$subtype) = $type; else $subtype = NULL;
    if(isset($add[0]) and is_array($add[0]) and isset($add[0]['tool:use'])){
      $restype = $add[0]['tool:use'];
      unset($add[0]['tool:use']);
    } else if(isset($this->extobj[$type])) {
      $restype = $this->extobj[$type];
    } else $restype = $type;

    switch($restype){
    case 'opc-auth':
      $this->req_files('opc_auth(_.*)?');
      $obj = new opc_auth($this,def($add,0),def($add,1));
      return $obj;



      /* match classes which fit name convention opc_[type]_[subtype] */
    case 'opc-auto':
      if(is_null($subtype)) 
	$cls = 'opc_' . $type;
      else
	$cls = 'opc_' . $type . '_' . $subtype;
      if(!class_exists($cls)) $this->req_files($cls);

      $obj = new $cls();
      if(!is_object($obj)) return 2;
      call_user_func_array(array($obj,'init'),$add);
      if(method_exists($obj,'init__tool')) $obj->init__tool($this);
      if(method_exists($obj,'init__fw')) $obj->init__fw(self::$fw);
      return $obj;

    default:
      if(substr($restype,0,4)=='cls:'){
	$cls = substr($restype,4);
	$this->req_files($cls);
	return new $cls($this,def($add,0,array()));
      }

    }
    $mth = 'ce_' . str_replace('-','_',$restype);
    return method_exists($this,$mth)?$this->$mth($subtype,$add):1;
  }

  protected function ce_opc_um($sub,$add){
    $this->req_files('opc_umd?(_.*)?');
    $set = def($add,0,array());
    if(!isset($set['umds'])){
      $set['umds'] = array('opc-devel'=>array('cls'=>'opc_umd_db_pg',
					      'label'=>'opc devel',
					      'connect'=>array('db'=>'um-devel'),
					      ));
    }
    $cls = def($add,'cls',def($this->cls_map,'opc_um','opc_um'));
    unset($add['cls']);
    return new $cls($this,$set);
  }


  protected function ce_pg_res($sub,$add){
    $con = $add[0];
    if(!preg_match('#dbname\s*=#',$con)) $con = $this->load_connection('pgdb',$con);
    if(!preg_match('#dbname\s*=#',$con)) return 2;
    $res = pg_connect($con);
    if(!is_resource($res)) return 2;
    return $res;
  } 
  
  protected function ce_opc_pg($sub,$add){ 
    $con = $add[0];
    $cls = def($add,'cls','opc_pg');
    if(!preg_match('#dbname\s*=#',$con)) $con = $this->load_connection('pgdb',$con);
    if(!preg_match('#dbname\s*=#',$con)) return 2;
    $res = new $cls($con);
    if(!is_object($res)) return 2;
    if(preg_match('#utf-?8#i',def($this->set,'charset'))) $res->phpenc = 'UTF8';
    else $res->phpenc = 'SQL_ASCII';
    return $res;
  }

  /** Three function to check syntax of (saved) data (see opc_sobj fo a current implementation */
  function syntax_str($syntax){
    $syn = array();
    foreach($syntax as $ck=>$cv) $syn[] = $ck . ':' . implode('.',(array)$cv);
    return '[[' . implode(' ',$syn) . ']]';
  }

  function syntax_split($syntax){
    $res = array();
    $syntax = preg_replace('/.*\[\[(.*)\]\].*$/','$1',$syntax);
    $syntax = preg_replace('/\s+/',' ',$syntax);
    $syntax = array_filter(explode(' ',$syntax));
    foreach($syntax as $cpart){
      $cpart = explode(':',$cpart,2);
      if(count($cpart)!=2) return NULL;
      $res[$cpart[0]] = explode('.',$cpart[1]);
    }
    return $res;
  }

  function syntax_test($test,$syntax){
    $test = self::syntax_split($test);
    if(!is_array($test)) return $test;
    foreach($syntax as $ckey=>$cvalues){
      if(!isset($test[$ckey])) return "Err-5: $ckey missing";
      $ctvalues = $test[$ckey];
      $cvalues = (array)$cvalues;
      $n = count($cvalues);
      if($n>count($ctvalues)) return "Err-6: $ckey has to less elements"; 
      for($i=0;$i<$n;$i++)
	if(strval($cvalues[$i])!=$ctvalues[$i]) return "Err-7: $ckey $i differs"; 
    }
    return TRUE;
  }


  function offsetExists($key)  { return isset($this->set[$key]);}
  function offsetGet($key)     { return $this->set[$key];}
  function offsetSet($key,$val){ $this->set[$key] = $val;}
  function offsetUnset($key)   { unset($this->set[$key]);}
  function getIterator()       { return $this->set;}





  /* ================================================================================
     ================================= Dynamic text =================================
     ================================================================================ */
  function dynamic($org,$values=array()){
    if(is_array($org)){
      foreach($org as $key=>$val)
	if(is_string($val)) $org[$key] = $this->dynamic($val,$values);
      return $org;
    }
    $pos = strrpos($org,'[[');
    while($pos!==FALSE){
      $end = strpos($org,']]',$pos);
      $cpart = explode(':',substr($org,$pos+2,$end-$pos-2),2);
      $fkt = explode('-',$cpart[0],2);
      $args = explode(';',def($cpart,1,''));
      $mth = 'dynamic__' . $fkt[0];
      if(!method_exists($this,$mth)){
	trigger_error('Unkonwn dynamic text: ' . $mth);
	$tmp = '';
      } else $tmp = $this->$mth($fkt,$args,$values);
      $org = substr($org,0,$pos) . $tmp . substr($org,$end+2);
      $pos = strrpos($org,'[[');
    }
    return $org;
  }

  function dynamic__var($fkt,$args,$values){
    return def($values,$args[0]);
  }

  function dynamic__file($fkt,$args){
    $org = $args[0];
    $txt = preg_split('#\{@(.*)\}#',$args[0],-1,PREG_SPLIT_DELIM_CAPTURE);
    $res = array_shift($txt);
    while(count($txt)>0){
      $cpart = explode(':',array_shift($txt),2);
      switch($cpart[0]){
      case 'W': $res .= $this->dir($cpart[1],'web'); break;
      case 'R': $res .= $this->dir($cpart[1],'rel'); break;
      case 'A': $res .= $this->dir($cpart[1],'abs'); break;
      default:
	$res .= implode(':',$cpart);
      }
      if(count($txt)>0) $res .= array_shift($txt);
    }
    return $res;
  }


  // removes all files in cache with the asked prefix which are older than n hours
  function cache_clear($prefix,$hours=1){
    $dir = $this->dir('cache','abs');
    if(!is_writeable($dir)) return 1;
    $n = strlen($prefix);
    $limit = time()-3600*$hours;
    foreach(scandir($dir) as $cf){
      if(substr($cf,0,$n)!=$prefix) continue;
      $cf = $dir . '/' . $cf;
      if(filemtime($cf)<$limit and is_writeable($cf)) unlink($cf); 
    }
  }

  // returns a random filename in cache using prefix, randomnumber (0-999999) and extension
  function cache_newfile($prefix,$ext){
    $dir = $this->dir('cache','rel');
    if(!is_writeable($dir)) return 1;
    $n = 0;
    do{
      $fn = $dir . $prefix . sprintf('%06d',rand(0,999999)) . '.' . $ext;
      if($n++>1000) return 2;
    } while(file_exists($fn));
    return $fn;
  }
}



class opt{
  static $tool = NULL;
  static $tmode = 'unkown';

  protected static $msg_txt = array('err-get'=>'Undefined property %__CLASS__%::$',
				    'notool'=>'Not able to find tool ($_tool_ or _load_tools())',
				    );
  protected static $msg_mth = array('__CLASS__'=>'class_get',
				    );

  public static $en2dt = array('january'=>'Januar',
			       'february'=>'Februar',
			       'march'=>'März',
			       'may'=>'Mai',
			       'june'=>'Juni',
			       'july'=>'Juli',
			       'august'=>'August',
			       'september'=>'September',
			       'october'=>'Oktober',
			       'december'=>'Dezember',
			       'mar'=>'Mrz',
			       'oct'=>'Okt',
			       'dec'=>'Dez',
			       'monday'=>'Montag',
			       'tuesday'=>'Dienstag',
			       'wednesday'=>'Mittwoch',
			       'thursday'=>'Donnerstag',
			       'friday'=>'Freitag',
			       'saturday'=>'Samstag',
			       'sunday'=>'Sonntag',
			       'mon'=>'MO',
			       'tue'=>'DI',
			       'wed'=>'MI',
			       'thu'=>'DO',
			       'fri'=>'FR',
			       'sat'=>'SA',
			       'son'=>'SO');

  // tries to set the tool
  static function tool(){
    if(isset($GLOBALS['_tool_']))
      self::$tool = &$GLOBALS['_tool_'];
    else if(function_exists('_load_tools'))
      self::$tool = _load_tools();
    else
      self::$tool = NULL;
    self::$tmode = is_object(self::$tool)?self::$tool->mode:'ukown';
    return is_object(self::$tool);
  }

  // call require files from tool
  static function dir($what,$mode='rel'){
    if(!self::tool()) return self::r(self::$msg_txt['notool']);
    return self::$tool->dir($what,$mode);
  }

  // call require files from tool
  static function req(/* ... */){
    if(!self::tool()) return self::r(self::$msg_txt['notool']);
    $ar = func_get_args();
    while($ce = array_shift($ar)){
      if(is_array($ce)) $ar = array_merge($ce,$ar);
      else self::$tool->req_files($ce);
    }
  }


  static function date_en2dt($str){
    $str = '=' . $str . '=';
    
    foreach(self::$en2dt as $ck=>$cv){
      $str = preg_replace("/(\W)$ck(\W)/","\$1$cv\$2",$str);
      $ck = ucfirst($ck); $cv = ucfirst($cv);
      $str = preg_replace("/(\W)$ck(\W)/","\$1$cv\$2",$str);
      $ck = strtoupper($ck); $cv = strtoupper($cv);
      $str = preg_replace("/(\W)$ck(\W)/","\$1$cv\$2",$str);
    }
    return substr($str,1,-1);
  }
  
 



  /* handling of cached data ==================================================
   * what: defines what is to do on which way
   *  last digit (action):
   *   0: just test if the file(s) in arg2 are newer (1) than cfile or not (0) ; 2 if a arg2 not accesible
   *   1: load cache (if still valid) returns result or NULL;
   *   2: load cache (allways) returns result or NULL;
   *   9: save data in arg2 to cfile (0: ok, 1: saving failed)
   *  second last digit (translation):
   *   0: no transformation
   *   1: use serialize/unserialize
   *  path: if not null will prepend the default cache path( defined by _tool_) and itself to the cachefile
   */ 
  static function cache($what,$cfile,$arg2,$path=NULL){
    if(!is_null($path)){
      if(!self::tool()) return self::r(self::$msg_txt['notool']);
      $cfile = self::$tool->dir('cache','abs') . $path . $cfile;
    }
    switch($what % 10){
    case 0:
      if(!file_exists($cfile) or !is_readable($cfile)) return 1;
      $ct = filemtime($cfile);
      foreach((array)$arg2 as $fn){
	if(!file_exists($fn) or !is_readable($fn)) return 2;
	if(filemtime($fn)>$ct) return 1;
      }
      return 0;

    case 1:
      if(self::cache(0,$cfile,$arg2)>0) return NULL; // no break
    case 2:
      $tmp = file_get_contents($cfile);
      switch(floor($what/10)){
      case 0: return $tmp;
      case 1: return unserialize($tmp);
      }

    case 9:
      switch(floor($what/10)){
      case 0: $data = $arg2; break;
      case 1: $data = $data = serialize($arg2); break;
      }
      return @file_put_contents($cfile,$data)===FALSE?1:0;
    }
  }






  /* ================================================================================
     error and abcktrace
     ================================================================================ */

  // triggers message (see msg_make for more infos); returns NULL
  static function e(/* */){
    $args = func_get_args();
    list($msg,$etype) = self::msg_make($args);qk();
    trigger_error($msg,$etype);
    return NULL;
  }

  // same as e but first arguemnt is return value
  static function r($ret/* */){
    $args = func_get_args();
    $ret = array_shift($args);
    list($msg,$etype) = self::msg_make($args);
    trigger_error($msg,$etype);
    return $ret;
  }

  static function bt_line ($cls=NULL){
    $cls = array_merge(array('_tools'),(array)$cls);
    $bt = debug_backtrace();
    while(count($bt)>0){
      $cl = array_shift($bt);
      if(!isset($cl['file'])) continue;
      if(isset($cl['class'])){
	if(in_array($cl['class'],$cls)) continue;
	if(count(array_intersect(class_implements($cl['class']),$cls))>0) continue;
      }
      if(preg_match('/^(_{0,3}(get|set)|offset(Exists|Unset|Get|Set))$/',def($bt[0],'function','-'))) continue;
      break;
    }
    $dr = def($_SERVER,'DOCUMENT_ROOT','-');
    $file = substr($cl['file'],0,strlen($dr))?substr($cl['file'],strlen($dr)):$cl['file'];
    return $file . '@' . def($cl,'line','-');
  }

  /* creates message base on array args
   * Elements
   *  string: message text
   *  integer: error type (see trigger_errors)
   *  bool: add backtrace line to  message
   *  array: additional settings (used for trg_line)
   */
  static function msg_make($args){
    self::tool();
    $mode =
    $msg = 'Unkonw error';
    $add = array();
    $typ = E_USER_NOTICE;
    $sbl = (self::$tmode=='test' or self::$tmode=='devel');
    foreach($args as $cv){
      if(is_string($cv))     $msg = $cv;
      else if(is_array($cv)) $add = $cv;
      else if(is_int($cv))   $typ = $cv;
      else if(is_bool($cv))  $sbl = $cv;
    }
    if($sbl){
      $btl = self::trg_line($add);
      if(self::$tmode=='devel') $msg .= ' | ' . $btl;
      else $msg = "<span title='$btl'>$msg</span>";
    }
    
    foreach(self::$msg_txt as $key=>$val)
      $msg = str_replace("%$key%",$val,$msg);
    foreach(self::$msg_mth as $key=>$mth)
      if(strpos($msg,"%$key%")!==FALSE)
	$msg = str_replace("%$key%",self::$mth(),$msg);
    return array($msg,$typ);
  }


  // gets the top most class from dbt (except own)
  static function class_get($txt=NULL) {
    $dbt = debug_backtrace();
    do{
      $tmp = array_shift($dbt);
      if(empty($tmp['class'])) continue;
      if($tmp['class']==__CLASS__) continue;
      if(is_string($txt))
	return str_replace('%__CLASS__%',$tmp['class'],$txt);
      return $tmp['class'];
    } while (count($dbt));
    return $txt;
  }

  static function trg_line($add=array()){
    if(is_string($add)) $add = array('cls'=>$add);
    $cls = def($add,'cls','-');
    $bt = debug_backtrace();
    $cl = array();
    $skip = def($add,'skip',0);
    $cfu = '-';
    while(count($bt)>1){
      try{
	$cfn = def($bt[0],'file'); 
	$ccl = def($bt[0],'class');
	$cfu = def($bt[0],'function');
	if(is_null($cfn)) throw new Exception();
	if(is_string($cls) and $ccl == $cls) throw new Exception();
	if(is_array($cls) and in_array($ccl,$cls)) throw new Exception();
	if(preg_match('/ht2o_msg.php$/',$cfn)) throw new Exception();
	if(in_array($cfu,array('trg_ret','trg_line','trg_err'))) throw new Exception();
	if(preg_match('/^(_{0,3}(get|set)|offset(exists|unset|get|set))$/i',$cfu)) throw new Exception();
	if($skip--) throw new Exception();
	break;
      } catch (Exception $ex){ 
	//qq($bt[0],"$cfn $ccl $cfu");
	array_shift($bt); 

      }
    }
    if(empty($bt)) return NULL;
    $cl = array_shift($bt);
    $dr = def($_SERVER,'DOCUMENT_ROOT','-');
    $file = substr($cl['file'],0,strlen($dr))?substr($cl['file'],strlen($dr)):$cl['file'];
    return $file . '@' . def($cl,'line','-');
  }


}


/*
 * __construct: 
 *  1. Arg: if FALSE (default): ignore all arguments
 *  all other arguments have a default
 */
interface opi_classA {
  function initByArray(&$args,$named=FALSE);
}
?>
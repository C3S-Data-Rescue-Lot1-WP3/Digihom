<?php
/**
 * Setbag allows to save single values in a class based way
 * the exact storage location is defined by the subclasses of opc_setbag
 * that may be a directory (one file per item), a single xml-file, a database ...
 *
 * core information of a setbag-item
 *  data/value: mixed
 * identification (an item is identified by all 3, only key is mandatory)
 *  key: string main identifier
 *  type: string (default '')
 *  uname: username (who made the last changes, default '')
 * additional informations
 *  comment: free text
 * automatic generated information
 *  date_created: timestamp of creation
 *  date_modified: timestamp of last modification
 *  uname_created: uname when item was created
 *  syntax: setbag syntax which was used to write this item
 *  id: internal identificator
 * 
 * @author Joerg Maeder <joerg@toolcase.org>
 * @version 1.0
 * @package setbag
 * 
 * @todo IO format for the date/time values
 */

  /** interface for the setbag package
   * @package setbag
   * @subpackage interface
   */
interface opi_setbag{

  /** Constructor (for details see {@link init()}) */
  public function __construct($bag=NULL,$logbag=NULL,$listmode=0);

  /** initalice of the current object
   * @param mixed $bag information to check and establish the target. See subclasses for details
   * @param mixed $logbag same as $bag but for the log target (NULL to not use it)
   * @param int $listmode (for details {@see listmode})
   */
  public function init($bag=NULL,$logbag=NULL,$listmode=0);

  /** checks if an item exist */
  public function exists($key,$typ=NULL,$uname=NULL);

  /** list all items which match the arguments (@see listmode) 
   * a FALSE value would match any saved item ignoring this part
   *  if listmode is not enabled for this part FALSE is replaced by NULL
   * a NULL value for typ/uname is equal as an empty string (means not used)
   */
  public function listitems($key=FALSE,$typ=FALSE,$uname=FALSE);


  /** removes item */
  public function delete($key,$typ=NULL,$uname=NULL);

  /** set/write item */
  public function set($value,$key,$typ=NULL,$uname=NULL,$comment=NULL);

  /** get/read item-value */
  public function get($key,$typ=NULL,$uname=NULL);

  /** get/read complete item */
  public function complete($key,$typ=NULL,$uname=NULL);
  /** reeads a single item */
  function read_item($key,$typ,$uname,$what);

  /**  @return string comment of the asked item*/
  public function comment($key,$typ=NULL,$uname=NULL);
  /**  @return string current setbag syntax */
  public function syntax($key,$typ=NULL,$uname=NULL);
  /**  @return string  date/time of creation */
  public function date_created($key,$typ=NULL,$uname=NULL);
  /**  @return string date/time of the last modification*/
  public function date_modified($key,$typ=NULL,$uname=NULL);
  /**  @return string type of the asked item */
  public function type($key,$typ=NULL,$uname=NULL);
  /**  @return string user name which made the last modification */
  public function uname($key,$typ=NULL,$uname=NULL);
  /**  @return string username who created the item */
  public function uname_created($key,$typ=NULL,$uname=NULL);
  /**  @return string current key */
  public function key($key,$typ=NULL,$uname=NULL);
  


  /** same as @see listimtes but for the log entries
   * @return array array(logid=>array(logitems))
   */
  public function listlogs($key=FALSE,$typ=FALSE,$uname=FALSE);

  /** reads a single log item
   * @param mixed $logid log item identificatir (as returned by {@link listlogs()})
   * @return array log item
   */
  public function getlog($logid);

  /** removes a single log item
   * @param mixed $logid log item identificatir (as returned by {@link listlogs()})
   * @return bool
   */
  public function dellog($logid);



  /** set/get actions that will be logged
   * use NULL to read the current mode
   * use a string like 'CMaD' to set the mode
   * Uppercase means log, lowercase not
   * Create, Modify, Access, Delete
   * @param null|string NULL or string like 'CMaD'
   * @return string current log mode
   */
  public function log_mode($mode=NULL);

  /**#@+ key conversion between 
   * ktu: array(key,type,uname)
   * str: string: syntax K[key]/T[type/U[uname]
   * int: internal (eg: filename, row identifier or so
   */
  function id_str2int($key);
  function id_str2ktu($key);
  function id_ktu2int($key,$typ=NULL,$uname=NULL);
  function id_ktu2str($key,$typ=NULL,$uname=NULL);
  function id_int2str($key);
  function id_int2ktu($key);
  function id_2ktu($key,$typ=NULL,$uname=NULL);
  /**#@-*/
  
  /** test if the bag is valid and ready*/
  public function testbag($bag);

  /**#@+ test if the asked item is valid */
  function test_key($key);
  function test_uname($uname);
  function test_type($typ);
  function test_comment($comment);
  /**#@-*/

  }

  /** abstract class for setbag including common functionality
   * @package setbag
   * @subpackage main_class
   * @property-read int $listmode
   * @property-read int $chars
   * @property-read int $logchars
   * @property-read int $syntax string to define the current syntax of the items
   * @property-read int $bag_def current used connection definition for the data-bag
   * @property-read int $logbag_def  current used connection definition for the log-bag
   */
abstract class opc_setbag implements opi_setbag {
  /** main object */
  protected $bag = NULL;
  /** object for logging */
  protected $logbag = NULL;

  /** connection definition for the main setbag */
  protected $bag_def = NULL;
  /** connection definition for the log setbag */
  protected $logbag_def = NULL;

  /** connection definition for the log setbag */
  protected $chars = array('U'=>'uname','T'=>'type','K'=>'key');

  protected $logchars = array('a'=>'access','c'=>'created','d'=>'deleted','m'=>'modified');

  /** @var string upper/lower-case string of the letters CMAD to define which processe will be logged 
   * The letters are: Create, Modify, Delete, Access
   */
  protected $logmode = 'cmad'; 

  /** @var int binary coded  
   * controls the behaviour of @see listitems() if an argument is FALSE
   * if a bit is not set FALSE act like a wildchar, otherwise the value has to be empty
   * 0/1: key
   * 1/2: type
   * 2/4: uname
   * */
  protected $listmode = FALSE;
  

  /** @var opc_status status/error object */
  public $err = NULL;


  /**   function __get
   * @access private
   * @param $key name of the asked variable
   * @return aksed value or error 103 is triggered
   */
  function __get($key){
    $tmp = NULL;
    if($this->___get($key,$tmp)) return $this->err->ok($tmp);
    return $this->err->err(103);    
  }

  /** subfunction of magic method __get to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return bool: returns TRUE if key was accepted (result is saved in $res) otherwise FALSE
   */
  protected function ___get($key,&$res){
    switch($key){
    case 'listmode': case 'chars': case 'logchars': case 'syntax':
    case 'bag_def': case 'logbag_def':
      $res = $this->$key; return TRUE;
    }
    return FALSE;
  }

  public function __construct($bag=NULL,$logbag=NULL,$listmode=0){
    $this->err = new opc_status($this->_msgs());
    $this->init($bag,$logbag,$listmode);
  }

  public function init($bag=NULL,$logbag=NULL,$listmode=0){
    if(!is_null($listmode)){
      $tmp = $this->init_listmode($listmode);
      if($tmp>0) $this->err->err($tmp);
    }

    if(!is_null($bag)){
      $tmp = $this->testbag($bag);
      if(is_numeric($tmp)) return $this->err->err($tmp);
      $this->bag = $tmp;
      $this->bag_def = $bag;
    }

    if(!is_null($logbag)){
      $tmp = $this->testbag($logbag);
      if(is_numeric($tmp)) return $this->err->err($tmp);
      $this->logbag = $tmp;
      $this->logbag_def = $logbag;
    }
  }
  
  function init_listmode($listmode){
    if(is_numeric($listmode) and $listmode>=0 and $listmode<8){
      $this->listmode = (int)$listmode;
      return 0;
    } else return 20;
  }



  public function log_mode($mode=NULL){
    if(is_null($mode)) return $this->logmode;
    if(is_null($this->logbag)) return $this->err->err(30);
    if(strtolower($mode)=='cmad'){
      $this->logmode = $mode;
      return $this->err->ok();
    } else return $this->err->err(20);
  }

  protected function _msgs(){
    return array(0=>array('ok','ok'),
		 -1=>array('nothing changed','ok'),
		 1=>array('invalid source','die'),
		 2=>array('unkown source','die'),
		 5=>array('not found','warn'),
		 10=>array('no read list','die'),
		 11=>array('no write list','notice'),
		 20=>array('invalid setting','notice'),
		 21=>array('invalid key','notice'),
		 22=>array('invalid value','notice'),
		 23=>array('invalid remark','notice'),
		 24=>array('invalid type','notice'),
		 25=>array('invalid uname','notice'),
		 30=>array('no log bag set','notice'),
		 31=>array('log does not exist','notice'),
		 103=>array('access denied','int'));
  }

  public function syntax($key,$typ=NULL,$uname=NULL){
    return $this->read_item($key,$typ,$uname,'syntax');
  }
  public function date_created($key,$typ=NULL,$uname=NULL){
    return $this->read_item($key,$typ,$uname,'date_created');
  }

  public function uname_created($key,$typ=NULL,$uname=NULL){
    return $this->read_item($key,$typ,$uname,'uname_created');
  }

  public function date_modified($key,$typ=NULL,$uname=NULL){
    return $this->read_item($key,$typ,$uname,'date_modified');
  }

  public function key($key,$typ=NULL,$uname=NULL){
    return $this->read_item($key,$typ,$uname,'key');
  }

  public function uname($key,$typ=NULL,$uname=NULL){
    return $this->read_item($key,$typ,$uname,'uname');
  }

  public function type($key,$typ=NULL,$uname=NULL){
    return $this->read_item($key,$typ,$uname,'type');
  }

  public function comment($key,$typ=NULL,$uname=NULL){
    return $this->read_item($key,$typ,$uname,'comment');
  }

  public function exists($key,$typ=NULL,$uname=NULL){
    list($key,$typ,$uname) = $this->id_2kt($key,$typ,$uname);
    return $this->_exists($key,$typ,$uname)!==FALSE;
  }

  public function test_key($key){
    if(trim($key)!=$key) return FALSE;
    if(strlen($key)>128) return FALSE;
    if(!preg_match('/^[_\w][\w.-_@: ]*$/',$key)) return FALSE;
    return TRUE;
  }

  public function test_uname($uname){
    if(is_null($uname) or $uname==='' or $uname===FALSE) return TRUE;
    if(strlen($uname)>128) return FALSE;
    if(trim($uname)!=$uname) return FALSE;
    if(!preg_match('/^[_\w][\w.-_ ]*$/',$uname)) return FALSE;
    return TRUE;
  }

  public function test_type($typ){
    if(is_null($typ) or $typ==='' or $typ===FALSE) return TRUE;
    if(trim($typ)!=$typ) return FALSE;
    if(strlen($typ)>128) return FALSE;
    if(!preg_match('/^[_\w][\w.:-_]*$/',$typ)) return FALSE;
    return TRUE;
  }

  public function test_comment($comment){
    if(is_null($comment)) return TRUE;
    return is_string($comment);
  }

  /** adjust the listitems arguments with listmode
   * returns array(key,type,uname) or NULL (if arguments not valid)
   */
  protected function _listitems_prepare($key=FALSE,$typ=FALSE,$uname=FALSE){
    if($key==FALSE and ($this->listmode & 1)==1) $key = '';
    if($typ==FALSE and ($this->listmode & 2)==2) $typ = '';
    if($uname==FALSE and ($this->listmode & 4)==4) $uname = '';
    if(is_null($key)) $key = '';
    if(is_null($typ)) $typ = '';
    if(is_null($uname)) $uname = '';
    if($key==='') return NULL;
    return array($key,$typ,$uname);
  }
  
  /** mode is one of c,m,d,a */
  abstract protected function log($mode,$key,$typ,$uname);
  /** returns FALSE or the internal key */
  abstract protected function _exists($key,$typ,$uname);

  /** is the same for all subclasses */
  final public function id_ktu2str($key,$typ=NULL,$uname=NULL){
    if(!$this->test_key($key)) return 22;
    if(!$this->test_type($typ)) return 24;
    if(!$this->test_uname($uname)) return 25;
    return "K$key/T$typ/U$uname";
  }

  /** is the same for all subclasses */
  final public function id_str2ktu($str){
    $res = array();
    foreach(explode('/',$str) as $ci) $res[$this->chars[substr($ci,0,1)]] = substr($ci,1);
    return $res;	    
  }

}

?>
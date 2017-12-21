<?php
/**
 * Kennung: um bei gleichern/aehnlcihen Daten kein neues ticket erzeugt wird
 *  Beispiel: F5 bei Formular, ansonsten entstehen mehrere sich
 *  konkurrierende Tickets
 */


interface opi_ticket {

  /** test if key is valid */
  function test_key($key);


  function exists($key);
  function create($data=array(),$exp=10,$key=NULL,$type=1);
  function reuse($key,$data=array(),$exp=10); // reuse ticket with given data
  function renew($key,$data=array(),$exp=10); // renew ticket (old data) or create (this data)
  function setback($key,$exp=10); // sets expire date of existing ticket to now + exp minutes
  function remove($key);
  function status($key,&$target=NULL);
  function setstatus($key,$newstatus=NULL);
  function expire($key,$format=0);// when does it expire
  function expired($key); // is expired?
  function valid($key); // is expired?

  function get($key);

  function clear();
  }

abstract class opc_ticket implements opi_ticket{

  protected $source = NULL;
  protected $source_def = NULL;
  // remove ticket if date expire is older than ... minutes
  public $remove_after = 1440; // 1 day

  public $stati = array(-99=>'unkown',
			0=>'created',
			1=>'used',
			2=>'expired',
			);

  /** error/status object */
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
    case 'source_def': case 'running':
      $res = $this->$key; return TRUE;
    }
    return FALSE;
  }

  function __construct($con=NULL){
    $this->err = new opc_status($this->_msgs());
    $this->err->mode_success = 1;
    $this->init($con);
  }

  public function init($source){
    $this->running = FALSE;
    if(is_null($source)) return;
    $tmp = $this->testsource($source);
    if(is_null($tmp)) return $this->err->err(100);

    $this->source = $tmp;
    $this->source_def = $source;
    $this->running = TRUE;
    $this->clean();
  }

  public function running(){return is_object($this->source);}

  protected function _msgs(){
    return array(0=>array('ok','ok'),
		 -99=>array('unkown ticket','ok'),
		 -1=>array('ticket is used','ok'),
		 -2=>array('ticket is expired','ok'),

		 1=>array('ticket is used','notice'),
		 2=>array('ticket is expired','notice'),
		 90=>array('ticket exists already','notice'),
		 91=>array('invalid key','notice'),
		 99=>array('unkown ticket','notice'),

		 100=>array('invalid source','die'),

		 103=>array('access denied','int'),
		 );
  }

  function test_key($key){ 
    if(!is_string($key)) return FALSE;
    return preg_match('/^[_a-z][a-z0-9:_]*$/i',$key);
  }

  function key_clean($key){ 
    return preg_replace('/[^-a-zA-Z0-9:_]/','_',$key);
  }


  public function key_generate(){
    $res = 't' . sprintf('%06d',rand(1,999999));
    while($this->exists($res)) $res = 't' . sprintf('%06d',rand(1,999999));
    return $res;
  }

  public function create($data=array(),$exp=10,$key=NULL,$type=1){
    if(is_null($key) or $key==='') $key = $this->key_generate();
    else if(!$this->test_key($key)) return $this->err->err(91);
    else if($this->exists($key))    return $this->err->err(90);
    $attrs = array('type'=>$type,
		   'status'=>'created',
		   'dat_created'=>date('Y-m-d H:i:s'),
		   'dat_modified'=>date('Y-m-d H:i:s'),
		   'dat_expire'=>date('Y-m-d H:i:s',time()+min($exp,1e7)*60),
		   );
    $this->_save($key,$data,$attrs);
    return $this->err->ok($key);
  }

  public function reuse($key,$data=array(),$exp=10){
    if(is_null($key) or $key==='') return $this->err->err(91);
    if(!$this->test_key($key))     return $this->err->err(91);
    if(!$this->exists($key))       return $this->err->err(99);
    $attrs = array('type'=>$this->getfield($key,'type'),
		   'status'=>'created',
		   'dat_created'=>$this->getfield($key,'dat_created'),
		   'dat_modified'=>date('Y-m-d H:i:s'),
		   'dat_expire'=>date('Y-m-d H:i:s',time()+min($exp,1e7)*60),
		   );
    $this->_save($key,$data,$attrs);
    return $this->err->ok($key);
  }

  /* reuse with old data or create new ticket */
  public function renew($key,$data=array(),$exp=10){
    if(is_null($key) or $key==='') return $this->err->err(91);
    if(!$this->test_key($key))     return $this->err->err(91);
    if($this->exists($key))        return $this->reuse($key,$this->get($key),$exp);
    $attrs = array('type'=>$this->getfield($key,'type'),
		   'status'=>'created',
		   'dat_created'=>$this->getfield($key,'dat_created'),
		   'dat_modified'=>$this->getfield($key,'dat_modified'),
		   'dat_expire'=>date('Y-m-d H:i:s',time()+min($exp,1e7)*60),
		   );
    $this->_save($key,$data,$attrs);
    return $this->err->ok($key);
  }

  /* reuse with old data or create new ticket */
  public function setback($key,$exp=10){
    if(is_null($key) or $key==='') return $this->err->err(91);
    if(!$this->test_key($key))     return $this->err->err(91);
    if(!$this->exists($key))       return $this->err->err(99);
    $attrs = array('type'=>$this->getfield($key,'type'),
		   'status'=>$this->getfield($key,'status'),
		   'dat_created'=>$this->getfield($key,'dat_created'),
		   'dat_modified'=>$this->getfield($key,'dat_modified'),
		   'dat_expire'=>date('Y-m-d H:i:s',time()+min($exp,1e7)*60),
		   );
    $this->_save($key,$this->get($key),$attrs);
    return $this->err->ok($key);
  }

  

  function get($key){
    if(!$this->test_key($key)) return $this->err->err(91);
    if(!$this->exists($key)) return $this->err->err(99);
    if($this->_expired($key)) $this->setstatus($key,'expired');
    return $this->err->ok($this->_get($key)); 
  }

  function setstatus($key,$newstatus=NULL){
    if(!$this->test_key($key)) return $this->err->err(91);
    if(!$this->exists($key)) return $this->err->err(90);
    if(is_numeric($newstatus)) $newstatus = $this->stati[$newstatus];
    $this->setfield($key,'status',$newstatus);
    return $this->err->ok();
  }

  function status($key,&$target=NULL){
    if(!$this->test_key($key)) return $this->err->err(91);
    if($this->exists($key)===FALSE) return $this->err->set(-99,'unkwon');
    $res = $this->getfield($key,'status');

    switch($res){
    case 'created':
      if($this->_expired($key)){
	$this->setstatus($key,'expired');
	return $this->err->set(-2,'expired');
      } else {
	$target = $this->_get($key);
	return $this->err->ok('created');
      }
    case 'expired': return $this->err->set(-2,'expired');
    case 'used': return $this->err->set(-1,'used');
    }
  }

  function expire($key,$format=0){
    if(!$this->test_key($key)) return $this->err->err(91);
    if(!$this->exists($key)) return $this->err->err(90);
    $res = $this->getfield($key,'dat_expire');
    switch($format){
    case 1: return $this->str2time($res); break;
    }
    return $this->err->ok($res);
  }

  function expired($key){
    if(!$this->test_key($key)) return $this->err->err(91);
    if(!$this->exists($key)) return $this->err->err(90);
    return $this->_expired($key);
  }

  protected function _expired($key){
    return time()>$this->str2time($this->getfield($key,'dat_expire'));
  }

  function valid($key){
    if(!$this->test_key($key)) return $this->err->err(91);
    if(!$this->exists($key)) return $this->err->err(90);
    return $this->_valid($key);
  }

  protected function _valid($key){
    $res = $this->getfield($key,'status');
    switch($res){
    case 'created': 
      if($this->_expired($key)) 
	$this->setstatus($key,'expired');
      else 
	return TRUE;
    }
    return FALSE;
  }
  

  function remove($key){
    if(!$this->test_key($key)) return $this->err->err(91);
    $this->_remove($key);
    return $this->err->ok();
  }

  /** internal variations */
  abstract protected function getfield($key,$field);
  abstract protected function setfield($key,$field,$value);
  abstract protected function _save($key,$data,$attrs);
  abstract protected function _get($key);
  abstract protected function _remove($key);
  abstract function clean();

  public function str2time($date){
    if(strlen($date)<10)
      return mktime(0,0,0,
		    (int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4));
    return mktime((int)substr($date,11,2),(int)substr($date,14,2),(int)substr($date,17,4),
		  (int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4));
    
  }

}

?>
<?php

  /* 
     tudu: get_attr bei benützung in create (wie bei _text ...) -> styles korrekt??
     bug: attribute werden nicht abgeholt bei emb-strukturen (eg table/tr)
   */
interface opi_viewer{
  function init();
  function load(&$obj);
  function create();
}



class opc_dist_hto implements opi_dist{
  /** allows direct mapping between type and target class */
  static public $decode_table = array(); 

  /** creates a new opc_hto-Object depending on type */
  static function newobj($type /* ... */){
    $ar = func_get_args();
    $type = array_shift($ar); // remove type/object
    try{
      // get the asked class
      if(is_null($type)){
	return NULL;
      } else if(is_string($type)){
	$cls = defnz(self::$decode_table,$type,'opc_hto_' . $type);
      } else if($type instanceof opc_item){
	$cls = 'opc_htoi' . $type->get_ownclass();
      } else if(is_object($type) and array_key_exists(get_class($type),self::$decode_table)){
	$cls = self::$decode_table[get_class($type)];
      } else throw new Exception('invalid type: ' . var_export($type,TRUE));
      // check this class
      if(!class_exists($cls))
	throw new Exception('unknown class: ' . $cls);
      if(!in_array('opi_viewer',class_implements($cls))) 
	throw new Excepteion($cls . ' does not implement opi_viewer');
      // crate and init the item
      $res = new $cls();
      call_user_func_array(array(&$res,'init'),$ar);
      if(is_object($type)) $res->load($type);
      return $res;

    } catch (Exception $ex) {
      trigger_error('error creating a hto: ' . $ex->getMessage(),E_USER_WARNING);
      return NULL;
    }
  }

  /** similar to {@link newobj}, but returns the result of create instead of the object itself */
  static function createobj(/* ... */){
    $ar = func_get_args();
    if(is_null($ar[0])) return;
    $res = call_user_func_array(array('self','newobj'),$ar);
    return is_object($res)?$res->create():NULL;
  }

}











abstract class opc_hto implements opi_viewer{
  public $ht = NULL;
  protected $obj = NULL;
  protected $err = NULL;
  protected $inf = NULL;
  protected $infp = NULL;

  protected $mode = array();

  public $html_settings = array('class','id','style','name','value');

  // interface relation between this class and the object
  protected $intf_list = array(); // acceptable interaces
  protected $intf_acc = array(); //  accepted interfaces 

  function __construct(){
    $this->err = new opc_status($this->_init_msgs());
  }

  

  function _init_msgs(){
    return array(0=>array('ok','ok'),
		 1=>array('only objkects accepted','type'=>'notice'),
		 2=>array('object does not implement one of the asked interfaces','notice'),
		 3=>array('invalid init arguments','warning'),
		 );
  }


  function get_attr($attrs=array(),$tag=NULL){
    $res = new opc_attrs($tag,$attrs);
    $byp = $this->inf->getSRuel($this->infp->path);
    if(!is_null($byp)) foreach($byp as $cp) $res->setn($cp->get());
    if(!is_null($this->obj->key)){
      $tres = $this->inf->getID($this->obj->key);
      if(is_array($tres)) $res->setn($tres);
    } 
    $tres = array_filter(ops_array::get($this->obj->settings,$this->html_settings));
    $res->setn($tres);
    return $res;
  }

  // ht2-functions ===============================================================================
  function open($tag,$attr){
    $this->infp->open($tag,$attr);
    $attr = $this->get_attr($attr,$tag);
    return is_null($tag)?NULL:$this->ht->open($tag,$attr);
  }

  function close($tag=NULL){
    if(!is_null($tag)){
      $res = NULL;
      while($this->ht->get_type(NULL,1)!=$tag){
	$res = $this->ht->close();
	$this->infp->close();
      }
      return $res;
    } else return $this->infp->close();
  } 

  function tag($tag,$data,$attr){
    $this->open($tag,$attr);
    $this->ht->add($data);
    return $this->close($tag);
  }
  
  function utag($data,$attr,$def='span'){
    return $this->tag(def($attr,'tag',$def),$data,$attr);
  }

  function add($data){
    return $this->ht->add($data);
  }

  function output(){
    $this->create();
    return $this->ht->exp();
  }
  // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

  function load(&$obj){
    if(!is_object($obj)) $this->_err(1);
    $ai = $this->intf_list;
    if(count($ai)>0){
      $this->intf_acc = array_intersect($ai,class_implements($obj));
      if(count($this->intf_acc)==0) 
	return $this->_err(array(2,implode('; ',$this->intf_list)));
    }
    $this->obj = &$obj;
    return $this->_ok();
  }

  function init($set=array()){
    if(!is_array($set) or count($set)==0) return;
    // do this first --------------------------------------------------
    // copy from similar object
    if(isset($set['hto'])) {
      $def = array('ht'=>$set['hto']->ht,
		   'info_sys_key'=>$set['hto']->inf->key,
		   'info_sys_pkey'=>$set['hto']->infp->key,
		   );
      $set = array_merge($def,$set);
    }
    // info system
    $this->inf = opc_infosystem::init(def($set,'info_sys_key','global'));
    $this->infp = $this->inf->get_pointer(def($set,'info_sys_pkey'));

    // ht object
    if(isset($set['ht'])) $this->ht = &$set['ht'];
    else $this->ht = new opc_ht2();

    // all the other settings
    foreach($set as $key=>$val) $this->set_setting($key,$val);
  }

  function set_setting($key,$val){
    switch($key){
    case 'info_sys_key': case 'info_sys_pkey': 
    case 'ht': case 'hto':break; // done in init
    case 'mode': $this->$key = $val;	break;
    default:
      trigger_error('unkown setting: ' . $key);
    }
  }


  function create_direct($data,$tag=NULL,$attr=array(),$emptyok=FALSE){
    if(is_null($data) and $emptyok==FALSE) return;
    $this->open($tag,$attr);
    $set = array('ht'=>&$this->ht,
		 'info_sys_key'=>$this->inf->key,
		 'info_sys_pkey'=>$this->infp->key);
    $res = opc_dist_hto::createobj($data,$set);
    $this->close($tag);
    return $res;
  }



  /* ================================================================================
   Error
   ================================================================================ */
  function status()                { return $this->err->status;}
  function isok()                  { return $this->err->is_success();}
  function msg()                   { return $this->err->msg();}
  function _ok($ret=TRUE)          { return $this->err->ok($ret);}
  function _okA($code=0,$ret=TRUE) { return $this->err->okA($code,$ret);}
  function _okC($code=0)           { return $this->err->okC($code);}
  function _err($code,$ret=NULL)   { return $this->err->err($code,$ret);}
  function _errC($code)            { return $this->err->errC($code);}
  // ================================================================================

  /** returns a standard opc_attrs for different situation
   * typically function to overload
   * @param: string $at: wrap/create
   * @return: an opc_attrs object
   */
  function std_attr($at){
    switch($at){
    case 'wrap': $attr = array('id'=>strval($this->obj->id));break;
    default:
      $attr = array();

    }
    return new opc_attrs(NULL,$attr);
  }

  function create($attr= array()){
    $pos = $this->open('wrap',$this->std_attr('wrap'));
    $keys = preg_grep('/hto_/',array_keys($this->ht[$pos]->getn()));
    $def = array('hto_mode'=>'text','hto_layout'=>'dl');
    $this->mode = array_merge($def,$this->mode,ops_array::key_extract($this->ht[$pos],$keys));
    $mth = 'create_' . $this->mode['hto_mode'];
    $att = $this->std_attr('create');
    $att->setn($attr,'add');
    if(method_exists($this,$mth)) return $this->$mth($att);
    return $this->createDef($att);
  }

  function createDef($attr){
    if(count($attr)==0) 
      return $this->add($this->obj->exp('string'));
    else
      return $this->utag($this->obj->exp('string'),$attr);
  }

  function _create_edit_hidden(){
    if(!is_null($this->obj->id) and ($this->obj instanceof mds_data_element)){
      $this->ht->tag('input',NULL,array('type'=>'hidden','name'=>'typ__' . $this->obj->id,'value'=>get_class($this->obj)));
      $this->ht->tag('input',NULL,array('type'=>'hidden','name'=>'pid__' . $this->obj->id,'value'=>$this->obj->pid));
      $this->ht->tag('input',NULL,array('type'=>'hidden','name'=>'key__' . $this->obj->id,'value'=>$this->obj->key));
    }
  }

  function create_edit($attr){
    $this->_create_edit_hidden();
    $attr->set('value',$this->obj->exp('string','edit'));
    $attr->set('name','fld__' . $this->obj->id);
    $attr->set('size',30);
    return $this->tag('input',NULL,$attr);
  }
}





class opc_htoi_text extends opc_hto{
  protected $intf_list = array(); // acceptable interaces
}



class opc_htoi_email extends opc_hto{
  protected $intf_list = array(); // acceptable interaces

  function createDef($attr){
    $args = $this->obj->getdef();
    list($lnk,$txt) = array_values(ops_array::key_extract($args,array('email','label')));
    if(is_null($lnk)) return $this->tag('span',$txt,array('class'=>'emptyemail'));
    return $this->ht->mail($lnk,$txt,$args,$attr);
  }
}




/** creater for a boolean
 */
class opc_htoi_bool extends opc_hto{
  protected $intf_list = array(); // acceptable interaces

  function create_edit($attr){
    $attr = $this->get_attr();
    $attr->set('typ','checkbox');
    $attr->set('value',$this->obj->exp('string','edit'));
    return $this->tag('input',NULL,$attr);
  }
}




class opc_htoi_list extends opc_hto{
  protected $intf_list = array('opi_object'); // acceptable interaces

  function create(){
    if(!$this->obj->isset) return NULL;
    $data = $this->obj->getdef();
    if(count($data)==0) return;
    
    $this->open('dl',array());
    foreach($data as $item){
      $this->create_direct($item[1],'dt');
      $this->create_direct($item[0],'dd');
    }
    return $this->close('dl');
  }
}



class opc_htoi_table extends opc_hto{
  protected $intf_list = array('opi_object'); // acceptable interaces
  protected $dim = array(0,0);

  protected function _create_row($row,$cells,$rowh,$tag){
    $this->open('tr',array());
    if(is_array($rowh) and !empty($rowh))
      $this->create_direct($rowh[$row],'th',array(),TRUE);
    else if($rowh==TRUE) 
      $this->create_direct(NULL,'th',array(),TRUE);
    
    for($ii=1;$ii<=$this->dim[1];$ii++){
      $this->create_direct(def($cells,$ii),$tag,array(),TRUE);
    }

    $this->close('tr');
  }

  function create(){
    $this->dim = $this->obj->dim();
    if($this->dim===array(0,0)) return;
    $rowh = $this->obj['rowhead']->exp();
    $this->open('table',array());
    if($this->obj['colhead']->size>0) 
      $this->_create_row(0,$this->obj['colhead']->exp(),count($rowh)>0,'th');
    for($cr=1;$cr<=$this->dim[0];$cr++){
      if($this->obj['cellhead']->isused($cr))
	$this->_create_row($cr,$this->obj['cellhead'][$cr]->exp(),count($rowh)>0,'th');
      $this->_create_row($cr,$this->obj['cell'][$cr]->exp(),$rowh,'td');
    }
    return $this->close('table');
  }
}


?>
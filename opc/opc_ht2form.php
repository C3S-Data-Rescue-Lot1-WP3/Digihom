<?php

  /* 
     ticket system falls objekt gesetz $this->hid -> ticket, ticket-nummer als hidden
     select with none or only one option -> shortcuts / special design?
     readonly
     captcha
  */

class opc_ptr_ht2form extends opc_ptr_ht2 {

  /** named array with default values */
  public $def = array(); 

  /** integer or anmed array with integer values
   * defines if the values from def will override the values from the method calls
   *                mode returns v: value; d: value in def; n: NULL; default mode is 0
   * value def-val  0 1 2 3
   * !NULL !NULL    v d v d
   * !NULL  NULL    v v v d
   *  NULL !NULL    d d v d
   *  NULL  NULL    [NULL in any case]
   *  any   NA      [value in any case]
   */
  public $def_mode = 0;
   
  /** collect all hidden values as named array, will be inserted by ffinish */
  public $hid = array();

  /* Ticket system
   * ticket: ticket-object
   * ticket_data: data to be saved inside the ticket
   * ticket_mode: 
   *  0: do nothing by default
   *  1: create ticket
   *  2: reuse ticket
   *  3: renew
   *  4: setback 
   * 1/2 collect data during ftag
   */
  public $ticket = NULL;
  public $ticket_data = array();
  public $ticket_mode = 0;
  public $ticket_key = '__ticket'; // to divide different tickets
  public $ticket_id = NULL;


  /* the current key of the form (-tag) in the ht2-object */
  public $fkey = NULL;
  /* named array with all field keys in ht2
   * the key is the field name
   * the value is an array of ht2-keys
   * see also $array_mode
   */
  public $ikey = array();

  /* action attribute for the form-tag
   * if NULL it will be the cuurent page
   */
  public $action = NULL;

  /* method for the form-tag
   * allowed are get, post and file
   * file will, be transformed to suite a form with data upload
   */
  public $mode = 'post';
  /* allowd values for $mode */
  public $modes = array('get','post','file');

  /* charset for the data entred by the user through the form
   * if NULL the source charste of the link ht2 object is used
   * or (if even NULL) the (target) charset of the ht2.
   */
  public $charset = NULL;


  /* contorls which fields are allowed or need an array syntax to catch multiple values from user input
   * this can be controled per input field type. If not set, the default is 0 (auto-mode)
   * 0: use array sntax if a field name appears more than once or if the attribute 'multiple' is set
   * 1: use allways the array syntax, even if the field-name appears only once inside the form
   * 2: do not allow array syntax, enforce to submit a single value 
   * 3: leave it as it is.
   * 99: trigger Warning if name is used more than once (E_USER_WARNING)
   * This is done while closing the frame (method ffinish)
   */
  public $array_mode = array('radio'=>2,'password'=>99,'file'=>99);


  function init($set){
    $res = parent::init($set);
    if(isset($res['ticket'])) $this->ticket_start($res['ticket']);
    return $res;
  }

  function init_match(&$key,$val){
    if($key==='ticket' or ($val instanceof opi_ticket)) return array('ticket',$val);
    return parent::init_match($key,$val);
  }

  /** overloaded to ensure that the hiddena rguments are placed at the end of the form
   */
  protected function _out($n){
    if(count($this->stack)<$n) return FALSE;
    while($n-->0){
      if($this->key===$this->fkey) $this->ffinish();
      list($this->nxt,$this->key) = array_shift($this->stack);
    }
    return TRUE;
  }

  /* final setting the form attributes and include the hidden arguments */
  protected function ffinish(){
    // adjust if a filed name need brackets [] or not
    foreach($this->ikey as $cn=>$keys){
      switch(def($this->array_mode,$this->data[$keys[0]]->get('type','text'),0)){
      case 0:
	if(count($keys)>1)
	  foreach($keys as $ck) $this->data[$ck]->set('name',$cn . '[]'); 
	else if($this->data[$keys[0]]->get('multiple',FALSE))
	  $this->data[$keys[0]]->set('name',$cn . '[]'); 
	else
	  $this->data[$keys[0]]->set('name',$cn); 
	break;

      case 1: foreach($keys as $ck) $this->data[$ck]->set('name',$cn . '[]'); break;
      case 2: foreach($keys as $ck) $this->data[$ck]->set('name',$cn); break;
      case 3: break;
      case 99 : 
	if(count($keys)>1) trigger_error("'$cn' is used more than once as name, but this is not allowed for this type",E_USER_WARNING); 
	break;
      }
    }
    
    // adjust the method and enctype
    switch(strtolower($this->mode)){
    case 'get': 
      $this->data[$this->fkey]->set('method','get'); 
      break;

    case 'file': // no break
      $this->data[$this->fkey]->set('enctype','multipart/form-data');
    default:
      $this->data[$this->fkey]->set('method','post'); 
    }

    // set the action
    if(is_null($this->action)) $this->data[$this->fkey]->set('action',$this->obj->myself());
    else                       $this->data[$this->fkey]->set('action',$this->action);

    // set charset for input data: instance variable of this > source charset of hts > (target) charset  of ht2
    $this->data[$this->fkey]->set('accept-charset',nz($this->charset,$this->obj->set['charset_source'],$this->obj->set['charset']));
    if(in_array($this->ticket_mode,array(1,2,3,4)) and is_object($this->ticket)){
      if($this->ticket_mode!=4){
	$td = array('fields'=>$this->ticket_data,
		    'hidden'=>$this->hid);
	switch($this->ticket_mode){
	case 1: $this->ticket_id = $this->ticket->create($td); break;
	case 2: $this->ticket->reuse($this->ticket_id,$td); break;
	case 3: $this->ticket->renew($this->ticket_id,$td); break;
	}
      } else $this->ticket->setback($this->ticket_id);
      $this->ftag('input',NULL,array('name'=>$this->ticket_key,'value'=>$this->ticket_id,'type'=>'hidden'));
    } else {
      foreach($this->hid as $ck=>$cv) $this->ftag('input',NULL,array('name'=>$ck,'value'=>$cv,'type'=>'hidden'));
    }
    $this->reset();
  }

  /*
    args:
    1. array -> hid
    2. array -> attrs
    string -> $nxt (if in nxt_allowed) or $action
    bool -> set mode TRUE=>post, FALSE=>get
   */
  function fopen(/* ... */){
    $ar = func_get_args();
    return $this->_fopen($ar);
  }
  
  function _fopen($ar){
    $nxt = 'lcl';
    $attr = array();
    $narr = 0;
    foreach($ar as $ca){
      if(is_bool($ca)){
	$this->mode = $ca?'post':'get';
      } else if(is_string($ca)){
	if(in_array($ca,self::$nxt_allowed)) $nxt = $ca; else $this->action = $ca;
      } else if(is_array($ca)){
	switch($narr++){
	case 0: $this->hid = array_merge($this->hid,$ca); break;
	case 1: $attr = $ca; break;
	}
      }
    }
    $this->fkey = parent::open('form',$attr,$nxt);
    return $this->fkey;
  }


  function fclose($hid=array()){
    if(is_null($this->fkey)) return NULL;
    $this->hid = array_merge($this->hid,$hid); 
    $res = $this->close_to('form','tag',FALSE);
    $this->ffinish();
    $this->close(1); // the form itself
    return $res;
  }

  function reset(){
    $this->hid = array();
    $this->fkey = NULL;
    $this->ikey = array();
    $this->mode = 'post';
    $this->action = NULL;
  }

  /* setzt default werte (uses ops_array:set_ext)
   * key/val may also be array itself
   * primary thought to include form-data
   * uses ops_arra::set_ext/merge_ext
   * mode:
   *  mode (odd modes are equal to the even ones with swap meaning)
   *  0: normal merge (new data overwrites same keys in data)
   *  2: overwrite only if new value is not null
   *  4: ignore internal completly, use new
   */
  function def_add($key,$val=NULL,$mode=0){
    return ops_array::set_ext($this->def,$key,$val,$mode);
  }

  /* similar to def_add using all values in data where key match $pat */
  function def_add_preg($pat,$data,$mode=0){
    $ndat = array();
    $tmp = preg_grep($pat,array_keys($data));
    foreach($tmp as $ck) $ndat[$ck] = $data[$ck];
    return $this->def_add($ndat,NULL,$mode);
  }


  function fsopen($legend=NULL,$attr=array(),$lattr=array()){
    $res = $this->open('fieldset',$attr);
    if(!is_null($legend)) $this->tag('legend',$legend,$lattr);
    return $res;
  }

  function fsclose(){
    return $this->close_to('fieldset');
  }

  function hidden($name,$value=NULL,$attr= array()){
    if(is_array($name)){
      $res = $this->open('span','display:none;');
      foreach($name as $ck=>$cv) $this->ftag('input',array('type'=>'hidden','name'=>$ck,'value'=>$cv));
      return $this->close();
    } else { // maybe id is set for hidden!
      $attr = array_merge($this->obj->auto_attr($attr),array('type'=>'hidden','name'=>$name,'value'=>$value));
      return $this->ftag('input',NULL,$attr);
    }
  }

  function input($name,$value=NULL,$type='text',$attr=array()){
    switch($type){
    case 'textarea': return $this->textarea($name,$value,$attr);
    }
    return $this->ftag('input',NULL,$this->auto_fattr($attr,$name,$value,$type));
  }

  function text($name,$value=NULL,$attr=array()){ 
    return $this->ftag('input',NULL,$this->auto_fattr($attr,$name,$value,'text'));
  }

  function radio($name,$value=NULL,$checked=NULL,$attr=array()){ 
    return $this->ftag('input',NULL,$this->auto_fattr($attr,$name,$value,'radio',$checked));
  }

  function checkbox($name,$value=NULL,$checked=NULL,$attr=array()){ 
    return $this->ftag('input',NULL,$this->auto_fattr($attr,$name,$value,'checkbox',$checked));
  }

  function sbutton($name,$value=NULL,$attr=array()){ 
    return $this->ftag('input',NULL,$this->auto_fattr($attr,$name,$value,'submit')); 
  }

  function password($name,$value=NULL,$attr=array()){ 
    if($this->mode=='get') $this->mode = 'post'; // for security reasons
    return $this->ftag('input',NULL,$this->auto_fattr($attr,$name,$value,'password'));
  }

  function file($name,$attr=array()){ 
    $this->mode = 'file';
    return $this->ftag('input',NULL,$this->auto_fattr($attr,$name,NULL,'file'));
  }

  function label($for,$content='',$attr=array()){ 
    self::setIfNN($attr,'for',$for);
    return $this->tag('label',$content,$attr);
  }
  

  function button($name,$text=NULL,$attr=array()){
    if(is_null($text)) $text = $name;
    $attr['name'] = $name;
    if(!array_key_exists('value',$attr)) $attr['value'] = $text;
    if(!array_key_exists('type',$attr)) $attr['type'] = 'button';
    return $this->ftag('button',$text,$attr);
  }

  function textarea($name,$value=NULL,$attr=array()){
    $value = $this->getval($name,(isset($attr['value']) and is_null($value))?$attr['value']:$value,FALSE);
    return $this->ftag('textarea',$value,$this->auto_fattr($attr,$name,NULL));
  }

  /**
   * oattr is the default attribute array for all options
   * oattrs is alist of attribute array (same key as $list)
   */
  function select($name,$list,$value=NULL,$attr=array(),$oattr=array(),$oattrs=array()){
    $oattr = $this->obj->auto_attr($oattr);
    $oattrs = $this->obj->auto_attr($oattrs);
    $ckey = $this->key;
    list($value,$res) = $this->select_pre($name,$list,$value,$attr);

    $this->in($res);
    foreach($list as $ckey=>$copt) $this->select_opt($value,$ckey,$copt,$oattr,$oattrs);
    $this->out();

    return $res;
  }

 /** like select but for nested lists
   * oattr is the default attribute array for all options
   * oattrs is alist of attribute array (same key as $list)
   */
  function select_nested($name,$list,$value=NULL,$attr=array(),$oattr=array(),$oattrs=array()){
    $oattr = $this->obj->auto_attr($oattr);
    $oattrs = $this->obj->auto_attr($oattrs);
    $ckey = $this->key;
    list($value,$res) = $this->select_pre($name,$list,$value,$attr);

    $this->in($res);
    $keys = array_keys($list);
    $stack = array();
    while(TRUE){
      $ckey = array_shift($keys);
      if(is_null($ckey)){
	if(count($stack)==0) break;
	$this->close();
	list($keys,$list) = array_shift($stack);
      } else {
	$copt = $list[$ckey];
	if(is_array($copt)){
	  $cattr = array_merge($oattr,def($oattrs,$ckey,array()));
	  if(!isset($cattr['label'])) $cattr['label'] = $ckey;
	  $this->open('optgroup',$cattr);
	  array_unshift($stack,array($keys,$list));
	  $list = $copt;
	  $keys = array_keys($list);
			
	} else $this->select_opt($value,$ckey,$copt,$oattr,$oattrs);
      }
    }
    $this->out();

    return $res;
  }

  protected function select_pre($name,$list,$value,$attr){
    if(is_array($value)){
      $multi = TRUE;
      $attr['multiple'] = TRUE;
    } else $multi = def($attr,'multiple',FALSE)?TRUE:FALSE;
    $value = $this->getval($name,(isset($attr['value']) and is_null($value))?$attr['value']:$value,$multi);
    $res = $this->ftag('select',NULL,$this->auto_fattr($attr,$name,NULL));
    return array($value,$res);
  }

  protected function select_opt($value,$ckey,$copt,$oattr,$oattrs){
    $cattr = array_merge($oattr,def($oattrs,$ckey,array()));
    if(is_array($value)) $cattr['selected'] = in_array($ckey,$value)?TRUE:NULL;
    else $cattr['selected'] = $ckey==$value?TRUE:NULL;
    $cattr['value'] = $ckey;
    $this->tag('option',$copt,$cattr);
  }




  /* submit/reset buttons
    0 strings: use sytem defaults for both
    1 string: send button only using this string as text
    2 strings: send and reset button using this strings as text
    3 strings: as above; 3rd string is used as separator inbetween
    1 array: used as attributes for send and reset button (type is set automatically)
    2 arrays: used for send and reset button as attributes (type is set automatically)
    3 arrays: as above third definies the embedding tag (eg array('tag'=>'div','class'=>'buttons'))

    styles and classes has to be used allways in array form
    if you use send2s or similar with send and reset button use allways also the third array!
   */

  function send(/* */){
    $ar = func_get_args();
    $typ = array('txts'=>'ns','txtr'=>'ns','attrs'=>'A','attrr'=>'A','sep'=>'ns','tag'=>'A');
    $def = array('txts'=>NULL,'txtr'=>NULL,'attrs'=>array(),'attrr'=>array(),'sep'=>NULL,'tag'=>array());
    extract(self::args_set($ar,$typ,$def),EXTR_OVERWRITE);
    if(count($attrr)==0) $attrr = $attrs;
    if(isset($tag['tag'])) $this->open($tag['tag'],$tag);
    if(!is_null($txts)) $attrs['value'] = $txts;
    if(!is_null($txtr)) $attrr['value'] = $txtr;
    $attrs['type'] = 'submit';
    $attrr['type'] = 'reset';
    $res = $this->tag('input',NULL,$attrs);
    if(is_null($txts) or (!is_null($txts) and !is_null($txtr))){
      if(!empty($sep)) $this->add($sep);
      $res = $this->tag('input',NULL,$attrr);
    }
    if(isset($tag['tag'])) $res = $this->close();
    return $res;

  }

  function ftag($tag,$data,$attr){
    $name = $attr['name'];
    if(substr($name,-2)=='[]') $name = substr($name,0,-2); // remove the array brackets for ikey

    $res = $this->tag($tag,$data,$attr);


    if(isset($this->ikey[$name])) $this->ikey[$name][] = $res;
    else                          $this->ikey[$name] = array($res);

    switch($this->ticket_mode){
    case 1: case 2:
      if($tag=='button') break;
      $tmp = $attr;
      if(!is_null($data)) $tmp['value'] = $data;
      $this->ticket_data[$name] = $tmp;
      break;
    }

    return $res;
  }

  function ticket_start($tsys,$tid=NULL,$mode=NULL,$key='__ticket'){
    if($tsys instanceof opi_ticket) $this->ticket = $tsys;
    if(!is_object($this->ticket)) return FALSE;
    if(is_null($mode)) $this->ticket_mode = is_null($tid)?1:2;
    else               $this->ticket_mode = $mode;
    if(!is_null($tid))$this->ticket_id = $tid;
    $this->ticket_key = $key;
    return $this->ticket_mode;
  }

  function auto_fattr($attr,$name,$value,$type=NULL,$checked=NULL){
    $attr = $this->obj->auto_attr($attr);
    if(!is_null($name)) $attr['name'] = $name;
    else if(!isset($name)) trigger_error('missing name for an input field in opc_ht2form',E_USER_WARNING);
    else $name = $attr['name'];
    if(!is_null($type)) $attr['type'] = $type;
    else if(!isset($type)) $attr['type'] = 'text';
    
    switch($attr['type']){
    case 'radio': case 'checkbox': case 'option': case 'select':
      /* mention that in this cases value is the potential value and not in all cases the (user submitted) current value
       * that on is saved (indirectly) in checked
       */
      if(substr($name,-2)=='[]') $name = substr($name,0,-2);
      if(!is_null($value)) $attr['value'] = $value;
      $checked = $this->getval($name,(isset($attr['checked']) and is_null($checked))?$attr['checked']:$checked,TRUE);
      if(is_bool($checked))         $attr['checked'] = $checked;
      else if(is_array($checked))   $attr['checked'] = in_array($value,$checked);
      else                          $attr['checked'] = $checked===$value;
      if(!$attr['checked']) unset($attr['checked']);
      break;
    case 'password': // no default;
      if(isset($attr['value'])) unset($attr['value']);
      break; 
    default:
      $attr['value'] = $this->getval($name,(isset($attr['value']) and is_null($value))?$attr['value']:$value,FALSE);
    }
    return $attr;
  }

  /** see def_mode for details, $accarr: accept arrays as return value? */
  function getval($name,$value,$accarr){
    if(!array_key_exists($name,$this->def)) return $value; // if there is no default use value
    $def = $this->def[$name];
    if(is_array($def) and !$accarr){
      $nele = count(def($this->ikey,$name,array()));
      $def = def($def,$nele,NULL);
    }
    // this line is reached only if neither value nor default is null
    switch(is_numeric($this->def_mode)?$this->def_mode:def($this->def_mode,$name,0)){
    case 0: return is_null($value)?$def:$value;
    case 1: return is_null($def)?$value:$def;
    case 2: return $value;
    case 3: return $def;
    }
    trigger_error('unkown def-mode for opc_ht2form',E_USER_NOTICE);
    return is_null($value)?$def:$value;
  }


  function selection($name,$list,$value=NULL,$attr=array()){
    $typ_input = defex($attr,'type','radio');
    if($typ_input='radio' or $typ_input='checkbox'){
      $typ_list = defex($attr,'list','div');
      switch($typ_list){
      case 'div':
	$this->open('div');
	foreach($list as $key=>$val){
	  $this->open('div');
	  $this->$typ_input($name,$key);
	  $this->add($val);
	  $this->close();
	}
	$this->close();
      }

    }
    switch($typ){
    }
  }  
  }
?>
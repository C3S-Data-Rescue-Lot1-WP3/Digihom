<?php

  /* nested select, input-tables and so on

  bug array als result of post/get
   */
require_once('opc_ht.php');
require_once('ops_arguments.php');

class opc_htform extends opc_ht{

  var $method = 'get';
  var $action = NULL;//NULL -> myself()

  /* The following named arrays may include standard values for different things.
   the key will make the connection to the name attribute of the input fields
   (or textarea, select ...).*/
  var $def_value = array();
  var $def_title = array();
  var $def_style = array();
  var $def_class = array();

  // similar to those above but not a named array, just the names of the fields
  var $def_readonly = array(); 


  /* the following arrays work similar to the $def_* but the match
   works with the type of the field (hidden, text, radio, textarea ...)
  */
  var $typ_size = array();//textarea: size is an array (rows & cols)
  var $typ_class = array();
  var $typ_style = array();

  function __construct($method='get',$action=NULL,$xhtml=FALSE){
    $this->method = $method;
    $this->action = is_null($action)?$this->myself():$action;
    parent::opc_ht($xhtml);
  }

  /* opens a form tag   for enable file upload use 'method'=>'file' in attrs  */
  function fopen($attr=array(),$hidden=array()){
    array_unshift($this->stack,$this->fopen2arr($attr,$hidden));
  }
  function fopen2str($attr=array(),$hidden=array()){
    return($this->_implode2str($this->fopen2arr($attr,$hidden),1));
  }
  function fopen2arr($attr=array(),$hidden=array()){
    $attr = $this->_attr_auto($attr);
    if(!isset($attr['action'])) $attr['action'] = $this->action;
    if(!isset($attr['method'])) $attr['method'] = strtolower($this->method);
    if($attr['method']=='file'){
      $attr['method'] = 'post';
      $attr['enctype'] = 'multipart/form-data';
    }
    $res = $this->open2arr('form',$attr);
    foreach($hidden as $key=>$val) $res[] = $this->hidden2arr($key,$val);
    return($res);
  }



  /* opens a fieldset */
  function fsopen    ($legend=NULL,$attr=array(),$attrleg=array()){
     array_unshift($this->stack,$this->fsopen2arr($legend,$attr,$attrleg));
  }
  function fsopen2str($legend=NULL,$attr=array(),$attrleg=array()){
    return($this->_implode2str($this->fsopen2arr($legend,$attr,$attrleg),1));
  }
  function fsopen2arr($legend=NULL,$attr=array(),$attrleg=array()){
    $attr = $this->_attrs_def($attr,NULL,'fieldset',NULL);
    unset($attr['type']);
    $res = $this->_attr_auto($attr,'fieldset');
    $attrleg = $this->_attrs_def($attrleg,NULL,'legend',NULL);
    unset($attrleg['type']);
    if(!is_null($legend)) $res[] = $this->tag2arr('legend',$legend,$attrleg);
    return($res);
  }


  /* ============================================================
   input fields and so on
   ============================================================ */

  //on or more hidden values
  function hidden    ($name,$value=NULL,$attr=array()){
    $this->add($this->hidden2arr($name,$value,$attr));
  }
  function hidden2str($name,$value=NULL,$attr=array()){
    return($this->_implode2str($this->hidden2arr($name,$value,$attr)));
  }
  function hidden2arr($name,$value=NULL,$attr=array()){
    if(is_array($name)){
      $res = array();
      foreach($name as $key=>$val) 
	$res[] = $this->hidden2arr($key,$val,$attr);
    } else {
      $show = ops_array::key_extract($attr,'show',FALSE);
      $res = $this->_attr_auto($attr,'input',
			       array('name'=>$name,'type'=>'hidden','value'=>$value));
      if($show) $res = array('tag'=>'span',$res,array('tag'=>'span',$value));
    }
    return($res);
  }
  




  function input    ($name,$def=NULL,$type='text',$attr=array()){
    $this->add($this->input2arr($name,$def,$type,$attr));
  }
  function input2str($name,$def=NULL,$type='text',$attr=array()){
    return($this->_implode2str($this->input2arr($name,$def,$type,$attr)));
  }
  function input2arr($name,$def=NULL,$type='text',$attr=array()){
    switch($type){
    case 'textarea': return($this->textarea2arr($name,$def,$attr));
    }
    $attr = $this->_attrs_def($attr,$name,is_null($type)?'text':$type,$def);
    return($this->tag2arr('input',NULL,$attr));
  }



  function text    ($name,$def=NULL,$attr=array()){
    $this->add($this->text2arr($name,$def,$attr));
  }
  function text2str($name,$def=NULL,$attr=array()){
    return($this->_implode2str($this->text2arr($name,$def,$attr)));
  }
  function text2arr($name,$def=NULL,$attr=array()){
    return($this->input2arr($name,$def,'text',$attr));
  }



  function password    ($name,$def=NULL,$attr=array()){
    $this->add($this->password2arr($name,$def,$attr));
  }
  function password2str($name,$def=NULL,$attr=array()){
    return($this->_implode2str($this->password2arr($name,$def,$attr)));
  }
  function password2arr($name,$def=NULL,$attr=array()){
    return($this->input2arr($name,$def,'password',$attr));
  }




  function radio    ($name,$def=NULL,$attr=array()){
    $this->add($this->radio2arr($name,$def,$attr));
  }
  function radio2str($name,$def=NULL,$attr=array()){
    return($this->_implode2str($this->radio2arr($name,$def,$attr)));
  }
  function radio2arr($name,$def=NULL,$attr=array()){
    ops_array::rename($attr,'value','svalue');//value is used a little bit different
    $attr = $this->_attrs_def($attr,$name,'radio',$def);
    $def = ops_array::key_extract($attr,'value');
    ops_array::rename($attr,'svalue','value');
    if(!is_null($def)){
      if(isset($attr['value'])){
	if($def==$attr['value']) $attr['checked'] = TRUE;
      } else if($def) $attr['checked'] = TRUE;
    }
    return($this->tag2arr('input',NULL,$attr));
  }



  function checkbox    ($name,$def=NULL,$attr=array()){
    $this->add($this->checkbox2arr($name,$def,$attr));
  }
  function checkbox2str($name,$def=NULL,$attr=array()){
    return($this->_implode2str($this->checkbox2arr($name,$def,$attr)));
  }
  function checkbox2arr($name,$def=NULL,$attr=array()){
    ops_array::rename($attr,'value','svalue');//value is used a little bit different
    $attr = $this->_attrs_def($attr,$name,'checkbox',$def);
    $def = ops_array::key_extract($attr,'value');
    ops_array::rename($attr,'svalue','value');
    if(!is_null($def)){
      if(is_array($def)){//multiple values possible
	if(isset($attr['value']) and in_array($attr['value'],$def))
	  $attr['checked'] = TRUE;
      } else if(isset($attr['value'])){
	if($def==$attr['value']) $attr['checked'] = TRUE;
      } else if($def) $attr['checked'] = TRUE;
    }
    return($this->tag2arr('input',NULL,$attr));
  }

  /* creates a select structer
   using key and value of data for the list
   special attrs: 
     key: if FALSE ->  value is used as key
     if list is not an array or empty
       empty_value: used in hidden input-tag (default: 'NULL')
       empty_disp: used for displaying (default: empty_value)
       if empty_disp is an array it should be a tag-array
       otherwise it a span-tag will be constructed (class: empty_select)
       the hidden input-tag will be added to the this tag
       if both are missing an empty select-tag is the result
     if list has only one element
       single_disp: tag-name or a tag-array, value and key will be added
       if not definied an single-item select-tag is the result
   uses __i
   */
  function select    ($name,$list,$def=NULL,$attr=array()){
    $this->add($this->select2arr($name,$list,$def,$attr));
  }
  function select2str($name,$list,$def=NULL,$attr=array()){
    return($this->_implode2str($this->select2arr($name,$list,$def,$attr)));
  }
  function select2arr($name,$list,$def=NULL,$attr=array()){
    if(isset($attr['multiple']) and $attr['multiple'] and substr($name,-2)<>'[]') $name .= '[]';
    $attr = $this->_attrs_def($attr,$name,'select',$def);
    unset($attr['type']);//type evt used in _attrs_def
    $def = ops_array::key_extract($attr,'value');

    if(is_scalar($list)) $list = array($list);
    if(ops_array::key_extract($attr,'key')===FALSE) $list = ac($list);

    $e_val = ops_array::key_extract($attr,'empty_value',NULL);
    $e_disp = ops_array::key_extract($attr,'empty_disp',$e_val);
    $s_disp = ops_array::key_extract($attr,'single_disp',NULL);

    if(count($list)==0 and (!is_null($e_val) or !is_null($e_disp))){
      $res = is_array($e_disp)?$e_disp:array('tag'=>'span','class'=>'empty_select',$e_disp);
      $res[] = array('tag'=>'input','type'=>'hidden','name'=>$name,'value'=>$e_val);
      unset($attr['name']);
    } else if(count($list)==1 and (!is_null($s_disp))){
      list($ak,$av)=each($list);
      $res = is_array($s_disp)?$s_disp:array('tag'=>$s_disp,'class'=>'single_select');
      $res[] = $av;
      $res[] = array('tag'=>'input','type'=>'hidden','name'=>$name,'value'=>$ak);
      unset($attr['name']);
    } else {
      $res = array('tag'=>'select');
      $ci = 0;
      while(list($ak,$av)=each($list)){
	$opt = array('tag'=>'option','__i'=>$ci++,'value'=>strval($ak));
	if(!is_null($def) and ((is_array($def) and in_array($ak,$def)) or $ak==$def))
	  $opt['selected'] = TRUE;
	$opt[] = $av;
	$res[] = $opt;
      }
      $attr = $this->_attr_shift($attr,'select',array('option'));
    }
    return($this->implode2arr($res,$attr));
  }


  //nested select (does not work everywhere) uses __p
  function nested_select    ($name,$list,$def=NULL,$attr=array()){
    $this->add($this->nested_select2arr($name,$list,$def,$attr));
  }
  function nested_select2str($name,$list,$def=NULL,$attr=array()){
    return($this->_implode2str($this->nested_select2arr($name,$list,$def,$attr)));
  }
  function nested_select2arr($name,$list,$def=NULL,$attr=array()){
    $attr = $this->_attrs_def($attr,$name,'select',$def);
    unset($attr['type']);//type evt used in _attrs_def
    $def = ops_array::key_extract($attr,'value');
    $res = array();//$attr;
    $res['tag'] = 'select';
    $res = array_merge($res,$this->_nested_select($list,$def,''));
    $attr = $this->_attr_shift($attr,'select',array('optgroup'));
    return($this->implode2arr($res,$attr));//    return(array_merge($res,$attr));
  }
  function _nested_select($data,$def,$path=''){
    $ci = 1; $res = array();
    while(list($ak,$av)=each($data)){
      if(is_array($av)){
	$opt = array('tag'=>'optgroup','label'=>$ak);
	$opt = array_merge($opt,$this->_nested_select($av,$def,$path . $ci++ . '.'));
      } else {
	$opt = array('tag'=>'option','label'=>$av,'value'=>$ak,
		     '__p'=>$path . $ci++,$av);
	if(!is_null($def) and $ak==$def) $opt['selected'] = TRUE;
      }
      $res[] = $opt;
    }
    return($res);
  }

  /* creates a textarea
   special for attrs: attrs['size'] = array(rows,cols)
   */
  function textarea    ($name,$def=NULL,$attr=array()){
    $this->add($this->textarea2arr($name,$def,$attr));
  }
  function textarea2str($name,$def=NULL,$attr=array()){
    return($this->_implode2str($this->textarea2arr($name,$def,$attr)));
  }
  function textarea2arr($name,$def=NULL,$attr=array()){
    $attr = $this->_attrs_def($attr,$name,'textarea',$def);
    unset($attr['type']);// type may be used in _attrs_def
    $def = ops_array::key_extract($attr,'value');
    $size = ops_array::key_extract($attr,'size');
    if(is_array($size) and count($size)==2){
      ops_array::setweak($attr,'rows',$size[0]);
      ops_array::setweak($attr,'cols',$size[1]);
    }
    unset($attr['type']);
    $def = is_null($def)?'':$def;
    return($this->tag2arr('textarea',htmlspecialchars($def),$attr));
  }

  function label    ($for=NULL,$content='',$attr=array()){
    $this->add($this->label2arr($for,$content,$attr));
  }
  function label2str($for=NULL,$content='',$attr=array()){
    return($this->_implode2str($this->label2arr($for,$content,$attr)));
  }
  function label2arr($for=NULL,$content='',$attr=array()){
    if(!is_null($for)) $attr['for'] = $for;
    return($this->tag2arr('label',$content,$attr));
  }

  function button    ($name,$text=NULL,$attr=array()){
    $this->add($this->button2arr($name,$text,$attr));
  }
  function button2str($name,$text=NULL,$attr=array()){
    return($this->_implode2str($this->button2arr($name,$text,$attr)));
  }
  function button2arr($name,$text=NULL,$attr=array()){
    if(is_null($text)) $text = $name;
    $attr = $this->_attrs_def($attr,$name,'button',NULL);
    unset($attr['type']);
    return($this->tag2arr('button',$text,$attr));
  }

  // similar to button but using input type submit
  function sbutton    ($name,$text=NULL,$attr=array()){
    $this->add($this->sbutton2arr($name,$text,$attr));
  }
  function sbutton2str($name,$text=NULL,$attr=array()){
    return($this->_implode2str($this->sbutton2arr($name,$text,$attr)));
  }
  function sbutton2arr($name,$text=NULL,$attr=array()){
    if(is_null($text)) $text = $name;
    $attr = $this->_attrs_def($attr,$name,'button',NULL);
    $attr['type'] = 'submit';
    return($this->input2arr($name,$text,'submit',$attr));
  }

  function file    ($name,$attr=array()){
    $this->add($this->file2arr($name,$attr));
  }
  function file2str($name,$attr=array()){
    return($this->_implode2str($this->file2arr($name,$attr)));
  }
  function file2arr($name,$attr=array()){
    return($this->input2arr($name,'','file',$attr));
  }

  /* creates the buttons and the end of an form
   Args: up to three strings and two array
    0 strings: submit and reset button with default strings from system
    1 string: submit button only (with this text)
    2 strings: sumbit & reset button (with this texts) and a &nbsp; between
    3 strings: sumbit & reset button (with this texts) and the third string between
    0 array: no special attributes for the buttons
    1 array: same attributes used for both buttons (if both are use)
    2 array: separate attributte arrays for the two buttons
   */
  function submit    (/* ... */){
    $ar = func_get_args(); 
    $this->add(call_user_func_array(array(&$this,'submit2str'),$ar));
  }
  function submit2str(/* ... */){
    $ar = func_get_args(); 
    return($this->_implode2str(call_user_func_array(array(&$this,'submit2arr'),$ar)));
  }
  function submit2arr(/* ... */){
    $def = array('txts'=>'','txtr'=>'',
		 'attr'=>array(),'attrr'=>array(),
		 'sep'=>'&nbsp;');
    $args = func_get_args();
    extract(ops_arguments::setargs($args,$def),EXTR_OVERWRITE);

    if(count($attrr)==0) $attrr = $attr;
    $res = array();
    if($txts=='' and $txtr==''){
      $attr = $this->_attrs_def($attr,NULL,'submit',NULL);
      $res[] = $this->tag2arr('input',$attr);
      if($sep!='') $res[] = $sep;
      $attrr = $this->_attrs_def($attrr,NULL,'reset',NULL);
      $res[] = $this->tag2arr('input',$attrr);
    } else if($txtr==''){
      $attr = $this->_attrs_def($attr,NULL,'submit',$txts);
      $res[] = $this->tag2arr('input',$attr);
    } else {
      $attr = $this->_attrs_def($attr,NULL,'submit',$txts);
      $res[] = $this->tag2arr('input',$attr);
      if($sep!='') $res[] = $sep;
      $attrr = $this->_attrs_def($attrr,NULL,'reset',$txtr);
      $res[] = $this->tag2arr('input',$attrr);
    }
    return($res);
  }





  function _attrs_def($attr,$name=NULL,$type=NULL,$value=NULL){
    if(!is_null($name)) $attr['name'] = $name;
    if(!is_null($type)) $attr['type'] = $type;
    if(!is_null($value)) $attr['value'] = $value;
    if(isset($attr['name'])){
      $islist = substr($attr['name'],-2)=='[]'; 
      $rname = $islist?substr($attr['name'],0,-2):$attr['name'];
    } else {
      $islist = FALSE;
      $rname = NULL;
    }
    $fld = array('value','title','style','class');
    foreach($fld as $cf){
      $ca = 'def_' . $cf;
      $cd = $this->$ca; //can not be used directly inside the next line!
      if(!isset($attr[$cf]) and isset($cd[$rname]))
	$attr[$cf] = $cd[$rname];
    }

    $fld = array('readonly');
    foreach($fld as $cf){
      $ca = 'def_' . $cf;
      $cd = $this->$ca; //can not be used directly inside the next line!
      if(!isset($attr[$cf]) and in_array($rname,$cd))
	$attr[$cf] = TRUE;
    }

    $fld = array('size','style','class');
    foreach($fld as $cf){
      $ca = 'typ_' . $cf;
      $cd = $this->$ca; //can not be used directly inside the next line!
      if(!isset($attr[$cf]) and isset($cd[$attr['type']]))
	$attr[$cf] = $cd[$attr['type']];
    }
    return($attr);
  }
    

}
?>
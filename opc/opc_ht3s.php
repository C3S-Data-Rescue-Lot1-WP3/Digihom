<?php

  // interface to use 3_i as target
interface opi_h3i_use {
  function exp_open(&$res);
  function exp_add(&$res);
  function exp_close(&$res);
  }

class opc_ht3s extends opc_tstore {

  protected $defca_map_get_dir = array('xhtml','charset','httype');

  public $xhtml   = FALSE;
  public $charset = 'UTF-8';
  public $httype  = 'strict';
  public $htvers  = 4.01;

  function import_fw($fw){
    $this->xhtml   = $fw->xhtml;
    $this->charset = $fw->charset;
    $this->httype  = $fw->httype;
    $this->htvers  = $fw->htvers;
  }

  function init_first(){
    parent::init_first();
    $this->root_ele['t'] = NULL;
    $this->map_get_dir = array_merge($this->map_get_dir,$this->defca_map_get_dir);
    return 0;
  }

  // saves only the first element of $data
  function data_add_one($key,$data){
    $dat = array_shift($data);
    $this->data[$key] = $dat;
    if($dat instanceof opc_ht3t)
      $this->str[$key]['t'] = 'tag';
    else if(is_string($dat) or is_array($dat))
      $this->str[$key]['t'] = 'text';
    else if($dat instanceof opc_ht3str)
      $this->str[$key]['t'] = 'structer';
    else 
      $this->str[$key]['t'] = 'unkown';
  }


  function seq2ht($root){
    $seq = $this->seq_ht($root,0);

    if(count($seq)==1){
      return array(array('id'=>$seq[0][0],
			 'op'=>'add',
			 'lev'=>$seq[0][1],
			 'pos'=>0));
    } 
  
    $res = array();   // result
    $fl = $seq[0][1]; // first level
    $ll = $fl;        // last used level
    $p = 0;           // position per branch
    $n = 0;           // over all position
    $b = array();     // array(x=>n): n opend level x

    foreach($seq as $citem){
      list($ele,$cl) = $citem;
      if($cl>$ll) { // change from add to open if we are deeper
	$res[$n-1]['op'] = 'open';
	$b[$cl] = $n-1;
	$ll++;
	$p = 0;
      } else if($cl<$ll){
	do {
	  $res[$n++] = array('id'=>$res[$b[$ll]]['id'],
			     'op'=>'close',
			     'lev'=>$ll-1,
			     'pos'=>$res[$b[$ll]]['pos']);
	} while(--$ll>$cl);
	$p = $res[$n-1]['pos']+1;
      }

      // add current element as add
      $res[$n++] = array('id'=>$ele,
			 'op'=>'add',
			 'lev'=>$cl,
			 'pos'=>$p++);
    }
    // close remaining levels
    while($ll>$fl){
      $res[$n++] = array('id'=>$res[$b[$ll]]['id'],
			 'op'=>'close',
			 'lev'=>$ll-1,
			 'pos'=>$res[$b[$ll]]['pos']);
      $ll--;
    }
    return $res;
  }


  function seq_ht($pi,$slev){
    if(!isset($this->str[$pi])) return FALSE;
    $flev = $this->str[$pi]['l'];
    if(is_null($this->str[$pi]['u'])){
      $dlev = $slev - $flev - 1;
      $res = array();
    } else {
      $dlev = $slev - $flev;
      $res = array(array($pi,$slev));
    }
    $down = TRUE;
    while(FALSE!== $this->nxt($pi,$down)){
      if($this->str[$pi]['l']<=$flev) break;
      switch($this->str[$pi]['t']){
      case 'tag': case 'text':
	$res[] = array($pi,$dlev+$this->str[$pi]['l']);
	$down = TRUE;
	break;
      case 'structer':
	$tmp = $this->data[$pi]->seq_ht($pi,$dlev+$this->str[$pi]['l']);
	if(is_array($tmp)) $res = array_merge($res,$tmp);
	$down = FALSE;    
	break;
      default:
	qx($this->str[$pi]['t']);
	return $res;
      }
    }
    
    return $res;
  }

  function doctype(){
    if($this->xhtml){
      $tmp = ucfirst($this->httype);
      $httype = strtolower($this->httype);
      return "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 $tmp//EN\""
	. " \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-$httype.dtd\">";
    } else {
      $res = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML ' . $this->htvers;
      switch($this->httype){
      case 'strict':
	return $res . '//EN" "http://www.w3.org/TR/html4/strict.dtd">';
      case 'transitional':
	return $re . ' Transitional//EN ""http://www.w3.org/TR/html4/loose.dtd">';
      case 'frameset':
	return $res . ' Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">';
      default:
	return $res . '//EN">';
      }
    }      
  }

  function output($head,$body){
    if($this->xhtml){
      $res = '<?xml version="1.0" encoding="' . $this->charset . '" ?>'
	. "\n" . $this->doctype()
	. "\n" . '<html xmlns="http://www.w3.org/1999/xhtml">';
    } else {
      $res = $this->doctype() . "\n<html>";
    }
    return $res
      . "\n" . $head->export()
      . "\n" . $body->export()
      . "\n</html>\n";
  }


  function export($root){
    if(!isset($this->str[$root])) return 9990;
    $this->seq = $this->seq2ht($root);
    $exp = new opc_ht3_x($this);
    foreach($this->seq as $n=>$tmp)
      $exp->add($tmp,$this->data[$tmp['id']]);
    return $exp->exp($this->seq);
  }

  }

class opc_ht3_x{
  protected $ts = NULL;
  protected $lay_line_size = 80;
  protected $lay_lev;

  /* how to close an empty item (0 is default)
   * 0: long version (</tag>)
   * 1: short version (html: do not close)
   * 2: skip entire tag
   */
  public $ifempty = array('meta'=>1,
			  'hr'=>1,'br'=>1,'img'=>1,
			  'dl'=>2,'dt'=>2,'dd'=>2,'ol'=>2,'ul'=>2,'li'=>2,
			  'a'=>2,
			  'i'=>2,'b'=>2,
			  'span'=>2,'div'=>2,'p'=>2,
			  'table'=>2,'tr'=>2,'colgroup'=>1);
  
  public $data = array();
  
  function __construct($ts){
    $this->ts = $ts;
  }

  function add($str,$dat){
    $id = $str['id'];
    if($str['op']!='close') 
      $this->data[$id] = new opc_ht3_i();
    if($dat instanceof opi_h3i_use){
      $mth = 'exp_' . $str['op'];
      $dat->$mth($this->data[$id]);
    } else if(is_array($dat) or is_string($dat)){
      $this->data[$id]->type = 'text';
      $this->data[$id]->raw = $dat;
    } else qx();
  }

  function nl($d=0){
    return "\n" . str_repeat('  ',max(0,1+$this->lay_lev+$d));
  }

  function exp($seq){
    $bl = $seq[0]['lev']-1;
    $cnt = array($bl=>new opc_ht3_t());
    foreach($seq as $tmp){
      $this->lay_lev = $tmp['lev'];
      if($tmp['op']=='open'){
	$cnt[$this->lay_lev] = new opc_ht3_t();
      } else if($tmp['op']=='add'){
	$item = $this->data[$tmp['id']];
	$mth = 'exp_add__' . $item->type;
	$this->$mth($item,$cnt[$this->lay_lev-1]);
      } else {
	$item = $this->data[$tmp['id']];
	$mth = 'exp_close__' . $item->type;
	$this->$mth($cnt[$this->lay_lev],$item,$cnt[$this->lay_lev-1]);
      }
    }
    list($res,$bl) = $cnt[$bl]->out($this->nl($bl),$this->lay_line_size);
    return $res;
  }

  function exp_add__text($item,$tar){
    if(is_array($item->raw)){
      foreach($item->raw as $cl)
	$tar->add($cl,TRUE);
    } else $tar->add($item->raw);
  }


  // add tag without child nodes
  function exp_add__tag($item,$tar){
    $tag = $item->head['.'];
    $head = $this->expc_tag_head($item,$hlen);
    if(empty($item->raw)){ // the tag is really empty!
      switch(def($this->ifempty,$tag,0)){
      case 0: 
	$ctag = '</' . $tag . '>';
	return $tar->add($head . $ctag,$hlen<0);
      case 1: 
	if($this->ts->xhtml)
	  return $tar->add(substr($head,0,-1) . '/>',$hlen<0);
	else
	  return $tar->add($head,$hlen==0);
      case 3: 
	return 0;
      }
    } else { // but there is are internal texts!
      $cont = new opc_ht3_t();
      $cont->add($item->raw);
      $ctag = '</' . $tag . '>';
      return $this->exp_tag($head,$hlen,$cont,$ctag,$tar);
    }
  }

  // add tag with cild elements
  function exp_close__tag($cont,$item,$tar){
    if($cont->len==0) return $this->exp_add__tag($item,$tar);
    if(!empty($item->raw)) $cont->pre($item->raw);
    $head = $this->expc_tag_head($item,$hlen);
    $ctag = '</' . $item->head['.'] . '>';
    return $this->exp_tag($head,$hlen,$cont,$ctag,$tar);
  }

  function exp_tag($head,$hlen,$cont,$ctag,$tar){
    $cont->pre($head,$hlen<0);
    list($res,$inline) = $cont->out($this->nl(),$this->lay_line_size);
    if($inline) $res .= $ctag;
    else $res .= $this->nl(-1) . $ctag;
    $tar->add($res,$inline);
  }

  function expc_tag_head(&$item,&$tlen){
    $head = array('<' . $item->head['.']);
    $len = strlen($item->head['.']);
    foreach($item->head as $ck=>$cv){
      if($ck=='.') continue;
      $tmp =  $ck . '="' . $cv . '"';
      $len += strlen($tmp);
      $head[] = $tmp;
    }
    if($len<$this->lay_line_size){
      $tmp =  implode(' ',$head) . '>';
      $tlen = strlen($tmp);
    } else {
      $tlen = -1;
      $tmp = implode($this->nl() . ' ',$head) . '>';
    }
    return $tmp;
  }

}

class opc_ht3_i {
  public $type = NULL;
  public $head = array();
  public $raw = '';
}

class opc_ht3_t {
  public $txt = array(); // content
  public $len = 0;       // total length
  public $inline = TRUE; //all in one line? or multi line
  
  function add($txt,$ml=FALSE){
    if($txt!=''){
      $this->len += strlen($txt);
      $this->txt[] = $txt;
    }
    if($ml) $this->inline = FALSE;
  }

  function pre($txt,$ml=FALSE){
    if($txt!=''){
      $this->len += strlen($txt);
      array_unshift($this->txt,$txt);
    }
    if($ml) $this->inline = FALSE;
  }

  /* makes content to a single string
   * if total length is above $lim, $nl is used
   * for implode otherwise '',
   * returns array(string,bool)
   *  where bool is TRUE if '' was used
   * resets the item afterward
   */
  function out($nl,$lim){
    if(empty($this->txt)) return NULL;
    $mod = $this->len>$lim?FALSE:$this->inline;
    $res = array(implode($mod?'':$nl,$this->txt),$mod);
    $this->txt = array();
    $this->len = 0;
    $this->inline = TRUE;
    return $res;
  }
}


class opc_ht3str {
  public $type = 'wrap';
  public $subtype = NULL;
  protected $ts = NULL;

  function __construct(){
    $ar = func_get_args();
    $this->ts = array_shift($ar);
    $this->type = array_shift($ar);
    $mth = 'init__' . $this->type;
    if(method_exists($this,$mth))
      $this->$mth($ar);
    else
      qx($this->type);
  }
  
  function init__wrap($args){  }

  function init__incl($args){
    $key = array_shift($args);
    if($key instanceof opc_ht3p){
      $this->incl_key = $key->root();
    } else if(is_scalar($key)) {
      $this->incl_key = $key;
    } else $this->incl_key = NULL;
  }

  function seq_ht($id,$slev){
    $mth = 'seq_ht__' . $this->type;
    return $this->$mth($id,$slev);
  }

  function seq_ht__wrap($id,$slev){
    $res = $this->ts->seq_ht($id,$slev-1);
    array_shift($res);
    return $res;
  }

  function seq_ht__incl($id,$slev){
    if(is_null($this->incl_key)) return NULL;
    return $this->ts->seq_ht($this->incl_key,$slev);
  }
}
?>
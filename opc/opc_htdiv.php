<?php
require_once('opc_ht.php');
/*
 array2list variation (v/htable which breaks after n elements)
*/

class opc_htdiv extends opc_ht{

  var $rem_size = 80;
  var $rem_char = array('=',':','- ','.',' ');
  var $rem_styles = array('del'=>NULL,
			  'addline'=>NULL, 
			  'align'=>array('center','left','right'));// restricted values
  var $rem_style = array('del'=>'html','addline'=>0,'align'=>'center');


  /*


   creates a 'beautiful' remark for your source
   text: the displayed text or if NULL '[END]'
   char: character (or short string) to mark the text
         values 0-4 will lead to '=' ':' '- ' '.' ' '
   style: named array
    align: center, left, right
    addline: number of additional lines 1: above; 2: 1 above 1 below; 3: 2 above 1 below ...
    delim: which delimiter are used 
      '<' -> "< ! - -" and  "- - >" at top and bottom as used in html and xml; aliases h, html
      '*' -> "/ *" and "* /"  at top and bottom as used in C; alias c
      or an array with up to 4 elements
        #ele | head first | head others | tail last | tail others
	1    | item 0     | item 0      | -         | -
	2    | item 0     | -           | item 1    | -
	3    | item 0     | item 2      | -         | item 1
	4    | item 0     | item 2      | item 3    | item 1
  ~2arr makes no sense
  */
  function rem    ($text=NULL,$char=0,$style=array()){$this->add($this->rem2str($text,$char,$style));}
  function rem2str($text=NULL,$char=0,$style=array()){
    $dc = "\x02";
    $def = array('del'=>'h','addline'=>0,'align'=>'left');
    $style = ops_array::setdefault($style,$def);
    //define the delimiters
    switch($style['del']){
    case '<': case'h': case 'html': $del = array('<!--','','','-->'); break;
    case '*': case 'c': $del = array('/*','','','*/'); break;
    default:
      $del = is_array($style['del'])?($style['del']):array($style['del']);
      switch(count($del)){
      case 1: $del = array($del[0],'',$del[0],'');      break;
      case 2: $del = array($del[0],'','',$del[1]);      break;
      case 3: $del = array($del[0],$del[1],$del[2],''); break;
      }
    }
    // set delim 0 and 2 to the same length
    $dn = strlen($del[0]) - strlen($del[2]);
    $del[2] .= str_repeat(' ',1+($dn>0?$dn:0));
    $del[0] .= str_repeat(' ',1+($dn<0?-$dn:0));
    $del[1] = ' ' . $del[1];
    $del[3] = ' ' . $del[3];
    $dn = strlen($del[0]);

    $siz = (is_null($this->rem_size) or $this->rem_size < 20)?99999:$this->rem_size-$dn;
    if(is_numeric($char)) $char = $this->rem_char[$char];
    $char = ' ' . str_repeat($char,ceil($siz/strlen($char))) . ' ';

    //split text into different lines
    $txt = array();
    if(is_null($text)) $txt[] = '[END]';
    else
      foreach(explode("\n",$text) as $ct)
	$txt = array_merge($txt,explode($dc,wordwrap($ct,$siz,$dc)));
    // add additional lines abovem below, above ...
    $al = abs($style['addline']);
    while($al-- >0){ array_unshift($txt,''); if($al-- >0) $txt[] = ''; }
    $nl = count($txt); // number of line

    if($style['addline']<0) { $charS = $char; $char = str_repeat(' ',$siz);}

    for($cl=0;$cl<$nl;$cl++){
      $tx = trim($txt[$cl]); $nc = strlen($tx); 
      if($nc==0) {
	if(($style['addline']<0) and ($cl==0 or $cl==$nl-1) )
	  $txt[$cl] = substr($charS,0,$siz);
	else
	  $txt[$cl] = substr(trim($char)==''?$char:trim($char),0,$siz);
      }
      else {
	switch($style['align']){
	case 'right': $txt[$cl] = substr($char,$nc-$siz) . $tx;	break;
	case 'left':  $txt[$cl] = $tx . substr($char,0,$siz-$nc);    break;
	case 'center': 
	  $nr = ceil(($siz-$nc)/2);
	  $txt[$cl]= ($nr!=0?substr($char,-$nr):'')
	    . $tx . substr($char,0,$siz-$nc-$nr);
	  break;
	}
      }
      if($cl==$nl-1) $del[1] = $del[3];
      $txt[$cl] = $del[0] . $txt[$cl] . $del[1];
      if($cl==0){
	$del[0] = $del[2];
	if($style['addline']==0) $char = str_repeat(' ',strlen($char));
      }
    }

    $res = "\n\n" . implode("\n",$txt) . "\n\n";
    return($this->implode2str($res,array()));
  }

  /* shows its own content (or of an external ht-based object) as source-code
   does not change the stack */
  function source($mode=1,$obj=NULL,$tit=NULL){
    if(is_null($obj)) $obj = $this;
    if(!($obj instanceof opc_ht)) return(NULL);
    $ele = $obj->stack;
    $top = array_pop($ele);
    while(list($ak,$av)=each($top)){
      if(is_array($av)) $av = $this->_implode2str($av);
      $top[$ak] = htmlspecialchars($av);
    }
    $res = array();
    foreach($ele as $cele){
      if(is_array($cele)) {
	$cele = $this->implode2str($cele);
	$cele = substr($cele,0,strrpos($cele,'<'));
      }
      array_unshift($res,htmlspecialchars($cele));
    }
    $sep = array('gh'=>'<h5>Top elements</h5><ol>',
		 'gf'=>'</ol>','ih'=>'<li style="white-space:pre;">','if'=>'</li>');
    array_unshift($res,ops_array::eimplode($top,$sep));

    $style= 'background-color:#EEEEEE; margin: 5px; padding: 5px 1px 5px 20px; '
      . 'border-style:solid; border-color: #888888; border-width: 2px 15px;';
    if(is_null($tit)) $tit = 'current content of ' . get_class($obj);
    $tit .= ' [' . implode(' - ',$obj->_cur_tag(NULL)) . ']';
    $sep = array('gh'=>"<div style='$style' class='_source'><h4>$tit</h4>\n<ol>",
		 'gf'=>'</ol></div>','ih'=>"\n<li style='white-space:pre;'>",'if'=>"</li>\n");
    $res = ops_array::eimplode($res,$sep);
    return($res);
  }


  /*
   creates a ul, ol or dl structer based on an array
   attr: attributes (nested)
    default: array('type'=>'dl')
    as string -> array('type'=>$attr);
    special attributes
      type (t,typ): one of dl (default)
                           ul, ol 
			   vtable, htable (horizntal/vertical table)
			   file: uses ul key is file, value is description
			         if description contains a ¦...¦ part
				 this will be the a-tag otherwise the whole
				 description
				 namtag will be ignored!
      nametag (nt): tag-name which is used to add the keys of arr in front of li-elements
               if not given keys are ignored. nametag is ignored for dl structers
    
   all li, dt and dd get an __i-attribute which defines the position (0,1,...)
  */
  function array2list    ($arr,$attr=array()){$this->add($this->array2list2arr($arr,$attr));}
  function array2list2str($arr,$attr=array()){return($this->_implode2str($this->array2list2arr($arr,$attr)));}
  function array2list2arr($arr,$attr=array()){
    if(!is_array($arr)) return(NULL);
    if(is_string($attr)) $attr = array('type'=>$attr);
    else if(!is_array($attr)) $attr = array('type'=>'dl');
    $type = ops_array::firstkey_extract($attr,array('type','t','typ'),'dl');
    $ntag = ops_array::firstkey_extract($attr,array('nt','nametag'),NULL);

    switch($type){
    case 'file':
      $res = array('tag'=>'ul');
      foreach($arr as $key=>$val){
	if(is_numeric($key)) $key = $val;
	if(!preg_match('/¦[^¦]+¦/',$val)) $ci = "<a href='$key'>$val</a>";
	else $ci = preg_replace('/¦([^¦]+)¦/',"<a href='$key'>\$1</a>",$val);
	$res[] = array('tag'=>'li',$ci);
      }
      break;
      break;
    case 'ul': case 'ol':
      $res = array('tag'=>$type);
      $ii = 0;
      while(list($ak,$av)=each($arr)){
	$ci = array('tag'=>'li','__i'=>$ii++);
	if(!is_null($ntag)) $ci[] = array('tag'=>$ntag,$ak);
	$ci[] = $av;
	$res[] = $ci;
      }
      break;
    case 'dl':
      $res = array('tag'=>'dl');
      $ii = 0;
      while(list($ak,$av)=each($arr)){
	$res[] = array('tag'=>'dt','__i'=>$ii,$ak);
	$res[] = array('tag'=>'dd','__i'=>$ii++,$av);
      }
      break;
    case 'vtable':
      $res = array('tag'=>'table');
      $ii = 0;
      foreach($arr as $ak=>$av){
	$cv = array('tag'=>'tr','__i'=>$ii);
	if(!is_null($ntag)) $cv[] =  array('tag'=>$ntag,$ak,'__i'=>$ii);
	$cv[] = array('tag'=>'td',$av,'__i'=>$ii++);
	$res[] = $cv;
      }
      break;
    case 'htable':
      $res = array('tag'=>'table');
      if(!is_null($ntag)){
	$ii = 0;
	$cv = array('tag'=>'tr');
	foreach(array_keys($arr) as $ck) $cv[] =  array('tag'=>$ntag,$ck,'__i'=>$ii++);
	$res[] = $cv;
      }
      $cv = array('tag'=>'tr');
      $ii = 0;
      foreach($arr as $ck) $cv[] =  array('tag'=>'td',$ck,'__i'=>$ii++);
      $res[] = $cv;
      break;
    }
    return($this->implode2arr($res,$attr));
  }

  // same as above but allows nested lists, h/vtable not allowed
  function arrayn2list    ($arr,$attr=array()){$this->add($this->arrayn2list2arr($arr,$attr));}
  function arrayn2list2str($arr,$attr=array()){return($this->_implode2str($this->arrayn2list2arr($arr,$attr)));}
  function arrayn2list2arr($arr,$attr=array()){
    if(!is_array($arr)) return(NULL);
    if(is_string($attr)) $attr = array('type'=>$attr);
    else if(!is_array($attr)) $attr = array('type'=>'dl');
    $type = ops_array::firstkey_extract($attr,array('type','t','typ'),'dl');
    $ntag = ops_array::firstkey_extract($attr,array('nt','nametag'),NULL);
    $attr['type'] = $type;
    $attr['nametag'] = $ntag;
    switch($type){
    case 'ul': case 'ol':
      $res = array('tag'=>$type);
      $ii = 0;
      while(list($ak,$av)=each($arr)){
	$ci = array('tag'=>'li','__i'=>$ii++);
	if(!is_null($ntag)) $ci[] = array('tag'=>$ntag,$ak);
	$ci[] = is_array($av)?$this->arrayn2list2arr($av,$attr):$av;
	$res[] = $ci;
      }
      break;
    case 'dl':
      $res = array('tag'=>'dl');
      $ii = 0;
      while(list($ak,$av)=each($arr)){
	$res[] = array('tag'=>'dt','__i'=>$ii,$ak);
	$res[] = array('tag'=>'dd','__i'=>$ii++,is_array($av)?$this->arrayn2list2arr($av,$attr):$av);
      }
      break;
    default:
      $res = var_export($arr,TRUE);
    }
    return($this->implode2arr($res,$attr));
  }


  /*
   created a table based on an array
   arr: array of strings or array of arrays of strings (outer elements are the rows)
   attr: attributes (nested)
     special attr:
     rownames (rn): array of rownames or TRUE (will use keys of arr)
     colnames (cn): array of colnames or TRUE (will use keys of first row)
      if rownames are given too colnames may onclude one more element which is used at top left

   
   all tr, td and th elements get the following  __-attribute
     __col: col-number (0-... and 'head' if colnames used)
     __row: similar to __col
  */
  function array2table    ($arr,$attr=array()){$this->add($this->array2table2arr($arr,$attr));}
  function array2table2str($arr,$attr=array()){return($this->_implode2str($this->array2table2arr($arr,$attr)));}
  function array2table2arr($arr,$attr=array()){
    $rown = ops_array::firstkey_extract($attr,array('rownames','rn','row','rown'),NULL);
    $coln = ops_array::firstkey_extract($attr,array('colnames','cn','col','coln'),NULL);
    $tit = ops_array::firstkey_extract($attr,'title',NULL);
    $ta = $arr; array_walk($ta,create_function('&$v,$k', '$v = is_array($v)?count($v):1;'));
    $cols = max($ta); $rows = count($arr); // rows and cols without head col/row
    $rowk = array_keys($arr);
    $nrow = count($rowk);
    $lines = array('tag'=>'table');
    for($cl=0;$cl<$nrow;$cl++){
      $cline = array('tag'=>'tr','__row'=>$cl);
      $cells = $arr[$rowk[$cl]];
      if(is_array($cells)){
	$col = 0;
	foreach($cells as $cc) $cline[] = array('tag'=>'td','__col'=>$col++,'__row'=>$cl,$cc);
      } else $cline[] = array('tag'=>'td',$cells);
      $lines[] = $cline;
    }
    if(!is_null($rown) and $rown!==FALSE){
      $rown = is_array($rown)?$rown:$rowk;
      for($cl=0;$cl<$nrow;$cl++){
	array_unshift($lines[$cl],array('tag'=>'th','__col'=>'head','__row'=>$cl,$rown[$cl]));
      }
    }
    if(!is_null($coln) and $coln!==FALSE){
      $coln = is_array($coln)?$coln:array_keys($arr[$rowk[0]]);
      $cline = array('tag'=>'tr','__row'=>'head');
      if(!is_null($rown) and $rown!==FALSE){
	$tit = count($coln)<=$cols?$tit:array_shift($coln);
	$cline[] = array('tag'=>'th','__col'=>'head',$tit);
      }
      $col = 0;
      foreach($coln as $cc) 
	$cline[] = array('tag'=>'th','__col'=>$col++,'__row'=>'head',is_numeric($cc)?NULL:$cc);
      array_unshift($lines,$cline);
    }
    return($this->implode2arr($lines,$attr));
  }


  /*
   creates full width table with n columns
   1 item -> center
   2 item -> left & right
   3 item -> left, center right, equal width
   >3 items -> all left, equal width
   if attr align is set then it is used for all columns
  */
  function tablebar    ($arr,$attr=array()){$this->add($this->tablebar2arr($arr,$attr));}
  function tablebar2str($arr,$attr=array()){return($this->_implode2str($this->tablebar2arr($arr,$attr)));}
  function tablebar2arr($data,$attr=array()){
    $attr = $this->_attr_auto($attr);
    $line = array('tag'=>'tr');
    $data = array_values($data);
    $nd = count($data);
    $res = array('tag'=>'table','style'=>'margin: 5px 0px; width: 100%;',);
    if(isset($attr['align'])){
      $align = ops_array::key_extract($attr,'align');
      $res[] = array('tag'=>'colgroup','span'=>strval($nd),'width'=>floor(100/$nd) . '%');
      for($ci=0;$ci<$nd;$ci++) 
	$line[] = array('tag'=>'td','__i'=>$ci,$data[$ci],
			'style'=>'text-align:' . $align . ';'); 
    } else {
      switch($nd){
      case 1: 
	$line[] = array('tag'=>'td','style'=>'text-align:center;','__i'=>0,$data[0]); 
	break;
      case 2:
	$line[] = array('tag'=>'td','style'=>'text-align:left;','__i'=>0,$data[0]); 
	$line[] = array('tag'=>'td','style'=>'text-align:right;','__i'=>1,$data[1]); 
	break;
      case 3:
	$res[] = array('tag'=>'colgroup','span'=>'3','width'=>'33%');
	$line[] = array('tag'=>'td','style'=>'text-align:left;','__i'=>0,$data[0]); 
	$line[] = array('tag'=>'td','style'=>'text-align:center;','__i'=>1,$data[1]); 
	$line[] = array('tag'=>'td','style'=>'text-align:right;','__i'=>2,$data[2]); 
	break;
      default:
	$res[] = array('tag'=>'colgroup','span'=>strval($nd),'width'=>floor(100/$nd) . '%');
	for($ci=0;$ci<$nd;$ci++) $line[] = array('tag'=>'td','__i'=>$ci,$data[$ci]); 
      }
    }
    $res[] = $line;
    return($this->implode2arr($res,$attr));
  }

  /* link to w2 validator */
  function valid    ($txt=NULL,$attr=array()){$this->add($this->valid2arr($txt,$attr));}
  function valid2str($txt=NULL,$attr=array()){return($this->_implode2str($this->valid2arr($txt,$attr)));}
  function valid2arr($txt=NULL,$attr=array()){
    $attr = $this->_attr_auto($attr);
    if(is_null($txt)) $txt = 'valid ' . ($this->xhtml?'xhtml':'html') . '?';
    $attr['href'] = 'http://validator.w3.org/check?uri=referer&ss=1&group=1';
    return($this->tag2arr('a',$txt,$attr));
  }

  /* adds a message and returns the output ~2arr/~2str makes no sense */
  function stop($msg,$cls='error',$title='Serious error'){
    if(!empty($title)) $this->h(1,$title,NULL,$cls);
    $this->p($msg,$cls);
    return($this->output());
  }



}
?>
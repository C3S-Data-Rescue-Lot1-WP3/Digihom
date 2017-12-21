<?php
  /*

  INTRODUCTION ========================================
  a class with basic xml function
  it allows to construct a new xml object, modify it and iterate through it
  not included are functions for read/write (from/to files) searching and so on

  OVERLOADING =========================================
  You can overload this class to add functionality or increase performance
  for certain cases.

  READ/WRITE FILES ====================================
  


  Standard Arguments ==================================
  pos: if NULL an internal pointer is used
  move: TRUE -> the function will set the internal pointer to the result


   */


class opc_xml_basic {
  
  //pointer to the current element
  var $cpos = NULL;

  // Default, if the  iteration functions may set the current position too
  var $movepos = TRUE;


  function init(){
    $cpos = NULL;
  }

  /* ======================= test functions ========================= */
  //checks if the complete xml structer is empty (or not valid)
  function is_emptyxml(){}

  //checks if the current position is a (sub)node
  function is_node($pos=NULL){return($this->kind($pos)==1);}

  // checks if the current position is an attribute
  function is_attr($pos=NULL){return($this->kind($pos)==2);}

  //checks if the current position is a pure text data
  function is_textdata($pos=NULL){return($this->kind($pos)==3);}

  // checks if the current position is a child (textdata or node)
  function is_child($pos=NULL){
    $kind = $this->kind($pos);
    return($kind==1 or $kind==3);
  }

  // checks if the asked attr exsits (pos is a node or an attr)
  function attr_exists($key,$pos=NULL){}

  /* ------------------------------------------------------------*/



  /* ========================= structer ========================= */

  //returns the current level (top level is 0)
  function level($pos=NULL){}

  //returns the position (node/textdata -> n'th child; attr -> n'th attr)
  function n($pos=NULL){}

  // count the number of attr (pos is a node or an attr)
  function attrs_count($pos=NULL){}

  //counts the number of childs (pos is the parant node)
  function childs_count($pos=NULL){}

  //counts the number of textdata childs
  // if trim is true, only textdata with length > 0 after a trim are counted
  function textdata_count($trim=FALSE,$pos=NULL){}

  // counts the number of subnodes (no textdata, pos is the parent node)
  // if key is given it counts only subnodes of this type
  function nodes_count($key=NULL,$pos=NULL){}

  /* ------------------------------------------------------------*/



  /* ========================= iteration ========================= */

  //returns the current position
  function pos($pos=NULL){return($this->extpos($pos));}

  // set the current position
  function setpos($pos=NULL){
    $this->cpos = $this->extpos($pos);
    return($this->cpos);
  }

  // loops through the attributes in the current node
  function nextattr($pos=NULL,$move=NULL){return($this->iter('nA',$pos,$move));}
  function prevattr($pos=NULL,$move=NULL){return($this->iter('pA',$pos,$move));}
  function lastattr($pos=NULL,$move=NULL){return($this->iter('lA',$pos,$move));}
  function firstattr($pos=NULL,$move=NULL){return($this->iter('fA',$pos,$move));}

  // loops through nodes ignoring text data (stays on the same level)
  function nextnode($pos=NULL,$move=NULL){return($this->iter('nN',$pos,$move));}
  function prevnode($pos=NULL,$move=NULL){return($this->iter('pN',$pos,$move));}
  function lastnode($pos=NULL,$move=NULL){return($this->iter('lN',$pos,$move));}
  function firstnode($pos=NULL,$move=NULL){return($this->iter('fN',$pos,$move));}

  // loops throug all siblings (nodes and text data; stays on the same level)
  function next($pos=NULL,$move=NULL){return($this->iter('nS',$pos,$move));}
  function prev($pos=NULL,$move=NULL){return($this->iter('pS',$pos,$move));}
  function last($pos=NULL,$move=NULL){return($this->iter('lS',$pos,$move));}
  function first($pos=NULL,$move=NULL){return($this->iter('fS',$pos,$move));}

  // loops throug everything (going down and up in the structer)
  function nextelem($pos=NULL,$move=NULL){return($this->iter('nE',$pos,$move));}
  function prevelem($pos=NULL,$move=NULL){return($this->iter('pE',$pos,$move));}
  function lastelem($pos=NULL,$move=NULL){return($this->iter('lE',$pos,$move));}
  function firstelem($pos=NULL,$move=NULL){return($this->iter('fE',$pos,$move));}

  // goes to the really first node of the whole xml-structer
  function home($move=NULL){return($this->iter('h',NULL,$move));}

  // goes to the parent node if is an attr (attr -> parent; node/text -> stays)
  function this($pos=NULL,$move=NULL){return($this->iter('t',$pos,$move));}

  // goes to the parent node if it is not a node (attr/text -> parent; node -> stays)
  function me($pos=NULL,$move=NULL){return($this->iter('m',$pos,$move));}

  // moves to the node above (pos is a node)
  function up($pos=NULL,$move=NULL){return($this->iter('u',$pos,$move));}

  // moves to the first child inside a node (goes one level down; pos is a node)
  function down($pos=NULL,$move=NULL){return($this->iter('d',$pos,$move));}

  // moves to the first child-node inside a node (goes one level down; pos is a node)
  function child($pos=NULL,$move=NULL){return($this->iter('c',$pos,$move));}

  // moves to the last child inside a node (goes one level; pos is a node)
  function bottom($pos=NULL,$move=NULL){return($this->iter('b',$pos,$move));}

  /* ------------------------------------------------------------*/


  /* ========================= element operation ================== */

  // returns the current item (attr/text -> string; node -> a object)
  function get($pos=NULL){}

  // returns the name (attr -> attrname, node -> tagname, textdata -> NULL)
  function name($pos=NULL){}

  // returns a array (numeric keys) of all childs (pos is a node)
  function childs($pos=NULL){}

  // returns an array (string keys) of all attributes  (pos is a node or an attr)
  function attrs($pos=NULL){}

  // returns an array with attribute names
  function attr_keys($pos=NULL){}

  // returns the value of an attribute (pos is a node or an attr)
  // key may be an array (-> result is a named array)
  function attr($key,$pos=NULL){}
  
  // renames the given position (pos is a node or an attr)
  function rename($newname,$pos=NULL){}
  
  /* inserts/update an attribute 
   pos: points to a node or an attribute of a node  
  */
  function attr_set($key,$value,$pos=NULL){}

  //similar to attr_set but uses a named array
  function attrs_set($attrs,$pos=NULL){}

  /* removes an attribute
   key may be an array of keys, NULL means all
   pos: points to a node or an attribute of a node  
  */
  function attr_remove($key=NULL,$pos=NULL){}


  /* standards to child operations
   pos should point to a node or textdata
   inside: 
     TRUE: pos is a node and the operation will create/remove a child of it
     FALSE: pos is a node or textdata and the operation will create/remove a sibling of it
   nth: absolute position
     >= 0: counting from the first child (=0) to the last
     <  0: counting backward where -1 is the last element
  */

  /* inserts a child element
   if pos >= 0 the element will be inserted before the position (eg: 0 -> new first child)
   if pos <  0 the element will be inserted behind the position (eg: -1 -> new last child)
   default is -1 (new last child)
  */
  function child_insert($value,$nth=NULL,$inside=TRUE,$pos=NULL){}


  /* replaces a existing child element (or add it at the end) */
  function child_replace($value,$nth=NULL,$inside=TRUE,$pos=NULL){}

  /* inserts a child as sibling relativ to position (rel: TRUE -> behind; FALSE -> before)*/
  function child_insertrel($value,$rel=NULL,$pos=NULL){}


  /* removes child
   if inside is FALSE it removes the current position (ignoring nth)
   if necessary the current position is updated too (even if save is false)
   */
  function child_remove($nth=NULL,$inside=TRUE,$pos=NULL,$move=FALSE){}

  /* inserts multiple child elements
   similar to child_insert
   value is an array of childs
   they will be inserted at the end
  */
  function childs_insert($value,$inside=TRUE,$pos=NULL){}

  /* removes all childs
   similar to child_remove
  */
  function childs_reset($inside=TRUE,$pos=NULL,$move=TRUE){}

  /* replaces all childs by the given one
   similar to child_replace
   value is an array of childs
   current childs will be removed and the new one inserted
  */
  function childs_replace($value,$inside=TRUE,$pos=NULL){}

  /* similar to child_insert but with a new created subnode */
  function node_open($tagname,$nth=NULL,$inside=TRUE,$pos=NULL,$move=TRUE){}

  /* similar to child_insertrel but with a new empty node */
  function node_openrel($tagname,$rel=NULL,$pos=NULL){}


  /* ------------------------------------------------------------*/

  
  /* ============================================================
   ===                                                        ===
   ===             non standard functions                     ===
   ===                                                        ===
   ============================================================ */
  
  /* 
   returns the kind of the position
   0: invalid position
   1: node
   2: attribute
   3: text data
  */
  function kind($pos=NULL){}



  function iter($how,$pos=NULL,$move=NULL){}

  //uses the build in pointer if null is given
  function extpos($pos=NULL,$move=FALSE){return(is_null($pos)?$this->cpos:$pos); }
  

}

?>
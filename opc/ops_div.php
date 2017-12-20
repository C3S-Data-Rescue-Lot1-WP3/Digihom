<?php

static class ops_div{

  function notNull(/*...*/){
    $ar = func_get_args();
    while(count($ar)>0)
      if(!is_null($ca = array_shift($ar))) 
	return($ca);
    return(NULL);
  }
}
?>
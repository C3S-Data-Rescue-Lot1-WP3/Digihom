<?php

class opc_validation{
  var $pattern = array('any'=>'/.?/',
                       'int'=>'/^[0-9]+$/',
                       'name'=>'/^[_A-Za-z][_A-Za-z0-9]*$/');

  var $source = array();

  function URL_source($source='get'){
    if(is_null($source)) return($this->source);
    if(is_array($source)) return($source);
    if(!is_string($source)) return(null);
    switch(strtolower($source)){
    case 'both': return(array_merge($_POST,$_GET));
    case 'get': return($_GET);
    case 'post': return($_POST);
    }
  }

  function URL_setsource($source='get'){
    $this->source = $this->URL_source($source);
  }

  function URL_get($name,$typ='any',$default=null,$source=null){
    $source = $this->URL_source($source);
    if(!array_key_exists($name,$source)) return($default);
    $val = $source[$name];
    if(is_null($typ)) return($val);
    if(!preg_match($this->pattern[$typ],$val)) return($default);
    return($val);
  }

}

?>
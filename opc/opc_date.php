<?php

/*
 was bedeutet diesen/nächsten Mittowch genau?
 schnellprüfung ob als datum akzeptabel (preg_match)
 Zeitzonen
 Wochenbeginn
 Welches ist die erste Woche im Jahr
 was bedeutet 5/1/2007? Januar oder May?
 auf inkonsistenz prüfen (Fr 20.7.1975)
 out of range werte korrigieren 32. Dez -> 1. Jan oder Fehler
 Schaltsekunden und kalenderwechsel
 historische Daten
 orthodoxer kalender, andere kalender
 benannte tage: ostern, weihnachten
 ist wochenende, feiertag etc
 datums arithmetik
 Begriff werktage
 anzahl werktage/Sonntage etc in einem bereich
 wie werden fehlende Jahrhudertangaben ergänzt
 Quartal, Jahreszeiten
 
*/


class opc_date{
  /*
   returns FALSE if no date was recognized or a numeric code
   1: xYYYYMMDD:
      x is an optional character (a-z)
   2: xYYYYMMDDyHHIISS: 
      x is an optional character (a-z)
      y is an optional non digital character
      II: minutes (optional, default: 0)
      SS: second (optional, default: 0)
      
   50:  m/d/y: 
      m/d: 1 or two numbers
      y: 2 or 4 numbers
   60:  d.m.y (similar to 10)
   
   */
  static function is_date($value){
    $value = trim($value);
    if(preg_match('|^[a-zA-Z]?\d{8}$|',$value)) 
      return(1);
    if(preg_match('/^[a-zA-Z]?\d{8}\D?(\d{2}|\d{4}|\d{6})$/',$value)) 
      return(1001);
    if(preg_match('/^\d?\d\/\d?\d\/(\d\d|\d{4})$/',$value)) 
      return(50);
    if(preg_match('/^\d?\d\.\d?\d\.(\d\d|\d{4})$/',$value)) 
      return(60);

    
    return(FALSE);
  }

  static function to_ts($value,$typ=NULL){
    date_default_timezone_set('Europe/Zurich');
    $value = trim($value);
    if(is_null($typ)) $typ = opc_date::is_date($value);
    if($typ===FALSE) return(FALSE);
    switch($typ){
    case 1:
      preg_match('|^[a-zA-Z]?(\d{4})(\d{2})(\d{2})$|',$value,$match);
      return(mktime(0,0,0,$match[2],$match[3],$match[1]));
    case 1000:
      preg_match('|^(\d{2})(\d{2})?(\d{2})?$|',$value,$match);
      switch(count($match)){
      case 2: return($match[1]*3600);      
      case 3: return($match[2]*60 + $match[1]*3600);      
      }
      return($match[3] + $match[2]*60 + $match[1]*3600);      
    case 1001:
      preg_match('|^[a-zA-Z]?(\d{8})\D?(\d+)?$|',$value,$match);
      return(opc_date::to_ts($match[1],1) + opc_date::to_ts($match[2],1000));
    case 50:
      preg_match('|^(\d+)\D(\d+)\D(\d+)$|',$value,$match);
      return(mktime(0,0,0,$match[1],$match[2],$match[3]));
    case 60:
      preg_match('|^(\d+)\D(\d+)\D(\d+)$|',$value,$match);
      return(mktime(0,0,0,$match[2],$match[1],$match[3]));
    }
    return(FALSE);
  }
}

class opc_date_enh extends opc_date {
  var $lng = array();
  var $months = array();
  var $months_en = array(1=>array('januar','jan'),
			2=>array('februar','feb'),
			3=>array('march','mar'),
			4=>array('april','apr'),
			5=>array('may'),
			6=>array('june','jun'),
			7=>array('july','jul'),
			8=>array('august','aug'),
			9=>array('september','sept','sep'),
			10=>array('october','oct'),
			11=>array('november','nov'),
			12=>array('december','dec'));
    
  var $months_de = array(1=>array('januar','jan'),
			2=>array('februar','feb'),
			3=>array('maerz','märz','mrz'),
			4=>array('arpil','apr'),
			5=>array('mai'),
			6=>array('juni','jun'),
			7=>array('july','jul'),
			8=>array('august','aug'),
			9=>array('september','sept','sep'),
			10=>array('oktober','okt'),
			11=>array('november','nov'),
			12=>array('dezember','dec'));

  var $days = array();
  var $days_en = array(0=>array('sunday','sun','su'),
		       1=>array('monday','mon','mo'),
		       2=>array('tuesady','tue','tu'),
		       3=>array('wednesday','wed','we'),
		       4=>array('thursday','thu','th'),
		       5=>array('friday','fri','fr'),
		       6=>array('saturday','sat','sa'));

  var $days_de = array(0=>array('sonntag','so'),
		       1=>array('montag','mo'),
		       2=>array('dienstag','di'),
		       3=>array('mittwoch','mi'),
		       4=>array('donnerstag','do'),
		       5=>array('freitag','fr'),
		       6=>array('samstag','sonnabend','sa'));

  var $mod = array();
  var $words_en = array('today'=>array('today'),
			'now'=>array('now'),
			'ago'=>array('ago','earlier'),
			'this'=>array('this'),
			'last'=>array('last','preceding'),
			'next'=>array('next','proceeding'));
  var $words_de = array('today'=>array('heute'),
			'now'=>array('jetzt'),			
			'ago'=>array('vorher','davor'),
			'this'=>array('diesen','dieser'),
			'last'=>array('letzte','letzter','letzten'),
			'next'=>array('nächsten','nächster'));


  function opc_date_enh($lng='en'){
    $this->set_lng($lng);
  }


  function set_lng($lng){
    $ar = array('days','months');
    foreach($ar as $cv){
      $cl = $cv . '_' . $lng;
      if(isset($this->$cl)) $this->$cv = $this->$cl;
    }
    $this->lng = array($lng);
  }

  function get_lng(){
    switch(count($this->lng)){
    case 0: return(FALSE);
    case 1: return($this->lng[0]);
    }
    return($this->lng);
  }

  function add_lng($lng){
    if(in_array($lng,$this->lng)) return; // already done
    $ar = array('days','months');
    foreach($ar as $cv){
      $cl = $cv . '_' . $lng;
      if(isset($this->$cl)) {
	$cur = $this->$cv;
	$add = $this->$cl;
	foreach(array_keys($cur) as $ck){
	  $new = array_unique(array_merge($cur[$ck],$add[$ck]));
	  usort($new,array($this,'_len_sort_rev'));
	  $cur[$ck] = $new;
	}
	$this->$cv = $cur;
      }
    }
    $this->lng[] = $lng;
  }

  static function _len_sort_rev($a1,$a2){
    return(strlen($a1)>strlen($a2)?-1:1);
  }

}    

?>
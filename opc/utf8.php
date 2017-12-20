<?php

function str_utf82western($str,$prefix='=',$na='?',$tohtml=FALSE){
  global $utftableB;
  $pat = "[a-fA-F0-9]";
  $split = preg_split("/$prefix$pat$pat/",$str);
  if(count($split)==1) return($str);
  $splint = array();
  preg_match_all("/$prefix$pat$pat/",$str,$splint);
  $splint = $splint[0];
  $res = array_shift($split);
  while(count($splint)>0){
    $utf8 = strtolower(preg_replace("/$prefix/",'',array_shift($splint)));
    $np = array_shift($split);
    if(hexdec($utf8)>127) {
      $utf8 .= strtolower(preg_replace("/$prefix/",'',array_shift($splint)));
      $np = array_shift($split);
      $nc = isset($utftableB[$utf8])?$utftableB[$utf8]:ord($na);
    } else $nc = hexdec($utf8);
    $res .= ($tohtml?"&#$nc;":chr($nc)) . $np;
  }
  return($res);
}

function str_western2utf8($str,$prefix='=',$na='?'){
  global $utftableA;
  $nc = strlen($str); $res = '';
  for($cp=0;$cp<$nc;$cp++){
    $cc = substr($str,$cp,1);
    if($cc==$prefix)
      $res .= $prefix . sprintf('%x',ord($cc));
    elseif(ord($cc)<128)
      $res .= $cc;
    elseif(isset($utftableA[ord($cc)]))
      $res .= $prefix . $utftableA[ord($cc)][0] . $prefix . $utftableA[ord($cc)][1];
    else
      $res .= $na;
  }
  return($res);
}

function str_needsutf8($str){
  $nc = strlen($str); $res = '';
  for($cp=0;$cp<$nc;$cp++) if(ord(substr($str,$cp,1)>127)) return(TRUE);
  return(FALSE);
}

$utftableA = array(128=>array("e2","82"),		 130=>array("e2","80"),		 131=>array("c6","92"),		 132=>array("e2","80"),
		   133=>array("e2","80"),		 134=>array("e2","80"),		 135=>array("e2","80"),		 136=>array("cb","86"),
		   137=>array("e2","80"),		 138=>array("c5","a0"),		 139=>array("e2","80"),		 140=>array("c5","92"),
		   145=>array("e2","80"),		 146=>array("e2","80"),		 147=>array("e2","80"),		 148=>array("e2","80"),
		   149=>array("e2","80"),		 150=>array("e2","80"),		 151=>array("e2","80"),		 152=>array("cb","9c"),
		   153=>array("e2","84"),		 154=>array("c5","a1"),		 155=>array("e2","80"),		 156=>array("c5","93"),
		   159=>array("c5","b8"),		 160=>array("c2","a0"),		 161=>array("c2","a1"),		 162=>array("c2","a2"),
		   163=>array("c2","a3"),		 164=>array("c2","a4"),		 165=>array("c2","a5"),		 166=>array("c2","a6"),
		   167=>array("c2","a7"),		 168=>array("c2","a8"),		 169=>array("c2","a9"),		 170=>array("c2","aa"),
		   171=>array("c2","ab"),		 172=>array("c2","ac"),		 173=>array("c2","ad"),		 174=>array("c2","ae"),
		   175=>array("c2","af"),		 176=>array("c2","b0"),		 177=>array("c2","b1"),		 178=>array("c2","b2"),
		   179=>array("c2","b3"),		 180=>array("c2","b4"),		 181=>array("c2","b5"),		 182=>array("c2","b6"),
		   183=>array("c2","b7"),		 184=>array("c2","b8"),		 185=>array("c2","b9"),		 186=>array("c2","ba"),
		   187=>array("c2","bb"),		 188=>array("c2","bc"),		 189=>array("c2","bd"),		 190=>array("c2","be"),
		   191=>array("c2","bf"),		 192=>array("c3","80"),		 193=>array("c3","81"),		 194=>array("c3","82"),
		   195=>array("c3","83"),		 196=>array("c3","84"),		 197=>array("c3","85"),		 198=>array("c3","86"),
		   199=>array("c3","87"),		 200=>array("c3","88"),		 201=>array("c3","89"),		 202=>array("c3","8a"),
		   203=>array("c3","8b"),		 204=>array("c3","8c"),		 205=>array("c3","8d"),		 206=>array("c3","8e"),
		   207=>array("c3","8f"),		 208=>array("c3","90"),		 209=>array("c3","91"),		 210=>array("c3","92"),
		   211=>array("c3","93"),		 212=>array("c3","94"),		 213=>array("c3","95"),		 214=>array("c3","96"),
		   215=>array("c3","97"),		 216=>array("c3","98"),		 217=>array("c3","99"),		 218=>array("c3","9a"),
		   219=>array("c3","9b"),		 220=>array("c3","9c"),		 221=>array("c3","9d"),		 222=>array("c3","9e"),
		   223=>array("c3","9f"),		 224=>array("c3","a0"),		 225=>array("c3","a1"),		 226=>array("c3","a2"),
		   227=>array("c3","a3"),		 228=>array("c3","a4"),		 229=>array("c3","a5"),		 230=>array("c3","a6"),
		   231=>array("c3","a7"),		 232=>array("c3","a8"),		 233=>array("c3","a9"),		 234=>array("c3","aa"),
		   235=>array("c3","ab"),		 236=>array("c3","ac"),		 237=>array("c3","ad"),		 238=>array("c3","ae"),
		   239=>array("c3","af"),		 240=>array("c3","b0"),		 241=>array("c3","b1"),		 242=>array("c3","b2"),
		   243=>array("c3","b3"),		 244=>array("c3","b4"),		 245=>array("c3","b5"),		 246=>array("c3","b6"),
		   247=>array("c3","b7"),		 248=>array("c3","b8"),		 249=>array("c3","b9"),		 250=>array("c3","ba"),
		   251=>array("c3","bb"),		 252=>array("c3","bc"),		 253=>array("c3","bd"),		 254=>array("c3","be"),
		   255=>array("c3","bf"));
$utftableB = array("e282"=>128,		   "e280"=>130,		   "c692"=>131,		   "e280"=>132,
		   "e280"=>133,		   "e280"=>134,		   "e280"=>135,		   "cb86"=>136,
		   "e280"=>137,		   "c5a0"=>138,		   "e280"=>139,		   "c592"=>140,
		   "e280"=>145,		   "e280"=>146,		   "e280"=>147,		   "e280"=>148,
		   "e280"=>149,		   "e280"=>150,		   "e280"=>151,		   "cb9c"=>152,
		   "e284"=>153,		   "c5a1"=>154,		   "e280"=>155,		   "c593"=>156,
		   "c5b8"=>159,		   "c2a0"=>160,		   "c2a1"=>161,		   "c2a2"=>162,
		   "c2a3"=>163,		   "c2a4"=>164,		   "c2a5"=>165,		   "c2a6"=>166,
		   "c2a7"=>167,		   "c2a8"=>168,		   "c2a9"=>169,		   "c2aa"=>170,
		   "c2ab"=>171,		   "c2ac"=>172,		   "c2ad"=>173,		   "c2ae"=>174,
		   "c2af"=>175,		   "c2b0"=>176,		   "c2b1"=>177,		   "c2b2"=>178,
		   "c2b3"=>179,		   "c2b4"=>180,		   "c2b5"=>181,		   "c2b6"=>182,
		   "c2b7"=>183,		   "c2b8"=>184,		   "c2b9"=>185,		   "c2ba"=>186,
		   "c2bb"=>187,		   "c2bc"=>188,		   "c2bd"=>189,		   "c2be"=>190,
		   "c2bf"=>191,		   "c380"=>192,		   "c381"=>193,		   "c382"=>194,
		   "c383"=>195,		   "c384"=>196,		   "c385"=>197,		   "c386"=>198,
		   "c387"=>199,		   "c388"=>200,		   "c389"=>201,		   "c38a"=>202,
		   "c38b"=>203,		   "c38c"=>204,		   "c38d"=>205,		   "c38e"=>206,
		   "c38f"=>207,		   "c390"=>208,		   "c391"=>209,		   "c392"=>210,
		   "c393"=>211,		   "c394"=>212,		   "c395"=>213,		   "c396"=>214,
		   "c397"=>215,		   "c398"=>216,		   "c399"=>217,		   "c39a"=>218,
		   "c39b"=>219,		   "c39c"=>220,		   "c39d"=>221,		   "c39e"=>222,
		   "c39f"=>223,		   "c3a0"=>224,		   "c3a1"=>225,		   "c3a2"=>226,
		   "c3a3"=>227,		   "c3a4"=>228,		   "c3a5"=>229,		   "c3a6"=>230,
		   "c3a7"=>231,		   "c3a8"=>232,		   "c3a9"=>233,		   "c3aa"=>234,
		   "c3ab"=>235,		   "c3ac"=>236,		   "c3ad"=>237,		   "c3ae"=>238,
		   "c3af"=>239,		   "c3b0"=>240,		   "c3b1"=>241,		   "c3b2"=>242,
		   "c3b3"=>243,		   "c3b4"=>244,		   "c3b5"=>245,		   "c3b6"=>246,
		   "c3b7"=>247,		   "c3b8"=>248,		   "c3b9"=>249,		   "c3ba"=>250,
		   "c3bb"=>251,		   "c3bc"=>252,		   "c3bd"=>253,		   "c3be"=>254,
		   "c3bf"=>255);
?>

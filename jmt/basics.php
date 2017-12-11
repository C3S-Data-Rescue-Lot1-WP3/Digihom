<?php
$pv = array_merge($_GET,$_POST);

$unibe = in_array($_SERVER['SERVER_NAME'],
		  array('130.92.133.24','www.oeschger-data.unibe.ch')); 
if($unibe) include('../../local/auto_prepend.php');

$set = array('key'=>'jmt_' . $iid,
	     'version'=>'1.4.1',
	     'date'=>'20110526');

$_tool_ = _load_tools($set);
$_tool_->add_dir('import',"%pdata%/digihom/$iid/new/");
$_tool_->add_dir('jobs',"%pdata%/digihom/$iid/jobs/");
$_tool_->add_dir('uploads',"%upload%/digihom/$iid/");

// Include libraries
$incls = array('@default','@fw','@pgdb','@auth','@ht2d_um',
	       '@ht2o_all',
	       'opc_sxml','opc_sx_text',
	       'opc_ht2form');
$_tool_->req_files($incls);

// settings for the usermanagement (um)
$um_set = array('autostart'=>TRUE,'ddef'=>'digihom',
		'umds'=>array('digihom'=>array('cls'=>'opc_umd_db_pg',
					       'connect'=>array('db'=>'digihom_' . $iid))));

// settings for the displayer of the um
$umd_set = array('pwd_mandatory'=>$_tool_->mode!='devel','autologin'=>TRUE);

// text sources; first wins!
$txp_set = array('sources'=>array('msg.xml','../../jmt/msg.xml'));

// collect settings
$set = array('db'=>'digihom_' . $iid, // db connectionn (saved in hid/.dblogins)
	     'um'=>$um_set,
	     'ht2d_um'=>$umd_set,
	     'layout'=>'std_hmf',   // standard layout with header, main column and right column (small)
	     'txp'=>$txp_set,
	     'nav'=>array('css'=>TRUE,'style'=>'divspan'),
	     );


// include and create local subclass of opc_fw
include('lcl_fw.php');
$ip = new lcl_fw($_tool_,$set);
$ip->ftp = $_tool_->load_connection('con',$ftp);

$ip->prepare_head();

if(file_Exists('favicon.ico'))
  $ip->head->favicon = TRUE;
 else
   $ip->head->favicon = $_tool_->dir('jmt','web') . 'favicon.ico';


$ip->prepare();     // prepare objects like db, um etc
$ip->process();     // process request (from form)
$ip->navigation();  // create navigation
$ip->site();        // create site content


echo $ip->output();
$ip->ses_set('site',$ip->nav->cur_get());

// removes files and non-empty directories; from promaty at gmail dot com
function rrmdir($dir) {
  if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file)
    if ($file != "." && $file != "..") rrmdir("$dir/$file");
    rmdir($dir);
  }
  else if (file_exists($dir)) unlink($dir);
} 

// copies files and non-empty directories ; from promaty at gmail dot com
function rcopy($src, $dst) {
  if (file_exists($dst)) rrmdir($dst);
  if (is_dir($src)) {
    mkdir($dst);
    $files = scandir($src);
    foreach ($files as $file)
    if ($file != "." && $file != "..") rcopy("$src/$file", "$dst/$file");
  }
  else if (file_exists($src)) copy($src, $dst);
}
?>
<?php
if(PHP_SAPI!=='cli')exit(1);
define('ABSPATH',__DIR__.'/');define('XI_FILE',dirname(__DIR__).'/xiaoman-inquiry-center/xiaoman-inquiry-center.php');define('XI_VERSION','2.0.0-beta.1');
class WP_Error {function __construct(public $code,public $message){}}
function is_wp_error($v){return $v instanceof WP_Error;}
function plugin_basename($v){return 'xiaoman-inquiry-center/xiaoman-inquiry-center.php';}
require dirname(__DIR__).'/xiaoman-inquiry-center/updater.php';
$tests=0;function verify($name,$value){global $tests;if(!$value)throw new RuntimeException($name);$tests++;}
$raw=file_get_contents(__DIR__.'/manifest.fixture.json');verify('signed_manifest',is_array(XI_Updater::envelope($raw)));
$e=json_decode($raw,true);$m=base64_decode($e['manifest']);$e['manifest']=base64_encode(str_replace('beta.1','beta.2',$m));verify('tampered_version',XI_Updater::envelope(json_encode($e))===false);
$e=json_decode($raw,true);$e['signature']=base64_encode(str_repeat('x',64));verify('tampered_signature',XI_Updater::envelope(json_encode($e))===false);
verify('external_repo_url',XI_Updater::release_url('https://github.com/another/repository/releases/download/v2.0.0/plugin.zip')===false);
verify('unrelated_plugin_check',XI_Updater::check('existing',array(),'other/other.php',array())==='existing');
verify('bulk_updates_do_not_intercept_others',XI_Updater::download('existing','https://downloads.wordpress.org/plugin/other.zip',null,array('plugins'=>array(plugin_basename(XI_FILE),'other/other.php')))==='existing');
if(class_exists('ZipArchive'))foreach(array('valid'=>'xiaoman-inquiry-center/xiaoman-inquiry-center.php','wrong_root'=>'wrong/plugin.php','traversal'=>'xiaoman-inquiry-center/../evil.php','windows_separators'=>'xiaoman-inquiry-center\\xiaoman-inquiry-center.php','missing_entry'=>'xiaoman-inquiry-center/other.php') as $case=>$entry){$tmp=tempnam(sys_get_temp_dir(),'xi-package-');try{$z=new ZipArchive();$z->open($tmp,ZipArchive::OVERWRITE);$z->addFromString($entry,'<?php // fixture');$z->close();$result=XI_Updater::validate_zip($tmp);verify($case,$case==='valid'?$result===true:is_wp_error($result));}finally{unlink($tmp);}}
echo json_encode(array('php'=>PHP_VERSION,'passed'=>$tests,'suite'=>'signed updater and package layout'))."\n";

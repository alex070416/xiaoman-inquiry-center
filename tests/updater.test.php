<?php
if(PHP_SAPI!=='cli')exit(1);
define('ABSPATH',__DIR__.'/');define('XI_FILE',dirname(__DIR__).'/xiaoman-inquiry-center/xiaoman-inquiry-center.php');define('XI_VERSION','2.0.0-beta.7');define('HOUR_IN_SECONDS',3600);
class WP_Error {function __construct(public $code,public $message){}}
function is_wp_error($v){return $v instanceof WP_Error;}
function plugin_basename($v){return 'xiaoman-inquiry-center/xiaoman-inquiry-center.php';}
// Offline GitHub: each URL answers from $net; nothing is cached between cases.
$net=array();
function get_transient($k){return false;}
function set_transient($k,$v,$t){return true;}
function wp_remote_get($url,$args){global $net;if(($args['redirection']??null)!==0)throw new RuntimeException('API lookups must not follow redirects');return $net[$url]??array('code'=>404,'body'=>'');}
function wp_safe_remote_get($url,$args){global $net;return $net[$url]??array('code'=>404,'body'=>'');}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
require dirname(__DIR__).'/xiaoman-inquiry-center/updater.php';
$tests=0;function verify($name,$value){global $tests;if(!$value)throw new RuntimeException($name);$tests++;}
$raw=file_get_contents(__DIR__.'/manifest.fixture.json');verify('signed_manifest',is_array(XI_Updater::envelope($raw)));
$e=json_decode($raw,true);$m=base64_decode($e['manifest']);$e['manifest']=base64_encode(str_replace('beta.7','beta.8',$m));verify('tampered_version',XI_Updater::envelope(json_encode($e))===false);
$e=json_decode($raw,true);$e['signature']=base64_encode(str_repeat('x',64));verify('tampered_signature',XI_Updater::envelope(json_encode($e))===false);
verify('external_repo_url',XI_Updater::release_url('https://github.com/another/repository/releases/download/v2.0.0/plugin.zip')===false);
verify('project_repo_url',XI_Updater::release_repo('https://github.com/cixin123/xiaoman-inquiry-center/releases/download/v2.0.0/plugin.zip')==='cixin123/xiaoman-inquiry-center');
verify('previous_repo_url',XI_Updater::release_repo('https://github.com/alex070416/xiaoman-inquiry-center/releases/download/v2.0.0/plugin.zip')==='alex070416/xiaoman-inquiry-center');
verify('lookalike_repo_url',XI_Updater::release_url('https://github.com/cixin123/xiaoman-inquiry-center-fork/releases/download/v2.0.0/plugin.zip')===false);
verify('unrelated_plugin_check',XI_Updater::check('existing',array(),'other/other.php',array())==='existing');
verify('bulk_updates_do_not_intercept_others',XI_Updater::download('existing','https://downloads.wordpress.org/plugin/other.zip',null,array('plugins'=>array(plugin_basename(XI_FILE),'other/other.php')))==='existing');
// Release discovery across the two known repository locations.
function api($repo){return 'https://api.github.com/repos/'.$repo.'/releases/latest';}
function asset($repo){return 'https://github.com/'.$repo.'/releases/download/v2.0.0-beta.7/manifest.json';}
function release($repo,$prerelease=false){return array('code'=>200,'body'=>json_encode(array('tag_name'=>'v2.0.0-beta.7','draft'=>false,'prerelease'=>$prerelease,'assets'=>array(array('name'=>'manifest.json','browser_download_url'=>asset($repo))))));}
$primary='cixin123/xiaoman-inquiry-center';$previous='alex070416/xiaoman-inquiry-center';
$fixturePrevious=file_get_contents(__DIR__.'/manifest.fixture.previous.json');
$net=array(api($primary)=>release($primary),asset($primary)=>array('code'=>200,'body'=>$raw));
$d=XI_Updater::fetch();verify('primary_repository_release',is_array($d)&&$d['version']==='2.0.0-beta.7'&&XI_Updater::release_repo($d['package'])===$primary);
$net=array(api($primary)=>array('code'=>301,'body'=>''),api($previous)=>release($previous),asset($previous)=>array('code'=>200,'body'=>$fixturePrevious));
$d=XI_Updater::fetch();verify('moved_repository_falls_back_to_previous',is_array($d)&&XI_Updater::release_repo($d['package'])===$previous);
$net=array(api($primary)=>array('code'=>301,'body'=>''),api($previous)=>release($previous),asset($previous)=>array('code'=>200,'body'=>$raw));
verify('package_must_belong_to_the_answering_repository',XI_Updater::fetch()===false);
$net=array(api($primary)=>release($primary,true),api($previous)=>release($previous),asset($previous)=>array('code'=>200,'body'=>$fixturePrevious));
verify('prerelease_is_ignored_without_falling_back',XI_Updater::fetch()===false);
$net=array(api($primary)=>array('code'=>301,'body'=>''),api($previous)=>array('code'=>404,'body'=>''));
verify('no_repository_answers',XI_Updater::fetch()===false);
if(class_exists('ZipArchive'))foreach(array('valid'=>'xiaoman-inquiry-center/xiaoman-inquiry-center.php','wrong_root'=>'wrong/plugin.php','traversal'=>'xiaoman-inquiry-center/../evil.php','windows_separators'=>'xiaoman-inquiry-center\\xiaoman-inquiry-center.php','missing_entry'=>'xiaoman-inquiry-center/other.php') as $case=>$entry){$tmp=tempnam(sys_get_temp_dir(),'xi-package-');try{$z=new ZipArchive();$z->open($tmp,ZipArchive::OVERWRITE);$z->addFromString($entry,'<?php // fixture');$z->close();$result=XI_Updater::validate_zip($tmp);verify($case,$case==='valid'?$result===true:is_wp_error($result));}finally{unlink($tmp);}}
echo json_encode(array('php'=>PHP_VERSION,'passed'=>$tests,'suite'=>'signed updater and package layout'))."\n";

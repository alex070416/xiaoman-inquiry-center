<?php
if(!defined('ABSPATH'))exit;
final class XI_Updater {
 const REPO='alex070416/xiaoman-inquiry-center';
 static function boot(){add_filter('update_plugins_github.com',array(__CLASS__,'check'),10,4);add_filter('upgrader_pre_download',array(__CLASS__,'download'),10,4);}
 static function release_url($url){return is_string($url)&&preg_match('#^https://github\.com/'.preg_quote(self::REPO,'#').'/releases/download/v[0-9A-Za-z.+_-]+/[a-zA-Z0-9._-]+$#D',$url);}
 static function envelope($raw){
  $e=json_decode($raw,true);if(!is_array($e)||!is_string($e['manifest']??null)||!is_string($e['signature']??null))return false;
  $m=base64_decode($e['manifest'],true);$sig=base64_decode($e['signature'],true);$pub=base64_decode(trim((string)@file_get_contents(__DIR__.'/release-public-key.txt')),true);
  if(!function_exists('sodium_crypto_sign_verify_detached')||!$m||strlen($sig?:'')!==64||strlen($pub?:'')!==32)return false;
  if(!sodium_crypto_sign_verify_detached($sig,$m,$pub))return false;
  $d=json_decode($m,true);if(!is_array($d)||($d['slug']??'')!=='xiaoman-inquiry-center'||!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.]+)?$/D',$d['version']??'')||!preg_match('/^[a-f0-9]{64}$/D',$d['sha256']??'')||!self::release_url($d['package']??''))return false;
  if(strpos($d['package'],'/v'.$d['version'].'/')===false)return false;
  return $d;
 }
 static function fetch(){
  $cached=get_transient('xi_release_v1');if(is_array($cached))return $cached;
  $r=wp_remote_get('https://api.github.com/repos/'.self::REPO.'/releases/latest',array('timeout'=>8,'redirection'=>0,'headers'=>array('Accept'=>'application/vnd.github+json')));
  if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)return false;
  $release=json_decode(wp_remote_retrieve_body($r),true);if(!is_array($release)||!empty($release['draft'])||!empty($release['prerelease']))return false;
  $asset='';foreach(($release['assets']??array()) as $a)if(($a['name']??'')==='manifest.json')$asset=$a['browser_download_url']??'';
  if(!self::release_url($asset))return false;
  $r=wp_safe_remote_get($asset,array('timeout'=>8,'redirection'=>5,'limit_response_size'=>24000));if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)return false;
  $d=self::envelope(wp_remote_retrieve_body($r));if(!$d||($release['tag_name']??'')!=='v'.$d['version'])return false;
  set_transient('xi_release_v1',$d,6*HOUR_IN_SECONDS);return $d;
 }
 static function check($update,$data,$file,$locales){
  if($file!==plugin_basename(XI_FILE))return $update;
  $d=self::fetch();if(!$d||!version_compare($d['version'],XI_VERSION,'>'))return false;
  return array('id'=>'https://github.com/'.self::REPO,'slug'=>'xiaoman-inquiry-center','version'=>$d['version'],'url'=>'https://github.com/'.self::REPO.'/releases/tag/v'.$d['version'],'package'=>$d['package'],'requires'=>'6.9','requires_php'=>'8.2','autoupdate'=>true);
 }
 static function validate_zip($file){
  if(!class_exists('ZipArchive'))return new WP_Error('xi_zip','ZIP 扩展不可用。');$z=new ZipArchive();if($z->open($file)!==true)return new WP_Error('xi_zip','升级包不是有效 ZIP。');$entry=false;
  try{for($i=0;$i<$z->numFiles;$i++){$n=$z->getNameIndex($i);if(!is_string($n)||strpos($n,'\\')!==false||strpos($n,'..')!==false||strpos($n,'xiaoman-inquiry-center/')!==0)return new WP_Error('xi_layout','升级包目录结构不符合要求。');$s=$z->statIndex($i);if(($s['size']??0)>10*1024*1024)return new WP_Error('xi_layout','升级包包含异常大文件。');if($n==='xiaoman-inquiry-center/xiaoman-inquiry-center.php')$entry=true;}return $entry?true:new WP_Error('xi_layout','升级包缺少主文件。');}finally{$z->close();}
 }
 static function download($reply,$package,$upgrader,$extra){
  $is_ours=($extra['plugin']??'')===plugin_basename(XI_FILE);
  if(!$is_ours&&!self::release_url($package))return $reply;
  if(is_wp_error($reply))return $reply;
  if($is_ours&&is_string($package)&&is_file($package))return $reply; // Explicit local upload / rollback uses WordPress' installer.
  $d=self::fetch();if(!$d||$package!==$d['package'])return new WP_Error('xi_signature','无法核对升级包签名，请稍后重试。');
  if(!function_exists('download_url'))require_once ABSPATH.'wp-admin/includes/file.php';
  $file=download_url($package,60);if(is_wp_error($file))return $file;
  if(filesize($file)>20*1024*1024||!hash_equals($d['sha256'],hash_file('sha256',$file))){wp_delete_file($file);return new WP_Error('xi_checksum','升级包校验失败，已停止升级。');}
  $valid=self::validate_zip($file);if(is_wp_error($valid)){wp_delete_file($file);return $valid;}return $file;
 }
}

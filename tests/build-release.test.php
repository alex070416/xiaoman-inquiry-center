<?php
// Pure CLI fixtures: never read the real signing seed or sign the real plugin tree.
if(PHP_SAPI!=='cli')exit(1);
if(!extension_loaded('sodium')||!extension_loaded('zip'))throw new RuntimeException('This offline test requires Sodium and ZIP.');
$checks=0;
function build_assert($name,$ok){global $checks;if(!$ok)throw new RuntimeException($name);$checks++;}
function build_process($args){
 $pipes=array();$p=proc_open($args,array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
 if(!is_resource($p))throw new RuntimeException('Could not start fixture process');
 fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 return array('code'=>proc_close($p),'out'=>$out,'err'=>$err);
}
function build_fixture($dir,$source,$public){
 mkdir($dir.'/repo/tools',0700,true);mkdir($dir.'/repo/xiaoman-inquiry-center',0700,true);
 copy($source,$dir.'/repo/tools/build-release.php');
 $main="<?php\n/**\n * Version: 2.0.0-beta.9\n */\n";
 file_put_contents($dir.'/repo/xiaoman-inquiry-center/xiaoman-inquiry-center.php',$main);
 if($public!==null)file_put_contents($dir.'/repo/xiaoman-inquiry-center/release-public-key.txt',$public);
 return $main;
}
function build_remove_fixture($path,$root){
 $normal=str_replace('\\','/',realpath($path)?:'');$bound=str_replace('\\','/',$root);
 if($normal!==$bound&&strpos($normal,$bound.'/')!==0)throw new RuntimeException('Fixture cleanup escaped its exact temporary root');
 foreach(new FilesystemIterator($path,FilesystemIterator::SKIP_DOTS) as $entry){
  if($entry->isDir()&&!$entry->isLink())build_remove_fixture($entry->getPathname(),$root);else unlink($entry->getPathname());
 }
 rmdir($path);
}
$tempParent=realpath(sys_get_temp_dir());if($tempParent===false)throw new RuntimeException('Temporary root unavailable');
$fixture=$tempParent.'/xi-build-release-'.bin2hex(random_bytes(8));mkdir($fixture,0700);
$fixture=realpath($fixture);$tempPrefix=str_replace('\\','/',$tempParent).'/xi-build-release-';
if(!$fixture||strpos(str_replace('\\','/',$fixture),$tempPrefix)!==0)throw new RuntimeException('Unexpected fixture root');
$builder=dirname(__DIR__).'/tools/build-release.php';
$seed=str_repeat("\x01",SODIUM_CRYPTO_SIGN_SEEDBYTES);$pair=sodium_crypto_sign_seed_keypair($seed);
$public=sodium_crypto_sign_publickey($pair);$publicText=base64_encode($public)."\n";sodium_memzero($pair);
$otherPair=sodium_crypto_sign_seed_keypair(str_repeat("\x02",SODIUM_CRYPTO_SIGN_SEEDBYTES));
$wrongText=base64_encode(sodium_crypto_sign_publickey($otherPair))."\n";sodium_memzero($otherPair);
$php=array(PHP_BINARY);$ini=php_ini_loaded_file();if($ini)$php=array_merge($php,array('-c',$ini));
$php=array_merge($php,array('-d','extension_dir='.ini_get('extension_dir')));
// Propagate explicitly loaded CLI modules only when the same binary's child lacks them.
$probe=build_process(array_merge($php,array('-r','echo json_encode(array("sodium"=>extension_loaded("sodium"),"zip"=>extension_loaded("zip")));')));
$loaded=json_decode($probe['out'],true);build_assert('child runtime probe',$probe['code']===0&&is_array($loaded));
foreach(array('sodium','zip') as $ext)if(empty($loaded[$ext]))$php=array_merge($php,array('-d','extension='.$ext));
try{
 foreach(array('missing-seed','wrong-public-key','missing-public-key','normal') as $case){
  $dir=$fixture.'/'.$case;mkdir($dir,0700);
  $key=$case==='missing-public-key'?null:($case==='wrong-public-key'?$wrongText:$publicText);
  $main=build_fixture($dir,$builder,$key);$seedPath=$dir.'/synthetic.seed';
  if($case!=='missing-seed')file_put_contents($seedPath,$seed);
  $pubPath=$dir.'/repo/xiaoman-inquiry-center/release-public-key.txt';$outDir=$dir.'/output';
  $r=build_process(array_merge($php,array($dir.'/repo/tools/build-release.php',$seedPath,$outDir)));
  build_assert($case.' public file unchanged',$key===null?!file_exists($pubPath):file_get_contents($pubPath)===$key);
  build_assert($case.' main file unchanged',file_get_contents($dir.'/repo/xiaoman-inquiry-center/xiaoman-inquiry-center.php')===$main);
  build_assert($case.' synthetic seed unchanged',$case==='missing-seed'?!file_exists($seedPath):file_get_contents($seedPath)===$seed);
  if($case!=='normal'){
   build_assert($case.' fails before output',$r['code']!==0&&!file_exists($outDir));
   $message=$case==='missing-seed'?'existing signing seed':'release public key';
   build_assert($case.' expected identity failure',stripos($r['out'].$r['err'],$message)!==false);
   continue;
  }
  build_assert('normal fixture succeeds',$r['code']===0&&is_array(json_decode($r['out'],true)));
  $envelope=json_decode(file_get_contents($outDir.'/manifest.json'),true);
  $manifestBytes=base64_decode($envelope['manifest'],true);$signature=base64_decode($envelope['signature'],true);
  build_assert('synthetic signature verifies',sodium_crypto_sign_verify_detached($signature,$manifestBytes,$public));
  $manifest=json_decode($manifestBytes,true);$zipPath=$outDir.'/xiaoman-inquiry-center-2.0.0-beta.9.zip';
  build_assert('version and slug match',$manifest['version']==='2.0.0-beta.9'&&$manifest['slug']==='xiaoman-inquiry-center');
  build_assert('versioned package URL matches',$manifest['package']==='https://github.com/alex070416/xiaoman-inquiry-center/releases/download/v2.0.0-beta.9/xiaoman-inquiry-center-2.0.0-beta.9.zip');
  build_assert('ZIP hash matches manifest',hash_file('sha256',$zipPath)===$manifest['sha256']);
  build_assert('SHA256SUMS matches ZIP',file_get_contents($outDir.'/SHA256SUMS')===$manifest['sha256'].'  '.basename($zipPath)."\n");
  $zip=new ZipArchive();build_assert('synthetic ZIP opens',$zip->open($zipPath)===true);
  try{
   build_assert('only two fixture files packaged',$zip->numFiles===2);
   build_assert('ZIP main bytes preserved',$zip->getFromName('xiaoman-inquiry-center/xiaoman-inquiry-center.php')===$main);
   build_assert('ZIP original public key preserved',$zip->getFromName('xiaoman-inquiry-center/release-public-key.txt')===$publicText);
  }finally{$zip->close();}
 }
}finally{
 sodium_memzero($seed);build_remove_fixture($fixture,$fixture);
}
echo json_encode(array('php'=>PHP_VERSION,'passed'=>$checks,'suite'=>'existing signing identity and synthetic release builder','real_seed_read'=>false,'real_plugin_signed'=>false,'temporary_fixture_cleaned'=>!file_exists($fixture)),JSON_UNESCAPED_SLASHES)."\n";

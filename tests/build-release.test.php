<?php
// Pure CLI fixtures: never read the real signing seed or sign the real plugin tree.
if(PHP_SAPI!=='cli')exit(1);
if(!extension_loaded('sodium')||!extension_loaded('zip'))throw new RuntimeException('This offline test requires Sodium and ZIP.');
$checks=0;$skipped=array();
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
 copy(dirname($source).'/verify-release.php',$dir.'/repo/tools/verify-release.php');
 $main="<?php\n/**\n * Version: 2.0.0-beta.9\n */\ndefine('XI_VERSION','2.0.0-beta.9');\n";
 file_put_contents($dir.'/repo/xiaoman-inquiry-center/xiaoman-inquiry-center.php',$main);
 if($public!==null)file_put_contents($dir.'/repo/xiaoman-inquiry-center/release-public-key.txt',$public);
 return $main;
}
function build_remove_fixture($path,$root){
 $normal=str_replace('\\','/',realpath($path)?:'');$bound=str_replace('\\','/',$root);
 if($normal!==$bound&&strpos($normal,$bound.'/')!==0)throw new RuntimeException('Fixture cleanup escaped its exact temporary root');
 foreach(new FilesystemIterator($path,FilesystemIterator::SKIP_DOTS) as $entry){
  if($entry->isDir()&&!$entry->isLink())build_remove_fixture($entry->getPathname(),$root);else {if(!$entry->isLink())chmod($entry->getPathname(),0600);unlink($entry->getPathname());}
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
  build_assert('builder self verification succeeds',json_decode($r['out'],true)['verified']===true);
  $envelope=json_decode(file_get_contents($outDir.'/manifest.json'),true);
  $manifestBytes=base64_decode($envelope['manifest'],true);$signature=base64_decode($envelope['signature'],true);
  build_assert('synthetic signature verifies',sodium_crypto_sign_verify_detached($signature,$manifestBytes,$public));
  $manifest=json_decode($manifestBytes,true);$zipPath=$outDir.'/xiaoman-inquiry-center-2.0.0-beta.9.zip';
  build_assert('version and slug match',$manifest['version']==='2.0.0-beta.9'&&$manifest['slug']==='xiaoman-inquiry-center');
  build_assert('versioned package URL matches',$manifest['package']==='https://github.com/cixin123/xiaoman-inquiry-center/releases/download/v2.0.0-beta.9/xiaoman-inquiry-center-2.0.0-beta.9.zip');
  build_assert('ZIP hash matches manifest',hash_file('sha256',$zipPath)===$manifest['sha256']);
  build_assert('source provenance included',preg_match('/^[a-f0-9]{64}$/D',$manifest['provenance']['source_sha256'])===1&&$manifest['provenance']['commit']===null&&$manifest['provenance']['tracked_tree_clean']===null);
  build_assert('SHA256SUMS matches ZIP',file_get_contents($outDir.'/SHA256SUMS')===$manifest['sha256'].'  '.basename($zipPath)."\n");
  $zip=new ZipArchive();build_assert('synthetic ZIP opens',$zip->open($zipPath)===true);
  try{
   build_assert('only two fixture files packaged',$zip->numFiles===2);
   build_assert('ZIP main bytes preserved',$zip->getFromName('xiaoman-inquiry-center/xiaoman-inquiry-center.php')===$main);
   build_assert('ZIP original public key preserved',$zip->getFromName('xiaoman-inquiry-center/release-public-key.txt')===$publicText);
  }finally{$zip->close();}
  $before=array_map('hash_file',array_fill(0,3,'sha256'),array($zipPath,$outDir.'/manifest.json',$outDir.'/SHA256SUMS'));
  $repeat=build_process(array_merge($php,array($dir.'/repo/tools/build-release.php',$seedPath,$outDir)));
  build_assert('same-version rebuild fails without overwriting',$repeat['code']!==0&&stripos($repeat['err'],'already exists')!==false&&$before===array_map('hash_file',array_fill(0,3,'sha256'),array($zipPath,$outDir.'/manifest.json',$outDir.'/SHA256SUMS')));
  $verified=build_process(array_merge($php,array($dir.'/repo/tools/verify-release.php',$zipPath,$outDir.'/manifest.json',$pubPath,$outDir.'/SHA256SUMS')));
  build_assert('independent verifier CLI accepts full artifact set',$verified['code']===0&&json_decode($verified['out'],true)['verified']===true);
 }
 foreach(array('mismatch','missing-XI_VERSION','unsafe-version','output-inside-source','output-nested-inside-source','private-file','private-directory','seed-inside-repo','output-contains-seed','existing-manifest','existing-sums','require-clean-without-git','symlink-file','symlink-directory') as $case){
  $dir=$fixture.'/'.$case;mkdir($dir,0700);$main=build_fixture($dir,$builder,$publicText);$plugin=$dir.'/repo/xiaoman-inquiry-center';$seedPath=$dir.'/synthetic.seed';file_put_contents($seedPath,$seed);$outDir=$dir.'/output';$args=array();$message='';
  switch($case){
   case 'mismatch':file_put_contents($plugin.'/xiaoman-inquiry-center.php',str_replace("define('XI_VERSION','2.0.0-beta.9')","define('XI_VERSION','2.0.0-beta.8')",$main));$message='must match';break;
   case 'missing-XI_VERSION':file_put_contents($plugin.'/xiaoman-inquiry-center.php',"<?php\n/**\n * Version: 2.0.0-beta.9\n */\n// define('XI_VERSION','2.0.0-beta.9');\n");$message='must match';break;
   case 'unsafe-version':file_put_contents($plugin.'/xiaoman-inquiry-center.php',str_replace('2.0.0-beta.9','2.0.0/../secret',$main));$message='Version header';break;
   case 'output-inside-source':$outDir=$plugin;$message='outside the plugin';break;
   case 'output-nested-inside-source':$outDir=$plugin.'/new/deep/dist';$message='outside the plugin';break;
   case 'private-file':file_put_contents($plugin.'/.env.local','fixture-private');$message='private file';break;
   case 'private-directory':mkdir($plugin.'/.git');file_put_contents($plugin.'/.git/config','fixture-private');$message='private file';break;
   case 'seed-inside-repo':$seedPath=$dir.'/repo/synthetic.seed';file_put_contents($seedPath,$seed);$message='outside the repository';break;
   case 'output-contains-seed':$outDir=$dir;$message='contain the signing seed';break;
   case 'existing-manifest':case 'existing-sums':mkdir($outDir);file_put_contents($outDir.'/'.($case==='existing-manifest'?'manifest.json':'SHA256SUMS'),'old artifact');$message='already exists';break;
   case 'require-clean-without-git':$args[]='--require-clean';$message='clean tracked tree';break;
   case 'symlink-file':case 'symlink-directory':
    $target=$case==='symlink-file'?$seedPath:$dir.'/external';if($case==='symlink-directory'){mkdir($target);file_put_contents($target.'/private.txt','fixture-private');}
    if(!@symlink($target,$plugin.'/external-link')){$skipped[]=$case.' (host does not permit symbolic links)';continue 2;}$message='Symbolic links';break;
  }
  $r=build_process(array_merge($php,array($dir.'/repo/tools/build-release.php',$seedPath,$outDir),$args));
  build_assert($case.' rejected',$r['code']!==0&&stripos($r['err'],$message)!==false);
  build_assert($case.' no ZIP emitted',!file_exists($outDir.'/xiaoman-inquiry-center-2.0.0-beta.9.zip'));
 }
 // These commits exist only in the disposable synthetic fixture repository.
 $dir=$fixture.'/git-provenance';mkdir($dir,0700);build_fixture($dir,$builder,$publicText);$seedPath=$dir.'/synthetic.seed';file_put_contents($seedPath,$seed);$repo=$dir.'/repo';
 foreach(array(array('init','--quiet'),array('add','.'),array('-c','user.name=Fixture','-c','user.email=fixture@example.invalid','-c','commit.gpgsign=false','commit','--quiet','-m','Synthetic fixture')) as $gitArgs){$r=build_process(array_merge(array('git','-C',$repo),$gitArgs));build_assert('fixture git command succeeds',$r['code']===0);}
 $clean=build_process(array_merge($php,array($repo.'/tools/build-release.php',$seedPath,$dir.'/clean-output','--require-clean')));$cleanResult=json_decode($clean['out'],true);
 build_assert('explicit clean gate accepts clean fixture Git checkout',$clean['code']===0&&$cleanResult['provenance']['tracked_tree_clean']===true&&$cleanResult['provenance']['plugin_tree_clean']===true&&preg_match('/^[a-f0-9]{40,64}$/D',$cleanResult['provenance']['commit'])===1);
 file_put_contents($repo.'/xiaoman-inquiry-center/fixture.txt','new untracked fixture');
 $untracked=build_process(array_merge($php,array($repo.'/tools/build-release.php',$seedPath,$dir.'/untracked-output','--require-clean')));
 build_assert('clean gate rejects untracked packaged files',$untracked['code']!==0&&!file_exists($dir.'/untracked-output'));
 file_put_contents($repo.'/.git/info/exclude',"\nxiaoman-inquiry-center/fixture.txt\n",FILE_APPEND);
 $ignored=build_process(array_merge($php,array($repo.'/tools/build-release.php',$seedPath,$dir.'/ignored-output','--require-clean')));
 build_assert('clean gate rejects ignored packaged files',$ignored['code']!==0&&!file_exists($dir.'/ignored-output'));
 file_put_contents($repo.'/xiaoman-inquiry-center/xiaoman-inquiry-center.php',"\n// fixture edit\n",FILE_APPEND);
 $dirty=build_process(array_merge($php,array($repo.'/tools/build-release.php',$seedPath,$dir.'/dirty-output','--require-clean')));
 build_assert('clean gate rejects dirty tracked tree',$dirty['code']!==0&&!file_exists($dir.'/dirty-output'));
 $allowed=build_process(array_merge($php,array($repo.'/tools/build-release.php',$seedPath,$dir.'/allowed-output')));$allowedResult=json_decode($allowed['out'],true);
 build_assert('default build records dirty state without blocking fixture',$allowed['code']===0&&$allowedResult['provenance']['tracked_tree_clean']===false&&$allowedResult['provenance']['plugin_tree_clean']===false);
}finally{
 sodium_memzero($seed);build_remove_fixture($fixture,$fixture);
}
build_assert('temporary fixtures completely removed',!file_exists($fixture));
echo json_encode(array('php'=>PHP_VERSION,'passed'=>$checks,'skipped'=>$skipped,'suite'=>'existing signing identity and synthetic release builder','real_seed_read'=>false,'real_plugin_signed'=>false,'temporary_fixture_cleaned'=>!file_exists($fixture)),JSON_UNESCAPED_SLASHES)."\n";

<?php
// Run locally. The private signing seed must live OUTSIDE the repository.
if(PHP_SAPI!=='cli')exit(1);
if(!extension_loaded('sodium')||!extension_loaded('zip'))throw new RuntimeException('Sodium and ZIP extensions are required.');
$repo=dirname(__DIR__);$root=$repo.'/xiaoman-inquiry-center';
$seedPath=$argv[1]??'';if(!$seedPath)throw new RuntimeException('Usage: php tools/build-release.php /private/path/release.seed [output directory]');
$parent=realpath(dirname($seedPath));$repoReal=realpath($repo);
if(!$parent||stripos(str_replace('\\','/',$parent).'/',str_replace('\\','/',$repoReal).'/')===0)throw new RuntimeException('Signing seed must be outside the repository.');
if(!file_exists($seedPath)){file_put_contents($seedPath,random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES),LOCK_EX);chmod($seedPath,0600);}
$seed=file_get_contents($seedPath);if(strlen($seed)!==SODIUM_CRYPTO_SIGN_SEEDBYTES)throw new RuntimeException('Invalid signing seed length.');
$pair=sodium_crypto_sign_seed_keypair($seed);$public=sodium_crypto_sign_publickey($pair);$secret=sodium_crypto_sign_secretkey($pair);
file_put_contents($root.'/release-public-key.txt',base64_encode($public)."\n");
$source=file_get_contents($root.'/xiaoman-inquiry-center.php');if(!preg_match('/ \* Version: ([^\r\n]+)/',$source,$match))throw new RuntimeException('Version header missing.');$version=trim($match[1]);
$output=$argv[2]??$repo.'/dist';if(!is_dir($output))mkdir($output,0700,true);
$asset='xiaoman-inquiry-center-'.$version.'.zip';$zipPath=$output.'/'.$asset;
$z=new ZipArchive();$z->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE);
$entries=array();foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile())$entries[]=str_replace('\\','/',$file->getPathname());sort($entries);
foreach($entries as $path){$relative=substr($path,strlen(str_replace('\\','/',$root))+1);if(preg_match('/(^|\/)(\.git|\.env|wp-config\.php|.*\.(seed|key|sqlite|log))($|\/)/',$relative))throw new RuntimeException('Unexpected private file in plugin tree.');$entry='xiaoman-inquiry-center/'.$relative;$z->addFile($path,$entry);$z->setMtimeName($entry,946684800);}
$z->close();
$manifest=json_encode(array('slug'=>'xiaoman-inquiry-center','version'=>$version,'package'=>'https://github.com/alex070416/xiaoman-inquiry-center/releases/download/v'.$version.'/'.$asset,'sha256'=>hash_file('sha256',$zipPath),'requires'=>'6.9','requires_php'=>'8.2'),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$envelope=json_encode(array('manifest'=>base64_encode($manifest),'signature'=>base64_encode(sodium_crypto_sign_detached($manifest,$secret))),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
file_put_contents($output.'/manifest.json',$envelope."\n");file_put_contents($output.'/SHA256SUMS',hash_file('sha256',$zipPath).'  '.$asset."\n");sodium_memzero($secret);sodium_memzero($seed);
echo json_encode(array('version'=>$version,'zip'=>$zipPath,'sha256'=>hash_file('sha256',$zipPath),'manifest'=>$output.'/manifest.json','files'=>count($entries)),JSON_UNESCAPED_SLASHES)."\n";

<?php
// Entirely synthetic offline archives and signing identity; no real release seed is read.
if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__).'/tools/verify-release.php';
xi_release_extensions();
$checks = 0;
function release_assert($name, $ok) { global $checks; if (!$ok) throw new RuntimeException($name); $checks++; }
function release_cleanup($path, $root) {
    $resolved = realpath($path);
    if (!$resolved || !xi_release_within($resolved, $root)) throw new RuntimeException('Fixture cleanup escaped its temporary root.');
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) release_cleanup($entry->getPathname(), $root);
        else { if (!$entry->isLink()) chmod($entry->getPathname(), 0600); unlink($entry->getPathname()); }
    }
    rmdir($path);
}
function release_envelope($path, $manifest, $secret) {
    $bytes = json_encode($manifest, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    file_put_contents($path, json_encode(array('manifest'=>base64_encode($bytes),'signature'=>base64_encode(sodium_crypto_sign_detached($bytes, $secret))), JSON_THROW_ON_ERROR));
}
$parent = realpath(sys_get_temp_dir());
if (!$parent) throw new RuntimeException('Temporary directory unavailable.');
$fixture = $parent.'/xi-verify-release-'.bin2hex(random_bytes(8)); mkdir($fixture, 0700); $fixture = realpath($fixture);
if (!$fixture || strpos(str_replace('\\','/',$fixture), str_replace('\\','/',$parent).'/xi-verify-release-') !== 0) throw new RuntimeException('Unsafe fixture root.');
$seed = str_repeat("\x31", SODIUM_CRYPTO_SIGN_SEEDBYTES); $pair = sodium_crypto_sign_seed_keypair($seed);
$secret = sodium_crypto_sign_secretkey($pair); $public = sodium_crypto_sign_publickey($pair); sodium_memzero($pair); sodium_memzero($seed);
$other = sodium_crypto_sign_seed_keypair(str_repeat("\x32", SODIUM_CRYPTO_SIGN_SEEDBYTES));
$wrongPublic = sodium_crypto_sign_publickey($other); sodium_memzero($other);
$main = "<?php\n/**\n * Version: 2.0.0-beta.9\n */\ndefine('XI_VERSION','2.0.0-beta.9');\n";
$asset = 'xiaoman-inquiry-center-2.0.0-beta.9.zip'; $prefix = 'xiaoman-inquiry-center/';
$cases = array('normal','wrong-trusted-key','wrong-bundled-key','invalid-bundled-key','bad-signature','hash-mismatch','tampered-manifest','wrong-repository','wrong-tag','wrong-asset','wrong-version','XI_VERSION-mismatch','missing-XI_VERSION','duplicate-version-header','duplicate-XI_VERSION','missing-main','missing-public','wrong-root','absolute-path','path-traversal','backslash','empty-segment','repeated-trailing-slash','windows-drive','windows-reserved','trailing-dot','duplicate-case','duplicate-exact','symlink','directory-link','file-directory-collision','case-file-directory-collision','directory-file-attributes','file-directory-attributes','private-file','oversized-member','wrong-source-digest','wrong-SHA256SUMS','wrong-filename','oversized-manifest','missing-manifest');
try {
    foreach ($cases as $case) {
        $dir = $fixture.'/'.$case; mkdir($dir, 0700); $zipPath = $dir.'/'.$asset;
        $files = array($prefix.'xiaoman-inquiry-center.php'=>$main, $prefix.'release-public-key.txt'=>base64_encode($public)."\n");
        $manifest = array('slug'=>'xiaoman-inquiry-center','version'=>'2.0.0-beta.9','package'=>xi_release_package('2.0.0-beta.9'),'sha256'=>'');
        $trust = $public; $sums = false;
        switch ($case) {
            case 'wrong-trusted-key': $trust = $wrongPublic; break;
            case 'wrong-bundled-key': $files[$prefix.'release-public-key.txt'] = base64_encode($wrongPublic); break;
            case 'invalid-bundled-key': $files[$prefix.'release-public-key.txt'] = 'not a public key'; break;
            case 'wrong-repository': $manifest['package'] = str_replace('cixin123','attacker',$manifest['package']); break;
            case 'wrong-tag': $manifest['package'] = str_replace('/v2.0.0-beta.9/','/v2.0.0-beta.8/',$manifest['package']); break;
            case 'wrong-asset': $manifest['package'] = str_replace('.zip','-other.zip',$manifest['package']); break;
            case 'wrong-version': $files[$prefix.'xiaoman-inquiry-center.php'] = str_replace('2.0.0-beta.9','2.0.0-beta.8',$main); break;
            case 'XI_VERSION-mismatch': $files[$prefix.'xiaoman-inquiry-center.php'] = str_replace("define('XI_VERSION','2.0.0-beta.9')","define('XI_VERSION','2.0.0-beta.8')",$main); break;
            case 'missing-XI_VERSION': $files[$prefix.'xiaoman-inquiry-center.php'] = str_replace("define('XI_VERSION','2.0.0-beta.9');","// define('XI_VERSION','2.0.0-beta.9');",$main); break;
            case 'duplicate-version-header': $files[$prefix.'xiaoman-inquiry-center.php'] = str_replace(' * Version:'," * Version: 2.0.0-beta.9\n * Version:",$main); break;
            case 'duplicate-XI_VERSION': $files[$prefix.'xiaoman-inquiry-center.php'] .= "define('XI_VERSION','2.0.0-beta.9');\n"; break;
            case 'missing-main': unset($files[$prefix.'xiaoman-inquiry-center.php']); break;
            case 'missing-public': unset($files[$prefix.'release-public-key.txt']); break;
            case 'wrong-root': $files['other-plugin/payload.php'] = 'fixture'; break;
            case 'absolute-path': $files['/xiaoman-inquiry-center/payload.php'] = 'fixture'; break;
            case 'path-traversal': $files[$prefix.'../payload.php'] = 'fixture'; break;
            case 'backslash': $files[$prefix.'nested\\payload.php'] = 'fixture'; break;
            case 'empty-segment': $files[$prefix.'nested//payload.php'] = 'fixture'; break;
            case 'repeated-trailing-slash': $files[$prefix.'nested//'] = ''; break;
            case 'windows-drive': $files[$prefix.'C:/payload.php'] = 'fixture'; break;
            case 'windows-reserved': $files[$prefix.'CON.txt'] = 'fixture'; break;
            case 'trailing-dot': $files[$prefix.'payload.php.'] = 'fixture'; break;
            case 'duplicate-case': $files[$prefix.'XIAOMAN-INQUIRY-CENTER.PHP'] = $main; break;
            case 'duplicate-exact': $files[$prefix.'dupa.txt'] = 'a'; $files[$prefix.'dupb.txt'] = 'b'; break;
            case 'symlink': case 'directory-link': $files[$prefix.'linked'] = '../../synthetic.seed'; break;
            case 'file-directory-collision': $files[$prefix.'collision'] = 'file'; $files[$prefix.'collision/child.php'] = 'fixture'; break;
            case 'case-file-directory-collision': $files[$prefix.'COLLISION'] = 'file'; $files[$prefix.'collision/child.php'] = 'fixture'; break;
            case 'directory-file-attributes': $files[$prefix.'directory/'] = ''; break;
            case 'file-directory-attributes': $files[$prefix.'directory'] = ''; break;
            case 'private-file': $files[$prefix.'.env.production'] = 'fixture'; break;
            case 'oversized-member': $files[$prefix.'large.txt'] = str_repeat('x',10*1024*1024+1); break;
            case 'wrong-source-digest': $manifest['provenance'] = array('source_sha256'=>str_repeat('0',64)); break;
            case 'wrong-SHA256SUMS': $sums = true; break;
        }
        $zip = new ZipArchive(); release_assert($case.' create ZIP',$zip->open($zipPath,ZipArchive::CREATE|ZipArchive::EXCL)===true);
        foreach ($files as $name=>$data) release_assert($case.' add fixture member',$zip->addFromString($name,$data));
        if ($case==='symlink' || $case==='directory-link') release_assert($case.' set symlink attribute',$zip->setExternalAttributesName($prefix.'linked',ZipArchive::OPSYS_UNIX,0120777<<16));
        if ($case==='directory-file-attributes') release_assert($case.' set file attribute',$zip->setExternalAttributesName($prefix.'directory/',ZipArchive::OPSYS_UNIX,0100644<<16));
        if ($case==='file-directory-attributes') release_assert($case.' set directory attribute',$zip->setExternalAttributesName($prefix.'directory',ZipArchive::OPSYS_UNIX,0040755<<16));
        release_assert($case.' close ZIP',$zip->close()); unset($files);
        if ($case==='duplicate-exact') file_put_contents($zipPath,str_replace('dupb.txt','dupa.txt',file_get_contents($zipPath)));
        $manifest['sha256'] = hash_file('sha256',$zipPath);
        if ($case==='hash-mismatch') $manifest['sha256'] = str_repeat('0',64);
        $manifestPath = $dir.'/manifest.json'; release_envelope($manifestPath,$manifest,$secret);
        if ($case==='bad-signature') { $envelope=json_decode(file_get_contents($manifestPath),true);$envelope['signature']=base64_encode(str_repeat("\0",64));file_put_contents($manifestPath,json_encode($envelope)); }
        if ($case==='tampered-manifest') { $envelope=json_decode(file_get_contents($manifestPath),true);$envelope['manifest']=base64_encode(str_replace('beta.9','beta.8',base64_decode($envelope['manifest'])));file_put_contents($manifestPath,json_encode($envelope)); }
        if ($case==='wrong-filename') { rename($zipPath,$dir.'/renamed.zip');$zipPath=$dir.'/renamed.zip'; }
        if ($case==='oversized-manifest') file_put_contents($manifestPath,str_repeat('x',24001));
        if ($case==='missing-manifest') unlink($manifestPath);
        $publicPath = $dir.'/trusted-public-key.txt'; file_put_contents($publicPath,base64_encode($trust)."\n");
        if ($sums) file_put_contents($dir.'/SHA256SUMS','wrong checksum');
        $failed = false; $verified = null;
        try { $verified=xi_release_verify($zipPath,$manifestPath,$publicPath,$sums?$dir.'/SHA256SUMS':null); }
        catch (Throwable $e) { $failed = true; }
        release_assert($case.' expected verification result',$case==='normal'?!$failed&&$verified['verified']===true:$failed);
    }
    // No execution of plugin code is needed to identify its version.
    release_assert('commented spoof define ignored',xi_release_version($main."// define('XI_VERSION','9.9.9');\n")==='2.0.0-beta.9');
} finally {
    sodium_memzero($secret); release_cleanup($fixture,$fixture);
}
release_assert('temporary fixture completely removed',!file_exists($fixture));
echo json_encode(array('php'=>PHP_VERSION,'passed'=>$checks,'suite'=>'offline signed artifact verification','real_seed_read'=>false,'real_plugin_signed'=>false,'temporary_fixture_cleaned'=>!file_exists($fixture)),JSON_UNESCAPED_SLASHES)."\n";

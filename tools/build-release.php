<?php
// Local only: the existing private signing seed must remain outside the repository.
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__.'/verify-release.php';

function xi_build_provenance($repo, $requireClean) {
    $run = static function ($args) use ($repo) {
        $pipes = array();
        $process = @proc_open(array_merge(array('git', '-C', $repo), $args), array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')), $pipes);
        if (!is_resource($process)) return null;
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return proc_close($process) === 0 ? trim($out) : null;
    };
    $commit = $run(array('rev-parse', '--verify', 'HEAD'));
    $tree = $run(array('rev-parse', '--verify', 'HEAD^{tree}'));
    $tracked = $run(array('status', '--porcelain', '--untracked-files=no'));
    $plugin = $run(array('status', '--porcelain', '--untracked-files=all', '--', 'xiaoman-inquiry-center'));
    // No exclude patterns: ignored files would still be shipped and must count as untracked.
    $untracked = $run(array('ls-files', '--others', '--', 'xiaoman-inquiry-center'));
    $available = is_string($commit) && preg_match('/^[a-f0-9]{40,64}$/D', $commit) && is_string($tree) && preg_match('/^[a-f0-9]{40,64}$/D', $tree) && $tracked !== null && $plugin !== null && $untracked !== null;
    if ($requireClean && (!$available || $tracked !== '' || $plugin !== '' || $untracked !== '')) throw new RuntimeException('--require-clean requires a clean tracked tree and no untracked plugin files in a Git checkout.');
    return array('commit'=>$available ? $commit : null, 'tracked_tree'=>$available ? $tree : null, 'tracked_tree_clean'=>$available ? $tracked === '' : null, 'plugin_tree_clean'=>$available ? $plugin === '' && $untracked === '' : null);
}

function xi_build_release($args) {
    xi_release_extensions();
    $requireClean = false; $positionals = array();
    foreach ($args as $arg) {
        if ($arg === '--require-clean') { $requireClean = true; continue; }
        if (strpos($arg, '--') === 0) throw new RuntimeException('Unknown option: '.$arg);
        $positionals[] = $arg;
    }
    if (count($positionals) < 1 || count($positionals) > 2) throw new RuntimeException('Usage: php tools/build-release.php /private/path/release.seed [output directory] [--require-clean]');
    $repo = realpath(dirname(__DIR__)); $root = $repo.'/xiaoman-inquiry-center';
    if (is_link($root) || !is_dir($root)) throw new RuntimeException('Plugin root must be an ordinary directory.');
    $seedPath = $positionals[0]; $seedReal = realpath($seedPath);
    if (!$seedReal || !is_file($seedReal)) throw new RuntimeException('An existing signing seed is required; this tool does not initialize signing identities.');
    if (is_link($seedPath) || xi_release_within($seedReal, $repo)) throw new RuntimeException('Signing seed must be a regular file outside the repository.');
    $output = xi_release_resolve_output($positionals[1] ?? $repo.'/dist');
    if (xi_release_within($output, $root)) throw new RuntimeException('Output directory must be outside the plugin source tree.');
    if (xi_release_within($seedReal, $output)) throw new RuntimeException('Output directory must not contain the signing seed.');
    $version = xi_release_version(xi_release_read($root.'/xiaoman-inquiry-center.php'));
    $publicPath = $root.'/release-public-key.txt';
    $entries = array(); $hashes = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $file) {
        $path = $file->getPathname(); $relative = substr(str_replace('\\','/',$path), strlen(str_replace('\\','/',$root))+1);
        xi_release_member('xiaoman-inquiry-center/'.$relative);
        if ($file->isLink()) throw new RuntimeException('Symbolic links are not allowed in the plugin tree: '.$relative);
        if ($file->isDir()) continue;
        if (!$file->isFile() || realpath($path) === false || !xi_release_within(realpath($path), $root)) throw new RuntimeException('Unsafe source file: '.$relative);
        if ($file->getSize() > 10*1024*1024) throw new RuntimeException('Source file exceeds release size limit: '.$relative);
        $entries[$relative] = $path; $hashes[$relative] = hash_file('sha256', $path);
    }
    ksort($entries, SORT_STRING); ksort($hashes, SORT_STRING);
    $provenance = xi_build_provenance($repo, $requireClean);
    $provenance['source_sha256'] = hash('sha256', json_encode($hashes, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $asset = 'xiaoman-inquiry-center-'.$version.'.zip';
    foreach (array($asset, 'manifest.json', 'SHA256SUMS') as $name) if (file_exists($output.'/'.$name) || is_link($output.'/'.$name)) throw new RuntimeException('Release artifact already exists; choose a new empty output directory: '.$name);
    $seed = xi_release_read($seedReal); $secret = null; $pair = null; $stage = null; $published = array();
    try {
        if (strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) throw new RuntimeException('Invalid signing seed length.');
        $pair = sodium_crypto_sign_seed_keypair($seed); $public = sodium_crypto_sign_publickey($pair);
        if (!hash_equals(xi_release_public_key($publicPath), $public)) throw new RuntimeException('Existing release public key does not match the signing identity; no key is overwritten.');
        $secret = sodium_crypto_sign_secretkey($pair); sodium_memzero($pair);
        if (!is_dir($output) && !mkdir($output, 0700, true)) throw new RuntimeException('Could not create output directory.');
        $stage = $output.'/.xi-release-'.bin2hex(random_bytes(12));
        if (!mkdir($stage, 0700)) throw new RuntimeException('Could not create release staging directory.');
        $zipPath = $stage.'/'.$asset; $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE|ZipArchive::EXCL) !== true) throw new RuntimeException('Could not create release ZIP.');
        try {
            foreach ($entries as $relative=>$path) {
                $entry = 'xiaoman-inquiry-center/'.$relative;
                if (!$zip->addFile($path, $entry) || !$zip->setMtimeName($entry, 946684800)) throw new RuntimeException('Could not add source file to ZIP: '.$relative);
            }
        } finally { if (!$zip->close()) throw new RuntimeException('Could not finish release ZIP.'); }
        // ZipArchive reads source files on close; reject edits during the build.
        foreach ($entries as $relative=>$path) if (is_link($path) || !hash_equals($hashes[$relative], (string)@hash_file('sha256', $path))) throw new RuntimeException('Plugin source changed during build: '.$relative);
        $sha256 = hash_file('sha256', $zipPath);
        $manifest = json_encode(array('slug'=>'xiaoman-inquiry-center','version'=>$version,'package'=>xi_release_package($version),'sha256'=>$sha256,'requires'=>'6.9','requires_php'=>'8.2','provenance'=>$provenance), JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $envelope = json_encode(array('manifest'=>base64_encode($manifest),'signature'=>base64_encode(sodium_crypto_sign_detached($manifest, $secret))), JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($stage.'/manifest.json', $envelope) !== strlen($envelope)) throw new RuntimeException('Could not write signed manifest.');
        $sums = $sha256.'  '.$asset."\n";
        if (file_put_contents($stage.'/SHA256SUMS', $sums) !== strlen($sums)) throw new RuntimeException('Could not write SHA256SUMS.');
        xi_release_verify($zipPath, $stage.'/manifest.json', $publicPath, $stage.'/SHA256SUMS');
        // Exclusive creation prevents overwriting even if another builder wins a race.
        foreach (array($asset, 'manifest.json', 'SHA256SUMS') as $name) {
            $target = $output.'/'.$name; $handle = @fopen($target, 'x+b');
            if ($handle === false) throw new RuntimeException('Release artifact already exists or cannot be created: '.$name);
            $published[] = $target; $input = fopen($stage.'/'.$name, 'rb');
            try { if ($input === false || stream_copy_to_stream($input, $handle) !== filesize($stage.'/'.$name)) throw new RuntimeException('Could not publish complete artifact: '.$name); }
            finally { if (is_resource($input)) fclose($input); fclose($handle); }
        }
        $published = array();
        return array('version'=>$version,'zip'=>$output.'/'.$asset,'sha256'=>$sha256,'manifest'=>$output.'/manifest.json','files'=>count($entries),'provenance'=>$provenance,'verified'=>true);
    } finally {
        foreach ($published as $path) @unlink($path);
        if ($stage !== null) { foreach (array($asset, 'manifest.json', 'SHA256SUMS') as $name) if (is_file($stage.'/'.$name)) @unlink($stage.'/'.$name); @rmdir($stage); }
        if (is_string($secret)) sodium_memzero($secret);
        if (is_string($pair)) sodium_memzero($pair);
        sodium_memzero($seed);
    }
}

try { echo json_encode(xi_build_release(array_slice($argv, 1)), JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"; }
catch (Throwable $e) { fwrite(STDERR, 'Build failed: '.$e->getMessage()."\n"); exit(1); }

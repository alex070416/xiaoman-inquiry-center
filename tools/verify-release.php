<?php
// Offline verification before manual installation. Never trust a key taken from the ZIP itself.
function xi_release_extensions() {
    if (!extension_loaded('sodium') || !extension_loaded('zip')) throw new RuntimeException('Sodium and ZIP extensions are required.');
}
function xi_release_read($path) {
    $bytes = @file_get_contents($path);
    if (!is_string($bytes)) throw new RuntimeException('Could not read required file: '.$path);
    return $bytes;
}
function xi_release_within($path, $root) {
    $path = rtrim(str_replace('\\','/',$path), '/'); $root = rtrim(str_replace('\\','/',$root), '/');
    if (PHP_OS_FAMILY === 'Windows') { $path = strtolower($path); $root = strtolower($root); }
    return $path === $root || strpos($path, $root.'/') === 0;
}
function xi_release_resolve_output($path) {
    if ($path === '' || strpos($path, "\0") !== false || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) throw new RuntimeException('Unsafe output path.');
    $cursor = $path; $tail = array();
    while (!file_exists($cursor) && !is_link($cursor)) {
        $part = basename($cursor);
        if ($part === '.' || $part === '..' || $part === '' || preg_match('/[<>:"|?*\x00-\x1f]/', $part)) throw new RuntimeException('Unsafe output path.');
        array_unshift($tail, $part); $next = dirname($cursor);
        if ($next === $cursor) throw new RuntimeException('Cannot resolve output path.');
        $cursor = $next;
    }
    $base = realpath($cursor);
    if (!$base || !is_dir($base)) throw new RuntimeException('Output ancestor must be a directory.');
    return rtrim(str_replace('\\','/',$base), '/').($tail ? '/'.implode('/', $tail) : '');
}
function xi_release_version($source) {
    preg_match_all('/^[ \t]*\*[ \t]*Version:[ \t]*([^\r\n]+)/m', substr($source, 0, 8192), $headers);
    $version = count($headers[1]) === 1 ? trim($headers[1][0]) : '';
    if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.]+)?$/D', $version)) throw new RuntimeException('One valid plugin Version header is required.');
    $tokens = array();
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], array(T_WHITESPACE,T_COMMENT,T_DOC_COMMENT), true)) continue;
        $tokens[] = $token;
    }
    $versions = array();
    for ($i=0; $i<count($tokens)-5; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || strtolower($tokens[$i][1]) !== 'define' || $tokens[$i+1] !== '(') continue;
        $name = $tokens[$i+2]; $value = $tokens[$i+4];
        if (!is_array($name) || $name[0] !== T_CONSTANT_ENCAPSED_STRING || !preg_match('/^([\'"])XI_VERSION\1$/D', $name[1])) continue;
        if ($tokens[$i+3] !== ',' || !is_array($value) || $value[0] !== T_CONSTANT_ENCAPSED_STRING || !preg_match('/^([\'"])([0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.]+)?)\1$/D', $value[1], $match) || $tokens[$i+5] !== ')') throw new RuntimeException('XI_VERSION must be one literal version.');
        $versions[] = $match[2];
    }
    if (count($versions) !== 1 || $versions[0] !== $version) throw new RuntimeException('Plugin Version header and XI_VERSION must match exactly.');
    return $version;
}
function xi_release_public_key($path) {
    if (is_link($path) || !is_file($path)) throw new RuntimeException('Existing release public key must be a regular file.');
    $public = base64_decode(trim(xi_release_read($path)), true);
    if (!is_string($public) || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) throw new RuntimeException('Existing release public key is invalid.');
    return $public;
}
function xi_release_package($version) {
    return 'https://github.com/alex070416/xiaoman-inquiry-center/releases/download/v'.$version.'/xiaoman-inquiry-center-'.$version.'.zip';
}
function xi_release_member($name) {
    if (!is_string($name) || strpos($name, 'xiaoman-inquiry-center/') !== 0 || strpos($name, '\\') !== false || strpos($name, '//') !== false || preg_match('/[\x00-\x1f\x7f:<>"|?*]/', $name)) throw new RuntimeException('Unsafe ZIP member path.');
    $parts = explode('/', rtrim($name, '/'));
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..' || preg_match('/[. ]$/D', $part) || preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $part)) throw new RuntimeException('Unsafe ZIP member path: '.$name);
        if (preg_match('/^(?:\.git|\.svn|\.hg|\.env.*|wp-config\.php|auth\.json|credentials(?:\.json)?|id_(?:rsa|dsa|ecdsa|ed25519)|.*\.(?:seed|key|pem|p12|pfx|sqlite3?|db|log|sql|dump|bak))$/iD', $part)) throw new RuntimeException('Unexpected private file in plugin tree: '.$name);
    }
    return $name;
}
function xi_release_verify($zipPath, $manifestPath, $publicPath, $sumsPath = null) {
    xi_release_extensions(); $public = xi_release_public_key($publicPath);
    if (!is_file($manifestPath) || is_link($manifestPath) || filesize($manifestPath) > 24000) throw new RuntimeException('Signed manifest must be a regular file within the size limit.');
    if ($sumsPath !== null && (!is_file($sumsPath) || is_link($sumsPath))) throw new RuntimeException('SHA256SUMS must be a regular file.');
    if (!is_file($zipPath) || is_link($zipPath) || filesize($zipPath) > 20*1024*1024) throw new RuntimeException('Release ZIP must be a regular file within the size limit.');
    $envelope = json_decode(xi_release_read($manifestPath), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($envelope) || !is_string($envelope['manifest'] ?? null) || !is_string($envelope['signature'] ?? null)) throw new RuntimeException('Invalid signed manifest envelope.');
    $bytes = base64_decode($envelope['manifest'], true); $signature = base64_decode($envelope['signature'], true);
    if (!is_string($bytes) || !is_string($signature) || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || !sodium_crypto_sign_verify_detached($signature, $bytes, $public)) throw new RuntimeException('Manifest signature does not match the trusted public key.');
    $manifest = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR); $version = $manifest['version'] ?? null;
    if (!is_array($manifest) || ($manifest['slug'] ?? null) !== 'xiaoman-inquiry-center' || !is_string($version) || !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.]+)?$/D', $version) || ($manifest['package'] ?? null) !== xi_release_package($version) || !is_string($manifest['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $manifest['sha256'])) throw new RuntimeException('Manifest slug, version, package URL or hash is invalid.');
    $asset = 'xiaoman-inquiry-center-'.$version.'.zip';
    if (basename($zipPath) !== $asset || !hash_equals($manifest['sha256'], hash_file('sha256', $zipPath))) throw new RuntimeException('ZIP filename or SHA256 does not match the signed manifest.');
    if ($sumsPath !== null && xi_release_read($sumsPath) !== $manifest['sha256'].'  '.$asset."\n") throw new RuntimeException('SHA256SUMS does not match the release.');
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('Could not open release ZIP.');
    $seen = array(); $regular = array(); $files = array(); $total = 0; $main = null; $bundledPublic = null;
    try {
        if ($zip->numFiles > 10000) throw new RuntimeException('Too many ZIP members.');
        for ($i=0; $i<$zip->numFiles; $i++) {
            $name = xi_release_member($zip->getNameIndex($i)); $collision = strtolower(rtrim($name, '/'));
            if (isset($seen[$collision])) throw new RuntimeException('Duplicate ZIP member: '.$name);
            $seen[$collision] = true; $stat = $zip->statIndex($i);
            if (!is_array($stat) || ($stat['size'] ?? 0) > 10*1024*1024 || ($total += ($stat['size'] ?? 0)) > 100*1024*1024 || !empty($stat['encryption_method'])) throw new RuntimeException('ZIP member exceeds safe limits or is encrypted.');
            $opsys = 0; $attributes = 0;
            if (!$zip->getExternalAttributesIndex($i, $opsys, $attributes)) throw new RuntimeException('Cannot inspect ZIP member attributes.');
            $kind = ($attributes >> 16) & 0170000;
            if ($kind !== 0 && $kind !== 0100000 && $kind !== 0040000) throw new RuntimeException('ZIP links and special files are forbidden.');
            if (substr($name, -1) === '/') {
                if ($kind !== 0 && $kind !== 0040000) throw new RuntimeException('ZIP directory has file attributes.');
                continue;
            }
            if ($kind === 0040000) throw new RuntimeException('ZIP file has directory attributes.');
            $regular[$collision] = true;
            $data = $zip->getFromIndex($i);
            if (!is_string($data) || strlen($data) !== $stat['size']) throw new RuntimeException('Could not read complete ZIP member.');
            $files[substr($name, strlen('xiaoman-inquiry-center/'))] = hash('sha256', $data);
            if ($name === 'xiaoman-inquiry-center/xiaoman-inquiry-center.php') $main = $data;
            if ($name === 'xiaoman-inquiry-center/release-public-key.txt') $bundledPublic = base64_decode(trim($data), true);
        }
    } finally { $zip->close(); }
    foreach (array_keys($seen) as $name) {
        $parent = dirname($name);
        while ($parent !== '.' && $parent !== 'xiaoman-inquiry-center') {
            if (isset($regular[$parent])) throw new RuntimeException('ZIP file/directory collision.');
            $parent = dirname($parent);
        }
    }
    if (!is_string($main) || xi_release_version($main) !== $version) throw new RuntimeException('ZIP plugin version does not match the signed manifest.');
    if (!is_string($bundledPublic) || !hash_equals($public, $bundledPublic)) throw new RuntimeException('ZIP release public key differs from the trusted public key.');
    ksort($files, SORT_STRING); $sourceHash = hash('sha256', json_encode($files, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    if (isset($manifest['provenance']) && (!is_array($manifest['provenance']) || !is_string($manifest['provenance']['source_sha256'] ?? null) || !hash_equals($manifest['provenance']['source_sha256'], $sourceHash))) throw new RuntimeException('ZIP source digest does not match signed provenance.');
    return array('verified'=>true,'version'=>$version,'zip'=>$zipPath,'sha256'=>$manifest['sha256'],'files'=>count($files),'provenance'=>$manifest['provenance'] ?? null);
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        if (count($argv) < 4 || count($argv) > 5) throw new RuntimeException('Usage: php tools/verify-release.php ZIP MANIFEST TRUSTED_PUBLIC_KEY [SHA256SUMS]');
        echo json_encode(xi_release_verify($argv[1], $argv[2], $argv[3], $argv[4] ?? null), JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    } catch (Throwable $e) { fwrite(STDERR, 'Verification failed: '.$e->getMessage()."\n"); exit(1); }
}

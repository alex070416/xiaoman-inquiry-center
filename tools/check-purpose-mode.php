<?php
// Offline preflight: pipe the raw conversion option JSON on stdin. Prints no option values.
// Usage: php tools/check-purpose-mode.php purpose|service < sanitized-option.json
$expected=$argv[1]??'';
if(!in_array($expected,array('purpose','service'),true)){fwrite(STDERR,"Expected mode must be purpose or service.\n");exit(2);}
$raw=file_get_contents('php://stdin');$decoded=json_decode($raw);
if(!is_object($decoded)||json_last_error()!==JSON_ERROR_NONE){fwrite(STDERR,"Raw option must be a JSON object.\n");exit(2);}
$option=(array)$decoded;
$configured=array_key_exists('purpose_consent_enabled',$option);
$value=$configured?$option['purpose_consent_enabled']:false;
$mode=$configured&&!is_bool($value)?'invalid':($value===true?'purpose':'service');
$ok=$mode===$expected&&($expected!=='purpose'||$configured);
echo json_encode(array('pass'=>$ok,'mode_configured'=>$configured,'mode'=>$mode,'expected_mode'=>$expected),JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($ok?0:2);

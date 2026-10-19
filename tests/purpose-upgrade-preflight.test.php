<?php
// Feed minimal raw options into the shipped offline preflight; never load site configuration.
$cases=array(
 array('HJ raw mode absent blocks purpose update','purpose','{"enabled":true,"site_host":"hj-fixture.invalid"}',2),
 array('HT raw mode absent blocks purpose update','purpose','{"enabled":true,"site_host":"ht-fixture.invalid"}',2),
 array('legacy service absent key retains contract','service','{}',0),
 array('strict boolean true enables purpose preflight','purpose','{"purpose_consent_enabled":true}',0),
 array('strict false cannot approve purpose update','purpose','{"purpose_consent_enabled":false}',2),
 array('explicit false approves service preflight','service','{"purpose_consent_enabled":false}',0),
);
foreach(array('null','0','1','"false"','"true"','[]','{}') as $v)$cases[]=array('invalid raw type '.$v,'purpose','{"purpose_consent_enabled":'.$v.'}',2);
$cases[]=array('JSON array is not a raw option object','service','[]',2);
foreach($cases as [$name,$expected,$input,$code]){
 $process=proc_open(array(PHP_BINARY,'-n','tools/check-purpose-mode.php',$expected),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
 if(!is_resource($process))throw new RuntimeException('Cannot start offline preflight');
 fwrite($pipes[0],$input);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$actual=proc_close($process);
 if($actual!==$code)throw new RuntimeException($name.' unexpected exit '.$actual.' '.$out.$err);
 if(str_contains($out,'site_host')||str_contains($out,'fixture.invalid'))throw new RuntimeException('Preflight leaked input configuration');
}
echo json_encode(array('suite'=>'purpose-upgrade-raw-preflight','passed'=>count($cases))).PHP_EOL;

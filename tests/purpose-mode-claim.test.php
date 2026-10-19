<?php
// Actual integrated claim implementation, in-memory SQLite only; no WordPress or network.
define('XI_FIXTURE_BOOTSTRAP_ONLY',true);require __DIR__.'/owner-protocol.test.php';
$cfg['purpose_consent_enabled']=true;$cfg['gtm_id']='GTM-FIXTURE';
$before=$wpdb->db->query('SELECT * FROM fixture_xi_conversion_receipts')->fetchAll(PDO::FETCH_ASSOC);
foreach(array(false,true) as $schema){
 XI_Conversions::$owner_schema=$schema;
 foreach(array('', 'legacy-v1') as $protocol){
  $r=XI_Conversions::claim($token,$number,'ga4',$consent,$protocol);
  check('purpose mode rejects old protocol with schema '.(int)$schema.' protocol '.$protocol,$r instanceof WP_Error&&$r->code==='protocol');
 }
}
XI_Conversions::$owner_schema=false;
$r=XI_Conversions::claim($token,$number,'ga4',$consent,XI_Conversions::PROTOCOL);
check('purpose mode rejects owned request without owner schema',$r instanceof WP_Error&&$r->code==='protocol');
XI_Conversions::$owner_schema=true;$cfg['gtm_id']='';
$r=XI_Conversions::claim($token,$number,'ga4',$consent,XI_Conversions::PROTOCOL);
check('purpose mode rejects missing container',$r instanceof WP_Error&&$r->code==='protocol');
$cfg['gtm_id']='GTM-FIXTURE';
foreach(array(null,0,1,'false','true',array(),array(true)) as $value){
 $cfg['purpose_consent_enabled']=$value;
 check('invalid raw mode type '.json_encode($value),XI_Conversions::purpose_mode_status()===array('mode_configured'=>true,'mode'=>'invalid'));
 $r=XI_Conversions::claim($token,$number,'ga4',$consent,XI_Conversions::PROTOCOL);
 check('invalid mode cannot consume pending '.json_encode($value),$r instanceof WP_Error&&$r->code==='protocol');
}
check('rejected purpose claims preserve full receipt/expiry/owner rows',$before===$wpdb->db->query('SELECT * FROM fixture_xi_conversion_receipts')->fetchAll(PDO::FETCH_ASSOC));
$cfg['purpose_consent_enabled']=true;
$r=XI_Conversions::claim($token,$number,'ga4',$consent,XI_Conversions::PROTOCOL);
check('valid purpose mode still obtains an atomic owned claim',$r['dispatch']===true&&preg_match('/^[a-f0-9]{32}$/D',$r['claim_owner'])===1);
check('duplicate purpose claim does not resend',XI_Conversions::claim($token,$number,'ga4',$consent,XI_Conversions::PROTOCOL)===array('dispatch'=>false,'reason'=>'already_claimed'));
echo json_encode(array('suite'=>'purpose-mode-claim','passed'=>count($checks),'checks'=>$checks)).PHP_EOL;

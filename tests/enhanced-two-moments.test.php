<?php
// Isolated actual PHP claim path with in-memory SQLite; no WordPress/network.
define('XI_FIXTURE_BOOTSTRAP_ONLY',true);require __DIR__.'/owner-protocol.test.php';
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL)!==false;}
$GLOBALS['fixture_values']=array('F5'=>'xi-independent-review@example.com','F6'=>'+12025550123');
check('online_email_trim_lower_and_gmail_dots',XI_Conversions::email_hash(' Jane.Doe@GMAIL.COM ' )===hash('sha256','janedoe@gmail.com'));
check('online_googlemail_dots_keep_domain',XI_Conversions::email_hash('A.B@Googlemail.com')===hash('sha256','ab@googlemail.com'));
check('non_gmail_dots_and_alias_preserved',XI_Conversions::email_hash('User.Name+NYC@Example.com')===hash('sha256','user.name+nyc@example.com'));
check('invalid_email_omitted',XI_Conversions::email_hash('bad-email')==='');
check('formatted_international_phone_normalized',XI_Conversions::phone_hash('+1 (202) 555-0123')===hash('sha256','+12025550123'));
check('national_phone_not_guessed',XI_Conversions::phone_hash('2025550123')==='');
check('overlength_phone_omitted',XI_Conversions::phone_hash('+1234567890123456')==='');
$cfg['enhanced']=true;
$results=array();
foreach(array('denied','granted') as $submitted)foreach(array('denied','granted') as $sending){
 $wpdb->query($wpdb->prepare("UPDATE fixture_xi_conversion_receipts SET ads_state='pending',ads_claim_owner=NULL,consent_at_submit=%s WHERE id=1",json_encode(array('ad_user_data'=>$submitted))));
 $decision=$consent;$decision['ad_user_data']=$sending;
 $result=XI_Conversions::claim($token,$number,'ads',$decision,XI_Conversions::PROTOCOL);
 $expect=$submitted==='granted'&&$sending==='granted';
 check('submit_'.$submitted.'_send_'.$sending.'_hash_gate',isset($result['user_data'])===$expect);
 if($expect)check('both_consents_hashes_are_exact_no_raw_contacts',$result['user_data']===array('sha256_email_address'=>hash('sha256','xi-independent-review@example.com'),'sha256_phone_number'=>hash('sha256','+12025550123')));
}
$cfg['enhanced']=false;$wpdb->query("UPDATE fixture_xi_conversion_receipts SET ads_state='pending',ads_claim_owner=NULL WHERE id=1");
$r=XI_Conversions::claim($token,$number,'ads',$consent,XI_Conversions::PROTOCOL);check('enhanced_feature_disabled_omits_hashes',!isset($r['user_data']));
$cfg['enhanced']=true;$wpdb->query("UPDATE fixture_xi_conversion_receipts SET ga4_state='pending',ga4_claim_owner=NULL WHERE id=1");
$r=XI_Conversions::claim($token,$number,'ga4',$consent,XI_Conversions::PROTOCOL);check('ga4_claim_never_contains_contacts_or_user_data',!isset($r['user_data'])&&!isset($r['email'])&&!isset($r['phone']));
$wpdb->query("UPDATE fixture_xi_conversion_receipts SET ads_state='pending',ads_claim_owner=NULL WHERE id=1");$decision=$consent;$decision['ad_storage']='denied';
$r=XI_Conversions::claim($token,$number,'ads',$decision,XI_Conversions::PROTOCOL);check('ad_storage_denied_rejected_before_atomic_claim',$r instanceof WP_Error&&$r->code==='consent'&&$wpdb->get_var("SELECT ads_state FROM fixture_xi_conversion_receipts WHERE id=1")==='pending');
echo json_encode(array('suite'=>'enhanced-two-moments-actual-php-claim','passed'=>count($checks),'checks'=>$checks,'scope'=>'isolated SQLite fixture; no network/production data'),JSON_PRETTY_PRINT).PHP_EOL;

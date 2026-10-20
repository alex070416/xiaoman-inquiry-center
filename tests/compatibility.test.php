<?php
// Sanitized legacy-interface fixtures. This is not a five-site production import.
if (PHP_SAPI!=='cli') exit(1);
$site=$argv[1]??'fangwei';
$profiles=array('fangwei'=>array('FW','fangwei-inquiry-center','fangwei-xiaoman-sync.php','FW-N','fwnxm_'),'huajing'=>array('NI','huajing-native-recovery','native-inquiry.php','HJ-N','ninxm_'),'anxing'=>array('NI','anxing-native-inquiry','native-inquiry.php','AX-N','ninxm_'),'rongfeng'=>array('NI','rongfeng-inquiry-center','rongfeng-inquiry-center.php','RF-N','ninxm_'),'tongqu'=>array('NI','tongqu-native-inquiry','native-inquiry.php','TQ-N','ninxm_'));
if(!isset($profiles[$site]))throw new RuntimeException('Unknown fixture');
list($prefix,$folder,$entry,$number,$queue)=$profiles[$site];
$fixture_id=(string)(array_search($site,array_keys($profiles),true)+1);
$post_type=$site==='rongfeng'?'product':'products';$taxonomy=$site==='rongfeng'?'machine-types':'product-category';
$credentials_mode=$argv[2]??'constants';
if(!in_array($credentials_mode,array('constants','stored-credentials','missing-decrypt'),true))throw new RuntimeException('Unknown credentials fixture');
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);
if($credentials_mode==='constants'){define('XIAOMAN_CLIENT_ID','fixture-client');define('XIAOMAN_CLIENT_SECRET','fixture-secret');}
if($prefix==='NI')define('NI_PROFILE',array('taxonomies'=>array($taxonomy)));
class WP_Error {function __construct(public $code,public $message){}}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_parse_args($v,$defaults){return array_merge($defaults,$v);}
function wp_salt($v){if(!empty($GLOBALS['crypto_failure']))throw new RuntimeException('Synthetic encryption failure');return $GLOBALS['fixture_salt']??'public-test-fixture-salt';}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
$options=array('active_plugins'=>array($folder.'/'.$entry,'native-inquiry-required/required.php'),'xi_required_v1'=>array('preserve'=>'previous-required'),'ninxm_capture_cursor_v1'=>190,'ninxm_job_v1_190'=>array('state'=>'success','attempts'=>1));$writes=array();
function get_option($key,$default=false){global $options;return $options[$key]??$default;}
function get_site_option($key,$default=false){return $default;}
function update_option($key,$value,$autoload=null){global $options,$writes;$writes[]=$key;$options[$key]=$value;return true;}
function maybe_unserialize($value){return is_string($value)?unserialize($value):$value;}
function get_terms($args){if(!empty($GLOBALS['terms_failure']))return new WP_Error('db','Synthetic term failure');return array((object)array('slug'=>'electric-forklift','name'=>'Electric Forklift')) ;}
function wp_remote_request(){throw new RuntimeException('Migration must not make HTTP requests');}
class XI_Fixture_DB {
 public $postmeta='wp_postmeta';public $posts='wp_posts';public $last_error='';
 function get_results($sql,$format){if($this->last_error)return null;return array(array('meta_value'=>serialize(array(array('name'=>'form','settings'=>array('fields'=>array(array('name'=>'Equipment','options'=>"Electric Forklift\nself loading stacker\nOther\nCustom Option"))))))));}
}
$wpdb=new XI_Fixture_DB();
$equipment=$prefix==='FW'?'':'static function equipment($term){return $term->name;}';
$role=$prefix==='FW'?'':'const ROLE="'.$site.'_inquiry_viewer";';
eval('class '.$prefix.'_Native_Inquiry_Center { const META="'.strtolower($prefix).'_native_record";const CAP="bricks_form_submission_access";const PAGE="'.$site.'-inquiry-center";'.$role.'static function number($id){return "'.$number.'".$id;}static function text($v,$n){return substr($v,0,$n);}'.$equipment.'}');
eval('class '.$prefix.'_Native_Xiaoman_Sync {const JOB="'.$queue.'send_v1";const SWEEP="'.$queue.'recover_v1";const GROUP="'.$site.'-native-xiaoman";const CURSOR="'.$queue.'capture_cursor_v1";static function key($id){return "'.$queue.'job_v1_".$id;}}');
$provider_constants=$site==='rongfeng'?'':'const ORIGIN="'.$fixture_id.'2345";const ORIGIN_NAME="Fixture '.$site.'";const PRODUCT_FIELD="'.$fixture_id.'3456";const CUSTOMER_LEVEL_FIELD="'.$fixture_id.'4567";const CUSTOMER_LEVEL="无";';
$provider_mapping=$site==='rongfeng'?'$map=RF_Inquiry_Config::get()["products"];$product=$map[strtolower(trim($raw))]??"";return array("lead_name"=>$product!==""?$product:"其他","product_name"=>$product);':'if($raw==="self loading stacker")return array("lead_name"=>"自提车","product_name"=>"堆高车");if($raw==="Electric Forklift")return array("lead_name"=>"电动叉车","product_name"=>"电动叉车");return array("lead_name"=>"其他","product_name"=>"其他");';
eval('class '.$prefix.'_Xiaoman_Queue_V1 {const API="https://api-sandbox.xiaoman.cn";'.$provider_constants.'static function product_mapping($raw){'.$provider_mapping.'}}');
// A stray RF configuration class must never alter another site's identity.
class RF_Inquiry_Config {static function get(){global $fixture_id,$site;return array('origin_id'=>$fixture_id.'2345','origin_name'=>'Fixture '.$site,'product_field'=>$fixture_id.'3456','level_field'=>$fixture_id.'4567','level_value'=>'无','products'=>array('legacy-only'=>'保留旧映射','electric forklift'=>'电动叉车','self loading stacker'=>'堆高车'),'number_prefix'=>'WRONG-N','record_meta'=>'wrong_record','queue_prefix'=>'wrong_','queue_cursor'=>'wrong_cursor','post_type'=>'wrong_type','secrets'=>array('secret'=>'must-never-be-returned'));}}
class NIFR_Conditional_Required {static function options(){global $post_type;if(array_key_exists('required_override',$GLOBALS))return $GLOBALS['required_override'];return array('post_type'=>$post_type,'field_name'=>'Message','min_length'=>3,'error_message'=>'Fixture required message','campaign_codes'=>array('pm'),'categories'=>array('electric-forklift'));}}
require dirname(__DIR__).'/xiaoman-inquiry-center/configuration.php';
require dirname(__DIR__).'/xiaoman-inquiry-center/compatibility.php';
$n=0;function verify_migration($name,$value){global $n;if(!$value)throw new RuntimeException($name);$n++;}
if($credentials_mode!=='constants'){
 $fixture=XI_Compatibility::preview_profile();verify_migration('stored_credential_preview',!is_wp_error($fixture));
 $pending=$fixture['config'];$pending['migration']['status']='import_preparing';
 foreach(array('id','secret') as $name){$pending['secrets'][$name]=XI_Config::encrypt('synthetic-recovery-'.$name);verify_migration('synthetic_credential_encrypted',is_string($pending['secrets'][$name]));}
 $options[XI_Config::OPTION]=$pending;$saved_before=$options;
 if($credentials_mode==='missing-decrypt'){
  verify_migration('sodium_decrypt_unavailable',!function_exists('sodium_crypto_secretbox_open'));
  $failed=XI_Compatibility::prepare_import();
  verify_migration('missing_decrypt_rejected',is_wp_error($failed)&&$failed->code==='legacy_credentials');
  verify_migration('missing_decrypt_preserves_ciphertext',$options===$saved_before&&$writes===array());
 }else{
  $retry=XI_Compatibility::prepare_import();verify_migration('stored_credentials_retry',!is_wp_error($retry));
  verify_migration('stored_credentials_retry_no_writes',$options===$saved_before&&$writes===array());
  $options[XI_Config::OPTION]=$retry['config'];foreach(array('id','secret') as $name)verify_migration('stored_credentials_remain_readable',XI_Config::credential($name)==='synthetic-recovery-'.$name);$options=$saved_before;
  foreach(array('id','secret') as $name){
   $damaged=$pending;$damaged['secrets'][$name]='synthetic-corrupt-ciphertext';$options[XI_Config::OPTION]=$damaged;$damaged_before=$options;
   $failed=XI_Compatibility::prepare_import();verify_migration('damaged_ciphertext_rejected',is_wp_error($failed)&&$failed->code==='legacy_credentials');
   verify_migration('damaged_ciphertext_preserved',$options===$damaged_before&&$writes===array());
   foreach(array('synthetic-recovery-id','synthetic-recovery-secret','synthetic-corrupt-ciphertext') as $value)verify_migration('credential_error_has_no_values',strpos($failed->message,$value)===false);
  }
  $options=$saved_before;$fixture_salt='changed-synthetic-salt';$failed=XI_Compatibility::prepare_import();
  verify_migration('changed_salt_rejected',is_wp_error($failed)&&$failed->code==='legacy_credentials');
  verify_migration('changed_salt_preserves_ciphertext',$options===$saved_before&&$writes===array());unset($fixture_salt);
  $malformed=$pending;$malformed['secrets']='synthetic-invalid-secret-container';$options[XI_Config::OPTION]=$malformed;$malformed_before=$options;$failed=XI_Compatibility::prepare_import();
  verify_migration('malformed_secret_container_rejected',is_wp_error($failed)&&$failed->code==='legacy_credentials');
  verify_migration('malformed_secret_container_preserved',$options===$malformed_before&&$writes===array());$options=$saved_before;
 }
 $preview=XI_Compatibility::preview_profile();verify_migration('unreadable_credentials_preview',!is_wp_error($preview)&&!isset($preview['config']['secrets'])&&$preview['config']['migration']['status']==='preview');
 verify_migration('unreadable_credentials_preview_no_writes',$writes===array());
 echo json_encode(array('php'=>PHP_VERSION,'fixture'=>$site,'credential_mode'=>$credentials_mode,'passed'=>$n))."\n";exit;
}
$before=$options;
$crypto_failure=true;$preview=XI_Compatibility::preview_profile();
verify_migration('preview_does_not_encrypt',!is_wp_error($preview));
verify_migration('preview_has_no_secret_container',!array_key_exists('secrets',$preview['config']));
verify_migration('preview_not_claimed_imported',$preview['config']['migration']['status']==='preview');
$encoded=json_encode($preview);
foreach(array('fixture-client','fixture-secret','must-never-be-returned') as $secret)verify_migration('preview_contains_no_credentials',strpos($encoded,$secret)===false);
verify_migration('preview_required_preserved',$preview['required']===NIFR_Conditional_Required::options());
verify_migration('preview_no_writes',$options===$before&&$writes===array());
$failed=XI_Compatibility::prepare_import();verify_migration('encryption_failure',is_wp_error($failed));
verify_migration('encryption_failure_no_partial_write',$options===$before&&$writes===array());$crypto_failure=false;
$wpdb->last_error='Synthetic form query failure';$failed=XI_Compatibility::prepare_import();
verify_migration('form_failure',is_wp_error($failed)&&$failed->code==='legacy_forms');
verify_migration('form_failure_no_partial_write',$options===$before&&$writes===array());$wpdb->last_error='';
$terms_failure=true;$failed=XI_Compatibility::prepare_import();
verify_migration('taxonomy_failure',is_wp_error($failed)&&$failed->code==='legacy_terms');
verify_migration('taxonomy_failure_no_partial_write',$options===$before&&$writes===array());$terms_failure=false;
$required_override=array('field_name'=>'Message');$failed=XI_Compatibility::prepare_import();
verify_migration('required_failure',is_wp_error($failed)&&$failed->code==='legacy_required');
verify_migration('required_failure_no_partial_write',$options===$before&&$writes===array());unset($required_override);
$existing=array_merge(XI_Config::defaults(),array('enabled'=>true,'number_prefix'=>'HT-N','record_meta'=>'ht_native_record','queue_prefix'=>'htxi_','secrets'=>array('secret'=>'existing-encrypted-value'),'future_config'=>array('preserve'=>true)));
$options[XI_Config::OPTION]=$existing;$saved_before=$options;
foreach(array('prepare_import','import_profile','preview_profile') as $method){$failed=XI_Compatibility::$method();verify_migration('existing_unified_config_rejected',is_wp_error($failed)&&$failed->code==='legacy_config_exists');verify_migration('existing_unified_config_unchanged',$options===$saved_before&&$writes===array());}
foreach(array('0',0,'',true) as $malformed){$options[XI_Config::OPTION]=$malformed;$saved_before=$options;$failed=XI_Compatibility::prepare_import();verify_migration('malformed_existing_config_rejected',is_wp_error($failed)&&$failed->code==='legacy_config_exists');verify_migration('malformed_existing_config_unchanged',$options===$saved_before&&$writes===array());}
unset($options[XI_Config::OPTION]);
$prepared=XI_Compatibility::prepare_import();
verify_migration('preparation_success',!is_wp_error($prepared));
verify_migration('prepared_required_exact',$prepared['required']===NIFR_Conditional_Required::options());
verify_migration('prepared_profile_no_writes',$options===$before&&$writes===array());
// A manual retry accepts only the exact public profile from the same sources.
function reverse_fixture_keys($value){if(!is_array($value))return $value;foreach($value as $key=>$item)$value[$key]=reverse_fixture_keys($item);return array_reverse($value,true);}
$pending=reverse_fixture_keys($prepared['config']);$pending['migration']['status']='import_preparing';$pending['migration']['at']='2026-01-01T00:00:00+00:00';$pending['migration']['from']=array_reverse($pending['migration']['from']);$pending['secrets']=array('id'=>'pending-encrypted-id','secret'=>'pending-encrypted-secret','future-key'=>'pending-future-ciphertext');
$options[XI_Config::OPTION]=$pending;$pending_before=$options;$crypto_failure=true;
$pending_preview=XI_Compatibility::preview_profile();
verify_migration('pending_preview_no_encryption',!is_wp_error($pending_preview));
verify_migration('pending_preview_no_credentials',!isset($pending_preview['config']['secrets'])&&strpos(json_encode($pending_preview),'pending-encrypted-secret')===false);
verify_migration('pending_preview_status',$pending_preview['config']['migration']['status']==='preview');
verify_migration('pending_preview_no_writes',$options===$pending_before&&$writes===array());$crypto_failure=false;
$retry=XI_Compatibility::prepare_import();
verify_migration('pending_same_profile_can_prepare',!is_wp_error($retry)&&$retry['config']['enabled']===false&&$retry['required']===$prepared['required']);
verify_migration('pending_retry_encrypts_after_comparison',isset($retry['config']['secrets']['id'],$retry['config']['secrets']['secret'])&&$retry['config']['secrets']['secret']!=='pending-encrypted-secret');
verify_migration('constants_override_unreadable_ciphertext',XI_Config::credential('id')==='fixture-client'&&XI_Config::credential('secret')==='fixture-secret');
verify_migration('unknown_secret_entries_preserved',$retry['config']['secrets']['future-key']==='pending-future-ciphertext');
verify_migration('pending_retry_does_not_persist',$options===$pending_before&&$writes===array());
$required_original=NIFR_Conditional_Required::options();$required_override=reverse_fixture_keys($required_original);$retry=XI_Compatibility::prepare_import();
verify_migration('pending_required_key_order_accepted',!is_wp_error($retry)&&$retry['config']['migration']['required_hash']===$pending['migration']['required_hash']);
verify_migration('pending_required_key_order_no_writes',$options===$pending_before&&$writes===array());
foreach(array('campaign_codes','min_length') as $field){
 $required_override=$required_original;$required_override[$field]=$field==='campaign_codes'?array():'3';$crypto_failure=true;
 $rejected=XI_Compatibility::prepare_import();verify_migration('pending_required_drift_rejected',is_wp_error($rejected)&&$rejected->code==='legacy_config_changed');
 verify_migration('pending_required_drift_keeps_same_post_type',$required_override['post_type']===$pending['post_type']);
 verify_migration('pending_required_drift_no_writes',$options===$pending_before&&$writes===array());
}
unset($required_override);$crypto_failure=false;
foreach(array('identity','mapping','scalar-type','extra-field','core-enabled','core-toggle-type','crm-toggle','migration-source','missing-source','missing-required-hash','imported-status') as $change){
 $changed=$pending;
 if($change==='identity')$changed['number_prefix']='OTHER-N';
 elseif($change==='mapping')$changed['products']['electric forklift']='Changed product';
 elseif($change==='scalar-type')$changed['origin_id']=(int)$changed['origin_id'];
 elseif($change==='extra-field')$changed['future_config']=array('preserve'=>true);
 elseif($change==='core-enabled')$changed['enabled']=true;
 elseif($change==='core-toggle-type')$changed['enabled']=0;
 elseif($change==='crm-toggle')$changed['crm']=false;
 elseif($change==='migration-source')$changed['migration']['from']=array('other-site/legacy.php');
 elseif($change==='missing-source')unset($changed['migration']['from']);
 elseif($change==='missing-required-hash')unset($changed['migration']['required_hash']);
 else $changed['migration']['status']='imported';
 $options[XI_Config::OPTION]=$changed;$changed_before=$options;$crypto_failure=true;
 $error_code=in_array($change,array('core-enabled','core-toggle-type','imported-status'),true)?'legacy_config_exists':'legacy_config_changed';
 foreach(array('prepare_import','preview_profile') as $method){$rejected=XI_Compatibility::$method();verify_migration('pending_changed_rejected_'.$change,is_wp_error($rejected)&&$rejected->code===$error_code);verify_migration('pending_changed_unchanged_'.$change,$options===$changed_before&&$writes===array());}
}
$crypto_failure=false;$options=$before;
$config=XI_Compatibility::import_profile();
verify_migration('import_profile',!is_wp_error($config));
verify_migration('disabled_preparation',$config['enabled']===false);
verify_migration('record_meta',$config['record_meta']===strtolower($prefix).'_native_record');
verify_migration('number_prefix',$config['number_prefix']===$number);
verify_migration('queue_prefix',$config['queue_prefix']===$queue);
verify_migration('queue_cursor',$config['queue_cursor']===$queue.'capture_cursor_v1');
verify_migration('queue_cpt',$config['queue_cpt']===rtrim($queue,'_').'_job');
verify_migration('legacy_job',$config['legacy_job']===$queue.'send_v1');
verify_migration('legacy_role',$config['legacy_role']===$site.'_inquiry_viewer');
verify_migration('site_post_type',$config['post_type']===$post_type);
verify_migration('site_taxonomies',$config['taxonomies']===array($taxonomy));
verify_migration('site_origin',$config['origin_id']===$fixture_id.'2345'&&$config['origin_name']==='Fixture '.$site);
verify_migration('site_product_field',$config['product_field']===$fixture_id.'3456');
verify_migration('site_excluded_terms',$config['excluded_terms']===($site==='rongfeng'?array('tractor-attachments'):array()));
verify_migration('only_rf_imports_rf_options',isset($config['products']['legacy-only'])===($site==='rongfeng'));
verify_migration('category_labels',$config['equipment_labels']['electric-forklift']==='Electric Forklift');
verify_migration('product_mapping',$config['products']['electric forklift']==='电动叉车');
verify_migration('other_not_a_taxonomy',$site==='rongfeng'?!isset($config['products']['other']):$config['products']['other']==='其他');
verify_migration('quick_option_not_a_taxonomy',$config['products']['self loading stacker']==='堆高车');
verify_migration('distinct_lead_name',$config['product_lead_names']['self loading stacker']===($site==='rongfeng'?'堆高车':'自提车'));
verify_migration('unmapped_quick_option_preserved',$site==='rongfeng'?!isset($config['products']['custom option'])&&$config['product_lead_names']['custom option']==='其他':$config['products']['custom option']==='其他');
verify_migration('level_field',$config['level_field']===$fixture_id.'4567'&&$config['level_value']==='无');
verify_migration('successful_import_still_has_no_writes',$options===$before&&$writes===array());
verify_migration('credentials_encrypted',isset($config['secrets']['id'],$config['secrets']['secret'])&&strpos($config['secrets']['secret'],'fixture-secret')===false);
// The caller persists the required fields only after the entire import succeeds.
update_option(XI_Config::OPTION,$config);
update_option('xi_required_v1',$prepared['required']);
verify_migration('required_preserved',get_option('xi_required_v1')===NIFR_Conditional_Required::options());
verify_migration('queue_cursor_and_success_untouched',get_option('ninxm_capture_cursor_v1')===190&&get_option('ninxm_job_v1_190')===array('state'=>'success','attempts'=>1)&&$writes===array(XI_Config::OPTION,'xi_required_v1'));
verify_migration('credentials_readable',XI_Config::credential('id')==='fixture-client'&&XI_Config::credential('secret')==='fixture-secret');
define('XI_API',$config['api']);require dirname(__DIR__).'/xiaoman-inquiry-center/provider.php';
verify_migration('provider_separate_lead_name',XI_Xiaoman_Queue_V1::product_mapping('self loading stacker')===array('lead_name'=>$site==='rongfeng'?'堆高车':'自提车','product_name'=>'堆高车'));

// Execute the actual record reader with the imported legacy metadata key.
define('XI_RECORD_META',$config['record_meta']);define('XI_VIEW_CAP','xiaoman_inquiry_view');define('XI_VIEW_ROLE','xiaoman_inquiry_viewer');define('XI_PROFILE',$config);
function add_action(...$args){}function absint($id){return abs((int)$id);}
class XI_Request_Attribution_V1 {static function repair_legacy($v){return $v;}}
class XI_Inquiry_Source_V1 {static function label($v){return 'Fixture source';}}
require dirname(__DIR__).'/xiaoman-inquiry-center/native-inquiry-center.php';
$record=array('schema'=>1,'uuid'=>'historical-fixture','kind'=>'detail','hd'=>array(),'source'=>'Fixture source','sync_requested'=>true,'submitted_at'=>'2026-09-01 00:00:00');
$row=array('form_data'=>json_encode(array($config['record_meta']=>array('value'=>json_encode($record)))));
verify_migration('runtime_imported_meta',XI_Native_Inquiry_Center::META===$config['record_meta']);
verify_migration('historical_record_reader',XI_Native_Inquiry_Center::record($row)===$record);
verify_migration('historical_number',XI_Native_Inquiry_Center::number(123)===$number.'123');
verify_migration('unrelated_record_ignored',XI_Native_Inquiry_Center::record(array('form_data'=>'{}'))===array());
echo json_encode(array('php'=>PHP_VERSION,'fixture'=>$site,'passed'=>$n))."\n";

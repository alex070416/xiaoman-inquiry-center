<?php
// Real WordPress function signatures/hook order with isolated substitutes and in-memory SQLite.
// Does not claim to exercise WordPress/MySQL dbDelta itself.
define('XI_FIXTURE_BOOTSTRAP_ONLY',true);require __DIR__.'/owner-protocol.test.php';
define('XI_FILE',__DIR__.'/fixture-plugin.php');define('XI_VERSION','CANDIDATE');define('XI_PROFILE',array('number_prefix'=>'FW-N'));
$GLOBALS['options']=array('home'=>'https://fixture.example.invalid');$GLOBALS['hooks']=array();
function add_filter($name,$callback,$priority=10,$args=1){}
function add_action($name,$callback,$priority=10,$args=1){$GLOBALS['hooks'][$name][$priority][]=$callback;}
function do_action($name){$groups=$GLOBALS['hooks'][$name]??array();ksort($groups);foreach($groups as $callbacks)foreach($callbacks as $callback)$callback();}
function is_admin(){return false;}
function admin_url($v){return 'https://fixture.example.invalid/wp-admin/'.$v;}
function plugins_url($file,$base){return 'https://fixture.example.invalid/plugin/'.$file;}
function wp_enqueue_script($handle,$url,$deps,$version,$footer){$GLOBALS['asset']=$url;}
function wp_add_inline_script($handle,$code,$placement){$GLOBALS['inline']=$code;}
function wp_json_encode($v){return json_encode($v);}
function wp_parse_url($v,$component=-1){return parse_url($v,$component);}
function untrailingslashit($v){return rtrim($v,'/');}
function home_url($v=''){return 'https://fixture.example.invalid'.$v;}
// The tests use only explicit configured paths, so substitute the native fallback here.
// XI_Native_Inquiry_Center::thank_you_url is supplied by the fixture class below via its original fixture definition.
function reset_legacy_table(){
 global $wpdb;$wpdb->query('DROP TABLE fixture_xi_conversion_receipts');
 $wpdb->query('CREATE TABLE fixture_xi_conversion_receipts(id INTEGER PRIMARY KEY,submission_id INTEGER,token_hash TEXT,request_uuid TEXT,form_kind TEXT,expires_utc TEXT,is_test INTEGER,consent_at_submit TEXT,ga4_state TEXT,ads_state TEXT,ga4_claimed_utc TEXT,ads_claimed_utc TEXT,ga4_callback_utc TEXT,ads_callback_utc TEXT)');
 foreach(array(7=>'pending',8=>'claimed',9=>'callback') as $id=>$state)$wpdb->query($wpdb->prepare("INSERT INTO fixture_xi_conversion_receipts(id,submission_id,token_hash,request_uuid,form_kind,expires_utc,is_test,consent_at_submit,ga4_state,ads_state) VALUES(%d,%d,%s,'fixture-request-uuid','quick',%s,1,'{}',%s,'pending')",$id,$id,hash('sha256',str_repeat(dechex($id+3),64)),gmdate('Y-m-d H:i:s',time()+1800),$state));
 XI_Conversions::$owner_schema=null;XI_Conversions::$enqueued=false;$GLOBALS['options'][XI_Conversions::SCHEMA_OPTION]='';unset($GLOBALS['options'][XI_Conversions::MIGRATION_STATUS]);
}
reset_legacy_table();$before=$wpdb->db->query('SELECT id,ga4_state FROM fixture_xi_conversion_receipts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
XI_Conversions::boot();check('migration_hook_registered_at_init_zero',in_array(array('XI_Conversions','maybe_upgrade'),$GLOBALS['hooks']['init'][0],true));
check('owned_action_registered_for_loggedin_and_guest',isset($GLOBALS['hooks']['wp_ajax_xi_conversion_owned'],$GLOBALS['hooks']['wp_ajax_nopriv_xi_conversion_owned']));
do_action('init');check('update_migrates_without_settings_save',XI_Conversions::schema_ready()&&get_option(XI_Conversions::SCHEMA_OPTION)===XI_Conversions::PROTOCOL);
check('migration_preserves_old_pending_claimed_callback',$before===$wpdb->db->query('SELECT id,ga4_state FROM fixture_xi_conversion_receipts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
$calls=$GLOBALS['dbdelta_calls'];do_action('init');check('healthy_schema_does_not_rerun_dbdelta',$GLOBALS['dbdelta_calls']===$calls);
$legacyToken=str_repeat('a',64);$legacy=XI_Conversions::claim($legacyToken,'FW-N7','ga4',$consent);check('old_beta7_js_keeps_legacy_claim_contract',$legacy['dispatch']&&!isset($legacy['claim_owner']));
check('old_beta7_js_ack_without_owner_accepted',XI_Conversions::acknowledge($legacyToken,'FW-N7','ga4')===true);
check('historic_claimed_not_reset_or_replayed',XI_Conversions::claim(str_repeat('b',64),'FW-N8','ga4',$consent,XI_Conversions::PROTOCOL)['dispatch']===false);
check('historic_no_owner_ack_still_accepted',XI_Conversions::acknowledge(str_repeat('b',64),'FW-N8','ga4')===true);
XI_Conversions::assets();check('healthy_schema_serves_owned_js_and_protocol',str_ends_with($GLOBALS['asset'],'/conversions.js')&&str_contains($GLOBALS['inline'],'"receipt_protocol":"owned-v1"'));
reset_legacy_table();$GLOBALS['migration_fail']=true;do_action('init');check('failed_migration_does_not_mark_schema_ready',!XI_Conversions::schema_ready()&&get_option(XI_Conversions::SCHEMA_OPTION)==='');
$rejected=XI_Conversions::claim($legacyToken,'FW-N7','ga4',$consent,XI_Conversions::PROTOCOL);check('owned_claim_blocked_before_legacy_pending_consumed',$rejected instanceof WP_Error&&$rejected->code==='protocol'&&$wpdb->get_var('SELECT ga4_state FROM fixture_xi_conversion_receipts WHERE id=7')==='pending');
XI_Conversions::assets();check('failed_migration_keeps_beta7_legacy_asset',str_ends_with($GLOBALS['asset'],'/conversions.legacy.js')&&str_contains($GLOBALS['inline'],'"receipt_protocol":""'));
$legacy=XI_Conversions::claim($legacyToken,'FW-N7','ga4',$consent);check('legacy_claim_survives_absent_owner_columns',$legacy['dispatch']&&!isset($legacy['claim_owner']));
check('legacy_ack_survives_absent_owner_columns',XI_Conversions::acknowledge($legacyToken,'FW-N7','ga4')===true);
$failedCalls=$GLOBALS['dbdelta_calls'];do_action('init');check('failed_migration_frontend_is_throttled',$GLOBALS['dbdelta_calls']===$failedCalls&&!$wpdb->lock_held);
$GLOBALS['migration_fail']=false;$GLOBALS['options'][XI_Conversions::MIGRATION_STATUS]['retry_at']=time()-1;XI_Conversions::$owner_schema=null;do_action('init');check('later_init_recovers_failed_migration',XI_Conversions::schema_ready());
$columns=$wpdb->get_col('SHOW COLUMNS FROM '.XI_Conversions::table());check('added_owner_columns_only',count($columns)===16);
reset_legacy_table();$GLOBALS['options'][XI_Conversions::SCHEMA_OPTION]=XI_Conversions::PROTOCOL;do_action('init');check('schema_option_alone_cannot_hide_missing_columns',XI_Conversions::schema_ready());
reset_legacy_table();$cfg['enabled']=false;$calls=$GLOBALS['dbdelta_calls'];do_action('init');check('disabled_site_is_not_migrated',$GLOBALS['dbdelta_calls']===$calls&&!XI_Conversions::schema_ready());$cfg['enabled']=true;
reset_legacy_table();$wpdb->external_lock=true;$calls=$GLOBALS['dbdelta_calls'];do_action('init');check('concurrent_migration_lock_does_not_wait_or_run_ddl',$GLOBALS['dbdelta_calls']===$calls&&!XI_Conversions::schema_ready());$wpdb->external_lock=false;
do_action('init');check('released_competing_lock_allows_migration',XI_Conversions::schema_ready()&&!$wpdb->lock_held);
echo json_encode(array('suite'=>'wordpress-interface-migration-and-rolling-fixtures','passed'=>count($checks),'checks'=>$checks,'limitation'=>'dbDelta and WordPress hooks are substitutes; real MySQL migration still required'),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;

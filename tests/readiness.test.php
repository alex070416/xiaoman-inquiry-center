<?php
// Local WordPress metadata fixtures only. No bootstrap, customer rows, network,
// signing identity or delivery runtime is loaded by this gate.
if (PHP_SAPI !== 'cli') exit(1);
define('ABSPATH', __DIR__.'/');
define('ARRAY_A', 'ARRAY_A');
define('XI_VERSION', 'readiness-fixture');
define('BRICKS_VERSION', '2.4.1-fixture');
define('XIAOMAN_CLIENT_ID', 'SYNTHETIC-CLIENT-DO-NOT-DISCLOSE');
define('XIAOMAN_CLIENT_SECRET', 'SYNTHETIC-SECRET-DO-NOT-DISCLOSE');

class WP_Error {
 public function __construct(public $code, public $message) {}
 public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function forbidden_effect($name) {
 $GLOBALS['forbidden_effects'][]=$name;
 throw new RuntimeException('Readiness performed a prohibited effect: '.$name);
}
function wp_remote_get(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_remote_post(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_remote_request(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_safe_remote_get(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_safe_remote_post(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_safe_remote_request(...$args) { return forbidden_effect(__FUNCTION__); }
function update_option(...$args) { return forbidden_effect(__FUNCTION__); }
function add_option(...$args) { return forbidden_effect(__FUNCTION__); }
function delete_option(...$args) { return forbidden_effect(__FUNCTION__); }
function update_site_option(...$args) { return forbidden_effect(__FUNCTION__); }
function add_site_option(...$args) { return forbidden_effect(__FUNCTION__); }
function delete_site_option(...$args) { return forbidden_effect(__FUNCTION__); }
function set_transient(...$args) { return forbidden_effect(__FUNCTION__); }
function delete_transient(...$args) { return forbidden_effect(__FUNCTION__); }
function update_post_meta(...$args) { return forbidden_effect(__FUNCTION__); }
function add_post_meta(...$args) { return forbidden_effect(__FUNCTION__); }
function delete_post_meta(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_insert_post(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_update_post(...$args) { return forbidden_effect(__FUNCTION__); }
function dbDelta(...$args) { return forbidden_effect(__FUNCTION__); }
function as_enqueue_async_action(...$args) { return forbidden_effect(__FUNCTION__); }
function as_schedule_recurring_action(...$args) { return forbidden_effect(__FUNCTION__); }
function as_schedule_single_action(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_schedule_event(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_schedule_single_event(...$args) { return forbidden_effect(__FUNCTION__); }
function do_action(...$args) { return forbidden_effect(__FUNCTION__); }
function wp_register_ability(...$args) { return forbidden_effect(__FUNCTION__); }
function add_filter(...$args) { return forbidden_effect(__FUNCTION__); }
function add_action($hook, $callback, $priority=10, $accepted=1) {
 if ($hook!=='admin_menu' || $callback!==array('XI_Readiness','menu')) forbidden_effect('business_boot:'.$hook);
 $GLOBALS['hooks'][]=array($hook,$callback,$priority);
}
function add_submenu_page($parent,$title,$label,$capability,$slug,$callback) {
 $GLOBALS['menus'][]=compact('parent','title','label','capability','slug','callback');
 return 'settings_page_'.$slug;
}
function current_user_can($capability) { return $capability==='manage_options' && $GLOBALS['can_manage']; }
function get_option($key,$default=false) {
 $GLOBALS['reads'][]='option:'.$key;
 if (preg_match('/(?:job_v1_|manual_v1_|lead_token|conversion_test_plan)/', (string)$key)) forbidden_effect('customer_option:'.$key);
 return array_key_exists($key,$GLOBALS['options'])?$GLOBALS['options'][$key]:$default;
}
function get_site_option($key,$default=false) {
 $GLOBALS['reads'][]='site_option:'.$key;
 return $key==='active_sitewide_plugins'?$GLOBALS['network_plugins']:$default;
}
function wp_parse_args($value,$defaults) { return array_merge($defaults,$value); }
function wp_parse_url($url,$component=-1) { return parse_url($url,$component); }
function home_url($path='') { return 'https://'.$GLOBALS['site_host'].'/'.ltrim($path,'/'); }
function admin_url($path='') { return home_url('wp-admin/'.$path); }
function get_bloginfo($name) { return $GLOBALS['wordpress_version']; }
function is_ssl() { return true; }
function post_type_exists($name) { return $name==='rcb-cookie' || $name===$GLOBALS['post_type']; }
function taxonomy_exists($name) { return $name===$GLOBALS['taxonomy']; }
function is_object_in_taxonomy($post_type,$taxonomy) { return $post_type===$GLOBALS['post_type'] && $taxonomy===$GLOBALS['taxonomy']; }
function get_posts($args) {
 if (($args['post_type']??'')!=='rcb-cookie' || ($args['meta_key']??'')!=='uniqueName') forbidden_effect('non_rcb_posts');
 $GLOBALS['reads'][]='rcb_posts';$out=array();
 foreach ($GLOBALS['services'] as $id=>$service) {
  if ($service['name']===($args['meta_value']??'') && in_array($service['status'],$args['post_status'],true)) $out[]=(object)array('ID'=>$id,'post_status'=>$service['status']);
 }
 return array_slice($out,0,$args['posts_per_page']??2);
}
function get_post_meta($id,$key,$single=false) {
 if (!isset($GLOBALS['services'][$id])) forbidden_effect('non_rcb_meta');
 if (!in_array($key,array('technicalDefinitions','googleConsentModeConsentTypes','deleteTechnicalDefinitionsAfterOptOut'),true)) forbidden_effect('unexpected_rcb_meta:'.$key);
 $GLOBALS['reads'][]='rcb_meta:'.$key;
 return $GLOBALS['services'][$id]['meta'][$key]??'';
}
function esc_html($value) { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function esc_url($value) { return esc_html($value); }
function wp_json_encode($value,$flags=0) { return json_encode($value,$flags); }

class Readiness_Fixture_DB {
 public $prefix='fixture_';
 public $tables=array(),$columns=array(),$indexes=array(),$queries=array();
 function esc_like($value) { return addcslashes($value,'_%\\'); }
 function prepare($query,...$args) {
  foreach ($args as $arg) $query=preg_replace_callback('/%[sd]/',static function($match)use($arg){return $match[0]==='%d'?(string)(int)$arg:"'".str_replace("'","''",$arg)."'";},$query,1);
  return $query;
 }
 private function show($query) {
  if (!is_string($query) || !preg_match('/\ASHOW\s/i',$query)) forbidden_effect('non_SHOW_SQL');
  $this->queries[]=$query;
 }
 function get_var($query) {
  $this->show($query);
  if (!preg_match("/\ASHOW TABLES LIKE '([^']+)'\z/D",$query,$match)) forbidden_effect('unexpected_SHOW');
  $table=str_replace(array('\\_','\\%','\\\\'),array('_','%','\\'),$match[1]);
  return in_array($table,$this->tables,true)?$table:null;
 }
 function get_col($query) {
  $this->show($query);
  if (!preg_match('/\ASHOW COLUMNS FROM ([a-zA-Z0-9_]+)\z/D',$query,$match)) forbidden_effect('unexpected_SHOW_columns');
  return in_array($match[1],$this->tables,true)?$this->columns:array();
 }
 function get_results($query,$format=null) {
  $this->show($query);
  if (!preg_match('/\ASHOW INDEX FROM ([a-zA-Z0-9_]+)\z/D',$query,$match) || $format!==ARRAY_A) forbidden_effect('unexpected_SHOW_indexes');
  return in_array($match[1],$this->tables,true)?$this->indexes:array();
 }
 function query(...$args) { return forbidden_effect('wpdb.query'); }
 function insert(...$args) { return forbidden_effect('wpdb.insert'); }
 function update(...$args) { return forbidden_effect('wpdb.update'); }
 function delete(...$args) { return forbidden_effect('wpdb.delete'); }
 function get_row(...$args) { return forbidden_effect('wpdb.customer_row'); }
}

$GLOBALS['forbidden_effects']=array();$GLOBALS['hooks']=array();$GLOBALS['menus']=array();
$GLOBALS['autoloads']=array();
spl_autoload_register(static function($name){$GLOBALS['autoloads'][]=$name;throw new RuntimeException('Unexpected autoload: '.$name);});
require dirname(__DIR__).'/xiaoman-inquiry-center/configuration.php';
require dirname(__DIR__).'/xiaoman-inquiry-center/cookies.php';
require dirname(__DIR__).'/xiaoman-inquiry-center/compatibility.php';
require dirname(__DIR__).'/xiaoman-inquiry-center/readiness.php';

$assertions=0;$cases=array();
function verify_readiness($name,$condition) {
 global $assertions;if (!$condition) throw new RuntimeException($name);$assertions++;
}
function case_done_readiness($name) { $GLOBALS['cases'][]=$name; }
function check_readiness($report,$code) {
 foreach ($report['checks'] as $check) if ($check['code']===$code) return $check;
 throw new RuntimeException('Missing readiness check: '.$code);
}
function render_readiness() { ob_start();try{XI_Readiness::page();return ob_get_contents();}finally{ob_end_clean();} }
function service_fixture($id,$name,$purposes,$definitions=array()) {
 $GLOBALS['services'][$id]=array('name'=>$name,'status'=>'publish','meta'=>array(
  'googleConsentModeConsentTypes'=>json_encode($purposes),'technicalDefinitions'=>json_encode($definitions),'deleteTechnicalDefinitionsAfterOptOut'=>true));
}
function reset_readiness($index=1) {
 $GLOBALS['can_manage']=true;$GLOBALS['reads']=array();$GLOBALS['network_plugins']=array();
 $GLOBALS['site_host']='synthetic'.$index.'.example.invalid';$GLOBALS['post_type']='catalog'.$index;$GLOBALS['taxonomy']='category'.$index;
 $GLOBALS['wordpress_version']='7.1.2';
 $profile=array_merge(XI_Config::defaults(),array('enabled'=>false,'crm'=>true,'number_prefix'=>'S'.$index.'-N','record_meta'=>'synthetic'.$index.'_record',
  'queue_prefix'=>'synthetic'.$index.'_','queue_cursor'=>'synthetic'.$index.'_cursor','queue_cpt'=>'synthetic'.$index.'_job',
  'post_type'=>$GLOBALS['post_type'],'taxonomies'=>array($GLOBALS['taxonomy']),'origin_id'=>(string)(1000+$index),'product_field'=>(string)(2000+$index),
  'products'=>array('fixture product'=>'fixture option'),'field_aliases'=>array('FixtureEmail'=>'F5'),'consent_service'=>'synthetic-attribution',
  'secrets'=>array('secret'=>'SYNTHETIC-ENCRYPTED-NO-OUTPUT'),'migration'=>array('status'=>'imported')));
 $conversion=array('enabled'=>true,'enhanced'=>true,'site_host'=>$GLOBALS['site_host'],'purpose_consent_enabled'=>true,
  'gtm_id'=>'GTM-TEST'.$index,'ga4_id'=>'G-TEST'.$index,'ads_id'=>'AW-'.(90000+$index),'ads_label'=>'quote_'.$index,
  'analytics_service'=>'synthetic-analytics','ads_service'=>'synthetic-ads','user_data_service'=>'synthetic-enhanced','pending_ads_storage_service'=>'synthetic-ads-pending');
 $GLOBALS['options']=array(XI_Config::OPTION=>$profile,'xi_conversion_config_v1'=>$conversion,'xi_conversion_receipt_schema_v1'=>'owned-v1',
  'xi_mcp_readonly_v1'=>array('enabled'=>true,'site_host'=>$GLOBALS['site_host']),'active_plugins'=>array('rongfeng-inquiry-center/inquiry-center.php'),
  $profile['queue_prefix'].'last_recovery_v1'=>1700000000);
 $GLOBALS['services']=array();
 $pending=array(array('name'=>'xi_conversion_pending_v1','type'=>'session','host'=>$GLOBALS['site_host'],'duration'=>30,'durationUnit'=>'m'));
 service_fixture(10,'synthetic-attribution',array(),XI_Cookies::definitions());
 service_fixture(11,'synthetic-analytics',array('analytics_storage'),$pending);
 service_fixture(12,'synthetic-ads',array('ad_storage'));
 service_fixture(13,'synthetic-enhanced',array('ad_user_data'));
 service_fixture(14,'synthetic-ads-pending',array('ad_storage'),$pending);
 $db=new Readiness_Fixture_DB();
 $db->tables=array($db->prefix.'bricks_form_submissions',$db->prefix.'xi_conversion_receipts');
 $db->columns=array('id','submission_id','token_hash','request_uuid','form_kind','created_utc','expires_utc','site_timezone','is_test','consent_at_submit',
  'ga4_state','ads_state','ga4_claimed_utc','ads_claimed_utc','ga4_claim_owner','ads_claim_owner','ga4_callback_utc','ads_callback_utc');
 foreach (array('PRIMARY'=>'id','submission_id'=>'submission_id','token_hash'=>'token_hash') as $name=>$column) $db->indexes[]=array('Non_unique'=>0,'Key_name'=>$name,'Seq_in_index'=>1,'Column_name'=>$column);
 $GLOBALS['wpdb']=$db;
}

reset_readiness();XI_Readiness::boot();XI_Readiness::menu();
verify_readiness('readiness registers only its own admin menu',count($GLOBALS['hooks'])===1 && $GLOBALS['hooks'][0][1]===array('XI_Readiness','menu'));
verify_readiness('preparation menu uses Settings and administrator capability',$GLOBALS['menus'][0]['parent']==='options-general.php' && $GLOBALS['menus'][0]['capability']==='manage_options');
$entry=file_get_contents(dirname(__DIR__).'/xiaoman-inquiry-center/xiaoman-inquiry-center.php');
$early=strpos($entry,'XI_Readiness::boot()');$guard=strpos($entry,'if (!$xi_profile[\'enabled\']');
$runtime=strpos($entry,"define('XI_PROFILE'");
verify_readiness('real entry registers readiness before runtime early return',$early!==false && $guard!==false && $runtime!==false && $early<$guard && $guard<$runtime);
case_done_readiness('preparation entry and menu placement');

for ($site=1;$site<=6;$site++) {
 reset_readiness($site);$before=$GLOBALS['options'];$r=XI_Readiness::snapshot();
 verify_readiness('synthetic site identity accepted '.$site,check_readiness($r,'profile')['state']==='pass' && $r['site_host']===$GLOBALS['site_host']);
 verify_readiness('disabled core remains visible '.$site,check_readiness($r,'core')['state']==='warning');
 verify_readiness('active legacy remains visible '.$site,check_readiness($r,'legacy')['state']==='warning' && XI_Compatibility::legacy_active()!==array());
 verify_readiness('fixture custom identifiers are not rewritten '.$site,$GLOBALS['options']===$before);
 verify_readiness('no runtime constants or business classes booted '.$site,!defined('XI_HOST') && !defined('XI_PROFILE') && !defined('XI_QUEUE_PREFIX') && !class_exists('XI_Native_Inquiry_Center',false) && !class_exists('XI_Native_Xiaoman_Sync',false) && !class_exists('XI_Conversions',false) && !class_exists('XI_MCP_Readonly',false));
 verify_readiness('all database operations are SHOW '.$site,count($GLOBALS['wpdb']->queries)>0 && !array_filter($GLOBALS['wpdb']->queries,static fn($q)=>!str_starts_with($q,'SHOW ')));
 verify_readiness('external evidence remains unchecked '.$site,$r['external_acceptance']==='not_checked' && count($r['external_checks'])>=7);
}
reset_readiness();$GLOBALS['options']['active_plugins']=array();$GLOBALS['network_plugins']=array('huajing-native-recovery/native-inquiry.php'=>123);
verify_readiness('network-active legacy detected',check_readiness(XI_Readiness::snapshot(),'legacy')['state']==='warning');
case_done_readiness('six synthetic identity profiles, disabled core and network legacy');

foreach (array('true','false',1,0,null,array(true),new stdClass()) as $invalid) {
 reset_readiness();$GLOBALS['options']['xi_conversion_config_v1']['purpose_consent_enabled']=$invalid;
 verify_readiness('nonboolean purpose type blocks',check_readiness(XI_Readiness::snapshot(),'purpose_mode')['state']==='blocked');
}
foreach (array(false,true) as $boolean) {
 reset_readiness();$GLOBALS['options']['xi_conversion_config_v1']['purpose_consent_enabled']=$boolean;
 verify_readiness('explicit boolean purpose mode accepted',check_readiness(XI_Readiness::snapshot(),'purpose_mode')['state']==='pass');
}
reset_readiness();unset($GLOBALS['options']['xi_conversion_config_v1']['purpose_consent_enabled']);
verify_readiness('absent purpose key is not implied true',check_readiness(XI_Readiness::snapshot(),'purpose_mode')['state']==='warning');
reset_readiness();$GLOBALS['options']['xi_conversion_config_v1']['site_host']='another.example.invalid';
verify_readiness('conversion host mismatch blocks',check_readiness(XI_Readiness::snapshot(),'conversion_host')['state']==='blocked');
reset_readiness();$GLOBALS['options']['xi_config_v2']['migration']['status']='import_preparing';
verify_readiness('partial import blocks',check_readiness(XI_Readiness::snapshot(),'migration')['state']==='blocked');
reset_readiness();unset($GLOBALS['options'][XI_Config::OPTION]);
verify_readiness('defaults cannot pass as saved identity',check_readiness(XI_Readiness::snapshot(),'stored_profile')['state']==='blocked');
case_done_readiness('strict purpose types, domain binding and incomplete identity');

reset_readiness();$GLOBALS['taxonomy']='Tractors';$GLOBALS['options'][XI_Config::OPTION]['taxonomies']=array('Tractors');
verify_readiness('registered mixed-case taxonomy accepted',!in_array('taxonomy_missing',XI_Readiness::profile_errors($GLOBALS['options'][XI_Config::OPTION]),true));
foreach (array('tractors','1Tractors','Trac tors') as $bad) {
 reset_readiness();$GLOBALS['taxonomy']='Tractors';$GLOBALS['options'][XI_Config::OPTION]['taxonomies']=array($bad);
 verify_readiness('unregistered or malformed taxonomy rejected: '.$bad,in_array('taxonomy_missing',XI_Readiness::profile_errors($GLOBALS['options'][XI_Config::OPTION]),true));
}
case_done_readiness('taxonomy keys matched case-sensitively');

reset_readiness();$r=XI_Readiness::snapshot();verify_readiness('complete local receipt structure accepted',check_readiness($r,'receipt')['state']==='pass');
foreach (array('missing-table','missing-ga4-owner','missing-ads-owner','missing-number-unique','missing-token-unique','nonunique-number','composite-number-only','missing-schema-version') as $fault) {
 reset_readiness();$db=$GLOBALS['wpdb'];
 if ($fault==='missing-table') $db->tables=array($db->prefix.'bricks_form_submissions');
 elseif ($fault==='missing-ga4-owner' || $fault==='missing-ads-owner') $db->columns=array_values(array_diff($db->columns,array($fault==='missing-ga4-owner'?'ga4_claim_owner':'ads_claim_owner')));
 elseif ($fault==='missing-schema-version') unset($GLOBALS['options']['xi_conversion_receipt_schema_v1']);
 else {
  $column=$fault==='missing-token-unique'?'token_hash':'submission_id';
  $db->indexes=array_values(array_filter($db->indexes,static fn($index)=>$index['Column_name']!==$column));
  if ($fault==='nonunique-number') $db->indexes[]=array('Non_unique'=>1,'Key_name'=>'not_unique','Seq_in_index'=>1,'Column_name'=>'submission_id');
  if ($fault==='composite-number-only') foreach (array(1=>'submission_id',2=>'request_uuid') as $position=>$name) $db->indexes[]=array('Non_unique'=>0,'Key_name'=>'composite','Seq_in_index'=>$position,'Column_name'=>$name);
 }
 verify_readiness('receipt fault blocks '.$fault,check_readiness(XI_Readiness::snapshot(),'receipt')['state']==='blocked');
}
case_done_readiness('receipt absence, owner columns and true single-column uniqueness');

reset_readiness();verify_readiness('complete attribution definitions accepted',check_readiness(XI_Readiness::snapshot(),'attribution_service')['state']==='pass');
foreach (array('duplicate','draft','private','wrong-type','wrong-ttl','wrong-unit','wrong-host','implicit-session','no-cleanup','duplicate-storage','missing-storage') as $fault) {
 reset_readiness();
 if ($fault==='duplicate') $GLOBALS['services'][20]=$GLOBALS['services'][10];
 elseif (in_array($fault,array('draft','private'),true)) $GLOBALS['services'][10]['status']=$fault;
 elseif ($fault==='no-cleanup') $GLOBALS['services'][10]['meta']['deleteTechnicalDefinitionsAfterOptOut']='false';
 else {
  $defs=json_decode($GLOBALS['services'][10]['meta']['technicalDefinitions'],true);
  if ($fault==='wrong-type') $defs[0]['type']='http';
  if ($fault==='wrong-ttl') $defs[0]['duration']=31;
  if ($fault==='wrong-unit') $defs[0]['durationUnit']='mo';
  if ($fault==='wrong-host') $defs[0]['host']='other.example.invalid';
  if ($fault==='implicit-session') $defs[0]['isSessionDuration']=true;
  if ($fault==='duplicate-storage') $defs[]=$defs[0];
  if ($fault==='missing-storage') array_shift($defs);
  $GLOBALS['services'][10]['meta']['technicalDefinitions']=json_encode($defs);
 }
 verify_readiness('attribution metadata mismatch detected '.$fault,check_readiness(XI_Readiness::snapshot(),'attribution_service')['state']==='warning');
}
reset_readiness();$defs=json_decode($GLOBALS['services'][10]['meta']['technicalDefinitions'],true);$defs[0]['host']='.'.strtoupper($GLOBALS['site_host']);$GLOBALS['services'][10]['meta']['technicalDefinitions']=$defs;
verify_readiness('equivalent dot and case host accepted',check_readiness(XI_Readiness::snapshot(),'attribution_service')['state']==='pass');
foreach (array('analytics-purpose','analytics-storage','ads-purpose','enhanced-purpose','ads-pending-cleanup') as $fault) {
 reset_readiness();
 if ($fault==='analytics-purpose') $GLOBALS['services'][11]['meta']['googleConsentModeConsentTypes']='["ad_storage"]';
 if ($fault==='analytics-storage') $GLOBALS['services'][11]['meta']['technicalDefinitions']='[]';
 if ($fault==='ads-purpose') $GLOBALS['services'][12]['meta']['googleConsentModeConsentTypes']='[]';
 if ($fault==='enhanced-purpose') $GLOBALS['services'][13]['meta']['googleConsentModeConsentTypes']='[]';
 if ($fault==='ads-pending-cleanup') $GLOBALS['services'][14]['meta']['deleteTechnicalDefinitionsAfterOptOut']=false;
 $code=match($fault){'analytics-purpose','analytics-storage'=>'analytics_service','ads-purpose'=>'ads_service','enhanced-purpose'=>'enhanced_service',default=>'ads_pending_service'};
 verify_readiness('conversion declaration fault blocks '.$fault,check_readiness(XI_Readiness::snapshot(),$code)['state']==='blocked');
}
case_done_readiness('RCB duplicate, publication, type, TTL, host, cleanup and independent purposes');

reset_readiness();$GLOBALS['can_manage']=false;$before=$GLOBALS['options'];$r=XI_Readiness::snapshot();
verify_readiness('unauthorized snapshot rejected',is_wp_error($r) && $r->code==='forbidden');
verify_readiness('permission rejection precedes metadata reads',$GLOBALS['reads']===array() && $GLOBALS['wpdb']->queries===array());
verify_readiness('unauthorized page renders no report',render_readiness()==='' && $GLOBALS['options']===$before);
case_done_readiness('administrator capability checked before metadata');

reset_readiness();$attack='<script>globalThis.SYNTHETIC_XSS=1</script><img src=x onerror="SYNTHETIC_XSS=2">';
foreach (array('origin_name','level_value','api') as $key) $GLOBALS['options'][XI_Config::OPTION][$key]=$attack;
$GLOBALS['options'][XI_Config::OPTION]['products']=array($attack=>$attack);
$GLOBALS['options'][XI_Config::OPTION]['migration']['private_customer']=$attack.' SYNTHETIC-PRIVATE-CONTACT';
$GLOBALS['options'][XI_Config::OPTION]['secrets']['token']='SYNTHETIC-PRIVATE-TOKEN';
foreach (array('gtm_id','ga4_id','ads_id','ads_label','site_host','analytics_service') as $key) $GLOBALS['options']['xi_conversion_config_v1'][$key]=$attack;
$before=$GLOBALS['options'];$r=XI_Readiness::snapshot();$encoded=json_encode($r);$html=render_readiness();
foreach (array(XIAOMAN_CLIENT_ID,XIAOMAN_CLIENT_SECRET,'SYNTHETIC-ENCRYPTED-NO-OUTPUT','SYNTHETIC-PRIVATE-CONTACT','SYNTHETIC-PRIVATE-TOKEN','SYNTHETIC_XSS') as $secret) {
 verify_readiness('secret and hostile settings absent from whitelist JSON',!str_contains($encoded,$secret));
 verify_readiness('secret and hostile settings absent from HTML',!str_contains($html,$secret));
}
verify_readiness('malicious saved settings are never repaired by report',$GLOBALS['options']===$before);
reset_readiness();$GLOBALS['wordpress_version']=$attack;$html=render_readiness();
verify_readiness('render escapes an untrusted displayed metadata string',!str_contains($html,'<script>') && !str_contains($html,'<img src=x') && str_contains($html,'&lt;script&gt;'));
case_done_readiness('allowlisted output and HTML escaping without mutation');

reset_readiness();$GLOBALS['options'][XI_Config::OPTION]['enabled']=true;$GLOBALS['options']['active_plugins']=array();
$r=XI_Readiness::snapshot();
verify_readiness('complete local fixture can match all local checks',$r['local_counts']['blocked']===0 && $r['local_counts']['warning']===0 && $r['local_counts']['pass']===count($r['checks']));
verify_readiness('all local matches still do not grant external acceptance',$r['external_acceptance']==='not_checked' && count($r['external_checks'])>=7);
$html=render_readiness();
verify_readiness('external boundary rendered explicitly',str_contains($html,'外部验收：尚未由本页验证') && str_contains($html,'本地匹配不代表外部配置或业务验收通过'));
verify_readiness('MCP report does not register or query abilities',check_readiness($r,'mcp')['state']==='pass' && !class_exists('XI_MCP_Readonly',false));
verify_readiness('no runtime constants introduced across all cases',!defined('XI_HOST') && !defined('XI_PROFILE') && !defined('XI_API'));
verify_readiness('no hidden class autoload across all cases',$GLOBALS['autoloads']===array());
verify_readiness('no prohibited effects across all cases',$GLOBALS['forbidden_effects']===array());
case_done_readiness('all-local-pass remains external-not-checked with no delivery side effects');

echo json_encode(array('result'=>'PASS','php'=>PHP_VERSION,'suite'=>'readiness real classes with local metadata fixtures','cases'=>count($cases),'assertions'=>$assertions,'coverage'=>$cases,
 'network'=>false,'persistent_writes'=>false,'customer_rows'=>false,'delivery_runtime_loaded'=>false,'xi_host_defined'=>defined('XI_HOST')),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";

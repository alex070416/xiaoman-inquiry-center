<?php
// Sanitized legacy-interface fixtures. This is not a five-site production import.
if (PHP_SAPI!=='cli') exit(1);
$site=$argv[1]??'fangwei';
$profiles=array('fangwei'=>array('FW','fangwei-inquiry-center','fangwei-xiaoman-sync.php','FW-N','fwnxm_'),'huajing'=>array('NI','huajing-native-recovery','native-inquiry.php','HJ-N','ninxm_'),'anxing'=>array('NI','anxing-native-inquiry','native-inquiry.php','AX-N','ninxm_'),'rongfeng'=>array('NI','rongfeng-inquiry-center','rongfeng-inquiry-center.php','RF-N','ninxm_'),'tongqu'=>array('NI','tongqu-native-inquiry','native-inquiry.php','TQ-N','ninxm_'));
if(!isset($profiles[$site]))throw new RuntimeException('Unknown fixture');
list($prefix,$folder,$entry,$number,$queue)=$profiles[$site];
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);
define('XIAOMAN_CLIENT_ID','fixture-client');define('XIAOMAN_CLIENT_SECRET','fixture-secret');
if($prefix==='NI')define('NI_PROFILE',array('taxonomies'=>array('product-category')));
class WP_Error {function __construct(public $code,public $message){}}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_parse_args($v,$defaults){return array_merge($defaults,$v);}
function wp_salt($v){return 'public-test-fixture-salt';}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
$options=array('active_plugins'=>array($folder.'/'.$entry));
function get_option($key,$default=false){global $options;return $options[$key]??$default;}
function get_site_option($key,$default=false){return $default;}
function update_option($key,$value,$autoload=null){global $options;$options[$key]=$value;return true;}
function maybe_unserialize($value){return is_string($value)?unserialize($value):$value;}
function get_terms($args){return array((object)array('slug'=>'electric-forklift','name'=>'Electric Forklift')) ;}
function wp_remote_request(){throw new RuntimeException('Migration must not make HTTP requests');}
class XI_Fixture_DB {
 public $postmeta='wp_postmeta';public $posts='wp_posts';public $last_error='';
 function get_results($sql,$format){return array(array('meta_value'=>serialize(array(array('name'=>'form','settings'=>array('fields'=>array(array('name'=>'Equipment','options'=>"Electric Forklift\nself loading stacker\nOther\nCustom Option"))))))));}
}
$wpdb=new XI_Fixture_DB();
$equipment=$prefix==='FW'?'':'static function equipment($term){return $term->name;}';
$role=$prefix==='FW'?'':'const ROLE="'.$site.'_inquiry_viewer";';
eval('class '.$prefix.'_Native_Inquiry_Center { const META="'.strtolower($prefix).'_native_record";const CAP="bricks_form_submission_access";const PAGE="'.$site.'-inquiry-center";'.$role.'static function number($id){return "'.$number.'".$id;}static function text($v,$n){return substr($v,0,$n);}'.$equipment.'}');
eval('class '.$prefix.'_Native_Xiaoman_Sync {const JOB="'.$queue.'send_v1";const SWEEP="'.$queue.'recover_v1";const GROUP="'.$site.'-native-xiaoman";const CURSOR="'.$queue.'capture_cursor_v1";static function key($id){return "'.$queue.'job_v1_".$id;}}');
eval('class '.$prefix.'_Xiaoman_Queue_V1 {const API="https://api-sandbox.xiaoman.cn";const ORIGIN="12345";const ORIGIN_NAME="Fixture";const PRODUCT_FIELD="23456";const CUSTOMER_LEVEL_FIELD="34567";const CUSTOMER_LEVEL="无";static function product_mapping($raw){if($raw==="self loading stacker")return array("lead_name"=>"自提车","product_name"=>"堆高车");if($raw==="Electric Forklift")return array("lead_name"=>"电动叉车","product_name"=>"电动叉车");return array("lead_name"=>"其他","product_name"=>"其他");}}');
class NIFR_Conditional_Required {static function options(){return array('post_type'=>'products','field_name'=>'Message','campaign_codes'=>array('pm'),'categories'=>array());}}
require dirname(__DIR__).'/xiaoman-inquiry-center/configuration.php';
require dirname(__DIR__).'/xiaoman-inquiry-center/compatibility.php';
$n=0;function verify_migration($name,$value){global $n;if(!$value)throw new RuntimeException($name);$n++;}
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
verify_migration('category_labels',$config['equipment_labels']['electric-forklift']==='Electric Forklift');
verify_migration('product_mapping',$config['products']['electric forklift']==='电动叉车');
verify_migration('other_not_a_taxonomy',$config['products']['other']==='其他');
verify_migration('quick_option_not_a_taxonomy',$config['products']['self loading stacker']==='堆高车');
verify_migration('distinct_lead_name',$config['product_lead_names']['self loading stacker']==='自提车');
verify_migration('level_field',$config['level_field']==='34567'&&$config['level_value']==='无');
verify_migration('required_preserved',get_option('xi_required_v1')===NIFR_Conditional_Required::options());
verify_migration('credentials_encrypted',isset($config['secrets']['id'],$config['secrets']['secret'])&&strpos($config['secrets']['secret'],'fixture-secret')===false);
update_option(XI_Config::OPTION,$config);
verify_migration('credentials_readable',XI_Config::credential('id')==='fixture-client'&&XI_Config::credential('secret')==='fixture-secret');
define('XI_API',$config['api']);require dirname(__DIR__).'/xiaoman-inquiry-center/provider.php';
verify_migration('provider_separate_lead_name',XI_Xiaoman_Queue_V1::product_mapping('self loading stacker')===array('lead_name'=>'自提车','product_name'=>'堆高车'));
echo json_encode(array('php'=>PHP_VERSION,'fixture'=>$site,'passed'=>$n))."\n";

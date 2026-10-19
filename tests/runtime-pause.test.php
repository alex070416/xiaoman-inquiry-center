<?php
// Offline behavioral checks using synthetic records and in-memory WP/AS substitutes.
// This file performs no network or database connections and is not part of the plugin.
define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');
define('XI_QUEUE_PREFIX','htxi_');define('XI_QUEUE_CURSOR','htxi_capture_cursor_v1');
define('XI_QUEUE_CPT','htxi_job');define('XI_RECORD_META','htxi_native_record');
define('XI_VIEW_CAP','xiaoman_inquiry_view');define('XI_VIEW_ROLE','xiaoman_inquiry_viewer');
define('XI_PROFILE',array('crm'=>false,'number_prefix'=>'HT-N'));define('XI_HOST','offline.invalid');
$base=dirname(__DIR__).'/xiaoman-inquiry-center';
$target=$argv[1]??($base.'/native-xiaoman-sync.php');$enabled_only=($argv[2]??'')==='enabled-only';
$checks=array();$options=array();$saved=array();$posts=array();$metas=array();$events=array();$actions=array();$config=array();$http_mode='success';
function event($type,$value=null){$GLOBALS['events'][]=array($type,$value);}
function add_action($hook,$callback,$priority=10,$args=1){$GLOBALS['actions'][$hook][]=$callback;}
function has_action($hook){return !empty($GLOBALS['actions'][$hook]);}
function do_action($hook,...$args){foreach($GLOBALS['actions'][$hook]??array() as $callback)call_user_func_array($callback,$args);}
function do_action_ref_array($hook,$args){do_action($hook,...$args);}
function add_filter(...$args){}function register_post_type(...$args){event('register_post_type');}
function current_user_can($cap){return true;}function get_current_user_id(){return 1;}
function absint($v){return abs((int)$v);}function wp_json_encode($v){return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
function get_option($key,$default=false){return array_key_exists($key,$GLOBALS['options'])?$GLOBALS['options'][$key]:$default;}
function add_option($key,$value,...$rest){if(array_key_exists($key,$GLOBALS['options']))return false;$GLOBALS['options'][$key]=$value;event('option_add',$key);return true;}
function update_option($key,$value,...$rest){$GLOBALS['options'][$key]=$value;event('option_update',$key);return true;}
function delete_option($key){$exists=array_key_exists($key,$GLOBALS['options']);unset($GLOBALS['options'][$key]);event('option_delete',$key);return $exists;}
function wp_insert_post($data,$error=false){$id=900+count($GLOBALS['posts']);$GLOBALS['posts'][$id]=(object)array('ID'=>$id);$GLOBALS['metas'][$id]=$data['meta_input'];event('index_insert',$id);return $id;}
function update_post_meta($id,$key,$value){$GLOBALS['metas'][$id][$key]=$value;event('meta_update',$key);}
function get_post_meta($id,$key,$single=true){return $GLOBALS['metas'][$id][$key]??'';}
function get_posts($args){event('index_read');$out=array();foreach($GLOBALS['posts'] as $id=>$post)if(in_array($GLOBALS['metas'][$id]['_htxi_status']??'',array('queued','retry','authorizing','sending'),true))$out[]=$post;return array_slice($out,0,50);}
function as_enqueue_async_action($hook,$args,$group,$unique){event('as_async',array($hook,$args,$group));return 1;}
function as_schedule_single_action($at,$hook,$args,$group,$unique){event('as_single',array($hook,$args,$group,$at));return 1;}
function as_schedule_recurring_action($at,$interval,$hook,$args,$group,$unique){event('as_recurring',array($hook,$interval));return 1;}
function as_has_scheduled_action(...$args){return false;}function as_unschedule_all_actions(...$args){event('as_cancel');}
function delete_transient($key){event('transient_delete',$key);}function wp_cache_delete(...$args){event('cache_delete');}
function wp_remote_post($url,$args){event(str_contains($url,'oauth2')?'oauth':'http_push');if(str_contains($url,'oauth2'))return array('http'=>200,'body'=>'{"access_token":"OFFLINE_TOKEN"}');if($GLOBALS['http_mode']==='network')return new WP_Error('network','offline synthetic network failure');$status=$GLOBALS['http_mode']==='success'?200:(int)$GLOBALS['http_mode'];return array('http'=>$status,'body'=>wp_json_encode(array('code'=>$status,'data'=>$status===200?array('lead_id'=>'123456'):array())));}
function wp_remote_retrieve_response_code($r){return $r['http'];}function wp_remote_retrieve_body($r){return $r['body'];}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function esc_attr($v){return esc_html($v);}function esc_url($v){return $v;}
function admin_url($v=''){return 'https://offline.invalid/wp-admin/'.$v;}function add_query_arg($a,$u){return $u.'?'.http_build_query($a);}
function wp_nonce_field(...$args){}function check_admin_referer(...$args){}function wp_unslash($v){return $v;}function sanitize_text_field($v){return trim($v);}
function wp_date($format,$stamp){return gmdate($format,$stamp);}
class WP_Error {function __construct(public $code,public $message){}function get_error_message(){return $this->message;}}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Query {public $posts=array();public $found_posts=0;public $max_num_pages=0;function __construct($args){}}
class XI_Config {static function get(){return array_merge(array('crm'=>true),$GLOBALS['config']);}static function credential($name){return 'OFFLINE';}}
class XI_Native_Inquiry_Trash {static function has($id){return false;}}
class XI_Request_Attribution_V1 {static function repair_legacy($hd){return $hd;}}
class XI_Inquiry_Source_V1 {static function label($hd){return 'offline';}}
class XI_Xiaoman_Queue_V1 {
 const API='https://offline.invalid';const TOKEN='offline-token';
 static function text($v,$limit=1000){return substr((string)$v,0,$limit);}
 static function token(){event('token_entry');$r=wp_remote_post(self::API.'/v1/oauth2/access_token',array());return json_decode($r['body'],true)['access_token'];}
 static function payload($submission,$stamp){event('payload_build');return array('name'=>'OFFLINE SYNTHETIC','customers'=>array());}
 static function error_hint(...$args){return 'offline response';}
}
class Offline_DB {
 public $insert_id=27;public $prefix='offline_';public $options='offline_options';
 function esc_like($s){return addcslashes($s,'_%\\');}
 function prepare($query,...$args){$i=0;return preg_replace_callback('/%[sd]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:"'".addslashes((string)$v)."'";},$query);}
 function get_var($query){event('db_read');if(str_starts_with($query,'SHOW TABLES'))return 'offline_bricks_form_submissions';if(str_contains($query,'MAX(id)'))return $GLOBALS['saved']?max(array_keys($GLOBALS['saved'])):0;if(str_starts_with($query,'SELECT id'))return $GLOBALS['lookup_id']??0;throw new Exception('Unexpected synthetic query');}
 function get_row($query,$format){event('db_read');preg_match('/WHERE id=(\d+)/',$query,$m);return $GLOBALS['saved'][(int)($m[1]??0)]??null;}
 function get_results($query,$format){event('db_read');preg_match('/WHERE id>(\d+)/',$query,$m);$cursor=(int)($m[1]??0);$rows=array_filter($GLOBALS['saved'],static fn($r)=>$r['id']>$cursor);ksort($rows);return array_slice(array_values($rows),0,100);}
 function delete($table,$where,...$args){unset($GLOBALS['options'][$where['option_name']]);event('db_delete');return 1;}
}
$wpdb=new Offline_DB();
require $base.'/native-inquiry-center.php';require $base.'/bricks.php';require $target;
// Exercise the real bundled scheduler action and completion path with only its store replaced.
require $base.'/vendor/action-scheduler/classes/schedules/ActionScheduler_Schedule.php';
require $base.'/vendor/action-scheduler/classes/actions/ActionScheduler_Action.php';
require $base.'/vendor/action-scheduler/deprecated/ActionScheduler_Abstract_QueueRunner_Deprecated.php';
class ActionScheduler_Store {const STATUS_PENDING='pending';const STATUS_FAILED='failed';}
require $base.'/vendor/action-scheduler/classes/abstracts/ActionScheduler_Abstract_QueueRunner.php';
class Offline_AS_Store extends ActionScheduler_Store {
 public $status='pending';public $action;
 function __construct($id){$schedule=new class implements ActionScheduler_Schedule {function next(?DateTime $after=null){return null;}function is_recurring(){return false;}};$this->action=new ActionScheduler_Action(XI_Native_Xiaoman_Sync::JOB,array($id),$schedule,XI_Native_Xiaoman_Sync::GROUP);}
 function get_status($id){return $this->status;}function fetch_action($id){return $this->action;}function log_execution($id){}function mark_complete($id){$this->status='complete';}function mark_failure($id){$this->status='failed';}
}
class Offline_AS_Runner extends ActionScheduler_Abstract_QueueRunner {function __construct($store){$this->store=$store;}function run($context=''){$this->process_action(1,$context);}}
XI_Native_Xiaoman_Sync::boot();
function reset_case($crm=true){$GLOBALS['options']=array();$GLOBALS['saved']=array();$GLOBALS['posts']=array();$GLOBALS['metas']=array();$GLOBALS['events']=array();$GLOBALS['config']=array('crm'=>$crm);$GLOBALS['http_mode']='success';$GLOBALS['lookup_id']=0;$GLOBALS['wpdb']->insert_id=27;$_POST=array();$_SERVER['REQUEST_METHOD']='GET';XI_Native_Inquiry_Center::$requests=array();}
function check($ok,$label){if(!$ok)throw new Exception($label);$GLOBALS['checks'][]=$label;}
function count_event($type){return count(array_filter($GLOBALS['events'],static fn($e)=>$e[0]===$type));}
function fixture($id,$requested=true){$r=array('schema'=>1,'uuid'=>'offline-uuid','kind'=>'quick','form_id'=>'offline-form','post_id'=>1,'submitted_at'=>'2026-10-15 00:00:00','sync_requested'=>$requested,'values'=>array(),'hd'=>array(),'source'=>'offline','attribution'=>array());$GLOBALS['saved'][$id]=array('id'=>$id,'form_id'=>'offline-form','post_id'=>1,'form_data'=>wp_json_encode(array(XI_RECORD_META=>array('value'=>wp_json_encode($r)))));return $r;}
function job($id,$status='queued',$attempts=0,$index=true){fixture($id);$row=array('submission_id'=>$id,'form_id'=>'offline-form','created_at'=>'2026-10-15 00:00:00','updated_at'=>gmdate('Y-m-d H:i:s'),'status'=>$status,'attempts'=>$attempts,'next_at'=>0,'payload'=>'{"name":"OFFLINE SYNTHETIC","customers":[]}','lead_id'=>'','lead_name'=>'OFFLINE SYNTHETIC','detail'=>'offline fixture','index_id'=>0);$GLOBALS['options'][XI_Native_Xiaoman_Sync::key($id)]=$row;if($index)XI_Native_Xiaoman_Sync::index($id,$row);$GLOBALS['events']=array();return XI_Native_Xiaoman_Sync::row($id);}
function assert_no_network($label){check(count_event('token_entry')===0&&count_event('oauth')===0&&count_event('http_push')===0,$label);}
function same_state(){return serialize(array($GLOBALS['options'],$GLOBALS['posts'],$GLOBALS['metas'],$GLOBALS['wpdb']->insert_id));}

try {
 if(!$enabled_only){
  reset_case(false);foreach(array(2,7,11,16,23,30) as $id)fixture($id);XI_Native_Xiaoman_Sync::setup();
  check(get_option(XI_QUEUE_CURSOR)===30,'paused setup initializes cursor to actual max ID rather than six-row count');
  check(count_event('as_recurring')===1&&count_event('as_async')===0,'paused setup preserves recovery heartbeat without a send job');assert_no_network('paused setup sends no OAuth or HTTP');
  reset_case(false);fixture(40);$before=same_state();check(XI_Native_Xiaoman_Sync::capture(40)===false,'paused capture reports not captured');check(same_state()===$before&&count($GLOBALS['events'])===0,'paused capture creates no option/index/schedule or DB reads');
  reset_case(false);$original=job(40);$GLOBALS['options'][XI_QUEUE_CURSOR]=30;$before=same_state();XI_Native_Xiaoman_Sync::recover();check(same_state()===$before&&count($GLOBALS['events'])===0,'paused recover does not scan, advance cursor, modify tasks or schedule');
  $as_store=new Offline_AS_Store(40);$as_runner=new Offline_AS_Runner($as_store);$as_runner->process_action(1,'offline');
  check($as_store->status==='complete','real bundled AS runner completes a paused send callback normally');
  check(same_state()===$before&&count($GLOBALS['events'])===0,'paused existing queued send leaves attempts, status, lead, lock and payload unchanged');assert_no_network('paused existing queued send makes no token, OAuth or push call');
  $GLOBALS['config']['crm']=true;XI_Native_Xiaoman_Sync::recover();check(count_event('as_async')===1,'reenabled recovery reschedules indexed queued row after paused AS callback completed');
  XI_Native_Xiaoman_Sync::send(40);XI_Native_Xiaoman_Sync::send(40);check(count_event('oauth')===1&&count_event('http_push')===1&&XI_Native_Xiaoman_Sync::row(40)['status']==='success','same durable queued row sends once after reenable');
  reset_case(false);job(41,'retry',2);$before=same_state();XI_Native_Xiaoman_Sync::send(41);check(same_state()===$before&&count($GLOBALS['events'])===0,'paused retry row also remains untouched');
  reset_case(false);job(47,'uncertain',1);$GLOBALS['options'][XI_QUEUE_CURSOR]=47;$before=same_state();XI_Native_Xiaoman_Sync::send(47);check(same_state()===$before,'paused uncertain row remains untouched');$GLOBALS['config']['crm']=true;XI_Native_Xiaoman_Sync::recover();XI_Native_Xiaoman_Sync::send(47);check(XI_Native_Xiaoman_Sync::row(47)['status']==='uncertain'&&count_event('as_async')===0,'uncertain remains paused after CRM is reenabled');assert_no_network('uncertain pause and reenable never call OAuth or HTTP');
  reset_case(false);fixture(42,false);$before=same_state();check(XI_Native_Xiaoman_Sync::authorize_history(42)===false,'paused historical authorization refuses capture');check(same_state()===$before&&count($GLOBALS['events'])===0,'paused historical authorization leaves no manual option');
  reset_case(false);job(43);$before=same_state();XI_Native_Xiaoman_Sync::enqueue(43);XI_Native_Xiaoman_Sync::enqueue(43,60);XI_Native_Xiaoman_Sync::retry(43,1,'offline');XI_Native_Xiaoman_Sync::retry(43,4,'offline');check(same_state()===$before&&count($GLOBALS['events'])===0,'paused direct enqueue and retry have no side effects');
  reset_case(false);job(44,'uncertain',1);$before=same_state();check(is_wp_error(XI_Native_Xiaoman_Sync::resolve(44,'','offline review')),'paused manual resolve returns compatible error');check(same_state()===$before&&count($GLOBALS['events'])===0,'paused manual resolve changes no state or lock');
  reset_case(false);job(45,'rejected');$before=same_state();$_SERVER['REQUEST_METHOD']='POST';$_POST=array('retry_id'=>45);ob_start();XI_Native_Xiaoman_Sync::page();$html=ob_get_clean();check(str_contains($html,'小满同步已暂停'),'paused sync page explains its refused POST');check(same_state()===$before&&count($GLOBALS['events'])===0,'paused sync page cannot reset rejected task to queued');
  reset_case(false);$r=fixture(46);$GLOBALS['lookup_id']=46;
  $form=new class {public $result;function get_id(){return 'offline-form';}function get_post_id(){return 1;}function set_result($r){$this->result=$r;}};
  XI_Native_Inquiry_Center::$requests['offline-form:1']=$r;XI_Bricks::action($form);
  check($form->result['type']==='success','paused capture false does not turn saved Bricks action into a save failure');
  $response=XI_Native_Inquiry_Center::response(array('type'=>'success'),$form);check($response['type']==='success'&&$response['inquiryNumber']==='HT-N46','paused saved response retains success and valid inquiry number');check(!XI_Native_Xiaoman_Sync::row(46)&&count_event('as_async')===0,'paused form response creates no queue task');assert_no_network('paused form action and response send no OAuth or HTTP');
 }
 // These cases run unchanged against both the fixed base and the candidate.
 reset_case(true);fixture(50);check(XI_Native_Xiaoman_Sync::capture(50)&&XI_Native_Xiaoman_Sync::capture(50),'enabled capture remains idempotent');check($GLOBALS['wpdb']->insert_id===27&&count_event('as_async')===1,'enabled capture preserves insert ID and creates one scheduled action');
 XI_Native_Xiaoman_Sync::send(50);XI_Native_Xiaoman_Sync::send(50);$row=XI_Native_Xiaoman_Sync::row(50);check($row['status']==='success'&&$row['lead_id']==='123456'&&$row['payload']===''&&count_event('http_push')===1,'enabled success clears payload, retains lead ID and prevents duplicate creation');
 reset_case(true);job(51);$GLOBALS['options']['htxi_lock_v1_51']=time();$before=same_state();XI_Native_Xiaoman_Sync::send(51);check(same_state()===$before&&count_event('http_push')===0&&count_event('oauth')===0,'enabled atomic lock still prevents concurrent send');
 reset_case(true);job(52,'uncertain',1);$GLOBALS['options'][XI_QUEUE_CURSOR]=52;XI_Native_Xiaoman_Sync::recover();XI_Native_Xiaoman_Sync::send(52);check(XI_Native_Xiaoman_Sync::row(52)['status']==='uncertain'&&count_event('as_async')===0,'enabled uncertain task is not rescheduled');assert_no_network('enabled uncertain task is never automatically replayed');
 foreach(array('network'=>'uncertain','401'=>'retry','429'=>'retry','422'=>'rejected','500'=>'uncertain') as $mode=>$expected){reset_case(true);job(53);$GLOBALS['http_mode']=(string)$mode;$start=time();XI_Native_Xiaoman_Sync::send(53);$row=XI_Native_Xiaoman_Sync::row(53);check($row['status']===$expected&&$row['attempts']===1,'enabled '.$mode.' retains original outcome and attempt behavior');$sent=count_event('http_push');XI_Native_Xiaoman_Sync::send(53);check(count_event('http_push')===$sent,'enabled '.$mode.' cannot immediately duplicate a request');if($expected==='retry')check($row['next_at']>=$start+60&&count_event('as_single')===1,'enabled '.$mode.' preserves first retry delay');}
 reset_case(true);job(54);foreach(array(1=>60,2=>300,3=>1800) as $attempt=>$delay){$start=time();XI_Native_Xiaoman_Sync::retry(54,$attempt,'offline');$row=XI_Native_Xiaoman_Sync::row(54);check($row['status']==='retry'&&$row['next_at']>=$start+$delay,'enabled retry delay '.$attempt.' preserved');}XI_Native_Xiaoman_Sync::retry(54,4,'offline');check(XI_Native_Xiaoman_Sync::row(54)['status']==='failed'&&count_event('as_single')===3,'enabled retry cap still fails at four without another schedule');
 reset_case(true);job(55,'sending');$row=XI_Native_Xiaoman_Sync::row(55);$row['updated_at']=gmdate('Y-m-d H:i:s',time()-700);$GLOBALS['options'][XI_Native_Xiaoman_Sync::key(55)]=$row;$GLOBALS['options'][XI_QUEUE_CURSOR]=55;XI_Native_Xiaoman_Sync::recover();check(XI_Native_Xiaoman_Sync::row(55)['status']==='uncertain'&&count_event('as_async')===0,'enabled stale sending task becomes uncertain without replay');
 reset_case(true);fixture(56,false);check(XI_Native_Xiaoman_Sync::authorize_history(56)&&get_option('htxi_manual_v1_56')&&XI_Native_Xiaoman_Sync::row(56)['status']==='queued','enabled explicit historical authorization unchanged');
 reset_case(true);job(57,'uncertain',1);check(XI_Native_Xiaoman_Sync::resolve(57,'','offline reviewed absence')===true&&XI_Native_Xiaoman_Sync::row(57)['status']==='queued'&&count_event('as_async')===1,'enabled reviewed absence still explicitly requeues uncertain task');
 reset_case(true);job(58,'uncertain',1);check(XI_Native_Xiaoman_Sync::resolve(58,'123456','offline reviewed success')===true&&XI_Native_Xiaoman_Sync::row(58)['status']==='success'&&count_event('as_async')===0,'enabled reviewed lead still resolves uncertain task to success');
 reset_case(true);unset($GLOBALS['config']['crm']);job(59);XI_Native_Xiaoman_Sync::send(59);check(XI_Native_Xiaoman_Sync::row(59)['status']==='success','existing default crm=true behavior unchanged');
 echo wp_json_encode(array('passed'=>true,'mode'=>$enabled_only?'enabled-only':'all','checks'=>count($checks),'results'=>$checks,'network'=>'in-memory substitutes only; no sockets or external processes','records'=>'synthetic local fixtures only')).PHP_EOL;
} catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage().PHP_EOL);exit(1);}

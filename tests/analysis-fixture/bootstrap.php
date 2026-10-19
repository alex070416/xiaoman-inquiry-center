<?php
/** Deterministic, local-only WP surface; write, HTTP, cron and CRM actions fail loudly. */
define('ABSPATH', __DIR__.'/wordpress/');
define('ARRAY_A', 'ARRAY_A');
define('XI_HOST', 'fixture.example.invalid');
define('XI_RECORD_META', '_xi_native_v1');
define('XI_QUEUE_PREFIX', 'xi_fixture_');
define('XI_PROFILE', array('number_prefix'=>'FW-N','site_label'=>'Fangwei Fixture'));
$fixture=array('caps'=>array(), 'options'=>array('home'=>'https://'.XI_HOST), 'abilities'=>array(), 'hooks'=>array());
class WP_Error {
 private $code; private $message; private $data;
 function __construct($code='', $message='', $data=null){$this->code=$code;$this->message=$message;$this->data=$data;}
 function get_error_code(){return $this->code;}
 function get_error_message(){return $this->message;}
 function get_error_data(){return $this->data;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function current_user_can($cap){global $fixture;return !empty($fixture['caps'][$cap]);}
function is_user_logged_in(){global $fixture;return !empty($fixture['caps']);}
function get_current_user_id(){return is_user_logged_in()?15:0;}
function get_option($key,$default=false){global $fixture;return $fixture['options'][$key]??$default;}
function get_bloginfo($key=''){return $key==='name'?'Fangwei Fixture':'';}
function home_url($path=''){return 'https://'.XI_HOST.$path;}
function wp_parse_url($url,$part=-1){return parse_url($url,$part);}
function wp_timezone_string(){return 'Asia/Shanghai';}
function wp_timezone(){return new DateTimeZone('Asia/Shanghai');}
function wp_date($format,$stamp=null,$zone=null){$d=new DateTimeImmutable('@'.($stamp??time()));return $d->setTimezone($zone??wp_timezone())->format($format);}
function wp_salt($scheme='auth'){return 'offline-fixture-never-production-'.$scheme;}
function wp_json_encode($v,$flags=0,$depth=512){return json_encode($v,$flags,$depth);}
function wp_parse_args($v,$defaults=array()){return array_merge($defaults,(array)$v);}
function absint($v){return abs((int)$v);}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function sanitize_email($v){return filter_var((string)$v,FILTER_SANITIZE_EMAIL);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function wp_strip_all_tags($v,$remove_breaks=false){$v=strip_tags((string)$v);return $remove_breaks?preg_replace('/[\r\n\t ]+/',' ',$v):$v;}
function wp_unslash($v){return is_string($v)?stripslashes($v):$v;}
function __($v,$domain=''){return $v;}
function add_action($hook,$fn,$priority=10,$accepted_args=1){global $fixture;$fixture['hooks'][$hook][]=array($fn,$priority,$accepted_args);}
function add_filter($hook,$fn,$priority=10,$accepted_args=1){add_action($hook,$fn,$priority,$accepted_args);}
function apply_filters($hook,$value,...$args){return $value;}
function wp_register_ability($name,$args){global $fixture;$fixture['abilities'][$name]=$args;return true;}
function wp_register_ability_category($name,$args){global $fixture;$fixture['categories'][$name]=$args;return true;}
function write_forbidden($action){throw new RuntimeException('READONLY VIOLATION: '.$action);}
function add_option(...$args){write_forbidden('add_option');}
function update_option(...$args){write_forbidden('update_option');}
function delete_option(...$args){write_forbidden('delete_option');}
function set_transient(...$args){write_forbidden('set_transient');}
function delete_transient(...$args){write_forbidden('delete_transient');}
function wp_remote_get(...$args){write_forbidden('wp_remote_get');}
function wp_remote_post(...$args){write_forbidden('wp_remote_post');}
function wp_remote_request(...$args){write_forbidden('wp_remote_request');}
function wp_schedule_event(...$args){write_forbidden('wp_schedule_event');}
function wp_schedule_single_event(...$args){write_forbidden('wp_schedule_single_event');}
function as_enqueue_async_action(...$args){write_forbidden('as_enqueue_async_action');}
function as_schedule_single_action(...$args){write_forbidden('as_schedule_single_action');}
function get_role(...$args){write_forbidden('get_role / role mutation');}
function add_role(...$args){write_forbidden('add_role');}
function do_action(...$args){write_forbidden('do_action');}
final class XI_Native_Inquiry_Center {
 const META=XI_RECORD_META;
 static function table(){global $wpdb;return $wpdb->prefix.'bricks_form_submissions';}
 static function base_where(){global $wpdb;return $wpdb->prepare('form_data LIKE %s','%'.$wpdb->esc_like('"'.XI_RECORD_META.'"').'%');}
 static function record($row){$outer=json_decode($row['form_data']??'',true);$inner=$outer[XI_RECORD_META]['value']??null;return is_string($inner)?(json_decode($inner,true)?:array()):array();}
 static function number($id){return XI_PROFILE['number_prefix'].(int)$id;}
}
final class XI_Native_Inquiry_Trash {const PREFIX='fwn_trash_v1_';}
final class XI_Conversions {static function table(){global $wpdb;return $wpdb->prefix.'xi_conversion_receipts';}}
function fixture_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function fixture_case($name,$fn){global $fixture_passed;$fn();$fixture_passed[]=$name;echo 'PASS '.$name.PHP_EOL;}
function fixture_error($value,$message){fixture_check(is_wp_error($value),$message);}
function fixture_no_error($value,$context){fixture_check(!is_wp_error($value),$context.': '.(is_wp_error($value)?$value->get_error_message():''));return $value;}

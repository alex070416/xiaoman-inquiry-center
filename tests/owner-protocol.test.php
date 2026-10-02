<?php
// In-memory SQLite fixture only. Does not load WordPress, CRM, or any site secrets.
define('ABSPATH',__DIR__.'/wp-interface-fixture/');define('ARRAY_A','ARRAY_A');define('XI_HOST','fixture.example.invalid');
class WP_Error { function __construct(public $code,public $message){} }
function wp_parse_args($v,$defaults){return array_merge($defaults,$v);}
function get_option($key,$default=null){return $key==='xi_conversion_config_v1'?$GLOBALS['cfg']:($GLOBALS['options'][$key]??$default);}
function update_option($key,$value,$autoload=null){$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['options'][$key]);return true;}
function absint($v){return abs((int)$v);}
function post_type_exists($type){return $type==='rcb-cookie';}
function get_posts($args){return array((object)array('ID'=>1,'post_status'=>'publish'));}
function get_post_meta($id,$key,$single){return array('analytics_storage','ad_storage','ad_user_data');}
class XI_Native_Inquiry_Trash {static function has($id){return false;}}
class XI_Native_Inquiry_Center {
 static function number($id){return 'FW-N'.$id;}
 static function thank_you_url($record){return 'https://fixture.example.invalid/thank-you/';}
 static function row($id){return in_array((int)$id,array(7,8,9),true)?array('id'=>(int)$id):null;}
 static function record($row){return array('uuid'=>'fixture-request-uuid','values'=>array());}
}
class FixtureWpdb {
 public $prefix='fixture_';public PDO $db;public $before_query=null;public $external_lock=false;public $lock_held=false;
 function __construct(){$this->db=new PDO('sqlite::memory:');$this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}
 function prepare($sql,...$args){$i=0;return preg_replace_callback('/%[sd]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:$this->db->quote((string)$v);},$sql);}
 function query($sql){if(is_callable($this->before_query)){$hook=$this->before_query;$this->before_query=null;$hook($this,$sql);}return $this->db->exec($sql);}
 function get_row($sql,$mode){$r=$this->db->query($sql)->fetch(PDO::FETCH_ASSOC);return $r?:null;}
 function get_col($sql){if(str_starts_with($sql,'SHOW COLUMNS FROM '))return array_column($this->db->query('PRAGMA table_info('.substr($sql,18).')')->fetchAll(PDO::FETCH_ASSOC),'name');throw new RuntimeException('Unexpected SQL: '.$sql);}
 function get_var($sql){if(str_starts_with($sql,'SELECT GET_LOCK(')){if($this->external_lock||$this->lock_held)return '0';$this->lock_held=true;return '1';}if(str_starts_with($sql,'SELECT RELEASE_LOCK(')){$this->lock_held=false;return '1';}if(str_starts_with($sql,'SHOW TABLES LIKE ')){$table=trim(substr($sql,17),"'");return $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name=".$this->db->quote($table))->fetchColumn()?:null;}return $this->db->query($sql)->fetchColumn();}
 function get_charset_collate(){return '';}
 function esc_like($v){return $v;}
}
require dirname(__DIR__).'/xiaoman-inquiry-center/conversions.php';
$cfg=array_merge(XI_Conversions::defaults(),array('enabled'=>true,'site_host'=>XI_HOST,'cutover_id'=>0,'ga4_id'=>'G-FIXTURE','ads_id'=>'AW-123','ads_label'=>'FORM','analytics_service'=>'fixture-analytics','ads_service'=>'fixture-ads','user_data_service'=>'fixture-ec'));
$wpdb=new FixtureWpdb();$wpdb->query('CREATE TABLE fixture_xi_conversion_receipts(id INTEGER PRIMARY KEY,submission_id INTEGER,token_hash TEXT,request_uuid TEXT,form_kind TEXT,expires_utc TEXT,is_test INTEGER,consent_at_submit TEXT,ga4_state TEXT,ads_state TEXT,ga4_claimed_utc TEXT,ads_claimed_utc TEXT,ga4_callback_utc TEXT,ads_callback_utc TEXT,ga4_claim_owner TEXT,ads_claim_owner TEXT)');
$token=str_repeat('a',64);$number='FW-N7';$consent=array('analytics_storage'=>'granted','ad_storage'=>'granted','ad_user_data'=>'granted');
$wpdb->query($wpdb->prepare("INSERT INTO fixture_xi_conversion_receipts(id,submission_id,token_hash,request_uuid,form_kind,expires_utc,is_test,consent_at_submit,ga4_state,ads_state) VALUES(1,7,%s,%s,'quick',%s,1,'{}','pending','pending')",hash('sha256',$token),'fixture-request-uuid',gmdate('Y-m-d H:i:s',time()+1800)));
$checks=array();function check($name,$ok){if(!$ok)throw new RuntimeException('FAILED: '.$name);$GLOBALS['checks'][]=$name;}
if(defined('XI_FIXTURE_BOOTSTRAP_ONLY'))return;
function owned_claim($token,$number,$channel,$consent){return XI_Conversions::claim($token,$number,$channel,$consent,XI_Conversions::PROTOCOL);}
$first=owned_claim($token,$number,'ga4',$consent);$owner=$first['claim_owner'];
check('claim_assigns_32_hex_owner',preg_match('/^[a-f0-9]{32}$/D',$owner)===1&&$first['dispatch']===true);
check('duplicate_claim_does_not_reveal_owner',owned_claim($token,$number,'ga4',$consent)===array('dispatch'=>false,'reason'=>'already_claimed'));
check('wrong_owner_cannot_release',XI_Conversions::release_unsent($token,$number,'ga4',str_repeat('f',32))===false);
check('known_unused_owner_releases',XI_Conversions::release_unsent($token,$number,'ga4',$owner)===true);
check('lost_release_response_idempotent',XI_Conversions::release_unsent($token,$number,'ga4',$owner)===true);
$next=owned_claim($token,$number,'ga4',$consent);$nextOwner=$next['claim_owner'];
check('safe_reclaim_assigns_new_owner',$next['dispatch']===true&&$nextOwner!==$owner);
check('stale_owner_cannot_release_new_claim',XI_Conversions::release_unsent($token,$number,'ga4',$owner)===false);
check('wrong_owner_cannot_ack',XI_Conversions::acknowledge($token,$number,'ga4',$owner)===false);
check('current_owner_can_ack',XI_Conversions::acknowledge($token,$number,'ga4',$nextOwner)===true);
check('current_callback_ack_is_idempotent',XI_Conversions::acknowledge($token,$number,'ga4',$nextOwner)===true);
check('callback_cannot_release',XI_Conversions::release_unsent($token,$number,'ga4',$nextOwner)===false);
$wpdb->query($wpdb->prepare("UPDATE fixture_xi_conversion_receipts SET ga4_state='claimed',ga4_claim_owner=%s WHERE id=1",$nextOwner));
$wpdb->before_query=static function($db,$sql){$db->db->exec("UPDATE fixture_xi_conversion_receipts SET ga4_claim_owner='".str_repeat('e',32)."' WHERE id=1");};
check('owner_switch_between_select_and_ack_is_blocked',XI_Conversions::acknowledge($token,$number,'ga4',$nextOwner)===false);
check('later_claim_remains_claimed_after_stale_ack',$wpdb->get_var("SELECT ga4_state FROM fixture_xi_conversion_receipts WHERE id=1")==='claimed'&&$wpdb->get_var("SELECT ga4_claim_owner FROM fixture_xi_conversion_receipts WHERE id=1")===str_repeat('e',32));
$ad=owned_claim($token,$number,'ads',$consent);
check('ads_owner_independent',$ad['dispatch']===true&&$ad['claim_owner']!==$nextOwner);
$wpdb->query("UPDATE fixture_xi_conversion_receipts SET ads_claim_owner=NULL WHERE id=1");
check('historic_unknown_claim_no_blind_replay',XI_Conversions::release_unsent($token,$number,'ads',$ad['claim_owner'])===false&&!owned_claim($token,$number,'ads',$consent)['dispatch']);
$wpdb->query($wpdb->prepare('UPDATE fixture_xi_conversion_receipts SET expires_utc=%s WHERE id=1',gmdate('Y-m-d H:i:s',time()-1)));
check('expired_receipt_cannot_release',XI_Conversions::release_unsent($token,$number,'ga4',$nextOwner)===false);
echo json_encode(array('suite'=>'owned-claim-release-sqlite-fixture','passed'=>count($checks),'checks'=>$checks),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;

<?php
// Offline server-validation regression. No WordPress bootstrap, clients, storage or HTTP.
if (PHP_SAPI !== 'cli') exit(1);
define('ABSPATH', __DIR__.'/offline-only/');
define('XI_VERSION', 'fixture');
define('XI_RECORD_META', 'ni_native_record');
define('XI_VIEW_CAP', 'fixture_view');
define('XI_VIEW_ROLE', 'fixture_viewer');
define('DAY_IN_SECONDS', 86400);
function wp_parse_args($value,$defaults){return array_merge($defaults,$value);}
function get_option($key,$default=false){if($key!=='xi_required_v1')throw new RuntimeException('Unexpected option read');return $GLOBALS['required_options'];}
function sanitize_text_field($value){return trim(strip_tags((string)$value));}
function wp_unslash($value){return is_string($value)?stripslashes($value):$value;}
function wp_parse_url($value,$part=-1){return parse_url($value,$part);}
function home_url($path=''){return 'https://fixture.example.invalid'.$path;}
function wp_salt($scheme){return 'public-required-fixture-salt';}
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);}
function absint($value){return abs((int)$value);}
function is_tax(){return false;}
function is_singular($type){return false;}
function get_post_type($id){return $id===101?'product':'page';}
function is_wp_error($value){return false;}
function get_term($id){return null;}
function wp_get_post_terms($id,$taxonomy){return array();}
function add_action(...$args){}
function add_filter(...$args){}
function plugin_basename($path){return basename($path);}
function update_option(...$args){throw new RuntimeException('Option writes are forbidden');}
function add_option(...$args){throw new RuntimeException('Option writes are forbidden');}
function get_posts(...$args){throw new RuntimeException('Stored history reads are forbidden');}
function get_post_meta(...$args){throw new RuntimeException('Stored history reads are forbidden');}
function wp_remote_request(...$args){throw new RuntimeException('HTTP is forbidden');}
function wp_remote_get(...$args){throw new RuntimeException('HTTP is forbidden');}
function wp_remote_post(...$args){throw new RuntimeException('HTTP is forbidden');}
final class XI_Config {
 static function get(){return array('post_type'=>'product','field_aliases'=>array());}
 static function credential(...$args){throw new RuntimeException('Credential access is forbidden');}
}
final class XI_Xiaoman_Queue_V1 {
 static function text($value,$limit=1000){return is_scalar($value)?substr(sanitize_text_field($value),0,$limit):'';}
}
final class Required_Fixture_DB {function __call($name,$args){throw new RuntimeException('Database access is forbidden');}}
$wpdb=new Required_Fixture_DB();
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
require dirname(__DIR__).'/xiaoman-inquiry-center/native-inquiry-center.php';
require dirname(__DIR__).'/xiaoman-inquiry-center/required/required.php';
class Required_Fixture_Form {
 function __construct(public $submitted=array(),public $id='rf1001',public $post_id=101){}
 function get_settings(){return array('submissionFormName'=>'快速表单','actions'=>array('save-submission','custom','redirect'),'fields'=>array(
  array('id'=>'fwname','name'=>'FullName'),array('id'=>'fwmail','name'=>'Email'),array('id'=>'fwtell','name'=>'WhatsApp'),array('id'=>'fwmesg','name'=>'Message','type'=>'textarea')
 ));}
 function get_fields(){return $this->submitted;}
 function get_id(){return $this->id;}
 function get_post_id(){return $this->post_id;}
}
$cases=0;$assertions=0;
function check_required($condition,$message){$GLOBALS['assertions']++;if(!$condition)throw new RuntimeException($message);}
function reset_required(){
 $GLOBALS['required_options']=array('post_type'=>'product','field_name'=>'Message','min_length'=>3,'error_message'=>'Fixture message required','campaign_codes'=>array('pm'),'categories'=>array());
 $_POST=array();$_SERVER=array('REQUEST_METHOD'=>'POST','HTTP_REFERER'=>'https://fixture.example.invalid/products/example/');XI_Native_Inquiry_Center::$requests=array();
}
function required_case($name,$callback){reset_required();$callback();$GLOBALS['cases']++;}
function expect_required($submitted,$blocked,$label=''){
 $form=new Required_Fixture_Form($submitted);$prior=array('existing-error');$actual=XIFR_Conditional_Required::validate($prior,$form);
 check_required($actual===$prior||$actual===array('existing-error','Fixture message required'),'Only the configured message validation may be added: '.$label);
 check_required((count($actual)>count($prior))===$blocked,'Required result mismatch: '.$label);
}
function signed_required($flags=array(),$overrides=array()){
 $data=array_merge(array('v'=>1,'page_required'=>0,'campaign_required'=>0,'rules_hash'=>XIFR_Conditional_Required::rules_hash(),'host'=>'fixture.example.invalid'),$flags,$overrides);
 $payload=XIFR_Conditional_Required::base64url_encode(wp_json_encode($data));return $payload.'.'.hash_hmac('sha256',$payload,wp_salt('auth'));
}

foreach(array('xifr_campaign','nifr_campaign') as $key){
 required_case($key.' submitted',function()use($key){expect_required(array($key=>'PM-fixture','Message'=>''),true);expect_required(array($key=>'PM-fixture','Message'=>'Enough'),false);expect_required(array($key=>'ordinary','Message'=>''),false);});
 required_case($key.' POST fallback',function()use($key){$_POST[$key]='pm-fixture';expect_required(array('form-field-fwmesg'=>'  '),true);});
}
foreach(array('xi_context','nixm_context','fwxm_context') as $key){
 foreach(array('A1','landing') as $source){
  required_case($key.' '.$source.' submitted and POST',function()use($key,$source){
   $packet=$source==='A1'?array('A1'=>'pm-fixture'):array('_request'=>array('landing_ad_url'=>'https://fixture.example.invalid/?campaign_name=pm-fixture'));
   $raw=wp_json_encode($packet);expect_required(array($key=>$raw,'Message'=>''),true);expect_required(array($key=>$raw,'Message'=>'abc'),false);$_POST[$key]=$raw;expect_required(array('Message'=>''),true);
  });
 }
 required_case($key.' ordinary and malformed packets',function()use($key){
  foreach(array('', 'not-json', '[]', str_repeat('x',24001),wp_json_encode(array('A1'=>'ordinary')),wp_json_encode(array('A1'=>array('pm-fixture'))),wp_json_encode(array('_request'=>array('landing_ad_url'=>array('pm-fixture')))),array('A1'=>'pm-fixture')) as $raw)expect_required(array($key=>$raw,'Message'=>''),false);
 });
 required_case($key.' historical source is not a new evidence surface',function()use($key){
  foreach(array(time()*1000,(time()-31*DAY_IN_SECONDS)*1000,(time()+DAY_IN_SECONDS)*1000) as $at){
   $raw=wp_json_encode(array('_source'=>array('landing'=>'https://fixture.example.invalid/?campaign_name=pm-history','at'=>$at),'_request'=>array('consent_status'=>'accepted')));
   expect_required(array($key=>$raw,'Message'=>''),false);
  }
 });
}
required_case('empty new fields do not hide a legacy PM field',function(){expect_required(array('xifr_campaign'=>'','nifr_campaign'=>'pm-fixture','xi_context'=>'{}','Message'=>''),true);});
required_case('ordinary new packet does not erase legacy PM evidence',function(){expect_required(array('xi_context'=>'{"A1":"ordinary"}','nixm_context'=>'{"A1":"pm-fixture"}','Message'=>''),true);});
required_case('unconfirmed aliases and customer values are not campaign evidence',function(){expect_required(array('fwfr_campaign'=>'pm-fixture','fwfr_context'=>signed_required(array('campaign_required'=>1)),'other_context'=>'{"A1":"pm-fixture"}','A1'=>'pm-fixture','Email'=>'pm-fixture','Message'=>''),false);});
required_case('URL referrer remains a current request fallback',function(){$_SERVER['HTTP_REFERER']='https://fixture.example.invalid/products/example/?utm_campaign=pm-fixture';expect_required(array('Message'=>''),true);});
required_case('legacy POST still works with an absent referrer',function(){unset($_SERVER['HTTP_REFERER']);$_POST['nixm_context']='{"A1":"pm-fixture"}';expect_required(array('Message'=>''),true);});

foreach(array('xifr_context','nifr_context') as $key){
 required_case($key.' signed page and campaign flags',function()use($key){
  foreach(array('page_required','campaign_required') as $flag){$token=signed_required(array($flag=>1));expect_required(array($key=>$token,'nifr_campaign'=>'ordinary','Message'=>''),true);expect_required(array($key=>$token,'Message'=>'abc'),false);}
  expect_required(array($key=>signed_required(),'nifr_campaign'=>'ordinary','Message'=>''),false);
 });
 required_case($key.' POST token fallback',function()use($key){$_POST[$key]=signed_required(array('page_required'=>1));expect_required(array('Message'=>''),true);});
 required_case($key.' invalid signature host rules and shape',function()use($key){
  foreach(array(signed_required(array('campaign_required'=>1)).'x',signed_required(array('campaign_required'=>1),array('host'=>'foreign.example.invalid')),signed_required(array('campaign_required'=>1),array('rules_hash'=>'outdated')),str_repeat('x',3001),array('invalid')) as $token)expect_required(array($key=>$token,'Message'=>''),false);
 });
}
required_case('invalid new token cannot suppress a valid old token',function(){expect_required(array('xifr_context'=>'invalid','nifr_context'=>signed_required(array('page_required'=>1)),'Message'=>''),true);});
required_case('invalid token cannot suppress current PM evidence',function(){expect_required(array('nifr_context'=>'invalid','nifr_campaign'=>'pm-fixture','Message'=>''),true);});
required_case('normalized history in the current form record cannot force a message',function(){
 // Boundary after native validate(): accepted, recent first-touch history may
 // already have repaired hd.A1. It is still not this submission's PM evidence.
 XI_Native_Inquiry_Center::$requests['rf1001:101']=array('schema'=>1,'submitted_at'=>gmdate('Y-m-d H:i:s'),'hd'=>array('A1'=>'pm-history','R2'=>'https://fixture.example.invalid/?campaign_name=pm-history'),'attribution'=>array('capture_basis'=>'consented_first_touch','consent_status'=>'accepted','campaign_name'=>'pm-history'));
 expect_required(array('Message'=>''),false);
 expect_required(array('nifr_campaign'=>'ordinary','Message'=>''),false);
 expect_required(array('xi_context'=>'{"A1":"ordinary"}','Message'=>''),false);
 expect_required(array('nixm_context'=>'{"A1":"ordinary"}','Message'=>''),false);
 expect_required(array('nifr_campaign'=>'pm-current','Message'=>''),true);
});
required_case('another form or page record is not borrowed',function(){XI_Native_Inquiry_Center::$requests=array('rf2001:101'=>array('hd'=>array('A1'=>'pm-fixture')),'rf1001:202'=>array('hd'=>array('A1'=>'pm-fixture')));expect_required(array('Message'=>''),false);});
required_case('record campaign fields are never a new evidence source',function(){
 foreach(array(array('hd'=>array('A1'=>'pm-history')),array('hd'=>array('A1'=>array('pm-history'))),array('hd'=>array('A2'=>'pm-history'),'attribution'=>array('campaign_name'=>'pm-history'),'values'=>array('A1'=>'pm-history'))) as $record){XI_Native_Inquiry_Center::$requests['rf1001:101']=$record;expect_required(array('Message'=>''),false);}
});
required_case('non-target form remains unchanged',function(){
 $form=new class(array('nifr_campaign'=>'pm-fixture','Message'=>'')) extends Required_Fixture_Form {function get_settings(){return array('submissionFormName'=>'Unrelated','fields'=>array(array('id'=>'fwmesg','name'=>'Message')));}};
 check_required(XIFR_Conditional_Required::validate(array(),$form)===array(),'Unrelated form was changed');
});
required_case('no campaign rules means an ordinary optional message',function(){$GLOBALS['required_options']['campaign_codes']=array();expect_required(array('nifr_campaign'=>'pm-fixture','Message'=>''),false);});
echo json_encode(array('php'=>PHP_VERSION,'suite'=>'required-legacy-request-compatibility','cases'=>$cases,'assertions'=>$assertions,'network'=>false,'storage_writes'=>false)).PHP_EOL;

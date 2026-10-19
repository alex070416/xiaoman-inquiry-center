<?php
// Local deterministic WordPress/HTTP mocks only. No network, customer or plugin runtime.
define('ABSPATH',__DIR__);
define('XI_VERSION','test');
if(($argv[1]??'')!=='missing-credentials'){
 define('XIAOMAN_CLIENT_ID','MOCK-CLIENT-92AB');
 define('XIAOMAN_CLIENT_SECRET','MOCK-SECRET-45CD');
}
class WP_Error { public $code,$message; function __construct($c,$m){$this->code=$c;$this->message=$m;}function get_error_message(){return $this->message;} }
class TestDie extends Exception { public $response;function __construct($message,$response){parent::__construct($message);$this->response=$response;} }
class TestRedirect extends Exception {}
class XI_Compatibility { static function legacy_active(){return $GLOBALS['legacy'];}static function import_profile(){$GLOBALS['imports']++;return $GLOBALS['import_profile'];} }
class XI_Cookies {static function definitions(){return array();}static function status(){return array();}}
function is_wp_error($v){return $v instanceof WP_Error;}
function get_option($k,$d=false){return $k==='xi_config_v2'?$GLOBALS['option']:$d;}
function wp_parse_args($v,$d){return array_merge($d,$v);}
function current_user_can($cap){return $cap==='manage_options'&&$GLOBALS['can_manage'];}
function wp_salt($v){return 'mock-salt';}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_textarea_field($v){return trim(strip_tags((string)$v));}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower($v));}
function wp_unslash($v){return $v;}
function post_type_exists($v){return $v==='products';}
function taxonomy_exists($v){return $v==='product-category';}
function is_object_in_taxonomy($p,$t){return $p==='products'&&$t==='product-category';}
function update_option($k,$v,$autoload){$GLOBALS['writes'][]=$k;$GLOBALS['option']=$v;}
function delete_transient($v){$GLOBALS['cache_deletes'][]=$v;}
function set_transient($v,$x,$ttl){$GLOBALS['cache_writes'][]=$v;}
function get_transient($v){$GLOBALS['cache_reads'][]=$v;return 'ordinary-lead-token';}
function admin_url($p){return 'https://mock-wordpress.invalid/wp-admin/'.$p;}
function wp_safe_redirect($v){throw new TestRedirect($v);}
function wp_die($v,$title='',$args=array()){throw new TestDie($v,$args['response']??500);}
function check_admin_referer($v){$GLOBALS['nonce_checks'][]=$v;if(!$GLOBALS['nonce_valid'])wp_die('安全校验失败。','',array('response'=>403));}
function nocache_headers(){$GLOBALS['nocache']++;}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function esc_attr($v){return esc_html($v);}
function esc_url($v){return esc_attr($v);}
function esc_textarea($v){return esc_html($v);}
function wp_json_encode($v,$f=0){return json_encode($v,$f);}
function selected($a,$b,$echo=true){return $a===$b?'selected="selected"':'';}
function checked($a,$b,$echo=true){return $a===$b?'checked="checked"':'';}
function wp_nonce_field($v){echo '<input type="hidden" name="_wpnonce" value="mock-nonce">';}
function submit_button($label,$style='',$name='submit'){echo '<button name="'.esc_attr($name).'">'.esc_html($label).'</button>';}
function get_bloginfo($v){return '6.9-mock';}
function is_ssl(){return true;}
function add_action($a,$b,$p=10){$GLOBALS['hooks'][$a]=$b;}
function wp_remote_retrieve_response_code($v){return $v['status'];}
function wp_remote_retrieve_body($v){return $v['body'];}
function response($status,$data){return array('status'=>$status,'body'=>wp_json_encode($data));}
function wp_remote_post($url,$args){
 $GLOBALS['http'][]=array('method'=>'POST','url'=>$url,'args'=>$args);
 switch($GLOBALS['mode']){
  case 'auth-error':return new WP_Error('unsafe','MOCK-SECRET-45CD https://untrusted.invalid/private?token=MOCK-TOKEN-67EF');
  case 'exception':throw new RuntimeException('MOCK-SECRET-45CD https://untrusted.invalid/private?token=MOCK-TOKEN-67EF');
  case 'auth-redirect':return response(302,array('access_token'=>'MOCK-TOKEN-67EF','message'=>'PRIVATE_RAW'));
  case 'bad-token':return response(200,array('access_token'=>"unsafe\r\nInjected: yes"));
  default:return response(200,array('access_token'=>'MOCK-TOKEN-67EF','private'=>'PRIVATE_RAW'));
 }
}
function wp_remote_get($url,$args){
 $GLOBALS['http'][]=array('method'=>'GET','url'=>$url,'args'=>$args);
 if($GLOBALS['mode']==='metadata-error')return response(403,array('code'=>403,'data'=>null,'message'=>'PRIVATE_RAW MOCK-SECRET-45CD'));
 if($GLOBALS['mode']==='origin-actual'&&str_ends_with($url,'/v1/company/fields/selector?field=origin')){
  $origins=array();for($i=0;$i<59;$i++)$origins[]=array('origin_id'=>'99000000000'.str_pad((string)$i,3,'0',STR_PAD_LEFT),'origin_name'=>'站点来源'.$i.($i===0?' MOCK-SECRET-45CD MOCK-TOKEN-67EF MOCK-CLIENT-92AB':''),'name_info'=>array('info_label'=>'PRIVATE_RAW','info_value'=>'PRIVATE_RAW'),'parent_id'=>'0');
  return response(200,array('code'=>200,'data'=>$origins));
 }
 if(str_ends_with($url,'/v1/company/fields/selector?field=origin'))return response(200,array('code'=>0,'data'=>array('entries'=>array(array('id'=>'46500000000000','name'=>'华腾网站 MOCK-SECRET-45CD','note'=>'PRIVATE_RAW')),'customers'=>array(array('id'=>99,'name'=>'PRIVATE_CUSTOMER')))));
 if(str_ends_with($url,'/v1/lead/fields?type=lead'))return response(200,array('code'=>200,'data'=>array('groups'=>array(array('fields'=>array(
  array('id'=>'14206903842368','name'=>'意向产品','field_type'=>7,'ext_info'=>wp_json_encode(array('enumchoices'=>array(array('id'=>8,'name'=>'叉车 MOCK-TOKEN-67EF MOCK-CLIENT-92AB MOCK-SECRET-45CD')),'access_token'=>'MOCK-TOKEN-67EF','client_id'=>'MOCK-CLIENT-92AB','customers'=>array('PRIVATE_CUSTOMER')))),
  array('id'=>'19675841541642','name'=>'客户等级','field_type'=>1,'ext_info'=>array('options'=>array('普通'),'default'=>false)),
  array('id'=>98,'name'=>'客户邮箱','ext_info'=>array('PRIVATE_CONTACT'))
 ))))));
 throw new RuntimeException('Unexpected mock endpoint');
}
require dirname(__DIR__).'/xiaoman-inquiry-center/configuration.php';
$assertions=0;$cases=array();
function ok($condition,$label){$GLOBALS['assertions']++;if(!$condition)throw new RuntimeException('ASSERTION: '.$label);}
function reset_mock(){
 $GLOBALS['option']=array_merge(XI_Config::defaults(),array('enabled'=>false,'crm'=>false,'api'=>'https://api-sandbox.xiaoman.cn','capture_enabled'=>false,'ads_enabled'=>false,'secrets'=>array('id'=>'existing-encrypted-id','secret'=>'existing-encrypted-secret')));
 foreach(array('http','writes','cache_deletes','cache_writes','cache_reads','nonce_checks') as $k)$GLOBALS[$k]=array();
 $GLOBALS['mode']='ok';$GLOBALS['can_manage']=true;$GLOBALS['nonce_valid']=true;$GLOBALS['legacy']=false;$GLOBALS['nocache']=0;$GLOBALS['imports']=0;$_POST=array();
}
function case_done($name){$GLOBALS['cases'][]=$name;}
function save_post($overrides=array()){
 $_POST=array_merge(array('crm_control_v1'=>'1','api'=>'https://api-sandbox.xiaoman.cn','origin_id'=>'46500000000000','origin_name'=>'华腾','product_field'=>'14206903842368','level_field'=>'19675841541642','level_value'=>'','post_type'=>'products','taxonomies'=>'product-category,invalid','products'=>'{"forklift":"柴油叉车"}','product_lead_names'=>'{"forklift":"叉车"}','equipment_labels'=>'{"forklift":"叉车"}','field_aliases'=>'{"email":"F5"}','client_id'=>'','client_secret'=>''),$overrides);
}
function invoke_save(){try{XI_Config::save();}catch(TestRedirect $e){return true;}catch(TestDie $e){return $e;}return false;}
reset_mock();
if(($argv[1]??'')==='missing-credentials'){
 $GLOBALS['option']['secrets']=array();$r=XI_Config::inspect_configuration();ok(is_wp_error($r),'missing credentials rejected');ok(count($GLOBALS['http'])===0,'no HTTP without credentials');case_done('missing credentials');
}else{
 foreach(array('https://api-sandbox.xiaoman.cn') as $api)ok(XI_Config::api_base($api)===$api,'approved API accepted');
 foreach(array('https://api.xiaoman.cn','https://evil.invalid','http://api-sandbox.xiaoman.cn','https://api-sandbox.xiaoman.cn/','https://api-sandbox.xiaoman.cn/path','https://api-sandbox.xiaoman.cn:443','https://u:p@api-sandbox.xiaoman.cn','https://api-sandbox.xiaoman.cn?next=x','https://api-sandbox.xiaoman.cn#x','https://api-sandbox.xiaoman.cn.evil.invalid',array()) as $api)ok(is_wp_error(XI_Config::api_base($api)),'API variant rejected');
 ok(XI_Config::api_base('')==='','empty management API allowed');ok(is_wp_error(XI_Config::api_base('',true)),'required API cannot be empty');case_done('official production API exact allowlist');
 $before=$GLOBALS['option'];$r=XI_Config::inspect_configuration();$encoded=wp_json_encode($r,JSON_UNESCAPED_UNICODE);
 ok(is_array($r)&&count($GLOBALS['http'])===3,'disabled inspection makes exactly three HTTP requests');
 ok(!defined('XI_API')&&!class_exists('XI_Xiaoman_Queue_V1',false),'runtime provider and constant remain unloaded');
 ok($GLOBALS['option']===$before&&$GLOBALS['writes']===array(),'inspection does not save config');
 ok($GLOBALS['cache_deletes']===array()&&$GLOBALS['cache_reads']===array()&&$GLOBALS['cache_writes']===array(),'normal token untouched');
 $auth=json_decode($GLOBALS['http'][0]['args']['body'],true);ok($auth['scope']==='company lead','independent scope');
 foreach($GLOBALS['http'] as $request)ok($request['args']['redirection']===0&&$request['args']['reject_unsafe_urls']===true&&$request['args']['sslverify']===true,'safe HTTP options');
 ok($GLOBALS['http'][0]['url']==='https://api-sandbox.xiaoman.cn/v1/oauth2/access_token','only OAuth post');
 ok($GLOBALS['http'][1]['url']==='https://api-sandbox.xiaoman.cn/v1/company/fields/selector?field=origin'&&$GLOBALS['http'][2]['url']==='https://api-sandbox.xiaoman.cn/v1/lead/fields?type=lead','only fixed metadata GETs');
 ok(count($r['来源配置'])===1&&$r['来源配置'][0]['来源编号']==='46500000000000','entries origin metadata extracted');
 ok(count($r['线索映射字段'])===2,'only requested lead fields');
 foreach(array('MOCK-TOKEN-67EF','MOCK-CLIENT-92AB','MOCK-SECRET-45CD','PRIVATE_RAW','PRIVATE_CUSTOMER','PRIVATE_CONTACT','客户邮箱') as $secret)ok(!str_contains($encoded,$secret),'secret or irrelevant data removed');
 $ext=json_decode($r['线索映射字段'][0]['选项配置'],true);ok(isset($ext['enumchoices'][0]['name'])&&str_contains($ext['enumchoices'][0]['name'],'叉车'),'encoded unknown enum schema preserved');
 ok($ext['access_token']==='[已隐藏]'&&$ext['customers']==='[已隐藏]','sensitive nested keys redacted');case_done('management-only metadata and redaction');
 reset_mock();$GLOBALS['mode']='origin-actual';$before=$GLOBALS['option'];$r=XI_Config::inspect_configuration();$encoded=wp_json_encode($r,JSON_UNESCAPED_UNICODE);
 ok(count($r['来源配置'])===59,'actual origin_id/origin_name list extracted');
 ok($r['来源配置'][0]['来源编号']==='99000000000000'&&$r['来源配置'][58]['来源编号']==='99000000000058','all actual-shape records retained');
 foreach(array('MOCK-SECRET-45CD','MOCK-TOKEN-67EF','MOCK-CLIENT-92AB','PRIVATE_RAW') as $private)ok(!str_contains($encoded,$private),'actual-shape values redacted and extra metadata excluded');
 ok(count($GLOBALS['http'])===3&&$GLOBALS['option']===$before&&$GLOBALS['writes']===array(),'actual-shape inspection remains read-only');
 ok($GLOBALS['cache_deletes']===array()&&$GLOBALS['cache_reads']===array()&&$GLOBALS['cache_writes']===array(),'actual-shape inspection leaves lead token untouched');case_done('actual observed origin response shape');
 $pairs=XI_Config::configuration_origins(array('node'=>array(array('origin_id'=>'99','origin_name'=>'新配对','id'=>'999','name'=>'旧配对'),array('origin_id'=>'孤立编号','name'=>'不混合'),array('origin_name'=>'孤立名称','id'=>'不混合'),array('origin_id'=>'孤立编号','id'=>'legacy-id','name'=>'旧兼容'),array('origin_id'=>array(),'origin_name'=>'无效','value'=>'select-id','label'=>'选项兼容'))),array());
 ok(count($pairs)===3,'incomplete origin pairs never mix with legacy keys');
 ok($pairs[0]===array('来源编号'=>'99','来源名称'=>'新配对'),'complete origin pair takes priority');
 ok($pairs[1]===array('来源编号'=>'legacy-id','来源名称'=>'旧兼容'),'legacy id/name fallback retained');
 ok($pairs[2]===array('来源编号'=>'select-id','来源名称'=>'选项兼容'),'legacy value/label fallback and recursion retained');case_done('paired precedence and legacy fallback');
 foreach(array('auth-error','exception','auth-redirect','bad-token','metadata-error') as $scenario){reset_mock();$GLOBALS['mode']=$scenario;$r=XI_Config::inspect_configuration();$out=is_wp_error($r)?$r->get_error_message():wp_json_encode($r);foreach(array('MOCK-SECRET','MOCK-TOKEN','PRIVATE_RAW','untrusted.invalid') as $s)ok(!str_contains($out,$s),'fixed safe error');ok(count($GLOBALS['http'])===($scenario==='metadata-error'?3:1),'bounded failure requests: '.$scenario.' count='.count($GLOBALS['http']));}
 case_done('network exception redirect and API failure safety');
 reset_mock();$GLOBALS['can_manage']=false;$r=XI_Config::inspect_configuration();ok(is_wp_error($r)&&count($GLOBALS['http'])===0,'method permission gate');try{XI_Config::inspect_page();}catch(TestDie $e){ok($e->response===403,'handler permission 403');}ok($GLOBALS['nocache']===1&&count($GLOBALS['nonce_checks'])===0,'nocache before unauthorized response');case_done('capability gate');
 reset_mock();$GLOBALS['nonce_valid']=false;try{XI_Config::inspect_page();}catch(TestDie $e){ok($e->response===403,'nonce failure');}ok(count($GLOBALS['http'])===0&&$GLOBALS['nonce_checks']===array('xi_config_inspect'),'nonce before HTTP');case_done('nonce gate');
 reset_mock();try{XI_Config::inspect_page();}catch(TestDie $e){ok($e->response===200&&str_contains($e->getMessage(),'返回小满询盘设置'),'read-only result page');ok(!str_contains($e->getMessage(),'MOCK-SECRET-45CD'),'HTML result redacted');}ok($GLOBALS['nocache']===1&&$GLOBALS['writes']===array(),'result page not cached or saved');case_done('result rendering');
 reset_mock();$r=XI_Config::configuration_get('https://evil.invalid','/v1/lead/fields?type=lead','x');ok(is_wp_error($r)&&count($GLOBALS['http'])===0,'helper base gate');$r=XI_Config::configuration_get('https://api-sandbox.xiaoman.cn','/v1/lead/list','x');ok(is_wp_error($r)&&count($GLOBALS['http'])===0,'helper endpoint gate');case_done('helper allowlists');
 reset_mock();save_post();$r=invoke_save();ok($r===true&&count($GLOBALS['writes'])===1,'valid save');$saved=$GLOBALS['option'];ok($saved['origin_id']==='46500000000000'&&$saved['product_field']==='14206903842368'&&$saved['level_field']==='19675841541642','ID fields preserved');ok($saved['secrets']===array('id'=>'existing-encrypted-id','secret'=>'existing-encrypted-secret'),'blank credentials preserved');ok($saved['products']===array('forklift'=>'柴油叉车')&&$saved['field_aliases']===array('email'=>'F5'),'mapping fields retained');ok($saved['taxonomies']===array('product-category'),'taxonomy filtering retained');ok($saved['enabled']===false&&$saved['crm']===false&&$saved['capture_enabled']===false&&$saved['ads_enabled']===false,'other switches retained');case_done('save compatibility');
 reset_mock();save_post(array('api'=>''));ok(invoke_save()===true&&$GLOBALS['option']['api']===''&&$GLOBALS['option']['enabled']===false,'empty API saved while off');case_done('empty API while disabled');
 reset_mock();save_post(array('api'=>'','enabled'=>'1'));$r=invoke_save();ok($r instanceof TestDie&&$GLOBALS['writes']===array(),'cannot enable empty API');case_done('empty API enable blocked');
 reset_mock();save_post(array('api'=>'https://api-sandbox.xiaoman.cn:443'));$r=invoke_save();ok($r instanceof TestDie&&$GLOBALS['writes']===array(),'invalid API fails before persistence');case_done('save invalid API rejected');
 reset_mock();save_post(array('api'=>'https://api.xiaoman.cn'));$r=invoke_save();ok($r instanceof TestDie&&$GLOBALS['writes']===array(),'unverified hostname cannot be saved');$r=XI_Config::configuration_get('https://api.xiaoman.cn','/v1/lead/fields?type=lead','x');ok(is_wp_error($r)&&$GLOBALS['http']===array(),'unverified hostname cannot receive requests');case_done('unverified hostname rejected');
 reset_mock();save_post(array('origin_id'=>'bad'));ok(invoke_save() instanceof TestDie&&$GLOBALS['writes']===array(),'existing ID validation retained');case_done('existing ID validation');
 reset_mock();$GLOBALS['import_profile']=array_merge($GLOBALS['option'],array('enabled'=>false,'api'=>'https://api-sandbox.xiaoman.cn','migration'=>array('from'=>'legacy')));$_POST=array('import_legacy'=>'1');ok(invoke_save()===true&&$GLOBALS['imports']===1&&$GLOBALS['option']['migration']===array('from'=>'legacy'),'legacy import path preserved');case_done('legacy import');
 reset_mock();XI_Config::boot();ok(isset($GLOBALS['hooks']['admin_post_xi_config_inspect']),'admin-post handler registered');ob_start();XI_Config::page();$page=ob_get_clean();ok(str_contains($page,'name="api"')&&str_contains($page,'小满官方接口（正式）')&&!str_contains($page,'沙盒环境')&&!str_contains($page,'https://api.xiaoman.cn')&&str_contains($page,'xi_config_inspect')&&str_contains($page,'客户端编号')&&str_contains($page,'只读核对小满来源与字段'),'Chinese controls rendered while off');ok(!str_contains($page,'MOCK-SECRET-45CD')&&!str_contains($page,'MOCK-CLIENT-92AB'),'credentials never prefilled');case_done('management UI');

// CRM toggle exercises the real settings save entry point with rendered-form values.
function complete_form_from_saved($config){
 $post=array('crm_control_v1'=>'1','api'=>$config['api'],'post_type'=>$config['post_type'],'taxonomies'=>implode(',',$config['taxonomies']),'client_id'=>'','client_secret'=>'');
 foreach(array('origin_id','origin_name','product_field','level_field','level_value') as $k)$post[$k]=$config[$k];
 foreach(array('products','product_lead_names','equipment_labels','field_aliases') as $k)$post[$k]=wp_json_encode((object)$config[$k]);
 if($config['enabled'])$post['enabled']='1';
 if($config['crm'])$post['crm_enabled']='1';
 return $post;
}
foreach(array(false,true) as $master){
 foreach(array(false,true) as $prior){
  foreach(array(false,true) as $requested){
   reset_mock();
   $before=array_merge($GLOBALS['option'],array('enabled'=>$master,'crm'=>$prior,'origin_id'=>'99000001','origin_name'=>'Synthetic website','product_field'=>'99000002','level_field'=>'99000003','level_value'=>'None','number_prefix'=>'HT-N','taxonomies'=>array('product-category'),'products'=>array('forklift'=>'Synthetic product'),'product_lead_names'=>array('forklift'=>'Synthetic lead'),'equipment_labels'=>array('forklift'=>'Forklifts'),'field_aliases'=>array('email'=>'F5'),'migration'=>array('from'=>'preserve-original'),'queue_prefix'=>'htxi_','conversion'=>array('enabled'=>false,'enhanced'=>false),'future_config'=>array('preserve'=>array(1,2,3))));
   $GLOBALS['option']=$before;$_POST=complete_form_from_saved($before);
   unset($_POST['crm_enabled']);if($requested)$_POST['crm_enabled']='1';
   ok(invoke_save()===true,'complete settings POST succeeds');
   $expected=$before;$expected['crm']=$requested;
   ok($GLOBALS['option']===$expected,'only CRM changes; master, mappings, encrypted credentials and unknown keys remain exact');
   ok($GLOBALS['http']===array(),'settings save makes no CRM or OAuth calls');
  }
 }
}
case_done('CRM toggle actual save: eight independent master/prior/requested combinations');
foreach(array('0','', 'on','true','01',1,true,array('1'),array('nested'=>'1'),null) as $forged){
 reset_mock();$GLOBALS['option']['enabled']=true;$GLOBALS['option']['crm']=true;$before=$GLOBALS['option'];$_POST=complete_form_from_saved($before);$_POST['crm_enabled']=$forged;
 ok(invoke_save()===true,'malformed checkbox cannot bypass valid save');
 ok($GLOBALS['option']['crm']===false&&$GLOBALS['option']['enabled']===true,'only explicit browser checkbox value enables CRM');
}
case_done('CRM malformed and array input fail closed');
foreach(array(false,true) as $prior){
 reset_mock();$GLOBALS['option']['crm']=$prior;$before=$GLOBALS['option'];$_POST=complete_form_from_saved($before);unset($_POST['crm_control_v1'],$_POST['crm_enabled']);
 ok(invoke_save()===true&&$GLOBALS['option']===$before,'older form without CRM controls preserves saved CRM value');
 ok($GLOBALS['http']===array(),'older form save makes no network request');
}
ok(XI_Config::defaults()['crm']===true&&XI_Config::defaults()['enabled']===false,'historical defaults unchanged; master remains off');
case_done('older settings POST preserves both saved CRM states and defaults');
reset_mock();$GLOBALS['option']['crm']=true;$before=$GLOBALS['option'];$_POST=complete_form_from_saved($before);unset($_POST['crm_enabled']);
ok(invoke_save()===true&&$GLOBALS['option']['crm']===false,'new form marker with unchecked checkbox explicitly pauses CRM');
$before['crm']=false;ok($GLOBALS['option']===$before&&$GLOBALS['http']===array(),'explicit pause preserves all other saved settings without network');
case_done('new form marker permits explicit unchecked pause');
foreach(array(false,true) as $prior){
 foreach(array('1','0',array('1'),null) as $forged){
  reset_mock();$GLOBALS['option']['crm']=$prior;$before=$GLOBALS['option'];$_POST=complete_form_from_saved($before);unset($_POST['crm_control_v1']);$_POST['crm_enabled']=$forged;
  ok(invoke_save()===true&&$GLOBALS['option']===$before,'checkbox without new form marker cannot change saved CRM value');
 }
}
case_done('unmarked forged checkbox preserves saved CRM setting');
foreach(array('0','','on','true','01',1,true,array('1'),array('nested'=>'1'),null) as $marker){
 foreach(array(false,true) as $prior){
  reset_mock();$GLOBALS['option']['crm']=$prior;$before=$GLOBALS['option'];$_POST=complete_form_from_saved($before);$_POST['crm_control_v1']=$marker;
  if($prior)unset($_POST['crm_enabled']);else $_POST['crm_enabled']='1';
  ok(invoke_save()===true&&$GLOBALS['option']===$before,'only exact string marker permits a CRM setting change');
 }
}
case_done('malformed or array form markers preserve saved CRM setting');
foreach(array('permission','nonce') as $gate){
 reset_mock();$_POST=complete_form_from_saved($GLOBALS['option']);$_POST['crm_enabled']='1';$before=$GLOBALS['option'];
 if($gate==='permission')$GLOBALS['can_manage']=false;else $GLOBALS['nonce_valid']=false;
 $r=invoke_save();ok($r instanceof TestDie&&$r->response===403,'unauthorized toggle save rejected');
 ok($GLOBALS['option']===$before&&$GLOBALS['writes']===array()&&$GLOBALS['http']===array(),'rejected toggle has no persistence or network effects');
}
case_done('CRM save capability and nonce enforced');
foreach(array(false,true) as $state){
 reset_mock();$GLOBALS['option']['crm']=$state;ob_start();XI_Config::page();$html=ob_get_clean();
 preg_match('/<input type="checkbox" name="crm_enabled" value="1" ([^>]*)>/', $html,$match);
 ok(count($match)===2,'one CRM checkbox rendered');
 ok(substr_count($html,'name="crm_enabled"')===1&&str_contains($match[1],'checked="checked"')===$state,'checkbox matches persisted CRM flag');
 ok(substr_count($html,'name="crm_control_v1"')===1&&str_contains($html,'<input type="hidden" name="crm_control_v1" value="1">'),'one new form marker accompanies CRM checkbox');
 ok(str_contains($html,'允许小满同步')&&str_contains($html,'已在途请求可能完成')&&str_contains($html,'审核记录保留'),'pause limits visible');
 ok(!str_contains($html,'MOCK-SECRET-45CD')&&!str_contains($html,'MOCK-CLIENT-92AB')&&!str_contains($html,'existing-encrypted-secret'),'no credential values rendered');
}
case_done('CRM UI reflects saved state and exposes no credentials');
reset_mock();$GLOBALS['import_profile']=array_merge($GLOBALS['option'],array('crm'=>true,'enabled'=>false,'migration'=>array('from'=>'legacy')));$_POST=array('import_legacy'=>'1','crm_enabled'=>array('forged'));
ok(invoke_save()===true&&$GLOBALS['option']===$GLOBALS['import_profile'],'legacy import ignores forged checkbox and preserves imported profile');
case_done('legacy import branch unchanged');
}
echo wp_json_encode(array('result'=>'PASS','cases'=>count($cases),'assertions'=>$assertions,'coverage'=>$cases,'real_network'=>false,'provider_loaded'=>class_exists('XI_Xiaoman_Queue_V1',false),'xi_api_defined'=>defined('XI_API')),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";

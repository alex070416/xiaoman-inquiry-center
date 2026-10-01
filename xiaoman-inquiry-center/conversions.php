<?php
if (!defined('ABSPATH')) exit;
/** Claims guarantee at most one browser dispatch per saved inquiry/channel, not Google delivery. */
final class XI_Conversions {
 const OPTION='xi_conversion_config_v1';
 static $tickets=array();
 static $enqueued=false;
 static function defaults(){return array('enabled'=>false,'site_host'=>'','cutover_id'=>0,'gtm_id'=>'','ga4_id'=>'','ads_id'=>'','ads_label'=>'','whatsapp_label'=>'','email_label'=>'','analytics_service'=>'','ads_service'=>'','user_data_service'=>'','enhanced'=>false,'thank_you_paths'=>array());}
 static function config(){return wp_parse_args((array)get_option(self::OPTION,array()),self::defaults());}
 static function active(){$c=self::config();return $c['enabled'] && $c['site_host']===XI_HOST;}
 static function table(){global $wpdb;return $wpdb->prefix.'xi_conversion_receipts';}
 static function boot(){
  add_filter('bricks/form/response',array(__CLASS__,'response'),90,2);add_action('wp_enqueue_scripts',array(__CLASS__,'assets'),0);
  add_action('wp_head',array(__CLASS__,'early_assets'),-100);
  add_action('template_redirect',array(__CLASS__,'privacy_headers'),0);
  add_filter('script_loader_tag',array(__CLASS__,'script_tag'),10,2);add_filter('wp_inline_script_attributes',array(__CLASS__,'inline_attributes'));
  foreach(array('litespeed_optimize_js_excludes','litespeed_optm_js_defer_exc','litespeed_optm_gm_js_exc') as $filter)add_filter($filter,array(__CLASS__,'optimizer_exclusions'));
  foreach(array('wp_ajax_xi_conversion','wp_ajax_nopriv_xi_conversion') as $tag)add_action($tag,array(__CLASS__,'ajax'));
  add_action('admin_menu',array(__CLASS__,'menu'),46);add_action('admin_post_xi_conversion_save',array(__CLASS__,'save_config'));
 }
 static function setup(){
  global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$t=self::table();$charset=$wpdb->get_charset_collate();
  dbDelta("CREATE TABLE $t (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   submission_id bigint(20) unsigned NOT NULL,
   token_hash char(64) NOT NULL,
   request_uuid varchar(36) NOT NULL,
   form_kind varchar(12) NOT NULL,
   created_utc datetime NOT NULL,
   expires_utc datetime NOT NULL,
   site_timezone varchar(64) NOT NULL,
   is_test tinyint(1) NOT NULL DEFAULT 0,
   consent_at_submit text NOT NULL,
   ga4_state varchar(16) NOT NULL DEFAULT 'pending',
   ads_state varchar(16) NOT NULL DEFAULT 'pending',
   ga4_claimed_utc datetime DEFAULT NULL,
   ads_claimed_utc datetime DEFAULT NULL,
   ga4_callback_utc datetime DEFAULT NULL,
   ads_callback_utc datetime DEFAULT NULL,
   PRIMARY KEY  (id),
   UNIQUE KEY submission_id (submission_id),
   UNIQUE KEY token_hash (token_hash),
   KEY created_utc (created_utc)
  ) $charset;");
  return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($t)))===$t;
 }
 static function safe_path($v){return is_string($v)&&preg_match('#^/(?:[a-zA-Z0-9_-]{1,64}/){1,4}$#D',$v)?$v:'';}
 static function optimizer_exclusions($items){return self::active()?array_values(array_unique(array_merge((array)$items,array('xiaoman-inquiry-center/conversions.js','XIConversionConfig')))):$items;}
 static function absolute_path($path){
  // TranslatePress filters home_url during language AJAX requests. Configured paths already contain their language prefix.
  $base=untrailingslashit((string)get_option('home'));
  return self::safe_path($path)&&wp_parse_url($base,PHP_URL_HOST)===XI_HOST?$base.$path:'';
 }
 static function thank_you($r){
  $c=self::config();$path=(string)wp_parse_url($r['hd']['H1']??'',PHP_URL_PATH);$paths=(array)$c['thank_you_paths'];
  foreach($paths as $lang=>$target)if($lang!=='default'&&preg_match('/^[a-zA-Z0-9_-]+$/D',$lang)&&strpos($path,'/'.$lang.'/')===0&&self::absolute_path($target))return self::absolute_path($target);
  $native=XI_Native_Inquiry_Center::thank_you_url($r);
  return $native===home_url('/thank-you/')&&self::absolute_path($paths['default']??'')?self::absolute_path($paths['default']):$native;
 }
 static function thank_you_routes(){
  $urls=array(self::thank_you(array()));
  foreach((array)self::config()['thank_you_paths'] as $target)if(self::absolute_path($target))$urls[]=self::absolute_path($target);
  if(class_exists('TRP_Translate_Press'))try{
   $trp=TRP_Translate_Press::get_trp_instance();$settings=$trp->get_component('settings')->get_settings();$converter=$trp->get_component('url_converter');
   foreach((array)($settings['publish-languages']??array()) as $lang){$source=$converter->get_url_for_language($lang,home_url('/'),'');$urls[]=self::thank_you(array('hd'=>array('H1'=>$source)));}
  }catch(Throwable $e){/* Explicit configured paths remain available if translation lookup fails. */}
  $out=array();$host=wp_parse_url(home_url(),PHP_URL_HOST);
  foreach($urls as $url){$path=(string)wp_parse_url($url,PHP_URL_PATH);if(wp_parse_url($url,PHP_URL_HOST)===$host&&self::safe_path($path))$out[]=$path;}
  return array_values(array_unique($out));
 }
 static function is_thank_you_path($path){
  return is_string($path)&&in_array(rtrim($path,'/').'/',self::thank_you_routes(),true);
 }
 static function states($v){$out=array();foreach(array('analytics_storage','ad_storage','ad_user_data','ad_personalization') as $k)$out[$k]=($v[$k]??'denied')==='granted'?'granted':'denied';return $out;}
 static function service_ready($name,$purpose){
  if(!is_string($name)||$name===''||!post_type_exists('rcb-cookie'))return false;
  $posts=get_posts(array('post_type'=>'rcb-cookie','post_status'=>array('publish','draft','private'),'posts_per_page'=>2,'meta_key'=>'uniqueName','meta_value'=>$name));
  if(count($posts)!==1||$posts[0]->post_status!=='publish')return false;
  $types=get_post_meta($posts[0]->ID,'googleConsentModeConsentTypes',true);if(is_string($types))$types=json_decode($types,true);
  return is_array($types)&&in_array($purpose,$types,true);
 }
 static function test_record($values){
  $p=get_option('xi_conversion_test_plan_v1',array());
  return is_array($p)&&($p['expires']??0)>time()&&in_array(strtolower(trim($values['F5']??'')),(array)($p['emails']??array()),true)&&strpos($values['F3']??'','XI-PILOT-TEST-')===0;
 }
 static function issue($id,$r){
  $c=self::config();if(!self::active()||$id<=absint($c['cutover_id'])||empty($r['uuid']))return '';
  if(isset(self::$tickets[$id]))return self::$tickets[$id];global $wpdb;$token=bin2hex(random_bytes(32));
  $ok=$wpdb->insert(self::table(),array('submission_id'=>$id,'token_hash'=>hash('sha256',$token),'request_uuid'=>$r['uuid'],'form_kind'=>$r['kind'],'created_utc'=>gmdate('Y-m-d H:i:s'),'expires_utc'=>gmdate('Y-m-d H:i:s',time()+1800),'site_timezone'=>wp_timezone_string(),'is_test'=>!empty($r['is_test'])?1:0,'consent_at_submit'=>wp_json_encode(self::states($r['measurement_consent']??array()))));
  if(!$ok)return '';return self::$tickets[$id]=$token;
 }
 static function response($response,$form){
  if(!self::active()||!is_array($response)||($response['type']??'')!=='success'||!is_callable(array($form,'get_id')))return $response;
  $r=XI_Native_Inquiry_Center::$requests[XI_Native_Inquiry_Center::context_key($form)]??null;
  // Other Bricks forms retain their original response and actions.
  if(!$r)return $response;$id=XI_Bricks::saved_id($form);
  if(!$id||($response['inquiryNumber']??'')!==XI_Native_Inquiry_Center::number($id)){unset($response['redirectTo'],$response['redirectUrl'],$response['inquiryNumber']);$response['type']='danger';$response['message']='Your inquiry could not be verified. Please contact us directly.';return $response;}
  $args=array('form'=>'quick_quote','inquiry_id'=>XI_Native_Inquiry_Center::number($id),'form_type'=>$r['kind']);
  // A measurement storage fault must not invite repeat form/CRM submissions.
  try{XI_Lead_Stages::record($id,'initial','');$token=self::issue($id,$r);if($token!=='')$args['xi_receipt']=$token;}catch(Throwable $e){$token='';}
  $response['redirectTo']=add_query_arg($args,self::thank_you($r));if(array_key_exists('redirectUrl',$response))$response['redirectUrl']=$response['redirectTo'];
  $response['xiTracking']=$token!==''?'receipt_issued':'unavailable';return $response;
 }
 static function email_hash($v){$v=strtolower(trim((string)$v));if(!is_email($v))return '';list($local,$host)=explode('@',$v,2);if(in_array($host,array('gmail.com','googlemail.com'),true))$local=str_replace('.','',$local);return hash('sha256',$local.'@'.$host);}
 static function phone_hash($v){$v=preg_replace('/[\s().-]/','',trim((string)$v));return preg_match('/^\+[1-9][0-9]{7,14}$/D',$v)?hash('sha256',$v):'';}
 static function receipt($token,$number){
  if(!self::active()||!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))return null;global $wpdb;
  $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE token_hash=%s',hash('sha256',$token)),ARRAY_A);
  if(!$row||$number!==XI_Native_Inquiry_Center::number($row['submission_id'])||$row['submission_id']<=absint(self::config()['cutover_id'])||strtotime($row['expires_utc'].' UTC')<=time()||XI_Native_Inquiry_Trash::has($row['submission_id']))return null;
  $saved=XI_Native_Inquiry_Center::row($row['submission_id']);$r=XI_Native_Inquiry_Center::record($saved);
  return $saved&&hash_equals($row['request_uuid'],(string)($r['uuid']??''))?$row:null;
 }
 static function claim($token,$number,$channel,$consent){
  if(!in_array($channel,array('ga4','ads'),true))return new WP_Error('channel','Invalid channel');$row=self::receipt($token,$number);if(!$row)return new WP_Error('receipt','Invalid or expired receipt');
  $c=self::config();$purpose=$channel==='ga4'?'analytics_storage':'ad_storage';$service=$channel==='ga4'?$c['analytics_service']:$c['ads_service'];$consent=self::states($consent);if($consent[$purpose]!=='granted'||!self::service_ready($service,$purpose))return new WP_Error('consent','Consent not granted or service not declared');
  $dest=$channel==='ga4'?$c['ga4_id']:$c['ads_id'].'/'.$c['ads_label'];
  if(($channel==='ga4'&&!preg_match('/^G-[A-Z0-9]+$/D',$dest))||($channel==='ads'&&!preg_match('#^AW-[0-9]+/[A-Za-z0-9_-]+$#D',$dest)))return new WP_Error('config','Destination missing');
  global $wpdb;$state=$channel.'_state';$at=$channel.'_claimed_utc';$changed=$wpdb->query($wpdb->prepare('UPDATE '.self::table()." SET $state='claimed', $at=%s WHERE id=%d AND $state='pending'",gmdate('Y-m-d H:i:s'),$row['id']));
  if($changed===false)return new WP_Error('storage','Claim failed');if($changed!==1)return array('dispatch'=>false,'reason'=>'already_claimed');
  $out=array('dispatch'=>true,'channel'=>$channel,'send_to'=>$dest,'inquiry_id'=>$number,'form_type'=>$row['form_kind'],'is_test'=>(int)$row['is_test']);$submitted=self::states(json_decode($row['consent_at_submit'],true)??array());
  if($channel==='ads'&&$c['enhanced']&&self::service_ready($c['user_data_service'],'ad_user_data')&&$consent['ad_user_data']==='granted'&&$submitted['ad_user_data']==='granted'){
   $r=XI_Native_Inquiry_Center::record(XI_Native_Inquiry_Center::row($row['submission_id']));$v=$r['values']??array();$email=self::email_hash($v['F5']??'');$phone=self::phone_hash($v['F6']??'');
   if($email){$out['user_data']=array('sha256_email_address'=>$email);if($phone)$out['user_data']['sha256_phone_number']=$phone;}
  }return $out;
 }
 static function acknowledge($token,$number,$channel){
  if(!in_array($channel,array('ga4','ads'),true)||!($row=self::receipt($token,$number)))return false;global $wpdb;$state=$channel.'_state';$at=$channel.'_callback_utc';
  return $wpdb->query($wpdb->prepare('UPDATE '.self::table()." SET $state='callback', $at=%s WHERE id=%d AND $state='claimed'",gmdate('Y-m-d H:i:s'),$row['id']))!==false;
 }
 static function ajax(){
  nocache_headers();header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex');$origin=$_SERVER['HTTP_ORIGIN']??'';$h=wp_parse_url(home_url());$expected=$h['scheme'].'://'.$h['host'].(isset($h['port'])?':'.$h['port']:'');
  if(strtoupper($_SERVER['REQUEST_METHOD']??'')!=='POST'||($origin!==''&&$origin!==$expected))wp_send_json_error(array('code'=>'origin'),403);
  $raw=file_get_contents('php://input',false,null,0,4097);$p=strlen($raw)<=4096?json_decode($raw,true):null;if(!is_array($p))wp_send_json_error(array('code'=>'request'),400);
  $token=$p['receipt']??'';$number=$p['inquiry_id']??'';$channel=$p['channel']??'';
  if(($p['op']??'claim')==='ack')wp_send_json_success(array('ack'=>self::acknowledge($token,$number,$channel)));
  $out=self::claim($token,$number,$channel,(array)($p['consent']??array()));if(is_wp_error($out))wp_send_json_error(array('code'=>$out->get_error_code()),$out->get_error_code()==='receipt'?410:400);wp_send_json_success($out);
 }
 static function assets(){
  if(self::$enqueued||!self::active()||is_admin())return;self::$enqueued=true;$c=self::config();$pub=array_intersect_key($c,array_flip(array('site_host','ga4_id','ads_id','ads_label','whatsapp_label','email_label','analytics_service','ads_service','user_data_service')));$pub['endpoint']=admin_url('admin-ajax.php?action=xi_conversion');$pub['number_prefix']=XI_PROFILE['number_prefix'];$pub['thank_you_routes']=self::thank_you_routes();
  foreach(array('analytics_service'=>'analytics_storage','ads_service'=>'ad_storage','user_data_service'=>'ad_user_data') as $key=>$purpose)if(!self::service_ready($c[$key],$purpose))$pub[$key]='';
  wp_enqueue_script('xi-conversions',plugins_url('conversions.js',XI_FILE),array(),XI_VERSION,false);wp_add_inline_script('xi-conversions','window.XIConversionConfig='.wp_json_encode($pub).';','before');
 }
 static function early_assets(){self::assets();if(self::$enqueued)wp_print_scripts('xi-conversions');}
 static function privacy_headers(){
  if(!self::active()||!self::is_thank_you_path((string)wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)))return;
  if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();header('Cache-Control: no-store, private');header('Referrer-Policy: same-origin');header('X-Robots-Tag: noindex, nofollow');
 }
 static function inline_attributes($attrs){if(($attrs['id']??'')==='xi-conversions-js-before')$attrs=array_merge($attrs,array('data-no-optimize'=>'1','data-no-defer'=>'1','data-cfasync'=>'false'));return $attrs;}
 static function script_tag($tag,$handle){if($handle!=='xi-conversions')return $tag;$p=new WP_HTML_Tag_Processor($tag);if($p->next_tag('SCRIPT'))foreach(array('data-no-optimize'=>'1','data-no-defer'=>'1','data-cfasync'=>'false') as $key=>$value)$p->set_attribute($key,$value);return $p->get_updated_html();}
 static function menu(){add_submenu_page(XI_Native_Inquiry_Center::PAGE,'转化与对账','转化与对账','manage_options','xiaoman-conversions',array(__CLASS__,'page'));}
 static function save_config(){
  if(!current_user_can('manage_options'))wp_die('Forbidden','',array('response'=>403));check_admin_referer('xi_conversion_save');$c=self::config();
  foreach(array('gtm_id','ga4_id','ads_id','ads_label','whatsapp_label','email_label','analytics_service','ads_service','user_data_service') as $k)$c[$k]=sanitize_text_field(wp_unslash($_POST[$k]??''));
  foreach(array('gtm_id'=>'/^GTM-[A-Z0-9]+$/D','ga4_id'=>'/^G-[A-Z0-9]+$/D','ads_id'=>'/^AW-[0-9]+$/D','ads_label'=>'/^[A-Za-z0-9_-]+$/D','whatsapp_label'=>'/^[A-Za-z0-9_-]+$/D','email_label'=>'/^[A-Za-z0-9_-]+$/D') as $k=>$re)if($c[$k]!==''&&!preg_match($re,$c[$k]))wp_die('Invalid '.$k);
  $paths=json_decode(wp_unslash($_POST['thank_you_paths']??'{}'),true);if(!is_array($paths))wp_die('Invalid paths');foreach($paths as $lang=>$path)if(!preg_match('/^(default|[a-zA-Z0-9_-]+)$/D',(string)$lang)||!self::safe_path($path))wp_die('Invalid thank-you path');$c['thank_you_paths']=$paths;
  $enable=isset($_POST['enabled']);if($enable&&(!$c['ga4_id']||!$c['ads_id']||!$c['ads_label']||!self::service_ready($c['analytics_service'],'analytics_storage')||!self::service_ready($c['ads_service'],'ad_storage')||!self::setup()||!XI_Lead_Stages::setup()))wp_die('Missing destinations, published consent-purpose declarations or storage');
  if($enable&&isset($_POST['enhanced'])&&!self::service_ready($c['user_data_service'],'ad_user_data'))wp_die('Enhanced conversions require a published ad_user_data consent declaration');
  if($enable&&!$c['enabled']){global $wpdb;$c['cutover_id']=(int)$wpdb->get_var('SELECT MAX(id) FROM '.XI_Native_Inquiry_Center::table());$c['site_host']=XI_HOST;}
  $c['enabled']=$enable;$c['enhanced']=isset($_POST['enhanced'])&&$c['user_data_service']!=='';update_option(self::OPTION,$c,false);wp_safe_redirect(admin_url('admin.php?page=xiaoman-conversions&updated=1'));exit;
 }
 static function page(){
  if(!current_user_can('manage_options'))return;$c=self::config();echo '<div class="wrap"><h1>转化与对账</h1><p>启用前备份并停用对应旧表单及联系点击标签，保留现有 Google 基础代码与同意管理。GTM 容器 ID 仅登记，不重复安装容器。</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="xi_conversion_save">';wp_nonce_field('xi_conversion_save');echo '<table class="form-table">';
  foreach(array('gtm_id'=>'GTM 容器','ga4_id'=>'GA4 衡量 ID','ads_id'=>'Ads 转化 ID','ads_label'=>'表单转化标签','whatsapp_label'=>'WhatsApp 次要标签（可选）','email_label'=>'邮箱次要标签（可选）','analytics_service'=>'统计同意服务 Unique Name','ads_service'=>'广告存储同意服务 Unique Name','user_data_service'=>'已声明 ad_user_data 的同意服务 Unique Name') as $k=>$label)echo '<tr><th>'.esc_html($label).'</th><td><input class="regular-text" name="'.esc_attr($k).'" value="'.esc_attr($c[$k]).'"></td></tr>';
  echo '<tr><th>感谢页语言路径</th><td><textarea name="thank_you_paths" rows="5" cols="65">'.esc_textarea(wp_json_encode((object)$c['thank_you_paths'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</textarea><p>空对象使用 TranslatePress 实际语言路径。例：{"default":"/thank-you/","es":"/es/thank-you/"}。</p></td></tr></table><p><label><input type="checkbox" name="enabled" '.checked($c['enabled'],true,false).'>接管新询盘转化</label></p><p><label><input type="checkbox" name="enhanced" '.checked($c['enhanced'],true,false).'>增强型转化：已同意的邮箱和有效国际电话 SHA256</label></p>';submit_button('保存转化配置');echo '</form><p>站点：'.esc_html($c['site_host']).'；接管边界：'.absint($c['cutover_id']).'；网站时区：'.esc_html(wp_timezone_string()).'。发送记录使用 UTC，不补发历史询盘。</p><p>claimed 表示取得一次发送资格；callback 仅为标签回调，不代表 Google 入账。按编号对账，GA4 导入与 Ads 原生不可相加。</p></div>';
 }
}

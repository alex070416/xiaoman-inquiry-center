<?php
if(!defined('ABSPATH'))exit;
final class XI_Config {
 const OPTION='xi_config_v2';
 static function defaults(){return array('enabled'=>false,'crm'=>true,'api'=>'https://api-sandbox.xiaoman.cn','number_prefix'=>'XI-N','post_type'=>'products','taxonomies'=>array(),'record_meta'=>'xi_native_record','queue_prefix'=>'xi_','queue_cursor'=>'xi_capture_cursor_v1','queue_cpt'=>'xi_job','legacy_job'=>'','legacy_sweep'=>'','legacy_group'=>'','legacy_cap'=>'','legacy_role'=>'','consent_service'=>'xiaoman-inquiry-attribution','origin_id'=>'','origin_name'=>'','product_field'=>'','level_field'=>'','level_value'=>'','products'=>array(),'product_lead_names'=>array(),'equipment_labels'=>array(),'excluded_terms'=>array(),'field_aliases'=>array(),'secrets'=>array(),'migration'=>array());}
 static function get(){ $v=get_option(self::OPTION,array());return wp_parse_args(is_array($v)?$v:array(),self::defaults()); }
 static function credential($name){
  $constant=$name==='id'?'XIAOMAN_CLIENT_ID':'XIAOMAN_CLIENT_SECRET';
  if(defined($constant)&&constant($constant))return (string)constant($constant);
  $v=self::get()['secrets'][$name]??'';
  if(!$v||!function_exists('sodium_crypto_secretbox_open'))return '';
  $raw=base64_decode($v,true); if(!$raw||strlen($raw)<40)return '';
  try {$plain=sodium_crypto_secretbox_open(substr($raw,24),substr($raw,0,24),hash('sha256',wp_salt('auth').'xiaoman-inquiry-secrets',true));return $plain===false?'':$plain;}catch(Throwable $e){return '';}
 }
 static function encrypt($value){
  if(!function_exists('sodium_crypto_secretbox'))return new WP_Error('crypto','服务器缺少 Sodium，无法保存加密密钥。');
  $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  return base64_encode($nonce.sodium_crypto_secretbox($value,$nonce,hash('sha256',wp_salt('auth').'xiaoman-inquiry-secrets',true)));
 }
 static function boot(){ add_action('admin_menu',array(__CLASS__,'menu'),45);add_action('admin_post_xi_config_save',array(__CLASS__,'save'));add_action('admin_notices',array(__CLASS__,'notice')); }
 static function menu(){
  $parent=class_exists('XI_Native_Inquiry_Center',false)?XI_Native_Inquiry_Center::PAGE:'options-general.php';
  add_submenu_page($parent,'小满询盘设置','小满询盘设置','manage_options','xiaoman-inquiry-settings',array(__CLASS__,'page'));
 }
 static function notice(){if(!current_user_can('manage_options'))return;
  if(XI_Compatibility::legacy_active())echo '<div class="notice notice-warning"><p>小满统一插件处于配置准备状态。请导入并核对原配置、备份后停用旧询盘插件，再启用统一处理。</p></div>';
 }
 static function save(){
  if(!current_user_can('manage_options'))wp_die('没有配置权限。', '', array('response'=>403));check_admin_referer('xi_config_save');
  $c=self::get();
  if(isset($_POST['import_legacy'])){
   $c=XI_Compatibility::import_profile();
   if(is_wp_error($c))wp_die(esc_html($c->get_error_message()));
  } else {
   foreach(array('origin_id','product_field','level_field') as $key){$v=sanitize_text_field(wp_unslash($_POST[$key]??''));if($v!==''&&!preg_match('/^[1-9][0-9]*$/D',$v))wp_die('字段 ID 格式无效。');$c[$key]=$v;}
   foreach(array('origin_name','level_value') as $key)$c[$key]=sanitize_text_field(wp_unslash($_POST[$key]??''));
   $c['post_type']=sanitize_key($_POST['post_type']??$c['post_type']);
   if(!post_type_exists($c['post_type']))wp_die('产品文章类型不存在。');
   $c['taxonomies']=array_values(array_filter(array_map('sanitize_key',preg_split('/[\s,]+/',wp_unslash($_POST['taxonomies']??''))),static function($t)use($c){return taxonomy_exists($t)&&is_object_in_taxonomy($c['post_type'],$t);}));
   foreach(array('products','product_lead_names','equipment_labels','field_aliases') as $key){$raw=json_decode(wp_unslash($_POST[$key]??'{}'),true);if(!is_array($raw))wp_die('映射必须为有效 JSON 对象。');$c[$key]=array();foreach($raw as $k=>$v)if(is_string($k)&&is_string($v))$c[$key][sanitize_text_field($k)]=sanitize_text_field($v);}
   foreach(array('id','secret') as $key){$v=trim(wp_unslash($_POST['client_'.$key]??''));if($v!==''){$encrypted=self::encrypt($v);if(is_wp_error($encrypted))wp_die($encrypted->get_error_message());$c['secrets'][$key]=$encrypted;}}
   $c['enabled']=isset($_POST['enabled']);
   if($c['enabled']&&XI_Compatibility::legacy_active())wp_die('请先备份并停用旧询盘插件，避免重复提交。');
  }
  update_option(self::OPTION,$c,false);delete_transient('xi_lead_token_v1');
  wp_safe_redirect(admin_url('admin.php?page=xiaoman-inquiry-settings&updated=1'));exit;
 }
 static function page(){
  if(!current_user_can('manage_options'))return;$c=self::get();
  echo '<div class="wrap"><h1>小满询盘中心 · '.esc_html(XI_VERSION).'</h1>';
  if(isset($_GET['updated']))echo '<div class="notice notice-success"><p>配置已保存。</p></div>';
  echo '<p>Bricks 原生 Form → 本地保存 → 小满后台同步。请先完成配置核对再启用。</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
  wp_nonce_field('xi_config_save');echo '<input type="hidden" name="action" value="xi_config_save"><table class="form-table">';
  foreach(array('origin_id'=>'小满来源 ID','origin_name'=>'线索名称来源词','product_field'=>'意向产品字段 ID','level_field'=>'客户等级字段 ID（可选）','level_value'=>'客户等级选项（可选）','post_type'=>'产品文章类型') as $key=>$label)echo '<tr><th>'.esc_html($label).'</th><td><input class="regular-text" name="'.esc_attr($key).'" value="'.esc_attr($c[$key]).'"></td></tr>';
  echo '<tr><th>产品分类</th><td><input class="large-text" name="taxonomies" value="'.esc_attr(implode(',',$c['taxonomies'])).'"></td></tr>';
  foreach(array('products'=>'产品到小满选项映射','product_lead_names'=>'产品到线索名称词映射','equipment_labels'=>'分类 slug 到产品类别名称','field_aliases'=>'自定义表单字段名到 F1/F2/F3/F4/F5/F6/F9') as $key=>$label)echo '<tr><th>'.esc_html($label).'</th><td><textarea name="'.esc_attr($key).'" rows="5" cols="70">'.esc_textarea(wp_json_encode((object)$c[$key],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</textarea></td></tr>';
  foreach(array('id'=>'Client ID','secret'=>'Client Secret') as $key=>$label)echo '<tr><th>'.esc_html($label).'</th><td><input type="password" autocomplete="new-password" name="client_'.esc_attr($key).'" value=""><span> '.(self::credential($key)?'已配置，留空保留':'尚未配置').'</span></td></tr>';
  echo '</table><label><input type="checkbox" name="enabled" value="1" '.checked($c['enabled'],true,false).'>启用统一询盘处理</label>';
  submit_button('保存设置');if(XI_Compatibility::legacy_active())submit_button('导入原询盘配置','secondary','import_legacy');echo '</form>';
  echo '<hr><h2>环境检查</h2><pre>'.esc_html(wp_json_encode(self::health(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</pre>';
  echo '<h2>Cookies 配置</h2><p>同意服务缺失时仍可提交询盘；跨页面广告归因仅在用户同意后启用。</p><pre>'.esc_html(wp_json_encode(XI_Cookies::definitions(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre>';
  if(post_type_exists('rcb-cookie')){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="xi_cookie_prepare">';wp_nonce_field('xi_cookie_prepare');submit_button('准备 Cookies 服务配置','secondary');echo '</form>';}
  echo '<p>升级源：<a href="https://github.com/alex070416/xiaoman-inquiry-center/releases">GitHub Releases</a>。测试版需人工升级，正式稳定版可使用 WordPress 自动更新。</p></div>';
 }
 static function health(){
  $c=self::get();return array('plugin'=>XI_VERSION,'wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'bricks'=>defined('BRICKS_VERSION')?BRICKS_VERSION:'missing','https'=>is_ssl(),'credentials'=>array('id'=>(bool)self::credential('id'),'secret'=>(bool)self::credential('secret')),'storage'=>class_exists('XI_Native_Inquiry_Center',false)?XI_Native_Inquiry_Center::exists():null,'queue'=>function_exists('as_enqueue_async_action'),'cron_disabled'=>defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON,'last_recovery'=>get_option($c['queue_prefix'].'last_recovery_v1',0),'consent'=>XI_Cookies::status(),'legacy_conflict'=>XI_Compatibility::legacy_active(),'migration'=>$c['migration']);
 }
}

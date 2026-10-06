<?php
if(!defined('ABSPATH'))exit;
final class XI_Cookies {
 static function boot(){add_action('admin_post_xi_cookie_prepare',array(__CLASS__,'prepare'));}
 static function definitions(){
  $host=(string)wp_parse_url(home_url(),PHP_URL_HOST);return array(
   array('type'=>'local','name'=>'xi_first_v1','host'=>$host,'duration'=>30,'durationUnit'=>'d'),
   array('type'=>'local','name'=>'xi_marketing_v1','host'=>$host,'duration'=>30,'durationUnit'=>'d'),
   array('type'=>'session','name'=>'xi_visit_v1','host'=>$host,'duration'=>30,'durationUnit'=>'m'),
   array('type'=>'session','name'=>'xi_session_ad_v1','host'=>$host,'duration'=>30,'durationUnit'=>'m'));
 }
 static function status(){
  if(!post_type_exists('rcb-cookie'))return array('state'=>'not_installed','cross_page_attribution'=>'disabled');
  $service=XI_Config::get()['consent_service'];$p=get_posts(array('post_type'=>'rcb-cookie','post_status'=>array('publish','draft'),'posts_per_page'=>2,'meta_key'=>'uniqueName','meta_value'=>$service));
  if(count($p)!==1)return array('state'=>count($p)?'duplicate_service':'service_missing','service'=>$service);
  $definitions=json_decode(get_post_meta($p[0]->ID,'technicalDefinitions',true),true);$names=array_column(is_array($definitions)?$definitions:array(),'name');$missing=array_diff(array_column(self::definitions(),'name'),$names);
  return array('state'=>$p[0]->post_status==='publish'&&!$missing?'ready':'configuration_incomplete','service'=>$service,'service_id'=>$p[0]->ID,'missing_keys'=>array_values($missing));
 }
 static function prepare(){
  if(!current_user_can('manage_options'))wp_die('没有权限。','',array('response'=>403));check_admin_referer('xi_cookie_prepare');
  if(!post_type_exists('rcb-cookie'))wp_die('Real Cookie Banner 尚未安装。');
  $status=self::status();if(($status['state']??'')==='duplicate_service')wp_die('存在重名同意服务，请先核对。');
  $id=$status['service_id']??0;$service=XI_Config::get()['consent_service'];
  // New consent declarations remain drafts for the site owner to review/publish.
  if(!$id)$id=wp_insert_post(array('post_type'=>'rcb-cookie','post_status'=>'draft','post_title'=>'Xiaoman inquiry attribution','post_content'=>'Stores first entry, current visit and advertising parameters after consent. Local storage expires after 30 days; visit storage expires after 30 minutes.'),true);
  if(is_wp_error($id))wp_die($id->get_error_message());
  $existing=json_decode(get_post_meta($id,'technicalDefinitions',true),true);$merged=is_array($existing)?$existing:array();
  // Preserve legacy and site-specific declarations during a plugin upgrade.
  foreach(self::definitions() as $definition){$found=false;foreach($merged as &$item)if(($item['name']??'')===$definition['name']&&($item['type']??'')===$definition['type']){$item=array_merge($item,$definition);$found=true;}unset($item);if(!$found)$merged[]=$definition;}
  update_post_meta($id,'uniqueName',$service);update_post_meta($id,'technicalDefinitions',wp_slash(wp_json_encode($merged)));update_post_meta($id,'deleteTechnicalDefinitionsAfterOptOut',true);
  wp_safe_redirect(get_edit_post_link($id,'raw'));exit;
 }
}

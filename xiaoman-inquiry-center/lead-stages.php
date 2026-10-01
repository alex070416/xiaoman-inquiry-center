<?php
if(!defined('ABSPATH'))exit;
/** Local funnel ledger only. No customer-data or offline conversion uploads. */
final class XI_Lead_Stages {
 static function table(){global $wpdb;return $wpdb->prefix.'xi_lead_stages';}
 static function boot(){add_action('admin_menu',array(__CLASS__,'menu'),47);add_action('admin_post_xi_lead_stage',array(__CLASS__,'save'));}
 static function setup(){
  global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$t=self::table();$charset=$wpdb->get_charset_collate();
  dbDelta("CREATE TABLE $t (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   submission_id bigint(20) unsigned NOT NULL,
   stage varchar(12) NOT NULL,
   occurred_utc datetime NOT NULL,
   recorded_utc datetime NOT NULL,
   crm_lead_id varchar(32) NOT NULL DEFAULT '',
   customer_grade char(1) NOT NULL DEFAULT '',
   order_id varchar(80) NOT NULL DEFAULT '',
   actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
   PRIMARY KEY  (id),
   UNIQUE KEY inquiry_stage (submission_id,stage),
   KEY occurred_utc (occurred_utc)
  ) $charset;");
  return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($t)))===$t;
 }
 static function record($id,$stage,$at,$grade='',$order=''){
  $id=absint($id);$saved=XI_Native_Inquiry_Center::row($id);$r=XI_Native_Inquiry_Center::record($saved);
  if(!$saved||!$r||XI_Native_Inquiry_Trash::has($id)||!in_array($stage,array('initial','qualified','converted'),true))return new WP_Error('inquiry','Invalid inquiry or stage');
  if($stage!=='initial'&&!current_user_can('manage_options'))return new WP_Error('permission','Administrator verification required');
  $q=XI_Native_Xiaoman_Sync::row($id);$lead=(string)($q['lead_id']??'');
  if($stage!=='initial'&&(($q['status']??'')!=='success'||!preg_match('/^[1-9][0-9]*$/D',$lead)))return new WP_Error('crm','Verify the CRM record first');
  if($stage==='qualified'&&!in_array($grade,array('A','B','C'),true))return new WP_Error('grade','Only verified ABC customers qualify');
  if($stage==='converted'&&(!is_string($order)||!preg_match('/^[A-Za-z0-9_.\/-]{1,80}$/D',$order)))return new WP_Error('order','Verified order ID required');
  if($stage==='initial')$at=($r['submitted_at']??'').' +00:00';
  // Require an explicit UTC offset in manual CRM times. Never infer a report timezone.
  if(!is_string($at)||!preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/D',$at))return new WP_Error('timezone','Explicit timezone required');
  try{$d=new DateTimeImmutable($at);$stamp=$d->getTimestamp();}catch(Throwable $e){return new WP_Error('date','Invalid date');}
  $submitted=strtotime(($r['submitted_at']??'').' UTC');if(!$submitted||$stamp<$submitted||$stamp>time()+300)return new WP_Error('date','Stage date outside valid range');
  global $wpdb;$existing=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table().' WHERE submission_id=%d AND stage=%s',$id,$stage));if($existing)return new WP_Error('duplicate','Stage already recorded; retained unchanged');
  $ok=$wpdb->insert(self::table(),array('submission_id'=>$id,'stage'=>$stage,'occurred_utc'=>gmdate('Y-m-d H:i:s',$stamp),'recorded_utc'=>gmdate('Y-m-d H:i:s'),'crm_lead_id'=>$lead,'customer_grade'=>$stage==='qualified'?$grade:'','order_id'=>$stage==='converted'?$order:'','actor_id'=>get_current_user_id()));
  return $ok?true:new WP_Error('storage','Stage save failed');
 }
 static function menu(){add_submenu_page(XI_Native_Inquiry_Center::PAGE,'线索阶段','线索阶段','manage_options','xiaoman-lead-stages',array(__CLASS__,'page'));}
 static function save(){
  if(!current_user_can('manage_options'))wp_die('Forbidden','',array('response'=>403));check_admin_referer('xi_lead_stage');
  if(!self::setup())wp_die('Storage unavailable');$number=sanitize_text_field(wp_unslash($_POST['inquiry_id']??''));$prefix=XI_PROFILE['number_prefix'];$tail=substr($number,strlen($prefix));
  if(strpos($number,$prefix)!==0||!preg_match('/^[1-9][0-9]*$/D',$tail))wp_die('Invalid inquiry number');
  $out=self::record((int)$tail,sanitize_key($_POST['stage']??''),sanitize_text_field(wp_unslash($_POST['occurred_at']??'')),strtoupper(sanitize_text_field($_POST['grade']??'')),sanitize_text_field(wp_unslash($_POST['order_id']??'')));
  if(is_wp_error($out))wp_die(esc_html($out->get_error_message()));wp_safe_redirect(admin_url('admin.php?page=xiaoman-lead-stages&updated=1'));exit;
 }
 static function page(){
  if(!current_user_can('manage_options'))return;echo '<div class="wrap"><h1>线索阶段</h1><p>初次表单、已核实的 ABC 合格客户、已核实的订单成交分别记录。不会向 Google 自动上传客户资料或补发浏览器转化。</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="xi_lead_stage">';wp_nonce_field('xi_lead_stage');
  echo '<p>询盘编号 <input name="inquiry_id" required placeholder="'.esc_attr(XI_PROFILE['number_prefix']).'123"></p><p>阶段 <select name="stage"><option value="qualified">合格线索：小满 ABC 客户</option><option value="converted">成交线索：小满订单</option></select></p><p>实际发生时间（含时区）<input class="regular-text" name="occurred_at" required placeholder="2026-10-01T18:00:00+08:00"></p><p>已核实等级 <select name="grade"><option value="">请选择</option><option>A</option><option>B</option><option>C</option></select></p><p>已核实订单编号 <input name="order_id"></p>';submit_button('记录已核实阶段');echo '</form><p>请先在小满核对等级、订单及时间。同一询盘的同一阶段只保存一次，初次提交不代表合格或成交。</p></div>';
 }
}

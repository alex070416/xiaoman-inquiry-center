<?php
if(!defined('ABSPATH'))exit;
final class XI_Compatibility {
 static function legacy_active(){
  $names=array('fangwei-inquiry-center','fangwei-xiaoman-sync','huajing-native-recovery','huajing-native-inquiry','anxing-native-inquiry','rongfeng-inquiry-center','tongqu-native-inquiry','unified-inquiry-dashboard','native-inquiry-required','fangwei-form-required');
  $active=array_merge(get_option('active_plugins',array()),array_keys(get_site_option('active_sitewide_plugins',array())));$out=array();
  foreach($active as $file)if(in_array(explode('/',$file)[0],$names,true))$out[]=$file;
  if(!$out&&(class_exists('FW_Native_Inquiry_Center',false)||class_exists('NI_Native_Inquiry_Center',false)))$out[]='legacy-inline-code';return $out;
 }
 static function packet($request){
  foreach(array('xi_context','fwxm_context','nixm_context') as $key){$v=is_array($request)?($request[$key]??''):'';if(!is_string($v)||$v==='')$v=isset($_POST[$key])&&is_string($_POST[$key])?wp_unslash($_POST[$key]):'';if(is_string($v)&&$v!==''&&strlen($v)<=24000)return $v;}return '';
 }
 static function history_valid($source){$at=$source['at']??0;$now=microtime(true)*1000;return is_numeric($at)&&$at>0&&$at<=$now&&$now-$at<30*DAY_IN_SECONDS*1000;}
 static function import_profile(){
  $prepared=self::prepare_import();return is_wp_error($prepared)?$prepared:$prepared['config'];
 }
 // Preparation never persists either option. The settings save handler owns the
 // config/required writes after validating the entire prepared import.
 static function prepare_import(){return self::build_import(true);}
 // Safe for local readiness output: do not read, encrypt or return credentials.
 static function preview_profile(){
  $prepared=self::build_import(false);if(is_wp_error($prepared))return $prepared;
  unset($prepared['config']['secrets']);$prepared['config']['migration']['status']='preview';return $prepared;
 }
 private static function build_import($with_secrets){
  $saved=get_option(XI_Config::OPTION,array());$has_saved=$saved!==array()&&$saved!==null&&$saved!==false;
  if($has_saved&&(!is_array($saved)||($saved['enabled']??null)!==false||!is_array($saved['migration']??null)||($saved['migration']['status']??null)!=='import_preparing'))return new WP_Error('legacy_config_exists','已有统一询盘配置，请保留现有配置并单独核对迁移。');
  try{
   // A manual retry may resume only the same disabled preparation. Compare the
   // complete public profile before reading credentials; never replace drifted data.
   $prepared=self::read_import();if(is_wp_error($prepared))return $prepared;
   if($has_saved&&!self::same_preparation($saved,$prepared['config']))return new WP_Error('legacy_config_changed','准备中的配置与原配置不一致，请保留现有配置并单独核对迁移。');
   if($with_secrets&&$has_saved){if(!is_array($saved['secrets']??array()))return new WP_Error('legacy_credentials','准备中的凭据无法读取，请先恢复本站密钥读取环境后重试。');$prepared['config']['secrets']=$saved['secrets']??array();}
   if($with_secrets)foreach(array('id','secret') as $name){
    $value=XI_Config::credential($name);
    if($has_saved&&$value===''&&!empty($saved['secrets'][$name]))return new WP_Error('legacy_credentials','准备中的凭据无法读取，请先恢复本站密钥读取环境后重试。');
    if($value!==''){$enc=XI_Config::encrypt($value);if(is_wp_error($enc))return $enc;$prepared['config']['secrets'][$name]=$enc;}
   }
   return $prepared;
  }catch(Throwable $e){return new WP_Error('legacy_profile','原询盘配置读取失败，请先核对原模块。');}
 }
 private static function same_preparation($saved,$candidate){
  $required_hash=$saved['migration']['required_hash']??null;
  if(!is_string($required_hash)||!preg_match('/^[a-f0-9]{64}$/D',$required_hash)||!hash_equals($required_hash,$candidate['migration']['required_hash']??''))return false;
  $from=$saved['migration']['from']??null;$current=$candidate['migration']['from']??null;
  if(!is_array($from)||!is_array($current))return false;
  foreach(array_merge($from,$current) as $source)if(!is_string($source))return false;
  $from=array_values(array_unique($from));$current=array_values(array_unique($current));sort($from,SORT_STRING);sort($current,SORT_STRING);
  if($from!==$current)return false;
  unset($saved['secrets'],$saved['migration'],$candidate['secrets'],$candidate['migration']);
  return self::same_value($saved,$candidate);
 }
 private static function same_value($a,$b,$depth=0){
  if($depth>32||gettype($a)!==gettype($b))return false;
  if(!is_array($a))return (is_scalar($a)||$a===null)&&$a===$b;
  if(count($a)!==count($b))return false;
  foreach($a as $key=>$value)if(!array_key_exists($key,$b)||!self::same_value($value,$b[$key],$depth+1))return false;
  return true;
 }
 private static function canonical_value($value,$depth=0){
  if($depth>32||(!is_array($value)&&!is_scalar($value)&&$value!==null))throw new RuntimeException('Invalid legacy required configuration');
  if(is_array($value)){ksort($value,SORT_STRING);foreach($value as $key=>$item)$value[$key]=self::canonical_value($item,$depth+1);}
  return $value;
 }
 private static function read_import(){
  $c=XI_Config::defaults();$prefix=class_exists('FW_Native_Inquiry_Center',false)?'FW':(class_exists('NI_Native_Inquiry_Center',false)?'NI':'');
  if(!$prefix)return new WP_Error('legacy','未找到可迁移的原询盘中心。');
  $center=$prefix.'_Native_Inquiry_Center';$sync=$prefix.'_Native_Xiaoman_Sync';$provider=$prefix.'_Xiaoman_Queue_V1';
  if(!class_exists($sync,false)||!class_exists($provider,false))return new WP_Error('legacy','原询盘模块不完整，请先核对。');
  $p=new ReflectionClass($provider);
  $c['record_meta']=$center::META;$c['legacy_cap']=$center::CAP;$c['legacy_role']=defined($center.'::ROLE')?$center::ROLE:str_replace('-inquiry-center','_inquiry_viewer',$center::PAGE);
  $c['legacy_job']=$sync::JOB;$c['legacy_sweep']=$sync::SWEEP;$c['legacy_group']=$sync::GROUP;$c['queue_cursor']=$sync::CURSOR;
  $key=$sync::key(1);$c['queue_prefix']=substr($key,0,-strlen('job_v1_1'));$c['queue_cpt']=rtrim($c['queue_prefix'],'_').'_job';
  $c['number_prefix']=preg_replace('/1$/','',$center::number(1));
  if($center::PAGE==='rongfeng-inquiry-center')$c['excluded_terms']=array('tractor-attachments');
  $profile=defined('NI_PROFILE')?NI_PROFILE:array('taxonomies'=>array('product-category'));
  $c['taxonomies']=$profile['taxonomies']??array();
  $required=null;foreach(array('FWFR_Conditional_Required','NIFR_Conditional_Required') as $class)if(class_exists($class,false)&&is_callable(array($class,'options'))){$required=$class::options();break;}
  if(!$required){foreach(array('fwfr_conditional_required_options','nifr_conditional_required_options') as $option){$v=get_option($option);if(is_array($v)){$required=$v;break;}}}
  if($required!==null&&!is_array($required))return new WP_Error('legacy_required','原必填字段配置无效，请先核对。');
  if(is_array($required)){if(!is_string($required['post_type']??null)||$required['post_type']==='')return new WP_Error('legacy_required','原必填字段配置缺少产品文章类型。');$c['post_type']=$required['post_type'];}
  if($center::PAGE==='rongfeng-inquiry-center'&&class_exists('RF_Inquiry_Config',false))$c=array_merge($c,array_intersect_key(RF_Inquiry_Config::get(),array_flip(array('origin_id','origin_name','product_field','level_field','level_value','products'))));
  foreach(array('ORIGIN'=>'origin_id','ORIGIN_NAME'=>'origin_name','PRODUCT_FIELD'=>'product_field','CUSTOMER_LEVEL_FIELD'=>'level_field','CUSTOMER_LEVEL'=>'level_value','API'=>'api') as $old=>$new)if($p->hasConstant($old))$c[$new]=$p->getConstant($old);
  // Older Fangwei uses term names directly and has no equipment() method.
  // Preserve both CRM option values and the separate words used in lead names.
  $labels=array('Other');
  foreach($c['taxonomies'] as $tax){$terms=get_terms(array('taxonomy'=>$tax,'hide_empty'=>false));if(is_wp_error($terms)||!is_array($terms))return new WP_Error('legacy_terms','无法读取原产品分类映射，请先核对。');foreach($terms as $term){$label=is_callable(array($center,'equipment'))?$center::equipment($term):$center::text($term->name,150);$c['equipment_labels'][$term->slug]=$label;$labels[]=$label;}}
  // Quick form options can include labels absent from the product taxonomy.
  global $wpdb;
  $forms=$wpdb->get_results("SELECT m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_status='publish' AND m.meta_key IN ('_bricks_page_content_2','_bricks_page_header_2','_bricks_page_footer_2')",ARRAY_A);
  if($wpdb->last_error||!is_array($forms))return new WP_Error('legacy_forms','无法读取原生表单映射，请先核对数据库。');
  foreach($forms as $row){$elements=maybe_unserialize($row['meta_value']);if(!is_array($elements))continue;foreach($elements as $element){if(($element['name']??'')!=='form')continue;foreach(($element['settings']['fields']??array()) as $field){if(!in_array($field['name']??'',array('Equipment','F1'),true))continue;foreach(preg_split('/\r\n|\r|\n/',$field['options']??'') as $label){if(!empty($field['valueLabelOptions']))$label=explode(':',$label,2)[0];if(trim($label)!=='')$labels[]=trim($label);}}}}
  foreach(array_unique($labels) as $label)if(is_callable(array($provider,'product_mapping'))){$m=$provider::product_mapping($label);$key=strtolower(trim($label));if(is_array($m)){if(isset($m['product_name'])&&$m['product_name']!=='')$c['products'][$key]=$m['product_name'];if(isset($m['lead_name'])&&$m['lead_name']!=='')$c['product_lead_names'][$key]=$m['lead_name'];}}
  $c['enabled']=false;$c['migration']=array('version'=>1,'at'=>gmdate('c'),'from'=>XI_Compatibility::legacy_active(),'status'=>'imported','record_count'=>0,'required_hash'=>hash('sha256',serialize(self::canonical_value($required))));
  return array('config'=>$c,'required'=>$required);
 }
 static function runtime(){
  add_action('plugins_loaded',array(__CLASS__,'halt_conflicting_runtime'),999);
  $c=XI_Config::get();
  if($c['legacy_job']&&$c['legacy_job']!==XI_Native_Xiaoman_Sync::JOB)add_action($c['legacy_job'],array('XI_Native_Xiaoman_Sync','send'),10,1);
  if($c['legacy_sweep']&&$c['legacy_sweep']!==XI_Native_Xiaoman_Sync::SWEEP)add_action($c['legacy_sweep'],array('XI_Native_Xiaoman_Sync','recover'));
 }
 static function halt_conflicting_runtime(){
  if(!self::legacy_active())return;global $wp_filter;
  foreach($wp_filter as $tag=>$hook)foreach($hook->callbacks as $priority=>$callbacks)foreach($callbacks as $item){$callback=$item['function'];if(!is_array($callback)||!is_string($callback[0]))continue;$class=$callback[0];if(in_array($class,array('XI_Native_Inquiry_Center','XI_Native_Xiaoman_Sync','XI_Dashboard','XI_Bricks','XI_Request_Attribution_V1','XI_Xiaoman_Queue_V1','XIFR_Conditional_Required'),true))remove_filter($tag,$callback,$priority);}
 }
 static function restrict_service_caps($allcaps,$caps,$args,$user){
  // Legacy customer-service roles sometimes store administrator capabilities.
  // Keep their effective permission limited when legacy filters are removed.
  $c=XI_Config::get();$roles=(array)$user->roles;
  foreach(array('FW_Native_Inquiry_Center','NI_Native_Inquiry_Center') as $legacy)if(class_exists($legacy,false)){
   if(!$c['legacy_cap'])$c['legacy_cap']=$legacy::CAP;
   if(!$c['legacy_role'])$c['legacy_role']=defined($legacy.'::ROLE')?$legacy::ROLE:str_replace('-inquiry-center','_inquiry_viewer',$legacy::PAGE);
  }
  if(!preg_match('/(?:_inquiry_view$|^bricks_form_submission_access$)/D',$c['legacy_cap']))$c['legacy_cap']='';
  $restricted=in_array('kefu',$roles,true)||in_array('xiaoman_inquiry_viewer',$roles,true)||($c['legacy_role']&&in_array($c['legacy_role'],$roles,true));
  if($restricted&&!in_array('administrator',$roles,true)){foreach($allcaps as $cap=>$grant)$allcaps[$cap]=in_array($cap,array('read','xiaoman_inquiry_view',$c['legacy_cap']),true);$allcaps['xiaoman_inquiry_view']=true;$allcaps['read']=true;}
  elseif($c['legacy_cap']&&!empty($allcaps[$c['legacy_cap']]))$allcaps['xiaoman_inquiry_view']=true;
  return $allcaps;
 }
}

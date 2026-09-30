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
  $c=XI_Config::defaults();$prefix=class_exists('FW_Native_Inquiry_Center',false)?'FW':(class_exists('NI_Native_Inquiry_Center',false)?'NI':'');
  if(!$prefix)return new WP_Error('legacy','未找到可迁移的原询盘中心。');
  $center=$prefix.'_Native_Inquiry_Center';$sync=$prefix.'_Native_Xiaoman_Sync';$provider=$prefix.'_Xiaoman_Queue_V1';
  if(!class_exists($sync,false)||!class_exists($provider,false))return new WP_Error('legacy','原询盘模块不完整，请先核对。');
  $q=new ReflectionClass($sync);$p=new ReflectionClass($provider);
  $c['record_meta']=$center::META;$c['legacy_cap']=$center::CAP;$c['legacy_role']=defined($center.'::ROLE')?$center::ROLE:str_replace('-inquiry-center','_inquiry_viewer',$center::PAGE);
  $c['legacy_job']=$sync::JOB;$c['legacy_sweep']=$sync::SWEEP;$c['legacy_group']=$sync::GROUP;$c['queue_cursor']=$sync::CURSOR;
  $key=$sync::key(1);$c['queue_prefix']=substr($key,0,-strlen('job_v1_1'));$c['queue_cpt']=rtrim($c['queue_prefix'],'_').'_job';
  $c['number_prefix']=preg_replace('/1$/','',$center::number(1));
  if($center::PAGE==='rongfeng-inquiry-center')$c['excluded_terms']=array('tractor-attachments');
  $profile=defined('NI_PROFILE')?NI_PROFILE:array('taxonomies'=>array('product-category'));
  $c['taxonomies']=$profile['taxonomies']??array();
  $required='';foreach(array('FWFR_Conditional_Required','NIFR_Conditional_Required') as $class)if(class_exists($class,false)&&is_callable(array($class,'options'))){$required=$class::options();break;}
  if(!$required){foreach(array('fwfr_conditional_required_options','nifr_conditional_required_options') as $option){$v=get_option($option);if(is_array($v)){$required=$v;break;}}}
  if(is_array($required)){update_option('xi_required_v1',$required,false);$c['post_type']=$required['post_type'];}
  if(class_exists('RF_Inquiry_Config',false))$c=array_merge($c,RF_Inquiry_Config::get());
  foreach(array('ORIGIN'=>'origin_id','ORIGIN_NAME'=>'origin_name','PRODUCT_FIELD'=>'product_field','CUSTOMER_LEVEL_FIELD'=>'level_field','CUSTOMER_LEVEL'=>'level_value','API'=>'api') as $old=>$new)if($p->hasConstant($old))$c[$new]=$p->getConstant($old);
  // Derive labels/mapping through the existing pure lookup methods, never network calls.
  foreach($c['taxonomies'] as $tax){$terms=get_terms(array('taxonomy'=>$tax,'hide_empty'=>false));if(is_wp_error($terms))continue;foreach($terms as $term){$label=$center::equipment($term);$c['equipment_labels'][$term->slug]=$label;if(is_callable(array($provider,'product_mapping'))){$m=$provider::product_mapping($label);if(is_array($m)&&!empty($m['product_name']))$c['products'][strtolower($label)]=$m['product_name'];}}}
  foreach(array('id','secret') as $name){$value=XI_Config::credential($name);if($value!==''){$enc=XI_Config::encrypt($value);if(is_wp_error($enc))return $enc;$c['secrets'][$name]=$enc;}}
  $c['enabled']=false;$c['migration']=array('version'=>1,'at'=>gmdate('c'),'from'=>XI_Compatibility::legacy_active(),'status'=>'imported','record_count'=>0);
  return $c;
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

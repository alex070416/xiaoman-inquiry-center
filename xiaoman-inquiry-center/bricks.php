<?php
if(!defined('ABSPATH'))exit;
final class XI_Bricks {
 static function boot(){
  add_filter('bricks/elements/form/controls',array(__CLASS__,'controls'),20);
  add_filter('bricks/form/validate',array(__CLASS__,'order'),10,2);
  add_action('bricks/form/action/xiaoman-inquiry',array(__CLASS__,'action'));
 }
 static function controls($controls){
  if(isset($controls['actions']['options']))$controls['actions']['options']['xiaoman-inquiry']='小满询盘';
  $controls['xiInquiryKind']=array('tab'=>'content','group'=>'actions','type'=>'select','label'=>'小满询盘表单类型','options'=>array(''=>'自动识别原表单','quick'=>'快速询盘','detail'=>'产品询盘'),'required'=>array('actions','=','xiaoman-inquiry'));
  return $controls;
 }
 static function order($errors,$form){
  $s=$form->get_settings();$a=$s['actions']??array();$n=array_search('xiaoman-inquiry',$a,true);
  if($n===false)return $errors;$save=array_search('save-submission',$a,true);
  if($save===false||$save>$n)$errors[]='Inquiry configuration is unavailable. Please contact us directly.';
  if(!XI_Native_Inquiry_Center::kind($s))$errors[]='Inquiry fields are unavailable. Please contact us directly.';
  return $errors;
 }
 static function saved_id($form){
  $r=XI_Native_Inquiry_Center::$requests[XI_Native_Inquiry_Center::context_key($form)]??array();if(!$r)return 0;
  global $wpdb;return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.XI_Native_Inquiry_Center::table().' WHERE form_id=%s AND post_id=%d AND form_data LIKE %s LIMIT 1',$r['form_id'],$r['post_id'],'%'.$wpdb->esc_like($r['uuid']).'%'));
 }
 static function action($form){
  $id=self::saved_id($form);
  if(!$id){$form->set_result(array('action'=>'xiaoman-inquiry','type'=>'error','message'=>'Your inquiry could not be saved. Please try again.'));return;}
  // Persist first, then let the scheduler send. No CRM HTTP call on the request.
  try {XI_Native_Xiaoman_Sync::capture($id);}catch(Throwable $e){/* The sweep recovers the saved record. */}
  $form->set_result(array('action'=>'xiaoman-inquiry','type'=>'success','message'=>'Your inquiry has been received.'));
 }
}

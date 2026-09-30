<?php
/** Reversible inbox deletion without changing Bricks' submission schema. */
if(!defined('ABSPATH')) return;
final class XI_Native_Inquiry_Trash {
 const PREFIX='fwn_trash_v1_';
 static function key($id) {return self::PREFIX.absint($id);}
 static function has($id) {return (bool)get_option(self::key($id),false);}
 static function clause($trash=false) {
  global $wpdb;
  return ' AND '.($trash?'EXISTS':'NOT EXISTS').' (SELECT 1 FROM '.$wpdb->options.' fwn_trash WHERE fwn_trash.option_name=CONCAT(\''.self::PREFIX.'\', '.XI_Native_Inquiry_Center::table().'.id))';
 }
 static function change($id,$action) {
  if(!current_user_can('manage_options')) return new WP_Error('permission','没有删除或恢复询盘的权限。');
  if(!in_array($action,array('trash','restore','delete'),true)) return new WP_Error('action','操作无效。');
  $id=absint($id);$lock=XI_QUEUE_PREFIX.'lock_v1_'.$id;
  // Use the same lock as lead creation: do not delete a submission mid-request.
  if(!add_option($lock,time(),'',false)) return new WP_Error('busy','询盘正在同步，请稍后再试。');
  try {
   $saved=XI_Native_Inquiry_Center::row($id);
   if(!$saved || !XI_Native_Inquiry_Center::record($saved)) return new WP_Error('missing','未找到原生询盘。');
   $trashed=self::has($id);$job=XI_Native_Xiaoman_Sync::row($id);
   if($action==='trash') {
    if($trashed) return false;
    if(!add_option(self::key($id),array('at'=>gmdate('Y-m-d H:i:s'),'user_id'=>get_current_user_id()),'',false)) return new WP_Error('save','移入回收站失败。');
    if($job && in_array($job['status'],array('queued','retry','invalid','rejected','failed'),true)) {
     if(!XI_Native_Xiaoman_Sync::set($id,array('status'=>'trashed','paused_status'=>$job['status'],'paused_detail'=>$job['detail']??'','detail'=>'网站询盘已移入回收站，暂停同步'))) {
      delete_option(self::key($id));return new WP_Error('queue','暂停同步失败，请重试。');
     }
    }
    self::unschedule($id);return true;
   }
   if(!$trashed) return new WP_Error('not_trash','请先将询盘移入回收站。');
   if($action==='restore') {
    if(!delete_option(self::key($id))) return new WP_Error('restore','恢复询盘失败。');
    if(($job['status']??'')==='trashed') {
     $status=$job['paused_status']??'queued';
     if(!XI_Native_Xiaoman_Sync::set($id,array('status'=>$status,'detail'=>$job['paused_detail']??'询盘已恢复','next_at'=>max(time(),(int)($job['next_at']??0))))) {
      add_option(self::key($id),array('at'=>gmdate('Y-m-d H:i:s'),'user_id'=>get_current_user_id()),'',false);
      return new WP_Error('queue','恢复同步状态失败，请重试。');
     }
     if(in_array($status,array('queued','retry'),true)) XI_Native_Xiaoman_Sync::enqueue($id,max(0,(int)($job['next_at']??0)-time()));
    } elseif(!$job) XI_Native_Xiaoman_Sync::capture($id);
    return true;
   }
   global $wpdb;
   if(false===$wpdb->delete(XI_Native_Inquiry_Center::table(),array('id'=>$id),array('%d'))) return new WP_Error('delete','永久删除失败，请重试。');
   // Keep the minimal sync audit / lead ID, clear any pending contact payload.
   if($job) XI_Native_Xiaoman_Sync::set($id,array('status'=>'deleted','payload'=>'','paused_status'=>'','paused_detail'=>'','detail'=>'网站询盘已永久删除；小满已有线索保留'));
   self::unschedule($id);delete_option(self::key($id));delete_option(XI_QUEUE_PREFIX.'manual_v1_'.$id);return true;
  } finally {delete_option($lock);}
 }
 static function unschedule($id) {
  if(function_exists('as_unschedule_all_actions')) as_unschedule_all_actions(XI_Native_Xiaoman_Sync::JOB,array((int)$id),XI_Native_Xiaoman_Sync::GROUP);
 }
}

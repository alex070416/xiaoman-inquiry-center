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
  if(!function_exists('sodium_crypto_secretbox'))return new WP_Error('crypto','������ȱ�� Sodium���޷����������Կ��');
  $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  return base64_encode($nonce.sodium_crypto_secretbox($value,$nonce,hash('sha256',wp_salt('auth').'xiaoman-inquiry-secrets',true)));
 }
 static function boot(){ add_action('admin_menu',array(__CLASS__,'menu'),45);add_action('admin_post_xi_config_save',array(__CLASS__,'save'));add_action('admin_post_xi_config_inspect',array(__CLASS__,'inspect_page'));add_action('admin_notices',array(__CLASS__,'notice')); }
 static function api_base($value,$required=false){
  if(!is_string($value))return new WP_Error('api','�ӿڻ���ַ��Ч����ѡ���ṩ�Ļ�����');
  $value=trim($value);
  if($value===''&&!$required)return '';
  // Xiaoman documents this hostname as its production API, including "sandbox".
  if($value!=='https://api-sandbox.xiaoman.cn')return new WP_Error('api','����ѡ�񲢱�����Ч��С���ӿڻ���ַ��');
  return $value;
 }
 static function menu(){
  $parent=class_exists('XI_Native_Inquiry_Center',false)?XI_Native_Inquiry_Center::PAGE:'options-general.php';
  add_submenu_page($parent,'С��ѯ������','С��ѯ������','manage_options','xiaoman-inquiry-settings',array(__CLASS__,'page'));
 }
 static function notice(){if(!current_user_can('manage_options'))return;
  if(XI_Compatibility::legacy_active())echo '<div class="notice notice-warning"><p>С��ͳһ�����������׼��״̬���뵼�벢�˶�ԭ���á����ݺ�ͣ�þ�ѯ�̲����������ͳһ������</p></div>';
 }
 static function save(){
  if(!current_user_can('manage_options'))wp_die('û������Ȩ�ޡ�', '', array('response'=>403));check_admin_referer('xi_config_save');
  $c=self::get();
  if(isset($_POST['import_legacy'])){
   $c=XI_Compatibility::import_profile();
   if(is_wp_error($c))wp_die(esc_html($c->get_error_message()));
  } else {
   $api=self::api_base(wp_unslash($_POST['api']??$c['api']));if(is_wp_error($api))wp_die(esc_html($api->get_error_message()));$c['api']=$api;
   foreach(array('origin_id','product_field','level_field') as $key){$v=sanitize_text_field(wp_unslash($_POST[$key]??''));if($v!==''&&!preg_match('/^[1-9][0-9]*$/D',$v))wp_die('�ֶ� ID ��ʽ��Ч��');$c[$key]=$v;}
   foreach(array('origin_name','level_value') as $key)$c[$key]=sanitize_text_field(wp_unslash($_POST[$key]??''));
   $c['post_type']=sanitize_key($_POST['post_type']??$c['post_type']);
   if(!post_type_exists($c['post_type']))wp_die('��Ʒ�������Ͳ����ڡ�');
   $c['taxonomies']=array_values(array_filter(array_map('sanitize_key',preg_split('/[\s,]+/',wp_unslash($_POST['taxonomies']??''))),static function($t)use($c){return taxonomy_exists($t)&&is_object_in_taxonomy($c['post_type'],$t);}));
   foreach(array('products','product_lead_names','equipment_labels','field_aliases') as $key){$raw=json_decode(wp_unslash($_POST[$key]??'{}'),true);if(!is_array($raw))wp_die('ӳ�����Ϊ��Ч JSON ����');$c[$key]=array();foreach($raw as $k=>$v)if(is_string($k)&&is_string($v))$c[$key][sanitize_text_field($k)]=sanitize_text_field($v);}
   foreach(array('id','secret') as $key){$v=trim(wp_unslash($_POST['client_'.$key]??''));if($v!==''){$encrypted=self::encrypt($v);if(is_wp_error($encrypted))wp_die($encrypted->get_error_message());$c['secrets'][$key]=$encrypted;}}
   $c['enabled']=isset($_POST['enabled']);
   if(($_POST['crm_control_v1']??null)==='1')$c['crm']=isset($_POST['crm_enabled'])&&$_POST['crm_enabled']==='1';
   if($c['enabled']&&XI_Compatibility::legacy_active())wp_die('���ȱ��ݲ�ͣ�þ�ѯ�̲���������ظ��ύ��');
  }
  $api=self::api_base($c['api'],!empty($c['enabled']));if(is_wp_error($api))wp_die(esc_html($api->get_error_message()));$c['api']=$api;
  update_option(self::OPTION,$c,false);delete_transient('xi_lead_token_v1');
  wp_safe_redirect(admin_url('admin.php?page=xiaoman-inquiry-settings&updated=1'));exit;
 }
 static function configuration_text($value,$secrets){
  if(!is_scalar($value))return '';
  $value=(string)$value;
  foreach($secrets as $secret)if(is_string($secret)&&$secret!=='')$value=str_replace($secret,'[������]',$value);
  $value=sanitize_textarea_field($value);
  return function_exists('mb_substr')?mb_substr($value,0,1000):substr($value,0,1000);
 }
 static function configuration_options($value,$secrets,$key='',$depth=0){
  if($depth>12)return '[��ʡ�Թ�������]';
  if(preg_match('/token|secret|password|authorization|client[_-]?id|credential|cookie|customer|contact|email|phone|whatsapp/i',(string)$key))return '[������]';
  // ext_info is field configuration, sometimes a JSON string. Keep its schema intact.
  if(is_string($value)){
   $decoded=json_decode($value,true);
   if(is_array($decoded))return wp_json_encode(self::configuration_options($decoded,$secrets,'',$depth+1),JSON_UNESCAPED_UNICODE);
   return self::configuration_text($value,$secrets);
  }
  if(!is_array($value)){$clean=self::configuration_text($value,$secrets);return is_scalar($value)&&(string)$value===$clean?$value:$clean;}
  $clean=array();foreach($value as $child_key=>$item)$clean[self::configuration_text($child_key,$secrets)]=self::configuration_options($item,$secrets,$child_key,$depth+1);
  return $clean;
 }
 static function configuration_origins($data,$secrets,$depth=0){
  $found=array();if(!is_array($data)||$depth>12)return $found;
  $id=$data['id']??($data['value']??null);$name=$data['name']??($data['label']??null);
  if(is_scalar($data['origin_id']??null)&&is_scalar($data['origin_name']??null)){$id=$data['origin_id'];$name=$data['origin_name'];}
  if(is_scalar($id)&&is_scalar($name))$found[]=array('��Դ���'=>self::configuration_text($id,$secrets),'��Դ����'=>self::configuration_text($name,$secrets));
  foreach($data as $key=>$child)if(is_array($child)&&!preg_match('/customer|contact|token|secret|password|authorization|credential/i',(string)$key))$found=array_merge($found,self::configuration_origins($child,$secrets,$depth+1));
  return $found;
 }
 static function configuration_fields($data,$secrets,$depth=0){
  $found=array();if(!is_array($data)||$depth>12)return $found;
  if(in_array($data['name']??'',array('�����Ʒ','�ͻ��ȼ�'),true)){
   return array(array('�ֶα��'=>self::configuration_text($data['id']??'',$secrets),'�ֶ�����'=>self::configuration_text($data['name'],$secrets),'�ֶ�����'=>self::configuration_text($data['field_type']??'',$secrets),'ѡ������'=>self::configuration_options($data['ext_info']??array(),$secrets)));
  }
  foreach($data as $child)if(is_array($child))$found=array_merge($found,self::configuration_fields($child,$secrets,$depth+1));
  return $found;
 }
 static function configuration_get($api,$path,$token){
  $api=self::api_base($api,true);if(is_wp_error($api))return $api;
  if(!in_array($path,array('/v1/company/fields/selector?field=origin','/v1/lead/fields?type=lead'),true))return new WP_Error('endpoint','�ֶκ˶Զ˵���Ч��');
  $response=wp_remote_get($api.$path,array('timeout'=>15,'redirection'=>0,'limit_response_size'=>1048576,'reject_unsafe_urls'=>true,'sslverify'=>true,'headers'=>array('Accept'=>'application/json','Authorization'=>$token)));
  if(is_wp_error($response))return new WP_Error('network','�ֶ����ö�ȡʧ�ܣ����������쳣��');
  $http=(int)wp_remote_retrieve_response_code($response);$data=json_decode(wp_remote_retrieve_body($response),true);
  $code=is_array($data)&&isset($data['code'])&&is_numeric($data['code'])?(int)$data['code']:0;
  if($http<200||$http>=300||!is_array($data)||($code!==0&&$code!==200)||!array_key_exists('data',$data))return new WP_Error('metadata','�ֶ����ö�ȡʧ�ܣ��ӿ���Ӧ��Ч��״̬�� '.$http.'����');
  return $data['data'];
 }
 static function inspect_configuration(){
  if(!current_user_can('manage_options'))return new WP_Error('permission','û�к˶��ֶ����õ�Ȩ�ޡ�');
  $api=self::api_base(self::get()['api'],true);if(is_wp_error($api))return $api;
  $id=self::credential('id');$secret=self::credential('secret');
  if($id===''||$secret==='')return new WP_Error('auth','�������ò�����ͻ��˱����ͻ�����Կ��');
  try{
   // An independent token remains in this request only; ordinary lead tokens are untouched.
   $response=wp_remote_post($api.'/v1/oauth2/access_token',array('timeout'=>15,'redirection'=>0,'limit_response_size'=>65536,'reject_unsafe_urls'=>true,'sslverify'=>true,'headers'=>array('Content-Type'=>'application/json','Accept'=>'application/json'),'body'=>wp_json_encode(array('grant_type'=>'client_credentials','client_id'=>$id,'client_secret'=>$secret,'scope'=>'company lead'))));
   if(is_wp_error($response))return new WP_Error('auth','�ֶκ˶���Ȩʧ�ܣ����������쳣��');
   $http=(int)wp_remote_retrieve_response_code($response);$data=json_decode(wp_remote_retrieve_body($response),true);
   if($http!==200||!is_array($data)||empty($data['access_token'])||!is_string($data['access_token'])||strlen($data['access_token'])>8192||preg_match('/[\r\n]/',$data['access_token']))return new WP_Error('auth','�ֶκ˶���Ȩʧ�ܣ��ӿ���Ӧ��Ч��״̬�� '.$http.'����');
   $token=$data['access_token'];$secrets=array($token,$id,$secret);
   $origin=self::configuration_get($api,'/v1/company/fields/selector?field=origin',$token);
   $fields=self::configuration_get($api,'/v1/lead/fields?type=lead',$token);
   return array('��Դ����'=>is_wp_error($origin)?array('����'=>$origin->get_error_message()):self::configuration_origins($origin,$secrets),'����ӳ���ֶ�'=>is_wp_error($fields)?array('����'=>$fields->get_error_message()):self::configuration_fields($fields,$secrets));
  }catch(Throwable $e){return new WP_Error('metadata','�ֶκ˶�ʧ�ܣ�����������ӿ�Ȩ�޺����ԡ�');}
 }
 static function inspect_page(){
  nocache_headers();
  if(!current_user_can('manage_options'))wp_die('û�к˶��ֶ����õ�Ȩ�ޡ�','',array('response'=>403));
  check_admin_referer('xi_config_inspect');
  $result=self::inspect_configuration();
  $html='<h1>С���ֶ����ú˶�</h1><p>�������ѱ��滷������Դ���ֶ����ã�������Ա�˶ԡ���������ҳ�����д������ӳ�䡣</p>';
  $html.=is_wp_error($result)?'<p>'.esc_html($result->get_error_message()).'</p>':'<pre>'.esc_html(wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>';
  $html.='<p><a href="'.esc_url(admin_url('admin.php?page=xiaoman-inquiry-settings')).'">����С��ѯ������</a></p>';
  wp_die($html,'С���ֶ����ú˶�',array('response'=>200));
 }
 static function page(){
  if(!current_user_can('manage_options'))return;$c=self::get();
  echo '<div class="wrap"><h1>С��ѯ������ �� '.esc_html(XI_VERSION).'</h1>';
  if(isset($_GET['updated']))echo '<div class="notice notice-success"><p>�����ѱ��档</p></div>';
  echo '<p>Bricks ԭ�� Form �� ���ر��� �� С����̨ͬ��������������ú˶������á�</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
  wp_nonce_field('xi_config_save');echo '<input type="hidden" name="action" value="xi_config_save"><table class="form-table">';
  echo '<tr><th>�ӿڻ���ַ</th><td><select name="api">';
  foreach(array(''=>'��δ����','https://api-sandbox.xiaoman.cn'=>'С���ٷ��ӿڣ���ʽ��') as $value=>$label)echo '<option value="'.esc_attr($value).'" '.selected($c['api'],$value,false).'>'.esc_html($label).'</option>';
  echo '</select><p class="description">ʹ��С���ٷ��ĵ��г�����ʽ�ӿڡ�����ͳһ����ǰ����ѡ����Ч��ַ��</p></td></tr>';
  foreach(array('origin_id'=>'С����Դ ID','origin_name'=>'����������Դ��','product_field'=>'�����Ʒ�ֶ� ID','level_field'=>'�ͻ��ȼ��ֶ� ID����ѡ��','level_value'=>'�ͻ��ȼ�ѡ���ѡ��','post_type'=>'��Ʒ��������') as $key=>$label)echo '<tr><th>'.esc_html($label).'</th><td><input class="regular-text" name="'.esc_attr($key).'" value="'.esc_attr($c[$key]).'"></td></tr>';
  echo '<tr><th>��Ʒ����</th><td><input class="large-text" name="taxonomies" value="'.esc_attr(implode(',',$c['taxonomies'])).'"></td></tr>';
  foreach(array('products'=>'��Ʒ��С��ѡ��ӳ��','product_lead_names'=>'��Ʒ���������ƴ�ӳ��','equipment_labels'=>'���� slug ����Ʒ�������','field_aliases'=>'�Զ�������ֶ����� F1/F2/F3/F4/F5/F6/F9') as $key=>$label)echo '<tr><th>'.esc_html($label).'</th><td><textarea name="'.esc_attr($key).'" rows="5" cols="70">'.esc_textarea(wp_json_encode((object)$c[$key],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</textarea></td></tr>';
  foreach(array('id'=>'�ͻ��˱��','secret'=>'�ͻ�����Կ') as $key=>$label)echo '<tr><th>'.esc_html($label).'</th><td><input type="password" autocomplete="new-password" name="client_'.esc_attr($key).'" value=""><span> '.(self::credential($key)?'�����ã����ձ���':'��δ����').'</span></td></tr>';
  echo '</table><label><input type="checkbox" name="enabled" value="1" '.checked($c['enabled'],true,false).'>����ͳһѯ�̴���</label>';
  echo '<input type="hidden" name="crm_control_v1" value="1"><p><label><input type="checkbox" name="crm_enabled" value="1" '.checked($c['crm'],true,false).'>����С��ͬ��</label></p><p class="description">ȡ����ѡ����ͣ�������ŶӺͷ��ͣ�����;���������ɡ�������������˼�¼������</p>';
  submit_button('��������');if(XI_Compatibility::legacy_active())submit_button('����ԭѯ������','secondary','import_legacy');echo '</form>';
  echo '<hr><h2>С���ֶ����ú˶�</h2><p>�ȱ���ӿڻ���ַ��ƾ�ݣ��ٺ˶���Դ�������Ʒ���ͻ��ȼ��ֶΡ�ͳһѯ�̴����ر�ʱҲ�ɺ˶ԡ�</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="xi_config_inspect">';wp_nonce_field('xi_config_inspect');submit_button('ֻ���˶�С����Դ���ֶ�','secondary');echo '</form>';
  echo '<hr><h2>�������</h2><pre>'.esc_html(wp_json_encode(self::health(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</pre>';
  echo '<h2>Cookies ����</h2><p>ͬ�����ȱʧʱ�Կ��ύѯ�̣���ҳ�����������û�ͬ������á�</p><pre>'.esc_html(wp_json_encode(XI_Cookies::definitions(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre>';
  if(post_type_exists('rcb-cookie')){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="xi_cookie_prepare">';wp_nonce_field('xi_cookie_prepare');submit_button('׼�� Cookies ��������','secondary');echo '</form>';}
  echo '<p>����Դ��<a href="https://github.com/alex070416/xiaoman-inquiry-center/releases">GitHub Releases</a>�����԰����˹���������ʽ�ȶ����ʹ�� WordPress �Զ����¡�</p></div>';
 }
 static function health(){
  $c=self::get();return array('plugin'=>XI_VERSION,'wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'bricks'=>defined('BRICKS_VERSION')?BRICKS_VERSION:'missing','https'=>is_ssl(),'credentials'=>array('id'=>(bool)self::credential('id'),'secret'=>(bool)self::credential('secret')),'storage'=>class_exists('XI_Native_Inquiry_Center',false)?XI_Native_Inquiry_Center::exists():null,'queue'=>function_exists('as_enqueue_async_action'),'cron_disabled'=>defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON,'last_recovery'=>get_option($c['queue_prefix'].'last_recovery_v1',0),'consent'=>XI_Cookies::status(),'legacy_conflict'=>XI_Compatibility::legacy_active(),'migration'=>$c['migration']);
 }
}

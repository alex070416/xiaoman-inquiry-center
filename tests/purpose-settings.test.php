<?php
// Save-handler compatibility fixture. No database, CRM, GTM, customer or network access.
define('ABSPATH',__DIR__);define('XI_HOST','fixture.example.invalid');
class Saved extends Exception{};
function wp_parse_args($v,$d){return array_merge($d,$v);}
function get_option($key,$d=false){return $key==='xi_conversion_config_v1'?$GLOBALS['stored']:$d;}
function current_user_can($cap){return $cap==='manage_options';}
function check_admin_referer($key){}
function sanitize_text_field($v){return trim((string)$v);}
function wp_unslash($v){return $v;}
function wp_die($message,...$rest){throw new RuntimeException($message);}
function update_option($key,$value,$autoload){$GLOBALS['stored']=$value;}
function admin_url($path){return 'https://fixture.example.invalid/wp-admin/'.$path;}
function wp_safe_redirect($url){throw new Saved;}
require dirname(__DIR__).'/xiaoman-inquiry-center/conversions.php';
$passed=0;
function save($before,$post){$GLOBALS['stored']=$before;$_POST=$post+array('thank_you_paths'=>'{}');try{XI_Conversions::save_config();}catch(Saved $e){return $GLOBALS['stored'];}throw new RuntimeException('Save did not redirect');}
function check($ok,$label){if(!$ok)throw new RuntimeException($label);$GLOBALS['passed']++;}
foreach(array(false,true) as $prior){
 $before=array_merge(XI_Conversions::defaults(),array('purpose_consent_enabled'=>$prior));
 check(save($before,array())['purpose_consent_enabled']===$prior,'old form without new control changed prior mode');
 check(save($before,array('purpose_consent_enabled'=>'1'))['purpose_consent_enabled']===$prior,'forged checkbox without control marker changed prior mode');
 check(save($before,array('purpose_mode_control_v1'=>'1'))['purpose_consent_enabled']===false,'new form unchecked did not select service mode');
 check(save($before,array('purpose_mode_control_v1'=>'1','purpose_consent_enabled'=>'1'))['purpose_consent_enabled']===true,'new form strict checkbox did not select purpose mode');
 foreach(array('true','0',true,1,array('1')) as $value)check(save($before,array('purpose_mode_control_v1'=>'1','purpose_consent_enabled'=>$value))['purpose_consent_enabled']===false,'non-browser checkbox value enabled purpose mode');
}
echo json_encode(array('suite'=>'purpose-settings-save-compatibility','passed'=>$passed)).PHP_EOL;

<?php
// Local connection-lock/CAS fixtures; never boot WordPress or contact a service.
if(PHP_SAPI!=='cli')exit(1);
define('ABSPATH',__DIR__.'/');define('XI_VERSION','lock-fixture');define('DB_NAME','synthetic_fixture_db');
class WP_Error {function __construct(public $code,public $message){}function get_error_message(){return $this->message;}}
class LockDie extends Exception {function __construct($message,public $response){parent::__construct($message);}}
class LockRedirect extends Exception {}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_parse_args($v,$d){return array_merge($d,$v);}
function maybe_serialize($v){return is_array($v)?serialize($v):$v;}
function maybe_unserialize($v){return is_string($v)&&str_starts_with($v,'a:')?unserialize($v):$v;}
function get_current_blog_id(){return 7;}
function current_user_can($cap){return $cap==='manage_options'&&$GLOBALS['manage'];}
function check_admin_referer($action){if(!$GLOBALS['nonce'])wp_die('Nonce rejected.','',array('response'=>403));}
function wp_unslash($v){return $v;}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_url($v){return 'https://synthetic.example.invalid/wp-admin/'.$v;}
function post_type_exists($v){if(is_callable($GLOBALS['validation_hook'])){$f=$GLOBALS['validation_hook'];$GLOBALS['validation_hook']=null;$f();}return $v==='products';}
function taxonomy_exists($v){return $v==='product-category';}
function is_object_in_taxonomy($p,$t){return $p==='products'&&$t==='product-category';}
function get_option($key,$default=false){
 $cache=$GLOBALS['cache'];if(array_key_exists($key,$cache))return $cache[$key];
 if(!empty($cache['notoptions'][$key]))return $default;
 if(array_key_exists($key,$cache['alloptions']??array()))return maybe_unserialize($cache['alloptions'][$key]);
 return isset($GLOBALS['wpdb']->rows[$key])?maybe_unserialize($GLOBALS['wpdb']->rows[$key]):$default;
}
function update_option(...$args){throw new RuntimeException('Bare option write bypassed atomic ownership');}
function wp_cache_delete($key,$group){if($group!=='options')throw new RuntimeException('Unexpected cache group');$GLOBALS['cache_deletes'][]=$key;unset($GLOBALS['cache'][$key]);return true;}
function delete_transient($key){$GLOBALS['token_deletes'][]=$key;}
function wp_remote_get(...$args){throw new RuntimeException('Unexpected network');}
function wp_remote_post(...$args){throw new RuntimeException('Unexpected network');}
function wp_remote_request(...$args){throw new RuntimeException('Unexpected network');}
function shutdown_check(){if(isset($GLOBALS['wpdb']->owners[fixture_lock_name()])){echo "Owned lock survived shutdown\n";exit(3);}echo json_encode(array('shutdown_released'=>true))."\n";}
function wp_die($message,$title='',$args=array()){
 if($GLOBALS['mode']==='shutdown-die'){register_shutdown_function('shutdown_check');exit(0);}
 throw new LockDie((string)$message,$args['response']??500);
}
function wp_safe_redirect($url){if($GLOBALS['mode']==='shutdown-success'){register_shutdown_function('shutdown_check');return;}throw new LockRedirect($url);}
class XI_Readiness {static function profile_errors($c){return array();}}
class XI_Compatibility {
 static function legacy_active(){return array();}
 static function prepare_import(){if(is_callable($GLOBALS['import_hook'])){$f=$GLOBALS['import_hook'];$GLOBALS['import_hook']=null;$f();}return $GLOBALS['prepared'];}
}
class LockDB {
 public $options='fixture_options',$last_error='',$connection=101,$supported=true,$release_failures=0;
 public $rows=array(),$owners=array(),$sql=array(),$before_atomic=null;
 function prepare($sql,...$args){return array($sql,$args);}
 function get_var($p){
  if(is_string($p)){$sql=$p;$a=array();}else list($sql,$a)=$p;$this->sql[]=$sql;$this->last_error='';
  if($sql==='SELECT CONNECTION_ID()')return (string)$this->connection;
  if(str_starts_with($sql,'SELECT GET_LOCK(')){if(!$this->supported){$this->last_error='unsupported';return null;}if(isset($this->owners[$a[0]])&&$this->owners[$a[0]]!==$this->connection)return '0';$this->owners[$a[0]]=$this->connection;return '1';}
  if(str_starts_with($sql,'SELECT IF(IS_USED_LOCK('))return ($this->owners[$a[0]]??null)===$this->connection&&$this->connection===(int)$a[1]?'1':'0';
  if(str_starts_with($sql,'SELECT RELEASE_LOCK(')){if($this->release_failures-->0)return null;if(($this->owners[$a[0]]??null)!==$this->connection)return '0';unset($this->owners[$a[0]]);return '1';}
  if(str_starts_with($sql,'SELECT option_value FROM '.$this->options.' WHERE option_name='))return $this->rows[$a[0]]??null;
  throw new RuntimeException('Unexpected SELECT');
 }
 function query($p){
  list($sql,$a)=$p;$this->sql[]=$sql;$this->last_error='';
  if(is_callable($this->before_atomic)){$f=$this->before_atomic;$this->before_atomic=null;$f($this);}
  $insert=str_starts_with($sql,'INSERT IGNORE INTO '.$this->options.' ');$update=str_starts_with($sql,'UPDATE '.$this->options.' SET option_value=');
  if((!$insert&&!$update)||!str_contains($sql,'IS_USED_LOCK(')||!str_contains($sql,'CONNECTION_ID()'))throw new RuntimeException('Unconditional write');
  if($insert)list($key,$next,$name,$connection)=$a;else{list($next,$key,$before,$name,$connection)=$a;if(!str_contains($sql,'BINARY option_value=BINARY'))throw new RuntimeException('CAS is not byte-sensitive');}
  if(($this->owners[$name]??null)!==$this->connection||$this->connection!==(int)$connection)return 0;
  if(($insert&&array_key_exists($key,$this->rows))||($update&&($this->rows[$key]??null)!==$before))return 0;
  $GLOBALS['writes'][]=$key;if($GLOBALS['write_failure']){$this->last_error='synthetic';return false;}
  $same=($this->rows[$key]??null)===$next;$this->rows[$key]=$next;
  if(is_callable($GLOBALS['after_write'])){$f=$GLOBALS['after_write'];$GLOBALS['after_write']=null;$f($key,maybe_unserialize($next));}return $same?0:1;
 }
 function disconnect(){foreach($this->owners as $name=>$owner)if($owner===$this->connection)unset($this->owners[$name]);$this->connection++;}
}
require dirname(__DIR__).'/xiaoman-inquiry-center/configuration.php';
$mode=$argv[1]??'main';$assertions=0;$cases=array();$wpdb=new LockDB();
function check_lock($name,$ok){global $assertions;if(!$ok)throw new RuntimeException($name);$assertions++;}
function fixture_lock_name(){return 'xi_config_'.substr(hash('sha256',DB_NAME.'|'.get_current_blog_id().'|'.$GLOBALS['wpdb']->options),0,48);}
function form_fixture($c){$p=array('api'=>$c['api'],'origin_id'=>$c['origin_id'],'product_field'=>$c['product_field'],'level_field'=>$c['level_field'],'origin_name'=>$c['origin_name'],'level_value'=>$c['level_value'],'post_type'=>'products','taxonomies'=>'product-category','crm_control_v1'=>'1');foreach(array('products','product_lead_names','equipment_labels','field_aliases') as $k)$p[$k]=json_encode((object)$c[$k]);if($c['crm'])$p['crm_enabled']='1';return $p;}
function reset_lock(){
 if(isset($GLOBALS['wpdb'])){$GLOBALS['wpdb']->release_failures=0;XI_Config::release_save_lock();}
 $GLOBALS['wpdb']=new LockDB();$GLOBALS['manage']=true;$GLOBALS['nonce']=true;$GLOBALS['cache']=array();$GLOBALS['cache_deletes']=array();$GLOBALS['token_deletes']=array();$GLOBALS['writes']=array();$GLOBALS['write_failure']=false;$GLOBALS['validation_hook']=null;$GLOBALS['import_hook']=null;$GLOBALS['after_write']=null;
 $_SERVER['REQUEST_METHOD']='POST';$_COOKIE=array();$c=array_merge(XI_Config::defaults(),array('post_type'=>'products','taxonomies'=>array('product-category'),'origin_id'=>'100','product_field'=>'200','products'=>array('fixture'=>'option')));$GLOBALS['wpdb']->rows[XI_Config::OPTION]=serialize($c);$_POST=form_fixture($c);$GLOBALS['prepared']=array('config'=>array_merge($c,array('migration'=>array('status'=>'imported','from'=>array('fixture')))),'required'=>array('field_name'=>'Message','post_type'=>'products'));
}
function save_fixture(){try{XI_Config::save();}catch(LockDie|LockRedirect $e){return $e;}throw new RuntimeException('Save returned');}
if(in_array($mode,array('shutdown-die','shutdown-success'),true)){reset_lock();if($mode==='shutdown-die')$_POST['post_type']='missing';XI_Config::save();exit(4);}

foreach(array('method','permission','nonce') as $gate){reset_lock();if($gate==='method')$_SERVER['REQUEST_METHOD']='GET';if($gate==='permission')$GLOBALS['manage']=false;if($gate==='nonce')$GLOBALS['nonce']=false;$before=$wpdb->rows;$r=save_fixture();check_lock('authorization before lock '.$gate,$r instanceof LockDie&&$r->response===($gate==='method'?405:403));check_lock('rejected request has no effects '.$gate,$wpdb->sql===array()&&$wpdb->rows===$before&&$GLOBALS['writes']===array());}
$cases[]='POST, capability and nonce before locking';
reset_lock();$_COOKIE['save_lock']='forged';$_POST['save_lock']='forged';$r=save_fixture();check_lock('unchanged SQL zero is accepted',$r instanceof LockRedirect);check_lock('normal save releases owned lock',$wpdb->owners===array());check_lock('hashed lock identity is bounded and private',strlen(fixture_lock_name())<64&&!str_contains(fixture_lock_name(),DB_NAME));foreach(array(XI_Config::OPTION,'notoptions','alloptions') as $key)check_lock('WordPress cache invalidated '.$key,in_array($key,$GLOBALS['cache_deletes'],true));
reset_lock();$GLOBALS['write_failure']=true;unset($_POST['crm_enabled']);$before=$wpdb->rows;$r=save_fixture();check_lock('failed write is not saved',$r instanceof LockDie&&$wpdb->rows===$before&&$wpdb->owners===array()&&$GLOBALS['token_deletes']===array());$cases[]='ordinary no-op, strict failure and option-cache invalidation';
reset_lock();$wpdb->owners[fixture_lock_name()]=909;$before=$wpdb->rows;$r=save_fixture();check_lock('live owner never expires or gets released by contender',$r instanceof LockDie&&$r->response===409&&$wpdb->owners[fixture_lock_name()]===909&&$wpdb->rows===$before);
reset_lock();$wpdb->supported=false;$r=save_fixture();check_lock('unsupported database fails closed',$r instanceof LockDie&&$r->response===409&&$GLOBALS['writes']===array());
reset_lock();$wpdb->owners[fixture_lock_name()]=$wpdb->connection;$wpdb->disconnect();check_lock('disconnect frees abandoned lock',save_fixture() instanceof LockRedirect&&$wpdb->owners===array());$cases[]='live contention and disconnect recovery';
reset_lock();unset($wpdb->rows[XI_Config::OPTION]);$GLOBALS['cache']['notoptions'][XI_Config::OPTION]=true;$_POST=array('import_legacy'=>'1');$GLOBALS['import_hook']=static function(){$r=save_fixture();check_lock('reentrant save cannot interleave',$r instanceof LockDie&&$r->response===409&&$GLOBALS['writes']===array());};$r=save_fixture();check_lock('first import invalidates negative cache',$r instanceof LockRedirect&&get_option(XI_Config::OPTION)['migration']['status']==='imported');check_lock('required written once',count(array_filter($GLOBALS['writes'],static fn($v)=>$v==='xi_required_v1'))===1);$cases[]='serialized import and first-option insertion';
foreach(array('ordinary','import') as $kind){reset_lock();$c=get_option(XI_Config::OPTION);$replacement=array_merge($c,array('enabled'=>true,'number_prefix'=>'OTHER-N'));$GLOBALS['cache'][XI_Config::OPTION]=$c;$hook=static function()use($replacement){$GLOBALS['wpdb']->rows[XI_Config::OPTION]=serialize($replacement);};if($kind==='import'){$_POST=array('import_legacy'=>'1');$GLOBALS['import_hook']=$hook;}else $GLOBALS['validation_hook']=$hook;$r=save_fixture();check_lock('raw preimage rejects stale request cache '.$kind,$r instanceof LockDie&&$r->response===409&&$GLOBALS['writes']===array()&&maybe_unserialize($wpdb->rows[XI_Config::OPTION])===$replacement);}
foreach(array('reconnect','new-owner','case-drift','insert-race') as $fault){reset_lock();if($fault==='insert-race'){unset($wpdb->rows[XI_Config::OPTION]);$_POST=array('import_legacy'=>'1');}$replacement=array_merge(XI_Config::defaults(),array('enabled'=>true,'number_prefix'=>'NEW-N'));$wpdb->before_atomic=static function($db)use($fault,$replacement){if($fault==='reconnect'){$db->disconnect();$db->owners[fixture_lock_name()]=707;}if($fault==='new-owner')$db->owners[fixture_lock_name()]=708;if($fault==='case-drift')$db->rows[XI_Config::OPTION]=str_replace('XI-N','xi-n',$db->rows[XI_Config::OPTION]);else $db->rows[XI_Config::OPTION]=serialize($replacement);};$r=save_fixture();check_lock('atomic write guards reconnect/CAS gap '.$fault,$r instanceof LockDie&&$GLOBALS['writes']===array());check_lock('newer image preserved '.$fault,$fault==='case-drift'?str_contains($wpdb->rows[XI_Config::OPTION],'xi-n'):maybe_unserialize($wpdb->rows[XI_Config::OPTION])===$replacement);if(in_array($fault,array('reconnect','new-owner'),true))check_lock('successor lock never released '.$fault,in_array($wpdb->owners[fixture_lock_name()]??0,array(707,708),true));}
$cases[]='raw/BINARY preimage and atomic reconnect/insert guards';
reset_lock();unset($wpdb->rows[XI_Config::OPTION]);$_POST=array('import_legacy'=>'1');$GLOBALS['after_write']=static function($key,$value){$value['number_prefix']='CHANGED-N';$GLOBALS['wpdb']->rows[$key]=serialize($value);};$r=save_fixture();check_lock('mid-import drift stops later stages',$r instanceof LockDie&&$GLOBALS['writes']===array(XI_Config::OPTION)&&!isset($wpdb->rows['xi_required_v1']));
reset_lock();$wpdb->release_failures=1;check_lock('release failure retains recoverable connection lock',save_fixture() instanceof LockRedirect&&isset($wpdb->owners[fixture_lock_name()]));XI_Config::release_save_lock();check_lock('shutdown retry releases after failed release',$wpdb->owners===array());
reset_lock();$wpdb->release_failures=1;save_fixture();$wpdb->disconnect();$wpdb->owners[fixture_lock_name()]=808;XI_Config::release_save_lock();check_lock('delayed cleanup retains successor',$wpdb->owners[fixture_lock_name()]===808);$cases[]='partial import and ownership-safe cleanup';
foreach(array('shutdown-die','shutdown-success') as $child_mode){
 // ASCII -r payload avoids Windows argv encoding of a Unicode workspace path.
 $payload='$argv=array(base64_decode("'.base64_encode(__FILE__).'"),"'.$child_mode.'");require $argv[0];';
 $pipes=array();$p=proc_open(array(PHP_BINARY,'-n','-r',$payload),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);if(!is_resource($p))throw new RuntimeException('No child process');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);$r=json_decode(trim($out),true);check_lock('real exit invokes cleanup '.$child_mode,$exit===0&&$err===''&&($r['shutdown_released']??false)===true);
}
$cases[]='actual wp_die and successful exit shutdown';
echo json_encode(array('result'=>'PASS','php'=>PHP_VERSION,'suite'=>'configuration connection mutex and atomic preimage','cases'=>count($cases),'assertions'=>$assertions,'coverage'=>$cases,'production'=>false,'network'=>false),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";

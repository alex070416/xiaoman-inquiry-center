<?php
// Offline routing/header-intent fixture. No WordPress server, customers, CRM, Google or HTTP.
define('ABSPATH',__DIR__.'/fixture/');define('ARRAY_A','ARRAY_A');define('XI_HOST','fixture.example.invalid');define('XI_FILE',__FILE__);define('XI_VERSION','2.0.0-beta.10');define('XI_PROFILE',array('number_prefix'=>'FW-N'));
function wp_parse_args($v,$defaults){return array_merge($defaults,$v);}
function get_option($key,$default=false){global $fixture;return $key==='home'?$fixture['home']:($key==='xi_conversion_config_v1'?$fixture['config']:$default);}
function wp_parse_url($url,$part=-1){return parse_url($url,$part);}
function untrailingslashit($v){return rtrim($v,'/');}
function home_url($path=''){global $fixture;return $fixture['home'].$path;}
function add_action($name,$fn,$priority=10,$accepted=1){global $fixture;$fixture['registered'][]=$name;}
function add_filter($name,$fn,$priority=10,$accepted=1){}
function do_action($name,...$args){global $fixture;$fixture['actions'][]=array($name,$args);}
function nocache_headers(){global $fixture;$fixture['nocache']++;}
function url_to_postid($url){global $fixture;$fixture['resolved'][]=$url;return $fixture['pages'][$url]??0;}
function is_page(){global $fixture;return $fixture['is_page'];}
function get_post_type($id){global $fixture;return $fixture['types'][$id]??null;}
function get_queried_object_id(){global $fixture;return $fixture['query_id'];}
final class XI_Native_Inquiry_Center {static function thank_you_url($r){return home_url('/thank-you/');}}
require dirname(__DIR__).'/xiaoman-inquiry-center/conversions.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function fixture(){global $fixture;$fixture=array(
 'home'=>'https://'.XI_HOST,'config'=>array('enabled'=>true,'site_host'=>XI_HOST,'thank_you_paths'=>array('default'=>'/thank-you/')),
 'pages'=>array('https://'.XI_HOST.'/thank-you/'=>91),'types'=>array(91=>'page',14=>'page'),
 'is_page'=>true,'query_id'=>0,'actions'=>array(),'registered'=>array(),'resolved'=>array(),'nocache'=>0
);$_SERVER['REQUEST_URI']='/products/';XI_Conversions::$original_request_uri=null;}
$passed=array();
function run_case($name,$fn){global $passed;fixture();$fn();$passed[]=$name;}
function cache_result($expected){global $fixture;$before=$fixture['nocache'];XI_Conversions::privacy_headers();check(($fixture['nocache']>$before)===$expected,'no-cache header intent mismatch');check(count($fixture['actions'])===($expected?1:0),'unexpected LiteSpeed cache mutation');if($expected){check(defined('DONOTCACHEPAGE')&&DONOTCACHEPAGE===true,'WP page caching remained enabled');check($fixture['actions'][0][0]==='litespeed_control_set_nocache','LiteSpeed no-cache action missing');}}

run_case('explicit configured incoming thank-you route is protected without query proof',function(){$_SERVER['REQUEST_URI']='/thank-you/?form=quick_quote&inquiry_id=FW-N7';check(XI_Conversions::is_thank_you_request(),'known direct route not recognized');cache_result(true);});
run_case('boot preserves incoming translated path after REQUEST_URI rewrite',function(){global $fixture;$fixture['config']['thank_you_paths']['es']='/es/thankyou/';$_SERVER['REQUEST_URI']='/es/thankyou/';XI_Conversions::boot();$_SERVER['REQUEST_URI']='/translated-internal-alias/';check(XI_Conversions::$original_request_uri==='/es/thankyou/','original URI lost');XI_Conversions::boot();check(XI_Conversions::$original_request_uri==='/es/thankyou/','original URI overwritten');cache_result(true);});
run_case('unlisted Spanish alias uses proof of the configured canonical thank-you page',function(){global $fixture;XI_Conversions::$original_request_uri='/es/thankyou/';$_SERVER['REQUEST_URI']='/translated-internal-alias/';$fixture['query_id']=91;check(XI_Conversions::is_thank_you_request(),'canonical page proof rejected');check($fixture['resolved']===array('https://'.XI_HOST.'/thank-you/'),'did not resolve configured canonical URL');cache_result(true);});
run_case('canonical page proof follows per-site configured URL and ID rather than a hardcoded live ID',function(){global $fixture;$fixture['config']['thank_you_paths']['default']='/success/';$fixture['pages']['https://'.XI_HOST.'/success/']=202;$fixture['types'][202]='page';$fixture['query_id']=202;$_SERVER['REQUEST_URI']='/fr/success-alias/';check(XI_Conversions::is_thank_you_request(),'site-specific canonical page was not recognized');cache_result(true);});
run_case('ordinary page with a different query ID remains cacheable',function(){global $fixture;$fixture['query_id']=14;check(!XI_Conversions::is_thank_you_request(),'ordinary page matched canonical proof');cache_result(false);});
run_case('a thank-you substring on a product route is insufficient',function(){$_SERVER['REQUEST_URI']='/products/thankyou-widget/';check(!XI_Conversions::is_thank_you_request(),'substring accepted');cache_result(false);});
run_case('no configured default means no canonical-page fallback',function(){global $fixture;$fixture['config']['thank_you_paths']=array();$fixture['query_id']=91;$_SERVER['REQUEST_URI']='/es/unknown-alias/';check(!XI_Conversions::is_thank_you_request(),'implicit canonical fallback created');cache_result(false);});
run_case('cross-origin configured URL cannot become a canonical fallback',function(){global $fixture;$fixture['config']['thank_you_paths']['default']='https://evil.example.invalid/thank-you/';$fixture['query_id']=91;check(!XI_Conversions::is_thank_you_request(),'cross-origin target accepted');cache_result(false);});
run_case('a foreign home option cannot prove this site canonical page',function(){global $fixture;$fixture['home']='https://foreign.example.invalid';$fixture['query_id']=91;$_SERVER['REQUEST_URI']='/es/unknown-alias/';check(!XI_Conversions::is_thank_you_request(),'foreign home accepted');cache_result(false);});
run_case('unresolved canonical URL cannot disable caching on an unrelated page',function(){global $fixture;$fixture['pages']=array();$fixture['query_id']=91;check(!XI_Conversions::is_thank_you_request(),'unresolved URL accepted');cache_result(false);});
run_case('canonical URL resolving to a post is not a thank-you page proof',function(){global $fixture;$fixture['types'][91]='post';$fixture['query_id']=91;check(!XI_Conversions::is_thank_you_request(),'non-page accepted');cache_result(false);});
run_case('non-page query and missing query ID are rejected',function(){global $fixture;$fixture['query_id']=91;$fixture['is_page']=false;check(!XI_Conversions::is_thank_you_request(),'non-page query accepted');$fixture['is_page']=true;$fixture['query_id']=0;check(!XI_Conversions::is_thank_you_request(),'missing query ID accepted');cache_result(false);});
run_case('disabled conversion configuration leaves cache behavior untouched',function(){global $fixture;$fixture['config']['enabled']=false;$fixture['query_id']=91;cache_result(false);});
// Print only after header calls; otherwise CLI would mask header-before-output mistakes.
foreach($passed as $name)echo 'PASS '.$name.PHP_EOL;
echo count($passed).' offline privacy-routing cases passed.'.PHP_EOL;

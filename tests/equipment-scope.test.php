<?php
// Offline: detail-form attributes with and without the per-form equipment scope. No WordPress or network.
define('ABSPATH',__DIR__.'/fixture/');define('XI_RECORD_META','xi_native_record');define('XI_VIEW_CAP','xi_view');define('XI_VIEW_ROLE','xi_viewer');
define('XI_PROFILE',array('taxonomies'=>array('forklifts','warehouse-equipment'),'post_type'=>'products','number_prefix'=>'HT-N'));
final class WP_Term { public $term_id,$taxonomy,$slug,$name; function __construct($id,$tax,$slug,$name){$this->term_id=$id;$this->taxonomy=$tax;$this->slug=$slug;$this->name=$name;} }
final class XI_Config { static function get(){return array('field_aliases'=>array(),'excluded_terms'=>array(),'equipment_labels'=>array());} }
final class XI_Xiaoman_Queue_V1 { static function text($v,$n=1000){return substr((string)$v,0,$n);} }
$fixture=array('page'=>'neutral','terms'=>array(),'products'=>array());
$fixture['terms']=array(21=>new WP_Term(21,'forklifts','diesel-forklifts','Diesel Forklifts'),20=>new WP_Term(20,'forklifts','electric-forklifts','Electric Forklifts'),28=>new WP_Term(28,'warehouse-equipment','stackers','Stackers'));
$fixture['products']=array(186=>array('title'=>'1.5 Ton Electric Forklift','terms'=>array(20)),187=>array('title'=>'2 Ton Electric Forklift','terms'=>array(20)),300=>array('title'=>'CPCD30','terms'=>array(21)),400=>array('title'=>'Reach Stacker','terms'=>array(28)));
function add_action(){} function add_filter(){}
function absint($v){return abs((int)$v);}
function is_wp_error($v){return false;}
function wp_json_encode($v){return json_encode($v);}
function wp_salt($s){return 'fixture-salt';}
function is_tax($tax){global $fixture;return $fixture['page']==='category';}
function get_queried_object(){global $fixture;return $fixture['terms'][20];}
function is_singular($type){global $fixture;return $fixture['page']==='product';}
function get_queried_object_id(){return 186;}
function get_post_type($id){global $fixture;return isset($fixture['products'][$id])?'products':'post';}
function get_post_status($id){return 'publish';}
function get_the_title($id){global $fixture;return $fixture['products'][$id]['title'];}
function get_permalink($id){return 'https://fixture.example.invalid/products/'.$id.'/';}
function wp_get_post_terms($id,$tax){global $fixture;return array_map(function($t)use($fixture){return $fixture['terms'][$t];},$fixture['products'][$id]['terms']);}
function get_term($id){global $fixture;return $fixture['terms'][$id]??null;}
// Category with the page term deliberately not last, so a reused loop variable would be caught.
function get_terms($args){global $fixture;return array($fixture['terms'][21],$fixture['terms'][20],$fixture['terms'][28]);}
function get_posts($args){global $fixture;$tid=$args['tax_query'][0]['terms'];$out=array();foreach($fixture['products'] as $id=>$p)if(in_array($tid,$p['terms'],true))$out[]=$id;return $out;}
require __DIR__.'/../xiaoman-inquiry-center/native-inquiry-center.php';
function fields(){$f=array();foreach(array('Equipment'=>'select','Product'=>'hidden','FullName'=>'text','Email'=>'email','WhatsApp'=>'tel','Message'=>'textarea','ProductID'=>'select','CategoryID'=>'hidden','CategorySignature'=>'hidden') as $n=>$t)$f[]=array('id'=>strtolower($n),'name'=>$n,'type'=>$t);return $f;}
function attrs($page,$scope){global $fixture;$fixture['page']=$page;$s=array('submissionFormName'=>'详情页表单','fields'=>fields());if($scope!==null)$s['xiEquipmentScope']=$scope;
 return XI_Native_Inquiry_Center::attributes(array('_root'=>array()),'_root',(object)array('name'=>'form','id'=>'066a8b','settings'=>$s))['_root'];}
$fail=0;function check($label,$ok){global $fail;echo ($ok?'ok   ':'FAIL ').$label."\n";if(!$ok)$fail++;}

$a=attrs('category',null);
check('default scope keeps the locked category packet on a category page',isset($a['data-xi-category'])&&!isset($a['data-xi-catalog'])&&json_decode($a['data-xi-category'],true)['id']===20);
$a=attrs('product',null);
check('default scope keeps the hidden-equipment product page contract',($a['data-xi-product-page']??'')==='true'&&json_decode($a['data-xi-category'],true)['selected_product_id']===186);
$a=attrs('category','catalog');$c=json_decode($a['data-xi-catalog']??'null',true);
check('catalog scope on a category page lists every category',is_array($c)&&array_column($c['categories'],'id')===array(21,20,28)&&!isset($a['data-xi-category']));
check('catalog scope starts on the page category, not the last listed one',($c['default_category_id']??null)===20&&!isset($c['selected_product_id']));
check('every listed category carries its own signature',count(array_unique(array_column($c['categories'],'signature')))===3);
$a=attrs('product','catalog');$c=json_decode($a['data-xi-catalog']??'null',true);
check('catalog scope on a product page preselects its category and product',($c['default_category_id']??null)===20&&($c['selected_product_id']??null)===186);
check('catalog scope does not hide the equipment field on product pages',!isset($a['data-xi-product-page']));
$a=attrs('neutral','catalog');$c=json_decode($a['data-xi-catalog']??'null',true);
check('catalog scope on a neutral page has no preset',is_array($c)&&!isset($c['default_category_id'])&&!isset($c['selected_product_id']));
$a=attrs('neutral',null);$c=json_decode($a['data-xi-catalog']??'null',true);
check('default scope on a neutral page is unchanged',is_array($c)&&!isset($c['default_category_id']));
exit($fail?1:0);

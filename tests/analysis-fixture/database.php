<?php
/** Tiny semantic SELECT fixture. It never executes SQL or opens a connection. */
final class AnalysisFixtureDB {
 public $prefix='fixture_'; public $options='fixture_options'; public $last_error='';
 public $queries=array(); public $rows=array(); public $receipts=array(); public $options_rows=array();
 function esc_like($s){return addcslashes($s,'_%\\');}
 function prepare($sql,...$args){
  if(count($args)===1&&is_array($args[0]))$args=$args[0];$n=0;
  return preg_replace_callback('/%([dsf%])/',function($m)use(&$n,$args){if($m[1]==='%')return '%';$v=$args[$n++]??null;return $m[1]==='d'?(string)(int)$v:($m[1]==='f'?(string)(float)$v:"'".str_replace(array('\\',"'"),array('\\\\',"''"),(string)$v)."'");},$sql);
 }
 private function audit($sql){$this->queries[]=$sql;fixture_check((bool)preg_match('/^\s*(SELECT|SHOW)\b/i',$sql),'Non-read SQL issued: '.$sql);fixture_check(!preg_match('/\b(FOR\s+UPDATE|GET_LOCK|RELEASE_LOCK|INTO\s+OUTFILE|SLEEP\s*\(|BENCHMARK\s*\()/i',$sql),'Dangerous SELECT issued');}
 function get_var($sql){$this->audit($sql);if(stripos($sql,'SHOW TABLES')!==false){if(stripos($sql,'conversion')!==false)return $this->prefix.'xi_conversion_receipts';return $this->prefix.'bricks_form_submissions';}if(stripos($sql,'MAX(')!==false)return $this->rows?max(array_keys($this->rows)):null;if(stripos($sql,'COUNT(')!==false)return count($this->select_rows($sql));if(stripos($sql,$this->options)!==false&&preg_match("/option_name\s*=\s*'([^']*)'/i",$sql,$m)){$v=$this->options_rows[$m[1]]??null;if(preg_match('/OCTET_LENGTH\(option_value\)\s*<=\s*(\d+)/i',$sql,$max)&&is_string($v)&&strlen($v)>(int)$max[1])return null;return $v;}return null;}
 function get_results($sql,$output=ARRAY_A){$this->audit($sql);if(stripos($sql,$this->options)!==false&&stripos($sql,'bricks_form_submissions')===false){$rows=array();foreach($this->options_rows as $name=>$value)$rows[]=array('option_name'=>$name,'option_value'=>$value);return $rows;}if(stripos($sql,'xi_conversion_receipts')!==false){$rows=array_values($this->receipts);if(preg_match('/submission_id\s*=\s*(\d+)/i',$sql,$m))$rows=array_values(array_filter($rows,static fn($r)=>(int)$r['submission_id']===(int)$m[1]));return $rows;}return $this->select_rows($sql);}
 function get_row($sql,$output=ARRAY_A){$rows=$this->get_results($sql,$output);return $rows[0]??null;}
 function get_col($sql){$this->audit($sql);return array();}
 private function select_rows($sql){
  $rows=array_values($this->rows);
  // Native candidates are JSON-decoded and strictly filtered by the production query module.
  foreach(array('<='=>'/\b(?:[a-z_][a-z0-9_]*\.)?id\s*<=\s*(\d+)/i','>='=>'/\b(?:[a-z_][a-z0-9_]*\.)?id\s*>=\s*(\d+)/i','<'=>'/\b(?:[a-z_][a-z0-9_]*\.)?id\s*<\s*(\d+)/i','>'=>'/\b(?:[a-z_][a-z0-9_]*\.)?id\s*>\s*(\d+)/i','='=>'/\b(?:[a-z_][a-z0-9_]*\.)?id\s*=\s*(\d+)/i') as $operator=>$pattern){
   if(preg_match_all($pattern,$sql,$matches))foreach($matches[1] as $bound)$rows=array_values(array_filter($rows,static function($row)use($operator,$bound){$v=(int)$row['id'];return match($operator){'<='=>$v<=(int)$bound,'>='=>$v>=(int)$bound,'<'=>$v<(int)$bound,'>'=>$v>(int)$bound,'='=>$v===(int)$bound};}));
  }
  usort($rows,static fn($a,$b)=>preg_match('/ORDER BY (?:[a-z_][a-z0-9_]*\.)?id ASC/i',$sql)?(int)$a['id']<=>(int)$b['id']:(int)$b['id']<=>(int)$a['id']);
  if(preg_match('/\bLIMIT\s+(\d+)/i',$sql,$m))$rows=array_slice($rows,0,(int)$m[1]);return $rows;
 }
 function insert(...$args){write_forbidden('wpdb insert');} function update(...$args){write_forbidden('wpdb update');} function delete(...$args){write_forbidden('wpdb delete');} function query(...$args){write_forbidden('wpdb query');}
}
function fixture_record($id,$submitted_at,$changes=array(),$row_changes=array()){
 global $wpdb;
 $record=array_replace_recursive(array('schema'=>1,'uuid'=>'UUID-SECRET-'.$id,'kind'=>'detail','form_name'=>'Native Detail','form_id'=>'detail_form','post_id'=>900+$id,
  'submitted_at'=>$submitted_at,'submitted_timezone'=>'UTC','site_timezone'=>'Asia/Shanghai','is_test'=>false,'country'=>'France','source'=>'Google Ads','sync_requested'=>true,
  'values'=>array('F1'=>'Electric Forklift','F2'=>'2.5 Ton Electric Forklift','F3'=>'PRIVATE-NAME','F5'=>'buyer@example.invalid','F6'=>'+12025550123','F9'=>'Need two forklifts for warehouse loading.'),
  'hd'=>array('H1'=>'https://'.XI_HOST.'/products/forklift/?gclid=SECRET-CLICK&email=LEAK-EMAIL','H2'=>'192.0.2.25','H3'=>'Mobile','A1'=>'pmex-fr','A2'=>'electric forklift','A3'=>'forklift-group','A4'=>'m','A5'=>'SECRET-CLICK'),
  'attribution'=>array('source_platform'=>'google_ads','source_label'=>'Google Ads','campaign'=>'pmex-fr','campaign_id'=>'198421111','adgroup'=>'forklift-group','keyword'=>'electric forklift','device'=>'m','gclid'=>'SECRET-CLICK','landing_url'=>'https://'.XI_HOST.'/?xi_receipt=SECRET-RECEIPT&email=LEAK-EMAIL'),
  'measurement_consent'=>array('analytics_storage'=>'granted','ad_storage'=>'denied','ad_user_data'=>'denied'),'product'=>array('title'=>'2.5 Ton Electric Forklift','id'=>44)), $changes);
 $row=array_replace(array('id'=>$id,'status'=>'read','form_id'=>$record['form_id'],'post_id'=>$record['post_id'],'form_data'=>json_encode(array(XI_RECORD_META=>array('value'=>json_encode($record)))),'is_trash'=>0,'xi_is_trash'=>0,'trashed'=>0),$row_changes);
 $wpdb->rows[$id]=$row;return $row;
}
function fixture_filters($changes=array()){return array_merge(array('from'=>'2026-10-18','to'=>'2026-10-18','timezone'=>'Asia/Shanghai'),$changes);}

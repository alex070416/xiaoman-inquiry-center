<?php
/** Offline behavior/security fixtures. Never contacts WordPress, customers, CRM or Google. */
require __DIR__.'/analysis-fixture/bootstrap.php';
require __DIR__.'/analysis-fixture/database.php';
require dirname(__DIR__).'/xiaoman-inquiry-center/analysis-query.php';
$wpdb=new AnalysisFixtureDB();$fixture_passed=array();
function fresh_rows(){global $wpdb;$wpdb=new AnalysisFixtureDB();}
function analysis_query($extra=array()){return fixture_no_error(XI_Analysis_Query::query_inquiries(fixture_filters($extra)),'query');}
function item_numbers($result){return array_column($result['items'],'inquiry_number');}
function expect_window_ids($expected,$extra=array()){$r=analysis_query($extra);fixture_check(item_numbers($r)===$expected,'Expected '.json_encode($expected).'; got '.json_encode(item_numbers($r)));return $r;}

fixture_case('Beijing calendar dates normalize to a half-open UTC window; lead-name cutoff does not apply',function(){
 $f=fixture_no_error(XI_Analysis_Query::normalize_filters(fixture_filters()),'filter');
 fixture_check($f['timezone']==='Asia/Shanghai','timezone changed');
 fixture_check($f['from_utc']==='2026-10-17 16:00:00'&&$f['to_utc_exclusive']==='2026-10-18 16:00:00','wrong UTC calendar day');
 fresh_rows();foreach(array(10=>'2026-10-17 15:59:59',11=>'2026-10-17 16:00:00',12=>'2026-10-18 09:59:59',13=>'2026-10-18 10:00:00',14=>'2026-10-18 15:59:59',15=>'2026-10-18 16:00:00') as $id=>$utc)fixture_record($id,$utc);
 expect_window_ids(array('FW-N14','FW-N13','FW-N12','FW-N11'));
});
fixture_case('31 inclusive calendar days and leap day valid; reversed/32-day/invalid/relative dates rejected',function(){
 foreach(array(array('2026-10-01','2026-10-31'),array('2024-02-29','2024-02-29')) as [$from,$to])fixture_no_error(XI_Analysis_Query::normalize_filters(fixture_filters(array('from'=>$from,'to'=>$to))),'valid calendar range');
 foreach(array(array('2026-10-01','2026-11-01'),array('2026-10-19','2026-10-18'),array('2026-02-29','2026-02-29'),array('2026-13-01','2026-13-01'),array('tomorrow','tomorrow'),array('2026-10-18 00:00:00','2026-10-18')) as [$from,$to])fixture_error(XI_Analysis_Query::normalize_filters(fixture_filters(array('from'=>$from,'to'=>$to))),'Invalid date range accepted: '.$from.' '.$to);
 fixture_error(XI_Analysis_Query::normalize_filters(fixture_filters(array('timezone'=>'Mars/Olympus'))),'Invalid timezone accepted');
 $dst=fixture_no_error(XI_Analysis_Query::normalize_filters(fixture_filters(array('from'=>'2026-11-01','to'=>'2026-11-01','timezone'=>'America/New_York'))),'DST day');fixture_check(strtotime($dst['to_utc_exclusive'].' UTC')-strtotime($dst['from_utc'].' UTC')===90000,'DST fall-back reporting day incorrectly forced to 24 hours');
});
fixture_case('date filtering uses metadata submission time across reused template page IDs',function(){
 fresh_rows();fixture_record(1015,'2026-10-18 03:20:00',array('post_id'=>8));fixture_record(1016,'2026-10-18 04:20:00',array('post_id'=>1200));
 $r=expect_window_ids(array('FW-N1016','FW-N1015'));
 fixture_check($r['total']===2,'Template reuse loses inquiry');global $wpdb;foreach($wpdb->queries as $sql)fixture_check(!preg_match('/WHERE[^;]*post_id\s*=/i',$sql),'Query incorrectly scopes submissions to template page');
});
fixture_case('campaign exact, prefix, contains and unknown campaign are distinct',function(){
 fresh_rows();fixture_record(1,'2026-10-18 01:00:00');fixture_record(2,'2026-10-18 01:01:00',array('hd'=>array('A1'=>'pmex-uk'),'attribution'=>array('campaign'=>'pmex-uk')));fixture_record(3,'2026-10-18 01:02:00',array('hd'=>array('A1'=>''),'attribution'=>array('campaign'=>'','campaign_id'=>'')));fixture_record(4,'2026-10-18 01:03:00',array('hd'=>array('A1'=>'search-pmex-x'),'attribution'=>array('campaign'=>'search-pmex-x')));
 expect_window_ids(array('FW-N1'),array('campaign'=>'pmex-fr','campaign_match'=>'exact'));
 expect_window_ids(array('FW-N2','FW-N1'),array('campaign'=>'pmex','campaign_match'=>'prefix'));
 expect_window_ids(array('FW-N4','FW-N2','FW-N1'),array('campaign'=>'pmex','campaign_match'=>'contains'));
 $all=analysis_query();fixture_check($all['total']===4,'Missing campaign record silently dropped');
});
fixture_case('legacy-only A1, attribution-only campaign, and conflicting evidence retain actual field provenance',function(){
 fresh_rows();fixture_record(1,'2026-10-18 01:00:00',array('hd'=>array('A1'=>'legacy-pmex'),'attribution'=>array('campaign'=>'','campaign_id'=>'')));fixture_record(2,'2026-10-18 01:01:00',array('hd'=>array('A1'=>''),'attribution'=>array('campaign'=>'modern-pmex','campaign_id'=>'123456')));fixture_record(3,'2026-10-18 01:02:00',array('hd'=>array('A1'=>'legacy-pmex'),'attribution'=>array('campaign'=>'conflicting-search','campaign_id'=>'987654')));
 expect_window_ids(array('FW-N3','FW-N1'),array('campaign'=>'legacy-pmex','campaign_match'=>'exact'));expect_window_ids(array('FW-N2'),array('campaign'=>'modern-pmex','campaign_match'=>'exact'));expect_window_ids(array('FW-N3'),array('campaign'=>'conflicting-search','campaign_match'=>'exact'));
 $r=fixture_no_error(XI_Analysis_Query::get_inquiry(array('inquiry_number'=>'FW-N3')),'conflicting evidence');$c=$r['item']['campaign_candidates'];fixture_check(in_array(array('field'=>'hd.A1','value'=>'legacy-pmex'),$c,true)&&in_array(array('field'=>'attribution.campaign','value'=>'conflicting-search'),$c,true),'Evidence overwritten instead of preserving conflict');
});
fixture_case('snapshot cursor pagination is stable when a newer inquiry arrives',function(){
 fresh_rows();for($i=1;$i<=32;$i++)fixture_record($i,'2026-10-18 02:00:00');
 $first=analysis_query(array('limit'=>25));fixture_check(count($first['items'])===25&&$first['total']===32,'Wrong first-page count');fixture_check($first['pagination']['has_more']===true,'Missing next page');
 $snapshot=$first['pagination']['snapshot_max_id'];$before=$first['pagination']['next_before_id'];fixture_record(33,'2026-10-18 03:00:00');
 $second=analysis_query(array('limit'=>25,'before_id'=>$before,'snapshot_max_id'=>$snapshot));
 fixture_check($second['total']===32&&count($second['items'])===7,'Snapshot total/page changed');fixture_check(!in_array('FW-N33',item_numbers($second),true),'New inquiry entered established snapshot');fixture_check(!array_intersect(item_numbers($first),item_numbers($second)),'Page overlap');
 fixture_check($second['pagination']['has_more']===false,'Last page has_more incorrect');
});
fixture_case('multiple internal read batches produce complete totals without exposing internal cursor as client pagination',function(){
 global $wpdb;fresh_rows();for($i=1;$i<=301;$i++)fixture_record($i,'2026-10-18 02:00:00');$r=analysis_query(array('limit'=>25));fixture_check($r['total']===301&&$r['coverage']['candidate_rows_read']===301,'A read batch was lost or counted twice');fixture_check(count($r['items'])===25&&$r['pagination']['next_before_id']===277,'Internal batch cursor leaked into page cursor');$reads=array_values(array_filter($wpdb->queries,static fn($sql)=>(bool)preg_match('/ORDER BY (?:[a-z_][a-z0-9_]*\.)?id DESC LIMIT 250\s*$/i',$sql)));fixture_check(count($reads)===2,'Did not exercise both internal DB batches');fixture_check((bool)preg_match('/\bid\s*<=\s*301\b/i',$reads[0])&&(bool)preg_match('/\bid\s*<=\s*301\b/i',$reads[1]),'Internal batches lost the same snapshot bound');fixture_check((bool)preg_match('/\bid\s*<\s*52\b/i',$reads[1]),'Second internal batch did not continue below the last row ID');
});
fixture_case('test, spam and trash exclusions overlap transparently; explicit inclusions work',function(){
 fresh_rows();fixture_record(1,'2026-10-18 01:00:00');fixture_record(2,'2026-10-18 01:00:00',array('is_test'=>true));fixture_record(3,'2026-10-18 01:00:00',array(),array('status'=>'spam'));fixture_record(4,'2026-10-18 01:00:00',array(),array('is_trash'=>1,'xi_is_trash'=>1,'trashed'=>1));fixture_record(5,'2026-10-18 01:00:00',array('is_test'=>true),array('status'=>'spam'));
 expect_window_ids(array('FW-N1'));
 $all=analysis_query(array('exclude_tests'=>false,'include_spam'=>true,'include_trash'=>true));fixture_check($all['total']===5,'Explicit inclusion not honored');
 $s=fixture_no_error(XI_Analysis_Query::summarize_inquiries(fixture_filters()),'summary');fixture_check($s['summary']['raw_total']===5&&$s['summary']['eligible_total']===1,'Summary does not distinguish raw and eligible totals');
 fixture_check($s['summary']['excluded']===array('test'=>2,'spam'=>2,'trash'=>1,'excluded_union'=>4,'multiple_exclusion_reasons'=>1),'Overlapping exclusions double-counted or hidden');
});
fixture_case('one contact with two procurement needs is never silently deduplicated',function(){
 fresh_rows();fixture_record(1,'2026-10-18 01:00:00');fixture_record(2,'2026-10-18 01:30:00',array('values'=>array('F9'=>'Also need one rough terrain machine for a construction site.')));
 $r=analysis_query();fixture_check($r['total']===2&&count($r['items'])===2,'Separate requests auto-collapsed');fixture_check($r['items'][0]['contact_group_key']===$r['items'][1]['contact_group_key'],'Contact grouping unavailable');fixture_check($r['items'][0]['message']!==$r['items'][1]['message'],'Distinct requirements lost');
});
fixture_case('invalid stored dates and unrelated native submissions cannot be assigned to the reporting day',function(){
 fresh_rows();fixture_record(1,'2026-10-18 01:00:00');fixture_record(2,'2026-02-30 01:00:00');fixture_record(3,'not-a-timestamp');fixture_record(4,'');global $wpdb;$wpdb->rows[5]=array('id'=>5,'status'=>'read','form_data'=>json_encode(array('customer_message'=>'Unrelated Bricks form')),'is_trash'=>0);
 $r=expect_window_ids(array('FW-N1'));fixture_check($r['total']===1,'Invalid or unrelated record inflated total');fixture_check(isset($r['coverage'])&&is_array($r['coverage']),'Missing data coverage');
});
fixture_case('explicit non-UTC metadata is excluded and undeclared legacy timezone is not silently relabeled as verified',function(){
 global $wpdb;fresh_rows();fixture_record(1,'2026-10-18 01:00:00');fixture_record(2,'2026-10-18 01:00:00',array('submitted_timezone'=>'Asia/Shanghai'));
 $row=fixture_record(3,'2026-10-18 01:00:00');$outer=json_decode($row['form_data'],true);$record=json_decode($outer[XI_RECORD_META]['value'],true);unset($record['submitted_timezone']);$outer[XI_RECORD_META]['value']=json_encode($record);$wpdb->rows[3]['form_data']=json_encode($outer);
 $r=analysis_query();fixture_check(!in_array('FW-N2',item_numbers($r),true),'Explicit local timestamp treated as UTC');fixture_check($r['coverage']['non_utc_metadata_count']===1,'Non-UTC date coverage absent');
 fixture_check(isset($r['coverage']['undeclared_timezone_count'])&&$r['coverage']['undeclared_timezone_count']===1,'Legacy date timezone assumption not disclosed');
});
fixture_case('contact redaction removes raw PII, URL query secrets and protocol owners by default',function(){
 global $fixture;fresh_rows();fixture_record(7,'2026-10-18 01:00:00',array('values'=>array('F9'=>'Contact buyer@example.invalid or +12025550123. Need 2 forklifts.')));
 $fixture['caps']=array('xiaoman_mcp_read'=>true);$r=analysis_query();$json=json_encode($r);
 foreach(array('buyer@example.invalid','+12025550123','PRIVATE-NAME','192.0.2.25','UUID-SECRET','SECRET-CLICK','SECRET-RECEIPT','LEAK-EMAIL','claim_owner','token_hash') as $secret)fixture_check(strpos($json,$secret)===false,'Secret leaked: '.$secret);
 fixture_check(!isset($r['items'][0]['values']),'Raw values returned');
 $denied=XI_Analysis_Query::query_inquiries(fixture_filters(array('include_contacts'=>true)));fixture_error($denied,'Contact read silently escalated');
 $fixture['caps']['xiaoman_mcp_view_contacts']=true;$allowed=analysis_query(array('include_contacts'=>true));fixture_check(strpos(json_encode($allowed),'buyer@example.invalid')!==false,'Explicit contact permission not honored');$fixture['caps']=array();
});
fixture_case('numeric advertising identifiers remain usable but cannot bypass a matching known phone redaction',function(){
 fresh_rows();fixture_record(7,'2026-10-18 01:00:00',array('values'=>array('F6'=>'12025550123'),'attribution'=>array('campaign_id'=>'12025550123','landing_url'=>'https://'.XI_HOST.'/contact/buyer@example.invalid/')));fixture_record(8,'2026-10-18 01:00:00',array('attribution'=>array('campaign_id'=>'198421111')));$r=analysis_query();$text=json_encode($r);fixture_check(strpos($text,'12025550123')===false,'Known contact phone leaked through numeric campaign ID');fixture_check(strpos($text,'198421111')!==false,'Ordinary campaign ID destroyed');fixture_check(strpos($text,'buyer@example.invalid')===false,'Known email leaked through URL path');
});
fixture_case('missing CRM and receipts are unknown, never synthetic success',function(){
 fresh_rows();fixture_record(7,'2026-10-18 01:00:00');$r=fixture_no_error(XI_Analysis_Query::get_delivery_status(array('inquiry_number'=>'FW-N7')),'delivery');$json=strtolower(json_encode($r));fixture_check(strpos($json,'unknown')!==false||strpos($json,'missing')!==false||strpos($json,'not_found')!==false,'Absence not marked unknown');fixture_check(strpos($json,'google_reported')===false,'Local evidence presented as Google report');
});
fixture_case('local CRM and receipt success are projected with strict whitelist and remain distinct from Google attribution',function(){
 global $wpdb;fresh_rows();fixture_record(7,'2026-10-18 01:00:00');
 $wpdb->options_rows[XI_QUEUE_PREFIX.'job_v1_7']=serialize(array('status'=>'success','attempts'=>2,'lead_id'=>'105859128470432','created_at'=>'2026-10-18 01:00:00','updated_at'=>'2026-10-18 01:00:04','payload'=>array('email'=>'CRM-SECRET@example.invalid','name'=>'CRM-PRIVATE-NAME'),'detail'=>'CRM-SECRET@example.invalid; raw Authorization: SECRET-API-KEY','token'=>'SECRET-API-KEY'));
 $wpdb->receipts[7]=array('submission_id'=>7,'created_utc'=>'2026-10-18 01:00:00','expires_utc'=>'2026-10-18 01:30:00','is_test'=>0,'consent_at_submit'=>json_encode(array('analytics_storage'=>'granted','ad_storage'=>'denied','ad_user_data'=>'denied','email'=>'CONSENT-SECRET@example.invalid')),'ga4_state'=>'callback','ads_state'=>'claimed','ga4_claimed_utc'=>'2026-10-18 01:00:01','ga4_callback_utc'=>'2026-10-18 01:00:02','ads_claimed_utc'=>'2026-10-18 01:00:01','token_hash'=>'SECRET-TOKEN-HASH','ga4_claim_owner'=>'SECRET-OWNER','ads_payload'=>'SECRET-PAYLOAD');
 $r=fixture_no_error(XI_Analysis_Query::get_delivery_status(array('inquiry_number'=>'FW-N7')),'delivery success');$d=$r['delivery'];fixture_check($d['crm']['status']==='success'&&$d['crm']['attempts']===2&&$d['crm']['lead_id']==='105859128470432','CRM whitelist loses real status');fixture_check($d['conversion_receipt']['channels']['ga4']['local_state']==='callback','Local receipt state lost');fixture_check($d['conversion_receipt']['channels']['ga4']['actual_google_attributed']==='unknown','Callback was promoted to actual attribution');fixture_check($d['crm_verified_grade']==='unknown'&&$d['crm_order']==='unknown','Sync success was promoted to grade/order');$text=json_encode($r);foreach(array('CRM-SECRET','CRM-PRIVATE-NAME','SECRET-API-KEY','CONSENT-SECRET','SECRET-TOKEN-HASH','SECRET-OWNER','SECRET-PAYLOAD') as $secret)fixture_check(strpos($text,$secret)===false,'Delivery secret leaked: '.$secret);
 foreach($wpdb->queries as $sql)if(stripos($sql,'xi_conversion_receipts')!==false&&stripos($sql,'SELECT')===0)fixture_check(stripos($sql,'SELECT *')===false&&stripos($sql,'token_hash')===false&&stripos($sql,'claim_owner')===false,'Receipt SQL read unnecessary secrets');
});
final class SerializedOptionTrap {function __wakeup(){write_forbidden('Serialized object wakeup');}}
fixture_case('malformed, object-valued and oversized CRM option records fail safely without code execution',function(){
 global $wpdb;fresh_rows();fixture_record(7,'2026-10-18 01:00:00');
 foreach(array('not-serialized',serialize(new SerializedOptionTrap()),str_repeat('x',65537)) as $raw){$wpdb->options_rows[XI_QUEUE_PREFIX.'job_v1_7']=$raw;$r=fixture_no_error(XI_Analysis_Query::get_delivery_status(array('inquiry_number'=>'FW-N7')),'untrusted CRM option');fixture_check($r['delivery']['crm']['status']==='unknown'&&$r['delivery']['crm']['record_available']===false,'Unusable option presented as real CRM state');}
});
fixture_case('inquiry IDs enforce this site prefix and reject SQL/array injection',function(){
 fresh_rows();fixture_record(1016,'2026-10-18 01:00:00');fixture_no_error(XI_Analysis_Query::get_inquiry(array('inquiry_number'=>'FW-N1016')),'single inquiry');
 foreach(array('HT-N1016','FW-N1016 OR 1=1','1016;DROP TABLE x',array('FW-N1016')) as $id)fixture_error(XI_Analysis_Query::get_inquiry(array('inquiry_number'=>$id)),'Unsafe/foreign ID accepted');
 foreach(array(array('campaign'=>array('pmex')),array('campaign_match'=>'sql'),array('kind'=>'<script>'),array('limit'=>10000),array('before_id'=>'1 OR 1=1'),array('snapshot_max_id'=>array(1))) as $input)fixture_error(XI_Analysis_Query::normalize_filters(fixture_filters($input)),'Unsafe filter accepted: '.json_encode($input));
 foreach(array('false','true',1,0,array()) as $input)fixture_error(XI_Analysis_Query::normalize_filters(fixture_filters(array('include_contacts'=>$input))),'Nonboolean contact access accepted');
});
fixture_case('over-cap candidate ranges fail closed rather than claim truncated data is complete',function(){
 fresh_rows();for($i=1;$i<=5001;$i++)fixture_record($i,'2026-10-18 01:00:00');fixture_error(XI_Analysis_Query::query_inquiries(fixture_filters()),'Over-cap query returned misleading partial results');
});
fixture_case('oversized individual records are rejected before projection, never represented as a complete empty response',function(){
 fresh_rows();fixture_record(5,'2026-10-18 01:00:00',array('values'=>array('F9'=>str_repeat('x',262145))));fixture_error(XI_Analysis_Query::query_inquiries(fixture_filters()),'Oversized metadata record bypassed bounded read');fixture_error(XI_Analysis_Query::get_inquiry(array('inquiry_number'=>'FW-N5')),'Oversized single inquiry bypassed bounded read');fixture_error(XI_Analysis_Query::get_delivery_status(array('inquiry_number'=>'FW-N5')),'Oversized delivery inquiry bypassed bounded read');
});
fixture_case('database read errors return a sanitized failure instead of partial counts or SQL details',function(){
 global $wpdb;fresh_rows();fixture_record(5,'2026-10-18 01:00:00');$wpdb->last_error='SECRET-DB-CREDENTIAL SQL fixture_form_submissions buyer@example.invalid';$r=XI_Analysis_Query::query_inquiries(fixture_filters());fixture_error($r,'Database error returned data as complete');fixture_check(strpos($r->get_error_message(),'SECRET-DB')===false&&strpos($r->get_error_message(),'buyer@')===false,'Database exception information leaked');
});
fixture_case('all query tool statements are SELECT/SHOW and do not mutate options, rows, cron, CRM or HTTP',function(){
 global $wpdb,$fixture;fresh_rows();fixture_record(5,'2026-10-18 01:00:00');$before=array($wpdb->rows,$wpdb->receipts,$wpdb->options_rows,$fixture['options']);
 foreach(array('list_campaigns','query_inquiries','summarize_inquiries') as $method)fixture_no_error(XI_Analysis_Query::$method(fixture_filters()),$method);
 foreach(array('get_inquiry','get_delivery_status') as $method)fixture_no_error(XI_Analysis_Query::$method(array('inquiry_number'=>'FW-N5')),$method);
 fixture_check($before===array($wpdb->rows,$wpdb->receipts,$wpdb->options_rows,$fixture['options']),'Read calls mutated fixture data');fixture_check(count($wpdb->queries)>0,'No storage reads tested');foreach($wpdb->queries as $sql)fixture_check((bool)preg_match('/^\s*(SELECT|SHOW)\b/i',$sql),'Write SQL found');
});
echo count($fixture_passed).' offline analysis-query cases passed.'.PHP_EOL;

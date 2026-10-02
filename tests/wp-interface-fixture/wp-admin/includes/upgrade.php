<?php
// Isolated dbDelta substitute for migration trigger/column checks. Not a MySQL dbDelta test.
function dbDelta($sql){
 $GLOBALS['dbdelta_calls']=($GLOBALS['dbdelta_calls']??0)+1;
 if(!empty($GLOBALS['migration_fail']))return array();
 global $wpdb;$table=XI_Conversions::table();$cols=$wpdb->get_col('SHOW COLUMNS FROM '.$table);
 foreach(array('ga4_claim_owner','ads_claim_owner') as $col)if(!in_array($col,$cols,true))$wpdb->query('ALTER TABLE '.$table.' ADD COLUMN '.$col.' TEXT DEFAULT NULL');
 return array();
}

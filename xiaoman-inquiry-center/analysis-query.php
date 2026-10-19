<?php
/** Read-only projections of the native inbox for advertising analysis. No capture or delivery actions. */
if (!defined('ABSPATH')) { exit; }

final class XI_Analysis_Query {
    const SCHEMA_VERSION = '1.0';
    const MAX_ROWS = 5000;
    const MAX_SECONDS = 3.0;
    const MAX_GROUPS = 100;
    const MAX_OPTION_BYTES = 65536;
    const MAX_RECORD_BYTES = 262144;
    const MAX_READ_BYTES = 16777216;
    const READ_BATCH = 250;

    private static function error($code, $message, $status = 400) {
        return new WP_Error('xi_analysis_' . $code, $message, array('status' => $status));
    }
    private static function unicode_substr($value, $start, $length) {
        if (function_exists('mb_substr')) { return mb_substr($value, $start, $length, 'UTF-8'); }
        $matched = preg_match_all('/./us', $value, $characters);
        return $matched === false ? substr($value, $start, $length) : implode('', array_slice($characters[0], $start, $length));
    }
    private static function unicode_length($value) {
        if (function_exists('mb_strlen')) { return mb_strlen($value, 'UTF-8'); }
        $length = preg_match_all('/./us', $value, $characters);
        return $length === false ? strlen($value) : $length;
    }
    private static function case_fold($value) {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
    private static function text_position($value, $needle) {
        return function_exists('mb_strpos') ? mb_strpos($value, $needle, 0, 'UTF-8') : strpos($value, $needle);
    }
    private static function text($value, $limit = 500) {
        if (!is_scalar($value) || is_bool($value)) { return ''; }
        $value = html_entity_decode(wp_strip_all_tags((string)$value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $value);
        return self::unicode_substr(trim($value), 0, $limit);
    }
    private static function bool_input($input, $key, $default) {
        if (!array_key_exists($key, $input)) { return $default; }
        return is_bool($input[$key]) ? $input[$key] : self::error('invalid_input', $key . ' must be a boolean.');
    }
    private static function positive_input($input, $key, $default = 0, $max = PHP_INT_MAX) {
        if (!array_key_exists($key, $input)) { return $default; }
        $value = $input[$key];
        if (!(is_int($value) || (is_string($value) && preg_match('/^[0-9]{1,18}$/D', $value))) || $value < 1 || $value > $max) {
            return self::error('invalid_input', $key . ' must be a positive integer within its supported limit.');
        }
        return (int)$value;
    }
    private static function contact_access() {
        return current_user_can('manage_options') || current_user_can('xiaoman_mcp_view_contacts');
    }
    private static function prefix() { return (string)XI_PROFILE['number_prefix']; }
    private static function table() { global $wpdb; return $wpdb->prefix . 'bricks_form_submissions'; }
    private static function receipt_table() { global $wpdb; return $wpdb->prefix . 'xi_conversion_receipts'; }
    private static function identifier_safe($name) { return is_string($name) && preg_match('/^[A-Za-z0-9_]+$/D', $name); }
    private static function native_where($column = 'form_data') {
        global $wpdb;
        // $column is supplied only as a literal internal identifier, never from input.
        return $wpdb->prepare($column . ' LIKE %s', '%' . $wpdb->esc_like('"' . XI_RECORD_META . '"') . '%');
    }
    private static function inner_record_sql($column) {
        // Both the source column and metadata JSON path are internal, never client-controlled.
        global $wpdb;
        $key = str_replace(array('\\', '"'), array('\\\\', '\\"'), (string)XI_RECORD_META);
        $outer = "CASE WHEN JSON_VALID($column) THEN $column ELSE '{}' END";
        return 'JSON_UNQUOTE(JSON_EXTRACT(' . $outer . ', ' . $wpdb->prepare('%s', '$."' . $key . '".value') . '))';
    }
    private static function valid_date_sql($date) {
        // DateTime below remains the final authority. This predicate also selects unusable dates for coverage.
        return "($date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$'"
            . " AND SUBSTRING($date,1,4) BETWEEN '1000' AND '9999'"
            . " AND SUBSTRING($date,6,2) BETWEEN '01' AND '12'"
            . " AND CAST(SUBSTRING($date,9,2) AS UNSIGNED) BETWEEN 1 AND DAY(LAST_DAY(CONCAT(SUBSTRING($date,1,7),'-01')))"
            . " AND SUBSTRING($date,12,2) BETWEEN '00' AND '23'"
            . " AND SUBSTRING($date,15,2) BETWEEN '00' AND '59' AND SUBSTRING($date,18,2) BETWEEN '00' AND '59')";
    }

    /** Pure normalization (contact authorization is checked again here). Dates are inclusive local calendar dates. */
    public static function normalize_filters($input) {
        if (!is_array($input)) { return self::error('invalid_input', 'Input must be an object.'); }
        $out = array();
        foreach (array('from', 'to', 'timezone', 'campaign', 'campaign_match', 'kind', 'country', 'device', 'source') as $key) {
            if (isset($input[$key]) && !is_string($input[$key])) { return self::error('invalid_input', $key . ' must be a string.'); }
            $out[$key] = trim($input[$key] ?? '');
            if (strlen($out[$key]) > ($key === 'timezone' ? 64 : 500)) { return self::error('invalid_input', $key . ' is too long.'); }
        }
        $out['timezone'] = $out['timezone'] ?: 'Asia/Shanghai';
        if ($out['timezone'] !== 'UTC' && !in_array($out['timezone'], DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            return self::error('invalid_timezone', 'Use an IANA timezone name or UTC.');
        }
        $timezone = new DateTimeZone($out['timezone']);
        $dates = array();
        foreach (array('from', 'to') as $key) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $out[$key], $timezone);
            if (!$date || $date->format('Y-m-d') !== $out[$key]) { return self::error('invalid_date', 'from and to must be valid YYYY-MM-DD dates.'); }
            $dates[$key] = $date;
        }
        $days = (int)$dates['from']->diff($dates['to'])->format('%r%a') + 1;
        if ($days < 1 || $days > 31) { return self::error('date_range', 'Use an ordered date range of at most 31 inclusive calendar days.'); }
        $out['from_utc'] = $dates['from']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $out['to_utc_exclusive'] = $dates['to']->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $out['campaign_match'] = $out['campaign_match'] ?: 'exact';
        if (!in_array($out['campaign_match'], array('exact', 'prefix', 'contains'), true)) { return self::error('invalid_input', 'campaign_match must be exact, prefix or contains.'); }
        if (!in_array($out['kind'], array('', 'quick', 'detail'), true)) { return self::error('invalid_input', 'kind must be quick or detail.'); }
        if (!in_array($out['device'], array('', 'PC', 'Mobile'), true)) { return self::error('invalid_input', 'device must be PC or Mobile.'); }
        foreach (array('exclude_tests' => true, 'include_spam' => false, 'include_trash' => false, 'include_contacts' => false) as $key => $default) {
            $out[$key] = self::bool_input($input, $key, $default);
            if (is_wp_error($out[$key])) { return $out[$key]; }
        }
        if ($out['include_contacts'] && !self::contact_access()) { return self::error('contacts_forbidden', 'Contact detail permission is required.', 403); }
        foreach (array('limit' => array(25, 50), 'before_id' => array(0, PHP_INT_MAX), 'snapshot_max_id' => array(0, PHP_INT_MAX)) as $key => $limits) {
            $out[$key] = self::positive_input($input, $key, $limits[0], $limits[1]);
            if (is_wp_error($out[$key])) { return $out[$key]; }
        }
        return $out;
    }
    private static function single_input($input) {
        if (!is_array($input)) { return self::error('invalid_input', 'Input must be an object.'); }
        $contacts = self::bool_input($input, 'include_contacts', false);
        if (is_wp_error($contacts)) { return $contacts; }
        if ($contacts && !self::contact_access()) { return self::error('contacts_forbidden', 'Contact detail permission is required.', 403); }
        $id = 0;
        if (array_key_exists('id', $input)) {
            $id = self::positive_input($input, 'id');
            if (is_wp_error($id)) { return $id; }
        }
        if (array_key_exists('inquiry_number', $input)) {
            if (!is_string($input['inquiry_number']) || !preg_match('/^' . preg_quote(self::prefix(), '/') . '([1-9][0-9]{0,17})$/D', $input['inquiry_number'], $match)) {
                return self::error('invalid_number', 'Inquiry number must exactly match this site prefix.');
            }
            $number_id = (int)$match[1];
            if ($id && $id !== $number_id) { return self::error('invalid_number', 'id and inquiry_number do not match.'); }
            $id = $number_id;
        }
        if (!$id) { return self::error('invalid_number', 'Supply id or inquiry_number.'); }
        $timezone = $input['timezone'] ?? 'Asia/Shanghai';
        if (!is_string($timezone) || ($timezone !== 'UTC' && !in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true))) {
            return self::error('invalid_timezone', 'Use an IANA timezone name or UTC.');
        }
        return array('id' => $id, 'inquiry_number' => self::prefix() . $id, 'include_contacts' => $contacts, 'timezone' => $timezone);
    }
    private static function envelope($filters, $coverage = array()) {
        $coverage['text_case_folding'] = function_exists('mb_strtolower') ? 'unicode' : 'ascii_only; non-ASCII case variants require exact stored casing';
        return array('schema_version' => self::SCHEMA_VERSION, 'site' => array('host' => XI_HOST, 'number_prefix' => self::prefix()),
            'queried_at' => gmdate('Y-m-d\TH:i:s\Z'), 'filters' => $filters, 'coverage' => $coverage);
    }
    private static function within_budget($start) { return microtime(true) - $start <= self::MAX_SECONDS; }
    private static function budget_error() { return self::error('range_too_broad', 'The read budget was exceeded. Narrow the date range; no partial result is returned.', 422); }
    private static function db_error() { return self::error('storage_unavailable', 'The native inquiry store could not be read. No schema changes were attempted.', 503); }
    private static function parse_date($raw) {
        if (!is_string($raw)) { return null; }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $raw, new DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d H:i:s') === $raw ? $date : null;
    }
    private static function record($row) {
        // This is the inbox's parser; it repairs only its in-memory attribution representation.
        return XI_Native_Inquiry_Center::record($row);
    }
    private static function candidate_values($record) {
        $out = array();
        foreach (array('hd.A1', 'attribution.campaign_name', 'attribution.campaign', 'attribution.campaign_id') as $path) {
            list($group, $key) = explode('.', $path, 2);
            $value = self::text($record[$group][$key] ?? '');
            if ($value !== '') { $out[$path] = $value; }
        }
        return $out;
    }
    private static function matches($record, $filters) {
        if ($filters['kind'] !== '' && ($record['kind'] ?? '') !== $filters['kind']) { return false; }
        if ($filters['country'] !== '' && self::case_fold(self::text($record['country'] ?? '')) !== self::case_fold($filters['country'])) { return false; }
        if ($filters['device'] !== '' && ($record['hd']['H3'] ?? '') !== $filters['device']) { return false; }
        if ($filters['source'] !== '' && self::case_fold(self::text($record['source'] ?? '')) !== self::case_fold($filters['source'])) { return false; }
        if ($filters['campaign'] !== '') {
            $needle = self::case_fold($filters['campaign']); $matched = false;
            foreach (self::candidate_values($record) as $value) {
                $value = self::case_fold($value);
                if (($filters['campaign_match'] === 'exact' && $value === $needle)
                    || ($filters['campaign_match'] === 'prefix' && self::text_position($value, $needle) === 0)
                    || ($filters['campaign_match'] === 'contains' && self::text_position($value, $needle) !== false)) { $matched = true; break; }
            }
            if (!$matched) { return false; }
        }
        return true;
    }
    private static function flags($row, $record) {
        $values = is_array($record['values'] ?? null) ? $record['values'] : array();
        $marked = !empty($record['is_test']);
        foreach (array('F3', 'F4', 'F5', 'F9') as $key) {
            if (stripos(self::text($values[$key] ?? '', 10000), 'XI-PILOT-TEST') !== false) { $marked = true; }
        }
        return array('is_test' => $marked, 'is_spam' => ($row['status'] ?? '') === 'spam', 'is_trash' => !empty($row['xi_is_trash']));
    }
    private static function excluded($flags, $filters) {
        return ($filters['exclude_tests'] && $flags['is_test']) || (!$filters['include_spam'] && $flags['is_spam']) || (!$filters['include_trash'] && $flags['is_trash']);
    }
    private static function read_set($filters, $start) {
        global $wpdb;
        if (!self::identifier_safe(self::table()) || !self::identifier_safe($wpdb->options)) { return self::db_error(); }
        $db_max = $wpdb->get_var('SELECT /*+ MAX_EXECUTION_TIME(2000) */ MAX(id) FROM ' . self::table() . ' WHERE ' . self::native_where());
        if ($wpdb->last_error) { return self::db_error(); }
        $db_max = (int)$db_max;
        $snapshot = $filters['snapshot_max_id'] ?: $db_max;
        if ($snapshot > $db_max) { return self::error('invalid_snapshot', 'snapshot_max_id exceeds the current store high watermark.'); }
        // Keep the two JSON parses outside the repeated date-validity expression. Without both
        // NO_MERGE and NO_DERIVED_CONDITION_PUSHDOWN, MySQL may inline the expensive expression again.
        $date = 'xi_dates.xi_submitted_at';
        $trash_prefix = defined('XI_Native_Inquiry_Trash::PREFIX') ? XI_Native_Inquiry_Trash::PREFIX : 'fwn_trash_v1_';
        $trash_sql = $wpdb->prepare("EXISTS (SELECT 1 FROM $wpdb->options xi_trash WHERE xi_trash.option_name=CONCAT(%s, s.id))", $trash_prefix);
        $source_where = self::native_where('xi_source.form_data') . $wpdb->prepare(' AND xi_source.id<=%d', $snapshot);
        $range_where = $wpdb->prepare("(($date >= %s AND $date < %s) OR NOT COALESCE(" . self::valid_date_sql($date) . ', 0))', $filters['from_utc'], $filters['to_utc_exclusive']);
        $all = array(); $invalid_dates = 0; $invalid_records = 0; $non_utc = 0; $undeclared_timezone = 0; $read_rows = 0; $read_bytes = 0; $cursor = 0;
        do {
            $cursor_sql = $cursor ? $wpdb->prepare(' AND xi_source.id<%d', $cursor) : '';
            // No inner LIMIT: it could silently lose date-window records or unusable-date coverage.
            $records_sql = 'SELECT xi_source.id,' . self::inner_record_sql('xi_source.form_data') . ' AS xi_record FROM ' . self::table() . ' xi_source WHERE ' . $source_where . $cursor_sql;
            $dates_sql = "SELECT /*+ NO_MERGE(xi_records) NO_DERIVED_CONDITION_PUSHDOWN(xi_records) */ xi_records.id,NULLIF(JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(xi_records.xi_record) THEN xi_records.xi_record ELSE '{}' END,'$.submitted_at')),'null') AS xi_submitted_at FROM (" . $records_sql . ') xi_records';
            $columns = 's.id,s.status,CASE WHEN OCTET_LENGTH(s.form_data)<=' . self::MAX_RECORD_BYTES . ' THEN s.form_data ELSE NULL END AS form_data,OCTET_LENGTH(s.form_data) AS xi_record_bytes,';
            $rows = $wpdb->get_results('SELECT /*+ MAX_EXECUTION_TIME(2000) NO_MERGE(xi_dates) NO_DERIVED_CONDITION_PUSHDOWN(xi_dates) */ ' . $columns . $trash_sql . ' AS xi_is_trash FROM (' . $dates_sql . ') xi_dates INNER JOIN ' . self::table() . ' s ON s.id=xi_dates.id WHERE ' . $range_where . ' ORDER BY s.id DESC LIMIT ' . self::READ_BATCH, ARRAY_A);
            if ($wpdb->last_error || !is_array($rows)) { return self::db_error(); }
            $read_rows += count($rows);
            if ($read_rows > self::MAX_ROWS || !self::within_budget($start)) { return self::budget_error(); }
            $previous_cursor = $cursor;
            foreach ($rows as $row) {
            if (!self::within_budget($start)) { return self::budget_error(); }
            $read_bytes += isset($row['xi_record_bytes']) ? (int)$row['xi_record_bytes'] : strlen($row['form_data'] ?? '');
            if (($row['xi_record_bytes'] ?? strlen($row['form_data'] ?? '')) > self::MAX_RECORD_BYTES || $read_bytes > self::MAX_READ_BYTES) { return self::budget_error(); }
            $cursor = (int)$row['id'];
            if ($previous_cursor && $cursor >= $previous_cursor) { return self::error('unstable_read', 'The inbox cursor did not advance; no partial result is returned.', 503); }
            if ((int)$row['id'] > $snapshot) { continue; }
            $record = self::record($row);
            if (!$record) { $invalid_records++; continue; }
            $stamp = self::parse_date($record['submitted_at'] ?? null);
            if (!$stamp) { $invalid_dates++; continue; }
            $declared_timezone = self::text($record['submitted_timezone'] ?? '', 64);
            if ($declared_timezone !== '' && $declared_timezone !== 'UTC') { $non_utc++; continue; }
            if ($declared_timezone === '') { $undeclared_timezone++; }
            $raw_stamp = $stamp->format('Y-m-d H:i:s');
            if ($raw_stamp < $filters['from_utc'] || $raw_stamp >= $filters['to_utc_exclusive']) { continue; }
            if (!self::matches($record, $filters)) { continue; }
            $all[] = array('row' => $row, 'record' => $record, 'flags' => self::flags($row, $record));
            }
        } while (count($rows) === self::READ_BATCH);
        usort($all, static function($a, $b) { return (int)$b['row']['id'] <=> (int)$a['row']['id']; });
        return array('all' => $all, 'snapshot_max_id' => $snapshot, 'coverage' => array(
            'complete' => true, 'scope' => 'native_inquiry_metadata_only', 'native_template_scope' => 'all_posts_and_forms',
            'candidate_rows_read' => $read_rows, 'max_candidate_rows' => self::MAX_ROWS, 'candidate_bytes_read' => $read_bytes, 'max_candidate_bytes' => self::MAX_READ_BYTES,
            'invalid_record_count' => $invalid_records, 'unusable_submitted_at_count' => $invalid_dates,
            'non_utc_metadata_count' => $non_utc,
            'undeclared_timezone_count' => $undeclared_timezone,
            'timezone_assumption' => 'Records with no declared submitted_timezone use the native inbox legacy UTC convention and are explicitly labelled legacy_assumed_utc; explicit non-UTC metadata is excluded. Counts cover read candidates.',
            'unusable_date_scope' => 'all native records at or below snapshot selected by invalid-date predicate; cannot assign them to the requested date range',
            'date_basis' => 'record.submitted_at UTC; never lead-name date or row creation fallback',
            'invalid_metadata_excluded' => true, 'database_time_limit' => 'MySQL MAX_EXECUTION_TIME hint 2000ms; MariaDB may ignore hint; elapsed-time result guard 3s',
            'json_projection_strategy' => 'Two materialized derived projections with NO_MERGE and NO_DERIVED_CONDITION_PUSHDOWN (MySQL 8.0.22+); other engines may ignore hints without changing the query result.',
            'max_seconds' => self::MAX_SECONDS, 'contact_redaction' => $filters['include_contacts'] ? 'authorized_contact_details' : 'best_effort_known_identifiers_and_patterns',
            'advertising_campaign_type' => 'unknown_without_Google_Ads_account_data',
            'google_actual_attribution' => 'unknown', 'crm_customer_grade' => 'unknown', 'crm_order_stage' => 'unknown'));
    }
    private static function redact($value, $record, $contacts, $limit = 10000) {
        $value = self::text($value, $limit);
        // Marketing URLs must never leak arbitrary URL query values, fragments or user-info.
        $value = preg_replace_callback('~https?://[^\s<>"\']+~iu', static function($match) { return self::safe_url($match[0]); }, $value);
        if ($contacts) { return $value; }
        foreach (array('F3', 'F4', 'F5', 'F6') as $key) {
            $known = self::text($record['values'][$key] ?? '', 500);
            if ($known !== '' && self::unicode_length($known) >= 2) { $value = str_ireplace($known, '[redacted]', $value); }
        }
        $value = preg_replace('/[A-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[A-Z0-9](?:[A-Z0-9.-]*[A-Z0-9])?\.[A-Z]{2,}/iu', '[email redacted]', $value);
        $value = preg_replace('/(?<![\pL\pN])\+?[0-9][0-9\s().-]{5,}[0-9](?![\pL\pN])/u', '[phone or long number redacted]', $value);
        return $value;
    }
    private static function campaign_value($value, $field, $record, $contacts) {
        $value = self::text($value, 500);
        if (!$contacts) {
            foreach (array('F3', 'F4', 'F5', 'F6') as $key) {
                $known = self::text($record['values'][$key] ?? '', 500);
                if ($known !== '' && self::unicode_length($known) >= 2 && self::case_fold($value) === self::case_fold($known)) { return '[redacted]'; }
            }
            $known_phone_digits = preg_replace('/[^0-9]/', '', self::text($record['values']['F6'] ?? '', 500));
            if (strlen($known_phone_digits) >= 7 && $value === $known_phone_digits) { return '[redacted]'; }
        }
        // Explicit numeric advertising IDs remain usable; their semantic field is not a phone-number field.
        if (in_array($field, array('campaign_id', 'adgroup_id', 'ad_id', 'attribution.campaign_id', 'hd.A1', 'legacy_campaign', 'legacy_adgroup', 'legacy_ad'), true)
            && preg_match('/^[0-9]{1,30}$/D', $value)) { return $value; }
        return self::redact($value, $record, $contacts, 500);
    }
    private static function safe_url($raw) {
        $parts = wp_parse_url((string)$raw);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', array('http', 'https'), true) || empty($parts['host'])) { return ''; }
        $host = strtolower($parts['host']);
        if (!preg_match('/^[A-Za-z0-9.-]+$/D', $host)) { return ''; }
        return $parts['scheme'] . '://' . $host . self::text($parts['path'] ?? '/', 1800);
    }
    private static function contact_key($values) {
        $email = self::case_fold(trim(self::text($values['F5'] ?? '')));
        $phone = preg_replace('/[^0-9+]/', '', self::text($values['F6'] ?? ''));
        $identity = $email !== '' ? 'email:' . $email : ($phone !== '' ? 'phone:' . $phone : '');
        return $identity === '' ? null : 'site-contact-v1:' . hash_hmac('sha256', XI_HOST . ':' . $identity, wp_salt('auth'));
    }
    /** Public for deterministic fixtures. $record must be the native inbox's parsed record. */
    public static function project_record($row, $record, $flags, $filters) {
        $contacts = !empty($filters['include_contacts']) && self::contact_access();
        $values = is_array($record['values'] ?? null) ? $record['values'] : array();
        $hd = is_array($record['hd'] ?? null) ? $record['hd'] : array();
        $a = is_array($record['attribution'] ?? null) ? $record['attribution'] : array();
        $date = self::parse_date($record['submitted_at'] ?? null);
        $declared_timezone = self::text($record['submitted_timezone'] ?? '', 64);
        $timezone_basis = $declared_timezone === 'UTC' ? 'declared_utc' : ($declared_timezone === '' ? 'legacy_assumed_utc' : 'unsupported_declared_timezone');
        if ($timezone_basis === 'unsupported_declared_timezone') { $date = null; }
        $contact = array('name_present' => !empty($values['F3']) || !empty($values['F4']), 'email_present' => !empty($values['F5']),
            'phone_present' => !empty($values['F6']), 'email_format_valid' => (bool)is_email(self::text($values['F5'] ?? '')),
            'phone_e164_format_valid' => (bool)preg_match('/^\+[1-9][0-9]{7,14}$/D', preg_replace('/[\s().-]/', '', self::text($values['F6'] ?? ''))),
            'contactability_verified' => 'unknown', 'details_redacted' => !$contacts);
        if ($contacts) {
            $contact['full_name'] = self::text(trim(self::text($values['F3'] ?? '') . ' ' . self::text($values['F4'] ?? '')), 500);
            $contact['email'] = self::text($values['F5'] ?? ''); $contact['phone'] = self::text($values['F6'] ?? '');
        }
        $attribution = array();
        foreach (array('source_platform', 'capture_basis', 'first_touch_scope', 'consent_status', 'campaign_name', 'campaign', 'campaign_id',
            'adgroup_name', 'adgroup', 'adgroup_id', 'ad_name', 'ad', 'ad_id', 'keyword', 'device', 'network', 'utm_source', 'utm_medium', 'utm_campaign') as $key) {
            $attribution[$key] = self::campaign_value($a[$key] ?? '', $key, $record, $contacts);
        }
        foreach (array('campaign' => 'A1', 'keyword' => 'A2', 'adgroup' => 'A3', 'device' => 'A4', 'ad' => 'A7') as $key => $legacy) {
            $attribution['legacy_' . $key] = self::campaign_value($hd[$legacy] ?? '', 'legacy_' . $key, $record, $contacts);
        }
        $attribution['click_id_presence'] = array();
        foreach (array('gclid', 'gbraid', 'wbraid', 'dclid', 'fbclid', 'msclkid', 'ttclid') as $key) { $attribution['click_id_presence'][$key] = !empty($a[$key]); }
        $attribution['legacy_click_id_present'] = !empty($hd['A5']);
        $attribution['landing_url'] = self::redact(self::safe_url($a['landing_ad_url'] ?? $a['landing_url'] ?? $hd['R4'] ?? ''), $record, $contacts, 2000);
        $attribution['inquiry_url'] = self::redact(self::safe_url($hd['H1'] ?? ''), $record, $contacts, 2000);
        $campaigns = array();
        foreach (self::candidate_values($record) as $field => $value) { $campaigns[] = array('field' => $field, 'value' => self::campaign_value($value, $field, $record, $contacts)); }
        $message = self::redact($values['F9'] ?? '', $record, $contacts);
        $equipment = self::redact($values['F1'] ?? '', $record, $contacts, 500);
        $model = self::redact($values['F2'] ?? '', $record, $contacts, 500);
        return array('id' => (int)$row['id'], 'inquiry_number' => self::prefix() . (int)$row['id'],
            'submitted_at_utc' => $date ? $date->format('Y-m-d\TH:i:s\Z') : null,
            'submitted_at_local' => $date ? $date->setTimezone(new DateTimeZone($filters['timezone'] ?? 'Asia/Shanghai'))->format('Y-m-d\TH:i:sP') : null,
            'submitted_timezone' => $declared_timezone !== '' ? $declared_timezone : null, 'timezone_basis' => $timezone_basis,
            'submitted_at_status' => $date ? 'parseable' : 'invalid_or_unsupported_timezone',
            'form_kind' => in_array($record['kind'] ?? '', array('quick', 'detail'), true) ? $record['kind'] : 'unknown',
            'product' => array('equipment' => $equipment, 'model' => $model, 'product_id' => (int)($values['product_id'] ?? $record['product']['id'] ?? 0)),
            'message' => $message, 'source' => self::redact($record['source'] ?? '', $record, $contacts, 500),
            'attribution' => $attribution, 'campaign_candidates' => $campaigns,
            'country' => self::redact($record['country'] ?? '', $record, $contacts, 150), 'device' => in_array($hd['H3'] ?? '', array('PC', 'Mobile'), true) ? $hd['H3'] : 'unknown',
            'inbox_status' => in_array($row['status'] ?? '', array('read', 'spam', 'unread'), true) ? $row['status'] : 'unread',
            'flags' => $flags, 'contact_info' => $contact, 'contact_group_key' => self::contact_key($values),
            'quality_facts' => array('message_present' => self::text($values['F9'] ?? '', 10000) !== '', 'message_character_count' => self::unicode_length(self::text($values['F9'] ?? '', 10000)),
                'equipment_present' => $equipment !== '', 'model_present' => $model !== '', 'product_selection_present' => !empty($values['product_id']),
                'country_present' => !empty($record['country']), 'campaign_evidence_present' => count($campaigns) > 0,
                'customer_grade' => 'unknown', 'order_stage' => 'unknown', 'ai_quality_grade' => 'not_assessed',
                'interpretation' => 'Presence and format are facts only. Empty message is not spam; matching contact key is only a possible repeat contact, not a duplicate inquiry.'));
    }
    private static function campaign_groups($entries, $contacts, $start) {
        $groups = array();
        foreach ($entries as $entry) {
            if (!self::within_budget($start)) { return self::budget_error(); }
            foreach (self::candidate_values($entry['record']) as $field => $raw) {
                $value = self::campaign_value($raw, $field, $entry['record'], $contacts);
                $key = $field . "\n" . $value;
                if (!isset($groups[$key])) { $groups[$key] = array('field' => $field, 'value' => $value, 'inquiry_count' => 0); }
                $groups[$key]['inquiry_count']++;
            }
        }
        $groups = array_values($groups);
        usort($groups, static function($a, $b) { return ($b['inquiry_count'] <=> $a['inquiry_count']) ?: strcmp($a['field'] . $a['value'], $b['field'] . $b['value']); });
        return self::within_budget($start) ? $groups : self::budget_error();
    }
    public static function query_inquiries($input) {
        $start = microtime(true);
        $filters = self::normalize_filters($input); if (is_wp_error($filters)) { return $filters; }
        $set = self::read_set($filters, $start); if (is_wp_error($set)) { return $set; }
        $eligible = array_values(array_filter($set['all'], static function($entry) use ($filters) { return !self::excluded($entry['flags'], $filters); }));
        $page = array_values(array_filter($eligible, static function($entry) use ($filters) { return !$filters['before_id'] || (int)$entry['row']['id'] < $filters['before_id']; }));
        $has_more = count($page) > $filters['limit']; $page = array_slice($page, 0, $filters['limit']);
        $items = array(); foreach ($page as $entry) {
            if (!self::within_budget($start)) { return self::budget_error(); }
            $items[] = self::project_record($entry['row'], $entry['record'], $entry['flags'], $filters);
        }
        $filters['snapshot_max_id'] = $set['snapshot_max_id'];
        $out = self::envelope($filters, $set['coverage']); $out['items'] = $items; $out['total'] = count($eligible);
        $out['pagination'] = array('snapshot_max_id' => $set['snapshot_max_id'], 'before_id' => $filters['before_id'],
            'next_before_id' => $has_more && $items ? end($items)['id'] : null, 'has_more' => $has_more, 'returned' => count($items), 'limit' => $filters['limit']);
        $out['coverage']['snapshot_consistency'] = 'New IDs are excluded by the high watermark; edits/deletions of existing records are not frozen.';
        return self::within_budget($start) ? $out : self::budget_error();
    }
    public static function list_campaigns($input) {
        $start = microtime(true);
        $filters = self::normalize_filters($input); if (is_wp_error($filters)) { return $filters; }
        $set = self::read_set($filters, $start); if (is_wp_error($set)) { return $set; }
        $eligible = array_values(array_filter($set['all'], static function($entry) use ($filters) { return !self::excluded($entry['flags'], $filters); }));
        $groups = self::campaign_groups($eligible, $filters['include_contacts'], $start); if (is_wp_error($groups)) { return $groups; }
        if (count($groups) > self::MAX_GROUPS) { return self::error('range_too_broad', 'More than 100 campaign-field values match. Narrow the date range or campaign filter; no partial list is returned.', 422); }
        $filters['snapshot_max_id'] = $set['snapshot_max_id'];
        $out = self::envelope($filters, $set['coverage']); $out['campaigns'] = $groups; $out['total'] = count($eligible);
        $out['coverage']['campaign_count_basis'] = 'Each field/value is counted separately; counts across fields overlap and must not be summed as inquiries.';
        return self::within_budget($start) ? $out : self::budget_error();
    }
    private static function grouped_counts($entries, $field, $contacts, $start) {
        $groups = array();
        foreach ($entries as $entry) {
            if (!self::within_budget($start)) { return self::budget_error(); }
            if ($field === 'campaign') { $value = self::text($entry['record']['hd']['A1'] ?? ''); }
            elseif ($field === 'device') { $value = self::text($entry['record']['hd']['H3'] ?? ''); }
            else { $value = self::text($entry['record'][$field] ?? ''); }
            $value = $field === 'campaign' ? self::campaign_value($value, 'hd.A1', $entry['record'], $contacts) : self::redact($value, $entry['record'], $contacts, 500); $value = $value !== '' ? $value : '[unknown]';
            $groups[$value] = ($groups[$value] ?? 0) + 1;
        }
        arsort($groups, SORT_NUMERIC); $count = count($groups); $shown = array_slice($groups, 0, self::MAX_GROUPS, true);
        $rows = array(); foreach ($shown as $value => $total) { $rows[] = array('value' => (string)$value, 'count' => $total); }
        return array('items' => $rows, 'distinct_total' => $count, 'other_count' => array_sum($groups) - array_sum($shown), 'complete' => $count <= self::MAX_GROUPS);
    }
    public static function summarize_inquiries($input) {
        $start = microtime(true);
        $filters = self::normalize_filters($input); if (is_wp_error($filters)) { return $filters; }
        $set = self::read_set($filters, $start); if (is_wp_error($set)) { return $set; }
        $counts = array('test' => 0, 'spam' => 0, 'trash' => 0, 'excluded_union' => 0, 'multiple_exclusion_reasons' => 0);
        $eligible = array(); $facts = array('empty_message' => 0, 'missing_campaign' => 0, 'missing_country' => 0, 'invalid_email_format' => 0, 'invalid_e164_phone_format' => 0);
        $contact_groups = array();
        foreach ($set['all'] as $entry) {
            if (!self::within_budget($start)) { return self::budget_error(); }
            $reasons = (int)($filters['exclude_tests'] && $entry['flags']['is_test']) + (int)(!$filters['include_spam'] && $entry['flags']['is_spam']) + (int)(!$filters['include_trash'] && $entry['flags']['is_trash']);
            foreach (array('test', 'spam', 'trash') as $kind) { if ($entry['flags']['is_' . $kind]) { $counts[$kind]++; } }
            if ($reasons) { $counts['excluded_union']++; if ($reasons > 1) { $counts['multiple_exclusion_reasons']++; } continue; }
            $eligible[] = $entry;
            $projected = self::project_record($entry['row'], $entry['record'], $entry['flags'], $filters);
            if (!$projected['quality_facts']['message_present']) { $facts['empty_message']++; }
            if (!$projected['quality_facts']['campaign_evidence_present']) { $facts['missing_campaign']++; }
            if (!$projected['quality_facts']['country_present']) { $facts['missing_country']++; }
            if (!$projected['contact_info']['email_format_valid']) { $facts['invalid_email_format']++; }
            if (!$projected['contact_info']['phone_e164_format_valid']) { $facts['invalid_e164_phone_format']++; }
            if ($projected['contact_group_key']) { $contact_groups[$projected['contact_group_key']] = ($contact_groups[$projected['contact_group_key']] ?? 0) + 1; }
        }
        $campaigns = self::campaign_groups($eligible, $filters['include_contacts'], $start); if (is_wp_error($campaigns)) { return $campaigns; }
        $actual = array_slice($campaigns, 0, self::MAX_GROUPS); $other = 0;
        foreach (array_slice($campaigns, self::MAX_GROUPS) as $group) { $other += $group['inquiry_count']; }
        $repeat = array_filter($contact_groups, static function($count) { return $count > 1; });
        $filters['snapshot_max_id'] = $set['snapshot_max_id'];
        $groupings = array();
        foreach (array('country', 'device', 'campaign') as $field) {
            $groupings[$field] = self::grouped_counts($eligible, $field, $filters['include_contacts'], $start);
            if (is_wp_error($groupings[$field])) { return $groupings[$field]; }
        }
        $out = self::envelope($filters, $set['coverage']);
        $out['summary'] = array('raw_total' => count($set['all']), 'eligible_total' => count($eligible), 'excluded' => $counts,
            'exclusion_count_note' => 'test/spam/trash counts are independent flags and overlap; excluded_union applies the requested switches and is the only subtractable total.',
            'groupings' => $groupings,
            'actual_matched_campaigns' => array('items' => $actual, 'distinct_total' => count($campaigns), 'other_field_inquiry_count' => $other, 'complete' => count($campaigns) <= self::MAX_GROUPS),
            'data_completeness' => $facts, 'possible_repeat_contacts' => array('contact_groups' => count($repeat), 'inquiries' => array_sum($repeat), 'duplicates_confirmed' => 'unknown'),
            'quality_assessment' => 'not_assessed', 'verified_abc_customers' => 'unknown', 'orders' => 'unknown');
        return self::within_budget($start) ? $out : self::budget_error();
    }
    private static function read_single($filters) {
        global $wpdb;
        if (!self::identifier_safe(self::table()) || !self::identifier_safe($wpdb->options)) { return self::db_error(); }
        $trash_prefix = defined('XI_Native_Inquiry_Trash::PREFIX') ? XI_Native_Inquiry_Trash::PREFIX : 'fwn_trash_v1_';
        $trash = $wpdb->prepare("EXISTS (SELECT 1 FROM $wpdb->options xi_trash WHERE xi_trash.option_name=CONCAT(%s, s.id))", $trash_prefix);
        $columns = 'id,status,CASE WHEN OCTET_LENGTH(form_data)<=' . self::MAX_RECORD_BYTES . ' THEN form_data ELSE NULL END AS form_data,OCTET_LENGTH(form_data) AS xi_record_bytes,';
        $row = $wpdb->get_row($wpdb->prepare('SELECT /*+ MAX_EXECUTION_TIME(2000) */ ' . $columns . $trash . ' AS xi_is_trash FROM ' . self::table() . ' s WHERE id=%d AND ' . self::native_where() . ' LIMIT 1', $filters['id']), ARRAY_A);
        if ($wpdb->last_error) { return self::db_error(); }
        if ($row && ($row['xi_record_bytes'] ?? strlen($row['form_data'] ?? '')) > self::MAX_RECORD_BYTES) { return self::budget_error(); }
        if (!$row || !($record = self::record($row))) { return self::error('not_found', 'Native inquiry not found on this site.', 404); }
        return array('row' => $row, 'record' => $record, 'flags' => self::flags($row, $record));
    }
    public static function get_inquiry($input) {
        $start = microtime(true);
        $filters = self::single_input($input); if (is_wp_error($filters)) { return $filters; }
        $entry = self::read_single($filters); if (is_wp_error($entry)) { return $entry; }
        $out = self::envelope($filters, array('complete' => true, 'scope' => 'one native inquiry', 'includes_test_spam_trash' => true, 'google_actual_attribution' => 'unknown',
            'contact_redaction' => $filters['include_contacts'] ? 'authorized_contact_details' : 'best_effort_known_identifiers_and_patterns'));
        $out['item'] = self::project_record($entry['row'], $entry['record'], $entry['flags'], $filters);
        return self::within_budget($start) ? $out : self::budget_error();
    }
    private static function safe_option_row($id) {
        global $wpdb;
        // Read the exact queue option without invoking queue code. Serialized payload is discarded and never projected.
        $raw = $wpdb->get_var($wpdb->prepare('SELECT /*+ MAX_EXECUTION_TIME(2000) */ CASE WHEN OCTET_LENGTH(option_value)<=%d THEN option_value ELSE NULL END FROM ' . $wpdb->options . ' WHERE option_name=%s LIMIT 1', self::MAX_OPTION_BYTES, XI_QUEUE_PREFIX . 'job_v1_' . $id));
        if ($wpdb->last_error || !is_string($raw) || $raw === '') { return array('status' => 'unknown', 'record_available' => false); }
        $value = @unserialize($raw, array('allowed_classes' => false));
        if (!is_array($value)) { return array('status' => 'unknown', 'record_available' => false); }
        $out = array('record_available' => true, 'status' => in_array($value['status'] ?? '', array('invalid', 'queued', 'retry', 'sending', 'success', 'rejected', 'failed', 'trashed', 'deleted'), true) ? $value['status'] : 'unknown');
        foreach (array('attempts', 'next_at') as $key) { $out[$key] = isset($value[$key]) && is_numeric($value[$key]) ? max(0, (int)$value[$key]) : null; }
        $out['lead_id'] = is_string($value['lead_id'] ?? null) && preg_match('/^[1-9][0-9]{0,30}$/D', $value['lead_id']) ? $value['lead_id'] : null;
        foreach (array('created_at', 'updated_at') as $key) { $date = self::parse_date($value[$key] ?? null); $out[$key . '_utc'] = $date ? $date->format('Y-m-d\TH:i:s\Z') : null; }
        $out['error_detail'] = 'not_exposed'; $out['customer_grade'] = 'unknown'; $out['order_stage'] = 'unknown';
        return $out;
    }
    private static function safe_receipt_row($id) {
        global $wpdb;
        $table = self::receipt_table();
        if (!self::identifier_safe($table)) { return array('record_available' => false, 'status' => 'unknown'); }
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists !== $table) { return array('record_available' => false, 'status' => 'unknown'); }
        // Whitelist: no token/hash, request UUID or claim owner can enter this result.
        $row = $wpdb->get_row($wpdb->prepare('SELECT /*+ MAX_EXECUTION_TIME(2000) */ submission_id,created_utc,expires_utc,is_test,consent_at_submit,ga4_state,ads_state,ga4_claimed_utc,ads_claimed_utc,ga4_callback_utc,ads_callback_utc FROM ' . $table . ' WHERE submission_id=%d LIMIT 1', $id), ARRAY_A);
        if ($wpdb->last_error || !$row) { return array('record_available' => false, 'status' => 'unknown'); }
        $out = array('record_available' => true, 'is_test' => !empty($row['is_test']), 'channels' => array(), 'consent_at_submit' => array());
        foreach (array('created_utc', 'expires_utc') as $key) { $date = self::parse_date($row[$key] ?? null); $out[$key] = $date ? $date->format('Y-m-d\TH:i:s\Z') : null; }
        $consent = is_string($row['consent_at_submit'] ?? null) && strlen($row['consent_at_submit']) <= 4096 ? json_decode($row['consent_at_submit'], true) : array();
        foreach (array('analytics_storage', 'ad_storage', 'ad_user_data', 'ad_personalization') as $key) { $out['consent_at_submit'][$key] = in_array($consent[$key] ?? '', array('granted', 'denied'), true) ? $consent[$key] : 'unknown'; }
        foreach (array('ga4', 'ads') as $channel) {
            $state = in_array($row[$channel . '_state'] ?? '', array('pending', 'claimed', 'callback'), true) ? $row[$channel . '_state'] : 'unknown';
            $out['channels'][$channel] = array('local_state' => $state, 'actual_google_received' => 'unknown', 'actual_google_attributed' => 'unknown');
            foreach (array('claimed_utc', 'callback_utc') as $suffix) { $date = self::parse_date($row[$channel . '_' . $suffix] ?? null); $out['channels'][$channel][$suffix] = $date ? $date->format('Y-m-d\TH:i:s\Z') : null; }
        }
        $out['meaning'] = 'Local pending/claim/callback bookkeeping does not prove Google receipt, reporting, attribution or consent at send time.';
        return $out;
    }
    public static function get_delivery_status($input) {
        $start = microtime(true);
        $filters = self::single_input($input); if (is_wp_error($filters)) { return $filters; }
        $entry = self::read_single($filters); if (is_wp_error($entry)) { return $entry; }
        $crm = self::safe_option_row($filters['id']); $receipt = self::safe_receipt_row($filters['id']);
        if (!self::within_budget($start)) { return self::budget_error(); }
        $out = self::envelope($filters, array('complete' => true, 'scope' => 'local delivery bookkeeping only', 'includes_test_spam_trash' => true,
            'crm_available' => $crm['record_available'], 'receipt_available' => $receipt['record_available'], 'external_api_called' => false));
        $out['delivery'] = array('inquiry_number' => $filters['inquiry_number'], 'flags' => $entry['flags'],
            'sync_requested_at_submit' => !empty($entry['record']['sync_requested']), 'crm' => $crm, 'conversion_receipt' => $receipt,
            'google_actual_attribution' => 'unknown', 'crm_verified_grade' => 'unknown', 'crm_order' => 'unknown');
        return $out;
    }
}

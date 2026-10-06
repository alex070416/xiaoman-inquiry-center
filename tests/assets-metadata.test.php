<?php
// Offline WordPress fixture: no network, DB writes, Google calls or inquiry submissions.
define('ABSPATH', __DIR__ . '/fixture-only/');
define('ARRAY_A', 'ARRAY_A');
define('XI_HOST', 'fixture.example.invalid');
define('XI_FILE', __FILE__);
define('XI_VERSION', '2.0.0-beta.9');
define('XI_PROFILE', array('number_prefix' => 'FW-N'));

$fixture = array();
function wp_parse_args($value, $defaults) { return array_merge($defaults, $value); }
function get_option($key, $default = false) { global $fixture; return $key === 'home' ? 'https://'.XI_HOST : ($key === XI_Conversions::OPTION ? $fixture['config'] : $default); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function untrailingslashit($value) { return rtrim($value, '/'); }
function home_url($path = '') { return 'https://'.XI_HOST.$path; }
function admin_url($path) { return home_url('/wp-admin/'.$path); }
function plugins_url($path, $file) { return home_url('/wp-content/plugins/fixture/'.$path); }
function is_admin() { return false; }
function post_type_exists($type) { return $type === 'rcb-cookie'; }
function get_posts($args) { global $fixture; $name = $args['meta_value']; return isset($fixture['services'][$name]) ? array((object)array('ID' => $name, 'post_status' => $fixture['services'][$name]['status'])) : array(); }
function get_post_meta($id, $key, $single) { global $fixture; return $fixture['services'][$id]['types'] ?? array(); }
function absint($value) { return abs((int)$value); }
function wp_unslash($value) { return stripslashes($value); }
function wp_json_encode($value) { return json_encode($value); }
function wp_enqueue_script($handle, $url, $deps, $version, $footer) { global $fixture; $fixture['enqueued'] = compact('handle', 'url', 'version'); }
function wp_add_inline_script($handle, $script, $position) { global $fixture; $fixture['inline'] = $script; }
function update_option(...$args) { throw new Exception('Unexpected option write'); }
function delete_option(...$args) { throw new Exception('Unexpected option deletion'); }
final class XI_Native_Inquiry_Center {
    static function thank_you_url($record) { return home_url('/thank-you/'); }
    static function number($id) { return 'FW-N'.$id; }
    static function row($id) { global $fixture; return $fixture['saved'][$id] ?? null; }
    static function record($saved) { return is_array($saved) ? $saved : array(); }
}
final class XI_Native_Inquiry_Trash {
    static function has($id) { global $fixture; return in_array($id, $fixture['trash'], true); }
}
final class ReadOnlyFixtureDB {
    public $prefix = 'fixture_';
    function prepare($query, ...$args) { global $fixture; $fixture['prepared'][] = array($query, $args); return $query; }
    function get_row($query, $format) { global $fixture; $fixture['reads'][] = $query; return $fixture['row']; }
    function get_col($query) { global $fixture; $fixture['reads'][] = $query; return array('ga4_claim_owner', 'ads_claim_owner'); }
    function query(...$args) { throw new Exception('Unexpected DB mutation'); }
    function insert(...$args) { throw new Exception('Unexpected DB insertion'); }
    function update(...$args) { throw new Exception('Unexpected DB update'); }
    function delete(...$args) { throw new Exception('Unexpected DB deletion'); }
}
$wpdb = new ReadOnlyFixtureDB();
require dirname(__DIR__).'/xiaoman-inquiry-center/conversions.php';

function fixture() {
    global $fixture;
    $expiry = gmdate('Y-m-d H:i:s', time() + 900);
    $fixture = array(
        'config' => array('enabled' => true, 'site_host' => XI_HOST, 'cutover_id' => 438, 'ga4_id' => 'G-FIXTURE', 'ads_id' => 'AW-123', 'ads_label' => 'FORM', 'analytics_service' => 'analytics', 'ads_service' => 'ads', 'user_data_service' => 'ec', 'thank_you_paths' => array('default' => '/thank-you/', 'fr' => '/fr/merci/')),
        'services' => array('analytics' => array('status' => 'publish', 'types' => array('analytics_storage')), 'ads' => array('status' => 'publish', 'types' => array('ad_storage')), 'ec' => array('status' => 'publish', 'types' => array('ad_user_data'))),
        'row' => array('id' => 50, 'submission_id' => 500, 'token_hash' => hash('sha256', str_repeat('a', 64)), 'request_uuid' => 'verified-request', 'expires_utc' => $expiry, 'ga4_state' => 'pending', 'ads_state' => 'claimed', 'ads_claim_owner' => str_repeat('b', 32)),
        'saved' => array(500 => array('uuid' => 'verified-request', 'values' => array('F5' => 'private@example.invalid'))), 'trash' => array(), 'prepared' => array(), 'reads' => array(),
    );
    XI_Conversions::$enqueued = false;
    XI_Conversions::$owner_schema = null;
    $_GET = array('form' => 'quick_quote', 'inquiry_id' => 'FW-N500', 'xi_receipt' => str_repeat('a', 64));
    $_SERVER['REQUEST_URI'] = '/thank-you/?form=quick_quote&inquiry_id=FW-N500&xi_receipt='.str_repeat('a', 64);
}
function capture() {
    global $fixture;
    $before = $fixture['row'];
    XI_Conversions::assets();
    check($fixture['row'] === $before, 'metadata publication must not alter receipt state or original expiry');
    $inline = $fixture['inline'] ?? '';
    check(strpos($inline, 'window.XIConversionConfig=') === 0, 'frontend config was published');
    return json_decode(substr($inline, strlen('window.XIConversionConfig='), -1), true);
}
function check($condition, $message) { if (!$condition) throw new Exception($message); }
function no_receipt($pub) { check(!isset($pub['receipt_expires_at']) && !isset($pub['receipt_pending_channels']), 'invalid or unrelated request must not publish receipt metadata'); }
function run_case($label, $test) { fixture(); $test(); echo "PASS $label\n"; }

run_case('valid original receipt publishes exact UTC expiry and definitely pending mask, without owner or customer data', function() {
    global $fixture;
    $pub = capture();
    check($pub['receipt_expires_at'] === strtotime($fixture['row']['expires_utc'].' UTC'), 'original expiry changed');
    check($pub['receipt_pending_channels'] === array('ga4' => true, 'ads' => false), 'claimed channel became recoverable');
    check($pub['pending_storage_service'] === 'analytics', 'storage must use the validated analytics declaration');
    check(!isset($pub['receipt']) && !isset($pub['claim_owner']) && strpos(json_encode($pub), 'private@') === false && strpos(json_encode($pub), str_repeat('b', 32)) === false, 'private data or owner leaked');
    check($fixture['prepared'][0][1] === array(hash('sha256', str_repeat('a', 64))), 'lookup must use original token hash');
});
run_case('clean URL has no receipt metadata and does not read receipt table', function() { global $fixture; unset($_GET['xi_receipt']); no_receipt(capture()); check(!array_filter($fixture['reads'], function($sql) { return strpos($sql, 'SELECT *') === 0; }), 'receipt row was read without bearer'); });
run_case('unrelated page cannot publish metadata', function() { $_SERVER['REQUEST_URI'] = '/products/'; no_receipt(capture()); });
run_case('expired original receipt cannot restart its TTL', function() { global $fixture; $fixture['row']['expires_utc'] = gmdate('Y-m-d H:i:s', time() - 1); no_receipt(capture()); });
run_case('trash, wrong number and saved UUID mismatch cannot publish metadata', function() { global $fixture; $fixture['trash'] = array(500); no_receipt(capture()); XI_Conversions::$enqueued = false; $fixture['trash'] = array(); $_GET['inquiry_id'] = 'FW-N501'; no_receipt(capture()); XI_Conversions::$enqueued = false; $_GET['inquiry_id'] = 'FW-N500'; $fixture['saved'][500]['uuid'] = 'other-request'; no_receipt(capture()); });
run_case('callback and unknown state never become pending', function() { global $fixture; $fixture['row']['ga4_state'] = 'callback'; $fixture['row']['ads_state'] = 'unknown'; check(capture()['receipt_pending_channels'] === array('ga4' => false, 'ads' => false), 'non-pending states were marked recoverable'); });
run_case('only a published analytics-purpose declaration enables storage', function() { global $fixture; $fixture['services']['analytics']['status'] = 'draft'; check(capture()['pending_storage_service'] === '', 'draft service enabled storage'); XI_Conversions::$enqueued = false; $fixture['services']['analytics']['status'] = 'publish'; $fixture['services']['analytics']['types'] = array('ad_storage'); check(capture()['pending_storage_service'] === '', 'wrong purpose enabled storage'); });
run_case('configured translated thank-you route publishes same original receipt metadata', function() { $_SERVER['REQUEST_URI'] = '/fr/merci/?form=quick_quote'; check(capture()['receipt_pending_channels']['ga4'] === true, 'translated route could not publish pending metadata'); });
run_case('owned and legacy JS use the new paired plugin asset revision', function() { global $fixture; capture(); check($fixture['enqueued']['version'] === XI_VERSION, 'new owned JS did not bypass its old asset cache key'); check(strpos($fixture['enqueued']['url'], '/conversions.js') !== false, 'owned JS URL changed'); XI_Conversions::$enqueued = false; XI_Conversions::$owner_schema = false; capture(); check($fixture['enqueued']['version'] === XI_VERSION, 'legacy asset version changed'); check(strpos($fixture['enqueued']['url'], '/conversions.legacy.js') !== false, 'legacy fallback changed'); });
echo "9 offline metadata cases passed; unexpected DB and option writes throw.\n";

<?php
/** Bundled conditional required-field module. */

if (!defined('ABSPATH')) exit;

final class XIFR_Conditional_Required {
 const OPTION = 'xi_required_v1';
 const VERSION = XI_VERSION;
 const CAP = 'manage_options';

 static function boot() {
  add_action('init', array(__CLASS__, 'ensure_defaults'), 99);
  add_filter('bricks/element/render_attributes', array(__CLASS__, 'attributes'), 40, 3);
  add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'), 40);
  add_filter('bricks/form/validate', array(__CLASS__, 'validate'), 60, 2);
  add_action('admin_menu', array(__CLASS__, 'admin_menu'),40);
  add_action('admin_post_xifr_save', array(__CLASS__, 'save'));
  add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'action_links'));
 }

 static function defaults() {
  return array(
   'post_type' => XI_Config::get()['post_type'],
   'field_name' => 'Message',
   'min_length' => 1,
   'error_message' => 'Please tell us your required model, quantity or application.',
   'campaign_codes' => array(),
   'categories' => array(),
  );
 }

 static function options() {
  $saved = get_option(self::OPTION, array());
  return wp_parse_args(is_array($saved) ? $saved : array(), self::defaults());
 }

 static function ensure_defaults() { if(get_option(self::OPTION,null)===null)add_option(self::OPTION,self::defaults(),'',false); }

 static function text($value, $limit = 500) {
  if (!is_scalar($value)) return '';
  $value = sanitize_text_field(wp_unslash((string) $value));
  return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
 }

 static function fields($settings) {
  $fields = array();
  foreach (($settings['fields'] ?? array()) as $field) {
   if (!is_array($field) || !empty($field['isHoneypot'])) continue;
   $name = (string) ($field['name'] ?? '');
   if ($name !== '') $fields[$name] = $field;
  }
  return $fields;
 }

 static function target($settings) {
  if(!XI_Native_Inquiry_Center::kind($settings))return false;
  $fields = self::fields($settings);
  return self::target_field_name($fields) !== '';
 }

 static function target_field_name($fields) {
  $configured = (string) (self::options()['field_name'] ?? 'Message');
  foreach (array_unique(array($configured, 'Message', 'F9')) as $name) {
   if ($name !== '' && isset($fields[$name])) return $name;
  }
  $semantic=XI_Native_Inquiry_Center::fields(array('fields'=>array_values($fields)));
  if(isset($semantic['F9']))return (string)$semantic['F9']['name'];
  return '';
 }

 static function term_matches($candidate, $rule_term_id, $taxonomy) {
  if (!$candidate instanceof WP_Term || $candidate->taxonomy !== $taxonomy) return false;
  if ((int) $candidate->term_id === (int) $rule_term_id) return true;
  return term_is_ancestor_of((int) $rule_term_id, (int) $candidate->term_id, $taxonomy);
 }

 static function page_required() {
  $options = self::options();
  $rules = is_array($options['categories']) ? $options['categories'] : array();
  if (!$rules) return false;

  if (is_tax()) {
   $term = get_queried_object();
   foreach ($rules as $rule) {
    if (empty($rule['archive'])) continue;
    if (self::term_matches($term, (int) ($rule['term_id'] ?? 0), (string) ($rule['taxonomy'] ?? ''))) return true;
   }
  }

  if (is_singular($options['post_type'])) {
   $post_id = get_queried_object_id();
   foreach ($rules as $rule) {
    if (empty($rule['detail'])) continue;
    $taxonomy = (string) ($rule['taxonomy'] ?? '');
    $rule_term_id = (int) ($rule['term_id'] ?? 0);
    if (!$taxonomy || !$rule_term_id) continue;
    $terms = wp_get_post_terms($post_id, $taxonomy);
    if (is_wp_error($terms)) continue;
    foreach ($terms as $term) if (self::term_matches($term, $rule_term_id, $taxonomy)) return true;
   }
  }
  return false;
 }

 static function campaign_from_url($url) {
  if (!is_string($url) || $url === '') return '';
  $query = wp_parse_url($url, PHP_URL_QUERY);
  if (!is_string($query)) return '';
  parse_str($query, $params);
  foreach (array('campaign_name', 'campaign', 'utm_campaign', 'campaign_id', 'campaignid', 'utm_id') as $key) {
   if (isset($params[$key]) && is_scalar($params[$key])) {
    $value = self::text($params[$key], 100);
    if ($value !== '') return $value;
   }
  }
  return '';
 }

 static function campaign_matches($campaign) {
  $campaign = strtolower(self::text($campaign, 100));
  if ($campaign === '') return false;
  foreach ((array) self::options()['campaign_codes'] as $code) {
   $code = strtolower(self::text($code, 100));
   if ($code === '') continue;
   if (strpos($campaign, $code) === 0) return true;
  }
  return false;
 }

 static function rules_hash() {
  $options = self::options();
  return hash('sha256', wp_json_encode(array($options['post_type'], $options['field_name'], $options['campaign_codes'], $options['categories'])));
 }

 static function base64url_encode($value) {
  return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
 }

 static function base64url_decode($value) {
  $padding = strlen($value) % 4;
  if ($padding) $value .= str_repeat('=', 4 - $padding);
  return base64_decode(strtr($value, '-_', '+/'), true);
 }

 static function token() {
  $payload = self::base64url_encode(wp_json_encode(array(
   'v' => 1,
   'page_required' => self::page_required() ? 1 : 0,
   // Shared cached HTML contains page rules only. Ad rules use the current
   // request/referrer or consented context at submission time.
   'campaign_required' => 0,
   'rules_hash' => self::rules_hash(),
   'host' => wp_parse_url(home_url(), PHP_URL_HOST),
  )));
  $signature = hash_hmac('sha256', $payload, wp_salt('auth'));
  return $payload . '.' . $signature;
 }

 static function verify_token($token) {
  if (!is_string($token) || strlen($token) > 3000 || strpos($token, '.') === false) return false;
  list($payload, $signature) = explode('.', $token, 2);
  if (!hash_equals(hash_hmac('sha256', $payload, wp_salt('auth')), $signature)) return false;
  $json = self::base64url_decode($payload);
  $data = is_string($json) ? json_decode($json, true) : null;
  if (!is_array($data) || ($data['rules_hash'] ?? '') !== self::rules_hash()) return false;
  if (($data['host'] ?? '') !== wp_parse_url(home_url(), PHP_URL_HOST)) return false;
  return !empty($data['page_required']) || !empty($data['campaign_required']);
 }

 static function attributes($attributes, $key, $element) {
  if ($key !== '_root' || ($element->name ?? '') !== 'form' || !self::target($element->settings ?? array())) return $attributes;
  $attributes[$key]['data-xifr-form'] = '1';
  $attributes[$key]['data-xifr-page-required'] = self::page_required() ? 'true' : 'false';
  $attributes[$key]['data-xifr-token'] = self::token();
  $options = self::options();
  $attributes[$key]['data-xifr-field'] = self::target_field_name(self::fields($element->settings ?? array()));
  $attributes[$key]['data-xifr-min-length'] = (string) max(1, (int) $options['min_length']);
  $attributes[$key]['data-xifr-campaign-codes'] = wp_json_encode(array_values((array) $options['campaign_codes']));
  return $attributes;
 }

 static function assets() {
  $options = self::options();
  wp_enqueue_script('xifr-conditional-required', plugins_url('conditional-required.js', __FILE__), array(), self::VERSION, array('strategy' => 'defer', 'in_footer' => true));
  wp_localize_script('xifr-conditional-required', 'XIFRConfig', array(
   'fieldName' => $options['field_name'],
   'minLength' => max(1, (int) $options['min_length']),
   'campaignCodes' => array_values(array_filter(array_map('strval', (array) $options['campaign_codes']))),
  ));
 }

 static function submitted_value($submitted, $field, $name) {
  $id = (string) ($field['id'] ?? '');
  foreach (array('form-field-' . $id, $name) as $key) {
   if ($key !== '' && isset($submitted[$key]) && is_scalar($submitted[$key])) return self::text($submitted[$key], 10000);
  }
  return '';
 }

 static function request_value($submitted, $name) {
  if (isset($submitted[$name]) && is_scalar($submitted[$name])) return wp_unslash((string) $submitted[$name]);
  if (isset($_POST[$name]) && is_scalar($_POST[$name])) return wp_unslash((string) $_POST[$name]);
  return '';
 }

 static function campaigns($submitted) {
  $values = array();
  $direct = self::request_value($submitted, 'xifr_campaign');
  if ($direct !== '') $values[] = $direct;
  $packet = self::request_value($submitted, 'xi_context');
  if ($packet !== '' && strlen($packet) <= 24000) {
   $decoded = json_decode($packet, true);
   if (is_array($decoded) && is_scalar($decoded['A1'] ?? null)) $values[] = (string) $decoded['A1'];
   if (is_array($decoded) && is_array($decoded['_request'] ?? null)) {
    $values[] = self::campaign_from_url((string) ($decoded['_request']['landing_ad_url'] ?? ''));
   }
  }
  $values[] = self::campaign_from_url(wp_unslash($_SERVER['HTTP_REFERER'] ?? ''));
  return array_values(array_filter(array_map(array(__CLASS__, 'text'), $values)));
 }

 static function validate($errors, $form) {
  if (!is_object($form) || !is_callable(array($form, 'get_settings')) || !is_callable(array($form, 'get_fields'))) return $errors;
  $settings = $form->get_settings();
  if (!self::target($settings)) return $errors;
  $options = self::options();
  $fields = self::fields($settings);
  $submitted = $form->get_fields();
  $required = self::verify_token(self::request_value($submitted, 'xifr_context')) || self::server_page_required($form,$submitted);
  if (!$required) foreach (self::campaigns($submitted) as $campaign) if (self::campaign_matches($campaign)) { $required = true; break; }
  if (!$required) return $errors;
  $field_name = self::target_field_name($fields);
  if ($field_name === '') return $errors;
  $message = trim(self::submitted_value($submitted, $fields[$field_name], $field_name));
  $length = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
  if ($length < max(1, (int) $options['min_length'])) $errors[] = self::text($options['error_message'], 500);
  return $errors;
 }

 static function server_page_required($form,$submitted) {
  $o=self::options();$pid=absint($form->get_post_id());
  $record=XI_Native_Inquiry_Center::$requests[XI_Native_Inquiry_Center::context_key($form)]??array();
  $pid=absint($record['product']['id']??$pid);
  $values=XI_Native_Inquiry_Center::values($form->get_settings(),$submitted);
  $cid=absint($values['category_id']??0);$signature=$values['category_signature']??'';
  $category=$cid&&is_string($signature)&&hash_equals(XI_Native_Inquiry_Center::sign_category($cid,$form->get_id()),$signature)?get_term($cid):null;
  foreach((array)$o['categories'] as $rule){
   if(!empty($rule['detail'])&&get_post_type($pid)===$o['post_type']){$terms=wp_get_post_terms($pid,$rule['taxonomy']);if(!is_wp_error($terms))foreach($terms as $term)if(self::term_matches($term,$rule['term_id'],$rule['taxonomy']))return true;}
   if(!empty($rule['archive'])&&self::term_matches($category,$rule['term_id'],$rule['taxonomy']))return true;
  }
  return false;
 }

 static function action_links($links) {
  array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=xifr-conditional-required')) . '">设置</a>');
  return $links;
 }

 static function admin_menu() {add_submenu_page(XI_Native_Inquiry_Center::PAGE,'表单强制必填','表单强制必填',self::CAP,'xifr-conditional-required',array(__CLASS__,'settings_page'));}

 static function save() {
  if (!current_user_can(self::CAP)) wp_die('Permission denied.');
  check_admin_referer('xifr_save');
  $defaults = self::defaults();
  $post_type = sanitize_key($_POST['post_type'] ?? $defaults['post_type']);
  if (!post_type_exists($post_type)) $post_type = $defaults['post_type'];
  $field_name = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['field_name'] ?? $defaults['field_name']));
  if ($field_name === '') $field_name = $defaults['field_name'];
  $codes = preg_split('/[\r\n,]+/', (string) ($_POST['campaign_codes'] ?? ''));
  $codes = array_values(array_unique(array_filter(array_map(static function($code) {
   return strtolower(XIFR_Conditional_Required::text($code, 100));
  }, $codes))));
  $categories = array();
  foreach ((array) ($_POST['category'] ?? array()) as $key => $values) {
   if (!is_array($values) || strpos($key, ':') === false) continue;
   list($taxonomy, $term_id) = explode(':', sanitize_text_field($key), 2);
   $taxonomy = sanitize_key($taxonomy); $term_id = absint($term_id);
   $term = get_term($term_id, $taxonomy);
   if (!$term || is_wp_error($term)) continue;
   $archive = !empty($values['archive']) ? 1 : 0;
   $detail = !empty($values['detail']) ? 1 : 0;
   if (!$archive && !$detail) continue;
   $categories[$taxonomy . ':' . $term_id] = compact('taxonomy', 'term_id', 'archive', 'detail');
  }
  update_option(self::OPTION, array(
   'post_type' => $post_type,
   'field_name' => $field_name,
   'min_length' => max(1, min(5000, absint($_POST['min_length'] ?? 1))),
   'error_message' => self::text($_POST['error_message'] ?? $defaults['error_message'], 500),
   'campaign_codes' => $codes,
   'categories' => $categories,
  ), false);
  wp_cache_flush();
  do_action('litespeed_purge_all', 'Conditional form rules updated');
  wp_safe_redirect(add_query_arg(array('page' => 'xifr-conditional-required', 'updated' => '1'), admin_url('admin.php')));
  exit;
 }

 static function settings_page() {
  if (!current_user_can(self::CAP)) return;
  $options = self::options();
  $post_types = get_post_types(array('public' => true), 'objects');
  $taxonomies = get_object_taxonomies($options['post_type'], 'objects');
  ?>
  <div class="wrap xifr-wrap">
   <h1>表单强制必填</h1>
   <p>这里的规则直接作用于 Bricks 原生表单，不需要在表单模板中设置 required。页面表单与快速弹窗表单都会生效。</p>
   <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>规则已保存。</p></div><?php endif; ?>
   <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="xifr_save">
    <?php wp_nonce_field('xifr_save'); ?>
    <div class="xifr-card">
     <h2>表单字段</h2>
     <table class="form-table"><tbody>
      <tr><th><label for="xifr-post-type">产品文章类型</label></th><td><select id="xifr-post-type" name="post_type">
       <?php foreach ($post_types as $post_type): ?><option value="<?php echo esc_attr($post_type->name); ?>" <?php selected($options['post_type'], $post_type->name); ?>><?php echo esc_html($post_type->labels->singular_name . ' (' . $post_type->name . ')'); ?></option><?php endforeach; ?>
      </select><p class="description">保存后会按所选文章类型显示对应分类。</p></td></tr>
      <tr><th><label for="xifr-field">留言字段名</label></th><td><input id="xifr-field" class="regular-text" name="field_name" value="<?php echo esc_attr($options['field_name']); ?>"><p class="description">当前三个网站的原生留言字段统一为 Message；插件兼容旧字段 F9。</p></td></tr>
      <tr><th><label for="xifr-min">最少字符数</label></th><td><input id="xifr-min" type="number" min="1" max="5000" name="min_length" value="<?php echo esc_attr((int) $options['min_length']); ?>"></td></tr>
      <tr><th><label for="xifr-error">错误提示</label></th><td><input id="xifr-error" class="large-text" name="error_message" value="<?php echo esc_attr($options['error_message']); ?>"></td></tr>
     </tbody></table>
    </div>
    <div class="xifr-card">
     <h2>分类与详情页条件</h2>
     <p>“分类页”包含该分类及其子分类页面；“详情页”包含归属该分类或子分类的产品。</p>
     <table class="widefat striped"><thead><tr><th>分类</th><th>分类页留言必填</th><th>该分类详情页留言必填</th></tr></thead><tbody>
      <?php $has_terms = false; foreach ($taxonomies as $taxonomy):
       if (!$taxonomy->public) continue;
       $terms = get_terms(array('taxonomy' => $taxonomy->name, 'hide_empty' => false));
       if (is_wp_error($terms) || !$terms) continue;
       $has_terms = true; ?>
       <tr class="xifr-tax"><th colspan="3"><?php echo esc_html($taxonomy->labels->singular_name . ' (' . $taxonomy->name . ')'); ?></th></tr>
       <?php foreach ($terms as $term): $key = $taxonomy->name . ':' . $term->term_id; $rule = $options['categories'][$key] ?? array(); ?>
        <tr><td><?php echo esc_html($term->name); ?><code><?php echo esc_html($term->slug); ?></code></td>
         <td><label><input type="checkbox" name="category[<?php echo esc_attr($key); ?>][archive]" value="1" <?php checked(!empty($rule['archive'])); ?>> 启用</label></td>
         <td><label><input type="checkbox" name="category[<?php echo esc_attr($key); ?>][detail]" value="1" <?php checked(!empty($rule['detail'])); ?>> 启用</label></td></tr>
       <?php endforeach; endforeach; ?>
      <?php if (!$has_terms): ?><tr><td colspan="3">该文章类型暂时没有可用分类。请先保存正确的产品文章类型。</td></tr><?php endif; ?>
     </tbody></table>
    </div>
    <div class="xifr-card">
     <h2>广告 campaign 条件</h2>
     <p>每行填写一个代码，按“开头”匹配。例如 <code>pm</code> 会匹配 <code>pmex</code>、<code>pmfk-ou</code>、<code>pmtr-ty-la</code> 等所有以 pm 开头的 campaign。</p>
     <textarea name="campaign_codes" rows="7" class="large-text code"><?php echo esc_textarea(implode("\n", (array) $options['campaign_codes'])); ?></textarea>
    </div>
    <?php submit_button('保存必填规则'); ?>
   </form>
  </div>
  <style>
   .xifr-wrap{max-width:1100px}.xifr-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:18px 22px;margin:18px 0}.xifr-card h2{margin-top:0}.xifr-tax th{background:#f0f0f1!important}.xifr-card code{margin-left:8px}.xifr-card .widefat th:nth-child(n+2),.xifr-card .widefat td:nth-child(n+2){width:210px;text-align:center}
  </style>
  <?php
 }
}

XIFR_Conditional_Required::boot();

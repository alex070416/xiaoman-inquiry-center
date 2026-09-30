<?php
/** Bundled inquiry dashboard module. */
if (!defined('ABSPATH')) { exit; }
final class XI_Dashboard {
    const VERSION = XI_VERSION;
    public static $center;
    public static $trash;
    public static $sync;
    public static $prefix;
    public static $service_cap;
    public static $service_role;
    public static $legacy = false;
    static function boot() {
        foreach (array('XI') as $prefix) {
            $center = $prefix . '_Native_Inquiry_Center';
            if (class_exists($center) && is_callable(array($center, 'base_where'))) {
                self::$center = $center;
                self::$trash = $prefix . '_Native_Inquiry_Trash';
                self::$sync = $prefix . '_Native_Xiaoman_Sync';
                self::$prefix = strtolower($prefix) . '_native_';
                break;
            }
        }
        if (!self::$center || !class_exists(self::$trash) || !class_exists(self::$sync)) { return; }
        $c = self::$center;
        self::$legacy = false;
        $site = str_replace('-inquiry-center', '', $c::PAGE);
        self::$service_cap = self::$legacy ? $site . '_inquiry_view' : $c::CAP;
        self::$service_role = self::$legacy ? $site . '_inquiry_viewer' : $c::ROLE;
        if (self::$legacy) {
            require_once __DIR__ . '/legacy-service.php';
            UID_Legacy_Service::boot();
        }
        add_action('admin_menu', array(__CLASS__, 'replace_page'), 100);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        // Keep the existing action names: the restricted service account already permits this route.
        foreach (array('read' => 'read_action', 'export' => 'export') as $action => $method) {
            remove_action('admin_post_' . self::$prefix . $action, array(self::$center, $method));
            add_action('admin_post_' . self::$prefix . $action, array(__CLASS__, $method));
        }
    }
    static function manage() { return current_user_can('manage_options'); }
    static function allowed() { return self::$center && (self::manage() || current_user_can(self::$service_cap)); }
    static function guard($manage = false) {
        if (!self::allowed() || ($manage && !self::manage())) {
            wp_die('你没有执行此询盘操作的权限。', '', array('response' => 403));
        }
    }
    static function replace_page() {
        $c = self::$center;
        if (!self::allowed()) { return; }
        $hook = get_plugin_page_hookname($c::PAGE, '');
        remove_action($hook, array($c, 'page'));
        add_action($hook, array(__CLASS__, 'page'));
    }
    static function assets($hook) {
        $c = self::$center;
        if (!$c || $hook !== get_plugin_page_hookname($c::PAGE, '')) { return; }
        wp_enqueue_style('unified-inquiry-dashboard', plugins_url('dashboard.css', __FILE__), array('dashicons'), self::VERSION);
        wp_enqueue_script('unified-inquiry-dashboard', plugins_url('dashboard.js', __FILE__), array(), self::VERSION, true);
    }
    static function value($input, $key, $length = 200) {
        return isset($input[$key]) && is_scalar($input[$key]) ? mb_substr(sanitize_text_field((string)$input[$key]), 0, $length) : '';
    }
    static function filters($input) {
        $f = array();
        foreach (array('status','kind','s','view','from','to','country','campaign','keyword','adgroup','device','source') as $key) {
            $f[$key] = self::value($input, $key);
        }
        if (!in_array($f['status'], array('read','unread','spam'), true)) { $f['status'] = ''; }
        if (!in_array($f['kind'], array('quick','detail'), true)) { $f['kind'] = ''; }
        $f['view'] = self::manage() && $f['view'] === 'trash' ? 'trash' : '';
        if (!in_array($f['device'], array('PC','Mobile'), true)) { $f['device'] = ''; }
        foreach (array('from', 'to') as $key) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $f[$key], wp_timezone());
            if (!$date || $date->format('Y-m-d') !== $f[$key]) { $f[$key] = ''; }
        }
        if ($f['from'] && $f['to'] && $f['from'] > $f['to']) {
            list($f['from'], $f['to']) = array($f['to'], $f['from']);
        }
        return $f;
    }
    static function field_sql($path) {
        $c = self::$center;
        // Both JSON layers are guarded, including old or malformed submission rows.
        $outer = "CASE WHEN JSON_VALID(form_data) THEN form_data ELSE '{}' END";
        $inner = "JSON_UNQUOTE(JSON_EXTRACT($outer, '$.\"" . $c::META . "\".value'))";
        return "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID($inner) THEN $inner ELSE '{}' END, '$.$path')), 'null')";
    }
    static function where($f) {
        global $wpdb;
        $c = self::$center; $t = self::$trash;
        $where = $c::base_where() . $t::clause($f['view'] === 'trash');
        if ($f['status'] === 'unread') { $where .= " AND COALESCE(status,'') NOT IN ('read','spam')"; }
        elseif ($f['status'] !== '') { $where .= $wpdb->prepare(' AND status=%s', $f['status']); }
        if ($f['kind'] !== '') { $where .= $wpdb->prepare(' AND ' . self::field_sql('kind') . '=%s', $f['kind']); }
        if ($f['s'] !== '') {
            if (preg_match('/^[A-Z]+-N([1-9][0-9]*)$/iD', $f['s'], $match)) {
                $where .= $wpdb->prepare(' AND id=%d', (int)$match[1]);
            } else {
                $where .= $wpdb->prepare(' AND form_data LIKE %s', '%' . $wpdb->esc_like($f['s']) . '%');
            }
        }
        foreach (array('country'=>'country') as $key=>$path) {
            if ($f[$key] !== '') { $where .= $wpdb->prepare(' AND ' . self::field_sql($path) . ' LIKE %s', '%' . $wpdb->esc_like($f[$key]) . '%'); }
        }
        if ($f['device'] !== '') { $where .= $wpdb->prepare(' AND ' . self::field_sql('hd.H3') . '=%s', $f['device']); }
        foreach (array('from'=>'>=', 'to'=>'<') as $key=>$operator) {
            if (!$f[$key]) { continue; }
            $date = new DateTimeImmutable($f[$key] . ' 00:00:00', wp_timezone());
            if ($key === 'to') { $date = $date->modify('+1 day'); }
            $where .= $wpdb->prepare(' AND ' . self::field_sql('submitted_at') . " $operator %s", $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        }
        return $where . self::attribution_clause($f);
    }
    static function attribution_clause($f) {
        $terms = array_filter(array_intersect_key($f,array_flip(array('campaign','keyword','adgroup','source'))), static function($v){ return $v !== ''; });
        if (!$terms) { return ''; }
        global $wpdb; $sql='';
        $paths=array('campaign'=>array('hd.A1','attribution.campaign_name','attribution.campaign','attribution.campaign_id'), 'keyword'=>array('hd.A2','attribution.keyword'), 'adgroup'=>array('hd.A3','attribution.adgroup_name','attribution.adgroup','attribution.adgroup_id'), 'source'=>array('source','attribution.source_label'));
        foreach($terms as $field=>$term){$or=array();foreach($paths[$field] as $path)$or[]=$wpdb->prepare(self::field_sql($path).' LIKE %s','%'.$wpdb->esc_like($term).'%');$sql.=' AND ('.implode(' OR ',$or).')';}
        return $sql;
    }
    static function stats($f) {
        global $wpdb; $c = self::$center;
        $f['status'] = ''; $f['view'] = '';
        $where = self::where($f);
        $today = (new DateTimeImmutable('today', wp_timezone()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $sql = 'SELECT COUNT(*) AS total, SUM(COALESCE(status,\'\') NOT IN (\'read\',\'spam\')) AS unread, SUM(status=\'read\') AS processed, SUM(status=\'spam\') AS spam, SUM(COALESCE(status,\'\') NOT IN (\'read\',\'spam\') AND ' . self::field_sql('submitted_at') . '>=%s) AS today FROM ' . $c::table() . ' WHERE ' . $where;
        $stats = array_map('intval', (array)$wpdb->get_row($wpdb->prepare($sql, $today), ARRAY_A));
        if (self::manage()) {
            $f['view'] = 'trash';
            $stats['trash'] = (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . $c::table() . ' WHERE ' . self::where($f));
        }
        return $stats;
    }
    static function url($args = array()) { $c = self::$center; return $c::url(array_filter($args, static function($v) { return $v !== ''; })); }
    static function icon($name) { return '<span class="dashicons dashicons-' . esc_attr($name) . '" aria-hidden="true"></span>'; }
    static function hidden($f) { foreach ($f as $k=>$v) { echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string)$v) . '">'; } }
    static function select($key, $label, $options, $selected) {
        echo '<label class="uid-field"><span>' . esc_html($label) . '</span><select name="' . esc_attr($key) . '">';
        foreach ($options as $value=>$text) { echo '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($text) . '</option>'; }
        echo '</select></label>';
    }
    static function input($key, $label, $value, $type = 'text', $placeholder = '') {
        echo '<label class="uid-field"><span>' . esc_html($label) . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '"></label>';
    }
    static function page() {
        self::guard(); $c = self::$center;
        $detail = absint($_GET['inquiry'] ?? 0);
        $f = self::filters(wp_unslash($_GET));
        echo '<div class="wrap uid-dashboard fwn-inbox' . (self::manage() ? '' : ' uid-service') . '"><header class="uid-header"><div><h1>询盘中心</h1><p>查看并管理来自网站的客户询盘，及时跟进，把握每一个商机。</p></div>';
        if (self::manage() && !$detail && $c::exists()) {
            echo '<form class="uid-export" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            self::hidden(array_merge($f, array('action'=>self::$prefix . 'export')));
            wp_nonce_field(self::$prefix . 'export');
            echo '<button class="button uid-outline">' . self::icon('download') . '导出当前筛选 CSV</button></form>';
        }
        echo '</header>';
        if (!$c::exists()) { echo '<div class="notice notice-warning"><p>询盘存储尚未开启，请联系管理员。</p></div></div>'; return; }
        if ($detail) { self::detail($detail); echo '</div>'; return; }
        $stats = self::stats($f);
        $cards = array(
            array('total','全部询盘','email','','','包含垃圾询盘，不含回收站'),
            array('unread','未读询盘','format-chat','unread','','尚未标为已读的有效询盘'),
            array('processed','已处理','yes-alt','read','','已标为已读的询盘，不含垃圾询盘'),
        );
        if (self::manage()) { $cards[] = array('trash','回收站','trash','','trash','已删除到回收站的询盘'); }
        echo '<div class="uid-cards" style="--uid-card-count:' . count($cards) . '">';
        foreach ($cards as $card) {
            $active = $f['status'] === $card[3] && $f['view'] === $card[4];
            $url = self::url(array_merge($f, array('status'=>$card[3], 'view'=>$card[4])));
            echo '<a class="uid-card uid-card-' . esc_attr($card[0]) . ($active ? ' is-active' : '') . '" href="' . esc_url($url) . '" title="' . esc_attr($card[5]) . '"' . ($active ? ' aria-current="page"' : '') . '><span class="uid-card-icon">' . self::icon($card[2]) . '</span><span><span class="uid-card-label">' . esc_html($card[1]) . '</span><span class="uid-card-value">' . (int)($stats[$card[0]] ?? 0) . '</span>';
            if ($card[0] === 'unread' && !empty($stats['today'])) { echo '<span class="uid-today" title="今日新增且仍未读">今日 +' . (int)$stats['today'] . '</span>'; }
            echo '</span></a>';
        }
        echo '</div>';
        self::notices();
        $advanced = false;
        foreach (array('from','to','country','campaign','keyword','adgroup','device','source') as $key) { if ($f[$key] !== '') { $advanced = true; } }
        echo '<form class="uid-filters" method="get"><input type="hidden" name="page" value="' . esc_attr($c::PAGE) . '"><input type="hidden" name="view" value="' . esc_attr($f['view']) . '"><div class="uid-filter-main">';
        self::select('kind','表单',array(''=>'全部表单','quick'=>'快速表单','detail'=>'详情页表单'),$f['kind']);
        self::select('status','阅读状态',array(''=>'全部','unread'=>'未读','read'=>'已读','spam'=>'垃圾询盘'),$f['status']);
        echo '<label class="uid-search">' . self::icon('search') . '<span class="screen-reader-text">搜索询盘</span><input type="search" name="s" value="' . esc_attr($f['s']) . '" placeholder="编号 / 姓名 / 邮箱 / 广告 / 关键词"></label><button class="button button-primary uid-search-button">搜索</button><button class="button uid-outline uid-advanced-toggle" type="button" aria-controls="uid-advanced" aria-expanded="' . ($advanced ? 'true' : 'false') . '">' . self::icon('filter') . '高级筛选</button><a class="button uid-reset" href="' . esc_url(self::url()) . '">' . self::icon('image-rotate') . '重置</a></div>';
        echo '<div class="uid-advanced" id="uid-advanced"' . ($advanced ? '' : ' hidden') . '>';
        self::input('from','开始日期',$f['from'],'date'); self::input('to','结束日期',$f['to'],'date');
        self::input('country','国家',$f['country'],'text','输入国家名称'); self::input('source','询盘来源',$f['source'],'text','例如 Google Ads');
        self::input('campaign','广告系列',$f['campaign']); self::input('keyword','关键词',$f['keyword']); self::input('adgroup','广告组',$f['adgroup']);
        self::select('device','设备',array(''=>'全部设备','PC'=>'PC','Mobile'=>'Mobile'),$f['device']);
        echo '<p class="uid-filter-help">日期按网站时区筛选；文本条件为包含匹配。点击“搜索”应用所有条件。</p></div></form>';
        if ($f['view'] === 'trash') { echo '<p class="uid-context">回收站中的询盘暂停同步；恢复后继续处理。网站永久删除不会删除小满已有线索。</p>'; }
        self::listing($f);
        echo '</div>';
    }
    static function notices() {
        if (isset($_GET['updated'])) {
            $verbs = array('read'=>'已标为已读','unread'=>'已标为未读','spam'=>'已标为垃圾询盘','unspam'=>'垃圾询盘已转为已读','trash'=>'已移入回收站','restore'=>'已恢复','delete'=>'已永久删除');
            $op = self::value($_GET, 'operation');
            echo '<div class="notice notice-success"><p>' . esc_html($verbs[$op] ?? '已更新') . ' ' . absint($_GET['updated']) . ' 条询盘。</p></div>';
        }
        if (!empty($_GET['skipped'])) { echo '<div class="notice notice-info"><p>已跳过 ' . absint($_GET['skipped']) . ' 条垃圾询盘。使用“垃圾转已读”才能移出垃圾状态。</p></div>'; }
        if (!empty($_GET['failed'])) { echo '<div class="notice notice-warning"><p>' . absint($_GET['failed']) . ' 条未处理，可能正在同步或状态已改变，请刷新后重试。</p></div>'; }
    }
    static function pagination($p, $total, $f) {
        echo '<nav class="uid-pagination" aria-label="询盘分页"><span>共 <strong>' . (int)$total . '</strong> 条 · 每页 50 条</span><span class="uid-pages">';
        if ($p['page'] > 1) { echo '<a class="button" href="' . esc_url(self::url(array_merge($f,array('paged'=>$p['page']-1)))) . '">上一页</a>'; }
        echo '<span>第 ' . (int)$p['page'] . ' / ' . (int)$p['pages'] . ' 页</span>';
        if ($p['page'] < $p['pages']) { echo '<a class="button" href="' . esc_url(self::url(array_merge($f,array('paged'=>$p['page']+1)))) . '">下一页</a>'; }
        echo '</span></nav>';
    }
    static function listing($f) {
        global $wpdb; $c = self::$center; $sync = self::$sync;
        $where = self::where($f);
        $count = (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . $c::table() . ' WHERE ' . $where);
        $p = $c::paging($count, $_GET['paged'] ?? 1);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $c::table() . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT %d OFFSET %d', $c::PER_PAGE, $p['offset']), ARRAY_A);
        echo '<section class="uid-results"><form class="uid-bulk" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        self::hidden(array_merge($f,array('action'=>self::$prefix.'read','paged'=>$p['page']))); wp_nonce_field(self::$prefix.'read');
        echo '<div class="uid-bulk-toolbar"><div class="uid-bulk-buttons">';
        if ($f['view'] !== 'trash') {
            foreach (array('read'=>'标为已读','unread'=>'标为未读','spam'=>'标为垃圾询盘','unspam'=>'垃圾转已读') as $value=>$label) {
                echo '<button class="button' . ($value === 'spam' ? ' uid-spam-button' : '') . '" name="mark" value="' . esc_attr($value) . '">' . esc_html($label) . '</button>';
            }
        }
        if (self::manage()) {
            $actions = $f['view'] === 'trash' ? array('restore'=>'恢复选中','delete'=>'永久删除') : array('trash'=>'移入回收站');
            foreach ($actions as $value=>$label) { echo '<button class="button uid-danger" name="mark" value="' . esc_attr($value) . '">' . esc_html($label) . '</button>'; }
        }
        echo '</div><span class="uid-selected" role="status">已选择 0 条</span></div><div class="uid-table-scroll"><table class="widefat uid-table"><colgroup><col style="width:42px"><col style="width:170px"><col style="width:165px"><col style="width:125px"><col style="width:175px"><col style="width:220px"><col style="width:170px"><col style="width:150px"><col style="width:190px"></colgroup><thead><tr><th><input type="checkbox" class="uid-select-all" aria-label="选择本页全部"></th>';
        foreach (array('询盘编号 / 日期 / 表单','国家 / 产品 / 型号','客户全名','邮箱 / WhatsApp','询盘留言','campaign / 关键词 / adgroup','询盘来源 / 设备','询盘留言网址') as $label) { echo '<th scope="col">' . esc_html($label) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $d = $c::display($row); $r = $c::record($row); $state = ($row['status'] ?? '') === 'spam' ? 'spam' : ($c::unread($row) ? 'unread' : 'read');
            $number = $c::number($row['id']); $link = self::url(array('inquiry'=>$row['id']));
            echo '<tr class="uid-row-' . $state . '"><td><input type="checkbox" name="ids[]" value="' . (int)$row['id'] . '" aria-label="选择 ' . esc_attr($number) . '"></td><td><a class="uid-number" href="' . esc_url($link) . '">' . esc_html($number) . '</a>';
            self::sub($d['date'] ?? ''); self::sub($r['form_name'] ?? ''); self::sub($sync::status($row['id']));
            echo '<span class="uid-status uid-status-' . $state . '">' . esc_html(array('read'=>'已读','unread'=>'未读','spam'=>'垃圾询盘')[$state]) . '</span></td><td>' . esc_html(($d['country'] ?? '') ?: '未提供');
            self::sub($d['F1'] ?? ''); self::sub($d['F2'] ?? '');
            echo '</td><td>' . esc_html(trim(($d['F3'] ?? '') . ' ' . ($d['F4'] ?? '')) ?: '—') . '</td><td>' . esc_html(($d['F5'] ?? '') ?: '—'); self::sub($d['F6'] ?? '');
            echo '</td><td><div class="uid-message-preview">' . esc_html(($d['F9'] ?? '') ?: '—') . '</div>';
            if (mb_strlen($d['F9'] ?? '') > 100) { echo '<a class="uid-message-more" href="' . esc_url($link) . '">查看完整留言</a>'; }
            echo '</td><td>' . esc_html(($d['A1'] ?? '') ?: '—'); self::sub($d['A2'] ?? ''); self::sub($d['A3'] ?? '');
            echo '</td><td>' . esc_html(($d['source'] ?? '') ?: '—'); self::sub($d['H3'] ?? '');
            $url = esc_url($d['H1'] ?? '', array('http','https'));
            echo '</td><td>' . ($url ? '<a class="uid-url" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html($d['H1']) . '</a>' : '—') . '</td></tr>';
        }
        if (!$rows) { echo '<tr><td class="uid-empty" colspan="9">暂无符合条件的询盘，请调整筛选条件。</td></tr>'; }
        echo '</tbody></table></div></form>'; self::pagination($p,$count,$f); echo '</section>';
    }
    static function sub($text) { echo '<div class="uid-sub">' . esc_html($text !== '' ? $text : '—') . '</div>'; }
    static function detail($id) {
        self::guard(); $c = self::$center; $t = self::$trash; $s = self::$sync;
        if (!self::manage() && $t::has($id)) { echo '<p>未找到该询盘。</p>'; return; }
        $row = $c::row($id);
        if (!$row) { echo '<p>未找到该询盘。</p>'; return; }
        $r = $c::record($row); $d = $c::display($row); $state = $row['status'] ?? '';
        $trashed = $t::has($id);
        if ($trashed) { echo '<div class="notice notice-warning"><p>这条询盘已在回收站。<a href="' . esc_url(self::url(array('view'=>'trash'))) . '">前往回收站恢复或永久删除</a></p></div>'; }
        $label = $state === 'spam' ? '垃圾询盘' : ($c::unread($row) ? '未读' : '已读');
        echo '<div class="fwn-detail-actions"><a class="button" href="' . esc_url(self::url()) . '">返回列表</a><strong>' . esc_html($c::number($id)) . '</strong><span>' . esc_html(($r['form_name'] ?? '') . ' · ' . $label) . '</span>';
        if (!$trashed) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr(self::$prefix . 'read') . '"><input type="hidden" name="ids[]" value="' . (int)$id . '"><input type="hidden" name="open_detail" value="1">';
            wp_nonce_field(self::$prefix . 'read');
            if ($state === 'spam') { echo '<button class="button" name="mark" value="unspam">垃圾转已读</button>'; }
            else { echo '<button class="button" name="mark" value="' . ($c::unread($row) ? 'read' : 'unread') . '">' . ($c::unread($row) ? '标为已读' : '标为未读') . '</button><button class="button uid-spam-button" name="mark" value="spam">标为垃圾询盘</button>'; }
            echo '</form>';
        }
        echo '</div><table class="widefat fwn-detail"><tbody>';
        foreach ($c::labels($r['kind']) as $key=>$label) {
            if ($r['kind'] === 'quick' && in_array($key,array('F2','F4'),true)) { continue; }
            echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html(($d[$key] ?? '') ?: '—') . '</td></tr>';
        }
        echo '<tr><th>小满同步</th><td>' . esc_html($s::status($id)) . '</td></tr></tbody></table><p>打开详情不自动改为已读；确认查看后点击“标为已读”。</p>';
    }
    static function next_status($current, $action) {
        if (in_array($action,array('read','unread'),true) && $current === 'spam') { return null; }
        if ($action === 'unspam') { return $current === 'spam' ? 'read' : null; }
        return in_array($action,array('read','unread','spam'),true) ? $action : null;
    }
    static function read_action() {
        self::guard();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('请求方式错误。','',array('response'=>405)); }
        check_admin_referer(self::$prefix.'read');
        $action = self::value($_POST,'mark');
        if (!in_array($action,array('read','unread','spam','unspam','trash','restore','delete'),true)) { wp_die('状态无效。','',array('response'=>400)); }
        $destructive = in_array($action,array('trash','restore','delete'),true);
        if ($destructive) { self::guard(true); }
        $raw = $_POST['ids'] ?? array();
        $valid = is_array($raw) ? array_filter($raw,static function($v){ return is_scalar($v) && preg_match('/^[1-9][0-9]*$/D',(string)$v); }) : array();
        $ids = array_slice(array_unique(array_map('absint',$valid)),0,50);
        global $wpdb; $c = self::$center; $trash = self::$trash; $changed=0; $failed=0; $skipped=0;
        foreach ($ids as $id) {
            if ($destructive) {
                $result = $trash::change($id,$action);
                if (is_wp_error($result)) { $failed++; } elseif ($result) { $changed++; }
                continue;
            }
            $row = $c::row($id);
            if (!$row || $trash::has($id)) { continue; }
            $current = $row['status'] ?? '';
            $next = self::next_status($current,$action);
            if ($next === null) { if ($current === 'spam' && in_array($action,array('read','unread'),true)) { $skipped++; } continue; }
            // Compare the saved status too, so a concurrent staff action cannot overwrite a newer state.
            $result = $wpdb->query($wpdb->prepare('UPDATE ' . $c::table() . ' SET status=%s WHERE id=%d AND COALESCE(status,\'\')=%s AND ' . $c::base_where() . $trash::clause(), $next,$id,$current));
            if ($result === false) { $failed++; } elseif ($result) { $changed++; }
        }
        $args = array_merge(self::filters(wp_unslash($_POST)),array('paged'=>max(1,absint($_POST['paged'] ?? 1))));
        if (!$destructive && count($ids) === 1 && !empty($_POST['open_detail'])) { $args = array('inquiry'=>$ids[0]); }
        wp_safe_redirect(self::url(array_merge($args,array('updated'=>$changed,'operation'=>$action,'failed'=>$failed,'skipped'=>$skipped)))); exit;
    }
    static function export() {
        self::guard(true);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('请求方式错误。','',array('response'=>405)); }
        check_admin_referer(self::$prefix.'export');
        $c = self::$center; if (!$c::exists()) { wp_die('询盘存储尚未开启。'); }
        global $wpdb; $where = self::where(self::filters(wp_unslash($_POST)));
        $max = (int)$wpdb->get_var('SELECT MAX(id) FROM ' . $c::table() . ' WHERE ' . $where);
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_key($c::PAGE) . '-' . gmdate('Ymd-His') . '.csv"');
        $out = fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
        $labels = $c::labels('detail'); fputcsv($out,array_merge(array('表单','阅读状态'),array_values($labels)),',','"',''); $last=0;
        do {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $c::table() . ' WHERE ' . $where . ' AND id>%d AND id<=%d ORDER BY id ASC LIMIT 200',$last,$max),ARRAY_A);
            foreach ($rows as $row) {
                $r=$c::record($row); $d=$c::display($row);
                $line=array($r['form_name'] ?? '',($row['status'] ?? '') === 'spam' ? '垃圾询盘' : ($c::unread($row) ? '未读' : '已读'));
                foreach ($labels as $key=>$label) { $line[]=$d[$key] ?? ''; }
                fputcsv($out,array_map(array($c,'csv_cell'),$line),',','"',''); $last=(int)$row['id'];
            }
        } while (count($rows) === 200);
        fclose($out); exit;
    }
}
add_action('plugins_loaded', array('XI_Dashboard','boot'), 100);

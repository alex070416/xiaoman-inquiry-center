# 2.0.0-beta.15 测试与发布记录

## 目的

用户要求把方威、华旌、通衢、荣丰、安行换插件前的 Bricksforge 表单询盘导入网站询盘中心，不进小满，并且列表按提交时间从新到旧排列。

导入的记录写在现有记录之后，行号比最近一个月的询盘还大。原来的列表按编号（行号）倒序，所以旧询盘会排到最近的询盘前面。安行试导时就出现了这个问题，已撤回。

用户决定不另设历史编号：导入的询盘沿用行号编号，接在现有编号之后。

## 变更

- `native-inquiry-center.php`：询盘列表改为 `ORDER BY created_at DESC, id DESC`（原来是 `id DESC`），页面说明同步更新。`created_at` 是 Bricks 写入的 UTC 提交时间，导入时也写原始提交时间。
- `dashboard/dashboard.php`：询盘面板列表同样按提交时间排序。
- `native-xiaoman-sync.php`：手动“补同步”拒绝带 `import` 标记的记录，导入的历史询盘不会被推送到小满。自动补同步本来就跳过它们（`sync_requested=false`）。
- 版本 `2.0.0-beta.15`。
- 以下部分未改动：
  - 编号规则、转化回传、小满同步的发送内容；
  - 分析接口、MCP；
  - 全部 JS。

## 本地测试

在 Windows 发布机上，用 PHP 8.4.26 和 8.2.34 各跑一遍 CI 的全部 PHP 测试命令，加载了 sodium、zip、pdo_sqlite 扩展：

- 语法检查 0 错。
- 30 条通过。
- `build-release.test.php`：构建断言全部通过，只有最后一项“临时夹具完全删除”失败。原因是 Windows 上删不掉夹具里的目录符号链接，和 beta.13、beta.14 的记录相同，Linux CI 上不出现。残留的临时目录已手动清理。
- Node 测试 130/130 通过，依赖用本地 npm 缓存离线安装；`purpose-helper` 30 项通过。

## 上线核验

升级后补充：每站 readiness、列表排序、历史导入数量、未读数、小满任务。

# 小满询盘中心

一个 WordPress 插件，为 Bricks 原生 Form 提供询盘后台、条件留言校验、来源归因、持久化小满/OKKI 同步队列及 GitHub Releases 升级。

**本轮合入目标为 `2.0.0-beta.9` 预发布验证包，不是稳定版 `2.0.0`。** 纳入 2026-10-06 的未领取收据同标签页刷新恢复，以及小满线索名称日期加一次 12 小时的迁移兼容修复。保留默认关闭的保存成功转化、目的地响应检查、owner 领取、明确未发送时释放和未知结果不重放。签名新包与合入后的完整检查状态以测试报告为准，不能用热修验证代替新包验证。

本轮状态见 [beta.9 测试与发布准备记录](docs/TEST-REPORT-beta.9.md)，旧版范围见 [beta.8 测试记录](docs/TEST-REPORT-beta.8.md)，部署和对账见 [转化试点 SOP](docs/CONVERSION-PILOT-SOP.md)。真实广告对账尚未通过，不推广其余站点；参考站现有追踪保持稳定，迁移前另做兼容核验。

## 环境

- WordPress 6.9+、PHP 8.2+，生产数据库支持 MySQL/MariaDB JSON 函数。
- Bricks 原生 Form，已核对 Bricks 2.4/2.4.1 保存和校验接口。
- PHP cURL、ZIP、mbstring、Sodium、HTTPS；实际定时器应正常运行。
- Action Scheduler 4.2.0 已作为库内置，无需依赖 Rank Math 或其他队列插件。
- Real Cookie Banner 可选。服务缺失或用户拒绝时，不保留跨页面来源历史，当前页面仍可提交并提取 URL 中的广告参数。
- Cloudflare 可提供国家/IP 信息。广告系列名称须由 Google Ads URL 参数传入；单独 gclid 无法推出名称。不需要额外 Worker。

## Bricks 表单

使用原生 Form，保留原有字段、验证码、蜜罐、通知及跳转配置。

1. 设置表单类型“快速询盘”或“产品询盘”。已有 `快速表单` / `详情页表单` 可自动识别。
2. 常用字段名：`Equipment`、`Product`、`FullName`、`LastName`、`Email`、`WhatsApp`、`Message`。兼容 F1/F2/F3/F4/F5/F6/F9；自定义名字可在插件设置中映射。
3. 产品询盘保留 `ProductID`；分类选择使用 `CategoryID` 和 `CategorySignature`。签名及产品标题由服务器核对。
4. 动作顺序：**Save Submission → 小满询盘 → 原有 Email / Redirect 等动作**。转化模块关闭时保留原跳转；启用后在保存成功且编号已核实的响应中接管感谢页参数与语言路径。
5. 在“表单强制必填”设置分类、产品页面或广告系列前缀。`pm`、`fkxi` 是前缀规则，大小写不敏感；必须配置后才生效。

## 已有网站迁移

详见 [迁移与升级 SOP](docs/MIGRATION-SOP.md)。导入时统一处理默认关闭，旧插件继续处理；旧插件停用后才允许启用统一处理。发现旧代码通过 WPCode 等方式仍运行，会暂停统一运行时。

保留原 Bricks 询盘表、metadata 键、编号前缀、同步任务键、索引及已创建 CRM ID；不会自动补发历史询盘。结果不明的创建请求保持 `uncertain`，禁止自动重发。插件停用和删除不会清空客户数据。

小满产品选项与线索名称词分别映射，例如“自提车”名称词可以对应“堆高车”产品选项。导入也检查原生表单的 Equipment 选项，保留不在产品分类中的 Other 等选项。

小满线索名称中的日期按**原始保存时间戳加一次 12 小时，再按本站 WordPress 时区格式化为 YYMMDD**。备注、保存 UTC 和 Google 实际转化时间不平移；重试每次取同一个原始时间，既有成功 CRM 记录不重命名或重发。

## 转化、Cookies 与计数

- 两类表单保存成功才发送 `generate_lead`；Ads 原生使用同一询盘编号作为 `transaction_id`，GA4 普通参数不含客户联系方式。
- 刷新恢复只保存同一标签页 `sessionStorage` 的 `xi_conversion_pending_v1`，沿用原收据最长 30 分钟，仅继续从未领取的通道。已有 claimed/callback、未知结果、过期及历史询盘不重放。
- claim前移除恢复资格；只在严格`success === false`且data仅含`consent/config/protocol`之一的code时恢复该未领取通道，这些服务端检查发生在receipt更新之前。其它拒绝、额外字段、响应丢失或解析失败不恢复，不盲目重发。
- 该存储须在实际统计同意服务中声明并发布；代码中的 `pending_storage_service` 映射到本站有效 `analytics_service`。统计、广告存储、增强型数据分别核验 `analytics_storage`、`ad_storage`、`ad_user_data`，插件不授予同意。用途校验不能代替具体存储声明审核。
- 本项目试点的 Ads 两条表单操作均使用 **Every（每一次）/30 天点击窗口**；原生主要、GA4 导入次要，双路分别对账不相加。这是 Google 账号配置，不由安装插件自动修改，也不补齐历史缺失。
- `claimed`、标签回调、直接访问测试和处理后的广告归因是不同证据层级；完整 Google 验收通过前保持单站试点。

## 发布与升级

升级源固定为 [alex070416/xiaoman-inquiry-center Releases](https://github.com/alex070416/xiaoman-inquiry-center/releases)。只包含代码，不存放客户数据、站点配置或 API 密钥。

```text
php tools/build-release.php /private/path/release.seed
```

将 `dist` 里的 ZIP、`manifest.json`、`SHA256SUMS` 上传到对应 `v版本` Release。私钥保存在仓库外，必须单独备份，禁止上传 GitHub。插件通过内置公钥核对清单签名、包 SHA-256 和固定顶层目录。

beta.9 使用原有签名身份。构建工具要求已有seed和匹配的既有公钥；seed缺失、公钥缺失或不匹配时拒绝构建，不创建新身份或覆盖公钥。冻结待发布完整文件树，核对入口版本、签名清单版本/固定下载地址、ZIP SHA256、全部文件与公开源码一致；旧版包不能覆盖本轮日期及转化热修。当前131文件签名包已通过本地核验；GitHub上传、远程CI及beta.9生产安装仍须单独核验。

稳定版本使用 WordPress 原生更新界面。是否自动安装由各站 WordPress“启用自动更新”选项控制；这不代表所有站点立即同步升级。先验证一个站点，再依次升级其余站点。测试版通过人工上传安装。

## 测试与限制

`npm ci --ignore-scripts --omit=optional && npm test` 运行前端 DOM 检查；PHP 文件应在 8.2/8.4/8.5 下检查。

本轮已验证76项JavaScript，以及PHP8.2.34/8.4.26各121文件语法、metadata/owner/schema/date/updater/build和五个旧站接口配置测试。构建器的29项测试只使用临时合成身份，不读取真实签名seed。完整数量、包哈希和未验收范围见beta.9报告；8.5与远程CI不据此称已通过。

本地另外使用 WordPress 7.1.2 和已安装 Bricks 的 Form、Save_Submission、Submission_Database 源码执行保存及队列测试。仅替换环境帮助方法及远程小满响应；Bricks 商业源码不包含在此仓库。SQLite 仅用于本地测试，不表示已证明生产 MySQL 的所有行为。正式部署还需核对真实页面、Cookies 服务、定时器、广告转化配置和生产数据库查询。

本版本不保证覆盖所有 Cloudflare 524 原因。处理应用负载后，仍需按故障时间对照服务器日志。

## 许可

GPL-3.0-or-later。内置 Action Scheduler 的版权和许可保留在 `vendor/action-scheduler`。

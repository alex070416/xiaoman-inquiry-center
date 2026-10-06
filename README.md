# 小满询盘中心

一个 WordPress 插件，为 Bricks 原生 Form 提供询盘后台、条件留言校验、来源归因、持久化小满/OKKI 同步队列及 GitHub Releases 升级。

**本轮源码目标为 `2.0.0-beta.10` 预发布验证包，不是稳定版 `2.0.0`。** 保留 beta.9 已合入的 2026-10-06 未领取收据同标签页刷新恢复，将小满线索名称日期调整为北京时间每日 18:00 起使用次日日期。保留默认关闭的保存成功转化、目的地响应检查、owner 领取、明确未发送时释放和未知结果不重放。新包须独立完成签名、完整检查和部署核验，不能用旧包或热修验证代替。

名称日期的新规则见 [线索名称日期规则](docs/LEAD-NAME-DATE-RULE.md)。[beta.9 测试与发布准备记录](docs/TEST-REPORT-beta.9.md) 和 [beta.8 测试记录](docs/TEST-REPORT-beta.8.md) 记录各自历史版本的验证范围，不能作为 beta.10 的验收结果。部署和对账见 [转化试点 SOP](docs/CONVERSION-PILOT-SOP.md)。真实广告对账尚未通过，不推广其余站点；参考站现有追踪保持稳定，迁移前另做兼容核验。

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

小满线索名称中的日期按**原始保存时间换算为北京时间 `Asia/Shanghai`，00:00–17:59:59 使用当日，18:00–23:59:59 使用次日，格式为 YYMMDD**。这是一条名称日期分界规则，不给提交时间加 18 小时；不依赖 WordPress 或服务器当前时区。备注、保存 UTC 和 Google 实际转化时间不平移；重试每次从同一个原始保存时间按同一规则计算，既有成功 CRM 记录不重命名或重发。

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

beta.10 沿用原有签名身份。构建工具要求已有 seed 和匹配的既有公钥；seed 缺失、公钥缺失或不匹配时拒绝构建，不创建新身份或覆盖公钥。冻结待发布完整文件树，核对入口版本、签名清单版本/固定下载地址、ZIP SHA256、全部文件与公开源码一致；旧版包不能覆盖本轮名称日期及既有转化修复。每个新版本的包、GitHub 上传、远程 CI 及生产安装均须单独核验。

稳定版本使用 WordPress 原生更新界面。是否自动安装由各站 WordPress“启用自动更新”选项控制；这不代表所有站点立即同步升级。先验证一个站点，再依次升级其余站点。测试版通过人工上传安装。

## 测试与限制

`npm ci --ignore-scripts --omit=optional && npm test` 运行前端 DOM 检查；PHP 文件应在 8.2/8.4/8.5 下检查。

beta.9 本地记录包含 76 项 JavaScript，以及 PHP 8.2.34/8.4.26 各 121 文件语法、metadata/owner/schema/date/updater/build 和五个旧站接口配置测试。构建器的 29 项测试只使用临时合成身份，不读取真实签名 seed。历史数量、包哈希和范围见 beta.9 报告。beta.10 应另外验证北京时间 18:00 边界、月末/年末、不同 WordPress 时区及重复构建同一询盘的名称日期，再执行新包集成检查。

本地另外使用 WordPress 7.1.2 和已安装 Bricks 的 Form、Save_Submission、Submission_Database 源码执行保存及队列测试。仅替换环境帮助方法及远程小满响应；Bricks 商业源码不包含在此仓库。SQLite 仅用于本地测试，不表示已证明生产 MySQL 的所有行为。正式部署还需核对真实页面、Cookies 服务、定时器、广告转化配置和生产数据库查询。

本版本不保证覆盖所有 Cloudflare 524 原因。处理应用负载后，仍需按故障时间对照服务器日志。

## 许可

GPL-3.0-or-later。内置 Action Scheduler 的版权和许可保留在 `vendor/action-scheduler`。

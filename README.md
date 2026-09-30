# 小满询盘中心

一个 WordPress 插件，为 Bricks 原生 Form 提供询盘后台、条件留言校验、来源归因、持久化小满/OKKI 同步队列及 GitHub Releases 升级。

**当前为 `2.0.0-beta.1` 验证版本，尚未替换五个正式网站。** 启用前必须完成各站迁移与前台验收。测试版不会作为自动更新推送到正式站。

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
4. 动作顺序：**Save Submission → 小满询盘 → 原有 Email / Redirect 等动作**。插件自身不改写配置的跳转地址。
5. 在“表单强制必填”设置分类、产品页面或广告系列前缀。`pm`、`fkxi` 是前缀规则，大小写不敏感；必须配置后才生效。

## 已有网站迁移

详见 [迁移与升级 SOP](docs/MIGRATION-SOP.md)。导入时统一处理默认关闭，旧插件继续处理；旧插件停用后才允许启用统一处理。发现旧代码通过 WPCode 等方式仍运行，会暂停统一运行时。

保留原 Bricks 询盘表、metadata 键、编号前缀、同步任务键、索引及已创建 CRM ID；不会自动补发历史询盘。结果不明的创建请求保持 `uncertain`，禁止自动重发。插件停用和删除不会清空客户数据。

## 发布与升级

升级源固定为 [alex070416/xiaoman-inquiry-center Releases](https://github.com/alex070416/xiaoman-inquiry-center/releases)。只包含代码，不存放客户数据、站点配置或 API 密钥。

```text
php tools/build-release.php /private/path/release.seed
```

将 `dist` 里的 ZIP、`manifest.json`、`SHA256SUMS` 上传到对应 `v版本` Release。私钥保存在仓库外，必须单独备份，禁止上传 GitHub。插件通过内置公钥核对清单签名、包 SHA-256 和固定顶层目录。

稳定版本使用 WordPress 原生更新界面。是否自动安装由各站 WordPress“启用自动更新”选项控制；这不代表所有站点立即同步升级。先验证一个站点，再依次升级其余站点。测试版通过人工上传安装。

## 测试与限制

`npm ci --ignore-scripts --omit=optional && npm test` 运行前端 DOM 检查；PHP 文件应在 8.2/8.4/8.5 下检查。

本地另外使用 WordPress 7.1.2 和已安装 Bricks 的 Form、Save_Submission、Submission_Database 源码执行保存及队列测试。仅替换环境帮助方法及远程小满响应；Bricks 商业源码不包含在此仓库。SQLite 仅用于本地测试，不表示已证明生产 MySQL 的所有行为。正式部署还需核对真实页面、Cookies 服务、定时器、广告转化配置和生产数据库查询。

本版本不保证覆盖所有 Cloudflare 524 原因。处理应用负载后，仍需按故障时间对照服务器日志。

## 许可

GPL-3.0-or-later。内置 Action Scheduler 的版权和许可保留在 `vendor/action-scheduler`。

# 转化调度维护说明

本改动是待评审源码，不发布 Release、不调整主插件 `2.0.0-beta.10` 版本、不触发其他站升级。转化 JS 保留 `-dispatch-candidate20261011` 资源 suffix。生产迁移必须单独备份、差异核对、验证。

## 发送边界

- Bricks 原生成功保存返回有效询盘编号、收据、`owned-v1`、服务器原截止时间后，表单与感谢页共用调度器。快速/详情表单保持 `form=quick_quote`，用 `form_type` 区分。
- 只接管已核验响应的 `redirectTo`、`redirectUrl`、`redirectTimeout` 与 `refreshPage`，保留无同意、失败及无效响应的原生流程。无独立三秒强制跳转；claim 和 release 等待超时均按结果未知处理，不当作未提交。
- 服务器原子 claim/owner 检查维持唯一领取；Google 事件已尝试或结果未知不盲目重放。known-unused 仅可重复同一 owner 的幂等释放，收到明确释放成功才恢复 pending。
- `generate_lead` 使用 `inquiry_id`；Ads 原生表单转化使用相同 `transaction_id`。两路对账，不能相加。联系事件 `whatsapp_click`、`email_click` 另行统计。不补发历史浏览器事件。
- Google `event_callback`／`event_timeout` 仅作为本地标签事件处理证据，不能证明网络接收、报表入账或广告归因。没有 callback 时的本地等待结束不会伪造回调状态。

## 收据、同意与缓存

`xi_conversion_pending_v1` 仅在已声明服务及当前同意允许时保存同一标签页待发送收据；保留原 30 分钟期限、站点/编号/感谢页/`form=quick_quote` 匹配。严格确认 claim 前 `consent/config/protocol` 拒绝才恢复资格。owner、客户信息不写入此存储。

现有 Analytics 服务映射继续作为 pending 存储声明。需要独立 Ads 存储恢复时，先在发布的 Ads Cookie 服务登记键名、用途、期限与清理规则，再显式配置可选 `pending_ads_storage_service`；不会自动用 Ads 同意替代缺失的存储声明。增强型数据必须提交时和发送时均获 `ad_user_data` 同意；GA4 普通事件不含姓名、邮箱、电话。

感谢页以插件启动时原请求路径及配置 canonical 页面 ID 证明识别。后者只接受本站显式配置 default 感谢页 URL 解析出的 WordPress **page**，不猜语言 URL、不写死页面 ID、不影响普通页面缓存。写入 WP 禁缓存、LiteSpeed `litespeed_control_set_nocache` 与 HTTP `no-store, private`。需清理旧页面配置/脚本缓存，再核验各语言真实响应头及 CDN 缓存行为。

## 自动验证

维护 **90 个转化 JS 用例、46 个转化 PHP 用例**：

- JS：`conversions` 16、`failure-boundaries` 15、`pending-recovery` 33、`form-dispatch-safe` 26。
- PHP：`assets-metadata` 9、`form-response` 8、`privacy-routing` 13、`owner-protocol` 16。
- JS 新增测试由现有 `tests/*.test.cjs` 自动发现；PHP CI 新增两个用例入口，原有 PHP 8.2/8.4/8.5 matrix 与其他检查保持。
- `tests/fixtures/bricks-form-response-callsite.js` 仅保存公开运行时的最小 success/redirect 决策片段，用于验证同步事件后仍会读取 `refreshPage`；不包含完整第三方资源、站点账号、客户或询盘数据。

运行 `npm test`，以及 `php -n tests/assets-metadata.test.php`、`php -n tests/form-response.test.php`、`php -n tests/privacy-routing.test.php`、启用 PDO SQLite 后的 `php tests/owner-protocol.test.php`。测试不提交实际询盘、不发 Google 或 CRM 请求。离线成功不是线上归因验收，需要独立实际报表、唯一编号和同一时区对账。

## 发布及回滚

PHP/JS 必须成对迁移。部署前核对目标站生产哈希及现有修复；备份当前文件、资源版本、Google/Cookie 配置及缓存规则。GTM 成功事件旧新发送器互斥，保留基础 Google tag 与 CMP，并检查已发布版本。

回滚成对恢复备份文件/资源版本，清缓存并读回；不重置历史收据状态、不重复提交已保存询盘。各站独立配置自己的域名、编号前缀、感谢页路径、Google 目的地、Cookie 服务映射。其他站仅在试点完成并单独核验后迁移。

参考：[Google 事件参数](https://developers.google.com/tag-platform/gtagjs/reference/parameters)、[Bricks 原生表单](https://academy.bricksbuilder.io/builder/elements/general/form/)、[LiteSpeed API](https://docs.litespeedtech.com/lscache/lscwp/api/)。

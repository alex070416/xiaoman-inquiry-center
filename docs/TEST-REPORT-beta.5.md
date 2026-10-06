# beta.5 检查记录

状态：本地转化逻辑已验证；正式站提交、Google 入账及试点验收待完成。

- JavaScript：24 项通过（12 项转化及 12 项现有表单/归因检查）。
- 本地 WordPress 7.1.2：33 项原生保存/队列/兼容检查及 49 项转化/同意/阶段检查通过。使用已核对的 Bricks Form/Save_Submission/Submission_Database 源码；环境帮助方法和小满 HTTP 响应由本地替代，商业源码不进入此仓库。
- 数据库：本地 SQLite SQL 翻译，不代表生产 MySQL/MariaDB 已验证。
- PHP 8.2.34：插件顶层 PHP 语法检查通过。
- 范围：两类表单唯一编号、原生/旧跳转字段、语言路径、广告字段保留、一次领取、历史/无效凭据阻断、拒绝同意、草稿服务不能授权、增强型数据规范化、CRM 明确失败重试且成功不重复创建、ABC 与订单独立阶段。
- Google 标签回调不等于报表入账。手机实机、主要语言、Cloudflare/CMP/缓存、真实 CRM/通知及两路 Google 对账必须另行验收。

详见 [试点 SOP](CONVERSION-PILOT-SOP.md)。

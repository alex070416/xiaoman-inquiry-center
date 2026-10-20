# 同一个插件安装包

所有站点使用同一个 `xiaoman-inquiry-center` 源码、目录、版本和 ZIP。站点配置与数据保存在各自的 WordPress 数据库中，不随发行包分发。

## 环境

- WordPress 6.9 或更新版本，PHP 8.2 或更新版本。
- 使用 Bricks 原生 Form；保留原生 Save Submission 动作。
- 安装及升级校验需要 Sodium、ZIP 扩展。
- 数据库需支持 MySQL/MariaDB 连接级命名锁。

## beta.11

后台新增管理员可用的“统一配置检查”。核心未启用时也可检查本地配置。检查结果不能代替外部服务验收。

旧配置预览不写入数据库；未完成导入保持关闭。已有统一配置的站点升级时保留现有配置，不能重复导入覆盖。配置保存遇到并发变化、连接丢失或数据库写入失败时停止，读回成功才显示保存成功。

各站部署前应备份并验证恢复流程；数据、凭据和生产备份不进入公开仓库或发行包。安装相同版本不代表各站配置和验收状态相同。

## 构建和核验

从受审的干净 Git 提交构建，使用已有签名身份；私钥必须保存在仓库外：

```text
php tools/build-release.php /private/path/release.seed /private/output/new-version --require-clean
php tools/verify-release.php ZIP manifest.json TRUSTED_PUBLIC_KEY SHA256SUMS
```

每个版本生成一个固定目录 ZIP、签名 manifest 和 SHA256SUMS。禁止覆盖已发布产物或更换既有信任公钥。测试命令见 `.github/workflows/ci.yml`。

预发布包用于定向验收；正常在线升级通道跳过 prerelease。只有完成实际站点验收后，才发布稳定版本并按站点安排升级。

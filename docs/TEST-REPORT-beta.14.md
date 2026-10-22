# 2.0.0-beta.14 测试与发布记录

## 目的

通衢迁移时，插件自带的"导入原询盘配置"报 `taxonomy_missing`，导入中止，未写入任何配置。原因是通衢的产品分类在 WordPress 中注册为 `Tractors`（大写开头，网址别名是小写 `tractors`），而插件只接受小写分类键。WordPress 不要求分类键小写，所以这是插件的问题，按"同一份代码、差异只走设置"在插件里修，不做单站补丁。

## 变更

- `readiness.php`：配置检查的分类键规则由 `^[a-z][a-z0-9_-]{0,31}$` 改为 `^[A-Za-z][A-Za-z0-9_-]{0,31}$`。仍要求该分类已注册且挂在产品文章类型上。
- `configuration.php`：设置页保存分类时不再用 `sanitize_key()`。它会把 `Tractors` 改成 `tractors`，随后因"分类不存在"被过滤掉。现在只去掉非法字符，保留大小写。
- 运行时（表单、分类目录、签名）原本就按原样使用分类键，不受影响。
- 版本 `2.0.0-beta.14`。

## 测试

- 新增用例：
  - `readiness.test.php`：已注册的 `Tractors` 通过；`tractors`、`1Tractors`、`Trac tors` 被拒。
  - `configuration-toggle.test.php`：保存 `Tractors, product-category,tractors` 后得到 `Tractors`、`product-category`。
  - `compatibility.test.php`：通衢夹具的分类改为与线上一致的 `Tractors`，五站各 126 项通过。
- 完整结果见下方"本地测试"。

## 本地测试

Windows 发布机，PHP 8.4.26 和 8.2.34 各跑一遍 CI 的全部 30 条 PHP 测试命令，加载 sodium、zip、pdo_sqlite：

- 语法检查 0 错。
- 29 条通过，包括 updater、verify-release、readiness（114 项）、configuration-toggle（226 项，以及 missing-credentials）、五站 compatibility（各 126 项）、stored-credentials、missing-decrypt。
- `build-release.test.php` 的构建断言全部通过，只有最后一项"临时夹具完全删除"失败：Windows 上删不掉临时 Git 仓库里的只读文件。这和 beta.13 的记录相同，Linux CI 上不出现。
- 另在通衢服务器容器（PHP 8.4.27，Linux）的私有目录里跑过 readiness、configuration-toggle、configuration-lock 和五站 compatibility，全部通过。
- JS 没有改动，Node 测试由 CI 在推送后运行。

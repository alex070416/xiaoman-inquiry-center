# 2.0.0-beta.13 测试与发布记录

## 目的

方威、华旌、华腾三站统一到同一份签名包，并恢复 WordPress 在线升级。

## 变更

- 产品询盘表单新增控件“产品询盘设备栏”（`xiEquipmentScope`）。默认“锁定当前分类”，行为与 beta.12 相同。选“全部设备，默认当前分类”（`catalog`）后，分类页和产品页输出完整分类目录，带 `default_category_id`，产品页另带 `selected_product_id`；前端预选当前分类和产品，访客可改选其他设备，型号和分类签名随之切换，重置后回到默认。产品页在该模式下不再隐藏设备栏。每个分类签名仍由服务器生成和核对。
- 升级源依次询问 `cixin123/xiaoman-inquiry-center`、`alex070416/xiaoman-inquiry-center`，不跟随跳转；Release 资源和清单中的下载地址必须属于给出该 Release 的仓库，预发布或草稿不回退到另一个仓库。
- 签名身份更换（见 README“发布与升级”）。`tests/manifest.fixture.json`、`tests/manifest.fixture.previous.json` 用新身份重签，包哈希为全零。
- 版本 `2.0.0-beta.13`；插件头 `Update URI`、设置页升级源链接、`tools/verify-release.php` 固定下载地址改为 cixin123。

## 本地测试（Windows，发布机）

- PHP 8.2.34、8.4.26：`xiaoman-inquiry-center`、`tools` 下全部 PHP 文件语法检查 0 错。
- PHP 测试：除 `build-release.test.php` 外全部通过，与 beta.12 源码同环境结果一致；新增 `equipment-scope.test.php` 9 项、`updater.test.php` 共 19 项（新增双仓库发现、搬迁回退、地址归属、预发布不回退、全部无响应）。
- `build-release.test.php`：全部构建断言通过，仅最后“临时夹具完全删除”在 Windows 上失败（临时 Git 仓库只读文件无法删除），beta.12 源码同样如此；Linux CI 不受影响。
- Node 24（jsdom 30.1.1）：130/130 通过，含新增 `equipment-scope.test.cjs` 6 项。

## 发布与安装

- 由 `tools/build-release.php <新 seed> <仓库外输出目录> --require-clean` 从干净提交构建，`tools/verify-release.php` 用独立公钥副本复核。
- 以非预发布 Release `v2.0.0-beta.13` 发布到 cixin123/xiaoman-inquiry-center。
- 三站内置旧公钥，需人工安装一次：方威（beta.12，服务模式）、华旌（beta.12，purpose）、华腾（beta.10 候选加 3 文件补丁，安装前显式写入 `purpose_consent_enabled=true`）。逐站备份、切换、核对，之后由各站后台在线升级。
- 荣丰、通衢、安行不在本轮；荣丰升级时同样需人工安装一次。

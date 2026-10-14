# CLAUDE.md — LearnFlow

## 项目简介

LearnFlow 是面向讲师与训练营主理人的课程交付产品：写课、开营、测验、作业、证书、营收看板，学员端与讲师后台一体。属于产品矩阵（OpenFlow / PayFlow / UserLoop / MFlow）的一员，互通只走公开 API / MCP / Webhook，不共享数据库。详见 `README.md`、`docs/PLATFORM.md`。

## 技术栈

- PHP 8.3+，零框架、零 composer 运行时依赖
- 存储：JSON 文件（`data/`），生产切到独立 MySQL 实例（127.0.0.1:3308），可回退 SQLite
- Apache + `.htaccess` 伪静态；本地用 `php -S` + `bin/router.php`
- REST `/api/v1` + MCP（`mcp.php`）；AI 走 DeepSeek（OpenAI 兼容）
- 媒体可走 Cloudflare R2 + Worker（`bin/cf-deploy-files.php`）
- 微信小程序工程 `miniprogram/`

## 目录结构

```
*.php          学员端页面（courses/course/learn/quiz/camp/certificate/dashboard…）
admin/         讲师后台
api/           接口（含 payflow-webhook）
lib/           领域层（Course/Enrollment/Quiz/Certificate/Auth/R2/…）
includes/      bootstrap.php + 页头/导航/页脚
assets/        CSS/JS/字体（tokens.css、modules.css 同步自 OpenFlow）
bin/           CLI 运维脚本（seed/router/migrate/drain/remind/backup/selfcheck/autonomy/transcode/publish）
tests/         domain.php 领域层回归
docs/          文档（DEPLOY.md、CLOUDFLARE.md、API-MCP.md…）
data/ uploads/ 运行时数据（gitignored，服务器为源）
```

## 常用命令

```bash
# 安装：无依赖，只需 PHP 8.3+
php bin/seed.php                        # 生成管理员/示例课程/学员/邀请码
php -S 127.0.0.1:8080 bin/router.php    # 本地预览
php tests/domain.php                    # 领域层回归测试
php bin/migrate.php                     # 数据层切换后补导入
```

build / lint：无。

## 部署方式

- 目标：宝塔（Apache）服务器 `root@172.96.253.73`，SSH 端口 `28766`；目录 `/www/wwwroot/learnflow`，经 Apache `Alias /learnflow` 挂到 `https://nownexts.com/learnflow`（前置 Cloudflare）
- **唯一入口（双轨）**：`bash deploy/sync.sh [-m "提交信息"] [--server-only] [--dry-run]`
  - GitHub 轨（公开仓库，只放干净代码）：gitleaks 扫描暂存区 → `git add -A`（`.gitignore` 已排除 `data/`、`uploads/`、`.env*`、`secrets*`）→ commit → `git push origin main`；无变更则跳过 commit
  - 服务器轨：从工作区 rsync 到 `/www/wwwroot/learnflow/`（排除项同 `docs/DEPLOY.md`：`data/`、`uploads/`、`.env*`、`.user.ini`、`secrets*` 等服务器运行时数据永不覆盖/删除；另排除 `deploy/`）→ 清 `data/cache/*.cache` → `chown -R www:www` → 线上 HTTP 验收
  - `--server-only` 跳过 git；`--dry-run` 只做 `rsync -n` 预演，不提交/推送/清缓存/chown
  - 本地 `.git/hooks/pre-commit` 会跑 gitleaks，发现密钥即阻止提交
  - 注意：脚本会 `git add -A` 自动提交所有改动，运行前确认工作区

- 分支：`main`（`git push origin main` 仅推 GitHub `sevenaaaaaaaaa/learnflow`，不触发部署）
- 服务器 crontab：`bin/drain.php`（每 15 分钟）、`bin/remind.php`（09:00）、`bin/backup.php`（03:30）

## 注意事项

- 环境变量（服务器 `.env`）：`LF_BASE`（生产须为 `/learnflow`）、`LF_ENV`、`LF_DATA_DIR`、`LF_UPLOAD_DIR`
- 其余运行时配置（SMTP、DeepSeek Key、embedding、cf_token、r2_token、UserLoop token）在后台「设置」写入 `data/settings.json`，勿入库
- 生产 PHP 禁用 `putenv()`，`.env` 读取写 `$_ENV/$_SERVER`，勿改回裸调 `putenv`
- 站内链接一律经 `lf_url()` 生成，勿硬编码 `/learnflow` 前缀
- `data/`、`uploads/`、`.env*`、`docs/secrets-local.md` 永不入 git、部署永不覆盖

# LearnFlow · 部署与环境配置（同步自 OpenFlow）

> 从 OpenFlow 主仓同步的配置约定。本项目的具体路径已按下方参数适配。

## 一、服务器

| 项 | 值 |
|---|---|
| 主机 | root@172.96.253.73，SSH 端口 28766 |
| 面板 | 宝塔（Apache） |
| 访问地址 | `https://nownexts.com/learnflow`（**子路径部署**；根入口 = 讲师后台登录，学员端在 `/learnflow/*`） |
| 本项目目录 | `/www/wwwroot/learnflow`（**docroot 之外**，通过 Alias 挂到 `/learnflow`） |
| Apache 挂载 | 扩展配置 `/www/server/panel/vhost/apache/extension/nownexts.com/learnflow.conf`（`Alias /learnflow /www/wwwroot/learnflow`），随 `nownexts.com.conf` 的 `IncludeOptional` 生效（:80 + :443） |
| 证书 | nownexts.com 现用证书即可（CF full 模式容忍；边缘由 CF Universal SSL 覆盖） |
| PHP CLI | `/www/server/php/83/bin/php`（生产 CLI 报 zip 重复加载警告是已知问题） |
| 基础路径 | 应用在 docroot 之外，自动探测无法推断，须在 `.env` 设置 `LF_BASE=/learnflow`（见下） |
| 旧子域名 | `learnflow.nownexts.com` 已改为 301 → `https://nownexts.com/learnflow/` |

> 依赖注意：生产 PHP 禁用了 `putenv()`；`.env` 读取改为写 `$_ENV/$_SERVER` 并对 `getenv/putenv` 做存在性保护（勿改回裸调 `putenv`）。

## 二、代码同步（rsync）

```bash
# 本地 → 服务器（排除运行时数据与密钥）
rsync -az --delete -e "ssh -p 28766" \
  --exclude='.git/' --exclude='data/' --exclude='uploads/' \
  --exclude='vendor/' --exclude='node_modules/' \
  --exclude='.env*' --exclude='.user.ini' \
  --exclude='secrets*' --exclude='docs/secrets-local.md' \
  --exclude='.DS_Store' --exclude='*.bak*' \
  ./ "root@172.96.253.73:/www/wwwroot/learnflow/"
```

约定：`data/` 与 `.env` 是运行时数据（服务器为源），`--exclude` 保证部署永不删除；缓存清理
`rm -f /www/wwwroot/learnflow/data/cache/*.cache`。部署后属主回 `www:www`：
`chown -R www:www /www/wwwroot/learnflow`。

### 服务器侧一次性的两件事

1. `Alias` 扩展配置（`extension/nownexts.com/learnflow.conf`）：

```apache
Alias /learnflow /www/wwwroot/learnflow
<Directory /www/wwwroot/learnflow>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex admin/login.php index.php
</Directory>
```

2. 应用 `.env`（`/www/wwwroot/learnflow/.env`，属主 `www:www`，权限 `640`）：

```
LF_BASE=/learnflow
LF_ENV=prod
```

> 入口约定：`/learnflow` 根直接渲染后台登录（应用 `.htaccess` 的 `DirectoryIndex admin/login.php`
> 与 `^$ → admin/login.php`）；学员端位于 `/learnflow/courses`、`/learnflow/learn/*` 等二级路径；
> 产品页/能力页由主站（OpenFlow）承载，不在本应用内。
> OpenFlow 根 `.htaccess` 另保留了 `^learnflow(/.*)?$ - [L]` 放行规则（应用改用 Alias 后为防御性冗余）。


## 三、GitHub

- 仓库：`sevenaaaaaaaaa/learnflow`（private，与矩阵兄弟产品一致）
- 首次推送已完成；日常 `git push origin main`
- 凭证：见本机 gitignore 的 `docs/secrets-local.md`（勿入库）

## 四、目录结构约定

```
LearnFlow Dev/           # 本地 Dev 根（= git 仓库根 = 应用根）
├── README.md
├── .htaccess            # 伪静态路由 + data/lib/includes 拒绝直连
├── index.php            # 根目录请求兜底：跳转后台（正常由 .htaccess 直接渲染后台登录）
├── courses.php course.php learn.php quiz.php camp.php
├── certificate.php login.php dashboard.php join.php logout.php
├── admin/               # 讲师后台（login/index/courses/course-edit/quizzes/quiz-edit/students/invites/certificates/settings）
├── api/                 # courses.php progress.php payflow-webhook.php
├── lib/                 # 领域层：CourseLibrary/Enrollment/Progress/Quiz/Certificate/Student/PayFlow/View/Auth
├── includes/            # bootstrap.php + site-head/nav/footer.php
├── assets/              # tokens.css / modules.css（同步自 OpenFlow）+ app.css / app.js / fonts/
├── bin/                 # seed.php（种子数据）、router.php（本地预览路由）
├── tests/               # domain.php（领域层回归）
└── docs/
    ├── POSITIONING.md ROADMAP.md DESIGN-SYSTEM.md
    ├── CLOUDFLARE.md AI-DEEPSEEK.md
    ├── secrets-local.md   # gitignored
    └── assets-reference/  # tokens.css / modules.css 快照
```

服务器对应 `/www/wwwroot/learnflow/`（docroot 之外，Alias 挂载）；rsync 源 = 本地 Dev 根（应用文件直接位于根，与 OpenFlow 一致）。
`data/`、`uploads/` 为运行时目录（服务器为源），部署永不删除。
所有站内链接经 `lf_url()` 生成，自动带 `/learnflow` 前缀，无硬编码子域名。


# LearnFlow · 部署与环境配置（同步自 OpenFlow）

> 从 OpenFlow 主仓同步的配置约定。本项目的具体路径已按下方参数适配。

## 一、服务器

| 项 | 值 |
|---|---|
| 主机 | root@172.96.253.73，SSH 端口 28766 |
| 面板 | 宝塔（Apache） |
| 访问地址 | `https://nownexts.com/learnflow`（**子路径部署**；根入口 = 讲师后台登录，学员端在 `/learnflow/*`） |
| 本项目目录 | `/www/wwwroot/nownexts.com/learnflow`（即 OpenFlow 站点 docroot 下的 `learnflow/` 子目录） |
| Apache vhost | 沿用 OpenFlow 的 `nownexts.com.conf`（:80 + :443）；本目录自带 `.htaccess`（`RewriteBase /learnflow/`） |
| 证书 | OpenFlow/nownexts.com 现用证书即可（CF full 模式容忍；边缘由 CF Universal SSL 覆盖） |
| PHP CLI | `/www/server/php/83/bin/php`（生产 CLI 报 zip 重复加载警告是已知问题） |
| 基础路径 | 代码自动探测（应用目录相对 docroot 的路径）；如用 Alias 等无法探测的情形，设置环境变量 `LF_BASE=/learnflow` |

## 二、代码同步（rsync）

```bash
# 本地 → 服务器（排除运行时数据与密钥）
rsync -az --delete -e "ssh -p 28766" \
  --exclude='.git/' --exclude='data/' --exclude='uploads/' \
  --exclude='vendor/' --exclude='.env' --exclude='.user.ini' \
  --exclude='.DS_Store' --exclude='*.bak*' \
  ./ "root@172.96.253.73:/www/wwwroot/nownexts.com/learnflow/"
```

约定：`data/` 是运行时数据（服务器为源），部署永不删除；缓存清理
`rm -f /www/wwwroot/nownexts.com/learnflow/data/cache/*.cache`。

> 站点级要求：请求 `/learnflow/*` 必须落到本目录（目录存在即可，Apache 按子目录处理）。
> OpenFlow 根 `.htaccess` 对已存在的真实文件/目录放行，因此本子目录的 `.htaccess` 会正常接管伪静态。
>
> 入口约定：`/learnflow` 根直接渲染后台登录（`.htaccess` 内 `^$ → admin/login.php`），
> 后台独享根；学员端位于 `/learnflow/courses`、`/learnflow/learn/*` 等二级路径；
> 产品页/能力页由主站（OpenFlow）承载，不在本应用内。


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

服务器对应 `/www/wwwroot/nownexts.com/learnflow/`；rsync 源 = 本地 Dev 根（应用文件直接位于根，与 OpenFlow 一致）。
`data/`、`uploads/` 为运行时目录（服务器为源），部署永不删除。
所有站内链接经 `lf_url()` 生成，自动带 `/learnflow` 前缀，无硬编码子域名。


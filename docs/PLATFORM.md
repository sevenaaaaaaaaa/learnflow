# LearnFlow · 平台总览（PLATFORM.md）

> 面向维护者/自己的整体说明书：定位、架构、能力地图、入口、数据层、AI/Agent、运维与边界。

## 一、定位

**知识付费创作者的内容工作台 + Agent**：把「内容生产 → 组织上架 → 交付 → 运营 → 复盘」收进一套工作台，并通过 MCP/API 与矩阵（OpenFlow / UserLoop / MFlow / PayFlow / WebsFlow / inFlow）联动。
部署：`https://nownexts.com/learnflow`（子路径，应用在 docroot 之外经 Alias 挂载）。根入口 = 讲师后台登录。

## 二、技术栈与架构

- PHP 8.3，**零 composer 运行时依赖**；前端原生 JS/CSS（tokens/modules 设计系统同步自 OpenFlow）
- 数据层**双驱动**：MySQL（独立实例 127.0.0.1:3308）为默认，SQLite 可切；JSON 文件作快照备份。`json_read/json_write/json_update` 是唯一收口
- 目录：前台页在仓库根；`lib/` 领域层；`api/` 接口；`admin/` 后台；`data/` 运行时（gitignored）；`miniprogram/` 小程序
- 事件总线 `lf_emit`：写事件日志 + 站内通知/邮件 + 出站互通（UserLoop 实时 ingest；MFlow 队列）+ 积分

## 三、能力地图

| 域 | 能力 | 主要入口 |
|---|---|---|
| 内容生产 | 富文本编辑器、素材库、Markdown 导入、AI 内容工厂（讲义/资料成课/营销文案） | 后台·课程编辑 / 素材 / AI 工作台 / 内容库 |
| 组织上架 | 章节课时、分类标签、多语言、定时发布、发布审批、模板与跨课程复制 | 后台·课程 / 分类 / 内容库 |
| 交付 | 播放器（mp4/HLS、封面、字幕、自动连播、防盗水印）、图文、测验、证书 | 前台 `/learn/*` `/quiz/*` `/certificate` |
| 训练营 | 开营节奏、每日任务、作业与点评、打卡、圈子、直播日程 | 前台 `/camp/{slug}`（Tab） |
| 学员体验 | 学习笔记、划词高亮标注、课时搜索、资料区 | 学习页 |
| 触达 | 站内通知、SMTP 邮件、可编辑文案模板、群发 | 后台·通知 / 模板；cron 提醒 |
| 成交与增长 | 试看、优惠券、推荐码与归因、会员/订阅、分销佣金记录 | 前台课程页/会员页；后台·营销/会员/分销 |
| 营收与内容分析 | 营收/漏斗/来源、课时流失、题目正确率 | 后台·营收 |
| AI / Agent | RAG 答疑（BM25 + 可插拔向量）、测验/点评/周报、工作台助手、MCP/API | 学习页 AI 答疑；后台 AI 工作台；`/mcp` `/api/v1` |
| 协作 | 版本历史/恢复、在线编辑提示、团队批注 | 后台·课程编辑 |
| 账号与权限 | 学员注册/找回；管理员/编辑/只读三角色 | `/login` `/account`；后台·账号 |
| 运维与合规 | 备份轮转、登录限流、审计、协议页、数据导出/删除 | 后台·审计/设置；`bin/backup.php` |

## 四、入口一览（生产）

前台：`/` `→` 后台登录 ｜ `/courses` `/course/{slug}` `/learn/{slug}` `/quiz/{slug}` `/camp[/{slug}]` `/certificate[/{no}]` `/membership` `/dashboard` `/account` `/notifications` `/login` `/join` `/terms` `/privacy` ｜ `/en/…` 英文
接口：`/api/v1`（API Key RPC）｜`/mcp`（MCP JSON-RPC）｜`/api/student.php` `/api/auth.php`（学员令牌）｜`/api/progress.php` `/api/upload.php` `/api/ai.php` `/api/ai-qa.php` `/api/note.php` `/api/highlight.php` `/api/markdown.php` `/api/presence.php` `/api/payflow-webhook.php`
后台：`/admin/` 及 `/admin/{page}.php`

## 五、数据与存储

- 集合（KV）：`courses / categories / students / enrollments / progress / quizzes / quiz-attempts / certificates / assignments / assignment-submissions / schedule / task-completions / checkins / community / notifications / coupons / referrals / referral-attributions / commissions / membership-tiers / points / notes / highlights / media / course-templates / course-revisions / team-comments / presence / templates / api-keys / events / webhook-queue / ai-qa / ai-drafts / embeddings / settings …`
- 集合表 `lf_kv(k,v,updated_at)`；JSON 快照在 `data/*.json`；素材文件在 `uploads/`（禁止直连，图片走 `/media/{id}`，受控文件走 `/file`）
- 备份：`bin/backup.php` → `data/backups/<时间戳>/`（逻辑库+JSON），保留 14 份

## 六、AI 与 Agent

- DeepSeek（OpenAI 兼容）主力；向量检索可插拔 embeddings（未配置回退 BM25）
- 内容：讲义生成、资料→课程大纲、营销文案（7 类）、测验出题、作业点评、周报、工作台助手
- **MCP `/mcp`**：`course.*` `enrollment.*` `student.*` `assignment.*` `quiz.*` `analytics.*` `certificate.*` `community.*` `coupon.*` `referral.*` `commission.*` `membership.*` `points.*` `ai.*` `content.*`（详见 `docs/API-MCP.md`）

## 七、运维

- cron（6 条）：互通投递 `bin/drain.php`（15min）、学习提醒 `bin/remind.php`（9:00）、备份 `bin/backup.php`（3:30）、直播提醒 `bin/live-remind.php`（每小时）、定时发布 `bin/publish.php`（5min）、转码 `bin/transcode.php`（4:00，需 ffmpeg）
- 独立 MySQL 实例 systemd `learnflow-mysql.service`（:3308）；详见 `docs/DEPLOY.md`

## 八、已知边界（诚实清单）

- **实时协同**：仅版本历史 + 在线提示 + 批注，无多人光标/CRDT
- **视频转码**：服务器未装 ffmpeg，脚本就绪即用；无内置 DRM（签名 URL + 水印）
- **划词高亮**：按文本匹配（重复文本命中首个），跨标签选择可能不精确
- **小程序**：HLS/直播经 web-view 打开 H5；未内嵌微信支付
- **拼团**：归 PayFlow（资金域）；LearnFlow 只做归因/展示
- **订单/退款/发票**：等 PayFlow 订单查询 API
- **私域主动触达**：等 UserLoop 触点层发送 API（企微/公众号/短信）
- **性能**：进程内请求缓存 + id/slug/email 索引；大数据量下 analytics 逐步改增量与缓存

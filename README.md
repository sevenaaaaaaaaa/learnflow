# LearnFlow · 知识付费内容工作台 + Agent

> 芭乐派产品矩阵成员。定位 brief `docs/POSITIONING.md` · 路线图 `docs/ROADMAP.md` · 平台总览 `docs/PLATFORM.md` · 互通契约 `docs/MATRIX-API.md` · 自我进化 `docs/EVOLUTION.md`。

## 一句话定位

给讲师、教练、训练营主理人的**一体化内容工作台 + Agent**：把「内容生产 → 组织上架 → 交付 → 运营 → 复盘」收进一套台子，并通过线上 API/MCP 与矩阵互相赋能；不止交付课程，还能**自我体检与迭代**。

## 面向谁 · 差异化

- **人群**：知识付费创作者——讲师、教练、训练营主理人（R.B.E 训练营即自家案例）
- **差异化**：轻、可无头（REST + MCP）、数据可迁移、可自我进化；**不强依赖任何 CMS/CDP**；收款走 PayFlow（互通不绑定）
- **独立化三门槛（已过）**：独立人群 · 零母体依赖 · 数据模型级体量（课程/学员/进度/证书/订单归因是独立数据域）

## 核心能力（概览）

- **内容生产**：富文本编辑器 + 素材库（公共图床）+ Markdown 导入 + AI 内容工厂（讲义生成 / 资料成课 / 营销文案）
- **组织上架**：章节课时（图文/视频/HLS/直播）、分类标签、多语言、模板与跨课程复用、发布审批、定时发布
- **交付**：播放器（续播/字幕/连播/防盗水印）、测验、结业证书、训练营（每日任务/作业点评/打卡/圈子）、学习笔记与划词标注
- **运营**：站内通知 + SMTP 邮件与**可编辑文案模板**、优惠券、推荐与归因、**多级分销佣金记录**、会员订阅
- **增长与复盘**：营收/漏斗/内容分析、试看转化、课程模板与 SEO
- **平台化**：PWA + 微信小程序、多讲师与角色权限、Agent 接口（`/api/v1` + `/mcp`）
- **自进化**：自体检 → 提案 → （护栏内）执行 → 验证 → 策略复利；跨产品动作经矩阵 API
- 完整清单见下方「已实现能力」与 `docs/PLATFORM.md`

## 与矩阵的互通（线上 API/MCP，互相赋能、不共享数据库）

- **PayFlow**：收款（购买即入学 / 开通会员）
- **UserLoop**：学习事件实时进其旅程/Loop 引擎，**私域触达由其编排**
- **MFlow / inFlow / WebsFlow / OpenFlow**：内容分发 / 选题洞察 / 落地页与权益 / 直播与渠道——按产品对逐项经各自 API 打通，见 `docs/MATRIX-API.md`
- 原则：一切互通走公开 API/MCP（Bearer/HMAC），拔掉任一其余照常

## 状态

- [x] 立项 + 定位（本文档）
- [x] 产品页上线（产品/能力页由主站 nownexts.com 承载，各自独立二级目录）
- [x] 独立代码库搭建（H2，PayFlow 先行）——PHP 8.3 + JSON 数据层，零框架零 composer 运行时依赖
- [x] 部署上线（`nownexts.com/learnflow` = 后台入口；学员端在 `/learnflow/*`）
- [x] 训练营运营（H3）：开营节奏/每日任务/作业点评/打卡/圈子/完课率看板
- [x] 触达与 AI：SMTP 邮件+站内通知、DeepSeek（作业点评/测验出题/周报）
- [x] 视频保护与互通：签名 URL+Range 受控播放、UserLoop/MFlow 出站事件队列、多语言
- [x] 矩阵互通契约（`docs/MATRIX-API.md`）+ 跨产品 API 调用层（`lib/Matrix.php`）
- [x] 自我进化 E0–E4：自体检 / 动作闭环 / 分级自治 / 策略复利 / 跨产品动作（`docs/EVOLUTION.md`）
- [ ] 首个训练营闭环验证（R.B.E 第 4 期）

## 已实现能力（H2–H3+）

| 域 | 能力 |
|---|---|
| 课程结构 | 章节/课时（图文/视频/测验/资料/直播位）、分类标签、多语言、开营/结营 |
| 学员与账号 | 注册/登录、找回密码/改密、资料、分组、通知中心 |
| 学习交付 | 播放器（mp4/HLS、断点续播）、进度/完成/学习曲线、测验（章节+结业）、结业证书（可分享+校验） |
| 训练营运营 | 每日任务、作业提交与讲师/AI 点评、打卡与连续激励、圈子（问答/晒进度/点赞评论）、风险学员 |
| 触达 | 站内通知 + SMTP 邮件（报名/点评/证书/提醒）、群发 |
| 成交与增长 | 试看（免费课时匿名可看）、优惠券（展示/核销/透传 PayFlow）、推荐码与归因、推荐奖励券 |
| 营收看板 | 营收/客单价/付费人数、转化漏斗（注册→报名→付费→完课→发证）、来源与推荐、每日营收、CSV 导出 |
| 运维与合规 | 自动备份与轮转、登录限流、后台/API 审计、服务条款/隐私政策、学员数据导出/删除 |
| 会员与成长 | 会员等级/专享课程/会员折扣（PayFlow 下单即开通）、积分与成就、积分榜 |
| 直播 | 直播课时（HLS/嵌入播放）、直播日程、开播前 24h 提醒 |
| 多语言 | 公域 UI 文案中英切换（`?lang=en`）、课程与课时标题/内容 EN |
| 多讲师与权限 | 管理员 / 编辑 / 只读 三角色，敏感页仅管理员 |
| 移动端 | PWA（可安装、离线壳）；学员 API + 微信小程序工程（`miniprogram/`，见 `docs/MINIPROGRAM.md`） |
| 内容台 | 富文本编辑器（图片上传 / 排版 / Markdown 导入）、素材库（公共图床 URL）、通知/邮件文案模板、定时发布 |
| 内容复用与协作 | 课程模板、跨课程复制章节、Markdown 导入拆课时、发布审批（编辑提交→管理员上架） |
| 学员内容体验 | 学习笔记、划词高亮标注、课程内课时搜索、课程资料区、视频字幕、自动连播、防盗水印 |
| 协作 | 课程版本历史与恢复、在线编辑提示、团队批注（课程/课时级、可标记解决） |
| 检索与转码 | 向量检索（可插拔 embeddings，未配置自动回退 BM25）；`bin/transcode.php` 视频封面/转码（ffmpeg 就绪即用）；小程序 H5 播放（web-view + 一次性令牌） |
| 自进化 | 自体检（工程/内容/运营/业务信号→提案）+ **动作闭环**（人工执行→结果记录→信号消失自动验证）+ 分级自治（L0/L1/L2 护栏：每日上限/按类型上限/静默时段）+ 策略库（效果回流/淘汰/A-B）+ 跨产品动作（经矩阵 API）+ AI 改进计划；cron 每日体检 |
| 内容分析 | 课时流失榜、题目正确率（按课程） |
| 分销与拼团 | 多级分销佣金**记录/归因/待结算汇总**（不动资金）；拼团属资金域，交 PayFlow |
| AI（DeepSeek） | 作业点评、测验出题、学习周报、课程资料 RAG 问答、**讲义生成、资料→课程、营销文案（朋友圈/社群/直播脚本/口播/PPT/邮件）、工作台助手**（每日额度保险丝） |
| 互通 | PayFlow 购买即入学；**事件实时投递 UserLoop 旅程/Loop 引擎**（私域触达与自动化由 UserLoop 承载）；MFlow 走通用 webhook（HMAC） |
| Agent 接口 | API Key（read/write/ai 作用域 + 限流 + 审计）、REST `/api/v1`、**MCP server `/mcp`**（26 个工具，见 `docs/API-MCP.md`） |
| 内容安全 | 上传鉴权、签名 URL + Range、`uploads/` 禁止直连、CSRF、越权拦截 |
| 后台 | 看板/课程编辑器/测验编辑器/分类/排期/作业批改/学员/邀请码/证书/圈子/通知/设置/CSV 导出 |

## 入口约定（子路径部署）

| 路径 | 归属 |
|---|---|
| `nownexts.com/learnflow` | 讲师后台入口（直接渲染后台登录，独享根） |
| `nownexts.com/learnflow/admin/*` | 讲师后台 |
| `nownexts.com/learnflow/courses`、`/course/*`、`/learn/*`、`/quiz/*`、`/camp/*`、`/certificate`、`/dashboard` | 学员端 |
| `nownexts.com/learnflow/file` | 受控文件下载（签名 + 报名校验 + Range） |
| `nownexts.com/learnflow/api/*` | 接口（进度、上传、AI、PayFlow webhook、无头只读、API v1） |
| `nownexts.com/learnflow/mcp` | MCP server（Agent 接口，JSON-RPC 2.0） |
| 产品页 / 能力页 | 主站（OpenFlow）负责，不在本应用内 |

## 本地开发

```bash
php bin/seed.php                              # 生成管理员/示例课程/学员/邀请码
php tests/domain.php                          # 领域层回归测试（154 项）
php -S 127.0.0.1:8080 bin/router.php          # 本地预览（模拟 .htaccess 路由，根=后台登录）
php bin/drain.php                             # 手动投递互通事件队列
php bin/remind.php                            # 手动触发连续学习提醒
php bin/selfcheck.php                         # 自进化体检
php bin/autonomy.php                          # 护栏内自动执行（默认 L0 仅提议）
```

- 管理员默认 `admin / learnflow123`；演示学员 `demo@learnflow.local / demo123`；邀请码 `RBECAMP4`
- 生产为子路径部署（`nownexts.com/learnflow`）；本地验证前缀：`LF_BASE=/learnflow php -S 127.0.0.1:8080 bin/router.php`
- 目录：学员端页在仓库根（`courses.php` / `course.php` / `learn.php` / `quiz.php` / `certificate.php`），
  领域层 `lib/`，API `api/`，后台 `admin/`；运行时数据在 `data/`（gitignored，服务器为源）



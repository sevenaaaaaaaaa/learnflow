<div align="center">

# LearnFlow

**知识付费内容工作台 + Agent：内容生产 → 组织上架 → 交付 → 运营 → 复盘，外加自我体检**

![Language](https://img.shields.io/badge/Language-PHP%208.3%2B-blue)
![Version](https://img.shields.io/badge/Version-1.0.0-green)
![Tests](https://img.shields.io/badge/%E9%A2%86%E5%9F%9F%E5%9B%9E%E5%BD%92-155%20%E9%A1%B9-brightgreen)
![License](https://img.shields.io/badge/License-%E7%A7%81%E6%9C%89%E9%A1%B9%E7%9B%AE-lightgrey)

[官网](https://nownexts.com) · [使用指南](docs/USAGE-GUIDE.md) · [相关仓库](https://github.com/sevenaaaaaaaaa)

</div>

## 这是什么

讲师做线上课，通常东拼西凑：课程在 A 平台、作业在微信群、证书用设计工具手搓、学员数据导不出来。LearnFlow 把这条链收进一套台子：写课、排课，卖出去之后的交付（视频 / 测验 / 证书 / 训练营）、运营（通知 / 优惠券 / 分销 / 看板）到复盘，一个后台管完，开营到结业不出这个门。

它不是又一个小鹅通。第一，轻：PHP 8.3 + JSON 数据层，零框架、零 composer 运行时依赖，一台普通主机就能跑。第二，可无头：REST `/api/v1` + MCP `/mcp` 共 42 个工具，你的 Agent 能替你查数据、改课、出题、发通知（按 read/write/ai 作用域授权）。第三，数据可迁移：学员、进度、订单归因都在自己手里，可导出可删除。第四，它会自我体检：每天自检工程 / 内容 / 运营 / 业务信号，生成改进提案，护栏内自动执行并验证效果。

训练营运营能力全部来自真实开营（R.B.E 训练营即自家案例）；领域层 155 项回归测试守住每次改动。

讲师的一天，一图看懂：

```
内容生产（富文本编辑器 / Markdown 导入 / AI 内容工厂）
    ↓
组织上架（章节课时 · 发布审批 · 定时发布 · 多语言）
    ↓                                    收款 → PayFlow（购买即入学）
交付（视频播放 · 测验 · 证书 · 训练营任务/作业/打卡/圈子）
    ↓                                    学习事件 → UserLoop（旅程触达编排）
运营（通知/邮件 · 优惠券 · 推荐归因 · 会员）
    ↓
复盘（营收/漏斗/流失榜 · AI 周报）→ 自我体检（提案 → 护栏内执行 → 验证）
```

## 核心能力

- **内容生产** —— 富文本编辑器 + 素材库 + Markdown 导入拆课时；AI 内容工厂（讲义生成 / 资料成课 / 营销文案：朋友圈 / 社群 / 直播脚本 / 口播 / PPT / 邮件），DeepSeek 驱动，每日额度保险丝。
- **组织上架** —— 章节课时（图文 / 视频 / HLS / 直播位）、分类标签、中英多语言、课程模板与跨课程复用、发布审批（编辑提交→管理员上架）、定时发布。
- **交付与学习体验** —— 播放器（断点续播 / 字幕 / 自动连播 / 防盗水印，签名 URL + Range 分段防下载）、章节 + 结业测验、可分享可校验的结业证书、学习笔记与划词标注。
- **训练营运营** —— 开营节奏与每日任务、作业提交与讲师/AI 点评、打卡与连续激励、圈子（问答 / 晒进度 / 点赞评论）、风险学员识别。
- **触达与转化** —— 站内通知 + SMTP 邮件（文案模板可编辑）、免费课时试看、优惠券、推荐码归因、多级分销佣金记录、会员等级（PayFlow 下单即开通）。
- **经营复盘** —— 营收 / 客单价 / 转化漏斗（注册→报名→付费→完课→发证）、课时流失榜、题目正确率、来源与推荐分析，CSV 导出。
- **Agent 与平台化** —— API Key 三作用域（read / write / ai）+ 限流 + 审计，REST `/api/v1` 与 MCP `/mcp` 42 工具；PWA 可安装 + 微信小程序；多讲师三角色权限（管理员 / 编辑 / 只读，敏感页仅管理员）。
- **自我体检（自进化）** —— 每日自检 → 提案 → 分级自治执行（L0–L2 护栏：每日上限 / 按类型上限 / 静默时段）→ 效果验证 → 策略库复利；跨产品动作经矩阵 API。

能力覆盖面（全部已在代码中落地，按域归组）：

| 域 | 覆盖 |
|---|---|
| 课程与内容 | 章节课时、直播课时（开播前 24h 提醒）、课程模板、版本历史与恢复、在线编辑提示、团队批注 |
| 学员与账号 | 注册/登录/找回密码、分组、通知中心、邀请码、学员数据导出/删除 |
| 学习体验 | 进度/学习曲线、课程内课时搜索、资料区、字幕、自动连播、防盗水印 |
| 训练营 | 每日任务、作业点评（讲师 + AI）、打卡连续激励、圈子、风险学员 |
| 增长 | 试看、优惠券、推荐奖励券、积分与成就、积分榜、SEO |
| 运维合规 | 自动备份与轮转、登录限流、后台/API 审计、服务条款/隐私政策、上传鉴权、CSRF、越权拦截 |
| 检索 | 向量检索（可插拔 embeddings，回退 BM25） |

## 快速上手

```bash
# 依赖：PHP 8.3+（零框架、零 composer 运行时依赖）
git clone https://github.com/sevenaaaaaaaaa/learnflow.git && cd learnflow

php bin/seed.php                        # 生成管理员 / 示例课程 / 学员 / 邀请码
php tests/domain.php                    # 领域层回归：155 项
php -S 127.0.0.1:8080 bin/router.php    # 本地预览（根 = 后台登录）
```

默认账号：管理员 `admin / learnflow123` · 演示学员 `demo@learnflow.local / demo123` · 邀请码 `RBECAMP4`。打开后台建一门课、传一节视频，然后到学员端 `http://127.0.0.1:8080/courses` 用演示学员报名走完「学习 → 测验 → 证书」。

模拟生产子路径部署（线上为 `nownexts.com/learnflow`）：`LF_BASE=/learnflow php -S 127.0.0.1:8080 bin/router.php`。

运维脚本：`php bin/drain.php`（手动投递互通事件队列）· `php bin/selfcheck.php`（自进化体检）· `php bin/autonomy.php`（护栏内自动执行，默认 L0 仅提议）· `php bin/transcode.php`（视频封面/转码，需 ffmpeg）。

目录速览：学员端页面在仓库根（`courses.php` / `course.php` / `learn.php` / `quiz.php` / `camp.php` / `certificate.php` / `dashboard.php`），领域层 `lib/`，接口 `api/`，讲师后台 `admin/`，MCP 入口 `mcp.php`，小程序工程 `miniprogram/`；运行时数据在 `data/`（gitignored，服务器为源）。

线上入口约定（子路径部署）：`nownexts.com/learnflow` = 讲师后台入口；`/learnflow/courses`、`/learn`、`/camp`、`/certificate`、`/dashboard` = 学员端；`/learnflow/api/*` 与 `/learnflow/mcp` = Agent 接口。

## 与 OpenFlow 的关系

LearnFlow 属于进阶层：讲师要的是「交付闭环」，装它一个就够，不强依赖任何 CMS / CDP；全站官网和内容 CMS 归 OpenFlow，LearnFlow 只管课程交付域。

- 收款 → 交给 PayFlow：购买即入学、退款撤销权益（正向 Webhook + 反向入站事件，已联调）。
- 私域触达 → 学习事件实时投递 UserLoop 的旅程 / Loop 引擎，报名提醒、召回、复购编排由 UserLoop 承载。
- 内容分发 → MFlow 经通用 webhook（HMAC）拿到课程上新等信号。

互通原则（矩阵五个产品一致）：一切互通走公开 API / MCP（Bearer / HMAC），不共享数据库，拔掉任何一个其余照常；任何宣传口径不把其他产品说成本产品的模块。

## 使用指南

完整使用指南见 **[docs/USAGE-GUIDE.md](docs/USAGE-GUIDE.md)**。

深入文档：[平台总览](docs/PLATFORM.md) · [API 与 MCP](docs/API-MCP.md) · [矩阵互通契约](docs/MATRIX-API.md) · [自进化](docs/EVOLUTION.md) · [小程序](docs/MINIPROGRAM.md) · [部署](docs/DEPLOY.md) · [路线图](docs/ROADMAP.md) · [定位 brief](docs/POSITIONING.md)。

Agent 接入一览（42 个工具按作用域授权，限流 + 审计）：

| 作用域 | 能干什么（示例） |
|---|---|
| read | `course.list` `enrollment.list` `analytics.overview` `certificate.list` 等只读查询 |
| write | `course.create` `lesson.add` `quiz.create` `assignment.grade` `notification.send` 等内容与运营操作 |
| ai | `ai.generate_quiz` `ai.grade_assignment` `ai.weekly_report` `ai.outline` 等 AI 生成 |

接入方式：MCP 客户端连 `/mcp`（JSON-RPC 2.0：initialize / tools/list / tools/call），或 REST 直接调 `/api/v1`。

## 当前边界

- 分销只做佣金记录 / 归因 / 待结算汇总，不动资金——拼团等资金域交 PayFlow。
- 向量检索需自配 embeddings 服务，未配置自动回退 BM25，功能不中断。
- 视频转码 / 封面需主机装好 ffmpeg（`bin/transcode.php` 就绪即用）；小程序工程已就绪，上架发布需自备微信资质。
- 首个训练营全流程闭环（R.B.E 第 4 期）验证进行中，课程售卖、证书、看板已可用。
- AI 能力（作业点评 / 出题 / 周报 / 内容工厂）需自配 DeepSeek API key（见 `docs/AI-DEEPSEEK.md`），未配置时对应功能不可用，其余功能不受影响。
- 生产建议经 Cloudflare 接入（缓存与回源要点见 `docs/CLOUDFLARE.md`）；`data/` 与 `uploads/` 永不入 git，服务器为准。
- 产品页 / 能力页由主站统一承载（`nownexts.com/product/learnflow`），不在本应用内；`nownexts.com/learnflow` 是后台与学员端入口。

## License

未附带开源许可证，当前为私有项目。如需授权使用，请联系 [nownexts.com](https://nownexts.com)。

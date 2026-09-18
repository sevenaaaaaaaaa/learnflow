# LearnFlow · 课程与训练营交付引擎

> 芭乐派产品矩阵成员（P2 候补 → 立项）。定位 brief 见 `docs/POSITIONING.md`，路线图见 `docs/ROADMAP.md`。

## 一句话定位

课程交付 + 学员进度 + 测验证书 + 训练营运营——讲师、教练、训练营主理人的交付工具，不强依赖任何 CMS/CDP。

## 独立化三门槛（已过）

1. **独立人群**：讲师、教练、训练营主理人（R.B.E 训练营即自家案例）——要交付不不要 OS
2. **零母体依赖**：内容自带，收款接 PayFlow（API 互通不绑定）
3. **数据模型级体量**：课程/章节/学员/进度/证书是独立数据域

## 能力域（OpenFlow 现成底子 → LearnFlow 独立形态）

| 能力 | OpenFlow 来源 | LearnFlow 独立形态 |
|---|---|---|
| 课程结构 | CourseSystem | 章节/课时/图文/视频/测验 |
| 学员管理 | course-students | 报名/名单/分组 |
| 学习进度 | ProgressSystem | 续播/完成率/学习曲线 |
| 测验 | quiz 课时 | 章节测验 + 结业测验 |
| 证书 | CertificateSystem | 结业证书（可分享/防伪） |
| 视频播放 | course-player + R2 media | HLS/mp4 托管播放（Range 分段） |
| 训练营 | R.B.E 模式 | 开营节奏/作业提交/打卡 |
| 社区 | OpenFlow community | 训练营圈子（轻量） |

## 与矩阵的互通

- **PayFlow**：课程售卖与订阅（LearnFlow 调 PayFlow 收款，互通不绑定）
- **UserLoop**：学习行为（完课/进度）进全域档案
- **MFlow**：课程更新内容经分发触达学员
- **inFlow**：课程主题舆情/趋势反哺选题

## 状态

- [x] 立项 + 定位（本文档）
- [x] 产品页上线（产品/能力页由主站 nownexts.com 承载，各自独立二级目录）
- [x] 独立代码库搭建（H2，PayFlow 先行）——PHP 8.3 + JSON 数据层，零框架零 composer 运行时依赖
- [x] 部署上线（`nownexts.com/learnflow` = 后台入口；学员端在 `/learnflow/*`）
- [x] 训练营运营（H3）：开营节奏/每日任务/作业点评/打卡/圈子/完课率看板
- [x] 触达与 AI：SMTP 邮件+站内通知、DeepSeek（作业点评/测验出题/周报）
- [x] 视频保护与互通：签名 URL+Range 受控播放、UserLoop/MFlow 出站事件队列、多语言
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
| AI（DeepSeek） | 作业点评、测验出题、学习周报、**课程资料 RAG 问答**、课程大纲生成（每日额度保险丝） |
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
php tests/domain.php                          # 领域层回归测试（45 项）
php -S 127.0.0.1:8080 bin/router.php          # 本地预览（模拟 .htaccess 路由，根=后台登录）
php bin/drain.php                             # 手动投递互通事件队列
php bin/remind.php                            # 手动触发连续学习提醒
```

- 管理员默认 `admin / learnflow123`；演示学员 `demo@learnflow.local / demo123`；邀请码 `RBECAMP4`
- 生产为子路径部署（`nownexts.com/learnflow`）；本地验证前缀：`LF_BASE=/learnflow php -S 127.0.0.1:8080 bin/router.php`
- 目录：学员端页在仓库根（`courses.php` / `course.php` / `learn.php` / `quiz.php` / `certificate.php`），
  领域层 `lib/`，API `api/`，后台 `admin/`；运行时数据在 `data/`（gitignored，服务器为源）



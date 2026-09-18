# 芭乐派矩阵 · 互通契约（API / MCP）

> 红线（沿用 POSITIONING）：**一切互通走线上 API/MCP，不共享数据库；每个产品独立可用；拔掉任一其余照常。**
> 本文定义矩阵成员之间的能力边界、数据流向与契约，供各产品按同一口径对接。

## 一、成员与角色

| 产品 | 角色（拥有什么） | 形态 |
|---|---|---|
| **OpenFlow** | 站点/内容操作系统：CMS、CDP、渠道（邮件/公众号/企微）、直播底座、页面工场、全站 SEO | PHP，`/xmp` 后台，`api/*` + `mcp-server.php` |
| **WebsFlow** | 落地页/表单/结算页工场：建站、订阅计费、推荐/结算、权益核销 | Node，`/api/v1/*` + `mcp/websflow-mcp.js` |
| **PayFlow** | 收款与订阅：结账、订单状态机、发票/收据、退款、通道适配 | PHP，`/checkout` `/pay` `/return` `/invoice` |
| **UserLoop** | 用户旅程与 Loop 引擎：多源事件归一化、CDP 建档、Loop 编排、触达驱动、效果验证 | Python/FastAPI，`/userloop/api/v1/*` + `mcp_server.py` |
| **MFlow** | 内容生产与分发：长文/多平台、素材、批处理、agent | Python，`/api/agent/*` `/api/assets/*` `/api/batch/*` |
| **inFlow** | 选题与舆情智囊：抓取、洞察、成熟度、监测、循环 | Python/FastAPI，`/api/v1/insights` `/api/v1/ingest` `/api/loop/create` |
| **LearnFlow** | 课程交付与训练营运营（本项目）：课程/进度/测验/证书/训练营/会员/分销 + 内容工作台 + Agent | PHP，`/learnflow/api/*` + `/learnflow/mcp` |

## 二、统一互通底座

- **鉴权**：出站用各产品 API Key / HMAC 签名（如 LearnFlow→UserLoop 用 `X-UserLoop-Token`；通用 webhook 用 `X-LF-Signature`/`X-*-Signature`）。入站同理验签。
- **身份键**：以**邮箱**为主键（`distinct_id`），由 UserLoop 的 identity 图谱打通 `email ↔ 手机 ↔ openid/unionid ↔ wecom_userid`。
- **MCP 联邦**：每个产品暴露 MCP server；Agent 可跨产品编排（如「inFlow 选题 → MFlow 生产 → LearnFlow 成课 → UserLoop 触达 → PayFlow 收款」）。
- **事件总线**：统一事件命名（见下表），各产品既可是**事件源**也可是**消费者**。
- **版本与幂等**：接口带 `event_id`/`order_id` 幂等；破坏性变更走新版本路径。

## 三、事件与对象契约（矩阵总线）

| 事件 | 源 | 载荷要点 | 消费者 |
|---|---|---|---|
| `signup` | LearnFlow / WebsFlow / OpenFlow | email, name, source | UserLoop（建档/旅程） |
| `purchase` | PayFlow（经 LearnFlow 或直接） | order_id, product, amount, email, ref_code, coupon | LearnFlow（入学/会员）、UserLoop（旅程/归因）、WebsFlow（权益） |
| `entitlement.granted` | LearnFlow / WebsFlow / PayFlow | email, entitlement, expires_at | UserLoop、OpenFlow（内容解锁） |
| `course.completed` / `certificate.issued` | LearnFlow | course_id, cert_no, email | UserLoop（复购/转介绍）、MFlow（案例内容） |
| `lesson.completed` / `assignment.submitted` / `checkin.done` / `quiz.result` | LearnFlow | course_id, lesson_id, score, streak | UserLoop（断点/召回） |
| `course.updated` | LearnFlow | course_id, title, status | MFlow（内容触达）、OpenFlow（站点同步） |
| `content.published` | MFlow | channels, urls, topic | OpenFlow（站点）、UserLoop（触达素材） |
| `insight.topic_rising` | inFlow | topic, score, links | MFlow（选题）、LearnFlow（选题/大纲） |

## 四、LearnFlow 的对外契约

**出站（LearnFlow →）**
- → UserLoop：`POST /userloop/api/v1/ingest`（实时，`X-UserLoop-Token`）——事件字典见 `docs/EVENTS-USERLOOP.md`
- → MFlow：通用 webhook（HMAC）——课程更新/结业案例，供内容分发
- → 任意 MA/CDP：通用 webhook（`X-LF-Signature`）

**入站（→ LearnFlow）**
- PayFlow：`POST /learnflow/api/payflow-webhook.php`（验签）——`product_id → 课程/会员`，购买即入学/开通
- 学员端：`/learnflow/api/auth.php`（发学员令牌）、`/learnflow/api/student.php`（移动/小程序/第三方 App 复用）
- Agent：`/learnflow/api/v1`（API Key RPC）、`/learnflow/mcp`（MCP，30+ 工具）

## 五、矩阵协同闭环（示例）

1. **选题**：inFlow `insights` → LearnFlow/MFlow 选题
2. **生产**：MFlow 或 LearnFlow「AI 内容工厂」产出课程/图文/口播
3. **成交**：WebsFlow 落地页挂 PayFlow 结账 → `purchase` 回 LearnFlow
4. **交付**：LearnFlow 入学、学习、测验、证书
5. **触达**：LearnFlow 事件 → UserLoop Loop → 邮件/企微/短信
6. **复盘**：LearnFlow 营收/内容分析 + inFlow 舆情 → 反哺选题（回到 1）

## 六、落地清单（按产品对）

| 方向 | 现状 | 待办 |
|---|---|---|
| LearnFlow → UserLoop | ✅ 已接（实时 ingest + 队列重试） | 补 `content.published` 等源事件；等其发送 API 接主动触达 |
| PayFlow → LearnFlow | ✅ webhook 已接 | 等 PayFlow 订单查询 API 做订单/退款/发票视图 |
| LearnFlow → MFlow | ⏳ 队列就绪 | 确认真实 webhook 契约并开启 |
| inFlow → LearnFlow | ❌ | 接 `insights`：选题/大纲参考 |
| LearnFlow ↔ OpenFlow | ⏳ 直播状态已接 | 直播房间/渠道桥接（OpenFlow 出 API） |
| MCP 联邦 | ✅ 各产品均有 MCP | 统一 MCP 描述与跨产品编排样例 |

## 七、LearnFlow 侧已实现的调用入口

- **配置**：后台「设置 → 矩阵互通（跨产品 API）」—— 每个产品可配 `启用 / Base URL / Token`，含连通状态自检（`matrix_status()`）
- **调用层**：`lib/Matrix.php` 的 `matrix_call($product,$path,$payload,$method)`（UserLoop 用 `X-UserLoop-Token`，其余 `Authorization: Bearer`）；未配置即安全跳过
- **进化的跨产品动作**（自进化 E1/E2 可执行）：
  - `userloop_signal` → 对风险学员发 `reengage.requested`（→ UserLoop `reengage_requested`），由其编排触达
  - `mflow_distribute` → 调 MFlow API 提交内容分发（未配置则跳过）
  - `inflo_topics` → 拉 inFlow 洞察（`/api/v1/insights`）→ 生成选题草稿；后台 AI 工作台亦有「从 inFlow 取选题」
- **Agent 接口**：`matrix.status`（读，各产品连通状态）

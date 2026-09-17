# LearnFlow → UserLoop · 事件字典与自动化触发建议

> 用途：LearnFlow 作为**事件源**，UserLoop 作为**旅程/Loop 编排 + 私域触达**。本页给 UserLoop 侧配规则用。
> 传输：`POST http://127.0.0.1:8600/userloop/api/v1/ingest`，头 `X-UserLoop-Token`（实时；失败入队 `bin/drain.php` 重试）。

## 一、事件字典（LearnFlow 已发）

| LearnFlow 事件 | UserLoop event | distinct_id | 关键 props | 触发时机 |
|---|---|---|---|---|
| `student.registered` | `signup` | 邮箱 | `learner_id, name` | 注册/首次由购买或邀请创建账号 |
| `enrollment.created` | `purchase` | 邮箱 | `course_id, course_title, course_slug, order_id, amount, source(invite/payflow/free/api/coupon)` | 报名成功（含购买、邀请码、API） |
| `course.completed` | `course_completed` | 邮箱 | `course_id, course_title` | 完成全部课时 |
| `certificate.issued` | `certificate_issued` | 邮箱 | `cert_no, course_id, course_title` | 结业证书颁发 |
| `assignment.graded` | `assignment_graded` | 邮箱 | `title, feedback, course_id` | 讲师/AI 点评作业 |
| `study.reminder` | `study_reminder_sent` | 邮箱 | `course_title, course_id` | 连续未学习提醒（cron） |
| `course.updated` | `course_updated` | —（系统事件） | `course_id, title, status` | 课程更新（可驱动 MFlow/学员通知） |

`props` 统一附加 `learnflow_event`（原始事件名）与 `source: learnflow`；`distinct_id` 用邮箱，同时带 `user_id`（学员 id）。

## 二、UserLoop 侧建议触发器（条件 → 建议动作）

> 动作与渠道由 UserLoop 决定（邮件 / 企微 / 公众号 / 短信 / 飞书 / webhook）。频控/静默期/退订由 UserLoop 服务端强制。

| # | 场景 | 触发条件 | 建议动作 |
|---|---|---|---|
| 1 | 注册未报名 | `signup` 后 3 天无 `purchase` | 欢迎 + 课程推荐（邮件/企微） |
| 2 | 报名未开始 | `purchase` 后 2 天无学习事件 | 开课引导 + 第一节试看链接 |
| 3 | 学习中断 | 最近任意学习事件距今 > 7 天 | 召回（邮件 + 企微） |
| 4 | 作业未交 | 布置作业后 3 天无提交 | 交作业提醒（需补发事件，见第三节） |
| 5 | 未打卡 | 连续 2 天无打卡 | 打卡召回 + 连续激励 |
| 6 | 完课未复购 | `course_completed` 后 14 天无新 `purchase` | 进阶课推荐 + 发放优惠券 |
| 7 | 结业转介绍 | `certificate_issued` | 晒证 + 专属推荐码（`/courses?ref=CODE`） |
| 8 | 高意向未购 | 试看/访问课程 ≥ 3 次无 `purchase` | 限时优惠券促单 |
| 9 | 证书撤销/异常 | （如需）`certificate.revoked` | 人工介入（待补事件） |

推荐码分享链接由 LearnFlow 提供：学员在「我的学习」获取 `https://nownexts.com/learnflow/courses?ref=<CODE>`，报名时自动归因（`referral-attributions.json`），可配推荐奖励券。

## 三、建议 LearnFlow 补发的事件（按需，我可随时加）

现有事件偏“关键节点”，要做「作业未交 / 未打卡 / 试看次数」这类触发，需要更细粒度事件：

| 建议事件 | UserLoop event | props | 用途 |
|---|---|---|---|
| 课时完成 | `lesson_completed` | `course_id, lesson_id, title` | 学习曲线触发、中断检测更准 |
| 作业提交 | `assignment_submitted` | `assignment_id, title, course_id` | 未批改提醒、提交即触发 |
| 打卡完成 | `checkin_done` | `course_id, streak` | 连续打卡激励 |
| 测验通过/未过 | `quiz_result` | `quiz_id, passed, score` | 挂科召回 |
| 试看发生 | `preview_viewed` | `course_id, lesson_id` | 高意向未购识别 |
| 证书撤销 | `certificate_revoked` | `cert_no` | 合规/风控 |

> 目前仅 `assignment.graded`、`course.completed` 等已发；“未交/未打卡/试看”类触发在补齐上述事件前，可在 UserLoop 用「时间窗 + 已有事件」近似实现。

## 四、身份映射（跨渠道触达前置）

- LearnFlow 以**邮箱**为 `distinct_id`；要触达企微/公众号，需要 UserLoop 的 identity 图谱把 `email ↔ 手机 ↔ openid/unionid ↔ wecom_userid` 打通。
- OpenFlow 已有公众号/企微回调可写入身份（`lib/WechatMp.php` / `lib/Wecom.php`）；建议在 UserLoop 侧统一 resolve。

## 五、分工边界（矩阵一致）

- **LearnFlow**：发事件、开课前后的站内通知与邮件兜底、发券与核销、推荐归因。
- **UserLoop**：旅程/阶段/断点、Loop 编排、渠道投递、频控与效果验证。
- **OpenFlow**：公众号/企微/邮件渠道实现（UserLoop 通过桥接调用）。
- **PayFlow**：收款；LearnFlow 透传 `coupon` / `ref_code`，PayFlow 回传订单后 LearnFlow 核销与归因。

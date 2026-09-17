# LearnFlow · API 与 MCP（Agent 接口）

> 让 AI Agent / 自动化系统直接操作 LearnFlow：管理课程与课时、测验、作业与批改、报名与学员、通知、经营数据，以及 AI 出题/批改/周报。

## 一、鉴权

后台「API/MCP」页创建密钥（`lf_` 开头，只显示一次）。请求携带：

```
Authorization: Bearer lf_xxxxxxxxxxxxxxxx
```

也可用 `X-Api-Key: lf_xxx` 头或 `?key=lf_xxx` 查询参数（部分客户端/网关会剥离 Authorization 时用）。
应用 `.htaccess` 已把 FastCGI 下的 Authorization 透传给 PHP，无需额外配置。

- 作用域：`read` / `write` / `ai`（建密钥时勾选；工具声明所需作用域，越权返回 403）
- 速率限制：每密钥每分钟（默认 120，可配）
- 审计：每次调用写入 `data/api-log.json`（后台可见最近调用）

## 二、REST（RPC 风格）

```
POST https://nownexts.com/learnflow/api/v1
Authorization: Bearer lf_xxx
Content-Type: application/json

{"tool":"course.list","params":{"published_only":true}}
```

响应：

```json
{"ok":true,"data":{"courses":[...],"count":1},"ms":2}
```

错误：`{"ok":false,"error":"缺少作用域: write","ms":0}`（HTTP 状态码对应 400/401/403/404/422/429）。

## 三、MCP（Model Context Protocol，HTTP / JSON-RPC 2.0）

端点：`https://nownexts.com/learnflow/mcp`，同一密钥鉴权（Bearer）。

支持方法：`initialize`、`tools/list`、`tools/call`、`ping`、`notifications/*`。

> 传输为 MCP「Streamable HTTP」（POST 单次 JSON-RPC 请求/响应）。仅支持 stdio 的客户端（如旧版 Claude Desktop）可用桥接：`npx -y mcp-remote https://nownexts.com/learnflow/mcp --header "Authorization: Bearer lf_xxx"`。

客户端配置示例（Claude Desktop / 任意支持 Streamable HTTP 的 MCP 客户端）：

```json
{
  "mcpServers": {
    "learnflow": {
      "url": "https://nownexts.com/learnflow/mcp",
      "headers": { "Authorization": "Bearer lf_xxxxxxxxxxxxxxxx" }
    }
  }
}
```

调用示例：

```bash
curl -s https://nownexts.com/learnflow/mcp \
  -H "Authorization: Bearer lf_xxx" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call",
       "params":{"name":"course.list","arguments":{}}}'
```

## 四、工具清单

| 作用域 | 工具 |
|---|---|
| read | `course.list` `course.get` `quiz.list` `assignment.list` `enrollment.list` `student.list` `student.get` `analytics.course` `certificate.list` `community.list` `coupon.list` `referral.list` |
| write | `course.create` `course.update` `course.publish` `chapter.add` `lesson.add` `task.add` `quiz.create` `assignment.create` `assignment.grade` `enrollment.add` `enrollment.set_group` `student.upsert` `notification.send` `coupon.create` `coupon.delete` |
| ai | `ai.generate_quiz` `ai.grade_assignment` `ai.weekly_report` |

> `tools/list` 返回每个工具的 `inputSchema`（JSON Schema），Agent 可据此自动构造参数。

## 五、Agent 典型编排

1. `course.create` → `chapter.add` × n → `lesson.add` × n → `course.publish`
2. `ai.generate_quiz` → `quiz.create` → 挂到课时
3. 学员交作业后：`assignment.list` → `ai.grade_assignment` → `assignment.grade`（自动通知学员）
4. 运营巡检：`analytics.course`（完课率/风险学员/打卡）→ `notification.send` 定向触达
5. 周报：`ai.weekly_report`

## 六、约定与边界

- 与矩阵互通一致：一切走公开 API（Bearer/HMAC），不共享数据库；拔掉任一产品其余照常
- 写操作会触发既有事件总线（`enrollment.created` / `certificate.issued` 等）→ 出站队列投递 UserLoop/MFlow
- 视频/附件走签名 URL + 报名校验，不因 API 放宽

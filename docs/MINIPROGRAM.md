# LearnFlow · 微信小程序（学员端）

> 小程序工程在仓库 `miniprogram/`，复用网页端同一套**学员 API**（`/api/auth.php`、`/api/student.php`），不重复实现业务。

## 一、后端学员 API（已上线）

| 端点 | 方法 | 说明 |
|---|---|---|
| `/api/auth.php` | POST | `action=login/register`，体 `{email,password,name?}` → `{token}`（30 天，HMAC 签名）；带登录限流 |
| `/api/student.php?action=profile` | GET | 我的资料 + 会员 + 积分/成就 |
| `/api/student.php?action=courses` | GET | 在架课程（含是否已报名、进度） |
| `/api/student.php?action=my` | GET | 我的课程 + 续播课时 |
| `/api/student.php?action=course&slug=` | GET | 课程详情（章节/课时、锁定/完成、是否有权限） |
| `/api/student.php?action=lesson&slug=&lesson=` | GET | 课时内容/视频（签名 URL），越权拦截 |
| `/api/student.php?action=progress` | POST | 上报进度/完成 `{course_id,lesson_id,position,duration,delta,done}` |
| `/api/student.php?action=notifications` | GET | 通知列表 |
| `/api/student.php?action=membership` | GET | 会员等级 |

鉴权：请求头 `Authorization: Bearer <token>`（token 由 `/api/auth.php` 签发；与后台 API Key 不同，是**学员令牌**）。

## 二、小程序使用

1. 用**微信开发者工具**导入 `miniprogram/` 目录
2. `project.config.json` 填入你的 **AppID**
3. 配置 `app.js` 的 `globalData.baseUrl`（生产为 `https://nownexts.com/learnflow`）
4. 微信公众平台 → 开发管理 → 服务器域名：
   - **request 合法域名**：`https://nownexts.com`
   - （如需 web-view）**业务域名**：`https://nownexts.com`
5. 预览/上传 → 提交审核

## 三、能力与边界

- 已实现：登录/注册、课程列表与详情、图文课时阅读、mp4 视频播放、进度上报/完成、我的课程、积分/会员/通知
- **HLS 视频/直播**：小程序 `<video>` 对 HLS 支持有限，页面提示改在**网页端**观看（H5 已完整支持 HLS/断点续播）
- 支付：小程序不内嵌支付，购买走网页端 PayFlow 链接（可用 `wx.setClipboardData` 复制链接或 web-view 打开）
- 域名要求：小程序必须使用 **HTTPS + ICP 备案**域名，故用 `nownexts.com/learnflow` 而非 IP

## 四、移动端（PWA）

网页端已支持 PWA：移动浏览器打开 `https://nownexts.com/learnflow/courses` → 添加到主屏即可安装，离线可看壳层。小程序面向微信内传播，PWA 面向浏览器，二者共用同一套 API。

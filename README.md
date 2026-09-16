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
- [ ] 首个训练营闭环验证（R.B.E 第 4 期）

## 入口约定（子路径部署）

| 路径 | 归属 |
|---|---|
| `nownexts.com/learnflow` | 讲师后台入口（直接渲染后台登录，独享根） |
| `nownexts.com/learnflow/admin/*` | 讲师后台 |
| `nownexts.com/learnflow/courses`、`/course/*`、`/learn/*`、`/quiz/*`、`/certificate`、`/dashboard` | 学员端 |
| `nownexts.com/learnflow/api/*` | 接口（进度、PayFlow webhook、无头只读） |
| 产品页 / 能力页 | 主站（OpenFlow）负责，不在本应用内 |

## 本地开发

```bash
php bin/seed.php                              # 生成管理员/示例课程/学员/邀请码
php tests/domain.php                          # 领域层回归测试（23 项）
php -S 127.0.0.1:8080 bin/router.php          # 本地预览（模拟 .htaccess 路由，根=后台登录）
```

- 管理员默认 `admin / learnflow123`；演示学员 `demo@learnflow.local / demo123`；邀请码 `RBECAMP4`
- 生产为子路径部署（`nownexts.com/learnflow`）；本地验证前缀：`LF_BASE=/learnflow php -S 127.0.0.1:8080 bin/router.php`
- 目录：学员端页在仓库根（`courses.php` / `course.php` / `learn.php` / `quiz.php` / `certificate.php`），
  领域层 `lib/`，API `api/`，后台 `admin/`；运行时数据在 `data/`（gitignored，服务器为源）



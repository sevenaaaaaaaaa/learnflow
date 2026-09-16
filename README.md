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
- [x] 产品页上线（nownexts.com/learnflow）
- [ ] 独立代码库搭建（H2，PayFlow 先行）
- [ ] 首个训练营闭环验证（R.B.E 第 4 期）

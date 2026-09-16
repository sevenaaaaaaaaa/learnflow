<?php
require_once __DIR__ . '/includes/bootstrap.php';

$courses = course_all(true);
$featured = array_slice($courses, 0, 6);
$student = lf_student_current();
$siteName = (string)(lf_setting_get('site_name') ?: 'LearnFlow');

lf_page_start([
    'title' => $siteName . ' · 课程交付与训练营引擎',
    'description' => '课程交付、学员进度、测验证书、训练营运营。讲师与训练营主理人的交付工具，不强依赖任何 CMS/CDP。',
    'active' => 'home',
    'container' => false,
]);
?>
<section class="lf-hero lf-container">
  <span class="lf-kicker">课程交付 + 训练营运营</span>
  <h1>上课 → 进度 → 测验 → 证书<br>一条交付闭环</h1>
  <p>给讲师、教练、训练营主理人的交付工具：章节课时的图文与视频、报名与名单、断点续播、章节与结业测验、可验证的结业证书。不给增长 OS，只给交付。</p>
  <div class="lf-hero-cta">
    <a class="btn primary" href="/courses">浏览课程</a>
    <?php if ($student): ?>
      <a class="btn ghost" href="/dashboard">继续学习</a>
    <?php else: ?>
      <a class="btn ghost" href="/login">学员登录</a>
    <?php endif; ?>
  </div>
  <div class="lf-stats">
    <div class="lf-stat"><b><?= count($courses) ?></b><span>在架课程</span></div>
    <div class="lf-stat"><b><?= array_sum(array_map('course_lesson_count', $courses)) ?></b><span>课时内容</span></div>
    <div class="lf-stat"><b>mp4 / HLS</b><span>Range 分段播放</span></div>
  </div>
</section>

<section class="lf-sec lf-container">
  <div class="lf-sec-head">
    <div>
      <span class="lf-kicker">Courses</span>
      <h2 class="lf-sec-title">在架课程</h2>
    </div>
    <a class="btn subtle sm" href="/courses">查看全部</a>
  </div>
  <?php if (!$featured): ?>
    <div class="lf-empty">还没有上架课程。进入 <a href="/admin/">讲师后台</a> 创建第一门课程。</div>
  <?php else: ?>
    <div class="lf-grid">
      <?php foreach ($featured as $course) echo lf_course_card($course); ?>
    </div>
  <?php endif; ?>
</section>

<section class="lf-sec lf-container">
  <div class="lf-sec-head">
    <div>
      <span class="lf-kicker">Capability</span>
      <h2 class="lf-sec-title">交付能力域</h2>
    </div>
  </div>
  <div class="lf-grid">
    <?php
    $caps = [
        ['课程结构', '章节 / 课时 / 图文 / 视频 / 测验 / 资料', 'article'],
        ['学员与报名', '购买即入学 + 邀请码，名单与分组', 'user'],
        ['学习进度', '续播 / 完成标记 / 学习曲线', 'chart'],
        ['测验', '章节测验 + 结业测验，自动判分', 'quiz'],
        ['结业证书', '可分享链接 + 防伪校验', 'cert'],
        ['训练营', '开营节奏 / 作业 / 打卡（H3）', 'clock'],
    ];
    foreach ($caps as [$t, $d, $ic]): ?>
      <div class="lf-stat" style="display:flex;gap:14px;align-items:flex-start">
        <span class="lf-brand-ic" style="background:var(--accent-soft)"><?= lf_icon($ic, 18) ?></span>
        <div><b style="font-size:16px"><?= lf_e($t) ?></b><span><?= lf_e($d) ?></span></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php lf_page_end(); ?>

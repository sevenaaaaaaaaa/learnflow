<?php
require_once __DIR__ . '/includes/bootstrap.php';
$site = (string)(lf_setting_get('site_name') ?: 'LearnFlow');
$contact = (string)(lf_setting_get('contact_email') ?: ('support@' . (parse_url(lf_abs_url('/'), PHP_URL_HOST) ?: 'nownexts.com')));
lf_page_start(['title' => '隐私政策 · ' . $site, 'description' => $site . ' 隐私政策', 'container' => true]);
?>
<section class="lf-sec" style="padding-top:34px;max-width:820px;margin:0 auto">
  <span class="lf-kicker">Privacy</span>
  <h1 class="lf-sec-title" style="font-size:30px">隐私政策</h1>
  <div class="lf-prose" style="margin-top:20px">
    <p>本政策说明 <?= lf_e($site) ?> 如何收集、使用与保护你的个人信息。</p>
    <h3>一、我们收集的信息</h3>
    <p>账号信息（邮箱、称呼、手机号）、学习行为（报名、进度、测验、作业、打卡）、以及必要的访问日志（IP、时间）。</p>
    <h3>二、使用目的</h3>
    <p>用于课程交付、进度同步、证书颁发、学习提醒与客户服务；并可能用于经你同意的课程推荐。</p>
    <h3>三、共享</h3>
    <p>我们可能与矩阵服务（如支付 PayFlow、用户运营 UserLoop）在必要范围内共享你的邮箱与学习事件，用于交付与触达；不会向无关第三方出售你的个人信息。</p>
    <h3>四、存储与安全</h3>
    <p>数据存储于我们的服务器，采用访问控制、签名链接与传输加密等措施保护。我们按最小必要原则保留数据。</p>
    <h3>五、你的权利</h3>
    <p>你可以请求访问、更正、导出或删除你的个人数据，或退订学习提醒。请联系：<?= lf_e($contact) ?>。我们将在合理期限内处理。</p>
    <h3>六、Cookie</h3>
    <p>我们使用必要 Cookie 维持登录与偏好（如主题、语言）。</p>
    <p class="lf-faint">最后更新：<?= date('Y-m-d') ?></p>
  </div>
</section>
<?php lf_page_end(); ?>

<?php
require_once __DIR__ . '/includes/bootstrap.php';
$site = (string)(lf_setting_get('site_name') ?: 'LearnFlow');
$contact = (string)(lf_setting_get('contact_email') ?: ('support@' . (parse_url(lf_abs_url('/'), PHP_URL_HOST) ?: 'nownexts.com')));
lf_page_start(['title' => '服务条款 · ' . $site, 'description' => $site . ' 服务条款', 'container' => true]);
?>
<section class="lf-sec" style="padding-top:34px;max-width:820px;margin:0 auto">
  <span class="lf-kicker">Terms</span>
  <h1 class="lf-sec-title" style="font-size:30px">服务条款</h1>
  <div class="lf-prose" style="margin-top:20px">
    <p>欢迎使用 <?= lf_e($site) ?>（以下简称“本平台”）。访问或使用本平台即表示你同意本条款。</p>
    <h3>一、账号</h3>
    <p>你需要提供真实有效的邮箱注册账号，并对账号下的行为负责。请妥善保管密码；如发现账号被盗用，请及时联系我们。</p>
    <h3>二、课程与内容</h3>
    <p>课程的著作权归讲师或权利人所有。你获得的是个人学习许可，不得录制、转售、传播课程内容或用于商业用途。</p>
    <h3>三、支付与退款</h3>
    <p>支付由第三方支付服务（PayFlow）完成。虚拟内容一经交付通常不支持退款；如遇重复支付或服务未交付，请在 7 日内联系客服处理。</p>
    <h3>四、行为规范</h3>
    <p>你在训练营圈子等互动区发布的内容不得违法、侵权、骚扰或欺诈。我们有权删除违规内容并限制账号。</p>
    <h3>五、服务变更与免责</h3>
    <p>我们可能调整或暂停部分功能。因不可抗力或第三方服务故障导致的损失，本平台在合理范围内不承担责任。</p>
    <h3>六、联系方式</h3>
    <p>如有疑问请联系：<?= lf_e($contact) ?></p>
    <p class="lf-faint">最后更新：<?= date('Y-m-d') ?></p>
  </div>
</section>
<?php lf_page_end(); ?>

<?php
require_once __DIR__ . '/includes/bootstrap.php';

$no = trim((string)($_GET['no'] ?? ''));
$courseId = (string)($_GET['course'] ?? '');
$student = lf_student_current();
$cert = null;
$valid = false;
$query = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cert_no'])) {
    $query = trim((string)$_POST['cert_no']);
    header('Location: /certificate/' . rawurlencode($query));
    exit;
}

if ($courseId !== '' && $student !== null) {
    $course = course_find($courseId);
    if ($course !== null) {
        $cert = cert_maybe_issue((string)$student['id'], $course, (string)($student['name'] ?? ''));
        if ($cert === null) lf_flash('warn', '尚未满足结业条件：需完成全部课时并通过结业测验。');
    }
} elseif ($no !== '') {
    $cert = cert_get($no);
    $valid = $cert !== null && cert_verify($no);
}

lf_page_start([
    'title' => '证书验证 · LearnFlow',
    'description' => '验证 LearnFlow 结业证书真伪。',
    'active' => 'cert',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:34px;max-width:760px;margin:0 auto">
  <?php if ($cert !== null): ?>
    <?php if ($no !== '' && !$valid): ?>
      <div class="lf-flash danger" style="margin-bottom:18px">该证书校验失败，可能为伪造或已撤销。</div>
    <?php endif; ?>
    <div class="lf-cert">
      <span class="lf-kicker">Certificate of Completion</span>
      <h2><?= lf_e((string)$cert['course_title']) ?></h2>
      <p class="lf-faint" style="margin:0">兹证明</p>
      <div class="lf-cert-name"><?= lf_e((string)($cert['name'] ?: '学员')) ?></div>
      <p class="lf-muted" style="max-width:460px;margin:0 auto">已完成本课程全部课时并通过结业测验，特发此证。</p>
      <div class="lf-cert-meta">
        <span>证书编号<br><b class="lf-cert-no"><?= lf_e((string)$cert['cert_no']) ?></b></span>
        <span>颁发日期<br><b><?= lf_e(substr((string)$cert['issued_at'], 0, 10)) ?></b></span>
        <span>校验状态<br><b style="color:var(--ok)"><?= $valid || $no === '' ? '有效' : '有效' ?></b></span>
      </div>
      <div style="margin-top:26px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <button class="btn ghost sm" type="button" data-lf-copy="<?= lf_e(cert_share_url((string)$cert['cert_no'])) ?>">复制分享链接</button>
        <a class="btn subtle sm" href="/certificate/<?= rawurlencode((string)$cert['cert_no']) ?>">证书详情页</a>
      </div>
    </div>
    <?php if ($student !== null && $courseId !== ''): ?>
      <p style="text-align:center;margin-top:18px"><a class="btn primary sm" href="/dashboard">返回我的学习</a></p>
    <?php endif; ?>

  <?php else: ?>
    <span class="lf-kicker">Verify</span>
    <h1 class="lf-sec-title" style="font-size:30px;margin-top:10px">证书验证</h1>
    <p class="lf-muted">输入证书编号，核验 LearnFlow 结业证书的真伪。</p>
    <form method="post" class="lf-row" style="margin-top:20px;max-width:520px">
      <?= lf_csrf_field() ?>
      <input class="lf-inp" name="cert_no" placeholder="如 LF-20260916-XXXXXXXX" value="<?= lf_e($query) ?>" required>
      <button class="btn primary" type="submit" style="flex:0 0 auto">验证</button>
    </form>
    <?php if ($no !== ''): ?>
      <div class="lf-empty" style="margin-top:22px">未找到编号为 <b><?= lf_e($no) ?></b> 的证书。</div>
    <?php endif; ?>

    <?php if ($student !== null):
        $certs = cert_for_student((string)$student['id']);
        if ($certs): ?>
      <div class="lf-sec-head" style="margin-top:40px"><h2 class="lf-sec-title" style="font-size:22px">我的证书</h2></div>
      <div class="lf-grid">
        <?php foreach ($certs as $c): ?>
          <a class="lf-stat" href="/certificate/<?= rawurlencode((string)$c['cert_no']) ?>">
            <b style="font-size:16px"><?= lf_e((string)$c['course_title']) ?></b>
            <span class="lf-cert-no"><?= lf_e((string)$c['cert_no']) ?></span><br>
            <span><?= lf_e(substr((string)$c['issued_at'], 0, 10)) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; endif; ?>
  <?php endif; ?>
</section>
<?php lf_page_end(); ?>

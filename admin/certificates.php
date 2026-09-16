<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } elseif (($_POST['action'] ?? '') === 'revoke') {
        cert_revoke((string)($_POST['cert_no'] ?? ''));
        lf_flash('ok', '证书已撤销。');
    }
    header('Location: ' . lf_url('/admin/certificates.php'));
    exit;
}

$certs = cert_all();
krsort($certs);
$students = student_all();

lf_admin_page_start(['title' => '证书 · LearnFlow 讲师后台', 'active' => 'certificates']);
?>
<div class="lf-admin-head"><h1>结业证书</h1><span class="lf-faint">共 <?= count($certs) ?> 张</span></div>

<?php if (!$certs): ?>
  <div class="lf-empty">还没有颁发证书。学员完成全部课时并通过结业测验后自动颁发。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>证书编号</th><th>学员</th><th>课程</th><th>颁发时间</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($certs as $no => $c):
          $valid = cert_verify((string)$no);
          $stu = $students[(string)($c['student_id'] ?? '')] ?? null;
      ?>
        <tr>
          <td><a class="lf-cert-no" href="<?= lf_url('/certificate/') ?><?= rawurlencode((string)$no) ?>" target="_blank"><?= lf_e((string)$no) ?></a></td>
          <td><?= lf_e((string)($stu['name'] ?? $c['name'] ?? '')) ?><br><span class="lf-faint"><?= lf_e((string)($c['student_id'] ?? '')) ?></span></td>
          <td><?= lf_e((string)($c['course_title'] ?? '')) ?></td>
          <td class="lf-faint"><?= lf_e(substr((string)($c['issued_at'] ?? ''), 0, 16)) ?></td>
          <td><span class="lf-chip <?= $valid ? 'ok' : 'danger' ?>"><?= $valid ? '有效' : '已撤销' ?></span></td>
          <td><?php if ($valid): ?><form method="post" style="margin:0" onsubmit="return confirm('确认撤销该证书？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="cert_no" value="<?= lf_e((string)$no) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">撤销</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>

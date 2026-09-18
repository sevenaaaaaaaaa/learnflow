<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'upload') {
            $ok = 0; $err = '';
            $files = $_FILES['files'] ?? null;
            if ($files && is_array($files['name'])) {
                foreach ($files['name'] as $i => $name) {
                    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                    try {
                        $saved = lf_upload_save(['tmp_name' => $files['tmp_name'][$i], 'name' => $name, 'size' => $files['size'][$i], 'error' => $files['error'][$i]], 'library');
                        media_add($saved, ['scope' => 'library']);
                        $ok++;
                    } catch (Throwable $e) {
                        $err = $e->getMessage();
                    }
                }
            }
            lf_flash($ok ? 'ok' : 'danger', $ok ? "已上传 $ok 个文件。" : ('上传失败：' . $err));
        } elseif ($action === 'delete') {
            media_delete((string)($_POST['id'] ?? ''));
            lf_flash('ok', '已删除素材。');
        }
    }
    header('Location: ' . lf_url('/admin/media.php'));
    exit;
}

$kind = (string)($_GET['kind'] ?? '');
$media = media_all($kind);
$total = 0;
foreach ($media as $m) $total += (int)($m['size'] ?? 0);

lf_admin_page_start(['title' => '素材库 · LearnFlow 讲师后台', 'active' => 'media']);
?>
<div class="lf-admin-head"><h1>素材库</h1><span class="lf-faint"><?= count($media) ?> 个 · <?= number_format($total / 1048576, 1) ?> MB</span></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:20px">
  <form method="post" enctype="multipart/form-data" class="lf-row" style="align-items:flex-end">
    <?= lf_csrf_field() ?><input type="hidden" name="action" value="upload">
    <div class="lf-field" style="margin:0;flex:1"><label>上传素材（图片/视频/音频/文档，可多选）</label><input class="lf-inp" type="file" name="files[]" multiple style="height:auto;padding:10px"></div>
    <button class="btn primary sm" type="submit" style="flex:0 0 auto">上传</button>
  </form>
</div>

<div class="lf-tabs">
  <a class="lf-tab<?= $kind === '' ? ' on' : '' ?>" href="<?= lf_url('/admin/media.php') ?>">全部</a>
  <?php foreach (['image' => '图片', 'video' => '视频', 'audio' => '音频', 'file' => '文档'] as $k => $label): ?>
    <a class="lf-tab<?= $kind === $k ? ' on' : '' ?>" href="<?= lf_url('/admin/media.php?kind=' . $k) ?>"><?= lf_e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$media): ?>
  <div class="lf-empty">还没有素材，上传后可在编辑器里插入、作为封面或附件。</div>
<?php else: ?>
  <div class="lf-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
    <?php foreach ($media as $m): ?>
      <div class="lf-course-card">
        <div style="aspect-ratio:16/10;background:var(--hover);display:grid;place-items:center;overflow:hidden">
          <?php if (($m['kind'] ?? '') === 'image'): ?>
            <img src="<?= lf_e(media_public_url((string)$m['id'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            <?= lf_icon(($m['kind'] ?? '') === 'video' ? 'play' : 'file', 34) ?>
          <?php endif; ?>
        </div>
        <div class="lf-course-body" style="padding:12px;gap:6px">
          <div class="lf-faint" style="font-size:12px;word-break:break-all"><?= lf_e((string)$m['name']) ?></div>
          <div class="lf-faint" style="font-size:11px"><?= number_format((int)($m['size'] ?? 0) / 1024, 0) ?> KB · <?= lf_e((string)$m['created_at']) ?></div>
          <div class="lf-row" style="gap:6px;flex-wrap:wrap">
            <button class="btn subtle sm" type="button" data-lf-copy="<?= lf_e(media_public_url((string)$m['id'])) ?>" style="height:28px">复制链接</button>
            <form method="post" style="margin:0" onsubmit="return confirm('删除该素材？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= lf_e((string)$m['id']) ?>"><button class="btn subtle sm" type="submit" style="height:28px;color:var(--danger)">删除</button></form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php lf_admin_page_end(); ?>

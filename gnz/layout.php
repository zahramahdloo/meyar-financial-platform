<?php
/** MEYAR — چیدمان ماژولار جدول‌های صفحه اصلی (درگ اند دراپ) */
require_once __DIR__ . '/_panel.php';
panel_guard('layout');

$settings = meyar_load_settings();
$groups   = meyar_groups();
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_layout') {
    if (!meyar_csrf_ok()) {
        $err = 'توکن امنیتی نامعتبر.';
    } else {
        $decoded = json_decode((string)($_POST['layout_json'] ?? ''), true);
        $clean = [];
        if (is_array($decoded)) {
            foreach ($decoded as $slot) {
                $g = (string)($slot['group'] ?? '');
                $w = ($slot['width'] ?? '') === 'full' ? 'full' : 'half';
                if (isset($groups[$g])) $clean[] = ['group' => $g, 'width' => $w];
            }
        }
        if ($clean) {
            $settings['layout'] = $clean;
            meyar_save_settings($settings) ? $msg = 'چیدمان ذخیره شد ✔' : $err = 'خطا در ذخیره.';
            $settings = meyar_load_settings();
        } else {
            $err = 'چیدمان نامعتبر.';
        }
    }
}

$layout = (array)($settings['layout'] ?? []);
$inLayout = array_column($layout, 'group');
foreach ($groups as $gid => $t) {
    if (!in_array($gid, $inLayout, true)) $layout[] = ['group' => $gid, 'width' => 'half'];
}
$csrf = meyar_csrf();

panel_header('چیدمان صفحه اصلی', 'layout');
if ($msg) echo '<div class="msg">' . meyar_h($msg) . '</div>';
if ($err) echo '<div class="err">' . meyar_h($err) . '</div>';
?>

<div class="card">
  <h2>جدول‌ها را با موس بکشید و جابه‌جا کنید</h2>
  <p class="hint" style="margin-bottom:16px">
    ترتیب بالا-به-پایین همان ترتیب نمایش در سایت است. عرض «نصف» یعنی دو جدول کنار هم؛ «کامل» یعنی تمام‌عرض.
    برای اینکه جدول‌ها در یک سطر مرتب باشند، جدول‌های هم‌اندازه را با عرض «نصف» پشت سر هم بگذارید.
  </p>

  <div id="layoutList" class="layout-list">
    <?php foreach ($layout as $slot): $gid = $slot['group']; ?>
    <div class="layout-item" draggable="true" data-group="<?= meyar_h($gid) ?>">
      <span class="drag-handle">⠿</span>
      <b><?= meyar_h($groups[$gid]) ?></b>
      <select class="width-sel">
        <option value="half" <?= ($slot['width'] ?? '') !== 'full' ? 'selected' : '' ?>>عرض: نصف</option>
        <option value="full" <?= ($slot['width'] ?? '') === 'full' ? 'selected' : '' ?>>عرض: کامل</option>
      </select>
    </div>
    <?php endforeach; ?>
  </div>

  <form method="post" id="layoutForm" style="margin-top:18px">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="save_layout">
    <input type="hidden" name="layout_json" id="layoutJson">
    <button class="btn" type="submit">💾 ذخیره چیدمان</button>
    <a class="btn btn-ghost" href="../" target="_blank">پیش‌نمایش سایت ↗</a>
  </form>
</div>

<div class="card">
  <h2>پیش‌نمایش چیدمان</h2>
  <div id="previewGrid" class="preview-grid"></div>
</div>

<style>
.layout-list { display: flex; flex-direction: column; gap: 10px; }
.layout-item {
  display: flex; align-items: center; gap: 14px;
  background: var(--card2); border: 1px solid var(--line); border-radius: 12px;
  padding: 12px 16px; cursor: grab; transition: border .2s, transform .15s, opacity .2s;
}
.layout-item b { flex: 1; }
.layout-item .width-sel { width: 130px; }
.layout-item.dragging { opacity: .45; border-color: var(--gold); }
.layout-item.drag-over { border-color: var(--gold); transform: translateY(2px); box-shadow: 0 -3px 0 var(--gold); }
.drag-handle { color: var(--mut); font-size: 18px; cursor: grab; }
.preview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.preview-cell {
  background: linear-gradient(135deg, rgba(240,207,122,.14), rgba(168,124,31,.1));
  border: 1px dashed rgba(212,164,55,.45); border-radius: 10px;
  padding: 18px 10px; text-align: center; font-size: 13px; color: var(--gold-l);
}
.preview-cell.full { grid-column: 1 / -1; }
</style>

<script>
(function () {
  var list = document.getElementById('layoutList');
  var dragged = null;

  function bind(item) {
    item.addEventListener('dragstart', function () {
      dragged = item;
      setTimeout(function () { item.classList.add('dragging'); }, 0);
    });
    item.addEventListener('dragend', function () {
      item.classList.remove('dragging');
      dragged = null;
      renderPreview();
    });
    item.addEventListener('dragover', function (e) {
      e.preventDefault();
      if (!dragged || dragged === item) return;
      item.classList.add('drag-over');
      var rect = item.getBoundingClientRect();
      var before = (e.clientY - rect.top) < rect.height / 2;
      list.insertBefore(dragged, before ? item : item.nextSibling);
    });
    item.addEventListener('dragleave', function () { item.classList.remove('drag-over'); });
    item.addEventListener('drop', function (e) { e.preventDefault(); item.classList.remove('drag-over'); });
    item.querySelector('.width-sel').addEventListener('change', renderPreview);
  }

  function currentLayout() {
    return Array.prototype.map.call(list.children, function (el) {
      return { group: el.dataset.group, width: el.querySelector('.width-sel').value };
    });
  }

  function renderPreview() {
    var pg = document.getElementById('previewGrid');
    pg.innerHTML = '';
    currentLayout().forEach(function (slot) {
      var names = {coins:'جدول سکه‌ها', parsian:'سکه‌های پارسیان', gold:'جدول طلا', currency:'جدول ارزها'};
      var c = document.createElement('div');
      c.className = 'preview-cell' + (slot.width === 'full' ? ' full' : '');
      c.textContent = names[slot.group] || slot.group;
      pg.appendChild(c);
    });
  }

  Array.prototype.forEach.call(list.children, bind);
  renderPreview();

  document.getElementById('layoutForm').addEventListener('submit', function () {
    document.getElementById('layoutJson').value = JSON.stringify(currentLayout());
  });
})();
</script>

<?php panel_footer(); ?>

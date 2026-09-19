<?php
/** MEYAR — مدیریت ارزها و سکه‌ها: افزودن/ویرایش/حذف + سئو و اسکیمای هر صفحه */
require_once __DIR__ . '/_panel.php';
panel_guard('items');

$pdo = meyar_db();
$msg = ''; $err = '';
$groups = meyar_groups();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!meyar_csrf_ok()) {
        $err = 'توکن امنیتی نامعتبر.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_item') {
            $id = preg_replace('/[^a-z0-9_]/i', '', (string)($_POST['item_id'] ?? ''));
            $isNew = $id === '';
            if ($isNew) {
                $slug = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string)($_POST['new_slug'] ?? '')));
                if ($slug === '' || meyar_item_by_id($slug)) {
                    $slug = 'c' . substr(md5(uniqid('', true)), 0, 7);
                }
                $id = $slug;
            }
            $title = trim((string)($_POST['title'] ?? ''));
            $grp   = in_array($_POST['grp'] ?? '', array_keys($groups), true) ? $_POST['grp'] : 'coins';
            $icon  = trim((string)($_POST['icon'] ?? '')) ?: 'coin';
            $sort  = (int)($_POST['sort'] ?? 500);
            $srcT  = in_array($_POST['source_type'] ?? '', ['tgju','tgju_usd','parsian','manual'], true) ? $_POST['source_type'] : 'manual';
            $srcA  = trim((string)($_POST['source_arg'] ?? ''));
            $seoT  = trim((string)($_POST['seo_title'] ?? ''));
            $seoD  = trim((string)($_POST['seo_desc'] ?? ''));
            $pageD = trim((string)($_POST['page_desc'] ?? ''));
            $schJ  = trim((string)($_POST['schema_json'] ?? ''));
            if ($schJ !== '' && json_decode($schJ) === null) {
                $err = 'اسکیمای سفارشی JSON معتبر نیست؛ ذخیره نشد.';
            } elseif ($title === '') {
                $err = 'عنوان الزامی است.';
            } else {
                // آیا آیتم داخلی است؟
                $isBuiltin = false;
                foreach (meyar_builtin_items() as $b) { if ($b['id'] === $id) { $isBuiltin = true; break; } }
                $st = $pdo->prepare("SELECT id FROM items_meta WHERE id=?");
                $st->execute([$id]);
                $exists = (bool)$st->fetch();
                if ($exists) {
                    if ($isBuiltin) {
                        $pdo->prepare("UPDATE items_meta SET title=?, grp=?, icon=?, sort=?, seo_title=?, seo_desc=?, page_desc=?, schema_json=? WHERE id=?")
                            ->execute([$title, $grp, $icon, $sort, $seoT, $seoD, $pageD, $schJ, $id]);
                    } else {
                        $pdo->prepare("UPDATE items_meta SET title=?, grp=?, icon=?, sort=?, source_type=?, source_arg=?, seo_title=?, seo_desc=?, page_desc=?, schema_json=? WHERE id=?")
                            ->execute([$title, $grp, $icon, $sort, $srcT, $srcA, $seoT, $seoD, $pageD, $schJ, $id]);
                    }
                } else {
                    $pdo->prepare("INSERT INTO items_meta(id,title,grp,icon,sort,source_type,source_arg,is_custom,seo_title,seo_desc,page_desc,schema_json)
                                   VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$id, $title, $grp, $icon, $sort,
                                   $isBuiltin ? null : $srcT, $isBuiltin ? null : $srcA,
                                   $isBuiltin ? 0 : 1, $seoT, $seoD, $pageD, $schJ]);
                }
                $msg = 'آیتم «' . $title . '» ذخیره شد ✔';
            }
        }

        if ($action === 'delete_item') {
            $id = preg_replace('/[^a-z0-9_]/i', '', (string)($_POST['item_id'] ?? ''));
            $isBuiltin = false;
            foreach (meyar_builtin_items() as $b) { if ($b['id'] === $id) { $isBuiltin = true; break; } }
            if ($isBuiltin) {
                // داخلی: علامت حذف (قابل بازگردانی)
                $st = $pdo->prepare("SELECT id FROM items_meta WHERE id=?");
                $st->execute([$id]);
                if ($st->fetch()) {
                    $pdo->prepare("UPDATE items_meta SET deleted=1 WHERE id=?")->execute([$id]);
                } else {
                    $pdo->prepare("INSERT INTO items_meta(id, deleted) VALUES(?,1)")->execute([$id]);
                }
                $msg = 'آیتم داخلی حذف شد (از «حذف‌شده‌ها» قابل بازگردانی است).';
            } else {
                $pdo->prepare("DELETE FROM items_meta WHERE id=?")->execute([$id]);
                $msg = 'آیتم سفارشی حذف شد.';
            }
        }

        if ($action === 'restore_item') {
            $id = preg_replace('/[^a-z0-9_]/i', '', (string)($_POST['item_id'] ?? ''));
            $pdo->prepare("UPDATE items_meta SET deleted=0 WHERE id=?")->execute([$id]);
            $msg = 'آیتم بازگردانی شد ✔';
        }
    }
}

$items = meyar_items_full();
$deleted = $pdo->query("SELECT id, title FROM items_meta WHERE deleted=1")->fetchAll();
$editId = preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['edit'] ?? ''));
$editItem = $editId ? meyar_item_by_id($editId) : null;
$csrf = meyar_csrf();

panel_header('ارزها و سکه‌ها', 'items');
if ($msg) echo '<div class="msg">' . meyar_h($msg) . '</div>';
if ($err) echo '<div class="err">' . meyar_h($err) . '</div>';

/* ---------- فرم ویرایش / افزودن ---------- */
$showForm = $editItem || isset($_GET['new']);
if ($showForm):
    $e = $editItem ?: ['id'=>'','title'=>'','group'=>'coins','icon'=>'coin','source'=>['manual',0],'sort'=>500,
                       'is_custom'=>1,'seo_title'=>'','seo_desc'=>'','page_desc'=>'','schema_json'=>''];
    $isBuiltin = $editItem && !$editItem['is_custom'];
?>
<div class="card" style="border-color:rgba(212,164,55,.45)">
  <h2><?= $editItem ? 'ویرایش: ' . meyar_h($e['title']) : 'افزودن آیتم جدید' ?></h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="save_item">
    <input type="hidden" name="item_id" value="<?= meyar_h($editItem ? $e['id'] : '') ?>">

    <div class="grid3" style="margin-bottom:14px">
      <div><label>عنوان *</label><input type="text" name="title" value="<?= meyar_h($e['title']) ?>" required></div>
      <div><label>جدول</label>
        <select name="grp"><?php foreach ($groups as $gid => $gt): ?><option value="<?= $gid ?>" <?= $e['group'] === $gid ? 'selected' : '' ?>><?= meyar_h($gt) ?></option><?php endforeach; ?></select>
      </div>
      <div><label>آیکون (coin یا gold یا ایموجی پرچم)</label><input type="text" name="icon" value="<?= meyar_h($e['icon']) ?>"></div>
      <div><label>ترتیب نمایش (کوچک‌تر = بالاتر)</label><input type="number" name="sort" value="<?= (int)$e['sort'] ?>" style="direction:ltr"></div>
      <?php if (!$editItem): ?>
      <div><label>شناسه انگلیسی صفحه (اختیاری — مثل my_coin)</label><input type="text" name="new_slug" placeholder="خودکار" style="direction:ltr;text-align:left"></div>
      <?php endif; ?>
    </div>

    <?php if (!$isBuiltin): ?>
    <div class="grid2" style="margin-bottom:14px">
      <div><label>منبع قیمت</label>
        <select name="source_type" id="srcType">
          <option value="manual"   <?= $e['source'][0] === 'manual' ? 'selected' : '' ?>>قیمت دستی (تومان)</option>
          <option value="tgju"     <?= $e['source'][0] === 'tgju' ? 'selected' : '' ?>>کلید TGJU (ریالی)</option>
          <option value="tgju_usd" <?= $e['source'][0] === 'tgju_usd' ? 'selected' : '' ?>>کلید TGJU (دلاری)</option>
          <option value="parsian"  <?= $e['source'][0] === 'parsian' ? 'selected' : '' ?>>پارسیان (وزن گرم × طلای ۱۸)</option>
        </select>
      </div>
      <div><label id="srcArgLabel">مقدار منبع (قیمت تومان / کلید tgju / وزن گرم)</label>
        <input type="text" name="source_arg" value="<?= meyar_h((string)$e['source'][1]) ?>" style="direction:ltr;text-align:left">
      </div>
    </div>
    <?php else: ?>
    <p class="hint" style="margin-bottom:14px">این آیتم داخلی است؛ منبع قیمت آن ثابت است (<?= meyar_h($e['source'][0] . ':' . $e['source'][1]) ?>) ولی عنوان، جدول، سئو و بقیه موارد قابل ویرایش است.</p>
    <?php endif; ?>

    <div class="card" style="background:var(--card2)">
      <h2>سئوی صفحه اختصاصی (<span style="direction:ltr;display:inline-block">/price/<?= meyar_h($editItem ? $e['id'] : '…') ?></span>)</h2>
      <div class="grid2" style="margin-bottom:12px">
        <div><label>عنوان سئو (تگ title — خالی: خودکار)</label><input type="text" name="seo_title" value="<?= meyar_h($e['seo_title']) ?>" placeholder="قیمت لحظه‌ای <?= meyar_h($e['title']) ?> امروز | سکه و جواهر معیار"></div>
        <div><label>توضیحات متا (خالی: خودکار)</label><input type="text" name="seo_desc" value="<?= meyar_h($e['seo_desc']) ?>" maxlength="300"></div>
      </div>
      <div style="margin-bottom:12px">
        <label>متن توضیحات صفحه (زیر قیمت، بالای نمودار — برای سئو عالی است)</label>
        <textarea name="page_desc" rows="5" placeholder="مثلاً: سکه امامی یکی از پرمعامله‌ترین مسکوکات طلا در ایران است…"><?= meyar_h($e['page_desc']) ?></textarea>
      </div>
      <div>
        <label>اسکیمای سفارشی JSON-LD (خالی: اسکیمای خودکار Product + Breadcrumb ساخته می‌شود)</label>
        <textarea name="schema_json" rows="4" style="direction:ltr;text-align:left" placeholder='{"@context":"https://schema.org", ...}'><?= meyar_h($e['schema_json']) ?></textarea>
      </div>
    </div>

    <button class="btn" type="submit">💾 ذخیره آیتم</button>
    <a class="btn btn-ghost" href="items.php">انصراف</a>
  </form>
</div>
<script>
var st = document.getElementById('srcType');
if (st) {
  var lbl = document.getElementById('srcArgLabel');
  var map = { manual: 'قیمت (تومان)', tgju: 'کلید TGJU (مثل sekee یا price_dollar_rl)', tgju_usd: 'کلید TGJU دلاری (مثل ons)', parsian: 'وزن به گرم (مثل 0.5)' };
  function upd(){ lbl.textContent = map[st.value] || 'مقدار'; }
  st.addEventListener('change', upd); upd();
}
</script>
<?php endif; ?>

<div class="topbar">
  <span class="hint">روی هر آیتم «ویرایش» بزنید تا عنوان، سئو، توضیحات و اسکیمای صفحه‌اش را مدیریت کنید.</span>
  <a class="btn" href="items.php?new=1">＋ افزودن آیتم جدید</a>
</div>

<?php foreach ($groups as $gid => $gtitle):
    $rows = array_values(array_filter($items, function ($i) use ($gid) { return $i['group'] === $gid; }));
    if (!$rows) continue; ?>
<div class="card">
  <h2><?= meyar_h($gtitle) ?> <span class="pill"><?= meyar_fa_num((string)count($rows)) ?> آیتم</span></h2>
  <div style="overflow-x:auto">
  <table>
    <thead><tr><th>عنوان</th><th>شناسه</th><th>منبع</th><th>ترتیب</th><th>سئو</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i): ?>
      <tr>
        <td><?= meyar_h($i['title']) ?> <?= $i['is_custom'] ? '<span class="pill">سفارشی</span>' : '' ?></td>
        <td class="num" style="direction:ltr;text-align:left"><a href="../price/<?= meyar_h($i['id']) ?>" target="_blank"><?= meyar_h($i['id']) ?></a></td>
        <td class="hint"><?= meyar_h($i['source'][0]) ?><?= $i['source'][0] !== 'manual' ? ' : ' . meyar_h((string)$i['source'][1]) : '' ?></td>
        <td class="num"><?= (int)$i['sort'] ?></td>
        <td><?= ($i['seo_title'] !== '' || $i['seo_desc'] !== '' || $i['page_desc'] !== '') ? '<span class="pill ok">سفارشی</span>' : '<span class="pill">خودکار</span>' ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-sm btn-ghost" href="items.php?edit=<?= meyar_h($i['id']) ?>"><i class="hgi-stroke hgi-edit-02" aria-hidden="true"></i> ویرایش</a>
          <form method="post" style="display:inline" onsubmit="return confirm('حذف «<?= meyar_h($i['title']) ?>»؟')">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="delete_item">
            <input type="hidden" name="item_id" value="<?= meyar_h($i['id']) ?>">
            <button class="btn btn-sm btn-danger" type="submit">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endforeach; ?>

<?php if ($deleted): ?>
<div class="card">
  <h2>حذف‌شده‌ها (قابل بازگردانی)</h2>
  <table>
    <tbody>
    <?php foreach ($deleted as $d): ?>
      <tr>
        <td><?= meyar_h($d['title'] ?: $d['id']) ?></td>
        <td style="width:120px">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="restore_item">
            <input type="hidden" name="item_id" value="<?= meyar_h($d['id']) ?>">
            <button class="btn btn-sm btn-ghost" type="submit">↩ بازگردانی</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php panel_footer(); ?>

<?php
/** MEYAR — تعدیل قیمت‌ها (مبلغ/درصد/مخفی + پارامترهای محاسبه) */
require_once __DIR__ . '/_panel.php';
panel_guard('prices');

$settings = meyar_load_settings();
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_adjustments') {
    if (!meyar_csrf_ok()) {
        $err = 'توکن امنیتی نامعتبر.';
    } else {
        $adj = [];
        foreach ((array)($_POST['offset'] ?? []) as $id => $off) {
            $id = preg_replace('/[^a-z0-9_]/i', '', $id);
            $offset  = (float)str_replace([',', '،', ' '], '', (string)$off);
            $percent = (float)str_replace([',', '،', ' '], '', (string)($_POST['percent'][$id] ?? 0));
            $hidden  = !empty($_POST['hidden'][$id]);
            if ($offset != 0 || $percent != 0 || $hidden) {
                $adj[$id] = ['offset' => $offset, 'percent' => $percent, 'hidden' => $hidden];
            }
        }
        $settings['adjustments']        = $adj;
        $settings['buy_spread_percent'] = (float)($_POST['buy_spread'] ?? 0.5);
        $settings['parsian_premium']    = (float)str_replace([',', '،', ' '], '', (string)($_POST['parsian_premium'] ?? 0));
        $settings['cache_ttl']          = max(20, (int)($_POST['cache_ttl'] ?? 60));
        meyar_save_settings($settings) ? $msg = 'تنظیمات قیمت‌ها ذخیره شد ✔' : $err = 'خطا در ذخیره (دسترسی پوشه data).';
        $settings = meyar_load_settings();
    }
}

$prices = meyar_build_prices();
$groups = meyar_groups();
$adj    = (array)($settings['adjustments'] ?? []);
$csrf   = meyar_csrf();

panel_header('تعدیل قیمت‌ها', 'prices');
if ($msg) echo '<div class="msg">' . meyar_h($msg) . '</div>';
if ($err) echo '<div class="err">' . meyar_h($err) . '</div>';
?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">
  <input type="hidden" name="action" value="save_adjustments">

  <?php foreach ($groups as $gid => $gtitle):
      $rows = array_values(array_filter($prices['items'], function ($i) use ($gid) { return $i['group'] === $gid; }));
      if (!$rows) continue; ?>
  <div class="card">
    <h2><?= meyar_h($gtitle) ?></h2>
    <div style="overflow-x:auto">
    <table>
      <thead>
        <tr><th>عنوان</th><th>قیمت پایه (منبع)</th><th>تعدیل مبلغ (تومان ±)</th><th>تعدیل درصد (±)</th><th>قیمت نهایی</th><th>مخفی</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i):
            $a = (array)($adj[$i['id']] ?? []); ?>
        <tr>
          <td><?= meyar_h($i['title']) ?></td>
          <td class="num"><?= meyar_h(meyar_fmt($i['base'], $i['unit'] === 'دلار' ? 2 : 0)) ?> <span class="hint"><?= meyar_h($i['unit']) ?></span></td>
          <td><input type="text" name="offset[<?= meyar_h($i['id']) ?>]" value="<?= meyar_h((string)($a['offset'] ?? '0')) ?>" data-recalc data-base="<?= $i['base'] ?>" data-row="<?= meyar_h($i['id']) ?>" data-kind="offset" style="direction:ltr;text-align:left"></td>
          <td><input type="text" name="percent[<?= meyar_h($i['id']) ?>]" value="<?= meyar_h((string)($a['percent'] ?? '0')) ?>" data-recalc data-base="<?= $i['base'] ?>" data-row="<?= meyar_h($i['id']) ?>" data-kind="percent" style="direction:ltr;text-align:left"></td>
          <td class="num final" id="final-<?= meyar_h($i['id']) ?>"><?= meyar_h($i['live_fmt']) ?></td>
          <td style="text-align:center"><input type="checkbox" name="hidden[<?= meyar_h($i['id']) ?>]" value="1" <?= !empty($a['hidden']) ? 'checked' : '' ?> style="width:auto"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="card">
    <h2>پارامترهای محاسبه</h2>
    <div class="grid3">
      <div>
        <label>اجرت سکه پارسیان (تومان)</label>
        <input type="text" name="parsian_premium" value="<?= meyar_h((string)$settings['parsian_premium']) ?>" style="direction:ltr;text-align:left">
      </div>
      <div>
        <label>اختلاف قیمت خرید از فروش (درصد)</label>
        <input type="text" name="buy_spread" value="<?= meyar_h((string)$settings['buy_spread_percent']) ?>" style="direction:ltr;text-align:left">
      </div>
      <div>
        <label>مدت کش قیمت‌ها (ثانیه)</label>
        <input type="text" name="cache_ttl" value="<?= meyar_h((string)$settings['cache_ttl']) ?>" style="direction:ltr;text-align:left">
      </div>
    </div>
  </div>

  <div class="sticky-save"><button class="btn" type="submit">💾 ذخیره همه تغییرات</button></div>
</form>

<script>
function faNum(n){var d=['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];return String(n).replace(/\d/g,function(x){return d[+x];});}
function recalcRow(id){
  var off=document.querySelector('input[data-row="'+id+'"][data-kind="offset"]');
  var pct=document.querySelector('input[data-row="'+id+'"][data-kind="percent"]');
  var out=document.getElementById('final-'+id);
  if(!off||!pct||!out)return;
  var base=parseFloat(off.dataset.base)||0;
  var o=parseFloat(String(off.value).replace(/[,\s،]/g,''))||0;
  var p=parseFloat(String(pct.value).replace(/[,\s،]/g,''))||0;
  var v=base*(1+p/100)+o;
  out.textContent=faNum(Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,','));
}
document.querySelectorAll('input[data-recalc]').forEach(function(inp){
  inp.addEventListener('input',function(){recalcRow(inp.dataset.row);});
});
</script>

<?php panel_footer(); ?>

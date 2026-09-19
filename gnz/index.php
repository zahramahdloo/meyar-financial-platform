<?php
/** MEYAR — داشبورد: آمار بازدید و وضعیت کلی */
require_once __DIR__ . '/_panel.php';
panel_guard('dashboard');

$pdo = meyar_db();
$today = date('Y-m-d');
$d7  = date('Y-m-d', strtotime('-6 days'));
$d30 = date('Y-m-d', strtotime('-29 days'));

function q1(PDO $pdo, string $sql, array $p = []): int {
    $st = $pdo->prepare($sql); $st->execute($p);
    return (int)$st->fetchColumn();
}

$vToday   = q1($pdo, "SELECT COUNT(*) FROM visits WHERE d=?", [$today]);
$uToday   = q1($pdo, "SELECT COUNT(DISTINCT ip_hash) FROM visits WHERE d=?", [$today]);
$v7       = q1($pdo, "SELECT COUNT(*) FROM visits WHERE d>=?", [$d7]);
$u7       = q1($pdo, "SELECT COUNT(DISTINCT ip_hash) FROM visits WHERE d>=?", [$d7]);
$v30      = q1($pdo, "SELECT COUNT(*) FROM visits WHERE d>=?", [$d30]);
$openChat = q1($pdo, "SELECT COUNT(*) FROM threads WHERE status='open'");
$unread   = q1($pdo, "SELECT COALESCE(SUM(admin_unread),0) FROM threads");

// نمودار ۳۰ روز
$series = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $series[$d] = ['v' => 0, 'u' => 0];
}
$st = $pdo->prepare("SELECT d, COUNT(*) v, COUNT(DISTINCT ip_hash) u FROM visits WHERE d>=? GROUP BY d");
$st->execute([$d30]);
foreach ($st as $r) { if (isset($series[$r['d']])) $series[$r['d']] = ['v' => (int)$r['v'], 'u' => (int)$r['u']]; }
$labels = []; $vVals = []; $uVals = [];
foreach ($series as $d => $s) {
    $labels[] = meyar_jdate_from_ymd($d);
    $vVals[] = $s['v'];
    $uVals[] = $s['u'];
}

// پربازدیدترین صفحه‌ها (۳۰ روز)
$topPages = $pdo->prepare("SELECT path, COUNT(*) c FROM visits WHERE d>=? GROUP BY path ORDER BY c DESC LIMIT 10");
$topPages->execute([$d30]);
$topPages = $topPages->fetchAll();

// مرجع‌ها
$topRefs = $pdo->prepare("SELECT ref, COUNT(*) c FROM visits WHERE d>=? AND ref!='' GROUP BY ref ORDER BY c DESC LIMIT 8");
$topRefs->execute([$d30]);
$topRefs = $topRefs->fetchAll();

$market = meyar_build_prices();

panel_header('داشبورد', 'dashboard');
?>

<div class="stats-grid">
  <div class="stat-card"><div class="s-label">بازدید امروز</div><div class="s-value"><?= meyar_fa_num(number_format($vToday)) ?></div><div class="s-sub"><?= meyar_fa_num(number_format($uToday)) ?> بازدیدکننده یکتا</div></div>
  <div class="stat-card"><div class="s-label">بازدید ۷ روز اخیر</div><div class="s-value"><?= meyar_fa_num(number_format($v7)) ?></div><div class="s-sub"><?= meyar_fa_num(number_format($u7)) ?> یکتا</div></div>
  <div class="stat-card"><div class="s-label">بازدید ۳۰ روز اخیر</div><div class="s-value"><?= meyar_fa_num(number_format($v30)) ?></div><div class="s-sub">همه صفحات</div></div>
  <div class="stat-card"><div class="s-label">گفتگوهای باز</div><div class="s-value"><?= meyar_fa_num(number_format($openChat)) ?></div><div class="s-sub"><?= meyar_fa_num(number_format($unread)) ?> پیام خوانده‌نشده</div></div>
  <div class="stat-card"><div class="s-label">منبع قیمت</div>
    <div class="s-value" style="font-size:16px;padding-top:6px"><?php if ($market['stale']): ?><i class="hgi-stroke hgi-alert-02" aria-hidden="true"></i> کش قدیمی<?php else: ?><i class="hgi-stroke hgi-checkmark-circle-02" aria-hidden="true"></i> متصل<?php endif; ?></div>
    <div class="s-sub">آخرین دریافت: <?= meyar_h($market['updated']) ?></div>
  </div>
</div>

<div class="card">
  <h2>نمودار بازدید ۳۰ روز اخیر (تاریخ شمسی)</h2>
  <div style="height:300px;position:relative"><canvas id="visitsChart"></canvas></div>
</div>

<div class="grid2">
  <div class="card">
    <h2>پربازدیدترین صفحه‌ها (۳۰ روز)</h2>
    <?php if ($topPages): ?>
    <table>
      <thead><tr><th>صفحه</th><th>بازدید</th></tr></thead>
      <tbody>
      <?php foreach ($topPages as $p): ?>
        <tr><td style="direction:ltr;text-align:left"><?= meyar_h($p['path']) ?></td><td class="num"><?= meyar_fa_num(number_format((int)$p['c'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="hint">هنوز بازدیدی ثبت نشده.</p><?php endif; ?>
  </div>
  <div class="card">
    <h2>مرجع ورودی (ریفرر)</h2>
    <?php if ($topRefs): ?>
    <table>
      <thead><tr><th>مرجع</th><th>تعداد</th></tr></thead>
      <tbody>
      <?php foreach ($topRefs as $r): ?>
        <tr><td style="direction:ltr;text-align:left;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= meyar_h($r['ref']) ?></td><td class="num"><?= meyar_fa_num(number_format((int)$r['c'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="hint">ورودی از مرجع خارجی ثبت نشده (ورود مستقیم).</p><?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>وضعیت سئو</h2>
  <?php
  $items = meyar_items_full();
  $withSeo = 0;
  foreach ($items as $i) { if ($i['seo_title'] !== '' || $i['seo_desc'] !== '' || $i['page_desc'] !== '') $withSeo++; }
  ?>
  <p style="font-size:13.5px">
    <span class="pill ok"><?= meyar_fa_num((string)$withSeo) ?> آیتم دارای سئوی سفارشی</span>
    <span class="pill"><?= meyar_fa_num((string)(count($items) - $withSeo)) ?> آیتم با سئوی خودکار</span>
  </p>
  <p class="hint">همه صفحه‌های ارز/سکه به‌صورت خودکار عنوان، توضیحات متا، اسکیمای Product و BreadcrumbList دارند. برای سفارشی‌سازی به بخش «ارزها و سکه‌ها» بروید. نقشه سایت: <a href="../sitemap.php" target="_blank">sitemap.xml</a></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('visitsChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [
      { label: 'بازدید', data: <?= json_encode($vVals) ?>, backgroundColor: 'rgba(212,164,55,.65)', borderRadius: 4 },
      { label: 'یکتا', data: <?= json_encode($uVals) ?>, backgroundColor: 'rgba(93,109,255,.5)', borderRadius: 4 }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { labels: { font: {family:'IRANSansXFaNum'}, color: '#9aa0b5' }, rtl: true } },
    scales: {
      x: { ticks: { font:{family:'IRANSansXFaNum',size:10}, color:'#9aa0b5', maxTicksLimit: 10 }, grid: { display:false } },
      y: { ticks: { color:'#9aa0b5', precision: 0 }, grid: { color:'rgba(255,255,255,.05)' } }
    }
  }
});
</script>

<?php panel_footer(); ?>

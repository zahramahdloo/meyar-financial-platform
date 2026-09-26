<?php
/** MEYAR — صفحه اختصاصی هر ارز/سکه: سئو + اسکیما + نمودار شمسی */
require_once __DIR__ . '/inc/theme.php';

$id = preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['id'] ?? ''));
$item = $id ? meyar_item_by_id($id) : null;
if (!$item) {
    http_response_code(404);
    meyar_theme_head('یافت نشد — سکه معیار');
    echo '<div class="container" style="padding:80px 20px;text-align:center"><h1>۴۰۴</h1><p>این صفحه پیدا نشد.</p><a class="btn btn-gold" href="' . meyar_base() . '">بازگشت به صفحه اصلی</a></div></body></html>';
    exit;
}

meyar_track('/price/' . $id);

$settings = meyar_load_settings();
$data     = meyar_build_prices();
$groups   = meyar_groups();
$cur      = null;
foreach ($data['items'] as $i) { if ($i['id'] === $id) { $cur = $i; break; } }

$canonical = meyar_public_url_for('price/' . rawurlencode($id));

$title = $item['seo_title'] !== '' ? $item['seo_title']
       : 'قیمت لحظه‌ای ' . $item['title'] . ' امروز | سکه و جواهر معیار';
$desc  = $item['seo_desc'] !== '' ? $item['seo_desc']
       : 'قیمت زنده ' . $item['title'] . ' به همراه نمودار تاریخچه قیمت با تاریخ شمسی، تغییرات روزانه و تحلیل بازار در بورس سکه معیار.';

// ---- اسکیمای گوگل ----
if ($item['schema_json'] !== '') {
    $schema = $item['schema_json']; // اسکیمای سفارشی ادمین
} else {
    $schemaArr = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $item['title'],
        'description' => $desc,
        'url'         => $canonical,
        'brand'       => ['@type' => 'Organization', 'name' => 'سکه و جواهر معیار'],
    ];
    if ($cur) {
        $schemaArr['offers'] = [
            '@type'         => 'Offer',
            'price'         => round($cur['sell']),
            'priceCurrency' => $cur['unit'] === 'دلار' ? 'USD' : 'IRR',
            'availability'  => 'https://schema.org/InStock',
            'url'           => $canonical,
        ];
    }
    $schema = json_encode($schemaArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
$categoryUrl = meyar_public_url_for('prices.php');
$breadcrumb = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type'=>'ListItem','position'=>1,'name'=>'صفحه اصلی','item'=>meyar_public_url_for('')],
        ['@type'=>'ListItem','position'=>2,'name'=>$groups[$item['group']] ?? 'قیمت‌ها','item'=>$categoryUrl],
        ['@type'=>'ListItem','position'=>3,'name'=>$item['title'],'item'=>$canonical],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$hasChart = in_array($item['source'][0], ['tgju', 'tgju_usd', 'parsian'], true);

$extraHead = '<script type="application/ld+json">' . $schema . '</script>'
           . '<script type="application/ld+json">' . $breadcrumb . '</script>';

meyar_theme_head($title, $desc, $canonical, $extraHead);

// تیکر
$tickerIds = ['sekee','sekeb','nim','rob','gerami','geram18','silver999','usd','eur','ons'];
$ticker = array_values(array_filter($data['items'], function ($i) use ($tickerIds) {
    return in_array($i['id'], $tickerIds, true) && empty($i['hidden']);
}));
meyar_theme_topbar($settings, $ticker);

// آیتم‌های مرتبط (هم‌گروه)
$related = array_values(array_filter($data['items'], function ($i) use ($item) {
    return $i['group'] === $item['group'] && $i['id'] !== $item['id'] && empty($i['hidden']);
}));
$related = array_slice($related, 0, 8);
?>

<main class="container item-page">
  <nav class="breadcrumbs reveal" data-reveal="up" aria-label="breadcrumb">
    <a href="<?= meyar_base() ?>">صفحه اصلی</a> <span>›</span>
    <a href="<?= meyar_base() ?>#prices"><?= meyar_h($groups[$item['group']] ?? 'قیمت‌ها') ?></a> <span>›</span>
    <b><?= meyar_h($item['title']) ?></b>
  </nav>

  <header class="item-hero reveal" data-reveal="up">
    <div class="item-hero-right">
      <?php if ($item['icon'] === 'coin' || $item['icon'] === 'gold'): ?>
        <span class="mini-coin big <?= $item['icon'] ?>"></span>
      <?php else: ?>
        <span class="mini-flag big"><?= $item['icon'] ?></span>
      <?php endif; ?>
      <div>
        <h1>قیمت <?= meyar_h($item['title']) ?></h1>
        <div class="item-hero-sub">
          آخرین به‌روزرسانی: <b data-head-time><?= meyar_h($data['updated']) ?></b>
          <?php if ($data['stale']): ?><span class="stale-badge">از آخرین کش</span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php if ($cur): ?>
    <div class="item-hero-price" data-item-price="<?= meyar_h($item['id']) ?>">
      <div class="item-price-main"><span data-cell="live"><?= meyar_h($cur['live_fmt']) ?></span> <small><?= meyar_h($cur['unit']) ?></small></div>
      <span class="chg <?= $cur['dir'] === 'high' ? 'up' : ($cur['dir'] === 'low' ? 'down' : 'flat') ?>" data-cell="chg">
        <?php if ($cur['dir'] === 'high'): ?><i class="hgi-stroke hgi-arrow-up-01" aria-hidden="true"></i><?php elseif ($cur['dir'] === 'low'): ?><i class="hgi-stroke hgi-arrow-down-01" aria-hidden="true"></i><?php else: ?>–<?php endif; ?> <?= meyar_h($cur['change_pct']) ?>٪
      </span>
    </div>
    <?php endif; ?>
  </header>

  <?php if ($cur): ?>
  <div class="item-cards reveal" data-reveal="up">
    <div class="item-card"><span class="item-card-label">قیمت فروش</span><b class="num" data-cell="sell"><?= meyar_h($cur['sell_fmt']) ?></b></div>
    <div class="item-card"><span class="item-card-label">قیمت خرید</span><b class="num" data-cell="buy"><?= meyar_h($cur['buy_fmt']) ?></b></div>
    <div class="item-card"><span class="item-card-label">واحد</span><b><?= meyar_h($cur['unit']) ?></b></div>
    <div class="item-card"><span class="item-card-label">زمان قیمت منبع</span><b class="num"><?= meyar_h($cur['time'] ?: '—') ?></b></div>
  </div>
  <?php endif; ?>

  <?php if ($item['page_desc'] !== ''): ?>
  <section class="item-desc reveal" data-reveal="up">
    <?= nl2br(meyar_h($item['page_desc'])) ?>
  </section>
  <?php endif; ?>

  <?php if ($hasChart): ?>
  <section class="chart-card reveal" data-reveal="up">
    <div class="chart-head">
      <h2>نمودار قیمت <?= meyar_h($item['title']) ?> <small>(تاریخ شمسی)</small></h2>
      <div class="chart-filters" id="chartFilters">
        <button type="button" data-days="30" aria-pressed="false">۱ ماه</button>
        <button type="button" data-days="90" aria-pressed="false">۳ ماه</button>
        <button type="button" data-days="180" aria-pressed="false">۶ ماه</button>
        <button type="button" data-days="365" class="active" aria-pressed="true">۱ سال</button>
        <button type="button" data-days="1095" aria-pressed="false">۳ سال</button>
        <button type="button" data-days="4000" aria-pressed="false">همه</button>
      </div>
    </div>
    <div class="chart-stats" id="chartStats"></div>
    <div class="chart-wrap"><canvas id="priceChart" role="img" aria-label="نمودار قیمت <?= meyar_h($item['title']) ?>"></canvas><div class="chart-loading" id="chartLoading">در حال بارگذاری نمودار…</div></div>
  </section>
  <?php else: ?>
  <section class="chart-card reveal" data-reveal="up">
    <p style="padding:30px;text-align:center;color:var(--ink-soft)">این آیتم قیمت دستی دارد و نمودار تاریخچه برای آن موجود نیست.</p>
  </section>
  <?php endif; ?>

  <?php if ($related): ?>
  <section class="related reveal" data-reveal="up">
    <h2 class="related-title">قیمت‌های مرتبط</h2>
    <div class="related-grid">
      <?php foreach ($related as $r): ?>
      <a class="related-item" href="<?= meyar_base() ?>price/<?= meyar_h($r['id']) ?>">
        <?php if ($r['icon'] === 'coin' || $r['icon'] === 'gold'): ?>
          <span class="mini-coin <?= $r['icon'] ?>"></span>
        <?php else: ?>
          <span class="mini-flag"><?= $r['icon'] ?></span>
        <?php endif; ?>
        <span class="related-name"><?= meyar_h($r['title']) ?></span>
        <b class="num"><?= meyar_h($r['live_fmt']) ?></b>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php if ($hasChart): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
  var ctx = document.getElementById('priceChart');
  var loading = document.getElementById('chartLoading');
  var statsEl = document.getElementById('chartStats');
  var chart = null;
  var faD = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  function faNum(s){return String(s).replace(/\d/g,function(d){return faD[+d];});}
  function fmt(n){return faNum(Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g,','));}

  var currentDays = 365, retried = false, historyRequestSeq = 0;
  function showErr(msg) {
    loading.style.display = 'flex';
    loading.innerHTML = msg + ' <button type="button" class="chart-retry" id="chartRetry">تلاش مجدد</button>';
    var rb = document.getElementById('chartRetry');
    if (rb) rb.addEventListener('click', function () { retried = false; load(currentDays); });
  }
  function load(days) {
    currentDays = days;
    var requestSeq = ++historyRequestSeq;
    loading.style.display = 'flex';
    loading.textContent = 'در حال بارگذاری نمودار…';
    fetch(window.MEYAR_BASE + 'api/history.php?id=<?= meyar_h($item['id']) ?>&days=' + days, {cache:'no-store'})
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (requestSeq !== historyRequestSeq) return;
        loading.style.display = 'none';
        if (!j.ok || !j.points || !j.points.length) {
          if (!retried) { retried = true; setTimeout(function () { if (requestSeq === historyRequestSeq) load(days); }, 2500); loading.style.display='flex'; loading.textContent='در حال تلاش دوباره…'; return; }
          showErr('داده‌ای برای این بازه در دسترس نیست.');
          return;
        }
        retried = false;
        var labels = j.points.map(function (p) { return p.j; });
        var values = j.points.map(function (p) { return p.v; });

        var min = Math.min.apply(null, values), max = Math.max.apply(null, values);
        var first = values[0], last = values[values.length - 1];
        var chgP = first ? ((last - first) / first * 100) : 0;
        statsEl.innerHTML =
          '<span>کمترین: <b>' + fmt(min) + '</b></span>' +
          '<span>بیشترین: <b>' + fmt(max) + '</b></span>' +
          '<span>تغییر بازه: <b class="' + (chgP >= 0 ? 'txt-up' : 'txt-down') + '">' +
          (chgP >= 0 ? '▲' : '▼') + ' ' + faNum(Math.abs(chgP).toFixed(1)) + '٪</b></span>';

        if (chart) chart.destroy();
        var g = ctx.getContext('2d');
        var grad = g.createLinearGradient(0, 0, 0, 320);
        grad.addColorStop(0, 'rgba(212,164,55,.35)');
        grad.addColorStop(1, 'rgba(212,164,55,0)');
        chart = new Chart(ctx, {
          type: 'line',
          data: { labels: labels, datasets: [{
            data: values,
            borderColor: '#c79a2e',
            backgroundColor: grad,
            fill: true,
            borderWidth: 2,
            pointRadius: 0,
            pointHitRadius: 12,
            tension: .25
          }]},
          options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: { display: false },
              tooltip: {
                rtl: true, titleFont: {family: 'IRANSansXFaNum'}, bodyFont: {family: 'IRANSansXFaNum'},
                callbacks: {
                  title: function (t) { return t[0].label; },
                  label: function (t) { return ' ' + fmt(t.parsed.y) + ' <?= $cur && $cur['unit'] === 'دلار' ? 'دلار' : 'تومان' ?>'; }
                }
              }
            },
            scales: {
              x: {
                reverse: false,
                ticks: { font: {family:'IRANSansXFaNum', size: 11}, maxTicksLimit: 9, color: '#8a8fa3' },
                grid: { display: false }
              },
              y: {
                position: 'left',
                ticks: {
                  font: {family:'IRANSansXFaNum', size: 11}, color: '#8a8fa3',
                  callback: function (v) { return fmt(v); }
                },
                grid: { color: 'rgba(0,0,0,.05)' }
              }
            }
          }
        });
      })
      .catch(function () {
        if (requestSeq !== historyRequestSeq) return;
        if (!retried) { retried = true; setTimeout(function () { if (requestSeq === historyRequestSeq) load(days); }, 2500); loading.textContent = 'در حال تلاش دوباره…'; return; }
        showErr('خطا در دریافت داده نمودار.');
      });
  }

  document.querySelectorAll('#chartFilters button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('#chartFilters button').forEach(function (x) {
        x.classList.remove('active');
        x.setAttribute('aria-pressed', 'false');
      });
      b.classList.add('active');
      b.setAttribute('aria-pressed', 'true');
      load(+b.dataset.days);
    });
  });
  load(365);
})();
</script>
<?php endif; ?>

<?php meyar_theme_footer($settings); ?>

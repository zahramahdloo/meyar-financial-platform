<?php
require __DIR__ . '/cz-guard.php';

require_once __DIR__ . '/inc/theme.php';
meyar_track('/');

$settings = meyar_load_settings();
$data     = meyar_build_prices();
$items    = array_values(array_filter($data['items'], function ($i) { return empty($i['hidden']); }));
$groups   = meyar_groups();
$byGroup  = ['coins'=>[], 'parsian'=>[], 'gold'=>[], 'currency'=>[]];
foreach ($items as $i) { if (isset($byGroup[$i['group']])) $byGroup[$i['group']][] = $i; }

// چیدمان ماژولار جدول‌ها (پنل ادمین → تب چیدمان)
$layout = (array)($settings['layout'] ?? []);
if (!$layout) {
    $layout = [['group'=>'coins','width'=>'half'],['group'=>'currency','width'=>'half'],
               ['group'=>'parsian','width'=>'half'],['group'=>'gold','width'=>'half']];
}

// تیکر
$tickerIds = ['sekee','sekeb','nim','rob','gerami','geram18','usd','eur','ons'];
$ticker = array_values(array_filter($items, function ($i) use ($tickerIds) { return in_array($i['id'], $tickerIds, true); }));

$heroImg  = is_file(__DIR__.'/assets/img/hero.png') ? 'assets/img/hero.png' : '';
$aboutImg = is_file(__DIR__.'/assets/img/about.png') ? 'assets/img/about.png' : '';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$orgSchema = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'JewelryStore',
    'name'     => 'سکه و جواهر معیار',
    'url'      => $scheme . '://' . $host . '/',
    'telephone'=> $settings['site_phone'],
    'email'    => $settings['site_email'],
    'address'  => ['@type'=>'PostalAddress','addressLocality'=>'تهران','streetAddress'=>'بازار بزرگ تهران، سبزه میدان، پاساژ خادم، طبقه همکف، پلاک ۴'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

meyar_theme_head(
    'سکه و جواهر معیار | قیمت لحظه‌ای سکه، طلا و ارز',
    'بورس سکه معیار — قیمت لحظه‌ای سکه امامی، بهار آزادی، نیم سکه، ربع سکه، طلای ۱۸ عیار و ارز با نمودار تاریخچه شمسی. خرید و فروش سکه در بازار بزرگ تهران.',
    $scheme . '://' . $host . '/',
    '<script type="application/ld+json">' . $orgSchema . '</script>'
);
meyar_theme_topbar($settings, $ticker);
?>

<!-- ═══ هیرو: معرفی معیار و وضعیت بازار ═══ -->
<?php
$hsIds = ['geram18', 'usd', 'silver999'];
$hsItems = [];
foreach ($items as $i) { if (in_array($i['id'], $hsIds, true)) $hsItems[$i['id']] = $i; }
$marketHistory = [];
$historySources = [
    'geram18' => MEYAR_DATA . '/history_geram18.json',
    'usd' => MEYAR_DATA . '/history_price_dollar_rl.json',
];
foreach ($historySources as $marketId => $historyFile) {
    $historyJson = is_file($historyFile) ? json_decode((string)@file_get_contents($historyFile), true) : null;
    $historyRows = is_array($historyJson) ? (array)($historyJson['rows'] ?? []) : [];
    $marketHistory[$marketId] = array_values(array_filter(array_map(function ($row) {
        return ['g' => (string)($row['g'] ?? ''), 'v' => (float)($row['v'] ?? 0)];
    }, array_slice($historyRows, -90)), function ($row) { return $row['g'] !== '' && $row['v'] > 0; }));
}
$localHistory = is_file(MEYAR_DATA . '/local_history.json') ? json_decode((string)@file_get_contents(MEYAR_DATA . '/local_history.json'), true) : null;
$silverRows = is_array($localHistory) ? (array)($localHistory['silver_999'] ?? []) : [];
ksort($silverRows);
$marketHistory['silver999'] = [];
foreach (array_slice($silverRows, -90, null, true) as $g => $value) {
    if ((float)$value > 0) $marketHistory['silver999'][] = ['g' => (string)$g, 'v' => (float)$value / 10];
}
?>
<section class="hero hero-static" aria-labelledby="heroTitle">
  <div class="hero-overlay"></div>
  <div class="hero-grid-glow" aria-hidden="true"></div>
  <div class="hero-glow"></div>
  <div class="container hero-content hero-static-content">
    <div class="hero-text">
      <div class="hero-badge"><span class="online-dot"></span> مرجع معتبر بازار طلا و سکه</div>
      <h1 class="hero-title" id="heroTitle">خرید و فروش مطمئن<br><span class="gold-text">سکه و طلا</span></h1>
      <p class="hero-desc">قیمت لحظه‌ای بازار، مشاوره تخصصی و خرید و فروش حضوری با اعتماد و شفافیت</p>
      <div class="hero-actions">
        <a href="#prices" class="btn btn-gold">مشاهده قیمت‌های لحظه‌ای</a>
        <a href="#contact" class="btn btn-outline">مشاوره خرید</a>
      </div>
      <div class="hero-trust" aria-label="مزیت‌های معیار">
        <span>✓ بیش از دو دهه سابقه فعالیت</span>
        <span>✓ خرید و فروش حضوری</span>
        <span>✓ قیمت‌گذاری شفاف</span>
      </div>
    </div>

    <aside class="market-dashboard" aria-label="وضعیت بازار">
      <div class="dashboard-head">
        <div>
          <h2>وضعیت بازار</h2>
          <span class="dashboard-time">بروزرسانی: <?= meyar_h(date('H:i')) ?></span>
        </div>
      </div>
      <div class="market-chart" aria-label="روند واقعی قیمت بازار">
        <svg id="marketChart" viewBox="0 0 520 82" preserveAspectRatio="none" role="img" aria-label="نمودار روند قیمت"><defs><linearGradient id="marketFill" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#c9a227"/><stop offset="1" stop-color="#c9a227" stop-opacity="0"/></linearGradient></defs><path class="market-chart-area" fill="url(#marketFill)" opacity=".16"></path><path class="market-chart-line" fill="none" stroke="#c9a227" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      </div>
      <div class="market-chart-controls" role="tablist" aria-label="انتخاب روند بازار">
        <button type="button" class="active" data-chart-market="geram18" role="tab" aria-selected="true">طلا</button>
        <button type="button" data-chart-market="usd" role="tab" aria-selected="false">دلار</button>
        <button type="button" data-chart-market="silver999" role="tab" aria-selected="false">نقره</button>
      </div>
      <div class="market-cards">
        <?php foreach ($hsIds as $pid): if (empty($hsItems[$pid])) continue; $p = $hsItems[$pid]; ?>
        <a class="market-card" href="price/<?= meyar_h($pid) ?>" data-id="<?= meyar_h($pid) ?>">
          <span class="market-card-name"><?= meyar_h($p['title']) ?></span>
          <strong data-cell="live"><?= meyar_h($p['live_fmt']) ?> <small><?= meyar_h($p['unit']) ?></small></strong>
          <span class="market-card-change <?= $p['dir']==='high'?'up':($p['dir']==='low'?'down':'') ?>" data-cell="chg"><?= $p['dir']==='high'?'▲':($p['dir']==='low'?'▼':'–') ?> <?= meyar_h($p['change_pct']) ?>٪</span>
        </a>
        <?php endforeach; ?>
      </div>
      <script type="application/json" id="marketHistoryData"><?= json_encode($marketHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    </aside>
  </div>
</section>

<!-- ═══ جداول قیمت ═══ -->
<main class="container" id="prices">
  <div class="tables-grid">
    <?php foreach ($layout as $slot):
        $gid = $slot['group'] ?? '';
        $width = ($slot['width'] ?? 'half') === 'full' ? 'w-full' : 'w-half';
        if (empty($byGroup[$gid])) continue; ?>
    <section class="price-card <?= $width ?> reveal" data-reveal="up">
      <header class="price-card-head">
        <h2><?= meyar_h($groups[$gid]) ?></h2>
        <span class="head-time" data-head-time><?= meyar_h($data['updated']) ?></span>
      </header>
      <div class="table-wrap">
        <table class="price-table">
          <thead>
            <tr><th>عنوان</th><th>زنده</th><th>خرید</th><th>فروش</th><th>تغییرات</th><th></th></tr>
          </thead>
          <tbody data-group="<?= $gid ?>">
            <?php foreach ($byGroup[$gid] as $i): ?>
            <tr data-id="<?= meyar_h($i['id']) ?>" class="row-link" data-href="price/<?= meyar_h($i['id']) ?>">
              <td class="cell-title">
                <a href="price/<?= meyar_h($i['id']) ?>" class="item-link">
                <?php if ($i['icon'] === 'coin' || $i['icon'] === 'gold'): ?>
                  <span class="mini-coin <?= $i['icon'] ?>"></span>
                <?php else: ?>
                  <span class="mini-flag"><?= $i['icon'] ?></span>
                <?php endif; ?>
                <?= meyar_h($i['title']) ?>
                </a>
              </td>
              <td class="cell-num" data-cell="live"><?= meyar_h($i['live_fmt']) ?></td>
              <td class="cell-num" data-cell="buy"><?= meyar_h($i['buy_fmt']) ?></td>
              <td class="cell-num" data-cell="sell"><?= meyar_h($i['sell_fmt']) ?></td>
              <td>
                <span class="chg <?= $i['dir'] === 'high' ? 'up' : ($i['dir'] === 'low' ? 'down' : 'flat') ?>" data-cell="chg">
                  <?= $i['dir'] === 'high' ? '▲' : ($i['dir'] === 'low' ? '▼' : '–') ?>
                  <?= meyar_h($i['change_pct']) ?>٪
                </span>
              </td>
              <td class="cell-chart"><a href="price/<?= meyar_h($i['id']) ?>" title="نمودار <?= meyar_h($i['title']) ?>" class="chart-link">📈</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endforeach; ?>
  </div>
</main>

<!-- ═══ چرا سکه معیار ═══ -->
<section class="why" id="why">
  <div class="container">
    <h2 class="section-title reveal" data-reveal="up">چرا <span class="gold-text">سکه معیار</span>؟</h2>
    <p class="section-sub reveal" data-reveal="up">چهار دلیل برای اینکه معیار، معیارِ شماست</p>
    <div class="why-grid">
      <div class="why-card reveal" data-reveal="up">
        <div class="why-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></div>
        <h3>مجوز رسمی اتحادیه</h3>
        <p>دارای گواهی و مجوزهای رسمی از اتحادیه طلا، جواهر و سکه — معامله در محیطی کاملاً قانونی و مطمئن.</p>
      </div>
      <div class="why-card reveal" data-reveal="up">
        <div class="why-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/></svg></div>
        <h3>نزدیک به دو دهه سابقه</h3>
        <p>فعال از سال ۱۳۸۵ در قلب بازار بزرگ تهران؛ نامی شناخته‌شده و معتبر در بازار مسکوکات کشور.</p>
      </div>
      <div class="why-card reveal" data-reveal="up">
        <div class="why-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></div>
        <h3>قیمت لحظه‌ای و شفاف</h3>
        <p>قیمت‌ها به‌صورت خودکار و لحظه‌ای از منابع معتبر بازار به‌روزرسانی می‌شوند — بدون ابهام، بدون واسطه.</p>
      </div>
      <div class="why-card reveal" data-reveal="up">
        <div class="why-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0L12 5.36l-.77-.78a5.4 5.4 0 0 0-7.65 7.65l.77.78L12 20.66l7.65-7.65.77-.78a5.4 5.4 0 0 0 0-7.65z"/></svg></div>
        <h3>اعتماد مشتریان</h3>
        <p>مهم‌ترین سرمایه ما اعتماد شماست؛ نیروهای امین و مجرب پاسخ‌گوی نیاز شما در خرید و فروش هستند.</p>
      </div>
    </div>
  </div>
</section>

<!-- ═══ درباره ما ═══ -->
<section class="about" id="about">
  <div class="container">
    <h2 class="section-title reveal" data-reveal="up">بیشتر با <span class="gold-text">مجموعه معیار</span> آشنا شوید</h2>
    <p class="section-sub reveal" data-reveal="up">سابقه، اهداف و افتخارات سکه معیار</p>
    <div class="about-grid">
      <div class="about-text reveal" data-reveal="right">
        <h3>سابقه و اهداف سکه معیار</h3>
        <p>
          سکه معیار به عنوان یکی از واحدهای صنفی پیشرو در زمینه خرید و فروش مسکوکات طلا در کشور از سال ۱۳۸۵
          در بازار بزرگ تهران (سبزه میدان) فعالیت خود را آغاز نموده و در طول سالیان گذشته با اخذ گواهی و مجوزهای لازم
          (اتحادیه طلا، جواهر و سکه) و جذب نیروهای امین، متعهد و مجرب توانسته جایگاه ویژه‌ای در بازار مسکوکات کشور کسب نماید.
        </p>
        <p>
          این مجموعه در راستای ارتقاء سطح دانش پرسنل خود همگام با استانداردهای کشوری، موفق به گذراندن دوره‌های
          آموزشی مرتبط و اخذ گواهی‌نامه‌های معتبر گردیده است و تلاش دارد تا پاسخ شایسته‌ای به مهم‌ترین سرمایه خود
          — اعتماد مشتریان — بدهد.
        </p>
        <div class="about-stats">
          <div class="stat"><span class="stat-num" data-count="1385">۰</span><span class="stat-label">فعال از سال</span></div>
          <div class="stat"><span class="stat-num" data-count="20">۰</span><span class="stat-label">سال تجربه +</span></div>
          <div class="stat"><span class="stat-num" data-count="100">۰</span><span class="stat-label">٪ اعتماد مشتری</span></div>
        </div>
      </div>
      <div class="about-media reveal" data-reveal="left">
        <?php if ($aboutImg): ?>
          <img src="<?= $aboutImg ?>" alt="سکه و جواهر معیار" loading="lazy">
        <?php else: ?>
          <div class="about-media-ph"><img src="assets/img/logo.svg" alt="MEYAR"></div>
        <?php endif; ?>
        <div class="about-media-frame"></div>
      </div>
    </div>
  </div>
</section>

<?php meyar_theme_footer($settings); ?>

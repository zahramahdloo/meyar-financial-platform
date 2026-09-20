<?php
require __DIR__ . '/cz-guard.php';

require_once __DIR__ . '/inc/theme.php';
meyar_track('/');

$settings = meyar_load_settings();
$data     = meyar_build_prices();
$items    = array_values(array_filter($data['items'], function ($i) { return empty($i['hidden']); }));

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
$hsIds = ['sekee', 'geram18', 'usd', 'silver999'];
$hsItems = [];
foreach ($items as $i) { if (in_array($i['id'], $hsIds, true)) $hsItems[$i['id']] = $i; }
$marketHistory = [];
$historySources = [
    'sekee' => MEYAR_DATA . '/history_sekee.json',
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
$overviewIds = ['geram18', 'usd', 'sekee', 'silver999'];
$overviewLabels = [
    'geram18'   => 'طلا ۱۸ عیار',
    'usd'       => 'دلار آمریکا',
    'sekee'     => 'سکه امامی',
    'silver999' => 'نقره ۹۹۹.۹',
];
$overviewItems = [];
foreach ($overviewIds as $overviewId) {
    foreach ($items as $item) {
        if ($item['id'] !== $overviewId) continue;
        $rows = $marketHistory[$overviewId] ?? [];
        $values = array_values(array_filter(array_map(function ($row) { return (float)($row['v'] ?? 0); }, $rows), function ($value) { return $value > 0; }));
        $latest = count($values) ? $values[count($values) - 1] : (float)$item['live'];
        $previous = count($values) > 1 ? $values[count($values) - 2] : $latest;
        $dailyDiff = $latest - $previous;
        $overviewItems[$overviewId] = [
            'id'        => $overviewId,
            'name'      => $overviewLabels[$overviewId],
            'item'      => $item,
            'values'    => $values,
            'high'      => count($values) ? max($values) : $latest,
            'low'       => count($values) ? min($values) : $latest,
            'dailyDiff' => $dailyDiff,
        ];
        break;
    }
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
        <span><i class="hgi-stroke hgi-checkmark-circle-02" aria-hidden="true"></i> بیش از دو دهه سابقه فعالیت</span>
        <span><i class="hgi-stroke hgi-checkmark-circle-02" aria-hidden="true"></i> خرید و فروش حضوری</span>
        <span><i class="hgi-stroke hgi-checkmark-circle-02" aria-hidden="true"></i> قیمت‌گذاری شفاف</span>
      </div>
    </div>

    <aside class="market-dashboard market-insight-card" aria-label="تحلیل هوشمند بازار">
      <header class="market-insight-head">
        <div class="market-insight-heading">
          <span class="market-insight-icon"><i class="hgi hgi-stroke hgi-rounded hgi-magic-wand-01" aria-hidden="true"></i></span>
          <div>
            <div class="market-insight-title-row"><h2>تحلیل هوشمند بازار</h2><span class="market-insight-ai">AI</span></div>
            <p>جمع‌بندی هوشمند بازار با هوش مصنوعی معیار</p>
          </div>
        </div>
        <span class="market-insight-date"><i class="hgi hgi-stroke hgi-rounded hgi-calendar-03" aria-hidden="true"></i><?= meyar_h($data['updated_date'] ?? 'امروز') ?></span>
      </header>
      <div class="market-insight-body">
        <section class="market-insight-chart-panel" aria-label="روند امروز طلا">
          <div class="market-insight-chart-label"><span>روند امروز</span><b data-insight-trend>صعودی</b></div>
          <div class="market-chart market-insight-chart" aria-label="نمودار روند واقعی طلای ۱۸ عیار">
            <svg id="marketChart" viewBox="0 0 520 120" preserveAspectRatio="none" role="img" aria-label="نمودار روند طلای ۱۸ عیار"><defs><linearGradient id="marketFill" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#d4af37"/><stop offset="1" stop-color="#d4af37" stop-opacity="0"/></linearGradient></defs><path class="market-chart-area" fill="url(#marketFill)" opacity=".18"></path><path class="market-chart-line" fill="none" stroke="#d4af37" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
          </div>
          <?php $goldInsight = $hsItems['geram18'] ?? null; ?>
          <?php if ($goldInsight): ?>
          <a class="market-insight-asset" href="price/geram18" data-id="geram18">
            <span class="market-insight-asset-icon"><i class="hgi hgi-stroke hgi-rounded hgi-gold-ingots" aria-hidden="true"></i></span>
            <span><b>طلا ۱۸ عیار</b><small>دارایی منتخب بازار</small></span>
            <strong class="up" data-cell="insight-change">+<?= meyar_h($goldInsight['change_pct']) ?>٪</strong>
          </a>
          <?php endif; ?>
        </section>
        <section class="market-insight-list" aria-labelledby="marketInsightPoints">
          <h3 id="marketInsightPoints"><i class="hgi hgi-stroke hgi-rounded hgi-note-01" aria-hidden="true"></i> نکات مهم امروز</h3>
          <ul data-market-insights>
            <li>افزایش تقاضای جهانی طلا</li>
            <li>تاثیر نوسانات نرخ ارز</li>
            <li>روند مثبت اونس جهانی</li>
            <li>حفظ حمایت کلیدی در بازار داخلی</li>
          </ul>
        </section>
      </div>
      <script type="application/json" id="marketHistoryData"><?= json_encode($marketHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    </aside>
  </div>
</section>

<!-- ═══ نمای کلی بازار ═══ -->
<main class="container" id="prices">
  <section class="market-overview" aria-labelledby="marketOverviewTitle">
    <header class="market-overview-head">
      <div class="market-overview-updated">
        <div class="market-overview-updated-row">
          <span>آخرین به‌روزرسانی:</span>
        </div>
        <strong id="marketOverviewUpdated">
          <span id="marketOverviewDate"><?= meyar_h($data['updated_date'] ?? '—') ?></span>
          <span aria-hidden="true"> - </span>
          <span id="marketOverviewTime"><?= meyar_h($data['fetched_at'] ? meyar_fa_num(date('H:i', $data['fetched_at'])) : '—') ?></span>
        </strong>
      </div>
      <div class="market-overview-heading">
        <h2 id="marketOverviewTitle">وضعیت بازار امروز</h2>
        <p>آخرین قیمت‌ها، تغییرات و روند بازار</p>
      </div>
      <a class="market-overview-all" href="<?= meyar_base() ?>prices.php">مشاهده کامل بازار <i class="hgi-stroke hgi-arrow-left-01" aria-hidden="true"></i></a>
    </header>
    <div class="market-overview-grid">
      <?php foreach ($overviewItems as $overview):
          $i = $overview['item'];
          $dir = $i['dir'] === 'high' ? 'up' : ($i['dir'] === 'low' ? 'down' : 'flat');
          $decimals = $i['unit'] === 'دلار' ? 2 : 0;
          $diff = $overview['dailyDiff'];
          $diffText = $diff == 0 ? '—' : (($diff > 0 ? '+' : '−') . meyar_fmt(abs($diff), $decimals));
          $gradientId = 'overviewGradient_' . $overview['id'];
      ?>
      <article class="market-overview-card" data-overview-card="<?= meyar_h($overview['id']) ?>">
        <header class="market-overview-card-head">
          <span class="market-overview-icon <?= meyar_h($i['icon']) ?>" aria-hidden="true"><i class="hgi hgi-stroke hgi-rounded <?= $i['group'] === 'currency' ? 'hgi-cash-02' : ($i['group'] === 'gold' ? 'hgi-gold-ingots' : 'hgi-coins-01') ?>"></i></span>
          <div class="market-overview-title">
            <h3><?= meyar_h($overview['name']) ?></h3>
          </div>
        </header>
        <div class="market-overview-price"><strong><?= meyar_h($i['live_fmt']) ?></strong><span><?= meyar_h($i['unit']) ?></span></div>
        <div class="market-overview-change <?= $dir ?>">
          <strong><?= $dir === 'up' ? '+' : ($dir === 'down' ? '−' : '') ?><?= meyar_h($i['change_pct']) ?>٪</strong>
          <span><?= meyar_h($diffText) ?></span>
        </div>
        <div class="market-overview-chart-wrap">
          <svg class="market-overview-chart <?= $dir ?>" data-overview-chart="<?= meyar_h($overview['id']) ?>" viewBox="0 0 320 76" preserveAspectRatio="none" role="img" aria-label="نمودار <?= meyar_h($overview['name']) ?>">
            <defs><linearGradient id="<?= meyar_h($gradientId) ?>" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-opacity=".22"/><stop offset="1" stop-opacity="0"/></linearGradient></defs>
            <path class="market-overview-area" fill="url(#<?= meyar_h($gradientId) ?>)"></path>
            <path class="market-overview-line" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path>
          </svg>
        </div>
        <div class="market-overview-ranges" role="tablist" aria-label="بازه نمودار <?= meyar_h($overview['name']) ?>">
          <button type="button" class="active" data-overview-range="day" role="tab" aria-selected="true">روز</button>
          <button type="button" data-overview-range="week" role="tab" aria-selected="false">هفته</button>
          <button type="button" data-overview-range="month" role="tab" aria-selected="false">ماه</button>
        </div>
        <dl class="market-overview-details">
          <div><dt>بالاترین امروز</dt><dd><?= meyar_h(meyar_fmt($overview['high'], $decimals)) ?></dd></div>
          <div><dt>پایین‌ترین امروز</dt><dd><?= meyar_h(meyar_fmt($overview['low'], $decimals)) ?></dd></div>
        </dl>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

</main>

<!-- ═══ چرا سکه معیار ═══ -->
<section class="why" id="why">
  <div class="container">
    <h2 class="section-title reveal" data-reveal="up">چرا <span class="gold-text">سکه معیار</span>؟</h2>
    <p class="section-sub reveal" data-reveal="up">چهار دلیل برای اینکه معیار، معیارِ شماست</p>
    <div class="why-grid">
      <div class="why-card reveal" data-reveal="up">
        <div class="why-icon"><i class="hgi-stroke hgi-shield-check" aria-hidden="true"></i></div>
        <h3>مجوز رسمی اتحادیه</h3>
        <p>دارای گواهی و مجوزهای رسمی از اتحادیه طلا، جواهر و سکه — معامله در محیطی کاملاً قانونی و مطمئن.</p>
      </div>
      <div class="why-card reveal" data-reveal="up">
        <div class="why-icon"><i class="hgi-stroke hgi-medal-02" aria-hidden="true"></i></div>
        <h3>نزدیک به دو دهه سابقه</h3>
        <p>فعال از سال ۱۳۸۵ در قلب بازار بزرگ تهران؛ نامی شناخته‌شده و معتبر در بازار مسکوکات کشور.</p>
      </div>
      <div class="why-card reveal" data-reveal="up">
        <div class="why-icon"><i class="hgi-stroke hgi-chart-line-data-02" aria-hidden="true"></i></div>
        <h3>قیمت لحظه‌ای و شفاف</h3>
        <p>قیمت‌ها به‌صورت خودکار و لحظه‌ای از منابع معتبر بازار به‌روزرسانی می‌شوند — بدون ابهام، بدون واسطه.</p>
      </div>
      <div class="why-card reveal" data-reveal="up">
        <div class="why-icon"><i class="hgi-stroke hgi-favourite" aria-hidden="true"></i></div>
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
          <div class="about-media-ph"><img src="assets/img/meyar-logo/meyar-logo.svg" alt="MEYAR"></div>
        <?php endif; ?>
        <div class="about-media-frame"></div>
      </div>
    </div>
  </div>
</section>

<?php meyar_theme_footer($settings); ?>

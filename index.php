<?php
require __DIR__ . '/cz-guard.php';

require_once __DIR__ . '/inc/theme.php';
meyar_track('/');

$settings = meyar_load_settings();
$data     = meyar_build_prices();
$items    = array_values(array_filter($data['items'], function ($i) {
  return empty($i['hidden']);
}));

// تیکر
$tickerIds = ['sekee', 'sekeb', 'nim', 'rob', 'gerami', 'geram18', 'silver999', 'usd', 'eur', 'ons'];
$ticker = array_values(array_filter($items, function ($i) use ($tickerIds) {
  return in_array($i['id'], $tickerIds, true);
}));

$heroImg  = is_file(__DIR__ . '/assets/img/hero.png') ? 'assets/img/hero.png' : '';
$aboutImg = is_file(__DIR__ . '/assets/img/about.png') ? 'assets/img/about.png' : '';
$aboutBanner = is_file(__DIR__ . '/assets/img/about-meyar-banner.png') ? 'assets/img/about-meyar-banner.png' : '';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$orgSchema = json_encode([
  '@context' => 'https://schema.org',
  '@type'    => 'JewelryStore',
  'name'     => 'سکه و جواهر معیار',
  'url'      => $scheme . '://' . $host . '/',
  'telephone' => $settings['site_phone'],
  'email'    => $settings['site_email'],
  'address'  => ['@type' => 'PostalAddress', 'addressLocality' => 'تهران', 'streetAddress' => 'بازار بزرگ تهران، سبزه میدان، پاساژ خادم، طبقه همکف، پلاک ۴'],
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
  }, array_slice($historyRows, -90)), function ($row) {
    return $row['g'] !== '' && $row['v'] > 0;
  }));
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
    $values = array_values(array_filter(array_map(function ($row) {
      return (float)($row['v'] ?? 0);
    }, $rows), function ($value) {
      return $value > 0;
    }));
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
      <h1 class="hero-title" id="heroTitle">خرید و فروش مطمئن<br><span class="gold-text">سکه، طلا و نقره</span></h1>
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
          </div>
        </div>
        <span class="market-insight-date"><i class="hgi hgi-stroke hgi-rounded hgi-calendar-03" aria-hidden="true"></i><span><b><?= meyar_h($data['updated_date'] ?? 'امروز') ?></b><small>آخرین به‌روزرسانی</small></span></span>
      </header>
      <div class="market-insight-body market-insight-teaser-body">
        <section class="market-insight-list" aria-labelledby="marketInsightPoints">
          <h3 id="marketInsightPoints"><i class="hgi hgi-stroke hgi-rounded hgi-note-01" aria-hidden="true"></i> نکات مهم امروز</h3>
          <ul data-market-insights>
            <li><span class="market-insight-link">افزایش تقاضای جهانی طلا</span></li>
            <li><span class="market-insight-link">تاثیر نوسانات نرخ ارز</span></li>
            <li><span class="market-insight-link">روند مثبت اونس جهانی</span></li>
            <li><span class="market-insight-link">حفظ حمایت کلیدی در بازار داخلی</span></li>
          </ul>
        </section>
        <div class="market-insight-teaser-actions">
          <a class="market-insight-link market-insight-teaser-more" href="#ai-analysis" data-ai-topic="جمع‌بندی کلی بازار طلا، ارز و نقره" data-ai-trend="flat"><i class="hgi-stroke hgi-note-01" aria-hidden="true"></i><span>تحلیل کامل</span><i class="hgi-stroke hgi-arrow-left-01" aria-hidden="true"></i></a>
        </div>
      </div>
      <script type="application/json" id="marketHistoryData">
        <?= json_encode($marketHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
      </script>
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
        <article class="market-overview-card" data-overview-card="<?= meyar_h($overview['id']) ?>" data-overview-name="<?= meyar_h($overview['name']) ?>" data-overview-unit="<?= meyar_h($i['unit']) ?>" data-overview-live="<?= meyar_h($i['live']) ?>" data-overview-live-fmt="<?= meyar_h($i['live_fmt']) ?>" data-overview-change="<?= meyar_h($i['change_pct']) ?>" data-overview-dir="<?= meyar_h($i['dir']) ?>" tabindex="0" role="button" aria-haspopup="dialog" aria-label="نمایش جزئیات <?= meyar_h($overview['name']) ?>">
          <header class="market-overview-card-head">
            <span class="market-overview-icon" aria-hidden="true">
              <?php if ($i['group'] === 'currency'): ?>
                <span class="mini-flag"><?= meyar_h($i['icon']) ?></span>
              <?php else: ?>
                <img class="market-icon-image" src="assets/img/<?= $overview['id'] === 'silver999' ? 'silver.png' : ($i['group'] === 'gold' ? 'gold-icon.png' : 'emami.png') ?>" alt="">
              <?php endif; ?>
            </span>
            <div class="market-overview-title">
              <h3><?= meyar_h($overview['name']) ?></h3>
            </div>
            <span class="market-overview-action" aria-hidden="true">مشاهده نمودار <i class="hgi-stroke hgi-arrow-left-01"></i></span>
          </header>
          <div class="market-overview-price" aria-label="قیمت خرید و فروش">
            <div class="market-overview-quote buy">
              <span>خرید</span>
              <strong data-cell="buy"><?= meyar_h($i['buy_fmt']) ?></strong>
              <small><?= meyar_h($i['unit']) ?></small>
            </div>
            <div class="market-overview-quote sell">
              <span>فروش</span>
              <strong data-cell="sell"><?= meyar_h($i['sell_fmt']) ?></strong>
              <small><?= meyar_h($i['unit']) ?></small>
            </div>
          </div>
          <div class="market-overview-change <?= $dir ?>">
            <strong><?= $dir === 'up' ? '+' : ($dir === 'down' ? '−' : '') ?><?= meyar_h($i['change_pct']) ?>٪</strong>
            <span><?= meyar_h($diffText) ?></span>
          </div>
          <div class="market-overview-chart-wrap">
            <svg class="market-overview-chart <?= $dir ?>" data-overview-chart="<?= meyar_h($overview['id']) ?>" viewBox="0 0 320 76" preserveAspectRatio="none" role="img" aria-label="نمودار <?= meyar_h($overview['name']) ?>">
              <defs>
                <linearGradient id="<?= meyar_h($gradientId) ?>" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0" stop-opacity=".22" />
                  <stop offset="1" stop-opacity="0" />
                </linearGradient>
              </defs>
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
            <div>
              <dt>بالاترین امروز</dt>
              <dd><?= meyar_h(meyar_fmt($overview['high'], $decimals)) ?></dd>
            </div>
            <div>
              <dt>پایین‌ترین امروز</dt>
              <dd><?= meyar_h(meyar_fmt($overview['low'], $decimals)) ?></dd>
            </div>
          </dl>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

</main>

<!-- ═══ جزئیات دارایی بازار ═══ -->
<div class="market-overview-modal" id="marketOverviewModal" hidden>
  <div class="market-overview-modal-backdrop" data-overview-modal-close aria-hidden="true"></div>
  <div class="market-overview-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="marketOverviewModalTitle" tabindex="-1">
    <button class="market-overview-modal-close" type="button" data-overview-modal-close aria-label="بستن جزئیات بازار"><i class="hgi-stroke hgi-cancel-01" aria-hidden="true"></i></button>
    <div class="market-dashboard market-insight-card">
      <div class="market-insight-body">
        <div class="market-insight-chart-wide" data-overview-modal-chart-label>
          <div id="marketOverviewModalChart" class="market-chart market-insight-chart marketChart" role="img" aria-label="نمودار دارایی بازار">
            <div class="chart-tooltip" data-overview-modal-tooltip hidden></div>
            <div class="market-chart-message" data-overview-modal-message hidden></div>
          </div>
        </div>
        <div class="market-asset-summary">
          <div class="market-insight-asset" data-overview-modal-summary>
            <span class="market-insight-asset-icon" data-overview-modal-icon aria-hidden="true"><img class="market-icon-image" src="assets/img/gold-icon.png" alt=""></span>
            <span class="market-insight-asset-content">
              <span class="market-insight-asset-title-row"><b id="marketOverviewModalTitle" data-overview-modal-name>دارایی بازار</b></span>
              <div class="market-insight-chart-label"><span>روند:</span><b data-overview-modal-trend class="flat">خنثی</b></div>
              <span class="market-insight-asset-price-row">
                <strong data-overview-modal-change class="flat">—</strong>
                <small class="market-insight-asset-price" data-overview-modal-price>—</small>
              </span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ درباره ما ═══ -->
<section class="about" id="about">
  <div class="container">
    <div class="about-grid">
      <div class="about-text about-copy reveal" data-reveal="right">
        <span class="about-eyebrow">مجموعه معیار</span>
        <h2 class="about-title">مرجع مطمئن بازار طلا و نقره<br><span class="gold-text">با شفافیت و آگاهی</span></h2>
        <p class="about-lead">خرید و فروش حضوری، اطلاعات دقیق بازار و شناخت بهتر مسیر سرمایه‌گذاری</p>
        <p>معیار با ارائه خدمات خرید و فروش حضوری طلا و سکه، در کنار نمایش قیمت‌های به‌روز، روندهای بازار و اطلاعات تخصصی، به مشتریان و فعالان بازار کمک می‌کند تا با شناخت بهتر و دیدی آگاهانه‌تر برای تصمیم‌های مالی و سرمایه‌گذاری خود اقدام کنند.</p>
        <!-- <a class="about-cta" href="#about">آشنایی بیشتر با مجموعه <i class="hgi-stroke hgi-arrow-left-01" aria-hidden="true"></i></a> -->
      </div>
      <div class="about-media about-visual reveal" data-reveal="left" aria-label="معرفی برند سکه و جواهر معیار">
        <?php if ($aboutBanner): ?>
          <div class="about-banner-frame"><img src="<?= $aboutBanner ?>" alt="مجموعه معیار در بازار طلا" loading="lazy"></div>
        <?php else: ?>
          <div class="about-visual-inner">
            <span class="about-visual-kicker">بازار تهران</span>
            <div class="about-visual-brand"><img src="assets/img/meyar-logo/Meyar-logo.png" alt="سکه و جواهر معیار" loading="lazy"></div>
            <div class="about-visual-caption"><strong>خرید و فروش حضوری</strong><span>سکه و طلا</span></div>
          </div>
          <div class="about-visual-line" aria-hidden="true"></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="about-feature-row" aria-label="ویژگی‌های مجموعه معیار">
      <div class="about-feature"><i class="hgi-stroke hgi-favourite" aria-hidden="true"></i><span><strong>مشتریان ما</strong><small>اعتماد، بزرگ‌ترین سرمایه ما</small></span></div>
      <div class="about-feature"><i class="hgi-stroke hgi-store-01" aria-hidden="true"></i><span><strong>خرید و فروش حضوری</strong><small>سکه و طلا</small></span></div>
      <div class="about-feature"><i class="hgi-stroke hgi-location-01" aria-hidden="true"></i><span><strong>بازار تهران</strong><small>موقعیت مجموعه</small></span></div>
      <div class="about-feature"><i class="hgi-stroke hgi-calendar-03" aria-hidden="true"></i><span><strong>۱۳۸۵</strong><small>آغاز فعالیت</small></span></div>
    </div>
    <div class="about-brand-strip">
      <span class="about-brand-mark">MEYAR</span>
      <span class="about-brand-english">MORE THAN GOLD<br><small>A TRUSTED PARTNER</small></span>
      <p>برای ما، هر معامله فقط یک خرید و فروش نیست؛<br>بلکه آغاز یک رابطه بلندمدت مبتنی بر اعتماد است.</p>
    </div>
  </div>
</section>

<!-- ═══ چرا معیار ═══ -->
<section class="why why-luxury" id="why">
  <div class="container">
    <header class="why-luxury-heading reveal" data-reveal="up">
      <span class="why-luxury-eyebrow">WHY MEYAR</span>
      <h2 class="section-title">چرا <span class="gold-text">معیار</span>؟</h2>
      <p class="why-luxury-subtitle">انتخابی مطمئن برای امروز و فردای شما</p>
      <p class="why-luxury-description">معیار با ترکیب تجربه، شفافیت و دسترسی آسان به اطلاعات بازار، مسیر مطمئن‌تری برای خرید و فروش سکه و طلا فراهم می‌کند.</p>
    </header>
    <div class="why-luxury-grid">
      <article class="why-luxury-card reveal" data-reveal="up">
        <div class="why-luxury-image"><img src="assets/img/why-banners/transaction.png" alt="فضای حرفه‌ای معاملات حضوری معیار" loading="lazy"></div>
        <div class="why-luxury-body">
          <span class="why-luxury-icon"><i class="hgi-stroke hgi-store-01" aria-hidden="true"></i></span>
          <h3>معاملات حضوری</h3>
          <p>خرید و فروش حضوری سکه و طلا با ارتباط مستقیم و خدمات شفاف در مجموعه معیار.</p>
        </div>
      </article>
      <article class="why-luxury-card reveal" data-reveal="up">
        <div class="why-luxury-image"><img src="assets/img/why-banners/currentPrice.png" alt="نمودار بازار طلا و قیمت‌های لحظه‌ای" loading="lazy"></div>
        <div class="why-luxury-body">
          <span class="why-luxury-icon"><i class="hgi-stroke hgi-chart-line-data-02" aria-hidden="true"></i></span>
          <h3>قیمت‌های لحظه‌ای</h3>
          <p>دسترسی سریع به قیمت‌های به‌روز طلا، سکه و ارز برای تصمیم‌گیری دقیق‌تر.</p>
        </div>
      </article>
      <article class="why-luxury-card reveal" data-reveal="up">
        <div class="why-luxury-image"><img src="assets/img/why-banners/experience.png" alt="نماد سابقه و تجربه معیار در بازار طلا" loading="lazy"></div>
        <div class="why-luxury-body">
          <span class="why-luxury-icon"><i class="hgi-stroke hgi-calendar-03" aria-hidden="true"></i></span>
          <h3>سابقه و تجربه</h3>
          <p>حضور مستمر در بازار طلا از سال ۱۳۸۵ و شناخت عمیق از نیاز مشتریان.</p>
        </div>
      </article>
      <article class="why-luxury-card reveal" data-reveal="up">
        <div class="why-luxury-image"><img src="assets/img/why-banners/trust.png" alt="نماد امنیت و اعتماد در معاملات معیار" loading="lazy"></div>
        <div class="why-luxury-body">
          <span class="why-luxury-icon"><i class="hgi-stroke hgi-shield-check" aria-hidden="true"></i></span>
          <h3>شفافیت و اعتماد</h3>
          <p>ارائه اطلاعات دقیق و روشن برای ایجاد تجربه‌ای مطمئن در خرید و فروش.</p>
        </div>
      </article>
    </div>

    <aside class="why-credibility reveal" data-reveal="up">
      <div class="why-credibility-visual">
        <img src="assets/img/why-banners/why-credibility-metals.png" alt="ترکیب شمش طلا و شمش نقره" loading="lazy">
      </div>
      <div class="why-credibility-copy">
        <div class="why-credibility-quote-wrap">
          <p class="why-credibility-quote">شفافیت در قیمت‌گذاری،<br>آغاز یک رابطه بلندمدت است.</p>
          <div class="why-credibility-signature" aria-label="Meyar">
            <span></span><strong>MEYAR</strong><span></span>
          </div>
        </div>
        <div class="why-credibility-items">
          <span><i class="hgi-stroke hgi-user-check-01" aria-hidden="true"></i>اعتماد مشتریان</span>
          <span><i class="hgi-stroke hgi-medal-01" aria-hidden="true"></i>کیفیت و اصالت</span>
          <span><i class="hgi-stroke hgi-location-01" aria-hidden="true"></i>بازار تهران</span>
        </div>
      </div>
    </aside>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.3/dist/lightweight-charts.standalone.production.js"></script>
<?php meyar_theme_footer($settings); ?>

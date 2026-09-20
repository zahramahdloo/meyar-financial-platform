<?php
/** MEYAR — قالب مشترک سایت (هدر، تیکر، فوتر، چت) */

require_once __DIR__ . '/fetcher.php';

function meyar_theme_head(string $title, string $desc = '', string $canonical = '', string $extraHead = ''): void {
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= meyar_h($title) ?></title>
<?php if ($desc): ?><meta name="description" content="<?= meyar_h($desc) ?>"><?php endif; ?>
<?php if ($canonical): ?><link rel="canonical" href="<?= meyar_h($canonical) ?>"><?php endif; ?>
<meta property="og:title" content="<?= meyar_h($title) ?>">
<?php if ($desc): ?><meta property="og:description" content="<?= meyar_h($desc) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="fa_IR">
<link rel="icon" type="image/png" href="<?= meyar_base() ?>assets/img/meyar-logo/Meyar-logo.png">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="stylesheet" href="https://use.hugeicons.com/font/icons.css">
<link rel="stylesheet" href="<?= meyar_base() ?>assets/css/style.css?v=13">
<?= $extraHead ?>
</head>
<body>
<?php
}

/** مسیر پایه نسبی (صفحه‌ها در ریشه هستند؛ /price/x بازنویسی می‌شود) */
function meyar_base(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return (strpos($uri, '/price/') !== false) ? '../' : './';
}

function meyar_theme_topbar(array $settings, array $ticker): void {
    $base = meyar_base();
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? $requestUri);
    $isPricePage = strpos($requestPath, '/price/') !== false || basename($requestPath) === 'prices.php';
    $isTvPage = strpos($requestPath, '/tv') !== false;
?>
<!-- ═══ نوار بازار و تیکر قیمت ═══ -->
<div class="ticker-bar" id="tickerBar">
  <div class="container market-bar-inner">
    <div class="market-bar-status">
      <?php if (!empty($settings['online_badge'])): ?>
      <span class="market-live"><span class="online-dot"></span> بازار فعال</span>
      <?php endif; ?>
    </div>
    <div class="ticker-viewport" aria-label="قیمت‌های لحظه‌ای بازار">
      <div class="ticker-track" id="tickerTrack">
        <?php for ($rep = 0; $rep < 2; $rep++): ?>
          <div class="ticker-group">
      <?php foreach ($ticker as $t): ?>
        <a class="ticker-item" href="<?= $base ?>price/<?= meyar_h($t['id']) ?>" data-tid="<?= meyar_h($t['id']) ?>">
          <span class="ticker-name"><?= meyar_h($t['title']) ?></span>
          <span class="ticker-price"><?= meyar_h($t['live_fmt']) ?></span>
          <span class="ticker-change <?= $t['dir'] === 'high' ? 'up' : ($t['dir'] === 'low' ? 'down' : '') ?>">
            <?php if ($t['dir'] === 'high'): ?><i class="hgi-stroke hgi-arrow-up-01" aria-hidden="true"></i><?php elseif ($t['dir'] === 'low'): ?><i class="hgi-stroke hgi-arrow-down-01" aria-hidden="true"></i><?php endif; ?> <?= meyar_h($t['change_pct']) ?>٪
          </span>
        </a>
      <?php endforeach; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</div>

<!-- ═══ هدر ═══ -->
<header class="site-header" id="siteHeader">
  <div class="container header-inner">
    <a class="brand" href="<?= $base ?>">
      <img src="<?= $base ?>assets/img/meyar-logo/Meyar-logo.png" alt="سکه و جواهر معیار" class="brand-logo">
    </a>
    <button class="nav-toggle" id="navToggle" type="button" aria-label="باز کردن منو" aria-controls="mainNav" aria-expanded="false"><i class="hgi-stroke hgi-menu-01" aria-hidden="true"></i></button>
    <nav class="main-nav" id="mainNav" aria-label="منوی اصلی">
      <a href="<?= $base ?>" class="<?= !$isPricePage && !$isTvPage ? 'active' : '' ?>">صفحه اصلی</a>
      <div class="nav-dropdown">
        <button type="button" class="nav-dropdown-toggle <?= $isPricePage ? 'active' : '' ?>" aria-expanded="false">قیمت‌ها <i class="hgi-stroke hgi-arrow-down-01" aria-hidden="true"></i></button>
        <div class="nav-dropdown-menu">
          <a href="<?= $base ?>prices.php">همه قیمت‌ها</a>
          <a href="<?= $base ?>price/usd">قیمت ارز</a>
          <a href="<?= $base ?>price/geram18">قیمت طلا</a>
          <a href="<?= $base ?>price/sekee">قیمت سکه</a>
          <a href="<?= $base ?>price/geram18">طلای ۱۸ عیار</a>
          <a href="<?= $base ?>price/ons">انس جهانی طلا</a>
        </div>
      </div>
      <a href="<?= $base ?>tv.php">نمایشگر فروشگاه (TV)</a>
      <div class="nav-dropdown">
        <button type="button" class="nav-dropdown-toggle" aria-expanded="false">خدمات <i class="hgi-stroke hgi-arrow-down-01" aria-hidden="true"></i></button>
        <div class="nav-dropdown-menu">
          <a href="<?= $base ?>#contact">خرید سکه</a>
          <a href="<?= $base ?>#contact">فروش سکه</a>
          <a href="<?= $base ?>#contact">خرید طلا</a>
          <a href="<?= $base ?>#contact">مشاوره تخصصی</a>
        </div>
      </div>
      <a href="<?= $base ?>#about">درباره ما</a>
      <a href="<?= $base ?>#contact">تماس با ما</a>
    </nav>
    <div class="header-actions">
      <form class="header-search" id="headerSearchForm" role="search">
        <label class="sr-only" for="headerSearch">جستجوی قیمت</label>
        <input id="headerSearch" type="search" placeholder="جستجوی قیمت، طلا، سکه..." autocomplete="off">
        <button type="submit" aria-label="جستجو"><i class="hgi-stroke hgi-search-01" aria-hidden="true"></i></button>
        <div class="header-search-results" id="headerSearchResults" role="listbox" aria-label="نتایج جستجو" hidden></div>
      </form>
    </div>
  </div>
</header>
<?php
}

function meyar_theme_footer(array $settings): void {
    $base = meyar_base();
?>
<!-- ═══ فوتر ═══ -->
    <footer class="site-footer" id="contact">
      <div class="footer-glow" aria-hidden="true"></div>
      <div class="container footer-grid">
        <div class="footer-brand reveal" data-reveal="up">
          <img src="<?= $base ?>assets/img/meyar-logo/Meyar-logo.png" alt="سکه و جواهر معیار" class="footer-logo">
          <div class="footer-brand-fa">سکه و جواهر معیار</div>
          <p class="footer-brand-description">مرجع خرید و فروش حضوری سکه و طلا با اطلاعات دقیق بازار</p>
          <div class="footer-contact">
            <a href="tel:<?= meyar_h(str_replace('-', '', $settings['site_phone'])) ?>"><i class="hgi-stroke hgi-call-02" aria-hidden="true"></i><span><?= meyar_h(meyar_fa_num($settings['site_phone'])) ?></span></a>
            <a href="mailto:<?= meyar_h($settings['site_email']) ?>"><i class="hgi-stroke hgi-mail-01" aria-hidden="true"></i><span><?= meyar_h($settings['site_email']) ?></span></a>
            <span><i class="hgi-stroke hgi-location-01" aria-hidden="true"></i><span>بازار بزرگ تهران، پانزده خرداد</span></span>
          </div>
        </div>

        <nav class="footer-col reveal" data-reveal="up" aria-label="دسترسی سریع">
          <h4>دسترسی سریع</h4>
          <a href="<?= $base ?>">صفحه اصلی</a>
          <a href="<?= $base ?>prices.php">قیمت‌ها</a>
          <a href="<?= $base ?>#prices">وضعیت بازار</a>
          <a href="<?= $base ?>#about">درباره ما</a>
          <a href="<?= $base ?>#contact">تماس با ما</a>
        </nav>

        <nav class="footer-col reveal" data-reveal="up" aria-label="خدمات بازار">
          <h4>خدمات بازار</h4>
          <a href="<?= $base ?>price/geram18">قیمت لحظه‌ای طلا</a>
          <a href="<?= $base ?>price/sekee">قیمت سکه</a>
          <a href="<?= $base ?>price/usd">قیمت ارز</a>
          <a href="<?= $base ?>#prices">تحلیل بازار</a>
          <a href="<?= $base ?>tv.php">نمایشگر فروشگاه</a>
        </nav>

        <div class="footer-col footer-info reveal" data-reveal="up">
          <h4>معیار</h4>
          <span>سابقه فعالیت</span>
          <span>خرید و فروش حضوری</span>
          <span>اطلاعات شفاف بازار</span>
          <span>قوانین و حریم خصوصی</span>
        </div>
      </div>

      <div class="container footer-trust reveal" data-reveal="up" aria-label="اعتماد و خدمات معیار">
        <span><i class="hgi-stroke hgi-store-01" aria-hidden="true"></i>خرید و فروش حضوری</span>
        <span><i class="hgi-stroke hgi-chart-line-data-02" aria-hidden="true"></i>اطلاعات قیمت لحظه‌ای</span>
        <span><i class="hgi-stroke hgi-location-01" aria-hidden="true"></i>سابقه فعالیت در بازار تهران</span>
        <span><i class="hgi-stroke hgi-shield-check" aria-hidden="true"></i>اعتماد مشتریان</span>
      </div>

      <div class="footer-bottom">
        <div class="container">
          <div>تمامی حقوق مادی و معنوی متعلق به سکه معیار می‌باشد.</div>
        </div>
      </div>
    </footer>

<button class="back-top" id="backTop" aria-label="بازگشت به بالا"><i class="hgi-stroke hgi-arrow-up-01" aria-hidden="true"></i></button>

<!-- ═══ چت آنلاین ═══ -->
<div class="chat-widget" id="chatWidget">
  <button class="chat-fab" id="chatFab" aria-label="گفتگو با پشتیبانی">
    <i class="hgi-stroke hgi-message-01" aria-hidden="true"></i>
    <span class="chat-fab-badge" id="chatBadge" hidden>۱</span>
  </button>
  <div class="chat-panel" id="chatPanel" hidden>
    <div class="chat-head">
      <div class="chat-head-info">
        <span class="online-dot"></span>
        <div>
          <div class="chat-head-title">پشتیبانی سکه معیار</div>
          <div class="chat-head-sub">معمولاً سریع پاسخ می‌دهیم</div>
        </div>
      </div>
      <button class="chat-close" id="chatClose" aria-label="بستن"><i class="hgi-stroke hgi-cancel-01" aria-hidden="true"></i></button>
    </div>
    <div class="chat-body" id="chatBody">
      <div class="chat-msg a">
        <div class="chat-bubble">سلام 👋 به بورس سکه معیار خوش آمدید. سوال‌تان را بنویسید؛ همکاران ما پاسخ می‌دهند.</div>
      </div>
    </div>
    <form class="chat-input" id="chatForm" autocomplete="off">
      <input type="text" id="chatName" placeholder="نام شما (اختیاری)" maxlength="40">
      <div class="chat-input-row">
        <input type="text" id="chatText" placeholder="پیام خود را بنویسید…" maxlength="800" required>
        <button type="submit" aria-label="ارسال">
          <i class="hgi-stroke hgi-sent-01" aria-hidden="true"></i>
        </button>
      </div>
    </form>
  </div>
</div>

<script>window.MEYAR_BASE = '<?= $base ?>';</script>
<script src="<?= $base ?>assets/js/main.js?v=10"></script>
<script src="<?= $base ?>assets/js/chat.js?v=1"></script>
</body>
</html>
<?php
}

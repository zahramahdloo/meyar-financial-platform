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
<link rel="icon" type="image/svg+xml" href="<?= meyar_base() ?>assets/img/logo.svg">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<link rel="stylesheet" href="<?= meyar_base() ?>assets/css/style.css?v=7">
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
    $isPricePage = strpos($requestUri, '/price/') !== false;
?>
<!-- ═══ نوار بازار و تیکر قیمت ═══ -->
<div class="ticker-bar" id="tickerBar">
  <div class="container market-bar-inner">
    <div class="market-status">
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
            <?= $t['dir'] === 'high' ? '▲' : ($t['dir'] === 'low' ? '▼' : '') ?> <?= meyar_h($t['change_pct']) ?>٪
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
      <img src="<?= $base ?>assets/img/logo.svg" alt="سکه و جواهر معیار" class="brand-logo">
      <span class="brand-copy">
        <small>سکه و جواهر معیار</small>
        <em>مرجع قیمت و معاملات طلا</em>
      </span>
    </a>
    <button class="nav-toggle" id="navToggle" type="button" aria-label="باز کردن منو" aria-controls="mainNav" aria-expanded="false">☰</button>
    <nav class="main-nav" id="mainNav" aria-label="منوی اصلی">
      <a href="<?= $base ?>" class="<?= !$isPricePage && $requestUri !== '/tv' ? 'active' : '' ?>">صفحه اصلی</a>
      <div class="nav-dropdown">
        <button type="button" class="nav-dropdown-toggle <?= $isPricePage ? 'active' : '' ?>" aria-expanded="false">قیمت‌ها <span aria-hidden="true">⌄</span></button>
        <div class="nav-dropdown-menu">
          <a href="<?= $base ?>#prices">همه قیمت‌ها</a>
          <a href="<?= $base ?>price/usd">قیمت ارز</a>
          <a href="<?= $base ?>price/geram18">قیمت طلا</a>
          <a href="<?= $base ?>price/sekee">قیمت سکه</a>
          <a href="<?= $base ?>price/geram18">طلای ۱۸ عیار</a>
          <a href="<?= $base ?>price/ons">انس جهانی طلا</a>
        </div>
      </div>
      <a href="<?= $base ?>#analysis">تحلیل بازار</a>
      <div class="nav-dropdown">
        <button type="button" class="nav-dropdown-toggle" aria-expanded="false">خدمات <span aria-hidden="true">⌄</span></button>
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
        <button type="submit" aria-label="جستجو">⌕</button>
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
  <div class="container footer-grid">
    <div class="footer-brand reveal" data-reveal="up">
      <img src="<?= $base ?>assets/img/logo.svg" alt="MEYAR" class="footer-logo">
      <div class="footer-brand-fa">سکه و جواهر معیار</div>
      <div class="footer-contact">
        <div>شماره تماس: <a href="tel:<?= meyar_h(str_replace('-', '', $settings['site_phone'])) ?>"><?= meyar_h(meyar_fa_num($settings['site_phone'])) ?></a></div>
        <div>ایمیل: <a href="mailto:<?= meyar_h($settings['site_email']) ?>"><?= meyar_h($settings['site_email']) ?></a></div>
      </div>
    </div>
    <div class="footer-col reveal" data-reveal="up">
      <h4>دسترسی سریع</h4>
      <a href="<?= $base ?>price/usd">قیمت دلار</a>
      <a href="<?= $base ?>price/sekeb">قیمت سکه بهار آزادی</a>
      <a href="<?= $base ?>price/gbp">قیمت پوند</a>
      <a href="<?= $base ?>price/eur">قیمت یورو</a>
      <a href="<?= $base ?>price/aed">قیمت درهم</a>
      <a href="<?= $base ?>price/geram24">قیمت طلا ۲۴ عیار</a>
    </div>
    <div class="footer-col reveal" data-reveal="up">
      <h4>راهنما</h4>
      <a href="<?= $base ?>tv">نمایشگر فروشگاه (TV)</a>
      <a href="<?= $base ?>">صفحه اصلی</a>
      <a href="<?= $base ?>#prices">قیمت‌ها</a>
      <a href="<?= $base ?>#about">درباره ما</a>
      <a href="<?= $base ?>#contact">تماس با ما</a>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">
      <div>تمامی حقوق مادی و معنوی متعلق به بورس سکه معیار می‌باشد.</div>
      <div class="footer-credit">طراحی شده توسط <a href="https://hadignz.ir" target="_blank" rel="noopener">هادی قنادزاده</a></div>
    </div>
  </div>
</footer>

<button class="back-top" id="backTop" aria-label="بازگشت به بالا">↑</button>

<!-- ═══ چت آنلاین ═══ -->
<div class="chat-widget" id="chatWidget">
  <button class="chat-fab" id="chatFab" aria-label="گفتگو با پشتیبانی">
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
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
      <button class="chat-close" id="chatClose" aria-label="بستن">✕</button>
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
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
      </div>
    </form>
  </div>
</div>

<script>window.MEYAR_BASE = '<?= $base ?>';</script>
<script src="<?= $base ?>assets/js/main.js?v=4"></script>
<script src="<?= $base ?>assets/js/chat.js?v=1"></script>
</body>
</html>
<?php
}

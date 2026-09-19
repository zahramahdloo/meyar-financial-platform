<?php
/**
 * MEYAR — نمایشگر تلویزیون (صرافی / طلافروشی)
 * تمام‌صفحه، فونت درشت، چرخش خودکار صفحات، بروزرسانی زنده، جلوگیری از خواب صفحه
 * آدرس: /tv
 */
require_once __DIR__ . '/inc/theme.php';
meyar_track('/tv');

$settings = meyar_load_settings();
$data     = meyar_build_prices();
$items    = array_values(array_filter($data['items'], function ($i) { return empty($i['hidden']); }));
$byGroup  = ['coins'=>[], 'parsian'=>[], 'gold'=>[], 'currency'=>[]];
foreach ($items as $i) { if (isset($byGroup[$i['group']])) $byGroup[$i['group']][] = $i; }

/* صفحات: هر صفحه = حداکثر ۲ پنل، هر پنل حداکثر ۸ ردیف */
$pages = [];
if ($byGroup['coins'] || $byGroup['gold']) {
    $pages[] = [
        ['title' => 'سکه‌ها', 'items' => array_slice($byGroup['coins'], 0, 8)],
        ['title' => 'طلا',    'items' => array_slice($byGroup['gold'],  0, 8)],
    ];
}
if ($byGroup['parsian']) {
    foreach (array_chunk($byGroup['parsian'], 16) as $chunk) {
        $half = array_chunk($chunk, (int)ceil(count($chunk) / 2));
        $pages[] = [
            ['title' => 'سکه‌های پارسیان', 'items' => $half[0]],
            ['title' => 'سکه‌های پارسیان', 'items' => $half[1] ?? []],
        ];
    }
}
if ($byGroup['currency']) {
    foreach (array_chunk($byGroup['currency'], 16) as $chunk) {
        $half = array_chunk($chunk, (int)ceil(count($chunk) / 2));
        $pages[] = [
            ['title' => 'ارزها', 'items' => $half[0]],
            ['title' => 'ارزها', 'items' => $half[1] ?? []],
        ];
    }
}

$tickerIds = ['sekee','sekeb','nim','rob','gerami','geram18','usd','eur','ons'];
$ticker = array_values(array_filter($items, function ($i) use ($tickerIds) { return in_array($i['id'], $tickerIds, true); }));

function tv_row(array $i): void { ?>
  <div class="tv-row" data-id="<?= meyar_h($i['id']) ?>">
    <span class="tv-name">
      <i class="hgi hgi-stroke hgi-rounded <?= $i['group'] === 'currency' ? 'hgi-cash-02' : ($i['group'] === 'gold' ? 'hgi-gold-ingots' : 'hgi-coins-01') ?>" aria-hidden="true"></i>
      <?= meyar_h($i['title']) ?>
    </span>
    <b class="tv-buy" data-cell="buy"><?= meyar_h($i['buy_fmt']) ?></b>
    <b class="tv-sell" data-cell="sell"><?= meyar_h($i['sell_fmt']) ?></b>
    <span class="tv-chg <?= $i['dir'] === 'high' ? 'up' : ($i['dir'] === 'low' ? 'down' : 'flat') ?>" data-cell="chg">
      <?php if ($i['dir'] === 'high'): ?><i class="hgi-stroke hgi-arrow-up-01" aria-hidden="true"></i><?php elseif ($i['dir'] === 'low'): ?><i class="hgi-stroke hgi-arrow-down-01" aria-hidden="true"></i><?php else: ?>–<?php endif; ?> <?= meyar_h($i['change_pct']) ?>٪
    </span>
  </div>
<?php } ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>نمایشگر قیمت — سکه و جواهر معیار</title>
<link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="stylesheet" href="https://use.hugeicons.com/font/icons.css">
<style>
@font-face { font-family: 'IRANSansXFaNum'; src: url('assets/Sans-fonts/Woff2/IRANSansXFaNum-Regular.woff2') format('woff2'); font-weight: 400; font-style: normal; font-display: swap; }
@font-face { font-family: 'IRANSansXFaNum'; src: url('assets/Sans-fonts/Woff2/IRANSansXFaNum-Bold.woff2') format('woff2'); font-weight: 700 900; font-style: normal; font-display: swap; }
:root {
  --gold: #d4a437; --gold-light: #f0cf7a; --gold-dark: #a87c1f;
  --gold-grad: linear-gradient(135deg, #a87c1f 0%, #f0cf7a 45%, #d4a437 60%, #8a6516 100%);
  --bg: #0b0c11; --panel: #14161f; --line: rgba(212, 164, 55, .18);
  --green: #3ddc84; --red: #ff6b6b; --soft: #9298ab;
}
.tv-name > i { width: 28px; flex: 0 0 28px; color: var(--gold-light); font-size: 22px; text-align: center; }
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { height: 100%; overflow: hidden; }
body {
  font-family: 'IRANSansXFaNum', sans-serif;
  background:
    radial-gradient(1000px 500px at 85% -10%, rgba(212, 164, 55, .09), transparent 60%),
    radial-gradient(800px 500px at 10% 110%, rgba(212, 164, 55, .06), transparent 60%),
    var(--bg);
  color: #eceef4; display: flex; flex-direction: column;
  cursor: none;
}
.num, .tv-buy, .tv-sell, .tv-chg, .tv-clock { font-variant-numeric: tabular-nums; }

/* ═══ هدر ═══ */
.tv-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.2vh 2.2vw; border-bottom: 1px solid var(--line);
  background: linear-gradient(180deg, #14161f, #0f1118);
}
.tv-brand { display: flex; align-items: center; gap: 1.2vw; }
.tv-brand img { height: 7.5vh; filter: drop-shadow(0 4px 18px rgba(212, 164, 55, .35)); }
.tv-brand-txt b {
  display: block; font-size: 2.9vh; font-weight: 800;
  background: var(--gold-grad); -webkit-background-clip: text; background-clip: text; color: transparent;
}
.tv-brand-txt span { font-size: 1.7vh; color: var(--soft); }
.tv-mid { text-align: center; }
.tv-clock { font-size: 5.2vh; font-weight: 800; color: var(--gold-light); letter-spacing: 2px; line-height: 1.15; }
.tv-date { font-size: 1.9vh; color: var(--soft); }
.tv-status { text-align: left; font-size: 1.8vh; color: var(--soft); }
.tv-live { display: flex; align-items: center; gap: .6vw; justify-content: flex-end; color: var(--green); font-weight: 700; font-size: 1.9vh; }
.tv-live-dot {
  width: 1.1vh; height: 1.1vh; border-radius: 50%; background: var(--green);
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(61, 220, 132, .5); }
  70% { box-shadow: 0 0 0 1.2vh rgba(61, 220, 132, 0); }
  100% { box-shadow: 0 0 0 0 rgba(61, 220, 132, 0); }
}

/* ═══ صفحات ═══ */
.tv-main { flex: 1; position: relative; overflow: hidden; }
.tv-page {
  position: absolute; inset: 0; padding: 2vh 2.2vw;
  display: none; gap: 2vw;
  grid-template-columns: 1fr 1fr;
}
.tv-page.active { display: grid; animation: page-in .7s cubic-bezier(.22, .8, .35, 1); }
@keyframes page-in { from { opacity: 0; transform: translateX(-3vw); } }
.tv-panel {
  background: linear-gradient(165deg, #171a24, #12141d);
  border: 1px solid var(--line); border-radius: 1.6vh;
  overflow: hidden; display: flex; flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, .45);
}
.tv-panel-head {
  display: grid; grid-template-columns: 1fr 22% 22% 16%;
  align-items: center; gap: 1vw;
  padding: 1.4vh 1.4vw; border-bottom: 2px solid rgba(212, 164, 55, .4);
  background: linear-gradient(135deg, #1c1f2b, #171a24);
}
.tv-panel-head h2 {
  font-size: 2.5vh; font-weight: 800; display: flex; align-items: center; gap: .7vw;
  background: var(--gold-grad); -webkit-background-clip: text; background-clip: text; color: transparent;
}
.tv-panel-head h2::before {
  content: ''; width: 1.1vh; height: 1.1vh; border-radius: .35vh; flex-shrink: 0;
  background: var(--gold-grad); box-shadow: 0 0 12px rgba(212, 164, 55, .8);
}
.tv-col-label { font-size: 1.7vh; color: var(--soft); font-weight: 600; text-align: center; }
.tv-rows { flex: 1; display: flex; flex-direction: column; justify-content: space-evenly; padding: .5vh 0; }
.tv-row {
  display: grid; grid-template-columns: 1fr 22% 22% 16%;
  align-items: center; gap: 1vw; padding: .9vh 1.4vw;
  border-top: 1px solid rgba(255, 255, 255, .045);
}
.tv-row:first-child { border-top: none; }
.tv-row:nth-child(even) { background: rgba(255, 255, 255, .022); }
.tv-name { display: flex; align-items: center; gap: .8vw; font-size: 2.5vh; font-weight: 700; white-space: nowrap; overflow: hidden; }
.tv-coin {
  width: 3vh; height: 3vh; border-radius: 50%; flex-shrink: 0;
  background: radial-gradient(circle at 35% 30%, #ffe9a8, #d4a437 55%, #8a6516);
  box-shadow: inset 0 0 0 .35vh rgba(138, 101, 22, .5);
}
.tv-coin.gold { border-radius: .8vh; }
.tv-flag { font-size: 2.6vh; flex-shrink: 0; }
.tv-buy, .tv-sell { font-size: 2.8vh; font-weight: 800; text-align: center; white-space: nowrap; }
.tv-buy { color: #dfe3ee; }
.tv-sell { color: var(--gold-light); }
.tv-chg { font-size: 1.9vh; font-weight: 700; text-align: center; white-space: nowrap; }
.tv-chg.up { color: var(--green); }
.tv-chg.down { color: var(--red); }
.tv-chg.flat { color: var(--soft); }
.flash-up   { animation: flash-g 1.2s; }
.flash-down { animation: flash-r 1.2s; }
@keyframes flash-g { 0% { background: rgba(61, 220, 132, .22); } 100% { background: transparent; } }
@keyframes flash-r { 0% { background: rgba(255, 107, 107, .18); } 100% { background: transparent; } }

/* نشانگر صفحات */
.tv-pager {
  display: flex; justify-content: center; align-items: center; gap: .8vw; padding: .8vh 0 .4vh;
}
.tv-dot { width: 1.2vh; height: 1.2vh; border-radius: 2vh; background: rgba(255, 255, 255, .18); transition: all .4s; }
.tv-dot.active { width: 4vh; background: var(--gold-grad); box-shadow: 0 0 10px rgba(212, 164, 55, .6); }

/* ═══ تیکر پایین ═══ */
.tv-ticker {
  border-top: 1px solid var(--line);
  background: linear-gradient(90deg, #14161f, #1a1d29, #14161f);
  overflow: hidden; white-space: nowrap;
}
.tv-ticker-track { display: inline-flex; animation: tk 40s linear infinite; will-change: transform; }
@keyframes tk { from { transform: translateX(-50%); } to { transform: translateX(0); } }
.tv-tk-item { display: inline-flex; align-items: center; gap: .8vw; padding: 1.4vh 1.8vw; border-left: 1px solid rgba(255, 255, 255, .06); font-size: 2.2vh; }
.tv-tk-name { color: var(--soft); }
.tv-tk-price { color: var(--gold-light); font-weight: 800; }
.tv-tk-chg { font-size: 1.7vh; color: var(--soft); }
.tv-tk-chg.up { color: var(--green); }
.tv-tk-chg.down { color: var(--red); }
.tv-update { font-size: 1.6vh; color: var(--soft); text-align: center; padding: .5vh 0 .8vh; }
.tv-update b { color: var(--gold-light); }
</style>
</head>
<body>

<header class="tv-head">
  <div class="tv-brand">
    <img src="assets/img/logo.svg" alt="MEYAR">
    <div class="tv-brand-txt">
      <b>سکه و جواهر معیار</b>
      <span>قیمت لحظه‌ای سکه، طلا و ارز</span>
    </div>
  </div>
  <div class="tv-mid">
    <div class="tv-clock" id="tvClock">--:--:--</div>
    <div class="tv-date" id="tvDate">—</div>
  </div>
  <div class="tv-status">
    <div class="tv-live"><span class="tv-live-dot"></span> قیمت زنده</div>
    <div>تلفن: <?= meyar_h(meyar_fa_num($settings['site_phone'])) ?></div>
  </div>
</header>

<main class="tv-main" id="tvMain">
  <?php foreach ($pages as $pi => $page): ?>
  <div class="tv-page <?= $pi === 0 ? 'active' : '' ?>">
    <?php foreach ($page as $panel): if (empty($panel['items'])) continue; ?>
    <section class="tv-panel">
      <div class="tv-panel-head">
        <h2><?= meyar_h($panel['title']) ?></h2>
        <span class="tv-col-label">خرید</span>
        <span class="tv-col-label">فروش</span>
        <span class="tv-col-label">تغییر</span>
      </div>
      <div class="tv-rows">
        <?php foreach ($panel['items'] as $i) tv_row($i); ?>
      </div>
    </section>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</main>

<div class="tv-pager" id="tvPager">
  <?php foreach ($pages as $pi => $_): ?><span class="tv-dot <?= $pi === 0 ? 'active' : '' ?>"></span><?php endforeach; ?>
</div>

<div class="tv-ticker">
  <div class="tv-ticker-track">
    <?php for ($rep = 0; $rep < 2; $rep++): ?>
      <?php foreach ($ticker as $t): ?>
      <span class="tv-tk-item" data-tid="<?= meyar_h($t['id']) ?>">
        <span class="tv-tk-name"><?= meyar_h($t['title']) ?></span>
        <span class="tv-tk-price"><?= meyar_h($t['live_fmt']) ?></span>
        <span class="tv-tk-chg <?= $t['dir'] === 'high' ? 'up' : ($t['dir'] === 'low' ? 'down' : '') ?>">
          <?php if ($t['dir'] === 'high'): ?><i class="hgi-stroke hgi-arrow-up-01" aria-hidden="true"></i><?php elseif ($t['dir'] === 'low'): ?><i class="hgi-stroke hgi-arrow-down-01" aria-hidden="true"></i><?php endif; ?> <?= meyar_h($t['change_pct']) ?>٪
        </span>
      </span>
      <?php endforeach; ?>
    <?php endfor; ?>
  </div>
</div>
<div class="tv-update">آخرین به‌روزرسانی: <b id="tvUpdate"><?= meyar_h($data['updated']) ?></b></div>

<script>
(function () {
  'use strict';

  /* ساعت و تاریخ شمسی */
  var clockEl = document.getElementById('tvClock'), dateEl = document.getElementById('tvDate');
  var dateFmt = null, timeFmt = null;
  try {
    dateFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    timeFmt = new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
  } catch (e) {}
  function tick() {
    var now = new Date();
    clockEl.textContent = timeFmt ? timeFmt.format(now) : now.toLocaleTimeString();
    if (dateFmt) dateEl.textContent = dateFmt.format(now);
  }
  tick(); setInterval(tick, 1000);

  /* چرخش خودکار صفحات */
  var pages = document.querySelectorAll('.tv-page');
  var dots  = document.querySelectorAll('#tvPager .tv-dot');
  var PAGE_MS = 14000, idx = 0;
  if (pages.length > 1) {
    setInterval(function () {
      idx = (idx + 1) % pages.length;
      pages.forEach(function (p, k) { p.classList.toggle('active', k === idx); });
      dots.forEach(function (d, k) { d.classList.toggle('active', k === idx); });
    }, PAGE_MS);
  }

  /* بروزرسانی زنده قیمت‌ها */
  function updateCell(cell, val) {
    if (!cell || cell.textContent.trim() === val) return;
    var dir = cell.textContent.trim() < val ? 'up' : 'down';
    cell.textContent = val;
    var row = cell.closest('.tv-row');
    if (row) {
      row.classList.remove('flash-up', 'flash-down');
      void row.offsetWidth;
      row.classList.add(dir === 'up' ? 'flash-up' : 'flash-down');
    }
  }
  function setTvDirection(el, dir, value) {
    if (!el) return;
    el.innerHTML = (dir === 'high'
      ? '<i class="hgi-stroke hgi-arrow-up-01" aria-hidden="true"></i>'
      : (dir === 'low' ? '<i class="hgi-stroke hgi-arrow-down-01" aria-hidden="true"></i>' : '–')) + ' ' + value + '٪';
  }
  function refresh() {
    fetch('api/prices.php', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.items) return;
        var byId = {};
        data.items.forEach(function (i) { byId[i.id] = i; });
        document.querySelectorAll('.tv-row[data-id]').forEach(function (row) {
          var it = byId[row.getAttribute('data-id')];
          if (!it) return;
          updateCell(row.querySelector('[data-cell="buy"]'), it.buy_fmt);
          updateCell(row.querySelector('[data-cell="sell"]'), it.sell_fmt);
          var chg = row.querySelector('[data-cell="chg"]');
          if (chg) {
            setTvDirection(chg, it.dir, it.change_pct);
            chg.className = 'tv-chg ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : 'flat'));
          }
        });
        document.querySelectorAll('.tv-tk-item').forEach(function (t) {
          var it = byId[t.getAttribute('data-tid')];
          if (!it) return;
          t.querySelector('.tv-tk-price').textContent = it.live_fmt;
          var ch = t.querySelector('.tv-tk-chg');
          setTvDirection(ch, it.dir, it.change_pct);
          ch.className = 'tv-tk-chg ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : ''));
        });
        var u = document.getElementById('tvUpdate');
        if (u && data.updated) u.textContent = data.updated;
      })
      .catch(function () { /* تلاش بعدی */ });
  }
  setInterval(refresh, 30000);

  /* جلوگیری از خاموش شدن صفحه (Wake Lock) */
  var wakeLock = null;
  function reqWake() {
    if ('wakeLock' in navigator) {
      navigator.wakeLock.request('screen').then(function (l) { wakeLock = l; }).catch(function () {});
    }
  }
  reqWake();
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { reqWake(); refresh(); } });

  /* رفرش کامل هر ۴ ساعت (پاک شدن حافظه در نمایش طولانی) */
  setTimeout(function () { location.reload(); }, 4 * 3600 * 1000);
})();
</script>
</body>
</html>

<?php
/**
 * MEYAR — نمایشگر تلویزیون (صرافی / طلافروشی)
 * تمام‌صفحه، فونت درشت، جابه‌جایی دستی صفحات، بروزرسانی زنده، جلوگیری از خواب صفحه
 * آدرس: /tv
 */
require_once __DIR__ . '/inc/theme.php';
meyar_track('/tv');

$settings = meyar_load_settings();
$data     = meyar_build_prices();
$items    = array_values(array_filter($data['items'], function ($i) { return empty($i['hidden']); }));
$byGroup  = ['coins'=>[], 'parsian'=>[], 'gold'=>[], 'silver'=>[], 'currency'=>[]];
foreach ($items as $i) {
    if ($i['id'] === 'silver999') {
        $byGroup['silver'][] = $i;
    } elseif (isset($byGroup[$i['group']])) {
        $byGroup[$i['group']][] = $i;
    }
}
$importantCurrencyIds = ['usd', 'eur', 'aed', 'gbp', 'try', 'chf'];
$byGroup['currency'] = array_values(array_filter($byGroup['currency'], function ($i) use ($importantCurrencyIds) {
    return in_array($i['id'], $importantCurrencyIds, true);
}));

/* صفحات: هر صفحه = حداکثر ۲ پنل، هر پنل حداکثر ۸ ردیف */
$pages = [];
if ($byGroup['coins'] || $byGroup['gold']) {
    $pages[] = [
        ['title' => 'سکه‌ها', 'group' => 'coins', 'items' => array_slice($byGroup['coins'], 0, 8)],
        ['title' => 'طلا',    'group' => 'gold', 'items' => array_slice($byGroup['gold'],  0, 8)],
    ];
}
if ($byGroup['parsian']) {
    foreach (array_chunk($byGroup['parsian'], 16) as $chunk) {
        $half = array_chunk($chunk, (int)ceil(count($chunk) / 2));
        $pages[] = [
            ['title' => 'سکه‌های پارسیان', 'group' => 'parsian', 'items' => $half[0]],
            ['title' => 'سکه‌های پارسیان', 'group' => 'parsian', 'items' => $half[1] ?? []],
        ];
    }
}
if ($byGroup['currency']) {
    $pages[] = [
        ['title' => 'ارزها', 'group' => 'currency', 'items' => array_slice($byGroup['currency'], 0, 6)],
    ];
}
if ($byGroup['silver']) {
    $pages[] = [
        ['title' => 'نقره', 'group' => 'silver', 'items' => $byGroup['silver']],
    ];
}

$tickerIds = ['sekee','sekeb','nim','rob','gerami','geram18','silver999','usd','eur','ons'];
$ticker = array_values(array_filter($items, function ($i) use ($tickerIds) { return in_array($i['id'], $tickerIds, true); }));

function tv_row(array $i): void { ?>
  <div class="tv-row" data-id="<?= meyar_h($i['id']) ?>">
    <span class="tv-name">
      <?php if ($i['group'] === 'currency'): ?>
        <span class="tv-flag" aria-hidden="true"><?= meyar_h($i['icon']) ?></span>
      <?php endif; ?>
      <?= meyar_h($i['title']) ?>
    </span>
    <span class="tv-chg <?= $i['dir'] === 'high' ? 'up' : ($i['dir'] === 'low' ? 'down' : 'flat') ?>" data-cell="chg">
      <?php if ($i['dir'] === 'high'): ?><i class="hgi-stroke hgi-arrow-up-01" aria-hidden="true"></i><?php elseif ($i['dir'] === 'low'): ?><i class="hgi-stroke hgi-arrow-down-01" aria-hidden="true"></i><?php else: ?>–<?php endif; ?> <?= meyar_h($i['change_pct']) ?>٪
    </span>
    <b class="tv-sell" data-cell="sell"><?= meyar_h($i['sell_fmt']) ?></b>
    <b class="tv-buy" data-cell="buy"><?= meyar_h($i['buy_fmt']) ?></b>
  </div>
<?php } ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>نمایشگر قیمت — سکه و جواهر معیار</title>
<link rel="icon" type="image/png" href="assets/img/meyar-logo/Meyar-logo.png">
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
* { margin: 0; padding: 0; box-sizing: border-box; }
button, input, select, textarea { font-family: inherit; }
html, body { height: 100%; overflow: hidden; }
body {
  font-family: 'IRANSansXFaNum', sans-serif;
  background:
    radial-gradient(1000px 500px at 85% -10%, rgba(212, 164, 55, .09), transparent 60%),
    radial-gradient(800px 500px at 10% 110%, rgba(212, 164, 55, .06), transparent 60%),
    var(--bg);
  color: #eceef4; display: flex; flex-direction: column;
  cursor: default;
}
.num, .tv-buy, .tv-sell, .tv-chg { font-variant-numeric: tabular-nums; }

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
.tv-panel--silver { align-self: start; min-height: 0 !important; height: max-content; }
.tv-panel-head {
  display: grid; grid-template-columns: 1fr 22% 22% 16%;
  align-items: center; gap: 1vw;
  padding: 1.4vh 1.4vw; border-bottom: 2px solid rgba(212, 164, 55, .4);
  background: linear-gradient(135deg, #1c1f2b, #171a24);
}
.tv-panel-head h2 {
  font-weight: 800; display: flex; align-items: center; gap: .7vw;
  background: var(--gold-grad); -webkit-background-clip: text; background-clip: text; color: transparent;
}
.tv-panel-head h2::before {
  content: ''; width: 1.1vh; height: 1.1vh; border-radius: .35vh; flex-shrink: 0;
  background: var(--gold-grad); box-shadow: 0 0 12px rgba(212, 164, 55, .8);
}
.tv-col-label { color: var(--soft); font-weight: 600; text-align: center; }
.tv-rows { flex: 1; display: flex; flex-direction: column; justify-content: space-evenly; padding: .5vh 0; }
.tv-row {
  display: grid; grid-template-columns: 1fr 22% 22% 16%;
  align-items: center; gap: 1vw; padding: .9vh 1.4vw;
  border-top: 1px solid rgba(255, 255, 255, .045);
}
.tv-row:first-child { border-top: none; }
.tv-row:nth-child(even) { background: rgba(255, 255, 255, .022); }
.tv-name { display: flex; align-items: center; gap: .8vw; font-weight: 700; white-space: nowrap; overflow: hidden; }
.tv-coin {
  width: 3vh; height: 3vh; border-radius: 50%; flex-shrink: 0;
  background: radial-gradient(circle at 35% 30%, #ffe9a8, #d4a437 55%, #8a6516);
  box-shadow: inset 0 0 0 .35vh rgba(138, 101, 22, .5);
}
.tv-coin.gold { border-radius: .8vh; }
.tv-flag { flex-shrink: 0; }
.tv-buy, .tv-sell { font-weight: 800; text-align: center; white-space: nowrap; }
.tv-buy { color: #dfe3ee; }
.tv-sell { color: var(--gold-light); }
.tv-chg { font-weight: 700; text-align: center; white-space: nowrap; }
.tv-chg.up { color: var(--green); }
.tv-chg.down { color: var(--red); }
.tv-chg.flat { color: var(--soft); }
.tv-row { position: relative; isolation: isolate; }
.tv-row.price-flash-up { animation: tv-price-flash-up 850ms ease-out; }
.tv-row.price-flash-down { animation: tv-price-flash-down 850ms ease-out; }
@keyframes tv-price-flash-up {
  0% { background-color: rgba(61, 220, 132, .20); box-shadow: inset 0 0 0 1px rgba(61, 220, 132, .34); }
  100% { background-color: transparent; box-shadow: inset 0 0 0 1px transparent; }
}
@keyframes tv-price-flash-down {
  0% { background-color: rgba(255, 107, 107, .16); box-shadow: inset 0 0 0 1px rgba(255, 107, 107, .30); }
  100% { background-color: transparent; box-shadow: inset 0 0 0 1px transparent; }
}
@media (prefers-reduced-motion: reduce) {
  .tv-row.price-flash-up,
  .tv-row.price-flash-down { animation: none; }
}

/* نشانگر صفحات */
/* ═══ تیکر پایین ═══ */
.tv-ticker {
  position: relative;
  width: 100%;
  height: 38px;
  min-width: 0;
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  overflow: hidden;
  white-space: nowrap;
  direction: ltr;
  color: #cbd5e1;
  background: #0b1220;
  border-top: 1px solid rgba(201, 162, 39, .24);
}
.tv-ticker::before,
.tv-ticker::after {
  content: ''; position: absolute; top: 0; bottom: 0; z-index: 3;
  width: 55px; pointer-events: none;
}
.tv-ticker::before { left: 0; background: linear-gradient(to right, #0b1220 0%, rgba(11,18,32,0) 100%); }
.tv-ticker::after { right: 0; background: linear-gradient(to left, #0b1220 0%, rgba(11,18,32,0) 100%); }
.tv-ticker-track {
  --tv-ticker-duration: 38s;
  display: flex;
  align-items: center;
  width: max-content;
  height: 100%;
  animation: tv-ticker-scroll var(--tv-ticker-duration) linear infinite;
  will-change: transform;
  backface-visibility: hidden;
}
@keyframes tv-ticker-scroll {
  from { transform: translate3d(-50%, 0, 0); }
  to { transform: translate3d(0, 0, 0); }
}
.tv-tk-item {
  display: inline-flex; align-items: center; gap: 8px;
  height: 100%; flex: 0 0 auto; padding: 0 24px;
  direction: rtl; white-space: nowrap;
  border-left: 1px solid rgba(255, 255, 255, .14);
}
.tv-tk-name { color: var(--soft); }
.tv-tk-price { color: var(--gold-light); font-weight: 800; }
.tv-tk-chg { color: var(--soft); }
.tv-tk-chg.up { color: var(--green); }
.tv-tk-chg.down { color: var(--red); }

/* ═══ طراحی پریمیوم نمایشگر بازار ═══ */
:root {
  --bg: #071426;
  --panel: #101f36;
  --panel-soft: #142743;
  --line: rgba(212, 164, 55, .28);
  --line-soft: rgba(148, 163, 184, .14);
  --soft: #91a4bf;
}
body {
  background-color: var(--bg);
  background-image:
    linear-gradient(rgba(88, 121, 163, .045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(88, 121, 163, .045) 1px, transparent 1px),
    radial-gradient(900px 480px at 50% -20%, rgba(212, 164, 55, .13), transparent 70%);
  background-size: 42px 42px, 42px 42px, auto;
}
.tv-carousel { position: relative; display: flex; flex: 1; width: 100%; min-width: 0; min-height: 0; padding-inline: clamp(56px, 5vw, 96px); box-sizing: border-box; }
.tv-main { min-width: 0; padding: 1.7vh 0 0; }
.tv-page, .tv-page * { cursor: default; }
.tv-carousel button { cursor: pointer; }
.tv-nav {
  position: absolute; top: 50%; z-index: 5;
  display: inline-flex; align-items: center; justify-content: center;
  width: 42px; height: 42px; padding: 0;
  transform: translateY(-50%);
  border: 1px solid rgba(212, 164, 55, .62); border-radius: 50%;
  color: #f4d77c; background: rgba(7, 20, 38, .94);
  cursor: pointer; transition: background-color .18s ease, transform .18s ease, box-shadow .18s ease;
}
.tv-nav i { line-height: 1; }
.tv-nav--prev { inset-inline-start: clamp(8px, 1.5vw, 28px); }
.tv-nav--next { inset-inline-end: clamp(8px, 1.5vw, 28px); }
.tv-nav:hover { background: rgba(212, 164, 55, .16); box-shadow: 0 0 16px rgba(212, 164, 55, .2); transform: translateY(-50%) scale(1.04); }
.tv-nav:focus-visible { outline: 2px solid #f4d77c; outline-offset: 3px; }
.tv-nav:active { transform: translateY(-50%) scale(.97); }
.tv-nav:disabled { opacity: .35; cursor: not-allowed; pointer-events: none; }
.tv-page { position: relative; inset: auto; width: min(100%, 1500px); height: 100%; min-height: 0; margin-inline: auto; padding: 0; gap: 1.5vw; align-content: center; }
.tv-page.single-panel {
  grid-template-columns: minmax(0, 1fr);
  width: min(100%, 1150px);
  height: 100%;
  margin-inline: auto;
  align-content: center;
}
.tv-page.single-panel .tv-panel { min-height: clamp(420px, 58vh, 680px); }
.tv-panel {
  min-width: 0;
  background: linear-gradient(155deg, rgba(20, 39, 67, .96), rgba(12, 29, 51, .96));
  border: 1px solid rgba(212, 164, 55, .3);
  border-radius: 16px;
  box-shadow: 0 14px 32px rgba(0, 0, 0, .18);
}
.tv-panel-head {
  grid-template-columns: minmax(0, 1fr) 16% 23% 23%;
  gap: .7vw;
  min-height: 9vh;
  padding: 1.2vh 1.5vw;
  border-bottom: 1px solid rgba(212, 164, 55, .28);
  background: rgba(17, 42, 72, .72);
}
.tv-panel-head h2 {
  min-width: 0;
  gap: .65vw;
  color: #f4d77c;
  background: none;
  -webkit-text-fill-color: currentColor;
}
.tv-panel-head h2::before { display: none; }
.tv-market-icon { object-fit: contain; }
.tv-panel-head h2 > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.tv-col-label { color: #91a4bf; }
.tv-panel-head > .tv-col-label,
.tv-row > .tv-chg,
.tv-row > .tv-sell,
.tv-row > .tv-buy {
  border-inline-start: 1px solid rgba(148, 163, 184, .18);
}
.tv-rows { padding: .7vh 0; }
.tv-row {
  grid-template-columns: minmax(0, 1fr) 16% 23% 23%;
  gap: .7vw;
  min-height: 7.2vh;
  padding: .8vh 1.5vw;
  border-top-color: var(--line-soft);
}
.tv-row:nth-child(even) { background: rgba(93, 126, 170, .055); }
.tv-name { gap: .7vw; color: #f3f6fb; }
.tv-buy { color: #f3f6fb; }
.tv-sell { color: #f4d77c; }
.tv-ticker { border-top-color: rgba(201, 162, 39, .24); background: #0b1220; }
.tv-tk-item { border-left-color: rgba(148, 163, 184, .12); }
.tv-tk-name { color: #91a4bf; }
@media (max-width: 900px) {
  .tv-panel-head, .tv-row { grid-template-columns: minmax(0, 1fr) 17% 24% 24%; }
}
@media (max-width: 680px) {
  html, body { overflow: auto; }
  body { min-height: 100vh; }
  .tv-carousel { padding-inline: 50px; }
  .tv-main { padding: 1.5vh 0 0; overflow: visible; }
  .tv-nav { width: 40px; height: 40px; }
  .tv-nav--prev { inset-inline-start: 5px; }
  .tv-nav--next { inset-inline-end: 5px; }
  .tv-page, .tv-page.active { display: grid; grid-template-columns: 1fr; width: 100%; height: auto; gap: 1.4vh; align-content: start; }
  .tv-page.single-panel { width: 100%; height: auto; align-content: start; }
  .tv-page.single-panel .tv-panel { min-height: 0; }
  .tv-panel-head, .tv-row { grid-template-columns: minmax(0, 1fr) 18% 25% 25%; gap: 1.2vw; }
  .tv-panel-head { min-height: 7.5vh; padding-inline: 3vw; }
  .tv-row { min-height: 6.8vh; padding-inline: 3vw; }
  .tv-tk-item { padding-inline: 4vw; }
}
@media (max-width: 768px) {
  .tv-ticker-track { --tv-ticker-duration: 30s; }
  .tv-tk-item { gap: 6px; padding-inline: 18px; }
  .tv-ticker::before, .tv-ticker::after { width: 30px; }
}
@media (min-width: 1600px) {
  .tv-panel-head {
    min-height: clamp(76px, 7vh, 112px);
    padding-inline: clamp(24px, 1.5vw, 34px);
  }
  .tv-row {
    min-height: clamp(72px, 5.5vh, 104px);
    padding-inline: clamp(24px, 1.5vw, 34px);
  }
  .tv-nav { width: clamp(42px, 3vw, 64px); height: clamp(42px, 3vw, 64px); }
}
@media (min-width: 1200px) {
  .tv-page:not(.single-panel) {
    width: min(100%, 1700px);
  }
  .tv-page:not(.single-panel) .tv-panel {
    min-height: clamp(500px, 64vh, 760px);
  }
}

/* ═══ اندازه‌ی بزرگ‌تر برای نمایشگر TV ═══ */
@media (min-width: 901px) {
  .tv-carousel { padding-inline: clamp(36px, 3vw, 64px); }
  .tv-page:not(.single-panel) { width: min(calc(100% - 80px), 1800px); gap: 2vw; }
  .tv-page.single-panel { width: min(calc(100% - 80px), 1420px); }
  .tv-page:not(.single-panel) .tv-panel { min-height: clamp(560px, 72vh, 880px); }
  .tv-page.single-panel .tv-panel { min-height: clamp(500px, 68vh, 820px); }
  .tv-panel-head { min-height: 10vh; padding: 1.5vh 1.8vw; }
  .tv-panel-head h2 > span { white-space: nowrap; }
  .tv-row { min-height: clamp(72px, 7.5vh, 104px); padding: .9vh 1.8vw; }
  .tv-nav { width: clamp(46px, 3.4vw, 70px); height: clamp(46px, 3.4vw, 70px); }
  .tv-ticker { height: 46px; }
}
@media (max-width: 680px) {
  .tv-carousel { padding-inline: 38px; }
  .tv-page, .tv-page.active { width: calc(100% - 12px); }
  .tv-page.single-panel { width: calc(100% - 12px); }
  .tv-panel-head { min-height: 8.5vh; padding-inline: 2.5vw; }
  .tv-panel-head h2 > span { white-space: nowrap; }
  .tv-row { min-height: 7.1vh; padding-inline: 2.5vw; }
  .tv-ticker { height: 42px; }
}

/* ═══ مقیاس responsive استاندارد برای TV، دسکتاپ، لپ‌تاپ، تبلت و موبایل ═══ */
@media (min-width: 1600px) {
  .tv-page .tv-panel-head h2 { font-size: clamp(26px, 1.7vw, 34px); }
  .tv-page .tv-panel-head h2 > .tv-market-icon { width: clamp(32px, 1.8vw, 38px); height: clamp(32px, 1.8vw, 38px); flex-basis: clamp(32px, 1.8vw, 38px); }
  .tv-page .tv-col-label { font-size: clamp(15px, .95vw, 22px); }
  .tv-page .tv-name { font-size: clamp(22px, 1.25vw, 29px); }
  .tv-page .tv-buy, .tv-page .tv-sell { font-size: clamp(24px, 1.35vw, 32px); }
  .tv-page .tv-chg { font-size: clamp(17px, 1vw, 25px); }
  .tv-nav i { font-size: clamp(24px, 1.4vw, 32px); }
  .tv-tk-item { font-size: clamp(16px, .9vw, 22px); }
  .tv-tk-chg { font-size: clamp(13px, .75vw, 18px); }
}

@media (min-width: 1200px) and (max-width: 1599px) {
  .tv-page .tv-panel-head h2 { font-size: clamp(22px, 1.6vw, 30px); }
  .tv-page .tv-panel-head h2 > .tv-market-icon { width: clamp(28px, 1.65vw, 34px); height: clamp(28px, 1.65vw, 34px); flex-basis: clamp(28px, 1.65vw, 34px); }
  .tv-page .tv-col-label { font-size: clamp(14px, .9vw, 19px); }
  .tv-page .tv-name { font-size: clamp(18px, 1.1vw, 25px); }
  .tv-page .tv-buy, .tv-page .tv-sell { font-size: clamp(20px, 1.2vw, 29px); }
  .tv-page .tv-chg { font-size: clamp(15px, .9vw, 22px); }
  .tv-nav i { font-size: clamp(22px, 1.25vw, 28px); }
  .tv-tk-item { font-size: clamp(14px, .8vw, 20px); }
  .tv-tk-chg { font-size: clamp(12px, .7vw, 16px); }
}

/* لپ‌تاپ: اندازه‌ی متعادل برای فاصله‌ی دید نزدیک‌تر */
@media (min-width: 901px) and (max-width: 1199px) {
  .tv-page .tv-panel-head h2 { font-size: clamp(19px, 1.25vw, 24px); }
  .tv-page .tv-panel-head h2 > .tv-market-icon { width: clamp(25px, 1.5vw, 30px); height: clamp(25px, 1.5vw, 30px); flex-basis: clamp(25px, 1.5vw, 30px); }
  .tv-page .tv-col-label { font-size: clamp(13px, .8vw, 17px); }
  .tv-page .tv-name { font-size: clamp(17px, 1vw, 22px); }
  .tv-page .tv-buy, .tv-page .tv-sell { font-size: clamp(18px, 1.1vw, 25px); }
  .tv-page .tv-chg { font-size: clamp(14px, .8vw, 19px); }
  .tv-nav i { font-size: clamp(20px, 1.1vw, 25px); }
  .tv-tk-item { font-size: clamp(13px, .7vw, 17px); }
  .tv-tk-chg { font-size: clamp(11px, .6vw, 14px); }
}

@media (min-width: 681px) and (max-width: 900px) {
  .tv-page .tv-panel-head h2 { font-size: clamp(17px, 2.2vw, 21px); }
  .tv-page .tv-panel-head h2 > .tv-market-icon { width: clamp(23px, 3.1vw, 28px); height: clamp(23px, 3.1vw, 28px); flex-basis: clamp(23px, 3.1vw, 28px); }
  .tv-page .tv-col-label { font-size: clamp(12px, 1.7vw, 15px); }
  .tv-page .tv-name { font-size: clamp(15px, 2vw, 19px); }
  .tv-page .tv-buy, .tv-page .tv-sell { font-size: clamp(16px, 2.1vw, 21px); }
  .tv-page .tv-chg { font-size: clamp(12px, 1.7vw, 16px); }
  .tv-nav i { font-size: clamp(18px, 2.8vw, 23px); }
  .tv-tk-item { font-size: clamp(12px, 1.7vw, 15px); }
  .tv-tk-chg { font-size: clamp(10px, 1.4vw, 13px); }
}

@media (max-width: 680px) {
  .tv-page .tv-panel-head h2 { font-size: clamp(15px, 4vw, 18px); }
  .tv-page .tv-panel-head h2 > .tv-market-icon { width: clamp(22px, 6vw, 26px); height: clamp(22px, 6vw, 26px); flex-basis: clamp(22px, 6vw, 26px); }
  .tv-page .tv-col-label { font-size: clamp(10px, 2.7vw, 12px); }
  .tv-page .tv-name { font-size: clamp(14px, 3.7vw, 17px); }
  .tv-page .tv-buy, .tv-page .tv-sell { font-size: clamp(15px, 3.9vw, 18px); }
  .tv-page .tv-chg { font-size: clamp(11px, 3vw, 14px); }
  .tv-nav i { font-size: clamp(18px, 5vw, 22px); }
  .tv-tk-item { font-size: clamp(11px, 3.4vw, 14px); }
  .tv-tk-chg { font-size: clamp(10px, 2.8vw, 12px); }

  .tv-panel-head {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    grid-template-areas: 'title title title' 'change sell buy';
    row-gap: 7px;
  }
  .tv-panel-head h2 { grid-area: title; }
  .tv-panel-head > .tv-col-label:nth-child(2) { grid-area: change; }
  .tv-panel-head > .tv-col-label:nth-child(3) { grid-area: sell; }
  .tv-panel-head > .tv-col-label:nth-child(4) { grid-area: buy; }
  .tv-row {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    grid-template-areas: 'name change' 'sell buy';
    gap: 6px 10px;
    min-height: 0;
    padding-block: 11px;
  }
  .tv-name { grid-area: name; min-width: 0; }
  .tv-row > .tv-chg { grid-area: change; justify-self: end; }
  .tv-row > .tv-sell { grid-area: sell; }
  .tv-row > .tv-buy { grid-area: buy; }
  .tv-row > .tv-chg,
  .tv-row > .tv-sell,
  .tv-row > .tv-buy { border-inline-start: 0; }
  .tv-row > .tv-sell,
  .tv-row > .tv-buy { padding-top: 5px; border-top: 1px solid rgba(148, 163, 184, .12); }
}

/* آیکون نقره افقی است؛ ارتفاع آن با عنوان و عرض آن با نسبت تصویر تنظیم می‌شود */
.tv-page .tv-panel-head h2.tv-panel-title--parsian {
  font-size: .82em;
}
.tv-page .tv-panel-head h2 > .tv-market-icon--silver {
  width: 1.7em;
  height: 1.15em;
  flex-basis: 1.7em;
}
</style>
</head>
<body>

<div class="tv-carousel" id="tvCarousel">
  <button class="tv-nav tv-nav--prev" id="tvPrev" type="button" aria-label="صفحه قبلی">
    <i class="hgi-stroke hgi-arrow-right-01" aria-hidden="true"></i>
  </button>
<main class="tv-main" id="tvMain">
  <?php foreach ($pages as $pi => $page): ?>
  <div class="tv-page <?= $pi === 0 ? 'active' : '' ?><?= count($page) === 1 ? ' single-panel' : '' ?>">
    <?php foreach ($page as $panel): if (empty($panel['items'])) continue; ?>
    <section class="tv-panel<?= $panel['group'] === 'silver' ? ' tv-panel--silver' : '' ?>">
      <div class="tv-panel-head">
        <h2 class="tv-panel-title<?= $panel['group'] === 'parsian' ? ' tv-panel-title--parsian' : '' ?>"><img class="tv-market-icon<?= $panel['group'] === 'silver' ? ' tv-market-icon--silver' : '' ?>" src="assets/img/<?= $panel['group'] === 'coins' ? 'emami.png' : ($panel['group'] === 'silver' ? 'silver.png' : 'gold-icon.png') ?>" alt="" aria-hidden="true"><span><?= meyar_h($panel['title']) ?></span></h2>
        <span class="tv-col-label">تغییر</span>
        <span class="tv-col-label">فروش</span>
        <span class="tv-col-label">خرید</span>
      </div>
      <div class="tv-rows">
        <?php foreach ($panel['items'] as $i) tv_row($i); ?>
      </div>
    </section>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</main>
  <button class="tv-nav tv-nav--next" id="tvNext" type="button" aria-label="صفحه بعدی">
    <i class="hgi-stroke hgi-arrow-left-01" aria-hidden="true"></i>
  </button>
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

<script>
(function () {
  'use strict';

  /* جابه‌جایی دستی صفحات */
  var pages = document.querySelectorAll('.tv-page');
  var prevButton = document.getElementById('tvPrev');
  var nextButton = document.getElementById('tvNext');
  var idx = 0;
  function renderPage(nextIndex) {
    if (pages.length < 2) return;
    idx = (nextIndex + pages.length) % pages.length;
    pages.forEach(function (page, pageIndex) { page.classList.toggle('active', pageIndex === idx); });
  }
  if (pages.length > 1) {
    if (prevButton) prevButton.addEventListener('click', function () { renderPage(idx - 1); });
    if (nextButton) nextButton.addEventListener('click', function () { renderPage(idx + 1); });
  } else {
    if (prevButton) prevButton.disabled = true;
    if (nextButton) nextButton.disabled = true;
  }

  /* بروزرسانی زنده قیمت‌ها */
  function updateCell(cell, val) {
    if (!cell || cell.textContent.trim() === String(val)) return false;
    cell.textContent = val;
    return true;
  }
  function toNumericPrice(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : NaN;
    var text = String(value == null ? '' : value)
      .replace(/[۰-۹]/g, function (digit) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)); })
      .replace(/[٠-٩]/g, function (digit) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)); })
      .replace(/[−–—]/g, '-')
      .replace(/[٬،]/g, ',')
      .replace(/,/g, '')
      .replace(/٫/g, '.')
      .replace(/\s+/g, '')
      .replace(/[^\d.-]/g, '');
    var number = Number(text);
    return Number.isFinite(number) ? number : NaN;
  }
  function animatePriceChange(row, direction) {
    if (!row || !direction) return;
    row.classList.remove('price-flash-up', 'price-flash-down');
    void row.offsetWidth;
    row.classList.add(direction === 'up' ? 'price-flash-up' : 'price-flash-down');
    window.clearTimeout(row._priceFlashTimer);
    row._priceFlashTimer = window.setTimeout(function () {
      row.classList.remove('price-flash-up', 'price-flash-down');
    }, 900);
  }
  function setTvDirection(el, dir, value) {
    if (!el) return;
    var html = (dir === 'high'
      ? '<i class="hgi-stroke hgi-arrow-up-01" aria-hidden="true"></i>'
      : (dir === 'low' ? '<i class="hgi-stroke hgi-arrow-down-01" aria-hidden="true"></i>' : '–')) + ' ' + value + '٪';
    if (el.innerHTML !== html) el.innerHTML = html;
  }
  var priceRequest = null;
  var priceRequestSeq = 0;
  function refresh() {
    if (priceRequest) return priceRequest;
    var requestSeq = ++priceRequestSeq;
    var previousPrices = refresh.previousPrices || (refresh.previousPrices = Object.create(null));
    var hasPriceBaseline = refresh.hasPriceBaseline === true;
    priceRequest = fetch('api/prices.php', { cache: 'no-store' })
      .then(function (r) { if (!r.ok) throw new Error('price_api_failed'); return r.json(); })
      .then(function (data) {
        if (requestSeq !== priceRequestSeq || !data || !data.items) return;
        var byId = {};
        data.items.forEach(function (i) { byId[i.id] = i; });
        document.querySelectorAll('.tv-row[data-id]').forEach(function (row) {
          var assetId = row.getAttribute('data-id');
          var it = byId[assetId];
          if (!it) return;

          var nextPrices = {
            buy: toNumericPrice(it.buy),
            sell: toNumericPrice(it.sell)
          };
          var oldPrices = previousPrices[assetId];
          if (hasPriceBaseline && oldPrices) {
            var buyDirection = Number.isFinite(oldPrices.buy) && Number.isFinite(nextPrices.buy) && nextPrices.buy !== oldPrices.buy
              ? (nextPrices.buy > oldPrices.buy ? 'up' : 'down') : '';
            var sellDirection = Number.isFinite(oldPrices.sell) && Number.isFinite(nextPrices.sell) && nextPrices.sell !== oldPrices.sell
              ? (nextPrices.sell > oldPrices.sell ? 'up' : 'down') : '';
            animatePriceChange(row, sellDirection || buyDirection);
          }
          previousPrices[assetId] = nextPrices;

          updateCell(row.querySelector('[data-cell="buy"]'), it.buy_fmt);
          updateCell(row.querySelector('[data-cell="sell"]'), it.sell_fmt);
          var chg = row.querySelector('[data-cell="chg"]');
          if (chg) {
            setTvDirection(chg, it.dir, it.change_pct);
            var chgClass = 'tv-chg ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : 'flat'));
            if (chg.className !== chgClass) chg.className = chgClass;
          }
        });
        refresh.hasPriceBaseline = true;
        document.querySelectorAll('.tv-tk-item').forEach(function (t) {
          var it = byId[t.getAttribute('data-tid')];
          if (!it) return;
          var tickerPrice = t.querySelector('.tv-tk-price');
          if (tickerPrice && tickerPrice.textContent !== String(it.live_fmt)) tickerPrice.textContent = it.live_fmt;
          var ch = t.querySelector('.tv-tk-chg');
          setTvDirection(ch, it.dir, it.change_pct);
          if (ch) {
            var tickerClass = 'tv-tk-chg ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : ''));
            if (ch.className !== tickerClass) ch.className = tickerClass;
          }
        });
      })
      .catch(function () { /* تلاش بعدی */ })
      .then(function () {
        if (requestSeq === priceRequestSeq) priceRequest = null;
      });
    return priceRequest;
  }
  var refreshTimer = window.setInterval(refresh, 30000);

  /* جلوگیری از خاموش شدن صفحه (Wake Lock) */
  var wakeLock = null;
  function reqWake() {
    if ('wakeLock' in navigator) {
      navigator.wakeLock.request('screen').then(function (l) { wakeLock = l; }).catch(function () {});
    }
  }
  reqWake();
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      window.clearInterval(refreshTimer);
      refreshTimer = null;
      return;
    }
    reqWake();
    refresh();
    if (!refreshTimer) refreshTimer = window.setInterval(refresh, 30000);
  });

  /* رفرش کامل هر ۴ ساعت (پاک شدن حافظه در نمایش طولانی) */
  setTimeout(function () { location.reload(); }, 4 * 3600 * 1000);
})();
</script>
</body>
</html>

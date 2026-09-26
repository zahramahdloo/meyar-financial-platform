<?php
require __DIR__ . '/cz-guard.php';
require_once __DIR__ . '/inc/theme.php';

$settings = meyar_load_settings();
$data     = meyar_build_prices();
$requestedMarket = (string)($_GET['market'] ?? '');
$requestedMarket = in_array($requestedMarket, ['gold', 'coins', 'currency', 'silver'], true) ? $requestedMarket : '';
$items    = array_values(array_filter($data['items'], function ($i) { return empty($i['hidden']); }));
$byGroup  = ['coins'=>[], 'parsian'=>[], 'gold'=>[], 'silver'=>[], 'currency'=>[]];
$marketTitles = [
    'gold'    => 'بازار طلا (آب شده)',
    'currency'=> 'بازار ارز',
    'coins'   => 'بازار سکه',
    'parsian' => 'سکه‌های پارسیان',
    'silver'  => 'بازار نقره',
];
foreach ($items as $i) {
    if ($i['id'] === 'silver999') {
        $byGroup['silver'][] = $i;
    } elseif (isset($byGroup[$i['group']])) {
        $byGroup[$i['group']][] = $i;
    }
}
$currencyOrder = ['usd'=>1, 'eur'=>2, 'aed'=>3, 'try'=>4, 'cny'=>5];
usort($byGroup['currency'], function ($a, $b) use ($currencyOrder) {
    return ($currencyOrder[$a['id']] ?? 99) <=> ($currencyOrder[$b['id']] ?? 99);
});
$tickerIds = ['sekee','sekeb','nim','rob','gerami','geram18','silver999','usd','eur','ons'];
$ticker = array_values(array_filter($items, function ($i) use ($tickerIds) { return in_array($i['id'], $tickerIds, true); }));

meyar_theme_head('همه قیمت‌های بازار | سکه معیار', 'قیمت کامل طلا، سکه، ارز و نقره در بازار سکه معیار.');
meyar_theme_topbar($settings, $ticker);
?>
<main class="container prices-page" id="prices">
  <header class="prices-page-head">
    <a class="prices-back" href="./">بازگشت به صفحه اصلی <i class="hgi-stroke hgi-arrow-right-01" aria-hidden="true"></i></a>
    <div class="prices-page-updated" aria-label="آخرین به‌روزرسانی">
      <span>آخرین به‌روزرسانی</span>
      <strong><?= meyar_h($data['updated_date'] ?? '—') ?> <b aria-hidden="true">—</b> <?= meyar_h($data['updated'] ?? '—') ?></strong>
    </div>
    <h1>قیمت‌های لحظه‌ای بازار</h1>
    <p>مرجع کامل قیمت طلا، سکه، ارز و نقره با به‌روزرسانی آنلاین</p>
  </header>
  <nav class="prices-filter-tabs" aria-label="فیلتر بازار" role="tablist">
    <button type="button" class="prices-filter-tab<?= $requestedMarket === '' ? ' is-active' : '' ?>" data-market-filter="all" role="tab" aria-selected="<?= $requestedMarket === '' ? 'true' : 'false' ?>">همه</button>
    <button type="button" class="prices-filter-tab<?= $requestedMarket === 'gold' ? ' is-active' : '' ?>" data-market-filter="gold" role="tab" aria-selected="<?= $requestedMarket === 'gold' ? 'true' : 'false' ?>">طلا</button>
    <button type="button" class="prices-filter-tab<?= $requestedMarket === 'coins' ? ' is-active' : '' ?>" data-market-filter="coins" role="tab" aria-selected="<?= $requestedMarket === 'coins' ? 'true' : 'false' ?>">سکه</button>
    <button type="button" class="prices-filter-tab<?= $requestedMarket === 'currency' ? ' is-active' : '' ?>" data-market-filter="currency" role="tab" aria-selected="<?= $requestedMarket === 'currency' ? 'true' : 'false' ?>">ارز</button>
    <button type="button" class="prices-filter-tab<?= $requestedMarket === 'silver' ? ' is-active' : '' ?>" data-market-filter="silver" role="tab" aria-selected="<?= $requestedMarket === 'silver' ? 'true' : 'false' ?>">نقره</button>
  </nav>
  <div class="tables-grid">
    <?php foreach (['gold', 'coins', 'currency', 'parsian', 'silver'] as $gid):
        if (empty($byGroup[$gid])) continue;
        $itemsInGroup = $byGroup[$gid];
        $hasExtraItems = count($itemsInGroup) > 5; ?>
    <?php $marketGroup = $gid === 'parsian' ? 'coins' : $gid; $isRequestedGroup = $requestedMarket !== '' && $requestedMarket === $marketGroup; ?>
    <section class="price-card<?= $hasExtraItems ? ' has-expand' : '' ?><?= $requestedMarket !== '' && !$isRequestedGroup ? ' is-filtered-out' : '' ?>" data-market-container data-market-group="<?= meyar_h($marketGroup) ?>">
      <header class="price-card-head">
        <div class="price-card-title">
          <span class="price-card-icon" aria-hidden="true">
            <?php if ($gid === 'currency'): ?>
              <i class="hgi hgi-stroke hgi-rounded hgi-cash-02"></i>
            <?php else: ?>
              <img class="market-icon-image" src="assets/img/<?= $gid === 'gold' ? 'gold-icon.png' : ($gid === 'silver' ? 'silver.png' : 'emami.png') ?>" alt="">
            <?php endif; ?>
          </span>
          <h2><?= meyar_h($marketTitles[$gid]) ?></h2>
        </div>
      </header>
      <div class="market-columns-head" aria-hidden="true">
        <i class="market-columns-spacer" aria-hidden="true"></i><span>تغییر</span><span>فروش</span><span>خرید</span>
      </div>
      <div class="market-list" data-collapsible-table role="list">
        <?php foreach ($itemsInGroup as $index => $i): ?>
        <a class="market-asset<?= $index >= 5 ? ' is-extra' : '' ?>" href="price/<?= meyar_h($i['id']) ?>" data-id="<?= meyar_h($i['id']) ?>" role="listitem">
          <span class="market-asset-title">
            <span><?= meyar_h($i['title']) ?></span>
          </span>
          <span class="market-asset-quote buy">
            <small>خرید</small>
            <strong data-cell="buy"><?= meyar_h($i['buy_fmt']) ?></strong>
          </span>
          <span class="market-asset-quote sell">
            <small>فروش</small>
            <strong data-cell="sell"><?= meyar_h($i['sell_fmt']) ?></strong>
          </span>
          <span class="market-asset-change <?= $i['dir'] === 'high' ? 'up' : ($i['dir'] === 'low' ? 'down' : 'flat') ?>" data-cell="chg"><?= meyar_h($i['change_pct']) ?>٪ <span class="market-asset-trend" aria-hidden="true"><i class="hgi-stroke <?= $i['dir'] === 'high' ? 'hgi-arrow-up-right-01' : ($i['dir'] === 'low' ? 'hgi-arrow-down-right-01' : 'hgi-arrow-right-01') ?>"></i></span></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php if ($hasExtraItems): ?>
      <button type="button" class="table-expand-toggle" aria-expanded="false" aria-label="نمایش موارد بیشتر"><span aria-hidden="true">⌄</span><span class="table-expand-label">نمایش بیشتر</span></button>
      <?php endif; ?>
    </section>
    <?php endforeach; ?>
  </div>
</main>
<?php meyar_theme_footer($settings); ?>

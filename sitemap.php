<?php
/** MEYAR — نقشه سایت XML برای گوگل */
require_once __DIR__ . '/inc/fetcher.php';

header('Content-Type: application/xml; charset=utf-8');

$base = meyar_public_url();
if ($base === '') $base = 'https://sekemeyar.com';

function meyar_sitemap_loc(string $url): string {
    return htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '  <url><loc>' . meyar_sitemap_loc($base . '/') . "</loc></url>\n";
try {
    $data = meyar_build_prices();
} catch (Throwable $e) {
    error_log('Meyar sitemap error: ' . $e->getMessage());
    http_response_code(503);
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';
    exit;
}
$publicItems = array_values(array_filter($data['items'], function (array $item): bool {
    return empty($item['hidden']);
}));
$availableGroups = [];
foreach ($publicItems as $item) {
    $availableGroups[$item['group']] = true;
    if (in_array($item['id'], ['silver999', 'silver_ons'], true)) $availableGroups['silver'] = true;
}
$marketGroups = [
    'gold'     => ['gold'],
    'coins'    => ['coins', 'parsian'],
    'currency' => ['currency'],
    'silver'   => ['silver'],
];
foreach (['', 'gold', 'coins', 'currency', 'silver'] as $market) {
    if ($market !== '' && !array_filter($marketGroups[$market], function (string $group) use ($availableGroups): bool {
        return !empty($availableGroups[$group]);
    })) continue;
    echo '  <url><loc>' . meyar_sitemap_loc(meyar_public_url_for(meyar_market_canonical_path($market))) . "</loc></url>\n";
}

foreach ($publicItems as $i) {
    $loc = $base . '/price/' . rawurlencode((string)$i['id']);
    echo '  <url><loc>' . meyar_sitemap_loc($loc) . "</loc></url>\n";
}
echo '</urlset>';

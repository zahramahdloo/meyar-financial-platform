<?php
/** MEYAR — نقشه سایت XML برای گوگل */
require_once __DIR__ . '/inc/db.php';

header('Content-Type: application/xml; charset=utf-8');

$base = meyar_public_url();
if ($base === '') {
    http_response_code(503);
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';
    exit;
}

function meyar_sitemap_loc(string $url): string {
    return htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '  <url><loc>' . meyar_sitemap_loc($base . '/') . "</loc></url>\n";
echo '  <url><loc>' . meyar_sitemap_loc($base . '/prices.php') . "</loc></url>\n";

$settings = meyar_load_settings();
$adj = (array)($settings['adjustments'] ?? []);
foreach (meyar_items_full() as $i) {
    $a = (array)($adj[$i['id']] ?? []);
    if (!empty($a['hidden'])) continue;
    $loc = $base . '/price/' . rawurlencode($i['id']);
    echo '  <url><loc>' . meyar_sitemap_loc($loc) . "</loc></url>\n";
}
echo '</urlset>';

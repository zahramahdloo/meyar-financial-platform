<?php
/** MEYAR — نقشه سایت XML برای گوگل */
require_once __DIR__ . '/inc/db.php';

header('Content-Type: application/xml; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$today  = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo "  <url><loc>{$base}/</loc><lastmod>{$today}</lastmod><changefreq>hourly</changefreq><priority>1.0</priority></url>\n";

$settings = meyar_load_settings();
$adj = (array)($settings['adjustments'] ?? []);
foreach (meyar_items_full() as $i) {
    $a = (array)($adj[$i['id']] ?? []);
    if (!empty($a['hidden'])) continue;
    $loc = $base . '/price/' . rawurlencode($i['id']);
    echo "  <url><loc>{$loc}</loc><lastmod>{$today}</lastmod><changefreq>hourly</changefreq><priority>0.8</priority></url>\n";
}
echo '</urlset>';

<?php
/** MEYAR — خروجی JSON قیمت‌ها برای فرانت‌اند (بروزرسانی خودکار) */
require_once dirname(__DIR__) . '/inc/fetcher.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$data = meyar_build_prices();
// آیتم‌های مخفی‌شده توسط ادمین در خروجی عمومی نمی‌آیند
$data['items'] = array_values(array_filter($data['items'], function ($i) { return empty($i['hidden']); }));
foreach ($data['items'] as &$i) { unset($i['base']); }
unset($i);

echo json_encode($data, JSON_UNESCAPED_UNICODE);

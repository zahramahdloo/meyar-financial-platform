<?php
/** MEYAR — خروجی JSON قیمت‌ها برای فرانت‌اند (بروزرسانی خودکار) */
require_once dirname(__DIR__) . '/inc/api.php';
meyar_api_begin();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') meyar_api_error(405, 'method_not_allowed', 'این درخواست پشتیبانی نمی‌شود.');
    require_once dirname(__DIR__) . '/inc/fetcher.php';
    $data = meyar_build_prices();
    // آیتم‌های مخفی‌شده توسط ادمین در خروجی عمومی نمی‌آیند
    $data['items'] = array_values(array_filter($data['items'], function ($i) { return empty($i['hidden']); }));
    foreach ($data['items'] as &$i) { unset($i['base']); }
    unset($i);
    meyar_api_response($data);
} catch (Throwable $e) {
    error_log('Meyar prices API error: ' . $e->getMessage());
    meyar_api_error(500, 'server_error');
}

<?php
/** MEYAR — تاریخچه قیمت یک آیتم برای نمودار (تاریخ شمسی) — همیشه JSON برمی‌گرداند */
@set_time_limit(25);
require_once dirname(__DIR__) . '/inc/api.php';
meyar_api_begin();
header('Cache-Control: public, max-age=300');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') meyar_api_error(405, 'method_not_allowed', 'این درخواست پشتیبانی نمی‌شود.');
    require_once dirname(__DIR__) . '/inc/fetcher.php';

    $id = (string)($_GET['id'] ?? '');
    $daysRaw = (string)($_GET['days'] ?? '365');
    if (!meyar_api_valid_id($id) || !preg_match('/^[0-9]{1,4}$/', $daysRaw)) {
        meyar_api_error(400, 'bad_request', 'پارامترهای تاریخچه معتبر نیستند.');
    }
    $days = (int)$daysRaw;
    if ($days < 7 || $days > 4000) meyar_api_error(400, 'bad_range', 'بازه تاریخچه معتبر نیست.');

    $item = $id ? meyar_item_by_id($id) : null;
    if (!$item) {
        meyar_api_error(404, 'not_found', 'آیتم موردنظر پیدا نشد.');
    }

    $hist = meyar_item_history($item, $days);
    if (!$hist) {
        meyar_api_error(503, 'no_data', 'تاریخچه این آیتم فعلاً در دسترس نیست.');
    }

    meyar_api_response([
        'ok'     => true,
        'id'     => $item['id'],
        'title'  => $item['title'],
        'points' => $hist, // [{g,j,v}]
    ]);
} catch (Throwable $e) {
    error_log('Meyar history API error: ' . $e->getMessage());
    meyar_api_error(500, 'server_error');
}

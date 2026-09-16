<?php
/** MEYAR — تاریخچه قیمت یک آیتم برای نمودار (تاریخ شمسی) — همیشه JSON برمی‌گرداند */
error_reporting(0);
ini_set('display_errors', '0');
@set_time_limit(25);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

// اگر خطای مهلک رخ داد، باز هم JSON بده (نه صفحه HTML خطا)
ob_start();
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level()) ob_end_clean();
        echo json_encode(['ok' => false, 'error' => 'server_error']);
    }
});

try {
    require_once dirname(__DIR__) . '/inc/fetcher.php';

    $id   = preg_replace('/[^a-z0-9_]/i', '', (string)($_GET['id'] ?? ''));
    $days = max(7, min(4000, (int)($_GET['days'] ?? 365)));

    $item = $id ? meyar_item_by_id($id) : null;
    if (!$item) {
        while (ob_get_level()) ob_end_clean();
        echo json_encode(['ok' => false, 'error' => 'not_found']); exit;
    }

    $hist = meyar_item_history($item, $days);
    while (ob_get_level()) ob_end_clean();

    if (!$hist) {
        echo json_encode(['ok' => false, 'error' => 'no_data']); exit;
    }

    echo json_encode([
        'ok'     => true,
        'id'     => $item['id'],
        'title'  => $item['title'],
        'points' => $hist, // [{g,j,v}]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}

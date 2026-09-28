<?php
/** MEYAR — خلاصه وضعیت چهار بازار مهم */
require_once dirname(__DIR__) . '/inc/api.php';
meyar_api_begin();

function market_insight_response(array $payload, int $status = 200): void {
    http_response_code($status);
    meyar_api_response($payload, $status);
}

function market_insight_status(string $direction): string {
    return ['high' => 'افزایش', 'low' => 'کاهش', 'flat' => 'بدون تغییر'][$direction] ?? 'نامشخص';
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        market_insight_response(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'این درخواست پشتیبانی نمی‌شود.'], 405);
    }
    require_once dirname(__DIR__) . '/inc/fetcher.php';
    $data = meyar_build_prices();
    $items = [];
    foreach ((array)($data['items'] ?? []) as $item) $items[(string)($item['id'] ?? '')] = $item;

    $markets = [
        'usd' => 'دلار',
        'geram18' => 'طلا',
        'sekee' => 'سکه',
        'silver999' => 'نقره',
    ];
    $records = [];
    $up = 0;
    $down = 0;
    foreach ($markets as $id => $label) {
        $item = $items[$id] ?? null;
        $available = is_array($item) && empty($item['hidden']) && is_numeric($item['live']) && (float)$item['live'] > 0;
        $direction = $available && ($item['dir'] ?? '') === 'high' ? 'high' : ($available && ($item['dir'] ?? '') === 'low' ? 'low' : 'flat');
        if ($direction === 'high') $up++;
        if ($direction === 'low') $down++;
        $change = $available ? (string)($item['change_pct'] ?? '۰') : '';
        $title = $available ? $label . ': ' . market_insight_status($direction) . ($change !== '' ? ' ' . $change . '٪' : '') : $label . ': داده در دسترس نیست';
        $records[] = [
            'title' => $title,
            'topic' => $label,
            'trend' => $direction === 'high' ? 'up' : ($direction === 'low' ? 'down' : 'flat'),
            'direction' => $direction === 'high' ? 'positive' : ($direction === 'low' ? 'negative' : 'neutral'),
            'source' => 'market_data',
        ];
    }
    $overall = $up > $down ? 'up' : ($down > $up ? 'down' : 'flat');
    market_insight_response([
        'ok' => true,
        'success' => true,
        'cached' => false,
        'insight' => [
            'market' => 'multi',
            'asset' => 'important_markets',
            'trend' => $overall,
            'trend_label' => $overall === 'up' ? 'صعودی' : ($overall === 'down' ? 'نزولی' : 'خنثی'),
            'confidence' => 1,
            'generated_at' => date(DATE_ATOM),
            'insights' => $records,
            'source' => 'market_data',
        ],
    ]);
} catch (Throwable $e) {
    error_log('Meyar market insight error: ' . $e->getMessage());
    market_insight_response(['ok' => false, 'error' => 'server_error', 'message' => 'خلاصه بازار فعلاً در دسترس نیست.'], 500);
}

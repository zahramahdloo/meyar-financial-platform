<?php
/** MEYAR — تحلیل هوشمند بازار با OpenAI */
require_once dirname(__DIR__) . '/inc/fetcher.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cacheFile = MEYAR_DATA . '/market_insight_cache.json';
$cacheTtl  = 900;
$apiKey    = meyar_env('OPENAI_API_KEY');

function market_insight_response(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($apiKey === '') {
    market_insight_response(['ok' => false, 'error' => 'not_configured'], 503);
}

if (is_file($cacheFile)) {
    $cached = json_decode((string)@file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['created_at'], $cached['insights'])
        && time() - (int)$cached['created_at'] < $cacheTtl
        && is_array($cached['insights'])) {
        unset($cached['created_at']);
        market_insight_response(['ok' => true, 'insight' => $cached, 'cached' => true]);
    }
}

$data = meyar_build_prices();
$items = [];
foreach ((array)($data['items'] ?? []) as $item) {
    if (!empty($item['hidden']) || !in_array($item['id'], ['geram18', 'usd', 'sekee', 'silver999', 'ons'], true)) continue;
    $items[] = [
        'id'        => (string)$item['id'],
        'name'      => (string)$item['title'],
        'price'     => (string)$item['live_fmt'],
        'change'    => (string)$item['change_pct'],
        'direction' => (string)$item['dir'],
    ];
}

$marketJson = json_encode([
    'updated' => (string)($data['updated'] ?? ''),
    'markets' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$prompt = 'بر اساس داده‌های لحظه‌ای بازار زیر، یک خلاصه کوتاه و محتاطانه به زبان فارسی برای کارت تحلیل بازار سایت معیار تولید کن.'
    . ' فقط JSON معتبر با ساختار زیر برگردان و هیچ متن دیگری اضافه نکن:'
    . ' {"trend":"up|down|flat","trend_label":"صعودی|نزولی|خنثی","insights":["...", "...", "...", "..."]}.'
    . ' دقیقاً ۴ نکته کوتاه، واقعی و غیرتکراری بنویس. از پیش‌بینی قطعی، توصیه خرید و فروش و ادعای علت قطعی خودداری کن.'
    . ' اگر داده کافی نیست، از عبارت‌های احتمالی مثل «می‌تواند» استفاده کن. داده‌ها: ' . $marketJson;

$requestBody = json_encode([
    'model' => meyar_env('OPENAI_MODEL') ?: 'gpt-5-mini',
    'store' => false,
    'input' => [
        [
            'role' => 'system',
            'content' => [[
                'type' => 'input_text',
                'text' => 'تو تحلیل‌گر خلاصه‌نویس بازار مالی هستی. خروجی فقط JSON معتبر باشد.',
            ]],
        ],
        [
            'role' => 'user',
            'content' => [['type' => 'input_text', 'text' => $prompt]],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
]);
$raw = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($raw === false || $httpCode < 200 || $httpCode >= 300) {
    market_insight_response(['ok' => false, 'error' => 'openai_request_failed'], 502);
}

$response = json_decode((string)$raw, true);
$text = trim((string)($response['output_text'] ?? ''));
if ($text === '' && !empty($response['output']) && is_array($response['output'])) {
    foreach ($response['output'] as $output) {
        foreach ((array)($output['content'] ?? []) as $content) {
            if (($content['type'] ?? '') === 'output_text') {
                $text .= (string)($content['text'] ?? '');
            }
        }
    }
}
$text = trim(preg_replace('/^\x60\x60\x60(?:json)?|\x60\x60\x60$/m', '', $text));
$insight = json_decode($text, true);

if (!is_array($insight) || !is_array($insight['insights'] ?? null)) {
    market_insight_response(['ok' => false, 'error' => 'invalid_openai_response'], 502);
}

$trend = in_array($insight['trend'] ?? '', ['up', 'down', 'flat'], true) ? $insight['trend'] : 'flat';
$labels = ['up' => 'صعودی', 'down' => 'نزولی', 'flat' => 'خنثی'];
$insights = array_values(array_filter(array_map(function ($value) {
    return trim(mb_substr((string)$value, 0, 120));
}, array_slice($insight['insights'], 0, 4))));
if (count($insights) < 4) {
    market_insight_response(['ok' => false, 'error' => 'incomplete_openai_response'], 502);
}

$normalized = [
    'trend'       => $trend,
    'trend_label' => $labels[$trend],
    'insights'    => $insights,
    'created_at'  => time(),
];
@file_put_contents($cacheFile, json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
unset($normalized['created_at']);
market_insight_response(['ok' => true, 'insight' => $normalized, 'cached' => false]);

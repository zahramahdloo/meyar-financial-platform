<?php
/** MEYAR — جمع‌بندی داده‌محور بازار */
require_once dirname(__DIR__) . '/inc/api.php';
meyar_api_begin();

function market_insight_response(array $payload, int $status = 200): void {
    http_response_code($status);
    meyar_api_response($payload, $status);
}

function market_insight_label(string $trend): string {
    return [
        'up' => 'صعودی', 'down' => 'نزولی', 'stable' => 'باثبات', 'mixed' => 'نامشخص',
    ][$trend] ?? 'نامشخص';
}

function market_insight_title(string $text): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    return mb_substr($text, 0, 95);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') market_insight_response(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'این درخواست پشتیبانی نمی‌شود.'], 405);
    require_once dirname(__DIR__) . '/inc/fetcher.php';
    $data = meyar_build_prices();
$target = null;
foreach ((array)($data['items'] ?? []) as $item) {
    if (($item['id'] ?? '') === 'geram18' && empty($item['hidden'])) {
        $target = $item;
        break;
    }
}
if (!is_array($target) || !is_numeric($target['live'] ?? null)) {
    market_insight_response(['ok' => false, 'error' => 'market_data_unavailable'], 503);
}

$historyFile = MEYAR_DATA . '/history_geram18.json';
$historyJson = is_file($historyFile) ? json_decode((string)@file_get_contents($historyFile), true) : null;
$historyRows = is_array($historyJson) ? (array)($historyJson['rows'] ?? []) : [];
$historyValues = [];
foreach ($historyRows as $row) {
    $value = (float)($row['v'] ?? 0);
    if ($value > 0) $historyValues[] = $value;
}

$current = (float)$target['live'];
$series = array_values(array_filter(array_slice($historyValues, -7), static fn ($value) => $value > 0));
$series[] = $current;
$recent = array_slice($series, -5);
$upMoves = 0;
$downMoves = 0;
$epsilon = max(abs($current) * 0.0001, 0.01);
for ($i = 1, $count = count($recent); $i < $count; $i++) {
    $delta = $recent[$i] - $recent[$i - 1];
    if ($delta > $epsilon) $upMoves++;
    elseif ($delta < -$epsilon) $downMoves++;
}

$reference = count($recent) > 1 ? $recent[0] : $current;
$windowChange = $reference > 0 ? (($current - $reference) / $reference) * 100 : 0;
$range = count($recent) ? max($recent) - min($recent) : 0;
$average = count($recent) ? array_sum($recent) / count($recent) : $current;
$volatility = $average > 0 ? ($range / $average) * 100 : 0;

if (count($recent) < 3) $trend = 'mixed';
elseif (abs($windowChange) < 0.12 && abs($upMoves - $downMoves) <= 1) $trend = 'stable';
elseif ($upMoves >= $downMoves + 2 && $windowChange > 0.08) $trend = 'up';
elseif ($downMoves >= $upMoves + 2 && $windowChange < -0.08) $trend = 'down';
else $trend = 'mixed';

$assetName = trim((string)($target['title'] ?? 'طلای ۱۸ عیار'));
if ($trend === 'up') {
    $insights = ["روند صعودی قیمت {$assetName}", 'افزایش خالص قیمت در بازه اخیر', $volatility > 0.8 ? 'افزایش دامنه نوسان کوتاه‌مدت' : 'تداوم حرکت مثبت در داده‌های اخیر'];
} elseif ($trend === 'down') {
    $insights = ["روند نزولی قیمت {$assetName}", 'کاهش خالص قیمت در بازه اخیر', $volatility > 0.8 ? 'افزایش دامنه نوسان کوتاه‌مدت' : 'تداوم حرکت منفی در داده‌های اخیر'];
} elseif ($trend === 'stable') {
    $insights = ["ثبات نسبی قیمت {$assetName}", 'کاهش جهت‌گیری غالب در حرکت اخیر', $volatility < 0.35 ? 'دامنه نوسان کوتاه‌مدت محدود است' : 'نوسان محدود پیرامون سطح اخیر'];
} else {
    $insights = ["نوسان رفت‌وبرگشتی قیمت {$assetName}", 'جهت غالبی در حرکت اخیر مشاهده نشد', 'برای جمع‌بندی قطعی، داده بیشتری لازم است'];
}
$insights = array_values(array_unique(array_map('market_insight_title', $insights)));
$insightRecords = [];
foreach ($insights as $index => $title) {
    $insightRecords[] = [
        'title' => $title,
        'type' => $index === 0 ? 'trend' : ($index === 2 ? 'volatility' : 'movement'),
        'direction' => $trend === 'up' ? 'positive' : ($trend === 'down' ? 'negative' : 'neutral'),
        'source' => 'market_data',
    ];
}
$confidence = count($recent) >= 3 ? min(0.95, max(0.45, (abs($upMoves - $downMoves) + 1) / max(1, $upMoves + $downMoves + 1))) : 0.35;

    market_insight_response([
    'ok' => true,
    'success' => true,
    'cached' => false,
    'insight' => [
        'market' => 'gold', 'asset' => 'geram18', 'trend' => $trend,
        'trend_label' => market_insight_label($trend), 'confidence' => round($confidence, 2),
        'generated_at' => date(DATE_ATOM), 'insights' => $insightRecords, 'source' => 'market_data',
    ],
    ]);
} catch (Throwable $e) {
    error_log('Meyar market insight API error: ' . $e->getMessage());
    market_insight_response(['ok' => false, 'error' => 'server_error', 'message' => 'تحلیل بازار فعلاً در دسترس نیست.'], 500);
}

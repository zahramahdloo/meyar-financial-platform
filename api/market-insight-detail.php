<?php
/** MEYAR — تحلیل قاعده‌محور داده‌های بازار */
require_once dirname(__DIR__) . '/inc/api.php';
meyar_api_begin();

const MEYAR_MARKET_DETAIL_CACHE_TTL = 300;

function market_detail_response(array $payload, int $status = 200): void {
    http_response_code($status);
    meyar_api_response($payload, $status);
}

function market_detail_read_history(string $file, string $localKey = ''): array {
    $rows = [];
    if ($localKey !== '') {
        $local = meyar_read_json_file($file, []);
        foreach ((array)($local[$localKey] ?? []) as $date => $value) {
            if (is_numeric($value) && (float)$value > 0) $rows[] = ['date' => (string)$date, 'value' => (float)$value];
        }
    } else {
        $json = meyar_read_json_file($file, []);
        foreach ((array)($json['rows'] ?? []) as $row) {
            $value = (float)($row['v'] ?? 0);
            if ($value > 0) $rows[] = ['date' => (string)($row['g'] ?? ''), 'value' => $value];
        }
    }
    return array_slice($rows, -7);
}

function market_detail_number($value): ?float {
    if ($value === null || $value === '') return null;
    $value = strtr((string)$value, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        '٬'=>',','،'=>',','٫'=>'.','−'=>'-','٪'=>'',
    ]);
    $value = str_replace(',', '', $value);
    $value = preg_replace('/[^0-9.\-]/', '', $value);
    return $value !== '' && is_numeric($value) ? (float)$value : null;
}

function market_detail_snapshot(array $data): array {
    $definitions = [
        'usd' => ['name' => 'دلار آمریکا', 'history' => MEYAR_DATA . '/history_price_dollar_rl.json'],
        'geram18' => ['name' => 'طلای ۱۸ عیار', 'history' => MEYAR_DATA . '/history_geram18.json'],
        'sekee' => ['name' => 'سکه امامی', 'history' => MEYAR_DATA . '/history_sekee.json'],
        'silver999' => ['name' => 'نقره ۹۹۹.۹', 'history' => MEYAR_DATA . '/local_history.json', 'local_key' => 'silver_999'],
    ];
    $items = [];
    foreach ((array)($data['items'] ?? []) as $item) {
        $id = (string)($item['id'] ?? '');
        if ($id !== '') $items[$id] = $item;
    }
    $markets = [];
    foreach ($definitions as $id => $definition) {
        $item = $items[$id] ?? null;
        $markets[] = [
            'id' => $id,
            'name' => $definition['name'],
            'available' => is_array($item) && empty($item['hidden']) && is_numeric($item['live']) && (float)$item['live'] > 0,
            'price' => is_array($item) ? (string)($item['live_fmt'] ?? '') : '',
            'price_value' => is_array($item) ? (float)($item['live'] ?? 0) : 0,
            'change_percent' => is_array($item) ? (string)($item['change_pct'] ?? '') : '',
            'direction' => is_array($item) ? (string)($item['dir'] ?? '') : '',
            'unit' => is_array($item) ? (string)($item['unit'] ?? '') : '',
            'history' => market_detail_read_history((string)$definition['history'], (string)($definition['local_key'] ?? '')),
        ];
    }
    return [
        'updated_at' => (int)($data['fetched_at'] ?? 0),
        'updated_display' => (string)($data['updated'] ?? '—'),
        'stale' => (bool)($data['stale'] ?? true),
        'markets' => $markets,
    ];
}

function market_detail_direction(array $market): string {
    if (in_array($market['direction'], ['high', 'low'], true)) return $market['direction'];
    $change = market_detail_number($market['change_percent']);
    if ($change !== null && abs($change) < 0.000001) return 'flat';
    $history = $market['history'];
    if (count($history) >= 2) {
        $previous = (float)$history[count($history) - 2]['value'];
        $latest = (float)$history[count($history) - 1]['value'];
        if ($latest > $previous) return 'high';
        if ($latest < $previous) return 'low';
    }
    return 'flat';
}

function market_detail_status_label(string $direction): string {
    return ['high' => 'افزایش', 'low' => 'کاهش', 'flat' => 'بدون تغییر محسوس'][$direction] ?? 'نامشخص';
}

function market_detail_percent_text(?float $value): string {
    return $value === null ? '' : meyar_fa_num(number_format(abs($value), 2));
}

function market_detail_market_analysis(array $market, bool $stale): array {
    if (!$market['available']) {
        return [
            'title' => $market['name'] . ': داده ناکافی',
            'explanation' => 'برای ' . $market['name'] . ' قیمت معتبر در داده‌های فعلی سایت موجود نیست؛ بنابراین درباره جهت حرکت آن نتیجه‌گیری نمی‌شود.',
            'factor' => $market['name'] . ': قیمت معتبر موجود نیست.',
        ];
    }

    $direction = market_detail_direction($market);
    $status = market_detail_status_label($direction);
    $change = market_detail_number($market['change_percent']);
    $changeText = market_detail_percent_text($change);
    $prefix = $stale ? 'آخرین داده ذخیره‌شده ممکن است قدیمی باشد. ' : '';
    $observed = $prefix . 'قیمت ثبت‌شده برای ' . $market['name'] . ' ' . (string)$market['price'];
    if ($market['unit'] !== '') $observed .= ' ' . $market['unit'];
    $observed .= ' است و وضعیت ثبت‌شده «' . $status . '» است.';
    if ($changeText !== '') $observed .= ' تغییر درصدی ثبت‌شده: ' . $changeText . '٪.';

    $history = $market['history'];
    $historyNote = 'برای مقایسه‌ی تاریخچه، مشاهده‌ی معتبر کافی وجود ندارد.';
    if (count($history) >= 2) {
        $previous = (float)$history[count($history) - 2]['value'];
        $latest = (float)$history[count($history) - 1]['value'];
        if ($previous > 0 && $latest > 0) {
            $historyChange = (($latest - $previous) / $previous) * 100;
            $historyDirection = $historyChange > 0 ? 'افزایش' : ($historyChange < 0 ? 'کاهش' : 'بدون تغییر محسوس');
            $historyNote = 'در دو مشاهده‌ی اخیر تاریخچه نیز ' . $historyDirection . ' ثبت شده است';
            if (abs($historyChange) >= 0.000001) $historyNote .= ' (' . market_detail_percent_text($historyChange) . '٪)';
            $historyNote .= '.';
        }
    }

    return [
        'title' => $market['name'] . ': ' . $status . ($changeText !== '' ? ' ' . $changeText . '٪' : ''),
        'explanation' => $observed . ' ' . $historyNote . ' از این داده‌ها فقط جهت حرکت قیمت و میزان تغییر قابل مشاهده است؛ علت بیرونی یا پیش‌بینی آینده در داده‌های سایت وجود ندارد.',
        'factor' => $market['name'] . ': ' . $status . ($changeText !== '' ? ' ' . $changeText . '٪' : ''),
    ];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        market_detail_response(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'این درخواست پشتیبانی نمی‌شود.'], 405);
    }
    if (!meyar_api_rate_limit('market-insight-detail', 6, 600)) {
        market_detail_response(['ok' => false, 'error' => 'rate_limited', 'message' => 'تعداد درخواست‌های تحلیل بیش از حد مجاز است.'], 429);
    }

    $topic = trim((string)($_GET['topic'] ?? 'جمع‌بندی چهار بازار مهم'));
    $trend = (string)($_GET['trend'] ?? 'flat');
    if (!meyar_api_valid_topic($topic) || !in_array($trend, ['up', 'down', 'flat'], true)) {
        market_detail_response(['ok' => false, 'error' => 'bad_request', 'message' => 'پارامترهای تحلیل معتبر نیستند.'], 400);
    }
    $topic = trim((string)preg_replace('/\s+/u', ' ', $topic));

    require_once dirname(__DIR__) . '/inc/fetcher.php';
    $snapshot = market_detail_snapshot(meyar_build_prices());
    $availableMarkets = array_filter($snapshot['markets'], function ($market) { return !empty($market['available']); });
    if (!$availableMarkets) {
        market_detail_response(['ok' => false, 'error' => 'market_data_unavailable', 'message' => 'داده معتبر بازار برای تحلیل در دسترس نیست.'], 503);
    }

    $cacheKey = hash('sha256', 'rule-based-v1|' . $topic . '|' . $trend . '|' . json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $cacheFile = MEYAR_DATA . '/market_rule_detail_' . $cacheKey . '.json';
    if (is_file($cacheFile) && time() - (int)@filemtime($cacheFile) < MEYAR_MARKET_DETAIL_CACHE_TTL) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['explanation'])) {
            market_detail_response(['ok' => true, 'detail' => $cached, 'cached' => true, 'source' => 'rule_based']);
        }
    }

    $analyses = [];
    foreach ($snapshot['markets'] as $market) $analyses[] = market_detail_market_analysis($market, $snapshot['stale']);
    $validCount = count($availableMarkets);
    $updatedText = $snapshot['updated_display'] !== '' ? $snapshot['updated_display'] : 'نامشخص';
    $staleText = $snapshot['stale'] ? 'داده‌ها stale هستند و ممکن است به‌روز نباشند.' : 'داده‌ها stale نیستند.';
    $paragraphs = ['این تحلیل به‌صورت قاعده‌محور و فقط از داده‌های واقعی سایت تولید شده است. زمان آخرین به‌روزرسانی ثبت‌شده: ' . $updatedText . '؛ ' . $staleText . ''];
    foreach ($analyses as $analysis) $paragraphs[] = $analysis['explanation'];
    if ($validCount < count($snapshot['markets'])) $paragraphs[] = 'برای بخشی از بازارها داده معتبر موجود نبود؛ نتیجه‌ی آن بخش قابل اتکا نیست.';

    $detail = [
        'source' => 'rule_based',
        'title' => 'تحلیل قاعده‌محور بازار',
        'topic' => $topic,
        'explanation' => implode(PHP_EOL . PHP_EOL, $paragraphs),
        'factors' => array_map(function ($analysis) { return $analysis['factor']; }, $analyses),
        'disclaimer' => 'این تحلیل فقط وضعیت مشاهده‌شده‌ی قیمت‌ها را توضیح می‌دهد و توصیه خرید یا فروش یا پیش‌بینی قطعی نیست.',
    ];
    meyar_write_json_file($cacheFile, $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    market_detail_response(['ok' => true, 'detail' => $detail, 'cached' => false, 'source' => 'rule_based']);
} catch (Throwable $e) {
    error_log('Meyar market detail error: ' . $e->getMessage());
    market_detail_response(['ok' => false, 'error' => 'server_error', 'message' => 'تحلیل فعلاً در دسترس نیست.'], 500);
}

<?php
/**
 * MEYAR — دریافت قیمت‌های لحظه‌ای از TGJU با کش و چند سرور پشتیبان
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';

/** دریافت خام از TGJU (با fallback بین سرورها) */
function meyar_fetch_tgju_raw(): ?array {
    $endpoints = [
        'https://call2.tgju.org/ajax.json',
        'https://call1.tgju.org/ajax.json',
        'https://call3.tgju.org/ajax.json',
        'https://call4.tgju.org/ajax.json',
        'https://call.tgju.org/ajax.json',
    ];
    foreach ($endpoints as $url) {
        $body = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Referer: https://www.tgju.org/'],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code !== 200) { $body = null; }
        } else {
            $ctx = stream_context_create(['http' => [
                'timeout' => 20,
                'header'  => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\nReferer: https://www.tgju.org/\r\n",
            ]]);
            $body = @file_get_contents($url, false, $ctx) ?: null;
        }
        if ($body) {
            $json = json_decode($body, true);
            if (is_array($json) && !empty($json['current'])) {
                return $json['current'];
            }
        }
    }
    return null;
}

/** عدد از رشته «1,790,050,000» */
function meyar_num($str): ?float {
    if ($str === null || $str === '') return null;
    $clean = str_replace([',', ' '], '', (string)$str);
    return is_numeric($clean) ? (float)$clean : null;
}

/**
 * قیمت‌های نهایی: از کش یا شبکه. خروجی:
 * ['fetched_at'=>int, 'stale'=>bool, 'current'=>['sekee'=>['p'=>..,'dp'=>..,'dt'=>..,'t'=>..], ...]]
 */
function meyar_get_market(): array {
    $settings = meyar_load_settings();
    $ttl = max(20, (int)$settings['cache_ttl']);

    $cache = null;
    if (is_file(MEYAR_CACHE_FILE)) {
        $cache = json_decode(@file_get_contents(MEYAR_CACHE_FILE) ?: '', true);
    }
    if (is_array($cache) && isset($cache['fetched_at']) && (time() - $cache['fetched_at'] < $ttl)) {
        $cache['stale'] = false;
        return $cache;
    }

    $current = meyar_fetch_tgju_raw();
    if ($current !== null) {
        // فقط کلیدهای موردنیاز را نگه می‌داریم تا کش سبک بماند
        $needed = [];
        foreach (meyar_items_full() as $item) {
            if (in_array($item['source'][0], ['tgju', 'tgju_usd'], true)) {
                $needed[$item['source'][1]] = true;
            }
        }
        $needed['geram18'] = true;
        $slim = [];
        foreach ($needed as $key => $_) {
            if (isset($current[$key])) {
                $slim[$key] = [
                    'p'  => $current[$key]['p']  ?? null,
                    'dp' => $current[$key]['dp'] ?? 0,
                    'dt' => $current[$key]['dt'] ?? '',
                    't'  => $current[$key]['t']  ?? '',
                ];
            }
        }
        $data = ['fetched_at' => time(), 'stale' => false, 'current' => $slim];
        @file_put_contents(MEYAR_CACHE_FILE, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
        meyar_local_history_record($slim); // ذخیره روزانه برای نمودار (پشتیبان محلی)
        return $data;
    }

    // شبکه در دسترس نیست → آخرین کش (حتی قدیمی)
    if (is_array($cache) && !empty($cache['current'])) {
        $cache['stale'] = true;
        return $cache;
    }
    return ['fetched_at' => 0, 'stale' => true, 'current' => []];
}

/**
 * ساخت لیست نهایی آیتم‌ها با اعمال تنظیمات ادمین.
 * خروجی هر آیتم:
 * [id, title, group, icon, live, buy, sell, change_pct, dir(high|low|none), time, hidden]
 * قیمت‌ها به تومان (انس به دلار).
 */
function meyar_build_prices(): array {
    $settings = meyar_load_settings();
    $market   = meyar_get_market();
    $cur      = $market['current'];
    $adj      = (array)($settings['adjustments'] ?? []);
    $spread   = (float)($settings['buy_spread_percent'] ?? 0.5);
    $premium  = (float)($settings['parsian_premium'] ?? 0);

    $geram18Toman = null;
    if (isset($cur['geram18'])) {
        $v = meyar_num($cur['geram18']['p']);
        if ($v !== null) $geram18Toman = $v / 10;
    }

    $items = meyar_items_full();

    $out = [];
    foreach ($items as $item) {
        $base = null; $dp = 0; $dir = 'none'; $time = ''; $isUsd = false;
        [$srcType, $srcArg] = [$item['source'][0], $item['source'][1] ?? null];

        if ($srcType === 'tgju' || $srcType === 'tgju_usd') {
            if (isset($cur[$srcArg])) {
                $v = meyar_num($cur[$srcArg]['p']);
                if ($v !== null) {
                    $isUsd = ($srcType === 'tgju_usd');
                    $base  = $isUsd ? $v : $v / 10; // ریال → تومان
                    $dp    = (float)($cur[$srcArg]['dp'] ?? 0);
                    $d     = $cur[$srcArg]['dt'] ?? '';
                    $dir   = ($d === 'high') ? 'high' : (($d === 'low') ? 'low' : 'none');
                    $time  = (string)($cur[$srcArg]['t'] ?? '');
                }
            }
        } elseif ($srcType === 'parsian') {
            if ($geram18Toman !== null) {
                $base = $srcArg * $geram18Toman + $premium;
                if (isset($cur['geram18'])) {
                    $dp  = (float)($cur['geram18']['dp'] ?? 0);
                    $d   = $cur['geram18']['dt'] ?? '';
                    $dir = ($d === 'high') ? 'high' : (($d === 'low') ? 'low' : 'none');
                    $time= (string)($cur['geram18']['t'] ?? '');
                }
            }
        } elseif ($srcType === 'manual') {
            $base = (float)$srcArg;
        }

        if ($base === null) continue;

        // اعمال تنظیمات ادمین: اول درصد، بعد مبلغ ثابت (تومان)
        $a       = (array)($adj[$item['id']] ?? []);
        $offset  = (float)($a['offset'] ?? 0);
        $percent = (float)($a['percent'] ?? 0);
        $hidden  = !empty($a['hidden']);

        $final = $base * (1 + $percent / 100) + $offset;
        $buy   = $final * (1 - $spread / 100);
        $dec   = $isUsd ? 2 : 0;

        $out[] = [
            'id'         => $item['id'],
            'title'      => $item['title'],
            'group'      => $item['group'],
            'icon'       => $item['icon'],
            'unit'       => $isUsd ? 'دلار' : 'تومان',
            'base'       => $base,
            'live'       => $final,
            'buy'        => $buy,
            'sell'       => $final,
            'live_fmt'   => meyar_fmt($final, $dec),
            'buy_fmt'    => meyar_fmt($buy, $dec),
            'sell_fmt'   => meyar_fmt($final, $dec),
            'change_pct' => meyar_fa_num(number_format(abs($dp), 2)),
            'dir'        => $dir,
            'time'       => meyar_fa_num($time),
            'hidden'     => $hidden,
        ];
    }

    return [
        'ok'         => true,
        'stale'      => (bool)$market['stale'],
        'fetched_at' => (int)$market['fetched_at'],
        'updated'    => $market['fetched_at'] ? meyar_fa_num(date('H:i:s', $market['fetched_at'])) : '—',
        'items'      => $out,
    ];
}

/* ═══════════ تاریخچه قیمت (برای نمودار شمسی) ═══════════ */

/** یک URL را با curl یا stream می‌گیرد (تایم‌اوت کوتاه تا صفحه خطای PHP رخ ندهد) */
function meyar_http_get(string $url, int $timeout = 12): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: application/json, text/plain, */*', 'Referer: https://www.tgju.org/', 'Origin: https://www.tgju.org'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300 && $body !== '') ? $body : null;
    }
    return @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => $timeout, 'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\nReferer: https://www.tgju.org/\r\n"],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ])) ?: null;
}

/**
 * دریافت تاریخچه یک نماد TGJU (جدیدترین N روز).
 * خروجی: [['g'=>'2026-07-07','j'=>'۱۴۰۵/۰۴/۱۶','v'=>float(تومان)], ...] قدیمی→جدید
 * نکته: درخواست‌های سبک‌تر (به‌جای ۴۰۰۰ ردیف همیشگی) تا روی هاست اشتراکی تایم‌اوت نشود.
 */
function meyar_fetch_history(string $key, int $days = 365, bool $isUsd = false): ?array {
    $days = max(7, min(4000, $days));
    $safe = preg_replace('/[^a-z0-9_]/i', '', $key);
    $cacheFile = MEYAR_DATA . '/history_' . $safe . '.json';

    // کش ۶ ساعته — اگر کش به اندازه بازه درخواستی داده دارد، همان کافی است
    $cached = null;
    if (is_file($cacheFile)) {
        $cached = json_decode(@file_get_contents($cacheFile) ?: '', true);
        if (!is_array($cached) || !$cached) $cached = null;
    }
    $cacheFresh = $cached && (time() - filemtime($cacheFile) < 6 * 3600);
    $cacheFull  = $cached && !empty($cached['full']);
    $cacheRows  = $cached ? (array)($cached['rows'] ?? []) : [];
    if ($cacheFresh && (count($cacheRows) >= $days || $cacheFull)) {
        return array_slice($cacheRows, -$days);
    }

    // درخواست فقط به اندازه نیاز (با کمی حاشیه برای روزهای تعطیل)
    $length = ($days >= 4000) ? 4000 : min(4000, (int)ceil($days * 1.15) + 10);
    $isFull = ($length >= 4000);

    $endpoints = [
        'https://api.tgju.org/v1/market/indicator/summary-table-data/',
        'https://apiv2.tgju.org/v1/market/indicator/summary-table-data/',
    ];
    foreach ($endpoints as $ep) {
        $url  = $ep . rawurlencode($key) . '?lang=fa&order_dir=desc&draw=1&start=0&length=' . $length;
        $body = meyar_http_get($url, 12);
        if (!$body) continue;
        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['data'])) continue;

        $rows = [];
        foreach ($json['data'] as $r) {
            // [open, low, high, close, change, change%, date_g, date_j]
            $close = meyar_num($r[3] ?? null);
            $gDate = str_replace('/', '-', (string)($r[6] ?? ''));
            $jDate = (string)($r[7] ?? '');
            if ($close === null || $gDate === '') continue;
            $rows[] = ['g' => $gDate, 'j' => meyar_fa_num($jDate), 'v' => $isUsd ? $close : $close / 10];
        }
        $rows = array_reverse($rows); // قدیمی → جدید
        if ($rows) {
            // اگر کش قبلی بازه بزرگ‌تری داشت، آن را دور نریزیم — ادغام بر اساس تاریخ
            if (count($cacheRows) > count($rows)) {
                $byDate = [];
                foreach ($cacheRows as $r0) { $byDate[$r0['g']] = $r0; }
                foreach ($rows as $r1)      { $byDate[$r1['g']] = $r1; }
                ksort($byDate);
                $rows = array_values($byDate);
                $isFull = $isFull || $cacheFull;
            }
            @file_put_contents($cacheFile, json_encode(['full' => $isFull, 'rows' => $rows], JSON_UNESCAPED_UNICODE), LOCK_EX);
            return array_slice($rows, -$days);
        }
    }

    // شبکه قطع → کش قدیمی هرچقدر هم کهنه
    if ($cacheRows) return array_slice($cacheRows, -$days);

    // آخرین راه: تاریخچه محلی که خود سایت روزبه‌روز ذخیره کرده
    $local = meyar_local_history_rows($safe, $isUsd);
    return $local ? array_slice($local, -$days) : null;
}

/* ---------- تاریخچه محلی (ذخیره روزانه توسط خود سایت) ---------- */

/** ثبت قیمت امروزِ کلیدهای TGJU از روی کش قیمت لحظه‌ای (حداکثر یک‌بار در ساعت) */
function meyar_local_history_record(array $current): void {
    $file = MEYAR_DATA . '/local_history.json';
    // بیشتر از یک‌بار در ساعت بازنویسی نکن (کاهش I/O روی هاست اشتراکی)
    if (is_file($file) && date('Y-m-d H', filemtime($file)) === date('Y-m-d H')) return;
    $today = date('Y-m-d');
    $db = is_file($file) ? (json_decode(@file_get_contents($file) ?: '', true) ?: []) : [];
    $dirty = false;
    foreach ($current as $key => $row) {
        $v = meyar_num($row['p'] ?? null);
        if ($v === null) continue;
        $k = preg_replace('/[^a-z0-9_]/i', '', (string)$key);
        if (!isset($db[$k])) $db[$k] = [];
        if (!isset($db[$k][$today]) || (float)$db[$k][$today] !== $v) {
            $db[$k][$today] = $v; // مقدار خام (ریال/دلار) — تبدیل هنگام خواندن
            $dirty = true;
        }
        // بیش از ~۳ سال نگه نمی‌داریم
        if (count($db[$k]) > 1200) { ksort($db[$k]); $db[$k] = array_slice($db[$k], -1200, null, true); }
    }
    if ($dirty) @file_put_contents($file, json_encode($db, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** خواندن تاریخچه محلی یک کلید به فرمت ردیف‌های نمودار */
function meyar_local_history_rows(string $key, bool $isUsd): ?array {
    $file = MEYAR_DATA . '/local_history.json';
    if (!is_file($file)) return null;
    $db = json_decode(@file_get_contents($file) ?: '', true);
    if (!is_array($db) || empty($db[$key])) return null;
    ksort($db[$key]);
    $rows = [];
    foreach ($db[$key] as $g => $v) {
        $rows[] = ['g' => $g, 'j' => meyar_jdate_from_ymd($g), 'v' => $isUsd ? (float)$v : (float)$v / 10];
    }
    return $rows ?: null;
}

/**
 * تاریخچه نهایی برای یک آیتم سایت (با اعمال تعدیل ادمین و فرمول پارسیان)
 */
function meyar_item_history(array $item, int $days = 365): ?array {
    $settings = meyar_load_settings();
    $adj      = (array)($settings['adjustments'] ?? []);
    $a        = (array)($adj[$item['id']] ?? []);
    $offset   = (float)($a['offset'] ?? 0);
    $percent  = (float)($a['percent'] ?? 0);

    [$srcType, $srcArg] = [$item['source'][0], $item['source'][1] ?? null];

    if ($srcType === 'tgju' || $srcType === 'tgju_usd') {
        $hist = meyar_fetch_history((string)$srcArg, $days, $srcType === 'tgju_usd');
    } elseif ($srcType === 'parsian') {
        $hist = meyar_fetch_history('geram18', $days, false);
        if ($hist) {
            $premium = (float)($settings['parsian_premium'] ?? 0);
            foreach ($hist as &$h) { $h['v'] = $h['v'] * (float)$srcArg + $premium; }
            unset($h);
        }
    } else {
        return null; // آیتم دستی تاریخچه ندارد
    }
    if (!$hist) return null;

    foreach ($hist as &$h) {
        $h['v'] = round($h['v'] * (1 + $percent / 100) + $offset, $srcType === 'tgju_usd' ? 2 : 0);
    }
    unset($h);
    return $hist;
}

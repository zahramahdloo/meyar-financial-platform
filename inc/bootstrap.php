<?php
/**
 * MEYAR — bootstrap: settings, helpers
 * سازگار با هاست اشتراکی PHP 7.4+
 */

define('MEYAR_ROOT', dirname(__DIR__));
define('MEYAR_DATA', MEYAR_ROOT . '/data');
define('MEYAR_SETTINGS_FILE', MEYAR_DATA . '/settings.json');
define('MEYAR_CACHE_FILE', MEYAR_DATA . '/cache.json');

date_default_timezone_set('Asia/Tehran');

if (!is_dir(MEYAR_DATA)) { @mkdir(MEYAR_DATA, 0755, true); }

/* ---------- request-scoped cache and safe JSON I/O ---------- */
function meyar_request_cache_has(string $key): bool {
    return isset($GLOBALS['meyar_request_cache']) && array_key_exists($key, $GLOBALS['meyar_request_cache']);
}

function meyar_request_cache_get(string $key, $default = null) {
    return meyar_request_cache_has($key) ? $GLOBALS['meyar_request_cache'][$key] : $default;
}

function meyar_request_cache_set(string $key, $value) {
    if (!isset($GLOBALS['meyar_request_cache']) || !is_array($GLOBALS['meyar_request_cache'])) {
        $GLOBALS['meyar_request_cache'] = [];
    }
    $GLOBALS['meyar_request_cache'][$key] = $value;
    return $value;
}

function meyar_atomic_write(string $file, string $contents): bool {
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $tmp = @tempnam($dir, basename($file) . '.tmp-');
    if ($tmp === false) return false;

    $handle = @fopen($tmp, 'wb');
    if ($handle === false) { @unlink($tmp); return false; }
    $length = strlen($contents);
    $written = 0;
    while ($written < $length) {
        $count = @fwrite($handle, substr($contents, $written));
        if ($count === false || $count === 0) { @fclose($handle); @unlink($tmp); return false; }
        $written += $count;
    }
    @fflush($handle);
    if (function_exists('fsync')) @fsync($handle);
    @fclose($handle);

    if (is_file($file)) @chmod($tmp, fileperms($file) & 0777);
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
}

function meyar_read_json_file(string $file, $default = null) {
    $key = 'json:' . $file;
    if (meyar_request_cache_has($key)) return meyar_request_cache_get($key);
    if (!is_file($file)) return meyar_request_cache_set($key, $default);
    $data = json_decode((string)@file_get_contents($file), true);
    return meyar_request_cache_set($key, $data === null ? $default : $data);
}

function meyar_write_json_file(string $file, $value, int $flags = JSON_UNESCAPED_UNICODE): bool {
    $json = json_encode($value, $flags);
    if ($json === false || !meyar_atomic_write($file, $json)) return false;
    meyar_request_cache_set('json:' . $file, $value);
    return true;
}

/* ---------- server environment ---------- */
function meyar_env(string $key): string {
    static $fileValues = null;
    $value = getenv($key);
    if ($value !== false && trim((string)$value) !== '') return trim((string)$value);
    if (!empty($_ENV[$key])) return trim((string)$_ENV[$key]);
    if (!empty($_SERVER[$key])) return trim((string)$_SERVER[$key]);

    if ($fileValues === null) {
        $fileValues = [];
        foreach ([MEYAR_ROOT . '/.env.local', MEYAR_ROOT . '/.env'] as $envFile) {
            if (!is_file($envFile)) continue;
            foreach ((array)@file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim((string)$line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                [$name, $envValue] = explode('=', $line, 2);
                $name = trim($name);
                $envValue = trim($envValue);
                if ((strlen($envValue) >= 2) && (($envValue[0] === '"' && substr($envValue, -1) === '"') || ($envValue[0] === "'" && substr($envValue, -1) === "'"))) {
                    $envValue = substr($envValue, 1, -1);
                }
                if ($name !== '' && !array_key_exists($name, $fileValues)) $fileValues[$name] = $envValue;
            }
        }
    }
    return trim((string)($fileValues[$key] ?? ''));
}

/** Trusted public origin for SEO URLs; production must configure MEYAR_PUBLIC_URL. */
function meyar_public_url(): string {
    static $publicUrl = null;
    if ($publicUrl !== null) return $publicUrl;
    $configured = trim(meyar_env('MEYAR_PUBLIC_URL'));
    if ($configured !== '') {
        $parts = parse_url($configured);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = (string)($parts['host'] ?? '');
        if (in_array($scheme, ['http', 'https'], true) && $host !== '' && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $origin = $scheme . '://' . $host;
            if (!empty($parts['port'])) $origin .= ':' . (int)$parts['port'];
            if (!empty($parts['path']) && $parts['path'] !== '/') $origin .= '/' . trim($parts['path'], '/');
            return $publicUrl = rtrim($origin, '/');
        }
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if (preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$/i', $host)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $publicUrl = $scheme . '://' . $host;
    }
    return $publicUrl = '';
}

function meyar_public_url_for(string $path = ''): string {
    $base = meyar_public_url();
    if ($base === '') return '';
    return $path === '' ? $base . '/' : $base . '/' . ltrim($path, '/');
}

/* ---------- settings ---------- */

function meyar_default_settings(): array {
    return [
        'password_hash'        => null,      // legacy field; new administrators use the database setup flow
        'cache_ttl'            => 60,        // ثانیه
        'buy_spread_percent'   => 0.5,       // درصد اختلاف قیمت خرید نسبت به فروش
        'parsian_premium'      => 100000,    // اجرت هر سکه پارسیان (تومان)
        'adjustments'          => (object)[],// itemId => {offset, percent, hidden}
        'manual_items'         => [],        // (قدیمی — به دیتابیس منتقل می‌شود)
        'layout'               => [          // چیدمان جدول‌های صفحه اصلی
            ['group' => 'coins',    'width' => 'half'],
            ['group' => 'currency', 'width' => 'half'],
            ['group' => 'parsian',  'width' => 'half'],
            ['group' => 'gold',     'width' => 'half'],
        ],
        'online_badge'         => 1,         // چراغ «آنلاین هستیم»
        'site_phone'           => '021-33986164',
        'site_email'           => 'meyargroup2000@gmail.com',
        'site_instagram'       => '',
    ];
}

function meyar_load_settings(): array {
    if (meyar_request_cache_has('settings')) return meyar_request_cache_get('settings');
    $defaults = meyar_default_settings();
    $data = meyar_read_json_file(MEYAR_SETTINGS_FILE, []);
    $settings = is_array($data) ? array_merge($defaults, $data) : $defaults;
    return meyar_request_cache_set('settings', $settings);
}

function meyar_save_settings(array $s): bool {
    $ok = meyar_write_json_file(MEYAR_SETTINGS_FILE, $s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($ok) meyar_request_cache_set('settings', array_merge(meyar_default_settings(), $s));
    return $ok;
}

/* ---------- helpers ---------- */

function meyar_fa_num(string $str): string {
    return strtr($str, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}

function meyar_fmt(float $n, int $dec = 0): string {
    return meyar_fa_num(number_format($n, $dec));
}

function meyar_h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ---------- items catalog ----------
 * source انواع:
 *   ['tgju', key]          => از TGJU (ریال، تبدیل به تومان)
 *   ['tgju_usd', key]      => از TGJU (دلار، بدون تبدیل)
 *   ['parsian', gram]      => گرم × طلای ۱۸ + اجرت پارسیان
 *   ['manual']             => قیمت دستی از پنل ادمین
 */
function meyar_builtin_items(): array {
    if (meyar_request_cache_has('builtin_items')) return meyar_request_cache_get('builtin_items');
    $items = [];

    // ---- سکه‌ها ----
    $coins = [
        ['sekee',  'سکه امامی',      'sekee'],
        ['sekeb',  'سکه بهار آزادی', 'sekeb'],
        ['nim',    'نیم سکه',        'nim'],
        ['rob',    'ربع سکه',        'rob'],
        ['gerami', 'سکه گرمی',       'gerami'],
    ];
    foreach ($coins as $c) {
        $items[] = ['id'=>$c[0], 'title'=>$c[1], 'group'=>'coins', 'source'=>['tgju',$c[2]], 'icon'=>'coin'];
    }

    // ---- پارسیان (سوت = 0.001 گرم طلای ۱۸) ----
    for ($soot = 100; $soot <= 1500; $soot += 100) {
        $items[] = [
            'id'    => 'parsian_' . $soot,
            'title' => 'پارسیان ' . meyar_fa_num((string)$soot) . ' سوت',
            'group' => 'parsian',
            'source'=> ['parsian', $soot / 1000],
            'icon'  => 'coin',
        ];
    }

    // ---- طلا ----
    $gold = [
        ['geram18',   'طلای ۱۸ عیار',  'tgju',     'geram18'],
        ['geram24',   'طلای ۲۴ عیار',  'tgju',     'geram24'],
        ['mesghal',   'مثقال طلا',     'tgju',     'mesghal'],
        ['ons',       'انس جهانی طلا', 'tgju_usd', 'ons'],
        ['silver999', 'نقره ۹۹۹.۹',     'tgju',     'silver_999'],
    ];
    foreach ($gold as $g) {
        $items[] = ['id'=>$g[0], 'title'=>$g[1], 'group'=>'gold', 'source'=>[$g[2],$g[3]], 'icon'=>'gold'];
    }

    // ---- ارزها ----
    $fx = [
        ['usd','دلار آمریکا','price_dollar_rl','🇺🇸'],
        ['eur','یورو','price_eur','🇪🇺'],
        ['aed','درهم امارات','price_aed','🇦🇪'],
        ['gbp','پوند انگلیس','price_gbp','🇬🇧'],
        ['try','لیر ترکیه','price_try','🇹🇷'],
        ['chf','فرانک سوئیس','price_chf','🇨🇭'],
        ['cny','یوان چین','price_cny','🇨🇳'],
        ['jpy','ین ژاپن (۱۰۰)','price_jpy','🇯🇵'],
        ['krw','وون کره (۱۰۰)','price_krw','🇰🇷'],
        ['cad','دلار کانادا','price_cad','🇨🇦'],
        ['aud','دلار استرالیا','price_aud','🇦🇺'],
        ['nzd','دلار نیوزیلند','price_nzd','🇳🇿'],
        ['sgd','دلار سنگاپور','price_sgd','🇸🇬'],
        ['inr','روپیه هند','price_inr','🇮🇳'],
        ['pkr','روپیه پاکستان','price_pkr','🇵🇰'],
        ['iqd','دینار عراق (۱۰۰)','price_iqd','🇮🇶'],
        ['syp','لیر سوریه','price_syp','🇸🇾'],
        ['afn','افغانی','price_afn','🇦🇫'],
        ['dkk','کرون دانمارک','price_dkk','🇩🇰'],
        ['sek','کرون سوئد','price_sek','🇸🇪'],
        ['nok','کرون نروژ','price_nok','🇳🇴'],
        ['sar','ریال عربستان','price_sar','🇸🇦'],
        ['qar','ریال قطر','price_qar','🇶🇦'],
        ['omr','ریال عمان','price_omr','🇴🇲'],
        ['kwd','دینار کویت','price_kwd','🇰🇼'],
        ['bhd','دینار بحرین','price_bhd','🇧🇭'],
        ['thb','بات تایلند','price_thb','🇹🇭'],
        ['myr','رینگیت مالزی','price_myr','🇲🇾'],
        ['rub','روبل روسیه','price_rub','🇷🇺'],
        ['azn','منات آذربایجان','price_azn','🇦🇿'],
        ['amd','درام ارمنستان','price_amd','🇦🇲'],
        ['gel','لاری گرجستان','price_gel','🇬🇪'],
        ['tjs','سامانی تاجیکستان','price_tjs','🇹🇯'],
        ['tmt','منات ترکمنستان','price_tmt','🇹🇲'],
        ['kgs','سوم قرقیزستان','price_kgs','🇰🇬'],
    ];
    foreach ($fx as $f) {
        $items[] = ['id'=>$f[0], 'title'=>$f[1], 'group'=>'currency', 'source'=>['tgju',$f[2]], 'icon'=>$f[3]];
    }

    return meyar_request_cache_set('builtin_items', $items);
}

function meyar_groups(): array {
    if (meyar_request_cache_has('groups')) return meyar_request_cache_get('groups');
    return meyar_request_cache_set('groups', [
        'coins'    => 'جدول سکه‌ها',
        'parsian'  => 'جدول سکه‌های پارسیان',
        'gold'     => 'جدول طلا',
        'currency' => 'جدول ارزها',
    ]);
}

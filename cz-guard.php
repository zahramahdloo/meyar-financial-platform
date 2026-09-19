<?php
/* Customaz license module for this website. Do not edit or remove. */
if (defined('CZLIC')) { return; }
define('CZLIC', 1);

// Local development preview: the license is validated for the real domain,
// not for loopback hosts used by PHP's built-in server or Docker.
$czHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$czHost = preg_replace('/:\d+$/', '', $czHost);
if (in_array($czHost, ['localhost', '127.0.0.1', '::1'], true)) {
    return;
}

$czKey     = trim((string) (getenv('MEYAR_LICENSE_KEY') ?: ''));
$czSecret  = trim((string) (getenv('MEYAR_LICENSE_SECRET') ?: ''));
$czHub     = 'https://customaz.ir/api/v1';
$czProduct = 'سکه';
$czVersion = '1.0.0';
$czTtl     = 3600;
$czPub     = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA2UZBowlbheT9TWot6myu
TmuCxTVSkh4NgglSWj4sYBQdh6afsoZlDlNxFsA9Q+Fh1O2/fxKUdUzLyyqEW8Q2
T9OYbELE6SMm0Omf9YpAOYxb6SNB5I/1rmX7HrObI6xwb6rQS/mrLRzwaQ0GhP9w
zLS71t83zEWCuhUL8at7WcXBfvuVf6wNynQx3EKAzXviLVdGe0fu+2CYACnR6FTU
y7Izs8bNVhyqMY7r4AjG/p6V8cQvzVdRVFNNxb1KzybMWLssVskBiNqoR4H8piwv
AxdJRM5B9m7xh1RI2/oMJgS6JFGCumzsBi3tJ0kLg4giq/RVSHwpTY71e35TQzkz
mwIDAQAB
-----END PUBLIC KEY-----
';

$czDir   = isset($czDir) ? $czDir : __DIR__;
$czId    = substr(hash('sha256', $czKey), 0, 10);
$czCache = $czDir . '/cz-license-' . $czId . '.json';
$czLock  = $czDir . '/cz-license-' . $czId . '.lock';
$czNow   = time();

// Production licensing configuration is supplied by the server environment.
// Do not attempt verification with missing credentials.
if ($czKey === '' || $czSecret === '') {
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>در حال تعمیر</title></head><body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;background:#0f1420;color:#e6ebf5"><div style="text-align:center;max-width:480px;padding:40px"><div style="font-size:54px">&#128295;</div><h1 style="margin:12px 0">سایت در حال تعمیر است</h1><p style="color:#8ea0c0;line-height:1.9">پیکربندی سرویس کامل نیست. لطفاً بعداً مراجعه کنید.</p></div></body></html>';
    exit;
}

// A trusted answer is one the hub RSA-signed, that has not expired, and (for a
// live reply) whose echoed nonce matches ours. A hand-written, replayed or
// tampered cache fails this and is ignored.
$czVerify = function ($r, $pubPem, $wantNonce, $now) {
    if (!is_array($r) || !isset($r['rsa'], $r['status'], $r['expires_at'])) { return false; }
    if (!function_exists('openssl_verify')) { return false; }
    if ((int) $r['expires_at'] < $now) { return false; }
    if ($wantNonce !== null && (!isset($r['nonce']) || !hash_equals((string) $wantNonce, (string) $r['nonce']))) { return false; }
    $sig = base64_decode((string) $r['rsa'], true);
    if ($sig === false) { return false; }
    $c = $r; unset($c['rsa'], $c['signature']); ksort($c);
    $canon = json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pk = @openssl_pkey_get_public($pubPem);
    if (!$pk) { return false; }
    return openssl_verify($canon, $sig, $pk, OPENSSL_ALGO_SHA256) === 1;
};

$czCached = null;
if (is_file($czCache)) {
    $tmp = json_decode((string) file_get_contents($czCache), true);
    if (is_array($tmp)) { $czCached = $tmp; }
}
$czValid = ($czCached !== null && $czVerify($czCached, $czPub, null, $czNow)) ? $czCached : null;
$czCs    = $czValid ? (isset($czValid['status']) ? $czValid['status'] : null) : null;
$czAge   = $czValid ? ($czNow - (int) (isset($czValid['issued_at']) ? $czValid['issued_at'] : 0)) : PHP_INT_MAX;
$czStatus = null; $czMsg = null;

// How often to revalidate an ACTIVE answer. The hub sets the cadence through the
// signed check_interval, so it can be tuned centrally without rebuilding guards;
// it is clamped so a tampered or absent value cannot widen the window past TTL.
$czEvery = 120;
if ($czValid && isset($czValid['check_interval'])) { $czEvery = (int) $czValid['check_interval']; }
if ($czEvery < 30)      { $czEvery = 30; }
if ($czEvery > $czTtl)  { $czEvery = $czTtl; }

// Trust a still-fresh signed answer without calling the hub. While not active,
// re-check quickly so a re-activation is honoured fast.
$czFresh = false;
if ($czValid) {
    if ($czCs === 'active' && $czAge < $czEvery) { $czFresh = true; }
    elseif ($czCs !== 'active' && $czAge < 30) { $czFresh = true; }
}

// Serialize refreshes. When the cached answer goes stale, exactly ONE request
// talks to the hub while every other request keeps serving the still-signed
// cached answer, so a short revalidation window costs one hub call per window
// site-wide instead of one per visitor. A recent attempt also backs off, so an
// unreachable hub is not hammered. Without a usable cache we must ask the hub.
$czFp = null;
if (!$czFresh && $czValid) {
    $czFp = @fopen($czLock, 'c+');
    if ($czFp) {
        if (@flock($czFp, LOCK_EX | LOCK_NB)) {
            // The lock file's contents are the unix time of the last attempt.
            // A newly created file is empty and reads as 0, so the very first
            // refresh is never delayed - mtime would have said "just now".
            $czLast = (int) stream_get_contents($czFp);
            if ($czLast > 0 && ($czNow - $czLast) < 20) {
                $czFresh = true;
            } else {
                @ftruncate($czFp, 0);
                @rewind($czFp);
                @fwrite($czFp, (string) $czNow);
                @fflush($czFp);
            }
        } else {
            $czFresh = true;
            @fclose($czFp);
            $czFp = null;
        }
    }
}

if ($czFresh) {
    $czStatus = $czCs;
    $czMsg = isset($czValid['message']) ? $czValid['message'] : null;
    if ($czFp) { @flock($czFp, LOCK_UN); @fclose($czFp); }
} else {
    $czNonce = bin2hex(random_bytes(8));
    $czReq = array(
        'domain'      => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
        'license_key' => $czKey,
        'nonce'       => $czNonce,
        'timestamp'   => $czNow,
        'version'     => $czVersion,
    );
    $czSorted = $czReq; ksort($czSorted);
    $czReq['signature'] = hash_hmac('sha256', json_encode($czSorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $czSecret);
    $czResp = null;
    if (function_exists('curl_init')) {
        $czCh = curl_init(rtrim($czHub, '/') . '/license/verify');
        curl_setopt_array($czCh, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($czReq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ));
        $czResp = json_decode((string) curl_exec($czCh), true);
        // curl_close() is a no-op since PHP 8.0; letting the handle be
        // released naturally avoids the PHP 8 deprecation notice.
    }
    if (is_array($czResp) && $czVerify($czResp, $czPub, $czNonce, $czNow)) {
        $czStatus = $czResp['status'];
        $czMsg = isset($czResp['message']) ? $czResp['message'] : null;
        // Write atomically so a concurrent reader never sees a half-written file.
        $czTmp = $czCache . '.' . getmypid() . '.tmp';
        if (@file_put_contents($czTmp, json_encode($czResp)) !== false) {
            if (!@rename($czTmp, $czCache)) { @unlink($czTmp); @file_put_contents($czCache, json_encode($czResp)); }
        }
    } elseif ($czValid) {
        // hub unreachable / answer invalid -> ride the last valid signed answer until it expires
        $czStatus = $czCs;
        $czMsg = isset($czValid['message']) ? $czValid['message'] : null;
    } else {
        // no valid signed answer and hub unreachable -> fail closed
        $czStatus = 'suspended';
        $czMsg = null;
    }
    if ($czFp) { @flock($czFp, LOCK_UN); @fclose($czFp); }
}

if ($czStatus !== 'active') {
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=utf-8');
    }
    $czText = $czMsg ? $czMsg : 'این سرویس موقتاً غیرفعال است. لطفاً بعداً مراجعه کنید.';
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>در حال تعمیر</title></head><body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;background:#0f1420;color:#e6ebf5"><div style="text-align:center;max-width:480px;padding:40px"><div style="font-size:54px">&#128295;</div><h1 style="margin:12px 0">سایت در حال تعمیر است</h1><p style="color:#8ea0c0;line-height:1.9">' . htmlspecialchars($czText, ENT_QUOTES) . '</p></div></body></html>';
    exit;
}

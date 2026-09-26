<?php
/** MEYAR — common JSON API safeguards */

require_once __DIR__ . '/bootstrap.php';

function meyar_api_begin(): void {
    static $started = false;
    if ($started) return;
    $started = true;

    error_reporting(0);
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    ob_start();

    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        error_log(sprintf('Meyar API warning: %s in %s:%d', $message, $file, $line));
        return true;
    });

    register_shutdown_function(function (): void {
        $error = error_get_last();
        if (!$error || !in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) return;
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'server_error',
            'message' => 'خطای موقت سرور. لطفاً دوباره تلاش کنید.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });
}

function meyar_api_response(array $payload, int $status = 200): void {
    http_response_code($status);
    while (ob_get_level()) ob_end_clean();
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $json === false ? '{"ok":false,"error":"server_error"}' : $json;
    exit;
}

function meyar_api_error(int $status, string $code, string $message = 'خطای موقت سرور. لطفاً دوباره تلاش کنید.'): void {
    meyar_api_response(['ok' => false, 'error' => $code, 'message' => $message], $status);
}

function meyar_api_client_key(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 180);
    return hash('sha256', $ip . '|' . $ua);
}

/** Fail open if the host cannot write the lightweight limiter file. */
function meyar_api_rate_limit(string $bucket, int $limit, int $window): bool {
    $dir = MEYAR_DATA . '/.api-rate';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return true;
    $file = $dir . '/' . hash('sha256', $bucket . '|' . meyar_api_client_key()) . '.json';
    $handle = @fopen($file, 'c+');
    if (!$handle) return true;
    if (!@flock($handle, LOCK_EX)) { @fclose($handle); return true; }
    $raw = stream_get_contents($handle);
    $state = json_decode($raw ?: '', true);
    $now = time();
    if (!is_array($state) || ($now - (int)($state['started'] ?? 0)) >= $window) {
        $state = ['started' => $now, 'count' => 0];
    }
    $state['count'] = (int)$state['count'] + 1;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $state['count'] <= $limit;
}

function meyar_api_valid_id(string $id): bool {
    return (bool)preg_match('/^[a-z0-9][a-z0-9_]{0,63}$/i', $id);
}

function meyar_api_valid_topic(string $topic): bool {
    return $topic !== '' && mb_strlen($topic) <= 180
        && (bool)preg_match('/^[\p{L}\p{N}\p{M}\s\x{200C}\x{200D}،؛؟.,:()«»\-+٪%_\/]+$/u', $topic);
}

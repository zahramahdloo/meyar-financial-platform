<?php
/**
 * MEYAR — لایه دیتابیس SQLite
 * users / roles / items_meta / chat / visits
 */

require_once __DIR__ . '/bootstrap.php';

define('MEYAR_DB_FILE', MEYAR_DATA . '/meyar.sqlite');
define('MEYAR_DB_SCHEMA_VERSION', 1);

function meyar_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        die('افزونه pdo_sqlite روی هاست فعال نیست. از پشتیبانی هاست بخواهید فعالش کنند.');
    }
    $isNew = !is_file(MEYAR_DB_FILE);
    $pdo = new PDO('sqlite:' . MEYAR_DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=4000;');
    $schemaVersion = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($schemaVersion < MEYAR_DB_SCHEMA_VERSION) {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $schemaVersion = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
            if ($schemaVersion < MEYAR_DB_SCHEMA_VERSION) {
                meyar_db_migrate($pdo, $isNew);
                $pdo->exec('PRAGMA user_version = ' . MEYAR_DB_SCHEMA_VERSION);
            }
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->exec('ROLLBACK');
            throw $e;
        }
    }
    return $pdo;
}

function meyar_db_migrate(PDO $pdo, bool $isNew): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        perms TEXT NOT NULL DEFAULT '[]'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        pass_hash TEXT NOT NULL,
        display_name TEXT DEFAULT '',
        role_id INTEGER NOT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS items_meta (
        id TEXT PRIMARY KEY,
        title TEXT DEFAULT NULL,
        grp TEXT DEFAULT NULL,
        icon TEXT DEFAULT NULL,
        source_type TEXT DEFAULT NULL,
        source_arg TEXT DEFAULT NULL,
        sort INTEGER DEFAULT NULL,
        is_custom INTEGER NOT NULL DEFAULT 0,
        deleted INTEGER NOT NULL DEFAULT 0,
        seo_title TEXT DEFAULT '',
        seo_desc TEXT DEFAULT '',
        page_desc TEXT DEFAULT '',
        schema_json TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS threads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT NOT NULL UNIQUE,
        name TEXT DEFAULT '',
        status TEXT NOT NULL DEFAULT 'open',
        created_at INTEGER NOT NULL,
        last_at INTEGER NOT NULL,
        admin_unread INTEGER NOT NULL DEFAULT 0,
        visitor_unread INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        thread_id INTEGER NOT NULL,
        sender TEXT NOT NULL, -- v | a
        admin_name TEXT DEFAULT '',
        body TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_msg_thread ON messages(thread_id, id)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        d TEXT NOT NULL,          -- Y-m-d
        ts INTEGER NOT NULL,
        path TEXT NOT NULL,
        ip_hash TEXT NOT NULL,
        ua TEXT DEFAULT '',
        ref TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_d ON visits(d)");

    // ----- seed -----
    $hasRoles = (int)$pdo->query("SELECT COUNT(*) c FROM roles")->fetch()['c'];
    if (!$hasRoles) {
        $ins = $pdo->prepare("INSERT INTO roles(name, perms) VALUES(?,?)");
        $ins->execute(['مدیر کل', json_encode(['*'], JSON_UNESCAPED_UNICODE)]);
        $ins->execute(['اپراتور قیمت', json_encode(['dashboard','prices','items'], JSON_UNESCAPED_UNICODE)]);
        $ins->execute(['پشتیبان چت', json_encode(['dashboard','chat'], JSON_UNESCAPED_UNICODE)]);
    }
    $hasUsers = (int)$pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
    if (!$hasUsers) {
        $initialPassword = trim((string)(getenv('MEYAR_ADMIN_INITIAL_PASSWORD') ?: ''));
        if (strlen($initialPassword) >= 12) {
            $adminRole = (int)$pdo->query("SELECT id FROM roles WHERE perms LIKE '%*%' ORDER BY id LIMIT 1")->fetch()['id'];
            $passHash = password_hash($initialPassword, PASSWORD_DEFAULT);
            if ($passHash !== false) {
                $pdo->prepare("INSERT INTO users(username, pass_hash, display_name, role_id, active, created_at) VALUES(?,?,?,?,1,?)")
                    ->execute(['gnz', $passHash, 'مدیر اصلی', $adminRole, time()]);
            }
        }
    }

    // مهاجرت آیتم‌های دستی قدیمی از settings.json
    $settings = meyar_load_settings();
    if (!empty($settings['manual_items'])) {
        $ins = $pdo->prepare("INSERT OR IGNORE INTO items_meta(id,title,grp,icon,source_type,source_arg,sort,is_custom)
                              VALUES(?,?,?,?,?,?,?,1)");
        foreach ((array)$settings['manual_items'] as $m) {
            $id = 'manual_' . preg_replace('/[^a-z0-9_]/i', '', (string)($m['id'] ?? ''));
            if ($id === 'manual_') continue;
            $ins->execute([$id, $m['title'] ?? '', $m['group'] ?? 'coins', 'coin', 'manual', (string)($m['price'] ?? 0), 9000]);
        }
        $settings['manual_items'] = [];
        meyar_save_settings($settings);
    }
}

/* ---------- آیتم‌ها با اعمال دیتابیس ---------- */

function meyar_items_full(): array {
    if (meyar_request_cache_has('items_full')) return meyar_request_cache_get('items_full');
    $pdo = meyar_db();
    $meta = [];
    foreach ($pdo->query("SELECT * FROM items_meta") as $row) {
        $meta[$row['id']] = $row;
    }

    $out = [];
    $sort = 10;
    foreach (meyar_builtin_items() as $b) {
        $m = $meta[$b['id']] ?? null;
        if ($m && (int)$m['deleted'] === 1) { $sort += 10; continue; }
        $out[] = [
            'id'          => $b['id'],
            'title'       => ($m && $m['title'] !== null && $m['title'] !== '') ? $m['title'] : $b['title'],
            'group'       => ($m && $m['grp']) ? $m['grp'] : $b['group'],
            'icon'        => ($m && $m['icon']) ? $m['icon'] : $b['icon'],
            'source'      => $b['source'],
            'sort'        => ($m && $m['sort'] !== null) ? (int)$m['sort'] : $sort,
            'is_custom'   => 0,
            'seo_title'   => $m['seo_title']   ?? '',
            'seo_desc'    => $m['seo_desc']    ?? '',
            'page_desc'   => $m['page_desc']   ?? '',
            'schema_json' => $m['schema_json'] ?? '',
        ];
        $sort += 10;
    }
    foreach ($meta as $m) {
        if (!(int)$m['is_custom'] || (int)$m['deleted']) continue;
        $src = ['manual', (float)$m['source_arg']];
        if ($m['source_type'] === 'tgju')     $src = ['tgju', (string)$m['source_arg']];
        if ($m['source_type'] === 'tgju_usd') $src = ['tgju_usd', (string)$m['source_arg']];
        if ($m['source_type'] === 'parsian')  $src = ['parsian', (float)$m['source_arg']];
        $out[] = [
            'id'          => $m['id'],
            'title'       => $m['title'],
            'group'       => $m['grp'] ?: 'coins',
            'icon'        => $m['icon'] ?: 'coin',
            'source'      => $src,
            'sort'        => $m['sort'] !== null ? (int)$m['sort'] : 9000,
            'is_custom'   => 1,
            'seo_title'   => $m['seo_title']   ?? '',
            'seo_desc'    => $m['seo_desc']    ?? '',
            'page_desc'   => $m['page_desc']   ?? '',
            'schema_json' => $m['schema_json'] ?? '',
        ];
    }
    usort($out, function ($a, $b) { return $a['sort'] <=> $b['sort']; });
    return meyar_request_cache_set('items_full', $out);
}

function meyar_item_by_id(string $id): ?array {
    foreach (meyar_items_full() as $i) {
        if ($i['id'] === $id) return $i;
    }
    return null;
}

/* ---------- آمار بازدید ---------- */

function meyar_track(string $path): void {
    try {
        $pdo = meyar_db();
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua  = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
        // ربات‌های شاخص را جدا علامت نمی‌زنیم ولی هش روزانه یکتا می‌سازیم
        $hash = substr(sha1($ip . '|' . $ua . '|' . date('Y-m-d')), 0, 20);
        $pdo->prepare("INSERT INTO visits(d, ts, path, ip_hash, ua, ref) VALUES(?,?,?,?,?,?)")
            ->execute([date('Y-m-d'), time(), substr($path, 0, 190), $hash, $ua,
                       substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 250)]);
    } catch (Throwable $e) { /* آمار نباید سایت را بخواباند */ }
}

/* ---------- تبدیل میلادی به شمسی ---------- */

function meyar_g2j(int $gy, int $gm, int $gd): array {
    $gdm = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? $gy + 1 : $gy;
    $days = 355666 + 365 * $gy + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
    $jy = -1595 + 33 * intdiv($days, 12053);
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) { $jy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
    if ($days < 186) { $jm = 1 + intdiv($days, 31); $jd = 1 + $days % 31; }
    else { $jm = 7 + intdiv($days - 186, 30); $jd = 1 + ($days - 186) % 30; }
    return [$jy, $jm, $jd];
}

function meyar_jdate_from_ymd(string $ymd): string {
    $p = explode('-', $ymd);
    if (count($p) !== 3) return $ymd;
    [$jy, $jm, $jd] = meyar_g2j((int)$p[0], (int)$p[1], (int)$p[2]);
    return meyar_fa_num(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
}

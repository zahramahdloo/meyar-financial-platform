<?php
/** MEYAR — احراز هویت پنل مدیریت + نقش‌ها و مجوزها */

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function meyar_csrf(): string {
    if (empty($_SESSION['gnz_csrf'])) {
        $_SESSION['gnz_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['gnz_csrf'];
}

function meyar_csrf_ok(): bool {
    return isset($_POST['csrf']) && hash_equals(meyar_csrf(), (string)$_POST['csrf']);
}

function meyar_login(string $username, string $password): bool {
    $tries = $_SESSION['gnz_tries'] ?? 0;
    $last  = $_SESSION['gnz_last_try'] ?? 0;
    if ($tries >= 5 && time() - $last < 300) return false;

    $pdo = meyar_db();
    $st = $pdo->prepare("SELECT u.*, r.name role_name, r.perms FROM users u JOIN roles r ON r.id=u.role_id WHERE u.username=? AND u.active=1");
    $st->execute([trim($username)]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['pass_hash'])) {
        session_regenerate_id(true);
        $_SESSION['gnz_user'] = [
            'id'       => (int)$u['id'],
            'username' => $u['username'],
            'name'     => $u['display_name'] ?: $u['username'],
            'role'     => $u['role_name'],
            'perms'    => json_decode($u['perms'], true) ?: [],
        ];
        $_SESSION['gnz_tries'] = 0;
        return true;
    }
    $_SESSION['gnz_tries'] = $tries + 1;
    $_SESSION['gnz_last_try'] = time();
    return false;
}

function meyar_logout(): void {
    unset($_SESSION['gnz_user']);
}

function meyar_user(): ?array {
    return $_SESSION['gnz_user'] ?? null;
}

function meyar_can(string $perm): bool {
    $u = meyar_user();
    if (!$u) return false;
    return in_array('*', $u['perms'], true) || in_array($perm, $u['perms'], true);
}

/** مجوزهای قابل تعریف در نقش‌ها */
function meyar_all_perms(): array {
    return [
        'dashboard' => 'داشبورد و گزارش بازدید',
        'prices'    => 'تعدیل قیمت‌ها',
        'items'     => 'مدیریت ارزها و سکه‌ها (سئو)',
        'layout'    => 'چیدمان صفحه اصلی',
        'chat'      => 'چت با کاربران',
        'users'     => 'مدیریت کاربران و نقش‌ها',
        'settings'  => 'تنظیمات کلی',
    ];
}

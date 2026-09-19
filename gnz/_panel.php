<?php
/** MEYAR — پوسته مشترک پنل مدیریت */
require_once dirname(__DIR__) . '/inc/auth.php';
require_once dirname(__DIR__) . '/inc/fetcher.php';

/** گارد ورود + مجوز؛ اگر لاگین نباشد فرم ورود نشان داده می‌شود */
function panel_guard(string $perm = ''): void {
    $err = '';
    if (!meyar_user() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_user'])) {
        if (!meyar_csrf_ok()) {
            $err = 'نشست منقضی شده؛ دوباره تلاش کنید.';
        } elseif (meyar_login((string)$_POST['login_user'], (string)$_POST['login_pass'])) {
            header('Location: ' . ($_SERVER['REQUEST_URI'] ?? './')); exit;
        } else {
            $err = 'نام کاربری یا رمز اشتباه است (یا موقتاً قفل شده‌اید).';
        }
    }
    if (isset($_GET['logout'])) { meyar_logout(); header('Location: ./'); exit; }

    if (!meyar_user()) { panel_login_page($err); exit; }
    if ($perm && !meyar_can($perm)) {
        panel_header('دسترسی غیرمجاز');
        echo '<div class="err">شما مجوز دسترسی به این بخش را ندارید.</div>';
        panel_footer(); exit;
    }
}

function panel_login_page(string $err): void {
    $csrf = meyar_csrf();
    panel_css_head('ورود به پنل');
    ?>
    <div class="login-box">
      <h1><span class="dot"></span> پنل مدیریت معیار</h1>
      <p>نام کاربری و رمز عبور را وارد کنید</p>
      <?php if ($err): ?><div class="err"><?= meyar_h($err) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="text" name="login_user" placeholder="نام کاربری" autofocus required autocomplete="username">
        <input type="password" name="login_pass" placeholder="رمز عبور" required autocomplete="current-password">
        <button class="btn" type="submit" style="width:100%">ورود</button>
      </form>
    </div>
    </body></html>
    <?php
}

function panel_css_head(string $title): void {
    ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= meyar_h($title) ?> — پنل معیار</title>
<link rel="stylesheet" href="https://use.hugeicons.com/font/icons.css">
<style>
@font-face{font-family:'IRANSansXFaNum';src:url('../assets/Sans-fonts/Woff2/IRANSansXFaNum-Regular.woff2') format('woff2');font-weight:400;font-style:normal;font-display:swap}
@font-face{font-family:'IRANSansXFaNum';src:url('../assets/Sans-fonts/Woff2/IRANSansXFaNum-Bold.woff2') format('woff2');font-weight:700 900;font-style:normal;font-display:swap}
:root { --gold:#d4a437; --gold-l:#f0cf7a; --gold-d:#8a6516; --bg:#16171f; --card:#20222d; --card2:#262935; --ink:#e8eaf2; --mut:#9aa0b5; --line:rgba(255,255,255,.08); --green:#3ddc84; --red:#ff6b6b; }
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'IRANSansXFaNum',sans-serif;background:var(--bg);color:var(--ink);line-height:1.9;min-height:100vh}
a{color:var(--gold);text-decoration:none}
.layout{display:grid;grid-template-columns:230px 1fr;min-height:100vh}
.sidebar{background:#1b1d26;border-left:1px solid var(--line);padding:20px 14px;position:sticky;top:0;height:100vh;overflow-y:auto}
.side-brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:16px;margin-bottom:26px;padding:0 8px}
.dot{width:12px;height:12px;border-radius:50%;background:linear-gradient(135deg,#f0cf7a,#a87c1f);flex-shrink:0}
.side-nav a{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:12px;color:var(--mut);font-size:14px;margin-bottom:4px;transition:all .2s}
.side-nav a:hover{background:rgba(255,255,255,.04);color:var(--ink)}
.side-nav a.active{background:linear-gradient(135deg,rgba(240,207,122,.16),rgba(168,124,31,.16));color:var(--gold-l);font-weight:700}
.side-nav .nbadge{margin-right:auto;background:var(--red);color:#fff;font-size:11px;min-width:20px;height:20px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;padding:0 6px}
.side-user{margin-top:20px;border-top:1px solid var(--line);padding-top:14px;font-size:12.5px;color:var(--mut);padding-right:8px}
.side-user b{color:var(--ink)}
.main{padding:26px;min-width:0}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;flex-wrap:wrap;gap:10px}
h1{font-size:19px}
.msg{background:rgba(61,220,132,.12);border:1px solid rgba(61,220,132,.35);color:#5fe0a0;padding:10px 16px;border-radius:12px;margin-bottom:16px}
.err{background:rgba(255,107,107,.1);border:1px solid rgba(255,107,107,.35);color:#ff8d8d;padding:10px 16px;border-radius:12px;margin-bottom:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;margin-bottom:20px}
.card h2{font-size:15px;color:var(--gold);margin-bottom:14px;border-bottom:1px solid var(--line);padding-bottom:9px}
label{font-size:12.5px;color:var(--mut);display:block;margin-bottom:4px}
input,select,textarea{font-family:inherit;font-size:14px;background:#171821;border:1px solid var(--line);color:var(--ink);border-radius:10px;padding:8px 12px;width:100%}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--gold)}
textarea{resize:vertical;min-height:80px}
.btn{font-family:inherit;font-size:13.5px;font-weight:700;cursor:pointer;border:none;border-radius:10px;padding:9px 22px;background:linear-gradient(135deg,#f0cf7a,#d4a437,#a87c1f);color:#241a05;transition:transform .2s;display:inline-block}
.btn:hover{transform:translateY(-2px)}
.btn-ghost{background:transparent;border:1px solid var(--line);color:var(--mut)}
.btn-danger{background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.35);color:#ff8d8d}
.btn-sm{padding:5px 14px;font-size:12.5px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{font-weight:700;text-align:right;padding:8px;color:var(--gold);border-bottom:1px solid var(--line);font-size:12px;white-space:nowrap}
td{padding:7px 8px;border-bottom:1px solid var(--line);vertical-align:middle}
tr:hover td{background:rgba(255,255,255,.02)}
.num{font-variant-numeric:tabular-nums;white-space:nowrap}
.final{color:var(--gold-l);font-weight:700}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.grid3{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:20px}
.stat-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px}
.stat-card .s-label{font-size:12px;color:var(--mut)}
.stat-card .s-value{font-size:26px;font-weight:800;background:linear-gradient(135deg,#f0cf7a,#d4a437);-webkit-background-clip:text;background-clip:text;color:transparent;font-variant-numeric:tabular-nums}
.stat-card .s-sub{font-size:11.5px;color:var(--mut)}
.hint{font-size:12px;color:var(--mut)}
.pill{display:inline-block;padding:2px 10px;border-radius:12px;font-size:11.5px;background:rgba(255,255,255,.06);color:var(--mut)}
.pill.ok{background:rgba(61,220,132,.12);color:var(--green)}
.pill.warn{background:rgba(255,179,92,.12);color:#ffb35c}
.sticky-save{position:sticky;bottom:12px;text-align:center;margin-top:8px;z-index:5}
.sticky-save .btn{box-shadow:0 10px 30px rgba(0,0,0,.55);padding:11px 42px}
.login-box{max-width:380px;margin:12vh auto;background:var(--card);border:1px solid var(--line);border-radius:20px;padding:34px;text-align:center}
.login-box h1{display:flex;justify-content:center;align-items:center;gap:10px;margin-bottom:8px;font-size:18px}
.login-box p{color:var(--mut);font-size:13px;margin-bottom:20px}
.login-box input{margin-bottom:12px;text-align:center}
td input[type=text],td select{min-width:90px}
.menu-toggle{display:none}
@media(max-width:900px){
  .layout{grid-template-columns:1fr}
  .sidebar{position:fixed;right:-240px;width:230px;z-index:50;transition:right .3s}
  .sidebar.open{right:0}
  .menu-toggle{display:inline-block;background:var(--card);border:1px solid var(--line);color:var(--gold);border-radius:10px;padding:7px 14px;cursor:pointer;font-family:inherit}
  .grid2{grid-template-columns:1fr}
}
</style>
</head>
<body>
<?php
}

function panel_nav(): array {
    $pdo = meyar_db();
    $unread = 0;
    try {
        $unread = (int)$pdo->query("SELECT COALESCE(SUM(admin_unread),0) s FROM threads WHERE status='open'")->fetch()['s'];
    } catch (Throwable $e) {}
    return [
        ['dashboard', 'index.php',    'chart-line-data-02', 'داشبورد', 0],
        ['prices',    'prices.php',   'wallet-01', 'تعدیل قیمت‌ها', 0],
        ['items',     'items.php',    'coins-01', 'ارزها و سکه‌ها', 0],
        ['layout',    'layout.php',   'layout-01', 'چیدمان صفحه', 0],
        ['chat',      'chat.php',     'message-01', 'چت کاربران', $unread],
        ['users',     'users.php',    'user-group', 'کاربران و نقش‌ها', 0],
        ['settings',  'settings.php', 'settings-01', 'تنظیمات', 0],
    ];
}

function panel_header(string $title, string $active = ''): void {
    $u = meyar_user();
    panel_css_head($title);
    ?>
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <div class="side-brand"><span class="dot"></span> پنل معیار</div>
    <nav class="side-nav">
      <?php foreach (panel_nav() as $n):
          if (!meyar_can($n[0])) continue; ?>
        <a href="<?= $n[1] ?>" class="<?= $active === $n[0] ? 'active' : '' ?>">
          <i class="hgi-stroke hgi-<?= meyar_h($n[2]) ?>" aria-hidden="true"></i> <?= $n[3] ?>
          <?php if ($n[4] > 0): ?><span class="nbadge"><?= meyar_fa_num((string)$n[4]) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a href="../" target="_blank"><i class="hgi-stroke hgi-globe-02" aria-hidden="true"></i> مشاهده سایت</a>
      <a href="?logout=1"><i class="hgi-stroke hgi-logout-01" aria-hidden="true"></i> خروج</a>
    </nav>
    <div class="side-user">
      <b><?= meyar_h($u['name']) ?></b><br>
      نقش: <?= meyar_h($u['role']) ?>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <h1><?= meyar_h($title) ?></h1>
      <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="hgi-stroke hgi-menu-01" aria-hidden="true"></i> منو</button>
    </div>
    <?php
}

function panel_footer(): void {
    ?>
  </div>
</div>
</body>
</html>
    <?php
}

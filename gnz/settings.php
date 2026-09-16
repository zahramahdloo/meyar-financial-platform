<?php
/** MEYAR — تنظیمات کلی سایت */
require_once __DIR__ . '/_panel.php';
panel_guard('settings');

$settings = meyar_load_settings();
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!meyar_csrf_ok()) {
        $err = 'توکن امنیتی نامعتبر.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_general') {
            $settings['site_phone']     = trim((string)($_POST['site_phone'] ?? ''));
            $settings['site_email']     = trim((string)($_POST['site_email'] ?? ''));
            $settings['site_instagram'] = trim((string)($_POST['site_instagram'] ?? ''));
            $settings['online_badge']   = !empty($_POST['online_badge']) ? 1 : 0;
            meyar_save_settings($settings) ? $msg = 'تنظیمات ذخیره شد ✔' : $err = 'خطا در ذخیره.';
        }
        if ($action === 'clear_cache') {
            @unlink(MEYAR_CACHE_FILE);
            foreach (glob(MEYAR_DATA . '/history_*.json') ?: [] as $f) @unlink($f);
            $msg = 'کش قیمت‌ها و تاریخچه پاک شد ✔';
        }
        $settings = meyar_load_settings();
    }
}

$market = meyar_build_prices();
$csrf = meyar_csrf();

panel_header('تنظیمات', 'settings');
if ($msg) echo '<div class="msg">' . meyar_h($msg) . '</div>';
if ($err) echo '<div class="err">' . meyar_h($err) . '</div>';
?>

<div class="card">
  <h2>اطلاعات تماس و نمایش</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="save_general">
    <div class="grid3" style="margin-bottom:14px">
      <div><label>شماره تماس</label><input type="text" name="site_phone" value="<?= meyar_h($settings['site_phone']) ?>" style="direction:ltr;text-align:left"></div>
      <div><label>ایمیل</label><input type="text" name="site_email" value="<?= meyar_h($settings['site_email']) ?>" style="direction:ltr;text-align:left"></div>
      <div><label>اینستاگرام (آدرس کامل)</label><input type="text" name="site_instagram" value="<?= meyar_h($settings['site_instagram']) ?>" style="direction:ltr;text-align:left"></div>
    </div>
    <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
      <input type="checkbox" name="online_badge" value="1" <?= !empty($settings['online_badge']) ? 'checked' : '' ?> style="width:auto">
      نمایش چراغ سبز «آنلاین هستیم» بالای سایت
    </label>
    <button class="btn" type="submit">💾 ذخیره</button>
  </form>
</div>

<div class="card">
  <h2>وضعیت منبع قیمت (TGJU)</h2>
  <p style="font-size:14px">
    آخرین دریافت موفق: <b class="num"><?= meyar_h($market['updated']) ?></b>
    <?= $market['stale'] ? '<span class="pill warn">کش قدیمی — اتصال برقرار نشد</span>' : '<span class="pill ok">متصل ✔</span>' ?>
  </p>
  <form method="post" style="margin-top:10px">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="clear_cache">
    <button class="btn btn-ghost" type="submit">🔄 پاک کردن کش و دریافت مجدد</button>
  </form>
</div>

<div class="card">
  <h2>راهنمای سریع</h2>
  <p class="hint">
    • تعدیل قیمت (مثلاً +۲۰۰ هزار تومان به سکه تمام): بخش «تعدیل قیمت‌ها».<br>
    • افزودن/حذف ارز و سکه، سئو و توضیحات صفحه هر آیتم: بخش «ارزها و سکه‌ها».<br>
    • جابه‌جایی جدول‌های صفحه اصلی با درگ‌اند‌دراپ: بخش «چیدمان صفحه».<br>
    • تعریف کاربر و نقش با مجوزهای دلخواه: بخش «کاربران و نقش‌ها».<br>
    • پاسخ به پیام بازدیدکننده‌ها: بخش «چت کاربران».<br>
    • نقشه سایت برای گوگل: <a href="../sitemap.php" target="_blank">sitemap.xml</a> — این آدرس را در Google Search Console ثبت کنید.
  </p>
</div>

<?php panel_footer(); ?>

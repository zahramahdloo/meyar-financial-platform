# Meyar Financial Platform

Financial market platform for tracking gold, currency and market data.

## Features

- Gold and currency price tracking
- Market history API
- Admin dashboard
- AI chat assistant
- Rule-based market analysis from the site's current dollar, gold, coin and silver data

## تحلیل هوشمند بازار

تحلیل بازار بدون سرویس خارجی و بدون کلید API تولید می‌شود. مرورگر فقط endpoint داخلی `api/market-insight-detail.php` را فراخوانی می‌کند؛ endpoint قیمت و تاریخچه‌ی موجود چهار بازار دلار، طلای ۱۸ عیار، سکه امامی و نقره ۹۹۹.۹ را با قواعد شفاف بررسی می‌کند، داده‌ی stale یا ناقص را اعلام می‌کند و پاسخ را تا ۵ دقیقه cache می‌کند. متن خروجی فقط plain text است و در frontend با `textContent` نمایش داده می‌شود.

## اتصال ایمیلی چت

برای اعلان پیام‌های جدید و دریافت پاسخ از Gmail، متغیرهای بخش `Chat email bridge` در `.env` را تنظیم کنید. برای Gmail باید IMAP فعال باشد و معمولاً از App Password استفاده شود؛ رمز اصلی حساب را در پروژه ذخیره نکنید.

اسکریپت `cron/mail-sync.php` را هر دقیقه روی سرور اجرا کنید تا پاسخ‌هایی که با عنوان `[MEYAR #thread-token]` ارسال می‌شوند، در همان گفتگوی سایت ثبت شوند:

```cron
* * * * * /usr/bin/php /path/to/meyar/cron/mail-sync.php >> /path/to/meyar/data/mail-sync.log 2>&1
```
- Price management system

## Technology

- PHP
- SQLite
- JavaScript
- CSS

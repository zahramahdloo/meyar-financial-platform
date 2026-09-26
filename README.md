# Meyar Financial Platform

Financial market platform for tracking gold, currency and market data.

## Features

- Gold and currency price tracking
- Market history API
- Admin dashboard
- AI chat assistant

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

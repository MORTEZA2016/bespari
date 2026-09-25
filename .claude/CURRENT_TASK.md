# Current Task

> این فایل مهم‌ترین فایل برای Resume کردن پروژه است.
> آخرین به‌روزرسانی: 2026-09-26

## Task ID
BSPR-004 — اصلاح تاریخ شمسی (الگوریتم جلالی + شمسی‌سازی مستقل نمایش تاریخ پنل)

## Status
COMPLETED — همه‌ی تست‌ها PASS شدند و ۳ فایل روی dev مستقر شد.

## Goal
۱) `Helpers::gregorian_to_jalali()` الگوریتم اشتباهی داشت (۱۴۰۵ شمسی را ۲۳۷۷ نشان می‌داد)؛
۲) نمایش تاریخ پنل به پک محلی سایت وابسته بود. هدف: الگوریتم صحیح + شمسی‌سازی مستقل.

## Completed
- جایگزینی `gregorian_to_jalali()` با الگوریتم استاندارد جلالی.
- افزودن `Helpers::to_jalali_date()` + refactor `to_jalali()`.
- `date_fa` (خط تولید) و ستون تاریخ لیست سفارشات پنل به `to_jalali_date()`.
- استقرار روی dev + تست کامل.

## Key Results
- ۱۱ تاریخ مرجع معروف PASS (نوروز، کبیسه‌های میلادی/شمسی، مرز قرن).
- ۳۲۸۷ روز متوالی (۲۰۱۸→۲۰۲۶) در برابر `IntlCalendar` فارسی ICU: ۰ عدمتطابق.
- A/B زنده تحت وردپرس: خروجی قدیم (`mysql2date`) و جدید (`to_jalali_date`) دقیقاً یکسان
  (۸ تاریخ شامل مرز نیمه‌شب با timezone سایت +03:30) → بدون رگرسیون UI.
- روی dev، `mysql2date` خودش شمسی برمی‌گرداند، پس کاربر تغییری نمی‌بیند؛ اما اکنون
  نمایش شمسی روی هر سایتی مستقل از پک محلی کار می‌کند.

## Next Step
Task بسته شد. هیچ Task فعالی وجود ندارد (BSPR-001/002/003/۰۰۴ همه کامل).
منتظر دستور بعدی کاربر.

## Files Changed (BSPR-004)
- اصلاح: `includes/Support/Helpers.php`،
  `includes/Modules/Production/ProductionService.php`،
  `includes/Api/PanelRestController.php`

## Important Decisions
- الگوریتم استاندارد جلالی به‌جای patch روی الگوریتم غلط.
- `to_jalali_date` همان تفسیر زمانی `mysql2date('Y/m/d')` را حفظ کرد (timezone پیش‌فرض PHP)
  تا رگرسیون مرز روز پیش نیاید.

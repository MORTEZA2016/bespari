# Current Task

> این فایل مهم‌ترین فایل برای Resume کردن پروژه است.
> آخرین به‌روزرسانی: 2026-09-26

## Task ID
BSPR-005 — رفع باگ افزودن کاربر + پیام موفقیت CRUD + دسترسی برند/دسته‌بندی بازاریاب

## Status
COMPLETED — پیاده‌سازی + استقرار روی dev + تست کامل API (همه PASS).

## Goal
۱) فرم افزودن کاربر هیچ پیامی نمی‌داد و چیزی اضافه نمی‌کرد؛
۲) بعد از کارها (افزودن/ثبت سفارش و غیره) پیام نتیجه نمایش داده شود؛
۳) هنگام ساخت بازاریاب، به او برند + دسته‌بندی محصول دسترسی داده شود و فقط
برای همان‌ها بتواند سفارش ثبت کند.

## Completed
- **باگ پیام صامت**: `api()` اکنون پاسخ `ok:false` با HTTP 200 را هم رد می‌کند
  (دلیل اصلی: مثلاً ایمیل تکرادی بدون هیچ پیامی به‌ظاهر موفق می‌شد).
- **پیام ماندگار**: `crudState.pendingAlert` — پیام بعد از رندر مجدد جدول نمایش داده
  می‌شود (قبلاً بلافاصله با re-render از بین می‌رفت).
- **پیام نتیجه از backend**: `handle_write/handle_delete` پیام پیش‌فرض با برچسب فیچر
  می‌سازند؛ `write_users` پیام کامل با نام کاربر و تعداد برند/دسته برمی‌گرداند.
- **دسترسی بازاریاب**: جداول `seller_brands`/`seller_categories` (self-healing) +
  `SellerAccess` + فیلدهای checkboxes شرطی در فرم کاربران + اکشن «ویرایش» +
  محدودسازی POS (`pos/products`، `pos/channels`) و اعتبارسنجی `pos/order`/`calculate`.

## Key Results (تست زنده روی dev)
| تست | نتیجه |
|---|---|
| ایمیل تکرادی → پیام خطا | PASS |
| ساخت بازاریاب با برند ۱ + دسته ۱۷ → پیام موفقیت کامل | PASS |
| ستون «دسترسی بازاریاب» در لیست کاربران | PASS |
| `pos/products` بازاریاب: فقط محصولات مجاز (۱ از ۳) | PASS |
| `pos/channels` بازاریاب: فقط کانال‌های برندهای مجاز | PASS |
| سفارش محصول مجاز → ثبت شد | PASS |
| سفارش محصول دسته غیرمجاز → 403 | PASS |
| سفارش محصول بدون برند → 403 | PASS |
| ویرایش و گسترش دسترسی → محصولات بیشتر در POS نمایش داده شد | PASS |
| داده‌ی تست پس از تست‌ها کاملاً پاک و بازگردانی شد | PASS |

## Next Step
Task بسته شد. هیچ Task فعالی وجود ندارد (BSPR-001 تا ۰۰۵ کامل).
منتظر دستور بعدی کاربر.

## Files Changed (BSPR-005)
- **اصلاح**: `assets/js/app.js` (api، pendingAlert، checkboxes، showWhen، edit)؛
  `assets/css/app.css` (`.bp-checks`)؛ `includes/Core/Activator.php` (۲ جدول +
  self-healing)؛ `includes/Core/Plugin.php` (ثبت migration)؛
  `includes/Api/ErpRestController.php` (users form/access/values/messages)؛
  `includes/Api/PosRestController.php` (scope + validation)؛
  `includes/Modules/Product/ProductRepository.php` (`brand__in`/`category__in`)
- **جدید**: `includes/Modules/Seller/SellerAccess.php`

## Important Decisions
- فروشنده = کاربر وردپرس با نقش `bespari_seller` (جدول sellers وجود ندارد)؛
  `orders.seller_id` همان user_id است.
- محصول مجاز = برند مجاز **و** دسته مجاز (هر دو).
- ادمین/حسابدار (`bespari_manage_orders`) مشمول محدودیت نیست (`is_restricted`).
- کانال‌های بازاریاب = کانال‌های متصل به برندهای مجاز (brand_channels).
- داده‌ی تست دسترسی روی dev پس از تست‌ها کامل پاک شد.

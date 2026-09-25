# PROJECT_CONTEXT — بسپاری ERP

> Snapshot کوتاه و واقعی وضعیت فعلی پروژه. این فایل جایگزین سورس‌کد نیست.
> تاریخ به‌روزرسانی: 2026-09-25

## هدف پروژه

افزونه‌ی وردپرس/ووکامرس به نام **بسپاری ERP** برای مدیریت فروش چندکاناله (multi-channel sales):
ثبت سفارشات، قیمت‌گذاری پویا، تسویه، پورسانت بازاریاب‌ها، انبار، حمل، مرجوعی، حسابداری و گزارشات.
برندها و بازاریاب‌ها روی کانال‌های مختلف (دیجی‌کالا، با سلام، ترب، ایمالز، اسنپ شاپ، دیوار، ...) می‌فروشند.

## معماری کلی

- زبان: PHP 8.1+ با namespace و **PSR-4** (پیشوند `Bespari\`)، بدون composer — autoloader اختصاصی در `includes/Core/Autoloader.php`.
- الگوی: Module + Repository + Service + Model برای هر حوزه.
- ثبت همه‌چیز در `Plugin::boot_modules()` (مرکز واحد).
- بدون table سفارشی برای تنظیمات افزونه (options/transients/usermeta)؛ جداول اختصاصی فقط برای داده‌های تراکنشی.
- هوک‌های اختصاصی `bespari_*` برای رویدادهای داخلی (decoupling بین ماژول‌ها).

## ساختار Plugin

```text
bespari-core.php              # bootstrap، ثابت‌ها، autoloader، activation
includes/Core/                # Plugin (singleton), Activator, Deactivator, Autoloader
includes/Modules/<Name>/      # <Name>.php (register) + Service + Repository + Model
includes/Admin/               # Menu, Assets, Pages/, Panel/
includes/Api/                 # REST controllers
includes/Support/             # Helpers, BaseRepository, AuditLog
bespari-agent/                # افزونه‌ی اقماری (مستقل، قابل نصب روی سایت بازاریاب)
assets/{css,js}/              # admin, pos, panel, app
languages/bespari-core-fa_IR.po
templates/admin/
```

## bespari-agent

افزونه‌ی مجزا که روی **سایت بازاریاب** نصب می‌شود و به سایت مادر بسپاری متصل می‌شود:

- `Http.php` — درخواست‌های امضاشده HMAC-SHA256، لاگ اتصال.
- `OrderSync.php` — Push سفارش‌های ووکامرس هر ۵ دقیقه (صف در option + retry تا ۵ بار + idempotency با `external_ref`).
- `PriceSync.php` — Pull قیمت/موجودی هر ۳۰ دقیقه.
- `Admin.php` — صفحه تنظیمات + تست handshake.

روی سایت dev به‌صورت `bespari/bespari-agent/` (زیرپوشه‌ی افزونه اصلی) قرار دارد.

## /panel/ (اپ مستقل)

پنل تک‌صفحه‌ای (SPA) که در مسیر `/panel/` سرو می‌شود — کاملاً جدا از wp-admin:

- `AppPage.php` — rewrite rule + fallback admin-post + رندر shell خالی.
- `assets/js/app.js` + `assets/css/app.css` — کلاینت (hash routing، POS، زنگوله اعلان).
- احراز هویت با **توکن Bearer** (نه کوکی): `AppAuth.php` — توکن `<user_id>:<hex64>`، فقط هش sha256 در usermeta، ۷ روزه.
- نیازمندی دسترسی: داشتن حداقل یکی از capهای `bespari_*`.

## API

مسیرها زیر `bespari/v1/`:

| گروه | مسیرها | کنترلر |
|---|---|---|
| auth | `/auth/login`, `/auth/logout`, `/auth/me` | `AuthRestController` |
| panel | `/panel/{dashboard,orders,products,channels,sellers,commissions,warehouse,reports}` | `PanelRestController` |
| notifications | `/notifications/{list,unread-count,read,read-all}` | `NotificationRestController` |
| pos | `/pos/{products,channels,calculate,order}` | `PosRestController` |

احراز هویت: توکن Bearer یا کوکی وردپرس (`AppAuth::authenticate`). Guard دسترسی per-view با cap.

## Database

دیتابیس dev: `testdbname` (root، بدون رمز، prefix `wp_`).
۲۴ جدول `wp_bespari_*` شامل: channels, channel_settlement_periods, brands, brand_channels, products, product_channel, pricing_rules, orders, order_items, settlements, stock_movements, shipments, shipment_batches, returns, requests, transactions, invoices, agents, agent_log, audit_log, commissions, payouts, channel_invoices, notifications.
جزئیات کامل: `.claude/context/database.md`.

## Frontend

- پنل مستقل `/panel/` — SPA خام (بدون framework)، RTL، با توکن.
- POS داخل همان پنل — گرید محصول، سبد، محاسبه‌ی زنده از `/pos/calculate`، ثبت از `/pos/order`.
- wp-admin صفحات کلاسیک (PHP render + `assets/js/admin.js`).
- `nx_styles.css` در ریشه — استایل خارجی (LayoutDrop/NexLink) — جزء افزونه نیست.

## Moduleهای مهم

| ماژول | مسئولیت |
|---|---|
| Pricing/PricingEngine | موتور قیمت‌گذاری پویا (cash/credit، قوانین، snapshot) |
| Finance/Calculator | جمع‌زندی چندآیتمی سفارش روی PricingEngine |
| Order | چرخه‌ی سفارش + status + settlement |
| Notification | اعلان خودکار روی هوک‌های `bespari_*` |
| Optimization | کش آمار داشبورد + cleanup روزانه + invalidation |
| Settlement/Commission/Payout | تسویه، پورسانت، پرداخت |
| Warehouse/Shipping/Returns | انبار، حمل، مرجوعی |
| Accounting | تراکنش و صورتحساب |
| Agent/Batch/ChannelInvoice | اتصالات، محموله، صورتحساب کانال |

## وضعیت فعلی پروژه

**پایدار و تست‌شده روی dev.** همه‌ی ماژول‌ها بوت می‌شوند، RESTها کار می‌کنند، پنل و POS در مرورگر تست شده‌اند.
نسخه: 1.0.0.

## Task فعلی

هیچ Task فعالی وجود ندارد. BSPR-001/002/003 و اصلاح شمسی‌سازی (BSPR-004) کامل و بسته
شده‌اند. منتظر دستور کاربر برای تسک جدید.

## مشکلات شناخته‌شده

- در حل شد: ماژول Optimization به `boot_modules()` اضافه شد (BSPR-001).
- باگ POS (`ProductRepository::get_by_id` / stdClass→ProductModel) که در جلسه ۲۲-۲۳ سپتامبر fatal می‌داد، در سورس فعلی **برطرف شده** (`get_full()` مدل برمی‌گرداند) — تاییدشده با تست زنده.
- **اصلاح شد (BSPR-004):** `Helpers::gregorian_to_jalali()` الگوریتم اشتباهی داشت (۱۴۰۵ را ۲۳۷۷ نشان می‌داد).
  با الگوریتم استاندارد جلالی جایگزین شد؛ ۳۰/۳۰ تست واحد + ۳۲۸۷ روز متوالی در برابر
  `IntlCalendar` فارسی ICU + A/B زنده تحت وردپرس (ده تاریخ شامل مرز نیمه‌شب/نوروز/کبیسه).
- محصولات تستی موجودی `-1` دارند (داده‌ی تست، نه باگ).
- سفارش‌های تستی بدون بازاریاب (`seller_id=0`) هستند، پس لیست پورسانت‌ها درستاً خالی است.
- ستون `entity` در `wp_bespari_audit_log` به این نام نیست (تست دستی مرا خراب کرد؛ نام واقعی ستون را قبل از کوئری زدن بررسی کن).

## آخرین تغییرات مهم

- 2026-09-26 (BSPR-004): اصلاح `gregorian_to_jalali` + افزودن `Helpers::to_jalali_date()` +
  شمسی‌سازی مستقل `date_fa` (خط تولید) و ستون تاریخ لیست سفارشات پنل (دیگر وابسته به
  پک محلی سایت نیست).
- 2026-09-25 (BSPR-003): انتقال کامل به پنل مستقل `/panel/`، ErpRestController (۱۳ فیچر)،
  مدیریت کاربران و توکن از داخل پنل، wp-admin فقط صفحه‌ی معرفی.
- 2026-09-25 (BSPR-002): منوی خط تولید در `/panel/`، ماژول Production، نقش مدیر تولید،
  status `produced`، فیلدهای کانال (الویت تولید/بازه ارسال)، migration self-healing.
- 2026-09-24/25 (BSPR-001): اضافه شدن Optimization به `boot_modules()`، استقرار کامل روی dev، تست کامل مرورگری و API.
- ۲۲-۲۳ سپتامبر: ساخت اپ مستقل `/panel/`، ماژول Notification، auth با توکن، Optimization، بهبود cleanup/uninstall.

## Next Step

هیچ Task فعالی وجود ندارد — همه‌ی تسک‌ها کامل شده‌اند. منتظر دستور کاربر.
~Delori Product Enhancements به تصمیم کاربر کنتل شد (مرجع: DEC-008) — شروع نشود.~

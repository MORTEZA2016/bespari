# DECISIONS — تصمیمات ماندگار معماری پروژه

> فقط تصمیمات مهم و پایدار معماری/ساختار. Bug fixهای معمولی و تغییرات کوچک اینجا ثبت **نمی‌شوند**.
> هر تصمیم باید دلیل خود را داشته باشد تا در آینده قابل دفاع باشد.

---

## DEC-001

**Decision:**
هر حوزه‌ی کاری به‌صورت یک Module مستقل با چهار فایل `<Name>.php` (register) + `<Name>Service` + `<Name>Repository` + `<Name>Model` پیاده‌سازی شود.

**Reason:**
جداسازی清晰的 مسئولیت‌ها: register فقط هوک‌ها را وصل می‌کند، Service منطق را دارد، Repository فقط DB را می‌زند، Model داده‌ی typed است. اضافه/حذف حوزه بدون لمس بقیه ممکن می‌شود.

**Date:** فاز اول ساخت (سپتامبر ۲۰۲۶)

**Affected Areas:** کل `includes/Modules/`، `includes/Core/Plugin.php::boot_modules()`

---

## DEC-002

**Decision:**
بدون composer. از autoloader اختصاصی PSR-4 در `includes/Core/Autoloader.php` با پیشوند `Bespari\` → `includes/` استفاده شود.

**Reason:**
افزونه باید به‌صورت تک‌پوشه‌ای zip و روی هر نصب وردپرس کار کند بدون نیاز به autoload نصب یا وابستگی خارجی.

**Date:** فاز اول ساخت

**Affected Areas:** `bespari-core.php`، `includes/Core/Autoloader.php`، همه‌ی namespaceها

---

## DEC-003

**Decision:**
پنل کاربران به‌صورت یک اپ مستقل SPA در مسیر `/panel/` (نه داخل wp-admin) سرو شود، با احراز هویت مبتنی بر **توکن Bearer** (نه کوکی وردپرس).

**Reason:**
بازاریاب‌ها نباید به wp-admin دسترسی داشته باشند. توکن اجازه‌ی session مستقل (موبایل/دمای ستان) می‌دهد و خطر نشت کوکی admin را از بین می‌برد. فقط هش sha256 بخش تصادفی در usermeta ذخیره می‌شود.

**Date:** ۲۲-۲۳ سپتامبر ۲۰۲۶

**Affected Areas:** `includes/Admin/Panel/`، `includes/Api/{AppAuth,AuthRestController,PanelRestController,NotificationRestController}.php`، `assets/{js/app.js,css/app.css}`

---

## DEC-004

**Decision:**
ارتباط بین ماژول‌ها فقط از طریق هوک‌های اختصاصی `bespari_*` (نه فراخوانی مستقیم کلاس).

**Reason:**
ماژول Notification باید بدون وابستگی مستقیم به Order/Commission/Payout روی رویدادهای آن‌ها واکنش نشان دهد؛ حذف یا جایگزینی یک ماژول دیگرها را نمی‌شکند.

**Date:** ۲۳ سپتامبر ۲۰۲۶

**Affected Areas:** `do_action('bespari_order_created' | 'bespari_commission_approved' | 'bespari_payout_paid' | 'bespari_return_approved' | 'bespari_order_status_cancelled' | 'bespari_daily_cleanup')`

---

## DEC-005

**Decision:**
ماژول Optimization از transientها برای کش آمار داشبورد (۱۵ دقیقه) و یک cron روزانه برای cleanup استفاده کند.

**Reason:**
داشبورد روی جداول بزرگ کوئری‌های aggregate می‌زند؛ کش با invalidation رویدادی، هزینه را پایین نگه می‌دارد بدون اینکه داده‌ی staleness بیش از ۱۵ دقیقه ایجاد شود.

**Date:** ۲۳ سپتامبر ۲۰۲۶

**Affected Areas:** `includes/Modules/Optimization/Optimization.php`، `includes/Api/PanelRestController.php` (view_dashboard)

---

## DEC-006

**Decision:**
افزونه‌ی `bespari-agent` به‌صورت یک افزونه‌ی **مستقل و جداگانه** (نه بخشی از Core) ساخته شود.

**Reason:**
این افزونه روی سایت **بازاریاب** نصب می‌شود، در حالی که Core روی سایت مادر است. ترکیب آن‌ها در یک پلاگین تک‌پارچه امکان‌پذیر نیست. ارتباط از طریق REST امضاشده HMAC انجام می‌شود.

**Date:** ۲۲ سپتامبر ۲۰۲۶

**Affected Areas:** `bespari-agent/` (مستقل)

---

## DEC-007

**Decision:**
تنظیمات افزونه در options/transients/usermeta ذخیره می‌شود؛ جداول سفارشی فقط برای داده‌های تراکنشی.

**Reason:**
سازگاری با مهاجرت‌های آینده ساده‌تر و مطابق با قرارداد وردپرس است.

**Date:** فاز اول ساخت

**Affected Areas:** کل افزونه

---

## DEC-008

**Decision:**
پروژه‌ی **Delori Product Enhancements** به‌صورت افزونه‌ای کاملاً مستقل (نه بخشی از بسپاری) ساخته شود.

**Reason:**
این افزونه باید قابل نصب روی سایت live بدون همراهی ERP باشد و نباید وابسته به کلاس‌های بسپاری باشد. برنامه‌ی کامل در `.claude/delori-product-enhancements-plan.md`.

**Date:** ۱۹ سپتامبر ۲۰۲۶

**Affected Areas:** پروژه‌ی آینده (هنوز شروع نشده)

**نکته (۲۵ سپتامبر ۲۰۲۶):** این پروژه به تصمیم کاربر **کنسل شد** — ساخته نمی‌شود. برنامه در `.claude/delori-product-enhancements-plan.md` فقط به‌عنوان مرجع باقی می‌ماند. این رکورد صرفاً تاریخی است.

## DEC-009

**Decision:**
منوی «خط تولید» در **پنل مستقل `/panel/`** ساخته شد (نه به‌عنوان زیرمنوی wp-admin)، و نقش
«مدیر تولید» فقط cap اختصاصی `bespari_production_view` می‌گیرد (بدون `bespari_read_dashboard`).

**Reason:**
مکانیزم موجود `AppAuth::menus()` + `user_menus()` منوها را بر اساس cap فیلتر می‌کند، پس مدیر
تولید پس از لاگین به `/panel/` **فقط** منوی خط تولید را می‌بیند — دقیقاً مطابق درخواست کاربر.
ساختن صفحه‌ی کلاسیک wp-admin هم به UI سرور-side دوم نیاز داشت و نیاز «فقط این منو» را سخت‌تر
پر می‌کرد (منوی اصلی wp-admin به `bespari_read_dashboard` گره خورده).

**Date:** 2026-09-25

**Affected Areas:** `includes/Api/AppAuth.php::menus()`, `Activator::add_roles()`, `assets/js/app.js`

---

## DEC-010

**Decision:**
الویت آماده‌سازی سفارش **به کانال** تعریف می‌شود (`channels.production_priority`) و هنگام ثبت
سفارش روی آن snapshot می‌شود (`orders.production_priority`)؛ تاریخ تولید پیش‌فرض همان روز ثبت است.

**Reason:**
کاربر مشخص کرد الویت در زمان ثبت بر اساس کانال تعیین می‌شود (مثلاً دیجی‌کالا = ۱). Snapshot
شدن باعث می‌شود تغییرات آینده‌ی کانال روی سفارش‌های ثبت‌شده اثر نگذارد (همان منطق
`pricing_snapshot`). تاریخ تولید مجزا (`production_date`) امکان انتقال بین روزها را ممکن می‌کند
بدون اینکه `ordered_at` (تاریخ واقعی ثبت) دست بخورد.

**Date:** 2026-09-25

**Affected Areas:** `includes/Modules/Order/OrderService.php::create()`, `includes/Modules/Production/*`

---

## DEC-011

**Decision:**
برای نمایش تاریخ‌ها در خط تولید از `mysql2date()` استفاده شود، نه `Helpers::gregorian_to_jalali()`.

**Reason:**
الگوریتم `gregorian_to_jalali()` معیوب است (۱۴۰۵ شمسی را ۲۳۷۷ نشان می‌دهد). تابع فقط توسط
`to_jalali()` (خودش بی‌استفاده) خوانده می‌شود، بنابراین ریسکی ندارد. از طرفی در این نصب وردپرس
`mysql2date('Y/m/d', ...)` به‌خاطر locale فارسی خروجی شمسی می‌دهد — یعنی تاریخ‌ها شمسی هستند و
همزمان با قرارداد بقیه‌ی نماهای اپ (نمای سفارشات) یکپارچه می‌مانند.

**Date:** 2026-09-25

**Affected Areas:** `includes/Modules/Production/ProductionService.php::list_by_date()`

---

## DEC-012

**Decision:**
wp-admin دیگر هیچ منوی فیچری ندارد — تنها یک صفحه‌ی «معرفی افزونه + مزایا + لینک ورود
به پنل مستقل». همه‌ی ۲۲ منو فقط در `/panel/` (SPA) سرو می‌شوند و با cap گیت می‌شوند.

**Reason:**
کاربر خواست همه‌ی امکانات فقط از پنل مستقل قابل‌دسترس باشد و ادمین نیازی به ورود به
wp-admin نداشته باشد. صفحات wp-admin قبلی فقط رندرکننده‌های نازک روی سرویس‌ها بودند،
پرابرنده‌ی منطق نبودند — پس حذف آنها و سرو کردن از REST هیچ منطقی را از دست نداد.

**Date:** 2026-09-25

**Affected Areas:** `includes/Admin/Menu.php`, `includes/Admin/Pages/IntroPage.php`,
`includes/Core/Plugin.php` (حذف PosPage/PanelPage)، `includes/Api/AppAuth.php::menus()`

---

## DEC-013

**Decision:**
نماهای CRUD پنل از یک **renderer مبتنی بر schema** واحد (`renderCrud` در `assets/js/app.js`)
خوراک می‌گیرند — endpoint پاسخ `form.fields` (تعریف فیلدها) و `rowActions` (دکمه‌های
هر سطر) را برمی‌گرداند و یک کد برای همه‌ی ۱۳ فیچر کار می‌کند.

**Reason:**
نوشتن ۱۳ renderer اختصاصی ~۲۰۰ خطی در app.js غیرعملی بود (حجم و ریسک باگ). schema-driven
شدن باعث شد یک مسیر تستی برای همه‌ی فیچرها کافی باشد و افزودن فیچر جدید فقط یک متد
`read_*`/`write_*` در `ErpRestController` باشد.

**Date:** 2026-09-25

**Affected Areas:** `includes/Api/ErpRestController.php`, `assets/js/app.js::renderCrud`

---

## DEC-014

**Decision:**
مدیریت کاربران (ایجاد، تغییر نقش، حذف، صدور/ابطال توکن) کاملاً از داخل پنل انجام می‌شود
توسط ماژول `User` جدید + `ErpRestController`. توکن جدید یک‌بار در مودال نمایش داده می‌شود.

**Reason:**
کاربر صراحتاً خواست «از آنجا کاربر تعریف می‌شود و بهشان دسترسی می‌دهیم». `SellerService`
از قبل همین الگو (wp_insert_user + add_role) را داشت، پس تعمیم آن به همه‌ی نقش‌های
bespari طبیعی بود. صدور توکن از `AppAuth::issue` reuse شد.

**Date:** 2026-09-25

**Affected Areas:** `includes/Modules/User/UserService.php`, `includes/Api/ErpRestController.php`,
`Activator` (cap `bespari_manage_users`)

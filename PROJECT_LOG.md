# PROJECT_LOG — تاریخچه‌ی Taskهای پروژه

> هر Task یک ID و وضعیت دارد. وقتی این فایل بیش از حد بزرگ شد، تسک‌های قدیمی را
> به `.claude/history/YYYY-MM/` منتقل کن و فقط Summary آن‌ها را اینجا نگه دار.

---

## TASK-20260924-001 (BSPR-001)

**Status:** COMPLETED

**Goal:** ماژول Optimization در `includes/Core/Plugin.php` فقط import شده بود اما در `boot_modules()` ثبت نشده بود
(در نتیجه cron روزانه `bespari_daily_cleanup` و hookهای باطل‌سازی کش اجرا نمی‌شدند).
هدف: اصلاح، استقرار روی سایت dev و تست کامل فنی + مرورگری.

**Started:** 2026-09-24

**Completed:** 2026-09-25

**Changed Files:**
- `includes/Core/Plugin.php` — افزودن `'optimization' => new Optimization()` به `boot_modules()` (یک خط) و import قبلاً موجود.
- (روی dev) کل پلاگین استقرار شد: پوشه‌ی `includes/Modules/Optimization/` (جدید) + `Plugin.php` + `includes/Api/PanelRestController.php` + `uninstall.php` به‌روزرسانی شدند.

**Tests:**

| تست | نتیجه |
|---|---|
| PHP syntax check (همه‌ی فایل‌ها) | PASS |
| افزونه فعال در dev | PASS |
| بوت شدن Optimization (`Plugin::module('optimization')`) | PASS |
| ثبت cron `bespari_daily_cleanup` + schedule `bespari_daily` | PASS |
| hookهای invalidation (order_created/updated/cancelled) | PASS |
| hookهای Notification (commission/payout/return) | PASS |
| REST auth/me + ۸ نمای panel | PASS |
| REST notifications list/unread-count | PASS |
| REST pos products/channels/calculate/order | PASS |
| `pos/calculate` بدون fatal (باگ قبلی ProductModel رفع شد) | PASS |
| مرورگر: سایت + پنل + لاگین | PASS |
| مرورگر: dashboard/orders/products/channels/sellers/commissions/warehouse/reports | PASS |
| مرورگر: زنگوله اعلان | PASS |
| مرورگر: POS + ثبت سفارش `ORD-20260924-0003` + اعلان خودکار | PASS |
| مرورگر: logout و login مجدد | PASS |
| Optimization: ساخت کش داشبورد | PASS |
| Optimization: invalidation با سفارش جدید | PASS |
| Optimization: invalidation با لغو سفارش | PASS |
| Optimization: cleanup اعلان ۹۰ روزه پاک شد | PASS |

**Issues:**
- هنگام بررسی اولیه، لاگ Apache خطاهای fatal قدیمی (۲۲-۲۳ سپتامبر) از `ProductRepository::get_by_id()` و `stdClass → ProductModel` را نشان می‌داد. بررسی showed این باگ در سورس فعلی **از قبل رفع شده** (`get_full()` مدل برمی‌گرداند) — تاییدشده با تست زنده‌ی `pos/calculate`.
- تلاش برای درج ردیف تست در `audit_log` بهخاطر نام اشتباه ستون (`entity`) ناموفق بود — مشکل کوئری تست دستی من، نه کد افزونه. نام واقعی ستونها قبل از کوئری زدن باید بررسی شود.
- داده‌ی تست (۳ سفارش، چند اعلان، ۱ اعلان قدیمی حذف‌شده) روی dev باقی مانده — عمدی و قابل پاک‌شدن است.
- پشتیبان `wp-content/bespari-backup-20260924` روی dev exists (برای rollback).

**Important Decisions:**
- Optimization طبق طراحی اصلی به `boot_modules()` اضافه شد (یک خط) — نه refactor، نه بازنویسی.
- استقرار کامل سورس به dev به‌جای کپی فقط فایل تغییرکرده، تا اختلاف نسخه‌ها پیش نیاید (`.claude/` و `PROJECT_LOG.md` مستثنی).
- از تغییر هر فایل دیگری خودداری شد.

**Next Step:**
Task بسته شد. مرحله‌ی بعدی بر اساس درخواست کاربر: **ساخت زیرساخت Project Memory** (در حال انجام).
پس از آن: شروع **Delori Product Enhancements** (برنامه در `.claude/delori-product-enhancements-plan.md`).

---

## TASK-20260925-002

**Status:** COMPLETED

**Goal:** ساخت زیرساخت Project Memory برای پروژه تا Chatهای جدید بتوانند از همان نقطه ادامه دهند:
`PROJECT_CONTEXT.md`، `PROJECT_LOG.md` (این فایل)، `DECISIONS.md`، `.claude/START_HERE.md`،
`.claude/CURRENT_TASK.md`، `.claude/context/{architecture,database,api,frontend,modules}.md`، `.claude/history/`.

**Started:** 2026-09-25

**Completed:**
- `.claude/START_HERE.md` — راهنمای شروع Chat جدید + Workflow + قوانین Context.
- `PROJECT_CONTEXT.md` — snapshot وضعیت فعلی پروژه.
- `.claude/CURRENT_TASK.md` — وضعیت Task فعلی.
- `DECISIONS.md` — ۸ تصمیم معماری ماندگار.
- `.claude/context/architecture.md` — معماری و جریان بارگذاری.
- `.claude/context/database.md` — جداول و روابط.
- `.claude/context/api.md` — RESTها و احراز هویت.
- `.claude/context/frontend.md` — ساختار `/panel/` و SPA.
- `.claude/context/modules.md` — ماژول‌ها و مسئولیت‌ها.
- `.claude/history/` — ایجاد شد.
- `PROJECT_LOG.md` — بازنویسی به فرمت استاندارد با حفظ کامل BSPR-001.

**Changed Files (فقط فایل‌های Memory — کد اصلی تغییر نکرد):**
- `PROJECT_CONTEXT.md` (جدید)، `DECISIONS.md` (جدید)
- `.claude/START_HERE.md` (جدید)، `.claude/CURRENT_TASK.md` (جدید)
- `.claude/context/architecture.md` (جدید)، `database.md` (جدید)، `api.md` (جدید)، `frontend.md` (جدید)، `modules.md` (جدید)
- `.claude/history/` (پوشه‌ی جدید)
- `PROJECT_LOG.md` (بازنویسی با حفظ اطلاعات)

**Tests:**
- بررسی وجود فایلها — انجام شده در حین ساخت.
- تایید اینکه هیچ فایل PHP/JS/CSS اصلی تغییر نکرده — با diff قابل تایید.

**Issues / Blockers:**
- بدون blocker.
- نکته: `PROJECT_LOG.md` قبلاً شامل جزئیات بیشتر BSPR-001 بود (جدول پیشرفت ۸ مرحله‌ای). اطلاعات در این بازنویسی حفظ شد ولی به فرمت فشرده‌تر.

**Next Step:**
Task بسته شد. **Delori Product Enhancements به تصمیم کاربر کنسل شد** (۲۰۲۶-۰۹-۲۵) — ساخته
نمی‌شود و به‌عنوان مرحله‌ی بعد پیشنهاد نمی‌شود. فعلاً هیچ Task فعالی وجود ندارد؛ منتظر
دستور بعدی کاربر.

---

## TASK-20260925-003 (BSPR-002)

**Status:** COMPLETED (پیاده‌سازی + تست CLI کامل؛ تست مرورگر در BSPR-003 پوشش داده شد)

**Status:** IN PROGRESS — پیاده‌سازی کامل، تست مرورگری مانده.

**Goal:** منوی «خط تولید» در پنل `/panel/` برای نقش «مدیر تولید»: لیست سفارشات قابل‌تولید
هر روز (برند ← کانال ← اکاردئون الویت)، تقویم، انتقال به روزهای بعد («سفارش مهمان»)،
تیک آماده‌شدن + اعلان بازاریاب، دکمه ارسال بازاریاب، و فیلدهای الویت تولید/بازه ارسال در فرم کانال.

**Started:** 2026-09-25

**Completed:**
- schema + migration self-healing + نقش/cap (Activator, Plugin).
- ماژول Production جدید + REST controller (list/ready/move/ship).
- snapshot الویت و تاریخ تولید در OrderService::create؛ وضعیت produced.
- فیلدهای کانال + فرم؛ آیتم منو + role_label + badge + ship button در panel REST.
- SPA: renderProduction کامل + اتصال دکمه ارسال + استایل‌ها.
- استقرار کامل روی dev (htdocs/delori).
- تست CLI سرویس: ۱۲/۱۲ PASS. migration و ۴ مسیر REST تایید شدند. php -l و node --check OK.

**Changed Files:**
- Activator.php, Plugin.php, Order/OrderModel.php, Order/OrderService.php,
  Channel/ChannelModel.php, Channel/ChannelService.php, Admin/Pages/ChannelPage.php,
  Api/AppAuth.php, Api/PanelRestController.php
- جدید: Modules/Production/{Production,ProductionService,ProductionRepository}.php,
  Api/ProductionRestController.php
- assets/js/app.js, assets/css/app.css

**Issues:**
- تست مرورگر انجام نشده (Apache خاموش + کاربر مدیر تولید ساخته نشده).
- `Helpers::gregorian_to_jalali()` الگوریتم غلط است (بی‌استفاده؛ در این فیچر از mysql2date استفاده شد).
- داده تست: سفارش id=6 (produced→shipped، منتقل به 2026-09-26) + کانال ۱ دارای الویت ۱/بازه ارسال.

**Next Step:**
طبق `.claude/CURRENT_TASK.md` بخش «Next Exact Action»: استارت Apache → ساخت کاربر
مدیر تولید + توکن → تست مرورگر کامل (تب/اکاردئون/تقویم/تیک آماده‌شدن/ارسال بازاریاب/
اعلان) → COMPLETED + به‌روزرسانی Memory.

---

## TASK-20260925-004 (BSPR-003)

**Status:** COMPLETED

**Goal:** انتقال کامل افزونه به پنل مستقل `/panel/` — حذف همه‌ی منوهای wp-admin
(۱۹ زیرمنو → ۱ منوی معرفی)، انتقال ۱۲ فیچر به پنل، و مدیریت کامل کاربران (ایجاد،
نقش، صدور توکن) از داخل خود پنل.

**Started/Completed:** 2026-09-25

**Changed Files:**
- **جدید**: `includes/Api/ErpRestController.php` (REST یکپارچه ۱۳ فیچر + چاپ فاکتور)؛
  `includes/Admin/Pages/IntroPage.php` (صفحه‌ی معرفی + مزایا + لینک پنل)؛
  `includes/Modules/User/{User,UserService}.php` (مدیریت کاربران + توکن).
- **اصلاح**: `includes/Core/Plugin.php` (ثبت erp + ماژول user + migration + حذف PosPage/PanelPage)؛
  `includes/Admin/Menu.php` (۱۹ زیرمنو → ۱ منو)؛
  `includes/Api/AppAuth.php` (+۱۳ منو، META_KEY public، role_label گسترش‌یافته)؛
  `includes/Core/Activator.php` (cap `bespari_manage_users` + `maybe_sync_users_cap()`)؛
  `assets/js/app.js` (+~۴۳۰ خط: renderCrud + modal + preview + print + settings)؛
  `assets/css/app.css` (+۳۴ خط CRUD)؛ `assets/css/admin.css` (+۲۳ خط intro).
- **حذف**: ۲۰ `includes/Admin/Pages/*Page.php` (فقط BasePage + IntroPage ماند)؛
  `includes/Admin/PosPage.php`؛ `includes/Admin/Panel/{PanelPage,PanelViews}.php`؛
  `assets/{css,js}/pos.{css,js}`.

**Tests:**
| تست | نتیجه |
|---|---|
| php -l همه‌ی فایل‌ها | PASS |
| node --check app.js | PASS |
| مسیرهای erp در wp-json | PASS |
| erp/users لیست + فیلتر نقش | PASS |
| erp/brands create + list | PASS |
| erp/users create (modir-tolid) | PASS |
| erp/users/3/token صدور توکن | PASS |
| auth/me مدیر تولید → فقط منوی خط تولید | PASS |
| erp/users با توکن مدیر تولید → ۴۰۳ | PASS |
| auth/me admin → ۲۲ منو | PASS |
| Menu.php → دقیقاً ۱ منو | PASS |
| مرورگر: لاگین + ۲۲ منو + داشبورد | PASS |
| مرورگر: نمای کاربران (جدول، فیلتر، افزودن) | PASS |
| مرورگر: مودال صدور توکن (نمایش یک‌بار + کپی) | PASS |
| مرورگر: کاربر مدیر تولید → فقط خط تولید + تقویم شمسی | PASS |
| مرورگر: نمای تنظیمات (فرم + اطلاعات سیستم) | PASS |
| مرورگر: بدون خطای JS (فقط clipboard در حالت auto) | PASS |
| site/panel/wp-json همگی ۲۰۰ پس از پاک‌سازی | PASS |

**Important Decisions:**
- CRUD renderer مبتنی بر schema (یک کد برای ۱۳ فیچر) به‌جای ۱۳ renderer تکراری.
- همه‌ی Serviceهای ماژول موجود دست نخوردند — فقط خوانده شدند.
- admin_post handlers در ماژول‌ها نگه داشته شدند (cap+nonce guarded؛ UI حذف شد).
- توکن کاربر جدید یک‌بار در مودال با دکمه‌ی کپی نمایش داده می‌شود.
- seller scoping برای payouts/requests/accounting حفظ شد (`seller_scope`).

**Next Step:**
Task بسته شد. BSPR-002 (خط تولید) نیز در پی تست‌های این مرحله تأیید شد.
منتظر دستور بعدی کاربر.

---

## TASK-20260926-005 (BSPR-004)

**Status:** COMPLETED

**Goal:** رفع مشکل شناخته‌شده‌ی تاریخ شمسی: `Helpers::gregorian_to_jalali()` الگوریتم
اشتباهی داشت (مثلاً ۱۴۰۵ شمسی را ۲۳۷۷ نشان می‌داد) و نمایش تاریخ پنل به پک محلی سایت
وابسته بود. هدف: اصلاح الگوریتم + شمسی‌سازی مستقل و قابل‌اتکای نمایش تاریخ در پنل.

**Started/Completed:** 2026-09-26

**Changed Files:**
- `includes/Support/Helpers.php` — جایگزینی `gregorian_to_jalali()` با الگوریتم استاندارد
  جلالی (شامل شاخه‌ی gy<=1600، تقسیم ۱۲۰۵۳/۱۴۶۱ و_day-boundary)؛ افزودن
  `to_jalali_date()` (فقط بخش تاریخ)؛ refactor `to_jalali()` برای استفاده از آن.
- `includes/Modules/Production/ProductionService.php` — `date_fa` از `mysql2date('Y/m/d')`
  به `Helpers::to_jalali_date()` (فیلد `date` میلادی برای `<input type=date>` دست‌نخورده).
- `includes/Api/PanelRestController.php` — ستون تاریخ لیست سفارشات پنل به `to_jalali_date()`.

**Tests:**
| تست | نتیجه |
|---|---|
| php -l هر سه فایل | PASS |
| ۱۱ تاریخ مرجع معروف (نوروز ۱۳۵۷/۱۴۰۳/۱۴۰۵، کبیسه میلادی ۲۰۰۰/۲۰۲۴، روز اضافه شمسی ۱۴۰۳، مرز قرن ۲۰۹۹) | PASS |
| ۳۲۸۷ روز متوالی (۲۰۱۸-۰۱-۰۱ تا ۲۰۲۶-۱۲-۳۱) در برابر IntlCalendar فارسی ICU (مرجع مستقل) | PASS — ۰ عدمتطابق |
| `to_jalali_date` + `to_jalali` شامل ورودی‌های نامعتبر/خالی | PASS |
| زنده تحت وردپرس dev: A/B `mysql2date('Y/m/d')` (قدیمی) vs `to_jalali_date` (جدید) روی ۸ تاریخ شامل مرز نیمه‌شب (+03:30) | PASS — دقیقاً یکسان |
| زنده: `ProductionService::list_by_date` → `date_fa = ۱۴۰۵/۰۷/۰۴` و `date` میلادی | PASS |
| استقرار ۳ فایل روی dev | PASS |

**Issues:**
- نکته‌ی مهم: روی سایت dev خود `mysql2date` هم شمسی برمی‌گرداند (پک محلی سایت)، پس کاربر
  تغییری در UI نمی‌بیند. ارزش تغییر: نمایش تاریخ پنل دیگر به پک محلی وابسته نیست و روی
  هر سایتی شمسی و یکسان است. A/B زنده نشان داد خروجی قدیم و جدید در تمام نقاط یکسان است.
- `ext/intl` در XAMPP به‌صورت پیش‌فرض غیرفعال است (`php -d extension=intl` لازم بود).

**Important Decisions:**
- الگوریتم استاندارد جلالی (مشخصه‌ی شناخته‌شده) به‌جای patch روی الگوریتم غلط.
- `to_jalali_date` همان تفسیر زمانی `mysql2date('Y/m/d')` را دارد (هر دو timezone پیش‌فرض
  PHP برای تجزیه/قالب‌بندی) تا هیچ رگرسیون مرز روز پیش نیاید.

**Next Step:**
Task بسته شد. هیچ Task فعالی باقی نمانده؛ منتظر دستور بعدی کاربر.

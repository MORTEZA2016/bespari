# Frontend — پنل و UI

## پنل مستقل `/panel/`

### سرو shell
`includes/Admin/Panel/AppPage.php`:
- rewrite rule `^panel/?$` → `index.php?bespari_app=1`
- fallback: `admin-post.php?action=bespari_app` (بدون نیاز به flush)
- در `template_redirect` با priority 0 shell را رندر و `exit` می‌کند.
- shell: HTML خالی + `window.BP_CONFIG` (restUrl, homeUrl, version, brandName) + `assets/css/app.css` + `assets/js/app.js`.
- متادیتای `noindex,nofollow`، `dir="rtl"`.

### کلاینت `assets/js/app.js` (۷۴۹ خط، vanilla JS، IIFE)

ساختار:
```text
state {token, user, view, page}
getToken/setToken/clearToken   → localStorage
api(path, opts)                → fetch → JSON با مدیریت خطا
renderLogin(errorMsg)          → فرم لاگین
renderApp()                    → layout کامل (nav, topbar, bell, main)
hashchange listener            → routing با location.hash (#/view)
loadView(view)                 → fetch panel/<view> یا renderPos
renderTable(content, res)      → stat cards + table + pagination
POS block                      → cart, addToCart, setQty, setOverride, recalc, submitOrder
bell block                     → renderBell، notifTime (فارسی نسبی)، mark read
boot()                         → توکن → auth/me → renderApp یا renderLogin
```

### مسیریابی
- hash route: `#/dashboard`، `#/pos`، ...
- view نامعتبر → اولین منوی مجاز.
- منوها از `/auth/me` می‌آیند (مجاز بر اساس caps).

### POS
- جستجوی محصول با debounce ۳۰۰ms.
- cart در حافظه؛ quantity و price override دستی.
- `recalc` با debounce ۳۵۰ms → `/pos/calculate`.
- کانال‌های `needs_customer` فیلدهای نام/تلفن/آدرس را اجباری می‌کنند (پیام خطای فارسی).
- ثبت موفق → پیام موفقیت + خالی شدن سبد.

### زنگوله اعلان
- poll دوره‌ای `notifications/list` (setInterval در renderApp).
- شمارش unread به فارسی، زمان نسبی (لحظاتی پیش / X دقیقه پیش / ...).
- کلیک روی آیتم → mark read + باز کردن لینک.
- «خواندن همه» → `notifications/read-all`.
- بستن پنل با کلیک بیرون.

## صفحات wp-admin

- `includes/Admin/Menu.php` — ثبت منوها.
- `includes/Admin/Pages/*.php` — صفحات کلاسیک (BasePage مشترک).
- `includes/Admin/Panel/{PanelPage,PanelViews,AppPage}.php` — پنل ادمین + لینک به اپ مستقل.
- `includes/Admin/PosPage.php` — POS داخل ادمین (نسخه‌ی قدیمی‌تر).
- assets: `assets/{css,js}/{admin,pos,panel,app}.{css,js}`.

## استایل

- CSS با متغیرهای CSS (`--bp-*`) برای رنگ‌ها.
- RTL کامل، اعداد فارسی در UI.
- `nx_styles.css` در ریشه‌ی پروژه متعلق به **قالب NexLink** است، جزئی از افزونه‌ی بسپاری **نیست**.

## دسترس‌پذیری / امنیت

- همه‌ی ورودی‌های کاربر با `esc()` در کلاینت escape می‌شود.
- badgeهای HTML سرور-side از پیش `esc_attr`/`esc_html` شده‌اند.
- توکن فقط در localStorage؛ هرگز در URL.

## BSPR-003 — پنل مستقل کامل

wp-admin دیگر هیچ منوی فیچری ندارد. تنها منوی افزونه «بسپاری ERP» است که
`IntroPage` (معرفی + مزایا + دکمه‌ی ورود به `/panel/`) را نشان می‌دهد.

### ساختار جدید SPA
- `ERP_VIEWS` (۱۳ feature) از `renderCrud` و `api('erp/'+view)` خوراک می‌گیرند.
- `renderCrud`: stats + toolbar (search/filters/افزودن/preview) + table + ستون عملیات
  (rowActions) + pagination. مودال فرم از `form.fields` (schema) ساخته می‌شود.
- مودال: `openModal/closeModal`، `showSecret` (توکن/راز یک‌بار + کپی).
- `renderSettingsForm`: فرم تنظیمات + اطلاعات سیستم.
- viewهای قدیمی (dashboard/orders/.../reports) همچنان از `renderTable` استفاده می‌کنند.

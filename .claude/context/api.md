# API — RESTهای بسپاری

> همه‌ی مسیرها زیر `bespari/v1/`. namespace کامل: `F:/plugin/bespari/includes/Api/`.

## احراز هویت

کلاس مرکزی: `AppAuth.php`.

- توکن در هدر `Authorization: Bearer <token>` ارسال می‌شود.
- فرمت توکن: `<user_id>:<64 hex>`، فقط **هش sha256** بخش hex در usermeta (`bespari_app_tokens`) ذخیره می‌شود.
- طول عمر ۷ روز؛ امکان revoke.
- `AppAuth::authenticate($request)`: ابتدا توکن، در صورت نبود → کوکی وردپرس (`is_user_logged_in`).
- `AppAuth::menus()`: key → [label, caps (با `|` چندتایی)، icon]. guard per-view.
- `AppAuth::user_can`: OR روی caps؛ `can_access_panel`: داشتن حداقل یک منو.

## auth

| متد | مسیر | permission | توضیح |
|---|---|---|---|
| POST | `/auth/login` | public | `{username, password}` → `{ok, token, user}`؛ rate limit ۵ تلاش/۱۰ دقیقه per IP |
| POST | `/auth/logout` | authed | revoke توکن جاری |
| GET | `/auth/me` | authed | اطلاعات کاربر + منوهای مجاز |

کد دسترسی نقش: `bespari_read_dashboard` → مدیر، `bespari_seller_view` → بازاریاب.

## panel

| متد | مسیر | cap نمونه | توضیح |
|---|---|---|---|
| GET | `/panel/dashboard` | manage یا seller_view | stat cards + ۱۰ سفارش اخیر؛ **کش‌شده** (Optimization) |
| GET | `/panel/orders` | manage یا seller_view | صفحه‌بندی (`?paged=`)، ۲۰ در صفحه |
| GET | `/panel/products` | manage | ۵۰ محصول اخیر |
| GET | `/panel/channels` | manage_settings | لیست کانال‌ها |
| GET | `/panel/sellers` | manage_sellers | بازاریاب‌ها |
| GET | `/panel/commissions` | manage یا seller_view | آمار موجودی + لیست (seller فقط خودش) |
| GET | `/panel/warehouse` | manage_warehouse | ۵۰ محصول بر اساس موجودی |
| GET | `/panel/reports` | view_reports | فروش به تفکیک کانال (cancelledها excluded) |

**Scope:** اگر کاربر `bespari_read_dashboard` نداشته باشد، فقط داده‌های خودش را می‌بیند (`seller_scope()` → `get_current_user_id()`، وگرنه 0).
خروجی آماده‌ی رندر: `{ok, title, stats?, headers, rows, pages?}` — rows شامل HTML badge از پیش escape‌شده.

## notifications

| متد | مسیر | توضیح |
|---|---|---|
| GET | `/notifications/list?limit=` | `{ok, notifications, unread}` (limit 1..50) |
| GET | `/notifications/unread-count` | `{ok, unread}` |
| POST | `/notifications/read` | `{id}` → mark read (scoped به user) |
| POST | `/notifications/read-all` | mark all read |

## pos

| متد | مسیر | توضیح |
|---|---|---|
| GET | `/pos/products?s=&limit=` | جستجوی محصول |
| GET | `/pos/channels` | کانال‌ها + `needs_customer` |
| POST | `/pos/calculate` | `{channel_id, sale_type, seller_id, items[]}` → `{gross, deductions, net, profit, items}` |
| POST | `/pos/order` | همان ورودی + `customer_name/phone/address` برای کانال‌های needs_customer + `notes` |

`pos/calculate` و `pos/order` هر دو از `Finance\Calculator` → `Pricing\PricingEngine` استفاده می‌کنند.
**نکته‌ی مهم:** Calculator باید `ProductModel` بگیرد — متد درست `ProductRepository::get_full()` است (نه `get_by_id`).

## agent (سایت مادر)

مسیرهای دریافتی agent در `includes/Api/AgentRestController.php` — دریافت سفارش/قیمت از سایت بازاریاب.
سمت بازاریاب (`bespari-agent/`) به `bespari/v1/agent/*` درخواست امضاشده HMAC می‌زند.

## خطاها

- ۴۰۱ `rest_forbidden` وقتی permission callback false برگرداند.
- پاسخهای خطا شکل `{ok:false, message}` یا کدهای استاندارد WP REST.

## erp (BSPR-003 — همه‌ی فیچرهای ERP)

کنترلر یکپارچه `ErpRestController.php` زیر `bespari/v1/erp`:

| متد | مسیر | توضیح |
|---|---|---|
| GET | `/erp/{feature}` | view: `{ok,title,stats?,headers,rows,pages,form?,rowActions?,filters?}` |
| POST | `/erp/{feature}` | create |
| POST | `/erp/{feature}/{id}` | update |
| POST | `/erp/{feature}/{id}/{action}` | گردش‌کار (approve/reject/mark_paid/restock/rotate_secret/token/print) |
| DELETE | `/erp/{feature}/{id}` | delete |
| POST | `/erp/{feature}/preview` | پیش‌نمایش قیمت (فقط pricing) |
| GET | `/erp/invoices/{id}/print` | HTML قابل چاپ فاکتور |

featureها: brands, pricing, settlements, payouts, shipments, returns, requests,
accounting, invoices, agents, settings, seller_dashboard, users.

گارد: `AppAuth::authenticate` + cap منوی متناظر. seller scoping برای payouts/requests/accounting.
users نیاز به cap جدید `bespari_manage_users` دارد (ماژول `User`).

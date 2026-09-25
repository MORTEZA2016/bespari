# Database — جداول بسپاری

> Summary جداول و روابط. برای schema دقیق، `includes/Core/Activator.php` را بخوان (تمام CREATE TABLEها آنجاست).
> دیتابیس dev: `testdbname`، prefix `wp_`، جداول با پیشوند `wp_bespari_`.

## جداول و مسئولیت

### هسته‌ی فروش
| جدول | کلیدها | توضیح |
|---|---|---|
| `channels` | id | کانال‌های فروش؛ `settlement_mode` مقدارهای `statement` / `invoice` |
| `channel_settlement_periods` | channel_id | دوره‌های تسویه |
| `brands` | id | برندها |
| `brand_channels` | brand_id, channel_id | نگاشت برند↔کانال |
| `products` | id | محصولات؛ `base_price`, `stock`, `low_stock_threshold` |
| `product_channel` | product_id, channel_id | قیمت/موجودی per-channel |
| `pricing_rules` | id | قوانین قیمت‌گذاری پویا (نسخه‌بندی شده) |
| `orders` | id, channel_id, seller_id | سفارش‌ها؛ `status`, `settlement_status`, `sale_type`, snapshotهای مالی |
| `order_items` | order_id | آیتم‌های سفارش |

### مالی
| جدول | کلیدها | توضیح |
|---|---|---|
| `settlements` | order_id | تسویه‌ها |
| `commissions` | order_id, seller_id | پورسانت بازاریاب |
| `payouts` | seller_id | پرداخت‌ها |
| `transactions` | — | تراکنش‌های حسابداری |
| `invoices` | — | صورتحساب |
| `channel_invoices` | channel_id | صورتحساب کانال |

### عملیات
| جدول | کلیدها | توضیح |
|---|---|---|
| `stock_movements` | product_id | حرکات انبار |
| `shipments` | order_id | محموله‌ها |
| `shipment_batches` | — | دسته‌های محموله (کد `SHP-YYYYMMDD-XXXX`) |
| `returns` | order_id | مرجوعی |
| `requests` | — | درخواست‌ها |

### زیرساخت
| جدول | کلیدها | توضیح |
|---|---|---|
| `notifications` | user_id | اعلان‌ها (`is_read`, `type`, `link`) — ایجادشده توسط ماژول Notification |
| `audit_log` | user_id | لاگ تغییرات (trim به ۱۰۰۰ رکورد اخیر) |
| `agents` | — | اتصالات سایت بازاریاب |
| `agent_log` | — | لاگ درخواست‌های agent (trim به ۱۰۰۰ رکورد) |

## روابط اصلی

```text
channels 1───* orders *───1 sellers(user_id)
orders 1───* order_items *───1 products
orders 1───1 settlements
orders 1───* commissions *───1 sellers
sellers 1───* payouts
products *───* channels   (product_channel)
brands *───* channels      (brand_channels)
products 1───* stock_movements
orders 1───* shipments
```

## قراردادها

- شماره‌های یکتا: سفارش `ORD-YYYYMMDD-XXXX`، صورتحساب `INV-...`، محموله `SHP-...`، صورتحساب کانال `CINV-...` (توابع در `Helpers.php`).
- مبلغ‌ها با `Helpers::format_money()` برای نمایش، `Helpers::now()` برای timestamp.
- نام جدول از طریق `Helpers::table('orders')` ساخته می‌شود (نه hardcoded).
- کوئری‌های raw با `$wpdb->prepare` + comment `// phpcs:ignore`.

## داده‌ی تست فعلی (dev)

- ۲ کاربر: `admin` (ID 1, administrator) و `test` (ID 2, bespari_seller)
- ۱۰ کانال (channel 1 = دیجی‌کالا / statement، بقیه invoice)
- ۲ محصول (لوستر سقفی مدرن کدئ001 و کپی آن، موجودی `-1`)
- سفارش‌های تستی از BSPR-001: `ORD-20260924-0001/0002/0003` (۰۰۰۲ cancelled)
- چند اعلان تستی

# Architecture — معماری بسپاری

> Summary. برای جزئیات، فایل‌های واقعی را باز کن.

## جریان بارگذاری

```text
bespari-core.php
  ├─ defines: BESPARI_VERSION / BESPARI_FILE / BESPARI_PATH / BESPARI_URL
  ├─ Autoloader::register()          (PSR-4: Bespari\ → includes/)
  ├─ register_activation_hook  → Activator::activate()
  ├─ register_deactivation_hook → Deactivator::deactivate()
  └─ Plugin::instance()->run()
        ├─ plugins_loaded → load_textdomain
        ├─ is_admin()    → Menu, Assets, PosPage, PanelPage
        ├─ AppPage::register()         (همیشه — rewrite /panel/)
        ├─ PosRestController, AuthRestController, PanelRestController, NotificationRestController
        ├─ AuditLog::register()
        ├─ init(1) → maybe_migrate_settlement_modes()   (self-healing)
        └─ boot_modules()  → ۱۹ ماژول، هرکدام ->register()
```

## لایه‌ها

| لایه | نقش | نمونه |
|---|---|---|
| `<Module>.php` | ثبت هوک‌ها، نقطه‌ی ورود ماژول | `Order::register()` |
| `<Module>Service` | منطق کسب‌وکار | `OrderService::create()` |
| `<Module>Repository` | فقط دسترسی DB، برمی‌گرداند Model | `OrderRepository::paginate()` |
| `<Module>Model` | typed data holder، از `BaseModel` | `OrderModel::from_row()` |

قانون: Service به Repository وابسته است، Repository به هیچ Serviceای وابسته نیست.
ماژول‌ها به‌طور مستقیم همدیگر را صدا نمی‌زنند — از هوک `bespari_*` استفاده می‌کنند (DEC-004).

## Autoloader

`Bespari\Modules\Order\OrderService` → `includes/Modules/Order/OrderService.php`.
استثناها: `Bespari\Admin\...` → `includes/Admin/...`، `Bespari\Api\...` → `includes/Api/...`، `Bespari\Support\...` → `includes/Support/...`.

## ریشه‌های singleton

- `Plugin::instance()` — دسترسی به ماژول‌ها با `bespari()->module('order')` یا `Plugin::instance()->module('key')`.
- کلیدهای `boot_modules()`: channel, brand, product, pricing, order, settlement, seller, commission, payout, warehouse, shipping, returns, request, accounting, reports, agent, batch, channel_invoice, notification, **optimization**.

## فعال‌سازی

`Activator::activate()` با `dbDelta` همه‌ی ۲۴ جدول را می‌سازد + rewrite flush + schedule cronها.
`maybe_migrate_settlement_modes()` در `init` حالت‌های قدیمی (`monthly/biweekly/weekly/custom`) را به `invoice`/`statement` نگاشت می‌کند.

## محیط‌ها

- **سورس:** `F:/plugin/bespari` (ویرایش فقط اینجا)
- **dev:** `C:/xampp/htdocs/delori` + MariaDB `testdbname`
- استقرار = کپی دستی سورس به `wp-content/plugins/bespari` (symlink **نیست**)
- **live:** `C:/xampp/htdocs/live` — کپی سایت واقعی، قابل نصب‌دوباره با Duplicator. **تغییر نده.**

# Modules — ماژول‌های بسپاری

> ۲۰ ماژول در `boot_modules()` ثبت می‌شوند. هر کدام `<Name>.php` (register) + Service + Repository + Model.

| کلید در boot_modules | ماژول | مسئولیت |
|---|---|---|
| `channel` | Channel | کانال‌های فروش + حالت تسویه (`statement`/`invoice`) |
| `brand` | Brand | برندها و نگاشت به کانال‌ها |
| `product` | Product | محصولات، SKU، موجودی، قیمت per-channel |
| `pricing` | PricingRule | قوانین قیمت‌گذاری پویا (نسخه‌بندی، bump_version) |
| `order` | Order | چرخه‌ی سفارش: create, update_status, settle, delete |
| `settlement` | Settlement | تسویه‌ها |
| `seller` | Seller | بازاریاب‌ها (کاربران با cap `bespari_seller_view`) |
| `commission` | Commission | پورسانت + موجودی بازاریاب |
| `payout` | Payout | پرداخت‌ها |
| `warehouse` | Warehouse | حرکت انبار + موجودی کم |
| `shipping` | Shipping | محموله و دسته‌ها |
| `returns` | Returns | مرجوعی |
| `request` | Request | درخواست‌ها |
| `accounting` | Accounting | تراکنش + صورتحساب |
| `reports` | Reports | گزارشات جمعی |
| `agent` | Agent | اتصالات سایت بازاریاب |
| `batch` | Batch | دسته‌های محموله |
| `channel_invoice` | ChannelInvoice | صورتحساب کانال |
| `notification` | Notification | اعلان‌های خودکار |
| `optimization` | Optimization | کش آمار + cleanup روزانه |

## پرکاربردترین‌ها

### Pricing\PricingEngine
`calculate(ProductModel, ChannelModel, sale_type, seller_id, qty)` → `{sale_price_unit, sale_price_total, financials, applied_rules, rule_version}`.
دو نوع فروش: `cash` / `credit`. شامل tax, commission, processing_fee, shipping_fee, advertising_fee, gateway_fee.
`current_rule_version()` / `bump_version()` برای invalidation.

### Finance\Calculator
`calculate_order(channel_id, sale_type, seller_id, items)` → جمع‌زندی چندآیتمی + snapshotهای `pricing_snapshot` و `financials_snapshot` (در جدول orders ذخیره می‌شوند تا سفارش‌های قدیمی از قوانین فعلی تاثیر نگیرند).
پشتیبانی از `unit_price_override`.
**نکته:** محصول باید از `ProductRepository::get_full()` (Model برمی‌گرداند) بیاید.

### Order\OrderService
- `create(...)` → ساختن سفارش + `do_action('bespari_order_created')`.
- `update_status(id, status)` → وضعیت‌های `pending/confirmed/shipped/delivered/cancelled/paid`؛ در `cancelled` → `do_action('bespari_order_status_cancelled')`.
- `settle(...)` → `settlement_status = settled` + audit log.

### Notification
روی هوک‌های `bespari_order_created`، `bespari_commission_approved`، `bespari_payout_paid`، `bespari_return_approved`، `bespari_order_status_cancelled` گوش می‌دهد.
`notify_cap(cap, data)` برای ادمین‌ها؛ sellerها مستقیم.
`AppPanelLink::for_seller()` → `home_url('/panel/')`.

### Optimization
- `get_stats/set_stats` — transient به کلید `bespari_stats_{user}_{scope}` (۱۵ دقیقه).
- `flush_stats_cache()` روی `bespari_order_created/updated/status_cancelled`.
- `handle_cleanup()` (cron روزانه `bespari_daily_cleanup`): prune اعلان‌های +۶۰ روز، trim audit_log و agent_log به ۱۰۰۰ رکورد اخیر.

### Support\AuditLog
`AuditLog::log(action, entity, id, before, after)` — هر تغییر مهم لاگ می‌شود.

## Support
- `Helpers.php` — `table()`, `now()`, `format_money()`, شماره‌های یکتا (`ORD-...`، `INV-...`، `SHP-...`، `CINV-...`).
- `BaseRepository.php` — shared query helpers.

## BSPR-003 — ماژول User (جدید)

`includes/Modules/User/{User,UserService}.php` — مدیریت کاربران دارای نقش bespari*:
- `UserService::roles()` — ۶ نقش قابل تخصیص (admin/accountant/warehouse/seller/production/viewer).
- `list/create/update/delete` — با `wp_insert_user` (الگوی SellerService).
- `issue_token(id)` → `AppAuth::issue` (یک‌بار)، `revoke_tokens(id)`.
- cap لازم: `bespari_manage_users` (به administrator + bespari_admin).
- `User::register()` no-op است — همه‌ی عملیات از طریق REST.

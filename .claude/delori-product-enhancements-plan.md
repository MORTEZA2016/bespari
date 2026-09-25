# برنامه ساخت افزونه Delori Product Enhancements

## تصمیم‌های قطعی

- افزونه جدید و مستقل ساخته می‌شود و کد `Bespari Core` دست‌نخورده می‌ماند.
- نام فنی پیشنهادی: `Delori Product Enhancements` با text domain و پیشوند `delori-product-enhancements` / `dpe_`.
- WooCommerce وابستگی الزامی افزونه است؛ نبودن یا غیرفعال بودن آن با اعلان مدیریتی کنترل می‌شود و بخش‌های وابسته اجرا نمی‌شوند.
- اولویت مقادیر: تنظیم سراسری ← پیش‌فرض دسته با بیشترین عدد اولویت ← بازنویسی محصول.
- در محصول چنددسته‌ای، دسته دارای بالاترین عدد اولویت انتخاب می‌شود؛ در تساوی، term ID کوچک‌تر نتیجه قطعی می‌دهد.
- هر فیلد محصول حالت «ارث‌بری / فعال / غیرفعال» دارد تا بتوان پیش‌فرض دسته را صریحاً لغو کرد.
- متن‌ها و آیکون‌ها می‌توانند در سطح محصول بازنویسی شوند.
- داده‌ها با term meta، post meta و WordPress Options ذخیره می‌شوند؛ جدول سفارشی لازم نیست.
- خروجی فقط یک‌بار نمایش داده می‌شود: یا خودکار در hook انتخاب‌شده یا با shortcode/Elementor. اگر shortcode رندر شده باشد، رندر خودکار تکراری جلوگیری می‌شود.
- آیکون‌ها از مجموعه SVG داخلی و امن انتخاب می‌شوند؛ SVG دلخواه خام پذیرفته نمی‌شود.

## ساختار افزونه

یک پوشه مستقل مانند `delori-product-enhancements/` کنار فایل‌های افزونه فعلی ایجاد می‌شود:

- فایل bootstrap و lifecycle
- کلاس اصلی/loader
- سرویس resolve کردن تنظیمات سه‌سطحی
- پنل تنظیمات سراسری
- فیلدهای دسته محصول
- پنل داده محصول WooCommerce
- renderer ویترین و shortcode
- registry آیکون‌های SVG
- assets جداگانه admin/frontend
- ترجمه فارسی و `readme.txt`
- تست‌های PHP برای resolver و sanitizer، در حدی که محیط موجود اجازه دهد

## ۱. Bootstrap و سازگاری

- header استاندارد افزونه، نسخه، حداقل PHP/WordPress و `Requires Plugins: woocommerce`.
- guard برای direct access.
- بررسی WooCommerce پس از بارگذاری افزونه‌ها.
- بارگذاری translation domain.
- عدم وابستگی به Bespari Core یا کلاس‌های آن.
- استفاده از APIهای WordPress/WooCommerce و بدون query مستقیم DB.

## ۲. مدل داده و ارث‌بری

### تنظیمات سراسری

یک option نسخه‌دار شامل:

- فعال/غیرفعال بودن هر بخش: سفارشی‌سازی، ارسال ۲۴ ساعته، مشخصات سریع، صوت.
- عنوان‌ها و متن‌های پیش‌فرض.
- آیکون هر بخش و Play/Pause.
- محل نمایش: بالای عنوان، زیر عنوان، قبل قیمت.
- ترتیب sortable بخش‌ها.
- layout: عمودی یا grid.
- رنگ پس‌زمینه، حاشیه، متن اصلی، متن فرعی، accent.
- radius و gap با محدوده معتبر.

### term meta برای `product_cat`

- اولویت عددی دسته.
- حالت سه‌گانه هر قابلیت: inherit/on/off.
- مقدارهای پیش‌فرض قابل بازنویسی برای متن سفارشی‌سازی، URL، ارسال ۲۴ ساعته، مشخصات سریع و URL صوت.
- خالی بودن هر مقدار یعنی ارث‌بری از سطح سراسری، نه پاک کردن مبهم.

### product meta

- همان حالت سه‌گانه برای چهار بخش.
- متن، آیکون و URL اختصاصی سفارشی‌سازی.
- وضعیت ارسال ۲۴ ساعته و متن/آیکون اختصاصی.
- گرید انرژی، وات، لومن، فضای مناسب و گارانتی.
- URL فایل صوتی و عنوان/متن/آیکون اختصاصی.
- nonce، capability، autosave/revision guard و sanitize متناسب هر نوع داده.

Resolver نهایی یک آرایه canonical می‌سازد، انتخاب دسته را قطعی می‌کند، سپس بازنویسی محصول را اعمال می‌کند. فقط مقادیر مؤثر و معتبر رندر می‌شوند.

## ۳. رابط مدیریت

### صفحه تنظیمات افزونه

- زیرمنوی WooCommerce.
- Settings API با capability مناسب.
- تب محتوا/نمایش در یک صفحه ساده و RTL.
- color pickerهای بومی وردپرس.
- انتخاب محل و layout.
- کنترل radius/gap.
- فعال‌سازی مستقل هر باکس.
- ویرایش عنوان، متن و آیکون‌ها.
- Drag & Drop با jQuery UI Sortable موجود در وردپرس؛ ترتیب به input مخفی serialize می‌شود.
- preview سبک در پنل برای بازخورد فوری، بدون ذخیره داده ناامن.

### تنظیمات دسته

- فیلدها در فرم افزودن و ویرایش `product_cat`.
- اولویت عددی و حالت inherit/on/off.
- مقادیر پیش‌فرض دسته برای قابلیت‌ها و مشخصات.
- media picker برای فایل صوتی، همراه امکان URL مستقیم معتبر.

### تنظیمات محصول

- tab اختصاصی داخل Product Data ووکامرس، نه metabox پراکنده.
- بخش‌بندی روشن برای سفارشی‌سازی، ارسال سریع، مشخصات و صوت.
- selector حالت ارث‌بری/فعال/غیرفعال.
- نمایش مقدار مؤثر ارث‌رسیده برای فهم مدیر.
- media uploader وردپرس برای صوت و URL قابل ویرایش.
- ذخیره با hookهای CRUD محصول ووکامرس تا با ویرایش استاندارد و HPOS سازگار بماند.

## ۴. خروجی ویترین

- renderer واحد برای رندر خودکار، shortcode و Elementor.
- hook بر اساس محل انتخابی:
  - بالای عنوان: `woocommerce_single_product_summary` با priority قبل عنوان.
  - زیر عنوان: priority بلافاصله پس از عنوان.
  - قبل قیمت: priority قبل قیمت.
- shortcode: `[delori_product_enhancements]` با امکان `product_id` اختیاری برای templateهای Elementor؛ بدون ID از محصول جاری استفاده می‌کند.
- باکس سفارشی‌سازی لینک مستقیم دارد؛ URL خارجی با escaping و ویژگی‌های امن رندر می‌شود.
- باکس ارسال ۲۴ ساعته فقط وقتی مقدار مؤثر فعال باشد نمایش داده می‌شود.
- نوار مشخصات سریع خلاصه‌های موجود را نشان می‌دهد و دکمه جزئیات فقط در صورت وجود داده فعال است.
- modal دسترس‌پذیر با `role="dialog"`, `aria-modal`, عنوان مرتبط، بستن با Escape، بستن با overlay، focus trap و بازگرداندن focus.
- پلیر صوتی مبتنی بر HTML5 Audio با UI اختصاصی Play/Pause، progress، زمان و وضعیت loading/error؛ بدون autoplay.
- اگر JavaScript اجرا نشود، کنترل native audio fallback باقی می‌ماند.
- CSS scope‌شده، CSS variables برای تنظیمات، RTL، dark-mode خودکار، reduced-motion، grid/vertical و breakpoint موبایل.
- SVGها inline ولی از registry داخلی ثابت، با `aria-hidden`; متن قابل دسترس مستقل باقی می‌ماند.

## ۵. Elementor و جلوگیری از خروجی تکراری

- shortcode مسیر اصلی استفاده در Elementor است و در Single Product template کار می‌کند.
- اگر Elementor widget API موجود باشد، integration سبک ثبت می‌شود تا widget «Delori Product Enhancements» نیز در editor دیده شود؛ نبود Elementor هیچ خطایی ایجاد نمی‌کند.
- renderer در هر request/product یک registry داخلی دارد تا hook و shortcode یک خروجی یکسان را دوبار چاپ نکنند؛ در editor/preview امکان رندر کنترل‌شده حفظ می‌شود.

## ۶. امنیت، کیفیت و دسترس‌پذیری

- sanitize/validate بر اساس نوع: URL، رنگ hex، عدد محدود، enum allowlist، متن ساده.
- escape در آخرین نقطه خروجی.
- nonce و capability برای settings، term و product saves.
- هیچ SVG، HTML یا کلاس CSS دلخواه از کاربر پذیرفته نمی‌شود.
- لینک فایل صوتی فقط URL معتبر است و mime/پسوند قابل قبول در media picker محدود می‌شود؛ مرورگر خطای منبع خارجی را نمایش می‌دهد.
- همه رشته‌های رابط ترجمه‌پذیر و فارسی پیش‌فرض‌اند.
- ساختار semantic، keyboard controls و contrast معقول.

## ۷. مهاجرت و حذف

- schema/version option برای تغییرات آینده.
- deactivate داده را حذف نمی‌کند.
- `uninstall.php` فقط در صورت ثابت بودن uninstall context اجرا می‌شود و option/metaهای متعلق به افزونه را پاک می‌کند؛ حذف داده می‌تواند با گزینه «حفظ داده هنگام حذف» کنترل شود، با پیش‌فرض حفظ داده برای جلوگیری از حذف ناخواسته.

## ۸. اعتبارسنجی

- lint همه فایل‌های PHP.
- بررسی syntax فایل‌های JS در صورت وجود runtime مناسب.
- تست resolver برای اولویت global/category/product، دسته‌های هم‌اولویت، override صریح off و مقادیر خالی.
- تست sanitize تنظیمات و meta.
- بررسی دستی/مرورگری در سایت WordPress موجود، اگر launch configuration و نصب WooCommerce قابل اجرا باشد:
  - ذخیره تنظیمات سراسری، دسته و محصول.
  - هر سه محل نمایش.
  - modal، keyboard و focus.
  - Play/Pause و خطای URL صوت.
  - RTL، موبایل، dark mode و reduced motion.
  - shortcode در محتوای عادی و template Elementor.
  - نبود WooCommerce و نبود Elementor بدون fatal.
- اگر این repository محیط WordPress اجرایی یا test harness ندارد، lint و تست‌های مستقل اجرا می‌شوند و محدودیت integration verification صریح گزارش می‌شود.

## خروجی نهایی

- پوشه افزونه مستقل و قابل zip/install.
- مستندات نصب، اولویت تنظیمات، shortcode و روش استفاده در Elementor.
- فایل ZIP نصب‌پذیر پس از موفقیت validation.
- Bespari Core بدون تغییر باقی می‌ماند.

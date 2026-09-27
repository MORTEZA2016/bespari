# TASK STATE

## Current Task
تسکBSPR-006 — بررسی و بهبود داشبورد بسپاری

## Status
DONE

## Claimed By
Claude Code (VPS worker)

## Started At
2026-09-27

## Completed At
2026-09-27

## Commit
88aef07 (branch vps-worker, pushed to origin)

## Summary
بررسی داشبورد پنل مستقل و اعمال بهبودهای ظاهری/ساختاری بدون تغییر رفتار فعلی:

- `includes/Api/PanelRestController.php`:
  - افزودن کارت‌های آمار «فروش امروز» و «سود امروز» (اسکوپ بازاریاب رعایت می‌شود) به `view_dashboard`.
  - افزودن ستون تاریخ شمسی به جدول آخرین سفارشات.
  - افزودن کلید `section_link` برای لینک «مشاهده همه».
- `assets/js/app.js`:
  - استخراج helper صفحه‌بندی پنجره‌ای `paginationHtml` (اول | … | جاری±۲ | … | آخر + برچسب «صفحه X از Y»).
  - استفاده از آن در `renderTable` و `renderCrudBody` و انتقال صفحه‌بندی به زیر جدول.
  - تغییر سلکتور کلیک به `button.bp-page`.
  - رندر لینک «مشاهده همه» در عنوان section.
  - ریست `state.page` به ۱ هنگام تغییر نما.
- `assets/css/app.css`:
  - نوار رنگی بالای کارت‌های آمار + افکت hover.
  - section به flex و لینک با `margin-inline-start:auto`.
  - استایل‌های صفحه‌بندی جدید (ellipsis، info label، hover states).
  - اسکرول افقی جداول در عرض کمتر از ۷۲۰px.

## Tests
- `node --check assets/js/app.js` → OK.
- اعتبارسنجی جامع منطق صفحه‌بندی (۳۲۴۰ حالت: pages ۱..۸۰ × تمام صفحات جاری) → ALL_OK (بدون صفحه تکراری، ترتیب صعودی، مرز درست، بدون ellipsis بی‌مورد).
- PHP در این محیط قابل اجرا نیست؛ تغییرات PHP با بازبین دقیق بررسی شد.

## Trello Card
https://trello.com/c/x2RF7pGn

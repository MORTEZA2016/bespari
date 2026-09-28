# TASK STATE

## Current Task
BSPR-MENU-03 — کارمندان و دسترسی‌ها (Employees & Permissions)

## TASK ID
BSPR-MENU-03

## Worker
Claude-VPS

## Branch
vps-worker (worktree: `.claude/worktrees/bspr-menu-03`، شاخه محلی `vps-worker-menu03` بر پایه `origin/vps-worker`)

## Status
REVIEW

## Claimed At
2026-09-28T20:15:22Z

## Completed At
2026-09-28T21:40:00Z

## Trello Card
https://trello.com/c/TT264HBV
(انتقال یافت: Task Queue → In Progress)

## Task Summary (از Trello)
هدف: مدیریت کاربران داخلی افزونه.
امکانات: نام، نام خانوادگی، موبایل و عکس؛ افزودن، ویرایش و حذف.
تعیین دسترسی هر کارمند به منوها با حالت خواندن یا ویرایش.
تعیین دسترسی به کانال‌های فروش، دسته‌بندی‌ها، محصولات، برندها و نمایش کانال‌های فروش.
الزامات: سیستم Permission قابل توسعه و جلوگیری از دسترسی غیرمجاز.

## طراحی (Design)
- کارمندان = WP users با نقش‌های bespari_* به‌جز `bespari_seller` (بازاریاب‌ها ماژول مستقل دارند).
- فیلدها: user_login, email, first_name, last_name, mobile (usermeta), photo (attachment id).
- سیستم Permission در `includes/Modules/User/PermissionService.php`:
  - سوژه‌های منو: از `AppAuth::menus()` با حالت none/read/edit.
  - سوژه‌های موجودیت: channels, brands, categories, products (لیست ID مجاز) + flag channel_view.
  - ذخیره در usermeta `bespari_permissions`.
  - fallback نقش (backward compatible): کاربر بدون grant صریح → همان رفتار cap قبلی.
  - admin (دارای cap `bespari_manage_users`) دسترسی کامل.
- Enforcement: `AppAuth::user_menus()` + `ErpRestController::can_access()` (GET→read, غیرGET→edit)
  و فیلتر row در `read_channels` بر اساس scope کانال.
- UI: ERP feature جدید `employees` + field type های جدید در app.js:
  `permission_matrix`, `checkbox_group`, `photo` + مودال wide.
- Photo: آپلود base64 (data URI) داخل همان درخواست save؛ ذخیره به‌صورت attachment.

## Completed Steps
- بررسی معماری پروژه (ErpRestController, AppAuth, UserService, SellerService, app.js, app.css).
- claim کارت BSPR-MENU-03 و انتقال به In Progress.
- ساخت worktree بر پایه origin/vps-worker.
- ساخت `includes/Modules/User/PermissionService.php` (menu modes، entity scoping، channel_view، fallback نقش، admin bypass).
- ساخت `includes/Modules/User/EmployeeService.php` (list/get_form_values/create/update/delete، موبایل، عکس base64 → attachment با mime sniffing و محدودیت ۲MB).
- تغییر `includes/Api/AppAuth.php` (منوی employees، فیلتر user_menus بر اساس PermissionService).
- تغییر `includes/Api/ErpRestController.php` (feature employees، can_access بر اساس mode read/edit، فیلتر channels بر اساس scope).
- تغییر `assets/js/app.js` (ERP_VIEWS employees + field types جدید: permission_matrix, checkbox_group, photo + مودال wide).
- تغییر `assets/css/app.css` (استایل ماتریس دسترسی، checkbox_group، photo preview، مودال wide).
- تست: php -l روی ۴ فایل PHP، node --check روی app.js، دو harness منطقی آفلاین.

## Remaining Steps
- (none — منتظر تأیید کاربر برای انتقال کارت به Done)

## Files Changed
- includes/Modules/User/PermissionService.php (new)
- includes/Modules/User/EmployeeService.php (new)
- includes/Api/AppAuth.php
- includes/Api/ErpRestController.php
- assets/js/app.js
- assets/css/app.css
- .claude/TASK_STATE.md

## Tests Performed
- `php -l` روی PermissionService.php, EmployeeService.php, AppAuth.php, ErpRestController.php → بدون خطا.
- `node --check assets/js/app.js` → OK.
- harness `test-permissions.php`: ۴۱ بررسی، ۰ شکست (admin bypass، fallback نقش، grant صریح، entity scoping، clear_grants، grants_summary، همگام‌سازی menu_subjects با AppAuth::menus، فیلتر user_menus، modes/entity_subjects).
- harness `test-employees.php`: ۲۳ بررسی، ۰ شکست (roles بدون seller، is_employee، mime sniffing عکس PNG/JPEG/SVG، bad data URI/base64، محدودیت حجم، non-employee، photo_url + fallback avatar، delete_photo، sanitize_mobile، get_form_values).

## Last Commit
6b1a5d8 — feat(BSPR-MENU-03): employees & granular permissions module (7 files, +1322/-38)

## Last Push
SUCCESS — 997295b..6b1a5d8 HEAD -> vps-worker (2026-09-28)

## Next Action
انتقال کارت Trello به Review انجام شد. منتظر تأیید کاربر (انتقال به Done) — Task جدید برندار.

## Previous Task
BSPR-MENU-02 — کانال‌های فروش — DONE — commit 562473d (تأیید 2026-09-28T17:30:56Z)

---

## Worker Pipeline (ترتیب canonical — همیشه رعایت شود)
1. **Claim** — گرفتن کارت آزاد از `Task Queue` (اگر Worker دیگری claim کرده → SKIP).
2. **In Progress** — انتقال کارت به `In Progress` + ثبت WORKER/BRANCH/STATUS/CLAIMED_AT.
3. **Task** — انجام کار (فقط روی branch `vps-worker`).
4. **Test** — تأیید خروجی/رفتار.
5. **Commit** — فقط فایل‌های مرتبط (`git add .` ممنوع).
6. **Push** — `git push origin vps-worker`.
7. **Review** — فقط بعد از Push موفق: انتقال کارت به `Review` + ثبت COMMIT/COMPLETED_AT.
8. **توقف** — تا تأیید کاربر (انتقال به `Done`) Task جدید برندار.

## Board / List IDs (تأیید شده با MCP)
- Board `bespari`: `ari:cloud:trello::board/workspace/60c9af7f1b0b05478fb70727/6ab6fd9bd5bc7544ab9fb726`
- Task Queue:  `ari:cloud:trello::list/workspace/60c9af7f1b0b05478fb70727/6ab964932da2e90775f4096a`
- In Progress: `ari:cloud:trello::list/workspace/60c9af7f1b0b05478fb70727/6ab9649a8215e36ecd099352`
- Review:      `ari:cloud:trello::list/workspace/60c9af7f1b0b05478fb70727/6ab9649eb7c47d0caa053617`
- Done:        `ari:cloud:trello::list/workspace/60c9af7f1b0b05478fb70727/6ab964b2c4f942773077aa06`

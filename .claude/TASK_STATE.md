# TASK STATE

## Current Task
BSPR-MENU-02 — کانال‌های فروش

## Status
REVIEW

## Claimed By
Claude-VPS (vps-worker)

## Started At
2026-09-28T16:51:17Z

## Completed At
2026-09-28T17:05:00Z

## Commit
562473d (branch vps-worker, pushed to origin)

## Summary
پیاده‌سازی منوی مدیریت کانال‌های فروش با CRUD کامل در پنل مستقل:
- `includes/Api/ErpRestController.php`: feature «channels» (read/write/delete)
  با شمارش سفارش‌ها و برندهای مرتبط هر کانال، فرم حالت تسویه (select)،
  `rowValues` برای پیش‌تغذیه فرم ویرایش و بج وضعیت فعال/غیرفعال.
- `assets/js/app.js`: `channels` در `ERP_VIEWS`؛ اکشن عمومی «ویرایش» مودال CRUD
  را با مقادیر فعلی باز می‌کند؛ فیلدهای select مقدار فعلی را نمایش می‌دهند.

## Tests
- `node --check assets/js/app.js` → OK.
- PHP در این VPS نصب نیست؛ تغییرات PHP با بازبین دقیق بررسی شد.

## Trello Card
https://trello.com/c/f0m4fci8

## Previous Task
BSP-007 — تست Worker خودکار — DONE — commit a6e57b2

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

## قوانین
- یک Task = یک Worker؛ Task متعلق به Worker دیگر را برندار.
- اگر **Push موفق** شد ولی **Trello خطا داد**، Task دوباره اجرا نمی‌شود؛
  فقط همان عملیات Trello با commit موجود مجدداً تلاش می‌شود (idempotent).
- خطاهای گذرای Trello (خطای Stage 2 permission classifier) با retry ساده برطرف می‌شوند
  و به معنای مشکل ساختاری در IDها نیستند.

## Board / List IDs (تأیید شده با MCP)
- Board `bespari`: `ari:cloud:trello::board/workspace/60c9af7f1b0b05478fb70727/6ab6fd9bd5bc7544ab9fb726`
- Task Queue:  `ari:cloud:trello::list/workspace/60c9af7f1b0b05478fb70727/6ab964932da2e90775f4096a`
- In Progress: `ari:cloud:trello::list/workspace/60c9af7f1b0b05478fb70727/6ab9649a8215e36ecd099352`
- Review:      `ari:cloud:trello::list/workspace/60c9af7f1b0b05478fb70727/6ab9649eb7c47d0caa053617`
- Done:        `ari:cloud:trello::list/workspace/60c9af7f1b0b05478fb70727/6ab964b2c4f942773077aa06`

## Previous Task
BSPR-006 — بررسی و بهبود داشبورد بسپاری — DONE — commit 88aef07

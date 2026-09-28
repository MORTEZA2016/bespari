# TASK STATE

## Current Task
BSP-007 — تست Worker خودکار

## Status
DONE

## Claimed By
Claude Code (VPS worker)

## Started At
2026-09-28

## Completed At
2026-09-28

## Commit
d805bf5 (branch vps-worker, pushed to origin)

## Summary
ایجاد `TEST-AUTOMATION.md` در ریشه پروژه با محتوای «Worker automation test successful.»، سپس commit و push به `origin/vps-worker`.

## Tests
- محتوای فایل با `cat` تأیید شد.
- `git status` پس از commit تمیز بود.
- `git push origin vps-worker` → `35dc34c..d805bf5` موفق بود.

## Trello Card
https://trello.com/c/dVctcYVM

---

## Worker Pipeline (ترتیب canonical — همیشه رعایت شود)
1. **Claim** — گرفتن کارت از `Task Queue`.
2. **In Progress** — انتقال کارت به `In Progress`.
3. **Task** — انجام کار.
4. **Test** — تأیید خروجی/رفتار.
5. **Commit** — فقط فایل‌های مرتبط (`git add .` ممنوع).
6. **Push** — `git push origin vps-worker`.
7. **Done** — انتقال کارت به `Done`.

## قوانین
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

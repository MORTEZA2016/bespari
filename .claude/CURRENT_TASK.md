# Current Task

> این فایل مهم‌ترین فایل برای Resume کردن پروژه است.
> آخرین به‌روزرسانی: 2026-09-27

## Task ID
BSP-TEST-VPS — تست اتصال و اجرای خودکار Claude VPS (Trello + GitHub + پروژه Bespari)

## Status
COMPLETED — هر شش تست اتصال PASS شدند. این یک تست زیرساختی بود، نه فیچر محصول.

## Goal
تأیید اینکه کلاینت Claude روی این VPS می‌تواند: کارت‌های Trello را از طریق MCP بخواند،
به مخزن GitHub دسترسی خواندن/نوشتن داشته باشد، و فایل‌های پروژه Bespari را ببیند و ویرایش کند.

## Key Results
- Trello MCP: احراز هویت به‌عنوان `mmozafarnia`؛ خواندن بورد bespari، لیست Task Queue و
  کارت BSP-TEST-VPS — همه PASS.
- GitHub: `git ls-remote origin` شاخه‌های main و vps-worker را برگرداند (خواندن PASS)؛
  commit و push به `origin/vps-worker` موفق بود (نوشتن PASS).
- پروژه: فایل‌های اصلی (`bespari-core.php`، `includes/`، `assets/`، `uninstall.php`) موجود
  و git repo روی شاخه `vps-worker` سالم است.
- جزئیات کامل + جدول تست‌ها: `PROJECT_LOG.md` بخش TASK-20260927-006.

## Notes
- PHP روی این VPS نصب نیست — برای Taskهای فیچر (php -l، استقرار روی dev) باید نصب شود.
- `git config user.name/user.email` محلی با noreply GitHub مالک مخزن تنظیم شد.

## Next Step
تست بسته شد. BSPR-001 تا ۰۰۴ قبلاً کامل شده‌اند. VPS آماده دریافت Taskهای واقعی است؛
منتظر دستور بعدی کاربر.

## Files Changed (BSP-TEST-VPS)
- `PROJECT_LOG.md` (افزودن بخش TASK-20260927-006)
- `.claude/CURRENT_TASK.md` (این فایل)
- `.mcp.json` (پیکربندی trello MCP — جدید روی این VPS)
- `.claude/settings.local.json` (فعال‌سازی trello MCP + permissionها)

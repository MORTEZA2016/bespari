# START HERE — شروع هر Chat جدید

این راهنمای شروع سریع پروژه‌ی بسپاری است.

> **این فایل در شروع هر Chat جدید به‌صورت خودکار خوانده نمی‌شود و خواندنش الزامی نیست.**
> فقط زمانی این فایل و زنجیره‌ی Context را بخوان که کاربر **یک Task واقعی مربوط به Bespari** را شروع یا ادامه می‌دهد.
> برای پیام‌های ساده (تست اتصال، تست چت جدید، سؤال عمومی، سؤال درباره ابزارها، یا هر موضوع خارج از Bespari)
> نیازی به خواندن این فایل یا هر فایل Context دیگری نیست — مستقیماً جواب بده.

## قانون طلایی

> **Memory وضعیت و تاریخچه‌ی پروژه را توضیح می‌دهد، اما جایگزین سورس‌کد نیست.
> قبل از هر تغییر، فایل واقعی پروژه را بررسی کن. سورس فعلی نسبت به Memory اولویت دارد.**

اگر Memory با کد واقعی تناقض داشت، به کد واقعی اعتماد کن و سپس Memory را اصلاح کن.

## ترتیب خواندن (شرطی — فقط برای Task واقعی Bespari)

> این فهرست **الزامی در هر Chat نیست**. فقط وقتی کاربر یک Task واقعی Bespari را شروع یا ادامه می‌دهد،
> از `CURRENT_TASK.md` شروع کن و بقیه را **تنها در صورت نیاز همان Task** بخوان.

1. `.claude/CURRENT_TASK.md` — وضعیت Task در حال انجام + `Next Exact Action`. **همیشه اول این.**
2. `PROJECT_CONTEXT.md` — فقط اگر Task نیاز به snapshot وضعیت فعلی پروژه دارد.
3. `PROJECT_LOG.md` — فقط بخش‌های اخیر، و فقط اگر Task به تاریخچه‌ی تسک‌ها نیاز دارد.
4. `DECISIONS.md` — فقط اگر Task با تصمیمات معماری ارتباط دارد.
5. `.claude/context/*.md` — فقط فایل مرتبط با Task (مثلاً برای کار روی REST، `api.md`).

**قانون:** کمترین فایل ممکن را بخوان. هر فایلی به‌جز `CURRENT_TASK.md` فقط وقتی خوانده شود که
Task جاری واقعاً به آن نیاز دارد و `CURRENT_TASK.md` آن را تأیید نکرده باشد.

## قوانین کاهش مصرف Context

- **کل پروژه را بی‌دلیل اسکن/نخوان.** فقط فایل‌های مرتبط با Task فعلی.
- فایل‌های غیرمرتبط را باز نکن.
- **سورس‌کد را داخل Memory کپی نکن.** Memory فقط مسیر و مسئولیت هر جزء را می‌گوید؛ برای جزئیات، فایل را باز کن.
- خروجی‌های طولانی و تکراری تولید نکن.
- قبل از تغییر، محل واقعی کد را با Read/Grep بررسی کن — به مسیرهای Memory کورکورانه اعتماد نکن.
- پس از تغییرات مهم، Memory را به‌روزرسانی کن.

## Workflow دائمی (فقط برای Task واقعی Bespari)

> برای پیام‌های غیرمرتبط با Task واقعی Bespari (تست اتصال، تست چت جدید، سؤال عمومی،
> سؤال درباره ابزارها، هر موضوع خارج از Bespari): **هیچ Context پروژه‌ای خوانده نشود**
> و این Workflow اصلاً اجرا نشود — مستقیماً جواب بده.

```text
START
  ↓
Read .claude/CURRENT_TASK.md            ← اولین و تنها Context الزامی
  ↓
Decide: Task status + Next Exact Action چه Contextی نیاز دارد؟
  ↓
Read only relevant context              ← PROJECT_CONTEXT / PROJECT_LOG / DECISIONS / context/*.md
  ↓                                        فقط در صورت نیاز واقعی همان Task
Inspect only relevant source files
  ↓
Work on current task
  ↓
Test changes
  ↓
Update .claude/CURRENT_TASK.md
  ↓
Update PROJECT_CONTEXT.md (اگر وضعیت پروژه عوض شد)
  ↓
Update PROJECT_LOG.md (اگر تسکی انجام شد)
  ↓
Record important decisions in DECISIONS.md (اگر تصمیم معماری گرفتی)
  ↓
END
```

## هنگام نزدیک شدن به Context Limit

اولویت اول: ثبت **وضعیت دقیق Task** در `.claude/CURRENT_TASK.md`.

حتماً ثبت کن:
- آخرین کار انجام‌شده
- فایل‌های تغییرکرده
- تست‌های انجام‌شده و نتیجه‌ی هر کدام
- مشکلات / Blockerها
- کار فعلی
- **دقیقاً مرحله‌ی بعد** + `Next Exact Action` قابل اجرا

هدف: Chat جدید بتواند بدون بازسازی Chat قبلی ادامه دهد.

## محیط تست

- سورس: `F:/plugin/bespari`
- سایت dev: `C:/xampp/htdocs/delori` (پلاگین به `wp-content/plugins/bespari` **کپی** می‌شود — symlink نیست)
- برای تست: MySQL را از XAMPP استارت کن، سپس `http://localhost/delori/panel/`
- پشتیبان آخرین استقرار: `C:/xampp/htdocs/delori/wp-content/bespari-backup-20260924`

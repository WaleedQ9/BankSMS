# نظام متابعة المصاريف الشخصية
## ملف المشروع الكامل لـ Claude Code

---

## نظرة عامة

نظام شخصي لمتابعة المصاريف اليومية عبر تحليل رسائل البنك (SMS) تلقائياً، مع تصنيف كل عملية عبر Telegram Bot، وعرض التقارير على داشبورد ويب.

**الاستخدام:** شخصي (مستخدم واحد)  
**الجهاز الأساسي:** جوال (90% من الاستخدام)  
**اللغة:** العربية (RTL)

---

## Stack التقني

- **Backend:** Laravel 11 + MySQL
- **Frontend:** Laravel Blade + Bootstrap 5 RTL + Chart.js
- **Bot:** Telegram Bot API (Webhook)
- **Automation:** iOS Shortcut → Laravel API
- **Jobs:** Laravel Scheduler (Cron Jobs)
- **Deploy:** Shared Hosting (Hostinger)

---

## هيكل قاعدة البيانات

### جدول `categories` - التصنيفات
```sql
id
name          -- اسم الفئة بالعربي
icon          -- emoji (🛒 🍽️ 🎮 ...)
color         -- hex color للواجهة
is_active     -- boolean, default true
created_at
updated_at
```

**البيانات الأولية (Seeder):**
| الاسم | الأيقونة | اللون |
|---|---|---|
| مقاضي البيت | 🛒 | #10B981 |
| مطاعم وكافيهات | 🍽️ | #F59E0B |
| ترفيه | 🎮 | #8B5CF6 |
| مواصلات ووقود | 🚗 | #3B82F6 |
| صحة وصيدلية | 💊 | #EF4444 |
| ملابس | 👕 | #EC4899 |
| فواتير واشتراكات | 📱 | #6366F1 |
| تعليم | 📚 | #14B8A6 |
| أخرى | 📦 | #6B7280 |

---

### جدول `budgets` - الميزانية الشهرية
```sql
id
category_id   -- FK → categories
monthly_amount -- المبلغ الشهري الثابت (مثال: 1000 ريال)
created_at
updated_at
```
ملاحظة: ميزانية واحدة لكل فئة تنطبق على كل الدورات.

---

### جدول `billing_cycles` - الدورات الشهرية
```sql
id
start_date    -- دائماً يوم 27 من الشهر
end_date      -- يوم 26 من الشهر التالي
created_at
```

**منطق الإنشاء:**
- تُنشأ تلقائياً عند أول عملية في دورة جديدة
- مثال: 27/07/2026 → 26/08/2026

---

### جدول `billing_weeks` - الأسابيع
```sql
id
cycle_id      -- FK → billing_cycles
week_number   -- 1, 2, 3, 4
start_date
end_date
created_at
```

**منطق التقسيم (ثابت 25% لكل أسبوع):**
```
الأسبوع 1: اليوم 1-7  من الدورة
الأسبوع 2: اليوم 8-14
الأسبوع 3: اليوم 15-21
الأسبوع 4: اليوم 22-نهاية الدورة (26)
```

---

### جدول `transactions` - المعاملات
```sql
id
cycle_id           -- FK → billing_cycles
week_id            -- FK → billing_weeks
category_id        -- FK → categories (nullable - null = غير مصنف)
type               -- ENUM: purchase | income | transfer | atm
amount             -- decimal(10,2)
merchant           -- اسم المحل أو الجهة
card_last4         -- آخر 4 أرقام البطاقة
payment_method     -- مدى / Apple Pay / بطاقة ائتمان
transaction_date   -- datetime من الرسالة
sms_raw            -- نص الرسالة الأصلي كاملاً
is_classified      -- boolean, default false
classified_at      -- datetime
telegram_message_id -- message_id في تيليجرام (لتتبع الرسالة)
needs_reminder     -- boolean: هل أُرسل تذكير 12 ساعة؟
created_at
updated_at
```

---

### جدول `budget_alerts` - سجل التنبيهات
```sql
id
category_id   -- FK → categories
week_id       -- FK → billing_weeks
alert_type    -- ENUM: warning_80 | exceeded_100
sent_at       -- datetime
```
**الغرض:** منع إرسال نفس التنبيه أكثر من مرة في الأسبوع.

---

### جدول `settings` - الإعدادات
```sql
id
key           -- مثال: telegram_chat_id, telegram_bot_token
value
updated_at
```

---

## منطق حساب الميزانية الأسبوعية

```
الميزانية الشهرية للفئة = 1000 ريال
التقسيم الأساسي = 1000 ÷ 4 = 250 ريال/أسبوع

لكن الحصة الفعلية تتحدث في بداية كل أسبوع:
  المصروف حتى الآن = SUM(transactions) لهذا الشهر لهذه الفئة
  المتبقي الشهري = 1000 - المصروف
  الأسابيع الباقية = عدد الأسابيع المتبقية في الدورة
  حصة هذا الأسبوع = المتبقي ÷ الأسابيع الباقية

مثال تطبيقي:
  الأسبوع 1: حصة 250 | صرف 200 | متبقي شهري 800
  الأسبوع 2: حصة 800÷3 = 266 | صرف 200 | متبقي شهري 600
  الأسبوع 3: حصة 600÷2 = 300 | صرف 350 | متبقي شهري 250
  الأسبوع 4: حصة 250÷1 = 250
```

**مهم:** الميزانية للتنبيه فقط، لا يوجد قيد على الصرف.

---

## SMS Parser

### أنواع الرسائل والـ Patterns

يجب أن يكون Parser مرن يعتمد على Regex لا على نص ثابت، لأن البنك يغير صيغة الرسائل.

**نوع 1 - شراء POS:**
```
الكلمات المشغّلة: "مشتريات نقاط البيع" أو "شراء نقاط البيع" أو أي كلمة تحتوي "نقاط البيع"
النوع: purchase
يستخرج: المبلغ، اسم المحل، رقم البطاقة، طريقة الدفع، التاريخ والوقت
```

**نوع 2 - إيداع الراتب:**
```
الكلمات المشغّلة: "إيداع" أو "راتب" أو "credited"
النوع: income
يستخرج: المبلغ، المصدر، التاريخ
```

**نوع 3 - تحويل صادر:**
```
الكلمات المشغّلة: "تحويل" أو "transfer"
النوع: transfer
يستخرج: المبلغ، الجهة، التاريخ
```

**نوع 4 - سحب ATM:**
```
الكلمات المشغّلة: "سحب" أو "ATM" أو "صراف"
النوع: atm
يستخرج: المبلغ، اسم الصراف، التاريخ
```

### Regex للحقول المشتركة:
```php
// المبلغ
/مبلغ[:\s]+([0-9,]+\.?[0-9]*)\s*(SAR|ريال)?/u
// أو: /([0-9,]+\.?[0-9]*)\s*(SAR|ريال)/u

// اسم المحل
/لدى[:\s]*(.+?)(?:\n|$)/u
// أو: /at\s+(.+?)(?:\n|$)/u

// رقم البطاقة
/\*+([0-9]{4})/

// التاريخ والوقت
/(\d{1,2}\/\d{1,2}\/\d{2,4})\s+(\d{1,2}:\d{2})/

// طريقة الدفع
/(مدى|Apple Pay|Visa|Mastercard|بطاقة ائتمان)/iu
```

---

## API Endpoints

### استقبال SMS
```
POST /api/sms/receive
Headers: X-API-Key: {secret_key}
Body: { "message": "نص الرسالة كاملاً" }
Response: { "status": "ok", "transaction_id": 123 }
```

**المنطق:**
1. التحقق من الـ API Key
2. تشغيل Parser
3. إذا لم يُعرف النوع → تجاهل وتسجيل في log
4. تحديد أو إنشاء billing_cycle و billing_week الحالية
5. حفظ Transaction
6. إذا كان النوع (purchase/transfer/atm) → إرسال رسالة تيليجرام للتصنيف

### Telegram Webhook
```
POST /api/telegram/webhook
Body: Telegram Update Object
```

---

## Telegram Bot

### رسالة التصنيف (Inline Keyboard)
```
🛍 عملية جديدة
━━━━━━━━━━━━━
المحل: muasasat madhaq alsahb
المبلغ: 25.00 ريال
الوقت: 3/7/26 18:57
━━━━━━━━━━━━━
صنّف العملية:

[🛒 مقاضي] [🍽️ مطاعم] [🎮 ترفيه]
[🚗 مواصلات] [💊 صحة] [👕 ملابس]
[📱 فواتير] [📚 تعليم] [📦 أخرى]
```

### عند اختيار التصنيف:
1. حفظ `category_id` في جدول transactions
2. تحديث `is_classified = true` و `classified_at`
3. تعديل رسالة تيليجرام لتصبح:
```
✅ تم التصنيف
المحل: muasasat madhaq alsahb
المبلغ: 25.00 ريال
الفئة: 🍽️ مطاعم وكافيهات
```
4. حساب الميزانية وإرسال تنبيه إذا لزم

### تنبيه 80%:
```
⚠️ تنبيه ميزانية
━━━━━━━━━━━━━
وصلت لـ 80% من حصة المطاعم هذا الأسبوع
صرفت: 200 ريال من 250
المتبقي للأسبوع: 50 ريال
المتبقي للشهر: 800 ريال
```

### تنبيه تجاوز 100%:
```
🔴 تجاوزت الحصة الأسبوعية
━━━━━━━━━━━━━
تجاوزت حصة المطاعم هذا الأسبوع
صرفت: 280 ريال من 250
المتبقي للشهر: 720 ريال
```

### تذكير 12 ساعة (Cron Job):
```
⏰ عمليات غير مصنفة
━━━━━━━━━━━━━
عندك 3 عمليات تنتظر التصنيف:
• 25 ريال - muasasat madhaq alsahb
• 150 ريال - مطعم الشرق
• 80 ريال - صيدلية النهدي

صنّفها من الداشبورد 👇
https://sms.wy.sa/unclassified
```

---

## Cron Jobs (Laravel Scheduler)

```php
// كل ساعة: تحقق من عمليات لم تصنف منذ 12 ساعة
$schedule->job(new SendUnclassifiedReminderJob)->hourly();

// كل يوم الساعة 9 مساءً: ملخص يومي
$schedule->job(new SendDailySummaryJob)->dailyAt('21:00');

// كل جمعة الساعة 10 صباحاً: ملخص أسبوعي
$schedule->job(new SendWeeklySummaryJob)->weeklyOn(5, '10:00');

// في اليوم 27 من كل شهر: إنشاء دورة جديدة
$schedule->job(new CreateNewBillingCycleJob)->monthlyOn(27, '00:01');
```

---

## الداشبورد - تصميم الصفحات

### متطلبات التصميم العامة
- **RTL كامل** (dir="rtl", Bootstrap RTL)
- **Mobile-first** (90% استخدام جوال)
- **ألوان داكنة / نظيفة** - ليست فاتحة جداً
- **Bottom Navigation** للجوال (مثل تطبيقات الجوال)
- **بطاقات كبيرة** - أزرار واسعة سهلة الضغط بالإصبع
- **خط عربي:** Cairo أو Tajawal من Google Fonts
- لا تستخدم جداول كبيرة في الجوال - استخدم بطاقات بدلاً منها

### صفحة 1: الرئيسية (`/`)

**القسم الأعلى:**
- شريط يوضح: الدورة الحالية + رقم الأسبوع الحالي
- بطاقة كبيرة: إجمالي المصروف هذا الأسبوع / إجمالي المصروف هذا الشهر

**القسم الأوسط - بطاقات الفئات:**
لكل فئة عندها ميزانية:
```
[أيقونة] مقاضي البيت
████████░░  80%
200 من 250 ريال هذا الأسبوع
```
شريط تقدم ملون (أخضر → برتقالي عند 80% → أحمر عند 100%)

**القسم الأسفل:**
- آخر 5 معاملات (بطاقات بسيطة)
- زر "عرض الكل"

---

### صفحة 2: المعاملات (`/transactions`)

**فلاتر بسيطة (أزرار أفقية قابلة للتمرير):**
```
[الكل] [هذا الأسبوع] [هذا الشهر] [شراء] [تحويل] [سحب]
```

**قائمة المعاملات (بطاقات للجوال):**
```
┌─────────────────────────────┐
│ 🍽️ مطاعم        25.00 ريال │
│ muasasat madhaq alsahb      │
│ 3/7/26 - 18:57     [تعديل] │
└─────────────────────────────┘
```

**تعديل التصنيف:** Modal بسيط بأزرار التصنيف

---

### صفحة 3: غير المصنفة (`/unclassified`)

بطاقة لكل عملية غير مصنفة مع أزرار التصنيف مباشرة:
```
┌─────────────────────────────┐
│ 25.00 ريال                  │
│ muasasat madhaq alsahb      │
│ 3/7/26 - 18:57              │
│ [🛒][🍽️][🎮][🚗][💊][👕]  │
│ [📱][📚][📦]                │
└─────────────────────────────┘
```

Badge في الـ Navigation يظهر عدد غير المصنفة.

---

### صفحة 4: الميزانية (`/budget`)

لكل فئة:
```
┌─────────────────────────────┐
│ 🛒 مقاضي البيت              │
│ الميزانية الشهرية: 1,000 ريال│
│ حصة هذا الأسبوع: 266 ريال  │
│              [تعديل المبلغ] │
└─────────────────────────────┘
```

---

### صفحة 5: التقارير (`/reports`)

**تبويبات:**
- أسبوعي: Chart.js Bar Chart - مقارنة الأسابيع الأربعة
- شهري: Pie Chart - توزيع المصروف على الفئات
- مقارنة: آخر 3 أشهر

---

## Bottom Navigation (الجوال)

```
[🏠 الرئيسية] [📋 المعاملات] [⚠️ غير مصنف 3] [📊 التقارير] [⚙️ الإعدادات]
```

---

## صفحة الإعدادات (`/settings`)

- Telegram Chat ID
- Telegram Bot Token  
- API Key للـ Shortcut
- اختبار إرسال رسالة تيليجرام

---

## هيكل المشروع (Laravel)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── SmsController.php       ← استقبال SMS
│   │   │   └── TelegramController.php  ← Webhook
│   │   ├── DashboardController.php
│   │   ├── TransactionController.php
│   │   ├── BudgetController.php
│   │   └── ReportController.php
│   └── Middleware/
│       └── ApiKeyMiddleware.php
├── Models/
│   ├── Category.php
│   ├── Budget.php
│   ├── BillingCycle.php
│   ├── BillingWeek.php
│   ├── Transaction.php
│   ├── BudgetAlert.php
│   └── Setting.php
├── Services/
│   ├── SmsParserService.php      ← منطق تحليل الرسائل
│   ├── TelegramService.php       ← إرسال رسائل تيليجرام
│   ├── BudgetService.php         ← حساب الميزانية والتنبيهات
│   └── BillingCycleService.php   ← إدارة الدورات والأسابيع
├── Jobs/
│   ├── SendUnclassifiedReminderJob.php
│   ├── SendDailySummaryJob.php
│   └── SendWeeklySummaryJob.php
└── Console/
    └── Kernel.php                ← Cron Jobs
```

---

## iOS Shortcut

```
الـ Shortcut يشتغل عند وصول رسالة من: AlBilad (أو رقم البنك)
    ↓
يأخذ نص الرسالة
    ↓
POST https://sms.wy.sa/api/sms/receive
Headers:
  Content-Type: application/json
  X-API-Key: {SECRET_KEY}
Body:
  { "message": "[نص الرسالة]" }
```

---

## ملاحظات مهمة للتطوير

1. **SMS Parser** يجب أن يكون مرن تماماً - لا تعتمد على نص ثابت، استخدم Regex متعدد مع fallback
2. **Telegram Message ID** احفظه دائماً لتتمكن من تعديل الرسالة بعد التصنيف (editMessageText)
3. **billing_cycle** تُنشأ تلقائياً إذا لم تكن موجودة عند وصول أي معاملة
4. **التنبيهات** تُرسل مرة واحدة فقط - تحقق من budget_alerts قبل الإرسال
5. **كل الأرقام** بالريال السعودي (SAR) وتعرض بـ number_format مع فاصلة
6. **التواريخ** تعرض بالميلادي فقط (لا حاجة للهجري)
7. **الـ API Key** يُخزن في .env ويُقرأ من Settings table

---

## ترتيب التطوير (مرحلة بمرحلة)

```
المرحلة 1: الأساس
  ✓ Migrations كاملة
  ✓ Models + Relationships
  ✓ Seeders (categories + settings)
  ✓ BillingCycleService (إنشاء الدورات تلقائياً)

المرحلة 2: SMS
  ✓ SmsParserService (Regex مرن)
  ✓ API endpoint + ApiKeyMiddleware
  ✓ اختبار Parser بأمثلة رسائل حقيقية

المرحلة 3: Telegram
  ✓ TelegramService (إرسال + تعديل رسائل)
  ✓ Webhook Controller
  ✓ معالجة callback_query (الضغط على الأزرار)
  ✓ BudgetService (حساب + تنبيهات)

المرحلة 4: Dashboard
  ✓ Layout رئيسي (RTL + Bottom Nav + Mobile)
  ✓ صفحة الرئيسية
  ✓ صفحة المعاملات
  ✓ صفحة غير المصنفة
  ✓ صفحة الميزانية
  ✓ صفحة التقارير

المرحلة 5: Automation
  ✓ Cron Jobs (تذكير 12 ساعة + ملخص يومي/أسبوعي)
  ✓ إنشاء دورة جديدة كل 27 من الشهر
```

---

## مثال على رسائل البنك (للـ Parser)

```
مثال 1 - شراء:
مشتريات نقاط البيع
بطاقة: **4365; مدى, Apple Pay
مبلغ: 25.00 SAR
لدى: muasasat madhaq alsahb
3/7/26 18:57

مثال 2 - شراء (صيغة مختلفة):
شراء نقاط البيع
بطاقة: **4365; مدى, Apple Pay
مبلغ: 25.00 SAR
لدى: muasasat madhaq alsahb
3/7/26 18:57
```

**ملاحظة:** أرسائل الإيداع والتحويل والسحب ستُضاف لاحقاً عند توفرها.

---

*آخر تحديث للملف: يوليو 2026*

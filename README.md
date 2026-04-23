# JobHunter AI MVP Starter

هذا **starter kit** مخصص لك كنقطة بداية داخل مشروع Laravel 11، وليس مشروع Laravel كامل جاهز للتشغيل مباشرة بدون `composer create-project`.

## ماذا يحتوي؟
- هيكلة Modules بسيطة ومنظمة
- Migrations أساسية
- Models مبدئية
- Controllers / Requests / DTOs / Services stubs
- Scoring service
- Prompt templates config
- Routes API أولية
- Resume HTML template

## طريقة الاستخدام
1. أنشئ مشروع Laravel جديد:
   ```bash
   composer create-project laravel/laravel jobhunter-ai
   ```
2. انسخ محتويات هذا المجلد داخل مشروع Laravel.
3. عدل `composer.json` إذا أردت autoload إضافي (اختياري لأن الملفات تحت `app/`).
4. أضف متغيرات البيئة:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=jobhunter_ai
   DB_USERNAME=postgres
   DB_PASSWORD=secret

   OPENAI_API_KEY=
   JOBHUNTER_SCAN_HOURS=6
   JOBHUNTER_MATCH_THRESHOLD=75
   ```
5. نفذ:
   ```bash
   php artisan migrate
   ```
6. اربط الـ routes أو استخدم الملف المرفق `routes/api.php`.

## ما الذي ينقصه حتى يصبح production-ready؟
- integration حقيقي مع OpenAI / Bedrock
- ingestion فعلي لـ Greenhouse / Lever / Ashby
- auth / users
- queues + scheduler registration
- PDF generation service فعلي عبر Browsershot أو Playwright
- frontend dashboard

## نقطة مهمة
هذه النسخة **MVP scaffold** عملية لتبدأ البناء بسرعة، لكنها ليست منتجًا مكتملًا.

# اتصال واقعی پنل شاهانه به ووکامرس

## وضعیت اتصال فعلی
- مخزن GitHub در دسترس است و رابط کاربری در آن نگهداری می‌شود.
- فایل Figma برای طراحی دسکتاپ و موبایل ایجاد شده است.
- دسترسی WordPress MCP فعلی به دامنه shahanee.com از طریق حساب WordPress.com مجاز نیست: سایت خارج از WordPress.com میزبانی می‌شود و اتصال Jetpack آن به حساب متصل فعلی تعلق ندارد.
- ابزار MCP اختصاصی سایت در حال حاضر فقط یک قابلیت حذف مشتری را فهرست می‌کند؛ این قابلیت مخرب است و برای این پروژه فراخوانی نشده است.
- بنابراین هیچ خواندن/نوشتن زنده محصولات، سفارش‌ها یا مشتریان هنوز انجام نشده است.

## معماری پیشنهادی
1. مرورگر فقط با backend خود شاهانه ارتباط داشته باشد.
2. Backend از WooCommerce REST API با کلیدهای read-only برای شروع استفاده کند.
3. کلیدهای WooCommerce فقط در Environment Variables سرور نگهداری شوند؛ هرگز در GitHub یا JavaScript مرورگر قرار نگیرند.
4. بعد از تست خواندن محصولات و سفارش‌ها، عملیات نوشتن با مجوز جداگانه و تأیید صریح مدیر فعال شود.
5. داده‌های نمونه با برچسب Demo باقی بمانند تا زمانی که API واقعی فعال شود.

## متغیرهای محیطی مورد نیاز (مقادیر را در مخزن قرار ندهید)
```env
WORDPRESS_SITE_URL=https://shahanee.com
WOOCOMMERCE_CONSUMER_KEY=
WOOCOMMERCE_CONSUMER_SECRET=
APP_SESSION_SECRET=
```

## مسیرهای API پیشنهادی
- GET /api/health
- GET /api/products
- GET /api/orders
- GET /api/customers
- GET /api/reports/sales
- GET /api/inventory/alerts

این endpointها تا زمانی که backend امن ساخته و credentialها در میزبان تنظیم نشده‌اند، فعال نیستند.

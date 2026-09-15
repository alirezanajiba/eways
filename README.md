# Eways Video Commerce

نسخه آزمایشی پلتفرم فروش محصول از روی ویدئو برای ایویز.

## مسیرها

- اپ اصلی: / 
- مدیریت ویدئوها: /admin/
- API: /api.php
- ویدئوها: /media/videos/
- کاورها: /media/posters/

Document Root سابدامین: /home/radiba/public_html/eways/public

ریشه موثر اکانت FTP انتشار: /home/radiba/public_html/eways/public

در این حالت مقدار FTP_SERVER_DIR در GitHub Secrets برابر / است.

Workflow پیش از انتشار یک بسته تخت می سازد؛ بنابراین محتویات پوشه `public`
مستقیما در Document Root قرار می گیرند و پوشه `public/public` روی سرور ساخته نمی شود.
کدهای خصوصی `app` نیز با قواعد دسترسی وب محافظت می شوند.

## فرمت ویدئو

- Container: MP4
- Video codec: H.264
- Audio codec: AAC
- نسبت پیشنهادی: 9:16
- رزولوشن پیشنهادی: 1080x1920
- حداکثر حجم: 128 MB

کاور اختیاری است. در نبود کاور، خود ویدئو نمایش داده می شود و در اسلاید فعال به صورت خودکار، بی صدا و inline پخش می شود.

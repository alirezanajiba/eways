<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
  <meta name="theme-color" content="#f6f7fb">
  <title>مدیریت ویدئوهای ایویز</title>
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="مدیریت ایویز">
  <link rel="manifest" href="/admin/manifest.webmanifest">
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/admin-192.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/admin-180.png">
  <link rel="stylesheet" href="/assets/admin.css?v=7">
</head>
<body>
  <section class="login-shell" id="login-shell">
    <form class="login-card" id="login-form">
      <div class="logo">E</div>
      <h1>مدیریت ایویز ویدئو</h1>
      <p>برای مدیریت و بارگذاری ویدئوها وارد شوید.</p>
      <label>نام کاربری<input name="username" autocomplete="username" required></label>
      <label>رمز عبور<input name="password" type="password" autocomplete="current-password" required></label>
      <button type="submit">ورود به مدیریت</button>
      <div class="form-message" id="login-message"></div>
    </form>
  </section>

  <main class="dashboard hidden" id="dashboard">
    <header class="main-head">
      <div class="head-title"><div><small>ایویز ویدئو</small><h1 id="admin-page-title">صفحه مدیریت</h1></div></div>
      <div class="head-actions"><div class="dashboard-actions" id="dashboard-actions"><a href="/" target="_blank">مشاهده اپ</a><button id="logout-btn">خروج</button></div><button class="subpage-back hidden" id="subpage-back">بازگشت</button></div>
    </header>

    <section class="admin-home" id="admin-home">
      <section class="stats">
        <div><span>کل ویدئوها</span><b id="total-count">۰</b></div>
        <div><span>ویدئوهای فعال</span><b id="active-count">۰</b></div>
        <div><span>دسته بندی ها</span><b id="category-count">۰</b></div>
      </section>
      <section class="manage-grid">
        <button class="manage-card videos-card" data-admin-view="videos"><span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4Z"/></svg></span><div><b>مدیریت ویدئوها</b><small>افزودن، ویرایش، قیمت گذاری و گزارش عملکرد</small></div><i>›</i></button>
        <button class="manage-card categories-card" data-admin-view="categories"><span><svg viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg></span><div><b>مدیریت دسته بندی ها</b><small>تعریف دسته بندی و انتخاب آیکون</small></div><i>›</i></button>
      </section>
    </section>

    <section class="admin-view hidden" id="categories-view">
      <section class="content category-manager">
        <div class="list-head"><div><small>ساختار فروشگاه</small><h2>دسته بندی ها</h2></div><span>دسته بندی ها در اپ نمایش داده می شوند</span></div>
        <form class="category-form" id="category-form">
          <input type="hidden" name="id">
          <input type="hidden" name="icon_key" value="grid">
          <label>نام دسته بندی<input name="name" type="text" maxlength="120" required placeholder="مثلا لوازم جانبی موبایل"></label>
          <label>ترتیب نمایش<input name="sort_order" type="number" value="0"></label>
          <label class="category-active"><input name="is_active" type="checkbox" value="1" checked> نمایش در اپ</label>
          <div class="icon-picker-wrap"><b>آیکون دسته بندی</b><div class="icon-picker" id="icon-picker"></div></div>
          <div class="category-form-actions"><button type="submit">ذخیره دسته بندی</button><button type="button" class="category-cancel hidden" id="category-cancel">انصراف</button></div>
        </form>
        <div class="category-admin-list" id="category-admin-list"></div>
      </section>
    </section>

    <section class="admin-view hidden" id="videos-view">
      <section class="stats video-tools"><div><span>کل ویدئوها</span><b id="view-total-count">۰</b></div><div><span>ویدئوهای فعال</span><b id="view-active-count">۰</b></div><button id="new-video-btn">+ افزودن ویدئوی جدید</button></section>
      <section class="content">
        <div class="list-head"><h2>ویدئوهای ثبت شده</h2><span>گزارش هر ویدئو از رفتار واقعی کاربران محاسبه می شود</span></div>
        <div class="video-list" id="video-list"></div>
        <div class="empty hidden" id="admin-empty"><b>هنوز ویدئویی ثبت نشده</b><span>اولین ویدئوی محصول را اضافه کنید.</span></div>
      </section>
    </section>
  </main>

  <div class="backdrop" id="backdrop"></div>
  <aside class="editor" id="editor">
    <div class="editor-head"><div><small id="editor-kicker">ویدئوی جدید</small><h2 id="editor-title">افزودن ویدئو</h2></div><button type="button" id="close-editor">×</button></div>
    <form id="video-form">
      <input type="hidden" name="id">
      <section class="product-source">
        <b>روش تعریف کالا</b>
        <div class="source-options">
          <label><input type="radio" name="source_type" value="manual" checked><span>تعریف مستقل</span></label>
          <label><input type="radio" name="source_type" value="eways"><span>اتصال به کالای ایویز</span></label>
        </div>
        <div class="eways-lookup hidden" id="eways-lookup">
          <label>کد کالای ایویز<input name="eways_product_id" type="number" inputmode="numeric" min="1" placeholder="کد کالا را وارد کنید"></label>
          <button type="button" id="lookup-eways-product">استعلام کالا</button>
          <div class="eways-product-result" id="eways-product-result"></div>
        </div>
      </section>
      <div class="field-grid">
        <label class="full">عنوان محصول<span>*</span><input name="title" maxlength="255" required placeholder="مثلا هدفون بی سیم JBQ مدل H68"></label>
        <label class="manual-product-field">کد محصول<input name="product_code" maxlength="100" placeholder="SKU یا کد داخلی"></label>
        <label>دسته بندی<select name="category_id" id="product-category"><option value="">بدون دسته بندی</option></select></label>
        <label>برند<input name="brand" maxlength="120" placeholder="مثلا JBQ"></label>
        <label>قیمت فروش (تومان)<input name="price" type="text" inputmode="numeric" class="money-input" required></label>
        <label>زمان ارسال<input name="shipping_text" maxlength="160" placeholder="مثلا ارسال امروز"></label>
        <label>موجودی باقیمانده<input name="stock_remaining" type="number" inputmode="numeric" min="0" value="0"></label>
        <label>موجودی اولیه<input name="stock_total" type="number" inputmode="numeric" min="0" value="0"></label>
        <label>پایان تایمر<input name="timer_end_display" id="timer-end-display" type="text" inputmode="none" readonly placeholder="انتخاب تاریخ و ساعت شمسی"><input name="timer_end" type="hidden"></label>
        <label>ترتیب نمایش<input name="sort_order" type="number" inputmode="numeric" min="0" value="0"></label>
        <label class="full">توضیحات محصول<textarea name="description" rows="4" placeholder="توضیحات و مشخصات مهم محصول"></textarea></label>
      </div>

      <section class="tier-editor">
        <div class="tier-head"><div><b>قیمت گذاری پلکانی</b><small>با رسیدن تعداد خرید به هر پله، قیمت واحد همان پله اعمال می شود.</small></div><button type="button" id="add-tier-btn">+ افزودن پله</button></div>
        <div class="tier-columns"><span>از تعداد</span><span>قیمت هر واحد (تومان)</span><i></i></div>
        <div class="tier-rows" id="tier-rows"></div>
        <p class="tier-empty" id="tier-empty">هنوز پله ای تعریف نشده و همان قیمت فروش محصول محاسبه می شود.</p>
      </section>

      <div class="upload-grid">
        <label class="upload-card">
          <input type="file" name="video_file" accept="video/mp4">
          <span class="upload-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3Z"/></svg></span>
          <b>فایل ویدئو MP4</b>
          <small id="video-file-name">حداکثر حجم ۱۲۸ مگابایت</small>
        </label>
        <label class="upload-card">
          <input type="file" name="poster_file" accept="image/jpeg,image/png,image/webp">
          <span class="upload-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m5 17 4-4 3 3 2-2 5 4"/></svg></span>
          <b>کاور ویدئو (اختیاری)</b>
          <small id="poster-file-name">بدون کاور هم ویدئو پخش می شود</small>
        </label>
      </div>

      <label class="remove-poster hidden" id="remove-poster-row"><input type="checkbox" name="remove_poster" value="1"> حذف کاور فعلی</label>
      <label class="switch-row"><span><b>نمایش در اپ</b><small>ویدئو برای کاربران فعال باشد</small></span><input type="checkbox" name="is_active" value="1" checked></label>

      <div class="progress hidden" id="upload-progress"><i></i><span>در حال بارگذاری... ۰٪</span></div>
      <div class="form-message" id="save-message"></div>
      <div class="editor-actions"><button type="submit" class="save">ذخیره ویدئو</button><button type="button" class="cancel" id="cancel-editor">انصراف</button></div>
    </form>
  </aside>

  <div class="confirm hidden" id="delete-confirm">
    <div><h3>حذف ویدئو؟</h3><p>فایل ویدئو، کاور و کامنت های آن برای همیشه حذف می شوند.</p><div><button id="cancel-delete">انصراف</button><button class="danger" id="confirm-delete">حذف شود</button></div></div>
  </div>
  <div class="jalali-picker hidden" id="jalali-picker">
    <div class="jalali-card"><div class="jalali-head"><div><small>تاریخ شمسی</small><b>پایان زمان سفارش</b></div><button type="button" id="close-jalali">×</button></div><div class="jalali-date-fields"><label>روز<select id="jalali-day"></select></label><label>ماه<select id="jalali-month"></select></label><label>سال<select id="jalali-year"></select></label></div><div class="jalali-time-fields"><label>ساعت<select id="jalali-hour"></select></label><span>:</span><label>دقیقه<select id="jalali-minute"></select></label></div><div class="jalali-actions"><button type="button" id="confirm-jalali">تایید</button><button type="button" id="clear-jalali">پاک کردن</button><button type="button" id="cancel-jalali">انصراف</button></div></div>
  </div>
  <div class="toast" id="toast"></div>
  <script src="/assets/admin.js?v=7" defer></script>
</body>
</html>

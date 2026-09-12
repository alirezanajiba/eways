<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#f6f7fb">
  <title>مدیریت ویدئوهای ایویز</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/admin.css?v=1">
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
      <div><small>ایویز ویدئو</small><h1>مدیریت ویدئوهای محصولات</h1></div>
      <div class="head-actions"><a href="/" target="_blank">مشاهده اپ</a><button id="logout-btn">خروج</button></div>
    </header>

    <section class="stats">
      <div><span>کل ویدئوها</span><b id="total-count">۰</b></div>
      <div><span>ویدئوهای فعال</span><b id="active-count">۰</b></div>
      <button id="new-video-btn">+ افزودن ویدئوی جدید</button>
    </section>

    <section class="content">
      <div class="list-head"><h2>ویدئوهای ثبت شده</h2><span>برای ویرایش روی هر ردیف کلیک کنید</span></div>
      <div class="video-list" id="video-list"></div>
      <div class="empty hidden" id="admin-empty"><b>هنوز ویدئویی ثبت نشده</b><span>اولین ویدئوی محصول را اضافه کنید.</span></div>
    </section>
  </main>

  <div class="backdrop" id="backdrop"></div>
  <aside class="editor" id="editor">
    <div class="editor-head"><div><small id="editor-kicker">ویدئوی جدید</small><h2 id="editor-title">افزودن ویدئو</h2></div><button type="button" id="close-editor">×</button></div>
    <form id="video-form">
      <input type="hidden" name="id">
      <div class="field-grid">
        <label class="full">عنوان محصول<span>*</span><input name="title" maxlength="255" required placeholder="مثلا هدفون بی سیم JBQ مدل H68"></label>
        <label>کد محصول<input name="product_code" maxlength="100" placeholder="SKU یا کد ایویز"></label>
        <label>برند<input name="brand" maxlength="120" placeholder="مثلا JBQ"></label>
        <label>قیمت فروش (تومان)<input name="price" type="number" min="0" step="1" required></label>
        <label>زمان ارسال<input name="shipping_text" maxlength="160" placeholder="مثلا ارسال امروز"></label>
        <label>موجودی باقی مانده<input name="stock_remaining" type="number" min="0" value="0"></label>
        <label>موجودی اولیه<input name="stock_total" type="number" min="0" value="0"></label>
        <label>پایان تایمر<input name="timer_end" type="datetime-local"></label>
        <label>ترتیب نمایش<input name="sort_order" type="number" value="0"></label>
        <label class="full">توضیحات محصول<textarea name="description" rows="4" placeholder="توضیحات و مشخصات مهم محصول"></textarea></label>
      </div>

      <div class="upload-grid">
        <label class="upload-card">
          <input type="file" name="video_file" accept="video/mp4">
          <span class="upload-icon">▶</span>
          <b>فایل ویدئو MP4</b>
          <small id="video-file-name">حداکثر حجم ۱۲۸ مگابایت</small>
        </label>
        <label class="upload-card">
          <input type="file" name="poster_file" accept="image/jpeg,image/png,image/webp">
          <span class="upload-icon">▧</span>
          <b>کاور ویدئو (اختیاری)</b>
          <small id="poster-file-name">بدون کاور هم ویدئو پخش می شود</small>
        </label>
      </div>

      <label class="remove-poster hidden" id="remove-poster-row"><input type="checkbox" name="remove_poster" value="1"> حذف کاور فعلی</label>
      <label class="switch-row"><span><b>نمایش در اپ</b><small>ویدئو برای کاربران فعال باشد</small></span><input type="checkbox" name="is_active" value="1" checked></label>

      <div class="progress hidden" id="upload-progress"><i></i><span>در حال بارگذاری... ۰٪</span></div>
      <div class="form-message" id="save-message"></div>
      <div class="editor-actions"><button type="button" class="cancel" id="cancel-editor">انصراف</button><button type="submit" class="save">ذخیره ویدئو</button></div>
    </form>
  </aside>

  <div class="confirm hidden" id="delete-confirm">
    <div><h3>حذف ویدئو؟</h3><p>فایل ویدئو، کاور و کامنت های آن برای همیشه حذف می شوند.</p><div><button id="cancel-delete">انصراف</button><button class="danger" id="confirm-delete">حذف شود</button></div></div>
  </div>
  <div class="toast" id="toast"></div>
  <script src="/assets/admin.js?v=1" defer></script>
</body>
</html>


<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
  <meta name="theme-color" content="#05060a">
  <title>ایویز ویدئو</title>
  <meta name="description" content="خرید عمده محصولات از روی ویدئو در ایویز">
  <link rel="stylesheet" href="/assets/app.css?v=7">
</head>
<body>
  <main class="app">
    <section class="feed-page active" id="feed-page">
      <div class="video-feed" id="video-feed" aria-live="polite"></div>
      <header class="topbar">
        <div class="wallet"><span class="coin">ت</span><span>سپرده<br><b>۱۸,۴۵۰,۰۰۰ تومان</b></span></div>
      </header>
      <div class="feed-state" id="feed-state"><span class="loader"></span><p>در حال دریافت ویدئوها...</p></div>
    </section>

    <section class="standard-page" id="saved-page">
      <header class="page-head"><h1>ذخیره شده ها</h1><span class="count-badge" id="saved-count">۰</span></header>
      <div class="card-list" id="saved-list"></div>
      <div class="empty-state" id="saved-empty"><div class="empty-bookmark"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4Z"/></svg></div><h2>هنوز چیزی ذخیره نکردی</h2><p>از داخل ویدئوها، محصولات مورد علاقه ات را ذخیره کن.</p></div>
    </section>

    <section class="standard-page categories-page" id="categories-page">
      <header class="page-head"><div><h1>دسته بندی محصولات</h1></div></header>
      <div class="category-list" id="category-list"></div>
      <div class="empty-state hidden" id="category-empty"><div>⌗</div><h2>هنوز دسته بندی ثبت نشده</h2><p>به زودی دسته بندی محصولات اینجا نمایش داده می شود.</p></div>
      <div class="gallery-head"><div><h2 id="gallery-title">ویدئوها</h2></div><span id="gallery-count">۰ محصول</span></div>
      <div class="video-gallery" id="category-gallery"></div>
      <div class="gallery-empty hidden" id="gallery-empty">در حال حاضر ویدئویی در این دسته وجود ندارد.</div>
    </section>

    <section class="standard-page" id="cart-page">
      <header class="page-head"><h1>سبد خرید</h1><button class="text-btn" id="clear-cart">پاک کردن</button></header>
      <div class="card-list" id="cart-list"></div>
      <div class="empty-state" id="cart-empty"><div>□</div><h2>سبد خرید خالی است</h2><p>هنگام تماشای ویدئو، تعداد را انتخاب و به سبد اضافه کن.</p></div>
      <div class="cart-summary hidden" id="cart-summary">
        <div><span>جمع سفارش:</span><strong id="cart-total">۰ تومان</strong></div>
        <button id="submit-order">ثبت سفارش</button>
      </div>
    </section>

    <nav class="bottom-nav">
      <button class="nav-btn active" data-page="feed" aria-label="ویدئوها"><span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4Z"/></svg></span><small>ویدئوها</small></button>
      <button class="nav-btn" data-page="categories" aria-label="دسته بندی"><span><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg></span><small>دسته بندی</small></button>
      <button class="nav-btn" data-page="saved" aria-label="ذخیره شده"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4Z"/></svg></span><small>ذخیره شده</small></button>
      <button class="nav-btn cart-nav" data-page="cart" aria-label="سبد خرید"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2 11h10l3-8H7"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></svg></span><i id="cart-badge">۰</i><small>سبد خرید</small></button>
    </nav>

    <div class="backdrop" id="backdrop"></div>
    <aside class="sheet" id="details-sheet">
      <div class="sheet-handle"></div>
      <div class="sheet-head"><h2>اطلاعات محصول</h2><button class="close-sheet">×</button></div>
      <h3 class="sheet-section-title">مشخصات محصول</h3>
      <div class="spec-grid">
        <div><small>برند</small><b id="details-brand">—</b></div>
        <div><small>زمان ارسال</small><b id="details-shipping">—</b></div>
        <div><small>کد محصول</small><b id="details-code">—</b></div>
        <div><small>موجودی</small><b id="details-stock">—</b></div>
      </div>
      <h3 class="sheet-section-title description-title">توضیحات محصول</h3>
      <p class="sheet-description" id="details-description"></p>
    </aside>

    <aside class="sheet" id="comments-sheet">
      <div class="sheet-handle"></div>
      <div class="sheet-head"><h2>کامنت ها</h2><button class="close-sheet">×</button></div>
      <div class="comments" id="comments-list"></div>
      <form class="comment-form" id="comment-form">
        <input name="display_name" placeholder="نام شما" maxlength="100" required>
        <textarea name="body" placeholder="نظر یا سوال خود را بنویسید" maxlength="1000" required></textarea>
        <button type="submit">ثبت دیدگاه</button>
      </form>
    </aside>

    <div class="toast" id="toast"></div>
  </main>

  <template id="video-template">
    <article class="video-slide">
      <video class="product-video" loop muted playsinline preload="auto"></video>
      <div class="video-controls">
        <button class="play-btn" aria-label="توقف ویدئو"><svg class="pause-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg><svg class="play-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 9 6-9 6Z"/></svg></button>
        <button class="sound-btn" aria-label="فعال کردن صدا"><svg class="volume-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10v4h4l5 4V6L8 10H4Z"/><path class="sound-wave" d="M16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/><path class="mute-cross" d="m16 9 5 6m0-6-5 6"/></svg></button>
      </div>
      <div class="autoplay-hint">برای پخش لمس کنید</div>
      <div class="deal-timer hidden"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><strong>۰۰:۰۰:۰۰</strong></div>
      <div class="side-actions">
        <button class="comments-btn" aria-label="کامنت ها"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8.5 8 9 9 0 0 1-3.7-.8L4 20l1.3-3.4A8 8 0 1 1 20 11.5Z"/><path d="M8 11.5h.01M12 11.5h.01M16 11.5h.01"/></svg></span><small>۰</small></button>
        <button class="save-btn" aria-label="ذخیره ویدئو"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4Z"/></svg></span></button>
      </div>
      <div class="product-panel">
        <div class="product-title-row"><h2></h2><button class="details-btn" aria-label="اطلاعات محصول"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg></button></div>
        <div class="price"></div>
        <div class="price-tier-note hidden"></div>
        <div class="tier-strip hidden"></div>
        <div class="stock-row"><span></span><small></small></div>
        <div class="stock-bar"><i></i></div>
        <div class="buy-row">
          <div class="qty"><button class="minus">−</button><b>۱</b><button class="plus">+</button></div>
          <button class="add-cart">افزودن به سبد خرید</button>
        </div>
      </div>
    </article>
  </template>

  <script src="/assets/app.js?v=7" defer></script>
</body>
</html>

<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#05060a">
  <title>ایویز ویدئو</title>
  <meta name="description" content="خرید عمده محصولات از روی ویدئو در ایویز">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/app.css?v=1">
</head>
<body>
  <main class="app">
    <section class="feed-page active" id="feed-page">
      <div class="video-feed" id="video-feed" aria-live="polite"></div>
      <header class="topbar">
        <div class="wallet"><span class="coin">ت</span><span>کیف پول<br><b>۱۸,۴۵۰,۰۰۰ تومان</b></span></div>
        <div class="feed-tabs"><button class="active">پیشنهادی</button><button>جدیدترین</button></div>
      </header>
      <div class="feed-state" id="feed-state"><span class="loader"></span><p>در حال دریافت ویدئوها...</p></div>
    </section>

    <section class="standard-page" id="saved-page">
      <header class="page-head"><h1>ذخیره شده ها</h1><span class="count-badge" id="saved-count">۰</span></header>
      <div class="card-list" id="saved-list"></div>
      <div class="empty-state" id="saved-empty"><div>♡</div><h2>هنوز چیزی ذخیره نکردی</h2><p>از داخل ویدئوها، محصولات مورد علاقه ات را ذخیره کن.</p></div>
    </section>

    <section class="standard-page" id="cart-page">
      <header class="page-head"><h1>سبد خرید</h1><button class="text-btn" id="clear-cart">پاک کردن</button></header>
      <div class="card-list" id="cart-list"></div>
      <div class="empty-state" id="cart-empty"><div>□</div><h2>سبد خرید خالی است</h2><p>هنگام تماشای ویدئو، تعداد را انتخاب و به سبد اضافه کن.</p></div>
      <div class="cart-summary hidden" id="cart-summary">
        <div><span>جمع سفارش</span><strong id="cart-total">۰ تومان</strong></div>
        <button>ادامه و ثبت سفارش</button>
      </div>
    </section>

    <nav class="bottom-nav">
      <button class="nav-btn active" data-page="feed"><span>▶</span><small>ویدئوها</small></button>
      <button class="nav-btn" data-page="saved"><span>♡</span><small>ذخیره شده</small></button>
      <button class="nav-btn cart-nav" data-page="cart"><span>▢</span><i id="cart-badge">۰</i><small>سبد خرید</small></button>
    </nav>

    <div class="backdrop" id="backdrop"></div>
    <aside class="sheet" id="details-sheet">
      <div class="sheet-handle"></div>
      <div class="sheet-head"><h2>مشخصات محصول</h2><button class="close-sheet">×</button></div>
      <p class="sheet-description" id="details-description"></p>
      <div class="spec-grid">
        <div><small>برند</small><b id="details-brand">—</b></div>
        <div><small>زمان ارسال</small><b id="details-shipping">—</b></div>
        <div><small>کد محصول</small><b id="details-code">—</b></div>
        <div><small>موجودی</small><b id="details-stock">—</b></div>
      </div>
    </aside>

    <aside class="sheet" id="comments-sheet">
      <div class="sheet-handle"></div>
      <div class="sheet-head"><h2>دیدگاه ها</h2><button class="close-sheet">×</button></div>
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
      <video class="product-video" loop muted playsinline preload="metadata"></video>
      <button class="sound-btn" aria-label="فعال کردن صدا">⌁</button>
      <div class="autoplay-hint">برای پخش لمس کنید</div>
      <div class="deal-timer hidden"><small>زمان باقی مانده سفارش</small><strong>۰۰:۰۰:۰۰</strong></div>
      <div class="side-actions">
        <button class="comments-btn"><span>◯</span><small>۰</small></button>
        <button class="save-btn"><span>♡</span><small>ذخیره</small></button>
      </div>
      <div class="product-panel">
        <h2></h2>
        <button class="details-btn">مشخصات و توضیحات محصول ◀</button>
        <div class="price"></div>
        <div class="stock-row"><span></span><small></small></div>
        <div class="stock-bar"><i></i></div>
        <div class="buy-row">
          <div class="qty"><button class="minus">−</button><b>۱</b><button class="plus">+</button></div>
          <button class="add-cart">افزودن به سبد</button>
        </div>
      </div>
    </article>
  </template>

  <script src="/assets/app.js?v=1" defer></script>
</body>
</html>


const fa = new Intl.NumberFormat('fa-IR');
const state = {
  videos: [],
  categories: [],
  activeCategory: null,
  activeId: null,
  saved: JSON.parse(localStorage.getItem('eways_saved') || '[]'),
  cart: JSON.parse(localStorage.getItem('eways_cart') || '{}')
};

const feed = document.getElementById('video-feed');
const template = document.getElementById('video-template');
const feedState = document.getElementById('feed-state');
const toastEl = document.getElementById('toast');
const backdrop = document.getElementById('backdrop');

function toast(message) {
  toastEl.textContent = message;
  toastEl.classList.add('show');
  setTimeout(() => toastEl.classList.remove('show'), 2200);
}

function persist() {
  localStorage.setItem('eways_saved', JSON.stringify(state.saved));
  localStorage.setItem('eways_cart', JSON.stringify(state.cart));
  renderSaved();
  renderCart();
}

function formatPrice(value) {
  return fa.format(Number(value || 0)) + ' <small>تومان</small>';
}

function stockInfo(video) {
  const total = Number(video.stock_total || 0);
  const remaining = Number(video.stock_remaining || 0);
  const sold = total > 0 ? Math.max(0, Math.min(100, Math.round(((total - remaining) / total) * 100))) : 0;
  return { remaining, sold };
}

function buildSlide(video) {
  const node = template.content.firstElementChild.cloneNode(true);
  node.dataset.id = video.id;
  const player = node.querySelector('video');
  player.src = video.video_path;
  if (video.poster_path) player.poster = video.poster_path;
  player.setAttribute('aria-label', video.title);

  node.querySelector('.product-panel h2').textContent = video.title;
  node.querySelector('.price').innerHTML = formatPrice(video.price);
  const stock = stockInfo(video);
  node.querySelector('.stock-row span').textContent = stock.remaining ? 'فقط ' + fa.format(stock.remaining) + ' عدد باقی مانده' : 'ناموجود';
  node.querySelector('.stock-row small').textContent = fa.format(stock.sold) + '٪ فروش رفته';
  node.querySelector('.stock-bar i').style.width = stock.sold + '%';
  node.querySelector('.comments-btn small').textContent = fa.format(video.comments_count || 0);

  const timer = node.querySelector('.deal-timer');
  if (video.timer_end) {
    timer.dataset.end = String(new Date(video.timer_end.replace(' ', 'T')).getTime());
    timer.classList.remove('hidden');
  }

  const save = node.querySelector('.save-btn');
  save.classList.toggle('saved', state.saved.includes(String(video.id)));
  save.querySelector('small').textContent = save.classList.contains('saved') ? 'ذخیره شد' : 'ذخیره';
  save.onclick = () => {
    const id = String(video.id);
    state.saved = state.saved.includes(id) ? state.saved.filter(x => x !== id) : [...state.saved, id];
    save.classList.toggle('saved', state.saved.includes(id));
    save.querySelector('small').textContent = state.saved.includes(id) ? 'ذخیره شد' : 'ذخیره';
    persist();
  };

  node.querySelector('.details-btn').onclick = () => openDetails(video);
  node.querySelector('.comments-btn').onclick = () => openComments(video);
  const qtyValue = node.querySelector('.qty b');
  node.querySelector('.minus').onclick = () => qtyValue.textContent = Math.max(1, Number(qtyValue.textContent) - 1);
  node.querySelector('.plus').onclick = () => qtyValue.textContent = Number(qtyValue.textContent) + 1;
  node.querySelector('.add-cart').onclick = () => {
    const id = String(video.id);
    state.cart[id] = (state.cart[id] || 0) + Number(qtyValue.textContent);
    persist();
    toast(fa.format(Number(qtyValue.textContent)) + ' عدد به سبد اضافه شد');
  };

  const sound = node.querySelector('.sound-btn');
  const play = node.querySelector('.play-btn');
  const syncControls = () => {
    play.classList.toggle('paused', player.paused);
    play.setAttribute('aria-label', player.paused ? 'پخش ویدئو' : 'توقف ویدئو');
    sound.classList.toggle('unmuted', !player.muted);
    sound.setAttribute('aria-label', player.muted ? 'فعال کردن صدا' : 'بی صدا کردن ویدئو');
  };
  const togglePlayback = async () => {
    if (player.paused) {
      try {
        await player.play();
        node.classList.remove('needs-play');
      } catch {
        node.classList.add('needs-play');
      }
    } else {
      player.pause();
    }
    syncControls();
  };
  play.onclick = event => {
    event.stopPropagation();
    togglePlayback();
  };
  sound.onclick = () => {
    player.muted = !player.muted;
    syncControls();
  };
  player.onclick = togglePlayback;
  player.addEventListener('play', syncControls);
  player.addEventListener('pause', syncControls);
  node.querySelector('.autoplay-hint').onclick = async () => {
    player.muted = true;
    await player.play();
    node.classList.remove('needs-play');
    syncControls();
  };
  syncControls();
  return node;
}

const observer = new IntersectionObserver(entries => {
  entries.forEach(async entry => {
    const player = entry.target.querySelector('video');
    if (entry.isIntersecting && entry.intersectionRatio >= .75) {
      state.activeId = entry.target.dataset.id;
      document.querySelectorAll('.product-video').forEach(v => { if (v !== player) v.pause(); });
      player.muted = true;
      try {
        await player.play();
        entry.target.classList.remove('needs-play');
        entry.target.querySelector('.play-btn')?.classList.remove('paused');
      } catch {
        entry.target.classList.add('needs-play');
        entry.target.querySelector('.play-btn')?.classList.add('paused');
      }
    } else {
      player.pause();
    }
  });
}, { root: feed, threshold: [.25, .75] });

async function loadVideos() {
  try {
    const [videosResponse, categoriesResponse] = await Promise.all([
      fetch('/api.php?action=videos', { headers: { Accept: 'application/json' } }),
      fetch('/api.php?action=categories', { headers: { Accept: 'application/json' } })
    ]);
    const data = await videosResponse.json();
    const categoryData = await categoriesResponse.json();
    if (!data.ok || !categoryData.ok) throw new Error(data.message || categoryData.message);
    state.videos = data.videos;
    state.categories = categoryData.categories;
    renderFeed();
    renderCategories();
    if (!state.videos.length) {
      feedState.innerHTML = '<h2>فعلا ویدئویی منتشر نشده</h2><p>ویدئوهای جدید به زودی اینجا نمایش داده می شوند.</p>';
      return;
    }
    feedState.classList.add('hidden');
    renderSaved();
    renderCart();
  } catch {
    feedState.innerHTML = '<h2>ارتباط برقرار نشد</h2><p>لطفا چند لحظه دیگر دوباره تلاش کنید.</p>';
  }
}

function renderFeed() {
  document.querySelectorAll('.video-slide').forEach(slide => observer.unobserve(slide));
  feed.innerHTML = '';
  const visible = state.activeCategory
    ? state.videos.filter(video => String(video.category_id) === String(state.activeCategory))
    : state.videos;
  visible.forEach(video => {
    const slide = buildSlide(video);
    feed.appendChild(slide);
    observer.observe(slide);
  });
  if (state.videos.length && !visible.length) {
    feedState.classList.remove('hidden');
    feedState.innerHTML = '<h2>محصولی در این دسته نیست</h2><p>یک دسته بندی دیگر را انتخاب کنید.</p>';
  } else if (visible.length) {
    feedState.classList.add('hidden');
  }
}

function renderCategories() {
  const list = document.getElementById('category-list');
  const empty = document.getElementById('category-empty');
  empty.classList.toggle('hidden', state.categories.length > 0);
  if (!state.categories.length) {
    list.innerHTML = '';
    return;
  }
  const cards = [{ id: '', name: 'همه محصولات', products_count: state.videos.length }, ...state.categories];
  list.innerHTML = cards.map(category =>
    '<button class="category-card ' + ((!state.activeCategory && !category.id) || String(state.activeCategory) === String(category.id) ? 'active' : '') + '" data-category="' + escapeHtml(category.id) + '">' +
      '<span class="category-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg></span>' +
      '<b>' + escapeHtml(category.name) + '</b><small>' + fa.format(Number(category.products_count || 0)) + ' محصول</small></button>'
  ).join('');
  list.querySelectorAll('.category-card').forEach(card => card.onclick = () => {
    state.activeCategory = card.dataset.category || null;
    renderCategories();
    renderFeed();
    showPage('feed');
  });
}

function openSheet(id) {
  backdrop.classList.add('show');
  document.getElementById(id).classList.add('show');
}

function closeSheets() {
  backdrop.classList.remove('show');
  document.querySelectorAll('.sheet').forEach(x => x.classList.remove('show'));
}

function openDetails(video) {
  document.getElementById('details-description').textContent = video.description || 'توضیحی برای این محصول ثبت نشده است.';
  document.getElementById('details-brand').textContent = video.brand || '—';
  document.getElementById('details-shipping').textContent = video.shipping_text || '—';
  document.getElementById('details-code').textContent = video.product_code || '—';
  document.getElementById('details-stock').textContent = fa.format(video.stock_remaining || 0) + ' عدد';
  openSheet('details-sheet');
}

async function openComments(video) {
  state.activeId = String(video.id);
  const list = document.getElementById('comments-list');
  list.innerHTML = '<p>در حال دریافت دیدگاه ها...</p>';
  openSheet('comments-sheet');
  const response = await fetch('/api.php?action=comments&video_id=' + encodeURIComponent(video.id));
  const data = await response.json();
  list.innerHTML = data.comments?.length ? data.comments.map(c =>
    '<article class="comment"><b>' + escapeHtml(c.display_name) + '</b><p>' + escapeHtml(c.body) + '</p><small>' + escapeHtml(c.created_at) + '</small></article>'
  ).join('') : '<p>هنوز دیدگاهی ثبت نشده است.</p>';
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value || '';
  return div.innerHTML;
}

document.getElementById('comment-form').onsubmit = async event => {
  event.preventDefault();
  const form = new FormData(event.currentTarget);
  const response = await fetch('/api.php?action=comments', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ video_id: state.activeId, display_name: form.get('display_name'), body: form.get('body') })
  });
  const data = await response.json();
  if (!data.ok) return toast(data.message);
  event.currentTarget.reset();
  const video = state.videos.find(v => String(v.id) === String(state.activeId));
  await openComments(video);
  toast('دیدگاه ثبت شد');
};

function renderSaved() {
  const videos = state.videos.filter(v => state.saved.includes(String(v.id)));
  const list = document.getElementById('saved-list');
  document.getElementById('saved-count').textContent = fa.format(videos.length);
  document.getElementById('saved-empty').classList.toggle('hidden', videos.length > 0);
  list.innerHTML = videos.map(v => itemCard(v, 'saved')).join('');
  list.querySelectorAll('[data-open]').forEach(b => b.onclick = () => goToVideo(b.dataset.open));
  list.querySelectorAll('[data-remove-saved]').forEach(b => b.onclick = () => {
    state.saved = state.saved.filter(x => x !== b.dataset.removeSaved);
    persist();
  });
}

function renderCart() {
  const items = Object.entries(state.cart).map(([id, qty]) => ({ video: state.videos.find(v => String(v.id) === id), qty })).filter(x => x.video);
  const list = document.getElementById('cart-list');
  document.getElementById('cart-empty').classList.toggle('hidden', items.length > 0);
  document.getElementById('cart-summary').classList.toggle('hidden', !items.length);
  document.getElementById('cart-badge').textContent = fa.format(items.reduce((sum, x) => sum + x.qty, 0));
  document.getElementById('cart-total').textContent = fa.format(items.reduce((sum, x) => sum + Number(x.video.price) * x.qty, 0)) + ' تومان';
  list.innerHTML = items.map(({ video, qty }) => itemCard(video, 'cart', qty)).join('');
  list.querySelectorAll('[data-remove-cart]').forEach(b => b.onclick = () => {
    delete state.cart[b.dataset.removeCart];
    persist();
  });
}

function itemCard(video, type, qty = 0) {
  const media = video.poster_path
    ? '<img src="' + escapeHtml(video.poster_path) + '" alt="">'
    : '<video src="' + escapeHtml(video.video_path) + '" muted playsinline preload="metadata"></video>';
  const action = type === 'saved'
    ? '<button data-open="' + video.id + '">مشاهده ویدئو</button><button data-remove-saved="' + video.id + '">حذف</button>'
    : '<span>' + fa.format(qty) + ' عدد</span><button data-remove-cart="' + video.id + '">حذف</button>';
  return '<article class="item-card">' + media + '<div class="info"><h3>' + escapeHtml(video.title) + '</h3><div class="item-price">' + formatPrice(video.price) + '</div><div class="item-actions">' + action + '</div></div></article>';
}

function showPage(name) {
  document.querySelectorAll('.feed-page,.standard-page').forEach(p => p.classList.remove('active'));
  document.getElementById(name + '-page').classList.add('active');
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.toggle('active', b.dataset.page === name));
  if (name !== 'feed') document.querySelectorAll('.product-video').forEach(v => v.pause());
  else {
    const active = document.querySelector('.video-slide');
    active?.scrollIntoView();
    active?.querySelector('video')?.play().catch(() => active?.classList.add('needs-play'));
  }
}

function goToVideo(id) {
  showPage('feed');
  requestAnimationFrame(() => document.querySelector('.video-slide[data-id="' + id + '"]')?.scrollIntoView({ behavior: 'smooth' }));
}

document.querySelectorAll('.nav-btn').forEach(b => b.onclick = () => showPage(b.dataset.page));
document.querySelectorAll('.close-sheet').forEach(b => b.onclick = closeSheets);
backdrop.onclick = closeSheets;
document.getElementById('clear-cart').onclick = () => { state.cart = {}; persist(); };

setInterval(() => {
  document.querySelectorAll('.deal-timer:not(.hidden)').forEach(timer => {
    const left = Math.max(0, Number(timer.dataset.end) - Date.now());
    if (!left) return timer.classList.add('hidden');
    const total = Math.floor(left / 1000);
    const h = String(Math.floor(total / 3600)).padStart(2, '0');
    const m = String(Math.floor(total % 3600 / 60)).padStart(2, '0');
    const s = String(total % 60).padStart(2, '0');
    timer.querySelector('strong').textContent = (h + ':' + m + ':' + s).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
  });
}, 1000);

document.addEventListener('visibilitychange', () => {
  const active = document.querySelector('.video-slide[data-id="' + state.activeId + '"] video');
  if (document.hidden) active?.pause();
  else if (active) { active.muted = true; active.play().catch(() => active.closest('.video-slide').classList.add('needs-play')); }
});

loadVideos();

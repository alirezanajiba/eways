import React, { useEffect, useMemo, useRef, useState } from 'react';
import { api } from './api';

const fa = new Intl.NumberFormat('fa-IR');
const pages = ['feed', 'categories', 'saved', 'cart'];

function visitorToken() {
  let token = localStorage.getItem('eways_visitor');
  if (!token) {
    token = (crypto.randomUUID?.() || `${Date.now()}-${Math.random()}`).replace(/-/g, '');
    localStorage.setItem('eways_visitor', token);
  }
  return token;
}

function useStoredState(key, fallback) {
  const [value, setValue] = useState(() => {
    try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
  });
  useEffect(() => { localStorage.setItem(key, JSON.stringify(value)); }, [key, value]);
  return [value, setValue];
}

function saleOpen(video) {
  return !video.timer_end || new Date(String(video.timer_end).replace(' ', 'T')).getTime() > Date.now();
}

function tierPrice(video, quantity) {
  let price = Number(video.price || 0);
  let activeTier = null;
  for (const tier of video.price_tiers || []) {
    if (quantity >= Number(tier.min_qty)) {
      price = Number(tier.unit_price);
      activeTier = tier;
    }
  }
  return { price, activeTier };
}

function stockInfo(video) {
  const total = Number(video.stock_total || 0);
  const remaining = Number(video.stock_remaining || 0);
  const sold = total > 0 ? Math.max(0, Math.min(100, Math.round(((total - remaining) / total) * 100))) : 0;
  return { remaining, sold };
}

function Timer({ end }) {
  const [text, setText] = useState('');
  useEffect(() => {
    const tick = () => {
      const left = Math.max(0, new Date(String(end).replace(' ', 'T')).getTime() - Date.now());
      const total = Math.floor(left / 1000);
      const h = String(Math.floor(total / 3600)).padStart(2, '0');
      const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
      const s = String(total % 60).padStart(2, '0');
      setText((`${h}:${m}:${s}`).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]));
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [end]);
  return <div className="deal-timer"><strong>{text}</strong><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>;
}

function VideoSlide({ video, muted, setMuted, saved, onSave, onAddCart, onDetails, onComments, onViewed }) {
  const ref = useRef(null);
  const playerRef = useRef(null);
  const [quantity, setQuantity] = useState(1);
  const [needsPlay, setNeedsPlay] = useState(false);
  const [paused, setPaused] = useState(false);
  const stock = stockInfo(video);
  const current = tierPrice(video, quantity);
  const open = saleOpen(video);

  useEffect(() => {
    const node = ref.current;
    const player = playerRef.current;
    if (!node || !player) return;
    const observer = new IntersectionObserver(async ([entry]) => {
      if (entry.isIntersecting && entry.intersectionRatio >= .75) {
        onViewed(video.id);
        player.muted = muted;
        try { await player.play(); setNeedsPlay(false); setPaused(false); } catch { setNeedsPlay(true); }
      } else {
        player.pause();
        setPaused(true);
      }
    }, { threshold: [.25, .75] });
    observer.observe(node);
    return () => observer.disconnect();
  }, [muted, onViewed, video.id]);

  async function togglePlay() {
    const player = playerRef.current;
    if (!player) return;
    if (player.paused) {
      try { await player.play(); setNeedsPlay(false); setPaused(false); } catch { setNeedsPlay(true); }
    } else {
      player.pause(); setPaused(true);
    }
  }

  function setQty(next) {
    setQuantity(Math.max(1, Math.min(stock.remaining || 1, Number(next) || 1)));
  }

  return (
    <article ref={ref} className={`video-slide ${needsPlay ? 'needs-play' : ''} ${!open ? 'sale-ended' : ''}`} data-id={video.id}>
      <video ref={playerRef} className="product-video" src={video.video_path} poster={video.poster_path || undefined} loop muted={muted} playsInline preload="metadata" onClick={togglePlay} />
      {video.timer_end && open && <Timer end={video.timer_end} />}
      <div className="video-controls">
        <button className={`play-btn ${paused ? 'paused' : ''}`} onClick={togglePlay} aria-label={paused ? 'پخش ویدئو' : 'توقف ویدئو'}>
          <svg className="pause-icon" viewBox="0 0 24 24"><path d="M8 5v14M16 5v14"/></svg><svg className="play-icon" viewBox="0 0 24 24"><path d="m9 6 9 6-9 6Z"/></svg>
        </button>
        <button className={`sound-btn ${!muted ? 'unmuted' : ''}`} onClick={() => setMuted(!muted)}><svg viewBox="0 0 24 24"><path d="M4 10v4h4l5 4V6L8 10H4Z"/><path className="sound-wave" d="M16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/><path className="mute-cross" d="m16 9 5 6m0-6-5 6"/></svg></button>
      </div>
      <div className="autoplay-hint" onClick={togglePlay}>برای پخش لمس کنید</div>
      <div className="side-actions">
        <button className="comments-btn" onClick={() => onComments(video)}><span><svg viewBox="0 0 24 24"><path d="M20 11.5a8 8 0 0 1-8.5 8 9 9 0 0 1-3.7-.8L4 20l1.3-3.4A8 8 0 1 1 20 11.5Z"/></svg></span><small>{fa.format(Number(video.comments_count || 0))}</small></button>
        <button className={`save-btn ${saved ? 'saved' : ''}`} onClick={() => onSave(video)}><span><svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4Z"/></svg></span></button>
      </div>
      <div className="product-panel">
        <div className="product-title-row"><h2>{video.title}</h2><button className="details-btn" onClick={() => onDetails(video)}><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg></button></div>
        <div className="price">{fa.format(current.price)} <small>تومان</small></div>
        {!!video.price_tiers?.length && <><div className="price-tier-note">{current.activeTier ? `قیمت واحد پلکانی برای ${fa.format(quantity)} عدد` : 'با افزایش تعداد، قیمت واحد کمتر می شود'}</div><div className="tier-strip">{video.price_tiers.map(t => <button key={t.min_qty} className={Number(t.min_qty) <= quantity ? 'active' : ''} onClick={() => setQty(t.min_qty)}><b>{fa.format(Number(t.min_qty))}+ عدد</b><span>{fa.format(Number(t.unit_price))}</span></button>)}</div></>}
        <div className="stock-row"><span>{stock.remaining ? `فقط ${fa.format(stock.remaining)} عدد باقی مانده` : 'ناموجود'}</span><small>{fa.format(stock.sold)}٪ فروش رفته</small></div>
        <div className="stock-bar"><i style={{width:`${stock.sold}%`}} /></div>
        <div className="buy-row"><div className="qty"><button onClick={() => setQty(quantity - 1)}>−</button><b>{fa.format(quantity)}</b><button onClick={() => setQty(quantity + 1)}>+</button></div><button className="add-cart" disabled={!stock.remaining || !open} onClick={() => onAddCart(video, quantity)}>افزودن به سبد خرید</button></div>
      </div>
    </article>
  );
}

function BottomNav({ page, setPage, count }) {
  const items = [['feed','ویدئوها','▶'],['categories','دسته بندی','▦'],['saved','ذخیره شده','♡'],['cart','سبد خرید','▣']];
  return <nav className="bottom-nav">{items.map(([key,label,icon]) => <button key={key} className={`nav-btn ${page===key?'active':''} ${key==='cart'?'cart-nav':''}`} onClick={()=>setPage(key)}><span>{icon}</span>{key==='cart'&&<i>{fa.format(count)}</i>}<small>{label}</small></button>)}</nav>;
}

function Sheet({ show, onClose, children, className='' }) {
  if (!show) return null;
  return <><div className="backdrop show" onClick={onClose}/><aside className={`sheet show ${className}`}><div className="sheet-handle"/>{children}</aside></>;
}

export default function App() {
  const [catalog, setCatalog] = useState({videos:[],categories:[]});
  const [page, setPage] = useState('feed');
  const [category, setCategory] = useState(null);
  const [muted, setMutedState] = useState(localStorage.getItem('eways_muted') !== 'false');
  const [saved, setSaved] = useStoredState('eways_saved', []);
  const [cart, setCart] = useStoredState('eways_cart', {});
  const [visitor] = useState(visitorToken);
  const [viewed] = useState(() => new Set());
  const [sheet, setSheet] = useState(null);
  const [sheetVideo, setSheetVideo] = useState(null);
  const [comments, setComments] = useState([]);
  const [user, setUser] = useState(null);
  const [depositUrl, setDepositUrl] = useState('#');
  const [message, setMessage] = useState('');
  const [toast, setToast] = useState('');
  const [loading, setLoading] = useState(true);
  const touch = useRef(null);

  const videos = catalog.videos || [];
  const categories = catalog.categories || [];
  const filtered = useMemo(() => category ? videos.filter(v => String(v.category_id)===String(category)) : videos, [videos, category]);
  const savedVideos = videos.filter(v => saved.includes(String(v.id)));
  const cartItems = Object.entries(cart).map(([id, qty]) => ({video: videos.find(v => String(v.id)===id), qty:Number(qty)})).filter(x=>x.video);
  const cartCount = cartItems.reduce((s,x)=>s+x.qty,0);
  const cartTotal = cartItems.reduce((s,x)=>s+tierPrice(x.video,x.qty).price*x.qty,0);

  function flash(text) { setToast(text); setTimeout(()=>setToast(''),2500); }
  function setMuted(value) { setMutedState(value); localStorage.setItem('eways_muted', String(value)); }

  async function loadCatalog(silent=false) {
    try {
      const data = await fetch(`/api/v1/catalog?_=${Date.now()}`, {cache:'no-store'}).then(r=>r.json());
      if (!data.ok) throw new Error();
      setCatalog({videos:data.videos||[],categories:data.categories||[]});
    } catch { if (!silent) flash('ارتباط با سرور برقرار نشد'); }
    finally { setLoading(false); }
  }

  async function loadUser() {
    try { const data = await api.currentUser(); setUser(data.authenticated ? data.user : null); setDepositUrl(data.deposit_url || '#'); } catch { setUser(null); }
  }

  useEffect(() => {
    loadCatalog(); loadUser();
    const id = setInterval(()=>loadCatalog(true),5000);
    return ()=>clearInterval(id);
  }, []);

  async function markViewed(id) {
    const key = String(id); if (viewed.has(key)) return; viewed.add(key);
    fetch('/api/v1/track-view',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({video_id:Number(id),visitor_token:visitor}),keepalive:true}).catch(()=>{});
  }

  async function toggleSave(video) {
    const id=String(video.id), next=!saved.includes(id);
    setSaved(next?[...saved,id]:saved.filter(x=>x!==id));
    fetch('/api/v1/track-save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({video_id:Number(video.id),visitor_token:visitor,saved:next})}).catch(()=>{});
  }

  function addCart(video, quantity) { setCart({...cart,[video.id]:(Number(cart[video.id])||0)+quantity}); flash(`${fa.format(quantity)} عدد به سبد اضافه شد`); }
  function changeCart(id, delta) { setCart({...cart,[id]:Math.max(1,(Number(cart[id])||1)+delta)}); }
  function removeCart(id) { const next={...cart}; delete next[id]; setCart(next); }

  async function openComments(video) {
    setSheetVideo(video); setComments([]); setSheet('comments');
    const data=await fetch(`/api/v1/comments?video_id=${video.id}&_=${Date.now()}`,{cache:'no-store'}).then(r=>r.json());
    setComments(data.comments||[]);
  }

  async function submitComment(event) {
    event.preventDefault();
    const form=new FormData(event.currentTarget);
    const response=await fetch('/api/v1/comments',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({video_id:sheetVideo.id,display_name:form.get('display_name'),body:form.get('body')})});
    const data=await response.json();
    if (!response.ok||!data.ok) return flash(data.message||'ثبت کامنت انجام نشد');
    setComments([data.comment,...comments]); event.currentTarget.reset(); loadCatalog(true); flash('کامنت ثبت شد');
  }

  async function login(event) {
    event.preventDefault(); setMessage(''); const form=new FormData(event.currentTarget);
    try { const data=await api.login(form.get('username'),form.get('password')); setUser(data.user); setDepositUrl(data.deposit_url||'#'); flash('با موفقیت وارد حساب ایویز شدید'); }
    catch(e){ setMessage(e.message); }
  }

  async function logout(){ await api.logout(); setUser(null); flash('از حساب ایویز خارج شدید'); }

  async function submitOrder(){
    if(!cartItems.length)return;
    try{
      const data=await api.submitOrder(cartItems.map(x=>({video_id:Number(x.video.id),quantity:x.qty})));
      setCart({}); if(data.user)setUser(data.user); loadCatalog(true); flash(data.eways_order_id?`سفارش ایویز شماره ${fa.format(data.eways_order_id)} ثبت شد`:`سفارش شماره ${fa.format(data.order_id)} ثبت شد`);
    }catch(e){
      if(e.data?.login_required){setSheet('account');setMessage(e.message);return;}
      if(e.data?.insufficient_deposit){setSheet('account');setMessage(`سپرده فعلی ${fa.format(Number(e.data.deposit||0))} تومان است و برای این سفارش ${fa.format(Number(e.data.required||0))} تومان نیاز دارید.`);setDepositUrl(e.data.deposit_url||'#');return;}
      flash(e.message);
    }
  }

  function swipeStart(e){ if(e.touches.length===1)touch.current={x:e.touches[0].clientX,y:e.touches[0].clientY}; }
  function swipeEnd(e){ if(!touch.current)return;const dx=e.changedTouches[0].clientX-touch.current.x,dy=e.changedTouches[0].clientY-touch.current.y;touch.current=null;if(Math.abs(dx)<65||Math.abs(dx)<Math.abs(dy)*1.4)return;const i=pages.indexOf(page),n=dx<0?i+1:i-1;if(pages[n])setPage(pages[n]); }

  return <main className="app" onTouchStart={swipeStart} onTouchEnd={swipeEnd}>
    <section className={`feed-page ${page==='feed'?'active':''}`} id="feed-page">
      <div className="video-feed">{filtered.map(v=><VideoSlide key={v.id} video={v} muted={muted} setMuted={setMuted} saved={saved.includes(String(v.id))} onSave={toggleSave} onAddCart={addCart} onDetails={v=>{setSheetVideo(v);setSheet('details')}} onComments={openComments} onViewed={markViewed}/>)}</div>
      <header className="topbar"><button className="wallet" onClick={()=>{setMessage('');setSheet('account')}}><span className="coin">ت</span><span>سپرده<br/><b>{user?`${fa.format(Number(user.revenue||0))} تومان`:'ورود به ایویز'}</b></span></button></header>
      {loading&&<div className="feed-state"><span className="loader"/><p>در حال دریافت ویدئوها...</p></div>}
    </section>

    <section className={`standard-page categories-page ${page==='categories'?'active':''}`}><header className="page-head"><h1>دسته بندی محصولات</h1></header><div className="category-list"><button className={`category-card ${!category?'active':''}`} onClick={()=>setCategory(null)}><span><b>همه محصولات</b><small>{fa.format(videos.length)} محصول</small></span></button>{categories.map(c=><button key={c.id} className={`category-card ${String(category)===String(c.id)?'active':''}`} onClick={()=>setCategory(c.id)}><span><b>{c.name}</b><small>{fa.format(Number(c.products_count||0))} محصول</small></span></button>)}</div><div className="gallery-head"><div><h2>{category?`ویدئوهای ${categories.find(c=>String(c.id)===String(category))?.name||''}`:'همه ویدئوها'}</h2></div><span>{fa.format(filtered.length)} محصول</span></div><div className="video-gallery">{filtered.map(v=><button className="gallery-card" key={v.id} onClick={()=>setPage('feed')}><span className="gallery-media">{v.poster_path?<img src={v.poster_path} alt=""/>:<video src={v.video_path} muted playsInline preload="metadata"/>}</span><b>{v.title}</b><span>{fa.format(tierPrice(v,1).price)} <small>تومان</small></span></button>)}</div></section>

    <section className={`standard-page ${page==='saved'?'active':''}`}><header className="page-head"><h1>ذخیره شده ها</h1><span className="count-badge">{fa.format(savedVideos.length)}</span></header><div className="card-list">{savedVideos.map(v=><article className="item-card" key={v.id}>{v.poster_path?<img src={v.poster_path} alt=""/>:<video src={v.video_path} muted/>}<div className="info"><h3>{v.title}</h3><div className="item-price">قیمت واحد: <b>{fa.format(tierPrice(v,1).price)} تومان</b></div><div className="item-actions"><button onClick={()=>setPage('feed')}>مشاهده ویدئو</button><button onClick={()=>toggleSave(v)}>حذف</button></div></div></article>)}</div>{!savedVideos.length&&<div className="empty-state"><h2>هنوز چیزی ذخیره نکردی</h2><p>از داخل ویدئوها، محصولات مورد علاقه ات را ذخیره کن.</p></div>}</section>

    <section className={`standard-page ${page==='cart'?'active':''}`}><header className="page-head"><h1>سبد خرید</h1><button className="text-btn" onClick={()=>setCart({})}>پاک کردن</button></header><div className="card-list">{cartItems.map(({video,qty})=>{const p=tierPrice(video,qty).price;return <article className="item-card cart-item" key={video.id}>{video.poster_path?<img src={video.poster_path} alt=""/>:<video src={video.video_path} muted/>}<div className="info"><div className="cart-title-row"><h3>{video.title}</h3><button className="cart-remove" onClick={()=>removeCart(String(video.id))}>×</button></div><div className="cart-prices"><div><span>قیمت واحد:</span><b>{fa.format(p)} تومان</b></div><div><span>مبلغ کل:</span><b>{fa.format(p*qty)} تومان</b></div></div><div className="cart-actions"><span>تعداد</span><div className="cart-qty"><button onClick={()=>changeCart(String(video.id),-1)}>−</button><b>{fa.format(qty)}</b><button onClick={()=>changeCart(String(video.id),1)}>+</button></div></div></div></article>})}</div>{!cartItems.length&&<div className="empty-state"><h2>سبد خرید خالی است</h2><p>هنگام تماشای ویدئو، تعداد را انتخاب و به سبد اضافه کن.</p></div>}{!!cartItems.length&&<div className="cart-summary"><div><span>جمع سفارش:</span><strong>{fa.format(cartTotal)} تومان</strong></div><button onClick={submitOrder}>ثبت سفارش</button></div>}</section>

    <BottomNav page={page} setPage={setPage} count={cartCount}/>

    <Sheet show={sheet==='details'} onClose={()=>setSheet(null)}><div className="sheet-head"><h2>اطلاعات محصول</h2><button className="close-sheet" onClick={()=>setSheet(null)}>×</button></div>{sheetVideo&&<><div className="spec-grid"><div><small>برند</small><b>{sheetVideo.brand||'—'}</b></div><div><small>زمان ارسال</small><b>{sheetVideo.shipping_text||'—'}</b></div><div><small>کد محصول</small><b>{sheetVideo.product_code||'—'}</b></div><div><small>موجودی</small><b>{fa.format(Number(sheetVideo.stock_remaining||0))} عدد</b></div></div><h3 className="sheet-section-title">توضیحات محصول</h3><p className="sheet-description">{sheetVideo.description||'توضیحی برای این محصول ثبت نشده است.'}</p></>}</Sheet>

    <Sheet show={sheet==='comments'} onClose={()=>setSheet(null)}><div className="sheet-head"><h2>کامنت ها</h2><button className="close-sheet" onClick={()=>setSheet(null)}>×</button></div><div className="comments">{comments.map(c=><article className="comment" key={c.id}><span className="comment-avatar">{String(c.display_name||'ک').trim().charAt(0)}</span><div className="comment-content"><b>{c.display_name}</b><p>{c.body}</p></div></article>)}</div><form className="comment-form" onSubmit={submitComment}><input name="display_name" placeholder="نام شما" required/><textarea name="body" placeholder="نظر یا سوال خود را بنویسید" required/><button>ثبت دیدگاه</button></form></Sheet>

    <Sheet show={sheet==='account'} onClose={()=>setSheet(null)} className="account-sheet"><div className="sheet-head"><h2>حساب ایویز</h2><button className="close-sheet" onClick={()=>setSheet(null)}>×</button></div>{user?<><div className="profile-name"><span className="profile-avatar">{String(user.fullName||user.userName||'ا').charAt(0)}</span><div><b>{user.fullName||user.userName}</b><small>{user.userName}</small></div></div><div className="deposit-card"><span>سپرده قابل استفاده</span><strong>{fa.format(Number(user.revenue||0))} تومان</strong></div>{message&&<div className="deposit-alert">{message}</div>}<a className="increase-deposit" href={depositUrl} target="_blank" rel="noreferrer">افزایش سپرده در ایویز</a><button className="eways-logout" onClick={logout}>خروج از حساب ایویز</button></>:<><div className="account-intro"><span className="account-logo">E</span><div><b>ورود به حساب ایویز</b><p>برای مشاهده سپرده و خرید محصولات متصل وارد شوید.</p></div></div><form className="eways-login-form" onSubmit={login}><label>نام کاربری ایویز<input name="username" required/></label><label>رمز عبور ایویز<input name="password" type="password" required/></label><button>ورود به ایویز</button><p className="account-message">{message}</p></form></>}</Sheet>

    {toast&&<div className="toast show">{toast}</div>}
  </main>;
}

import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { adminApi } from './api';

function Login({ onLogin }) {
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setBusy(true);
    setMessage('');
    const form = new FormData(event.currentTarget);
    try {
      await adminApi.login(form.get('username'), form.get('password'));
      onLogin();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} style={{width:'min(420px,100%)',display:'grid',gap:14,padding:28,background:'#fff',borderRadius:24,boxShadow:'0 20px 60px rgba(20,24,40,.10)'}}>
      <div style={{width:52,height:52,borderRadius:16,display:'grid',placeItems:'center',background:'#111827',color:'#fff',fontWeight:800,fontSize:24}}>E</div>
      <h1 style={{margin:'4px 0 0'}}>مدیریت EWAYS Next</h1>
      <p style={{margin:0,color:'#6b7280'}}>نسخه React + Laravel پنل مدیریت</p>
      <label style={{display:'grid',gap:6}}>نام کاربری<input name="username" autoComplete="username" required style={{padding:12,border:'1px solid #d1d5db',borderRadius:12}} /></label>
      <label style={{display:'grid',gap:6}}>رمز عبور<input name="password" type="password" autoComplete="current-password" required style={{padding:12,border:'1px solid #d1d5db',borderRadius:12}} /></label>
      <button disabled={busy} style={{padding:13,border:0,borderRadius:13,background:'#111827',color:'#fff'}}>{busy ? 'در حال ورود...' : 'ورود به مدیریت'}</button>
      {message && <p style={{margin:0,color:'#b91c1c'}}>{message}</p>}
    </form>
  );
}

function Dashboard({ onLogout }) {
  const [videos, setVideos] = useState([]);
  const [categories, setCategories] = useState([]);
  const [lookup, setLookup] = useState(null);
  const [lookupMessage, setLookupMessage] = useState('');

  useEffect(() => {
    Promise.all([adminApi.videos(), adminApi.categories()]).then(([v, c]) => {
      setVideos(v.videos || []);
      setCategories(c.categories || []);
    });
  }, []);

  async function lookupProduct(event) {
    event.preventDefault();
    setLookup(null);
    setLookupMessage('');
    const id = new FormData(event.currentTarget).get('product_id');
    try {
      const data = await adminApi.lookupProduct(id);
      setLookup(data.product);
    } catch (error) {
      setLookupMessage(error.message);
    }
  }

  return (
    <div style={{width:'min(1100px,100%)',padding:24}}>
      <header style={{display:'flex',justifyContent:'space-between',alignItems:'center',gap:16,marginBottom:24}}>
        <div><small style={{color:'#6b7280'}}>EWAYS Video</small><h1 style={{margin:4}}>صفحه مدیریت جدید</h1></div>
        <button onClick={onLogout} style={{padding:'10px 16px',border:'1px solid #d1d5db',borderRadius:12,background:'#fff'}}>خروج</button>
      </header>

      <section style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(180px,1fr))',gap:14,marginBottom:20}}>
        <article style={{background:'#fff',padding:18,borderRadius:18}}><span style={{color:'#6b7280'}}>کل ویدئوها</span><b style={{display:'block',fontSize:28}}>{videos.length.toLocaleString('fa-IR')}</b></article>
        <article style={{background:'#fff',padding:18,borderRadius:18}}><span style={{color:'#6b7280'}}>ویدئوهای فعال</span><b style={{display:'block',fontSize:28}}>{videos.filter(v => Number(v.is_active)).length.toLocaleString('fa-IR')}</b></article>
        <article style={{background:'#fff',padding:18,borderRadius:18}}><span style={{color:'#6b7280'}}>دسته‌بندی‌ها</span><b style={{display:'block',fontSize:28}}>{categories.length.toLocaleString('fa-IR')}</b></article>
      </section>

      <section style={{background:'#fff',padding:20,borderRadius:20}}>
        <h2 style={{marginTop:0}}>استعلام کالای EWAYS</h2>
        <form onSubmit={lookupProduct} style={{display:'flex',gap:10,flexWrap:'wrap'}}>
          <input name="product_id" type="number" min="1" inputMode="numeric" required placeholder="کد کالای EWAYS" style={{flex:'1 1 240px',padding:12,border:'1px solid #d1d5db',borderRadius:12}} />
          <button style={{padding:'12px 18px',border:0,borderRadius:12,background:'#111827',color:'#fff'}}>استعلام کالا</button>
        </form>
        {lookupMessage && <p style={{color:'#b91c1c'}}>{lookupMessage}</p>}
        {lookup && <div style={{marginTop:16,padding:16,background:'#f9fafb',borderRadius:14}}><b>{lookup.title || lookup.name || `کالا ${lookup.id}`}</b><pre style={{whiteSpace:'pre-wrap',direction:'ltr',textAlign:'left',fontSize:12,overflow:'auto'}}>{JSON.stringify(lookup,null,2)}</pre></div>}
      </section>
    </div>
  );
}

function AdminApp() {
  const [loading, setLoading] = useState(true);
  const [authenticated, setAuthenticated] = useState(false);

  useEffect(() => {
    adminApi.status().then(data => setAuthenticated(Boolean(data.authenticated))).finally(() => setLoading(false));
  }, []);

  async function logout() {
    await adminApi.logout();
    setAuthenticated(false);
  }

  return (
    <main dir="rtl" style={{minHeight:'100dvh',display:'grid',placeItems:'center',background:'#f6f7fb',color:'#16181d',fontFamily:'Vazirmatn,Tahoma,sans-serif'}}>
      {loading ? <p>در حال بارگذاری...</p> : authenticated ? <Dashboard onLogout={logout} /> : <Login onLogin={() => setAuthenticated(true)} />}
    </main>
  );
}

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <AdminApp />
    </BrowserRouter>
  </React.StrictMode>,
);

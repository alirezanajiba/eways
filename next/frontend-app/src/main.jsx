import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { api } from './api';

function App() {
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState(null);
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    api.currentUser()
      .then((data) => setUser(data.authenticated ? data.user : null))
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  async function login(event) {
    event.preventDefault();
    setSubmitting(true);
    setMessage('');
    const form = new FormData(event.currentTarget);
    try {
      const data = await api.login(form.get('username'), form.get('password'));
      setUser(data.user);
      event.currentTarget.reset();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setSubmitting(false);
    }
  }

  async function logout() {
    setSubmitting(true);
    try {
      await api.logout();
      setUser(null);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main dir="rtl" style={{minHeight:'100dvh',display:'grid',placeItems:'center',background:'#05060a',color:'#fff',fontFamily:'Vazirmatn,Tahoma,sans-serif',padding:24}}>
      <section style={{width:'min(420px,100%)',padding:24,border:'1px solid rgba(255,255,255,.12)',borderRadius:24,background:'rgba(255,255,255,.06)',backdropFilter:'blur(20px)'}}>
        <small style={{opacity:.65}}>نسخه بازنویسی React + Laravel</small>
        <h1 style={{margin:'8px 0 20px'}}>EWAYS Video Next</h1>

        {loading ? <p>در حال بررسی حساب ایویز...</p> : user ? (
          <div>
            <p>ورود موفق</p>
            <strong style={{display:'block',fontSize:22,marginBottom:8}}>{user.fullName || user.userName || 'کاربر ایویز'}</strong>
            <span style={{display:'block',opacity:.75,marginBottom:18}}>سپرده: {Number(user.revenue || 0).toLocaleString('fa-IR')} تومان</span>
            <button disabled={submitting} onClick={logout} style={{width:'100%',padding:12,border:0,borderRadius:14,cursor:'pointer'}}>خروج از حساب ایویز</button>
          </div>
        ) : (
          <form onSubmit={login} style={{display:'grid',gap:12}}>
            <label style={{display:'grid',gap:6}}>نام کاربری ایویز<input name="username" required autoComplete="username" style={{padding:12,borderRadius:12,border:'1px solid #333',background:'#11131a',color:'#fff'}} /></label>
            <label style={{display:'grid',gap:6}}>رمز عبور ایویز<input name="password" type="password" required autoComplete="current-password" style={{padding:12,borderRadius:12,border:'1px solid #333',background:'#11131a',color:'#fff'}} /></label>
            <button disabled={submitting} type="submit" style={{padding:12,border:0,borderRadius:14,cursor:'pointer'}}>{submitting ? 'در حال ورود...' : 'ورود به ایویز'}</button>
            {message && <p style={{margin:0,color:'#ff9f9f'}}>{message}</p>}
          </form>
        )}
      </section>
    </main>
  );
}

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </React.StrictMode>,
);

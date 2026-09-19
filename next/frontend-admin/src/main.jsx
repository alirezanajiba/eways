import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

function AdminApp() {
  return (
    <main style={{minHeight:'100dvh',display:'grid',placeItems:'center',background:'#f6f7fb',color:'#16181d',fontFamily:'Vazirmatn, sans-serif'}}>
      <section style={{textAlign:'center',padding:24}}>
        <strong style={{fontSize:28}}>مدیریت EWAYS Next</strong>
        <p>پایه React پنل مدیریت آماده است.</p>
      </section>
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

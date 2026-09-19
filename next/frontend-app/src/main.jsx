import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

function App() {
  return (
    <main style={{minHeight:'100dvh',display:'grid',placeItems:'center',background:'#05060a',color:'#fff',fontFamily:'Vazirmatn, sans-serif'}}>
      <section style={{textAlign:'center',padding:24}}>
        <strong style={{fontSize:28}}>EWAYS Video Next</strong>
        <p>پایه React اپلیکیشن آماده است.</p>
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

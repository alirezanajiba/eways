const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json' };
const API_BASE = (import.meta.env.VITE_API_BASE || '/api/v1').replace(/\/$/, '');

async function request(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    credentials: 'same-origin',
    cache: 'no-store',
    ...options,
    headers: { ...jsonHeaders, ...(options.headers || {}) },
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) {
    const error = new Error(data.message || 'خطا در ارتباط با سرور');
    error.status = response.status;
    error.data = data;
    throw error;
  }
  return data;
}

export const api = {
  currentUser: () => request('/eways/user', { method: 'GET' }),
  login: (username, password) => request('/eways/login', { method: 'POST', body: JSON.stringify({ username, password }) }),
  logout: () => request('/eways/logout', { method: 'POST', body: '{}' }),
  submitOrder: (items) => request('/orders', { method: 'POST', body: JSON.stringify({ items }) }),
};

export { API_BASE };

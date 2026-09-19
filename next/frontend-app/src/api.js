const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json' };

async function request(path, options = {}) {
  const response = await fetch(path, {
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
  currentUser: () => request('/api/v1/eways/user', { method: 'GET' }),
  login: (username, password) => request('/api/v1/eways/login', {
    method: 'POST',
    body: JSON.stringify({ username, password }),
  }),
  logout: () => request('/api/v1/eways/logout', { method: 'POST', body: '{}' }),
  submitOrder: (items) => request('/api/v1/orders', {
    method: 'POST',
    body: JSON.stringify({ items }),
  }),
};

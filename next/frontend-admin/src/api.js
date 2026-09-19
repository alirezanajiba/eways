const API_BASE = (import.meta.env.VITE_API_BASE || '/api/v1').replace(/\/$/, '');

async function request(path, options = {}) {
  const isFormData = options.body instanceof FormData;
  const response = await fetch(`${API_BASE}${path}`, {
    credentials: 'same-origin',
    cache: 'no-store',
    ...options,
    headers: {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...(options.headers || {}),
    },
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) {
    const error = new Error(data.message || 'خطای سرور');
    error.status = response.status;
    error.data = data;
    throw error;
  }
  return data;
}

export const adminApi = {
  status: () => request('/admin/status'),
  login: (username, password) => request('/admin/login', { method: 'POST', body: JSON.stringify({ username, password }) }),
  logout: () => request('/admin/logout', { method: 'POST', body: '{}' }),
  videos: () => request('/admin/videos'),
  categories: () => request('/admin/categories'),
  lookupProduct: (productId) => request('/admin/eways-product', { method: 'POST', body: JSON.stringify({ product_id: Number(productId) }) }),
  saveCategory: (payload) => request('/admin/categories/save', { method: 'POST', body: JSON.stringify(payload) }),
  deleteCategory: (id) => request('/admin/categories/delete', { method: 'POST', body: JSON.stringify({ id: Number(id) }) }),
  saveVideo: (formData) => request('/admin/videos/save', { method: 'POST', body: formData }),
  deleteVideo: (id) => request('/admin/videos/delete', { method: 'POST', body: JSON.stringify({ id: Number(id) }) }),
};

export { API_BASE };

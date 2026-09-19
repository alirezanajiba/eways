async function request(path, options = {}) {
  const isFormData = options.body instanceof FormData;
  const response = await fetch(path, {
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
  status: () => request('/api/v1/admin/status'),
  login: (username, password) => request('/api/v1/admin/login', { method: 'POST', body: JSON.stringify({ username, password }) }),
  logout: () => request('/api/v1/admin/logout', { method: 'POST', body: '{}' }),
  videos: () => request('/api/v1/admin/videos'),
  categories: () => request('/api/v1/admin/categories'),
  lookupProduct: (productId) => request('/api/v1/admin/eways-product', { method: 'POST', body: JSON.stringify({ product_id: Number(productId) }) }),
  saveCategory: (payload) => request('/api/v1/admin/categories/save', { method: 'POST', body: JSON.stringify(payload) }),
  deleteCategory: (id) => request('/api/v1/admin/categories/delete', { method: 'POST', body: JSON.stringify({ id: Number(id) }) }),
  saveVideo: (formData) => request('/api/v1/admin/videos/save', { method: 'POST', body: formData }),
  deleteVideo: (id) => request('/api/v1/admin/videos/delete', { method: 'POST', body: JSON.stringify({ id: Number(id) }) }),
};

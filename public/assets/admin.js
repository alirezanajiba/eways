const fa = new Intl.NumberFormat('fa-IR');
let videos = [];
let deleteId = null;
const loginShell = document.getElementById('login-shell');
const dashboard = document.getElementById('dashboard');
const editor = document.getElementById('editor');
const backdrop = document.getElementById('backdrop');
const form = document.getElementById('video-form');
const toastEl = document.getElementById('toast');

function toast(message) {
  toastEl.textContent = message;
  toastEl.classList.add('show');
  setTimeout(() => toastEl.classList.remove('show'), 2200);
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value || '';
  return div.innerHTML;
}

async function request(url, options = {}) {
  const response = await fetch(url, options);
  const data = await response.json();
  if (!response.ok || !data.ok) throw new Error(data.message || 'خطای سرور');
  return data;
}

async function init() {
  try {
    const data = await request('/api.php?action=admin-status');
    showAuthenticated(data.authenticated);
    if (data.authenticated) await loadVideos();
  } catch {
    showAuthenticated(false);
  }
}

function showAuthenticated(authenticated) {
  loginShell.classList.toggle('hidden', authenticated);
  dashboard.classList.toggle('hidden', !authenticated);
}

document.getElementById('login-form').onsubmit = async event => {
  event.preventDefault();
  const message = document.getElementById('login-message');
  message.textContent = '';
  const values = Object.fromEntries(new FormData(event.currentTarget));
  try {
    await request('/api.php?action=admin-login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(values)
    });
    showAuthenticated(true);
    await loadVideos();
  } catch (error) {
    message.textContent = error.message;
  }
};

document.getElementById('logout-btn').onclick = async () => {
  await request('/api.php?action=admin-logout', { method: 'POST' });
  showAuthenticated(false);
};

async function loadVideos() {
  const data = await request('/api.php?action=admin-videos');
  videos = data.videos;
  renderVideos();
}

function renderVideos() {
  const list = document.getElementById('video-list');
  document.getElementById('total-count').textContent = fa.format(videos.length);
  document.getElementById('active-count').textContent = fa.format(videos.filter(v => Number(v.is_active)).length);
  document.getElementById('admin-empty').classList.toggle('hidden', videos.length > 0);
  list.innerHTML = videos.map(video => {
    const media = video.poster_path
      ? '<img src="' + escapeHtml(video.poster_path) + '" alt="">'
      : '<video src="' + escapeHtml(video.video_path) + '" muted playsinline preload="metadata"></video>';
    return '<article class="video-row" data-id="' + video.id + '">' +
      media +
      '<div class="video-info"><h3>' + escapeHtml(video.title) + '</h3><p>' + escapeHtml(video.brand || 'بدون برند') + ' · موجودی ' + fa.format(video.stock_remaining) + ' عدد</p><strong>' + fa.format(video.price) + ' تومان</strong></div>' +
      '<div class="row-actions"><span class="status ' + (Number(video.is_active) ? '' : 'off') + '">' + (Number(video.is_active) ? 'فعال' : 'غیرفعال') + '</span><button class="edit-btn">ویرایش</button><button class="delete-btn">حذف</button></div>' +
      '</article>';
  }).join('');
  list.querySelectorAll('.edit-btn').forEach(button => button.onclick = () => openEditor(videos.find(v => String(v.id) === button.closest('.video-row').dataset.id)));
  list.querySelectorAll('.delete-btn').forEach(button => button.onclick = () => openDelete(button.closest('.video-row').dataset.id));
}

function openEditor(video = null) {
  form.reset();
  form.elements.is_active.checked = true;
  document.getElementById('save-message').textContent = '';
  document.getElementById('upload-progress').classList.add('hidden');
  document.getElementById('video-file-name').textContent = 'حداکثر حجم ۱۲۸ مگابایت';
  document.getElementById('poster-file-name').textContent = 'بدون کاور هم ویدئو پخش می شود';
  document.getElementById('editor-kicker').textContent = video ? 'ویرایش ویدئو' : 'ویدئوی جدید';
  document.getElementById('editor-title').textContent = video ? video.title : 'افزودن ویدئو';
  document.getElementById('remove-poster-row').classList.toggle('hidden', !video?.poster_path);
  if (video) {
    Object.keys(video).forEach(key => {
      if (!form.elements[key]) return;
      if (key === 'is_active') form.elements[key].checked = Number(video[key]) === 1;
      else if (key === 'timer_end' && video[key]) form.elements[key].value = video[key].replace(' ', 'T').slice(0, 16);
      else form.elements[key].value = video[key] ?? '';
    });
  }
  backdrop.classList.add('show');
  editor.classList.add('show');
}

function closeEditor() {
  backdrop.classList.remove('show');
  editor.classList.remove('show');
}

document.getElementById('new-video-btn').onclick = () => openEditor();
document.getElementById('close-editor').onclick = closeEditor;
document.getElementById('cancel-editor').onclick = closeEditor;
backdrop.onclick = closeEditor;
form.elements.video_file.onchange = event => document.getElementById('video-file-name').textContent = event.target.files[0]?.name || 'حداکثر حجم ۱۲۸ مگابایت';
form.elements.poster_file.onchange = event => document.getElementById('poster-file-name').textContent = event.target.files[0]?.name || 'بدون کاور هم ویدئو پخش می شود';

form.onsubmit = event => {
  event.preventDefault();
  const message = document.getElementById('save-message');
  const progress = document.getElementById('upload-progress');
  const bar = progress.querySelector('i');
  const label = progress.querySelector('span');
  message.textContent = '';
  progress.classList.remove('hidden');
  const xhr = new XMLHttpRequest();
  xhr.open('POST', '/api.php?action=admin-save');
  xhr.upload.onprogress = e => {
    if (!e.lengthComputable) return;
    const percent = Math.round(e.loaded / e.total * 100);
    bar.style.width = percent + '%';
    label.textContent = 'در حال بارگذاری... ' + fa.format(percent) + '٪';
  };
  xhr.onload = async () => {
    let data = {};
    try { data = JSON.parse(xhr.responseText); } catch {}
    if (xhr.status >= 200 && xhr.status < 300 && data.ok) {
      label.textContent = 'بارگذاری کامل شد';
      message.className = 'form-message success';
      message.textContent = data.message;
      await loadVideos();
      setTimeout(closeEditor, 650);
      toast('ویدئو ذخیره شد');
    } else {
      message.className = 'form-message';
      message.textContent = data.message || 'ذخیره ویدئو انجام نشد.';
      progress.classList.add('hidden');
    }
  };
  xhr.onerror = () => {
    message.textContent = 'ارتباط هنگام بارگذاری قطع شد.';
    progress.classList.add('hidden');
  };
  xhr.send(new FormData(form));
};

function openDelete(id) {
  deleteId = id;
  document.getElementById('delete-confirm').classList.remove('hidden');
}

document.getElementById('cancel-delete').onclick = () => document.getElementById('delete-confirm').classList.add('hidden');
document.getElementById('confirm-delete').onclick = async () => {
  try {
    await request('/api.php?action=admin-delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: deleteId })
    });
    document.getElementById('delete-confirm').classList.add('hidden');
    await loadVideos();
    toast('ویدئو حذف شد');
  } catch (error) {
    toast(error.message);
  }
};

init();


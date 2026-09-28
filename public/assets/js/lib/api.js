// Server bilan aloqa: CSRF, vaqt tugashi, xatolar va server soati bilan sinxronlash.

const meta = document.querySelector('meta[name="api-base"]');
const BASE = new URL(meta ? meta.content : 'api/', document.baseURI);

let csrfToken = '';
let clockOffset = 0;
let clockSamples = 0;

export class ApiError extends Error {
  constructor(status, code, message, data = {}) {
    super(message);
    this.status = status;
    this.code = code;
    this.data = data;
  }

  get isNetwork() {
    return this.status === 0;
  }
}

export function setCsrf(token) {
  csrfToken = token || '';
}

export function apiUrl(path) {
  return new URL(path.replace(/^\//, ''), BASE).toString();
}

/** Server vaqti (millisoniya). Qurilma soati noto'g'ri bo'lsa ham taymer to'g'ri ishlaydi. */
export function serverNow() {
  return Date.now() + clockOffset;
}

export function syncClock(serverMs, sentAt, receivedAt) {
  if (typeof serverMs !== 'number') return;
  const rtt = receivedAt - sentAt;
  if (rtt > 5000) return; // Juda sekin javob — aniq emas.
  const sample = serverMs - (sentAt + rtt / 2);
  clockSamples += 1;
  // Birinchi namunani to'liq qabul qilamiz, keyin silliqlaymiz.
  clockOffset = clockSamples === 1 ? sample : clockOffset * 0.7 + sample * 0.3;
}

/**
 * @param {string} method
 * @param {string} path
 * @param {object|FormData|null} body
 * @param {{timeout?: number, signal?: AbortSignal}} options
 */
export async function api(method, path, body = null, options = {}) {
  const { timeout = 25000 } = options;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);
  if (options.signal) options.signal.addEventListener('abort', () => controller.abort(), { once: true });

  const headers = { Accept: 'application/json' };
  if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
  let payload;
  if (body instanceof FormData) payload = body;
  else if (body !== null && method !== 'GET') {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  const sentAt = Date.now();
  let response;
  try {
    response = await fetch(apiUrl(path), {
      method,
      headers,
      body: payload,
      credentials: 'same-origin',
      cache: 'no-store',
      signal: controller.signal,
    });
  } catch (err) {
    clearTimeout(timer);
    throw new ApiError(0, 'network', "Internet aloqasi yo'q yoki server javob bermadi.");
  }
  clearTimeout(timer);
  const receivedAt = Date.now();

  let data = null;
  const type = response.headers.get('Content-Type') || '';
  if (type.includes('application/json')) {
    try {
      data = await response.json();
    } catch {
      data = null;
    }
  }
  if (!response.ok) {
    const err = data && data.error ? data.error : {};
    throw new ApiError(response.status, err.code || 'http_' + response.status, err.message || 'Serverda xatolik yuz berdi.', err);
  }
  if (data && typeof data.now === 'number') syncClock(data.now, sentAt, receivedAt);
  return data;
}

export const get = (path, options) => api('GET', path, null, options);
export const post = (path, body = {}, options) => api('POST', path, body, options);
export const put = (path, body = {}, options) => api('PUT', path, body, options);
export const del = (path, options) => api('DELETE', path, null, options);

/** Faylni yuklash jarayonini ko'rsatib yuklab olish (Listening audiolari uchun). */
export function download(path, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', apiUrl(path));
    xhr.responseType = 'blob';
    xhr.withCredentials = true;
    xhr.timeout = 180000;
    xhr.onprogress = (e) => onProgress && onProgress(e.loaded, e.lengthComputable ? e.total : 0);
    xhr.onload = () => {
      if (xhr.status >= 200 && xhr.status < 300) resolve(xhr.response);
      else reject(new ApiError(xhr.status, 'download_failed', 'Faylni yuklab bo\'lmadi.'));
    };
    xhr.onerror = () => reject(new ApiError(0, 'network', "Internet aloqasi yo'q."));
    xhr.ontimeout = () => reject(new ApiError(0, 'network', 'Yuklash juda uzoq davom etdi.'));
    xhr.send();
  });
}

/** FormData'ni yuklash (Speaking yozuvi va admin fayllari) — jarayon foizi bilan. */
export function upload(path, formData, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', apiUrl(path));
    xhr.withCredentials = true;
    xhr.timeout = 300000;
    if (csrfToken) xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = (e) => onProgress && e.lengthComputable && onProgress(e.loaded, e.total);
    xhr.onload = () => {
      let data = null;
      try {
        data = JSON.parse(xhr.responseText);
      } catch {
        data = null;
      }
      if (xhr.status >= 200 && xhr.status < 300) resolve(data);
      else {
        const err = data && data.error ? data.error : {};
        reject(new ApiError(xhr.status, err.code || 'http_' + xhr.status, err.message || 'Yuklashda xatolik.', err));
      }
    };
    xhr.onerror = () => reject(new ApiError(0, 'network', "Internet aloqasi yo'q."));
    xhr.ontimeout = () => reject(new ApiError(0, 'network', 'Yuklash juda uzoq davom etdi.'));
    xhr.send(formData);
  });
}

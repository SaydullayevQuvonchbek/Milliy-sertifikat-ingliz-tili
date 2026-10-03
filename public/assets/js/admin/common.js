// Admin panel uchun umumiy yordamchilar: sarlavha, jadval, holat belgisi, forma maydonlari.

import { h, icon } from '../lib/dom.js';

export const MOCK_STATUS = {
  draft: 'Qoralama',
  active: 'Faol',
  frozen: 'Muzlatilgan',
  archived: 'Arxivlangan',
};

export const ATTEMPT_STATUS = {
  in_progress: 'Jarayonda',
  completed: 'Yakunlangan',
  terminated: 'Chetlatilgan',
};

export const ROLE = { admin: 'Administrator', expert: 'Ekspert', student: "O'quvchi" };

export const STAGE = { L: 'Listening', R: 'Reading', W: 'Writing', S: 'Speaking', done: 'Yakunlangan' };

export const EVENT = {
  focus_lost: 'Oynadan chiqish',
  fullscreen_exit: "To'liq ekrandan chiqish",
  page_closed: 'Sahifa yopilgan',
  device_takeover: 'Boshqa oynaga ko\'chirish',
  multiple_tabs: 'Ikkinchi oyna',
  devtools: 'Dasturchi vositalari',
  paste_blocked: 'Joylashtirish bloklandi',
  copy_blocked: 'Nusxa olish bloklandi',
  cut_blocked: 'Kesib olish bloklandi',
  shortcut_blocked: 'Tugma bloklandi',
  contextmenu_blocked: "O'ng tugma bloklandi",
  drop_blocked: 'Fayl tashlash bloklandi',
  print_blocked: 'Chop etish bloklandi',
  offline: "Internet uzildi",
  online: 'Internet tiklandi',
  reload: 'Sahifa yangilandi',
  returned: 'Imtihonga qaytdi',
  audio_error: 'Audio xatosi',
  audio_resync: 'Audio sinxronlandi',
  large_insert: 'Katta matn bo\'lagi',
  mic_error: 'Mikrofon xatosi',
  device_change: 'Qurilma almashdi',
  terminated: 'Imtihon to\'xtatildi',
  camera_ok: 'Kamera yoqildi',
  camera_none: 'Kamera topilmadi',
  camera_denied: 'Kameraga ruxsat berilmadi',
  camera_lost: 'Kamera uzildi',
  screen_ok: 'Ekran ulashildi',
  screen_denied: 'Ekran ulashilmadi',
  screen_wrong: 'Butun ekran tanlanmadi',
  screen_stopped: "Ekran ulashish to'xtatildi",
  screen_unsupported: "Brauzer ekran yozishni qo'llamaydi",
  multi_screen: 'Bir nechta monitor',
  rec_error: 'Video yozuv xatosi',
  rec_dropped: "Video bo'lagi yo'qoldi",
  rec_unsupported: "Brauzer video yozishni qo'llamaydi",
};

/** Video nazorat holati (natijalar jadvali va urinish sahifasi). */
export const PROCTOR_STATE = {
  ok: 'ishladi',
  none: "yo'q",
  denied: 'rad etildi',
  wrong: "butun ekran emas",
  unsupported: "brauzer qo'llamaydi",
  insecure: 'sayt HTTPS emas',
  error: 'xato',
  stopped: "to'xtatildi",
  lost: 'uzildi',
  off: "o'chiq",
};

export function statusBadge(status, labels = MOCK_STATUS) {
  return h('span', { class: `status status-${status}` }, labels[status] || status);
}

/** Sayt http:// da ochilgan bo'lsa — kamera, ekran va mikrofon ishlamasligi haqida ogohlantirish (aks holda null). */
export function httpsAlert() {
  if (window.isSecureContext !== false) return null;
  return h('div', { class: 'alert alert-warn' }, icon('alert'), h('div', {
    text: "Sayt hozir http:// orqali ochilgan. Brauzerlar kamera, ekran va mikrofonga faqat https:// saytlarda ruxsat beradi: "
      + "SSL o'rnatilmaguncha video yozilmaydi (o'quvchida «sayt HTTPS emas» belgisi turadi) va Speaking'da ovoz yozib bo'lmaydi. "
      + "Hosting panelida SSL sertifikatini (Let's Encrypt) yoqing va http → https yo'naltirishni o'rnating.",
  }));
}

export function pageHeader(title, ...actions) {
  return h('div', { class: 'page-head' }, h('h1', { text: title }), h('div', { class: 'page-actions' }, actions));
}

export function field(label, input, hint = '') {
  return h('label', { class: 'field' },
    h('span', { class: 'field-label', text: label }),
    input,
    hint ? h('span', { class: 'field-hint', text: hint }) : null
  );
}

export function checkbox(label, checked, onChange) {
  const input = h('input', { type: 'checkbox', checked, onchange: (e) => onChange(e.target.checked) });
  return h('label', { class: 'check' }, input, h('span', { text: label }));
}

/**
 * Oddiy jadval.
 * @param {Array<{title: string, render: (row:any, index:number) => any, class?: string}>} columns
 */
export function table(columns, rows, { empty = "Ma'lumot yo'q.", onRow = null } = {}) {
  if (!rows.length) return h('p', { class: 'empty', text: empty });
  return h('div', { class: 'table-wrap' },
    h('table', { class: 'table' },
      h('thead', null, h('tr', null, columns.map((c) => h('th', { class: c.class || null, text: c.title })))),
      h('tbody', null, rows.map((row, i) => {
        const tr = h('tr', { class: onRow ? 'clickable' : null }, columns.map((c) => {
          const value = c.render(row, i);
          return h('td', { class: c.class || null }, value === null || value === undefined ? '' : value);
        }));
        if (onRow) tr.addEventListener('click', (e) => !e.target.closest('button, a, input, select') && onRow(row));
        return tr;
      }))
    )
  );
}

/** Maydonni obyekt xususiyatiga bog'lash (qayta chizishsiz). */
export function bindInput(input, obj, key, { type = 'text', onChange = null } = {}) {
  const read = () => {
    if (type === 'checkbox') return input.checked;
    if (type === 'int') return input.value === '' ? 0 : Math.round(Number(input.value));
    if (type === 'float') return input.value === '' ? 0 : Number(input.value);
    return input.value;
  };
  if (type === 'checkbox') input.checked = Boolean(obj[key]);
  else input.value = obj[key] ?? '';
  input.addEventListener(type === 'checkbox' ? 'change' : 'input', () => {
    obj[key] = read();
    if (onChange) onChange(obj[key]);
  });
  return input;
}

export function textInput(obj, key, { placeholder = '', onChange, lang = 'en', spellcheck = true, className = 'input' } = {}) {
  return bindInput(h('input', { class: className, type: 'text', placeholder, lang, spellcheck: String(spellcheck) }), obj, key, { onChange });
}

export function textArea(obj, key, { rows = 3, placeholder = '', onChange, lang = 'en', spellcheck = true } = {}) {
  return bindInput(h('textarea', { class: 'input', rows, placeholder, lang, spellcheck: String(spellcheck) }), obj, key, { onChange });
}

export function numberInput(obj, key, { min = 0, max = 9999, step = 1, onChange, width = null } = {}) {
  return bindInput(h('input', { class: 'input input-num', type: 'number', min, max, step, style: width ? { width } : null }), obj, key, { type: step < 1 ? 'float' : 'int', onChange });
}

export function toLocalInput(seconds) {
  if (!seconds) return '';
  const d = new Date(seconds * 1000);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function fromLocalInput(value) {
  if (!value) return null;
  const t = new Date(value).getTime();
  return Number.isNaN(t) ? null : Math.round(t / 1000);
}

export function copyText(text) {
  if (navigator.clipboard) return navigator.clipboard.writeText(text);
  const area = h('textarea', { value: text });
  document.body.append(area);
  area.select();
  document.execCommand('copy');
  area.remove();
  return Promise.resolve();
}

export function downloadFile(name, content, type = 'application/json') {
  const url = URL.createObjectURL(new Blob([content], { type }));
  const a = h('a', { href: url, download: name });
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
export const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

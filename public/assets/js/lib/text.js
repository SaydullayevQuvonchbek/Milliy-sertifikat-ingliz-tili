// Matn yordamchilari: so'z sanash, vaqt va sana formatlari.

/** Inglizcha matndagi so'zlar soni (server bilan bir xil qoida: harf yoki raqam bor bo'lak = so'z). */
export function countWords(text) {
  const tokens = String(text || '').trim().split(/\s+/u);
  let count = 0;
  for (const token of tokens) {
    if (token && /[\p{L}\p{N}]/u.test(token)) count += 1;
  }
  return count;
}

/** 3725000 → "1:02:05", 125000 → "2:05" */
export function formatClock(ms) {
  const total = Math.max(0, Math.ceil(ms / 1000));
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  const pad = (n) => String(n).padStart(2, '0');
  return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`;
}

export function formatMinutes(ms) {
  const minutes = Math.round(ms / 60000);
  return `${minutes} daqiqa`;
}

const MONTHS = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];

/** Unix soniya → "28-sentabr, 14:05" */
export function formatDate(seconds, withYear = false) {
  if (!seconds) return '—';
  const d = new Date(seconds * 1000);
  const pad = (n) => String(n).padStart(2, '0');
  const year = withYear ? ` ${d.getFullYear()}-y.` : '';
  return `${d.getDate()}-${MONTHS[d.getMonth()]}${year}, ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function formatBytes(bytes) {
  if (!bytes) return '0 B';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / 1048576).toFixed(1)} MB`;
}

export function randomId(length = 24) {
  const bytes = new Uint8Array(length);
  crypto.getRandomValues(bytes);
  const alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  return Array.from(bytes, (b) => alphabet[b % alphabet.length]).join('');
}

export function levelLabel(level) {
  return { C1: 'C1', B2: 'B2', B1: 'B1', below: 'B1 dan quyi' }[level] || '—';
}

export function formatScore(value) {
  if (value === null || value === undefined || value === '') return '—';
  const n = Number(value);
  return Number.isInteger(n) ? String(n) : n.toFixed(1);
}

// Kichik DOM yordamchilari. innerHTML ishlatilmaydi — barcha matn textContent orqali
// qo'yiladi, shuning uchun admin yoki o'quvchi kiritgan matn sahifani buza olmaydi (XSS yo'q).

/**
 * Element yaratish: h('button', { class: 'btn', onclick: fn }, 'Matn')
 * props: class, text, style (obyekt), dataset (obyekt), on<event> (funksiya), qolganlari atribut.
 */
export function h(tag, props = null, ...children) {
  const el = document.createElement(tag);
  if (props) {
    for (const [key, value] of Object.entries(props)) {
      if (value === null || value === undefined || value === false) continue;
      if (key === 'class') el.className = Array.isArray(value) ? value.filter(Boolean).join(' ') : value;
      else if (key === 'text') el.textContent = value;
      else if (key === 'style' && typeof value === 'object') Object.assign(el.style, value);
      else if (key === 'dataset') Object.assign(el.dataset, value);
      else if (key.startsWith('on') && typeof value === 'function') el.addEventListener(key.slice(2).toLowerCase(), value);
      else if (key === 'value') el.value = value;
      else if (key === 'checked' || key === 'selected' || key === 'disabled' || key === 'hidden' || key === 'required' || key === 'readOnly' || key === 'multiple') el[key] = Boolean(value);
      else el.setAttribute(key, value === true ? '' : String(value));
    }
  }
  append(el, children);
  return el;
}

export function append(parent, children) {
  for (const child of children.flat(Infinity)) {
    if (child === null || child === undefined || child === false) continue;
    parent.append(child instanceof Node ? child : document.createTextNode(String(child)));
  }
  return parent;
}

export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

export function clear(el) {
  el.replaceChildren();
  return el;
}

export function mount(root, ...children) {
  root.replaceChildren();
  append(root, children);
  return root;
}

/** Faqat matn o'zgargan bo'lsa yozadi — keraksiz qayta chizishning oldini oladi. */
export function setText(el, text) {
  const value = String(text);
  if (el.textContent !== value) el.textContent = value;
}

export function toggleClass(el, name, on) {
  if (el.classList.contains(name) !== Boolean(on)) el.classList.toggle(name, Boolean(on));
}

export function icon(name) {
  const paths = {
    clock: 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    flag: 'M5 21V4m0 0h11l-2 4 2 4H5',
    volume: 'M11 5 6 9H3v6h3l5 4V5Zm4.5 3.5a5 5 0 0 1 0 7M18 6a8.5 8.5 0 0 1 0 12',
    check: 'm5 12 5 5 9-10',
    left: 'm15 6-6 6 6 6',
    right: 'm9 6 6 6-6 6',
    text: 'M4 7V5h16v2M9 19h6M12 5v14',
    wifiOff: 'M3 3l18 18M8.5 16.5a5 5 0 0 1 7 0M5 12.5a10 10 0 0 1 5-2.7M19 12.5a10 10 0 0 0-2.2-1.6M12 20h.01',
    cloud: 'M7 18a4 4 0 1 1 .9-7.9A6 6 0 0 1 19 11a3.5 3.5 0 0 1 0 7H7Z',
    mic: 'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3Zm-7 9a7 7 0 0 0 14 0M12 19v3',
    user: 'M20 21a8 8 0 0 0-16 0M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
    logout: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
    note: 'M4 4h16v12l-4 4H4V4Zm12 16v-4h4',
    alert: 'M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
    play: 'M7 4v16l13-8L7 4Z',
    lock: 'M6 11h12v10H6V11Zm2 0V7a4 4 0 0 1 8 0v4',
  };
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('width', '18');
  svg.setAttribute('height', '18');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '2');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  svg.setAttribute('aria-hidden', 'true');
  svg.classList.add('icon');
  const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  path.setAttribute('d', paths[name] || '');
  svg.append(path);
  return svg;
}

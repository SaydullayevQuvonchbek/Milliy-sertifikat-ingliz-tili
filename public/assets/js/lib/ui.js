// Umumiy interfeys elementlari: bildirishnoma, modal oyna, tasdiqlash.
// Brauzerning alert/confirm oynalari ishlatilmaydi — ular imtihon oynasidan fokusni oladi.

import { h } from './dom.js';

let toastRoot = null;

export function toast(message, type = 'info', timeout = 4000) {
  if (!toastRoot) {
    toastRoot = h('div', { class: 'toasts', role: 'status', 'aria-live': 'polite' });
    document.body.append(toastRoot);
  }
  const el = h('div', { class: `toast toast-${type}` }, message);
  toastRoot.append(el);
  setTimeout(() => {
    el.classList.add('toast-hide');
    setTimeout(() => el.remove(), 300);
  }, timeout);
  return el;
}

/**
 * Modal oyna. Tugma bosilganda uning value qiymati bilan Promise yakunlanadi.
 * @param {{title: string, body?: Node|string, actions?: Array<{label:string, value:any, variant?:string}>, dismissible?: boolean, wide?: boolean}} options
 */
export function modal({ title, body = '', actions = [{ label: 'Yopish', value: null }], dismissible = true, wide = false }) {
  return new Promise((resolve) => {
    const previous = document.activeElement;
    let done = false;
    const close = (value) => {
      if (done) return;
      done = true;
      backdrop.remove();
      document.removeEventListener('keydown', onKey, true);
      if (previous && previous.focus) previous.focus();
      resolve(value);
    };
    const onKey = (e) => {
      if (e.key === 'Escape' && dismissible) {
        e.preventDefault();
        close(null);
      }
    };
    const buttons = actions.map((a) =>
      h('button', { class: `btn ${a.variant ? 'btn-' + a.variant : ''}`, type: 'button', onclick: () => close(a.value) }, a.label)
    );
    const dialog = h(
      'div',
      { class: `modal ${wide ? 'modal-wide' : ''}`, role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
      h('div', { class: 'modal-head' }, h('h3', { text: title })),
      h('div', { class: 'modal-body' }, typeof body === 'string' ? h('p', { text: body }) : body),
      h('div', { class: 'modal-actions' }, buttons)
    );
    const backdrop = h('div', { class: 'modal-backdrop' }, dialog);
    if (dismissible) backdrop.addEventListener('mousedown', (e) => e.target === backdrop && close(null));
    document.addEventListener('keydown', onKey, true);
    document.body.append(backdrop);
    const primary = buttons.find((b) => b.classList.contains('btn-primary')) || buttons[buttons.length - 1];
    if (primary) primary.focus();
  });
}

export async function confirmDialog(title, text, okLabel = 'Tasdiqlash', variant = 'primary') {
  const value = await modal({
    title,
    body: text,
    actions: [
      { label: 'Bekor qilish', value: false },
      { label: okLabel, value: true, variant },
    ],
  });
  return value === true;
}

export async function promptDialog(title, label, { placeholder = '', value = '', okLabel = 'Saqlash', multiline = false } = {}) {
  const input = multiline
    ? h('textarea', { class: 'input', rows: 3, placeholder, value })
    : h('input', { class: 'input', type: 'text', placeholder, value });
  const body = h('label', { class: 'field' }, h('span', { class: 'field-label', text: label }), input);
  setTimeout(() => input.focus(), 30);
  const result = await modal({
    title,
    body,
    actions: [
      { label: 'Bekor qilish', value: null },
      { label: okLabel, value: 'ok', variant: 'primary' },
    ],
  });
  return result === 'ok' ? input.value.trim() : null;
}

export function spinner(text = 'Yuklanmoqda…') {
  return h('div', { class: 'loading' }, h('span', { class: 'spinner', 'aria-hidden': 'true' }), h('span', { text }));
}

export function errorBox(message, retry) {
  return h(
    'div',
    { class: 'error-box', role: 'alert' },
    h('p', { text: message }),
    retry ? h('button', { class: 'btn', type: 'button', onclick: retry }, 'Qayta urinish') : null
  );
}

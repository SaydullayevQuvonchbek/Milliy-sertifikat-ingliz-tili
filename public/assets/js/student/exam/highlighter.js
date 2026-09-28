// Matnni belgilash (highlight) va izoh qoldirish — IELTS CD'dagi kabi.
// Matn tanlanganda kichik menyu chiqadi; sichqonchaning o'ng tugmasi ham shu menyuni ochadi.
// Belgilar faqat shu qurilmada saqlanadi (serverga yuborilmaydi).

import { h } from '../../lib/dom.js';

function textOffset(root, node, offset) {
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  let total = 0;
  let current = walker.nextNode();
  while (current) {
    if (current === node) return total + offset;
    total += current.nodeValue.length;
    current = walker.nextNode();
  }
  // Tanlov element chegarasida tugagan bo'lsa.
  if (node.nodeType === Node.ELEMENT_NODE) {
    let sum = 0;
    const w2 = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let t = w2.nextNode();
    const limit = node.childNodes[offset] || null;
    while (t) {
      if (limit && (limit === t || limit.contains(t))) return sum;
      sum += t.nodeValue.length;
      t = w2.nextNode();
    }
    return sum;
  }
  return total;
}

/** [start, end) oralig'idagi matn tugunlarini <mark> ichiga o'rash. */
function wrapRange(root, start, end, id, note) {
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  const targets = [];
  let pos = 0;
  let node = walker.nextNode();
  while (node) {
    const len = node.nodeValue.length;
    const s = Math.max(start, pos);
    const e = Math.min(end, pos + len);
    if (s < e && !node.parentElement.closest('.para-label')) targets.push([node, s - pos, e - pos]);
    pos += len;
    node = walker.nextNode();
  }
  for (const [textNode, s, e] of targets) {
    let target = textNode;
    if (s > 0) target = target.splitText(s);
    if (e - s < target.nodeValue.length) target.splitText(e - s);
    const mark = h('mark', { class: note ? 'hl hl-note' : 'hl', dataset: { hl: id }, title: note || null });
    target.parentNode.insertBefore(mark, target);
    mark.append(target);
  }
}

export class Highlighter {
  /**
   * @param {HTMLElement} root  belgilash mumkin bo'lgan matn konteyneri
   * @param {{load: () => Array, save: (items: Array) => void}} store
   */
  constructor(root, store) {
    this.root = root;
    this.store = store;
    this.items = store.load() || [];
    this.menu = null;
    for (const item of this.items) wrapRange(root, item.s, item.e, item.id, item.note);

    root.addEventListener('mouseup', () => setTimeout(() => this.onSelect(), 0));
    root.addEventListener('keyup', (e) => e.shiftKey && this.onSelect());
    root.addEventListener('touchend', () => setTimeout(() => this.onSelect(), 250));
    root.addEventListener('contextmenu', (e) => {
      const mark = e.target.closest && e.target.closest('mark.hl');
      if (mark) this.showMarkMenu(mark, e.clientX, e.clientY);
      else this.onSelect(e.clientX, e.clientY);
    });
    root.addEventListener('click', (e) => {
      const mark = e.target.closest && e.target.closest('mark.hl');
      const selection = window.getSelection();
      if (mark && selection && selection.isCollapsed) this.showMarkMenu(mark, e.clientX, e.clientY);
    });
    document.addEventListener('mousedown', (e) => {
      if (this.menu && !this.menu.contains(e.target)) this.closeMenu();
    });
    root.addEventListener('scroll', () => this.closeMenu(), { passive: true });
  }

  onSelect(x, y) {
    const selection = window.getSelection();
    if (!selection || selection.isCollapsed || selection.rangeCount === 0) return;
    const range = selection.getRangeAt(0);
    if (!this.root.contains(range.commonAncestorContainer)) return;
    const s = textOffset(this.root, range.startContainer, range.startOffset);
    const e = textOffset(this.root, range.endContainer, range.endOffset);
    if (e - s < 1) return;
    const rect = range.getBoundingClientRect();
    this.showSelectionMenu(s, e, x ?? rect.left + rect.width / 2, y ?? rect.bottom);
  }

  showSelectionMenu(s, e, x, y) {
    this.closeMenu();
    this.menu = h('div', { class: 'hl-menu', role: 'menu' },
      h('button', { type: 'button', role: 'menuitem', onclick: () => this.add(s, e, '') }, 'Belgilash'),
      h('button', { type: 'button', role: 'menuitem', onclick: () => this.addWithNote(s, e) }, 'Izoh qo\'shish')
    );
    this.place(x, y);
  }

  showMarkMenu(mark, x, y) {
    this.closeMenu();
    const id = mark.dataset.hl;
    const item = this.items.find((i) => i.id === id);
    this.menu = h('div', { class: 'hl-menu', role: 'menu' },
      item && item.note ? h('div', { class: 'hl-note-text', text: item.note }) : null,
      h('button', { type: 'button', role: 'menuitem', onclick: () => this.editNote(id) }, item && item.note ? 'Izohni o\'zgartirish' : 'Izoh qo\'shish'),
      h('button', { type: 'button', role: 'menuitem', onclick: () => this.remove(id) }, 'Belgini olib tashlash')
    );
    this.place(x, y);
  }

  place(x, y) {
    document.body.append(this.menu);
    const w = this.menu.offsetWidth;
    const hgt = this.menu.offsetHeight;
    this.menu.style.left = `${Math.max(8, Math.min(window.innerWidth - w - 8, x - w / 2))}px`;
    this.menu.style.top = `${Math.min(window.innerHeight - hgt - 8, y + 8)}px`;
  }

  closeMenu() {
    if (this.menu) {
      this.menu.remove();
      this.menu = null;
    }
  }

  add(s, e, note) {
    this.closeMenu();
    // Ustma-ust belgilarni birlashtirish.
    const overlapping = this.items.filter((i) => i.s < e && s < i.e);
    for (const item of overlapping) this.unwrap(item.id);
    const start = Math.min(s, ...overlapping.map((i) => i.s));
    const end = Math.max(e, ...overlapping.map((i) => i.e));
    const mergedNote = [note, ...overlapping.map((i) => i.note)].filter(Boolean).join(' | ');
    this.items = this.items.filter((i) => !overlapping.includes(i));
    const id = Math.random().toString(36).slice(2, 10);
    this.items.push({ id, s: start, e: end, note: mergedNote });
    wrapRange(this.root, start, end, id, mergedNote);
    window.getSelection()?.removeAllRanges();
    this.store.save(this.items);
    return id;
  }

  addWithNote(s, e) {
    const id = this.add(s, e, '');
    this.editNote(id);
  }

  editNote(id) {
    this.closeMenu();
    const item = this.items.find((i) => i.id === id);
    if (!item) return;
    const marks = this.root.querySelectorAll(`mark[data-hl="${id}"]`);
    const last = marks[marks.length - 1];
    const rect = last ? last.getBoundingClientRect() : { left: 100, bottom: 100 };
    const area = h('textarea', { class: 'hl-note-input', rows: 3, maxlength: 300, placeholder: 'Izoh…', value: item.note || '', spellcheck: 'false' });
    const save = () => {
      item.note = area.value.trim();
      marks.forEach((m) => {
        m.classList.toggle('hl-note', Boolean(item.note));
        if (item.note) m.title = item.note;
        else m.removeAttribute('title');
      });
      this.store.save(this.items);
      this.closeMenu();
    };
    this.menu = h('div', { class: 'hl-menu hl-editor' }, area, h('button', { type: 'button', class: 'btn btn-primary btn-sm', onclick: save }, 'Saqlash'));
    this.place(rect.left, rect.bottom);
    area.focus();
  }

  unwrap(id) {
    this.root.querySelectorAll(`mark[data-hl="${id}"]`).forEach((mark) => {
      const parent = mark.parentNode;
      while (mark.firstChild) parent.insertBefore(mark.firstChild, mark);
      mark.remove();
      parent.normalize();
    });
  }

  remove(id) {
    this.closeMenu();
    this.unwrap(id);
    this.items = this.items.filter((i) => i.id !== id);
    this.store.save(this.items);
  }
}

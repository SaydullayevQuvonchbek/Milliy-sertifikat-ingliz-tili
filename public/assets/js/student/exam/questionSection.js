// Reading va Listening bo'limlari uchun umumiy ko'rinish:
// Reading — chapda matn, o'ngda savollar (ajratgichni surib o'lchamini o'zgartirish mumkin);
// Listening — savollar bitta ustunda. Barcha qismlar bir marta quriladi, qism almashganda
// faqat ko'rinishi o'zgaradi — belgilar, yozilgan javoblar va aylantirish joyi saqlanib qoladi.

import { h, toggleClass } from '../../lib/dom.js';
import { renderRich } from '../../lib/markup.js';
import { T } from '../../lib/uz.js';
import { Highlighter } from './highlighter.js';
import { QuestionNav, loadPrefs, savePrefs } from './shell.js';
import { partNumbers, renderBlocks } from './questions.js';

export class QuestionSection {
  /**
   * @param {{
   *   section: 'L'|'R',
   *   content: {parts: Array},
   *   answers: Record<string,string>,
   *   shell: import('./shell.js').ExamShell,
   *   onAnswer?: (n:number, value:string) => void,
   *   readOnly?: boolean,
   *   key?: Record<string,string>,
   *   highlightKey?: string,
   *   flagsKey?: string,
   * }} options
   */
  constructor(options) {
    this.o = options;
    this.answers = new Map(Object.entries(options.answers || {}).map(([k, v]) => [String(k), String(v)]));
    this.items = new Map();
    this.partOf = new Map();
    this.partEls = [];
    this.currentPart = -1;
    this.prefs = loadPrefs();

    const parts = options.content.parts || [];
    this.nav = new QuestionNav(options.shell, {
      parts: parts.map((p, i) => ({ title: p.title || T.part(i + 1), numbers: partNumbers(p) })),
      onGo: (n) => this.goTo(n),
      onPart: (i) => this.showPart(i, true),
      onFlags: (flags) => this.saveFlags(flags),
    });

    this.root = h('div', { class: `qsection qsection-${options.section}` });
    parts.forEach((part, index) => {
      const el = this.buildPart(part, index);
      el.hidden = true;
      this.partEls.push(el);
      this.root.append(el);
    });

    for (const [n, value] of this.answers) this.nav.setAnswered(Number(n), value.trim() !== '');
    this.nav.updateCounters();
    this.nav.setFlags(this.loadFlags());

    this.root.addEventListener('focusin', (e) => {
      const q = e.target.closest && e.target.closest('.q[data-n]');
      if (q) this.nav.setCurrent(Number(q.dataset.n));
    });
    this.root.addEventListener('click', (e) => {
      const q = e.target.closest && e.target.closest('.q[data-n]');
      if (q) this.nav.setCurrent(Number(q.dataset.n));
    });

    if (parts.length) this.showPart(0, false);
    const first = this.nav.order[0];
    if (first !== undefined) this.nav.setCurrent(first);
  }

  buildPart(part, index) {
    const ctx = {
      section: this.o.section,
      answers: this.answers,
      readOnly: this.o.readOnly,
      key: this.o.key,
      onAnswer: (n, value) => this.answer(n, value),
      register: (n, item) => {
        this.items.set(n, item);
        this.partOf.set(n, index);
      },
    };
    const header = h('div', { class: 'part-header' },
      h('h2', { class: 'part-title', text: part.title || T.part(index + 1) }),
      part.instructions ? h('div', { class: 'part-instructions rich' }, renderRich(part.instructions)) : null
    );
    const questions = h('div', { class: 'questions' }, renderBlocks(part.blocks, ctx));

    if (this.o.section === 'R' && part.passage) {
      const passage = h('div', { class: 'passage rich', lang: 'en' },
        part.passage.title ? h('h3', { class: 'passage-title', text: part.passage.title }) : null,
        renderRich(part.passage.text, { paragraphClass: 'passage-p' })
      );
      const left = h('section', { class: 'pane pane-passage', 'aria-label': T.passage }, passage);
      const right = h('section', { class: 'pane pane-questions', 'aria-label': T.questions }, header, questions);
      const divider = h('div', { class: 'divider', role: 'separator', 'aria-orientation': 'vertical', tabindex: '0' });
      const split = h('div', { class: 'split' }, left, divider, right);
      this.applySplit(split);
      this.makeResizable(split, divider);

      const tabs = h('div', { class: 'mobile-tabs' },
        h('button', { type: 'button', class: 'mt active', onclick: (e) => this.mobileTab(split, 'passage', e.currentTarget) }, T.passage),
        h('button', { type: 'button', class: 'mt', onclick: (e) => this.mobileTab(split, 'questions', e.currentTarget) }, T.questions)
      );
      split.dataset.mobile = 'passage';

      if (!this.o.readOnly && this.o.highlightKey) {
        const key = `${this.o.highlightKey}:${index}`;
        new Highlighter(passage, {
          load: () => {
            try {
              return JSON.parse(localStorage.getItem(key) || '[]');
            } catch {
              return [];
            }
          },
          save: (items) => {
            try {
              localStorage.setItem(key, JSON.stringify(items));
            } catch {
              /* e'tiborsiz */
            }
          },
        });
      }
      return h('div', { class: 'part part-split', dataset: { part: index } }, tabs, split);
    }

    return h('div', { class: 'part part-single', dataset: { part: index } },
      h('section', { class: 'pane pane-single' }, h('div', { class: 'single-inner' }, header, questions))
    );
  }

  mobileTab(split, which, button) {
    split.dataset.mobile = which;
    button.parentElement.querySelectorAll('.mt').forEach((b) => toggleClass(b, 'active', b === button));
  }

  applySplit(split) {
    const ratio = Math.min(0.75, Math.max(0.25, Number(this.prefs.split) || 0.5));
    split.style.setProperty('--split', `${ratio * 100}%`);
  }

  makeResizable(split, divider) {
    let dragging = false;
    const move = (clientX) => {
      const rect = split.getBoundingClientRect();
      const ratio = Math.min(0.75, Math.max(0.25, (clientX - rect.left) / rect.width));
      this.prefs.split = ratio;
      this.partEls.forEach((p) => p.querySelector('.split')?.style.setProperty('--split', `${ratio * 100}%`));
    };
    divider.addEventListener('pointerdown', (e) => {
      dragging = true;
      divider.setPointerCapture(e.pointerId);
      split.classList.add('resizing');
    });
    divider.addEventListener('pointermove', (e) => dragging && move(e.clientX));
    divider.addEventListener('pointerup', () => {
      dragging = false;
      split.classList.remove('resizing');
      savePrefs(this.prefs);
    });
    divider.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      e.preventDefault();
      const rect = split.getBoundingClientRect();
      const current = Number(this.prefs.split) || 0.5;
      move(rect.left + rect.width * (current + (e.key === 'ArrowLeft' ? -0.05 : 0.05)));
      savePrefs(this.prefs);
    });
  }

  showPart(index, focusFirst) {
    if (index === this.currentPart || !this.partEls[index]) {
      if (focusFirst) this.focusFirstIn(index);
      return;
    }
    if (this.currentPart >= 0) this.partEls[this.currentPart].hidden = true;
    this.partEls[index].hidden = false;
    this.currentPart = index;
    this.nav.setPart(index);
    if (focusFirst) this.focusFirstIn(index);
  }

  focusFirstIn(index) {
    const n = Array.from(this.partOf.entries()).find(([, p]) => p === index)?.[0];
    if (n !== undefined) this.goTo(n);
  }

  goTo(n) {
    const part = this.partOf.get(n);
    if (part === undefined) return;
    this.showPart(part, false);
    const item = this.items.get(n);
    this.nav.setCurrent(n);
    if (item) {
      item.el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      if (!this.o.readOnly) item.focus();
      item.el.classList.add('q-pulse');
      setTimeout(() => item.el.classList.remove('q-pulse'), 900);
    }
  }

  answer(n, value) {
    this.answers.set(String(n), value);
    this.nav.setAnswered(n, value.trim() !== '');
    if (this.o.onAnswer) this.o.onAnswer(n, value);
  }

  /** Serverga yuboriladigan javoblar (bo'shlari tashlanadi). */
  values() {
    const out = {};
    for (const [n, v] of this.answers) if (v.trim() !== '') out[n] = v;
    return out;
  }

  loadFlags() {
    if (!this.o.flagsKey) return [];
    try {
      return JSON.parse(localStorage.getItem(this.o.flagsKey) || '[]');
    } catch {
      return [];
    }
  }

  saveFlags(flags) {
    if (!this.o.flagsKey) return;
    try {
      localStorage.setItem(this.o.flagsKey, JSON.stringify(flags));
    } catch {
      /* e'tiborsiz */
    }
  }
}

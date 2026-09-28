// Writing bo'limi: chapda topshiriq, o'ngda yozish maydoni va so'z hisoblagichi.
// Imlo tekshiruvi, avtoto'ldirish va joylashtirish (paste) o'chirilgan; yozish jarayoni
// statistikasi (tugmalar soni, katta matn bo'laklari) ekspertlar uchun saqlanadi.

import { h, setText, toggleClass } from '../../lib/dom.js';
import { renderRich } from '../../lib/markup.js';
import { countWords } from '../../lib/text.js';
import { T } from '../../lib/uz.js';
import { toast } from '../../lib/ui.js';

const BLOCKED_INPUT = new Set(['insertFromPaste', 'insertFromDrop', 'insertFromYank', 'insertReplacementText', 'insertFromPasteAsQuotation', 'insertLink']);
const LARGE_INSERT = 30;

export class WritingSection {
  /**
   * @param {{
   *   content: {parts: Array}, writing: Record<string,string>, shell: import('./shell.js').ExamShell,
   *   onChange?: () => void, report?: (type:string, detail:string) => void, readOnly?: boolean,
   * }} options
   */
  constructor(options) {
    this.o = options;
    this.texts = new Map(Object.entries(options.writing || {}));
    this.stats = {};
    this.tasks = [];
    this.current = -1;
    this.root = h('div', { class: 'wsection' });

    for (const part of options.content.parts || []) {
      for (const task of part.tasks || []) {
        this.tasks.push(this.buildTask(part, task));
      }
    }
    this.tasks.forEach((t) => this.root.append(t.el));
    this.buildNav();
    if (this.tasks.length) this.show(0);
  }

  buildTask(part, task) {
    const text = this.texts.get(task.id) || '';
    const counter = h('span', { class: 'wc-value' }, String(countWords(text)));
    const target = T.wordsTarget(task.min_words, task.max_words);
    const area = h('textarea', {
      class: 'writing-area',
      lang: 'en',
      spellcheck: 'false',
      autocomplete: 'off',
      autocorrect: 'off',
      autocapitalize: 'off',
      'data-gramm': 'false',
      'data-gramm_editor': 'false',
      'data-enable-grammarly': 'false',
      'aria-label': T.task(task.id),
      placeholder: T.writeHere,
      readOnly: this.o.readOnly,
      value: text,
    });
    const stats = (this.stats[task.id] = { keys: 0, blocked: 0, large: 0 });
    let previous = text.length;

    area.addEventListener('beforeinput', (e) => {
      if (BLOCKED_INPUT.has(e.inputType)) {
        e.preventDefault();
        stats.blocked += 1;
        toast(T.pasteBlocked, 'warn', 2500);
        this.o.report && this.o.report('paste_blocked', `Writing ${task.id}: ${e.inputType}`);
      }
    });
    area.addEventListener('keydown', () => {
      stats.keys += 1;
    });
    area.addEventListener('input', (e) => {
      const delta = area.value.length - previous;
      previous = area.value.length;
      if (delta > LARGE_INSERT && e.inputType !== 'insertCompositionText') {
        stats.large += 1;
        this.o.report && this.o.report('large_insert', `Writing ${task.id}: birdaniga ${delta} belgi qo'shildi`);
      }
      this.texts.set(task.id, area.value);
      this.updateCount(entry);
      this.o.onChange && this.o.onChange();
    });

    const left = h('section', { class: 'pane pane-task', 'aria-label': T.task(task.id) },
      h('div', { class: 'part-header' },
        h('h2', { class: 'part-title', text: part.title }),
        part.instructions ? h('div', { class: 'part-instructions rich' }, renderRich(part.instructions)) : null
      ),
      part.context ? h('div', { class: 'task-context rich', lang: 'en' }, renderRich(part.context)) : null,
      h('div', { class: 'task-box' },
        h('h3', { class: 'task-title', text: task.title || T.task(task.id) }),
        h('div', { class: 'task-prompt rich', lang: 'en' }, renderRich(task.prompt))
      )
    );
    const right = h('section', { class: 'pane pane-writing' },
      area,
      h('div', { class: 'word-count' }, h('span', { text: `${T.words}: ` }), counter, target ? h('span', { class: 'wc-target', text: ` (${target})` }) : null)
    );
    const el = h('div', { class: 'wtask split', hidden: true, dataset: { task: task.id } }, left, h('div', { class: 'divider divider-static' }), right);
    const entry = { task, el, area, counter };
    this.updateCount(entry);
    return entry;
  }

  updateCount(entry) {
    const words = countWords(entry.area.value);
    setText(entry.counter, String(words));
    const { min_words: min, max_words: max } = entry.task;
    toggleClass(entry.counter, 'wc-low', min > 0 && words < min);
    toggleClass(entry.counter, 'wc-ok', words >= min && (!max || words <= max) && words > 0);
    toggleClass(entry.counter, 'wc-high', max > 0 && words > max);
    const b = this.navButtons && this.navButtons[this.tasks.indexOf(entry)];
    if (b) toggleClass(b, 'answered', words > 0);
  }

  buildNav() {
    const shell = this.o.shell;
    this.navButtons = this.tasks.map((t, i) =>
      h('button', { type: 'button', class: 'np-q np-task', onclick: () => this.show(i) }, T.task(t.task.id))
    );
    shell.nav.replaceChildren(h('div', { class: 'np-group active' }, h('div', { class: 'np-nums' }, this.navButtons)));
    this.tasks.forEach((t) => this.updateCount(t));
    shell.flagBtn.hidden = true;
    shell.prevBtn.onclick = () => this.show(Math.max(0, this.current - 1));
    shell.nextBtn.onclick = () => this.show(Math.min(this.tasks.length - 1, this.current + 1));
  }

  focusCurrent() {
    if (!this.o.readOnly && this.tasks[this.current]) this.tasks[this.current].area.focus({ preventScroll: true });
  }

  show(index) {
    if (!this.tasks[index]) return;
    if (this.current >= 0) {
      this.tasks[this.current].el.hidden = true;
      this.navButtons[this.current].classList.remove('current');
    }
    this.current = index;
    this.tasks[index].el.hidden = false;
    this.navButtons[index].classList.add('current');
    if (!this.o.readOnly) this.tasks[index].area.focus({ preventScroll: true });
  }

  values() {
    const out = {};
    for (const t of this.tasks) out[t.task.id] = this.texts.get(t.task.id) || '';
    return out;
  }

  counts() {
    let unanswered = 0;
    for (const t of this.tasks) if (countWords(this.texts.get(t.task.id) || '') === 0) unanswered += 1;
    return { unanswered, flagged: 0 };
  }
}

// Imtihon qobig'i: yuqori panel (nomzod, bo'lim, taymer, saqlash holati, sozlamalar)
// va pastki navigator (qismlar, savol raqamlari, belgilash, yakunlash).
// Bir marta quriladi; keyin faqat matn va klasslar yangilanadi.

import { h, icon, setText, toggleClass } from '../../lib/dom.js';
import { formatClock } from '../../lib/text.js';
import { T } from '../../lib/uz.js';

const PREFS_KEY = 'mlm:prefs';

export function loadPrefs() {
  try {
    return { font: 'md', contrast: 'normal', split: 0.5, volume: 0.9, ...JSON.parse(localStorage.getItem(PREFS_KEY) || '{}') };
  } catch {
    return { font: 'md', contrast: 'normal', split: 0.5, volume: 0.9 };
  }
}

export function savePrefs(prefs) {
  try {
    localStorage.setItem(PREFS_KEY, JSON.stringify(prefs));
  } catch {
    /* e'tiborsiz */
  }
}

export function applyPrefs(root, prefs) {
  root.dataset.font = prefs.font;
  root.dataset.contrast = prefs.contrast;
}

export class ExamShell {
  /**
   * @param {{
   *   candidate: string, code: string, sectionName: string, mockTitle: string,
   *   onFinish?: () => void, extraHeader?: Node, showFinish?: boolean,
   * }} options
   */
  constructor(options) {
    this.options = options;
    this.prefs = loadPrefs();
    this.timerEl = h('span', { class: 'timer-value' }, '--:--');
    this.saveEl = h('span', { class: 'save-status', role: 'status', 'aria-live': 'polite' });
    this.body = h('main', { class: 'exam-body' });
    this.nav = h('div', { class: 'nav-parts' });
    this.flagBtn = h('button', { class: 'nav-btn nav-flag', type: 'button', title: T.reviewHint, 'aria-pressed': 'false' }, icon('flag'), h('span', { text: T.review }));
    this.prevBtn = h('button', { class: 'nav-btn', type: 'button', 'aria-label': T.prev }, icon('left'));
    this.nextBtn = h('button', { class: 'nav-btn', type: 'button', 'aria-label': T.next }, icon('right'));
    this.finishBtn = h('button', { class: 'btn btn-finish', type: 'button', onclick: () => options.onFinish && options.onFinish() }, T.finishSection);
    if (options.showFinish === false) this.finishBtn.hidden = true;

    this.settingsPanel = this.buildSettings();

    this.root = h('div', { class: 'exam' },
      h('header', { class: 'exam-header' },
        h('div', { class: 'eh-left' },
          h('div', { class: 'eh-candidate' }, icon('user'), h('span', { text: `${options.candidate}` }), h('span', { class: 'eh-code', text: options.code })),
        ),
        h('div', { class: 'eh-center' },
          h('div', { class: 'eh-section', text: options.sectionName }),
          h('div', { class: 'eh-timer', title: T.timeLeft }, icon('clock'), this.timerEl)
        ),
        h('div', { class: 'eh-right' },
          options.extraHeader || null,
          this.saveEl,
          h('button', { class: 'icon-btn', type: 'button', title: T.settings, 'aria-label': T.settings, onclick: () => this.toggleSettings() }, icon('text')),
          this.settingsPanel
        )
      ),
      this.body,
      h('footer', { class: 'exam-footer' },
        h('div', { class: 'nav-tools' }, this.flagBtn),
        this.nav,
        h('div', { class: 'nav-actions' }, this.prevBtn, this.nextBtn, this.finishBtn)
      )
    );
    applyPrefs(this.root, this.prefs);
    this.setSaveStatus('saved');
  }

  buildSettings() {
    const choice = (name, value, label, group) => h('button', {
      type: 'button',
      class: 'seg-btn',
      'aria-pressed': String(this.prefs[group] === value),
      dataset: { group, value },
      onclick: () => {
        this.prefs[group] = value;
        savePrefs(this.prefs);
        applyPrefs(this.root, this.prefs);
        this.settingsPanel.querySelectorAll(`[data-group="${group}"]`).forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.value === value)));
      },
    }, label);
    return h('div', { class: 'settings-panel', hidden: true },
      h('div', { class: 'settings-row' }, h('span', { text: T.fontSize }),
        h('div', { class: 'seg' }, choice('font', 'sm', 'A−', 'font'), choice('font', 'md', 'A', 'font'), choice('font', 'lg', 'A+', 'font'), choice('font', 'xl', 'A++', 'font'))
      ),
      h('div', { class: 'settings-row' }, h('span', { text: T.contrast }),
        h('div', { class: 'seg' }, choice('contrast', 'normal', T.contrastNormal, 'contrast'), choice('contrast', 'cream', T.contrastCream, 'contrast'), choice('contrast', 'yellow', T.contrastYellow, 'contrast'))
      )
    );
  }

  toggleSettings() {
    this.settingsPanel.hidden = !this.settingsPanel.hidden;
    if (!this.settingsPanel.hidden) {
      const close = (e) => {
        if (!this.settingsPanel.contains(e.target) && !e.target.closest('.icon-btn')) {
          this.settingsPanel.hidden = true;
          document.removeEventListener('mousedown', close, true);
        }
      };
      document.addEventListener('mousedown', close, true);
    }
  }

  setTimer(ms) {
    setText(this.timerEl, formatClock(ms));
    toggleClass(this.timerEl.parentElement, 'timer-warn', ms <= 5 * 60000 && ms > 60000);
    toggleClass(this.timerEl.parentElement, 'timer-danger', ms <= 60000);
  }

  setSaveStatus(status) {
    if (this.saveStatus === status) return;
    this.saveStatus = status;
    this.saveEl.replaceChildren(
      status === 'offline' ? icon('wifiOff') : icon(status === 'saved' ? 'check' : 'cloud'),
      h('span', { class: 'save-text', text: status === 'offline' ? T.offline : status === 'saving' ? T.saving : T.saved })
    );
    this.saveEl.className = `save-status save-${status}`;
  }

  setBody(...nodes) {
    this.body.replaceChildren(...nodes);
  }
}

/**
 * Savollar navigatori: qismlar va raqamlar. Javob berilgan, joriy va belgilangan savollar ajralib turadi.
 */
export class QuestionNav {
  /**
   * @param {ExamShell} shell
   * @param {{parts: Array<{title: string, numbers: number[]}>, onGo: (n:number)=>void, onPart: (index:number)=>void}} options
   */
  constructor(shell, options) {
    this.shell = shell;
    this.options = options;
    this.buttons = new Map();
    this.partButtons = [];
    this.partCounters = [];
    this.flags = new Set();
    this.current = null;
    this.order = options.parts.flatMap((p) => p.numbers);

    const groups = options.parts.map((part, index) => {
      const counter = h('span', { class: 'np-count' });
      this.partCounters.push({ counter, numbers: part.numbers });
      const title = h('button', { class: 'np-title', type: 'button', onclick: () => options.onPart(index) }, h('span', { text: part.title }), counter);
      this.partButtons.push(title);
      const nums = part.numbers.map((n) => {
        const b = h('button', { class: 'np-q', type: 'button', 'aria-label': `${n}-savol`, onclick: () => options.onGo(n) }, String(n));
        this.buttons.set(n, b);
        return b;
      });
      return h('div', { class: 'np-group', dataset: { part: index } }, title, h('div', { class: 'np-nums' }, nums));
    });
    shell.nav.replaceChildren(...groups);

    shell.flagBtn.onclick = () => this.current !== null && this.toggleFlag(this.current);
    shell.prevBtn.onclick = () => this.step(-1);
    shell.nextBtn.onclick = () => this.step(1);
  }

  step(dir) {
    const i = this.order.indexOf(this.current);
    const next = this.order[Math.min(this.order.length - 1, Math.max(0, (i === -1 ? 0 : i + dir)))];
    if (next !== undefined) this.options.onGo(next);
  }

  setAnswered(n, answered) {
    const b = this.buttons.get(n);
    if (b) toggleClass(b, 'answered', answered);
    this.updateCounters();
  }

  updateCounters() {
    for (const { counter, numbers } of this.partCounters) {
      const done = numbers.filter((n) => this.buttons.get(n)?.classList.contains('answered')).length;
      setText(counter, T.answeredOf(done, numbers.length));
    }
  }

  setCurrent(n) {
    if (this.current !== null) this.buttons.get(this.current)?.classList.remove('current');
    this.current = n;
    const b = this.buttons.get(n);
    if (b) {
      b.classList.add('current');
      b.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }
    this.shell.flagBtn.setAttribute('aria-pressed', String(this.flags.has(n)));
  }

  setPart(index) {
    this.partButtons.forEach((b, i) => toggleClass(b.parentElement, 'active', i === index));
  }

  toggleFlag(n) {
    if (this.flags.has(n)) this.flags.delete(n);
    else this.flags.add(n);
    toggleClass(this.buttons.get(n), 'flagged', this.flags.has(n));
    this.shell.flagBtn.setAttribute('aria-pressed', String(this.flags.has(n)));
    if (this.options.onFlags) this.options.onFlags(Array.from(this.flags));
  }

  setFlags(list) {
    for (const n of list) {
      this.flags.add(n);
      toggleClass(this.buttons.get(n), 'flagged', true);
    }
  }

  counts() {
    let unanswered = 0;
    for (const b of this.buttons.values()) if (!b.classList.contains('answered')) unanswered += 1;
    return { unanswered, flagged: this.flags.size };
  }
}

// Savol bloklarini chizish (IELTS CD uslubida). Har savol bir marta yaratiladi;
// javob o'zgarganda sahifa qayta chizilmaydi — faqat tegishli element holati yangilanadi.

import { h } from '../../lib/dom.js';
import { renderRich } from '../../lib/markup.js';

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

/**
 * @typedef {{
 *   section: string,
 *   answers: Map<string,string>,
 *   onAnswer: (n: number, value: string) => void,
 *   register: (n: number, item: {el: HTMLElement, focus: () => void}) => void,
 *   readOnly?: boolean,
 *   key?: Record<string, string>,
 * }} QuestionContext
 */

function keyBadge(ctx, n) {
  if (!ctx.key || ctx.key[String(n)] === undefined) return null;
  return h('span', { class: 'key-badge', title: "To'g'ri javob" }, ctx.key[String(n)] || '—');
}

function questionNumber(n) {
  return h('span', { class: 'q-num', 'aria-hidden': 'true' }, String(n));
}

function radioGroup(ctx, n, options, labelledBy) {
  const name = `q-${ctx.section}-${n}`;
  const current = ctx.answers.get(String(n)) || '';
  const inputs = [];
  const group = h('div', { class: 'q-options', role: 'radiogroup', 'aria-labelledby': labelledBy });
  for (const option of options) {
    const input = h('input', {
      type: 'radio',
      name,
      value: option.value,
      checked: current === option.value,
      disabled: ctx.readOnly,
      onchange: () => ctx.onAnswer(n, option.value),
    });
    inputs.push(input);
    group.append(
      h('label', { class: 'opt' },
        input,
        h('span', { class: 'opt-key' }, option.key),
        option.text !== undefined ? h('span', { class: 'opt-text' }, renderRich(option.text)) : null
      )
    );
  }
  return { group, focus: () => (inputs.find((i) => i.checked) || inputs[0])?.focus() };
}

function renderMcq(block, ctx) {
  const labelId = `ql-${ctx.section}-${block.n}`;
  const options = block.options.map((text, i) => ({ value: LETTERS[i], key: LETTERS[i], text }));
  const { group, focus } = radioGroup(ctx, block.n, options, labelId);
  const el = h('div', { class: 'q q-mcq', id: `q-${ctx.section}-${block.n}`, dataset: { n: block.n } },
    h('div', { class: 'q-head' }, questionNumber(block.n), h('div', { class: 'q-prompt', id: labelId }, renderRich(block.prompt)), keyBadge(ctx, block.n)),
    group
  );
  ctx.register(block.n, { el, focus });
  return el;
}

function renderTfng(block, ctx) {
  const labelId = `ql-${ctx.section}-${block.n}`;
  const labels = block.variant === 'yng' ? ['YES', 'NO', 'NOT GIVEN'] : ['TRUE', 'FALSE', 'NOT GIVEN'];
  const { group, focus } = radioGroup(ctx, block.n, labels.map((l) => ({ value: l, key: l })), labelId);
  group.classList.add('q-options-inline');
  const el = h('div', { class: 'q q-tfng', id: `q-${ctx.section}-${block.n}`, dataset: { n: block.n } },
    h('div', { class: 'q-head' }, questionNumber(block.n), h('div', { class: 'q-prompt', id: labelId }, renderRich(block.prompt)), keyBadge(ctx, block.n)),
    group
  );
  ctx.register(block.n, { el, focus });
  return el;
}

function renderMatch(block, ctx) {
  const optionsBox = h('div', { class: 'match-options' },
    block.options.map((o) => h('div', { class: 'match-option' }, h('span', { class: 'match-key' }, o.key), h('span', { class: 'match-text' }, renderRich(o.text))))
  );
  const items = block.items.map((item) => {
    const select = h('select', {
      class: 'match-select',
      disabled: ctx.readOnly,
      'aria-label': `${item.n}-savol javobi`,
      onchange: (e) => ctx.onAnswer(item.n, e.target.value),
    },
    h('option', { value: '' }, '—'),
    block.options.map((o) => h('option', { value: o.key, selected: ctx.answers.get(String(item.n)) === o.key }, o.key)));
    const el = h('div', { class: 'q q-match-item', id: `q-${ctx.section}-${item.n}`, dataset: { n: item.n } },
      questionNumber(item.n),
      h('div', { class: 'q-prompt' }, renderRich(item.prompt)),
      select,
      keyBadge(ctx, item.n)
    );
    ctx.register(item.n, { el, focus: () => select.focus() });
    return el;
  });
  return h('div', { class: 'q-block q-match' },
    block.title ? h('div', { class: 'block-title' }, renderRich(block.title)) : null,
    optionsBox,
    h('div', { class: 'match-items' }, items)
  );
}

function renderGapText(block, ctx) {
  const gap = (n) => {
    const input = h('input', {
      type: 'text',
      class: 'gap-input',
      value: ctx.answers.get(String(n)) || '',
      disabled: ctx.readOnly,
      autocomplete: 'off',
      autocorrect: 'off',
      autocapitalize: 'off',
      spellcheck: 'false',
      maxlength: '60',
      placeholder: String(n),
      'aria-label': `${n}-savol javobi`,
      'data-gramm': 'false',
      'data-enable-grammarly': 'false',
      oninput: (e) => ctx.onAnswer(n, e.target.value),
    });
    const el = h('span', { class: 'q gap', id: `q-${ctx.section}-${n}`, dataset: { n } },
      h('span', { class: 'gap-num', 'aria-hidden': 'true' }, String(n)),
      input,
      keyBadge(ctx, n)
    );
    ctx.register(n, { el, focus: () => input.focus() });
    return el;
  };
  const limit = block.max_words > 1 ? `NO MORE THAN ${['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE'][block.max_words] || block.max_words} WORDS` : '';
  return h('div', { class: 'q-block q-gaptext' },
    block.title ? h('div', { class: 'block-title' }, renderRich(block.title)) : null,
    limit ? h('div', { class: 'block-hint' }, limit) : null,
    h('div', { class: 'gap-body rich' }, renderRich(block.text, { gap }))
  );
}

/** @param {Array<object>} blocks  @param {QuestionContext} ctx */
export function renderBlocks(blocks, ctx) {
  const fragment = document.createDocumentFragment();
  for (const block of blocks || []) {
    switch (block.type) {
      case 'text':
        fragment.append(h('div', { class: 'q-block q-text rich' }, renderRich(block.text)));
        break;
      case 'mcq':
        fragment.append(renderMcq(block, ctx));
        break;
      case 'tfng':
        fragment.append(renderTfng(block, ctx));
        break;
      case 'match':
        fragment.append(renderMatch(block, ctx));
        break;
      case 'gap_text':
        fragment.append(renderGapText(block, ctx));
        break;
      default:
        break;
    }
  }
  return fragment;
}

/** Blokdagi savol raqamlari (tartib bilan). */
export function blockNumbers(block) {
  switch (block.type) {
    case 'mcq':
    case 'tfng':
      return [block.n];
    case 'match':
      return block.items.map((i) => i.n);
    case 'gap_text':
      return Array.from(String(block.text || '').matchAll(/\[\[\s*(\d{1,3})\s*\]\]/g), (m) => Number(m[1]));
    default:
      return [];
  }
}

export function partNumbers(part) {
  return (part.blocks || []).flatMap(blockNumbers);
}

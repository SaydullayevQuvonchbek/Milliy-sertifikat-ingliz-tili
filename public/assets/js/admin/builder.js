// Mock quruvchi: Listening/Reading qismlari va savol bloklari, Writing topshiriqlari, Speaking savollari.
// Matn maydonlari holatni to'g'ridan-to'g'ri yangilaydi (qayta chizishsiz); faqat tuzilma o'zgarganda
// (blok qo'shish/o'chirish/surish) tegishli karta qayta quriladi.

import { h, icon } from '../lib/dom.js';
import { gapNumbers } from '../lib/markup.js';
import { formatBytes } from '../lib/text.js';
import { confirmDialog } from '../lib/ui.js';
import { blockNumbers } from '../student/exam/questions.js';
import { LETTERS, ROMAN, bindInput, field, numberInput, textArea, textInput } from './common.js';

const BLOCK_LABELS = {
  text: 'Matn (ko\'rsatma yoki sarlavha)',
  mcq: 'Test (A, B, C, D)',
  tfng: 'True / False / Not Given',
  match: 'Moslashtirish',
  gap_text: "Bo'sh joy to'ldirish",
};

export function sectionNumbers(section) {
  return (section.parts || []).flatMap((p) => (p.blocks || []).flatMap(blockNumbers));
}

function nextNumber(section) {
  const numbers = sectionNumbers(section);
  return numbers.length ? Math.max(...numbers) + 1 : 1;
}

/** Savollarni qismlar bo'ylab 1 dan boshlab qayta raqamlash (gap-fill belgilari va javoblari bilan). */
export function renumber(section) {
  let n = 1;
  for (const part of section.parts || []) {
    for (const block of part.blocks || []) {
      if (block.type === 'mcq' || block.type === 'tfng') block.n = n++;
      else if (block.type === 'match') block.items.forEach((item) => (item.n = n++));
      else if (block.type === 'gap_text') {
        const answers = {};
        block.text = String(block.text || '').replace(/\[\[\s*(\d{1,3})\s*\]\]/g, (_, old) => {
          const fresh = n++;
          answers[String(fresh)] = (block.answers || {})[String(old)] ?? '';
          return `[[${fresh}]]`;
        });
        block.answers = answers;
      }
    }
  }
}

function newBlock(type, section) {
  const n = nextNumber(section);
  switch (type) {
    case 'text':
      return { type, text: '' };
    case 'mcq':
      return { type, n, prompt: '', options: ['', '', ''], answer: '' };
    case 'tfng':
      return { type, n, prompt: '', answer: '' };
    case 'match':
      return { type, title: '', options: LETTERS.slice(0, 4).map((key) => ({ key, text: '' })), items: [{ n, prompt: '', answer: '' }] };
    case 'gap_text':
      return { type, title: '', max_words: 1, text: `… [[${n}]] …`, answers: { [String(n)]: '' } };
    default:
      return null;
  }
}

function move(list, index, delta) {
  const target = index + delta;
  if (target < 0 || target >= list.length) return false;
  [list[index], list[target]] = [list[target], list[index]];
  return true;
}

function toolButton(title, iconName, onclick, extra = '') {
  return h('button', { type: 'button', class: `tool-btn ${extra}`, title, 'aria-label': title, onclick }, iconName.length <= 2 ? iconName : icon(iconName));
}

// ---------------------------------------------------------------------
// Listening / Reading
// ---------------------------------------------------------------------

export class SectionBuilder {
  /**
   * @param {{code: 'L'|'R', section: object, assets: () => Array, onChange: () => void, onUploadRequest: () => void}} options
   */
  constructor(options) {
    this.o = options;
    this.root = h('div', { class: 'builder' });
    this.render();
  }

  changed() {
    this.o.onChange();
  }

  render() {
    const s = this.o.section;
    const isL = this.o.code === 'L';
    const parts = h('div', { class: 'parts' });
    s.parts.forEach((part, i) => parts.append(this.partCard(part, i)));
    const numbers = sectionNumbers(s);
    this.root.replaceChildren(
      h('div', { class: 'builder-toolbar' },
        h('span', { class: 'muted', text: `${s.parts.length} ta qism · ${numbers.length} ta savol` }),
        isL ? h('label', { class: 'inline-field' }, h('span', { text: 'Javoblarni tekshirish vaqti (soniya)' }), numberInput(s, 'review_sec', { min: 0, max: 900, onChange: () => this.changed() })) : null,
        h('span', { class: 'spacer' }),
        h('button', {
          type: 'button', class: 'btn btn-sm',
          title: 'Barcha savollarni 1 dan boshlab ketma-ket raqamlash',
          onclick: () => {
            renumber(s);
            this.changed();
            this.render();
          },
        }, 'Raqamlarni tartiblash'),
        h('button', {
          type: 'button', class: 'btn btn-sm btn-primary',
          onclick: () => {
            s.parts.push(isL
              ? { title: `Part ${s.parts.length + 1}`, instructions: '', preview_sec: 30, gap_sec: 5, plays: 2, tracks: [], blocks: [] }
              : { title: `Part ${s.parts.length + 1}`, instructions: '', passage: { title: '', text: '' }, blocks: [] });
            this.changed();
            this.render();
          },
        }, '+ Qism qo\'shish')
      ),
      parts
    );
  }

  partCard(part, index) {
    const s = this.o.section;
    const isL = this.o.code === 'L';
    const card = h('section', { class: 'part-card' });
    const rerender = () => card.replaceWith(this.partCard(part, index));
    const numbers = (part.blocks || []).flatMap(blockNumbers);
    const range = numbers.length ? `${Math.min(...numbers)}–${Math.max(...numbers)}` : 'savol yo\'q';

    const head = h('div', { class: 'part-card-head' },
      textInput(part, 'title', { className: 'input input-title', onChange: () => this.changed() }),
      h('span', { class: 'chip', text: `Savollar: ${range}` }),
      h('span', { class: 'spacer' }),
      toolButton('Yuqoriga', '↑', () => move(s.parts, index, -1) && (this.changed(), this.render())),
      toolButton('Pastga', '↓', () => move(s.parts, index, 1) && (this.changed(), this.render())),
      toolButton("Qismni o'chirish", '✕', async () => {
        if (await confirmDialog("Qismni o'chirasizmi?", `"${part.title}" va undagi barcha savollar o'chiriladi.`, "O'chirish", 'danger')) {
          s.parts.splice(index, 1);
          this.changed();
          this.render();
        }
      }, 'danger')
    );

    const body = h('div', { class: 'part-card-body' },
      field('Ko\'rsatma (inglizcha)', textArea(part, 'instructions', { rows: 2, onChange: () => this.changed() }), '**qalin**, *kursiv*; yangi qator — Enter.')
    );

    if (isL) {
      body.append(
        h('div', { class: 'row' },
          field('Savollarni ko\'rish vaqti (s)', numberInput(part, 'preview_sec', { min: 0, max: 300, onChange: () => this.changed() })),
          field('Eshittirishlar orasidagi pauza (s)', numberInput(part, 'gap_sec', { min: 0, max: 120, onChange: () => this.changed() })),
          field('Necha marta eshittiriladi', numberInput(part, 'plays', { min: 1, max: 3, onChange: () => this.changed() }))
        ),
        this.tracksEditor(part, rerender)
      );
    } else {
      body.append(this.passageEditor(part, rerender));
    }

    const blocks = h('div', { class: 'blocks' });
    part.blocks.forEach((block, bi) => blocks.append(this.blockCard(part, block, bi, rerender)));
    const add = h('div', { class: 'add-block' },
      h('span', { class: 'muted small', text: 'Blok qo\'shish:' }),
      Object.entries(BLOCK_LABELS).map(([type, label]) => h('button', {
        type: 'button', class: 'btn btn-sm',
        onclick: () => {
          part.blocks.push(newBlock(type, s));
          this.changed();
          rerender();
        },
      }, label))
    );
    body.append(h('h4', { class: 'sub-title', text: 'Savollar' }), blocks, add);
    card.append(head, body);
    return card;
  }

  passageEditor(part, rerender) {
    if (!part.passage) {
      return h('div', { class: 'passage-empty' },
        h('span', { class: 'muted small', text: "Bu qismda alohida matn yo'q (matn savollar ichida, masalan, bo'sh joy to'ldirish)." }),
        h('button', { type: 'button', class: 'btn btn-sm', onclick: () => { part.passage = { title: '', text: '' }; this.changed(); rerender(); } }, "+ Matn qo'shish")
      );
    }
    return h('div', { class: 'passage-editor' },
      h('div', { class: 'row' },
        field('Matn sarlavhasi', textInput(part.passage, 'title', { onChange: () => this.changed() })),
        h('button', { type: 'button', class: 'btn btn-sm btn-ghost', onclick: () => { part.passage = null; this.changed(); rerender(); } }, 'Matnni olib tashlash')
      ),
      field('Matn (passage)', textArea(part.passage, 'text', { rows: 10, onChange: () => this.changed() }),
        'Xatboshilar orasida bo\'sh qator qoldiring. Xatboshi belgisi: [A] yoki [7] bilan boshlang. **qalin**, *kursiv*, ## sarlavha.')
    );
  }

  tracksEditor(part, rerender) {
    const audio = this.o.assets().filter((a) => a.kind === 'audio');
    const rows = part.tracks.map((track, ti) => {
      const select = h('select', { class: 'input' },
        h('option', { value: '' }, '— audio tanlang —'),
        audio.map((a) => h('option', { value: a.id, selected: Number(track.asset) === Number(a.id) }, `#${a.id} ${a.original_name || ''} (${a.duration ? Math.round(a.duration) + ' s' : '?'})`))
      );
      const duration = numberInput(track, 'duration', { min: 0, max: 3600, step: 0.1, onChange: () => this.changed(), width: '90px' });
      select.addEventListener('change', () => {
        track.asset = select.value ? Number(select.value) : null;
        const asset = audio.find((a) => Number(a.id) === track.asset);
        if (asset && asset.duration) {
          track.duration = Number(asset.duration);
          duration.value = String(track.duration);
        }
        this.changed();
      });
      const transcript = textArea(track, 'transcript', { rows: 2, placeholder: 'Transkript (faqat admin va ekspertlar uchun)', onChange: () => this.changed() });
      return h('div', { class: 'track-row' },
        h('span', { class: 'track-no', text: String(ti + 1) }),
        h('div', { class: 'track-main' },
          h('div', { class: 'row' }, select, h('label', { class: 'inline-field' }, h('span', { text: 'Davomiylik (s)' }), duration), textInput(track, 'label', { placeholder: 'Nomi (masalan, Conversation 1)', onChange: () => this.changed() })),
          transcript
        ),
        h('div', { class: 'track-tools' },
          toolButton('Yuqoriga', '↑', () => move(part.tracks, ti, -1) && (this.changed(), rerender())),
          toolButton('Pastga', '↓', () => move(part.tracks, ti, 1) && (this.changed(), rerender())),
          toolButton("O'chirish", '✕', () => { part.tracks.splice(ti, 1); this.changed(); rerender(); }, 'danger')
        )
      );
    });
    return h('div', { class: 'tracks' },
      h('h4', { class: 'sub-title', text: 'Audio yozuvlar (ijro tartibida)' }),
      rows.length ? rows : h('p', { class: 'muted small', text: "Audio qo'shilmagan." }),
      h('div', { class: 'row' },
        h('button', { type: 'button', class: 'btn btn-sm', onclick: () => { part.tracks.push({ asset: null, duration: 0, label: '', transcript: '' }); this.changed(); rerender(); } }, "+ Audio qo'shish"),
        h('button', { type: 'button', class: 'btn btn-sm btn-ghost', onclick: () => this.o.onUploadRequest() }, 'Yangi fayl yuklash')
      )
    );
  }

  blockCard(part, block, index, rerenderPart) {
    const card = h('div', { class: `block-card block-${block.type}` });
    const rerender = () => card.replaceWith(this.blockCard(part, block, index, rerenderPart));
    const numbers = blockNumbers(block);
    card.append(h('div', { class: 'block-head' },
      h('strong', { text: BLOCK_LABELS[block.type] || block.type }),
      numbers.length ? h('span', { class: 'chip', text: numbers.length > 1 ? `${Math.min(...numbers)}–${Math.max(...numbers)}` : `№ ${numbers[0]}` }) : null,
      h('span', { class: 'spacer' }),
      toolButton('Yuqoriga', '↑', () => move(part.blocks, index, -1) && (this.changed(), rerenderPart())),
      toolButton('Pastga', '↓', () => move(part.blocks, index, 1) && (this.changed(), rerenderPart())),
      toolButton("Blokni o'chirish", '✕', async () => {
        if (await confirmDialog("Blokni o'chirasizmi?", 'Blok va undagi savollar o\'chiriladi.', "O'chirish", 'danger')) {
          part.blocks.splice(index, 1);
          this.changed();
          rerenderPart();
        }
      }, 'danger')
    ));
    const body = h('div', { class: 'block-body' });
    card.append(body);
    const changed = () => this.changed();

    switch (block.type) {
      case 'text':
        body.append(textArea(block, 'text', { rows: 2, onChange: changed }));
        break;

      case 'mcq': {
        const letters = LETTERS;
        const group = `mcq-${Math.random().toString(36).slice(2)}`;
        const optionRows = h('div', { class: 'options-editor' });
        const drawOptions = () => {
          optionRows.replaceChildren(...block.options.map((_, oi) => {
            const input = h('input', { class: 'input', type: 'text', lang: 'en', spellcheck: 'true', value: block.options[oi], placeholder: `${letters[oi]} varianti` });
            input.addEventListener('input', () => { block.options[oi] = input.value; changed(); });
            const radio = h('input', { type: 'radio', name: group, checked: block.answer === letters[oi], title: "To'g'ri javob" });
            radio.addEventListener('change', () => { block.answer = letters[oi]; changed(); });
            return h('div', { class: 'option-row' },
              h('label', { class: 'correct-pick', title: "To'g'ri javob" }, radio, h('span', { class: 'opt-letter', text: letters[oi] })),
              input,
              toolButton("Variantni o'chirish", '✕', () => {
                block.options.splice(oi, 1);
                if (block.answer && letters.indexOf(block.answer) >= block.options.length) block.answer = '';
                changed();
                drawOptions();
              }, 'danger')
            );
          }));
        };
        drawOptions();
        body.append(
          h('div', { class: 'row' },
            field('Raqam', numberInput(block, 'n', { min: 1, max: 99, onChange: changed, width: '80px' })),
            h('div', { class: 'grow' }, field('Savol', textInput(block, 'prompt', { onChange: changed })))
          ),
          h('div', { class: 'field-label', text: "Variantlar (doiracha — to'g'ri javob)" }),
          optionRows,
          h('button', { type: 'button', class: 'btn btn-sm', onclick: () => { if (block.options.length < 6) { block.options.push(''); changed(); drawOptions(); } } }, "+ Variant")
        );
        break;
      }

      case 'tfng': {
        const labels = block.variant === 'yng' ? ['YES', 'NO', 'NOT GIVEN'] : ['TRUE', 'FALSE', 'NOT GIVEN'];
        const picks = h('div', { class: 'seg seg-inline' }, labels.map((l) => h('button', {
          type: 'button', class: 'seg-btn', 'aria-pressed': String(block.answer === l),
          onclick: (e) => {
            block.answer = l;
            picks.querySelectorAll('.seg-btn').forEach((b) => b.setAttribute('aria-pressed', String(b === e.currentTarget)));
            changed();
          },
        }, l)));
        const variant = h('select', { class: 'input' }, h('option', { value: 'tfng', selected: block.variant !== 'yng' }, 'TRUE / FALSE / NOT GIVEN'), h('option', { value: 'yng', selected: block.variant === 'yng' }, 'YES / NO / NOT GIVEN'));
        variant.addEventListener('change', () => { block.variant = variant.value; block.answer = ''; changed(); rerender(); });
        body.append(
          h('div', { class: 'row' },
            field('Raqam', numberInput(block, 'n', { min: 1, max: 99, onChange: changed, width: '80px' })),
            h('div', { class: 'grow' }, field('Tasdiq (statement)', textInput(block, 'prompt', { onChange: changed })))
          ),
          h('div', { class: 'row' }, field("To'g'ri javob", picks), field('Turi', variant))
        );
        break;
      }

      case 'match': {
        const keysStyle = block.options.length && ROMAN.includes(block.options[0].key) && block.options[0].key === 'I' ? 'roman' : 'letters';
        const relabel = (style) => {
          const source = style === 'roman' ? ROMAN : LETTERS;
          const map = {};
          block.options.forEach((o, i) => { map[o.key] = source[i]; o.key = source[i]; });
          block.items.forEach((it) => { it.answer = map[it.answer] || ''; });
        };
        const styleSelect = h('select', { class: 'input' },
          h('option', { value: 'letters', selected: keysStyle === 'letters' }, 'A, B, C …'),
          h('option', { value: 'roman', selected: keysStyle === 'roman' }, 'I, II, III … (sarlavhalar)'));
        styleSelect.addEventListener('change', () => { relabel(styleSelect.value); changed(); rerender(); });

        const options = h('div', { class: 'options-editor' }, block.options.map((opt, oi) => h('div', { class: 'option-row' },
          h('span', { class: 'opt-letter wide', text: opt.key }),
          textInput(opt, 'text', { placeholder: 'Variant matni', onChange: changed }),
          toolButton("O'chirish", '✕', () => {
            block.options.splice(oi, 1);
            relabel(styleSelect.value);
            changed();
            rerender();
          }, 'danger')
        )));
        const items = h('div', { class: 'items-editor' }, block.items.map((item, ii) => {
          const answer = h('select', { class: 'input input-answer', title: "To'g'ri javob" },
            h('option', { value: '' }, '—'),
            block.options.map((o) => h('option', { value: o.key, selected: item.answer === o.key }, o.key)));
          answer.addEventListener('change', () => { item.answer = answer.value; changed(); });
          return h('div', { class: 'item-row' },
            numberInput(item, 'n', { min: 1, max: 99, onChange: changed, width: '72px' }),
            textInput(item, 'prompt', { placeholder: 'Savol (masalan, Speaker 1 yoki Paragraph A)', onChange: changed }),
            answer,
            toolButton("O'chirish", '✕', () => { block.items.splice(ii, 1); changed(); rerender(); }, 'danger')
          );
        }));
        body.append(
          h('div', { class: 'row' },
            h('div', { class: 'grow' }, field('Sarlavha (ixtiyoriy)', textInput(block, 'title', { onChange: changed }))),
            field('Variant belgilari', styleSelect)
          ),
          h('div', { class: 'field-label', text: 'Variantlar' }),
          options,
          h('button', {
            type: 'button', class: 'btn btn-sm',
            onclick: () => {
              const source = styleSelect.value === 'roman' ? ROMAN : LETTERS;
              if (block.options.length < source.length) {
                block.options.push({ key: source[block.options.length], text: '' });
                changed();
                rerender();
              }
            },
          }, '+ Variant'),
          h('div', { class: 'field-label', text: "Savollar (raqam · matn · to'g'ri javob)" }),
          items,
          h('button', {
            type: 'button', class: 'btn btn-sm',
            onclick: () => {
              const last = block.items.length ? Math.max(...block.items.map((i) => Number(i.n) || 0)) : nextNumber(this.o.section) - 1;
              block.items.push({ n: last + 1, prompt: '', answer: '' });
              changed();
              rerender();
            },
          }, '+ Savol')
        );
        break;
      }

      case 'gap_text': {
        block.answers = block.answers || {};
        const answersBox = h('div', { class: 'gap-answers' });
        const drawAnswers = () => {
          const nums = gapNumbers(block.text);
          answersBox.replaceChildren(
            nums.length
              ? h('div', { class: 'gap-answer-grid' }, nums.map((n) => {
                const input = h('input', { class: 'input', type: 'text', lang: 'en', spellcheck: 'true', value: block.answers[String(n)] || '', placeholder: 'javob|muqobil' });
                input.addEventListener('input', () => { block.answers[String(n)] = input.value; changed(); });
                return h('label', { class: 'gap-answer' }, h('span', { class: 'q-num', text: String(n) }), input);
              }))
              : h('p', { class: 'muted small', text: "Matnda [[raqam]] belgisi yo'q." })
          );
        };
        let timer = null;
        const text = textArea(block, 'text', { rows: 5, onChange: () => { changed(); clearTimeout(timer); timer = setTimeout(drawAnswers, 400); } });
        const maxWords = h('select', { class: 'input' }, [1, 2, 3].map((w) => h('option', { value: w, selected: Number(block.max_words || 1) === w }, w === 1 ? 'ONE WORD' : w === 2 ? 'TWO WORDS' : 'THREE WORDS')));
        maxWords.addEventListener('change', () => { block.max_words = Number(maxWords.value); changed(); });
        const insertGap = h('button', {
          type: 'button', class: 'btn btn-sm',
          onclick: () => {
            const n = nextNumber(this.o.section);
            const token = `[[${n}]]`;
            const start = text.selectionStart ?? text.value.length;
            const end = text.selectionEnd ?? start;
            text.value = text.value.slice(0, start) + token + text.value.slice(end);
            block.text = text.value;
            block.answers[String(n)] = block.answers[String(n)] || '';
            text.focus();
            text.selectionStart = text.selectionEnd = start + token.length;
            changed();
            drawAnswers();
          },
        }, "+ Bo'sh joy [[n]]");
        drawAnswers();
        body.append(
          h('div', { class: 'row' },
            h('div', { class: 'grow' }, field('Sarlavha (ixtiyoriy)', textInput(block, 'title', { onChange: changed }))),
            field("So'z chegarasi", maxWords)
          ),
          field('Matn', text, "Bo'sh joy: [[9]] ko'rinishida. Ro'yxat uchun qatorni \"- \" bilan boshlang."),
          insertGap,
          h('div', { class: 'field-label', text: "To'g'ri javoblar (muqobillarni | bilan ajrating: colour|color, 12|twelve)" }),
          answersBox
        );
        break;
      }
      default:
        body.append(h('p', { class: 'muted', text: "Noma'lum blok turi." }));
    }
    return card;
  }
}

// ---------------------------------------------------------------------
// Writing
// ---------------------------------------------------------------------

export function writingEditor(section, onChange) {
  const root = h('div', { class: 'builder' });
  const render = () => {
    root.replaceChildren(
      h('div', { class: 'builder-toolbar' },
        h('span', { class: 'muted', text: `${section.parts.length} ta qism · ${section.parts.reduce((s, p) => s + p.tasks.length, 0)} ta topshiriq` }),
        h('span', { class: 'spacer' }),
        h('button', { type: 'button', class: 'btn btn-sm btn-primary', onclick: () => { section.parts.push({ title: `Part ${section.parts.length + 1}`, instructions: '', context: '', tasks: [] }); onChange(); render(); } }, "+ Qism qo'shish")
      ),
      ...section.parts.map((part, pi) => h('section', { class: 'part-card' },
        h('div', { class: 'part-card-head' },
          textInput(part, 'title', { className: 'input input-title', onChange }),
          h('span', { class: 'spacer' }),
          toolButton("Qismni o'chirish", '✕', async () => {
            if (await confirmDialog("Qismni o'chirasizmi?", 'Qism va undagi topshiriqlar o\'chiriladi.', "O'chirish", 'danger')) {
              section.parts.splice(pi, 1);
              onChange();
              render();
            }
          }, 'danger')
        ),
        h('div', { class: 'part-card-body' },
          field('Ko\'rsatma (inglizcha)', textArea(part, 'instructions', { rows: 2, onChange })),
          field('Kirish matni (masalan, elektron xat) — qismdagi barcha topshiriqlar uchun', textArea(part, 'context', { rows: 6, onChange }), '*kursiv* bilan xat ko\'rinishini berish mumkin.'),
          ...part.tasks.map((task, ti) => h('div', { class: 'block-card' },
            h('div', { class: 'block-head' },
              h('strong', { text: `Topshiriq ${task.id || ''}` }),
              h('span', { class: 'spacer' }),
              toolButton("O'chirish", '✕', () => { part.tasks.splice(ti, 1); onChange(); render(); }, 'danger')
            ),
            h('div', { class: 'block-body' },
              h('div', { class: 'row' },
                field('Raqami', textInput(task, 'id', { placeholder: '1.1', onChange, spellcheck: false })),
                h('div', { class: 'grow' }, field('Sarlavha', textInput(task, 'title', { onChange }))),
                field("Kamida (so'z)", numberInput(task, 'min_words', { min: 0, max: 1000, onChange, width: '100px' })),
                field("Ko'pi bilan (so'z)", numberInput(task, 'max_words', { min: 0, max: 2000, onChange, width: '100px' }))
              ),
              field('Topshiriq matni', textArea(task, 'prompt', { rows: 4, onChange }))
            )
          )),
          h('button', { type: 'button', class: 'btn btn-sm', onclick: () => { part.tasks.push({ id: '', title: '', prompt: '', min_words: 0, max_words: 0 }); onChange(); render(); } }, "+ Topshiriq")
        )
      ))
    );
  };
  render();
  return root;
}

// ---------------------------------------------------------------------
// Speaking
// ---------------------------------------------------------------------

export function speakingEditor(section, assets, onChange) {
  const root = h('div', { class: 'builder' });
  const render = () => {
    const images = assets().filter((a) => a.kind === 'image');
    const audio = assets().filter((a) => a.kind === 'audio');
    let total = 0;
    root.replaceChildren(
      h('div', { class: 'builder-toolbar' },
        h('span', { class: 'muted', text: `${section.parts.length} ta qism · ${section.parts.reduce((s, p) => s + p.questions.length, 0)} ta savol` }),
        h('span', { class: 'spacer' }),
        h('button', { type: 'button', class: 'btn btn-sm btn-primary', onclick: () => { section.parts.push({ id: '', title: '', instructions: '', images: [], questions: [] }); onChange(); render(); } }, "+ Qism qo'shish")
      ),
      ...section.parts.map((part, pi) => {
        part.images = part.images || [];
        const imagePicker = h('div', { class: 'image-picker' },
          images.length ? images.map((img) => {
            const checked = part.images.map(Number).includes(Number(img.id));
            const box = h('input', { type: 'checkbox', checked });
            box.addEventListener('change', () => {
              part.images = box.checked ? [...part.images, img.id] : part.images.filter((i) => Number(i) !== Number(img.id));
              onChange();
            });
            return h('label', { class: 'image-pick' }, box, h('img', { src: `../api/admin/assets/${img.id}`, alt: img.original_name || '' }), h('span', { class: 'small', text: `#${img.id}` }));
          }) : h('p', { class: 'muted small', text: "Rasm yuklanmagan. \"Fayllar\" bo'limida yuklang." })
        );
        const hasLists = part.id === '3' || (part.for && part.for.length) || (part.against && part.against.length);
        const listArea = (key) => {
          const area = h('textarea', { class: 'input', rows: 3, lang: 'en', spellcheck: 'true', value: (part[key] || []).join('\n') });
          area.addEventListener('input', () => { part[key] = area.value.split('\n').map((l) => l.trim()).filter(Boolean); onChange(); });
          return area;
        };
        return h('section', { class: 'part-card' },
          h('div', { class: 'part-card-head' },
            textInput(part, 'id', { className: 'input input-id', placeholder: '1.1', onChange, spellcheck: false }),
            textInput(part, 'title', { className: 'input input-title', placeholder: 'Part 1.1', onChange }),
            h('span', { class: 'spacer' }),
            toolButton("Qismni o'chirish", '✕', async () => {
              if (await confirmDialog("Qismni o'chirasizmi?", 'Qism va undagi savollar o\'chiriladi.', "O'chirish", 'danger')) {
                section.parts.splice(pi, 1);
                onChange();
                render();
              }
            }, 'danger')
          ),
          h('div', { class: 'part-card-body' },
            field('Ko\'rsatma (inglizcha)', textArea(part, 'instructions', { rows: 2, onChange })),
            h('div', { class: 'field-label', text: 'Rasmlar (1.2 — ikkita, 2 — bitta)' }),
            imagePicker,
            hasLists ? field('Mavzu (statement)', textArea(part, 'topic', { rows: 2, onChange })) : null,
            hasLists ? h('div', { class: 'row' }, h('div', { class: 'grow' }, field('FOR (har qatorda bittadan)', listArea('for'))), h('div', { class: 'grow' }, field('AGAINST (har qatorda bittadan)', listArea('against')))) : null,
            ...part.questions.map((q, qi) => {
              total += 1;
              const audioSelect = h('select', { class: 'input' }, h('option', { value: '' }, "— savol audiosi yo'q —"), audio.map((a) => h('option', { value: a.id, selected: Number(q.audio) === Number(a.id) }, `#${a.id} ${a.original_name || ''}`)));
              audioSelect.addEventListener('change', () => {
                q.audio = audioSelect.value ? Number(audioSelect.value) : null;
                const asset = audio.find((a) => Number(a.id) === q.audio);
                q.audio_duration = asset && asset.duration ? Number(asset.duration) : 0;
                onChange();
              });
              return h('div', { class: 'block-card' },
                h('div', { class: 'block-head' },
                  h('strong', { text: `Savol ${q.no || total}` }),
                  h('span', { class: 'spacer' }),
                  toolButton("O'chirish", '✕', () => { part.questions.splice(qi, 1); onChange(); render(); }, 'danger')
                ),
                h('div', { class: 'block-body' },
                  h('div', { class: 'row' },
                    field('Raqam', numberInput(q, 'no', { min: 1, max: 50, onChange, width: '80px' })),
                    field('Tayyorlanish (s)', numberInput(q, 'prep_sec', { min: 0, max: 300, onChange, width: '110px' })),
                    field('Javob (s)', numberInput(q, 'answer_sec', { min: 5, max: 600, onChange, width: '110px' })),
                    h('div', { class: 'grow' }, field('Savol audiosi (ixtiyoriy)', audioSelect))
                  ),
                  field('Savol matni', textArea(q, 'text', { rows: 3, onChange }))
                )
              );
            }),
            h('button', {
              type: 'button', class: 'btn btn-sm',
              onclick: () => {
                const all = section.parts.flatMap((p) => p.questions.map((x) => Number(x.no) || 0));
                part.questions.push({ no: (all.length ? Math.max(...all) : 0) + 1, text: '', prep_sec: 0, answer_sec: 30, audio: null });
                onChange();
                render();
              },
            }, '+ Savol')
          )
        );
      })
    );
  };
  render();
  return { root, render };
}

export function assetLabel(asset) {
  return `#${asset.id} · ${asset.original_name || asset.kind} · ${formatBytes(asset.size)}${asset.duration ? ` · ${Math.round(asset.duration)} s` : ''}`;
}

export { bindInput };

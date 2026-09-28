// Mock tahrirlovchi: umumiy sozlamalar, bo'limlar quruvchisi, fayllar, tekshiruv, ko'rish va JSON.

import { ApiError, del, get, post, put, upload } from '../lib/api.js';
import { h, icon, setText } from '../lib/dom.js';
import { renderRich } from '../lib/markup.js';
import { formatBytes } from '../lib/text.js';
import { confirmDialog, modal, spinner, toast } from '../lib/ui.js';
import { ExamShell } from '../student/exam/shell.js';
import { QuestionSection } from '../student/exam/questionSection.js';
import { WritingSection } from '../student/exam/writing.js';
import { SectionBuilder, speakingEditor, writingEditor } from './builder.js';
import {
  MOCK_STATUS, checkbox, copyText, downloadFile, field, fromLocalInput, numberInput, statusBadge, textArea, textInput, toLocalInput,
} from './common.js';
import { listeningTemplate, readingTemplate, speakingTemplate, writingTemplate } from './templates.js';

const TABS = [
  ['general', 'Umumiy'],
  ['L', 'Listening'],
  ['R', 'Reading'],
  ['W', 'Writing'],
  ['S', 'Speaking'],
  ['files', 'Fayllar'],
  ['check', 'Tekshirish'],
  ['preview', "O'quvchi ko'rinishi"],
  ['json', 'JSON'],
];

const SECTION_KEY = { L: 'listening', R: 'reading', W: 'writing', S: 'speaking' };

function emptySource() {
  return { listening: { review_sec: 120, parts: [] }, reading: { parts: [] }, writing: { parts: [] }, speaking: { parts: [] } };
}

function defaultSettings() {
  return {
    sections: ['L', 'R', 'W', 'S'],
    times: { reading: 3600, writing: 3600 },
    break_sec: 60,
    lockdown: { fullscreen: true, max_violations: 3, action: 'terminate', allow_mobile: false, require_seb: false },
    grading: { raters: 2, diff_w: 3, diff_s: 3 },
    speaking: { mode: 'separate', from: null, to: null },
    results: 'publish',
  };
}

/** Manbadan to'g'ri javoblar xaritasi (ko'rish rejimi uchun). */
function keyMap(section) {
  const out = {};
  for (const part of section.parts || []) {
    for (const b of part.blocks || []) {
      if (b.type === 'mcq' || b.type === 'tfng') out[String(b.n)] = b.answer || '';
      if (b.type === 'match') b.items.forEach((i) => (out[String(i.n)] = i.answer || ''));
      if (b.type === 'gap_text') Object.entries(b.answers || {}).forEach(([n, v]) => (out[String(n)] = v));
    }
  }
  return out;
}

function speakingPreviewCard(p, q) {
  const lists = (p.for || []).length || (p.against || []).length
    ? h('div', { class: 'sq-lists' },
      h('div', { class: 'sq-list' }, h('h4', { text: 'FOR' }), h('ul', null, (p.for || []).map((t) => h('li', { text: t })))),
      h('div', { class: 'sq-list' }, h('h4', { text: 'AGAINST' }), h('ul', null, (p.against || []).map((t) => h('li', { text: t })))))
    : null;
  return h('div', { class: 'speaking-card', lang: 'en' },
    h('div', { class: 'sq-head' }, h('span', { text: p.title || `Part ${p.id}` }), h('span', { text: `Savol ${q.no}` })),
    p.instructions ? h('div', { class: 'sq-instructions rich' }, renderRich(p.instructions)) : null,
    p.topic ? h('div', { class: 'sq-topic rich' }, renderRich(p.topic)) : null,
    (p.images || []).length ? h('div', { class: 'sq-images' }, p.images.map((id) => h('img', { class: 'sq-image', src: `../api/admin/assets/${id}`, alt: '' }))) : null,
    lists,
    h('div', { class: 'sq-text rich' }, renderRich(q.text)),
    h('p', { class: 'muted small', text: `Tayyorlanish: ${q.prep_sec} s · Javob: ${q.answer_sec} s` })
  );
}

export class MockEditor {
  constructor(root, id, { navigate }) {
    this.root = root;
    this.id = id;
    this.navigate = navigate;
    this.tab = 'general';
    this.dirty = false;
    this.meta = { status: 'draft', assets: [], in_progress: 0, attempts_total: 0 };
    this.onBeforeUnload = (e) => {
      if (this.dirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    };
    window.addEventListener('beforeunload', this.onBeforeUnload);
  }

  destroy() {
    window.removeEventListener('beforeunload', this.onBeforeUnload);
  }

  async canLeave() {
    if (!this.dirty) return true;
    return confirmDialog('Saqlanmagan o\'zgarishlar bor', "Sahifadan chiqsangiz, o'zgarishlar yo'qoladi. Davom etasizmi?", 'Chiqish', 'danger');
  }

  async load() {
    this.root.replaceChildren(spinner());
    if (this.id) {
      const { mock } = await get(`admin/mocks/${this.id}`);
      this.applyMock(mock);
    } else {
      this.state = {
        title: '', description: '', max_attempts: 2, available_from: null, available_to: null,
        source: emptySource(), settings: defaultSettings(),
      };
    }
    this.render();
  }

  applyMock(mock) {
    const source = { ...emptySource(), ...(mock.source || {}) };
    for (const key of ['listening', 'reading', 'writing', 'speaking']) {
      source[key] = source[key] && Array.isArray(source[key].parts) ? source[key] : { ...emptySource()[key] };
    }
    this.state = {
      title: mock.title,
      description: mock.description || '',
      max_attempts: mock.max_attempts,
      available_from: mock.available_from,
      available_to: mock.available_to,
      source,
      settings: { ...defaultSettings(), ...mock.settings },
    };
    this.meta = {
      status: mock.status,
      assets: mock.assets || [],
      in_progress: mock.in_progress,
      attempts_total: mock.attempts_total,
      stats: mock.stats || {},
    };
  }

  changed() {
    if (!this.dirty) {
      this.dirty = true;
      this.updateSaveButton();
    }
  }

  updateSaveButton() {
    if (!this.saveBtn) return;
    setText(this.saveBtn, this.dirty ? 'Saqlash *' : 'Saqlangan');
    this.saveBtn.classList.toggle('btn-primary', this.dirty);
  }

  // ------------------------------------------------------------------

  render() {
    this.saveBtn = h('button', { class: 'btn', type: 'button', onclick: () => this.save() });
    this.updateSaveButton();
    const statusActions = [];
    if (this.id) {
      const s = this.meta.status;
      if (s !== 'active') statusActions.push(h('button', { class: 'btn btn-success', type: 'button', onclick: () => this.setStatus('active') }, 'Faollashtirish'));
      if (s === 'active') statusActions.push(h('button', { class: 'btn', type: 'button', onclick: () => this.setStatus('frozen') }, '❄ Muzlatish'));
      if (s !== 'archived') statusActions.push(h('button', { class: 'btn btn-ghost', type: 'button', onclick: () => this.setStatus('archived') }, 'Arxivlash'));
      statusActions.push(h('a', { class: 'btn btn-ghost', href: `#/mocks/${this.id}/results` }, 'Natijalar'));
    }

    this.tabBar = h('nav', { class: 'tabs' }, TABS.map(([key, label]) => h('button', {
      type: 'button',
      class: `tab ${this.tab === key ? 'active' : ''}`,
      onclick: () => this.switchTab(key),
    }, label)));
    this.content = h('div', { class: 'tab-content' });

    this.root.replaceChildren(
      h('div', { class: 'editor-head' },
        h('a', { class: 'back-link', href: '#/mocks' }, icon('left'), 'Mocklar'),
        h('div', { class: 'page-head' },
          h('div', { class: 'title-line' }, h('h1', { text: this.state.title || 'Yangi mock' }), this.id ? statusBadge(this.meta.status, MOCK_STATUS) : null),
          h('div', { class: 'page-actions' }, statusActions, this.saveBtn)
        ),
        this.meta.in_progress > 0
          ? h('div', { class: 'alert alert-warn' }, icon('alert'), `Hozir ${this.meta.in_progress} ta o'quvchi bu mockni ishlayapti. Kontentni o'zgartirish ularning imtihoniga ta'sir qilishi mumkin — kerak bo'lsa, avval muzlating.`)
          : null,
        this.tabBar
      ),
      this.content
    );
    this.renderTab();
  }

  switchTab(key) {
    this.tab = key;
    this.tabBar.querySelectorAll('.tab').forEach((b, i) => b.classList.toggle('active', TABS[i][0] === key));
    this.renderTab();
  }

  renderTab() {
    const c = this.content;
    switch (this.tab) {
      case 'general':
        c.replaceChildren(this.generalTab());
        break;
      case 'L':
      case 'R':
        c.replaceChildren(this.sectionTab(this.tab));
        break;
      case 'W':
        c.replaceChildren(this.templateBar('W'), writingEditor(this.state.source.writing, () => this.changed()));
        break;
      case 'S':
        c.replaceChildren(this.templateBar('S'), speakingEditor(this.state.source.speaking, () => this.meta.assets, () => this.changed()).root);
        break;
      case 'files':
        c.replaceChildren(this.filesTab());
        break;
      case 'check':
        c.replaceChildren(this.checkTab());
        break;
      case 'preview':
        c.replaceChildren(this.previewTab());
        break;
      case 'json':
        c.replaceChildren(this.jsonTab());
        break;
      default:
        c.replaceChildren();
    }
  }

  // ------------------------------------------------------------------
  // Umumiy
  // ------------------------------------------------------------------

  generalTab() {
    const st = this.state;
    const s = st.settings;
    const onChange = () => this.changed();

    const sections = h('div', { class: 'check-row' }, ['L', 'R', 'W', 'S'].map((code) => checkbox(
      { L: 'Listening', R: 'Reading', W: 'Writing', S: 'Speaking' }[code],
      s.sections.includes(code),
      (on) => {
        s.sections = ['L', 'R', 'W', 'S'].filter((c) => (c === code ? on : s.sections.includes(c)));
        onChange();
      }
    )));

    const minutes = (obj, key) => {
      const input = h('input', { class: 'input input-num', type: 'number', min: 1, max: 240, value: Math.round(obj[key] / 60) });
      input.addEventListener('input', () => { obj[key] = Math.max(1, Number(input.value) || 1) * 60; onChange(); });
      return input;
    };
    const dateInput = (obj, key) => {
      const input = h('input', { class: 'input', type: 'datetime-local', value: toLocalInput(obj[key]) });
      input.addEventListener('change', () => { obj[key] = fromLocalInput(input.value); onChange(); });
      return input;
    };
    const select = (obj, key, options, cast = String) => {
      const el = h('select', { class: 'input' }, options.map(([v, label]) => h('option', { value: v, selected: String(obj[key]) === String(v) }, label)));
      el.addEventListener('change', () => { obj[key] = cast(el.value); onChange(); });
      return el;
    };

    return h('div', { class: 'form-grid' },
      h('div', { class: 'card' },
        h('h3', { text: 'Asosiy ma\'lumotlar' }),
        field('Mock nomi', textInput(st, 'title', { lang: 'uz', spellcheck: false, onChange })),
        field('Tavsif (o\'quvchilarga ko\'rinadi)', textArea(st, 'description', { rows: 3, lang: 'uz', spellcheck: false, onChange })),
        h('div', { class: 'row' },
          field('Bitta o\'quvchi necha marta ishlay oladi', select(st, 'max_attempts', [[1, '1 marta'], [2, '2 marta (ko\'pi bilan)']], Number)),
          field('Ochilish vaqti', dateInput(st, 'available_from'), 'Bo\'sh — darhol'),
          field('Yopilish vaqti', dateInput(st, 'available_to'), 'Bo\'sh — muddatsiz')
        ),
        h('div', { class: 'field-label', text: "Bo'limlar" }),
        sections
      ),
      h('div', { class: 'card' },
        h('h3', { text: 'Vaqt' }),
        h('div', { class: 'row' },
          field('Reading (daqiqa)', minutes(s.times, 'reading')),
          field('Writing (daqiqa)', minutes(s.times, 'writing')),
          field("Bo'limlar orasidagi tanaffus (soniya)", numberInput(s, 'break_sec', { min: 10, max: 900, onChange }))
        ),
        h('p', { class: 'muted small', text: "Listening davomiyligi audio yozuvlar, ko'rib chiqish va pauzalardan avtomatik hisoblanadi." })
      ),
      h('div', { class: 'card' },
        h('h3', { text: 'Imtihon xavfsizligi' }),
        checkbox("To'liq ekran rejimi majburiy", s.lockdown.fullscreen, (on) => { s.lockdown.fullscreen = on; onChange(); }),
        h('div', { class: 'row' },
          field('Qoidabuzarliklar chegarasi', numberInput(s.lockdown, 'max_violations', { min: 0, max: 50, onChange }), '0 — chegara yo\'q'),
          field('Chegaradan oshsa', select(s.lockdown, 'action', [['terminate', "Imtihon to'xtatiladi"], ['log', 'Faqat jurnalga yoziladi']]))
        ),
        checkbox('Telefon va planshetdan ishlashga ruxsat', s.lockdown.allow_mobile, (on) => { s.lockdown.allow_mobile = on; onChange(); }),
        checkbox('Faqat Safe Exam Browser orqali (to\'liq qulf)', s.lockdown.require_seb, (on) => { s.lockdown.require_seb = on; onChange(); }),
        h('p', { class: 'muted small', text: "Brauzerda oynadan chiqish, to'liq ekrandan chiqish, sahifani uzoq yopish va ikkinchi oyna qoidabuzarlik hisoblanadi. Nusxa olish, joylashtirish va yorliqlar doim o'chirilgan." })
      ),
      h('div', { class: 'card' },
        h('h3', { text: 'Writing va Speaking tekshiruvi' }),
        h('div', { class: 'row' },
          field('Har ishni nechta ekspert baholaydi', select(s.grading, 'raters', [[2, '2 ta (rasmiyga yaqin)'], [1, '1 ta']], Number)),
          field('Writing: 3-ekspert kerak bo\'ladigan farq', numberInput(s.grading, 'diff_w', { min: 1, max: 16, onChange })),
          field('Speaking: 3-ekspert kerak bo\'ladigan farq', numberInput(s.grading, 'diff_s', { min: 1, max: 21, onChange }))
        ),
        h('div', { class: 'row' },
          field('Speaking qachon', select(s.speaking, 'mode', [['separate', 'Alohida (bosh sahifadan)'], ['same_session', 'Yozma qismdan keyin darhol']])),
          field('Speaking ochiladi', dateInput(s.speaking, 'from'), 'Bo\'sh — yozma qismdan keyin'),
          field('Speaking yopiladi', dateInput(s.speaking, 'to'), 'Bo\'sh — muddatsiz')
        ),
        field('Natijalar o\'quvchiga', select(s, 'results', [['publish', "Administrator e'lon qilgach"], ['instant', 'Tayyor bo\'lishi bilan']]))
      )
    );
  }

  // ------------------------------------------------------------------
  // Bo'limlar
  // ------------------------------------------------------------------

  templateBar(code) {
    const key = SECTION_KEY[code];
    const section = this.state.source[key];
    const make = { L: listeningTemplate, R: readingTemplate, W: writingTemplate, S: speakingTemplate }[code];
    const apply = async () => {
      if (section.parts.length && !(await confirmDialog('Shablonni qo\'llaysizmi?', "Bo'limdagi joriy kontent rasmiy formatdagi bo'sh shablon bilan almashtiriladi.", 'Almashtirish', 'danger'))) return;
      this.state.source[key] = make();
      this.changed();
      this.renderTab();
    };
    if (!section.parts.length) {
      return h('div', { class: 'template-hero card' },
        h('h3', { text: "Bo'lim hali bo'sh" }),
        h('p', { class: 'muted', text: "Rasmiy formatdagi shablon qismlar, savol turlari, raqamlar va standart inglizcha ko'rsatmalar bilan yaratiladi. Siz faqat matn, variantlar va to'g'ri javoblarni kiritasiz." }),
        h('button', { class: 'btn btn-primary', type: 'button', onclick: apply }, 'Rasmiy format shablonini yaratish')
      );
    }
    return h('div', { class: 'template-bar' },
      h('span', { class: 'muted small', text: "Bo'limni noldan boshlash kerakmi?" }),
      h('button', { class: 'btn btn-sm btn-ghost', type: 'button', onclick: apply }, 'Rasmiy shablon bilan almashtirish')
    );
  }

  sectionTab(code) {
    const builder = new SectionBuilder({
      code,
      section: this.state.source[SECTION_KEY[code]],
      assets: () => this.meta.assets,
      onChange: () => this.changed(),
      onUploadRequest: () => this.switchTab('files'),
    });
    return h('div', null, this.templateBar(code), builder.root);
  }

  // ------------------------------------------------------------------
  // Fayllar
  // ------------------------------------------------------------------

  filesTab() {
    if (!this.id) return h('div', { class: 'card' }, h('p', { text: "Fayl yuklash uchun avval mockni saqlang." }), h('button', { class: 'btn btn-primary', type: 'button', onclick: () => this.save() }, 'Saqlash'));
    const list = h('div');
    const progress = h('div', { class: 'upload-list' });
    const input = h('input', { type: 'file', multiple: true, accept: 'audio/*,image/png,image/jpeg,image/webp,image/gif', class: 'file-input' });
    input.addEventListener('change', async () => {
      const files = Array.from(input.files || []);
      input.value = '';
      for (const file of files) await this.uploadFile(file, progress);
      drawList();
    });
    const drawList = () => {
      const used = this.usedAssets();
      list.replaceChildren(this.meta.assets.length ? h('div', { class: 'table-wrap' }, h('table', { class: 'table' },
        h('thead', null, h('tr', null, ['Fayl', 'Turi', 'Hajmi', 'Davomiylik', 'Ishlatilgan', 'Ko\'rish', ''].map((t) => h('th', { text: t })))),
        h('tbody', null, this.meta.assets.map((a) => h('tr', null,
          h('td', null, h('strong', { text: `#${a.id}` }), ' ', a.original_name || ''),
          h('td', { text: a.kind === 'audio' ? 'Audio' : 'Rasm' }),
          h('td', { text: formatBytes(a.size) }),
          h('td', { text: a.duration ? `${Number(a.duration).toFixed(1)} s` : '—' }),
          h('td', { text: used.has(Number(a.id)) ? 'Ha' : 'Yo\'q' }),
          h('td', null, a.kind === 'audio'
            ? h('audio', { controls: true, preload: 'none', src: `../api/admin/assets/${a.id}`, class: 'mini-audio' })
            : h('img', { src: `../api/admin/assets/${a.id}`, class: 'thumb', alt: '' })),
          h('td', null, h('button', {
            class: 'btn btn-sm btn-ghost danger', type: 'button', disabled: used.has(Number(a.id)),
            title: used.has(Number(a.id)) ? 'Fayl mockda ishlatilmoqda' : "O'chirish",
            onclick: async () => {
              if (!(await confirmDialog("Faylni o'chirasizmi?", `#${a.id} ${a.original_name || ''}`, "O'chirish", 'danger'))) return;
              try {
                await del(`admin/assets/${a.id}`);
                this.meta.assets = this.meta.assets.filter((x) => x.id !== a.id);
                drawList();
              } catch (err) {
                toast(err.message, 'error');
              }
            },
          }, "O'chirish"))
        )))
      )) : h('p', { class: 'empty', text: "Hali fayl yuklanmagan." }));
    };
    drawList();
    return h('div', null,
      h('div', { class: 'card upload-card' },
        h('h3', { text: 'Fayl yuklash' }),
        h('p', { class: 'muted small', text: "Audio: MP3, M4A, WAV, OGG (Listening uchun mono 64 kbps tavsiya etiladi — tez yuklanadi). Rasm: PNG, JPG, WEBP. Audio davomiyligi brauzerda avtomatik o'lchanadi." }),
        h('label', { class: 'btn btn-primary file-label' }, 'Fayllarni tanlash', input),
        progress
      ),
      list
    );
  }

  usedAssets() {
    const used = new Set();
    const s = this.state.source;
    (s.listening.parts || []).forEach((p) => (p.tracks || []).forEach((t) => t.asset && used.add(Number(t.asset))));
    (s.speaking.parts || []).forEach((p) => {
      (p.images || []).forEach((i) => used.add(Number(i)));
      (p.questions || []).forEach((q) => q.audio && used.add(Number(q.audio)));
    });
    return used;
  }

  measureDuration(file) {
    return new Promise((resolve) => {
      const audio = new Audio();
      const url = URL.createObjectURL(file);
      const done = (value) => {
        URL.revokeObjectURL(url);
        resolve(value);
      };
      audio.preload = 'metadata';
      audio.onloadedmetadata = () => {
        if (audio.duration === Infinity) {
          audio.currentTime = 1e7;
          audio.ontimeupdate = () => done(audio.duration);
        } else done(audio.duration);
      };
      audio.onerror = () => done(null);
      audio.src = url;
      setTimeout(() => done(null), 15000);
    });
  }

  async uploadFile(file, progressBox) {
    const kind = file.type.startsWith('image/') ? 'image' : 'audio';
    const bar = h('span', { class: 'progress-bar' });
    const row = h('div', { class: 'upload-row' }, h('span', { text: file.name }), h('span', { class: 'progress' }, bar));
    progressBox.append(row);
    const form = new FormData();
    form.append('kind', kind);
    if (kind === 'audio') {
      const duration = await this.measureDuration(file);
      if (duration) form.append('duration', duration.toFixed(3));
    }
    form.append('file', file);
    try {
      const res = await upload(`admin/mocks/${this.id}/assets`, form, (loaded, total) => (bar.style.width = `${Math.round((loaded / total) * 100)}%`));
      this.meta.assets.push(res.asset);
      row.remove();
      toast(`Yuklandi: ${file.name}`, 'success', 2500);
    } catch (err) {
      row.classList.add('bad');
      row.append(h('span', { class: 'small', text: err.message }));
    }
  }

  // ------------------------------------------------------------------
  // Tekshirish
  // ------------------------------------------------------------------

  checkTab() {
    const box = h('div', null, spinner('Tekshirilmoqda…'));
    this.runValidation(box);
    return box;
  }

  async runValidation(box) {
    if (!this.id) {
      box.replaceChildren(h('p', { text: 'Tekshirish uchun avval mockni saqlang.' }));
      return;
    }
    try {
      const { validation } = await post(`admin/mocks/${this.id}/validate`, { source: this.state.source, settings: this.state.settings });
      box.replaceChildren(this.validationView(validation));
    } catch (err) {
      box.replaceChildren(h('p', { class: 'form-error', text: err.message }));
    }
  }

  validationView(v) {
    const names = { L: 'Listening', R: 'Reading', W: 'Writing', S: 'Speaking' };
    const summary = Object.entries(v.summary || {}).map(([code, s]) => h('div', { class: 'stat' },
      h('span', { class: 'stat-label', text: names[code] }),
      h('span', { class: 'stat-value', text: s.questions !== undefined ? `${s.questions} savol` : `${s.tasks} topshiriq` }),
      h('span', { class: 'stat-hint', text: [s.parts ? `${s.parts} qism` : '', s.duration_sec ? `${Math.floor(s.duration_sec / 60)} daq. ${s.duration_sec % 60} s` : ''].filter(Boolean).join(' · ') })
    ));
    const list = (items, kind) => h('ul', { class: `issues issues-${kind}` }, items.map((i) => h('li', null, h('strong', { text: i.where }), ' — ', i.message)));
    return h('div', { class: 'validation' },
      h('div', { class: 'stats' }, summary),
      v.errors.length
        ? h('div', { class: 'card' }, h('h3', { class: 'danger-text', text: `Xatolar: ${v.errors.length}` }), h('p', { class: 'muted small', text: "Xatolar tuzatilmaguncha mockni faollashtirib bo'lmaydi." }), list(v.errors, 'error'))
        : h('div', { class: 'alert alert-ok' }, icon('check'), "Xato yo'q. Mockni faollashtirish mumkin."),
      v.warnings.length
        ? h('div', { class: 'card' }, h('h3', { class: 'warn-text', text: `Ogohlantirishlar: ${v.warnings.length}` }), h('p', { class: 'muted small', text: "Imlo va yozuv xatolari (ortiqcha bo'sh joy, takrorlangan so'z, kirill harfi aralashgan so'z va h.k.) hamda rasmiy formatdan farqlar." }), list(v.warnings, 'warn'))
        : null
    );
  }

  // ------------------------------------------------------------------
  // O'quvchi ko'rinishi
  // ------------------------------------------------------------------

  previewTab() {
    let showKey = true;
    let current = this.previewSection || 'R';
    const frame = h('div', { class: 'preview-frame' });
    const draw = () => {
      this.previewSection = current;
      frame.replaceChildren();
      const src = this.state.source;
      const shell = new ExamShell({ candidate: 'Namuna O\'quvchi', code: 'PREVIEW', sectionName: { L: 'Listening', R: 'Reading', W: 'Writing', S: 'Speaking' }[current], mockTitle: this.state.title });
      shell.setTimer((current === 'W' ? this.state.settings.times.writing : this.state.settings.times.reading) * 1000);
      if (current === 'L' || current === 'R') {
        const section = src[SECTION_KEY[current]];
        if (!section.parts.length) {
          frame.append(h('p', { class: 'empty', text: "Bo'lim bo'sh." }));
          return;
        }
        const view = new QuestionSection({ section: current, content: section, answers: {}, shell, key: showKey ? keyMap(section) : null });
        shell.setBody(view.root);
      } else if (current === 'W') {
        if (!src.writing.parts.length) {
          frame.append(h('p', { class: 'empty', text: "Bo'lim bo'sh." }));
          return;
        }
        const view = new WritingSection({ content: src.writing, writing: {}, shell });
        shell.setBody(view.root);
      } else {
        shell.nav.replaceChildren();
        shell.flagBtn.hidden = true;
        shell.finishBtn.hidden = true;
        const cards = (src.speaking.parts || []).flatMap((p) => p.questions.map((q) => speakingPreviewCard(p, q)));
        shell.setBody(h('div', { class: 'speaking-stage' }, cards));
      }
      frame.append(shell.root);
      if (current === 'L') {
        const tracks = (src.listening.parts || []).flatMap((p, pi) => (p.tracks || []).map((t, ti) => ({ p, pi, t, ti })));
        frame.append(h('div', { class: 'card preview-audio' },
          h('h3', { text: 'Audio yozuvlar (admin uchun — o\'quvchida boshqaruv tugmalari yo\'q)' }),
          tracks.length ? tracks.map(({ p, t, ti }) => h('div', { class: 'row' },
            h('span', { class: 'chip', text: `${p.title} · ${ti + 1}` }),
            t.asset ? h('audio', { controls: true, preload: 'none', src: `../api/admin/assets/${t.asset}` }) : h('span', { class: 'danger-text', text: 'Fayl tanlanmagan' }),
            t.transcript ? h('span', { class: 'muted small transcript', text: t.transcript }) : null
          )) : h('p', { class: 'muted', text: "Audio yo'q." })
        ));
      }
    };
    const buttons = ['L', 'R', 'W', 'S'].map((code) => h('button', {
      type: 'button', class: `seg-btn`, 'aria-pressed': String(code === current),
      onclick: (e) => {
        current = code;
        buttons.forEach((b) => b.setAttribute('aria-pressed', String(b === e.currentTarget)));
        draw();
      },
    }, { L: 'Listening', R: 'Reading', W: 'Writing', S: 'Speaking' }[code]));
    const keyToggle = checkbox("To'g'ri javoblarni ko'rsatish", showKey, (on) => { showKey = on; draw(); });
    draw();
    return h('div', null,
      h('div', { class: 'preview-toolbar' }, h('div', { class: 'seg' }, buttons), keyToggle,
        h('span', { class: 'muted small', text: "Saqlanmagan o'zgarishlar ham ko'rinadi. Bu yerda javob belgilash faqat sinov uchun." })),
      frame
    );
  }

  // ------------------------------------------------------------------
  // JSON
  // ------------------------------------------------------------------

  jsonTab() {
    const area = h('textarea', { class: 'input mono', rows: 24, spellcheck: 'false', value: JSON.stringify(this.state.source, null, 2) });
    return h('div', { class: 'card' },
      h('p', { class: 'muted small', text: "Mock kontentini JSON ko'rinishida saqlash yoki boshqa mockdan ko'chirish uchun. Fayllar (audio, rasm) identifikatorlari mock ichida bo'ladi." }),
      area,
      h('div', { class: 'actions' },
        h('button', { class: 'btn', type: 'button', onclick: () => copyText(area.value).then(() => toast('Nusxa olindi', 'success', 2000)) }, 'Nusxa olish'),
        h('button', { class: 'btn', type: 'button', onclick: () => downloadFile(`mock-${this.id || 'yangi'}.json`, area.value) }, 'Yuklab olish'),
        h('button', {
          class: 'btn btn-primary', type: 'button',
          onclick: async () => {
            let parsed;
            try {
              parsed = JSON.parse(area.value);
            } catch (err) {
              toast(`JSON xato: ${err.message}`, 'error', 6000);
              return;
            }
            if (!(await confirmDialog('Kontentni almashtirasizmi?', "Joriy kontent JSON'dagi bilan almashtiriladi (saqlash tugmasini bosguncha serverga yozilmaydi).", 'Almashtirish'))) return;
            this.state.source = { ...emptySource(), ...parsed };
            this.changed();
            toast('Kontent almashtirildi. Saqlashni unutmang.', 'success');
          },
        }, 'JSON\'dan olish')
      )
    );
  }

  // ------------------------------------------------------------------
  // Saqlash va holat
  // ------------------------------------------------------------------

  async save() {
    const st = this.state;
    if (!st.title.trim()) {
      toast('Mock nomini kiriting.', 'error');
      this.switchTab('general');
      return false;
    }
    this.saveBtn.disabled = true;
    try {
      const body = {
        title: st.title, description: st.description, max_attempts: st.max_attempts,
        available_from: st.available_from, available_to: st.available_to,
        source: st.source, settings: st.settings,
      };
      if (!this.id) {
        const res = await post('admin/mocks', body);
        this.dirty = false;
        toast('Mock yaratildi.', 'success');
        this.navigate(`/mocks/${res.mock.id}`, true);
        return true;
      }
      const res = await put(`admin/mocks/${this.id}`, body);
      this.dirty = false;
      this.meta.status = res.mock.status;
      this.meta.assets = res.mock.assets;
      this.updateSaveButton();
      const v = res.validation;
      const parts = [`Saqlandi.`];
      if (v.errors.length) parts.push(`${v.errors.length} ta xato`);
      if (v.warnings.length) parts.push(`${v.warnings.length} ta ogohlantirish`);
      if (res.rescored) parts.push(`${res.rescored} ta natija qayta hisoblandi`);
      toast(parts.join(' · '), v.errors.length ? 'warn' : 'success', 5000);
      if (this.tab === 'check') this.renderTab();
      return true;
    } catch (err) {
      if (err instanceof ApiError && err.data && err.data.validation) {
        this.showValidationModal(err.message, err.data.validation);
      } else toast(err.message, 'error', 6000);
      return false;
    } finally {
      this.saveBtn.disabled = false;
    }
  }

  showValidationModal(message, validation) {
    modal({ title: 'Tekshiruv natijasi', body: h('div', null, h('p', { text: message }), this.validationView(validation)), wide: true });
  }

  async setStatus(status) {
    if (this.dirty && !(await this.save())) return;
    const texts = {
      active: ['Mockni faollashtirasizmi?', "O'quvchilar mockni ko'radi va boshlay oladi."],
      frozen: ['Mockni muzlatasizmi?', "Yangi urinishlar to'xtatiladi va mock o'quvchilar ro'yxatidan yashiriladi. Boshlangan imtihonlar oxirigacha davom etadi. Keyin qayta faollashtirish mumkin."],
      archived: ['Mockni arxivlaysizmi?', "Mock o'quvchilarga ko'rinmaydi. Natijalar saqlanib qoladi."],
    };
    if (!(await confirmDialog(texts[status][0], texts[status][1], 'Tasdiqlash'))) return;
    try {
      const res = await post(`admin/mocks/${this.id}/status`, { status });
      this.meta.status = res.mock.status;
      toast(`Holat: ${MOCK_STATUS[res.mock.status]}`, 'success');
      this.render();
    } catch (err) {
      if (err instanceof ApiError && err.data && err.data.validation) this.showValidationModal(err.message, err.data.validation);
      else toast(err.message, 'error');
    }
  }
}


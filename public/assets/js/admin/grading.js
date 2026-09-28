// Ekspert ish joyi: Writing va Speaking ishlarini anonim holda baholash.
// Rasmiy mezonlar: Writing 1.1 (0–5), 1.2 (0–5), 2 (0–6); Speaking 1.1, 1.2, 2 (0–5), 3 (0–6).

import { apiUrl, get, post } from '../lib/api.js';
import { h, icon, mount } from '../lib/dom.js';
import { renderRich } from '../lib/markup.js';
import { formatDate, formatScore } from '../lib/text.js';
import { confirmDialog, errorBox, spinner, toast } from '../lib/ui.js';
import { field, pageHeader, table } from './common.js';

const FLAG_LABELS = {
  '': 'Maxsus holat yo\'q',
  off_topic: 'Mavzuga mos emas',
  memorized: 'Yodlangan',
  copied: 'Ko\'chirilgan',
  mother_tongue: 'Asosan ona tilida',
};

const HINTS = {
  W: {
    '1.1': '3–4 — B1 · 5 — B1 dan yuqori',
    '1.2': '3–4 — B2 · 5 — C1 va yuqori',
    2: '5 — C1 · 6 — C1 dan yuqori',
  },
  S: {
    '1.1': '4 / 3 — A2 yuqori / quyi',
    '1.2': '4 / 3 — B1 yuqori / quyi',
    2: '4 / 3 — B2 yuqori / quyi',
    3: '5 — C1 · 4 / 3 — B2 yuqori / quyi',
  },
};

export function gradingPage(root) {
  const workBox = h('div');
  const queueBox = h('div', { class: 'stats' });

  const loadQueue = async () => {
    try {
      const { queue } = await get('expert/queue');
      mount(queueBox,
        ['W', 'S'].map((skill) => h('div', { class: 'stat' },
          h('span', { class: 'stat-label', text: skill === 'W' ? 'Writing navbati' : 'Speaking navbati' }),
          h('span', { class: 'stat-value', text: String(queue[skill].waiting) }),
          h('span', { class: 'stat-hint', text: `Siz baholagan: ${queue[skill].mine}` }),
          h('button', { class: 'btn btn-primary btn-sm', type: 'button', disabled: queue[skill].waiting === 0, onclick: () => next(skill) }, 'Keyingi ish')
        ))
      );
    } catch (err) {
      queueBox.replaceChildren(errorBox(err.message, loadQueue));
    }
  };

  const next = async (skill) => {
    workBox.replaceChildren(spinner());
    try {
      const { work } = await post('expert/next', { skill });
      if (!work) {
        workBox.replaceChildren(h('div', { class: 'card' }, h('p', { text: "Siz uchun baholanadigan ish qolmadi." })));
        loadQueue();
        return;
      }
      workBox.replaceChildren(workView(work, async (done) => {
        await loadQueue();
        if (done === 'next') next(skill);
        else workBox.replaceChildren();
      }));
      workBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (err) {
      workBox.replaceChildren(errorBox(err.message));
    }
  };

  const historyBox = h('div');
  const loadHistory = async () => {
    try {
      const { ratings } = await get('expert/history');
      historyBox.replaceChildren(table([
        { title: 'Vaqt', render: (r) => formatDate(r.created_at) },
        { title: 'Mock', render: (r) => r.title },
        { title: 'Kod', render: (r) => h('code', { text: r.anon_code }) },
        { title: "Ko'nikma", render: (r) => (r.skill === 'W' ? 'Writing' : 'Speaking') },
        { title: 'Ballar', render: (r) => Object.entries(r.scores).map(([k, v]) => `${k}: ${v}`).join(', ') },
        { title: 'Jami', class: 'num', render: (r) => formatScore(r.raw_total) },
      ], ratings, { empty: 'Hali baholanmagan.' }));
    } catch (err) {
      historyBox.replaceChildren(errorBox(err.message));
    }
  };

  root.replaceChildren(
    pageHeader('Ekspert tekshiruvi'),
    h('p', { class: 'muted', text: "Ishlar anonim kod bilan beriladi. Bir ishni olganingizdan keyin 30 daqiqa ichida baholang, aks holda u navbatga qaytadi." }),
    queueBox,
    workBox,
    h('h2', { class: 'section-title', text: 'Mening baholarim' }),
    historyBox
  );
  loadQueue();
  loadHistory();
}

function rubricRow(skill, part, max, scores, flags, textEmpty) {
  const select = h('select', { class: 'input band-select' },
    h('option', { value: '' }, '—'),
    Array.from({ length: max + 1 }, (_, i) => h('option', { value: i }, String(i))));
  select.addEventListener('change', () => (scores[part] = select.value === '' ? null : Number(select.value)));
  const flag = h('select', { class: 'input' }, Object.entries(FLAG_LABELS).map(([v, label]) => h('option', { value: v }, label)));
  flag.addEventListener('change', () => {
    flags[part] = flag.value;
    if (skill === 'W' && flag.value) {
      // Rasmiy qoida: 1.1 da 1 ball (bo'sh bo'lsa 0), 1.2 va 2 da 0.
      const value = part === '1.1' && !textEmpty ? 1 : 0;
      select.value = String(value);
      scores[part] = value;
      select.disabled = true;
    } else {
      select.disabled = false;
    }
  });
  return h('div', { class: 'rubric-row' },
    h('div', { class: 'rubric-name' }, h('strong', { text: skill === 'W' ? `Task ${part}` : `Part ${part}` }), h('span', { class: 'muted small', text: `0–${max} · ${HINTS[skill][part] || ''}` })),
    select,
    flag
  );
}

function workView(work, onDone) {
  const scores = {};
  const flags = {};
  const comment = h('textarea', { class: 'input', rows: 3, placeholder: "Izoh (o'quvchi natijasida ko'rinadi)", lang: 'uz', spellcheck: 'false' });

  let content;
  if (work.skill === 'W') {
    content = work.tasks.map((t) => h('div', { class: 'card work-task' },
      h('div', { class: 'row' }, h('h3', { text: t.title || `Task ${t.id}` }), h('span', { class: `chip ${t.min_words && t.words < t.min_words ? 'chip-warn' : ''}`, text: `${t.words} so'z` }),
        h('span', { class: 'muted small', text: t.max_words ? `talab: ${t.min_words}–${t.max_words}` : t.min_words ? `talab: ~${t.min_words}` : '' })),
      h('details', { class: 'task-details' }, h('summary', { text: 'Topshiriq matni' }),
        t.context ? h('div', { class: 'task-context rich' }, renderRich(t.context)) : null,
        h('div', { class: 'rich' }, renderRich(t.prompt))
      ),
      h('pre', { class: 'writing-text', text: t.text || "(javob yo'q)" })
    ));
  } else {
    content = work.questions.map((q) => h('div', { class: 'card work-task' },
      h('div', { class: 'row' }, h('span', { class: 'chip', text: `Part ${q.part_id} · Savol ${q.no}` }), q.duration ? h('span', { class: 'muted small', text: `${q.duration.toFixed(1)} s / ${q.answer_sec} s` }) : null),
      q.topic ? h('div', { class: 'rich strong' }, renderRich(q.topic)) : null,
      h('div', { class: 'rich' }, renderRich(q.text)),
      (q.images || []).length ? h('div', { class: 'sq-images' }, q.images.map((id) => h('img', { class: 'sq-image', src: apiUrl(`expert/image/${work.attempt_id}/${id}`), alt: '' }))) : null,
      q.answer_id ? h('audio', { controls: true, preload: 'none', src: apiUrl(`speaking/${q.answer_id}`), class: 'answer-audio' }) : h('p', { class: 'danger-text', text: "Javob yozuvi yo'q." })
    ));
  }

  const textEmpty = (part) => {
    if (work.skill !== 'W') return false;
    const t = work.tasks.find((x) => x.id === part);
    return !t || t.words === 0;
  };
  const rubric = work.rubric.map(({ part, max }) => rubricRow(work.skill, part, max, scores, flags, textEmpty(part)));

  const submit = async (then) => {
    const missing = work.rubric.map((r) => r.part).filter((p) => scores[p] === null || scores[p] === undefined);
    if (missing.length) {
      toast(`Ball qo'yilmagan: ${missing.join(', ')}`, 'error');
      return;
    }
    try {
      await post('expert/rate', { attempt_id: work.attempt_id, skill: work.skill, scores, flags, comment: comment.value });
      toast('Baho saqlandi.', 'success', 2000);
      onDone(then);
    } catch (err) {
      toast(err.message, 'error', 6000);
    }
  };
  const release = async () => {
    if (!(await confirmDialog('Ishni navbatga qaytarasizmi?', "Bu ishni boshqa ekspert baholaydi.", 'Qaytarish'))) return;
    await post('expert/release', { attempt_id: work.attempt_id, skill: work.skill });
    onDone('close');
  };

  return h('div', { class: 'work' },
    h('div', { class: 'work-head' },
      h('h2', null, work.skill === 'W' ? 'Writing' : 'Speaking', ' · ', h('code', { text: work.code })),
      h('span', { class: 'muted small', text: work.claim_expires_ms ? `Muddat: ${new Date(work.claim_expires_ms).toLocaleTimeString('uz-UZ', { hour: '2-digit', minute: '2-digit' })} gacha` : '' })
    ),
    h('div', { class: 'work-grid' },
      h('div', { class: 'work-content' }, content),
      h('aside', { class: 'card rubric' },
        h('h3', { text: 'Baholash' }),
        rubric,
        field('Izoh', comment),
        h('div', { class: 'actions' },
          h('button', { class: 'btn btn-ghost', type: 'button', onclick: release }, 'Qaytarish'),
          h('button', { class: 'btn', type: 'button', onclick: () => submit('close') }, 'Saqlash'),
          h('button', { class: 'btn btn-primary', type: 'button', onclick: () => submit('next') }, icon('check'), 'Saqlash va keyingisi')
        )
      )
    )
  );
}

// Natijalar: urinishlar jadvali, CSV, qayta hisoblash, Rasch, e'lon qilish, savollar tahlili
// va bitta urinishning batafsil ko'rinishi.

import { ApiError, apiUrl, get, post } from '../lib/api.js';
import { h, icon, mount } from '../lib/dom.js';
import { formatDate, formatScore, levelLabel } from '../lib/text.js';
import { confirmDialog, errorBox, modal, promptDialog, spinner, toast } from '../lib/ui.js';
import { ATTEMPT_STATUS, EVENT, PROCTOR_STATE, STAGE, pageHeader, statusBadge, table } from './common.js';

function scoreCell(raw, score, max) {
  if (score === null || score === undefined) return raw !== null && raw !== undefined ? h('span', { class: 'muted', text: `${formatScore(raw)}${max ? '/' + max : ''}` }) : '—';
  return h('span', null, h('strong', { text: formatScore(score) }), raw !== null && raw !== undefined ? h('span', { class: 'muted small', text: ` (${formatScore(raw)}${max ? '/' + max : ''})` }) : null);
}

const REC_STATUS = {
  recording: 'Yozilmoqda',
  ready: 'Telegram navbatida',
  sent: "Telegram'ga yuborildi",
  failed: 'Yuborilmadi',
  expired: "O'chirilgan",
};
const REC_CONTENT = { 'screen+camera': 'Ekran + kamera', screen: 'Ekran', camera: 'Kamera' };

function playVideo(r) {
  const video = h('video', { class: 'video-player', controls: true, autoplay: true, preload: 'metadata', src: apiUrl(`admin/recordings/${r.id}/file`) });
  modal({ title: `${STAGE[r.section] || r.section} — ${new Date(r.started_ms).toLocaleString('uz-UZ')}`, body: video, wide: true })
    .then(() => video.pause());
}

/** Urinish sahifasi: video yozuvlar (ko'rish, yuklab olish, Telegram havolasi, qayta yuborish). */
function videoCard(d, meta, reload) {
  const recs = d.recordings || [];
  const p = meta.proctor || null;
  const enabled = d.proctoring && (d.proctoring.camera !== 'off' || d.proctoring.screen !== 'off');
  if (!recs.length && !p && !enabled) return null;
  const retry = async (r) => {
    try {
      await post(`admin/recordings/${r.id}/retry`);
      toast('Qayta navbatga qo\'yildi.', 'success');
      reload();
    } catch (err) {
      toast(err.message, 'error');
    }
  };
  const minutes = (ms) => (ms >= 60000 ? `${Math.round(ms / 60000)} daq` : `${Math.round(ms / 1000)} s`);
  return h('div', { class: 'card' },
    h('h3', { text: 'Video yozuvlar' }),
    p ? h('p', { class: 'muted small', text: [
      `Kamera: ${PROCTOR_STATE[p.camera] || '—'}`,
      `ekran: ${PROCTOR_STATE[p.screen] || '—'}`,
      p.screens > 1 ? `${p.screens} ta monitor` : null,
      p.camera_missing ? "imtihon davomida kamera ishlamagan payt bo'lgan" : null,
    ].filter(Boolean).join(' · ') }) : null,
    recs.length ? table([
      { title: "Bo'lim", render: (r) => STAGE[r.section] || r.section },
      { title: 'Boshlangan', render: (r) => new Date(r.started_ms).toLocaleTimeString('uz-UZ', { hour: '2-digit', minute: '2-digit' }) },
      { title: 'Davomiyligi', render: (r) => minutes(r.duration_ms) },
      { title: 'Tarkib', render: (r) => `${REC_CONTENT[r.content] || r.content}${Number(r.has_audio) ? ' + ovoz' : ''}` },
      { title: 'Hajm', render: (r) => `${(r.size / 1048576).toFixed(1)} MB` },
      { title: 'Holat', render: (r) => h('div', null,
        h('span', { class: r.status === 'failed' ? 'danger-text' : r.status === 'sent' ? '' : 'muted', text: REC_STATUS[r.status] || r.status }),
        Number(r.complete) || r.status === 'recording' ? null : h('div', { class: 'muted small', text: "uzilgan (to'liq emas)" }),
        r.tg_error && r.status !== 'sent' ? h('div', { class: 'muted small', text: r.tg_error }) : null
      ) },
      { title: '', render: (r) => h('div', { class: 'btn-row' },
        r.playable ? h('button', { class: 'btn btn-sm', type: 'button', onclick: (e) => { e.stopPropagation(); playVideo(r); } }, "Ko'rish") : null,
        r.playable ? h('a', { class: 'btn btn-sm', href: apiUrl(`admin/recordings/${r.id}/file?download=1`) }, 'Yuklab olish') : null,
        r.tg_link ? h('a', { class: 'btn btn-sm', href: r.tg_link, target: '_blank', rel: 'noopener noreferrer' }, 'Telegram') : null,
        r.status === 'failed' ? h('button', { class: 'btn btn-sm', type: 'button', onclick: () => retry(r) }, 'Qayta yuborish') : null
      ) },
    ], recs) : h('p', { class: 'muted', text: "Video yozuv yo'q." }),
    h('p', { class: 'muted small', text: "Yozma qism videolari Telegram'ga yuborilgach serverdan o'chiriladi (\"Telegram\" tugmasi kanal xabarini ochadi); Speaking videolari serverda ham saqlanadi." })
  );
}

/** Natijalar jadvali: video yozuvlar soni va kamera/ekran belgilari. */
function proctorCell(a) {
  const p = a.proctor;
  const flags = [];
  // Sayt http:// da ochilgan bo'lsa, brauzer kamera/ekranni umuman bermaydi — sababi belgi ustida ko'rsatiladi.
  const why = (status, fallback) => (status === 'insecure' ? "Sayt HTTPS emas — brauzer ruxsat bermadi" : fallback);
  if (p && p.camera_missing) flags.push(h('span', { class: 'badge-danger', title: why(p.camera, 'Kamera ishlamagan'), text: 'kamerasiz' }));
  if (p && p.screen_missing) flags.push(h('span', { class: 'badge-danger', title: why(p.screen, 'Ekran ulashilmagan'), text: 'ekransiz' }));
  if (p && p.screens > 1) flags.push(h('span', { class: 'badge-danger', text: `${p.screens} monitor` }));
  if (!flags.length && !a.videos) return h('span', { class: 'muted', text: '—' });
  return h('div', { class: 'proctor-flags' }, a.videos ? h('span', { class: 'muted small', text: `${a.videos} ta` }) : null, flags);
}

export async function resultsPage(root, mockId, navigate) {
  root.replaceChildren(spinner());
  let data;
  try {
    data = await get(`admin/mocks/${mockId}/attempts`);
  } catch (err) {
    root.replaceChildren(errorBox(err.message, () => resultsPage(root, mockId, navigate)));
    return;
  }
  const { mock, attempts, stats } = data;
  const reload = () => resultsPage(root, mockId, navigate);

  const rescore = async () => {
    const res = await post(`admin/mocks/${mockId}/rescore`);
    toast(`${res.rescored} ta natija qayta hisoblandi.`, 'success');
    reload();
  };

  const rasch = async () => {
    const mu = h('input', { class: 'input input-num', type: 'number', step: '0.01', placeholder: 'μ' });
    const sigma = h('input', { class: 'input input-num', type: 'number', step: '0.01', placeholder: 'σ' });
    const body = h('div', null,
      h('p', { text: "Listening va Reading uchun Rasch modeli bilan qiyinlik va qobiliyat hisoblanadi: T = 50 + 10·(θ − μ)/σ, 75 bilan cheklanadi. Kamida 10 ta qatnashchi kerak." }),
      h('p', { class: 'muted small', text: "Kichik MOC'da μ va σ beqaror bo'lishi mumkin. Katta MOC'dan olingan qiymatlarni qotirish mumkin (bo'sh qoldirsangiz — shu MOC qatnashchilaridan hisoblanadi)." }),
      h('div', { class: 'row' }, h('label', { class: 'inline-field' }, 'μ', mu), h('label', { class: 'inline-field' }, 'σ', sigma))
    );
    const ok = await modal({ title: 'Rasch hisoblash', body, actions: [{ label: 'Bekor qilish', value: false }, { label: 'Hisoblash', value: true, variant: 'primary' }] });
    if (!ok) return;
    const fixed = mu.value !== '' && sigma.value !== '' ? { L: { mu: Number(mu.value), sigma: Number(sigma.value) }, R: { mu: Number(mu.value), sigma: Number(sigma.value) } } : {};
    try {
      const res = await post(`admin/mocks/${mockId}/rasch`, { fixed });
      const lines = Object.entries(res.report).map(([code, r]) => `${STAGE[code]}: ${r.ok ? `μ = ${r.mu}, σ = ${r.sigma}, n = ${r.n}` : r.message}`);
      modal({ title: 'Rasch natijasi', body: h('div', null, lines.map((l) => h('p', { text: l }))) });
      reload();
    } catch (err) {
      toast(err.message, 'error', 6000);
    }
  };

  const publish = async (value) => {
    try {
      await post(`admin/mocks/${mockId}/publish`, { publish: value });
    } catch (err) {
      if (err instanceof ApiError && err.code === 'grading_pending') {
        if (!(await confirmDialog("Baholash tugamagan", err.message, "Baribir e'lon qilish", 'danger'))) return;
        await post(`admin/mocks/${mockId}/publish`, { publish: value, force: true });
      } else {
        toast(err.message, 'error');
        return;
      }
    }
    toast(value ? "Natijalar e'lon qilindi." : "Natijalar e'londan olindi.", 'success');
    reload();
  };

  const raschInfo = ['L', 'R'].map((code) => {
    const r = stats.rasch && stats.rasch[code];
    return h('div', { class: 'stat' },
      h('span', { class: 'stat-label', text: `${STAGE[code]} — shkala` }),
      h('span', { class: 'stat-value small-value', text: r ? 'Rasch' : 'Taxminiy' }),
      h('span', { class: 'stat-hint', text: r ? `μ = ${r.mu}, σ = ${r.sigma}, n = ${r.n}${r.fixed ? ' (qotirilgan)' : ''}` : "Rasch hali hisoblanmagan" })
    );
  });

  const done = attempts.filter((a) => a.status !== 'in_progress');
  const listTab = h('div');
  const itemsTab = h('div');
  const tabs = h('nav', { class: 'tabs' },
    h('button', { class: 'tab active', type: 'button', onclick: (e) => switchTab(e, 'list') }, `Natijalar (${attempts.length})`),
    h('button', { class: 'tab', type: 'button', onclick: (e) => switchTab(e, 'items') }, 'Savollar tahlili')
  );
  const switchTab = (e, which) => {
    tabs.querySelectorAll('.tab').forEach((t) => t.classList.toggle('active', t === e.currentTarget));
    listTab.hidden = which !== 'list';
    itemsTab.hidden = which !== 'items';
    if (which === 'items' && !itemsTab.dataset.loaded) itemsView(itemsTab, mockId);
  };
  itemsTab.hidden = true;

  listTab.append(table([
    { title: '#', render: (a, i) => (a.overall !== null ? i + 1 : '') },
    { title: "O'quvchi", render: (a) => h('div', null, h('strong', { text: a.full_name }), h('div', { class: 'muted small', text: a.login })) },
    { title: 'Urinish', render: (a) => `${a.attempt_no}` },
    { title: 'Holat', render: (a) => h('div', null, statusBadge(a.status, ATTEMPT_STATUS), a.status === 'in_progress' ? h('div', { class: 'muted small', text: STAGE[a.stage] }) : null) },
    { title: 'Listening', render: (a) => scoreCell(a.l_raw, a.l_score, 35) },
    { title: 'Reading', render: (a) => scoreCell(a.r_raw, a.r_score, 35) },
    { title: 'Writing', render: (a) => (a.grade_w === 'queue' ? h('span', { class: 'muted', text: 'Navbatda' }) : scoreCell(a.w_raw, a.w_score, 16)) },
    { title: 'Speaking', render: (a) => (a.grade_s === 'queue' ? h('span', { class: 'muted', text: 'Navbatda' }) : scoreCell(a.s_raw, a.s_score, 21)) },
    { title: 'Umumiy', render: (a) => h('strong', { text: formatScore(a.overall) }) },
    { title: 'Daraja', render: (a) => (a.level ? h('span', { class: `level level-${a.level}`, text: levelLabel(a.level) }) : '—') },
    { title: 'Qoidabuzarlik', class: 'num', render: (a) => h('span', { class: a.violations ? 'badge-danger' : 'muted', text: String(a.violations) }) },
    { title: 'Video', render: proctorCell },
  ], attempts, { empty: "Hali urinishlar yo'q.", onRow: (a) => navigate(`/attempts/${a.id}`) }));

  mount(root,
    h('a', { class: 'back-link', href: '#/mocks' }, icon('left'), 'Mocklar'),
    pageHeader(mock.title,
      h('a', { class: 'btn', href: `#/mocks/${mockId}` }, 'Tahrirlash'),
      h('a', { class: 'btn', href: apiUrl(`admin/mocks/${mockId}/export`) }, 'Excel (CSV)'),
      h('button', { class: 'btn', type: 'button', onclick: rescore }, 'Qayta hisoblash'),
      h('button', { class: 'btn', type: 'button', onclick: rasch }, 'Rasch'),
      mock.results_published_at
        ? h('button', { class: 'btn', type: 'button', onclick: () => publish(false) }, "E'londan olish")
        : h('button', { class: 'btn btn-primary', type: 'button', onclick: () => publish(true) }, "Natijalarni e'lon qilish")
    ),
    h('div', { class: 'stats' },
      h('div', { class: 'stat' }, h('span', { class: 'stat-label', text: 'Urinishlar' }), h('span', { class: 'stat-value', text: String(attempts.length) }), h('span', { class: 'stat-hint', text: `${done.length} tasi yakunlangan` })),
      ...raschInfo,
      h('div', { class: 'stat' }, h('span', { class: 'stat-label', text: 'Natijalar' }), h('span', { class: 'stat-value small-value', text: mock.results_published_at ? "E'lon qilingan" : "E'lon qilinmagan" }), h('span', { class: 'stat-hint', text: mock.results_published_at ? formatDate(mock.results_published_at) : '' }))
    ),
    tabs,
    listTab,
    itemsTab
  );
}

async function itemsView(box, mockId) {
  box.dataset.loaded = '1';
  box.replaceChildren(spinner());
  const draw = (items) => {
    box.replaceChildren(...['L', 'R'].filter((c) => items[c] && items[c].length).map((code) => h('div', { class: 'card' },
      h('h3', { text: STAGE[code] }),
      table([
        { title: '№', render: (r) => r.n },
        { title: 'Kalit', render: (r) => h('code', { text: r.answer }) },
        { title: "To'g'ri", class: 'num', render: (r) => (r.p === null ? '—' : h('span', { class: r.p < 0.2 || r.p > 0.95 ? 'warn-text' : '', text: `${Math.round(r.p * 100)}%` })) },
        { title: "Bo'sh", class: 'num', render: (r) => r.blank },
        { title: 'Qiyinlik (logit)', class: 'num', render: (r) => (r.difficulty === null || r.difficulty === undefined ? '—' : Number(r.difficulty).toFixed(2)) },
        {
          title: "Eng ko'p noto'g'ri javoblar",
          render: (r) => h('div', { class: 'wrong-list' }, Object.entries(r.wrong).map(([answer, count]) => h('span', { class: 'wrong-chip' },
            h('span', { text: `${answer} · ${count}` }),
            r.type === 'gap' ? h('button', {
              type: 'button', class: 'mini-btn', title: "Bu javobni to'g'ri deb qabul qilish (kalitga muqobil sifatida qo'shiladi)",
              onclick: async () => {
                if (!(await confirmDialog("Javobni qabul qilasizmi?", `${r.n}-savol uchun "${answer}" to'g'ri javob sifatida qo'shiladi va barcha natijalar qayta hisoblanadi.`, 'Qabul qilish'))) return;
                try {
                  const res = await post(`admin/mocks/${mockId}/items/accept`, { section: code, n: r.n, value: answer });
                  toast('Kalitga qo\'shildi, natijalar qayta hisoblandi.', 'success');
                  draw(res.items);
                } catch (err) {
                  toast(err.message, 'error');
                }
              },
            }, '✓') : null
          ))),
        },
      ], items[code])
    )));
    if (!box.children.length) box.replaceChildren(h('p', { class: 'empty', text: "Tahlil uchun ma'lumot yo'q." }));
  };
  try {
    const { items } = await get(`admin/mocks/${mockId}/items`);
    draw(items);
  } catch (err) {
    box.replaceChildren(errorBox(err.message));
  }
}

// ---------------------------------------------------------------------
// Bitta urinish
// ---------------------------------------------------------------------

export async function attemptPage(root, id, navigate) {
  root.replaceChildren(spinner());
  let d;
  try {
    d = await get(`admin/attempts/${id}`);
  } catch (err) {
    root.replaceChildren(errorBox(err.message, () => attemptPage(root, id, navigate)));
    return;
  }
  const a = d.attempt;
  const meta = d.meta || {};

  const reset = async () => {
    const reason = await promptDialog('Urinishni bekor qilish', "Sabab (masalan, texnik nosozlik). Urinish o'chiriladi va o'quvchiga shu urinish qaytariladi.", { multiline: true, okLabel: 'Bekor qilish' });
    if (!reason) return;
    try {
      await post(`admin/attempts/${id}/reset`, { reason });
      toast('Urinish bekor qilindi.', 'success');
      navigate(`/mocks/${d.mock.id}/results`);
    } catch (err) {
      toast(err.message, 'error');
    }
  };
  const terminate = async () => {
    const reason = await promptDialog("Imtihonni to'xtatish", 'Sabab (jurnalga yoziladi).', { multiline: true, okLabel: "To'xtatish" });
    if (!reason) return;
    try {
      await post(`admin/attempts/${id}/terminate`, { reason });
      toast("Imtihon to'xtatildi.", 'success');
      attemptPage(root, id, navigate);
    } catch (err) {
      toast(err.message, 'error');
    }
  };

  const sectionTimes = Object.entries(meta.sections || {}).map(([code, s]) => h('div', { class: 'stat' },
    h('span', { class: 'stat-label', text: STAGE[code] }),
    h('span', { class: 'stat-value small-value', text: s.finished_ms ? `${Math.round((s.finished_ms - s.started_ms) / 60000)} daqiqa` : 'Jarayonda' }),
    h('span', { class: 'stat-hint', text: s.reason === 'timeout' ? 'Vaqt tugadi' : s.reason === 'terminated' ? "To'xtatildi" : s.reason === 'submitted' ? 'Topshirildi' : '' })
  ));

  const review = ['L', 'R'].filter((c) => d.review[c]).map((code) => h('details', { class: 'card', open: false },
    h('summary', null, h('strong', { text: `${STAGE[code]}: javoblar` }), h('span', { class: 'muted', text: ` — ${d.review[code].filter((r) => r.correct).length}/${d.review[code].length} to'g'ri` })),
    h('div', { class: 'answer-grid' }, d.review[code].map((r) => h('div', { class: `answer-cell ${r.correct ? 'ok' : r.given ? 'bad' : 'empty'}` },
      h('span', { class: 'q-num', text: String(r.n) }),
      h('span', { class: 'given', text: r.given || '—' }),
      r.correct ? null : h('span', { class: 'key muted small', text: r.key })
    )))
  ));

  const wstats = meta.wstats || {};
  const writing = d.writing.length ? h('div', { class: 'card' },
    h('h3', { text: 'Writing' }),
    d.writing.map((w) => h('div', { class: 'writing-review' },
      h('div', { class: 'row' },
        h('strong', { text: `Task ${w.task}` }),
        h('span', { class: 'muted small', text: `${w.words} so'z` }),
        wstats[w.task] ? h('span', { class: 'muted small', text: `· ${wstats[w.task].keys} ta tugma bosilgan · ${wstats[w.task].blocked} ta paste bloklangan · ${wstats[w.task].large} ta katta bo'lak` }) : null
      ),
      h('pre', { class: 'writing-text', text: w.text || '(bo\'sh)' })
    ))
  ) : null;

  const speaking = d.speaking.length ? h('div', { class: 'card' },
    h('h3', { text: 'Speaking yozuvlari' }),
    d.speaking.map((s) => h('div', { class: 'row' }, h('span', { class: 'chip', text: `Savol ${s.q_no}` }), h('audio', { controls: true, preload: 'none', src: apiUrl(`speaking/${s.id}`) }), h('span', { class: 'muted small', text: `${Number(s.duration || 0).toFixed(1)} s` })))
  ) : null;

  const videos = videoCard(d, meta, () => attemptPage(root, id, navigate));

  const ratings = d.ratings.length ? h('div', { class: 'card' },
    h('h3', { text: 'Ekspert baholari' }),
    table([
      { title: "Ko'nikma", render: (r) => STAGE[r.skill] },
      { title: 'Tur', render: (r) => `${r.round}-ekspert` },
      { title: 'Ekspert', render: (r) => r.expert },
      { title: 'Ballar', render: (r) => Object.entries(r.scores).map(([k, v]) => `${k}: ${v}`).join(', ') },
      { title: 'Jami', render: (r) => formatScore(r.raw_total) },
      { title: 'Belgilar', render: (r) => Object.entries(r.flags || {}).map(([k, v]) => `${k}: ${v}`).join(', ') || '—' },
      { title: 'Izoh', render: (r) => r.comment || '—' },
    ], d.ratings)
  ) : null;

  const events = h('div', { class: 'card' },
    h('h3', { text: `Hodisalar jurnali (qoidabuzarliklar: ${a.violations})` }),
    table([
      { title: 'Vaqt', render: (e) => new Date(e.created_ms).toLocaleTimeString('uz-UZ') },
      { title: "Bo'lim", render: (e) => STAGE[e.section] || '—' },
      { title: 'Hodisa', render: (e) => h('span', { class: e.is_violation ? 'danger-text strong' : '', text: EVENT[e.type] || e.type }) },
      { title: 'Tafsilot', render: (e) => e.detail || '' },
    ], d.events, { empty: "Hodisalar yo'q." })
  );

  mount(root,
    h('a', { class: 'back-link', href: `#/mocks/${d.mock.id}/results` }, icon('left'), d.mock.title),
    pageHeader(`${d.user.full_name} — ${a.attempt_no}-urinish`,
      a.status === 'in_progress' ? h('button', { class: 'btn btn-danger', type: 'button', onclick: terminate }, "Imtihonni to'xtatish") : null,
      h('button', { class: 'btn', type: 'button', onclick: reset }, 'Urinishni bekor qilish')
    ),
    h('div', { class: 'stats' },
      h('div', { class: 'stat' }, h('span', { class: 'stat-label', text: 'Holat' }), statusBadge(a.status, ATTEMPT_STATUS), h('span', { class: 'stat-hint', text: `${d.user.login} · kod ${a.anon_code}` })),
      h('div', { class: 'stat' }, h('span', { class: 'stat-label', text: 'Umumiy ball' }), h('span', { class: 'stat-value', text: formatScore(a.overall) }), a.level ? h('span', { class: `level level-${a.level}`, text: levelLabel(a.level) }) : null),
      h('div', { class: 'stat' }, h('span', { class: 'stat-label', text: 'Boshlangan' }), h('span', { class: 'stat-value small-value', text: formatDate(a.started_at) }), h('span', { class: 'stat-hint', text: a.ip || '' })),
      ...sectionTimes
    ),
    meta.terminated ? h('div', { class: 'alert alert-danger' }, icon('alert'), `To'xtatilgan: ${meta.terminated.reason}`) : null,
    h('div', { class: 'score-grid' },
      ['L', 'R', 'W', 'S'].map((code) => {
        const k = code.toLowerCase();
        return h('div', { class: 'score-card' },
          h('span', { class: 'score-name', text: STAGE[code] }),
          h('span', { class: 'score-value', text: formatScore(a[`${k}_score`]) }),
          h('span', { class: 'score-detail', text: a[`${k}_raw`] !== null && a[`${k}_raw`] !== undefined ? `xom: ${formatScore(a[`${k}_raw`])}` : '' })
        );
      })
    ),
    review,
    writing,
    speaking,
    videos,
    ratings,
    events,
    h('p', { class: 'muted small', text: a.user_agent || '' })
  );
}


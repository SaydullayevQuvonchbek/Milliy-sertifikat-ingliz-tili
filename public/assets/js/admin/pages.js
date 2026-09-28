// Admin sahifalari: bosh panel, mocklar ro'yxati, jonli nazorat, sozlamalar.

import { del, get, post, put } from '../lib/api.js';
import { h, icon, setText } from '../lib/dom.js';
import { formatClock, formatDate } from '../lib/text.js';
import { confirmDialog, errorBox, spinner, toast } from '../lib/ui.js';
import { EVENT, MOCK_STATUS, STAGE, checkbox, field, pageHeader, statusBadge, table } from './common.js';

const SECTION_SHORT = { L: 'L', R: 'R', W: 'W', S: 'S' };

export async function dashboardPage(root) {
  root.replaceChildren(spinner());
  try {
    const d = await get('admin/dashboard');
    const stat = (label, value, hint, href) => h(href ? 'a' : 'div', { class: 'stat', href },
      h('span', { class: 'stat-label', text: label }),
      h('span', { class: 'stat-value', text: String(value) }),
      hint ? h('span', { class: 'stat-hint', text: hint }) : null
    );
    root.replaceChildren(
      pageHeader('Bosh panel', h('a', { class: 'btn btn-primary', href: '#/mocks/new' }, '+ Yangi mock')),
      h('div', { class: 'stats' },
        stat("O'quvchilar", d.students, `${d.experts} ta ekspert`, '#/users'),
        stat('Faol mocklar', d.mocks.active, `${d.mocks.frozen} muzlatilgan · ${d.mocks.draft} qoralama`, '#/mocks'),
        stat('Hozir imtihonda', d.in_progress, 'Jonli nazorat', '#/live'),
        stat('Tekshiruv navbati', d.queue.W + d.queue.S, `Writing: ${d.queue.W} · Speaking: ${d.queue.S}`, '#/grading'),
        stat('7 kunda urinishlar', d.week.attempts, `${d.week.terminated} tasi chetlatilgan`)
      ),
      h('h2', { class: 'section-title', text: "So'nggi amallar" }),
      table([
        { title: 'Vaqt', render: (r) => formatDate(r.created_at) },
        { title: 'Kim', render: (r) => r.full_name || '—' },
        { title: 'Amal', render: (r) => auditLabel(r.action) },
        { title: 'Obyekt', render: (r) => r.target },
      ], d.audit, { empty: "Hali amallar yo'q." })
    );
  } catch (err) {
    root.replaceChildren(errorBox(err.message, () => dashboardPage(root)));
  }
}

function auditLabel(action) {
  return {
    mock_create: 'Mock yaratildi',
    mock_status: 'Mock holati o\'zgardi',
    mock_duplicate: 'Mock nusxalandi',
    mock_delete: "Mock o'chirildi",
    mock_key_changed: "Kalit o'zgardi",
    mock_rescore: 'Qayta hisoblandi',
    mock_rasch: 'Rasch hisoblandi',
    mock_rasch_clear: 'Rasch bekor qilindi',
    key_alternative: 'Muqobil javob qabul qilindi',
    results_publish: "Natijalar e'lon qilindi",
    results_unpublish: "Natijalar e'londan olindi",
    asset_upload: 'Fayl yuklandi',
    asset_delete: "Fayl o'chirildi",
    attempt_reset: 'Urinish bekor qilindi',
    attempt_terminate: "Imtihon to'xtatildi",
    user_create: 'Foydalanuvchi qo\'shildi',
    user_bulk: "O'quvchilar ro'yxati qo'shildi",
    user_update: "Foydalanuvchi o'zgartirildi",
    user_password_reset: 'Parol yangilandi',
    settings_update: 'Sozlamalar o\'zgardi',
  }[action] || action;
}

// ---------------------------------------------------------------------
// Mocklar ro'yxati
// ---------------------------------------------------------------------

export async function mocksPage(root, navigate) {
  root.replaceChildren(spinner());
  let data;
  try {
    data = await get('admin/mocks');
  } catch (err) {
    root.replaceChildren(errorBox(err.message, () => mocksPage(root, navigate)));
    return;
  }
  const changeStatus = async (mock, status) => {
    const texts = {
      active: ['Faollashtirasizmi?', "O'quvchilar mockni ko'radi va boshlay oladi."],
      frozen: ['Muzlatasizmi?', "Yangi urinishlar to'xtatiladi, mock o'quvchilarga ko'rinmaydi. Boshlangan imtihonlar oxirigacha davom etadi."],
      archived: ['Arxivlaysizmi?', "Mock o'quvchilarga ko'rinmaydi, natijalar saqlanadi."],
    };
    if (!(await confirmDialog(`"${mock.title}" — ${texts[status][0]}`, texts[status][1], 'Tasdiqlash'))) return;
    try {
      await post(`admin/mocks/${mock.id}/status`, { status });
      toast(`Holat: ${MOCK_STATUS[status]}`, 'success');
      mocksPage(root, navigate);
    } catch (err) {
      toast(err.message, 'error', 6000);
      if (err.data && err.data.validation) navigate(`/mocks/${mock.id}`);
    }
  };
  const duplicate = async (mock) => {
    try {
      const res = await post(`admin/mocks/${mock.id}/duplicate`);
      toast('Nusxa yaratildi.', 'success');
      navigate(`/mocks/${res.mock.id}`);
    } catch (err) {
      toast(err.message, 'error');
    }
  };
  const remove = async (mock) => {
    if (!(await confirmDialog("Mockni o'chirasizmi?", `"${mock.title}" butunlay o'chiriladi.`, "O'chirish", 'danger'))) return;
    try {
      await del(`admin/mocks/${mock.id}`);
      toast("O'chirildi.", 'success');
      mocksPage(root, navigate);
    } catch (err) {
      toast(err.message, 'error', 6000);
    }
  };

  root.replaceChildren(
    pageHeader('Mocklar', h('a', { class: 'btn btn-primary', href: '#/mocks/new' }, '+ Yangi mock')),
    table([
      { title: 'Nomi', render: (m) => h('a', { href: `#/mocks/${m.id}`, class: 'strong-link' }, m.title) },
      { title: 'Holat', render: (m) => statusBadge(m.status, MOCK_STATUS) },
      { title: "Bo'limlar", render: (m) => m.sections.map((s) => SECTION_SHORT[s]).join(' · ') || '—' },
      { title: 'Urinish', render: (m) => `${m.max_attempts} marta` },
      { title: 'Jarayonda', class: 'num', render: (m) => m.attempts.in_progress },
      { title: 'Yakunlangan', class: 'num', render: (m) => m.attempts.completed + m.attempts.terminated },
      { title: 'Natijalar', render: (m) => (m.results_published ? "E'lon qilingan" : '—') },
      {
        title: '',
        render: (m) => h('div', { class: 'row-actions' },
          h('a', { class: 'btn btn-sm', href: `#/mocks/${m.id}` }, 'Tahrirlash'),
          h('a', { class: 'btn btn-sm', href: `#/mocks/${m.id}/results` }, 'Natijalar'),
          m.status === 'active'
            ? h('button', { class: 'btn btn-sm', type: 'button', onclick: () => changeStatus(m, 'frozen') }, '❄ Muzlatish')
            : h('button', { class: 'btn btn-sm btn-success', type: 'button', onclick: () => changeStatus(m, 'active') }, 'Faollashtirish'),
          h('button', { class: 'btn btn-sm btn-ghost', type: 'button', onclick: () => duplicate(m) }, 'Nusxa'),
          m.status !== 'archived' && m.status !== 'active' ? h('button', { class: 'btn btn-sm btn-ghost', type: 'button', onclick: () => changeStatus(m, 'archived') }, 'Arxiv') : null,
          m.attempts.in_progress + m.attempts.completed + m.attempts.terminated === 0
            ? h('button', { class: 'btn btn-sm btn-ghost danger', type: 'button', onclick: () => remove(m) }, "O'chirish")
            : null
        ),
      },
    ], data.mocks, { empty: "Hali mock yo'q. \"Yangi mock\" tugmasini bosing." }),
    h('p', { class: 'muted small', text: "Muzlatilgan mock o'quvchilarga ko'rinmaydi va yangi urinish boshlanmaydi. Bitta o'quvchi bitta mockni ko'pi bilan 2 marta ishlay oladi — bu serverda qat'iy tekshiriladi." })
  );
}

// ---------------------------------------------------------------------
// Jonli nazorat
// ---------------------------------------------------------------------

export function livePage(root, navigate) {
  const body = h('tbody');
  const info = h('p', { class: 'muted small' });
  const tableEl = h('div', { class: 'table-wrap' }, h('table', { class: 'table' },
    h('thead', null, h('tr', null, ["O'quvchi", 'Mock', "Bo'lim", 'Qolgan vaqt', 'Aloqa', 'Qoidabuzarlik', "So'nggi hodisa", ''].map((t) => h('th', { text: t })))),
    body
  ));
  let data = null;
  let stopped = false;
  const rows = new Map();

  const drawRow = (a, now) => {
    let entry = rows.get(a.id);
    if (!entry) {
      const tr = h('tr', { class: 'clickable', onclick: () => navigate(`/attempts/${a.id}`) });
      const cells = Array.from({ length: 8 }, () => h('td'));
      tr.append(...cells);
      entry = { tr, cells };
      rows.set(a.id, entry);
    }
    const { tr } = entry;
    const [c0, c1, c2, c3, c4, c5, c6, c7] = entry.cells;
    setText(c0, `${a.full_name} (${a.login})`);
    setText(c1, `${a.title} · ${a.attempt_no}-urinish`);
    setText(c2, `${STAGE[a.stage] || a.stage}${a.stage_state === 'pending' ? ' (kutmoqda)' : ''}`);
    setText(c3, a.section_deadline_ms ? formatClock(a.section_deadline_ms - now) : '—');
    c4.replaceChildren(h('span', { class: `dot ${a.online ? 'dot-on' : 'dot-off'}` }), a.online ? ' Onlayn' : ` ${a.last_seen_ms ? formatClock(now - a.last_seen_ms) + ' oldin' : "Yo'q"}`);
    c5.replaceChildren(h('span', { class: a.violations > 0 ? 'badge-danger' : 'muted', text: String(a.violations) }));
    c6.replaceChildren(a.last_event ? h('span', { class: a.last_event.is_violation ? 'danger-text' : 'muted', text: EVENT[a.last_event.type] || a.last_event.type }) : '—');
    c7.replaceChildren(h('a', { class: 'btn btn-sm', href: `#/attempts/${a.id}` }, 'Batafsil'));
    return tr;
  };

  const draw = () => {
    if (!data) return;
    const now = Date.now() - (data.localAt - data.now);
    const seen = new Set();
    data.attempts.forEach((a) => {
      seen.add(a.id);
      const tr = drawRow(a, now);
      if (tr.parentNode !== body) body.append(tr);
    });
    for (const [id, entry] of rows) {
      if (!seen.has(id)) {
        entry.tr.remove();
        rows.delete(id);
      }
    }
    setText(info, data.attempts.length ? `Imtihonda: ${data.attempts.length} ta o'quvchi. Har 10 soniyada yangilanadi.` : "Hozir hech kim imtihon ishlamayapti.");
  };

  const load = async () => {
    if (stopped) return;
    try {
      const res = await get('admin/live');
      data = { ...res, localAt: Date.now() };
      draw();
    } catch (err) {
      setText(info, err.message);
    }
  };

  root.replaceChildren(pageHeader('Jonli nazorat'), info, tableEl);
  load();
  const poll = setInterval(load, 10000);
  const tick = setInterval(draw, 1000);
  return { destroy: () => { stopped = true; clearInterval(poll); clearInterval(tick); } };
}

// ---------------------------------------------------------------------
// Sozlamalar
// ---------------------------------------------------------------------

export async function settingsPage(root) {
  root.replaceChildren(spinner());
  const { settings } = await get('admin/settings');
  const name = h('input', { class: 'input', value: settings.site_name, lang: 'uz', spellcheck: 'false' });
  let registration = Boolean(settings.registration_open);
  const cap = h('select', { class: 'input' }, h('option', { value: 1, selected: settings.max_attempts_cap === 1 }, '1 marta'), h('option', { value: 2, selected: settings.max_attempts_cap === 2 }, '2 marta'));
  const saveBtn = h('button', {
    class: 'btn btn-primary', type: 'button',
    onclick: async () => {
      try {
        await put('admin/settings', { site_name: name.value, registration_open: registration, max_attempts_cap: Number(cap.value) });
        toast('Saqlandi.', 'success');
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  }, 'Saqlash');
  root.replaceChildren(
    pageHeader('Sozlamalar'),
    h('div', { class: 'card form-narrow' },
      field('Platforma nomi', name),
      checkbox("O'quvchilar o'zi ro'yxatdan o'ta oladi", registration, (on) => (registration = on)),
      field('Bitta o\'quvchi bitta mockni ko\'pi bilan', cap, "Qat'iy yuqori chegara. Har bir mockda bundan kam son belgilash mumkin."),
      h('div', { class: 'actions' }, saveBtn)
    ),
    h('div', { class: 'card form-narrow' },
      h('h3', { text: 'Parolni almashtirish' }),
      passwordForm()
    )
  );
}

function passwordForm() {
  const current = h('input', { class: 'input', type: 'password', autocomplete: 'current-password' });
  const next = h('input', { class: 'input', type: 'password', autocomplete: 'new-password' });
  return h('form', {
    class: 'form',
    onsubmit: async (e) => {
      e.preventDefault();
      try {
        await post('auth/password', { current: current.value, password: next.value });
        current.value = '';
        next.value = '';
        toast('Parol almashtirildi.', 'success');
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  }, field('Joriy parol', current), field('Yangi parol', next), h('div', { class: 'actions' }, h('button', { class: 'btn', type: 'submit' }, icon('lock'), 'Almashtirish')));
}

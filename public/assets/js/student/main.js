// O'quvchi ilovasi: kirish, bosh sahifa (mocklar), imtihon va natijalar.
// Sahifalar faqat manzil (#/...) o'zgarganda almashadi — hech qachon o'z-o'zidan qayta yuklanmaydi.

import { ApiError, get, post, setCsrf } from '../lib/api.js';
import { h, icon, mount } from '../lib/dom.js';
import { formatDate, formatScore, levelLabel } from '../lib/text.js';
import { errorBox, modal, spinner, toast } from '../lib/ui.js';
import { SECTION, T } from '../lib/uz.js';
import { ExamApp } from './exam/app.js';

const root = document.getElementById('app');
let session = { user: null, site: { name: 'Multilevel Mock', registration_open: true } };
let page = null;

async function boot() {
  try {
    const me = await get('auth/me');
    setCsrf(me.csrf);
    session = me;
  } catch (err) {
    root.replaceChildren(h('div', { class: 'center-screen' }, errorBox(err.message, () => boot())));
    return;
  }
  document.title = session.site.name;
  window.addEventListener('hashchange', route);
  route();
}

function destroyPage() {
  if (page && page.destroy) page.destroy();
  page = null;
}

function route() {
  destroyPage();
  const hash = location.hash.replace(/^#/, '') || '/';
  const [path, query = ''] = hash.split('?');
  const parts = path.split('/').filter(Boolean);
  const params = new URLSearchParams(query);

  if (!session.user) {
    if (parts[0] === 'register') renderRegister();
    else renderLogin();
    return;
  }
  if (session.user.role !== 'student') {
    renderStaffNotice();
    return;
  }
  if (parts[0] === 'exam' && parts[1]) {
    page = new ExamApp(root, Number(parts[1]), { intent: params.get('intent') || '', onExit: () => go('/') });
    page.init();
    return;
  }
  if (parts[0] === 'result' && parts[1]) {
    renderResult(Number(parts[1]));
    return;
  }
  renderDashboard();
}

function go(path) {
  const target = '#' + path;
  if (location.hash === target) route();
  else location.hash = target;
}

// ---------------------------------------------------------------------
// Kirish va ro'yxatdan o'tish
// ---------------------------------------------------------------------

function authLayout(title, form, footer) {
  root.replaceChildren(h('div', { class: 'auth-screen' },
    h('div', { class: 'auth-card card' },
      h('div', { class: 'brand' }, h('span', { class: 'brand-mark', text: 'M' }), h('span', { text: session.site.name })),
      h('h1', { text: title }),
      h('p', { class: 'muted', text: T.loginSubtitle }),
      form,
      footer
    )
  ));
}

function field(label, input) {
  return h('label', { class: 'field' }, h('span', { class: 'field-label', text: label }), input);
}

function renderLogin() {
  const login = h('input', { class: 'input', name: 'login', autocomplete: 'username', required: true, placeholder: '+998 90 123 45 67' });
  const password = h('input', { class: 'input', name: 'password', type: 'password', autocomplete: 'current-password', required: true });
  const error = h('p', { class: 'form-error', role: 'alert' });
  const button = h('button', { class: 'btn btn-primary btn-block', type: 'submit' }, T.signIn);
  const form = h('form', {
    class: 'form',
    onsubmit: async (e) => {
      e.preventDefault();
      error.textContent = '';
      button.disabled = true;
      try {
        const res = await post('auth/login', { login: login.value, password: password.value });
        setCsrf(res.csrf);
        session.user = res.user;
        if (res.user.role !== 'student') {
          location.href = 'admin/';
          return;
        }
        go('/');
      } catch (err) {
        error.textContent = err.message;
      } finally {
        button.disabled = false;
      }
    },
  }, field(T.phoneOrLogin, login), field(T.password, password), error, button);
  authLayout(T.loginTitle, form, session.site.registration_open
    ? h('p', { class: 'auth-footer' }, T.noAccount + ' ', h('a', { href: '#/register' }, T.register))
    : null);
  login.focus();
}

function renderRegister() {
  const name = h('input', { class: 'input', autocomplete: 'name', required: true, placeholder: 'Aliyev Vali' });
  const phone = h('input', { class: 'input', autocomplete: 'tel', required: true, inputmode: 'tel', placeholder: '+998 90 123 45 67' });
  const password = h('input', { class: 'input', type: 'password', autocomplete: 'new-password', required: true, minlength: '6' });
  const error = h('p', { class: 'form-error', role: 'alert' });
  const button = h('button', { class: 'btn btn-primary btn-block', type: 'submit' }, T.register);
  const form = h('form', {
    class: 'form',
    onsubmit: async (e) => {
      e.preventDefault();
      error.textContent = '';
      button.disabled = true;
      try {
        const res = await post('auth/register', { full_name: name.value, phone: phone.value, password: password.value });
        setCsrf(res.csrf);
        session.user = res.user;
        go('/');
      } catch (err) {
        error.textContent = err.message;
      } finally {
        button.disabled = false;
      }
    },
  }, field(T.fullName, name), field(T.phone, phone), field(T.newPassword, password), error, button);
  authLayout(T.register, form, h('p', { class: 'auth-footer' }, T.haveAccount + ' ', h('a', { href: '#/login' }, T.signIn)));
  name.focus();
}

async function logout() {
  try {
    await post('auth/logout');
  } catch {
    /* baribir chiqamiz */
  }
  session.user = null;
  const me = await get('auth/me').catch(() => null);
  if (me) setCsrf(me.csrf);
  go('/login');
}

function renderStaffNotice() {
  root.replaceChildren(h('div', { class: 'center-screen' },
    h('div', { class: 'card card-narrow' },
      h('h2', { text: `${session.user.full_name}` }),
      h('p', { text: 'Siz xodim sifatida kirdingiz. Boshqaruv paneliga o\'ting.' }),
      h('div', { class: 'actions' },
        h('button', { class: 'btn', type: 'button', onclick: logout }, T.logout),
        h('a', { class: 'btn btn-primary', href: 'admin/' }, 'Boshqaruv paneli')
      )
    )
  ));
}

// ---------------------------------------------------------------------
// Bosh sahifa
// ---------------------------------------------------------------------

function topbar() {
  return h('header', { class: 'topbar' },
    h('a', { class: 'brand', href: '#/' }, h('span', { class: 'brand-mark', text: 'M' }), h('span', { text: session.site.name })),
    h('div', { class: 'topbar-user' },
      icon('user'),
      h('span', { text: session.user.full_name }),
      h('button', { class: 'btn btn-ghost btn-sm', type: 'button', onclick: logout }, icon('logout'), T.logout)
    )
  );
}

async function startMock(id, button) {
  button.disabled = true;
  try {
    const res = await post(`student/mocks/${id}/start`);
    go(`/exam/${res.attempt_id}`);
  } catch (err) {
    button.disabled = false;
    if (err instanceof ApiError && err.code === 'other_active' && err.data.attempt_id) {
      const open = await modal({
        title: 'Yakunlanmagan imtihon',
        body: err.message,
        actions: [{ label: T.cancel, value: false }, { label: T.resume, value: true, variant: 'primary' }],
      });
      if (open) go(`/exam/${err.data.attempt_id}`);
      return;
    }
    modal({ title: 'Boshlab bo\'lmadi', body: err.message });
  }
}

function mockCard(mock) {
  const active = mock.active_attempt;
  let action;
  if (active && active.stage === 'S') {
    action = h('a', { class: 'btn btn-primary', href: `#/exam/${active.id}?intent=speaking` }, icon('mic'), T.startSpeaking);
  } else if (active) {
    action = h('a', { class: 'btn btn-primary', href: `#/exam/${active.id}` }, T.resume);
  } else {
    const btn = h('button', { class: 'btn btn-primary', type: 'button', disabled: !mock.can_start }, T.start);
    btn.onclick = () => startMock(mock.id, btn);
    action = btn;
  }
  return h('article', { class: `mock-card ${mock.status === 'frozen' ? 'is-frozen' : ''}` },
    h('div', { class: 'mock-card-head' },
      h('h3', { text: mock.title }),
      h('span', { class: 'attempts-pill', text: T.attemptsUsed(mock.used_attempts, mock.max_attempts) })
    ),
    mock.description ? h('p', { class: 'muted', text: mock.description }) : null,
    h('div', { class: 'section-chips' }, mock.sections.map((c) => h('span', { class: 'chip', text: SECTION[c] }))),
    mock.reason && !active ? h('p', { class: 'mock-reason', text: mock.reason }) : null,
    mock.available_to ? h('p', { class: 'muted small', text: `Muddat: ${formatDate(mock.available_to)} gacha` }) : null,
    h('div', { class: 'mock-card-actions' }, action)
  );
}

async function renderDashboard() {
  const mocksBox = h('div', { class: 'mock-grid' }, spinner());
  const resultsBox = h('div', null, spinner());
  const randomBtn = h('button', { class: 'btn', type: 'button', title: T.randomHint }, T.randomMock);
  randomBtn.onclick = async () => {
    randomBtn.disabled = true;
    try {
      const res = await post('student/random');
      go(`/exam/${res.attempt_id}`);
    } catch (err) {
      randomBtn.disabled = false;
      modal({ title: T.randomMock, body: err.message });
    }
  };
  root.replaceChildren(h('div', { class: 'page' },
    topbar(),
    h('main', { class: 'container' },
      h('div', { class: 'page-head' }, h('h1', { text: T.myMocks }), randomBtn),
      mocksBox,
      h('h2', { class: 'section-title', text: T.myResults }),
      resultsBox
    )
  ));

  try {
    const [{ mocks }, { attempts }] = await Promise.all([get('student/mocks'), get('student/attempts')]);
    mocksBox.replaceChildren(...(mocks.length ? mocks.map(mockCard) : [h('p', { class: 'empty', text: T.noMocks })]));
    resultsBox.replaceChildren(attempts.length ? resultsTable(attempts) : h('p', { class: 'empty', text: T.noResults }));
  } catch (err) {
    mocksBox.replaceChildren(errorBox(err.message, () => renderDashboard()));
    resultsBox.replaceChildren();
  }
}

function statusLabel(status) {
  return { in_progress: T.statusInProgress, completed: T.statusCompleted, terminated: T.statusTerminated }[status] || status;
}

function resultsTable(attempts) {
  return h('div', { class: 'table-wrap' },
    h('table', { class: 'table' },
      h('thead', null, h('tr', null, ['Mock', 'Urinish', 'Holat', 'Sana', T.overall, T.level, ''].map((t) => h('th', { text: t })))),
      h('tbody', null, attempts.map((a) => h('tr', null,
        h('td', { text: a.title }),
        h('td', { text: T.attemptN(a.attempt_no) }),
        h('td', null, h('span', { class: `status status-${a.status}`, text: statusLabel(a.status) })),
        h('td', { text: formatDate(a.finished_at || a.started_at) }),
        h('td', { text: a.results_visible ? formatScore(a.overall) : '—' }),
        h('td', null, a.results_visible && a.level ? h('span', { class: `level level-${a.level}`, text: levelLabel(a.level) }) : '—'),
        h('td', null, a.status !== 'in_progress'
          ? h('a', { class: 'btn btn-sm', href: `#/result/${a.id}` }, a.results_visible ? T.viewResult : T.resultsHidden)
          : null)
      )))
    )
  );
}

// ---------------------------------------------------------------------
// Natija
// ---------------------------------------------------------------------

async function renderResult(id) {
  const body = h('div', null, spinner());
  root.replaceChildren(h('div', { class: 'page' }, topbar(), h('main', { class: 'container' },
    h('a', { class: 'back-link', href: '#/' }, icon('left'), T.toDashboard),
    body
  )));
  try {
    const r = await get(`student/attempts/${id}/result`);
    if (!r.visible) {
      body.replaceChildren(h('div', { class: 'card' }, h('h1', { text: r.title }), h('p', { text: T.resultsHidden + '.' })));
      return;
    }
    const cards = r.sections.map((code) => {
      const s = r.scores[code];
      let detail = '';
      if (code === 'L' || code === 'R') detail = s.raw !== null ? `${s.raw}/35 ${T.raw}` : '';
      if (code === 'W') detail = s.raw !== null ? `${formatScore(s.raw)}/16` : T.pending;
      if (code === 'S') detail = s.raw !== null ? `${formatScore(s.raw)}/21` : T.pending;
      return h('div', { class: 'score-card' },
        h('span', { class: 'score-name', text: SECTION[code] }),
        h('span', { class: 'score-value', text: formatScore(s.score) }),
        h('span', { class: 'score-detail', text: detail })
      );
    });
    const parts = ['L', 'R'].filter((c) => r.parts[c]).map((c) => h('div', { class: 'card' },
      h('h3', { text: `${SECTION[c]} — ${T.byParts.toLowerCase()}` }),
      h('div', { class: 'part-bars' }, r.parts[c].map((p) => h('div', { class: 'part-bar' },
        h('span', { class: 'pb-title', text: p.title }),
        h('span', { class: 'pb-track' }, h('span', { class: 'pb-fill', style: { width: `${p.total ? (p.correct / p.total) * 100 : 0}%` } })),
        h('span', { class: 'pb-value', text: `${p.correct}/${p.total}` })
      )))
    ));
    const comments = Object.entries(r.comments || {}).map(([skill, list]) => h('div', { class: 'card' },
      h('h3', { text: `${T.expertComments}: ${SECTION[skill]}` }),
      list.map((c) => h('p', { class: 'comment', text: c }))
    ));
    mount(body,
      h('div', { class: 'card result-hero' },
        h('div', null, h('p', { class: 'eyebrow', text: T.attemptN(r.attempt_no) }), h('h1', { text: r.title }),
          r.status === 'terminated' ? h('p', { class: 'status status-terminated', text: T.statusTerminated }) : null),
        h('div', { class: 'overall' },
          h('span', { class: 'overall-label', text: T.overall }),
          h('span', { class: 'overall-value', text: formatScore(r.overall) }),
          r.level ? h('span', { class: `level level-${r.level}`, text: levelLabel(r.level) }) : null
        )
      ),
      h('div', { class: 'score-grid' }, cards),
      r.method === 'provisional' ? h('p', { class: 'muted small', text: T.provisionalNote }) : null,
      parts,
      comments
    );
  } catch (err) {
    body.replaceChildren(errorBox(err.message, () => renderResult(id)));
  }
}

window.addEventListener('unhandledrejection', (e) => {
  if (e.reason instanceof ApiError && e.reason.status === 401) {
    session.user = null;
    toast('Sessiya tugadi. Qayta kiring.', 'warn');
    go('/login');
  }
});

boot();

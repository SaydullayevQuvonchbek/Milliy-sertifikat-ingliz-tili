// Boshqaruv paneli: administratorlar va ekspertlar uchun.

import { get, post, setCsrf } from '../lib/api.js';
import { h, icon } from '../lib/dom.js';
import { errorBox, spinner } from '../lib/ui.js';
import { ROLE, field } from './common.js';
import { MockEditor } from './editor.js';
import { gradingPage } from './grading.js';
import { dashboardPage, livePage, mocksPage, settingsPage } from './pages.js';
import { attemptPage, resultsPage } from './results.js';
import { usersPage } from './users.js';

const root = document.getElementById('app');
let session = null;
let page = null;
let content = null;
let nav = null;
let currentHash = location.hash;
let navigatingBack = false;

const MENU = [
  { href: '#/', label: 'Bosh panel', roles: ['admin'] },
  { href: '#/mocks', label: 'Mocklar', roles: ['admin'] },
  { href: '#/live', label: 'Jonli nazorat', roles: ['admin'] },
  { href: '#/grading', label: 'Tekshiruv', roles: ['admin', 'expert'] },
  { href: '#/users', label: 'Foydalanuvchilar', roles: ['admin'] },
  { href: '#/settings', label: 'Sozlamalar', roles: ['admin'] },
];

async function boot() {
  try {
    session = await get('auth/me');
    setCsrf(session.csrf);
  } catch (err) {
    root.replaceChildren(h('div', { class: 'center-screen' }, errorBox(err.message, boot)));
    return;
  }
  if (!session.user) {
    renderLogin();
    return;
  }
  if (session.user.role === 'student') {
    root.replaceChildren(h('div', { class: 'center-screen' }, h('div', { class: 'card card-narrow' },
      h('h2', { text: 'Ruxsat yo\'q' }),
      h('p', { text: "Bu bo'lim faqat administrator va ekspertlar uchun." }),
      h('div', { class: 'actions' }, h('a', { class: 'btn btn-primary', href: '../' }, "O'quvchi sahifasi"))
    )));
    return;
  }
  renderLayout();
  window.addEventListener('hashchange', onHashChange);
  route();
}

function renderLogin() {
  const login = h('input', { class: 'input', autocomplete: 'username', required: true });
  const password = h('input', { class: 'input', type: 'password', autocomplete: 'current-password', required: true });
  const error = h('p', { class: 'form-error', role: 'alert' });
  const button = h('button', { class: 'btn btn-primary btn-block', type: 'submit' }, 'Kirish');
  root.replaceChildren(h('div', { class: 'auth-screen' }, h('div', { class: 'auth-card card' },
    h('div', { class: 'brand' }, h('span', { class: 'brand-mark', text: 'M' }), h('span', { text: session.site.name })),
    h('h1', { text: 'Boshqaruv paneli' }),
    h('p', { class: 'muted', text: 'Administrator yoki ekspert sifatida kiring.' }),
    h('form', {
      class: 'form',
      onsubmit: async (e) => {
        e.preventDefault();
        button.disabled = true;
        error.textContent = '';
        try {
          const res = await post('auth/login', { login: login.value, password: password.value });
          setCsrf(res.csrf);
          await boot();
        } catch (err) {
          error.textContent = err.message;
        } finally {
          button.disabled = false;
        }
      },
    }, field('Login', login), field('Parol', password), error, button)
  )));
  login.focus();
}

function renderLayout() {
  const role = session.user.role;
  nav = h('nav', { class: 'side-nav' }, MENU.filter((m) => m.roles.includes(role)).map((m) => h('a', { href: m.href, class: 'side-link' }, m.label)));
  content = h('main', { class: 'admin-content' });
  root.replaceChildren(h('div', { class: 'admin' },
    h('aside', { class: 'sidebar' },
      h('a', { class: 'brand', href: role === 'admin' ? '#/' : '#/grading' }, h('span', { class: 'brand-mark', text: 'M' }), h('span', { text: session.site.name })),
      nav,
      h('div', { class: 'side-user' },
        h('div', null, h('strong', { text: session.user.full_name }), h('div', { class: 'muted small', text: ROLE[role] })),
        h('button', {
          class: 'btn btn-ghost btn-sm', type: 'button',
          onclick: async () => {
            await post('auth/logout').catch(() => {});
            location.hash = '';
            location.reload();
          },
        }, icon('logout'), 'Chiqish')
      )
    ),
    content
  ));
}

function navigate(path, replace = false) {
  const target = '#' + path;
  if (replace) {
    history.replaceState(null, '', target);
    currentHash = target;
    route();
  } else if (location.hash === target) route();
  else location.hash = target;
}

async function onHashChange() {
  if (navigatingBack) {
    navigatingBack = false;
    return;
  }
  if (page && page.canLeave && !(await page.canLeave())) {
    navigatingBack = true;
    location.hash = currentHash;
    return;
  }
  currentHash = location.hash;
  route();
}

function route() {
  if (page && page.destroy) page.destroy();
  page = null;
  const hash = location.hash.replace(/^#/, '') || '/';
  const parts = hash.split('?')[0].split('/').filter(Boolean);
  const role = session.user.role;

  nav.querySelectorAll('.side-link').forEach((a) => {
    const target = a.getAttribute('href').replace(/^#/, '');
    a.classList.toggle('active', target === '/' ? parts.length === 0 : hash.startsWith(target));
  });

  if (role === 'expert' && parts[0] !== 'grading') {
    navigate('/grading', true);
    return;
  }
  content.replaceChildren(spinner());
  content.scrollTop = 0;
  const fail = (err) => content.replaceChildren(errorBox(err.message || String(err), route));

  switch (parts[0]) {
    case undefined:
      dashboardPage(content).catch(fail);
      break;
    case 'mocks':
      if (!parts[1]) mocksPage(content, navigate).catch(fail);
      else if (parts[2] === 'results') resultsPage(content, Number(parts[1]), navigate).catch(fail);
      else {
        const editor = new MockEditor(content, parts[1] === 'new' ? null : Number(parts[1]), { navigate });
        page = editor;
        editor.load().catch(fail);
      }
      break;
    case 'attempts':
      attemptPage(content, Number(parts[1]), navigate).catch(fail);
      break;
    case 'users':
      usersPage(content, navigate);
      break;
    case 'grading':
      gradingPage(content);
      break;
    case 'live':
      page = livePage(content, navigate);
      break;
    case 'settings':
      settingsPage(content).catch(fail);
      break;
    default:
      content.replaceChildren(h('p', { class: 'empty', text: 'Sahifa topilmadi.' }));
  }
}

boot();

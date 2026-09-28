// Foydalanuvchilar: o'quvchilar, ekspertlar va administratorlar.

import { get, post, put } from '../lib/api.js';
import { h } from '../lib/dom.js';
import { formatDate, formatScore, levelLabel } from '../lib/text.js';
import { confirmDialog, errorBox, modal, spinner, toast } from '../lib/ui.js';
import { ATTEMPT_STATUS, ROLE, field, pageHeader, statusBadge, table } from './common.js';

export function usersPage(root, navigate) {
  const state = { role: 'student', q: '', page: 1 };
  const listBox = h('div');
  const search = h('input', { class: 'input', type: 'search', placeholder: 'Ism yoki telefon bo\'yicha qidirish…' });
  let timer = null;
  search.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      state.q = search.value.trim();
      state.page = 1;
      load();
    }, 300);
  });
  const roleTabs = h('nav', { class: 'tabs' }, Object.entries({ student: "O'quvchilar", expert: 'Ekspertlar', admin: 'Administratorlar' }).map(([role, label]) => h('button', {
    type: 'button', class: `tab ${state.role === role ? 'active' : ''}`,
    onclick: (e) => {
      state.role = role;
      state.page = 1;
      roleTabs.querySelectorAll('.tab').forEach((t) => t.classList.toggle('active', t === e.currentTarget));
      load();
    },
  }, label)));

  const load = async () => {
    listBox.replaceChildren(spinner());
    try {
      const params = new URLSearchParams({ role: state.role, q: state.q, page: String(state.page) });
      const data = await get(`admin/users?${params}`);
      const pages = Math.max(1, Math.ceil(data.total / data.per_page));
      listBox.replaceChildren(
        table([
          { title: 'F.I.Sh.', render: (u) => h('strong', { text: u.full_name }) },
          { title: 'Login', render: (u) => u.login },
          { title: 'Holat', render: (u) => (u.status === 'active' ? h('span', { class: 'status status-completed', text: 'Faol' }) : h('span', { class: 'status status-terminated', text: 'Bloklangan' })) },
          { title: 'Urinishlar', class: 'num', render: (u) => u.attempts },
          { title: "Qo'shilgan", render: (u) => formatDate(u.created_at) },
          { title: 'Oxirgi kirish', render: (u) => (u.last_login_at ? formatDate(u.last_login_at) : '—') },
          {
            title: '',
            render: (u) => h('div', { class: 'row-actions' },
              state.role === 'student' ? h('button', { class: 'btn btn-sm', type: 'button', onclick: () => showAttempts(u, navigate) }, 'Urinishlar') : null,
              h('button', { class: 'btn btn-sm', type: 'button', onclick: () => editUser(u, load) }, 'Tahrirlash'),
              h('button', { class: 'btn btn-sm', type: 'button', onclick: () => resetPassword(u) }, 'Parol'),
              h('button', {
                class: `btn btn-sm ${u.status === 'active' ? 'btn-ghost danger' : 'btn-success'}`, type: 'button',
                onclick: async () => {
                  const block = u.status === 'active';
                  if (!(await confirmDialog(block ? 'Bloklaysizmi?' : 'Blokdan chiqarasizmi?', `${u.full_name} (${u.login})`, 'Tasdiqlash', block ? 'danger' : 'primary'))) return;
                  try {
                    await put(`admin/users/${u.id}`, { status: block ? 'blocked' : 'active' });
                    load();
                  } catch (err) {
                    toast(err.message, 'error');
                  }
                },
              }, u.status === 'active' ? 'Bloklash' : 'Faollashtirish')
            ),
          },
        ], data.users, { empty: 'Topilmadi.' }),
        pages > 1 ? h('div', { class: 'pager' },
          h('button', { class: 'btn btn-sm', type: 'button', disabled: state.page <= 1, onclick: () => { state.page -= 1; load(); } }, '‹ Oldingi'),
          h('span', { class: 'muted', text: `${state.page} / ${pages} (${data.total} ta)` }),
          h('button', { class: 'btn btn-sm', type: 'button', disabled: state.page >= pages, onclick: () => { state.page += 1; load(); } }, 'Keyingi ›')
        ) : h('p', { class: 'muted small', text: `Jami: ${data.total} ta` })
      );
    } catch (err) {
      listBox.replaceChildren(errorBox(err.message, load));
    }
  };

  root.replaceChildren(
    pageHeader('Foydalanuvchilar',
      h('button', { class: 'btn', type: 'button', onclick: () => bulkAdd(load) }, "Ro'yxat bilan qo'shish"),
      h('button', { class: 'btn btn-primary', type: 'button', onclick: () => addUser(state.role, load) }, "+ Qo'shish")
    ),
    roleTabs,
    h('div', { class: 'toolbar' }, search),
    listBox
  );
  load();
}

function credentialsView(rows) {
  return h('div', null,
    h('p', { class: 'muted small', text: "Parollar faqat hozir ko'rsatiladi. Chop eting yoki o'quvchilarga yetkazing." }),
    h('div', { class: 'table-wrap printable' }, h('table', { class: 'table' },
      h('thead', null, h('tr', null, ['F.I.Sh.', 'Login', 'Parol'].map((t) => h('th', { text: t })))),
      h('tbody', null, rows.map((r) => h('tr', null, h('td', { text: r.full_name }), h('td', { text: r.login }), h('td', null, h('code', { text: r.password })))))
    )),
    h('div', { class: 'actions' }, h('button', { class: 'btn', type: 'button', onclick: () => window.print() }, 'Chop etish'))
  );
}

async function addUser(role, reload) {
  const name = h('input', { class: 'input', autocomplete: 'off' });
  const login = h('input', { class: 'input', autocomplete: 'off', placeholder: role === 'student' ? '+998 90 123 45 67' : 'masalan, ekspert1' });
  const password = h('input', { class: 'input', autocomplete: 'new-password', placeholder: "Bo'sh qoldirilsa, avtomatik yaratiladi" });
  const roleSelect = h('select', { class: 'input' }, Object.entries(ROLE).map(([r, label]) => h('option', { value: r, selected: r === role }, label)));
  const body = h('div', { class: 'form' },
    field('Rol', roleSelect),
    field('Ism va familiya', name),
    field('Login (o\'quvchi uchun — telefon raqami)', login),
    field('Parol', password)
  );
  const ok = await modal({ title: "Foydalanuvchi qo'shish", body, actions: [{ label: 'Bekor qilish', value: false }, { label: "Qo'shish", value: true, variant: 'primary' }] });
  if (!ok) return;
  try {
    const res = await post('admin/users', { role: roleSelect.value, full_name: name.value, login: login.value, password: password.value });
    modal({ title: "Qo'shildi", body: credentialsView([{ full_name: res.user.full_name, login: res.user.login, password: res.password }]) });
    reload();
  } catch (err) {
    toast(err.message, 'error', 6000);
  }
}

async function bulkAdd(reload) {
  const area = h('textarea', { class: 'input mono', rows: 10, placeholder: "Aliyev Vali; +998901234567\nKarimova Nodira; 90 765 43 21" });
  const ok = await modal({
    title: "O'quvchilarni ro'yxat bilan qo'shish",
    body: h('div', { class: 'form' }, h('p', { class: 'muted small', text: "Har qatorda: Ism Familiya; telefon. Excel'dan ikki ustunni nusxalab qo'yish ham mumkin. Parollar avtomatik yaratiladi." }), area),
    actions: [{ label: 'Bekor qilish', value: false }, { label: "Qo'shish", value: true, variant: 'primary' }],
    wide: true,
  });
  if (!ok) return;
  try {
    const res = await post('admin/users/bulk', { text: area.value });
    modal({
      title: `Qo'shildi: ${res.created.length} ta`,
      wide: true,
      body: h('div', null,
        res.errors.length ? h('div', { class: 'alert alert-warn' }, h('div', null, h('strong', { text: `Qo'shilmadi: ${res.errors.length} ta qator` }), h('ul', null, res.errors.map((e) => h('li', { text: `${e.line}-qator (${e.text}): ${e.message}` }))))) : null,
        res.created.length ? credentialsView(res.created) : null
      ),
    });
    reload();
  } catch (err) {
    toast(err.message, 'error');
  }
}

async function editUser(user, reload) {
  const name = h('input', { class: 'input', value: user.full_name });
  const login = h('input', { class: 'input', value: user.login });
  const ok = await modal({
    title: 'Tahrirlash',
    body: h('div', { class: 'form' }, field('Ism va familiya', name), field('Login', login)),
    actions: [{ label: 'Bekor qilish', value: false }, { label: 'Saqlash', value: true, variant: 'primary' }],
  });
  if (!ok) return;
  try {
    await put(`admin/users/${user.id}`, { full_name: name.value, login: login.value });
    toast('Saqlandi.', 'success');
    reload();
  } catch (err) {
    toast(err.message, 'error');
  }
}

async function resetPassword(user) {
  if (!(await confirmDialog('Yangi parol yaratilsinmi?', `${user.full_name} (${user.login}) uchun yangi parol yaratiladi. Eski parol ishlamay qoladi.`, 'Yaratish'))) return;
  try {
    const res = await post(`admin/users/${user.id}/password`, {});
    modal({ title: 'Yangi parol', body: credentialsView([{ full_name: user.full_name, login: user.login, password: res.password }]) });
  } catch (err) {
    toast(err.message, 'error');
  }
}

async function showAttempts(user, navigate) {
  try {
    const { attempts } = await get(`admin/users/${user.id}/attempts`);
    const go = await modal({
      title: `${user.full_name}: urinishlar`,
      wide: true,
      body: table([
        { title: 'Mock', render: (a) => a.title },
        { title: 'Urinish', render: (a) => `${a.attempt_no}` },
        { title: 'Holat', render: (a) => statusBadge(a.status, ATTEMPT_STATUS) },
        { title: 'Umumiy', render: (a) => formatScore(a.overall) },
        { title: 'Daraja', render: (a) => (a.level ? levelLabel(a.level) : '—') },
        { title: 'Qoidabuzarlik', render: (a) => a.violations },
        { title: '', render: (a) => h('a', { class: 'btn btn-sm', href: `#/attempts/${a.id}` }, 'Batafsil') },
      ], attempts, { empty: "Urinishlar yo'q." }),
    });
    if (go) navigate(go);
  } catch (err) {
    toast(err.message, 'error');
  }
}

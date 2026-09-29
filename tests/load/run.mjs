// Yuklama sinovi: ko'p o'quvchi bir vaqtda Reading imtihonini ishlaydi (kirish, boshlash, saqlash, yakunlash, natija).
//
//   node tests/load/run.mjs [--students 100] [--seconds 40] [--workers 8] [--sync 3]
//   MOCK_LOAD_DB=mysql node tests/load/run.mjs ...     (MOCK_TEST_MYSQL_* muhit o'zgaruvchilari, baza bo'sh bo'lishi kerak)
//
// Vaqtinchalik baza va PHP serverini o'zi ko'taradi (PHP_CLI_SERVER_WORKERS — parallel ishchilar; bu Apache/PHP-FPM
// ga qaraganda soddaroq, lekin bir vaqtdagi so'rovlar va baza qulflarini haqiqatan sinaydi).
// Har o'quvchi --sync soniyada bir marta javoblarni yuboradi (brauzerdagi saqlash davri ~ shunday). Chegaralar:
// xatolar 0 ta, sync p95 < 1500 ms, barcha urinishlar yakunlanib, ball hisoblangan bo'lishi kerak.

import { execFileSync, spawn } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const args = Object.fromEntries(
  process.argv.slice(2).reduce((acc, item, i, all) => (item.startsWith('--') ? [...acc, [item.slice(2), all[i + 1]]] : acc), []),
);
const STUDENTS = Number(args.students || 100);
const SECONDS = Number(args.seconds || 40);
const WORKERS = Number(args.workers || 8);
const SYNC_EVERY = Number(args.sync || 3);
const QUESTIONS = 35;

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const dir = mkdtempSync(path.join(tmpdir(), 'mlmock-load-'));
const useMysql = process.env.MOCK_LOAD_DB === 'mysql';
const mysql = {
  host: process.env.MOCK_TEST_MYSQL_HOST || '127.0.0.1',
  port: Number(process.env.MOCK_TEST_MYSQL_PORT || 3306),
  database: process.env.MOCK_TEST_MYSQL_DB || 'mlmock_load',
  username: process.env.MOCK_TEST_MYSQL_USER || 'mlmock',
  password: process.env.MOCK_TEST_MYSQL_PASS || 'mlmock',
};
const phpArray = (obj) => `[${Object.entries(obj).map(([k, v]) => `${JSON.stringify(k)} => ${JSON.stringify(v)}`).join(', ')}]`;
const config = path.join(dir, 'config.php');
writeFileSync(config, `<?php return [
  'db' => ${useMysql ? `['driver' => 'mysql', 'mysql' => ${phpArray(mysql)}]` : `['driver' => 'sqlite', 'sqlite_path' => ${JSON.stringify(path.join(dir, 'db.sqlite'))}]`},
  'storage_path' => ${JSON.stringify(dir)},
  'secure_cookies' => false,
  'debug' => ${process.env.MOCK_LOAD_DEBUG === '1' ? 'true' : 'false'},
];`);
const env = { ...process.env, MOCK_CONFIG: config, MOCK_TESTING: '1', PHP_CLI_SERVER_WORKERS: String(WORKERS) };
execFileSync('php', ['bin/install.php', '--admin-login=admin', '--admin-password=admin12345'], { cwd: root, env, stdio: 'ignore' });

const port = 9000 + Math.floor(Math.random() * 900);
const base = `http://127.0.0.1:${port}/api`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'bin/dev-router.php'], { cwd: root, env, stdio: 'ignore' });
process.on('exit', () => server.kill());

// ---- HTTP klient (har foydalanuvchi uchun alohida cookie va CSRF) ----
const stats = new Map();
const errors = [];
function record(name, ms, ok) {
  const s = stats.get(name) || { ms: [], fail: 0 };
  s.ms.push(ms);
  if (!ok) s.fail += 1;
  stats.set(name, s);
}
class Client {
  constructor(label) {
    this.label = label;
    this.cookies = new Map();
    this.csrf = '';
  }
  async call(method, url, body, name = `${method} ${url.replace(/\d+/g, ':id')}`) {
    const started = performance.now();
    let res;
    try {
      res = await fetch(`${base}/${url}`, {
        method,
        headers: {
          'content-type': 'application/json',
          'x-csrf-token': this.csrf,
          cookie: [...this.cookies].map(([k, v]) => `${k}=${v}`).join('; '),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
      });
    } catch (err) {
      record(name, performance.now() - started, false);
      errors.push(`${this.label} ${name}: ${err.message}`);
      throw err;
    }
    for (const line of res.headers.getSetCookie?.() || []) {
      const [pair] = line.split(';');
      const eq = pair.indexOf('=');
      this.cookies.set(pair.slice(0, eq), pair.slice(eq + 1));
    }
    const text = await res.text();
    let data = null;
    try {
      data = JSON.parse(text);
    } catch {
      // JSON emas
    }
    const ok = res.ok;
    record(name, performance.now() - started, ok);
    if (!ok) {
      errors.push(`${this.label} ${name}: ${res.status} ${text.slice(0, 160)}`);
      const err = new Error(`${name}: ${res.status}`);
      err.status = res.status;
      throw err;
    }
    if (data && data.csrf) this.csrf = data.csrf;
    return data;
  }
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---- Tayyorgarlik: admin mock yaratadi va o'quvchilarni qo'shadi ----
for (let i = 0; i < 50; i += 1) {
  try {
    await fetch(`${base}/auth/me`);
    break;
  } catch {
    await sleep(100);
  }
}
const admin = new Client('admin');
await admin.call('GET', 'auth/me');
await admin.call('POST', 'auth/login', { login: 'admin', password: 'admin12345' });

const blocks = Array.from({ length: QUESTIONS }, (_, i) => ({
  type: 'mcq', n: i + 1, prompt: `Question ${i + 1}: which option is correct?`, options: ['First option', 'Second option', 'Third option', 'Fourth option'], answer: 'ABCD'[i % 4],
}));
const created = await admin.call('POST', 'admin/mocks', {
  title: 'Yuklama sinovi',
  settings: { sections: ['R'], times: { reading: 3600 }, lockdown: { fullscreen: false, max_violations: 0 } },
  source: {
    listening: { review_sec: 0, parts: [] },
    reading: { parts: [{ title: 'Part 1', instructions: 'Choose the correct answer.', passage: { title: 'Load test', text: 'A short text.' }, blocks }] },
    writing: { parts: [] },
    speaking: { parts: [] },
  },
});
const mockId = created.mock.id;
await admin.call('POST', `admin/mocks/${mockId}/status`, { status: 'active' });

// Server vaqt chegarasiga yetsa "pending" qaytaradi — qolgan qatorlarni qayta yuboramiz.
let pending = Array.from({ length: STUDENTS }, (_, i) => `Talaba Sinov${i + 1}; +99890${String(10000000 + i).slice(1)}`);
const bulk = { created: [], errors: [] };
while (pending.length) {
  const res = await admin.call('POST', 'admin/users/bulk', { text: pending.join('\n') }).catch((err) => {
    console.error(errors.join('\n'));
    throw err;
  });
  bulk.created.push(...res.created);
  bulk.errors.push(...res.errors);
  if ((res.pending || []).length >= pending.length) throw new Error("Foydalanuvchi qo'shish oldinga siljimayapti");
  pending = res.pending || [];
}
if (bulk.created.length !== STUDENTS) throw new Error(`O'quvchilar yaratilmadi: ${bulk.created.length}/${STUDENTS} ${JSON.stringify(bulk.errors.slice(0, 2))}`);

// ---- O'quvchilar ----
let finished = 0;
async function student(user, index) {
  const c = new Client(`s${index}`);
  const clientId = `load-${index}-${Math.random().toString(36).slice(2, 10)}`;
  try {
    await c.call('GET', 'auth/me');
    await c.call('POST', 'auth/login', { login: user.login, password: user.password });
    await c.call('GET', 'student/mocks');
    const { attempt_id: id } = await c.call('POST', `student/mocks/${mockId}/start`, {});
    await c.call('POST', `exam/${id}/claim`, { client_id: clientId });
    await c.call('POST', `exam/${id}/section/start`, { client_id: clientId, section: 'R' });

    const answers = {};
    let seq = 0;
    const end = Date.now() + SECONDS * 1000;
    await sleep(Math.random() * SYNC_EVERY * 1000); // o'quvchilar bir vaqtda "chiqmasin"
    while (Date.now() < end) {
      const n = 1 + Math.floor(Math.random() * QUESTIONS);
      answers[n] = 'ABCD'[Math.floor(Math.random() * 4)];
      seq += 1;
      await c.call('POST', `exam/${id}/sync`, { client_id: clientId, section: 'R', seq, answers, events: [] });
      if (Math.random() < 0.15) await c.call('GET', `exam/${id}/state?client_id=${clientId}`, undefined, 'GET exam/:id/state');
      await sleep(SYNC_EVERY * 1000);
    }
    // Yakuniy javoblar (aniq ma'lum, natijani tekshirish uchun): hammasi to'g'ri.
    const finalAnswers = Object.fromEntries(Array.from({ length: QUESTIONS }, (_, i) => [i + 1, 'ABCD'[i % 4]]));
    await c.call('POST', `exam/${id}/section/finish`, { client_id: clientId, section: 'R', seq: seq + 1, answers: finalAnswers });
    const res = await c.call('GET', `student/attempts/${id}/result`);
    finished += 1;
    return { id, result: res };
  } catch (err) {
    return { error: err.message };
  }
}

console.log(`Yuklama: ${STUDENTS} o'quvchi, ${SECONDS} s, ${WORKERS} ishchi, saqlash har ${SYNC_EVERY} s, baza: ${useMysql ? 'MySQL' : 'SQLite'}`);
const started = Date.now();
const results = await Promise.all(bulk.created.map((u, i) => student(u, i)));
const elapsed = (Date.now() - started) / 1000;
const attemptsView = await admin.call('GET', `admin/mocks/${mockId}/attempts`).catch(() => ({ attempts: [] }));
server.kill();

// ---- Hisobot ----
const pct = (arr, p) => {
  const s = [...arr].sort((a, b) => a - b);
  return s[Math.min(s.length - 1, Math.floor((p / 100) * s.length))] || 0;
};
console.log(`\nDavomiylik: ${elapsed.toFixed(1)} s; yakunlagan o'quvchilar: ${finished}/${STUDENTS}\n`);
console.log('so\'rov'.padEnd(34), 'soni'.padStart(6), 'p50'.padStart(7), 'p95'.padStart(7), 'p99'.padStart(7), 'max'.padStart(7), 'xato'.padStart(6));
let totalRequests = 0;
let totalFail = 0;
for (const [name, s] of [...stats].sort((a, b) => b[1].ms.length - a[1].ms.length)) {
  totalRequests += s.ms.length;
  totalFail += s.fail;
  console.log(
    name.padEnd(34), String(s.ms.length).padStart(6),
    pct(s.ms, 50).toFixed(0).padStart(7), pct(s.ms, 95).toFixed(0).padStart(7), pct(s.ms, 99).toFixed(0).padStart(7),
    Math.max(...s.ms).toFixed(0).padStart(7), String(s.fail).padStart(6),
  );
}
console.log(`\nJami so'rov: ${totalRequests} (${(totalRequests / elapsed).toFixed(1)}/s), xato: ${totalFail}`);

// Natijani tekshirish: hamma to'g'ri javob bergan, Reading balli kutilganidek bo'lishi kerak.
const scores = results.filter((r) => r.result).map((r) => r.result);
const bad = results.filter((r) => r.error);
const sample = scores[0];
if (sample) console.log('Natija namunasi:', JSON.stringify(sample).slice(0, 300));

let failed = false;
const check = (ok, message) => {
  console.log(`${ok ? '✓' : '✗'} ${message}`);
  if (!ok) failed = true;
};
check(totalFail === 0 && bad.length === 0, `xatolar yo'q (${totalFail} so'rov xato, ${bad.length} o'quvchi to'xtagan)`);
check(finished === STUDENTS, `barcha o'quvchilar yakunladi (${finished}/${STUDENTS})`);
const scoredOk = attemptsView.attempts.filter((a) => a.status === 'completed' && Number(a.r_raw) === QUESTIONS).length;
check(scoredOk === STUDENTS, `Reading xom balli to'g'ri hisoblangan: ${scoredOk}/${STUDENTS} ta urinishda ${QUESTIONS}/${QUESTIONS}`);
check(pct(stats.get('POST exam/:id/sync')?.ms || [0], 95) < 1500, `sync p95 < 1500 ms (${pct(stats.get('POST exam/:id/sync')?.ms || [0], 95).toFixed(0)} ms)`);
if (errors.length) console.log('\nBirinchi xatolar:\n  ' + errors.slice(0, 8).join('\n  '));
rmSync(dir, { recursive: true, force: true });
process.exit(failed ? 1 : 0);

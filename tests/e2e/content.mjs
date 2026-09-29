// Tayyor mocklar (content/mocks/*) brauzerda: audio haqiqatan o'ynaladigan, davomiyligi bazadagi bilan mos,
// rasmlar ochiladi, o'quvchi Listening kontentini va audioni (Range bilan) oladi.
//   npm run test:content        (Playwright va PHP kerak)

import { execFileSync, spawn } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

let chromium;
try {
  ({ chromium } = await import('playwright'));
} catch {
  console.error("Playwright topilmadi. O'rnating: npm install");
  process.exit(1);
}

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const dir = mkdtempSync(path.join(tmpdir(), 'mlmock-content-'));
const config = path.join(dir, 'config.php');
writeFileSync(config, `<?php return [
  'db' => ['driver' => 'sqlite', 'sqlite_path' => ${JSON.stringify(path.join(dir, 'db.sqlite'))}],
  'storage_path' => ${JSON.stringify(dir)},
  'secure_cookies' => false,
  'debug' => true,
];`);
const env = { ...process.env, MOCK_CONFIG: config, MOCK_TESTING: '1' };
execFileSync('php', ['bin/install.php', '--admin-login=admin', '--admin-password=admin12345', '--content'], { cwd: root, env, stdio: 'inherit' });

const port = 7000 + Math.floor(Math.random() * 900);
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'bin/dev-router.php'], { cwd: root, env, stdio: 'ignore' });
for (let i = 0; i < 50; i += 1) {
  try {
    await fetch(`${base}/api/auth/me`);
    break;
  } catch {
    await new Promise((r) => setTimeout(r, 100));
  }
}

let failures = 0;
async function step(name, fn) {
  const started = Date.now();
  try {
    await fn();
    console.log(`  ✓ ${name} (${((Date.now() - started) / 1000).toFixed(1)} s)`);
  } catch (err) {
    failures += 1;
    console.log(`  ✗ ${name}\n      ${String(err && err.message ? err.message : err).split('\n').slice(0, 6).join('\n      ')}`);
  }
}
function assert(condition, message) {
  if (!condition) throw new Error(message);
}

const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM || undefined });
try {
  const ctx = await browser.newContext();
  const api = ctx.request;
  let csrf = (await (await api.get(`${base}/api/auth/me`)).json()).csrf;
  const call = async (method, url, data) => {
    const res = await api.fetch(`${base}/api/${url}`, { method, headers: { 'X-CSRF-Token': csrf }, data });
    const body = await res.json().catch(() => null);
    if (!res.ok()) throw new Error(`${method} ${url}: ${res.status()} ${JSON.stringify(body)}`);
    return body;
  };
  const login = await call('POST', 'auth/login', { login: 'admin', password: 'admin12345' });
  csrf = login.csrf;

  const page = await ctx.newPage();
  const pageErrors = [];
  page.on('pageerror', (e) => pageErrors.push(e.message));
  await page.goto(`${base}/assets/img/favicon.svg`);

  const { mocks } = await call('GET', 'admin/mocks');
  const content = mocks.filter((m) => /^Multilevel Mock \d+$/.test(m.title));
  await step('3 ta tayyor mock bazada va faol', async () => {
    assert(content.length >= 3, `Tayyor mocklar ${content.length} ta (kamida 3 kerak)`);
    for (const m of content) assert(m.status === 'active', `${m.title}: holati ${m.status}`);
  });

  for (const m of content) {
    const { mock } = await call('GET', `admin/mocks/${m.id}`);

    await step(`${m.title}: audio brauzerda ochiladi, davomiyligi mos`, async () => {
      const audio = mock.assets.filter((a) => a.kind === 'audio');
      assert(audio.length >= 26, `audio fayllar ${audio.length} ta (kamida 26: 18 Listening + 8 Speaking savol ovozi)`);
      const results = await page.evaluate(async (list) => {
        const out = [];
        for (const a of list) {
          const el = new Audio();
          el.preload = 'metadata';
          const result = await new Promise((resolve) => {
            el.onloadedmetadata = () => resolve({ id: a.id, duration: el.duration });
            el.onerror = () => resolve({ id: a.id, error: el.error ? el.error.code : 'xato' });
            setTimeout(() => resolve({ id: a.id, error: 'timeout' }), 15000);
            el.src = `/api/admin/assets/${a.id}`;
          });
          out.push(result);
        }
        return out;
      }, audio.map((a) => ({ id: a.id })));
      for (const r of results) {
        const asset = audio.find((a) => a.id === r.id);
        assert(!r.error, `audio #${r.id} (${asset.original_name}) ochilmadi: ${r.error}`);
        assert(Math.abs(r.duration - Number(asset.duration)) < 0.5, `${asset.original_name}: brauzerda ${r.duration.toFixed(2)} s, bazada ${asset.duration} s`);
        assert(r.duration > 1.5, `${asset.original_name}: juda qisqa (${r.duration.toFixed(2)} s)`);
      }
    });

    await step(`${m.title}: rasmlar ochiladi (Speaking)`, async () => {
      const images = mock.assets.filter((a) => a.kind === 'image');
      assert(images.length >= 3, `rasmlar ${images.length} ta (kamida 3)`);
      const sizes = await page.evaluate(async (list) => Promise.all(list.map((a) => new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve({ id: a.id, w: img.naturalWidth, h: img.naturalHeight });
        img.onerror = () => resolve({ id: a.id, error: true });
        img.src = `/api/admin/assets/${a.id}`;
      }))), images.map((a) => ({ id: a.id })));
      for (const s of sizes) assert(!s.error && s.w >= 1000 && s.h >= 600, `rasm #${s.id} ochilmadi yoki kichik: ${JSON.stringify(s)}`);
    });

    await step(`${m.title}: o'quvchi Listening kontentini va audioni (Range) oladi`, async () => {
      const sctx = await browser.newContext();
      const s = sctx.request;
      let scsrf = (await (await s.get(`${base}/api/auth/me`)).json()).csrf;
      const scall = async (method, url, data, headers = {}) => {
        const res = await s.fetch(`${base}/api/${url}`, { method, headers: { 'X-CSRF-Token': scsrf, ...headers }, data });
        return res;
      };
      const phone = `+99890${String(Math.floor(1000000 + Math.random() * 8999999))}`;
      const reg = await (await scall('POST', 'auth/register', { full_name: 'Sinov Talaba', phone, password: 'parol12345' })).json();
      scsrf = reg.csrf;
      const started = await (await scall('POST', `student/mocks/${m.id}/start`, {})).json();
      const id = started.attempt_id;
      const clientId = 'content-smoke';
      await scall('POST', `exam/${id}/claim`, { client_id: clientId });
      const state = await (await scall('POST', `exam/${id}/section/start`, { client_id: clientId, section: 'L' })).json();
      const section = state.section;
      assert(section && section.code === 'L' && section.content, "Listening kontenti kelmadi");
      assert(section.content.parts.length === 6, `qismlar ${section.content.parts.length} ta`);
      const text = JSON.stringify(section.content);
      assert(!/transcript/i.test(text), "o'quvchi kontentida transkript bor!");
      assert(!/"answer"/.test(text), "o'quvchi kontentida to'g'ri javob bor!");
      const first = section.audio[0];
      const res = await s.fetch(`${base}/api/exam/${id}/audio/${first.asset}`, { headers: { Range: 'bytes=0-1023' } });
      assert(res.status() === 206, `Range so'rovi ${res.status()} qaytardi (206 kerak)`);
      assert(res.headers()['content-type'] === 'audio/mpeg', `content-type: ${res.headers()['content-type']}`);
      assert((await res.body()).length === 1024, 'Range 1024 bayt qaytarmadi');
      await sctx.close();
    });
  }

  await step('brauzer xatolarisiz', async () => assert(pageErrors.length === 0, pageErrors.join('\n')));
} finally {
  await browser.close();
  server.kill();
}
if (failures) {
  console.log(`\n${failures} ta qadam muvaffaqiyatsiz.`);
  process.exit(1);
}
console.log('\nTayyor mocklar brauzerda muvaffaqiyatli o\'tdi.');

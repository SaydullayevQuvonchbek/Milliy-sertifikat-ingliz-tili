// Uchidan-uchigacha (E2E) test: haqiqiy brauzerda admin mock yaratadi, o'quvchi uni to'liq ishlaydi.
//   npm run test:e2e        (Playwright va PHP kerak)
//
// Tekshiriladi: ro'yxatdan o'tish, mock boshlash, to'liq ekran, Listening audiosi va avtomatik o'tish,
// qoidabuzarlik oynasi, sahifani yangilaganda javoblar saqlanishi, belgilash (highlight), Writing so'z
// hisoblagichi, Speaking yozuvi, admin natijalari, urinishlar soni va mockni muzlatish.

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
const dir = mkdtempSync(path.join(tmpdir(), 'mlmock-e2e-'));
const config = path.join(dir, 'config.php');
writeFileSync(config, `<?php return [
  'db' => ['driver' => 'sqlite', 'sqlite_path' => ${JSON.stringify(path.join(dir, 'db.sqlite'))}],
  'storage_path' => ${JSON.stringify(dir)},
  'secure_cookies' => false,
  'debug' => true,
];`);
const env = { ...process.env, MOCK_CONFIG: config, MOCK_TESTING: '1' };
execFileSync('php', ['bin/install.php', '--admin-login=admin', '--admin-password=admin12345'], { cwd: root, env, stdio: 'ignore' });

const port = 8000 + Math.floor(Math.random() * 900);
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
    throw err;
  }
}
function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function wav(seconds) {
  const rate = 8000;
  const n = Math.round(seconds * rate);
  const buf = Buffer.alloc(44 + n);
  buf.write('RIFF', 0);
  buf.writeUInt32LE(36 + n, 4);
  buf.write('WAVEfmt ', 8);
  buf.writeUInt32LE(16, 16);
  buf.writeUInt16LE(1, 20);
  buf.writeUInt16LE(1, 22);
  buf.writeUInt32LE(rate, 24);
  buf.writeUInt32LE(rate, 28);
  buf.writeUInt16LE(1, 32);
  buf.writeUInt16LE(8, 34);
  buf.write('data', 36);
  buf.writeUInt32LE(n, 40);
  for (let i = 0; i < n; i += 1) buf[44 + i] = 128 + Math.round(30 * Math.sin((2 * Math.PI * 440 * i) / rate));
  return buf;
}

const browser = await chromium.launch({
  executablePath: process.env.PLAYWRIGHT_CHROMIUM || undefined,
  args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'],
});
const pageErrors = [];

try {
  // ---------------- Admin: mock yaratish ----------------
  const adminCtx = await browser.newContext();
  const admin = adminCtx.request;
  let csrf = (await (await admin.get(`${base}/api/auth/me`)).json()).csrf;
  const call = async (method, url, data, multipart) => {
    const options = { method, headers: { 'X-CSRF-Token': csrf } };
    if (multipart) options.multipart = multipart;
    else if (data !== undefined && data !== null) options.data = data;
    const res = await admin.fetch(`${base}/api/${url}`, options);
    const body = await res.json().catch(() => null);
    if (!res.ok()) throw new Error(`${method} ${url}: ${res.status()} ${JSON.stringify(body)}`);
    return body;
  };
  let mockId;

  await step('admin tizimga kiradi va mock yaratadi', async () => {
    const login = await call('POST', 'auth/login', { login: 'admin', password: 'admin12345' });
    csrf = login.csrf;
    const created = await call('POST', 'admin/mocks', { title: 'E2E mock', max_attempts: 2 });
    mockId = created.mock.id;
    const asset = await call('POST', `admin/mocks/${mockId}/assets`, null, {
      kind: 'audio',
      duration: '2',
      file: { name: 'a.wav', mimeType: 'audio/wav', buffer: wav(2) },
    });
    const source = {
      listening: {
        review_sec: 3,
        parts: [{
          title: 'Part 1', instructions: 'Listen and answer.', preview_sec: 2, gap_sec: 1, plays: 2,
          tracks: [{ asset: asset.asset.id, duration: 2, label: 'Track' }],
          blocks: [
            { type: 'mcq', n: 1, prompt: 'Choose B.', options: ['One.', 'Two.', 'Three.'], answer: 'B' },
            { type: 'gap_text', title: 'Notes', max_words: 1, text: 'Platform [[2]].', answers: { 2: '7|seven' } },
          ],
        }],
      },
      reading: {
        parts: [{
          title: 'Part 1', instructions: 'Read the text.',
          passage: { title: 'A Park', text: 'The new park has a lake and a long path for walking. Dogs must be kept on a lead.' },
          blocks: [
            { type: 'tfng', n: 1, prompt: 'The park has a lake.', answer: 'TRUE' },
            { type: 'gap_text', title: 'Complete.', max_words: 1, text: 'Dogs must be kept on a [[2]].', answers: { 2: 'lead' } },
          ],
        }],
      },
      writing: { parts: [{ title: 'Part 1', context: 'Context.', tasks: [{ id: '1.1', title: 'Task 1.1', prompt: 'Write something.', min_words: 10, max_words: 0 }] }] },
      speaking: { parts: [{ id: '1.1', title: 'Part 1.1', questions: [{ no: 1, text: 'Say something.', prep_sec: 0, answer_sec: 4 }] }] },
    };
    const settings = {
      sections: ['L', 'R', 'W', 'S'],
      times: { reading: 180, writing: 180 },
      break_sec: 15,
      lockdown: { fullscreen: true, max_violations: 5, action: 'terminate' },
      grading: { raters: 1 },
      speaking: { mode: 'same_session' },
      results: 'instant',
    };
    const updated = await call('PUT', `admin/mocks/${mockId}`, { title: 'E2E mock', max_attempts: 2, source, settings });
    assert(updated.validation.errors.length === 0, 'tekshiruv xatolari: ' + JSON.stringify(updated.validation.errors));
    await call('POST', `admin/mocks/${mockId}/status`, { status: 'active' });
  });

  // ---------------- O'quvchi ----------------
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 820 } });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => pageErrors.push(e.message));
  page.on('console', (m) => m.type() === 'error' && !m.text().includes('Failed to load resource') && pageErrors.push(m.text()));
  page.on('dialog', (d) => d.accept());

  await step("o'quvchi ro'yxatdan o'tadi va mockni ko'radi", async () => {
    await page.goto(`${base}/#/register`);
    await page.getByLabel('Ism va familiya').fill('Sinov Talaba');
    await page.getByLabel('Telefon raqami').fill('90 123 45 67');
    await page.getByLabel('Parol (kamida 6 ta belgi)').fill('secret1');
    await page.getByRole('button', { name: "Ro'yxatdan o'tish" }).click();
    await page.locator('.mock-card', { hasText: 'E2E mock' }).waitFor();
    assert(await page.locator('.mock-card', { hasText: 'Urinishlar: 0/2' }).count() === 1, 'urinishlar soni ko\'rinmadi');
  });

  await step('qoidalar: audio oldindan yuklanadi, boshlash to\'liq ekranga o\'tkazadi', async () => {
    await page.locator('.mock-card', { hasText: 'E2E mock' }).getByRole('button', { name: 'Boshlash' }).click();
    await page.locator('.rules-card').waitFor();
    await page.locator('.preload.ok').waitFor({ timeout: 15000 });
    await page.getByLabel("Qoidalar bilan tanishdim va ularga roziman").check();
    await page.getByRole('button', { name: "Listening'ni boshlash" }).click();
    await page.locator('.exam .eh-section', { hasText: 'Listening' }).waitFor();
    const fs = await page.evaluate(() => Boolean(document.fullscreenElement));
    assert(fs, "to'liq ekran yoqilmadi");
  });

  await step('Listening: javoblar belgilanadi, navigator yangilanadi', async () => {
    await page.locator('#q-L-1 .opt', { hasText: 'Two.' }).click();
    await page.locator('#q-L-2 input').fill('seven');
    await page.waitForFunction(() => document.querySelectorAll('.np-q.answered').length === 2);
    await page.locator('.listen-status .ls-text', { hasText: /Eshittirilmoqda|ko'rib chiqing|Pauza|Boshlanishiga/ }).waitFor();
  });

  await step("to'liq ekrandan chiqish qoidabuzarlik sifatida bloklanadi", async () => {
    await page.evaluate(() => document.exitFullscreen());
    await page.locator('.lock-overlay').waitFor();
    const text = await page.locator('.lock-overlay').innerText();
    assert(text.includes('Qoidabuzarliklar: 1/5'), 'hisoblagich noto\'g\'ri: ' + text);
    await page.locator('.lock-overlay button').click();
    await page.locator('.lock-overlay').waitFor({ state: 'detached' });
  });

  await step('Listening vaqti tugagach avtomatik yakunlanadi va tanaffus boshlanadi', async () => {
    await page.locator('.break-card').waitFor({ timeout: 30000 });
    await page.getByRole('button', { name: "Reading bo'limini boshlash" }).click();
    await page.locator('.exam .eh-section', { hasText: 'Reading' }).waitFor();
  });

  await step("Reading: javob, belgilash va sahifa yangilanganda hech narsa yo'qolmaydi", async () => {
    await page.locator('#q-R-1 .opt', { hasText: 'TRUE' }).click();
    await page.locator('#q-R-2 input').fill('lead');
    // Matnni belgilash (highlight)
    await page.evaluate(() => {
      const p = document.querySelector('.passage .passage-p');
      const range = document.createRange();
      range.setStart(p.firstChild, 4);
      range.setEnd(p.firstChild, 12);
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
      p.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    });
    await page.locator('.hl-menu button', { hasText: 'Belgilash' }).click();
    assert(await page.locator('.passage mark.hl').count() > 0, 'belgi qo\'yilmadi');
    await page.locator('.save-status.save-saved').waitFor({ timeout: 10000 });

    await page.reload();
    await page.getByRole('button', { name: 'Davom etish' }).click();
    await page.locator('.exam .eh-section', { hasText: 'Reading' }).waitFor();
    assert(await page.locator('#q-R-1 input[value="TRUE"]').isChecked(), 'TRUE javobi tiklanmadi');
    assert((await page.locator('#q-R-2 input').inputValue()) === 'lead', 'gap-fill javobi tiklanmadi');
    assert(await page.locator('.passage mark.hl').count() > 0, 'belgi tiklanmadi');
  });

  await step("Reading'ni tasdiqlab yakunlash", async () => {
    await page.locator('.btn-finish').click();
    await page.getByRole('button', { name: 'Ha, yakunlash' }).click();
    await page.locator('.break-card').waitFor();
    await page.getByRole('button', { name: "Writing bo'limini boshlash" }).click();
    await page.locator('.writing-area').waitFor();
  });

  await step("Writing: so'z hisoblagichi, joylashtirish (paste) bloklanadi", async () => {
    const area = page.locator('.writing-area');
    await area.click();
    await page.keyboard.type('I would like to invite you to the new park this weekend.');
    await page.waitForFunction(() => document.querySelector('.wc-value').textContent === '12');
    await page.evaluate(() => navigator.clipboard && navigator.clipboard.writeText('PASTED TEXT').catch(() => {}));
    await page.keyboard.press('Control+V');
    const value = await area.inputValue();
    assert(!value.includes('PASTED'), 'paste bloklanmadi');
    await page.locator('.btn-finish').click();
    await page.getByRole('button', { name: 'Ha, yakunlash' }).click();
  });

  await step('Speaking: mikrofon tekshiruvi, yozuv va yuklash', async () => {
    await page.getByRole('button', { name: 'Mikrofonga ruxsat berish' }).click();
    await page.locator('.mic-status.ok').waitFor();
    await page.getByRole('button', { name: "Speaking'ni boshlash" }).click();
    await page.locator('.speaking-card .sq-text', { hasText: 'Say something.' }).waitFor();
    await page.locator('.final-card', { hasText: 'Imtihon yakunlandi' }).waitFor({ timeout: 30000 });
    const fs = await page.evaluate(() => Boolean(document.fullscreenElement));
    assert(!fs, "imtihon tugagach to'liq ekran o'chirilishi kerak");
  });

  await step('admin natijani va qoidabuzarlikni ko\'radi', async () => {
    const res = await call('GET', `admin/mocks/${mockId}/attempts`);
    const a = res.attempts[0];
    assert(a.status === 'completed', 'holat: ' + a.status);
    assert(a.l_raw === 2, 'Listening xom balli: ' + a.l_raw);
    assert(a.r_raw === 2, 'Reading xom balli: ' + a.r_raw);
    assert(a.violations === 1, 'qoidabuzarliklar: ' + a.violations);
    assert(a.grade_w === 'queue' && a.grade_s === 'queue', 'ekspert navbati: ' + a.grade_w + '/' + a.grade_s);
    const detail = await call('GET', `admin/attempts/${a.id}`);
    assert(detail.speaking.length === 1, 'Speaking yozuvi saqlanmadi');
    assert(detail.events.some((e) => e.type === 'fullscreen_exit'), 'hodisalar jurnali to\'liq emas');
  });

  await step("bosh sahifada urinishlar 1/2, muzlatilgan mock yashiriladi", async () => {
    await page.getByRole('button', { name: 'Bosh sahifaga qaytish' }).click();
    await page.locator('.mock-card', { hasText: 'Urinishlar: 1/2' }).waitFor();
    await call('POST', `admin/mocks/${mockId}/status`, { status: 'frozen' });
    await page.reload();
    await page.locator('h1', { hasText: 'Mocklar' }).waitFor();
    await page.waitForTimeout(500);
    assert(await page.locator('.mock-card', { hasText: 'E2E mock' }).count() === 0, 'muzlatilgan mock ko\'rinib turibdi');
    const res = await page.evaluate(async () => {
      const me = await (await fetch('api/auth/me')).json();
      const r = await fetch('api/student/mocks/1/start', { method: 'POST', headers: { 'X-CSRF-Token': me.csrf } });
      return { status: r.status, body: await r.json() };
    });
    assert(res.status === 403 && res.body.error.code === 'mock_frozen', 'muzlatilgan mock boshlandi: ' + JSON.stringify(res));
  });

  assert(pageErrors.length === 0, 'Brauzer xatolari:\n' + pageErrors.join('\n'));
  console.log('\nBarcha E2E qadamlari muvaffaqiyatli o\'tdi.');
} catch (err) {
  if (pageErrors.length) console.log('Brauzer xatolari:\n  ' + pageErrors.join('\n  '));
  failures = Math.max(failures, 1);
} finally {
  await browser.close();
  server.kill();
}
process.exit(failures ? 1 : 0);

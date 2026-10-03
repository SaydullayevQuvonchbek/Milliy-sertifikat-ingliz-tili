// Uchidan-uchigacha (E2E) test: haqiqiy brauzerda admin mock yaratadi, o'quvchi uni to'liq ishlaydi.
//   npm run test:e2e        (Playwright va PHP kerak)
//
// Tekshiriladi: ro'yxatdan o'tish, mock boshlash, to'liq ekran, Listening audiosi va avtomatik o'tish,
// qoidabuzarlik oynasi, sahifani yangilaganda javoblar saqlanishi, belgilash (highlight), Writing so'z
// hisoblagichi, Speaking yozuvi, admin natijalari, urinishlar soni va mockni muzlatish;
// video nazorat (soxta kamera va ekran): bo'laklar serverga, fayllar yig'iladi, soxta Telegram serveriga yuboriladi.

import { execFileSync, spawn } from 'node:child_process';
import { createServer } from 'node:http';
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
// MOCK_E2E_DB=mysql — MySQL/MariaDB'da ishlatish (MOCK_TEST_MYSQL_HOST/PORT/DB/USER/PASS; baza bo'sh bo'lishi kerak).
const dbConfig = process.env.MOCK_E2E_DB === 'mysql'
  ? `['driver' => 'mysql', 'mysql' => ${JSON.stringify({
    host: process.env.MOCK_TEST_MYSQL_HOST || '127.0.0.1',
    port: Number(process.env.MOCK_TEST_MYSQL_PORT || 3306),
    database: process.env.MOCK_TEST_MYSQL_DB || 'mlmock_e2e',
    username: process.env.MOCK_TEST_MYSQL_USER || 'mlmock',
    password: process.env.MOCK_TEST_MYSQL_PASS || 'mlmock',
  }).replace(/\{/g, '[').replace(/\}/g, ']').replace(/":/g, '"=>')}]`
  : `['driver' => 'sqlite', 'sqlite_path' => ${JSON.stringify(path.join(dir, 'db.sqlite'))}]`;
// Soxta Telegram Bot API: yuborilgan videolarni yozib boradi.
const telegram = [];
const tgServer = createServer((req, res) => {
  const chunks = [];
  req.on('data', (c) => chunks.push(c));
  req.on('end', () => {
    const body = Buffer.concat(chunks);
    const text = body.toString('latin1');
    const field = (name) => {
      const m = text.match(new RegExp(`name="${name}"\\r\\n\\r\\n([\\s\\S]*?)\\r\\n--`));
      return m ? Buffer.from(m[1], 'latin1').toString('utf8') : null;
    };
    const method = req.url.split('/').pop();
    telegram.push({ url: req.url, method, bytes: body.length, chat: field('chat_id'), caption: field('caption'), text: field('text') });
    res.setHeader('Content-Type', 'application/json');
    res.end(JSON.stringify({ ok: true, result: { message_id: telegram.length } }));
  });
});
await new Promise((r) => tgServer.listen(0, '127.0.0.1', r));
const tgBase = `http://127.0.0.1:${tgServer.address().port}`;

writeFileSync(config, `<?php return [
  'db' => ${dbConfig},
  'storage_path' => ${JSON.stringify(dir)},
  'secure_cookies' => false,
  'debug' => true,
  'telegram' => ['bot_token' => '111:E2E', 'chat_id' => '-100555', 'api_base' => ${JSON.stringify(tgBase)}],
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
// MOCK_E2E_SHOTS=/papka — muhim ekranlarning rasmlari (dizaynni ko'rib chiqish uchun).
async function shot(p, name) {
  if (!process.env.MOCK_E2E_SHOTS) return;
  await p.waitForTimeout(250);
  await p.screenshot({ path: path.join(process.env.MOCK_E2E_SHOTS, `${name}.png`) });
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
  args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--auto-select-desktop-capture-source=Entire screen'],
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

  await step('qoidalar: audio oldindan yuklanadi, kamera va ekran yoqiladi, boshlash to\'liq ekranga o\'tkazadi', async () => {
    await page.locator('.mock-card', { hasText: 'E2E mock' }).getByRole('button', { name: 'Boshlash' }).click();
    await page.locator('.rules-card').waitFor();
    await page.locator('.preload.ok').waitFor({ timeout: 15000 });
    await page.getByLabel("Qoidalar bilan tanishdim va ularga roziman").check();
    const start = page.getByRole('button', { name: "Listening'ni boshlash" });
    assert(await start.isDisabled(), 'kamera/ekran so\'ralmasdan boshlash tugmasi ochiq');
    await page.locator('.proctor-check').scrollIntoViewIfNeeded();
    await shot(page, '01-gate-before');
    await page.getByRole('button', { name: 'Kamerani yoqish' }).click();
    await page.locator('.proctor-status.ok', { hasText: 'Kamera ishlayapti' }).waitFor();
    await page.getByRole('button', { name: 'Ekranni ulashish' }).click();
    await page.locator('.proctor-status.ok', { hasText: 'Ekran ulashildi' }).waitFor();
    await shot(page, '02-gate-ready');
    await start.click();
    await page.locator('.exam .eh-section', { hasText: 'Listening' }).waitFor();
    const fs = await page.evaluate(() => Boolean(document.fullscreenElement));
    assert(fs, "to'liq ekran yoqilmadi");
  });

  await step('Listening: javoblar belgilanadi, navigator yangilanadi', async () => {
    await page.locator('#q-L-1 .opt', { hasText: 'Two.' }).click();
    await page.locator('#q-L-2 input').fill('seven');
    await page.waitForFunction(() => document.querySelectorAll('.np-q.answered').length === 2);
    await page.locator('.listen-status .ls-text', { hasText: /Eshittirilmoqda|ko'rib chiqing|Pauza|Boshlanishiga/ }).waitFor();
    await page.locator('.rec-badge', { hasText: 'Yozilmoqda' }).waitFor();
    await shot(page, '03-listening-rec-badge');
  });

  await step("to'liq ekrandan chiqish qoidabuzarlik sifatida bloklanadi", async () => {
    await page.evaluate(() => document.exitFullscreen());
    await page.locator('.lock-overlay').waitFor();
    const text = await page.locator('.lock-overlay').innerText();
    assert(text.includes('Qoidabuzarliklar: 1/5'), 'hisoblagich noto\'g\'ri: ' + text);
    await page.locator('.lock-overlay button').click();
    await page.locator('.lock-overlay').waitFor({ state: 'detached' });
  });

  await step('Listening vaqti tugagach avtomatik yakunlanadi; tanaffusda F5 — kamera va ekran qayta so\'raladi', async () => {
    await page.locator('.break-card').waitFor({ timeout: 30000 });
    // Bo'lim tugashi bilan Listening yozuvining hozirgacha qismi darhol serverga ketadi (tanaffusda F5 bo'lsa ham qoladi).
    let listened = false;
    for (let i = 0; i < 40 && !listened; i += 1) {
      const res = await call('GET', `admin/mocks/${mockId}/attempts`);
      const d = await call('GET', `admin/attempts/${res.attempts[0].id}`);
      listened = d.recordings.some((r) => r.section === 'L');
      if (!listened) await new Promise((r) => setTimeout(r, 250));
    }
    assert(listened, 'Listening yozuvi tanaffus boshida yuborilmadi');
    await page.reload();
    await page.locator('.break-card .proctor-check').waitFor();
    const startR = page.getByRole('button', { name: "Reading bo'limini boshlash" });
    assert(await startR.isDisabled(), 'kamera/ekran qayta yoqilmasdan boshlash tugmasi ochiq');
    await page.getByRole('button', { name: 'Kamerani yoqish' }).click();
    await page.getByRole('button', { name: 'Ekranni ulashish' }).click();
    await page.locator('.proctor-status.ok', { hasText: 'Ekran ulashildi' }).waitFor();
    await startR.click();
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
    // Sahifa yangilangach kamera va ekran qayta yoqiladi (brauzer qoidasi).
    const resume = page.getByRole('button', { name: 'Davom etish' });
    await page.getByRole('button', { name: 'Kamerani yoqish' }).click();
    await page.getByRole('button', { name: 'Ekranni ulashish' }).click();
    await page.locator('.proctor-status.ok', { hasText: 'Ekran ulashildi' }).waitFor();
    await resume.click();
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

  await step("ekran ulashish to'xtasa ogohlantirish chiqadi va qayta ulanadi", async () => {
    // Brauzerning "Ulashishni to'xtatish" tugmasi o'rniga: ekran trekiga 'ended' hodisasi.
    await page.evaluate(() => {
      const videos = Array.from(document.querySelectorAll('.proctor-sources video'));
      const screen = videos.map((v) => v.srcObject && v.srcObject.getVideoTracks()[0]).find((t) => t && t.getSettings().displaySurface === 'monitor');
      screen.dispatchEvent(new Event('ended'));
    });
    await page.locator('.proctor-banner', { hasText: "Ekran ulashish to'xtatildi" }).waitFor();
    await shot(page, '04-screen-stopped-banner');
    await page.locator('.proctor-banner').getByRole('button', { name: 'Ekranni qayta ulashish' }).click();
    await page.locator('.proctor-banner').waitFor({ state: 'detached' });
    assert(await page.locator('.lock-overlay').count() === 0, 'qayta ulashda qoidabuzarlik oynasi chiqmasligi kerak');
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
    assert(detail.meta.proctor && detail.meta.proctor.camera === 'ok', 'kamera holati: ' + JSON.stringify(detail.meta.proctor));
  });

  await step('video nazorat: har bo\'lim yozuvi serverga keldi va Telegram\'ga yuborildi', async () => {
    let detail;
    for (let i = 0; i < 40; i += 1) {
      const res = await call('GET', `admin/mocks/${mockId}/attempts`);
      detail = await call('GET', `admin/attempts/${res.attempts[0].id}`);
      if (['L', 'R', 'W', 'S'].every((c) => detail.recordings.some((r) => r.section === c && r.status !== 'recording'))) break;
      await new Promise((r) => setTimeout(r, 500));
    }
    const recs = detail.recordings;
    for (const c of ['L', 'R', 'W', 'S']) assert(recs.some((r) => r.section === c), `${c} bo'limi yozuvi yo'q: ` + JSON.stringify(recs.map((r) => [r.section, r.status, r.size])));
    const brief = JSON.stringify(recs.map((r) => [r.section, r.status, r.size, r.pieces, r.duration_ms, r.complete]));
    // Tanaffusda F5 bo'lgan Listening yozuvi yangi sahifa ochilganda yopiladi (to'liq emas) — qolganlari to'liq.
    assert(recs.every((r) => r.size > 0 && r.status !== 'recording'), "bo'sh yoki yopilmagan yozuv bor: " + brief);
    assert(recs.filter((r) => r.section !== 'L').every((r) => Number(r.complete) === 1), "to'liq bo'lmagan yozuv bor: " + brief);
    assert(recs.filter((r) => r.section === 'L' || r.section === 'S').every((r) => r.size > 20000 && r.duration_ms > 3000), 'Listening/Speaking yozuvi juda qisqa: ' + brief);
    // Yozma qism: ekran + kamera (Writing tugab Speaking oynasi ochilganda ekran ulashish to'xtaydi — u yog'i faqat kamera).
    for (const c of ['L', 'R', 'W']) assert(recs.some((r) => r.section === c && r.content === 'screen+camera'), `${c}: ekran + kamera yozuvi yo'q: ` + brief);
    assert(recs.filter((r) => r.section === 'S').every((r) => r.content === 'camera' && Number(r.has_audio) === 1), 'Speaking: kamera + ovoz');
    // Faylni yuklab, video ekanini tekshiramiz (WebM yoki MP4 boshi).
    const file = await admin.fetch(`${base}/api/admin/recordings/${recs[0].id}/file`);
    const head = (await file.body()).subarray(0, 12);
    const webm = head[0] === 0x1a && head[1] === 0x45 && head[2] === 0xdf && head[3] === 0xa3;
    const mp4 = head.subarray(4, 8).toString('latin1') === 'ftyp';
    assert(file.ok() && (webm || mp4), 'yozuv fayli video emas: ' + head.toString('hex'));

    const run = await call('POST', 'admin/recordings/run');
    assert(run.result.sent === recs.length, 'Telegram\'ga yuborildi: ' + JSON.stringify(run.result));
    const sends = telegram.filter((t) => t.method === 'sendVideo' || t.method === 'sendDocument');
    assert(sends.length === recs.length && sends.every((t) => t.url.startsWith('/bot111:E2E/') && t.chat === '-100555'), 'soxta Telegram so\'rovlari: ' + JSON.stringify(sends.map((t) => t.url)));
    assert(sends.every((t) => t.caption && t.caption.includes('Sinov Talaba')), 'yozuv matnida o\'quvchi ismi yo\'q');
    if (process.env.MOCK_E2E_SHOTS) {
      const ap = await adminCtx.newPage();
      await ap.setViewportSize({ width: 1366, height: 900 });
      await ap.goto(`${base}/admin/#/attempts/${detail.attempt.id}`);
      await ap.locator('h3', { hasText: 'Video yozuvlar' }).scrollIntoViewIfNeeded();
      await shot(ap, '05-admin-attempt-videos-before-send');
      await ap.close();
    }
    const after = await call('GET', `admin/attempts/${detail.attempt.id}`);
    assert(after.recordings.every((r) => r.status === 'sent'), 'holat sent emas');
    assert(after.recordings.filter((r) => r.section !== 'S').every((r) => !r.playable), 'yozma qism videosi serverda qolgan');
    assert(after.recordings.filter((r) => r.section === 'S').every((r) => r.playable), 'Speaking videosi serverda saqlanishi kerak');
    const list = await call('GET', `admin/mocks/${mockId}/attempts`);
    assert(list.attempts[0].videos === recs.length, 'natijalar jadvalida videolar soni');
    if (process.env.MOCK_E2E_SHOTS) {
      const ap = await adminCtx.newPage();
      await ap.setViewportSize({ width: 1366, height: 900 });
      await ap.goto(`${base}/admin/#/attempts/${detail.attempt.id}`);
      await ap.locator('h3', { hasText: 'Video yozuvlar' }).scrollIntoViewIfNeeded();
      await shot(ap, '06-admin-attempt-videos-sent');
      await ap.goto(`${base}/admin/#/mocks/${mockId}/results`);
      await ap.locator('.table').first().waitFor();
      await shot(ap, '07-admin-results-video-column');
      await ap.goto(`${base}/admin/#/settings`);
      await ap.locator('h3', { hasText: 'Video yozuvlar va Telegram' }).waitFor();
      await ap.locator('.tg-status').waitFor();
      await ap.locator('h3', { hasText: 'Video yozuvlar va Telegram' }).scrollIntoViewIfNeeded();
      await shot(ap, '08-admin-settings-telegram');
      await ap.goto(`${base}/admin/#/mocks/${mockId}`);
      await ap.locator('h3', { hasText: 'Video nazorat (kamera va ekran)' }).scrollIntoViewIfNeeded();
      await shot(ap, '09-admin-mock-proctoring-settings');
      await ap.close();
    }
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

  await step('ekspert Writing va Speaking ishini admin panelda baholaydi', async () => {
    const ap = await adminCtx.newPage();
    ap.on('pageerror', (e) => pageErrors.push('admin: ' + e.message));
    ap.on('console', (m) => m.type() === 'error' && !m.text().includes('Failed to load resource') && pageErrors.push('admin: ' + m.text()));
    ap.on('dialog', (d) => d.accept());
    await ap.goto(`${base}/admin/#/grading`);
    await ap.locator('h1', { hasText: 'Ekspert tekshiruvi' }).waitFor();
    for (const skill of ['Writing', 'Speaking']) {
      await ap.locator('.stat', { hasText: `${skill} navbati` }).getByRole('button', { name: 'Keyingi ish' }).click();
      await ap.locator('.rubric').waitFor();
      const selects = ap.locator('.rubric .band-select');
      const count = await selects.count();
      for (let i = 0; i < count; i += 1) await selects.nth(i).selectOption('3');
      await ap.locator('.rubric textarea').fill('Yaxshi harakat.');
      await ap.getByRole('button', { name: 'Saqlash', exact: true }).click();
      await ap.locator('.rubric').waitFor({ state: 'detached' });
    }
    const res = await call('GET', `admin/mocks/${mockId}/attempts`);
    const a = res.attempts[0];
    assert(a.grade_w === 'done' && a.grade_s === 'done', 'baholash tugamadi');
    assert(a.w_raw === 3 && a.s_raw === 3, `xom ballar: W ${a.w_raw}, S ${a.s_raw}`);
    assert(a.overall !== null && a.level, 'umumiy ball hisoblanmadi');
    await ap.close();
  });

  await step("admin: mock quruvchi — shablon, saqlash, tekshiruv va barcha tablar", async () => {
    const ap = await adminCtx.newPage();
    ap.on('pageerror', (e) => pageErrors.push('admin: ' + e.message));
    ap.on('console', (m) => m.type() === 'error' && !m.text().includes('Failed to load resource') && pageErrors.push('admin: ' + m.text()));
    ap.on('dialog', (d) => d.accept());
    await ap.goto(`${base}/admin/#/mocks/new`);
    await ap.locator('.field', { hasText: 'Mock nomi' }).locator('input').fill('UI orqali yaratilgan mock');
    await ap.locator('.tab', { hasText: 'Reading' }).click();
    await ap.getByRole('button', { name: 'Rasmiy format shablonini yaratish' }).click();
    assert((await ap.locator('.part-card').count()) === 5, "Reading shablonida 5 ta qism bo'lishi kerak");
    await ap.locator('.page-actions button', { hasText: 'Saqlash' }).click();
    await ap.waitForURL(/#\/mocks\/\d+$/);
    for (const tab of ['Umumiy', 'Listening', 'Reading', 'Writing', 'Speaking', 'Fayllar', "O'quvchi ko'rinishi", 'JSON']) {
      await ap.locator('.tab', { hasText: tab }).first().click();
      await ap.waitForTimeout(150);
    }
    await ap.locator('.tab', { hasText: 'Tekshirish' }).click();
    await ap.locator('.validation').waitFor();
    const text = await ap.locator('.validation').innerText();
    assert(text.includes('35 savol') && text.includes('Xatolar'), 'tekshiruv natijasi kutilganidek emas');
    await ap.close();
  });

  await step("o'quvchi natijasini va ekspert izohini ko'radi", async () => {
    await page.goto(`${base}/#/`);
    await page.locator('.table a', { hasText: "Natijani ko'rish" }).first().click();
    await page.locator('.overall-value').waitFor();
    const text = await page.locator('main').innerText();
    assert(text.includes('Yaxshi harakat.'), 'ekspert izohi ko\'rinmadi');
    assert(!text.includes('lead') && !text.includes('seven'), "kalit o'quvchiga ko'rsatilmasligi kerak");
  });

  await step("2-mock: kamera majburiy, vaqtdan oldin yakunlash o'chiq, Speaking'da keyingi savolga o'tish", async () => {
    const created = await call('POST', 'admin/mocks', { title: 'Flow mock', max_attempts: 1 });
    const id2 = created.mock.id;
    const source = {
      reading: {
        parts: [{
          title: 'Part 1', instructions: 'Read.', passage: { title: 'T', text: 'Short text about rivers and lakes.' },
          blocks: [{ type: 'tfng', n: 1, prompt: 'Rivers are mentioned.', answer: 'TRUE' }],
        }],
      },
      speaking: { parts: [{ id: '1.1', title: 'Part 1.1', questions: [
        { no: 1, text: 'First question.', prep_sec: 30, answer_sec: 30 },
        { no: 2, text: 'Second question.', prep_sec: 30, answer_sec: 30 },
      ] }] },
    };
    const settings = {
      sections: ['R', 'S'], times: { reading: 60 }, break_sec: 10,
      lockdown: { fullscreen: true, max_violations: 5, action: 'terminate' },
      speaking: { mode: 'same_session' }, results: 'instant',
      proctoring: { camera: 'required', screen: 'off' },
      flow: { early_finish: false, speaking_skip: true },
    };
    await call('PUT', `admin/mocks/${id2}`, { title: 'Flow mock', max_attempts: 1, source, settings });
    await call('POST', `admin/mocks/${id2}/status`, { status: 'active' });

    await page.goto(`${base}/#/`);
    await page.locator('.mock-card', { hasText: 'Flow mock' }).getByRole('button', { name: 'Boshlash' }).click();
    await page.locator('.rules-card').waitFor();
    await page.getByLabel("Qoidalar bilan tanishdim va ularga roziman").check();
    const start = page.getByRole('button', { name: "Reading bo'limini boshlash" });
    assert(await page.getByRole('button', { name: 'Ekranni ulashish' }).count() === 0, "ekran o'chiq — so'ralmasligi kerak");
    assert(await start.isDisabled(), 'kamera majburiy — kamerasiz boshlanmasligi kerak');
    await page.getByRole('button', { name: 'Kamerani yoqish' }).click();
    await page.locator('.proctor-status.ok').waitFor();
    await start.click();
    await page.locator('.exam .eh-section', { hasText: 'Reading' }).waitFor();
    assert(await page.locator('.btn-finish').isHidden(), "vaqt tugamasdan yakunlash tugmasi ko'rinmasligi kerak");
    await page.locator('#q-R-1 .opt', { hasText: 'TRUE' }).click();
    // Vaqt tugaganda o'zi yakunlanadi, keyin Speaking (kamera allaqachon yoqilgan).
    await page.getByRole('button', { name: 'Mikrofonga ruxsat berish' }).click({ timeout: 90000 });
    await page.locator('.mic-status.ok').waitFor();
    await page.getByRole('button', { name: "Speaking'ni boshlash" }).click();
    const t0 = Date.now();
    for (const text of ['First question.', 'Second question.']) {
      await page.locator('.speaking-card .sq-text', { hasText: text }).waitFor();
      if (text === 'First question.') await shot(page, '10-speaking-skip-prep');
      await page.getByRole('button', { name: 'Javob berishni boshlash' }).click();
      await page.getByRole('button', { name: 'Javobni yakunlash' }).waitFor({ timeout: 10000 });
      if (text === 'First question.') await shot(page, '11-speaking-finish-answer');
      await page.getByRole('button', { name: 'Javobni yakunlash' }).click({ timeout: 10000 });
    }
    await page.locator('.final-card', { hasText: 'Imtihon yakunlandi' }).waitFor({ timeout: 30000 });
    assert(Date.now() - t0 < 40000, "javobni erta tugatish ishlamadi (savollar vaqtini kutdi)");
    await page.locator('.rec-upload.ok', { hasText: 'Video yozuv serverga yuborildi' }).waitFor({ timeout: 20000 });
    await shot(page, '12-final-upload-done');
    const res = await call('GET', `admin/mocks/${id2}/attempts`);
    const a = res.attempts[0];
    const detail = await call('GET', `admin/attempts/${a.id}`);
    assert(detail.speaking.length === 2, 'Speaking javoblari: ' + detail.speaking.length);
    assert(detail.speaking.every((x) => x.duration < 20), 'javoblar erta tugatilmagan: ' + JSON.stringify(detail.speaking));
    const r = detail.meta.sections.R;
    assert(r.finished_ms - r.started_ms >= 59000, "Reading vaqt tugaganda yopilishi kerak: " + JSON.stringify(r));
  });

  await step("o'quvchi parolini o'zi almashtiradi va yangi parol bilan kira oladi", async () => {
    await page.goto(`${base}/#/`);
    await page.getByRole('button', { name: 'Parol', exact: true }).click();
    const dialog = page.locator('.modal');
    await dialog.getByLabel('Joriy parol').fill('secret1');
    await dialog.getByLabel('Yangi parol (kamida 6 ta belgi)').fill('yangi-parol-2');
    await dialog.getByLabel('Yangi parolni takrorlang').fill('yangi-parol-2');
    await dialog.getByRole('button', { name: 'Saqlash' }).click();
    await page.locator('.toast', { hasText: 'Parol almashtirildi' }).waitFor();
    // Yangi parol bilan alohida sessiyadan kirish (eskisi endi ishlamaydi).
    const other = await browser.newContext();
    const csrfToken = (await (await other.request.get(`${base}/api/auth/me`)).json()).csrf;
    const attempt = (password) => other.request.post(`${base}/api/auth/login`, { headers: { 'X-CSRF-Token': csrfToken }, data: { login: '+998901234567', password } });
    assert((await attempt('secret1')).status() === 422, 'eski parol hali ishlayapti');
    assert((await attempt('yangi-parol-2')).ok(), 'yangi parol bilan kirib bo\'lmadi');
    await other.close();
  });

  assert(pageErrors.length === 0, 'Brauzer xatolari:\n' + pageErrors.join('\n'));
  console.log('\nBarcha E2E qadamlari muvaffaqiyatli o\'tdi.');
} catch (err) {
  if (pageErrors.length) console.log('Brauzer xatolari:\n  ' + pageErrors.join('\n  '));
  failures = Math.max(failures, 1);
} finally {
  await browser.close();
  server.kill();
  tgServer.close();
}
process.exit(failures ? 1 : 0);

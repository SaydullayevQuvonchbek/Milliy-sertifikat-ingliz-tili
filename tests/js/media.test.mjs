// Video nazorat yordamchilari: format tanlash, kadr joylashuvi, identifikator.

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { AV_TYPES, VIDEO_TYPES, composeLayout, mimeExt, pickVideoMime, pieceBytes, segmentKey } from '../../public/assets/js/lib/media.js';

test('format: Chrome (H.264 bor) — MP4, ovozli bo\'lsa AAC bilan; 720p sig\'adigan daraja birinchi', () => {
  const chrome = new Set(['video/mp4;codecs=avc1.42E01F', 'video/mp4;codecs=avc1.42E01E', 'video/mp4;codecs=avc1.42E01F,mp4a.40.2', 'video/webm;codecs=vp9', 'video/webm']);
  assert.equal(pickVideoMime((t) => chrome.has(t), false), 'video/mp4;codecs=avc1.42E01F');
  assert.equal(pickVideoMime((t) => chrome.has(t), true), 'video/mp4;codecs=avc1.42E01F,mp4a.40.2');
  // Faqat eski satr qo'llansa — o'shasi.
  const old = new Set(['video/mp4;codecs=avc1.42E01E', 'video/mp4;codecs=avc1.42E01E,opus']);
  assert.equal(pickVideoMime((t) => old.has(t), false), 'video/mp4;codecs=avc1.42E01E');
  assert.equal(pickVideoMime((t) => old.has(t), true), 'video/mp4;codecs=avc1.42E01E,opus');
});

test('format: H.264 yo\'q (Chromium) — WebM; hech biri yo\'q — null; xato bergan tekshiruv o\'tkazib yuboriladi', () => {
  const chromium = new Set(['video/mp4', 'video/webm;codecs=vp9', 'video/webm;codecs=vp9,opus', 'video/webm']);
  assert.equal(pickVideoMime((t) => chromium.has(t), false), 'video/webm;codecs=vp9');
  assert.equal(pickVideoMime((t) => chromium.has(t), true), 'video/webm;codecs=vp9,opus');
  assert.equal(pickVideoMime(() => false, true), null);
  let calls = 0;
  assert.equal(pickVideoMime((t) => {
    calls += 1;
    if (calls === 1) throw new Error('boom');
    return t === 'video/webm';
  }, false), 'video/webm');
  // "video/mp4" (kodeksiz) tanlanmaydi — Chromium'da u VP9 bo'ladi va Telegram'da video sifatida ko'rinmasligi mumkin.
  assert.ok(!VIDEO_TYPES.includes('video/mp4') && !AV_TYPES.includes('video/mp4'));
});

test('joylashuv: Full HD ekran 1280×720 ga tushiriladi, kamera pastki o\'ng burchakda', () => {
  const l = composeLayout({ width: 1920, height: 1080 }, { width: 640, height: 360 });
  assert.equal(l.width, 1280);
  assert.equal(l.height, 720);
  assert.equal(l.content, 'screen+camera');
  assert.equal(l.fps, 6);
  assert.ok(l.cam.x + l.cam.w <= l.width - 12 && l.cam.y + l.cam.h <= l.height - 12);
  assert.ok(l.cam.w > 250 && l.cam.w < 300);
  assert.equal(l.cam.w % 2, 0);
  assert.equal(l.cam.h % 2, 0);
});

test('joylashuv: kichik ekran kattalashtirilmaydi; faqat ekran; faqat kamera; hech narsa', () => {
  const small = composeLayout({ width: 1024, height: 600 }, null);
  assert.deepEqual([small.width, small.height, small.cam, small.content], [1024, 600, null, 'screen']);
  const cam = composeLayout(null, { width: 1280, height: 720 });
  assert.deepEqual([cam.width, cam.height, cam.content, cam.fps], [640, 360, 'camera', 12]);
  assert.equal(composeLayout(null, null), null);
  assert.equal(composeLayout({ width: 0, height: 0 }, null), null);
  // Portret (tik) ekran: balandlik 720 ga tushiriladi.
  const tall = composeLayout({ width: 1080, height: 1920 }, null);
  assert.equal(tall.height, 720);
  assert.equal(tall.width % 2, 0);
});

test('identifikator: 20 belgi, faqat harf/raqam (server ^[A-Za-z0-9]{12,32}$ kutadi)', () => {
  for (let i = 0; i < 50; i += 1) assert.match(segmentKey(), /^[A-Za-z0-9]{20}$/);
  assert.notEqual(segmentKey(), segmentKey());
});

test('hajm va kengaytma', () => {
  assert.equal(pieceBytes(250), 937500);
  assert.equal(mimeExt('video/mp4;codecs=avc1'), 'mp4');
  assert.equal(mimeExt('video/webm;codecs=vp9'), 'webm');
  assert.equal(mimeExt(''), 'webm');
});

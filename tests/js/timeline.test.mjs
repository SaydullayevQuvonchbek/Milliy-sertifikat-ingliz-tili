// Frontend yordamchilarining testlari: node --test tests/js/

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildTimeline, locate, partAt } from '../../public/assets/js/lib/timeline.js';
import { countWords, formatClock } from '../../public/assets/js/lib/text.js';

const listening = {
  review_sec: 60,
  parts: [
    { preview_sec: 10, gap_sec: 5, plays: 2, tracks: [{ asset: 1, duration: 20.5 }] },
    { preview_sec: 5, gap_sec: 2, plays: 2, tracks: [{ asset: 2, duration: 3 }, { asset: 3, duration: 4 }] },
  ],
};

test('vaqt jadvali server qoidasi bilan bir xil davomiylik beradi', () => {
  const single = { review_sec: 60, parts: [listening.parts[0]] };
  // PHP testi bilan bir xil: 10 + 2 × 20.5 + 5 + 60 = 116 s
  assert.equal(buildTimeline(single).total, 116000);

  // 1-qism: 10 + 20.5 + 5 + 20.5 = 56 s, qismlar orasida 5 s pauza
  // 2-qism: 5 + (3 + 2 + 3 + 2 + 4 + 2 + 4) = 25 s, keyin 60 s tekshirish
  assert.equal(buildTimeline(listening).total, (56 + 5 + 25 + 60) * 1000);
});

test('har audio ikki marta, orasida pauza bilan', () => {
  const tl = buildTimeline(listening);
  const plays = tl.segments.filter((s) => s.type === 'play').map((s) => `${s.asset}:${s.play}`);
  assert.deepEqual(plays, ['1:1', '1:2', '2:1', '2:2', '3:1', '3:2']);
  assert.equal(tl.segments[0].type, 'preview');
  assert.equal(tl.segments.at(-1).type, 'review');
});

test('berilgan vaqtda qaysi bo\'lak ketayotgani topiladi', () => {
  const tl = buildTimeline(listening);
  assert.equal(locate(tl, -500).before, true);
  const a = locate(tl, 12000);
  assert.equal(a.segment.type, 'play');
  assert.equal(a.segment.play, 1);
  assert.equal(a.offset, 2000);
  const b = locate(tl, 33000); // 10 + 20.5 + 2.5 → pauza
  assert.equal(b.segment.type, 'gap');
  const c = locate(tl, 40000);
  assert.equal(c.segment.play, 2);
  assert.equal(partAt(tl, 70000), 1);
  assert.equal(locate(tl, tl.total + 1).after, true);
});

test('so\'z sanash serverdagi qoida bilan bir xil', () => {
  assert.equal(countWords(''), 0);
  assert.equal(countWords('  Hello   world  '), 2);
  assert.equal(countWords("It's a well-known fact, isn't it?"), 6);
  assert.equal(countWords('— , . 2024'), 1);
  assert.equal(countWords('Line one\nline two'), 4);
});

test('vaqt formati', () => {
  assert.equal(formatClock(125000), '2:05');
  assert.equal(formatClock(3725000), '1:02:05');
  assert.equal(formatClock(-5), '0:00');
  assert.equal(formatClock(999), '0:01');
});

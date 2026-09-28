// Listening vaqt jadvali. Server ham aynan shu qoida bilan bo'lim davomiyligini hisoblaydi
// (src/Services/MockService.php → listeningDurationMs). Ikkalasi bir xil bo'lishi shart.
//
// Har qism: savollarni ko'rib chiqish → har audio "plays" marta (orasida pauza) → keyingi qismgacha pauza.
// Oxirida javoblarni tekshirish vaqti.

/**
 * @typedef {{type:'preview'|'play'|'gap'|'review', start:number, duration:number, part?:number, track?:number, play?:number, asset?:number}} Segment
 * @returns {{segments: Segment[], total: number, partStarts: number[]}}
 */
export function buildTimeline(listening) {
  const segments = [];
  const partStarts = [];
  let t = 0;
  const push = (segment) => {
    if (segment.duration <= 0) return;
    segments.push({ ...segment, start: t });
    t += segment.duration;
  };

  const parts = (listening && listening.parts) || [];
  parts.forEach((part, partIndex) => {
    partStarts.push(t);
    const plays = Math.max(1, Number(part.plays) || 1);
    const gap = (Number(part.gap_sec) || 0) * 1000;
    push({ type: 'preview', duration: (Number(part.preview_sec) || 0) * 1000, part: partIndex });

    const plan = [];
    (part.tracks || []).forEach((track, trackIndex) => {
      const duration = Math.round((Number(track.duration) || 0) * 1000);
      for (let p = 1; p <= plays; p += 1) plan.push({ track: trackIndex, play: p, asset: track.asset, duration });
    });
    plan.forEach((item, i) => {
      if (i > 0) push({ type: 'gap', duration: gap, part: partIndex });
      push({ type: 'play', part: partIndex, track: item.track, play: item.play, asset: item.asset, duration: item.duration });
    });
    if (partIndex < parts.length - 1) push({ type: 'gap', duration: gap, part: partIndex });
  });
  push({ type: 'review', duration: (Number(listening && listening.review_sec) || 0) * 1000 });
  return { segments, total: t, partStarts };
}

/** t (ms) paytida qaysi bo'lak ketayotganini topish. */
export function locate(timeline, t) {
  if (t < 0) return { index: -1, segment: null, offset: t, before: true };
  const { segments } = timeline;
  let lo = 0;
  let hi = segments.length - 1;
  while (lo <= hi) {
    const mid = (lo + hi) >> 1;
    const s = segments[mid];
    if (t < s.start) hi = mid - 1;
    else if (t >= s.start + s.duration) lo = mid + 1;
    else return { index: mid, segment: s, offset: t - s.start };
  }
  return { index: segments.length, segment: null, offset: t - timeline.total, after: true };
}

/** Joriy qism raqami (0 dan). Tekshirish vaqtida oxirgi qism. */
export function partAt(timeline, t) {
  let part = 0;
  timeline.partStarts.forEach((start, i) => {
    if (t >= start) part = i;
  });
  return part;
}

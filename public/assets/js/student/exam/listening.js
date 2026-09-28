// Listening pleyeri.
// - Audio test boshlanishidan oldin qurilmaga yuklab olinadi (internet sekinlashsa ham uzilmaydi).
// - Boshqaruv tugmalari yo'q: to'xtatish, orqaga surish, tezlatish mumkin emas.
// - Qaysi audio qayerda ijro etilishi server soati bo'yicha hisoblanadi: sahifa yangilansa,
//   audio boshidan emas, aynan qolgan joyidan davom etadi.

import { download, serverNow } from '../../lib/api.js';
import { h, icon, setText } from '../../lib/dom.js';
import { formatClock } from '../../lib/text.js';
import { T } from '../../lib/uz.js';
import { buildTimeline, locate } from '../../lib/timeline.js';
import { loadPrefs, savePrefs } from './shell.js';

const cache = new Map();

/** Barcha audiolarni oldindan yuklash. @returns {Promise<Map<number,string>>} asset → blob URL */
export async function preloadAudio(attemptId, manifest, onProgress) {
  const total = manifest.length;
  let done = 0;
  const sizes = new Map();
  const report = () => {
    let loaded = 0;
    let expected = 0;
    for (const [, s] of sizes) {
      loaded += s.loaded;
      expected += s.total;
    }
    onProgress && onProgress({ done, total, fraction: expected > 0 ? loaded / expected : done / Math.max(1, total) });
  };
  const urls = new Map();
  for (const item of manifest) {
    const key = `${attemptId}:${item.asset}`;
    if (cache.has(key)) {
      urls.set(item.asset, cache.get(key));
      done += 1;
      report();
      continue;
    }
    let lastError = null;
    for (let attempt = 0; attempt < 3; attempt += 1) {
      try {
        const blob = await download(`exam/${attemptId}/audio/${item.asset}`, (loaded, totalBytes) => {
          sizes.set(item.asset, { loaded, total: totalBytes || loaded });
          report();
        });
        const url = URL.createObjectURL(blob);
        cache.set(key, url);
        urls.set(item.asset, url);
        lastError = null;
        break;
      } catch (err) {
        lastError = err;
        await new Promise((r) => setTimeout(r, 1500 * (attempt + 1)));
      }
    }
    if (lastError) throw lastError;
    done += 1;
    report();
  }
  return urls;
}

/** Brauzer audio ijrosiga ruxsat berishi uchun foydalanuvchi bosganda chaqiriladi. */
export function unlockAudio(audio) {
  try {
    audio.muted = true;
    const p = audio.play();
    if (p && p.then) p.then(() => { audio.pause(); audio.muted = false; }).catch(() => { audio.muted = false; });
    else audio.muted = false;
  } catch {
    audio.muted = false;
  }
}

export function createAudioElement() {
  const audio = document.createElement('audio');
  audio.preload = 'auto';
  audio.controls = false;
  audio.setAttribute('playsinline', '');
  audio.setAttribute('controlsList', 'nodownload noplaybackrate');
  audio.addEventListener('ratechange', () => {
    if (audio.playbackRate !== 1) audio.playbackRate = 1;
  });
  if ('mediaSession' in navigator) {
    for (const action of ['play', 'pause', 'stop', 'seekbackward', 'seekforward', 'seekto', 'previoustrack', 'nexttrack']) {
      try {
        navigator.mediaSession.setActionHandler(action, () => {});
      } catch {
        /* qo'llab-quvvatlanmaydi */
      }
    }
  }
  return audio;
}

export class ListeningPlayer {
  /**
   * @param {{
   *   content: object, urls: Map<number,string>, startMs: number, audio: HTMLAudioElement,
   *   onPart: (index:number) => void, onEnd: () => void, report: (type:string, detail:string) => void,
   * }} options
   */
  constructor(options) {
    this.o = options;
    this.timeline = buildTimeline(options.content);
    this.audio = options.audio;
    this.prefs = loadPrefs();
    this.audio.volume = Math.min(1, Math.max(0, Number(this.prefs.volume) || 0.9));
    this.segIndex = -2;
    this.part = -1;
    this.blocked = false;
    this.ended = false;
    this.currentAsset = null;

    this.statusText = h('span', { class: 'ls-text' });
    this.progress = h('span', { class: 'ls-bar' });
    this.resume = h('button', { class: 'btn btn-sm btn-primary ls-resume', type: 'button', hidden: true, onclick: () => this.userResume() }, icon('play'), T.continueAudio);
    const volume = h('input', {
      type: 'range', min: '0', max: '1', step: '0.05', value: String(this.audio.volume), class: 'volume', 'aria-label': T.volume,
      oninput: (e) => {
        this.audio.volume = Number(e.target.value);
        this.prefs.volume = this.audio.volume;
        savePrefs(this.prefs);
      },
    });
    this.widget = h('div', { class: 'listen-status' },
      h('div', { class: 'ls-main' }, this.statusText, h('span', { class: 'ls-track' }, this.progress)),
      this.resume,
      h('label', { class: 'ls-volume', title: T.volume }, icon('volume'), volume)
    );
  }

  get total() {
    return this.timeline.total;
  }

  start() {
    this.timer = setInterval(() => this.tick(), 200);
    this.tick();
  }

  stop() {
    clearInterval(this.timer);
    this.audio.pause();
  }

  playSafe() {
    const p = this.audio.play();
    if (p && p.catch) {
      p.catch((err) => {
        if (err && err.name === 'NotAllowedError') {
          this.blocked = true;
          this.resume.hidden = false;
        } else if (err && err.name !== 'AbortError') {
          this.o.report('audio_error', String(err.message || err.name));
        }
      });
    }
  }

  userResume() {
    this.blocked = false;
    this.resume.hidden = true;
    this.segIndex = -2; // Joriy joyni qayta hisoblab, o'sha yerdan davom etish.
    this.tick();
  }

  tick() {
    if (this.ended) return;
    const t = serverNow() - this.o.startMs;
    const tl = this.timeline;

    if (t >= tl.total) {
      this.ended = true;
      this.stop();
      setText(this.statusText, T.reviewTime);
      this.o.onEnd();
      return;
    }
    if (t < 0) {
      if (!this.audio.paused) this.audio.pause();
      setText(this.statusText, `${T.startsIn}: ${Math.ceil(-t / 1000)}`);
      this.progress.style.width = '0%';
      return;
    }

    const loc = locate(tl, t);
    const seg = loc.segment;
    if (!seg) return;
    const part = seg.part !== undefined ? seg.part : this.part;
    if (part !== this.part && part !== undefined && part >= 0) {
      this.part = part;
      this.o.onPart(part);
    }

    if (seg.type === 'play') {
      const url = this.o.urls.get(seg.asset);
      if (!url) return;
      const expected = loc.offset / 1000;
      if (loc.index !== this.segIndex) {
        this.segIndex = loc.index;
        if (this.currentAsset !== seg.asset) {
          this.audio.src = url;
          this.currentAsset = seg.asset;
        }
        try {
          this.audio.currentTime = expected;
        } catch {
          /* metadata hali yuklanmagan — keyingi tikda */
        }
        if (!this.blocked) this.playSafe();
      } else if (!this.blocked) {
        if (this.audio.paused && !this.audio.ended) this.playSafe();
        const drift = Math.abs(this.audio.currentTime - expected);
        if (!this.audio.seeking && drift > 1.5 && expected < (this.audio.duration || Infinity) - 0.5) {
          this.audio.currentTime = expected;
          if (drift > 5) this.o.report('audio_resync', `${Math.round(drift)} soniya farq tuzatildi`);
        }
      }
      const partTitle = T.part(seg.part + 1);
      setText(this.statusText, `${T.nowPlaying}: ${partTitle} · ${T.listenN(seg.play)}`);
      this.progress.style.width = `${Math.min(100, (loc.offset / seg.duration) * 100)}%`;
    } else {
      this.segIndex = loc.index;
      if (!this.audio.paused) this.audio.pause();
      const label = seg.type === 'preview' ? T.previewTime : seg.type === 'review' ? T.reviewTime : T.pauseTime;
      const remain = formatClock(seg.duration - loc.offset);
      setText(this.statusText, seg.type === 'review' ? `${label}: ${remain}` : `${T.part(seg.part + 1)} · ${label}: ${remain}`);
      this.progress.style.width = '0%';
    }
  }
}

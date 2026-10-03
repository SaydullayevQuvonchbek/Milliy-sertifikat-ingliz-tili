// Video nazorat: ekran + kamera yozuvi (Speaking'da kamera + ovoz).
//
// Ekran va kamera bitta videoga joylanadi (kamera pastki o'ng burchakda, ustida nomzod kodi, bo'lim va vaqt).
// MediaRecorder har 15 soniyada bo'lak beradi; bo'laklar navbat bilan serverga ketadi (internet uzilsa kutadi),
// har ~10 daqiqada (yoki bo'lim almashganda) yangi fayl boshlanadi. Server fayllarni Telegram kanalga yuboradi.
//
// Brauzer qoidasi: ekranni ulashishni sahifa o'zi boshlay olmaydi — o'quvchi tugmani bosib, "Butun ekran"ni
// tanlashi shart. Ulashish to'xtatilsa yoki kamera uzilsa — jurnalga yoziladi va qayta ulash so'raladi.

import { ApiError, post, upload } from '../../lib/api.js';
import { h, icon, setText } from '../../lib/dom.js';
import { composeLayout, mimeExt, pickVideoMime, segmentKey } from '../../lib/media.js';
import { SECTION, T } from '../../lib/uz.js';

/** Bo'lak uzunligi: sahifa yangilansa ko'pi bilan shuncha video yo'qoladi. */
const PIECE_MS = 15000;
/** Imtihon davomida ekranni qayta ulashish oynasi uchun beriladigan vaqt (shundan uzoq tursa — oynadan chiqish). */
const PICKER_GRACE_MS = 20000;
const MAX_SEGMENT_BYTES = 40 * 1024 * 1024;
const MAX_QUEUE_BYTES = 160 * 1024 * 1024;
/** Server rad etsa, yozuvni butunlay to'xtatadigan xatolar (imtihon yopilgan, boshqa oynaga o'tgan va h.k.). */
const FATAL_CODES = new Set(['rec_closed', 'rec_disabled', 'rec_quota', 'rec_disk_full', 'taken_over', 'finished', 'terminated', 'not_found', 'unauthorized', 'forbidden']);

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function clock(date = new Date()) {
  const p = (n) => String(n).padStart(2, '0');
  return `${p(date.getHours())}:${p(date.getMinutes())}:${p(date.getSeconds())}`;
}

export class Proctor {
  /**
   * @param {{
   *   attemptId: number, clientId: string, code: string,
   *   config: {camera: 'off'|'optional'|'required', screen: 'off'|'optional'|'required', video_kbps: number, segment_sec: number},
   *   report: (type: string, detail?: string, violation?: boolean) => void,
   *   lock?: {suppress: (ms: number) => void},
   * }} options
   */
  constructor(options) {
    this.o = options;
    const cfg = options.config || {};
    this.camera = { mode: cfg.camera || 'off', status: cfg.camera === 'off' ? 'off' : 'idle', stream: null };
    this.screen = { mode: cfg.screen || 'off', status: cfg.screen === 'off' ? 'off' : 'idle', stream: null };
    this.kbps = Math.max(100, Math.min(2000, Number(cfg.video_kbps) || 250));
    this.segmentMs = Math.max(120, Number(cfg.segment_sec) || 600) * 1000;
    this.screens = 1;
    this.section = null;
    this.audioTrack = null;
    this.comp = null;
    this.seg = null;
    this.segTimer = null;
    this.queue = [];
    this.queueBytes = 0;
    this.uploading = false;
    this.closed = false;
    this.stopped = false;
    this.listeners = new Set();
    this.lastUploadAt = Date.now();
    this.sources = h('div', { class: 'proctor-sources', 'aria-hidden': 'true' });
    this.badge = null;
    this.alert = null;
    this.supported = Boolean(window.MediaRecorder && HTMLCanvasElement.prototype.captureStream);
    this.everRecorded = false;
    this.speaking = false;
  }

  get enabled() {
    return this.camera.mode !== 'off' || this.screen.mode !== 'off';
  }

  get recording() {
    return Boolean(this.seg);
  }

  /** Ekran yozuvda qatnashadimi (Speaking'da — yo'q). */
  get screenActive() {
    return !this.speaking && this.screen.status === 'ok' && Boolean(this.screen.stream);
  }

  hasSources() {
    return this.camera.status === 'ok' || this.screenActive;
  }

  /** Boshlash tugmasi ochiladimi: majburiylari ishlayapti, ixtiyoriylari kamida bir marta so'ralgan. */
  ready() {
    const ok = (kind) => kind.mode === 'off'
      || (kind.mode === 'required' ? kind.status === 'ok' : kind.status !== 'idle');
    return ok(this.camera) && (this.speaking || ok(this.screen));
  }

  onChange(fn) {
    this.listeners.add(fn);
    return () => this.listeners.delete(fn);
  }

  changed() {
    this.listeners.forEach((fn) => {
      try {
        fn();
      } catch {
        /* e'tiborsiz */
      }
    });
    this.renderBadge();
  }

  report(type, detail = '', violation = false) {
    if (this.o.report) this.o.report(type, detail, violation);
  }

  // ------------------------------------------------------------------
  // Ruxsatlar
  // ------------------------------------------------------------------

  async enableCamera() {
    const cam = this.camera;
    if (cam.mode === 'off') return;
    if (cam.stream) cam.stream.getTracks().forEach((t) => t.stop());
    cam.stream = null;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !this.supported) {
      cam.status = 'unsupported';
      this.report('camera_none', 'Brauzer kamerani qo\'llab-quvvatlamaydi');
    } else {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { width: { ideal: 640 }, height: { ideal: 360 }, frameRate: { ideal: 15, max: 15 } },
          audio: false,
        });
        cam.stream = stream;
        cam.status = 'ok';
        const track = stream.getVideoTracks()[0];
        track.addEventListener('ended', () => this.onCameraLost());
        this.report('camera_ok', (track.label || '').slice(0, 120));
      } catch (err) {
        const name = (err && err.name) || 'Error';
        cam.status = name === 'NotAllowedError' || name === 'SecurityError' ? 'denied'
          : name === 'NotFoundError' || name === 'OverconstrainedError' ? 'none' : 'error';
        this.report(cam.status === 'denied' ? 'camera_denied' : 'camera_none', name);
      }
    }
    this.sourcesChanged();
  }

  async enableScreen() {
    const scr = this.screen;
    if (scr.mode === 'off') return;
    if (scr.stream) scr.stream.getTracks().forEach((t) => t.stop());
    scr.stream = null;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia || !this.supported) {
      scr.status = 'unsupported';
      this.report('screen_unsupported', '');
      this.sourcesChanged();
      return;
    }
    // Imtihon davomida qayta ulashilsa: tanlash oynasi imtihon oynasidan fokusni oladi — bu qoidabuzarlik emas.
    // Oyna yopilgach kuzatuv qisqa vaqtdan keyin tiklanadi va to'liq ekran holati qayta tekshiriladi. Tanlash oynasi
    // uzoq ochiq tursa (o'quvchi shu orada boshqa dasturga o'tishi mumkin) — bu oynadan chiqish deb yoziladi.
    const lock = this.o.lock;
    const locked = Boolean(lock && lock.enabled);
    const pickerOpened = Date.now();
    if (locked) lock.suppress(PICKER_GRACE_MS);
    try {
      const stream = await navigator.mediaDevices.getDisplayMedia({
        video: { displaySurface: 'monitor', frameRate: { ideal: 5, max: 10 }, width: { max: 1920 }, height: { max: 1080 } },
        audio: false,
        selfBrowserSurface: 'exclude',
        surfaceSwitching: 'exclude',
        monitorTypeSurfaces: 'include',
        systemAudio: 'exclude',
      });
      const track = stream.getVideoTracks()[0];
      const surface = track && track.getSettings ? track.getSettings().displaySurface : undefined;
      if (surface && surface !== 'monitor') {
        stream.getTracks().forEach((t) => t.stop());
        scr.status = 'wrong';
        this.report('screen_wrong', surface);
      } else {
        scr.stream = stream;
        scr.status = 'ok';
        track.addEventListener('ended', () => this.onScreenStopped());
        this.report('screen_ok', '');
        if (window.screen && window.screen.isExtended) {
          this.screens = 2;
          this.report('multi_screen', 'Kompyuterga bir nechta monitor ulangan');
        }
      }
    } catch (err) {
      const name = (err && err.name) || 'Error';
      scr.status = name === 'NotAllowedError' ? 'denied' : 'error';
      this.report('screen_denied', name);
    }
    if (locked) {
      const away = Date.now() - pickerOpened;
      if (away > PICKER_GRACE_MS) {
        this.report('focus_lost', `Ekranni ulashish oynasi ${Math.round(away / 1000)} soniya ochiq turdi`, true);
      }
      lock.suppress(1500);
      setTimeout(() => lock.checkReturn(), 1600);
    }
    this.sourcesChanged();
  }

  /** Server uchun holat: hali so'ralmagan qurilma yuborilmaydi (darvozada oraliq holat "yo'q" deb belgilanmasin). */
  statusPayload() {
    const out = { screens: this.screens };
    for (const [name, kind] of [['camera', this.camera], ['screen', this.screen]]) {
      // Speaking'da ekran so'ralmaydi — serverdagi yozma qism holati o'zgarmaydi.
      if (name === 'screen' && this.speaking) continue;
      if (kind.mode === 'off') out[name] = 'off';
      else if (kind.status !== 'idle') out[name] = kind.status;
    }
    return out;
  }

  async sendStatus() {
    if (!this.enabled) return;
    try {
      await post(`exam/${this.o.attemptId}/proctor`, { client_id: this.o.clientId, ...this.statusPayload() });
    } catch {
      /* Keyingi o'zgarishda yana yuboriladi. */
    }
  }

  // ------------------------------------------------------------------
  // Darvoza (boshlashdan oldin) kartasi
  // ------------------------------------------------------------------

  /** Kamera va ekranni yoqish kartasi. Holat o'zgarganda o'zi yangilanadi. */
  gateCard({ speaking = false } = {}) {
    if (!this.enabled) return null;
    const box = h('div', { class: 'proctor-check' });
    const preview = h('video', { class: 'proctor-preview', muted: true, autoplay: true, playsinline: true });
    preview.muted = true;

    const row = (kind, label, iconName, button, status, note) => h('div', { class: `proctor-row ${kind.status}` },
      h('span', { class: 'proctor-icon' }, icon(iconName)),
      h('div', { class: 'proctor-info' },
        h('strong', { text: label }),
        status ? h('span', { class: `proctor-status ${kind.status === 'ok' ? 'ok' : kind.status === 'idle' ? '' : 'bad'}`, text: status }) : null,
        note ? h('span', { class: 'muted small', text: note }) : null
      ),
      button
    );

    const render = () => {
      const rows = [];
      const cam = this.camera;
      if (cam.mode !== 'off') {
        const text = { ok: T.camOk, denied: T.camDenied, none: T.camNone, error: T.camError, unsupported: T.recUnsupported, lost: T.cameraLostTitle }[cam.status] || '';
        const btn = cam.status === 'ok' ? null : h('button', { class: 'btn btn-sm', type: 'button', onclick: async (e) => {
          e.currentTarget.disabled = true;
          await this.enableCamera();
        } }, icon('camera'), cam.status === 'idle' ? T.camEnable : T.retry);
        const note = cam.status !== 'ok' && cam.status !== 'idle' ? (cam.mode === 'required' ? T.camRequired : T.camOptional) : null;
        rows.push(row(cam, T.camLabel, 'camera', btn, text, note));
      }
      const scr = this.screen;
      if (scr.mode !== 'off' && !speaking && !this.speaking) {
        const text = { ok: T.scrOk, denied: T.scrDenied, wrong: T.scrWrong, error: T.scrDenied, unsupported: T.scrUnsupported, stopped: T.screenStoppedTitle }[scr.status] || '';
        const btn = scr.status === 'ok' ? null : h('button', { class: 'btn btn-sm', type: 'button', onclick: async (e) => {
          e.currentTarget.disabled = true;
          await this.enableScreen();
        } }, icon('monitor'), scr.status === 'idle' ? T.scrEnable : T.retry);
        const note = scr.status === 'idle' ? T.scrHint : scr.status !== 'ok' ? (scr.mode === 'required' ? T.scrRequired : T.scrOptional) : null;
        rows.push(row(scr, T.scrLabel, 'monitor', btn, text, note));
      }
      if (this.camera.stream && preview.srcObject !== this.camera.stream) {
        preview.srcObject = this.camera.stream;
        preview.play().catch(() => {});
      }
      preview.hidden = !this.camera.stream;
      // replaceChildren(null) "null" matnini qo'shadi — bo'sh qiymatlar olib tashlanadi.
      box.replaceChildren(...[
        h('h3', { text: T.procTitle }),
        h('p', { class: 'muted small', text: speaking ? T.procNoticeSpeaking : T.procNotice }),
        h('div', { class: 'proctor-body' }, h('div', { class: 'proctor-rows' }, rows), preview),
        this.screens > 1 ? h('p', { class: 'proctor-warn small', text: T.multiScreen }) : null,
      ].filter(Boolean));
    };
    const off = this.onChange(() => {
      if (!box.isConnected && box.dataset.mounted) {
        off();
        return;
      }
      render();
    });
    render();
    requestAnimationFrame(() => (box.dataset.mounted = '1'));
    return box;
  }

  /**
   * Speaking: faqat kamera va ovoz yoziladi (ekran kerak emas) — ekran ulashish to'xtatiladi,
   * darvozada faqat kamera so'raladi.
   */
  speakingMode() {
    if (this.speaking) return;
    this.speaking = true;
    // track.stop() 'ended' hodisasini chiqarmaydi — "ekran to'xtatildi" deb yozilmaydi.
    if (this.screen.stream) this.screen.stream.getTracks().forEach((t) => t.stop());
    this.screen.stream = null;
    this.buildComposite();
    if (this.section && !this.stopped) this.rotate();
    this.changed();
  }

  // ------------------------------------------------------------------
  // Yozish
  // ------------------------------------------------------------------

  /** Bo'lim boshlandi (yoki almashdi): yangi fayl. Speaking'da mikrofon ovozi ham qo'shiladi. */
  setSection(code, audioTrack = null) {
    if (!this.enabled || this.stopped) return;
    const sameAudio = (audioTrack || null) === (this.audioTrack || null);
    if (this.section === code && this.seg && sameAudio) return;
    this.section = code;
    if (!sameAudio) {
      if (this.audioTrack && this.audioTrack !== audioTrack) this.audioTrack.stop();
      this.audioTrack = audioTrack || null;
    }
    this.rotate();
    this.mountBadge();
  }

  sourcesChanged() {
    this.buildComposite();
    if (this.section && !this.stopped) this.rotate();
    this.changed();
    this.sendStatus();
  }

  videoFor(stream) {
    const video = h('video', { muted: true, autoplay: true, playsinline: true });
    video.muted = true;
    video.srcObject = stream;
    if (!this.sources.isConnected) document.body.append(this.sources);
    this.sources.append(video);
    video.play().catch(() => {});
    return video;
  }

  dims(stream, fallback) {
    const track = stream && stream.getVideoTracks()[0];
    const s = track && track.getSettings ? track.getSettings() : {};
    return s.width && s.height ? { width: s.width, height: s.height } : fallback;
  }

  buildComposite() {
    this.destroyComposite();
    const scr = this.screenActive ? this.screen.stream : null;
    const cam = this.camera.status === 'ok' ? this.camera.stream : null;
    const layout = composeLayout(scr ? this.dims(scr, { width: 1280, height: 720 }) : null, cam ? this.dims(cam, { width: 640, height: 360 }) : null);
    if (!layout || !this.supported) return;
    const canvas = h('canvas', { width: String(layout.width), height: String(layout.height) });
    const ctx = canvas.getContext('2d', { alpha: false });
    const sv = scr ? this.videoFor(scr) : null;
    const cv = cam ? this.videoFor(cam) : null;
    const draw = () => {
      ctx.fillStyle = '#10141c';
      ctx.fillRect(0, 0, layout.width, layout.height);
      if (sv && layout.screen && sv.readyState >= 2) ctx.drawImage(sv, 0, 0, layout.width, layout.height);
      if (cv && layout.cam && cv.readyState >= 2) {
        ctx.drawImage(cv, layout.cam.x, layout.cam.y, layout.cam.w, layout.cam.h);
        if (layout.screen) {
          ctx.strokeStyle = '#ffffff';
          ctx.lineWidth = 2;
          ctx.strokeRect(layout.cam.x, layout.cam.y, layout.cam.w, layout.cam.h);
        }
      }
      const label = `${this.o.code || ''} · ${SECTION[this.section] || ''} · ${clock()}`;
      ctx.font = '600 14px system-ui, sans-serif';
      const width = ctx.measureText(label).width + 16;
      ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
      ctx.fillRect(8, 8, width, 24);
      ctx.fillStyle = '#ffffff';
      ctx.fillText(label, 16, 25);
    };
    draw();
    const timer = setInterval(draw, Math.round(1000 / layout.fps));
    const stream = canvas.captureStream(layout.fps);
    this.comp = { canvas, timer, stream, layout, videos: [sv, cv].filter(Boolean) };
  }

  destroyComposite() {
    if (!this.comp) return;
    clearInterval(this.comp.timer);
    this.comp.stream.getTracks().forEach((t) => t.stop());
    this.comp.videos.forEach((v) => {
      v.srcObject = null;
      v.remove();
    });
    this.comp = null;
  }

  /** Joriy faylni yopib, yangisini boshlash (bo'lim, manba yoki vaqt o'zgarganda). */
  rotate() {
    clearTimeout(this.segTimer);
    const old = this.seg;
    this.seg = null;
    if (this.section && !this.stopped && this.hasSources()) {
      if (!this.comp) this.buildComposite();
      if (this.comp) this.seg = this.startSegment();
    }
    if (old) this.finishSegment(old);
    if (this.seg) this.segTimer = setTimeout(() => this.rotate(), this.segmentMs);
  }

  startSegment() {
    const withAudio = Boolean(this.audioTrack && this.audioTrack.readyState === 'live');
    this.badMimes = this.badMimes || new Set();
    // Ba'zi kompyuterlarda format "qo'llanadi" deyiladi-yu, kodlovchi ishga tushmaydi — bunday format keyingi
    // safar tashlab o'tiladi (boshqasi tanlanadi).
    const mime = pickVideoMime((t) => !this.badMimes.has(t) && MediaRecorder.isTypeSupported(t), withAudio);
    const tracks = [this.comp.stream.getVideoTracks()[0]];
    if (withAudio) tracks.push(this.audioTrack);
    let recorder;
    try {
      const opts = { videoBitsPerSecond: this.kbps * 1000, audioBitsPerSecond: 32000 };
      if (mime) opts.mimeType = mime;
      recorder = new MediaRecorder(new MediaStream(tracks), opts);
    } catch (err) {
      if (mime && !this.badMimes.has(mime)) {
        this.badMimes.add(mime);
        return this.startSegment();
      }
      this.report('rec_unsupported', String((err && err.name) || err).slice(0, 100));
      return null;
    }
    const seg = {
      key: segmentKey(),
      section: this.section,
      recorder,
      mime: recorder.mimeType || mime || 'video/webm',
      content: this.comp.layout.content,
      audio: withAudio,
      width: this.comp.layout.width,
      height: this.comp.layout.height,
      next: 0,
      bytes: 0,
      started: Date.now(),
      dropped: false,
    };
    recorder.ondataavailable = (e) => this.onData(seg, e.data);
    recorder.onstop = () => this.onStop(seg);
    recorder.onerror = (e) => {
      this.report('rec_error', `${String((e && e.error && e.error.name) || 'recorder')} (${seg.mime})`.slice(0, 200));
      // Boshida buzilgan format — boshqasi bilan qayta boshlanadi.
      if (mime && Date.now() - seg.started < 20000 && !this.badMimes.has(mime)) {
        this.badMimes.add(mime);
        if (seg === this.seg) this.rotate();
      }
    };
    try {
      recorder.start(PIECE_MS);
    } catch (err) {
      if (mime && !this.badMimes.has(mime)) {
        this.badMimes.add(mime);
        return this.startSegment();
      }
      this.report('rec_error', String((err && err.name) || err).slice(0, 100));
      return null;
    }
    this.everRecorded = true;
    return seg;
  }

  finishSegment(seg) {
    if (seg.recorder.state !== 'inactive') {
      try {
        seg.recorder.stop();
      } catch {
        this.onStop(seg);
      }
    }
  }

  onData(seg, blob) {
    if (!blob || !blob.size || seg.dropped) return;
    seg.mime = seg.recorder.mimeType || seg.mime;
    this.enqueue({ seg, n: seg.next++, blob, final: false, duration: Date.now() - seg.started });
    seg.bytes += blob.size;
    if (seg === this.seg && seg.bytes > MAX_SEGMENT_BYTES) this.rotate();
  }

  onStop(seg) {
    if (seg.ended) return;
    seg.ended = true;
    // Yozuv o'zi to'xtab qolgan bo'lsa (kodlovchi xatosi, manba uzilgan) — qisqa tanaffusdan keyin yangisi boshlanadi.
    if (seg === this.seg && !this.stopped) {
      this.seg = null;
      clearTimeout(this.segTimer);
      const now = Date.now();
      this.restarts = (this.restarts || []).filter((t) => now - t < 60000).concat(now);
      if (this.restarts.length <= 5) {
        setTimeout(() => {
          if (!this.seg && !this.stopped) this.rotate();
        }, 1000);
      } else {
        this.report('rec_error', "Video yozuv qayta-qayta to'xtadi — yozish to'xtatildi");
        this.renderBadge();
      }
    }
    if (seg.dropped) return;
    // Oxirgi bo'lak hali yuborilmagan bo'lsa — "yakuniy" deb belgilanadi; aks holda bo'sh yakunlash belgisi.
    for (let i = this.queue.length - 1; i >= 0; i -= 1) {
      const p = this.queue[i];
      if (p.seg === seg) {
        if (!p.sending) {
          p.final = true;
          this.pump();
          return;
        }
        break;
      }
    }
    if (seg.next === 0) return; // Hech narsa yozilmagan.
    this.enqueue({ seg, n: seg.next++, blob: null, final: true, duration: Date.now() - seg.started });
  }

  enqueue(piece) {
    if (this.closed) return;
    this.queue.push(piece);
    this.queueBytes += piece.blob ? piece.blob.size : 0;
    // Internet uzoq uzilsa xotira to'lmasin: eng eski fayllar (joriy emas) tashlanadi.
    while (this.queueBytes > MAX_QUEUE_BYTES) {
      const victim = this.queue.find((p) => !p.sending && p.seg !== this.seg);
      if (!victim) break;
      this.dropSegment(victim.seg, 'navbat to\'ldi');
    }
    this.pump();
  }

  dropSegment(seg, reason) {
    if (seg.dropped) return;
    seg.dropped = true;
    this.queue = this.queue.filter((p) => {
      if (p.seg === seg && !p.sending) {
        this.queueBytes -= p.blob ? p.blob.size : 0;
        return false;
      }
      return true;
    });
    this.report('rec_dropped', `${SECTION[seg.section] || seg.section}: ${reason}`.slice(0, 200));
  }

  async pump() {
    if (this.uploading || this.closed) return;
    this.uploading = true;
    let delay = 2000;
    try {
      while (this.queue.length && !this.closed) {
        const p = this.queue[0];
        if (p.seg.dropped && !p.sending) {
          this.queue.shift();
          this.queueBytes -= p.blob ? p.blob.size : 0;
          continue;
        }
        p.sending = true;
        const form = new FormData();
        form.append('client_id', this.o.clientId);
        form.append('seg', p.seg.key);
        form.append('piece', String(p.n));
        form.append('section', p.seg.section);
        form.append('content', p.seg.content);
        form.append('audio', p.seg.audio ? '1' : '0');
        form.append('mime', p.seg.mime);
        form.append('final', p.final ? '1' : '0');
        form.append('duration_ms', String(Math.round(p.duration)));
        form.append('width', String(p.seg.width));
        form.append('height', String(p.seg.height));
        if (p.blob) form.append('data', p.blob, `${p.seg.key}.${p.n}.${mimeExt(p.seg.mime)}`);
        try {
          await upload(`exam/${this.o.attemptId}/rec/piece`, form);
          this.queue.shift();
          this.queueBytes -= p.blob ? p.blob.size : 0;
          this.lastUploadAt = Date.now();
          delay = 2000;
        } catch (err) {
          p.sending = false;
          const rejected = err instanceof ApiError && !err.isNetwork && err.status < 500 && err.status !== 408 && err.status !== 429;
          if (!rejected) {
            await sleep(delay);
            delay = Math.min(delay * 2, 30000);
            continue;
          }
          if (FATAL_CODES.has(err.code) || err.status === 401 || err.status === 403) {
            // Yozuv endi qabul qilinmaydi (imtihon yopilgan, joy to'lgan, chegara va h.k.) — imtihon videosiz davom etadi.
            if (err.code !== 'taken_over' && err.code !== 'rec_closed') this.report('rec_error', `${err.code}: ${err.message}`.slice(0, 200));
            this.closed = true;
            this.queue = [];
            this.queueBytes = 0;
            this.stop();
            break;
          }
          // Faylning shu qismi qabul qilinmadi (tartib buzilgan, juda katta va h.k.) — fayl tashlanadi, yangisi boshlanadi.
          this.dropSegment(p.seg, err.code || String(err.status));
          if (p.seg === this.seg) this.rotate();
        }
      }
    } finally {
      this.uploading = false;
    }
  }

  /** Yuborilmagan hajm (bayt). */
  pendingBytes() {
    return this.queue.reduce((sum, p) => sum + (p.blob ? p.blob.size : 0), 0);
  }

  pendingCount() {
    return this.queue.length + (this.seg ? 1 : 0);
  }

  // ------------------------------------------------------------------
  // Uzilishlar
  // ------------------------------------------------------------------

  onScreenStopped() {
    if (this.screen.status !== 'ok' || this.stopped || this.speaking) return;
    this.screen.status = 'stopped';
    this.screen.stream = null;
    const required = this.screen.mode === 'required';
    this.report('screen_stopped', "O'quvchi ekran ulashishni to'xtatdi", required && Boolean(this.section));
    this.sourcesChanged();
    if (this.section) this.showAlert('screen');
  }

  onCameraLost() {
    if (this.camera.status !== 'ok' || this.stopped) return;
    this.camera.status = 'lost';
    this.camera.stream = null;
    const required = this.camera.mode === 'required';
    this.report('camera_lost', 'Kamera uzildi', required && Boolean(this.section));
    this.sourcesChanged();
    if (this.section) this.showAlert('camera');
  }

  /** Majburiy bo'lsa — imtihon ustini yopadigan oyna; ixtiyoriy bo'lsa — yuqorida ogohlantirish. */
  showAlert(kind) {
    this.hideAlert();
    const isScreen = kind === 'screen';
    const required = (isScreen ? this.screen : this.camera).mode === 'required';
    const fix = h('button', { class: 'btn btn-primary', type: 'button' }, icon(isScreen ? 'monitor' : 'camera'), isScreen ? T.reshare : T.recamera);
    fix.onclick = async () => {
      fix.disabled = true;
      if (isScreen) await this.enableScreen();
      else await this.enableCamera();
      fix.disabled = false;
      if ((isScreen ? this.screen : this.camera).status === 'ok') this.hideAlert();
    };
    if (required) {
      this.alert = h('div', { class: 'lock-overlay proctor-overlay' },
        h('div', { class: 'lock-card', role: 'alertdialog' },
          h('h2', { text: isScreen ? T.screenStoppedTitle : T.cameraLostTitle }),
          h('p', { text: isScreen ? T.screenStoppedText : T.cameraLostText }),
          isScreen ? h('p', { class: 'muted small', text: T.scrHint }) : null,
          fix
        ));
    } else {
      this.alert = h('div', { class: 'proctor-banner', role: 'status' },
        icon('alert'),
        h('span', { text: isScreen ? T.screenStoppedOptional : T.cameraLostOptional }),
        fix,
        h('button', { class: 'btn btn-sm', type: 'button', onclick: () => this.hideAlert() }, T.dismiss)
      );
    }
    document.body.append(this.alert);
  }

  hideAlert() {
    if (this.alert) this.alert.remove();
    this.alert = null;
  }

  // ------------------------------------------------------------------
  // Yozuv belgisi (o'quvchi kamerasini ko'rib turadi)
  // ------------------------------------------------------------------

  mountBadge() {
    if (this.badge || !this.enabled) {
      this.renderBadge();
      return;
    }
    this.badgeVideo = h('video', { muted: true, autoplay: true, playsinline: true });
    this.badgeVideo.muted = true;
    this.badgeLabel = h('span', { class: 'rec-label', text: T.recBadge });
    this.badge = h('div', { class: 'rec-badge', 'aria-live': 'polite' }, this.badgeVideo, h('span', { class: 'rec-line' }, h('span', { class: 'rec-dot' }), this.badgeLabel));
    document.body.append(this.badge);
    this.renderBadge();
  }

  renderBadge() {
    if (!this.badge) return;
    const cam = this.camera.status === 'ok' ? this.camera.stream : null;
    if (this.badgeVideo.srcObject !== cam) {
      this.badgeVideo.srcObject = cam;
      if (cam) this.badgeVideo.play().catch(() => {});
    }
    this.badgeVideo.hidden = !cam;
    this.badge.hidden = !this.seg;
  }

  // ------------------------------------------------------------------
  // Yakunlash
  // ------------------------------------------------------------------

  /** Yozishni to'xtatish (imtihon tugadi). Navbatdagi bo'laklar yuborilishda davom etadi. */
  stop() {
    if (this.stopped) return;
    this.stopped = true;
    clearTimeout(this.segTimer);
    const seg = this.seg;
    this.seg = null;
    if (seg) this.finishSegment(seg);
    this.hideAlert();
    // Yakuniy bo'lak MediaRecorder'dan kelgach manbalar yopiladi.
    setTimeout(() => this.releaseDevices(), 1500);
    if (this.badge) {
      this.badge.remove();
      this.badge = null;
    }
  }

  releaseDevices() {
    this.destroyComposite();
    for (const kind of [this.camera, this.screen]) {
      if (kind.stream) kind.stream.getTracks().forEach((t) => t.stop());
      kind.stream = null;
    }
    if (this.audioTrack) this.audioTrack.stop();
    this.audioTrack = null;
    this.sources.remove();
  }

  /** Yakuniy ekran uchun: yuborilish holati (tugaguncha yangilanadi). */
  uploadStatus() {
    if (!this.enabled || !this.everRecorded) return null;
    const line = h('p', { class: 'rec-upload muted small' });
    const update = () => {
      if (!line.isConnected && line.dataset.mounted) {
        clearInterval(timer);
        return;
      }
      line.dataset.mounted = '1';
      const bytes = this.pendingBytes();
      if (!this.queue.length && !this.uploading) {
        line.className = 'rec-upload ok small';
        setText(line, T.recUploaded);
        clearInterval(timer);
        return;
      }
      const stalled = Date.now() - this.lastUploadAt > 60000;
      line.className = `rec-upload small ${stalled ? 'bad' : 'muted'}`;
      setText(line, stalled ? T.recUploadStalled : T.recUploading((bytes / 1048576).toFixed(1)));
    };
    const timer = setInterval(update, 1000);
    setTimeout(update, 50);
    return line;
  }

  /** Sahifa yopilayotganda: yuborilmagan yozuv bo'lsa — ogohlantirish. */
  installUnloadGuard() {
    if (this.unloadGuard) return;
    this.unloadGuard = (e) => {
      if (this.queue.length && !this.closed) {
        e.preventDefault();
        e.returnValue = '';
      }
    };
    window.addEventListener('beforeunload', this.unloadGuard);
  }
}

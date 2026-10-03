// Speaking bo'limi: mikrofon tekshiruvi, savollar ketma-ketligi, tayyorlanish va javob taymerlari,
// yozuv va serverga yuklash (uzilsa qayta urinish).
// Mikrofon bir marta ochiladi va butun bo'lim davomida ishlatiladi (Android har so'rovda ruxsat so'ramasligi uchun).

import { ApiError, apiUrl, post, upload } from '../../lib/api.js';
import { h, icon, setText } from '../../lib/dom.js';
import { renderRich } from '../../lib/markup.js';
import { formatClock } from '../../lib/text.js';
import { T } from '../../lib/uz.js';

export function pickMime() {
  if (!window.MediaRecorder) return null;
  const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg'];
  for (const type of candidates) {
    if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(type)) return type;
  }
  return '';
}

export class Microphone {
  constructor() {
    this.stream = null;
    this.ctx = null;
    this.analyser = null;
  }

  async open() {
    if (this.stream) return this.stream;
    // http:// sahifada brauzer mikrofonga umuman ruxsat bermaydi (sababi o'quvchiga aniq aytiladi).
    if (window.isSecureContext === false) throw new Error('insecure');
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || pickMime() === null) {
      throw new Error('unsupported');
    }
    this.stream = await navigator.mediaDevices.getUserMedia({
      audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
    });
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      this.ctx = new Ctx();
      const source = this.ctx.createMediaStreamSource(this.stream);
      this.analyser = this.ctx.createAnalyser();
      this.analyser.fftSize = 512;
      source.connect(this.analyser);
    } catch {
      this.analyser = null;
    }
    return this.stream;
  }

  level() {
    if (!this.analyser) return 0;
    const data = new Uint8Array(this.analyser.fftSize);
    this.analyser.getByteTimeDomainData(data);
    let sum = 0;
    for (const v of data) sum += ((v - 128) / 128) ** 2;
    return Math.min(1, Math.sqrt(sum / data.length) * 4);
  }

  meter() {
    const bar = h('span', { class: 'meter-bar' });
    const el = h('div', { class: 'meter', 'aria-hidden': 'true' }, bar);
    const loop = () => {
      if (!el.isConnected) return;
      bar.style.width = `${Math.round(this.level() * 100)}%`;
      requestAnimationFrame(loop);
    };
    requestAnimationFrame(loop);
    return el;
  }

  setEnabled(on) {
    if (this.stream) this.stream.getAudioTracks().forEach((t) => (t.enabled = on));
  }

  beep() {
    return new Promise((resolve) => {
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        const ctx = this.ctx || new Ctx();
        if (ctx.state === 'suspended') ctx.resume();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.frequency.value = 880;
        gain.gain.value = 0.2;
        osc.connect(gain).connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.35);
        osc.onended = () => resolve();
      } catch {
        resolve();
      }
      setTimeout(resolve, 500);
    });
  }

  /**
   * Belgilangan vaqt davomida yozish. control.stop() — erta tugatish (administrator ruxsat bergan bo'lsa).
   * @returns {Promise<{blob: Blob, duration: number}>}
   */
  record(seconds, onTick, control = null) {
    return new Promise((resolve, reject) => {
      const mime = pickMime();
      let recorder;
      try {
        recorder = mime ? new MediaRecorder(this.stream, { mimeType: mime, audioBitsPerSecond: 32000 }) : new MediaRecorder(this.stream);
      } catch (err) {
        reject(err);
        return;
      }
      const chunks = [];
      const started = performance.now();
      recorder.ondataavailable = (e) => e.data && e.data.size && chunks.push(e.data);
      recorder.onerror = (e) => reject(e.error || new Error('recorder'));
      recorder.onstop = () => {
        clearInterval(timer);
        const duration = (performance.now() - started) / 1000;
        resolve({ blob: new Blob(chunks, { type: recorder.mimeType || mime || 'audio/webm' }), duration });
      };
      const timer = setInterval(() => {
        const left = seconds * 1000 - (performance.now() - started);
        onTick && onTick(Math.max(0, left));
        if (left <= 0 && recorder.state === 'recording') recorder.stop();
      }, 200);
      if (control) {
        control.stop = () => {
          if (recorder.state === 'recording') recorder.stop();
        };
      }
      recorder.start(1000);
    });
  }

  close() {
    if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
    this.stream = null;
    if (this.ctx) this.ctx.close().catch(() => {});
    this.ctx = null;
  }
}

/** Mikrofon tekshiruvi ekrani (qurilma sinovi). */
export function micCheck(mic, onReady) {
  const status = h('p', { class: 'mic-status' });
  const meterSlot = h('div', { class: 'meter-slot' });
  const playback = h('audio', { controls: true, hidden: true, class: 'mic-playback' });
  const testBtn = h('button', { class: 'btn', type: 'button', disabled: true }, T.micTest);
  const allowBtn = h('button', { class: 'btn btn-primary', type: 'button' }, icon('mic'), T.micAllow);

  allowBtn.onclick = async () => {
    try {
      await mic.open();
      meterSlot.replaceChildren(mic.meter());
      setText(status, T.micOk);
      status.className = 'mic-status ok';
      allowBtn.hidden = true;
      testBtn.disabled = false;
      onReady(true);
    } catch (err) {
      const reason = err && err.message;
      setText(status, reason === 'insecure' ? T.micInsecure : reason === 'unsupported' ? T.micUnsupported : T.micDenied);
      status.className = 'mic-status bad';
      onReady(false);
    }
  };
  testBtn.onclick = async () => {
    testBtn.disabled = true;
    const result = await mic.record(5, (left) => setText(testBtn, `${T.recording}: ${Math.ceil(left / 1000)}`));
    playback.src = URL.createObjectURL(result.blob);
    playback.hidden = false;
    setText(testBtn, T.micTest);
    testBtn.disabled = false;
  };
  return h('div', { class: 'mic-check' }, h('h3', { text: T.micCheck }), allowBtn, meterSlot, status, h('div', { class: 'mic-actions' }, testBtn), playback);
}

/** Speaking savollarini ketma-ket o'tkazish. */
export class SpeakingRunner {
  /**
   * @param {{attemptId:number, clientId:string, mic: Microphone, root: HTMLElement, total: number,
   *          uploaded: number[], onDone: (state:object) => void, onFatal: (err: ApiError) => void}} options
   */
  constructor(options) {
    this.o = options;
    this.queue = [];
    this.uploading = false;
    this.uploaded = new Set(options.uploaded || []);
    this.status = h('div', { class: 'upload-status', role: 'status' });
  }

  async call(path, body = {}) {
    let delay = 1500;
    for (;;) {
      try {
        return await post(`exam/${this.o.attemptId}/${path}`, { client_id: this.o.clientId, ...body });
      } catch (err) {
        if (err instanceof ApiError && err.code === 'too_early') {
          // Oldingi savol vaqti serverda hali tugamagan (masalan, sahifa yangilangandan keyin) — kutamiz.
          const wait = Math.min(Number(err.data.wait_ms) || 1000, 600000) + 300;
          await this.waitScreen(wait);
          continue;
        }
        if (!(err instanceof ApiError) || !err.isNetwork) throw err;
        setText(this.status, T.offline);
        await new Promise((r) => setTimeout(r, delay));
        delay = Math.min(delay * 2, 10000);
      }
    }
  }

  waitScreen(ms) {
    const value = h('span', { class: 'cd-value' });
    this.o.root.replaceChildren(h('div', { class: 'speaking-card center' },
      h('p', { text: T.nextQuestionIn }),
      h('div', { class: 'countdown' }, value)
    ));
    const end = performance.now() + ms;
    return new Promise((resolve) => {
      const loop = () => {
        const left = end - performance.now();
        setText(value, formatClock(left));
        if (left <= 0) resolve();
        else setTimeout(loop, 250);
      };
      loop();
    });
  }

  enqueue(no, blob, duration) {
    this.queue.push({ no, blob, duration });
    this.pump();
  }

  async pump() {
    if (this.uploading) return;
    this.uploading = true;
    let delay = 2000;
    while (this.queue.length) {
      const item = this.queue[0];
      const form = new FormData();
      form.append('client_id', this.o.clientId);
      form.append('q_no', String(item.no));
      form.append('duration', item.duration.toFixed(2));
      const ext = item.blob.type.includes('mp4') ? 'm4a' : item.blob.type.includes('ogg') ? 'ogg' : 'webm';
      form.append('audio', item.blob, `q${item.no}.${ext}`);
      try {
        setText(this.status, T.uploading);
        await upload(`exam/${this.o.attemptId}/speaking/upload`, form);
        this.uploaded.add(item.no);
        this.queue.shift();
        delay = 2000;
        setText(this.status, '');
      } catch (err) {
        if (err instanceof ApiError && !err.isNetwork && err.status !== 500) {
          // Tuzatib bo'lmaydigan xato (masalan, muddat o'tgan) — navbatdan chiqaramiz.
          this.queue.shift();
          setText(this.status, err.message);
          if (err.status === 409) {
            this.o.onFatal(err);
            break;
          }
          continue;
        }
        setText(this.status, T.uploadFailed);
        await new Promise((r) => setTimeout(r, delay));
        delay = Math.min(delay * 2, 15000);
      }
    }
    this.uploading = false;
  }

  async waitUploads() {
    while (this.queue.length || this.uploading) {
      await new Promise((r) => setTimeout(r, 400));
      if (!this.uploading && this.queue.length) this.pump();
    }
  }

  /** Tayyorlanish taymeri. skipLabel berilsa — tugma bilan erta tugatish mumkin. */
  countdown(seconds, label, container, skipLabel = null) {
    return new Promise((resolve) => {
      const value = h('span', { class: 'cd-value' });
      let done = false;
      const finish = () => {
        if (done) return;
        done = true;
        resolve();
      };
      const skip = skipLabel ? h('button', { class: 'btn btn-sm cd-skip', type: 'button', onclick: finish }, skipLabel) : null;
      container.replaceChildren(h('div', { class: 'countdown' }, h('span', { class: 'cd-label', text: label }), value, skip));
      const end = performance.now() + seconds * 1000;
      const loop = () => {
        if (done) return;
        const left = end - performance.now();
        setText(value, formatClock(left));
        if (left <= 0) finish();
        else setTimeout(loop, 200);
      };
      loop();
    });
  }

  playQuestionAudio(asset) {
    return new Promise((resolve) => {
      const audio = new Audio(apiUrl(`exam/${this.o.attemptId}/speaking/asset/${asset}`));
      this.o.mic.setEnabled(false);
      const done = () => {
        this.o.mic.setEnabled(true);
        resolve();
      };
      audio.onended = done;
      audio.onerror = done;
      audio.play().catch(done);
    });
  }

  renderQuestion(q) {
    const images = (q.images || []).map((asset) =>
      h('img', { class: 'sq-image', alt: 'Picture', src: apiUrl(`exam/${this.o.attemptId}/speaking/asset/${asset}`), draggable: 'false' })
    );
    const lists = (q.for && q.for.length) || (q.against && q.against.length)
      ? h('div', { class: 'sq-lists' },
        h('div', { class: 'sq-list' }, h('h4', { text: T.for }), h('ul', null, (q.for || []).map((t) => h('li', { text: t })))),
        h('div', { class: 'sq-list' }, h('h4', { text: T.against }), h('ul', null, (q.against || []).map((t) => h('li', { text: t }))))
      )
      : null;
    const timerSlot = h('div', { class: 'sq-timer' });
    const meterSlot = h('div', { class: 'sq-meter' });
    const card = h('div', { class: 'speaking-card', lang: 'en' },
      h('div', { class: 'sq-head' },
        h('span', { class: 'sq-part', text: q.part_title || `Part ${q.part_id}` }),
        h('span', { class: 'sq-count', text: T.question(q.no, this.o.total) })
      ),
      q.instructions ? h('div', { class: 'sq-instructions rich' }, renderRich(q.instructions)) : null,
      q.topic ? h('div', { class: 'sq-topic rich' }, renderRich(q.topic)) : null,
      images.length ? h('div', { class: `sq-images n${images.length}` }, images) : null,
      lists,
      h('div', { class: 'sq-text rich' }, renderRich(q.text)),
      timerSlot,
      meterSlot,
      this.status
    );
    this.o.root.replaceChildren(card);
    return { timerSlot, meterSlot };
  }

  async run(resume = null) {
    if (resume && resume.missed) {
      await new Promise((resolve) => {
        this.o.root.replaceChildren(h('div', { class: 'speaking-card center' },
          h('p', { text: T.missedQuestion }),
          h('button', { class: 'btn btn-primary', type: 'button', onclick: resolve }, T.continue)
        ));
      });
    }
    for (;;) {
      // "Javobni erta tugatish" yoqilgan bo'lsa: server keyingi savolni oldingi javob yuklangach darhol ochadi.
      if (this.o.skip) await this.waitUploads();
      const res = await this.call('speaking/next');
      if (res.done) break;
      const q = res.question;
      const { timerSlot, meterSlot } = this.renderQuestion(q);
      if (q.audio) {
        timerSlot.replaceChildren(h('div', { class: 'countdown' }, h('span', { class: 'cd-label', text: T.listenQuestion })));
        await this.playQuestionAudio(q.audio);
      }
      if (q.prep_sec > 0) await this.countdown(q.prep_sec, T.prepare, timerSlot, this.o.skip ? T.skipPrep : null);
      await this.o.mic.beep();
      meterSlot.replaceChildren(this.o.mic.meter());
      const value = h('span', { class: 'cd-value' });
      // Administrator ruxsat bergan bo'lsa: javobni vaqt tugamasdan yakunlab, keyingi savolga o'tish.
      const control = this.o.skip ? {} : null;
      const stopBtn = control
        ? h('button', { class: 'btn btn-sm cd-skip', type: 'button', onclick: () => control.stop && control.stop() }, T.finishAnswer)
        : null;
      timerSlot.replaceChildren(h('div', { class: 'countdown recording' }, h('span', { class: 'rec-dot' }), h('span', { class: 'cd-label', text: T.speakNow }), value, stopBtn));
      const result = await this.o.mic.record(q.answer_sec, (left) => setText(value, formatClock(left)), control);
      meterSlot.replaceChildren();
      this.enqueue(q.no, result.blob, result.duration);
    }
    this.o.root.replaceChildren(h('div', { class: 'speaking-card center' }, h('p', { text: T.uploading }), this.status));
    await this.waitUploads();
    const state = await this.call('speaking/finish');
    this.o.onDone(state);
  }
}

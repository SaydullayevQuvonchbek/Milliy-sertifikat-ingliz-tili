// Javoblarni saqlash: avval darhol qurilmaga (localStorage), keyin serverga.
// Internet uzilsa — javoblar yo'qolmaydi, sahifa qotmaydi; aloqa tiklanishi bilan yuboriladi.
// Har bir o'zgarish tartib raqami (seq) oladi — server eski so'rovni yangisining ustiga yozmaydi.

import { post } from '../../lib/api.js';

const HEARTBEAT_MS = 15000;
const DEBOUNCE_MS = 1200;
const MAX_WAIT_MS = 6000;

function storageKey(attemptId, section) {
  return `mlm:${attemptId}:${section}`;
}

function readJson(key) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function writeJson(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
    return true;
  } catch {
    return false;
  }
}

export class Saver {
  /**
   * @param {{
   *   attemptId: number, clientId: string, section: string, seq: number,
   *   getPayload: () => object,
   *   onStatus: (status: 'saved'|'saving'|'offline') => void,
   *   onSummary: (summary: object) => void,
   *   onFatal: (code: string, message: string) => void,
   * }} options
   */
  constructor(options) {
    this.o = options;
    this.seq = options.seq || 0;
    this.ackedSeq = options.seq || 0;
    this.events = readJson(`mlm:${options.attemptId}:events`) || [];
    this.inFlight = false;
    this.again = false;
    this.timer = null;
    this.firstDirtyAt = 0;
    this.retryDelay = 2000;
    this.lastSyncAt = 0;
    this.stopped = false;
    this.localTimer = null;
    this.onOnline = () => this.sync();
    this.onOffline = () => this.o.onStatus('offline');
  }

  static loadLocal(attemptId, section) {
    return readJson(storageKey(attemptId, section));
  }

  static clearLocal(attemptId, section) {
    try {
      localStorage.removeItem(storageKey(attemptId, section));
    } catch {
      /* e'tiborsiz */
    }
  }

  start() {
    this.heartbeat = setInterval(() => {
      if (Date.now() - this.lastSyncAt > HEARTBEAT_MS - 1000) this.sync();
    }, HEARTBEAT_MS);
    window.addEventListener('online', this.onOnline);
    window.addEventListener('offline', this.onOffline);
    if (this.seq > this.ackedSeq || this.events.length) this.schedule(300);
  }

  stop() {
    this.stopped = true;
    clearInterval(this.heartbeat);
    clearTimeout(this.timer);
    clearTimeout(this.localTimer);
    window.removeEventListener('online', this.onOnline);
    window.removeEventListener('offline', this.onOffline);
  }

  /**
   * Faol bo'limni ulash. seq — qurilmadagi eng so'nggi tartib raqami, acked — server tasdiqlagani.
   * getPayload null bo'lsa, faqat hodisalar va "yurak urishi" yuboriladi.
   */
  bind(section, getPayload, seq = 0, acked = seq) {
    this.o.section = section;
    this.o.getPayload = getPayload;
    this.seq = Math.max(seq, acked);
    this.ackedSeq = acked;
    this.firstDirtyAt = this.dirty ? Date.now() : 0;
    if (this.dirty) this.schedule(300);
  }

  unbind() {
    this.o.section = null;
    this.o.getPayload = null;
    this.ackedSeq = this.seq;
  }

  get dirty() {
    return Boolean(this.o.getPayload) && this.seq > this.ackedSeq;
  }

  /** Javob o'zgardi. */
  changed() {
    if (!this.o.getPayload) return;
    this.seq += 1;
    if (!this.firstDirtyAt) this.firstDirtyAt = Date.now();
    clearTimeout(this.localTimer);
    this.localTimer = setTimeout(() => this.persistLocal(), 200);
    const waited = Date.now() - this.firstDirtyAt;
    this.schedule(waited > MAX_WAIT_MS ? 0 : DEBOUNCE_MS);
    this.o.onStatus(navigator.onLine === false ? 'offline' : 'saving');
  }

  persistLocal() {
    if (!this.o.getPayload || !this.o.section) return;
    writeJson(storageKey(this.o.attemptId, this.o.section), { seq: this.seq, data: this.o.getPayload(), t: Date.now() });
  }

  /**
   * Hodisa (qoidabuzarlik yoki ma'lumot) — keyingi so'rov bilan serverga ketadi.
   * Har hodisaning o'z identifikatori bor: qayta yuborilsa ham server uni bir marta hisoblaydi.
   */
  event(type, detail = '', urgent = false, section = null) {
    const id = Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
    this.events.push({ id, type, detail: String(detail).slice(0, 300), t: Date.now(), section: section || this.o.section || undefined });
    if (this.events.length > 200) this.events.splice(0, this.events.length - 200);
    writeJson(`mlm:${this.o.attemptId}:events`, this.events);
    this.schedule(urgent ? 0 : 5000);
  }

  schedule(delay) {
    if (this.stopped) return;
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.sync(), delay);
  }

  async sync() {
    if (this.stopped) return null;
    if (this.inFlight) {
      this.again = true;
      return null;
    }
    clearTimeout(this.timer);
    this.inFlight = true;
    const sentSeq = this.seq;
    const sentEvents = this.events.slice();
    const body = { client_id: this.o.clientId, events: sentEvents };
    if (this.dirty) {
      this.persistLocal();
      Object.assign(body, { section: this.o.section, seq: sentSeq }, this.o.getPayload());
    }
    this.lastSyncAt = Date.now();
    try {
      const summary = await post(`exam/${this.o.attemptId}/sync`, body, { timeout: 15000 });
      this.retryDelay = 2000;
      this.events.splice(0, sentEvents.length);
      writeJson(`mlm:${this.o.attemptId}:events`, this.events);
      if (body.seq !== undefined) {
        if (summary.accepted) this.ackedSeq = Math.max(this.ackedSeq, sentSeq);
        else if (summary.stage === this.o.section && summary.stage_state === 'active' && summary.save_seq >= sentSeq) {
          // Server bizdan kattaroq tartib raqamini ko'rgan (masalan, boshqa oynadan) — raqamni oldinga suramiz.
          this.seq = summary.save_seq + 1;
          this.again = true;
        }
      }
      if (!this.dirty) this.firstDirtyAt = 0;
      this.o.onStatus(this.dirty ? 'saving' : 'saved');
      this.o.onSummary(summary);
      return summary;
    } catch (err) {
      if (err.status === 409 || err.status === 401 || err.status === 403) {
        this.stop();
        this.o.onFatal(err.code, err.message);
        return null;
      }
      this.o.onStatus('offline');
      this.retryDelay = Math.min(this.retryDelay * 2, 15000);
      this.schedule(this.retryDelay);
      return null;
    } finally {
      this.inFlight = false;
      if (this.again && !this.stopped) {
        this.again = false;
        this.schedule(200);
      }
    }
  }
}

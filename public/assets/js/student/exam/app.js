// Imtihon jarayonini boshqaruvchi: server holatiga qarab kerakli ekranni ko'rsatadi.
// Sahifa o'z-o'zidan qayta chizilmaydi — ekran faqat bosqich almashganda (masalan, Listening → Reading)
// yangilanadi. Taymer, saqlash holati va navigator o'z elementlarini joyida yangilaydi.

import { ApiError, get, post, serverNow } from '../../lib/api.js';
import { h, icon, setText } from '../../lib/dom.js';
import { formatClock, formatMinutes, randomId } from '../../lib/text.js';
import { confirmDialog, modal, spinner, toast } from '../../lib/ui.js';
import { SECTION, T } from '../../lib/uz.js';
import { Lockdown } from './lockdown.js';
import { Saver } from './saver.js';
import { ExamShell } from './shell.js';
import { QuestionSection } from './questionSection.js';
import { WritingSection } from './writing.js';
import { ListeningPlayer, createAudioElement, preloadAudio, unlockAudio } from './listening.js';
import { Microphone, SpeakingRunner, micCheck } from './speaking.js';

const WARN_AT = [10, 5, 1];
/** Sahifani yangilash uchun ruxsat etilgan vaqt; undan uzoq yopiq tursa — qoidabuzarlik. */
const RELOAD_GRACE_SEC = 20;

export class ExamApp {
  /**
   * @param {HTMLElement} root
   * @param {number} attemptId
   * @param {{intent?: string, onExit: () => void}} options
   */
  constructor(root, attemptId, options) {
    this.root = root;
    this.id = attemptId;
    this.options = options;
    this.clientId = this.loadClientId();
    this.state = null;
    this.view = null;
    this.violations = 0;
    this.finishing = false;
    this.destroyed = false;
    this.audio = createAudioElement();
    this.urls = null;
    this.mic = null;
    this.ticker = setInterval(() => this.tick(), 250);
  }

  /** Qurilma identifikatori shu brauzerda saqlanadi — tasodifan yopilgan oynani qayta ochish "boshqa qurilma" hisoblanmaydi. */
  loadClientId() {
    const key = `mlm:cid:${this.id}`;
    try {
      let id = localStorage.getItem(key);
      if (!id) {
        id = randomId(24);
        localStorage.setItem(key, id);
      }
      return id;
    } catch {
      return randomId(24);
    }
  }

  /** Bir brauzerda imtihon ikki oynada ochilsa: yangi oyna ishlaydi, eskisi yopiladi. */
  watchTabs() {
    if (!('BroadcastChannel' in window)) return;
    this.tabId = randomId(12);
    this.channel = new BroadcastChannel(`mlm-exam-${this.id}`);
    this.channel.onmessage = (e) => {
      const msg = e.data || {};
      if (msg.tab === this.tabId || this.destroyed) return;
      if (msg.type === 'hello') {
        this.channel.postMessage({ type: 'present', tab: this.tabId });
        this.onFatal('taken_over');
      } else if (msg.type === 'present') {
        this.report('multiple_tabs', 'Imtihon ikkinchi oynada ochildi', true);
      }
    };
    this.channel.postMessage({ type: 'hello', tab: this.tabId });
  }

  // ------------------------------------------------------------------
  // Hayot sikli
  // ------------------------------------------------------------------

  async init() {
    this.root.replaceChildren(h('div', { class: 'center-screen' }, spinner()));
    let state;
    try {
      state = await this.claim(false);
    } catch (err) {
      if (err instanceof ApiError && err.code === 'other_device') {
        const ok = await confirmDialog(T.otherDeviceTitle, T.otherDeviceText, T.continueHere, 'danger');
        if (!ok) {
          this.exit();
          return;
        }
        state = await this.claim(true);
      } else {
        this.showError(err);
        return;
      }
    }

    const lockdown = state.mock.lockdown;
    this.lock = new Lockdown({
      requireFullscreen: lockdown.fullscreen,
      report: (type, detail, violation) => this.report(type, detail, violation),
      onPageHide: () => this.rememberLeave(),
      getViolationInfo: () => ({
        count: this.violations,
        max: lockdown.action === 'terminate' ? lockdown.max_violations : 0,
      }),
    });
    this.saver = new Saver({
      attemptId: this.id,
      clientId: this.clientId,
      section: null,
      getPayload: null,
      seq: state.attempt.save_seq,
      onStatus: (status) => this.shell && this.shell.setSaveStatus(status),
      onSummary: (summary) => this.onSummary(summary),
      onFatal: (code, message) => this.onFatal(code, message),
    });
    if (state.attempt.status === 'in_progress') {
      this.saver.start();
      this.checkPreviousLeave(state);
      this.watchTabs();
    }
    window.addEventListener('offline', () => this.report('offline', '', false));
    window.addEventListener('online', () => this.report('online', '', false));
    this.render(state);
  }

  /** Sahifa yopilayotganda vaqtni eslab qolamiz (keyingi ochilishda tekshirish uchun). */
  rememberLeave() {
    try {
      if (this.lock && this.lock.enabled) localStorage.setItem(`mlm:left:${this.id}`, String(Date.now()));
    } catch {
      /* e'tiborsiz */
    }
  }

  /** Oddiy yangilash (bir necha soniya) — ma'lumot; uzoq vaqt yopiq qolgan bo'lsa — qoidabuzarlik. */
  checkPreviousLeave(state) {
    let left = 0;
    try {
      left = Number(localStorage.getItem(`mlm:left:${this.id}`) || 0);
      localStorage.removeItem(`mlm:left:${this.id}`);
    } catch {
      return;
    }
    if (!left) return;
    const seconds = Math.round((Date.now() - left) / 1000);
    const active = state.section && state.section.state === 'active';
    if (seconds > RELOAD_GRACE_SEC && active) {
      this.report('page_closed', `Imtihon sahifasi ${seconds} soniya yopiq bo'ldi`, true);
    } else {
      this.report('reload', `Sahifa yangilandi (${seconds} s)`, false);
    }
  }

  claim(takeover) {
    return post(`exam/${this.id}/claim`, { client_id: this.clientId, takeover });
  }

  async reloadState() {
    if (this.reloading || this.destroyed) return;
    this.reloading = true;
    try {
      const state = await get(`exam/${this.id}/state?client_id=${encodeURIComponent(this.clientId)}`);
      this.render(state);
    } catch (err) {
      if (err instanceof ApiError && err.status === 409) this.onFatal(err.code, err.message);
    } finally {
      this.reloading = false;
    }
  }

  destroy() {
    this.destroyed = true;
    if (this.channel) this.channel.close();
    clearInterval(this.ticker);
    this.teardownView();
    if (this.saver) this.saver.stop();
    if (this.lock) this.lock.disable();
    if (this.mic) this.mic.close();
  }

  exit() {
    this.destroy();
    this.options.onExit();
  }

  teardownView() {
    if (this.view && this.view.destroy) this.view.destroy();
    this.view = null;
    this.shell = null;
  }

  tick() {
    if (this.view && this.view.tick) this.view.tick(serverNow());
  }

  report(type, detail = '', violation = false) {
    // Server qoidabuzarlikni faqat bo'lim faol bo'lganda hisoblaydi — ekrandagi hisoblagich ham shunday.
    const active = this.state && this.state.section && this.state.section.state === 'active';
    if (violation && active) {
      this.violations += 1;
      if (this.lock) this.lock.refreshOverlay();
    }
    if (this.saver) this.saver.event(type, detail, violation);
  }

  onSummary(summary) {
    if (!this.state) return;
    this.violations = summary.violations;
    if (this.lock) this.lock.refreshOverlay();
    const a = this.state.attempt;
    if (summary.status !== a.status || summary.stage !== a.stage || summary.stage_state !== a.stage_state) {
      if (!this.finishing) this.reloadState();
    }
  }

  onFatal(code, message) {
    this.teardownView();
    if (this.saver) this.saver.stop();
    if (this.lock) {
      this.lock.disable();
      this.lock.exitFullscreen();
    }
    if (code === 'taken_over' || code === 'other_device') {
      this.screen(T.takenOverTitle, T.takenOverText, false);
    } else if (code === 'not_found') {
      this.screen(T.attemptGoneTitle, T.attemptGoneText, true);
    } else {
      this.screen('Xatolik', message || 'Kutilmagan xatolik.', true);
    }
  }

  showError(err) {
    this.root.replaceChildren(h('div', { class: 'center-screen' },
      h('div', { class: 'card card-narrow' },
        h('h2', { text: 'Imtihonni ochib bo\'lmadi' }),
        h('p', { text: err.message || String(err) }),
        h('div', { class: 'actions' },
          h('button', { class: 'btn', type: 'button', onclick: () => this.exit() }, T.toDashboard),
          h('button', { class: 'btn btn-primary', type: 'button', onclick: () => this.init() }, T.retry)
        )
      )
    ));
  }

  screen(title, text, withButton = true, extra = null) {
    this.root.replaceChildren(h('div', { class: 'center-screen' },
      h('div', { class: 'card card-narrow final-card' },
        h('h2', { text: title }),
        h('p', { text }),
        extra,
        withButton ? h('button', { class: 'btn btn-primary', type: 'button', onclick: () => this.exit() }, T.toDashboard) : null
      )
    ));
  }

  // ------------------------------------------------------------------
  // Holatga qarab ekran tanlash
  // ------------------------------------------------------------------

  render(state) {
    if (this.destroyed) return;
    this.teardownView();
    // Saqlovchi faqat faol bo'limga ulanadi (renderSection qayta ulaydi).
    if (this.saver) this.saver.unbind();
    this.state = state;
    this.violations = state.attempt.violations;
    this.finishing = false;
    const a = state.attempt;

    if (a.status === 'terminated') {
      this.stopExam();
      this.screen(T.terminatedTitle, T.terminatedText, true, state.terminated_reason ? h('p', { class: 'muted', text: state.terminated_reason }) : null);
      return;
    }
    if (a.status === 'completed' || a.stage === 'done') {
      this.stopExam();
      this.screen(T.finishedTitle, T.finishedText);
      return;
    }

    const sec = state.section;
    if (!sec) {
      this.screen(T.finishedTitle, T.finishedText);
      return;
    }

    if (sec.code === 'S') {
      if (sec.state === 'active') this.renderSpeakingResume(state);
      else if (this.options.intent === 'speaking' || state.mock.speaking_mode === 'same_session') this.renderSpeakingIntro(state);
      else {
        this.stopExam();
        this.screen(T.writtenDoneTitle, T.writtenDoneSeparate);
      }
      return;
    }

    if (sec.state === 'pending') {
      const first = a.sections[0] === sec.code;
      if (first) this.renderRules(state);
      else this.renderBreak(state);
      return;
    }

    // Sahifa yangilangandan keyin to'liq ekran va audio uchun foydalanuvchi bir marta bosishi kerak.
    if (this.gatePassed && (sec.code !== 'L' || this.urls)) {
      this.renderSection(state);
    } else {
      this.renderResumeGate(state);
    }
  }

  stopExam() {
    if (this.saver) this.saver.stop();
    if (this.lock) {
      this.lock.disable();
      this.lock.exitFullscreen();
    }
    this.audio.pause();
  }

  // ------------------------------------------------------------------
  // Qoidalar va boshlash
  // ------------------------------------------------------------------

  renderRules(state) {
    const sec = state.section;
    const lockdown = state.mock.lockdown;
    const accept = h('input', { type: 'checkbox', id: 'rules-accept' });
    const startBtn = h('button', { class: 'btn btn-primary btn-lg', type: 'button', disabled: true }, sec.code === 'L' ? T.startListening : T.startSection(SECTION[sec.code]));
    const audioStatus = h('div', { class: 'preload' });
    let audioReady = sec.code !== 'L';
    const update = () => {
      startBtn.disabled = !(accept.checked && audioReady);
    };
    accept.addEventListener('change', update);

    const autoNote = h('p', { class: 'muted small' });
    const view = {
      tick: (now) => setText(autoNote, T.autoStartFirst(formatClock(Math.max(0, sec.auto_start_ms - now)))),
    };
    this.view = view;

    if (sec.code === 'L') this.preload(sec.audio, audioStatus, () => {
      audioReady = true;
      update();
    });

    startBtn.onclick = async () => {
      startBtn.disabled = true;
      this.gatePassed = true;
      await this.lock.enterFullscreen();
      this.lock.enable();
      unlockAudio(this.audio);
      await this.startSection(sec.code);
    };

    const soundCheck = sec.code === 'L' || state.attempt.sections.includes('L') ? this.soundCheck() : null;
    this.root.replaceChildren(h('div', { class: 'center-screen' },
      h('div', { class: 'card rules-card' },
        h('div', { class: 'rules-head' },
          h('div', null, h('p', { class: 'eyebrow', text: state.mock.title }), h('h1', { text: T.rulesTitle })),
          h('div', { class: 'candidate-box' }, h('span', { class: 'muted', text: T.candidate }), h('strong', { text: state.candidate.name }), h('span', { class: 'code', text: state.attempt.candidate_no }))
        ),
        h('ol', { class: 'rules-list' }, T.rules.map((r) => h('li', { text: r })), h('li', { class: 'strong', text: T.rulesViolations(lockdown.action === 'terminate' ? lockdown.max_violations : 0) })),
        h('div', { class: 'section-chips' }, state.attempt.sections.map((c) => h('span', { class: 'chip', text: SECTION[c] }))),
        soundCheck,
        sec.code === 'L' ? audioStatus : null,
        h('label', { class: 'check' }, accept, h('span', { text: T.rulesAccept })),
        lockdown.fullscreen ? h('p', { class: 'muted small', text: T.fullscreenNote }) : null,
        h('div', { class: 'actions' }, startBtn),
        autoNote
      )
    ));
    view.tick(serverNow());
  }

  soundCheck() {
    const test = h('button', { class: 'btn', type: 'button' }, icon('volume'), T.playTestSound);
    test.onclick = () => {
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        const ctx = new Ctx();
        const notes = [523.25, 659.25, 783.99];
        notes.forEach((f, i) => {
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          osc.frequency.value = f;
          gain.gain.setValueAtTime(0.0001, ctx.currentTime + i * 0.35);
          gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + i * 0.35 + 0.05);
          gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.35 + 0.33);
          osc.connect(gain).connect(ctx.destination);
          osc.start(ctx.currentTime + i * 0.35);
          osc.stop(ctx.currentTime + i * 0.35 + 0.35);
        });
        setTimeout(() => ctx.close(), 1500);
      } catch {
        toast('Ovozni ijro etib bo\'lmadi.', 'error');
      }
    };
    return h('div', { class: 'sound-check' }, h('h3', { text: T.soundCheck }), h('p', { class: 'muted small', text: T.soundCheckHint }), test);
  }

  preload(manifest, box, onReady) {
    const bar = h('span', { class: 'progress-bar' });
    const label = h('span', { class: 'preload-label', text: T.audioLoading(0, manifest.length) });
    box.replaceChildren(label, h('span', { class: 'progress' }, bar));
    preloadAudio(this.id, manifest, ({ done, total, fraction }) => {
      setText(label, T.audioLoading(done, total));
      bar.style.width = `${Math.round(fraction * 100)}%`;
    }).then((urls) => {
      this.urls = urls;
      bar.style.width = '100%';
      box.classList.add('ok');
      setText(label, T.audioReady);
      onReady();
    }).catch(() => {
      box.classList.add('bad');
      box.replaceChildren(h('span', { text: T.audioFailed }), h('button', { class: 'btn btn-sm', type: 'button', onclick: () => this.preload(manifest, box, onReady) }, T.retry));
    });
  }

  async startSection(code) {
    for (let i = 0; i < 20 && !this.destroyed; i += 1) {
      try {
        const state = await post(`exam/${this.id}/section/start`, { client_id: this.clientId, section: code });
        this.render(state);
        return;
      } catch (err) {
        if (err instanceof ApiError && err.isNetwork) {
          toast(T.offline, 'warn');
          await new Promise((r) => setTimeout(r, 2000));
          continue;
        }
        if (err instanceof ApiError && err.status === 409) {
          this.onFatal(err.code, err.message);
          return;
        }
        toast(err.message, 'error');
        this.reloadState();
        return;
      }
    }
  }

  renderBreak(state) {
    const sec = state.section;
    const prevCode = state.attempt.sections[state.attempt.sections.indexOf(sec.code) - 1];
    const countdown = h('strong', { class: 'break-timer' });
    let starting = false;
    const start = async () => {
      if (starting) return;
      starting = true;
      this.gatePassed = true;
      if (!this.lock.enabled) {
        await this.lock.enterFullscreen();
        this.lock.enable();
      }
      await this.startSection(sec.code);
    };
    this.view = {
      tick: (now) => {
        const left = sec.auto_start_ms - now;
        setText(countdown, formatClock(Math.max(0, left)));
        if (left <= 0) start();
      },
    };
    this.root.replaceChildren(h('div', { class: 'center-screen' },
      h('div', { class: 'card card-narrow break-card' },
        prevCode ? h('p', { class: 'eyebrow', text: T.sectionFinished(SECTION[prevCode]) }) : null,
        h('h2', { text: T.nextSection(SECTION[sec.code]) }),
        h('p', { class: 'muted', text: T.sectionDuration(Math.round(sec.duration_ms / 60000)) }),
        h('p', null, T.autoStartPrefix, ' ', countdown, ' ', T.autoStartSuffix),
        h('button', { class: 'btn btn-primary btn-lg', type: 'button', onclick: start }, T.startSection(SECTION[sec.code]))
      )
    ));
    this.view.tick(serverNow());
  }

  renderResumeGate(state) {
    const sec = state.section;
    const box = h('div', { class: 'preload' });
    const btn = h('button', { class: 'btn btn-primary btn-lg', type: 'button', disabled: sec.code === 'L' && !this.urls }, T.resume);
    btn.onclick = async () => {
      btn.disabled = true;
      this.gatePassed = true;
      await this.lock.enterFullscreen();
      this.lock.enable();
      unlockAudio(this.audio);
      await this.reloadState();
    };
    if (sec.code === 'L' && !this.urls) this.preload(sec.audio, box, () => (btn.disabled = false));
    this.view = { tick: () => {} };
    this.root.replaceChildren(h('div', { class: 'center-screen' },
      h('div', { class: 'card card-narrow' },
        h('p', { class: 'eyebrow', text: state.mock.title }),
        h('h2', { text: `${SECTION[sec.code]} — ${T.resume.toLowerCase()}` }),
        h('p', { class: 'muted', text: `${T.timeLeft}: ${formatMinutes(Math.max(0, sec.deadline_ms - serverNow()))}` }),
        sec.code === 'L' ? box : null,
        h('div', { class: 'actions' }, btn)
      )
    ));
  }

  // ------------------------------------------------------------------
  // Faol bo'lim
  // ------------------------------------------------------------------

  renderSection(state) {
    const sec = state.section;
    const code = sec.code;
    const local = Saver.loadLocal(this.id, code);
    const serverSeq = state.attempt.save_seq;
    const useLocal = local && local.seq > serverSeq && local.data;
    const answers = useLocal ? local.data.answers || {} : sec.answers || {};
    const writing = useLocal ? local.data.writing || {} : sec.writing || {};

    let player = null;
    const shell = new ExamShell({
      candidate: state.candidate.name,
      code: state.attempt.candidate_no,
      sectionName: SECTION[code],
      mockTitle: state.mock.title,
      onFinish: () => this.confirmFinish(),
    });
    this.shell = shell;

    let view;
    if (code === 'W') {
      view = new WritingSection({
        content: sec.content,
        writing,
        shell,
        onChange: () => this.saver.changed(),
        report: (type, detail) => this.report(type, detail, false),
      });
      this.saver.bind('W', () => ({ writing: view.values(), wstats: view.stats }), useLocal ? local.seq : serverSeq, serverSeq);
    } else {
      view = new QuestionSection({
        section: code,
        content: sec.content,
        answers,
        shell,
        onAnswer: () => this.saver.changed(),
        highlightKey: `mlm:hl:${this.id}:${code}`,
        flagsKey: `mlm:flags:${this.id}:${code}`,
      });
      this.saver.bind(code, () => ({ answers: view.values() }), useLocal ? local.seq : serverSeq, serverSeq);
    }
    shell.setBody(view.root);

    if (code === 'L') {
      const reviewMs = (sec.content.review_sec || 0) * 1000;
      shell.finishBtn.hidden = true;
      player = new ListeningPlayer({
        content: sec.content,
        urls: this.urls,
        startMs: sec.started_ms,
        audio: this.audio,
        onPart: (i) => {
          view.showPart(i, false);
          if (serverNow() - sec.started_ms > 1500) toast(T.partStarted(i + 1), 'info', 2500);
        },
        onEnd: () => {},
        report: (type, detail) => this.report(type, detail, false),
      });
      shell.root.querySelector('.eh-right').prepend(player.widget);
      player.start();
      this.reviewFrom = sec.deadline_ms - reviewMs;
    }

    const warned = new Set();
    this.view = {
      tick: (now) => {
        const left = sec.deadline_ms - now;
        shell.setTimer(left);
        if (code === 'L' && shell.finishBtn.hidden && now >= this.reviewFrom) shell.finishBtn.hidden = false;
        for (const min of WARN_AT) {
          if (left <= min * 60000 && left > (min * 60000 - 5000) && !warned.has(min)) {
            warned.add(min);
            toast(T.timeWarning(min), 'warn', 6000);
          }
        }
        if (left <= 0) this.finishSection(true);
      },
      counts: () => (code === 'W' ? view.counts() : view.nav.counts()),
      values: () => (code === 'W' ? { writing: view.values(), wstats: view.stats } : { answers: view.values() }),
      destroy: () => {
        if (player) player.stop();
      },
      code,
    };
    this.root.replaceChildren(shell.root);
    this.view.tick(serverNow());
    if (view.focusCurrent) view.focusCurrent();
    if (useLocal) this.saver.sync();
  }

  async confirmFinish() {
    if (!this.view || !this.view.counts) return;
    const { unanswered, flagged } = this.view.counts();
    const ok = await confirmDialog(T.finishConfirmTitle, T.finishConfirm(unanswered, flagged), T.finishYes, 'danger');
    if (ok) this.finishSection(false);
  }

  async finishSection(auto) {
    if (this.finishing || !this.view || !this.view.values) return;
    this.finishing = true;
    const code = this.view.code;
    const payload = this.view.values();
    this.saver.seq += 1;
    this.saver.persistLocal();
    const seq = this.saver.seq;
    const overlay = h('div', { class: 'modal-backdrop' }, h('div', { class: 'modal modal-status' }, spinner(auto ? T.timeUp : T.saving)));
    document.body.append(overlay);

    try {
      for (;;) {
        try {
          const state = await post(`exam/${this.id}/section/finish`, { client_id: this.clientId, section: code, seq, ...payload });
          Saver.clearLocal(this.id, code);
          overlay.remove();
          this.render(state);
          return;
        } catch (err) {
          if (err instanceof ApiError && err.isNetwork) {
            if (this.shell) this.shell.setSaveStatus('offline');
            await new Promise((r) => setTimeout(r, 3000));
            continue;
          }
          overlay.remove();
          if (err instanceof ApiError && (err.status === 409 || err.status === 404)) {
            this.onFatal(err.code, err.message);
            return;
          }
          if (auto) {
            // Vaqt tugaganda avtomatik yakunlash: serverning soati biroz orqada bo'lsa, jim qayta urinamiz.
            setTimeout(() => (this.finishing = false), 1500);
            return;
          }
          this.finishing = false;
          toast(err.message, 'error');
          return;
        }
      }
    } finally {
      if (overlay.isConnected) overlay.remove();
    }
  }

  // ------------------------------------------------------------------
  // Speaking
  // ------------------------------------------------------------------

  renderSpeakingIntro(state) {
    this.mic = this.mic || new Microphone();
    const startBtn = h('button', { class: 'btn btn-primary btn-lg', type: 'button', disabled: true }, T.startSpeakingNow);
    const check = micCheck(this.mic, (ok) => (startBtn.disabled = !ok));
    startBtn.onclick = async () => {
      startBtn.disabled = true;
      await this.lock.enterFullscreen();
      this.lock.enable();
      try {
        const next = await post(`exam/${this.id}/speaking/start`, { client_id: this.clientId });
        this.state = next;
        this.runSpeaking(next, null);
      } catch (err) {
        startBtn.disabled = false;
        toast(err.message, 'error');
      }
    };
    this.view = { tick: () => {} };
    this.root.replaceChildren(h('div', { class: 'center-screen' },
      h('div', { class: 'card rules-card' },
        h('p', { class: 'eyebrow', text: state.mock.title }),
        h('h1', { text: T.speakingIntro }),
        h('ol', { class: 'rules-list' }, T.speakingRules.map((r) => h('li', { text: r }))),
        check,
        h('div', { class: 'actions' }, startBtn)
      )
    ));
  }

  renderSpeakingResume(state) {
    this.mic = this.mic || new Microphone();
    const sec = state.section;
    const btn = h('button', { class: 'btn btn-primary btn-lg', type: 'button', disabled: true }, T.resume);
    const check = micCheck(this.mic, (ok) => (btn.disabled = !ok));
    btn.onclick = async () => {
      btn.disabled = true;
      await this.lock.enterFullscreen();
      this.lock.enable();
      const current = sec.current;
      const missed = current && !sec.uploaded.includes(current.no);
      this.runSpeaking(state, { missed });
    };
    this.view = { tick: () => {} };
    this.root.replaceChildren(h('div', { class: 'center-screen' },
      h('div', { class: 'card card-narrow' },
        h('h2', { text: T.speakingIntro }),
        check,
        h('div', { class: 'actions' }, btn)
      )
    ));
  }

  runSpeaking(state, resume) {
    const stage = h('div', { class: 'speaking-stage' });
    const header = h('header', { class: 'exam-header' },
      h('div', { class: 'eh-left' }, h('div', { class: 'eh-candidate' }, icon('user'), h('span', { text: state.candidate.name }), h('span', { class: 'eh-code', text: state.attempt.candidate_no }))),
      h('div', { class: 'eh-center' }, h('div', { class: 'eh-section', text: SECTION.S })),
      h('div', { class: 'eh-right' })
    );
    this.root.replaceChildren(h('div', { class: 'exam exam-speaking' }, header, h('main', { class: 'exam-body' }, stage)));
    this.saver.unbind();
    const runner = new SpeakingRunner({
      attemptId: this.id,
      clientId: this.clientId,
      mic: this.mic,
      root: stage,
      total: state.section.total,
      uploaded: state.section.uploaded || [],
      onDone: (next) => {
        this.mic.close();
        this.mic = null;
        this.render(next);
      },
      onFatal: (err) => this.onFatal(err.code, err.message),
    });
    this.view = { tick: () => {} };
    runner.run(resume).catch((err) => {
      if (err instanceof ApiError && err.status === 409) this.onFatal(err.code, err.message);
      else {
        toast(err.message || 'Xatolik', 'error');
        this.reloadState();
      }
    });
  }
}

export async function openExam(root, attemptId, options) {
  const app = new ExamApp(root, attemptId, options);
  await app.init();
  return app;
}

export { modal };

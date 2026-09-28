// Imtihon oynasini "qulflash": to'liq ekran, oynadan chiqishni aniqlash, nusxa/joylashtirish,
// sichqonchaning o'ng tugmasi va klaviatura yorliqlarini o'chirish.
//
// Muhim: oddiy veb-sahifa operatsion tizimni to'liq boshqara olmaydi (masalan, telefonni
// qo'lga olishni yoki Ctrl+Alt+Del'ni to'xtata olmaydi). Shuning uchun har bir chiqish aniqlanadi,
// ekran bloklanadi, serverga yoziladi va chegaradan oshsa urinish to'xtatiladi. To'liq qulf kerak
// bo'lsa, mockni "Safe Exam Browser" orqali ishlatish mumkin (admin sozlamasi).

import { h } from '../../lib/dom.js';
import { T } from '../../lib/uz.js';

const BLUR_GRACE_MS = 1200;

export class Lockdown {
  /**
   * @param {{
   *   requireFullscreen: boolean,
   *   report: (type: string, detail?: string, violation?: boolean) => void,
   *   onIncident?: (active: boolean) => void,
   *   getViolationInfo?: () => {count:number, max:number},
   * }} options
   */
  constructor(options) {
    this.options = options;
    this.enabled = false;
    this.incident = null;
    this.blurTimer = null;
    this.overlay = null;
    this.lastShortcutLog = 0;
    this.suppressed = 0;
    this.fullscreenSupported = Boolean(document.documentElement.requestFullscreen) && document.fullscreenEnabled !== false;
    this.handlers = [];
  }

  get requireFullscreen() {
    return this.options.requireFullscreen && this.fullscreenSupported;
  }

  isFullscreen() {
    return Boolean(document.fullscreenElement);
  }

  /** Foydalanuvchi tugma bosganda chaqiriladi (brauzer to'liq ekranni faqat shunda ruxsat beradi). */
  async enterFullscreen() {
    if (!this.fullscreenSupported) return true;
    try {
      if (!this.isFullscreen()) await document.documentElement.requestFullscreen({ navigationUI: 'hide' });
      // Keyboard Lock API (Chrome/Edge): Esc, Alt+Tab, Win kabi tugmalarni ham sahifa ushlab qoladi.
      if (navigator.keyboard && navigator.keyboard.lock) {
        navigator.keyboard.lock().catch(() => {});
      }
      return true;
    } catch {
      return false;
    }
  }

  /** Vaqtincha kuzatuvni to'xtatish (masalan, fayl tanlash oynasi uchun) — imtihonda ishlatilmaydi. */
  suppress(ms) {
    this.suppressed = Date.now() + ms;
  }

  on(target, type, fn, options) {
    target.addEventListener(type, fn, options);
    this.handlers.push(() => target.removeEventListener(type, fn, options));
  }

  enable() {
    if (this.enabled) return;
    this.enabled = true;
    document.documentElement.classList.add('locked');

    this.on(document, 'fullscreenchange', () => {
      if (!this.isFullscreen() && this.requireFullscreen) this.leave('fullscreen_exit', "To'liq ekrandan chiqildi");
      else this.checkReturn();
    });
    this.on(document, 'visibilitychange', () => {
      if (document.visibilityState === 'hidden') this.leave('focus_lost', 'Boshqa oyna yoki dasturga o\'tildi');
      else this.checkReturn();
    });
    this.on(window, 'blur', () => {
      clearTimeout(this.blurTimer);
      this.blurTimer = setTimeout(() => {
        if (!document.hasFocus()) this.leave('focus_lost', 'Imtihon oynasi fokusni yo\'qotdi');
      }, BLUR_GRACE_MS);
    });
    this.on(window, 'focus', () => {
      clearTimeout(this.blurTimer);
      this.checkReturn();
    });

    const block = (type) => (e) => {
      if (this.isAllowedTarget(e.target, type)) return;
      e.preventDefault();
      e.stopPropagation();
      this.options.report(type + '_blocked', '', false);
    };
    this.on(document, 'copy', block('copy'), true);
    this.on(document, 'cut', block('cut'), true);
    this.on(document, 'paste', block('paste'), true);
    this.on(document, 'drop', block('drop'), true);
    this.on(document, 'dragover', (e) => e.preventDefault(), true);
    this.on(document, 'dragstart', (e) => e.preventDefault(), true);
    this.on(document, 'contextmenu', (e) => {
      // Matn ichidagi o'z menyumiz (belgilash) alohida ishlaydi.
      e.preventDefault();
      if (!e.target.closest || !e.target.closest('.passage')) this.options.report('contextmenu_blocked', '', false);
    }, true);
    this.on(window, 'keydown', (e) => this.onKey(e), true);
    this.on(window, 'beforeprint', () => this.options.report('print_blocked', '', false));
    this.on(window, 'beforeunload', (e) => {
      // Sahifa yangilanayotgan yoki yopilayotgan bo'lishi mumkin: bu vaqtdagi to'liq ekrandan chiqish
      // qoidabuzarlik sifatida yozilmaydi. Sahifa qancha vaqt yopiq bo'lgani qayta ochilganda tekshiriladi.
      this.unloadingSince = Date.now();
      e.preventDefault();
      e.returnValue = '';
      return '';
    });
    this.on(window, 'pagehide', () => {
      this.unloadingSince = Date.now();
      if (this.options.onPageHide) this.options.onPageHide();
    });

    // Orqaga tugmasini ushlab qolish.
    history.pushState({ locked: true }, '', location.href);
    this.on(window, 'popstate', () => history.pushState({ locked: true }, '', location.href));

    if (this.requireFullscreen && !this.isFullscreen()) {
      this.showOverlay('fullscreen');
    }
  }

  disable() {
    if (!this.enabled) return;
    this.enabled = false;
    clearTimeout(this.blurTimer);
    this.handlers.splice(0).forEach((off) => off());
    document.documentElement.classList.remove('locked');
    this.hideOverlay();
    this.incident = null;
    if (navigator.keyboard && navigator.keyboard.unlock) navigator.keyboard.unlock();
  }

  exitFullscreen() {
    if (this.isFullscreen() && document.exitFullscreen) document.exitFullscreen().catch(() => {});
  }

  isAllowedTarget() {
    return false;
  }

  onKey(e) {
    const key = (e.key || '').toLowerCase();
    const mod = e.ctrlKey || e.metaKey;
    const inField = e.target && (e.target.tagName === 'TEXTAREA' || (e.target.tagName === 'INPUT' && e.target.type === 'text'));

    let blocked = false;
    if (key === 'f12' || key === 'f5' || key === 'f11' || key === 'printscreen' || key === 'f1' || key === 'f3' || key === 'f7') blocked = true;
    else if (mod && e.shiftKey && ['i', 'j', 'c', 'k', 'm', 'r', 'delete', 'n', 't', 'w', 'tab'].includes(key)) blocked = true;
    else if (mod && ['c', 'v', 'x', 's', 'p', 'u', 'f', 'g', 'h', 'j', 'k', 'l', 'n', 'o', 'r', 't', 'w', 'd', 'e', 'b', 'i', 'y', 'q', 'tab', 'pageup', 'pagedown', '+', '-', '=', '0'].includes(key)) {
      // Matn maydonida bekor qilish/qaytarish (Ctrl+Z/Y) va hammasini belgilash (Ctrl+A) ruxsat.
      blocked = true;
    } else if (mod && key === 'a' && !inField) blocked = true;
    else if (e.altKey && ['arrowleft', 'arrowright', 'home', 'tab', 'f4'].includes(key)) blocked = true;
    else if (key === 'contextmenu' || key === 'meta' || key === 'os') blocked = true;

    if (mod && key === 'z') blocked = false;
    if (mod && key === 'y' && inField) blocked = false;

    if (!blocked) return;
    e.preventDefault();
    e.stopPropagation();
    const now = Date.now();
    if (now - this.lastShortcutLog > 3000) {
      this.lastShortcutLog = now;
      this.options.report('shortcut_blocked', (e.ctrlKey ? 'Ctrl+' : '') + (e.metaKey ? 'Cmd+' : '') + (e.altKey ? 'Alt+' : '') + (e.shiftKey ? 'Shift+' : '') + e.key, false);
    }
  }

  /** Oynadan chiqish — bitta hodisa (incident) davomida faqat bitta qoidabuzarlik yoziladi. */
  leave(type, detail) {
    if (!this.enabled || Date.now() < this.suppressed) return;
    if (this.unloadingSince && Date.now() - this.unloadingSince < 3000) {
      // "Sahifani tark etasizmi?" oynasida "Qolish" tanlansa, to'liq ekranga qaytish so'raladi.
      setTimeout(() => this.checkReturn(), 3100);
      return;
    }
    if (this.incident) {
      this.incident.causes.add(detail);
      return;
    }
    this.incident = { since: Date.now(), causes: new Set([detail]) };
    this.options.report(type, detail, true);
    if (this.options.onIncident) this.options.onIncident(true);
    this.showOverlay('left');
  }

  checkReturn() {
    if (!this.enabled) return;
    const visible = document.visibilityState === 'visible';
    const focused = document.hasFocus();
    const fullscreenOk = !this.requireFullscreen || this.isFullscreen();
    if (visible && focused && fullscreenOk) {
      if (this.incident) {
        const seconds = Math.round((Date.now() - this.incident.since) / 1000);
        this.options.report('returned', `${seconds} soniyadan keyin qaytdi: ${Array.from(this.incident.causes).join('; ')}`, false);
        this.incident = null;
        if (this.options.onIncident) this.options.onIncident(false);
      }
      this.hideOverlay();
    } else if (!fullscreenOk && !this.overlay) {
      this.showOverlay('fullscreen');
    }
  }

  showOverlay(kind) {
    const info = this.options.getViolationInfo ? this.options.getViolationInfo() : { count: 0, max: 0 };
    const title = kind === 'left' ? T.leftTitle : T.fullscreenRequired;
    const button = h('button', {
      class: 'btn btn-primary btn-lg',
      type: 'button',
      onclick: async () => {
        await this.enterFullscreen();
        // Fokus va to'liq ekran tiklangan bo'lsa, oyna yopiladi.
        setTimeout(() => this.checkReturn(), 150);
      },
    }, this.requireFullscreen ? T.enterFullscreen : T.backToExam);

    const body = h('div', { class: 'lock-card', role: 'alertdialog', 'aria-live': 'assertive' },
      h('h2', { text: title }),
      kind === 'left' ? h('p', { text: T.leftText }) : null,
      kind === 'left' ? h('p', { class: 'lock-count', text: T.violationCount(info.count, info.max) }) : null,
      kind === 'left' && info.max > 0 && info.count >= info.max ? h('p', { class: 'lock-warning', text: T.lastWarning }) : null,
      button
    );
    this.hideOverlay();
    this.overlay = h('div', { class: 'lock-overlay' }, body);
    document.body.append(this.overlay);
    button.focus();
  }

  /** Server qoidabuzarliklar sonini yangilaganda ogohlantirish matnini yangilash. */
  refreshOverlay() {
    if (!this.overlay) return;
    const info = this.options.getViolationInfo ? this.options.getViolationInfo() : { count: 0, max: 0 };
    const count = this.overlay.querySelector('.lock-count');
    if (count) count.textContent = T.violationCount(info.count, info.max);
  }

  hideOverlay() {
    if (this.overlay) {
      this.overlay.remove();
      this.overlay = null;
    }
  }
}

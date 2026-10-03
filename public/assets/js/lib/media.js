// Video nazorat uchun sof (DOM'siz) yordamchilar — Node testlari ham shularni tekshiradi.

/**
 * Yozuv formati: avval MP4 (H.264 — Telegram ichida video bo'lib ko'rinadi), bo'lmasa WebM.
 * H.264 darajasi 3.1 (42E01F) — 1280×720 sig'adi; 3.0 (42E01E) faqat 720×576 gacha, shuning uchun keyinroq.
 */
export const VIDEO_TYPES = [
  'video/mp4;codecs=avc1.42E01F',
  'video/mp4;codecs=avc1.4D0028',
  'video/mp4;codecs=avc1.42E01E',
  'video/mp4;codecs=avc1',
  'video/webm;codecs=vp9',
  'video/webm;codecs=vp8',
  'video/webm',
];

export const AV_TYPES = [
  'video/mp4;codecs=avc1.42E01F,mp4a.40.2',
  'video/mp4;codecs=avc1.42E01F,opus',
  'video/mp4;codecs=avc1.4D0028,mp4a.40.2',
  'video/mp4;codecs=avc1.42E01E,mp4a.40.2',
  'video/mp4;codecs=avc1.42E01E,opus',
  'video/mp4;codecs=avc1,opus',
  'video/webm;codecs=vp9,opus',
  'video/webm;codecs=vp8,opus',
  'video/webm',
];

/**
 * @param {(type: string) => boolean} isSupported MediaRecorder.isTypeSupported
 * @param {boolean} withAudio
 * @returns {string|null}
 */
export function pickVideoMime(isSupported, withAudio) {
  for (const type of withAudio ? AV_TYPES : VIDEO_TYPES) {
    try {
      if (isSupported(type)) return type;
    } catch {
      /* keyingisi */
    }
  }
  return null;
}

const even = (n) => Math.max(2, Math.round(n / 2) * 2);

/**
 * Bitta videoga joylash: ekran butun kadr (1280×720 gacha), kamera pastki o'ng burchakda (kadr eninining 22%).
 * Ekran bo'lmasa — faqat kamera (640×480 gacha).
 * @param {{width:number,height:number}|null} screen
 * @param {{width:number,height:number}|null} camera
 */
export function composeLayout(screen, camera) {
  if (screen && screen.width > 0 && screen.height > 0) {
    const scale = Math.min(1, 1280 / screen.width, 720 / screen.height);
    const width = even(screen.width * scale);
    const height = even(screen.height * scale);
    let cam = null;
    if (camera && camera.width > 0 && camera.height > 0) {
      const w = even(width * 0.22);
      const h = even((w * camera.height) / camera.width);
      cam = { x: width - w - 12, y: height - h - 12, w, h };
    }
    return { width, height, screen: { x: 0, y: 0, w: width, h: height }, cam, fps: 6, content: cam ? 'screen+camera' : 'screen' };
  }
  if (camera && camera.width > 0 && camera.height > 0) {
    const scale = Math.min(1, 640 / camera.width, 480 / camera.height);
    const width = even(camera.width * scale);
    const height = even(camera.height * scale);
    return { width, height, screen: null, cam: { x: 0, y: 0, w: width, h: height }, fps: 12, content: 'camera' };
  }
  return null;
}

/** Bitrate (kbit/s) bo'yicha — berilgan uzunlikdagi bo'lak (standart 30 soniya) taxminan necha bayt. */
export function pieceBytes(kbps, seconds = 30) {
  return Math.round((kbps * 1000 * seconds) / 8);
}

/** Server kutadigan identifikator: 20 ta harf/raqam. */
export function segmentKey(random = Math.random) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
  let out = '';
  for (let i = 0; i < 20; i += 1) out += chars[Math.floor(random() * chars.length)];
  return out;
}

/** Fayl kengaytmasi. */
export function mimeExt(mime) {
  return String(mime || '').startsWith('video/mp4') ? 'mp4' : 'webm';
}

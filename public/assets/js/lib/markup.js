// Xavfsiz "yengil" belgilash tili (admin kiritgan matnlar uchun). HTML qabul qilinmaydi.
//
//   bo'sh qator          — yangi xatboshi
//   bitta yangi qator    — qator ko'chishi
//   ## Sarlavha          — kichik sarlavha
//   - element            — ro'yxat
//   [A] Matn...          — xatboshi belgisi (masalan, sarlavha moslashtirish uchun)
//   **qalin**, *kursiv*, __tagiga chizilgan__
//   [[9]]                — 9-savol uchun bo'sh joy (gap-fill)

const INLINE = /(\*\*[^*\n]+\*\*|__[^_\n]+__|\*[^*\n]+\*|\[\[\s*\d{1,3}\s*\]\])/g;

function inline(text, options, out) {
  let last = 0;
  for (const match of text.matchAll(INLINE)) {
    if (match.index > last) out.append(document.createTextNode(text.slice(last, match.index)));
    const token = match[0];
    if (token.startsWith('[[')) {
      const n = Number(token.replace(/\D/g, ''));
      out.append(options.gap ? options.gap(n) : document.createTextNode(`(${n}) ________`));
    } else if (token.startsWith('**')) {
      const b = document.createElement('strong');
      b.textContent = token.slice(2, -2);
      out.append(b);
    } else if (token.startsWith('__')) {
      const u = document.createElement('u');
      u.textContent = token.slice(2, -2);
      out.append(u);
    } else {
      const i = document.createElement('em');
      i.textContent = token.slice(1, -1);
      out.append(i);
    }
    last = match.index + token.length;
  }
  if (last < text.length) out.append(document.createTextNode(text.slice(last)));
}

function lines(text, options, out) {
  const parts = text.split('\n');
  parts.forEach((line, index) => {
    if (index > 0) out.append(document.createElement('br'));
    inline(line, options, out);
  });
}

/**
 * @param {string} text
 * @param {{gap?: (n:number)=>Node, paragraphClass?: string}} options
 * @returns {DocumentFragment}
 */
export function renderRich(text, options = {}) {
  const fragment = document.createDocumentFragment();
  const source = String(text || '').replace(/\r\n?/g, '\n').trim();
  if (!source) return fragment;

  for (const block of source.split(/\n{2,}/)) {
    const trimmed = block.trim();
    if (trimmed.startsWith('## ')) {
      const h = document.createElement('h4');
      h.className = 'rich-heading';
      inline(trimmed.slice(3), options, h);
      fragment.append(h);
      continue;
    }
    const blockLines = trimmed.split('\n');
    if (blockLines.every((l) => /^\s*-\s+/.test(l))) {
      const ul = document.createElement('ul');
      ul.className = 'rich-list';
      for (const l of blockLines) {
        const li = document.createElement('li');
        inline(l.replace(/^\s*-\s+/, ''), options, li);
        ul.append(li);
      }
      fragment.append(ul);
      continue;
    }
    const p = document.createElement('p');
    if (options.paragraphClass) p.className = options.paragraphClass;
    const label = trimmed.match(/^\[([A-Z0-9]{1,3})\]\s+/);
    let body = trimmed;
    if (label) {
      const tag = document.createElement('span');
      tag.className = 'para-label';
      tag.textContent = label[1];
      p.append(tag);
      p.dataset.label = label[1];
      body = trimmed.slice(label[0].length);
    }
    lines(body, options, p);
    fragment.append(p);
  }
  return fragment;
}

/** Matndagi [[n]] raqamlari. */
export function gapNumbers(text) {
  return Array.from(String(text || '').matchAll(/\[\[\s*(\d{1,3})\s*\]\]/g), (m) => Number(m[1]));
}

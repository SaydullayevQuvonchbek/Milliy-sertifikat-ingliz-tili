// Rasmiy Multilevel formatidagi bo'sh shablonlar: qismlar, savol turlari, raqamlar va
// standart inglizcha ko'rsatmalar tayyor. Admin faqat matn, variant va javoblarni to'ldiradi —
// bu imlo va format xatolarining oldini oladi.

const mcq = (n, count) => ({ type: 'mcq', n, prompt: '', options: Array(count).fill(''), answer: '' });
const tfng = (n) => ({ type: 'tfng', n, prompt: '', answer: '' });
const range = (from, to) => Array.from({ length: to - from + 1 }, (_, i) => from + i);
const letters = (count) => 'ABCDEFGHIJ'.slice(0, count).split('');
const roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];

function gapNotes(from, to, title) {
  return {
    type: 'gap_text',
    title,
    max_words: 1,
    text: range(from, to).map((n) => `- [[${n}]]`).join('\n'),
    answers: Object.fromEntries(range(from, to).map((n) => [String(n), ''])),
  };
}

function track(label) {
  return { asset: null, duration: 0, label, transcript: '' };
}

export function listeningTemplate() {
  return {
    review_sec: 120,
    parts: [
      {
        title: 'Part 1',
        instructions: 'You will hear eight short conversations. You will hear each conversation twice.\nFor questions 1–8, choose the correct answer **A**, **B** or **C**.',
        preview_sec: 20, gap_sec: 4, plays: 2,
        tracks: range(1, 8).map((i) => track(`Conversation ${i}`)),
        blocks: range(1, 8).map((n) => mcq(n, 3)),
      },
      {
        title: 'Part 2',
        instructions: 'You will hear a talk. You will hear the talk twice.\nFor questions 9–14, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.',
        preview_sec: 30, gap_sec: 5, plays: 2,
        tracks: [track('Talk')],
        blocks: [gapNotes(9, 14, 'Notes')],
      },
      {
        title: 'Part 3',
        instructions: 'You will hear four short recordings. You will hear the recordings twice.\nFor questions 15–18, choose from the list (**A–F**) what each speaker says. Use each letter only once. There are **two** extra letters which you do not need to use.',
        preview_sec: 30, gap_sec: 4, plays: 2,
        tracks: range(1, 4).map((i) => track(`Speaker ${i}`)),
        blocks: [{
          type: 'match', title: '',
          options: letters(6).map((key) => ({ key, text: '' })),
          items: range(15, 18).map((n, i) => ({ n, prompt: `Speaker ${i + 1}`, answer: '' })),
        }],
      },
      {
        title: 'Part 4',
        instructions: 'You will hear a talk. You will hear the talk twice.\nFor questions 19–23, choose the correct option (**A–H**) for each item. There are **three** extra options which you do not need to use.',
        preview_sec: 30, gap_sec: 5, plays: 2,
        tracks: [track('Talk')],
        blocks: [{
          type: 'match', title: '',
          options: letters(8).map((key) => ({ key, text: '' })),
          items: range(19, 23).map((n) => ({ n, prompt: '', answer: '' })),
        }],
      },
      {
        title: 'Part 5',
        instructions: 'You will hear three conversations. You will hear each conversation twice.\nFor questions 24–29, choose the correct answer **A**, **B** or **C**.',
        preview_sec: 30, gap_sec: 5, plays: 2,
        tracks: range(1, 3).map((i) => track(`Conversation ${i}`)),
        blocks: [
          { type: 'text', text: '**Conversation 1**' }, mcq(24, 3), mcq(25, 3),
          { type: 'text', text: '**Conversation 2**' }, mcq(26, 3), mcq(27, 3),
          { type: 'text', text: '**Conversation 3**' }, mcq(28, 3), mcq(29, 3),
        ],
      },
      {
        title: 'Part 6',
        instructions: 'You will hear part of a lecture. You will hear the lecture twice.\nFor questions 30–35, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.',
        preview_sec: 30, gap_sec: 5, plays: 2,
        tracks: [track('Lecture')],
        blocks: [gapNotes(30, 35, 'Notes')],
      },
    ],
  };
}

export function readingTemplate() {
  return {
    parts: [
      {
        title: 'Part 1',
        instructions: 'Read the text. For questions 1–6, fill in each gap with **ONE** word.',
        passage: null,
        blocks: [{
          type: 'gap_text', title: '', max_words: 1,
          text: range(1, 6).map((n) => `… [[${n}]] …`).join('\n\n'),
          answers: Object.fromEntries(range(1, 6).map((n) => [String(n), ''])),
        }],
      },
      {
        title: 'Part 2',
        instructions: 'Read the texts (7–14) and the statements (**A–J**). Match each text with the correct statement. There are **two** extra statements which you do not need to use.',
        passage: { title: '', text: range(7, 14).map((n) => `[${n}] …`).join('\n\n') },
        blocks: [{
          type: 'match', title: '',
          options: letters(10).map((key) => ({ key, text: '' })),
          items: range(7, 14).map((n) => ({ n, prompt: `Text ${n}`, answer: '' })),
        }],
      },
      {
        title: 'Part 3',
        instructions: 'Read the text. For questions 15–20, choose the correct heading for each paragraph from the list of headings (**I–VIII**). There are **two** extra headings which you do not need to use.',
        passage: { title: '', text: letters(6).map((l) => `[${l}] …`).join('\n\n') },
        blocks: [{
          type: 'match', title: 'List of headings',
          options: roman.map((key) => ({ key, text: '' })),
          items: range(15, 20).map((n, i) => ({ n, prompt: `Paragraph ${letters(6)[i]}`, answer: '' })),
        }],
      },
      {
        title: 'Part 4',
        instructions: 'Read the text. For questions 21–24, choose the correct answer **A**, **B**, **C** or **D**.\nFor questions 25–29, decide if the statements agree with the information in the text. Choose **TRUE**, **FALSE** or **NOT GIVEN**.',
        passage: { title: '', text: '' },
        blocks: [
          ...range(21, 24).map((n) => mcq(n, 4)),
          { type: 'text', text: '**Questions 25–29.** Do the following statements agree with the information in the text?' },
          ...range(25, 29).map(tfng),
        ],
      },
      {
        title: 'Part 5',
        instructions: 'Read the text. For questions 30–33, complete the summary. Write **ONE WORD** from the text for each answer.\nFor questions 34–35, choose the correct answer **A**, **B**, **C** or **D**.',
        passage: { title: '', text: '' },
        blocks: [
          {
            type: 'gap_text', title: 'Summary', max_words: 1,
            text: range(30, 33).map((n) => `… [[${n}]] …`).join(' '),
            answers: Object.fromEntries(range(30, 33).map((n) => [String(n), ''])),
          },
          mcq(34, 4),
          mcq(35, 4),
        ],
      },
    ],
  };
}

export function writingTemplate() {
  return {
    parts: [
      {
        title: 'Part 1',
        instructions: 'Read the text and complete Task 1.1 and Task 1.2. You are advised to spend about 25 minutes on Part 1.',
        context: '',
        tasks: [
          { id: '1.1', title: 'Task 1.1', prompt: '\n\nWrite about **50** words.', min_words: 50, max_words: 0 },
          { id: '1.2', title: 'Task 1.2', prompt: '\n\nWrite **120–150** words.', min_words: 120, max_words: 150 },
        ],
      },
      {
        title: 'Part 2',
        instructions: 'You are advised to spend about 35 minutes on Part 2.',
        context: '',
        tasks: [{ id: '2', title: 'Task 2', prompt: '\n\nWrite **180–200** words.', min_words: 180, max_words: 200 }],
      },
    ],
  };
}

export function speakingTemplate() {
  const q = (no, prep, answer) => ({ no, text: '', prep_sec: prep, answer_sec: answer, audio: null });
  return {
    parts: [
      { id: '1.1', title: 'Part 1.1', instructions: 'Answer the questions about yourself. You have 30 seconds for each answer.', images: [], questions: [q(1, 0, 30), q(2, 0, 30), q(3, 0, 30)] },
      { id: '1.2', title: 'Part 1.2', instructions: 'Look at the two pictures and answer the questions.', images: [], questions: [q(4, 0, 45), q(5, 0, 30), q(6, 0, 30)] },
      { id: '2', title: 'Part 2', instructions: 'Look at the picture and answer the questions. You have one minute to prepare and two minutes to speak.', images: [], questions: [q(7, 60, 120)] },
      { id: '3', title: 'Part 3', instructions: 'Read the statement and the arguments for and against it. You have one minute to prepare and two minutes to speak.', images: [], topic: '', for: ['', '', ''], against: ['', '', ''], questions: [q(8, 60, 120)] },
    ],
  };
}

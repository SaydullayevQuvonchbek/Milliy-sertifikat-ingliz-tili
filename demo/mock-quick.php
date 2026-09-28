<?php

// Qisqa sinov mocki — platformani 5–10 daqiqada to'liq ko'rib chiqish uchun (har bo'limda bitta qism).

declare(strict_types=1);

return [
    'title' => 'Tezkor sinov (qisqa mock)',
    'description' => "Platforma bilan tanishish uchun qisqa mock: har bo'limda bitta qism, vaqtlar qisqartirilgan.",
    'settings' => [
        'times' => ['reading' => 300, 'writing' => 300],
        'break_sec' => 20,
        'speaking' => ['mode' => 'same_session'],
    ],
    'assets' => [
        'audio' => ['q1' => 7, 'q2' => 7, 'q3' => 12],
        'image' => ['qs' => 'A busy street market'],
    ],
    'source' => [
        'listening' => [
            'review_sec' => 30,
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => "You will hear two short conversations and a short announcement. You will hear each recording twice.\nFor questions 1–2, choose the correct answer **A**, **B** or **C**. For question 3, write **ONE WORD and/or A NUMBER**.",
                    'preview_sec' => 8,
                    'gap_sec' => 3,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:q1', 'label' => 'Conversation 1', 'transcript' => "M: Could I have a cup of tea, please?\nW: I'm sorry, we've run out of tea. Would you like coffee instead?"],
                        ['asset' => '@audio:q2', 'label' => 'Conversation 2', 'transcript' => "W: Where did you put the car keys?\nM: They're on the table next to the door."],
                        ['asset' => '@audio:q3', 'label' => 'Announcement', 'transcript' => "Attention, please. The train to Bukhara will now leave from platform seven."],
                    ],
                    'blocks' => [
                        ['type' => 'mcq', 'n' => 1, 'prompt' => 'What does the woman offer the man?', 'options' => ['Tea.', 'Coffee.', 'Water.'], 'answer' => 'B'],
                        ['type' => 'mcq', 'n' => 2, 'prompt' => 'Where are the car keys?', 'options' => ['In the car.', 'In his pocket.', 'On the table.'], 'answer' => 'C'],
                        ['type' => 'gap_text', 'title' => 'Announcement', 'max_words' => 1, 'text' => 'The train to Bukhara will leave from platform [[3]].', 'answers' => ['3' => '7|seven']],
                    ],
                ],
            ],
        ],
        'reading' => [
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => 'Read the text and answer questions 1–3.',
                    'passage' => [
                        'title' => 'A New Park',
                        'text' => "[A] Last month, the city opened a new park on the site of an old factory. The park has a small lake, a playground and a long path for walking and cycling.\n\n[B] The park is open from 6 a.m. to 11 p.m. every day. Dogs are welcome, but they must be kept on a lead near the playground.",
                    ],
                    'blocks' => [
                        ['type' => 'tfng', 'n' => 1, 'prompt' => 'The park was built where a factory used to be.', 'answer' => 'TRUE'],
                        ['type' => 'tfng', 'n' => 2, 'prompt' => 'The park closes at 10 p.m.', 'answer' => 'FALSE'],
                        ['type' => 'gap_text', 'title' => 'Complete the sentence with ONE word from the text.', 'max_words' => 1, 'text' => 'Near the playground, dogs must be kept on a [[3]].', 'answers' => ['3' => 'lead']],
                    ],
                ],
            ],
        ],
        'writing' => [
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => '',
                    'context' => '',
                    'tasks' => [
                        ['id' => '1.1', 'title' => 'Task 1.1', 'prompt' => 'Write a short message to a friend inviting them to visit the new park with you. Write about **50** words.', 'min_words' => 50, 'max_words' => 0],
                    ],
                ],
            ],
        ],
        'speaking' => [
            'parts' => [
                [
                    'id' => '1.1',
                    'title' => 'Part 1.1',
                    'instructions' => 'Answer the question.',
                    'questions' => [
                        ['no' => 1, 'text' => 'What do you like to do in your free time?', 'prep_sec' => 0, 'answer_sec' => 15],
                    ],
                ],
                [
                    'id' => '2',
                    'title' => 'Part 2',
                    'instructions' => 'Look at the picture and answer the question.',
                    'images' => ['@image:qs'],
                    'questions' => [
                        ['no' => 2, 'text' => 'Describe a market you like to visit.', 'prep_sec' => 10, 'answer_sec' => 20],
                    ],
                ],
            ],
        ],
    ],
];

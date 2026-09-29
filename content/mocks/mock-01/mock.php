<?php

// Multilevel Mock 1 — to'liq rasmiy format (Listening 6 qism/35, Reading 5 qism/35, Writing 3 topshiriq, Speaking 8 savol).
// Mavzular: ta'lim, texnologiya, sayohat, shahar hayoti. Barcha matnlar shu loyiha uchun yozilgan (original).
// Audio skripti va transkriptlar audio.json dan olinadi (yagona manba): `python3 content/tools/synth.py content/mocks/mock-01`.
// Speaking rasmlari: images/*.svg -> `python3 content/tools/render_images.py content/mocks/mock-01`.

declare(strict_types=1);

require_once __DIR__ . '/../../lib.php';

$audio = content_audio(__DIR__);
$tr = static fn (string $track): string => content_transcript($audio, $track);

$mcq = static fn (int $n, string $prompt, array $options, string $answer): array => [
    'type' => 'mcq', 'n' => $n, 'prompt' => $prompt, 'options' => $options, 'answer' => $answer,
];
$tfng = static fn (int $n, string $prompt, string $answer): array => [
    'type' => 'tfng', 'n' => $n, 'prompt' => $prompt, 'answer' => $answer,
];

return [
    'title' => 'Multilevel Mock 1',
    'description' => "To'liq rasmiy formatdagi mock: original matnlar, TTS yordamida yaratilgan audio va illyustratsiyalar. Mavzular: ta'lim, texnologiya, sayohat va shahar hayoti.",
    'assets' => [
        'audio' => [
            'l1_1', 'l1_2', 'l1_3', 'l1_4', 'l1_5', 'l1_6', 'l1_7', 'l1_8',
            'l2', 'l3_1', 'l3_2', 'l3_3', 'l3_4', 'l4', 'l5_1', 'l5_2', 'l5_3', 'l6',
            'sq1', 'sq2', 'sq3', 'sq4', 'sq5', 'sq6', 'sq7', 'sq8',
        ],
        'image' => [
            's12_1' => 'Picture 1: Travelling by fast train',
            's12_2' => 'Picture 2: Cycling through the mountains',
            's2' => 'A family cooking and celebrating together at home',
        ],
    ],
    'source' => [
        'listening' => [
            'review_sec' => 120,
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => "You will hear eight short conversations. You will hear each conversation twice.\nFor questions 1–8, choose the correct answer **A**, **B** or **C**.",
                    'preview_sec' => 20,
                    'gap_sec' => 4,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l1_1', 'label' => 'Conversation 1', 'transcript' => $tr('l1_1')],
                        ['asset' => '@audio:l1_2', 'label' => 'Conversation 2', 'transcript' => $tr('l1_2')],
                        ['asset' => '@audio:l1_3', 'label' => 'Conversation 3', 'transcript' => $tr('l1_3')],
                        ['asset' => '@audio:l1_4', 'label' => 'Conversation 4', 'transcript' => $tr('l1_4')],
                        ['asset' => '@audio:l1_5', 'label' => 'Conversation 5', 'transcript' => $tr('l1_5')],
                        ['asset' => '@audio:l1_6', 'label' => 'Conversation 6', 'transcript' => $tr('l1_6')],
                        ['asset' => '@audio:l1_7', 'label' => 'Conversation 7', 'transcript' => $tr('l1_7')],
                        ['asset' => '@audio:l1_8', 'label' => 'Conversation 8', 'transcript' => $tr('l1_8')],
                    ],
                    'blocks' => [
                        $mcq(1, 'Which train does the woman decide to take?', ['The 6:10 train.', 'The 7:40 train.', 'The 9:15 train.'], 'C'),
                        $mcq(2, 'When must the students hand in their history essays?', ['On Friday.', 'On Monday.', 'On Sunday.'], 'B'),
                        $mcq(3, 'What does the man think is wrong with the phone?', ['The battery is damaged.', 'The screen is cracked.', 'The phone was dropped.'], 'A'),
                        $mcq(4, 'How much will the woman pay today?', ['Nothing.', '10,000 som.', '20,000 som.'], 'C'),
                        $mcq(5, 'What does the woman like best about her online course?', ['She can decide when to study.', 'She has made new friends.', 'The lessons are shorter than usual.'], 'A'),
                        $mcq(6, 'Where will the woman have breakfast on Sunday?', ['In the restaurant.', 'On the terrace.', 'In her room.'], 'B'),
                        $mcq(7, 'Why did Farid miss the lecture?', ['His bus broke down.', 'He got up late.', 'He forgot about it.'], 'A'),
                        $mcq(8, 'Why did the man leave a message for the woman?', ['To remind her to pay for the ticket.', 'To offer her a better seat on the plane.', 'To inform her of a schedule change.'], 'C'),
                    ],
                ],
                [
                    'title' => 'Part 2',
                    'instructions' => "You will hear a talk. You will hear the talk twice.\nFor questions 9–14, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l2', 'label' => 'Talk', 'transcript' => $tr('l2')],
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Samarkand day trip',
                            'max_words' => 1,
                            'text' => "- Meeting point: under the big [[9]] in the main hall\n- The train leaves at [[10]] a.m.\n- Cost of the trip: [[11]] som\n- Payment: International Office, room [[12]]\n- Each guide will look after [[13]] students\n- Do not forget to bring your [[14]]",
                            'answers' => [
                                '9' => 'clock',
                                '10' => '7|7.00|7:00|07:00|seven',
                                '11' => '140,000|140000|140 000|140.000|one hundred and forty thousand',
                                '12' => '21|twenty-one|twenty one',
                                '13' => '15|fifteen',
                                '14' => 'passport',
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 3',
                    'instructions' => "You will hear four short recordings. You will hear the recordings twice.\nFor questions 15–18, choose from the list (**A–F**) what each speaker says. Use each letter only once. There are **two** extra letters which you do not need to use.",
                    'preview_sec' => 30,
                    'gap_sec' => 4,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l3_1', 'label' => 'Speaker 1', 'transcript' => $tr('l3_1')],
                        ['asset' => '@audio:l3_2', 'label' => 'Speaker 2', 'transcript' => $tr('l3_2')],
                        ['asset' => '@audio:l3_3', 'label' => 'Speaker 3', 'transcript' => $tr('l3_3')],
                        ['asset' => '@audio:l3_4', 'label' => 'Speaker 4', 'transcript' => $tr('l3_4')],
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'How do the speakers travel to work or study?',
                            'options' => [
                                ['key' => 'A', 'text' => 'I use this transport mainly because it is cheap.'],
                                ['key' => 'B', 'text' => 'I make good use of my travelling time.'],
                                ['key' => 'C', 'text' => 'The weather can affect my journey.'],
                                ['key' => 'D', 'text' => 'I would prefer a different type of transport, but I have no choice.'],
                                ['key' => 'E', 'text' => 'I only started travelling this way recently.'],
                                ['key' => 'F', 'text' => 'The journey helps me to relax.'],
                            ],
                            'items' => [
                                ['n' => 15, 'prompt' => 'Speaker 1', 'answer' => 'B'],
                                ['n' => 16, 'prompt' => 'Speaker 2', 'answer' => 'C'],
                                ['n' => 17, 'prompt' => 'Speaker 3', 'answer' => 'A'],
                                ['n' => 18, 'prompt' => 'Speaker 4', 'answer' => 'D'],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 4',
                    'instructions' => "You will hear a talk. You will hear the talk twice.\nFor questions 19–23, choose the correct option (**A–H**) for each item. There are **three** extra options which you do not need to use.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l4', 'label' => 'Talk', 'transcript' => $tr('l4')],
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'What is true about each place on the campus?',
                            'options' => [
                                ['key' => 'A', 'text' => 'It has recently been made larger.'],
                                ['key' => 'B', 'text' => 'It is currently closed for repairs.'],
                                ['key' => 'C', 'text' => 'It is only open to first-year students.'],
                                ['key' => 'D', 'text' => 'It offers modern equipment for practical work.'],
                                ['key' => 'E', 'text' => 'Students can use it free of charge.'],
                                ['key' => 'F', 'text' => 'It provides a comfortable place for friends to meet.'],
                                ['key' => 'G', 'text' => 'It is open day and night.'],
                                ['key' => 'H', 'text' => 'It is located outside the campus.'],
                            ],
                            'items' => [
                                ['n' => 19, 'prompt' => 'The library', 'answer' => 'G'],
                                ['n' => 20, 'prompt' => 'The sports hall', 'answer' => 'E'],
                                ['n' => 21, 'prompt' => 'The swimming pool', 'answer' => 'B'],
                                ['n' => 22, 'prompt' => 'The student centre', 'answer' => 'F'],
                                ['n' => 23, 'prompt' => 'The science building', 'answer' => 'D'],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 5',
                    'instructions' => "You will hear three conversations. You will hear each conversation twice.\nFor questions 24–29, choose the correct answer **A**, **B** or **C**.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l5_1', 'label' => 'Conversation 1', 'transcript' => $tr('l5_1')],
                        ['asset' => '@audio:l5_2', 'label' => 'Conversation 2', 'transcript' => $tr('l5_2')],
                        ['asset' => '@audio:l5_3', 'label' => 'Conversation 3', 'transcript' => $tr('l5_3')],
                    ],
                    'blocks' => [
                        ['type' => 'text', 'text' => '**Conversation 1**'],
                        $mcq(24, 'What does Rustam say is a disadvantage of tablets?', ['They are too heavy to carry around all day.', 'They are awkward for typing long texts.', 'They are not suitable for drawing plans.'], 'B'),
                        $mcq(25, 'What does Rustam advise the woman to do?', ['Buy a tablet with a keyboard.', 'Wait for a cheaper model.', 'Choose a laptop.'], 'C'),
                        ['type' => 'text', 'text' => '**Conversation 2**'],
                        $mcq(26, 'What does the man say about driving to the Fergana Valley?', ['There could be long delays.', 'The car might not be reliable enough.', 'Fuel costs too much for a long trip.'], 'A'),
                        $mcq(27, 'What will the speakers do?', ['Leave on Thursday evening.', 'Go by train, then rent a car.', 'Drive there on Saturday morning.'], 'B'),
                        ['type' => 'text', 'text' => '**Conversation 3**'],
                        $mcq(28, "What is Jasur's problem?", ['He has chosen a subject that is too difficult.', 'He does not have enough time before the deadline.', 'He cannot get the information he needs.'], 'C'),
                        $mcq(29, 'What does Doctor Hughes suggest?', ['Interviewing some bus drivers.', 'Choosing a different city.', "Waiting for the council's data."], 'A'),
                    ],
                ],
                [
                    'title' => 'Part 6',
                    'instructions' => "You will hear part of a lecture. You will hear the lecture twice.\nFor questions 30–35, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l6', 'label' => 'Lecture', 'transcript' => $tr('l6')],
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Online learning: findings of a university survey',
                            'max_words' => 1,
                            'text' => "- Number of people who took part in the survey: about [[30]]\n- The problem mentioned most often by students: [[31]]\n- Recorded lectures should be divided into sections of no more than [[32]] minutes\n- Students valued comments from a [[33]] more than fast automatic marking\n- The most successful study groups had no more than [[34]] students\n- Study groups reduced the number of students leaving the course by [[35]] per cent",
                            'answers' => [
                                '30' => '2,000|2000|2 000|two thousand',
                                '31' => 'motivation',
                                '32' => '15|fifteen',
                                '33' => 'tutor|tutors',
                                '34' => '6|six',
                                '35' => '30|thirty',
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'reading' => [
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => 'Read the text. For questions 1–6, fill in each gap with **ONE** word.',
                    'passage' => null,
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Join the Language Café',
                            'max_words' => 1,
                            'text' => "Are you looking for a friendly way to practise your English? Then come to the Language Café, which takes place [[1]] Wednesday evening in the student centre. It started last year with only ten students, but now more than a hundred people come every week.\n\nThe evening is very simple. When you arrive, you choose a table with a topic, such as travel, music or films, and you talk to the other people at your table for twenty minutes. After that, you move to a different table and meet new people. There are [[2]] teachers and no tests, so there is nothing to be afraid of. If you cannot think of the right word, just try to explain it [[3]] other words.\n\nMany members say that the Café has helped them to make friends. One student, Aziza, told us, \"I was very shy [[4]] I started coming here. Now I speak English with confidence, and I have friends from Korea, Turkey and Germany.\"\n\nThe Café is free, [[5]] you must register on the university website first, because the number of places is limited. Tea and biscuits are provided, but please bring [[6]] own cup if you would like coffee.",
                            'answers' => [
                                '1' => 'every|each|on',
                                '2' => 'no',
                                '3' => 'in|with',
                                '4' => 'before|until|when',
                                '5' => 'but|though|although',
                                '6' => 'your',
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 2',
                    'instructions' => 'Read the texts (7–14) and the statements (**A–J**). Match each text with the correct statement. There are **two** extra statements which you do not need to use.',
                    'passage' => [
                        'title' => 'Notices and messages',
                        'text' => "[7] **Library group rooms.** Study rooms in the university library can now be booked online, so there is no need to queue at the desk in the morning. Each booking lasts a maximum of two hours. Please arrive on time: if nobody has come after ten minutes, the room will be offered to the next group on the waiting list.\n\n[8] **City Metro: new timetable.** From Monday 3 March, trains will run every four minutes between 7 and 9 a.m. and between 5 and 7 p.m. At all other times of the day, trains will continue to arrive every eight minutes. We hope that the change will make journeys to work and school more comfortable.\n\n[9] Hi Anvar, thanks for sending the photos from the trip! Unfortunately, the file was too large, and my email programme rejected it. Could you put the pictures in our shared online folder instead? I'll download them tonight and choose the best ones for the class blog. Sabina\n\n[10] **Silk Road Guesthouse, Bukhara.** Guests can join a free walking tour of the old town every morning at nine. The tour lasts about ninety minutes and starts in the courtyard. Places are limited, so please put your name on the list at reception by six o'clock on the evening before.\n\n[11] **English exam preparation course.** Our next ten-week course begins on 15 September, with classes on Mondays, Wednesdays and Fridays. Students who register before 1 September pay only 80 per cent of the normal fee. After that date, the full price applies. Call us or visit the school office to sign up.\n\n[12] **Old Town Bike Rental.** Explore the city on two wheels! Bicycles cost 30,000 som per day, and every rental includes a helmet and a lock at no extra charge. Child seats are available on request. We are open daily from 8 a.m. to 8 p.m., and all bicycles must be returned before closing.\n\n[13] Your parcel could not be delivered today because nobody was at home. We will try again tomorrow between 10 a.m. and 2 p.m. If that time is not convenient, you can choose another day on our website or collect the parcel from our office on Amir Temur Street any weekday until 6 p.m.\n\n[14] **Reading room rules.** Please keep silent in this room. Mobile phones must be switched off or set to silent mode. If you need to discuss your work with other students, please use the lounge on the ground floor, where group work is welcome and you may also eat and drink.",
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'Which statement (A–J) matches each text?',
                            'options' => [
                                ['key' => 'A', 'text' => 'Registering early will reduce the price.'],
                                ['key' => 'B', 'text' => 'A free activity must be booked in advance.'],
                                ['key' => 'C', 'text' => 'Safety equipment is included in the price.'],
                                ['key' => 'D', 'text' => 'The writer asks the reader to use a different method to send something.'],
                                ['key' => 'E', 'text' => 'A service has been cancelled because of building work.'],
                                ['key' => 'F', 'text' => 'Talking is permitted in one part of the building.'],
                                ['key' => 'G', 'text' => 'Trains will run more frequently at busy times of day.'],
                                ['key' => 'H', 'text' => 'Someone who is out when a delivery arrives has more than one option.'],
                                ['key' => 'I', 'text' => 'Group rooms may be given to other users if people arrive late.'],
                                ['key' => 'J', 'text' => 'Entry to an event is limited to students.'],
                            ],
                            'items' => [
                                ['n' => 7, 'prompt' => 'Text 7', 'answer' => 'I'],
                                ['n' => 8, 'prompt' => 'Text 8', 'answer' => 'G'],
                                ['n' => 9, 'prompt' => 'Text 9', 'answer' => 'D'],
                                ['n' => 10, 'prompt' => 'Text 10', 'answer' => 'B'],
                                ['n' => 11, 'prompt' => 'Text 11', 'answer' => 'A'],
                                ['n' => 12, 'prompt' => 'Text 12', 'answer' => 'C'],
                                ['n' => 13, 'prompt' => 'Text 13', 'answer' => 'H'],
                                ['n' => 14, 'prompt' => 'Text 14', 'answer' => 'F'],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 3',
                    'instructions' => 'Read the text. For questions 15–20, choose the correct heading for each paragraph from the list of headings (**I–VIII**). There are **two** extra headings which you do not need to use.',
                    'passage' => [
                        'title' => 'Growing Food in the City',
                        'text' => "[A] For most of history, farms and cities were separate worlds. Food was grown in the countryside and carried to the people who lived in towns. Today, however, a growing number of urban residents are challenging that division. On rooftops, in car parks and even in old underground tunnels, they are growing vegetables, herbs and fruit in the middle of busy streets. What began as a hobby for a few enthusiasts is now being taken seriously by city planners and businesses alike.\n\n[B] The obvious question is where all this food can be grown, since land in cities is expensive and scarce. The answer is that growers have learned to use spaces that other people ignore. The flat roofs of offices and schools can hold hundreds of boxes of soil, while shelves stacked from floor to ceiling allow lettuce to grow indoors in several layers under special lamps. One farm in a former warehouse produces far more salad than an outdoor field of the same size.\n\n[C] Growing food where it is eaten also changes the journey the food takes. Vegetables from a distant farm may spend days in lorries and warehouses before they reach a shop, losing flavour and vitamins on the way. A tomato picked from a rooftop garden in the morning can be on a restaurant table by lunch. Fewer kilometres of transport also mean that less fuel is burned, which helps to reduce pollution in the city itself.\n\n[D] Urban farming is not only about food, though. Community gardens, which are often created on empty plots between buildings, have become popular meeting places. Retired people share their experience with teenagers, families swap seeds and recipes, and schools use the gardens to teach children where their lunch comes from. Many volunteers say that they got to know their neighbours for the first time simply by working next to them in the soil.\n\n[E] Nevertheless, the movement faces real difficulties. Soil in older parts of a city may contain metals from traffic and industry, so it must be tested before anything is planted. Indoor farms need a great deal of electricity for their lamps and pumps, which makes their vegetables more expensive than those from ordinary fields, and some owners have closed their businesses after only a few years. In addition, building regulations can make it hard to get permission for a rooftop garden.\n\n[F] Even so, many experts are optimistic. Several cities have already promised to grow a fixed share of their fresh vegetables within their own boundaries by the end of the decade, and they are offering cheap land and training to encourage newcomers. Researchers are also developing cheaper lamps and cleaner sources of power. If these efforts succeed, the city of the future may look less like a grey block of concrete and more like a green garden.",
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'List of headings',
                            'options' => [
                                ['key' => 'I', 'text' => 'Obstacles that remain'],
                                ['key' => 'II', 'text' => 'Why young people avoid working on farms'],
                                ['key' => 'III', 'text' => 'Fresher food after a shorter journey'],
                                ['key' => 'IV', 'text' => 'Farming arrives in the city'],
                                ['key' => 'V', 'text' => 'Building a sense of community'],
                                ['key' => 'VI', 'text' => 'Plans for the years ahead'],
                                ['key' => 'VII', 'text' => 'Finding room to grow'],
                                ['key' => 'VIII', 'text' => 'Lessons from traditional farming methods'],
                            ],
                            'items' => [
                                ['n' => 15, 'prompt' => 'Paragraph A', 'answer' => 'IV'],
                                ['n' => 16, 'prompt' => 'Paragraph B', 'answer' => 'VII'],
                                ['n' => 17, 'prompt' => 'Paragraph C', 'answer' => 'III'],
                                ['n' => 18, 'prompt' => 'Paragraph D', 'answer' => 'V'],
                                ['n' => 19, 'prompt' => 'Paragraph E', 'answer' => 'I'],
                                ['n' => 20, 'prompt' => 'Paragraph F', 'answer' => 'VI'],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 4',
                    'instructions' => "Read the text. For questions 21–24, choose the correct answer **A**, **B**, **C** or **D**.\nFor questions 25–29, decide if the statements agree with the information in the text. Choose **TRUE**, **FALSE** or **NOT GIVEN**.",
                    'passage' => [
                        'title' => 'A Year Without Flying',
                        'text' => "When Elena Voss announced that she would not take a single flight for twelve months, her friends thought she was joking. Elena, a travel photographer from Vienna, had spent most of her twenties in airports, and in some months she flew six or seven times. Then, during a long delay at a crowded terminal, she began to calculate how much of her life she had spent waiting in queues. \"I realised I had seen hundreds of airports and almost none of the places between them,\" she says.\n\nHer first challenge was practical. A magazine had asked her to photograph a spring festival in Samarkand, a job she would normally have reached within a few hours by plane. Instead, she travelled overland, using trains, buses and, on one memorable afternoon, a borrowed bicycle. The journey took eleven days, but it was during this trip that she took the photographs that later won her a national prize. \"On a plane you jump from one world into another,\" she explains. \"On a train you watch the landscape change slowly, and the people change with it. That's what I wanted to capture.\"\n\nThe year was not without difficulties. Booking international train journeys turned out to be far more complicated than clicking on an airline website, because every country has its own ticket system, and Elena often had to buy tickets at stations without speaking the local language. Twice she missed a connection and had to spend a night in a small town she had never planned to visit. She admits that on those evenings she seriously considered giving up. Both times, however, the delay led to something she would never have found from a plane window: in one town, a family invited her to a wedding, and in another she discovered a market where she took her favourite portrait of the year.\n\nThe trains themselves became part of the adventure. On long overnight journeys, Elena learned to pack food for two days, to sleep in narrow bunks and to make conversation with strangers using a few words, a phone dictionary and a lot of gestures. She kept a notebook in which she wrote down the stories that other passengers told her, from a retired engineer who had built railway bridges as a young man to a student travelling home to see her grandmother for the first time in three years. Many of these stories later appeared in her articles.\n\nThere were financial surprises, too. Because each assignment now took longer, Elena accepted fewer jobs, and her income fell by about a fifth. On the other hand, she spent less on airport hotels and expensive last-minute tickets, so the loss was smaller than she had feared. She also found that editors were willing to pay more for stories that described the journey itself, not just the destination.\n\nEnvironmental concerns were not Elena's main motive when she began, but they became more important as the year went on. Critics point out that one person's choices can hardly change the climate, and Elena does not disagree. She argues, however, that stories can influence other people. Since she started writing about her journeys, several readers have written to tell her that they have booked rail tickets for their own holidays instead of flights.\n\nThe year ended last December, and Elena did not rush to book a flight. Her next project is a book about night trains across Europe and Asia, and she is already planning the routes. \"I don't say that flying is wrong,\" she says. \"I say that it shouldn't be the only option we consider. Sometimes the slowest way is the one that lets you arrive properly.\"",
                    ],
                    'blocks' => [
                        $mcq(21, 'What made Elena decide to stop flying?', ['A magazine had asked her to write about railway journeys.', 'She realised how much time she wasted at airports.', 'A friend challenged her to try something different.', 'She had become worried about the danger of air travel.'], 'B'),
                        $mcq(22, "What does Elena mean when she says that on a plane you 'jump from one world into another'?", ['Air travel makes people forget where they come from.', 'Flying is less enjoyable than travelling by land.', 'People in different countries live in very different ways.', 'Plane passengers miss the gradual changes between places.'], 'D'),
                        $mcq(23, 'What happened when Elena missed her train connections?', ['She had experiences she would not otherwise have had.', 'She gave up her project and went back to Vienna.', 'She had to finish the rest of her journey by plane instead.', 'She lost some of the photographs she had taken.'], 'A'),
                        $mcq(24, "According to the article, how did the year affect Elena's finances?", ['Her income dropped sharply and she had to borrow money.', 'She spent much more on accommodation than before.', 'Her income fell, but not as much as she had feared.', 'Editors refused to pay for stories about slow travel.'], 'C'),
                        ['type' => 'text', 'text' => '**Questions 25–29.** Do the following statements agree with the information in the text?'],
                        $tfng(25, 'Before her year without flying, Elena sometimes took more than five flights in a single month.', 'TRUE'),
                        $tfng(26, "The magazine paid for Elena's journey to Samarkand.", 'NOT GIVEN'),
                        $tfng(27, 'Elena found it easy to buy tickets for international train journeys.', 'FALSE'),
                        $tfng(28, "Some of Elena's readers have booked train tickets instead of flights.", 'TRUE'),
                        $tfng(29, 'Elena believes that people should never travel by plane.', 'FALSE'),
                    ],
                ],
                [
                    'title' => 'Part 5',
                    'instructions' => "Read the text. For questions 30–33, complete the summary. Write **ONE WORD** from the text for each answer.\nFor questions 34–35, choose the correct answer **A**, **B**, **C** or **D**.",
                    'passage' => [
                        'title' => 'When the Machine Does the Thinking',
                        'text' => "Modern passenger aircraft can fly themselves for most of a journey. The pilots' main task is to monitor the automatic systems and to intervene only if something goes wrong, and there is no doubt that this arrangement has made air travel safer than ever before. Yet aviation researchers have identified a troubling side effect. Pilots who spend most of their time supervising a computer have fewer opportunities to practise basic flying skills, so when the system suddenly hands control back to them in an emergency, they may react more slowly, or less accurately, than pilots of an earlier generation. Some specialists call this the automation paradox: the more reliable a system becomes, the less prepared its human operators are to deal with its rare failures.\n\nThe same pattern can be seen in ordinary life. Drivers who follow satellite navigation tend to remember their routes less well than those who use paper maps, because the device does the difficult work of planning and orientation for them. A much-discussed study of London taxi drivers, who must memorise thousands of streets before they receive a licence, found that the part of their brain associated with spatial memory was larger than average. Some scientists suggest that people who never have to find their own way may never develop comparable mental maps, although others warn that the evidence is still limited and that the brain is more adaptable than such comparisons imply.\n\nConcerns of this kind are far from new. According to Plato, the philosopher Socrates once complained that the invention of writing would make people forgetful, because they would rely on marks on a page instead of exercising their memories. He was partly right: few of us today could recite a long poem by heart. But few of us would want to give up books, either. Each new tool has changed which skills a society values, and often the loss of one ability has been more than repaid by the arrival of others. The real question, therefore, is not whether technology changes us, but whether we understand what we are giving up in exchange.\n\nSome designers have begun to take this question seriously. Certain flight-training schools now require pilots to fly manually for part of every journey, even when the automatic system could do the job perfectly well. A number of language-learning applications deliberately wait a few seconds before showing the translation of a word, so that the learner first has to make an effort to remember it. Some teachers, too, ask pupils to struggle with a problem alone for a few minutes before showing them the method. The principle behind these examples is that a degree of difficulty is a necessary ingredient of learning: a system that removes every obstacle may also remove the opportunity to develop skill.\n\nNowhere is the balance more delicate than in medicine, where software can now scan images and flag possible illnesses. Studies suggest that doctors working with such tools often detect more problems than they would alone, but that some become less careful when the software declares an image healthy, accepting its verdict without thinking. Hospitals that have considered this risk tend to ask doctors to form their own opinion first and only then to compare it with the computer's. In this way, the machine becomes a second opinion rather than a replacement for judgement.\n\nIndividuals can apply the same principle. Trying to find your way around a familiar neighbourhood without a phone, doing a simple calculation in your head, or writing a short letter by hand are all small exercises that keep abilities alive. None of this means that we should reject convenient technology; to do so would be as unrealistic as it would be unwise. It does mean, however, that we should decide consciously which skills we are happy to hand over to machines and which we want to keep, rather than allowing the decision to be made for us by default.",
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Summary',
                            'max_words' => 1,
                            'text' => 'The automation paradox describes what happens when a system becomes so [[30]] that the people who supervise it lose the chance to practise their skills. A study of London taxi drivers showed that the area of the brain linked to [[31]] memory was unusually large. Some flight schools now insist that pilots fly [[32]] during part of each journey, and certain language applications delay showing the [[33]] of a new word.',
                            'answers' => [
                                '30' => 'reliable',
                                '31' => 'spatial',
                                '32' => 'manually',
                                '33' => 'translation',
                            ],
                        ],
                        $mcq(34, 'What point does the writer make in the third paragraph?', ['Socrates was wrong to worry about the effects of writing on memory.', 'People today have much worse memories than people had in earlier centuries.', 'Tools change which abilities matter, and gains may outweigh losses.', 'Technology should be judged only by what it allows people to do.'], 'C'),
                        $mcq(35, 'Why do some hospitals ask doctors to form their own opinion before checking the software?', ['Because the software often fails to find illnesses in images.', 'To stop doctors accepting its verdict without thinking.', 'To make the examination of every image faster and cheaper.', 'Because patients trust human opinions more than computers.'], 'B'),
                    ],
                ],
            ],
        ],

        'writing' => [
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => 'Read the text and complete Task 1.1 and Task 1.2. You are advised to spend about 25 minutes on Part 1.',
                    'context' => "You live in a town. You have received this email from the town council.\n\n*Dear resident,*\n\n*Next spring the council plans to close the main street in the town centre to cars at weekends. We would like to turn it into a pedestrian area with cafés, market stalls and free bicycle hire. Cars would park in a new car park on the edge of town, and a free bus would take people to the centre.*\n\n*Please tell us what you think about these plans and suggest any other improvements.*\n\n*Yours faithfully,*\n*Nargiza Yusupova, Transport Officer*",
                    'tasks' => [
                        [
                            'id' => '1.1',
                            'title' => 'Task 1.1',
                            'prompt' => 'Write a letter to your friend, who also lives in the town. Tell your friend about the email and say how you feel about the plans.' . "\n\n" . 'Write about **50** words.',
                            'min_words' => 50,
                            'max_words' => 0,
                        ],
                        [
                            'id' => '1.2',
                            'title' => 'Task 1.2',
                            'prompt' => 'Write a letter to the Transport Officer. Give your opinion about the plans and suggest other improvements.' . "\n\n" . 'Write **120–150** words.',
                            'min_words' => 120,
                            'max_words' => 150,
                        ],
                    ],
                ],
                [
                    'title' => 'Part 2',
                    'instructions' => 'You are advised to spend about 35 minutes on Part 2.',
                    'context' => '',
                    'tasks' => [
                        [
                            'id' => '2',
                            'title' => 'Task 2',
                            'prompt' => "You have seen this question in a university magazine:\n\n*Some people think that every university student should spend one year studying in another country. Others believe that it is better to complete the whole degree at home. What do you think?*\n\nWrite an essay for the magazine, discussing both views and giving your own opinion. Write **180–200** words.",
                            'min_words' => 180,
                            'max_words' => 200,
                        ],
                    ],
                ],
            ],
        ],

        'speaking' => [
            'parts' => [
                [
                    'id' => '1.1',
                    'title' => 'Part 1.1',
                    'instructions' => 'Answer the questions about yourself. You have 30 seconds for each answer.',
                    'questions' => [
                        ['no' => 1, 'text' => 'What do you usually do on your way to school, university or work?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq1'],
                        ['no' => 2, 'text' => 'Tell me about a place in your town or city that you enjoy visiting.', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq2'],
                        ['no' => 3, 'text' => 'Do you prefer to study alone or with other people? Why?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq3'],
                    ],
                ],
                [
                    'id' => '1.2',
                    'title' => 'Part 1.2',
                    'instructions' => 'Look at the two pictures and answer the questions.',
                    'images' => ['@image:s12_1', '@image:s12_2'],
                    'questions' => [
                        ['no' => 4, 'text' => 'Compare the two ways of travelling. What are the advantages and disadvantages of each?', 'prep_sec' => 0, 'answer_sec' => 45, 'audio' => '@audio:sq4'],
                        ['no' => 5, 'text' => 'Which of these two ways of travelling would you choose for a long holiday? Why?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq5'],
                        ['no' => 6, 'text' => 'How has technology changed the way people travel?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq6'],
                    ],
                ],
                [
                    'id' => '2',
                    'title' => 'Part 2',
                    'instructions' => 'Look at the picture and answer the questions. You have one minute to prepare and two minutes to speak.',
                    'images' => ['@image:s2'],
                    'questions' => [
                        ['no' => 7, 'text' => "Describe what the people in the picture are doing and how they might be feeling. Then tell me about a family celebration that is important to you.\n- What do you celebrate?\n- Who prepares the food?\n- Why is this celebration special for you?", 'prep_sec' => 60, 'answer_sec' => 120, 'audio' => '@audio:sq7'],
                    ],
                ],
                [
                    'id' => '3',
                    'title' => 'Part 3',
                    'instructions' => 'Read the statement and the arguments for and against it. You have one minute to prepare and two minutes to speak.',
                    'topic' => 'Students should be allowed to use smartphones in class.',
                    'for' => [
                        'Smartphones give students quick access to dictionaries and information.',
                        'Students can photograph the board and keep their notes in one place.',
                        'Learning to use technology responsibly is a useful life skill.',
                    ],
                    'against' => [
                        'Notifications and games distract students from the lesson.',
                        'Some students may use their phones to cheat in tests.',
                        'Not every student can afford a modern smartphone.',
                    ],
                    'questions' => [
                        ['no' => 8, 'text' => 'Discuss both sides of the argument and give your own opinion. Use at least two points from each list.', 'prep_sec' => 60, 'answer_sec' => 120, 'audio' => '@audio:sq8'],
                    ],
                ],
            ],
        ],
    ],
];

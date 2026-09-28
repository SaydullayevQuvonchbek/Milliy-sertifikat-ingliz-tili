<?php

// Namunaviy mock — rasmiy Multilevel formatiga to'liq mos (L 6 qism/35, R 5 qism/35, W 3 topshiriq, S 8 savol).
// Barcha matnlar ushbu loyiha uchun yozilgan (mualliflik huquqi muammosi yo'q).
// "@audio:..." va "@image:..." — seed-demo.php yaratadigan vaqtinchalik fayllar. Haqiqiy imtihon uchun
// transkriptlar asosida audio yozib, admin panelda almashtiring.

declare(strict_types=1);

$mcq = static fn (int $n, string $prompt, array $options, string $answer): array => [
    'type' => 'mcq', 'n' => $n, 'prompt' => $prompt, 'options' => $options, 'answer' => $answer,
];
$tfng = static fn (int $n, string $prompt, string $answer): array => [
    'type' => 'tfng', 'n' => $n, 'prompt' => $prompt, 'answer' => $answer,
];

return [
    'title' => 'Namunaviy mock №1 (to\'liq format)',
    'description' => 'Rasmiy formatdagi to\'liq namuna: Listening, Reading, Writing va Speaking. Audio va rasmlar vaqtinchalik — admin panelda haqiqiy fayllar bilan almashtiring.',
    'assets' => [
        'audio' => [
            'l1_1' => 9, 'l1_2' => 9, 'l1_3' => 10, 'l1_4' => 9, 'l1_5' => 9, 'l1_6' => 9, 'l1_7' => 8, 'l1_8' => 9,
            'l2' => 80, 'l3_1' => 20, 'l3_2' => 20, 'l3_3' => 20, 'l3_4' => 20, 'l4' => 75,
            'l5_1' => 35, 'l5_2' => 35, 'l5_3' => 35, 'l6' => 90,
        ],
        'image' => [
            's12_1' => 'Picture 1: Students studying in a library',
            's12_2' => 'Picture 2: A student studying online at home',
            's2' => 'A family having dinner together',
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
                        ['asset' => '@audio:l1_1', 'label' => 'Conversation 1', 'transcript' => "W: Excuse me, is this the right platform for the train to Samarkand?\nM: No, that one leaves from platform four, over the bridge."],
                        ['asset' => '@audio:l1_2', 'label' => 'Conversation 2', 'transcript' => "M: Shall we meet at the cinema at seven?\nW: The film starts at seven, so let's meet half an hour earlier."],
                        ['asset' => '@audio:l1_3', 'label' => 'Conversation 3', 'transcript' => "W: How was your holiday in the mountains?\nM: Beautiful, but it rained almost every day, so we spent most of the time reading in the hotel."],
                        ['asset' => '@audio:l1_4', 'label' => 'Conversation 4', 'transcript' => "M: I'd like to return these shoes. They're too small.\nW: I'm afraid we don't have a bigger size, but I can give you your money back."],
                        ['asset' => '@audio:l1_5', 'label' => 'Conversation 5', 'transcript' => "W: Did you walk to work today?\nM: No, my bike's broken and the buses were full, so I took a taxi."],
                        ['asset' => '@audio:l1_6', 'label' => 'Conversation 6', 'transcript' => "M: Are you going to Aziza's party on Saturday?\nW: I'd love to, but I promised to help my brother move house that day."],
                        ['asset' => '@audio:l1_7', 'label' => 'Conversation 7', 'transcript' => "W: This soup is delicious. Did you make it yourself?\nM: Actually, my grandmother made it. I only warmed it up."],
                        ['asset' => '@audio:l1_8', 'label' => 'Conversation 8', 'transcript' => "M: Is the library open on Sundays?\nW: Only in the summer. The rest of the year it closes at the weekend."],
                    ],
                    'blocks' => [
                        $mcq(1, 'Where does the woman need to go?', ['To platform four.', 'To the ticket office.', 'To the café by the bridge.'], 'A'),
                        $mcq(2, 'What time will the speakers meet?', ['At 6:30.', 'At 7:00.', 'At 7:30.'], 'A'),
                        $mcq(3, 'What did the man do on holiday?', ['He went hiking every day.', 'He mostly stayed indoors.', 'He went skiing.'], 'B'),
                        $mcq(4, 'What will the woman do?', ['Give the man a refund.', 'Order a bigger size.', 'Exchange the shoes for boots.'], 'A'),
                        $mcq(5, 'How did the man get to work?', ['By bike.', 'By bus.', 'By taxi.'], 'C'),
                        $mcq(6, "Why can't the woman go to the party?", ['She has to work.', 'She is helping a family member.', 'She is feeling ill.'], 'B'),
                        $mcq(7, 'Who made the soup?', ['The man.', 'The woman.', "The man's grandmother."], 'C'),
                        $mcq(8, 'When is the library open on Sundays?', ['All year round.', 'Only in the summer.', 'Never.'], 'B'),
                    ],
                ],
                [
                    'title' => 'Part 2',
                    'instructions' => "You will hear a guide giving information about a city museum. You will hear the talk twice.\nFor questions 9–14, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l2', 'label' => 'Talk', 'transcript' => "Good morning, everyone, and welcome to the City History Museum. Before we begin, here is some practical information. The museum is open every day except Monday, from nine in the morning until six in the evening. Tickets for adults cost thirty thousand so'm, and students pay half price if they show their student card. The museum has three floors. On the ground floor you will find our gift shop and a small café. The first floor is dedicated to the history of the Silk Road, and our most popular exhibit, a collection of old coins, is on the second floor. Please do not use a flash when you take photographs, as it can damage the paintings. Guided tours start every hour from the main entrance, and they last about forty-five minutes. Finally, bags larger than a laptop bag must be left in the cloakroom, which is free of charge."],
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'City History Museum',
                            'max_words' => 1,
                            'text' => "- The museum is closed on [[9]].\n- To get a discount, students must show their student [[10]].\n- The most popular exhibit is a collection of old [[11]].\n- Visitors must not use a [[12]] when taking photographs.\n- Guided tours last about [[13]] minutes.\n- Large bags must be left in the [[14]].",
                            'answers' => [
                                '9' => 'Monday|Mondays',
                                '10' => 'card',
                                '11' => 'coins',
                                '12' => 'flash',
                                '13' => '45|forty-five|forty five',
                                '14' => 'cloakroom',
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 3',
                    'instructions' => "You will hear four people talking about learning a foreign language. You will hear the recordings twice.\nFor questions 15–18, choose from the list (**A–F**) what each speaker says. Use each letter only once. There are **two** extra letters which you do not need to use.",
                    'preview_sec' => 30,
                    'gap_sec' => 4,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l3_1', 'label' => 'Speaker 1', 'transcript' => "When I got a job at an international hotel, I had no choice. I had to speak English with guests from the very first day, so I learned quickly."],
                        ['asset' => '@audio:l3_2', 'label' => 'Speaker 2', 'transcript' => "For the first year I hardly said a word in class. I was so worried that people would laugh at my pronunciation. Now I realise that everybody makes mistakes."],
                        ['asset' => '@audio:l3_3', 'label' => 'Speaker 3', 'transcript' => "I didn't take any lessons at all. I just watched cartoons and later whole films in English, first with subtitles and then without them."],
                        ['asset' => '@audio:l3_4', 'label' => 'Speaker 4', 'transcript' => "At school I thought English was boring, until a new teacher arrived. She brought songs and games into every lesson, and suddenly it became my favourite subject."],
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'What does each speaker say?',
                            'options' => [
                                ['key' => 'A', 'text' => 'I learned mainly by watching films.'],
                                ['key' => 'B', 'text' => 'A teacher made me love the language.'],
                                ['key' => 'C', 'text' => 'I was afraid of making mistakes at first.'],
                                ['key' => 'D', 'text' => 'I needed the language for my job.'],
                                ['key' => 'E', 'text' => 'I practised with friends online.'],
                                ['key' => 'F', 'text' => 'I studied grammar every morning.'],
                            ],
                            'items' => [
                                ['n' => 15, 'prompt' => 'Speaker 1', 'answer' => 'D'],
                                ['n' => 16, 'prompt' => 'Speaker 2', 'answer' => 'C'],
                                ['n' => 17, 'prompt' => 'Speaker 3', 'answer' => 'A'],
                                ['n' => 18, 'prompt' => 'Speaker 4', 'answer' => 'B'],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 4',
                    'instructions' => "You will hear a woman talking about how her town has changed. You will hear the talk twice.\nFor questions 19–23, choose what has happened to each place (**A–H**). There are **three** extra options which you do not need to use.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l4', 'label' => 'Talk', 'transcript' => "I grew up in this town, and it has changed a lot in the last ten years. The old cinema on Navoi Street, where I watched my first film, has been turned into a museum of local crafts, which I think is a good idea. The central park used to charge a small entrance fee, but now anyone can walk in without paying, so it's always full of families. The train station is exactly where it used to be, but it has been extended, with two new platforms and a much larger waiting hall. The market, sadly, is no longer in the city centre. It moved to a big new building near the ring road last year. As for the sports centre, it was badly damaged in a storm, so at the moment builders are working on it, and it should reopen next spring."],
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'What has happened to each place?',
                            'options' => [
                                ['key' => 'A', 'text' => 'It has been made bigger.'],
                                ['key' => 'B', 'text' => 'It has been turned into a museum.'],
                                ['key' => 'C', 'text' => 'It has moved to a new location.'],
                                ['key' => 'D', 'text' => 'It has closed down.'],
                                ['key' => 'E', 'text' => 'It has become free to use.'],
                                ['key' => 'F', 'text' => 'It is now open at night.'],
                                ['key' => 'G', 'text' => 'It has been painted a new colour.'],
                                ['key' => 'H', 'text' => 'It is being rebuilt.'],
                            ],
                            'items' => [
                                ['n' => 19, 'prompt' => 'The old cinema', 'answer' => 'B'],
                                ['n' => 20, 'prompt' => 'The central park', 'answer' => 'E'],
                                ['n' => 21, 'prompt' => 'The train station', 'answer' => 'A'],
                                ['n' => 22, 'prompt' => 'The market', 'answer' => 'C'],
                                ['n' => 23, 'prompt' => 'The sports centre', 'answer' => 'H'],
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
                        ['asset' => '@audio:l5_1', 'label' => 'Conversation 1', 'transcript' => "W: Hi, Sardor. Are you still planning to buy a new laptop?\nM: I was, but I've decided to repair my old one instead. The shop says it will only take two days.\nW: That's much cheaper. What's wrong with it?\nM: The battery. It stops working after about twenty minutes."],
                        ['asset' => '@audio:l5_2', 'label' => 'Conversation 2', 'transcript' => "M: Madina, how's your new job at the bank?\nW: It's going well, although the hours are long. I start at eight and often don't finish until seven.\nM: Do you still have time for tennis?\nW: Only at the weekend now. I play every Sunday morning with my sister."],
                        ['asset' => '@audio:l5_3', 'label' => 'Conversation 3', 'transcript' => "W: Excuse me, I booked a table for four people, but two more friends are coming.\nM: That's fine. We can move you to the big table by the window.\nW: Great. And could we see the children's menu too?\nM: Of course. I'll bring it with the drinks."],
                    ],
                    'blocks' => [
                        ['type' => 'text', 'text' => '**Conversation 1**'],
                        $mcq(24, 'What has the man decided to do?', ['Buy a new laptop.', 'Repair his old laptop.', 'Borrow a laptop from a friend.'], 'B'),
                        $mcq(25, 'What is wrong with the laptop?', ['The screen is broken.', 'It is too slow.', 'The battery does not last long.'], 'C'),
                        ['type' => 'text', 'text' => '**Conversation 2**'],
                        $mcq(26, 'What does the woman say about her job?', ['The working day is long.', 'The salary is low.', 'The office is far away.'], 'A'),
                        $mcq(27, 'When does the woman play tennis now?', ['Every evening.', 'On Saturday afternoons.', 'On Sunday mornings.'], 'C'),
                        ['type' => 'text', 'text' => '**Conversation 3**'],
                        $mcq(28, 'Why does the woman speak to the waiter?', ['She wants to cancel her booking.', 'More people are joining her group.', 'She wants to sit outside.'], 'B'),
                        $mcq(29, 'What will the waiter bring?', ["A children's menu.", 'The bill.', 'Some extra chairs.'], 'A'),
                    ],
                ],
                [
                    'title' => 'Part 6',
                    'instructions' => "You will hear part of a lecture about honey bees. You will hear the lecture twice.\nFor questions 30–35, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        ['asset' => '@audio:l6', 'label' => 'Lecture', 'transcript' => "Today I'd like to talk about honey bees, which are among the most useful insects on the planet. A single colony can contain up to sixty thousand bees, but only one of them, the queen, lays eggs. She can lay as many as two thousand eggs in a single day. Most of the bees in a colony are female worker bees. Their jobs change as they get older: young workers clean the hive and feed the larvae, while older workers leave the hive to collect nectar and pollen. Bees tell each other where to find flowers through a movement that scientists call the waggle dance. The angle of the dance shows the direction of the food, and its length shows the distance. Bees are essential for farming because they pollinate about a third of the crops we eat. Unfortunately, their numbers are falling in many countries. The main causes are pesticides, the loss of wild flowers and diseases carried by a tiny parasite. Farmers can help by planting flowers along the edges of their fields."],
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Honey bees',
                            'max_words' => 1,
                            'text' => "- A colony can contain up to [[30]] bees.\n- The queen can lay up to 2,000 eggs per [[31]].\n- Young worker bees clean the hive and feed the [[32]].\n- The direction of food is shown by the [[33]] of the waggle dance.\n- Bees pollinate about a [[34]] of the crops people eat.\n- Farmers can help by planting flowers along the edges of their [[35]].",
                            'answers' => [
                                '30' => '60,000|60000|60 000|sixty thousand',
                                '31' => 'day',
                                '32' => 'larvae',
                                '33' => 'angle',
                                '34' => 'third',
                                '35' => 'fields',
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
                            'title' => 'The library is reopening',
                            'max_words' => 1,
                            'text' => "Dear residents,\n\nWe are happy to announce that the Riverside Community Library will reopen on 1 March after six months of repair [[1]]. The building now has a larger reading room, faster internet and a new section for young children.\n\nTo celebrate, we are organising a special weekend of events. On Saturday, a well-known author will read from her [[2]] book and answer questions from the audience. On Sunday, children can take part in a drawing competition, and the winners will receive books as [[3]].\n\nMembership is still free for everyone who lives in the area. To join, simply bring a document that shows your home [[4]] and fill in a short form at the front desk.\n\nWe would also like to thank the many volunteers who gave up their free [[5]] to help us put thousands of books back on the shelves.\n\nWe look [[6]] to seeing you soon!\n\nThe Library Team",
                            'answers' => [
                                '1' => 'work|works',
                                '2' => 'new|latest',
                                '3' => 'prizes|prize|gifts|presents',
                                '4' => 'address',
                                '5' => 'time',
                                '6' => 'forward',
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 2',
                    'instructions' => 'Read the announcements (7–14) and the statements (**A–J**). Match each announcement with the correct statement. There are **two** extra statements which you do not need to use.',
                    'passage' => [
                        'title' => 'Announcements',
                        'text' => "[7] **Lost:** a small brown dog with a red collar, last seen near Chorsu Market on Tuesday evening. He answers to the name Bobur and is very friendly. Please call 90 555 12 34.\n\n[8] **Guitar lessons for beginners.** Learn to play your favourite songs in just ten lessons. Classes take place every Wednesday in the community centre. The first lesson is free!\n\n[9] **Room available** in a quiet flat near the university. Suitable for a non-smoking student. The kitchen and bathroom are shared. The rent includes electricity and internet.\n\n[10] Our shop will be closed from 10 to 14 July for staff training. We apologise for any inconvenience and look forward to welcoming you back.\n\n[11] **Volunteers needed!** Help us clean the city park this Saturday. Gloves and bags will be provided, and lunch is free for all helpers.\n\n[12] **For sale:** a children's bicycle in excellent condition, used for only one summer. The price can be discussed. Collect from Yunusabad.\n\n[13] The swimming pool on Amir Temur Street now offers early morning sessions from 6 a.m., which are perfect for people who want to swim before work.\n\n[14] **Found:** a set of keys on the number 45 bus. Please contact the bus station office and describe the key ring to collect them.",
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'Which statement (A–J) matches each announcement?',
                            'options' => [
                                ['key' => 'A', 'text' => 'You can win a prize in a competition.'],
                                ['key' => 'B', 'text' => 'Someone found something that belongs to another person.'],
                                ['key' => 'C', 'text' => 'A business will not be open for a few days.'],
                                ['key' => 'D', 'text' => 'Someone is looking for a pet.'],
                                ['key' => 'E', 'text' => 'There is a new timetable for people who start work early.'],
                                ['key' => 'F', 'text' => 'You do not need to pay for bills separately.'],
                                ['key' => 'G', 'text' => 'A concert has been cancelled because of the weather.'],
                                ['key' => 'H', 'text' => 'People can get a meal for helping.'],
                                ['key' => 'I', 'text' => 'You can try something for free before paying.'],
                                ['key' => 'J', 'text' => 'Someone wants to sell something they no longer need.'],
                            ],
                            'items' => [
                                ['n' => 7, 'prompt' => 'Announcement 7', 'answer' => 'D'],
                                ['n' => 8, 'prompt' => 'Announcement 8', 'answer' => 'I'],
                                ['n' => 9, 'prompt' => 'Announcement 9', 'answer' => 'F'],
                                ['n' => 10, 'prompt' => 'Announcement 10', 'answer' => 'C'],
                                ['n' => 11, 'prompt' => 'Announcement 11', 'answer' => 'H'],
                                ['n' => 12, 'prompt' => 'Announcement 12', 'answer' => 'J'],
                                ['n' => 13, 'prompt' => 'Announcement 13', 'answer' => 'E'],
                                ['n' => 14, 'prompt' => 'Announcement 14', 'answer' => 'B'],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 3',
                    'instructions' => 'Read the text. For questions 15–20, choose the correct heading for each paragraph from the list of headings (**I–VIII**). There are **two** extra headings which you do not need to use.',
                    'passage' => [
                        'title' => 'The Return of the Bicycle',
                        'text' => "[A] Only a few decades ago, many experts predicted that the bicycle would soon disappear from the streets of large cities. Cars were becoming cheaper, roads were getting wider, and cycling seemed old-fashioned. Today, however, the picture is completely different. In cities from Copenhagen to Bogotá, the number of people who cycle to work or school has risen sharply, and many city governments now treat the bicycle as a serious form of transport.\n\n[B] One of the main reasons for this change is concern about health. Doctors have long warned that people in modern cities do not move enough. Cycling to work allows people to include exercise in their daily routine without finding extra time for the gym. Studies suggest that regular cyclists take fewer days off sick and are less likely to suffer from heart disease than people who travel by car.\n\n[C] Money also plays a part. Owning a car has become increasingly expensive because of the cost of fuel, insurance and parking. A good bicycle, on the other hand, can be bought for the price of a few months of fuel, and it costs very little to maintain. For students and young workers in particular, the savings can be significant.\n\n[D] Cities have encouraged this trend by changing their streets. Separate cycle lanes, protected from traffic by low walls or rows of trees, make cyclists feel much safer. Some cities have also introduced bike-sharing schemes, which allow people to rent a bicycle for a short journey using a mobile phone app and leave it at any station in the city.\n\n[E] Nevertheless, cycling is not suitable for everyone or for every journey. In cities with very hot summers or cold, snowy winters, few people want to cycle all year round. Steep hills can also be a problem, and parents with small children often find it difficult to manage without a car. Electric bicycles, which help the rider when climbing hills, have solved some of these problems, but they are still quite expensive.\n\n[F] Looking to the future, many planners believe that the most successful cities will be those that combine different types of transport. A commuter might cycle to a train station, leave the bicycle in a secure bike park and complete the journey by train. If this vision becomes reality, the bicycle will not replace the car completely, but it will become an essential part of the way we move around.",
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'List of headings',
                            'options' => [
                                ['key' => 'I', 'text' => 'A cheaper way to travel'],
                                ['key' => 'II', 'text' => 'Why cycling is good for the body'],
                                ['key' => 'III', 'text' => 'An unexpected comeback'],
                                ['key' => 'IV', 'text' => 'The history of the first bicycles'],
                                ['key' => 'V', 'text' => 'Making roads safer and easier for cyclists'],
                                ['key' => 'VI', 'text' => 'Some practical limitations'],
                                ['key' => 'VII', 'text' => 'How cycling affects the environment'],
                                ['key' => 'VIII', 'text' => 'Part of a bigger system'],
                            ],
                            'items' => [
                                ['n' => 15, 'prompt' => 'Paragraph A', 'answer' => 'III'],
                                ['n' => 16, 'prompt' => 'Paragraph B', 'answer' => 'II'],
                                ['n' => 17, 'prompt' => 'Paragraph C', 'answer' => 'I'],
                                ['n' => 18, 'prompt' => 'Paragraph D', 'answer' => 'V'],
                                ['n' => 19, 'prompt' => 'Paragraph E', 'answer' => 'VI'],
                                ['n' => 20, 'prompt' => 'Paragraph F', 'answer' => 'VIII'],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 4',
                    'instructions' => "Read the text. For questions 21–24, choose the correct answer **A**, **B**, **C** or **D**.\nFor questions 25–29, decide if the statements agree with the information in the text. Choose **TRUE**, **FALSE** or **NOT GIVEN**.",
                    'passage' => [
                        'title' => 'From a Kitchen Table to Shops Around the World',
                        'text' => "When Nodira Karimova lost her job as an accountant in 2012, she did not expect that a family recipe would change her life. For months she applied for positions at other companies, but without success. To earn a little money while she looked for work, she began making dried apricots and raisins in the way her grandmother had taught her and selling them to her neighbours.\n\nThe fruit was so popular that people soon started placing regular orders. Within a year, Nodira was spending every evening in her kitchen, and her small flat was full of boxes. \"My husband joked that we would soon have to sleep on bags of apricots,\" she remembers. It was at this point that she decided to stop looking for an office job and to turn her hobby into a real business.\n\nThe first years were difficult. Nodira had no experience of marketing, and banks refused to lend her money because her company was too small. Instead, she borrowed from relatives and used her savings to rent a small workshop on the edge of the city. She employed two women from her neighbourhood, and together they worked long hours, drying and packing the fruit by hand.\n\nThe turning point came in 2016, when a buyer from a large supermarket chain tasted her apricots at a food fair in Tashkent. He was impressed by their natural sweetness and offered to sell them in fifty stores. To meet the demand, Nodira had to buy new machines and hire more staff. \"It was frightening,\" she admits. \"If the supermarket had changed its mind, I would have lost everything.\"\n\nFortunately, sales grew steadily. Today, her company employs more than one hundred and twenty people, most of them women, and its products are sold in eleven countries. Despite this success, Nodira insists that quality matters more than size. All the fruit still comes from farms in the Fergana Valley, and no sugar or preservatives are added.\n\nNodira is also proud of the training programme she has created for young people from rural areas. Every year, twenty students spend six months at the company, learning about food production, accounting and sales. Several of them have gone on to start their own businesses.\n\nWhen asked what advice she would give to people who want to start a company, Nodira smiles. \"Start small, listen to your customers and never be afraid to ask for help,\" she says. \"And remember that every big company was once a small idea on somebody's kitchen table.\"",
                    ],
                    'blocks' => [
                        $mcq(21, 'Why did Nodira start making dried fruit?', ['She wanted to continue a family tradition.', 'She needed some income while she was looking for a job.', 'Her neighbours asked her to teach them.', 'She had always dreamed of owning a business.'], 'B'),
                        $mcq(22, "What does the joke by Nodira's husband show?", ['He did not support her plans.', 'Their flat had become very crowded.', 'He wanted to move to a bigger home.', 'The business was losing money.'], 'B'),
                        $mcq(23, 'How did Nodira pay for her first workshop?', ['With a loan from a bank.', 'With money from a supermarket.', 'With her own money and help from her family.', 'With a grant from the city.'], 'C'),
                        $mcq(24, "How did Nodira feel after the supermarket's offer?", ['Calm, because the deal was safe.', 'Worried about the risks involved.', 'Disappointed with the price.', 'Unsure whether the fruit was good enough.'], 'B'),
                        ['type' => 'text', 'text' => '**Questions 25–29.** Do the following statements agree with the information in the text?'],
                        $tfng(25, 'Nodira worked as an accountant before she started her business.', 'TRUE'),
                        $tfng(26, 'The supermarket buyer first tasted the apricots at a food fair.', 'TRUE'),
                        $tfng(27, "The company's products are now sold in more than twenty countries.", 'FALSE'),
                        $tfng(28, 'The company adds a small amount of sugar to some of its products.', 'FALSE'),
                        $tfng(29, 'Most of the students on the training programme are women.', 'NOT GIVEN'),
                    ],
                ],
                [
                    'title' => 'Part 5',
                    'instructions' => "Read the text. For questions 30–33, complete the summary. Write **ONE WORD** from the text for each answer.\nFor questions 34–35, choose the correct answer **A**, **B**, **C** or **D**.",
                    'passage' => [
                        'title' => 'Why Do We Forget?',
                        'text' => "Most of us have walked into a room and immediately forgotten why we went there. Moments like these can be annoying, but scientists who study memory argue that forgetting is not simply a failure of the brain. In many cases, it is a useful process that helps us to deal with the enormous amount of information we receive every day.\n\nOne of the first researchers to study forgetting in a systematic way was the German psychologist Hermann Ebbinghaus, who worked in the late nineteenth century. He memorised long lists of nonsense syllables and then tested himself after different periods of time. His results showed that most forgetting happens very quickly, within the first hour or so, and then slows down. This pattern is now known as the forgetting curve.\n\nWhy does this happen? One explanation is that memories are not stored like files on a computer. Each time we remember something, the brain rebuilds the memory, and small details can change or disappear. Another explanation is interference: new information can make it harder to recall older information, especially when the two are similar. For example, a person who has changed their phone number several times may find it difficult to remember any of the old ones.\n\nSleep also appears to play an important role. During deep sleep, the brain seems to replay the experiences of the day and decide which memories to keep. Students who sleep well after studying often remember more than those who stay awake all night before an exam.\n\nThe good news is that there are simple techniques that can reduce forgetting. The most effective is probably spaced repetition: instead of reviewing new material many times on the same day, learners review it several times over a period of days or weeks, with longer gaps between each review. Another useful technique is to test yourself rather than simply reading your notes again, because the effort of recalling information makes the memory stronger.\n\nFinally, researchers remind us that forgetting has advantages. A brain that remembered every detail would quickly become overloaded, and it would be hard to see the general patterns that help us make decisions. In this sense, forgetting is not the opposite of memory but a part of it.",
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Summary',
                            'max_words' => 1,
                            'text' => 'Ebbinghaus found that most forgetting happens within the first [[30]] after learning. One reason we forget is [[31]], when new information makes older information harder to recall. During deep [[32]], the brain seems to choose which memories to keep. Reviewing material over days or weeks, which is known as spaced [[33]], helps to reduce forgetting.',
                            'answers' => [
                                '30' => 'hour',
                                '31' => 'interference',
                                '32' => 'sleep',
                                '33' => 'repetition',
                            ],
                        ],
                        $mcq(34, 'According to the writer, what happens each time we remember something?', ['The memory is stored in a new place.', 'The brain rebuilds the memory, and details may change.', 'The memory becomes permanent.', 'Older memories are deleted.'], 'B'),
                        $mcq(35, "What is the writer's main point in the final paragraph?", ['Forgetting is a serious medical problem.', 'People should try to remember every detail.', 'Forgetting is a normal and useful part of memory.', 'Most people forget things because they are tired.'], 'C'),
                    ],
                ],
            ],
        ],

        'writing' => [
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => 'Read the email and complete Task 1.1 and Task 1.2. You are advised to spend about 25 minutes on Part 1.',
                    'context' => "You are a member of a sports club. You have received this email from the club manager.\n\n*Dear member,*\n\n*We are planning to change the club's opening hours next month. From 1 May, the club will open at 9 a.m. instead of 7 a.m. and close at 8 p.m. instead of 10 p.m. We would also like to replace the swimming classes with more yoga classes.*\n\n*Please let us know what you think about these changes and suggest any other improvements.*\n\n*Best wishes,*\n*Aziz Rahimov, Club Manager*",
                    'tasks' => [
                        [
                            'id' => '1.1',
                            'title' => 'Task 1.1',
                            'prompt' => 'Write a letter to your friend, who is also a member of the club. Tell your friend about the email and say how you feel about the changes.' . "\n\n" . 'Write about **50** words.',
                            'min_words' => 50,
                            'max_words' => 0,
                        ],
                        [
                            'id' => '1.2',
                            'title' => 'Task 1.2',
                            'prompt' => 'Write a letter to the club manager. Give your opinion about the planned changes and suggest other improvements.' . "\n\n" . 'Write **120–150** words.',
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
                            'prompt' => "You have seen this post on an online education forum:\n\n*Some people say that students learn more from online courses than from lessons in a real classroom. Others believe that face-to-face teaching will always be better. What do you think?*\n\nWrite a post for the forum giving your opinion. Write **180–200** words.",
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
                        ['no' => 1, 'text' => 'What do you usually do at the weekend?', 'prep_sec' => 0, 'answer_sec' => 30],
                        ['no' => 2, 'text' => 'Tell me about the place where you live.', 'prep_sec' => 0, 'answer_sec' => 30],
                        ['no' => 3, 'text' => 'What kind of music do you enjoy? Why?', 'prep_sec' => 0, 'answer_sec' => 30],
                    ],
                ],
                [
                    'id' => '1.2',
                    'title' => 'Part 1.2',
                    'instructions' => 'Look at the two pictures and answer the questions.',
                    'images' => ['@image:s12_1', '@image:s12_2'],
                    'questions' => [
                        ['no' => 4, 'text' => 'Compare the two pictures. What are the advantages of studying in each place?', 'prep_sec' => 0, 'answer_sec' => 45],
                        ['no' => 5, 'text' => 'Where do you prefer to study? Why?', 'prep_sec' => 0, 'answer_sec' => 30],
                        ['no' => 6, 'text' => 'How has technology changed the way young people learn?', 'prep_sec' => 0, 'answer_sec' => 30],
                    ],
                ],
                [
                    'id' => '2',
                    'title' => 'Part 2',
                    'instructions' => 'Look at the picture and answer the questions. You have one minute to prepare and two minutes to speak.',
                    'images' => ['@image:s2'],
                    'questions' => [
                        ['no' => 7, 'text' => "Tell me about a family tradition that is important to you.\n- What is the tradition?\n- How often do you follow it?\n- Why is it important to you and your family?", 'prep_sec' => 60, 'answer_sec' => 120],
                    ],
                ],
                [
                    'id' => '3',
                    'title' => 'Part 3',
                    'instructions' => 'Read the statement and the arguments for and against it. You have one minute to prepare and two minutes to speak.',
                    'topic' => 'Everyone should learn to cook at school.',
                    'for' => [
                        'It helps young people to live a healthier life.',
                        'It saves money in the future.',
                        'It is a useful skill for living independently.',
                    ],
                    'against' => [
                        'Schools should focus on academic subjects.',
                        'Cooking can be learned at home.',
                        'Equipping school kitchens is expensive.',
                    ],
                    'questions' => [
                        ['no' => 8, 'text' => 'Discuss both sides of the argument and give your own opinion. Use at least two points from each list.', 'prep_sec' => 60, 'answer_sec' => 120],
                    ],
                ],
            ],
        ],
    ],
];

<?php

// Multilevel Mock 2 — to'liq rasmiy format (Listening 6 qism/35, Reading 5 qism/35, Writing 3 topshiriq, Speaking 8 savol).
// Mavzular: atrof-muhit va tabiat, mehnat va kasb, tarix va madaniyat, jamoat ishlari (volontyorlik).
// Barcha matnlar original. Audio: audio.json -> tools/synth.py (Kokoro TTS). Rasmlar: images/*.svg -> tools/render_images.py.
// Transkriptlar audio.json dan olinadi (content_transcript) — qo'lda takrorlanmaydi.

declare(strict_types=1);

require_once __DIR__ . '/../../lib.php';

$audio = content_audio(__DIR__);

$mcq = static fn (int $n, string $prompt, array $options, string $answer): array => [
    'type' => 'mcq', 'n' => $n, 'prompt' => $prompt, 'options' => $options, 'answer' => $answer,
];
$tfng = static fn (int $n, string $prompt, string $answer): array => [
    'type' => 'tfng', 'n' => $n, 'prompt' => $prompt, 'answer' => $answer,
];
$track = static fn (string $key, string $label): array => [
    'asset' => '@audio:' . $key, 'label' => $label, 'transcript' => content_transcript($audio, $key),
];

return [
    'title' => 'Multilevel Mock 2',
    'description' => "To'liq rasmiy formatdagi mock: Listening, Reading, Writing va Speaking. Barcha matnlar original, audio TTS yordamida yaratilgan, Speaking uchun rasmlar tayyor. Mavzular: atrof-muhit, mehnat va kasb, tarix va madaniyat, jamoat ishlari.",
    'assets' => [
        'audio' => [
            'l1_1', 'l1_2', 'l1_3', 'l1_4', 'l1_5', 'l1_6', 'l1_7', 'l1_8',
            'l2', 'l3_1', 'l3_2', 'l3_3', 'l3_4', 'l4', 'l5_1', 'l5_2', 'l5_3', 'l6',
            'sq1', 'sq2', 'sq3', 'sq4', 'sq5', 'sq6', 'sq7', 'sq8',
        ],
        'image' => [
            's12_1' => 'Picture 1: Colleagues working together in an office',
            's12_2' => 'Picture 2: A woman working from home',
            's2' => 'Volunteers cleaning up a river bank in a park',
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
                        $track('l1_1', 'Conversation 1'),
                        $track('l1_2', 'Conversation 2'),
                        $track('l1_3', 'Conversation 3'),
                        $track('l1_4', 'Conversation 4'),
                        $track('l1_5', 'Conversation 5'),
                        $track('l1_6', 'Conversation 6'),
                        $track('l1_7', 'Conversation 7'),
                        $track('l1_8', 'Conversation 8'),
                    ],
                    'blocks' => [
                        $mcq(1, 'What does the man suggest?', ['Leaving later than planned.', 'Cancelling the whole trip.', 'Going to a different park.'], 'A'),
                        $mcq(2, 'What time will the meeting take place?', ["At ten o'clock.", "At two o'clock.", 'At half past one.'], 'C'),
                        $mcq(3, 'What did the woman like best about the exhibition?', ['The old maps.', "The merchant's diary.", 'The models of caravans.'], 'B'),
                        $mcq(4, "What does the man say about the woman's son?", ['He can come to the picnic.', 'He can help with the clean-up.', 'He should bring his own gloves.'], 'A'),
                        $mcq(5, 'Why does the man want to change jobs?', ['He is unhappy with his salary.', 'He does not like the people he works with.', 'He wants to spend less time at a desk.'], 'C'),
                        $mcq(6, 'Why does the woman refuse a plastic bag?', ['She wants to save money.', 'She wants to protect the environment.', 'She already has too many at home.'], 'B'),
                        $mcq(7, 'Which tour does the woman book?', ['The morning tour.', 'The afternoon tour.', 'The evening tour.'], 'B'),
                        $mcq(8, 'Where will the new designer work at first?', ['With the marketing team.', 'In the new second-floor office.', "In the speakers' own team."], 'C'),
                    ],
                ],
                [
                    'title' => 'Part 2',
                    'instructions' => "You will hear a talk. You will hear the talk twice.\nFor questions 9–14, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        $track('l2', 'Talk'),
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Aral Sea tree-planting weekend',
                            'max_words' => 1,
                            'text' => "- Minimum age this year: [[9]]\n- Meeting point: outside the [[10]] in Nukus\n- Journey by minibus: about [[11]] hours\n- Essential item to bring: a face [[12]]\n- Target for each volunteer: [[13]] trees per day\n- Coordinator's surname: [[14]]",
                            'answers' => [
                                '9' => '16|sixteen',
                                '10' => 'museum',
                                '11' => '4|four',
                                '12' => 'mask',
                                '13' => '40|forty',
                                '14' => 'Rashidova',
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
                        $track('l3_1', 'Speaker 1'),
                        $track('l3_2', 'Speaker 2'),
                        $track('l3_3', 'Speaker 3'),
                        $track('l3_4', 'Speaker 4'),
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'What does each speaker say about starting a new job?',
                            'options' => [
                                ['key' => 'A', 'text' => 'The journey to work took up too much of my time.'],
                                ['key' => 'B', 'text' => 'An experienced colleague taught me useful skills.'],
                                ['key' => 'C', 'text' => 'I was surprised by how much I enjoyed working in a team.'],
                                ['key' => 'D', 'text' => 'I had to do more paperwork than I expected.'],
                                ['key' => 'E', 'text' => 'I felt nervous about speaking in front of a group.'],
                                ['key' => 'F', 'text' => 'I wish I had asked for more training.'],
                            ],
                            'items' => [
                                ['n' => 15, 'prompt' => 'Speaker 1', 'answer' => 'D'],
                                ['n' => 16, 'prompt' => 'Speaker 2', 'answer' => 'E'],
                                ['n' => 17, 'prompt' => 'Speaker 3', 'answer' => 'B'],
                                ['n' => 18, 'prompt' => 'Speaker 4', 'answer' => 'A'],
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
                        $track('l4', 'Talk'),
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'What can visitors do in each part of the Caravan Heritage Centre?',
                            'options' => [
                                ['key' => 'A', 'text' => 'Watch craftspeople at work.'],
                                ['key' => 'B', 'text' => 'Look at objects found by archaeologists.'],
                                ['key' => 'C', 'text' => 'Try dishes from different countries.'],
                                ['key' => 'D', 'text' => 'Enjoy a view over the desert.'],
                                ['key' => 'E', 'text' => 'Listen to live music.'],
                                ['key' => 'F', 'text' => 'Read letters written by travellers.'],
                                ['key' => 'G', 'text' => 'Dress up in historical clothes.'],
                                ['key' => 'H', 'text' => 'Buy gifts made by local artists.'],
                            ],
                            'items' => [
                                ['n' => 19, 'prompt' => 'The main courtyard', 'answer' => 'E'],
                                ['n' => 20, 'prompt' => 'The long hall', 'answer' => 'A'],
                                ['n' => 21, 'prompt' => 'The old stables', 'answer' => 'B'],
                                ['n' => 22, 'prompt' => 'The tower', 'answer' => 'D'],
                                ['n' => 23, 'prompt' => 'The cellar', 'answer' => 'F'],
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
                        $track('l5_1', 'Conversation 1'),
                        $track('l5_2', 'Conversation 2'),
                        $track('l5_3', 'Conversation 3'),
                    ],
                    'blocks' => [
                        ['type' => 'text', 'text' => '**Conversation 1**'],
                        $mcq(24, "What is Malika's main concern about the job offer?", ['The pay is not high enough.', 'She doubts she has the right experience.', 'She would have to leave her family.'], 'C'),
                        $mcq(25, 'What does Malika decide to do?', ['Ask to work from her current city.', 'Turn the offer down completely.', 'Accept the offer and move at once.'], 'A'),
                        ['type' => 'text', 'text' => '**Conversation 2**'],
                        $mcq(26, 'What went wrong with some of the water samples?', ['They were collected from the wrong places.', 'They were not stored properly.', 'They were damaged by heavy rain.'], 'B'),
                        $mcq(27, 'What does the tutor advise Dilnoza to do?', ['Have new samples tested at the university.', 'Ask the factory manager for permission again.', 'Test the old samples again more carefully.'], 'A'),
                        ['type' => 'text', 'text' => '**Conversation 3**'],
                        $mcq(28, 'Why does Nargiza choose the Saturday session?', ['She prefers a busier atmosphere.', 'She wants advice from an expert.', 'She cannot attend on Tuesday evenings.'], 'C'),
                        $mcq(29, 'What must Nargiza take with her on Saturday?', ['Some garden tools.', 'Suitable shoes.', 'A pair of gloves.'], 'B'),
                    ],
                ],
                [
                    'title' => 'Part 6',
                    'instructions' => "You will hear part of a lecture. You will hear the lecture twice.\nFor questions 30–35, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        $track('l6', 'Lecture'),
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'The Silk Road: myth and reality',
                            'max_words' => 1,
                            'text' => "- The name Silk Road was first used in [[30]].\n- Goods usually passed along a chain of [[31]].\n- Caravanserais were most commonly built about [[32]] kilometres apart.\n- Knowledge of papermaking reached [[33]] in the eighth century.\n- The Black Death probably spread along the routes in the [[34]] century.\n- Overland trade declined partly because [[35]] could carry goods more cheaply.",
                            'answers' => [
                                '30' => '1877',
                                '31' => 'middlemen|middleman',
                                '32' => '30|thirty',
                                '33' => 'Samarkand',
                                '34' => '14th|fourteenth|14',
                                '35' => 'ships|ship',
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
                            'title' => 'An email from the mountains',
                            'max_words' => 1,
                            'text' => "**Subject:** My week in the mountains\n\nHi Dilya,\n\nI am writing to you from the Ugam-Chatkal National Park, where I have been working as a volunteer [[1]] Monday. It is the first time I have ever [[2]] in a tent, and I have to admit that I did not sleep very well on the first night because the wind was so noisy!\n\nEvery morning we get [[3]] at six and have a quick breakfast. Then our group leader, Farhod, divides us into two teams. My team looks after the footpaths: we remove fallen branches and repair the small wooden bridges [[4]] cross the streams. The other team counts the birds and writes down which species they see, [[5]] is much harder than it sounds.\n\nThe scenery is absolutely amazing. Yesterday we walked to a lake high in the mountains, and the water was so clear that we could see the fish swimming near the bottom. I wish you [[6]] here with me!\n\nI will send you some photos when I get back. Write soon and tell me all your news.\n\nLove,\nNilufar",
                            'answers' => [
                                '1' => 'since',
                                '2' => 'slept|stayed|camped|lived',
                                '3' => 'up',
                                '4' => 'that|which',
                                '5' => 'which',
                                '6' => 'were|was',
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 2',
                    'instructions' => 'Read the texts (7–14) and the statements (**A–J**). Match each text with the correct statement. There are **two** extra statements which you do not need to use.',
                    'passage' => [
                        'title' => 'Notices and advertisements',
                        'text' => "[7] The east gallery of the History Museum will be closed until 3 November while workers repair the roof. The Ancient Coins collection has been moved to Room 4 and can be visited as usual, and the museum café remains open every day. We apologise for any inconvenience this may cause.\n\n[8] **Wanted:** assistant for a small graphic design studio in the city centre. No experience is necessary because full training is provided, but you must be willing to work some weekends. The studio is a five-minute walk from the metro station. Please send a short letter explaining why you would like the job to the address below.\n\n[9] Passengers for the 8.15 bus to Samarkand should note that, because of roadworks near the main station, buses will leave from Gate 3 instead of Gate 1 until further notice. Tickets that have already been bought remain valid, and staff will be available at both gates to help passengers find the right bus.\n\n[10] **Join our Sunday tree-planting group!** Every week we plant young trees along the canal in Yunusabad, and you will be working alongside people of all ages. No experience is needed, and tools and drinks are provided. Children under twelve are welcome, but they must be accompanied by an adult.\n\n[11] Please sort your rubbish into the correct containers: glass, paper or plastic. The bins are emptied every Tuesday morning, so please do not put anything out before Monday evening. Bags left outside the containers will not be collected, and repeated offenders may be fined.\n\n[12] **Learn how to write a CV that gets you noticed!** Our four-week online course is taught by experienced recruiters and includes a personal review of your own CV. Students receive a reduction of twenty per cent if they register before the end of the month.\n\n[13] Campfires are strictly forbidden in the park from June to September because of the danger of forest fires. Visitors who wish to cook should use the gas stoves provided at the campsite. Rangers patrol the area every day, and anyone who breaks this rule will be asked to leave.\n\n[14] **The Silk and Spice Festival returns to the old town this weekend!** Entry is free, and there will be live music, street food and craft stalls from morning until late evening. A small fee is charged for the cooking workshops, which take place in the main square.",
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'Which statement (A–J) matches each text?',
                            'options' => [
                                ['key' => 'A', 'text' => 'Travellers will have to go to a different place to catch their transport.'],
                                ['key' => 'B', 'text' => 'Someone is offering a room to rent.'],
                                ['key' => 'C', 'text' => 'Young people can take part if an older person is with them.'],
                                ['key' => 'D', 'text' => 'An event is free to visit, but some activities cost money.'],
                                ['key' => 'E', 'text' => 'Rubbish that is not put in the right place will not be taken away.'],
                                ['key' => 'F', 'text' => 'Some exhibits are in a different place because of building work.'],
                                ['key' => 'G', 'text' => 'People who are studying can pay less if they sign up early.'],
                                ['key' => 'H', 'text' => 'The winner of a competition will receive a prize.'],
                                ['key' => 'I', 'text' => 'A company will teach new employees the skills they need.'],
                                ['key' => 'J', 'text' => 'Lighting fires is not allowed during part of the year.'],
                            ],
                            'items' => [
                                ['n' => 7, 'prompt' => 'Text 7', 'answer' => 'F'],
                                ['n' => 8, 'prompt' => 'Text 8', 'answer' => 'I'],
                                ['n' => 9, 'prompt' => 'Text 9', 'answer' => 'A'],
                                ['n' => 10, 'prompt' => 'Text 10', 'answer' => 'C'],
                                ['n' => 11, 'prompt' => 'Text 11', 'answer' => 'E'],
                                ['n' => 12, 'prompt' => 'Text 12', 'answer' => 'G'],
                                ['n' => 13, 'prompt' => 'Text 13', 'answer' => 'J'],
                                ['n' => 14, 'prompt' => 'Text 14', 'answer' => 'D'],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 3',
                    'instructions' => 'Read the text. For questions 15–20, choose the correct heading for each paragraph from the list of headings (**I–VIII**). There are **two** extra headings which you do not need to use.',
                    'passage' => [
                        'title' => 'Forests in the City',
                        'text' => "[A] A century ago, city planners mostly thought of trees as decoration. A few were planted along wide avenues so that wealthy residents could stroll in the shade, but most of the available space was needed for houses, factories and roads. Today, many cities have changed their minds. From Tashkent to Toronto, officials now talk about urban forests and set targets for the number of trees they hope to plant over the next decade.\n\n[B] The first reason for this new interest is heat. Concrete and asphalt absorb sunlight and release it slowly at night, which makes city centres several degrees warmer than the surrounding countryside. Trees work like natural air conditioners: they provide shade, and they also cool the air as water evaporates from their leaves. A street lined with mature trees can be noticeably more comfortable in July than a bare one only a few blocks away. In hot cities this difference has real consequences, because fewer air-conditioning units are needed.\n\n[C] Air quality is another concern. Leaves catch dust and absorb some of the gases produced by traffic, and studies in several countries suggest that children who live in greener neighbourhoods have fewer breathing problems. Some cities have therefore planted dense lines of trees beside their busiest roads. However, researchers warn that trees cannot solve the problem alone. The most effective way to clean the air is still to reduce the number of polluting vehicles, and trees should be seen as a helpful addition rather than a replacement for cleaner transport.\n\n[D] Trees also affect how people feel. Psychologists have found that residents who can see greenery from their windows report lower levels of stress, and hospital patients sometimes recover faster in rooms with a view of a garden. Even a short walk under trees seems to lift people's mood. For this reason, some companies now build their offices around courtyards planted with trees, hoping that employees will be happier and more productive.\n\n[E] Planting a tree in a city is not as simple as it sounds, though. Roots need space and water, but underground pipes and cables often leave little room. Young trees are frequently damaged by traffic or careless visitors, and in some places up to half of them die within five years. Experts therefore say that looking after a tree once it has been planted matters as much as choosing where to put it. Some cities now plant trees in specially prepared soil pits that give the roots more room.\n\n[F] In some cities, ordinary residents are helping to solve this problem. Volunteers in neighbourhoods around the world join local groups that water young trees, remove litter from around their bases and report signs of disease to the city council, and some councils even provide free training and equipment. These groups also teach children how to care for trees, so that the next generation will feel responsible for them. The result, planners say, is not only healthier trees but stronger communities.",
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'List of headings',
                            'options' => [
                                ['key' => 'I', 'text' => 'Keeping the city cool'],
                                ['key' => 'II', 'text' => 'A new way of thinking about trees'],
                                ['key' => 'III', 'text' => 'Why care after planting is essential'],
                                ['key' => 'IV', 'text' => 'Species that survive in dry climates'],
                                ['key' => 'V', 'text' => 'Cleaner air, but not a complete solution'],
                                ['key' => 'VI', 'text' => 'The financial cost of planting'],
                                ['key' => 'VII', 'text' => 'Ordinary residents lend a hand'],
                                ['key' => 'VIII', 'text' => 'Good for the mind'],
                            ],
                            'items' => [
                                ['n' => 15, 'prompt' => 'Paragraph A', 'answer' => 'II'],
                                ['n' => 16, 'prompt' => 'Paragraph B', 'answer' => 'I'],
                                ['n' => 17, 'prompt' => 'Paragraph C', 'answer' => 'V'],
                                ['n' => 18, 'prompt' => 'Paragraph D', 'answer' => 'VIII'],
                                ['n' => 19, 'prompt' => 'Paragraph E', 'answer' => 'III'],
                                ['n' => 20, 'prompt' => 'Paragraph F', 'answer' => 'VII'],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Part 4',
                    'instructions' => "Read the text. For questions 21–24, choose the correct answer **A**, **B**, **C** or **D**.\nFor questions 25–29, decide if the statements agree with the information in the text. Choose **TRUE**, **FALSE** or **NOT GIVEN**.",
                    'passage' => [
                        'title' => 'The Inn That Came Back to Life',
                        'text' => "When the architect Shohida Nurmatova first visited the ruined caravanserai near her grandparents' village in 2015, she found sheep sheltering in the courtyard and a roof that had collapsed years earlier. Only the entrance arch and a few sections of wall were still standing, and most local people simply called the place 'the old barn'. Few of them realised that it had once offered a safe night's rest to merchants travelling along the Silk Road. \"I stood there and thought it would be a crime to let it disappear,\" she remembers.\n\nHer first step was to approach the regional government. Officials agreed that the building was historically valuable, but they explained that there was no money available for restoration. Instead of giving up, Shohida decided to ask the villagers themselves. She invited them to a meeting in the local tea house, expecting only a handful of people. About thirty came, most of them out of curiosity. To her surprise, many of the older residents had played in the ruins as children, and they described in detail what the building had looked like before it fell apart. These memories later helped her to draw up accurate plans. She also collected old photographs from families in the area, and one of them showed the arched gateway with its original blue tiles.\n\nThe work began in 2017 with a small group of volunteers who cleared away rubbish and sorted thousands of old bricks by hand, because there was no budget for machinery. On Saturdays up to forty people worked together, and between shifts the women of the village brought tea and bread for everyone. Shohida insisted that everyone should be trained first. Each newcomer spent a day with Mr Abdullaev, a retired builder, who taught them to lay bricks using lime mortar rather than cement. Cement, he explained, is much harder than old clay bricks, and over time it makes them crack and crumble. Lime is softer, so the walls can move slightly with the changing seasons without being damaged.\n\nNot everyone welcomed the project. Some villagers argued that donations should be spent on repairing the school and the road, not on a pile of old bricks. Shohida listened patiently and agreed that these things were important, but she was convinced that the caravanserai could bring visitors and income to the village. To prove it, she organised an open day in 2018. More than eight hundred people came, and local families who sold tea, bread and handmade crafts earned more in one day than they normally did in a month. Most of the opposition disappeared after that.\n\nThe project has changed the village in other ways too. The tea house, once half empty, now serves lunch to groups of visitors every weekend, and two young residents have started small businesses making souvenirs. Shohida herself no longer lives in the capital. Three years ago she moved into her grandparents' old house to be closer to the site.\n\nProgress has nevertheless been slower than the team hoped. The north wing was finished in 2023 and now contains a small museum and a workshop where schoolchildren try traditional crafts, but the roof over the main hall is still missing. The team has raised roughly half of the money it needs, mainly through small donations, and the remaining work requires professional carpenters, whom it cannot yet afford to hire. Around a hundred and twenty people have volunteered at some point, from university students to retired teachers.\n\nShohida is realistic about the challenges ahead, but she is not discouraged. \"People assume that volunteers give their time for nothing,\" she says. \"In fact, they receive something much more valuable, which is the knowledge that they helped to build something that will still be standing in five hundred years.\" Nobody expects the work to be finished before the end of the decade. Her dream is that one day a traveller will sleep in the restored rooms again, exactly as travellers did centuries ago.",
                    ],
                    'blocks' => [
                        $mcq(21, 'Why did Shohida ask the villagers for help?', ['The government did not think the building was important.', 'The government could not afford to restore the building.', 'The villagers had asked her to lead the project.', 'The villagers had offered to pay for the work.'], 'B'),
                        $mcq(22, 'According to Mr Abdullaev, why is lime better than cement for this building?', ['It is cheaper to buy.', 'It is easier for volunteers to use.', 'It does not damage the old bricks.', 'It makes the walls look newer.'], 'C'),
                        $mcq(23, 'How did Shohida change the minds of villagers who opposed the project?', ['She showed the site could earn money locally.', 'She used the donations to repair the school.', 'She promised to give every villager a job.', 'She asked the regional government to support the plan.'], 'A'),
                        $mcq(24, 'What does Shohida believe volunteers receive from the project?', ['Training for a new career.', 'A small payment for their time.', 'Recognition from the government.', 'Pride in building something lasting.'], 'D'),
                        ['type' => 'text', 'text' => '**Questions 25–29.** Do the following statements agree with the information in the text?'],
                        $tfng(25, 'The caravanserai was in good condition when Shohida first saw it.', 'FALSE'),
                        $tfng(26, 'The restoration work started before 2018.', 'TRUE'),
                        $tfng(27, 'Mr Abdullaev has trained builders in other villages.', 'NOT GIVEN'),
                        $tfng(28, 'The team has already collected all the money it needs.', 'FALSE'),
                        $tfng(29, 'Some of the volunteers are retired teachers.', 'TRUE'),
                    ],
                ],
                [
                    'title' => 'Part 5',
                    'instructions' => "Read the text. For questions 30–33, complete the summary. Write **ONE WORD** from the text for each answer.\nFor questions 34–35, choose the correct answer **A**, **B**, **C** or **D**.",
                    'passage' => [
                        'title' => 'Beyond the Career Ladder',
                        'text' => "For most of the twentieth century, the ideal career had a simple shape. A young person joined a company, learnt the job and climbed a ladder of promotions until retirement. Loyalty was rewarded with security, and changing employers was widely seen as a sign of restlessness or failure. For many families, a job at a single factory or office shaped where they lived and how they planned their lives. Today that picture looks increasingly out of date. Economists estimate that someone entering the workforce now will change jobs many times, and may change professions entirely more than once.\n\nSeveral forces are behind this shift. Technology is the most obvious. Tasks that once required a room full of clerks can now be completed by software in seconds, and entire occupations have disappeared within a single generation. At the same time, new roles appear that nobody could have predicted twenty years ago, such as app developers or online safety consultants. Companies, too, are less willing to promise lifelong employment, because markets change faster than they can plan. Globalisation has also played its part, since work can now be moved across borders at the click of a mouse. As a result, the sense that a job is a permanent home has given way to the idea that it is one stage on a longer journey.\n\nOne response has been the rise of the so-called 'portfolio career', in which a person combines several part-time jobs or projects instead of committing to a single employer. A translator might, for example, teach evening classes and run a small online business as well. Supporters argue that this pattern spreads risk: if one source of income dries up, the others continue. It also encourages people to develop a wider range of skills. Critics, however, point out that portfolio workers often face irregular earnings, no paid holidays and little protection if they fall ill, and they suggest that the model works best for those who can afford to take chances.\n\nResearchers who study workplace psychology have also begun to question the assumption that promotion is the best measure of success. In one widely discussed approach, known as 'job crafting', employees make small changes to their own roles, taking on tasks that suit their strengths or reshaping the way they work with colleagues. Studies suggest that people who craft their jobs in this way report greater satisfaction, even when their title and salary stay the same. The findings imply that motivation depends less on climbing higher than on having a sense of control over one's daily work.\n\nEmployers are gradually adapting to these ideas. Some now allow staff to move between departments every few years, and others offer sabbaticals, long periods of leave that workers can use to study or travel. Managers who introduced such schemes report that fewer talented employees leave the company, which suggests that flexibility can benefit organisations as well as individuals.\n\nNone of this means that traditional careers have vanished. In fields such as medicine, law and engineering, long periods of training and formal qualifications still make a linear path the most practical one. Moreover, the freedom of a flexible career is not equally available to everyone. Those with savings, useful contacts or highly sought-after skills can move between jobs with confidence, whereas others may feel that they have little real choice. Career advisers therefore stress that flexibility is an advantage only when it is chosen rather than forced.\n\nWhat should young people do, then? Most experts recommend building skills that transfer between occupations, such as communication, problem-solving and the ability to learn quickly, as almost everyone will need to keep learning throughout their working lives. They also advise developing a network of contacts beyond one's own workplace, since opportunities often arrive through people rather than through advertisements. Above all, they suggest treating a career not as a fixed ladder but as something closer to a climbing frame, offering many routes upwards and many places to stop and enjoy the view.",
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Summary',
                            'max_words' => 1,
                            'text' => 'In the past, [[30]] to one company was rewarded with job security, and people expected to move steadily upwards. Today, technology has meant that some occupations have [[31]] altogether, and many people build a portfolio career, which is said to spread [[32]] because income comes from several sources. Research into job crafting suggests that satisfaction depends more on a sense of [[33]] over daily work than on promotion.',
                            'answers' => [
                                '30' => 'loyalty',
                                '31' => 'disappeared',
                                '32' => 'risk',
                                '33' => 'control',
                            ],
                        ],
                        $mcq(34, 'Why does the writer mention medicine, law and engineering?', ['To explain why portfolio careers are unpopular with young workers.', 'To argue that formal qualifications are no longer important today.', 'To show that a traditional path still makes sense in some fields.', 'To suggest that these professions are better paid than most others.'], 'C'),
                        $mcq(35, "What is the writer's overall view of the changes described in the text?", ['Flexible careers are clearly better for everyone.', 'The changes bring advantages but also real limitations.', 'Young people will struggle to find any work.', 'Career patterns have hardly changed at all.'], 'B'),
                    ],
                ],
            ],
        ],

        'writing' => [
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => 'Read the text and complete Task 1.1 and Task 1.2. You are advised to spend about 25 minutes on Part 1.',
                    'context' => "You are a volunteer at a community centre in your town. You have received this email from the centre's director.\n\n*Dear volunteer,*\n\n*Thank you for all your hard work this year. Next spring we plan to open a \"repair café\" at the centre. Local people will be able to bring broken items, such as bicycles, clothes and electrical goods, and volunteers will help to fix them for free. We would like the café to open every Saturday from 10.00 until 14.00.*\n\n*Before we make our final plans, we would like to hear your views. Please tell us whether you can help, and suggest how we could tell local people about the project.*\n\n*Best wishes,*\n*Gulnora Ahmedova, Centre Director*",
                    'tasks' => [
                        [
                            'id' => '1.1',
                            'title' => 'Task 1.1',
                            'prompt' => 'Write a letter to your friend, who is also a volunteer at the centre. Tell your friend about the email and say what you think about the idea.' . "\n\n" . 'Write about **50** words.',
                            'min_words' => 50,
                            'max_words' => 0,
                        ],
                        [
                            'id' => '1.2',
                            'title' => 'Task 1.2',
                            'prompt' => 'Write a letter to the centre director. Say whether you can help, explain what you could do and suggest how to tell local people about the project.' . "\n\n" . 'Write **120–150** words.',
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
                            'prompt' => "You have seen this post on an online forum about culture and travel:\n\n*Some people believe that museums and historic sites should be free for everyone. Others think that visitors should pay to enter, so that the money can be used to look after these places. What do you think?*\n\nWrite a post for the forum discussing both views and giving your own opinion. Write **180–200** words.",
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
                        ['no' => 1, 'text' => 'Tell me about your job or your studies. What do you like most about it?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq1'],
                        ['no' => 2, 'text' => 'Do you like spending time outdoors? What do you enjoy doing in nature?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq2'],
                        ['no' => 3, 'text' => 'Tell me about an interesting historical place that you have visited, or would like to visit.', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq3'],
                    ],
                ],
                [
                    'id' => '1.2',
                    'title' => 'Part 1.2',
                    'instructions' => 'Look at the two pictures and answer the questions.',
                    'images' => ['@image:s12_1', '@image:s12_2'],
                    'questions' => [
                        ['no' => 4, 'text' => 'Look at the two pictures. Compare them, and say what the advantages and disadvantages of working in each place are.', 'prep_sec' => 0, 'answer_sec' => 45, 'audio' => '@audio:sq4'],
                        ['no' => 5, 'text' => 'Which of these two ways of working would you prefer? Why?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq5'],
                        ['no' => 6, 'text' => 'Do you think more people will work from home in the future? Why, or why not?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq6'],
                    ],
                ],
                [
                    'id' => '2',
                    'title' => 'Part 2',
                    'instructions' => 'Look at the picture and answer the questions. You have one minute to prepare and two minutes to speak.',
                    'images' => ['@image:s2'],
                    'questions' => [
                        ['no' => 7, 'text' => "Look at the picture and describe what the people are doing. Then tell me about a time when you helped other people or your community.\n- What did you do?\n- Who did you do it with?\n- How did you feel afterwards?", 'prep_sec' => 60, 'answer_sec' => 120, 'audio' => '@audio:sq7'],
                    ],
                ],
                [
                    'id' => '3',
                    'title' => 'Part 3',
                    'instructions' => 'Read the statement and the arguments for and against it. You have one minute to prepare and two minutes to speak.',
                    'topic' => 'Cities should ban private cars from the centre.',
                    'for' => [
                        'It would reduce air pollution and noise.',
                        'Streets would be safer for pedestrians and cyclists.',
                        'Public spaces could be used for parks, markets and cafés.',
                    ],
                    'against' => [
                        'Elderly and disabled people may find it hard to get around.',
                        'Shops and businesses in the centre may lose customers.',
                        'Public transport must be good enough first, and that is expensive.',
                    ],
                    'questions' => [
                        ['no' => 8, 'text' => 'Discuss both sides of the argument and give your own opinion. Use at least two points from each list.', 'prep_sec' => 60, 'answer_sec' => 120, 'audio' => '@audio:sq8'],
                    ],
                ],
            ],
        ],
    ],
];

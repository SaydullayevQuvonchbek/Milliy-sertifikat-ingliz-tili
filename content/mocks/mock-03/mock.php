<?php

// Multilevel Mock 3 — to'liq rasmiy format (L 6 qism/35, R 5 qism/35, W 3 topshiriq, S 8 savol).
// Mavzular: fan va kashfiyotlar, oziq-ovqat va turmush tarzi, sport va salomatlik, media va aloqa.
// Barcha matnlar original. Audio matnlari audio.json da (transkriptlar undan olinadi).

declare(strict_types=1);

require_once __DIR__ . '/../../lib.php';

$audio = content_audio(__DIR__);

$mcq = static fn (int $n, string $prompt, array $options, string $answer): array => [
    'type' => 'mcq', 'n' => $n, 'prompt' => $prompt, 'options' => $options, 'answer' => $answer,
];
$tfng = static fn (int $n, string $prompt, string $answer): array => [
    'type' => 'tfng', 'n' => $n, 'prompt' => $prompt, 'answer' => $answer,
];
$track = static fn (array $audio, string $key, string $label): array => [
    'asset' => '@audio:' . $key, 'label' => $label, 'transcript' => content_transcript($audio, $key),
];

return [
    'title' => 'Multilevel Mock 3',
    'description' => "To'liq rasmiy formatdagi mock: Listening, Reading, Writing va Speaking. Barcha matnlar original, audio TTS yordamida yozilgan, Speaking uchun maxsus rasmlar tayyorlangan (fan, oziq-ovqat, sport va media mavzulari).",
    'assets' => [
        'audio' => array_keys($audio['tracks']),
        'image' => [
            's12_1' => 'Picture 1: A busy open-air bazaar',
            's12_2' => 'Picture 2: A modern supermarket',
            's2' => 'People taking part in a fun run',
        ],
    ],
    'source' => [
        'listening' => [
            'review_sec' => 120,
            'parts' => [
                // ---------------------------------------------------------------- Part 1
                [
                    'title' => 'Part 1',
                    'instructions' => "You will hear eight short conversations. You will hear each conversation twice.\nFor questions 1–8, choose the correct answer **A**, **B** or **C**.",
                    'preview_sec' => 20,
                    'gap_sec' => 4,
                    'plays' => 2,
                    'tracks' => [
                        $track($audio, 'l1_1', 'Conversation 1'),
                        $track($audio, 'l1_2', 'Conversation 2'),
                        $track($audio, 'l1_3', 'Conversation 3'),
                        $track($audio, 'l1_4', 'Conversation 4'),
                        $track($audio, 'l1_5', 'Conversation 5'),
                        $track($audio, 'l1_6', 'Conversation 6'),
                        $track($audio, 'l1_7', 'Conversation 7'),
                        $track($audio, 'l1_8', 'Conversation 8'),
                    ],
                    'blocks' => [
                        $mcq(1, 'What price does the woman pay for a kilo of dried apricots?', ["50,000 so'm", "55,000 so'm", "60,000 so'm"], 'B'),
                        $mcq(2, 'What time will the friends meet on Saturday?', ['At 10:00.', 'At 10:30.', 'At 11:00.'], 'A'),
                        $mcq(3, "Why can't the man play football on Sunday?", ['He has to work.', 'He is going away.', 'He has hurt his ankle.'], 'C'),
                        $mcq(4, 'Why is the woman phoning the radio station?', ['To take part in a programme.', 'To complain about a change.', 'To ask for a favourite song.'], 'A'),
                        $mcq(5, 'What will the man go out to buy?', ['Some lamb.', 'Some cooking oil.', 'Some cumin.'], 'C'),
                        $mcq(6, "When is the woman's new appointment?", ['On Tuesday at 9:00.', 'On Thursday at 8:30.', 'On Wednesday at 10:00.'], 'B'),
                        $mcq(7, 'How does the man usually follow the news?', ['By reading on his phone.', 'By listening to a podcast.', 'By watching television.'], 'B'),
                        $mcq(8, "What will Kamila's science project be about?", ['Paper aeroplanes.', 'A model volcano.', 'Plants and light.'], 'A'),
                    ],
                ],
                // ---------------------------------------------------------------- Part 2
                [
                    'title' => 'Part 2',
                    'instructions' => "You will hear a talk. You will hear the talk twice.\nFor questions 9–14, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        $track($audio, 'l2', 'Talk'),
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Discovery Science Centre: visitor information',
                            'max_words' => 1,
                            'text' => "- The centre is closed on [[9]].\n- Children under [[10]] years old go in free.\n- The space show in the dome lasts [[11]] minutes.\n- Seats for the show must be booked at the [[12]] desk.\n- The Robot Lab is on floor [[13]].\n- Large bags should be left in the [[14]] near the entrance.",
                            'answers' => [
                                '9' => 'Monday|Mondays',
                                '10' => '6|six',
                                '11' => '25|twenty-five|twenty five',
                                '12' => 'information',
                                '13' => '3|three|third|3rd',
                                '14' => 'lockers|locker',
                            ],
                        ],
                    ],
                ],
                // ---------------------------------------------------------------- Part 3
                [
                    'title' => 'Part 3',
                    'instructions' => "You will hear four short recordings. You will hear the recordings twice.\nFor questions 15–18, choose from the list (**A–F**) what each speaker says. Use each letter only once. There are **two** extra letters which you do not need to use.",
                    'preview_sec' => 30,
                    'gap_sec' => 4,
                    'plays' => 2,
                    'tracks' => [
                        $track($audio, 'l3_1', 'Speaker 1'),
                        $track($audio, 'l3_2', 'Speaker 2'),
                        $track($audio, 'l3_3', 'Speaker 3'),
                        $track($audio, 'l3_4', 'Speaker 4'),
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'Why did each speaker start to exercise?',
                            'options' => [
                                ['key' => 'A', 'text' => 'My doctor warned me about my health.'],
                                ['key' => 'B', 'text' => 'I wanted to spend more time with my family.'],
                                ['key' => 'C', 'text' => 'I wanted to meet new people.'],
                                ['key' => 'D', 'text' => 'I accepted a challenge from a colleague.'],
                                ['key' => 'E', 'text' => 'I was looking for a way to deal with pressure at work.'],
                                ['key' => 'F', 'text' => 'I received a fitness watch as a present.'],
                            ],
                            'items' => [
                                ['n' => 15, 'prompt' => 'Speaker 1', 'answer' => 'E'],
                                ['n' => 16, 'prompt' => 'Speaker 2', 'answer' => 'C'],
                                ['n' => 17, 'prompt' => 'Speaker 3', 'answer' => 'D'],
                                ['n' => 18, 'prompt' => 'Speaker 4', 'answer' => 'A'],
                            ],
                        ],
                    ],
                ],
                // ---------------------------------------------------------------- Part 4
                [
                    'title' => 'Part 4',
                    'instructions' => "You will hear a talk. You will hear the talk twice.\nFor questions 19–23, choose the correct option (**A–H**) for each item. There are **three** extra options which you do not need to use.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        $track($audio, 'l4', 'Talk'),
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'What happens in each part of the radio station?',
                            'options' => [
                                ['key' => 'A', 'text' => 'Guests wait here before going on air.'],
                                ['key' => 'B', 'text' => 'Old recordings are kept here.'],
                                ['key' => 'C', 'text' => 'The news is read live from here.'],
                                ['key' => 'D', 'text' => 'Ideas for new programmes are discussed here.'],
                                ['key' => 'E', 'text' => 'Visitors can try presenting a programme here.'],
                                ['key' => 'F', 'text' => 'Adverts are recorded here.'],
                                ['key' => 'G', 'text' => 'Programmes are edited and mixed here.'],
                                ['key' => 'H', 'text' => 'Staff have their meals here.'],
                            ],
                            'items' => [
                                ['n' => 19, 'prompt' => 'Reception', 'answer' => 'A'],
                                ['n' => 20, 'prompt' => 'Studio One', 'answer' => 'C'],
                                ['n' => 21, 'prompt' => 'Studio Two', 'answer' => 'E'],
                                ['n' => 22, 'prompt' => 'The archive', 'answer' => 'B'],
                                ['n' => 23, 'prompt' => 'The control room', 'answer' => 'G'],
                            ],
                        ],
                    ],
                ],
                // ---------------------------------------------------------------- Part 5
                [
                    'title' => 'Part 5',
                    'instructions' => "You will hear three conversations. You will hear each conversation twice.\nFor questions 24–29, choose the correct answer **A**, **B** or **C**.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        $track($audio, 'l5_1', 'Conversation 1'),
                        $track($audio, 'l5_2', 'Conversation 2'),
                        $track($audio, 'l5_3', 'Conversation 3'),
                    ],
                    'blocks' => [
                        ['type' => 'text', 'text' => '**Conversation 1**'],
                        $mcq(24, 'What has made Dilnoza believe that her idea will succeed?', ['Her colleagues liked the samsa she took to the office.', 'A professional cook praised her recipe.', 'An online survey showed strong interest.'], 'A'),
                        $mcq(25, "What is Rustam's main worry?", ['The ingredients may be too expensive.', 'The samsa may not stay fresh after freezing.', 'Dilnoza may not have enough free time.'], 'C'),
                        ['type' => 'text', 'text' => '**Conversation 2**'],
                        $mcq(26, "What does the coach think is the main cause of Malika's knee pain?", ['Her running shoes are too old.', 'She has not had enough rest.', 'The road surface is too hard.'], 'B'),
                        $mcq(27, 'What does the coach advise Malika to do for the next two weeks?', ['Do some swimming or cycling instead of some runs.', 'Stop training completely.', 'Run shorter distances every day.'], 'A'),
                        ['type' => 'text', 'text' => '**Conversation 3**'],
                        $mcq(28, 'Why is Sanjar doubtful about an online-only magazine?', ['It would cost more than the printed one.', 'Not many students have internet access.', 'He thinks students would not read the articles properly.'], 'C'),
                        $mcq(29, 'What do the speakers decide to suggest to the editor?', ['Putting everything online and printing nothing.', 'Printing a smaller edition and publishing the rest online.', 'Making video versions of the best articles.'], 'B'),
                    ],
                ],
                // ---------------------------------------------------------------- Part 6
                [
                    'title' => 'Part 6',
                    'instructions' => "You will hear part of a lecture. You will hear the lecture twice.\nFor questions 30–35, complete the notes. Write **ONE WORD and/or A NUMBER** for each answer.",
                    'preview_sec' => 30,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [
                        $track($audio, 'l6', 'Lecture'),
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'How we experience flavour',
                            'max_words' => 1,
                            'text' => "- An adult may have up to [[30]] taste buds.\n- Umami was identified in [[31]] by Kikunae Ikeda.\n- Ikeda was studying a soup that contained [[32]].\n- Aromas travel from the mouth to the [[33]].\n- Some researchers say that smell provides up to [[34]] per cent of flavour.\n- In an experiment, a dessert seemed sweeter on a round [[35]] plate.",
                            'answers' => [
                                '30' => '10,000|10000|10 000|ten thousand',
                                '31' => '1908|nineteen oh eight|nineteen zero eight|nineteen o eight',
                                '32' => 'seaweed',
                                '33' => 'nose',
                                '34' => '80|eighty',
                                '35' => 'white',
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'reading' => [
            'parts' => [
                // ---------------------------------------------------------------- Part 1
                [
                    'title' => 'Part 1',
                    'instructions' => 'Read the text. For questions 1–6, fill in each gap with **ONE** word.',
                    'passage' => null,
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Saturdays at the Bazaar',
                            'max_words' => 1,
                            'text' => <<<'TXT'
Every Saturday, my grandmother and I go to the bazaar. We leave home early, [[1]] the best fruit and vegetables are usually sold before nine o'clock. My grandmother knows most of the sellers, and they always give her a friendly greeting [[2]] we walk past.

The first thing we buy is bread, still warm from the oven. After that, we visit the vegetable stalls, where she checks every tomato carefully. "You can tell if it is fresh by its smell," she says. I used to think this was boring, but now I enjoy listening [[3]] her advice. For example, I can choose a good melon just by pressing it gently and listening to the sound it makes.

Last week, we bought so much food that I could hardly carry the bags. Luckily, a young man [[4]] was going the same way offered to carry two of them. When we got home, my grandmother cooked a big plov [[5]] the whole family. It was delicious, and everyone asked for a second plate. I think the bazaar is much more interesting [[6]] a supermarket, because you can talk to real people and try things before you buy them.
TXT,
                            'answers' => [
                                '1' => 'because|as|since|for',
                                '2' => 'when|as|whenever|while',
                                '3' => 'to',
                                '4' => 'who|that',
                                '5' => 'for',
                                '6' => 'than',
                            ],
                        ],
                    ],
                ],
                // ---------------------------------------------------------------- Part 2
                [
                    'title' => 'Part 2',
                    'instructions' => 'Read the texts (7–14) and the statements (**A–J**). Match each text with the correct statement. There are **two** extra statements which you do not need to use.',
                    'passage' => [
                        'title' => 'Notices and messages',
                        'text' => <<<'TXT'
[7] **Green Basket Market.** Because our refrigerators are being repaired, the market will close at 18:00 instead of 21:00 from Monday to Wednesday next week. From Thursday we will close at the usual time again. We are sorry for the inconvenience and thank you for your understanding.

[8] **Free blood pressure checks.** On the first Monday of every month, the City Health Centre invites everyone over the age of 40 to have their blood pressure measured between 10:00 and 12:00. No appointment is needed, and there is nothing to pay. Please bring your medical card if you have one.

[9] **Stargazing evening.** The Amateur Astronomy Club invites the public to look at Saturn and the Moon through its telescopes at the university observatory on Friday from 21:00. No experience is necessary, and warm clothes are recommended. If the sky is cloudy, the evening will take place on the following Friday instead.

[10] **Important notice.** Some of our customers have received an email that looks as if it comes from this bank. It asks for card numbers and passwords. We never ask for such information by email. Please do not reply or click on any link. Delete the message and call our helpline.

[11] **Tashkent Wolves** amateur football club needs a goalkeeper for the new season. We train on Tuesday and Thursday evenings and play matches on Sunday mornings. Age and experience are not important; a positive attitude is. Speak to Coach Sherzod after training or send a message to the club page.

[12] **Cook with Aziza!** Learn to make plov, manti, lagman and samsa in this four-week course, which starts on 3 October. All ingredients and a recipe booklet are included in the price. Reserve your place before 15 September and the whole course will cost 10 per cent less.

[13] Hi Diana, I tried to phone you, but your mobile seems to be switched off. Sorry, I gave you the wrong time yesterday. The bus to the airport leaves at 6:15, not 6:45, so please be at the station by 6:00. Text me when you get this. Safe travels! Olim

[14] **Your memories wanted!** Radio Sunrise is preparing a new programme called "My Neighbourhood". If you have an old photograph, a family recipe or a story about the place where you grew up, send it to us by email. The best contributions will be shown on our website and discussed on air.
TXT,
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'Which statement (A–J) matches each text?',
                            'options' => [
                                ['key' => 'A', 'text' => 'An event might be moved to another date because of the weather.'],
                                ['key' => 'B', 'text' => 'People who reserve a place early will pay less.'],
                                ['key' => 'C', 'text' => 'The writer is correcting information given earlier.'],
                                ['key' => 'D', 'text' => 'A health check is offered at no cost to a particular group of people.'],
                                ['key' => 'E', 'text' => 'Members of the public are invited to send in personal contributions.'],
                                ['key' => 'F', 'text' => 'A group is looking for a new member.'],
                                ['key' => 'G', 'text' => 'A business will keep shorter hours for a few days.'],
                                ['key' => 'H', 'text' => 'Readers are warned about a dishonest message.'],
                                ['key' => 'I', 'text' => 'Users must update their software before a deadline.'],
                                ['key' => 'J', 'text' => 'Beginners can attend the first lesson without paying.'],
                            ],
                            'items' => [
                                ['n' => 7, 'prompt' => 'Text 7', 'answer' => 'G'],
                                ['n' => 8, 'prompt' => 'Text 8', 'answer' => 'D'],
                                ['n' => 9, 'prompt' => 'Text 9', 'answer' => 'A'],
                                ['n' => 10, 'prompt' => 'Text 10', 'answer' => 'H'],
                                ['n' => 11, 'prompt' => 'Text 11', 'answer' => 'F'],
                                ['n' => 12, 'prompt' => 'Text 12', 'answer' => 'B'],
                                ['n' => 13, 'prompt' => 'Text 13', 'answer' => 'C'],
                                ['n' => 14, 'prompt' => 'Text 14', 'answer' => 'E'],
                            ],
                        ],
                    ],
                ],
                // ---------------------------------------------------------------- Part 3
                [
                    'title' => 'Part 3',
                    'instructions' => 'Read the text. For questions 15–20, choose the correct heading for each paragraph from the list of headings (**I–VIII**). There are **two** extra headings which you do not need to use.',
                    'passage' => [
                        'title' => 'Why Podcasts Are Everywhere',
                        'text' => <<<'TXT'
[A] Ten years ago, few people outside the radio industry had even heard the word "podcast". Today, there are millions of programmes on subjects ranging from ancient history to home gardening, and in some countries more than a third of adults listen to at least one every month. Many of the early shows were recorded in bedrooms and cupboards by people who simply wanted to share an interest. What began as a hobby for a small group of technology enthusiasts has grown into a global business with its own stars, prizes and advertisers, and it shows no sign of slowing down.

[B] One reason for this success is convenience. Unlike a live radio programme, a podcast can be downloaded and played whenever the listener likes: on the bus, while cooking, or during an evening run. Because it needs ears but not eyes, it fits into moments when reading or watching a screen would be impossible. For busy people, the chance to turn a dull journey into an opportunity to learn something new is a powerful attraction.

[C] Equally important is the sense of closeness that a voice can create. Many listeners describe their favourite presenters as friends, even though they have never met them. Researchers who study the media suggest that hearing someone speak in a relaxed, natural way builds trust more quickly than reading the same words. A presenter who laughs, hesitates or sighs seems more real than a polished newsreader, and this feeling of honesty is exactly what audiences value.

[D] Another factor is how cheap it is to begin. A basic podcast needs little more than a microphone, a laptop and free editing software, and the finished programme can be uploaded within hours. This has opened the door for people who would never have been offered a radio show, such as teachers, farmers, nurses and students, to reach listeners in other countries. As a result, there is a variety of topics and voices that traditional broadcasters could never offer.

[E] However, the same freedom brings problems. With no editors to check the facts, false information can spread quickly, and it is often hard for listeners to tell an expert from someone who simply speaks with confidence. Quality also varies enormously: for every well-researched series there are dozens of badly recorded ones, abandoned after a few episodes. Finding programmes worth hearing can be harder than downloading them.

[F] Looking ahead, the industry is likely to change in several ways. Advertisers are already paying more for shows with loyal audiences, and some producers are experimenting with paid subscriptions instead. Schools and universities, meanwhile, are starting to use podcasts as teaching tools, especially for language learning, because students can listen to the same conversation many times. Some experts also expect programmes to be translated automatically into other languages, which would give small producers a much larger audience. Whatever happens, people's appetite for listening to stories does not seem likely to disappear.
TXT,
                    ],
                    'blocks' => [
                        [
                            'type' => 'match',
                            'title' => 'List of headings',
                            'options' => [
                                ['key' => 'I', 'text' => 'Making programmes at very low cost'],
                                ['key' => 'II', 'text' => 'A voice that feels like a friend'],
                                ['key' => 'III', 'text' => 'Listening whenever and wherever you like'],
                                ['key' => 'IV', 'text' => 'Problems that come with freedom'],
                                ['key' => 'V', 'text' => 'An industry that is still changing'],
                                ['key' => 'VI', 'text' => 'Why radio is disappearing'],
                                ['key' => 'VII', 'text' => 'A surprising rise from a small hobby'],
                                ['key' => 'VIII', 'text' => 'Advice for new presenters'],
                            ],
                            'items' => [
                                ['n' => 15, 'prompt' => 'Paragraph A', 'answer' => 'VII'],
                                ['n' => 16, 'prompt' => 'Paragraph B', 'answer' => 'III'],
                                ['n' => 17, 'prompt' => 'Paragraph C', 'answer' => 'II'],
                                ['n' => 18, 'prompt' => 'Paragraph D', 'answer' => 'I'],
                                ['n' => 19, 'prompt' => 'Paragraph E', 'answer' => 'IV'],
                                ['n' => 20, 'prompt' => 'Paragraph F', 'answer' => 'V'],
                            ],
                        ],
                    ],
                ],
                // ---------------------------------------------------------------- Part 4
                [
                    'title' => 'Part 4',
                    'instructions' => "Read the text. For questions 21–24, choose the correct answer **A**, **B**, **C** or **D**.\nFor questions 25–29, decide if the statements agree with the information in the text. Choose **TRUE**, **FALSE** or **NOT GIVEN**.",
                    'passage' => [
                        'title' => 'Sleeping Like a Champion',
                        'text' => <<<'TXT'
When people imagine what makes a champion, they usually picture hours in the gym, strict diets and demanding coaches. Yet in recent years, a growing number of sports clubs have begun to pay attention to something much simpler and far cheaper: sleep. Several professional football and basketball teams now employ sleep specialists, who work alongside the physiotherapists and nutritionists, and players receive advice about their bedrooms as well as their training.

The scientific reasoning is straightforward. During sleep, the body repairs damaged muscles and releases hormones that support growth, while the brain organises what it has learned during the day. Researchers who followed a group of university swimmers for a season found that when the swimmers slept an extra hour and a half each night, their reaction times at the start of a race improved noticeably, and they felt less tired during training. The swimmers themselves were surprised. "I had always believed that a champion is simply someone who trains harder than everyone else," one of them said. "It never occurred to me that I could improve just by going to bed earlier."

Not everyone finds it easy to sleep well before a big event. Nervousness the night before a competition is very common, and even experienced athletes admit to lying awake for hours. Many teams therefore teach relaxation techniques, such as slow breathing, and ask players to put their phones away an hour before bedtime, because bright screens can make it harder to fall asleep. Travel is another difficulty. A team that flies across several time zones may lose its normal sleeping pattern for days, and a rule of thumb used by some coaches is one day of adjustment for every time zone crossed.

Sleep has been valued in some sports for a long time, but often for reasons that have nothing to do with science. A long rest in the middle of the day is common among athletes in warm countries, and many older coaches believe in a short nap before an evening match. What has changed is that these habits are now measured. Some clubs give players small wristbands that record how long they sleep and how often they wake, and the coaching staff study the results. Not all players like this. A few complain that they feel watched, and one coach admitted that a player stopped wearing his wristband because the figures made him anxious about his sleep, which, ironically, made him sleep worse.

Individual needs also differ. Sleep specialists say that most adults need between seven and nine hours a night, but young athletes in intensive training may need more, and some people naturally function well on less. For this reason, many clubs now prefer to measure each player separately rather than apply a single rule to the whole team.

Critics point out that the research still has limits. Most of the studies involve small groups, often of students, and it is not clear whether their results apply equally to older professionals. It is also difficult to separate the effect of sleep from other changes. An athlete who sleeps more may also eat better and train more regularly, simply because he or she feels healthier. For these reasons, most scientists describe sleep as one important factor among many rather than a magic solution.

For ordinary people who exercise for pleasure, the message is nevertheless encouraging. You do not need expensive equipment to benefit. Experts suggest keeping regular bedtimes, keeping the bedroom cool and dark, and avoiding heavy meals late in the evening. If a busy schedule forces you to choose between an early run and an extra hour in bed, the second option may sometimes do you more good. Even a small change, such as switching off the television half an hour earlier, can make a difference.
TXT,
                    ],
                    'blocks' => [
                        $mcq(21, 'What is new about the way some sports clubs prepare their players?', ['They pay attention to how well players sleep.', 'They employ more coaches than before.', 'They have replaced physiotherapists with sleep specialists.', 'They spend less time on diets and gym work.'], 'A'),
                        $mcq(22, 'According to the text, why are players often asked to put their phones away before bedtime?', ['They tend to feel more nervous when they read about the competition.', 'Relaxation techniques cannot be practised with a phone.', 'Coaches want to check what the players are reading.', 'Bright screens can make it harder to fall asleep.'], 'D'),
                        $mcq(23, 'What does the example of the player who stopped wearing his wristband show?', ['Wristbands often record incorrect information.', 'Coaches should not discuss sleep results with players.', 'Monitoring can sometimes have the opposite effect to the one intended.', 'Players prefer traditional methods to modern ones.'], 'C'),
                        $mcq(24, 'What criticism of the research is made in the text?', ['Most of it was carried out on professional players.', 'It cannot prove that sleep alone causes the improvements.', 'It has been paid for by the sports clubs.', 'It ignores athletes who already sleep well.'], 'B'),
                        ['type' => 'text', 'text' => '**Questions 25–29.** Do the following statements agree with the information in the text?'],
                        $tfng(25, 'Some professional clubs have sleep specialists on their staff.', 'TRUE'),
                        $tfng(26, 'The swimmers in the study were all under twenty years old.', 'NOT GIVEN'),
                        $tfng(27, 'Experienced athletes rarely have trouble sleeping before competitions.', 'FALSE'),
                        $tfng(28, 'Every player is happy to wear a sleep wristband.', 'FALSE'),
                        $tfng(29, 'The writer suggests that extra sleep may sometimes be more useful than early exercise.', 'TRUE'),
                    ],
                ],
                // ---------------------------------------------------------------- Part 5
                [
                    'title' => 'Part 5',
                    'instructions' => "Read the text. For questions 30–33, complete the summary. Write **ONE WORD** from the text for each answer.\nFor questions 34–35, choose the correct answer **A**, **B**, **C** or **D**.",
                    'passage' => [
                        'title' => 'Science for Everyone',
                        'text' => <<<'TXT'
For most of history, scientific discovery was the business of a small educated elite. Today, however, thousands of ordinary people with no formal training are contributing to real research, often from their kitchens and back gardens. This movement, known as citizen science, has grown rapidly since the arrival of the internet and the smartphone, and it is changing the way researchers work.

One of the first large projects of this kind invited volunteers to classify images of galaxies taken by a telescope. The quantity of data was simply too great for a small team of astronomers, and the computers of the time were poor at recognising the shapes involved. Human eyes, it turned out, were remarkably good at the task. Similar projects have since asked volunteers to transcribe handwritten documents and to sort recordings of animal sounds. Within months, several hundred thousand volunteers had examined millions of images. Their work led to the identification of a rare type of object which professional astronomers had not expected to find, and the first person to notice it was a schoolteacher who was simply curious about a strange greenish cloud in one of the pictures.

Not every project involves computers. In many countries, volunteers count birds, record the dates when trees come into flower, or measure rainfall with simple equipment. Individually, such observations may seem unimportant. Collected over many years and across large areas, however, they create a record that no single research team could ever produce. Ecologists have used records of this kind to show that some migrating birds now arrive earlier in the year, as spring temperatures have risen.

The smartphone has made participation easier than ever. Apps can now identify a plant from a photograph, recognise the song of a bird or map the position of an unusual insect, and each observation is sent automatically, together with its location and the time, to a central database. Researchers can therefore collect in a week an amount of information that once took years to gather.

Why do people volunteer? Surveys of participants suggest a variety of motives. Some are driven by curiosity, others by a wish to help a cause they care about, such as protecting wildlife. A smaller number enjoy the friendly competition created by tables showing how many observations each volunteer has submitted.

Critics have raised legitimate concerns. The most common is quality: can we trust data collected by people with no training? Organisers respond in several ways. Most projects show the same picture to several volunteers and accept an answer only when the majority agree. Others begin with a short tutorial and a test, and remove participants whose results are unreliable. Some also give each volunteer a score based on earlier answers, so that the most reliable participants have more influence on the final result. Studies comparing volunteers' measurements with those of professionals have often found the difference to be small, particularly for simple tasks such as counting or identifying.

There is also a second benefit that has little to do with data. Taking part seems to change how people think about science. Volunteers frequently report that they have become more interested in the natural world and more willing to question claims they read in the news. Some have even developed their own research questions and published them alongside professionals. For schools, this offers a valuable way of showing pupils that science is not a finished body of facts but an ongoing process of investigation.

The movement does have limits. Not every scientific problem can be broken into small tasks that a beginner can perform; work in a laboratory, for instance, requires expensive equipment and years of training. There is also a risk that volunteers lose interest, and many projects report that a small minority of participants provide the bulk of the contributions. Yet the experience of the last two decades suggests that the partnership between professionals and amateurs is likely to grow, and that some of tomorrow's most important discoveries will owe something to people who simply looked closely.
TXT,
                    ],
                    'blocks' => [
                        [
                            'type' => 'gap_text',
                            'title' => 'Summary',
                            'max_words' => 1,
                            'text' => 'Citizen science allows ordinary people to contribute to research. In one early project, volunteers looked at images of [[30]] because human eyes were better than computers at recognising the shapes involved. Other projects rely on simple observations, such as measuring [[31]], and these create a long-term [[32]] that no single research team could produce. To deal with doubts about quality, organisers usually accept an answer only when the [[33]] of volunteers agree.',
                            'answers' => [
                                '30' => 'galaxies',
                                '31' => 'rainfall',
                                '32' => 'record',
                                '33' => 'majority',
                            ],
                        ],
                        $mcq(34, 'What is the writer\'s purpose in the paragraph that begins "Critics have raised legitimate concerns"?', ['To explain how organisers respond to a common criticism.', 'To show that citizen science produces unreliable results.', 'To compare volunteers with professional scientists in detail.', 'To argue that all volunteers should receive training.'], 'A'),
                        $mcq(35, "What is the writer's overall view of citizen science in the final paragraph?", ['It is unsuitable for most types of research.', 'It will eventually replace traditional laboratories.', 'It is popular mainly because it costs nothing.', 'It has weaknesses but is likely to become more important.'], 'D'),
                    ],
                ],
            ],
        ],

        'writing' => [
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => 'Read the text and complete Task 1.1 and Task 1.2. You are advised to spend about 25 minutes on Part 1.',
                    'context' => "You are a member of a community centre. You have received this email from the centre director.\n\n*Dear member,*\n\n*Next month we are planning a \"Healthy Living Month\". There will be free cooking lessons on Saturday mornings, and on the last Sunday we will hold a 5 km fun run in the park. To make room for these activities, we may have to stop the Friday film evening.*\n\n*Please tell us what you think about these plans and suggest anything else we could do.*\n\n*Kind regards,*\n*Malika Yusupova, Centre Director*",
                    'tasks' => [
                        [
                            'id' => '1.1',
                            'title' => 'Task 1.1',
                            'prompt' => 'Write a letter to your friend, who is also a member of the centre. Tell your friend about the email and say what you think of the plans.' . "\n\n" . 'Write about **50** words.',
                            'min_words' => 50,
                            'max_words' => 0,
                        ],
                        [
                            'id' => '1.2',
                            'title' => 'Task 1.2',
                            'prompt' => 'Write a letter to the centre director. Give your opinion about the plans and suggest other activities for the month.' . "\n\n" . 'Write **120–150** words.',
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
                            'prompt' => "You have seen this post on an online forum:\n\n*Some people believe that news on social media is just as reliable as news on television or in newspapers. Others disagree. What do you think?*\n\nWrite a post for the forum giving your opinion. Write **180–200** words.",
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
                        ['no' => 1, 'text' => 'What do you usually have for dinner, and who cooks it?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq1'],
                        ['no' => 2, 'text' => 'What do you do to keep fit and healthy?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq2'],
                        ['no' => 3, 'text' => 'How do you keep in touch with friends and family who live far away?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq3'],
                    ],
                ],
                [
                    'id' => '1.2',
                    'title' => 'Part 1.2',
                    'instructions' => 'Look at the two pictures and answer the questions.',
                    'images' => ['@image:s12_1', '@image:s12_2'],
                    'questions' => [
                        ['no' => 4, 'text' => 'Look at the two pictures. Compare them and tell me what the advantages of shopping in each place are.', 'prep_sec' => 0, 'answer_sec' => 45, 'audio' => '@audio:sq4'],
                        ['no' => 5, 'text' => 'Which of these two ways of shopping do you prefer? Why?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq5'],
                        ['no' => 6, 'text' => 'How has online shopping changed the way people buy things?', 'prep_sec' => 0, 'answer_sec' => 30, 'audio' => '@audio:sq6'],
                    ],
                ],
                [
                    'id' => '2',
                    'title' => 'Part 2',
                    'instructions' => 'Look at the picture and answer the questions. You have one minute to prepare and two minutes to speak.',
                    'images' => ['@image:s2'],
                    'questions' => [
                        ['no' => 7, 'text' => "Look at the picture. Tell me about a sports event that you have watched or taken part in.\n- What was the event?\n- Who were you with?\n- Why do you remember it?", 'prep_sec' => 60, 'answer_sec' => 120, 'audio' => '@audio:sq7'],
                    ],
                ],
                [
                    'id' => '3',
                    'title' => 'Part 3',
                    'instructions' => 'Read the statement and the arguments for and against it. You have one minute to prepare and two minutes to speak.',
                    'topic' => 'Everyone should learn a second foreign language at school.',
                    'for' => [
                        'It helps young people to find better jobs.',
                        'It makes it easier to understand other cultures.',
                        'It keeps the brain active and improves memory.',
                    ],
                    'against' => [
                        'Students already have too many subjects.',
                        'One foreign language is difficult enough for many students.',
                        'Many schools do not have enough qualified teachers.',
                    ],
                    'questions' => [
                        ['no' => 8, 'text' => 'Discuss both sides of the argument and give your own opinion. Use at least two points from each list.', 'prep_sec' => 60, 'answer_sec' => 120, 'audio' => '@audio:sq8'],
                    ],
                ],
            ],
        ],
    ],
];

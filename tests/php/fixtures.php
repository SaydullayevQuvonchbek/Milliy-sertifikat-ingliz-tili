<?php

declare(strict_types=1);

use App\Db;
use App\Installer;
use App\Services\MockService;
use App\Util;

/** Kichik, lekin to'liq tuzilishli sinov mocki. */
function sample_source(): array
{
    return [
        'listening' => [
            'review_sec' => 60,
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => 'Listen and choose the correct answer.',
                    'preview_sec' => 10,
                    'gap_sec' => 5,
                    'plays' => 2,
                    'tracks' => [['asset' => 1, 'duration' => 20.5, 'label' => 'Dialogue 1', 'transcript' => 'secret']],
                    'blocks' => [
                        ['type' => 'mcq', 'n' => 1, 'prompt' => 'Where is the man going?', 'options' => ['To the bank.', 'To the park.', 'To school.'], 'answer' => 'B'],
                        ['type' => 'gap_text', 'title' => 'Notes', 'text' => 'The museum opens at [[2]] a.m.', 'answers' => ['2' => 'nine|9'], 'max_words' => 1],
                    ],
                ],
            ],
        ],
        'reading' => [
            'parts' => [
                [
                    'title' => 'Part 1',
                    'instructions' => 'Read the text and answer the questions.',
                    'passage' => ['title' => 'Rivers', 'text' => 'Rivers carry water to the sea.'],
                    'blocks' => [
                        ['type' => 'tfng', 'n' => 1, 'prompt' => 'Rivers carry water.', 'answer' => 'TRUE'],
                        [
                            'type' => 'match',
                            'title' => 'Choose the heading.',
                            'options' => [['key' => 'A', 'text' => 'Water'], ['key' => 'B', 'text' => 'Fire']],
                            'items' => [['n' => 2, 'prompt' => 'Paragraph 1', 'answer' => 'A']],
                        ],
                    ],
                ],
            ],
        ],
        'writing' => [
            'parts' => [
                [
                    'title' => 'Part 1',
                    'context' => 'You received this email from a friend.',
                    'tasks' => [
                        ['id' => '1.1', 'title' => 'Task 1.1', 'prompt' => 'Write to your friend.', 'min_words' => 50],
                        ['id' => '1.2', 'title' => 'Task 1.2', 'prompt' => 'Write to the manager.', 'min_words' => 120, 'max_words' => 150],
                    ],
                ],
                ['title' => 'Part 2', 'context' => '', 'tasks' => [['id' => '2', 'title' => 'Task 2', 'prompt' => 'Write a blog post.', 'min_words' => 180, 'max_words' => 200]]],
            ],
        ],
        'speaking' => [
            'parts' => [
                ['id' => '1.1', 'title' => 'Part 1.1', 'questions' => [
                    ['no' => 1, 'text' => 'What is your name?', 'prep_sec' => 0, 'answer_sec' => 30],
                    ['no' => 2, 'text' => 'Where do you live?', 'prep_sec' => 0, 'answer_sec' => 30],
                ]],
                ['id' => '3', 'title' => 'Part 3', 'topic' => 'Homework', 'for' => ['Practice'], 'against' => ['Stress'], 'questions' => [
                    ['no' => 3, 'text' => 'Discuss the topic.', 'prep_sec' => 60, 'answer_sec' => 120],
                ]],
            ],
        ],
    ];
}

function make_mock(array $overrides = [], string $status = 'active'): array
{
    $source = $overrides['source'] ?? sample_source();
    $settings = MockService::mergeSettings($overrides['settings'] ?? []);
    $compiled = MockService::compile($source);
    $id = Db::insert('mocks', [
        'title' => $overrides['title'] ?? 'Sinov mock',
        'description' => '',
        'status' => $status,
        'max_attempts' => $overrides['max_attempts'] ?? 2,
        'source_json' => Util::json($source),
        'content_json' => Util::json($compiled['content']),
        'key_json' => Util::json($compiled['key']),
        'settings_json' => Util::json($settings),
        'stats_json' => '{}',
        'created_at' => time(),
        'updated_at' => time(),
    ]);
    return MockService::find($id);
}

function make_user(string $role = 'student', string $login = '+998901112233'): array
{
    $id = Installer::createUser($role, 'Test Foydalanuvchi', $login, 'parol123');
    return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
}

<?php

// content/mocks/<slug>/mock.php uchun yordamchilar.
//   require_once __DIR__ . '/../../lib.php';
//   $audio = content_audio(__DIR__);
//   'transcript' => content_transcript($audio, 'l1_1'),

declare(strict_types=1);

/** <slug>/audio.json ni o'qish (audio sintezi uchun manba — transkript ham shu yerdan olinadi). */
function content_audio(string $dir): array
{
    $file = $dir . '/audio.json';
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data) || !isset($data['tracks'])) {
        throw new RuntimeException("audio.json noto'g'ri: {$file}");
    }
    return $data;
}

/** Trek matni: "W: ...\nM: ..." ko'rinishida (admin panelda ko'rsatiladi, o'quvchiga berilmaydi). */
function content_transcript(array $audio, string $track): string
{
    if (!isset($audio['tracks'][$track])) {
        throw new RuntimeException("audio.json da '{$track}' treki yo'q");
    }
    $lines = [];
    foreach ($audio['tracks'][$track]['script'] as $item) {
        if (is_array($item) && isset($item['pause'])) {
            continue;
        }
        [$who, $text] = is_array($item) && array_is_list($item) ? [$item[0], $item[1]] : [$item['s'] ?? $item['speaker'], $item['t'] ?? $item['text']];
        if ($who === 'pause') {
            continue;
        }
        $lines[] = ($who === 'N' ? 'Narrator' : $who) . ': ' . trim((string) $text);
    }
    return implode("\n", $lines);
}

<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = array_merge(glob($root . '/b/lessen/*.md'), glob($root . '/k/lessen/*.md'), glob($root . '/gt/lessen/*.md'), glob($root . '/havo/lessen/*.md'));
$count = 0;
$total = 0;
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (preg_match('/^(?:#{1,6}\s+|\*\*)(?:Controlevragen|Toetsvragen|Extra vragen)/mi', $source)) {
        throw new RuntimeException('Oud vragenblok: ' . $file);
    }
    if (!str_contains($source, '### Zelftest')) continue;
    if (!preg_match('/^### Zelftest\n(?<questions>.*?)^#### Antwoorden\n(?<answers>.*?)(?=^### |\z)/ms', $source, $section)) {
        throw new RuntimeException('Zelftest mist apart antwoordenblok: ' . $file);
    }
    preg_match_all('/^(\d+)\. (.+)$/m', $section['questions'], $questions, PREG_SET_ORDER);
    preg_match_all('/^(\d+)\. (.+)$/m', $section['answers'], $answers, PREG_SET_ORDER);
    if (!$questions || count($questions) !== count($answers)) {
        throw new RuntimeException('Aantal vragen en antwoorden verschilt: ' . $file);
    }
    $seen = [];
    foreach ($questions as $index => $question) {
        if ((int) $question[1] !== $index + 1 || (int) $answers[$index][1] !== $index + 1) {
            throw new RuntimeException('Nummering klopt niet: ' . $file);
        }
        $key = preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($question[2], 'UTF-8'));
        if (isset($seen[$key])) throw new RuntimeException('Dubbele vraag: ' . $file);
        $seen[$key] = true;
        if (str_contains($question[2], 'Antwoord:')) throw new RuntimeException('Antwoord tussen vragen: ' . $file);
    }
    $count++;
    $total += count($questions);
}
echo "OK: $count Zelftests met $total vragen, apart genummerde antwoorden en geen identieke vragen.\n";

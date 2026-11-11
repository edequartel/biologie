<?php
declare(strict_types=1);
require dirname(__DIR__) . '/website/functions.php';

$pages = $overviews = [];
foreach (['gt' => 59, 'b' => 49, 'k' => 49] as $level => $minimum) {
    $overviewPath = "$level/$level.md";
    $overviews[$level] = renderMarkdown((string) file_get_contents(contentPath($overviewPath)), $overviewPath);
    preg_match_all('~href="index\.php\?page=([^"]+)">Basisstof\b~i', $overviews[$level], $links);
    $overviews[$level . '-lessons'] = array_unique(array_map('rawurldecode', $links[1]));
    if (count($overviews[$level . '-lessons']) < $minimum) {
        throw new RuntimeException('Basisstoflinks ontbreken in ' . $overviewPath);
    }
    array_push($pages, ...$overviews[$level . '-lessons']);
}
foreach ([5, 8, 9] as $theme) {
    for ($number = 1; $number <= 6; $number++) {
        $pages[] = 'havo/lessen/t' . $theme . 'b' . $number . '.md';
    }
}
$sharedImages = [
    't1b1' => 't1b1', 't1b2' => 't1b2', 't1b4' => 't1b3',
    't2b1' => 't2b1', 't2b2' => 't2b2', 't2b3' => 't2b3', 't2b4' => 't2b4',
    't2b5' => 't2b5', 't2b6' => 't2b6', 't2b7' => 't2b7',
    't3b1' => 't4b2', 't3b2' => 't4b3',
    't4b1' => 't5b1', 't4b2' => 't5b2', 't4b3' => 't5b3',
    't4b4' => 't5b4', 't4b5' => 't5b5', 't4b6' => 't5b6',
    't5b1' => 't6b1', 't5b2' => 't6b1', 't5b3' => 't6b4',
    't5b4' => 't6b5', 't5b5' => 't6b6',
    't6b1' => 't7b1', 't6b2' => 't7b2', 't6b3' => 't7b3',
    't6b4' => 't7b4', 't6b5' => 't7b5', 't6b6' => 't7b7',
];
$imageFiles = [];
foreach (array_unique($pages) as $page) {
        $html = renderMarkdown((string) file_get_contents(contentPath($page)), $page);
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $headings = $xpath->query('//h3[starts-with(@id, "afbeelding-")]');
        $descriptions = $xpath->query('//h4[@id="tekstbeschrijving-van-de-afbeelding"]');
        $images = $xpath->query('//img');
        if ($headings->length !== 1 || $descriptions->length !== 1 || $images->length !== 1) {
            throw new RuntimeException('Afbeelding of beschrijving ontbreekt of is dubbel: ' . $page);
        }
        $image = $images->item(0);
        if (mb_strlen($image->getAttribute('alt')) < 30 || $image->getAttribute('loading') !== 'lazy') {
            throw new RuntimeException('Alternatieve tekst of lazy loading ontbreekt: ' . $page);
        }
        parse_str((string) parse_url($image->getAttribute('src'), PHP_URL_QUERY), $parameters);
        $file = contentPath($parameters['file'] ?? '');
        if ($file === null || !getimagesize($file)) {
            throw new RuntimeException('Afbeeldingsbestand ontbreekt of is ongeldig: ' . $page);
        }
        if (!preg_match('~^media/(?:organen-en-cellen|voortplanting-en-seksualiteit|erfelijkheid-en-evolutie|ordening|stevigheid-en-beweging|ecologie|duurzaam-leven|planten|regeling|dna|stofwisseling-in-de-cel)/[^/]+\.png$~D', $parameters['file'])) {
            throw new RuntimeException('Afbeelding staat niet in een themamap: ' . $page);
        }
        $lastHeading = $xpath->query('//h4')->item($xpath->query('//h4')->length - 1);
        if ($lastHeading->getAttribute('id') !== 'tekstbeschrijving-van-de-afbeelding') {
            throw new RuntimeException('Afbeeldingsbeschrijving staat niet onderaan: ' . $page);
        }
        $imageFiles[$page] = $file;
}
foreach ($sharedImages as $basisLesson => $gtLesson) {
    $page = 'b/lessen/' . $basisLesson . '.md';
    if ($imageFiles[$page] !== $imageFiles['gt/lessen/' . $gtLesson . '.md']) {
        throw new RuntimeException('Onjuiste gedeelde uitlegafbeelding: ' . $page);
    }
}
$count = count($imageFiles);
echo "OK: $count basisstoflessen met een geldige uitlegafbeelding, alt-tekst en tekstbeschrijving onderaan.\n";

$overview = $overviews['gt'];
$badge = contentPath('media/lesson-image-badge.png');
if ($badge === null || !getimagesize($badge)) {
    throw new RuntimeException('Thema-icoon ontbreekt of is ongeldig.');
}
foreach ([1, 2, 3, 4, 5, 6, 7, 9, 10] as $theme) {
    if (!preg_match('~<h4 id="thema-' . $theme . '-[^"]+">[^<]*<img\b[^>]*alt="Met uitlegafbeeldingen"~', $overview)) {
        throw new RuntimeException('Toegankelijk icoon ontbreekt bij GT-thema ' . $theme);
    }
}
echo "OK: GT-thema's met uitlegafbeeldingen hebben een icoon met alt-tekst.\n";

$basis = (string) file_get_contents(contentPath('b/b.md'));
$kader = (string) file_get_contents(contentPath('k/k.md'));
foreach ($overviews['b-lessons'] as $lesson) {
    if (!str_starts_with($lesson, 'b/lessen/t8b') && !in_array($lesson, $overviews['k-lessons'], true)) {
        throw new RuntimeException('Gedeelde basisstof ontbreekt in KBL: ' . $lesson);
    }
}
foreach ([
    1 => 'Organen en cellen', 2 => 'Voortplanting en seksualteit', 3 => 'Ordening',
    4 => 'Stevigheid en beweging', 5 => 'Ecologie', 6 => 'Duurzaam leven',
] as $theme => $title) {
    if (!str_contains($basis, '#### Thema ' . $theme . ' ' . $title . ' ![Met uitlegafbeeldingen]')
        || !str_contains($kader, '### ' . $title . ' ![Met uitlegafbeeldingen]')) {
        throw new RuntimeException('Thema-icoon ontbreekt bij BBL of KBL: ' . $title);
    }
}
echo "OK: BBL en KBL tonen thema-iconen en delen de passende lesafbeeldingen.\n";

foreach ([
    'media/gt-thema-1-4/t4b2-uitleg.png' => 'media/ordening/t4b2-uitleg.png',
    'media/gt-thema-5-7/t6b1-uitleg.png' => 'media/ecologie/t6b1-uitleg.png',
    'media/stofwisseling/stofwisseling-atp-uitleg.png' => 'media/stofwisseling-in-de-cel/stofwisseling-atp-uitleg.png',
] as $old => $new) {
    if (contentPath($old) === null || contentPath($old) !== contentPath($new)) {
        throw new RuntimeException('Oude afbeeldingslink werkt niet: ' . $old);
    }
}
echo "OK: Oude afbeeldingslinks blijven werken.\n";

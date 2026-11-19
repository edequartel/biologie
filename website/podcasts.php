<?php
declare(strict_types=1);

const PODCAST_ORIGIN = 'https://www.tastenbraille.com';
const PODCAST_API = PODCAST_ORIGIN . '/biologie/api/audio.php?format=all';
const HAVO_PODCAST_OVERVIEW = PODCAST_ORIGIN . '/biologie/havo.php';

function podcastCatalogue(string $url, callable $parse, bool $refresh = false): ?array
{
    $cachePath = sys_get_temp_dir() . '/biologie-podcasts-' . hash('sha256', __DIR__ . $url);
    $cachedJson = is_file($cachePath) ? @file_get_contents($cachePath) : false;
    $cached = $cachedJson !== false ? json_decode($cachedJson, true) : null;
    if (!$refresh && is_array($cached) && time() - (int) @filemtime($cachePath) < 3600) {
        return $cached;
    }

    $context = stream_context_create(['http' => ['timeout' => 2.0, 'follow_location' => 0]]);
    $response = @file_get_contents($url, false, $context, 0, 1_048_577);
    $catalogue = $response !== false && strlen($response) <= 1_048_576 ? $parse($response) : null;
    if ($catalogue === null) {
        return !$refresh && is_array($cached) ? $cached : null;
    }

    $temporary = @tempnam(sys_get_temp_dir(), 'biologie-podcasts-');
    $saved = $temporary !== false
        && @file_put_contents($temporary, json_encode($catalogue, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)) !== false
        && @rename($temporary, $cachePath);
    if (!$saved) {
        if ($temporary !== false) {
            @unlink($temporary);
        }
        return $refresh ? null : $catalogue;
    }
    return $catalogue;
}

function refreshLessonPodcasts(): array
{
    $api = podcastCatalogue(PODCAST_API, 'parsePodcastApi', true);
    $havo = podcastCatalogue(HAVO_PODCAST_OVERVIEW, 'parseHavoPodcastOverview', true);
    if ($api !== null && $havo !== null) {
        return [true, 'De catalogus is vernieuwd. Beschikbare podcasts staan bij de bijbehorende basisstoffen.', 'Podcasts bijgewerkt'];
    }

    $source = $api === null
        ? ($havo === null ? 'de audio-API en het Havo-overzicht' : 'de audio-API')
        : 'het Havo-overzicht';
    return [false, 'De catalogus uit ' . $source . ' kon niet worden opgehaald of opgeslagen.', 'Podcasts niet volledig bijgewerkt'];
}

function indexPodcast(array &$catalogue, string $url, array $parsed = []): void
{
    if (!preg_match('~^' . preg_quote(PODCAST_ORIGIN, '~') . '/biologie/(b|bk|k|gt|havo)/[^?#]+$~D', $url, $section)) {
        return;
    }
    $filename = rawurldecode(basename($url));
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['m4a', 'mp3', 'ogg', 'wav', 'aac', 'opus', 'flac'], true)) {
        return;
    }

    $stem = pathinfo($filename, PATHINFO_FILENAME);
    // The catalogue also contains spaced codes, a missing digit, and a Havo episode for two lessons.
    $code = $stem === 'GT T3 B Genotype en fenotype' ? 'GT T3B1 Genotype en fenotype' : $stem;
    if (preg_match('/^(?:\d+)?(?:BK|BBL|B|KBL|K|GT|HAVO|H)\h+T(\d+)\h*B(\d+)(?::(\d+))?\h+[–—\-\h]*(.+)$/iuD', $code, $match)) {
        $topic = (int) $match[1];
        $blocks = $match[3] === '' ? [(int) $match[2]] : [(int) $match[2], (int) $match[3]];
        $title = trim($match[4]);
    } elseif (is_string($parsed['topic'] ?? null) && is_string($parsed['block'] ?? null)
        && preg_match('/^T(\d+)B(\d+)$/iD', $parsed['topic'] . $parsed['block'], $match)) {
        $topic = (int) $match[1];
        $blocks = [(int) $match[2]];
        $title = trim(is_string($parsed['title'] ?? null) ? $parsed['title'] : $stem);
    } else {
        return;
    }

    foreach ($blocks as $block) {
        $key = $section[1] . '/t' . $topic . 'b' . $block;
        if (!isset($catalogue[$key]) || preg_match('/\hcopy$/i', $catalogue[$key]['title'])) {
            $catalogue[$key] = ['url' => $url, 'title' => $title];
        }
    }
}

function parsePodcastApi(string $response): ?array
{
    $data = json_decode($response, true);
    if (!is_array($data['folders'] ?? null)) {
        return null;
    }
    $catalogue = [];
    foreach ($data['folders'] as $folder) {
        if (!is_array($folder['audio_files'] ?? null)) {
            continue;
        }
        foreach ($folder['audio_files'] as $file) {
            if (is_string($file['url'] ?? null)) {
                indexPodcast($catalogue, $file['url'], is_array($file['parsed'] ?? null) ? $file['parsed'] : []);
            }
        }
    }
    return $catalogue;
}

function parseHavoPodcastOverview(string $response): ?array
{
    $dom = new DOMDocument();
    if ($response === '' || !@$dom->loadHTML($response, LIBXML_NONET)) {
        return null;
    }
    $catalogue = [];
    foreach ((new DOMXPath($dom))->query('//*[@data-audio-src]') as $node) {
        indexPodcast($catalogue, $node->getAttribute('data-audio-src'));
    }
    return $catalogue ?: null;
}

function lessonPodcast(string $page): string
{
    if (!preg_match('~^(b|k|gt|havo)/lessen/(t\d+b\d+)\.md$~D', $page, $lesson)) {
        return '';
    }
    $chapter = $lesson[2];
    $key = $lesson[1] . '/' . $chapter;
    $catalogue = podcastCatalogue(PODCAST_API, 'parsePodcastApi');
    $episode = $catalogue[$key] ?? null;
    if ($episode === null && $lesson[1] === 'havo') {
        $catalogue = podcastCatalogue(HAVO_PODCAST_OVERVIEW, 'parseHavoPodcastOverview');
        $episode = $catalogue[$key] ?? null;
    }

    // Some Basis and GT topics share episodes. Regeling is numbered differently for Kader.
    $shared = [
        'b/t1b1' => 'gt/t1b1',
        'b/t1b2' => 'gt/t1b2',
        'b/t1b4' => 'gt/t1b3',
        'b/t3b6' => 'gt/t4b6',
        'k/t9b1' => 'bk/t8b1',
        'k/t9b2' => 'bk/t8b2',
        'k/t9b3' => 'gt/t10b3',
        'k/t9b4' => 'gt/t10b4',
        'k/t9b5' => 'gt/t10b5',
        'k/t9b6' => 'bk/t8b6',
        'gt/t6b1' => 'bk/t5b2',
        'gt/t7b1' => 'bk/t6b1',
        'gt/t7b2' => 'bk/t6b2',
        'gt/t7b3' => 'bk/t6b3',
        'gt/t7b4' => 'bk/t6b4',
        'gt/t7b5' => 'bk/t6b5',
        'gt/t7b6' => 'bk/t6b6',
        'gt/t7b7' => 'bk/t6b6',
    ];
    if ($episode === null && $lesson[1] === 'b') {
        $episode = $catalogue['bk/' . $chapter] ?? null;
    }
    if ($episode === null && isset($shared[$key])) {
        $episode = $catalogue[$shared[$key]] ?? null;
    }

    $contents = '<h3>Podcast</h3>';
    if ($episode !== null) {
        $url = escape($episode['url']);
        $title = escape($episode['title']);
        $filename = escape(rawurldecode(basename($episode['url'])));
        $contents .= '<audio controls="controls" preload="none" aria-label="Podcast ' . $title . '" src="' . $url . '"></audio>'
            . '<p><a href="' . $url . '">' . $filename . '</a></p>';
    } else {
        $contents .= $catalogue === null
            ? '<p>De podcast kon niet worden geladen.</p>'
            : '<p>Voor deze basisstof is nog geen podcast beschikbaar.</p>';
    }
    return '<section class="lesson-podcast" aria-label="Podcast bij deze basisstof">' . $contents . '</section>';
}

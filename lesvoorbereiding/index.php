<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/website/functions.php';

$fromMainSite = $lessonCatalogFromHome ?? false;
$rootLink = $fromMainSite ? '' : '../';
$catalogLink = 'index.php' . ($fromMainSite ? '?section=lesdoelen' : '');
$downloadPrefix = $catalogLink . ($fromMainSite ? '&' : '?') . 'download=';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

$levels = [
    'HAVO' => ['Havo', ['havo/havo.md']],
    'GT' => ['Vmbo GT', ['gt/gt.md']],
    'BK' => ['Vmbo Basis & Kader', ['b/b.md', 'k/k.md']],
];
$downloads = [];
$bundles = [];
foreach ($levels as $level => [$label]) {
    foreach (['', 'Les A', 'Les B'] as $variant) {
        $suffix = $variant === '' ? '' : " $variant";
        $key = $level . $suffix;
        $filename = "Lesdoelen $key.docx";
        $path = __DIR__ . '/' . $filename;
        if (!is_link($path) && is_file($path) && is_readable($path)) {
            $downloads[$key] = ['path' => $path, 'name' => $filename];
            $bundles[$level][$variant] = ['key' => $key, 'label' => $label . $suffix];
        }
    }
    if (isset($bundles[$level]['Les A'], $bundles[$level]['Les B'])) {
        unset($bundles[$level]['']);
    }
}
$themeTitles = [];
$themes = [];
$lessons = [];

foreach ($levels as $level => [$label, $indexes]) {
    foreach ($indexes as $index) {
        $source = contentPath($index);
        if ($source === null) {
            continue;
        }
        $markdown = preg_replace('/<!--.*?(?:-->|$)/s', '', (string) file_get_contents($source));
        preg_match_all('/^#{1,6}\h+Thema\h+(\d+)\h*:?\h+([^\r\n!]+)/mi', $markdown, $headings, PREG_SET_ORDER);
        foreach ($headings as [, $number, $title]) {
            $themeTitles[$level]['T' . $number][] = trim($title);
        }
    }

    $levelPath = __DIR__ . '/' . $level;
    if (is_link($levelPath)) {
        continue;
    }
    $themePaths = glob($levelPath . '/T*', GLOB_ONLYDIR) ?: [];
    natsort($themePaths);
    foreach ($themePaths as $themePath) {
        $theme = basename($themePath);
        if (is_link($themePath) || !preg_match('/^T[1-9][0-9]*$/D', $theme)) {
            continue;
        }
        $paths = glob($themePath . '/*.docx') ?: [];
        natcasesort($paths);
        foreach ($paths as $path) {
            $name = basename($path);
            $prefix = preg_quote("$level $theme ", '/');
            if (!preg_match('/^' . $prefix . '([A-Z][1-9][0-9]*) (.+) lesdoel(?: (Les [AB]))?\.docx$/Du', $name, $parts)
                || is_link($path) || !is_file($path) || !is_readable($path)) {
                continue;
            }
            $key = "$level/$theme/$name";
            $downloads[$key] = ['path' => $path, 'name' => $name];
            $variant = $parts[3] ?? '';
            $basename = "$level $theme {$parts[1]} {$parts[2]} lesdoel.docx";
            $lessonKey = "$level/$theme/$basename";
            $lessons[$lessonKey] ??= [
                'name' => $basename,
                'level' => $level,
                'theme' => $theme,
                'code' => $parts[1],
                'title' => $parts[2],
                'files' => [],
            ];
            $lessons[$lessonKey]['files'][$variant] = $key;
            $themes[$theme] = true;
        }
    }
}

if (isset($_GET['download'])) {
    $key = $_GET['download'];
    $download = is_string($key) ? ($downloads[$key] ?? null) : null;
    $file = $download ? @fopen($download['path'], 'rb') : false;
    if ($file === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Lesdoeldocument niet gevonden.');
    }
    $stat = fstat($file);
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($download['name']));
    if ($stat !== false) {
        header('Content-Length: ' . $stat['size']);
    }
    fpassthru($file);
    fclose($file);
    exit;
}

uksort($themes, 'strnatcmp');
$levelFilter = is_string($_GET['niveau'] ?? null) && isset($levels[$_GET['niveau']]) ? $_GET['niveau'] : '';
$themeFilter = is_string($_GET['thema'] ?? null) && isset($themes[$_GET['thema']]) ? $_GET['thema'] : '';
$query = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 160) : '';
$terms = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$filtered = $levelFilter !== '' || $themeFilter !== '' || $query !== '';
$groups = [];
$resultCount = 0;
$documentCount = 0;

foreach ($lessons as $key => $lesson) {
    if (isset($lesson['files']['Les A'], $lesson['files']['Les B'])) {
        unset($lesson['files']['']);
    }
    $documentCount += count($lesson['files']);
    $level = $lesson['level'];
    $theme = $lesson['theme'];
    if (($levelFilter !== '' && $level !== $levelFilter) || ($themeFilter !== '' && $theme !== $themeFilter)) {
        continue;
    }
    $headings = $themeTitles[$level][$theme] ?? [];
    $search = mb_strtolower($lesson['name'] . ' ' . $levels[$level][0] . ' ' . implode(' ', $headings));
    foreach ($terms as $term) {
        if (preg_match('/^([tbops])\d+$/D', $term)) {
            if ($term !== strtolower($theme) && $term !== strtolower($lesson['code'])) {
                continue 2;
            }
        } elseif (!str_contains($search, $term)) {
            continue 2;
        }
    }
    $groups[$level][$theme][$key] = $lesson;
    $resultCount++;
}

$countLabel = static fn (int $count): string => $count . ($count === 1 ? ' les' : ' lessen');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lesdoelen downloaden | Biologie voor Bartiméus</title>
    <script src="<?= escape($rootLink . assetUrl('assets/theme.js')) ?>"></script>
    <link rel="stylesheet" href="<?= escape($rootLink . assetUrl('assets/site.css')) ?>">
    <link rel="stylesheet" href="<?= escape($rootLink . assetUrl('assets/lesdoelen.css')) ?>">
    <script src="<?= escape($rootLink . assetUrl('assets/site.js')) ?>" defer></script>
</head>
<body class="lesson-library">
<a class="skip-link" href="#inhoud">Direct naar de inhoud</a>
<header class="library-header">
    <div class="library-shell library-header-inner">
        <a class="library-brand" href="<?= $rootLink ?>index.php">
            <span class="brand-mark" aria-hidden="true">B</span>
            <span>Biologie <small>voor Bartiméus</small></span>
        </a>
        <div class="reading-controls" aria-label="Leesinstellingen">
            <button type="button" id="text-size" aria-pressed="false">Grotere tekst</button>
            <button type="button" id="contrast" aria-pressed="false">Hoog contrast</button>
            <button type="button" id="theme" aria-pressed="false">Donker thema</button>
        </div>
    </div>
</header>
<main id="inhoud" class="library-shell">
    <div class="library-intro">
        <div>
            <p class="library-context">Lesvoorbereiding biologie</p>
            <h1>Lesdoelen downloaden</h1>
            <p>Les A gebruikt de website. In les B kijk je de boekopgaven na.</p>
        </div>
        <p class="library-total"><strong><?= $documentCount ?></strong> Word-documenten</p>
    </div>

    <?php if ($bundles): ?>
        <section class="library-bundles" aria-labelledby="bundels">
            <h2 id="bundels">Alle lessen per niveau</h2>
            <ul>
                <?php foreach ($bundles as $variants): ?>
                    <?php foreach ($variants as $bundle): ?>
                        <li>
                            <a class="library-download" href="<?= escape($downloadPrefix . rawurlencode($bundle['key'])) ?>">
                                Download <?= escape($bundle['label']) ?> (.docx)
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <form action="index.php#documenten" method="get" class="library-search" role="search" aria-label="Lesdoelen zoeken">
        <?php if ($fromMainSite): ?>
            <input type="hidden" name="section" value="lesdoelen">
        <?php endif; ?>
        <div class="library-field">
            <label for="zoeken">Les of code</label>
            <input type="search" name="q" id="zoeken" maxlength="160" value="<?= escape($query) ?>" placeholder="Bijv. zintuigen of T11 B1">
        </div>
        <div class="library-field">
            <label for="niveau">Niveau</label>
            <select name="niveau" id="niveau">
                <option value="">Alle niveaus</option>
                <?php foreach ($levels as $code => [$label]): ?>
                    <option value="<?= $code ?>" <?= $levelFilter === $code ? 'selected' : '' ?>><?= escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="library-field">
            <label for="thema">Thema</label>
            <select name="thema" id="thema">
                <option value="">Alle thema’s</option>
                <?php foreach (array_keys($themes) as $code): ?>
                    <option value="<?= $code ?>" <?= $themeFilter === $code ? 'selected' : '' ?>><?= $code ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="library-submit">Zoeken</button>
    </form>

    <section id="documenten" aria-labelledby="resultaten">
        <div class="library-results">
            <h2 id="resultaten"><?= $filtered ? 'Zoekresultaten' : 'Alle lesdoelen' ?></h2>
            <div class="library-results-tools">
                <span><?= $countLabel($resultCount) ?></span>
                <?php if ($filtered): ?>
                    <a href="<?= escape($catalogLink) ?>">Wis filters</a>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$groups): ?>
            <p class="library-empty"><?= $lessons ? 'Geen lesdoelen gevonden. Gebruik minder zoekwoorden of wis de filters.' : 'Er staan nog geen lesdoeldocumenten in de themamappen.' ?></p>
        <?php endif; ?>
        <?php foreach ($groups as $level => $levelGroups): ?>
            <?php foreach ($levelGroups as $theme => $items): ?>
                <?php $titles = implode(' / ', array_unique($themeTitles[$level][$theme] ?? [])); ?>
                <section class="library-group" aria-labelledby="<?= "$level-$theme" ?>">
                    <header class="library-group-header">
                        <h3 id="<?= "$level-$theme" ?>">
                            <span class="library-group-code"><?= "$level $theme" ?></span>
                            <?= escape($titles ?: 'Thema ' . substr($theme, 1)) ?>
                        </h3>
                        <span><?= $countLabel(count($items)) ?></span>
                    </header>
                    <ul class="library-list">
                        <?php foreach ($items as $lesson): ?>
                            <li>
                                <span class="library-lesson-code"><?= $lesson['code'] ?></span>
                                <span class="library-lesson-title"><?= escape($lesson['title']) ?></span>
                                <div class="library-downloads">
                                    <?php foreach ($lesson['files'] as $variant => $key): ?>
                                        <?php $linkLabel = $variant === '' ? 'Download .docx' : "$variant (.docx)"; ?>
                                        <a class="library-download" href="<?= escape($downloadPrefix . rawurlencode($key)) ?>" aria-label="<?= escape($linkLabel . ' – ' . $lesson['title'] . ', ' . "$level $theme " . $lesson['code']) ?>"><?= escape($linkLabel) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>

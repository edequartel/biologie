<?php
declare(strict_types=1);
require __DIR__ . '/website/functions.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net; script-src 'self'; img-src 'self' https: data:; font-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

if (isset($_GET['file'])) {
    $file = is_string($_GET['file']) ? contentPath($_GET['file']) : null;
    $types = ['pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4'];
    $extension = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';
    if (!$file || !isset($types[$extension])) {
        http_response_code(404);
        exit('Bestand niet gevonden.');
    }
    header('Content-Type: ' . $types[$extension]);
    header('Content-Length: ' . filesize($file));
    header("Content-Disposition: " . (in_array($extension, ['doc', 'docx']) ? 'attachment' : 'inline') . "; filename*=UTF-8''" . rawurlencode(basename($file)));
    readfile($file);
    exit;
}

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('Cache-Control: no-store');
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    $password = getenv('BIOLOGIE_ADMIN_PASSWORD');
    $authenticated = !$password || (is_string($_POST['password'] ?? null) && hash_equals($password, $_POST['password']));
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token) || !canPull() || !$authenticated) {
        http_response_code(403);
        $flash = [false, 'De update is niet toegestaan. Controleer het beheerderswachtwoord.'];
    } else {
        $flash = pullRepository();
    }
    $_SESSION['flash'] = $flash;
    header('Location: index.php', true, 303);
    exit;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$documents = documents();
$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'README.md';
$selected = $documents[$page] ?? null;
$query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$catalogue = isset($_GET['browse']) || $query !== '';
if (!$selected && !$catalogue) {
    http_response_code(404);
}
$title = $catalogue ? 'Alle lesmaterialen' : ($selected['title'] ?? 'Pagina niet gevonden');
$groups = ['algemeen' => 'Algemeen', 'bk' => 'Basis en kader', 'gt' => 'Gemengd theoretisch', 'havo' => 'Havo', 'openlessen' => 'Open lessen', 'lesplanning' => 'Lesplanning', 'media' => 'Media'];
$matches = array_filter($documents, static fn ($item, $path) => $query === '' || mb_stripos($item['title'] . ' ' . $path, $query) !== false, ARRAY_FILTER_USE_BOTH);
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($title) ?> | Biologie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css">
    <link rel="stylesheet" href="assets/site.css">
    <script src="assets/site.js" defer></script>
</head>
<body>
<a class="skip-link" href="#inhoud">Direct naar de inhoud</a>
<header class="navbar navbar-light">
    <div class="container-xl site-header">
        <a class="navbar-brand" href="index.php"><span class="brand-mark" aria-hidden="true">B</span> Biologie <span class="brand-detail">voor Bartiméus</span></a>
        <div class="reading-controls" aria-label="Leesinstellingen">
            <button class="btn" type="button" id="text-size" aria-pressed="false">Grotere tekst</button>
            <button class="btn" type="button" id="contrast" aria-pressed="false">Hoog contrast</button>
        </div>
    </div>
</header>
<div class="container-xl site-layout">
    <aside class="sidebar" aria-label="Navigatie en zoeken">
        <p class="eyebrow">LEREN & ONTDEKKEN</p>
        <nav aria-label="Hoofdnavigatie">
            <a class="nav-item" href="index.php" <?= !$catalogue && $page === 'README.md' ? 'aria-current="page"' : '' ?>>Startpagina</a>
            <a class="nav-item" href="index.php?browse=1" <?= $catalogue ? 'aria-current="page"' : '' ?>>Alle lesmaterialen <span class="badge"><?= count($documents) ?></span></a>
            <?php foreach (['bk/bk.md', 'gt/gt.md', 'havo/havo.md', 'openlessen/openlessen.md'] as $entry): ?>
            <?php if (isset($documents[$entry])): ?>
            <a class="nav-item" href="index.php?page=<?= rawurlencode($entry) ?>" <?= !$catalogue && $page === $entry ? 'aria-current="page"' : '' ?>><?= escape($groups[$documents[$entry]['group']] ?? $documents[$entry]['title']) ?></a>
            <?php endif; endforeach; ?>
        </nav>
        <form action="index.php" method="get" role="search" class="search-form">
            <label for="search">Zoek op titel of bestandsnaam</label>
            <input class="form-control" id="search" name="q" type="search" value="<?= escape($query) ?>" placeholder="Bijvoorbeeld: ecologie">
            <button class="btn btn-primary" type="submit">Zoeken</button>
        </form>
        <details class="update-panel">
            <summary>Website bijwerken</summary>
            <p>Haal de nieuwste bestanden op uit Git.</p>
            <form action="index.php" method="post" id="pull-form">
                <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
                <?php if (getenv('BIOLOGIE_ADMIN_PASSWORD')): ?>
                <label for="password">Beheerderswachtwoord</label>
                <input class="form-control" type="password" name="password" id="password" autocomplete="current-password" required>
                <?php endif; ?>
                <button class="btn btn-outline-primary" type="submit" <?= canPull() ? '' : 'disabled' ?>>Git pull</button>
                <?php if (!canPull()): ?><p class="small">De beheerder moet de updatefunctie op deze server inschakelen.</p><?php endif; ?>
                <p id="pull-status" role="status"></p>
            </form>
        </details>
    </aside>
    <main id="inhoud" tabindex="-1">
        <div class="page-heading"><p class="eyebrow">BIOLOGIE / <?= escape($catalogue ? 'BIBLIOTHEEK' : strtoupper($groups[$selected['group'] ?? 'algemeen'] ?? 'LESMATERIAAL')) ?></p></div>
        <?php if ($flash): ?>
        <div class="alert <?= $flash[0] ? 'alert-success' : 'alert-danger' ?>" role="status"><strong><?= $flash[0] ? 'Website bijgewerkt' : 'Bijwerken niet gelukt' ?></strong><pre><?= escape($flash[1]) ?></pre></div>
        <?php endif; ?>
        <div class="card content-card"><div class="card-body">
        <?php if ($catalogue): ?>
            <h1>Alle lesmaterialen</h1>
            <p class="intro"><?= count($matches) ?> bestanden<?= $query !== '' ? ' gevonden voor "' . escape($query) . '"' : '. Nieuwe Markdown-bestanden verschijnen hier automatisch.' ?></p>
            <?php foreach (array_unique(array_column($matches, 'group')) as $group): ?>
            <section class="document-group" aria-label="<?= escape($groups[$group] ?? ucfirst($group)) ?>">
                <h2><?= escape($groups[$group] ?? ucfirst($group)) ?></h2>
                <ul class="document-list">
                <?php foreach ($matches as $path => $item): if ($item['group'] !== $group) continue; ?>
                    <li><a href="index.php?page=<?= rawurlencode($path) ?>"><span><?= escape($item['title']) ?></span><small><?= escape($path) ?></small></a></li>
                <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>
            <?php if (!$matches): ?><p>Geen bestanden gevonden. Probeer een andere zoekterm.</p><?php endif; ?>
        <?php elseif ($selected): ?>
            <article class="markdown"><?= renderMarkdown((string) file_get_contents(contentPath($page)), $page) ?></article>
        <?php else: ?>
            <h1>Pagina niet gevonden</h1><p>Dit Markdown-bestand bestaat niet of is niet beschikbaar.</p><a href="index.php?browse=1">Bekijk alle lesmaterialen</a>
        <?php endif; ?>
        </div></div>
        <footer>Biologie voor Bartiméus <span>Lesmateriaal dat met je meegroeit.</span></footer>
    </main>
</div>
</body>
</html>

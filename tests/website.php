<?php
declare(strict_types=1);
require dirname(__DIR__) . '/website/functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "OK: $message\n";
}

$docs = documents();
check(isset($docs['README.md'], $docs['bk/lessen/t5b1.md']), 'Startpagina en lessen worden ontdekt');
check(str_contains($docs['bk/lessen/t5b1.md']['title'], 'Fotosynthese'), 'Lesnamen bevatten de basisstof');
check(!isset($docs['website/INSTALL.md']), 'Interne documenten blijven buiten de bibliotheek');
check(contentPath('../README.md') === null, 'Paden buiten de projectmap zijn geblokkeerd');
check(contentPath('.git/config') === null, 'Verborgen bestanden zijn geblokkeerd');
check(contentPath('website/functions.php') === null, 'Interne PHP-bestanden zijn geblokkeerd');
check(localUrl('../bk.md', 'bk/lessen/t5b1.md') === 'index.php?page=bk%2Fbk.md', 'Relatieve Markdown-links werken');
check(localUrl('../samenvattingB/T5_ecologie.pdf', 'bk/examens-b/examenBBL.md') === 'index.php?file=bk%2FsamenvattingB%2FT5_ecologie.pdf', 'Relatieve downloads werken');
check(localUrl('https://example.org/test', 'README.md') === 'https://example.org/test', 'Externe links blijven intact');
$html = renderMarkdown("# Titel\n\n<!-- geheim antwoord -->\n\n<script>alert(1)</script>\n\n[Onveilig](javascript:alert(1))\n\n| A | B |\n|---|---|\n| 1 | 2 |", 'README.md');
check(!str_contains($html, 'geheim antwoord'), 'Verborgen commentaren worden verwijderd');
check(!str_contains($html, '<script>') && !str_contains($html, 'href="javascript:'), 'Actieve HTML en onveilige links worden geblokkeerd');
check(str_contains($html, '<table tabindex="0"') && str_contains($html, 'id="titel"'), 'Tabellen en ankerlinks worden gerenderd');
$home = homepageContent((string) file_get_contents(dirname(__DIR__) . '/README.md'));
check(isset($home['sections']['podcasts']) && substr_count($home['sections']['podcasts'], '<a ') === 3, 'De podcastsectie bevat de drie niveaulinks');
check(!str_contains($home['content'], 'Podcasts Brain Bananas'), 'Podcasts staan apart van de startpagina');
foreach (['havo', 'gt', 'bk'] as $level) {
    $url = 'https://www.tastenbraille.com/biologie/' . $level . '.php';
    check(str_contains($home['sections']['podcasts'], $url)
        && !str_contains((string) file_get_contents(dirname(__DIR__) . '/' . $level . '/' . $level . '.md'), $url),
        'Podcastlink voor ' . $level . ' is verplaatst naar de podcastsectie');
}
$adminPassword = getenv('BIOLOGIE_ADMIN_PASSWORD');
$bkSource = (string) file_get_contents(dirname(__DIR__) . '/bk/bk.md');
$bkHtml = renderMarkdown($bkSource, 'bk/bk.md');
foreach (['samenvattingB', 'samenvattingK', 'antwoordenbb', 'antwoordenk', 'examens-b', 'examens-k', 'begrippenbb', 'begrippenk'] as $folder) {
    foreach (glob(dirname(__DIR__) . '/bk/' . $folder . '/*') as $file) {
        if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['pdf', 'docx', 'md'], true)) {
            continue;
        }
        $url = localUrl($folder . '/' . basename($file), 'bk/bk.md');
        check(str_contains($bkHtml, 'href="' . escape($url) . '"'), 'BK-bestand is zichtbaar gekoppeld: ' . $folder . '/' . basename($file));
    }
}
preg_match_all('/href="(index\\.php\\?file=[^"]+)"/', $bkHtml, $bkDownloads);
foreach ($bkDownloads[1] as $url) {
    parse_str((string) parse_url(html_entity_decode($url), PHP_URL_QUERY), $parameters);
    check(contentPath($parameters['file']) !== null, 'BK-download bestaat: ' . $parameters['file']);
}
putenv('BIOLOGIE_ADMIN_PASSWORD');
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
check(!canPull(), 'Git pull is zonder wachtwoord geblokkeerd voor externe bezoekers');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
check(canPull(), 'Lokaal beheer is beschikbaar');
putenv('BIOLOGIE_ADMIN_PASSWORD=test-only-password');
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
check(canPull(), 'Extern beheer kan met een serverwachtwoord worden ingeschakeld');
putenv($adminPassword === false ? 'BIOLOGIE_ADMIN_PASSWORD' : 'BIOLOGIE_ADMIN_PASSWORD=' . $adminPassword);
foreach ($docs as $path => $item) {
    if (renderMarkdown((string) file_get_contents(contentPath($path)), $path) === '') {
        throw new RuntimeException('Lege weergave: ' . $path);
    }
}
check(true, 'Alle ' . count($docs) . ' Markdown-bestanden renderen zonder fouten');

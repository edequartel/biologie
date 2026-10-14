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
check(isset($docs['README.md'], $docs['b/lessen/t5b1.md'], $docs['b/b.md'], $docs['k/k.md']), 'Startpagina, Basis, Kader en lessen worden ontdekt');
check(str_contains($docs['b/lessen/t5b1.md']['title'], 'Fotosynthese'), 'Lesnamen bevatten de basisstof');
check(!isset($docs['website/INSTALL.md']), 'Interne documenten blijven buiten de bibliotheek');
check(contentPath('../README.md') === null, 'Paden buiten de projectmap zijn geblokkeerd');
check(contentPath('.git/config') === null, 'Verborgen bestanden zijn geblokkeerd');
check(contentPath('website/functions.php') === null, 'Interne PHP-bestanden zijn geblokkeerd');
check(localUrl('../b.md', 'b/lessen/t5b1.md') === 'index.php?page=b%2Fb.md', 'Relatieve Markdown-links werken');
check(localUrl('../samenvattingen/T5_ecologie.pdf', 'b/examens/examenBBL.md') === 'index.php?file=b%2Fsamenvattingen%2FT5_ecologie.pdf', 'Relatieve downloads werken');
check(contentPath('bk/lessen/t5b1.md') === contentPath('b/lessen/t5b1.md'), 'Oude BK-leslinks blijven werken');
check(contentPath('bk/samenvattingK/T3_Erfelijkheid_en_evolutie.pdf') === contentPath('k/samenvattingen/T3_Erfelijkheid_en_evolutie.pdf'), 'Oude Kader-downloadlinks blijven werken');
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
foreach (['b', 'k'] as $level) {
    $document = $level . '/' . $level . '.md';
    $html = renderMarkdown((string) file_get_contents(contentPath($document)), $document);
    check(str_contains($home['content'], 'index.php?page=' . rawurlencode($document)), 'Niveau staat op de startpagina: ' . $level);
    foreach (['samenvattingen', 'antwoorden', 'examens', 'begrippen'] as $folder) {
        $count = 0;
        foreach (glob(dirname(__DIR__) . '/' . $level . '/' . $folder . '/*') as $file) {
            if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['pdf', 'docx', 'md'], true)) {
                continue;
            }
            $url = localUrl($folder . '/' . basename($file), $document);
            if (!str_contains($html, 'href="' . escape($url) . '"')) {
                throw new RuntimeException('Bestand ontbreekt in overzicht: ' . $file);
            }
            $count++;
        }
        check($count > 0, 'Alle bestanden gekoppeld: ' . $level . '/' . $folder . ' (' . $count . ')');
    }
    preg_match_all('/href="(index\\.php\\?(?:file|page)=[^"]+)"/', $html, $links);
    foreach ($links[1] as $url) {
        parse_str((string) parse_url(html_entity_decode($url), PHP_URL_QUERY), $parameters);
        $target = $parameters['file'] ?? $parameters['page'];
        if (contentPath($target) === null) {
            throw new RuntimeException('Ontbrekend bestand in ' . $document . ': ' . $target);
        }
    }
    check(true, 'Alle lokale links werken voor ' . $level);
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

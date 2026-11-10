<?php
declare(strict_types=1);

function renderPage(array $query): DOMXPath
{
    $code = '$_GET = ' . var_export($query, true) . ';'
        . '$_SERVER["REQUEST_METHOD"] = "GET";'
        . '$_SERVER["REMOTE_ADDR"] = "127.0.0.1";'
        . 'require ' . var_export(dirname(__DIR__) . '/index.php', true) . ';';
    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Kan pagina niet renderen.');
    }
    $html = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || $errors !== '') {
        throw new RuntimeException('Renderfout: ' . $errors);
    }
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($dom);
}

foreach ([
    'b/lessen/t8b1.md' => 'b/b.md',
    'k/lessen/t9b1.md' => 'k/k.md',
    'gt/lessen/t10b1.md' => 'gt/gt.md',
    'havo/lessen/t9b1.md' => 'havo/havo.md',
    'openlessen/vogels-herkennen.md' => 'openlessen/openlessen.md',
    'bk/lessen/t5b1.md' => 'b/b.md',
] as $page => $overview) {
    $xpath = renderPage(['page' => $page]);
    foreach (['//aside', '//nav', '//form', '//*[@id="search"]', '//*[@id="pull-form"]'] as $selector) {
        if ($xpath->query($selector)->length !== 0) {
            throw new RuntimeException('Ongewenste navigatie in les: ' . $page . ' (' . $selector . ')');
        }
    }
    foreach (['//main[@id="inhoud"]', '//main//article', '//*[@id="text-size"]', '//*[@id="contrast"]', '//*[@id="theme"]', '//a[@href="#inhoud"]'] as $selector) {
        if ($xpath->query($selector)->length !== 1) {
            throw new RuntimeException('Lesinhoud of leesinstelling ontbreekt: ' . $page . ' (' . $selector . ')');
        }
    }
    if ($xpath->query('//main//article//h1')->length < 1) {
        throw new RuntimeException('Lestitel ontbreekt: ' . $page);
    }
    $return = $xpath->query('//p[@class="lesson-return"]/a');
    if ($return->length !== 1 || $return->item(0)->getAttribute('href') !== 'index.php?page=' . rawurlencode($overview)) {
        throw new RuntimeException('Onjuiste teruglink: ' . $page);
    }
    echo 'OK: Les zonder zijmenu, met leesinstellingen en teruglink: ' . $page . "\n";
}

foreach ([[], ['page' => 'b/b.md'], ['page' => 'k/k.md'], ['page' => 'openlessen/openlessen.md'], ['section' => 'algemeen'], ['page' => 'b/lessen/t8b1.md', 'q' => 'ecologie']] as $query) {
    $xpath = renderPage($query);
    if ($xpath->query('//aside/nav')->length !== 1 || $xpath->query('//*[@id="pull-form"]')->length !== 1 || $xpath->query('//p[@class="lesson-return"]')->length !== 0) {
        throw new RuntimeException('Navigatie ontbreekt op overzicht of zoekpagina.');
    }
}
echo "OK: Overzichten en zoekpagina houden hun navigatie.\n";

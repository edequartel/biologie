<?php
declare(strict_types=1);
require dirname(__DIR__) . '/website/functions.php';

$count = 0;
foreach ([['b', 8], ['k', 9], ['gt', 10], ['havo', 5], ['havo', 9]] as [$level, $theme]) {
    for ($number = 1; $number <= 6; $number++) {
        $page = $level . '/lessen/t' . $theme . 'b' . $number . '.md';
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
        $lastHeading = $xpath->query('//h4')->item($xpath->query('//h4')->length - 1);
        if ($lastHeading->getAttribute('id') !== 'tekstbeschrijving-van-de-afbeelding') {
            throw new RuntimeException('Afbeeldingsbeschrijving staat niet onderaan: ' . $page);
        }
        $count++;
    }
}
echo "OK: $count basisstoflessen met een geldige uitlegafbeelding, alt-tekst en tekstbeschrijving onderaan.\n";

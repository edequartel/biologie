<?php
// Router for the PHP development server. Only public assets bypass index.php.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (in_array($path, ['/assets/site.css', '/assets/lesdoelen.css', '/assets/site.js', '/assets/theme.js'], true)) {
    return false;
}
if (in_array($path, ['/lesvoorbereiding/', '/lesvoorbereiding/index.php'], true)) {
    require __DIR__ . '/lesvoorbereiding/index.php';
    return;
}
if ($path === '/lesvoorbereiding') {
    $query = http_build_query($_GET);
    header('Location: /lesvoorbereiding/' . ($query === '' ? '' : '?' . $query));
    return;
}
if ($path !== '/' && $path !== '/index.php') {
    http_response_code(404);
    exit('Pagina niet gevonden.');
}
require __DIR__ . '/index.php';

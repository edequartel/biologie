<?php
// Router for the PHP development server. Only public assets bypass index.php.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (in_array($path, ['/assets/site.css', '/assets/site.js'], true)) {
    return false;
}
if ($path !== '/' && $path !== '/index.php') {
    http_response_code(404);
    exit('Pagina niet gevonden.');
}
require __DIR__ . '/index.php';

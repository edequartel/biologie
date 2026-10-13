<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/Parsedown.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function contentPath(string $path): ?string
{
    $root = dirname(__DIR__);
    $parts = explode('/', str_replace('\\', '/', $path));
    foreach ($parts as $part) {
        if (str_starts_with($part, '.') && $part !== '..' && $part !== '.') {
            return null;
        }
    }
    $resolved = realpath($root . '/' . $path);
    if ($resolved === false || !str_starts_with($resolved, $root . '/') || !is_file($resolved)) {
        return null;
    }
    $relative = substr($resolved, strlen($root) + 1);
    if (preg_match('~(?:^|/)\.~', $relative) || preg_match('~^(website|assets|tests)/~', $relative)) {
        return null;
    }
    return $resolved;
}

function documents(): array
{
    $root = dirname(__DIR__);
    $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator($directory, static function ($file): bool {
        return !str_starts_with($file->getFilename(), '.')
            && !in_array($file->getFilename(), ['website', 'assets', 'tests', 'node_modules', 'vendor'], true)
            && !$file->isLink();
    });
    $documents = [];
    foreach (new RecursiveIteratorIterator($filter) as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
            continue;
        }
        $path = substr($file->getPathname(), strlen($root) + 1);
        $source = preg_replace('/<!--.*?(?:-->|$)/s', '', (string) file_get_contents($file->getPathname()));
        preg_match('/^#\s+(.+)$/m', $source, $match);
        $title = isset($match[1]) ? trim($match[1]) : pathinfo($path, PATHINFO_FILENAME);
        if (preg_match('/^##\s+(Basisstof\s+.+)$/mi', $source, $basisstof)) {
            $title .= ' - ' . trim($basisstof[1]);
        }
        $documents[$path] = ['title' => $title, 'group' => str_contains($path, '/') ? explode('/', $path)[0] : 'algemeen'];
    }
    uksort($documents, 'strnatcasecmp');
    return $documents;
}

function localUrl(string $url, string $document): string
{
    if ($url === '' || $url[0] === '#' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $url)) {
        return $url;
    }
    $parts = parse_url($url);
    if ($parts === false) {
        return '#';
    }
    $path = rawurldecode($parts['path'] ?? '');
    $path = str_starts_with($path, '/') ? ltrim($path, '/') : dirname($document) . '/' . $path;
    $resolved = contentPath($path);
    if ($resolved !== null) {
        $path = substr($resolved, strlen(dirname(__DIR__)) + 1);
    }
    $key = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'md' ? 'page' : 'file';
    return 'index.php?' . $key . '=' . rawurlencode($path)
        . (isset($parts['fragment']) ? '#' . rawurlencode(rawurldecode($parts['fragment'])) : '');
}

function renderMarkdown(string $source, string $document): string
{
    // Hidden teacher answers and notes must never be included in the response.
    $source = preg_replace('/<!--.*?(?:-->|$)/s', '', $source);
    $parser = new Parsedown();
    $parser->setSafeMode(true);
    $html = $parser->text($source);
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="markdown-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    foreach (['a' => 'href', 'img' => 'src'] as $tag => $attribute) {
        foreach ($dom->getElementsByTagName($tag) as $node) {
            $node->setAttribute($attribute, localUrl($node->getAttribute($attribute), $document));
            if ($tag === 'img') {
                $node->setAttribute('loading', 'lazy');
            }
        }
    }
    foreach ($dom->getElementsByTagName('table') as $table) {
        $table->setAttribute('tabindex', '0');
        $table->setAttribute('aria-label', 'Tabel; gebruik de pijltjestoetsen om horizontaal te scrollen');
    }
    $ids = [];
    foreach ((new DOMXPath($dom))->query('//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
        $slug = mb_strtolower(trim($heading->textContent), 'UTF-8');
        $slug = preg_replace('/[^\p{L}\p{N}_ -]/u', '', $slug);
        $slug = str_replace(' ', '-', $slug) ?: 'onderdeel';
        $count = $ids[$slug] ?? 0;
        $ids[$slug] = $count + 1;
        $heading->setAttribute('id', $slug . ($count ? '-' . $count : ''));
    }
    $output = '';
    foreach ($dom->getElementById('markdown-root')->childNodes as $node) {
        $output .= $dom->saveHTML($node);
    }
    return $output;
}

function homepageContent(string $source): array
{
    $html = renderMarkdown($source, 'README.md');
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="home-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $sections = array_fill_keys(['links', 'algemeen', 'werkwijze', 'toets', 'video', 'buiten'], '');
    // Snapshot the headings because removing nodes changes the live DOM list.
    foreach (iterator_to_array($dom->getElementsByTagName('h2')) as $heading) {
        $key = mb_strtolower(trim($heading->textContent), 'UTF-8');
        if (!array_key_exists($key, $sections)) {
            continue;
        }
        $node = $heading->nextSibling;
        $heading->parentNode->removeChild($heading);
        while ($node && !in_array(strtolower($node->nodeName), ['h1', 'h2'], true)) {
            $next = $node->nextSibling;
            $sections[$key] .= $dom->saveHTML($node);
            $node->parentNode->removeChild($node);
            $node = $next;
        }
    }
    $content = '';
    foreach ($dom->getElementById('home-root')->childNodes as $node) {
        $content .= $dom->saveHTML($node);
    }
    return ['content' => $content, 'sections' => $sections];
}

function canPull(): bool
{
    return (bool) getenv('BIOLOGIE_ADMIN_PASSWORD')
        || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

function pullRepository(): array
{
    if (!function_exists('proc_open')) {
        return [false, 'De server staat het uitvoeren van Git niet toe.'];
    }
    $lock = fopen(sys_get_temp_dir() . '/biologie-pull-' . hash('sha256', dirname(__DIR__)) . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        return [false, 'Er wordt al een update uitgevoerd. Probeer het later opnieuw.'];
    }
    $pipes = [];
    $process = proc_open(['git', '-c', 'core.hooksPath=/dev/null', 'pull', '--ff-only'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__),
        array_merge(getenv(), ['GIT_TERMINAL_PROMPT' => '0', 'GIT_SSH_COMMAND' => 'ssh -o BatchMode=yes -o ConnectTimeout=10']));
    if (!is_resource($process)) {
        fclose($lock);
        return [false, 'Git kon niet worden gestart.'];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $output = '';
    $deadline = microtime(true) + 30;
    do {
        $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $output = substr($output, -16000);
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        usleep(100000);
    } while (microtime(true) < $deadline);
    if ($status['running']) {
        proc_terminate($process, 9);
        $output .= "\nDe update duurde te lang en is gestopt.";
    }
    $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    fclose($lock);
    return [!$status['running'] && $status['exitcode'] === 0, trim($output)];
}

<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

$configPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/supabase_config.php';

echo "Config path:\n$configPath\n\n";

if (!file_exists($configPath)) {
    exit("ERROR: Config file not found\n");
}

$config = require $configPath;

$url = $config['SUPABASE_URL'] ?? '';

echo "SUPABASE_URL:\n$url\n\n";

if ($url === '') {
    exit("ERROR: SUPABASE_URL is empty\n");
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    exit("ERROR: Invalid URL format\n");
}

$parts = parse_url($url);
$host = strtolower($parts['host'] ?? '');

echo "Host:\n$host\n\n";

$endsWithSupabase = substr($host, -strlen('.supabase.co')) === '.supabase.co';

if ($endsWithSupabase && $host !== 'broadcast.supabase.co') {
    echo "RESULT: SUPABASE_URL looks VALID\n";
} else {
    echo "RESULT: SUPABASE_URL looks INVALID\n";
    echo "Expected format: https://YOURPROJECTREF.supabase.co\n";
}
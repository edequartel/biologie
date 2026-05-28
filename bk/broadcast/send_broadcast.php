<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$config = require dirname(__DIR__, 3) . '/private/supabase_config.php';

$sessionCode = $_POST['session'] ?? '';
$scriptId = $_POST['script_id'] ?? '';

if ($sessionCode === '' || $scriptId === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'session en script_id zijn verplicht'
    ]);
    exit;
}

$projectUrl = rtrim($config['SUPABASE_URL'], '/');
$serviceKey = $config['SUPABASE_SERVICE_ROLE_KEY'];

$url = $projectUrl . '/realtime/v1/api/broadcast';

$body = [
    'messages' => [
        [
            'topic' => 'braillestudio-' . $sessionCode,
            'event' => 'start-script',
            'payload' => [
                'scriptId' => $scriptId,
                'sentAt' => date('c')
            ]
        ]
    ]
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'apikey: ' . $serviceKey,
        'Authorization: Bearer ' . $serviceKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($body),
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $error
    ]);
    exit;
}

http_response_code($status ?: 200);

echo json_encode([
    'ok' => $status >= 200 && $status < 300,
    'status' => $status,
    'response' => json_decode($response, true) ?? $response
]);
<?php
declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
foreach (['/config/', '/includes/', '/database/', '/java/', '/storage/', '/.vscode/'] as $blocked) {
    if (str_starts_with($uri, $blocked)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

if ($uri !== '/' && $uri !== '') {
    $file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';

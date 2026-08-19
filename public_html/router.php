<?php
// router.php for local development with php -S

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// 1. Serve static files if they exist
if (file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    if ($uri === '/') {
        // Fall through to index.html if it exists, or let it continue
    } else {
        // Prevent access to /api/ physical files (mimic .htaccess)
        if (strpos($uri, '/api/') === 0) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Access Forbidden';
            return true;
        }
        return false; // serve the requested resource as-is
    }
}

if ($uri === '/' && file_exists(__DIR__ . '/index.html')) {
    return false; // serve index.html
}

// 2. Custom route for /portal/SLUG
if (preg_match('/^\/portal\/[^\/]+\/?$/', $uri)) {
    $_SERVER['REQUEST_URI'] = '/portal.html';
    include __DIR__ . '/portal.html';
    return true;
}

// 3. Fallback to index.php (CodeIgniter 4)
$_SERVER['SCRIPT_NAME'] = '/index.php';
include __DIR__ . '/index.php';

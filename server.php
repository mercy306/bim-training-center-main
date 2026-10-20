<?php
/**
 * BSD BIM Training Center - Development Server Router
 * Usage: php -S localhost:3000 server.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$fullPath = __DIR__ . $uri;

// 1. API endpoint rewrites
if ($uri === '/api/save-videos') {
    require __DIR__ . '/api/save-videos.php';
    return true;
}

if ($uri === '/api/upload-video') {
    require __DIR__ . '/api/upload-video.php';
    return true;
}

if ($uri === '/api/auth') {
    require __DIR__ . '/api/auth.php';
    return true;
}

if ($uri === '/api/users' || strpos($uri, '/api/users.php') === 0) {
    require __DIR__ . '/api/users.php';
    return true;
}

if ($uri === '/api/progress' || strpos($uri, '/api/progress.php') === 0) {
    require __DIR__ . '/api/progress.php';
    return true;
}

if ($uri === '/api/analytics' || strpos($uri, '/api/analytics.php') === 0) {
    require __DIR__ . '/api/analytics.php';
    return true;
}

if ($uri === '/api/courses' || strpos($uri, '/api/courses.php') === 0) {
    require __DIR__ . '/api/courses.php';
    return true;
}

if ($uri === '/api/stream' || strpos($uri, '/api/stream.php') === 0) {
    require __DIR__ . '/api/stream.php';
    return true;
}

// 2. Direct video file streaming via Range headers
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
if (in_array($ext, ['mp4', 'webm', 'ogg', 'mov'])) {
    $_GET['file'] = ltrim($uri, '/');
    require __DIR__ . '/api/stream.php';
    return true;
}

// 3. Static files: CSS, JS, Images, Fonts, JSON, and PHP scripts
if ($uri !== '/' && file_exists($fullPath)) {
    if (is_dir($fullPath)) {
        if (file_exists($fullPath . '/index.php')) {
            require $fullPath . '/index.php';
            return true;
        }
    } elseif (is_file($fullPath)) {
        if (pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
            require $fullPath;
            return true;
        }
        return false; // Let built-in PHP server serve static asset
    }
}

// 4. Default entry point: index.php
require __DIR__ . '/index.php';
return true;

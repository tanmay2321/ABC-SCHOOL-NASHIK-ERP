<?php
/**
 * Bellwether International School Nandurbar ERP - Built-in Server Router & Security Guard
 * Used by PHP built-in server: php -S localhost:8000 router.php
 * Protects database files, credentials, and prevents direct script execution in uploads.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rawurldecode($uri);
$requestedFile = __DIR__ . $uri;

// Normalize path separators
$requestedFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $requestedFile);
$rootDir = realpath(__DIR__);

// 1. Prohibit directory traversal attacks
$realPath = realpath($requestedFile);
if ($realPath !== false && strpos($realPath, $rootDir) !== 0) {
    http_response_code(403);
    echo "<h1>403 Forbidden</h1><p>Directory traversal is strictly prohibited.</p>";
    exit;
}

// 2. Block access to sensitive files (SQLite DB, .env, .git, .bat, etc.)
$blockedExtensions = ['sqlite', 'sqlite3', 'db', 'env', 'bat', 'sh', 'log', 'ini', 'md'];
$pathInfo = pathinfo($uri);
$extension = strtolower($pathInfo['extension'] ?? '');
$basename = strtolower($pathInfo['basename'] ?? '');

if (
    in_array($extension, $blockedExtensions, true) ||
    $basename === 'database.sqlite' ||
    strpos($uri, '/.git') === 0 ||
    strpos($basename, '.') === 0 // Hidden files (.gitignore, .htaccess, etc.)
) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;">' .
         '<h1 style="color:#b91c1c;">403 Forbidden</h1><p>Direct access to this resource is prohibited for security reasons.</p></body></html>';
    exit;
}

// 3. Prohibit direct execution of PHP files inside uploads directory
if (strpos($uri, '/uploads/') === 0 && $extension === 'php') {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;">' .
         '<h1 style="color:#b91c1c;">403 Forbidden</h1><p>Executing scripts inside the uploads directory is prohibited.</p></body></html>';
    exit;
}

// 4. Handle directory requests (e.g. / or /admin/ or /teacher/ or /app/)
if (is_dir($requestedFile)) {
    $indexFile = rtrim($requestedFile, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
    if (file_exists($indexFile)) {
        require $indexFile;
        return true;
    }
    $indexHtml = rtrim($requestedFile, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
    if (file_exists($indexHtml)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($indexHtml);
        return true;
    }
}

// 5. If it's an existing file:
if (file_exists($requestedFile) && !is_dir($requestedFile)) {
    // If it's a PHP file, execute it
    if ($extension === 'php') {
        require $requestedFile;
        return true;
    }
    // Otherwise, let PHP's built-in server serve the static file natively (css, js, images, pdf, etc.)
    return false;
}

// 6. Support extension-less URLs (e.g. /login -> /login.php)
$phpFile = $requestedFile . '.php';
if (file_exists($phpFile) && !is_dir($phpFile)) {
    require $phpFile;
    return true;
}

// 7. Not found
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;">' .
     '<h1 style="color:#334155;">404 Not Found</h1><p>The requested page does not exist.</p>' .
     '<a href="/" style="color:#1e40af;text-decoration:none;font-weight:bold;">&larr; Back to School Home</a></body></html>';
return true;

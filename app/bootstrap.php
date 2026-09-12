<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

$localConfig = __DIR__ . '/config.local.php';
$config = is_file($localConfig) ? require $localConfig : require __DIR__ . '/config.example.php';

function config(string $key, mixed $default = null): mixed
{
    global $config;
    return $config[$key] ?? $default;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        config('db_host', 'localhost'),
        config('db_name')
    );

    $pdo = new PDO($dsn, (string) config('db_user'), (string) config('db_password'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_category_name (name),
            INDEX idx_category_order (is_active, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS videos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_code VARCHAR(100) DEFAULT NULL,
            category_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            brand VARCHAR(120) DEFAULT NULL,
            shipping_text VARCHAR(160) DEFAULT NULL,
            price BIGINT UNSIGNED NOT NULL DEFAULT 0,
            stock_remaining INT UNSIGNED NOT NULL DEFAULT 0,
            stock_total INT UNSIGNED NOT NULL DEFAULT 0,
            timer_end DATETIME DEFAULT NULL,
            video_path VARCHAR(500) NOT NULL,
            poster_path VARCHAR(500) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category_id (category_id),
            INDEX idx_active_order (is_active, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $categoryColumn = $pdo->query("SHOW COLUMNS FROM videos LIKE 'category_id'")->fetch();
    if (!$categoryColumn) {
        $pdo->exec("ALTER TABLE videos ADD COLUMN category_id BIGINT UNSIGNED DEFAULT NULL AFTER product_code, ADD INDEX idx_category_id (category_id)");
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS comments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            video_id BIGINT UNSIGNED NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            body VARCHAR(1000) NOT NULL,
            is_approved TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_video (video_id, is_approved, id),
            CONSTRAINT fk_comments_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $done = true;
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function requireAdmin(): void
{
    if (empty($_SESSION['eways_admin'])) {
        jsonResponse(['ok' => false, 'message' => 'نیاز به ورود مدیر دارید.'], 401);
    }
}

function publicUrl(string $absolutePath): string
{
    $publicRoot = realpath(__DIR__ . '/../public') ?: (__DIR__ . '/../public');
    $relative = str_replace('\\', '/', substr($absolutePath, strlen($publicRoot)));
    return '/' . ltrim($relative, '/');
}

function deleteMediaFile(?string $url): void
{
    if (!$url || !str_starts_with($url, '/media/')) {
        return;
    }
    $path = realpath(__DIR__ . '/../public' . $url);
    $mediaRoot = realpath(__DIR__ . '/../public/media');
    if ($path && $mediaRoot && str_starts_with($path, $mediaRoot . DIRECTORY_SEPARATOR) && is_file($path)) {
        unlink($path);
    }
}

function saveUpload(array $file, string $type): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('بارگذاری فایل کامل نشد.');
    }

    $isVideo = $type === 'video';
    $limit = $isVideo ? (int) config('max_video_bytes', 134217728) : 8388608;
    if (($file['size'] ?? 0) > $limit) {
        throw new RuntimeException($isVideo ? 'حجم ویدئو بیشتر از ۱۲۸ مگابایت است.' : 'حجم کاور بیشتر از ۸ مگابایت است.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = $isVideo
        ? ['video/mp4' => 'mp4', 'application/mp4' => 'mp4']
        : ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException($isVideo ? 'فقط ویدئوی MP4 مجاز است.' : 'کاور باید JPG، PNG یا WebP باشد.');
    }

    $folder = $isVideo ? 'videos' : 'posters';
    $targetDir = __DIR__ . '/../public/media/' . $folder;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('پوشه فایل قابل ایجاد نیست.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $target = $targetDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('ذخیره فایل روی هاست انجام نشد.');
    }
    chmod($target, 0644);
    return publicUrl(realpath($target) ?: $target);
}

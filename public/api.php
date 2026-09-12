<?php

declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($action === 'videos' && $method === 'GET') {
        $rows = db()->query(
            "SELECT v.*,
                (SELECT COUNT(*) FROM comments c WHERE c.video_id = v.id AND c.is_approved = 1) AS comments_count
             FROM videos v
             WHERE v.is_active = 1
             ORDER BY v.sort_order ASC, v.id DESC"
        )->fetchAll();
        jsonResponse(['ok' => true, 'videos' => $rows]);
    }

    if ($action === 'comments' && $method === 'GET') {
        $videoId = filter_input(INPUT_GET, 'video_id', FILTER_VALIDATE_INT);
        $stmt = db()->prepare("SELECT id, display_name, body, created_at FROM comments WHERE video_id = ? AND is_approved = 1 ORDER BY id DESC LIMIT 100");
        $stmt->execute([$videoId]);
        jsonResponse(['ok' => true, 'comments' => $stmt->fetchAll()]);
    }

    if ($action === 'comments' && $method === 'POST') {
        $data = requestData();
        $videoId = filter_var($data['video_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($data['display_name'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if (!$videoId || mb_strlen($name) < 2 || mb_strlen($body) < 2) {
            jsonResponse(['ok' => false, 'message' => 'نام و متن کامنت را کامل وارد کنید.'], 422);
        }
        $stmt = db()->prepare("INSERT INTO comments (video_id, display_name, body) VALUES (?, ?, ?)");
        $stmt->execute([$videoId, mb_substr($name, 0, 100), mb_substr($body, 0, 1000)]);
        jsonResponse(['ok' => true, 'message' => 'کامنت ثبت شد.']);
    }

    if ($action === 'admin-login' && $method === 'POST') {
        $data = requestData();
        $expectedUser = (string) config('admin_username');
        $expectedPass = (string) config('admin_password');
        if ($expectedPass === '') {
            jsonResponse(['ok' => false, 'message' => 'رمز مدیر هنوز روی سرور تنظیم نشده است.'], 503);
        }
        if (hash_equals($expectedUser, (string) ($data['username'] ?? '')) &&
            hash_equals($expectedPass, (string) ($data['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['eways_admin'] = true;
            jsonResponse(['ok' => true]);
        }
        jsonResponse(['ok' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است.'], 401);
    }

    if ($action === 'admin-logout' && $method === 'POST') {
        $_SESSION = [];
        session_destroy();
        jsonResponse(['ok' => true]);
    }

    if ($action === 'admin-status' && $method === 'GET') {
        jsonResponse(['ok' => true, 'authenticated' => !empty($_SESSION['eways_admin'])]);
    }

    if ($action === 'admin-videos' && $method === 'GET') {
        requireAdmin();
        $rows = db()->query("SELECT * FROM videos ORDER BY sort_order ASC, id DESC")->fetchAll();
        jsonResponse(['ok' => true, 'videos' => $rows]);
    }

    if ($action === 'admin-save' && $method === 'POST') {
        requireAdmin();
        $data = requestData();
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            jsonResponse(['ok' => false, 'message' => 'عنوان محصول اجباری است.'], 422);
        }

        $existing = null;
        if ($id) {
            $stmt = db()->prepare("SELECT * FROM videos WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing) {
                jsonResponse(['ok' => false, 'message' => 'ویدئو پیدا نشد.'], 404);
            }
        }

        $videoPath = $existing['video_path'] ?? null;
        $posterPath = $existing['poster_path'] ?? null;
        if (!empty($_FILES['video_file']['name'])) {
            $newVideo = saveUpload($_FILES['video_file'], 'video');
            deleteMediaFile($videoPath);
            $videoPath = $newVideo;
        }
        if (!empty($_FILES['poster_file']['name'])) {
            $newPoster = saveUpload($_FILES['poster_file'], 'poster');
            deleteMediaFile($posterPath);
            $posterPath = $newPoster;
        }
        if (!empty($data['remove_poster'])) {
            deleteMediaFile($posterPath);
            $posterPath = null;
        }
        if (!$videoPath) {
            jsonResponse(['ok' => false, 'message' => 'انتخاب فایل ویدئو اجباری است.'], 422);
        }

        $values = [
            trim((string) ($data['product_code'] ?? '')) ?: null,
            $title,
            trim((string) ($data['description'] ?? '')) ?: null,
            trim((string) ($data['brand'] ?? '')) ?: null,
            trim((string) ($data['shipping_text'] ?? '')) ?: null,
            max(0, (int) ($data['price'] ?? 0)),
            max(0, (int) ($data['stock_remaining'] ?? 0)),
            max(0, (int) ($data['stock_total'] ?? 0)),
            !empty($data['timer_end']) ? date('Y-m-d H:i:s', strtotime((string) $data['timer_end'])) : null,
            $videoPath,
            $posterPath,
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $stmt = db()->prepare(
                "UPDATE videos SET product_code=?, title=?, description=?, brand=?, shipping_text=?, price=?,
                 stock_remaining=?, stock_total=?, timer_end=?, video_path=?, poster_path=?, sort_order=?, is_active=? WHERE id=?"
            );
            $values[] = $id;
            $stmt->execute($values);
        } else {
            $stmt = db()->prepare(
                "INSERT INTO videos (product_code,title,description,brand,shipping_text,price,stock_remaining,stock_total,timer_end,video_path,poster_path,sort_order,is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute($values);
            $id = (int) db()->lastInsertId();
        }
        jsonResponse(['ok' => true, 'id' => $id, 'message' => 'ویدئو ذخیره شد.']);
    }

    if ($action === 'admin-delete' && $method === 'POST') {
        requireAdmin();
        $data = requestData();
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $stmt = db()->prepare("SELECT video_path, poster_path FROM videos WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(['ok' => false, 'message' => 'ویدئو پیدا نشد.'], 404);
        }
        $delete = db()->prepare("DELETE FROM videos WHERE id = ?");
        $delete->execute([$id]);
        deleteMediaFile($row['video_path']);
        deleteMediaFile($row['poster_path']);
        jsonResponse(['ok' => true, 'message' => 'ویدئو حذف شد.']);
    }

    jsonResponse(['ok' => false, 'message' => 'درخواست نامعتبر است.'], 404);
} catch (Throwable $e) {
    error_log($e->getMessage());
    jsonResponse(['ok' => false, 'message' => 'خطایی در ارتباط با سرور رخ داد.'], 500);
}


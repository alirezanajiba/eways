<?php

declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function attachPriceTiers(array &$rows): void
{
    if (!$rows) {
        return;
    }
    $ids = array_map(static fn(array $row): int => (int) $row['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT video_id, min_qty, unit_price FROM price_tiers WHERE video_id IN ($placeholders) ORDER BY min_qty ASC");
    $stmt->execute($ids);
    $grouped = [];
    foreach ($stmt->fetchAll() as $tier) {
        $grouped[(int) $tier['video_id']][] = [
            'min_qty' => (int) $tier['min_qty'],
            'unit_price' => (int) $tier['unit_price'],
        ];
    }
    foreach ($rows as &$row) {
        $row['price_tiers'] = $grouped[(int) $row['id']] ?? [];
    }
    unset($row);
}

function validVisitorToken(mixed $value): ?string
{
    $token = trim((string) $value);
    return preg_match('/^[a-zA-Z0-9_-]{16,80}$/', $token) ? $token : null;
}

function serverTierPrice(PDO $pdo, int $videoId, int $quantity, int $basePrice): int
{
    $stmt = $pdo->prepare("SELECT unit_price FROM price_tiers WHERE video_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1");
    $stmt->execute([$videoId, $quantity]);
    $tierPrice = $stmt->fetchColumn();
    return $tierPrice === false ? $basePrice : (int) $tierPrice;
}

try {
    if ($action === 'catalog' && $method === 'GET') {
        $videos = db()->query(
            "SELECT v.*, cat.name AS category_name,
                (SELECT COUNT(*) FROM comments c WHERE c.video_id = v.id AND c.is_approved = 1) AS comments_count
             FROM videos v
             LEFT JOIN categories cat ON cat.id = v.category_id
             WHERE v.is_active = 1
             ORDER BY v.sort_order ASC, v.id DESC"
        )->fetchAll();
        attachPriceTiers($videos);
        $categories = db()->query(
            "SELECT c.id, c.name, c.icon_key, c.sort_order,
                (SELECT COUNT(*) FROM videos v WHERE v.category_id = c.id AND v.is_active = 1) AS products_count
             FROM categories c
             WHERE c.is_active = 1
             ORDER BY c.sort_order ASC, c.id ASC"
        )->fetchAll();
        jsonResponse(['ok' => true, 'videos' => $videos, 'categories' => $categories]);
    }

    if ($action === 'videos' && $method === 'GET') {
        $rows = db()->query(
            "SELECT v.*, cat.name AS category_name,
                (SELECT COUNT(*) FROM comments c WHERE c.video_id = v.id AND c.is_approved = 1) AS comments_count
             FROM videos v
             LEFT JOIN categories cat ON cat.id = v.category_id
             WHERE v.is_active = 1
             ORDER BY v.sort_order ASC, v.id DESC"
        )->fetchAll();
        attachPriceTiers($rows);
        jsonResponse(['ok' => true, 'videos' => $rows]);
    }

    if ($action === 'categories' && $method === 'GET') {
        $rows = db()->query(
            "SELECT c.id, c.name, c.icon_key, c.sort_order,
                (SELECT COUNT(*) FROM videos v WHERE v.category_id = c.id AND v.is_active = 1) AS products_count
             FROM categories c
             WHERE c.is_active = 1
             ORDER BY c.sort_order ASC, c.id ASC"
        )->fetchAll();
        jsonResponse(['ok' => true, 'categories' => $rows]);
    }

    if ($action === 'track-view' && $method === 'POST') {
        $data = requestData();
        $videoId = filter_var($data['video_id'] ?? null, FILTER_VALIDATE_INT);
        $visitorToken = validVisitorToken($data['visitor_token'] ?? null);
        if (!$videoId || !$visitorToken) {
            jsonResponse(['ok' => false, 'message' => 'اطلاعات بازدید نامعتبر است.'], 422);
        }
        $pdo = db();
        $video = $pdo->prepare("SELECT id FROM videos WHERE id = ? AND is_active = 1");
        $video->execute([$videoId]);
        if (!$video->fetch()) {
            jsonResponse(['ok' => false, 'message' => 'ویدئو پیدا نشد.'], 404);
        }
        $pdo->beginTransaction();
        $stats = $pdo->prepare("INSERT INTO video_stats (video_id, total_views) VALUES (?, 1) ON DUPLICATE KEY UPDATE total_views = total_views + 1");
        $stats->execute([$videoId]);
        $viewer = $pdo->prepare("INSERT IGNORE INTO video_viewers (video_id, visitor_token) VALUES (?, ?)");
        $viewer->execute([$videoId, $visitorToken]);
        $pdo->commit();
        jsonResponse(['ok' => true]);
    }

    if ($action === 'track-save' && $method === 'POST') {
        $data = requestData();
        $videoId = filter_var($data['video_id'] ?? null, FILTER_VALIDATE_INT);
        $visitorToken = validVisitorToken($data['visitor_token'] ?? null);
        if (!$videoId || !$visitorToken) {
            jsonResponse(['ok' => false, 'message' => 'اطلاعات ذخیره نامعتبر است.'], 422);
        }
        if (!empty($data['saved'])) {
            $stmt = db()->prepare("INSERT IGNORE INTO video_saves (video_id, visitor_token) VALUES (?, ?)");
            $stmt->execute([$videoId, $visitorToken]);
        } else {
            $stmt = db()->prepare("DELETE FROM video_saves WHERE video_id = ? AND visitor_token = ?");
            $stmt->execute([$videoId, $visitorToken]);
        }
        jsonResponse(['ok' => true]);
    }

    if ($action === 'order' && $method === 'POST') {
        $data = requestData();
        $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
        $requested = [];
        foreach ($rawItems as $item) {
            $videoId = filter_var($item['video_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($videoId && $quantity && $quantity > 0) {
                $requested[(int) $videoId] = ($requested[(int) $videoId] ?? 0) + (int) $quantity;
            }
        }
        if (!$requested) {
            jsonResponse(['ok' => false, 'message' => 'سبد خرید خالی است.'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();
        $productStmt = $pdo->prepare("SELECT id, title, price, stock_remaining, is_active, timer_end FROM videos WHERE id = ? FOR UPDATE");
        $products = [];
        $shortages = [];
        foreach ($requested as $videoId => $quantity) {
            $productStmt->execute([$videoId]);
            $product = $productStmt->fetch();
            $available = $product && (int) $product['is_active'] === 1 && (empty($product['timer_end']) || strtotime((string) $product['timer_end']) > time())
                ? (int) $product['stock_remaining'] : 0;
            if (!$product || $quantity > $available) {
                $shortages[] = [
                    'video_id' => $videoId,
                    'title' => $product['title'] ?? 'محصول ناموجود',
                    'available' => $available,
                    'requested' => $quantity,
                ];
                continue;
            }
            $unitPrice = serverTierPrice($pdo, $videoId, $quantity, (int) $product['price']);
            $product['quantity'] = $quantity;
            $product['unit_price'] = $unitPrice;
            $product['line_total'] = $unitPrice * $quantity;
            $products[] = $product;
        }
        if ($shortages) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'message' => 'موجودی بعضی محصولات برای ثبت سفارش کافی نیست.', 'shortages' => $shortages], 409);
        }

        $total = array_sum(array_column($products, 'line_total'));
        $orderStmt = $pdo->prepare("INSERT INTO orders (total_amount) VALUES (?)");
        $orderStmt->execute([$total]);
        $orderId = (int) $pdo->lastInsertId();
        $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, video_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
        $stockStmt = $pdo->prepare("UPDATE videos SET stock_remaining = stock_remaining - ? WHERE id = ?");
        foreach ($products as $product) {
            $itemStmt->execute([$orderId, $product['id'], $product['quantity'], $product['unit_price'], $product['line_total']]);
            $stockStmt->execute([$product['quantity'], $product['id']]);
        }
        $pdo->commit();
        jsonResponse(['ok' => true, 'order_id' => $orderId, 'total_amount' => $total, 'message' => 'سفارش با موفقیت ثبت شد.']);
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
        $cleanName = mb_substr($name, 0, 100);
        $cleanBody = mb_substr($body, 0, 1000);
        $stmt = db()->prepare("INSERT INTO comments (video_id, display_name, body) VALUES (?, ?, ?)");
        $stmt->execute([$videoId, $cleanName, $cleanBody]);
        jsonResponse(['ok' => true, 'message' => 'کامنت ثبت شد.', 'comment' => [
            'id' => (int) db()->lastInsertId(),
            'display_name' => $cleanName,
            'body' => $cleanBody,
            'created_at' => date('Y-m-d H:i:s'),
        ]]);
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
        $rows = db()->query(
            "SELECT v.*, c.name AS category_name,
                COALESCE((SELECT s.total_views FROM video_stats s WHERE s.video_id = v.id), 0) AS total_views,
                (SELECT COUNT(*) FROM video_viewers uv WHERE uv.video_id = v.id) AS unique_views,
                COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.video_id = v.id), 0) AS sales_count,
                COALESCE((SELECT SUM(oi.line_total) FROM order_items oi WHERE oi.video_id = v.id), 0) AS sales_amount,
                (SELECT COUNT(*) FROM video_saves vs WHERE vs.video_id = v.id) AS saves_count,
                (SELECT COUNT(*) FROM comments cm WHERE cm.video_id = v.id AND cm.is_approved = 1) AS comments_count
             FROM videos v LEFT JOIN categories c ON c.id = v.category_id
             ORDER BY v.sort_order ASC, v.id DESC"
        )->fetchAll();
        attachPriceTiers($rows);
        jsonResponse(['ok' => true, 'videos' => $rows]);
    }

    if ($action === 'admin-categories' && $method === 'GET') {
        requireAdmin();
        $rows = db()->query(
            "SELECT c.*, (SELECT COUNT(*) FROM videos v WHERE v.category_id = c.id) AS products_count
             FROM categories c ORDER BY c.sort_order ASC, c.id ASC"
        )->fetchAll();
        jsonResponse(['ok' => true, 'categories' => $rows]);
    }

    if ($action === 'admin-category-save' && $method === 'POST') {
        requireAdmin();
        $data = requestData();
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($data['name'] ?? ''));
        $allowedIcons = ['grid','mobile','charger','headphones','speaker','battery','cable','watch','camera','computer','keyboard','mouse','gamepad','car','home','lamp','gift','bag','tools','screen','wifi','memory','printer','audio'];
        $iconKey = in_array((string) ($data['icon_key'] ?? ''), $allowedIcons, true) ? (string) $data['icon_key'] : 'grid';
        if ($name === '') {
            jsonResponse(['ok' => false, 'message' => 'نام دسته بندی اجباری است.'], 422);
        }
        try {
            if ($id) {
                $stmt = db()->prepare("UPDATE categories SET name=?, icon_key=?, sort_order=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $iconKey, (int) ($data['sort_order'] ?? 0), !empty($data['is_active']) ? 1 : 0, $id]);
            } else {
                $stmt = db()->prepare("INSERT INTO categories (name, icon_key, sort_order, is_active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $iconKey, (int) ($data['sort_order'] ?? 0), !empty($data['is_active']) ? 1 : 0]);
                $id = (int) db()->lastInsertId();
            }
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                jsonResponse(['ok' => false, 'message' => 'دسته بندی دیگری با این نام وجود دارد.'], 422);
            }
            throw $e;
        }
        jsonResponse(['ok' => true, 'id' => $id, 'message' => 'دسته بندی ذخیره شد.']);
    }

    if ($action === 'admin-category-delete' && $method === 'POST') {
        requireAdmin();
        $data = requestData();
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            jsonResponse(['ok' => false, 'message' => 'دسته بندی نامعتبر است.'], 422);
        }
        $count = db()->prepare("SELECT COUNT(*) FROM videos WHERE category_id = ?");
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) {
            jsonResponse(['ok' => false, 'message' => 'ابتدا محصولات این دسته بندی را جابجا کنید.'], 422);
        }
        $stmt = db()->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['ok' => true, 'message' => 'دسته بندی حذف شد.']);
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

        $tierMins = (array) ($data['tier_min_qty'] ?? []);
        $tierPrices = (array) ($data['tier_unit_price'] ?? []);
        $priceTiers = [];
        foreach ($tierMins as $index => $minimum) {
            if ($minimum === '' && ($tierPrices[$index] ?? '') === '') {
                continue;
            }
            $minimum = (int) $minimum;
            $unitPrice = (int) ($tierPrices[$index] ?? 0);
            if ($minimum < 1 || $unitPrice < 1) {
                jsonResponse(['ok' => false, 'message' => 'حداقل تعداد و قیمت همه پله ها باید بیشتر از صفر باشد.'], 422);
            }
            $priceTiers[$minimum] = $unitPrice;
        }
        ksort($priceTiers, SORT_NUMERIC);
        $previousTierPrice = max(0, (int) ($data['price'] ?? 0));
        foreach ($priceTiers as $unitPrice) {
            if ($previousTierPrice > 0 && $unitPrice > $previousTierPrice) {
                jsonResponse(['ok' => false, 'message' => 'قیمت هر پله باید از قیمت پله قبلی کمتر یا مساوی باشد.'], 422);
            }
            $previousTierPrice = $unitPrice;
        }

        $categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($categoryId) {
            $categoryCheck = db()->prepare("SELECT id FROM categories WHERE id = ?");
            $categoryCheck->execute([$categoryId]);
            if (!$categoryCheck->fetch()) {
                jsonResponse(['ok' => false, 'message' => 'دسته بندی انتخاب شده معتبر نیست.'], 422);
            }
        }

        $values = [
            trim((string) ($data['product_code'] ?? '')) ?: null,
            $categoryId,
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
                "UPDATE videos SET product_code=?, category_id=?, title=?, description=?, brand=?, shipping_text=?, price=?,
                 stock_remaining=?, stock_total=?, timer_end=?, video_path=?, poster_path=?, sort_order=?, is_active=? WHERE id=?"
            );
            $values[] = $id;
            $stmt->execute($values);
        } else {
            $stmt = db()->prepare(
                "INSERT INTO videos (product_code,category_id,title,description,brand,shipping_text,price,stock_remaining,stock_total,timer_end,video_path,poster_path,sort_order,is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute($values);
            $id = (int) db()->lastInsertId();
        }

        $deleteTiers = db()->prepare("DELETE FROM price_tiers WHERE video_id = ?");
        $deleteTiers->execute([$id]);
        if ($priceTiers) {
            $insertTier = db()->prepare("INSERT INTO price_tiers (video_id, min_qty, unit_price) VALUES (?, ?, ?)");
            foreach ($priceTiers as $minimum => $unitPrice) {
                $insertTier->execute([$id, $minimum, $unitPrice]);
            }
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
        $sales = db()->prepare("SELECT COUNT(*) FROM order_items WHERE video_id = ?");
        $sales->execute([$id]);
        if ((int) $sales->fetchColumn() > 0) {
            jsonResponse(['ok' => false, 'message' => 'این ویدئو سابقه فروش دارد و قابل حذف نیست؛ آن را غیرفعال کنید.'], 422);
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

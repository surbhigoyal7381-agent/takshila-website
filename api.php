<?php
/* Takshila CMS API — credentials injected by GitHub Actions at deploy time */

define('CMS_API_KEY', '%%CMS_API_KEY%%');
define('DB_HOST',     '%%DB_HOST%%');
define('DB_NAME',     '%%DB_NAME%%');
define('DB_USER',     '%%DB_USER%%');
define('DB_PASS',     '%%DB_PASS%%');

header('Content-Type: application/json');
header('Cache-Control: no-store');

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_data (
            id          INT PRIMARY KEY DEFAULT 1,
            data        LONGTEXT NOT NULL,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS contact_enquiries (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(200) NOT NULL,
            parent_name VARCHAR(200) NOT NULL DEFAULT '',
            mobile      VARCHAR(20) NOT NULL,
            email       VARCHAR(200) DEFAULT '',
            class       VARCHAR(50) DEFAULT '',
            course      VARCHAR(100) DEFAULT '',
            message     TEXT DEFAULT '',
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
        exit;
    }
    return $pdo;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/* ── GET contacts: list all enquiries (requires API key) ── */
if ($method === 'GET' && $action === 'contacts') {
    $key = $_SERVER['HTTP_X_CMS_KEY'] ?? '';
    if (!CMS_API_KEY || $key !== CMS_API_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $rows = db()->query("SELECT * FROM contact_enquiries ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
}

/* ── GET: return current CMS data ── */
if ($method === 'GET') {
    $row = db()->query("SELECT data FROM cms_data WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    echo $row ? $row['data'] : 'null';
    exit;
}

/* ── POST contact: save contact form submission (public) ── */
if ($method === 'POST' && $action === 'contact') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        exit;
    }
    $name   = trim($data['name'] ?? '');
    $mobile = trim($data['mobile'] ?? '');
    if (!$name || !$mobile) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and mobile are required']);
        exit;
    }
    $stmt = db()->prepare(
        "INSERT INTO contact_enquiries (name, parent_name, mobile, email, class, course, message)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $name,
        trim($data['parentName'] ?? ''),
        $mobile,
        trim($data['email'] ?? ''),
        trim($data['class'] ?? ''),
        trim($data['course'] ?? ''),
        trim($data['message'] ?? '')
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

/* ── POST: save CMS data (requires API key) ── */
if ($method === 'POST') {
    $key = $_SERVER['HTTP_X_CMS_KEY'] ?? '';
    if (!CMS_API_KEY || $key !== CMS_API_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $body = file_get_contents('php://input');
    if (!$body) { http_response_code(400); echo json_encode(['error' => 'Empty body']); exit; }

    json_decode($body);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $stmt = db()->prepare(
        "INSERT INTO cms_data (id, data) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()"
    );
    $stmt->execute([$body]);
    echo json_encode(['ok' => true]);
    exit;
}

/* ── DELETE contact: remove an enquiry (requires API key) ── */
if ($method === 'DELETE' && $action === 'contact') {
    $key = $_SERVER['HTTP_X_CMS_KEY'] ?? '';
    if (!CMS_API_KEY || $key !== CMS_API_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit; }
    db()->prepare("DELETE FROM contact_enquiries WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

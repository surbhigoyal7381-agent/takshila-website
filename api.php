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
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
        exit;
    }
    return $pdo;
}

$method = $_SERVER['REQUEST_METHOD'];

/* ── GET: return current CMS data ── */
if ($method === 'GET') {
    $row = db()->query("SELECT data FROM cms_data WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    echo $row ? $row['data'] : 'null';
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

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = [];
if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

if (!$body) {
    $body = $_POST;
}

$name = trim((string)($body['name'] ?? ''));
$ipAddress = trim((string)($body['IP_address'] ?? ''));
$switch = (int)($body['switch'] ?? 0);
$fw = trim((string)($body['fw'] ?? 'v1.0'));
$power = (float)($body['power'] ?? 0);
$status = trim((string)($body['status'] ?? '1'));

if ($name === '') {
    $name = 'PLC';
}

if ($ipAddress === '' || !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid IPv4 IP_address is required']);
    exit;
}

if ($switch <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'switch must be a positive number']);
    exit;
}

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$databases = ['plc_management'];

$conn = null;
foreach ($databases as $dbName) {
    try {
        $tmp = new mysqli($host, $user, $pass, $dbName);
        if (!$tmp->connect_errno) {
            $conn = $tmp;
            break;
        }
    } catch (Throwable $e) {
        // Try the next configured database name.
        continue;
    }
}

if (!$conn instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    exit;
}

$conn->set_charset('utf8mb4');

try {
    $sql = 'INSERT INTO PLCdevices (name, IP_address, fw, `switch`, power, status) VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed');
    }
    $stmt->bind_param('sssids', $name, $ipAddress, $fw, $switch, $power, $status);

    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed');
    }

    $savedId = (int)$conn->insert_id;
    echo json_encode(['ok' => true, 'device_id' => $savedId]);
} catch (Throwable $e) {
    $error = 'Failed to insert device';
    $statusCode = 500;
    if ($e instanceof mysqli_sql_exception && (int)$e->getCode() === 1062) {
        $error = 'Device ID already exists. Use another ID or leave it blank.';
        $statusCode = 409;
    }
    http_response_code($statusCode);
    echo json_encode(['ok' => false, 'error' => $error]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
}


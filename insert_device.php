<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/api/db.php';

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
$deviceIdRaw = trim((string)($body['device_id'] ?? ''));
$ipAddress = trim((string)($body['IP_address'] ?? ''));
$switch = (int)($body['switch'] ?? 0);
$fw = trim((string)($body['fw'] ?? ''));
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

$allowedSwitches = [1, 2, 4, 8, 16];
if (!in_array($switch, $allowedSwitches, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'switch must be one of 1, 2, 4, 8, 16']);
    exit;
}

if ($fw === '') {
    $fw = 'v1.0';
}

$status = strtoupper($status);
if (!in_array($status, ['ON', 'OF', '0', '1', 'OFF'], true)) {
    $status = '1';
}

$deviceId = null;
if ($deviceIdRaw !== '') {
    if (!ctype_digit($deviceIdRaw)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'device_id must be an integer']);
        exit;
    }
    $deviceId = (int)$deviceIdRaw;
}

try {
    if ($deviceId !== null) {
        $stmt = db()->prepare('INSERT INTO PLCdevices (device_id, name, IP_address, fw, `switch`, power, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$deviceId, $name, $ipAddress, $fw, $switch, $power, $status]);
        echo json_encode(['ok' => true, 'device_id' => $deviceId]);
        exit;
    }

    $stmt = db()->prepare('INSERT INTO PLCdevices (name, IP_address, fw, `switch`, power, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $ipAddress, $fw, $switch, $power, $status]);
    $savedId = (int)db()->lastInsertId();
    echo json_encode(['ok' => true, 'device_id' => $savedId]);
} catch (Throwable $e) {
    $error = 'Failed to insert device';
    $statusCode = 500;
    if (str_contains(strtolower($e->getMessage()), 'duplicate') || (int)$e->getCode() === 23000) {
        $error = 'Device ID already exists. Use another ID or leave it blank.';
        $statusCode = 409;
    }
    http_response_code($statusCode);
    echo json_encode(['ok' => false, 'error' => $error]);
}


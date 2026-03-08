<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$deviceId = (int)($_GET['device_id'] ?? 0);
if ($deviceId <= 0) {
    json_response(['ok' => false, 'error' => 'device_id required'], 400);
    exit;
}

try {
    $stmt = db()->prepare('SELECT device_id, name, IP_address, fw, switch, power, status FROM PLCdevices WHERE device_id=?');
    $stmt->execute([$deviceId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_response(['ok' => false, 'error' => 'Not found'], 404);
        exit;
    }

    json_response(['ok' => true, 'device' => $row]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to load device'], 500);
}

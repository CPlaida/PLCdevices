<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$body = read_json_body();

$deviceId = (int)($body['device_id'] ?? 0);

if ($deviceId <= 0) {
    json_response(['ok' => false, 'error' => 'device_id required'], 400);
    exit;
}

try {
    // Deprecated endpoint: retained for compatibility with older frontend code.
    $stmt = db()->prepare('SELECT device_id FROM PLCdevices WHERE device_id=?');
    $stmt->execute([$deviceId]);
    $exists = (int)($stmt->fetchColumn() ?: 0);
    if ($exists <= 0) {
        json_response(['ok' => false, 'error' => 'Device not found'], 404);
        exit;
    }
    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to update device state'], 500);
}

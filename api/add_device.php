<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$body = read_json_body();

$ip = trim((string)($body['IP_address'] ?? ''));
$switch = (int)($body['switch'] ?? 0);

if ($ip === '' || $switch <= 0) {
    json_response(['ok' => false, 'error' => 'IP_address and switch are required'], 400);
    exit;
}

$name = (string)($body['name'] ?? 'PLC');
$fw = (string)($body['fw'] ?? '1.0.0');
$power = (float)($body['power'] ?? 0);
$status = (string)($body['status'] ?? 'OF');

try {
    $stmt = db()->prepare('INSERT INTO PLCdevices (name, IP_address, fw, switch, power, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $ip, $fw, $switch, $power, $status]);
    $savedId = (int)db()->lastInsertId();

    json_response(['ok' => true, 'device_id' => $savedId]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to add device'], 500);
}

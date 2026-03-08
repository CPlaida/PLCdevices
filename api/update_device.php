<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$body = read_json_body();

$deviceId = (int)($body['device_id'] ?? 0);
$name = trim((string)($body['name'] ?? ''));
$ip = trim((string)($body['IP_address'] ?? ''));
$switch = (int)($body['switch'] ?? 0);
$power = (float)($body['power'] ?? 0);

if ($deviceId <= 0) {
    json_response(['ok' => false, 'error' => 'device_id required'], 400);
    exit;
}

if ($name === '') {
    $name = 'PLC';
}

if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    json_response(['ok' => false, 'error' => 'Valid IPv4 IP_address is required'], 400);
    exit;
}

$allowedSwitches = [1, 2, 4, 8, 16];
if (!in_array($switch, $allowedSwitches, true)) {
    json_response(['ok' => false, 'error' => 'switch must be one of 1, 2, 4, 8, 16'], 400);
    exit;
}

if (!is_finite($power) || $power < 0) {
    json_response(['ok' => false, 'error' => 'power must be a valid non-negative number'], 400);
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE PLCdevices SET name=?, IP_address=?, `switch`=?, power=? WHERE device_id=?');
    $stmt->execute([$name, $ip, $switch, $power, $deviceId]);

    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT device_id FROM PLCdevices WHERE device_id=?');
        $exists->execute([$deviceId]);
        if (!(int)($exists->fetchColumn() ?: 0)) {
            json_response(['ok' => false, 'error' => 'Device not found'], 404);
            exit;
        }
    }

    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to update device'], 500);
}

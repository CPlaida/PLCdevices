<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$body = read_json_body();

$deviceId = (int)($body['device_id'] ?? 0);
$roomId = (int)($body['room_id'] ?? 0);
$applianceType = trim((string)($body['appliance_type'] ?? 'APPLIANCE'));
$applianceName = trim((string)($body['appliance_name'] ?? $applianceType));
$applianceId = trim((string)($body['appliance_id'] ?? uniqid('A', true)));
$ipaddress = trim((string)($body['ipaddress'] ?? '192.168.1.210'));
$power = (float)($body['power'] ?? 0);
$hp = (float)($body['hp'] ?? 0);
$current = (float)($body['current'] ?? 0);
$status = (string)($body['status'] ?? 'OF');

if ($deviceId <= 0 || $roomId <= 0) {
    json_response(['ok' => false, 'error' => 'device_id and room_id required'], 400);
    exit;
}

try {
    $pdo = db();

    // Validate room belongs to device
    $stmt = $pdo->prepare('SELECT device_id FROM roomdeployment WHERE room_id=?');
    $stmt->execute([$roomId]);
    $ownerDevice = (int)($stmt->fetchColumn() ?: 0);
    if ($ownerDevice !== $deviceId) {
        json_response(['ok' => false, 'error' => 'Room does not belong to device'], 409);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO PLCdeployment (room_id, appliance_name, appliance_id, ipaddress, power, hp, current, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$roomId, $applianceName, $applianceId, $ipaddress, $power, $hp, $current, $status]);

    $deploymentId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('UPDATE roomdeployment SET appliances=(SELECT COUNT(*) FROM PLCdeployment WHERE room_id=?) WHERE room_id=?');
    $stmt->execute([$roomId, $roomId]);

    json_response(['ok' => true, 'deployment_id' => $deploymentId]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to save appliance'], 500);
}

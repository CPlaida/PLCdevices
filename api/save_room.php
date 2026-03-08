<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$body = read_json_body();

$deviceId = (int)($body['device_id'] ?? 0);
$roomnoname = trim((string)($body['roomnoname'] ?? 'ROOM'));
$bldgno = trim((string)($body['bldgno'] ?? 'BLDG'));
$appliances = (int)($body['appliances'] ?? 0);
$ipaddress = trim((string)($body['ipaddress'] ?? '192.168.1.200'));

if ($deviceId <= 0) {
    json_response(['ok' => false, 'error' => 'device_id required'], 400);
    exit;
}

try {
    $pdo = db();

    // Validate device exists
    $stmt = $pdo->prepare('SELECT device_id, `switch` FROM PLCdevices WHERE device_id=?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();
    if (!$device) {
        json_response(['ok' => false, 'error' => 'Invalid device'], 400);
        exit;
    }

    $mark = (int)($device['switch'] ?? 0);
    if ($mark <= 0) {
        json_response(['ok' => false, 'error' => 'Invalid device marking'], 409);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM roomdeployment WHERE device_id=?');
    $stmt->execute([$deviceId]);
    $roomCount = (int)($stmt->fetchColumn() ?: 0);
    if ($roomCount >= $mark) {
        json_response(['ok' => false, 'error' => 'Mark capacity reached: cannot add more rooms to this PLC'], 409);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO roomdeployment (roomnoname, bldgno, appliances, ipaddress, device_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$roomnoname, $bldgno, $appliances, $ipaddress, $deviceId]);

    json_response(['ok' => true, 'room_id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to save room'], 500);
}

<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$body = read_json_body();

$deviceId = (int)($body['device_id'] ?? 0);
$roomId = (int)($body['room_id'] ?? 0);
$applianceType = trim((string)($body['appliance_type'] ?? 'APPLIANCE'));
$applianceName = trim((string)($body['appliance_name'] ?? $applianceType));
$applianceId = trim((string)($body['appliance_id'] ?? uniqid('A', true)));
$brand = trim((string)($body['brand'] ?? ''));
$volts = (float)($body['volts'] ?? 0);
$switchCode = trim((string)($body['switch_code'] ?? ''));
$ipaddress = trim((string)($body['ipaddress'] ?? '192.168.1.210'));
$power = (float)($body['power'] ?? 0);
$hp = (float)($body['hp'] ?? 0);
$current = (float)($body['current'] ?? 0);
$status = (string)($body['status'] ?? 'OF');

if ($deviceId <= 0 || $roomId <= 0) {
    json_response(['ok' => false, 'error' => 'device_id and room_id required'], 400);
    exit;
}

$hasExtraColumns = false;
try {
    $cols = db()->query("SHOW COLUMNS FROM PLCdeployment LIKE 'appliance_type'")->fetch();
    $hasExtraColumns = $cols !== false;
} catch (Throwable $e) {
    $hasExtraColumns = false;
}

if ($hasExtraColumns) {
    if ($brand === '' || $switchCode === '') {
        json_response(['ok' => false, 'error' => 'brand and switch_code are required'], 400);
        exit;
    }

    if (!is_finite($volts) || $volts < 0) {
        json_response(['ok' => false, 'error' => 'volts must be a valid non-negative number'], 400);
        exit;
    }
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

    // Enforce Mark rule: (rooms * appliances_after_insert) <= mark
    $stmt = $pdo->prepare('SELECT `switch` FROM PLCdevices WHERE device_id=?');
    $stmt->execute([$deviceId]);
    $mark = (int)($stmt->fetchColumn() ?: 0);
    if ($mark <= 0) {
        json_response(['ok' => false, 'error' => 'Invalid device marking'], 409);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM roomdeployment WHERE device_id=?');
    $stmt->execute([$deviceId]);
    $roomCount = (int)($stmt->fetchColumn() ?: 0);
    $roomCount = max(1, $roomCount);

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM PLCdeployment d
         INNER JOIN roomdeployment r ON r.room_id=d.room_id
         WHERE r.device_id=?'
    );
    $stmt->execute([$deviceId]);
    $totalAppliances = (int)($stmt->fetchColumn() ?: 0);

    $nextTotalAppliances = $totalAppliances + 1;
    $appliancesPerRoomAfter = (int)ceil($nextTotalAppliances / $roomCount);
    if (($roomCount * $appliancesPerRoomAfter) > $mark) {
        json_response(['ok' => false, 'error' => 'Mark capacity reached: cannot add more appliances to this PLC'], 409);
        exit;
    }

    if ($hasExtraColumns) {
        $stmt = $pdo->prepare('INSERT INTO PLCdeployment (room_id, appliance_type, appliance_name, appliance_id, brand, volts, switch_code, ipaddress, power, hp, current, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$roomId, $applianceType, $applianceName, $applianceId, $brand, $volts, $switchCode, $ipaddress, $power, $hp, $current, $status]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO PLCdeployment (room_id, appliance_name, appliance_id, ipaddress, power, hp, current, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$roomId, $applianceName, $applianceId, $ipaddress, $power, $hp, $current, $status]);
    }

    $deploymentId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('UPDATE roomdeployment SET appliances=(SELECT COUNT(*) FROM PLCdeployment WHERE room_id=?) WHERE room_id=?');
    $stmt->execute([$roomId, $roomId]);

    json_response(['ok' => true, 'deployment_id' => $deploymentId]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to save appliance'], 500);
}

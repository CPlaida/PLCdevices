<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$deviceId = (int)($_GET['device_id'] ?? 0);
if ($deviceId <= 0) {
    json_response(['ok' => false, 'error' => 'device_id required'], 400);
    exit;
}

try {
    $pdo = db();

    $stmtRooms = $pdo->prepare('SELECT room_id, roomnoname, bldgno, appliances, ipaddress, device_id FROM roomdeployment WHERE device_id=? ORDER BY room_id ASC');
    $stmtRooms->execute([$deviceId]);
    $rooms = $stmtRooms->fetchAll();

    $stmtApps = $pdo->prepare('SELECT d.deployment_id, d.room_id, d.appliance_name, d.appliance_id, d.ipaddress, d.power, d.hp, d.current, d.status FROM PLCdeployment d INNER JOIN roomdeployment r ON r.room_id=d.room_id WHERE r.device_id=? ORDER BY d.deployment_id ASC');
    $stmtApps->execute([$deviceId]);
    $apps = $stmtApps->fetchAll();

    json_response(['ok' => true, 'rooms' => $rooms, 'appliances' => $apps]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to load deployment'], 500);
}

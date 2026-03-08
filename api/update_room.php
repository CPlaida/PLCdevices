<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$body = read_json_body();

$roomId = (int)($body['room_id'] ?? 0);
$deviceId = (int)($body['device_id'] ?? 0);
$roomnoname = trim((string)($body['roomnoname'] ?? ''));
$bldgno = trim((string)($body['bldgno'] ?? ''));
$ipaddress = trim((string)($body['ipaddress'] ?? ''));

if ($roomId <= 0) {
    json_response(['ok' => false, 'error' => 'room_id required'], 400);
    exit;
}
if ($roomnoname === '' || $bldgno === '' || $ipaddress === '') {
    json_response(['ok' => false, 'error' => 'Room fields are required'], 400);
    exit;
}

try {
    $pdo = db();

    if ($deviceId > 0) {
        $stmt = $pdo->prepare('UPDATE roomdeployment SET roomnoname=?, bldgno=?, ipaddress=? WHERE room_id=? AND device_id=?');
        $stmt->execute([$roomnoname, $bldgno, $ipaddress, $roomId, $deviceId]);
    } else {
        $stmt = $pdo->prepare('UPDATE roomdeployment SET roomnoname=?, bldgno=?, ipaddress=? WHERE room_id=?');
        $stmt->execute([$roomnoname, $bldgno, $ipaddress, $roomId]);
    }

    if ($stmt->rowCount() < 1) {
        $check = $pdo->prepare('SELECT room_id FROM roomdeployment WHERE room_id=?');
        $check->execute([$roomId]);
        if (!(int)$check->fetchColumn()) {
            json_response(['ok' => false, 'error' => 'Room not found'], 404);
            exit;
        }
    }

    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to update room'], 500);
}


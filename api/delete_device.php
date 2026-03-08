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
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT device_id FROM PLCdevices WHERE device_id=?');
    $stmt->execute([$deviceId]);
    $exists = (int)($stmt->fetchColumn() ?: 0);
    if ($exists <= 0) {
        $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'Device not found'], 404);
        exit;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM roomdeployment LIKE 'device_id'");
    $hasDeviceIdInRooms = $stmt->fetch() !== false;

    if ($hasDeviceIdInRooms) {
        $stmt = $pdo->prepare('SELECT room_id FROM roomdeployment WHERE device_id=?');
        $stmt->execute([$deviceId]);
        $roomIds = array_map(
            static fn(array $row): int => (int)$row['room_id'],
            $stmt->fetchAll()
        );

        if (count($roomIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM PLCdeployment WHERE room_id IN ({$placeholders})");
            $stmt->execute($roomIds);
        }

        $stmt = $pdo->prepare('DELETE FROM roomdeployment WHERE device_id=?');
        $stmt->execute([$deviceId]);
    }

    $stmt = $pdo->prepare('DELETE FROM PLCdevices WHERE device_id=?');
    $stmt->execute([$deviceId]);

    $pdo->commit();
    json_response(['ok' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'error' => 'Failed to delete device'], 500);
}


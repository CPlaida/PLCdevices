<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$body = read_json_body();
$deploymentId = (int)($body['deployment_id'] ?? 0);
$status = strtoupper(trim((string)($body['status'] ?? 'OF')));

if ($deploymentId <= 0 || ($status !== 'ON' && $status !== 'OF')) {
    json_response(['ok' => false, 'error' => 'deployment_id and status (ON/OF) required'], 400);
    exit;
}

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT d.deployment_id, d.power, d.current, d.status AS appliance_status, r.room_id, r.device_id, p.power AS plc_power_limit, p.status AS plc_status
         FROM PLCdeployment d
         INNER JOIN roomdeployment r ON r.room_id = d.room_id
         INNER JOIN PLCdevices p ON p.device_id = r.device_id
         WHERE d.deployment_id = ?'
    );
    $stmt->execute([$deploymentId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_response(['ok' => false, 'error' => 'Appliance not found'], 404);
        exit;
    }

    $plcStatus = strtoupper(trim((string)($row['plc_status'] ?? '1')));
    if ($status === 'ON' && ($plcStatus === '0' || $plcStatus === 'OFF' || $plcStatus === 'OF')) {
        json_response(['ok' => false, 'error' => 'PLC is OFF. Cannot turn appliances ON.'], 409);
        exit;
    }

    if ($status === 'ON') {
        $deviceId = (int)$row['device_id'];
        $powerLimit = (float)($row['plc_power_limit'] ?? 0);
        $appliancePower = (float)($row['power'] ?? 0);

        $sumStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(d.power), 0) AS total_power
             FROM PLCdeployment d
             INNER JOIN roomdeployment r ON r.room_id = d.room_id
             WHERE r.device_id = ? AND d.status = "ON" AND d.deployment_id <> ?'
        );
        $sumStmt->execute([$deviceId, $deploymentId]);
        $totals = $sumStmt->fetch() ?: ['total_power' => 0];

        $nextPower = (float)$totals['total_power'] + $appliancePower;

        if ($powerLimit > 0 && $nextPower > $powerLimit) {
            json_response(['ok' => false, 'error' => 'Power limit exceeded for this PLC'], 409);
            exit;
        }
    }

    $update = $pdo->prepare('UPDATE PLCdeployment SET status=? WHERE deployment_id=?');
    $update->execute([$status, $deploymentId]);

    json_response(['ok' => true, 'status' => $status]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to update status'], 500);
}


<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$deploymentId = (int)($_GET['deployment_id'] ?? 0);
if ($deploymentId <= 0) {
    json_response(['ok' => false, 'error' => 'deployment_id required'], 400);
    exit;
}

try {
    $pdo = db();

    $hasExtraColumns = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM PLCdeployment LIKE 'appliance_type'")->fetch();
        $hasExtraColumns = $col !== false;
    } catch (Throwable $e) {
        $hasExtraColumns = false;
    }

    if ($hasExtraColumns) {
        $stmt = $pdo->prepare(
            'SELECT deployment_id, appliance_type, appliance_name, appliance_id, brand, volts, switch_code, ipaddress, power, hp, current, status
             FROM PLCdeployment
             WHERE deployment_id=?'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT deployment_id, appliance_name, appliance_id, ipaddress, power, hp, current, status
             FROM PLCdeployment
             WHERE deployment_id=?'
        );
    }
    $stmt->execute([$deploymentId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_response(['ok' => false, 'error' => 'Not found'], 404);
        exit;
    }

    if (!$hasExtraColumns) {
        $row['appliance_type'] = '';
        $row['brand'] = '';
        $row['volts'] = 0;
        $row['switch_code'] = '';
    }

    $row['watts'] = (float)$row['power'];

    json_response(['ok' => true, 'appliance' => $row]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to load appliance details'], 500);
}

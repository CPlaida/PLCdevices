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
    $stmt = $pdo->prepare(
        'SELECT deployment_id, appliance_name, appliance_id, power, hp, current, status
         FROM PLCdeployment
         WHERE deployment_id=?'
    );
    $stmt->execute([$deploymentId]);
    $row = $stmt->fetch();
    if (!$row) {
        json_response(['ok' => false, 'error' => 'Not found'], 404);
        exit;
    }

    // Keep UI contract without depending on extra tables.
    $row['brand'] = 'GENERIC';
    $row['volts'] = 220;
    $row['watts'] = (float)$row['power'];

    json_response(['ok' => true, 'appliance' => $row]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to load appliance details'], 500);
}

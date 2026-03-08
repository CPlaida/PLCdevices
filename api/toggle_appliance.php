<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$body = read_json_body();
$deploymentId = (int)($body['deployment_id'] ?? 0);
$status = (string)($body['status'] ?? 'OF');

if ($deploymentId <= 0 || ($status !== 'ON' && $status !== 'OF')) {
    json_response(['ok' => false, 'error' => 'deployment_id and status (ON/OF) required'], 400);
    exit;
}

try {
    $stmt = db()->prepare('UPDATE PLCdeployment SET status=? WHERE deployment_id=?');
    $stmt->execute([$status, $deploymentId]);
    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to update status'], 500);
}

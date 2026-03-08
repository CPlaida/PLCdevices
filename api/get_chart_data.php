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
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(d.power), 0) AS total_power
         FROM PLCdeployment d
         INNER JOIN roomdeployment r ON r.room_id = d.room_id
         WHERE r.device_id=?'
    );
    $stmt->execute([$deviceId]);
    $basePower = (float)($stmt->fetchColumn() ?: 0);
    if ($basePower <= 0) {
        $basePower = 200;
    }

    $labels = [];
    $values = [];
    for ($i = 11; $i >= 0; $i--) {
        $labels[] = (string)(12 - $i);
        $noise = mt_rand(-15, 15) / 100;
        $values[] = round($basePower * (1 + $noise), 2);
    }

    json_response(['ok' => true, 'labels' => $labels, 'values' => $values]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to load chart data'], 500);
}

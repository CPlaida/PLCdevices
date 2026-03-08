<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

try {
    $stmt = db()->query("SHOW TABLE STATUS LIKE 'PLCdevices'");
    $row = $stmt->fetch();
    $nextId = (int)($row['Auto_increment'] ?? 1);
    json_response(['ok' => true, 'next_device_id' => $nextId]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to get next device ID'], 500);
}

<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

try {
    $rows = db()->query('SELECT device_id, name, IP_address, fw, switch, power, status FROM PLCdevices ORDER BY device_id DESC')->fetchAll();
    json_response(['ok' => true, 'devices' => $rows]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to load devices'], 500);
}

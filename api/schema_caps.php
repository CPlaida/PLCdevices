<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

try {
    $hasApplianceExtras = false;
    try {
        $col = db()->query("SHOW COLUMNS FROM PLCdeployment LIKE 'appliance_type'")->fetch();
        $hasApplianceExtras = $col !== false;
    } catch (Throwable $e) {
        $hasApplianceExtras = false;
    }

    json_response([
        'ok' => true,
        'has_appliance_extras' => $hasApplianceExtras,
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to read schema capabilities'], 500);
}

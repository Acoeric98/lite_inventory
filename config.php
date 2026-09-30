<?php
declare(strict_types=1);

return [
    'database' => getenv('INVENTORY_DB') ?: __DIR__ . '/data/inventory.sqlite',
    'session_name' => 'lite_inventory_session',
];


<?php

declare(strict_types=1);

return [
    'salt' => env('HASHED_ROUTE_KEY_SALT', env('APP_KEY')),
    'append_route_key' => true,
    'default_attribute_name' => 'route_key',
    'min_payload_length' => 3,
    'check_length' => 4,
    'offset_multiplier' => 1,
];

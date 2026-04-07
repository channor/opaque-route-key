<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Route-Key Salt
    |--------------------------------------------------------------------------
    |
    | Base salt used to derive model-specific alphabets and check tags. Keep
    | this value stable after URLs are public. Changing it can invalidate
    | existing route keys.
    |
    */
    'salt' => env('OPAQUE_ROUTE_KEY_SALT', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Serialized Route-Key Attribute
    |--------------------------------------------------------------------------
    |
    | Controls whether models using the trait append a computed route-key
    | attribute during serialization. Valid values for append_route_key are
    | true, false, or a string attribute name.
    |
    */
    'append_route_key' => true,
    'default_attribute_name' => 'route_key',

    /*
    |--------------------------------------------------------------------------
    | Encoding Parameters
    |--------------------------------------------------------------------------
    |
    | These values define the generated key shape and validation tag length.
    | Keep them stable per model after URLs are public.
    |
    | min_payload_length: integer, at least 1.
    | check_length: integer, 1 to 32.
    | offset_multiplier: integer, at least 1.
    |
    */
    'min_payload_length' => 3,
    'check_length' => 4,
    'offset_multiplier' => 1,

    /*
    |--------------------------------------------------------------------------
    | Reserved Route Keys
    |--------------------------------------------------------------------------
    |
    | Words listed here will not be emitted as generated route keys. Use this
    | to avoid collisions with route paths such as "create", "edit", or "new".
    |
    | The default list is empty because reserved words are application-specific.
    | With the default encoding settings, generated keys are at least 7
    | characters long, so shorter words cannot be emitted.
    |
    */
    'reserved_words' => [
        // 'admin',
        // 'root',
        // 'create',
        // 'edit',
        // 'new',
        // 'settings',
        // 'search',
    ],
    'reserved_words_case_sensitive' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto-Reserved Model Names
    |--------------------------------------------------------------------------
    |
    | When enabled, the trait reserves each model's lowercase singular and
    | plural basename, for example "account" and "accounts" for Account.
    | These model-name reservations are always case-insensitive.
    |
    */
    'auto_reserve_model_names' => false,

    /*
    |--------------------------------------------------------------------------
    | Reserved-Word Attempts
    |--------------------------------------------------------------------------
    |
    | Maximum deterministic candidate encodings to try when a generated key is
    | reserved. This integer must be at least 1.
    |
    */
    'reserved_word_max_attempts' => 10,
];

# Upgrade To V2

Version `2.0.0` removes the deprecated `hashed-route-key` compatibility layer kept during `v1.x`.

## Composer

If your application still requires the old package name:

```bash
composer remove channor/hashed-route-key
composer require channor/opaque-route-key:^2.0
```

If your application already requires `channor/opaque-route-key`:

```bash
composer require channor/opaque-route-key:^2.0
```

Stay on `channor/opaque-route-key:^1.1` if you still need the deprecated
`Channor\HashedRouteKey` API.

## Imports

Replace old imports:

```php
use Channor\HashedRouteKey\HashedRouteKeyCodec;
use Channor\HashedRouteKey\HashedRouteKeyServiceProvider;
use Channor\HashedRouteKey\UsesHashedRouteKey;
```

With:

```php
use Channor\OpaqueRouteKey\OpaqueRouteKeyCodec;
use Channor\OpaqueRouteKey\OpaqueRouteKeyServiceProvider;
use Channor\OpaqueRouteKey\UsesOpaqueRouteKey;
```

## Config

Rename old config/env names:

- `config/hashed-route-key.php` -> `config/opaque-route-key.php`
- `HASHED_ROUTE_KEY_SALT` -> `OPAQUE_ROUTE_KEY_SALT`
- `hashed-route-key-config` -> `opaque-route-key-config`

Keep the same effective salt value when renaming config/env keys. Route-key outputs remain stable
when the effective salt and per-model strategy settings stay the same.

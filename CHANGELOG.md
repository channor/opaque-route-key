# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-04-07

### Added
- Renamed the package to `channor/opaque-route-key`.
- Added `Channor\OpaqueRouteKey` namespace, `OpaqueRouteKeyCodec`, `UsesOpaqueRouteKey`, and `OpaqueRouteKeyServiceProvider`.
- Added optional `reserved_words`, `reserved_words_case_sensitive`, `auto_reserve_model_names`, and `reserved_word_max_attempts` config for avoiding generated route keys that collide with configured route words.

### Deprecated
- Deprecated `Channor\HashedRouteKey` namespace, `HashedRouteKeyCodec`, `UsesHashedRouteKey`, and `HashedRouteKeyServiceProvider`.
- Deprecated `HASHED_ROUTE_KEY_SALT`, `hashed-route-key` config, and `hashed-route-key-config` publish tag. These remain supported throughout `v1.x`.

### Compatibility
- Existing `v1.0.x` route-key outputs remain stable by default.
- Existing route keys remain decodable when `reserved_words` is later configured.
- `reserved_words_case_sensitive` defaults to `true` and `auto_reserve_model_names` defaults to `false` throughout `v1.x`.
- Old package consumers can continue using the deprecated `HashedRouteKey` API until `v2.0.0`.

## [1.0.1] - 2026-03-08

### Added
- Enabled Laravel package discovery.

## [1.0.0] - 2026-03-08

### Added
- Deterministic opaque route keys for integer model IDs.
- Laravel trait and service provider integration.
- Configurable base salt and per-model salt suffix support.
- Stable route-key contract test generator command.
- Standalone package test suite with Orchestra Testbench.

[1.1.0]: https://github.com/channor/opaque-route-key/releases/tag/v1.1.0
[1.0.1]: https://github.com/channor/opaque-route-key/releases/tag/v1.0.1
[1.0.0]: https://github.com/channor/opaque-route-key/releases/tag/v1.0.0

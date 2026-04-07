# Opaque Route Key

Deterministic, model-level opaque route keys for Laravel.

Replaces sequential integer IDs in URLs with compact, HMAC-verified route keys without storing
anything extra in the database.

```text
/teams/3             -> /teams/kX9mG7
/teams/3/members/42  -> /teams/kX9mG7/members/bR4nYp2w
```

The result is deterministic and decodable. It is obfuscation with integrity checks, not encryption
and not a one-way hash.

## Why this package exists

This package was built for Laravel apps that want opaque route keys to be a model-level concern. The
trait-based integration keeps route-key generation and route binding on the model itself, while
deriving distinct salts per model so the same integer ID produces different route keys across
different models by default.

## Installation

```bash
composer require channor/opaque-route-key
```

Supported targets:

- PHP 8.2, 8.3, and 8.4
- Laravel 11 and 12

Laravel package discovery will register the service provider automatically.

If your application disables package discovery, register the provider manually in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Channor\OpaqueRouteKey\OpaqueRouteKeyServiceProvider::class,
];
```

Publish config:

```bash
php artisan vendor:publish --tag=opaque-route-key-config
```

## Quick start

Add the trait to an Eloquent model with an integer primary key:

```php
use Channor\OpaqueRouteKey\UsesOpaqueRouteKey;

class Team extends Model
{
    use UsesOpaqueRouteKey;
}
```

That is enough for route model binding and `route()` URL generation to use the opaque key.
Serialization includes the computed `route_key` by default, and can be customized or disabled.

## Upgrade from `channor/hashed-route-key`

This package was renamed in `v1.1.0` because the generated value is decodable and is not a one-way
hash.

```bash
composer remove channor/hashed-route-key
composer require channor/opaque-route-key
```

Preferred imports:

```php
use Channor\OpaqueRouteKey\OpaqueRouteKeyCodec;
use Channor\OpaqueRouteKey\OpaqueRouteKeyServiceProvider;
use Channor\OpaqueRouteKey\UsesOpaqueRouteKey;
```

Deprecated imports remain available throughout `v1.x`:

```php
use Channor\HashedRouteKey\HashedRouteKeyCodec;
use Channor\HashedRouteKey\HashedRouteKeyServiceProvider;
use Channor\HashedRouteKey\UsesHashedRouteKey;
```

The old `hashed-route-key` config name, `HASHED_ROUTE_KEY_SALT` environment variable, and
`hashed-route-key-config` publish tag are also still supported. They are planned for removal in
`v2.0.0`.

## How it works

1. Encodes `id + offset` as a base-62 payload using a salt-shuffled alphabet.
2. Appends a keyed HMAC-based check tag.
3. Verifies the check tag before decoding.
4. Optionally retries with a deterministic alternate salt if the generated key is in `reserved_words`.

The result is deterministic and decodable, while rejecting nearly all cross-salt, cross-model, or
tampered keys.

## Stability warning

Once URLs are public, the following settings must stay stable per model. Changing any of them can
invalidate existing URLs or change future generated URLs for that model:

- salt base: `config('opaque-route-key.salt')`
- salt suffix: `routeKeySaltSuffix()`
- payload length: `routeKeyMinPayloadLength()`
- check length: `routeKeyCheckLength()`
- offset multiplier: `routeKeyOffsetMultiplier()`
- reserved words: `config('opaque-route-key.reserved_words')`
- reserved-word case sensitivity: `config('opaque-route-key.reserved_words_case_sensitive')`
- auto-reserved model names: `config('opaque-route-key.auto_reserve_model_names')`
- reserved-word attempts: `config('opaque-route-key.reserved_word_max_attempts')`

The old `config('hashed-route-key.*')` keys remain supported for compatibility, but new code should
use `config('opaque-route-key.*')`.

## Reserved words

Use `reserved_words` when a generated route key would collide with route words such as `create`,
`edit`, or `new`:

```php
return [
    'reserved_words' => ['create', 'edit', 'new'],
    'reserved_words_case_sensitive' => true,
    'auto_reserve_model_names' => false,
    'reserved_word_max_attempts' => 10,
];
```

The default list is empty to preserve existing outputs. When a key collides with a reserved word, the
codec retries deterministically until it finds a non-reserved key or exhausts
`reserved_word_max_attempts`.

Manual reserved words are case-sensitive by default in `v1.x`. Set `reserved_words_case_sensitive`
to `false` if reserving `admin` should also avoid emitting `aDmIn`.

When `auto_reserve_model_names` is `true`, each model using the trait also reserves its lowercase
singular and plural class basename. For example, `Account` reserves `account` and `accounts`.
These auto-reserved model names are always matched case-insensitively, independent of
`reserved_words_case_sensitive`. Override `routeKeyReservedModelNames()` on the model if your route
words need a different shape. The default is `false` throughout `v1.x`; it is planned to become
`true` in `v2.0.0`.

Attempt `0` is the original `v1.0.x` encoding, so existing URLs remain decodable even if you later
reserve a word that an existing key used. However, enabling or changing `reserved_words`,
`reserved_words_case_sensitive`, or `auto_reserve_model_names` can change future output for affected
IDs, so treat them as part of your public URL contract.

## Contract test generator

The package ships with an Artisan command that generates stable app-level contract tests for models
using `UsesOpaqueRouteKey`:

```bash
php artisan route-key:generate-test --class=User
php artisan route-key:generate-test --class=App\\Models\\Project --force
php artisan route-key:generate-test --all
php artisan route-key:generate-test --reserved
php artisan route-key:generate-test --all --reserved
php artisan route-key:generate-test --all --namespace=App\\Domain\\People\\Models
php artisan route-key:generate-test --all --namespace=Domain\\People\\Models --model-path=src/Domain/People/Models
```

The command:

- resolves common model inputs such as `User`, `Users`, or a fully qualified class name
- can scan all models using the trait with `--all`
- verifies that the model uses `UsesOpaqueRouteKey`, or the deprecated `UsesHashedRouteKey`
- writes model strategy assertions and fixed-output route-key assertions using a fixed salt base
- writes a separate `ReservedOpaqueRouteKeyTest` with `--reserved` to pin reserved-word stability

By default, generated tests are written to `tests/Feature/RouteKeys`. Use `--path=` to write them
elsewhere.

## Per-model overrides

Override any strategy method on the model when the defaults do not fit:

```php
class Account extends Model
{
    use UsesOpaqueRouteKey;

    protected function routeKeyMinPayloadLength(): int
    {
        return 2;
    }

    protected function routeKeyCheckLength(): int
    {
        return 3;
    }

    protected function routeKeyOffsetMultiplier(): int
    {
        return 2;
    }

    protected function routeKeySaltSuffix(): string
    {
        return 'account';
    }
}
```

## Salt derivation

By default, the trait builds the codec salt from:

1. `config('opaque-route-key.salt')`
2. `routeKeySaltSuffix()`, which defaults to `snake_case(class_basename(Model::class))`

For example, `App\Models\Project` uses a salt shaped like:

```php
config('opaque-route-key.salt').':project'
```

The default config uses `OPAQUE_ROUTE_KEY_SALT`, falls back to `HASHED_ROUTE_KEY_SALT`, and then falls
back to `APP_KEY`. Changing the effective salt changes all emitted keys for all models using the
trait unless you keep the effective salt stable.

If you need a custom or shared model suffix, override `routeKeySaltSuffix()` on that model. Keep the
suffix stable once URLs are public.

## Appending `route_key`

The trait can append a computed route-key attribute during array / JSON serialization.

Global config:

```php
return [
    'append_route_key' => true,
    'default_attribute_name' => 'route_key',
];
```

Per-model overrides:

```php
protected bool|string $appendRouteKey = false;
```

```php
protected bool|string $appendRouteKey = 'opaque_key';
```

You can also override the method directly when you need custom logic:

```php
public function appendRouteKey(): bool|string
{
    return $this->is_public ? 'route_key' : false;
}
```

## Behavior on decode failure

When an opaque key is invalid, tampered, wrong-model, or malformed:

- `OpaqueRouteKeyCodec::decode()` returns `null`
- route model binding queries `WHERE id = -1`, which matches nothing and results in a `404`
- `$model->route_key` returns `null` on unsaved models

If you need to work with the lower-level codec directly, use the same model-specific salt strategy
that the trait uses:

```php
use Channor\OpaqueRouteKey\OpaqueRouteKeyCodec;

$codec = new OpaqueRouteKeyCodec(
    salt: config('opaque-route-key.salt').':team',
    minPayloadLength: config('opaque-route-key.min_payload_length'),
    checkLength: config('opaque-route-key.check_length'),
    offsetMultiplier: config('opaque-route-key.offset_multiplier'),
);

$routeKey = $codec->encode(42);
$id = $codec->decode($routeKey); // 42
```

## Config reference

```php
return [
    'salt' => env('OPAQUE_ROUTE_KEY_SALT', env('HASHED_ROUTE_KEY_SALT', env('APP_KEY'))),
    'append_route_key' => true,
    'default_attribute_name' => 'route_key',
    'min_payload_length' => 3,
    'check_length' => 4,
    'offset_multiplier' => 1,
    'reserved_words' => [],
    'reserved_words_case_sensitive' => true,
    'auto_reserve_model_names' => false,
    'reserved_word_max_attempts' => 10,
];
```

| Key | Purpose | Default |
|-----|---------|---------|
| `salt` | Base salt for all route-key derivations | `APP_KEY` |
| `append_route_key` | Auto-append `route_key` during serialization | `true` |
| `default_attribute_name` | Attribute name when appending | `route_key` |
| `min_payload_length` | Minimum encoded payload characters | `3` |
| `check_length` | HMAC check tag characters (max 32) | `4` |
| `offset_multiplier` | Shifts encoding space for low IDs | `1` |
| `reserved_words` | Generated keys to avoid emitting | `[]` |
| `reserved_words_case_sensitive` | Match manual reserved words by exact case only | `true` |
| `auto_reserve_model_names` | Reserve each model's lowercase singular and plural basename | `false` in `v1.x`; planned `true` in `v2.0.0` |
| `reserved_word_max_attempts` | Maximum candidate encodings, including the original | `10` |

## Capacity math (base-62)

| Payload length | Unique values |
|----------------|---------------|
| `p=2` | 3,844 |
| `p=3` | 238,328 |
| `p=4` | 14,776,336 |

The payload starts at `min_payload_length` characters and adds characters as IDs grow beyond what
the current length can represent. `offset_multiplier` shifts where these length boundaries fall.

## Alternatives

[`vinkla/laravel-hashids`](https://github.com/vinkla/laravel-hashids) and
[`cybercog/laravel-optimus`](https://github.com/cybercog/laravel-optimus) are relevant alternatives
for Laravel applications that want obfuscated identifiers. Either may be a better fit depending on
your needs and existing conventions.

## Notes

- This is obfuscation with integrity checks, not encryption.
- This package is not a security boundary and does not replace authentication, authorization, or
  signed URLs.
- Only non-negative integer IDs are supported.

## Development note

This package was developed with LLM assistance. Final design, review, and release decisions remain
with the maintainer.

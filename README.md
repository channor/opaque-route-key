# Hashed Route Key

Deterministic, model-level hashed route keys for Laravel.

Replaces sequential integer IDs in URLs with compact, opaque, HMAC-verified hashes without storing
anything extra in the database.

```text
/teams/3             -> /teams/kX9mG7
/teams/3/members/42  -> /teams/kX9mG7/members/bR4nYp2w
```

The core codec is plain PHP. The trait, command, and service provider require Laravel.

## Why this package exists

This package was built for Laravel apps that want hashed route keys to be a model-level concern. The
trait-based integration keeps route-key generation and route binding on the model itself, while
deriving distinct salts per model so the same integer ID produces different route keys across
different models by default.

## Quick start

Add the trait to an Eloquent model with an integer primary key:

```php
use Channor\HashedRouteKey\UsesHashedRouteKey;

class Team extends Model
{
    use UsesHashedRouteKey;
}
```

That is enough for route model binding and `route()` URL generation to use the hashed key.
Serialization includes the computed route key by default, and can be customized or disabled.

## How it works

1. Encodes `id + offset` as a base-62 payload using a salt-shuffled alphabet.
2. Appends a keyed HMAC-based check tag.
3. Verifies the check tag before decoding.

The result is deterministic and reversible, while rejecting nearly all cross-salt, cross-model, or
tampered hashes.

## Installation

Supported targets:

- PHP 8.2, 8.3, and 8.4
- Laravel 11 and 12

Laravel package discovery will register the service provider automatically.

If your application disables package discovery, register the provider manually in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Channor\HashedRouteKey\HashedRouteKeyServiceProvider::class,
];
```

Publish config:

```bash
php artisan vendor:publish --tag=hashed-route-key-config
```

## Stability warning

Once URLs are public, the following settings must stay stable per model. Changing any of them
invalidates all existing hashed URLs for that model:

- salt base: `config('hashed-route-key.salt')`
- salt suffix: `routeKeySaltSuffix()`
- payload length: `routeKeyMinPayloadLength()`
- check length: `routeKeyCheckLength()`
- offset multiplier: `routeKeyOffsetMultiplier()`

The contract test generator below exists to catch accidental changes to these values.

## Contract test generator

The package ships with an Artisan command that generates stable app-level contract tests for models
using `UsesHashedRouteKey`:

```bash
php artisan route-key:generate-test --class=User
php artisan route-key:generate-test --class=App\\Models\\Project --force
php artisan route-key:generate-test --all
php artisan route-key:generate-test --all --namespace=App\\Domain\\People\\Models
php artisan route-key:generate-test --all --namespace=Domain\\People\\Models --model-path=src/Domain/People/Models
```

The command:

- resolves common model inputs such as `User`, `Users`, or a fully qualified class name
- can scan all models using the trait with `--all`
- verifies that the model uses `UsesHashedRouteKey`
- writes strategy assertions and fixed-output hash assertions using a fixed salt base

By default, generated tests are written to `tests/Feature/RouteKeys`. Use `--path=` to write them
elsewhere.

For `--all`, the default discovery scope is:

- `--namespace=App\\Models`
- `--model-path=app/Models`

If you pass a namespace under `App\\...` and omit `--model-path`, the command infers the path from
that namespace. For namespaces outside `App\\...`, provide both `--namespace` and `--model-path`.

Generated tests extend `Tests\TestCase` by default, which matches the standard Laravel application
test base class convention.

## Per-model overrides

Override any strategy method on the model when the defaults do not fit:

```php
class Account extends Model
{
    use UsesHashedRouteKey;

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

1. `config('hashed-route-key.salt')`
2. `routeKeySaltSuffix()`, which defaults to `snake_case(class_basename(Model::class))`

For example, `App\Models\Project` uses a salt shaped like:

```php
config('hashed-route-key.salt').':project'
```

The default config uses `HASHED_ROUTE_KEY_SALT` and falls back to `APP_KEY`, so changing either
changes all emitted hashes for all models using the trait unless you keep the effective salt stable.

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
protected bool|string $appendRouteKey = 'hashed_key';
```

You can also override the method directly when you need custom logic:

```php
public function appendRouteKey(): bool|string
{
    return $this->is_public ? 'route_key' : false;
}
```

## Behavior on decode failure

When a hashed key is invalid, tampered, wrong-model, or malformed:

- `HashedRouteKeyCodec::decode()` returns `null`
- route model binding queries `WHERE id = -1`, which matches nothing and results in a `404`
- `$model->route_key` returns `null` on unsaved models

## Core codec

The codec has no framework dependencies and can be used outside Laravel:

```php
use Channor\HashedRouteKey\HashedRouteKeyCodec;

$codec = new HashedRouteKeyCodec(
    salt: 'my-secret-salt',
    minPayloadLength: 3,
    checkLength: 4,
    offsetMultiplier: 1,
);

$hash = $codec->encode(42);
$id = $codec->decode($hash); // 42
```

## Config reference

```php
return [
    'salt' => env('HASHED_ROUTE_KEY_SALT', env('APP_KEY')),
    'append_route_key' => true,
    'default_attribute_name' => 'route_key',
    'min_payload_length' => 3,
    'check_length' => 4,
    'offset_multiplier' => 1,
];
```

| Key | Purpose | Default |
|-----|---------|---------|
| `salt` | Base salt for all hash derivations | `APP_KEY` |
| `append_route_key` | Auto-append `route_key` during serialization | `true` |
| `default_attribute_name` | Attribute name when appending | `route_key` |
| `min_payload_length` | Minimum encoded payload characters | `3` |
| `check_length` | HMAC check tag characters (max 32) | `4` |
| `offset_multiplier` | Shifts encoding space for low IDs | `1` |

## Capacity math (base-62)

| Payload length | Unique values |
|----------------|---------------|
| `p=2` | 3,844 |
| `p=3` | 238,328 |
| `p=4` | 14,776,336 |

The payload starts at `min_payload_length` characters and adds characters as IDs grow beyond what
the current length can represent. `offsetMultiplier` shifts where these length boundaries fall.

## Why this codec exists

The codec in this package is intentionally narrow. Its job is to produce compact, deterministic
route keys for integer model IDs with integrity checks and predictable growth characteristics. It is
not intended as a general-purpose hashing framework or a statement against existing codec packages.

If another package fits your application better, use that. This package exists to support a
model-centric Laravel route-key workflow with per-model salt separation and minimal operational
surface area.

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

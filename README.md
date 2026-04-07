# Opaque Route Key

Deterministic, model-level opaque route keys for Laravel.

Replaces sequential integer IDs in URLs with compact, HMAC-verified route keys without storing
anything extra in the database:

```text
/teams/3             -> /teams/kX9mG7
/teams/3/members/42  -> /teams/kX9mG7/members/bR4nYp2w
```

The result is deterministic and decodable. It is obfuscation with integrity checks, **not encryption,
not secrecy, and not authorization**.

> **Note:** This package is not a security boundary and does not replace authentication,
> authorization, or signed URLs.

The Eloquent trait keeps route-key generation and route binding on the model itself, while deriving
distinct salts per model so the same integer ID produces different route keys across models by
default.

## Installation

```bash
composer require channor/opaque-route-key
```

Supported targets:

- PHP 8.2, 8.3, and 8.4
- Laravel 11 and 12

Laravel package discovery registers the service provider automatically. If your application disables
package discovery, register it manually in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Channor\OpaqueRouteKey\OpaqueRouteKeyServiceProvider::class,
];
```

Publish config when you need to customize behavior:

```bash
php artisan vendor:publish --tag=opaque-route-key-config
```

## Quick Start

Add the trait to an Eloquent model with an integer primary key:

```php
use Channor\OpaqueRouteKey\UsesOpaqueRouteKey;

class Team extends Model
{
    use UsesOpaqueRouteKey;
}
```

That is enough for route model binding and `route()` URL generation to use the opaque key.
Serialization includes the computed `route_key` by default.

## Upgrade To V2

Version `2.0.0` removes the deprecated `hashed-route-key` compatibility layer kept during `v1.x`.
See [the v2 upgrade guide](docs/upgrade-v2.md) for package, import, and config migration steps.

## Configuration

```php
return [
    'salt' => env('OPAQUE_ROUTE_KEY_SALT', env('APP_KEY')),
    'append_route_key' => true,
    'default_attribute_name' => 'route_key',
    'min_payload_length' => 3,
    'check_length' => 4,
    'offset_multiplier' => 1,
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
| `auto_reserve_model_names` | Reserve each model's lowercase singular and plural basename | `false` |
| `reserved_word_max_attempts` | Maximum candidate encodings, including the original | `10` |

Once URLs are public, keep the effective salt and per-model strategy settings stable. Changing salt,
salt suffix, payload length, check length, offset multiplier, reserved words, or reserved-word
attempt settings can invalidate existing URLs or change future generated URLs for affected IDs.

## Reserved Words

`reserved_words` prevents the codec from emitting route keys that collide with route paths such as
`create`, `edit`, or `new`. The default list is empty because reserved route words are
application-specific. With the default encoding settings, generated keys are at least 7 characters
long, so shorter words cannot be emitted.

Manual reserved words are case-sensitive by default. Reserving `account` will not reserve `aCcOuNt`
unless `reserved_words_case_sensitive` is `false`.

When `auto_reserve_model_names` is `true`, each model using the trait reserves its lowercase singular
and plural class basename, for example `account` and `accounts` for `Account`. These model-name
reservations are always matched case-insensitively.

If a generated key is reserved, the codec retries deterministic alternate encodings up to
`reserved_word_max_attempts`. If every attempt collides, encoding throws a `RuntimeException`.
Existing keys remain decodable even if you later reserve a word that was previously emitted.

## Customization

Override strategy methods on a model when the defaults do not fit:

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

By default, the codec salt is:

```php
config('opaque-route-key.salt').':'.routeKeySaltSuffix()
```

`routeKeySaltSuffix()` defaults to `snake_case(class_basename(Model::class))`. Keep custom suffixes
stable once URLs are public.

To customize the serialized attribute:

```php
protected bool|string $appendRouteKey = false;
```

```php
protected bool|string $appendRouteKey = 'opaque_key';
```

```php
public function appendRouteKey(): bool|string
{
    return $this->is_public ? 'route_key' : false;
}
```

## Contract Tests

Generate app-level stability tests before changing route-key settings:

```bash
php artisan route-key:generate-test --class=User
php artisan route-key:generate-test --all
php artisan route-key:generate-test --reserved
php artisan route-key:generate-test --all --reserved
```

Useful options:

- `--force` overwrites existing generated tests
- `--path=tests/Feature/RouteKeys` changes the output directory
- `--namespace=App\\Domain\\People\\Models` changes the `--all` discovery namespace
- `--model-path=src/Domain/People/Models` sets discovery for non-`App\\...` namespaces

Generated model tests pin strategy values and fixed route-key outputs. The reserved config test pins
reserved-word settings.

## Decode Failures

When an opaque key is invalid, tampered, wrong-model, or malformed:

- `OpaqueRouteKeyCodec::decode()` returns `null`
- route model binding queries `WHERE id = -1`, which matches nothing and results in a `404`
- `$model->route_key` returns `null` on unsaved models

## Advanced Usage

If you need the lower-level codec directly, use the same model-specific salt strategy as the trait:

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

Capacity with the default base-62 alphabet:

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

- Only non-negative integer IDs are supported.

## Development Note

This package was developed with LLM assistance. Final design, review, and release decisions remain
with the maintainer.

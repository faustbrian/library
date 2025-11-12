[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# Archive

A simplified Laravel media storage package focused on storage and URL generation without image conversions.

## Features

- **Simple fluent API** for adding media files
- **Polymorphic relationships** - attach media to any model
- **Collection organization** - group media into logical collections
- **Custom path generators** - full control over file organization
- **Query builder scopes** - convenient filtering methods
- **Auto-cleanup** - files deleted when models are removed
- **Transaction safety** - atomic operations for data integrity
- **Anonymous media** - orphan files without owners
- **Curator interface** - support for non-Eloquent owners

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/archive
```

Publish the configuration and migration:

```bash
php artisan vendor:publish --tag=archive-config
php artisan vendor:publish --tag=archive-migrations
php artisan migrate
```

## Quick Start

### 1. Add trait to your model

```php
use Cline\Archive\Contracts\Curator;
use Cline\Archive\Models\Concerns\HasArchive;

class User extends Model implements Curator
{
    use HasArchive;
}
```

### 2. Add media files

```php
use Cline\Archive\Archive;

// Add to a model
$media = Archive::add($request->file('avatar'))
    ->toCurator($user)
    ->toCollection('avatars')
    ->store();

// Anonymous media (no curator)
$media = Archive::add($file)
    ->asAnonymous()
    ->store();

// Full featured
$media = Archive::add($file)
    ->toCurator($user)
    ->toCollection('documents')
    ->withName('Annual Report')
    ->withFileName('report-2024.pdf')
    ->toDisk('s3')
    ->withProperties(['year' => 2024])
    ->store();
```

### 3. Retrieve media

```php
// Via relationship
$avatars = $user->media()->where('collection', 'avatars')->get();
$avatar = $user->media()->where('collection', 'avatars')->first();

// Query builder scopes
$images = Media::inCollection('avatars')
    ->ownedBy($user)
    ->ofType('image')
    ->get();

// Get URL
$url = $avatar->getUrl();
```

## Documentation

See the [cookbook](cookbook/) directory for comprehensive guides:

- [Getting Started](cookbook/getting-started.md)
- [Fluent API](cookbook/fluent-api.md)
- [Collections](cookbook/collections.md)
- [Eloquent Trait](cookbook/eloquent-trait.md)
- [Curator Interface](cookbook/media-owner-interface.md)
- [URL Generation](cookbook/url-generation.md)
- [Examples](cookbook/examples.md)

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/archive/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/archive.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/archive.svg

[link-tests]: https://github.com/faustbrian/archive/actions
[link-packagist]: https://packagist.org/packages/cline/archive
[link-downloads]: https://packagist.org/packages/cline/archive
[link-security]: https://github.com/faustbrian/archive/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors

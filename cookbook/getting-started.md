# Getting Started

## Installation

Install the package via Composer:

```bash
composer require cline/archive
```

Publish the configuration and migration:

```bash
php artisan vendor:publish --tag=archive-config
php artisan vendor:publish --tag=archive-migrations
php artisan migrate
```

## Basic Configuration

The package configuration is located at `config/archive.php`:

```php
return [
    'disk' => env('LIBRARY_DISK', config('filesystems.default')),
    'prefix' => env('LIBRARY_PREFIX', 'media'),
    'path_generator' => \Cline\Archive\Storage\PathGenerator\DefaultPathGenerator::class,
    'url_generator' => \Cline\Archive\Support\UrlGenerator\DefaultUrlGenerator::class,
    'max_file_size' => 1024 * 1024 * 10, // 10MB
    'primary_key_type' => env('LIBRARY_PRIMARY_KEY', 'id'), // 'id', 'ulid', or 'uuid'
];
```

## Quick Start

### 1. Add the trait to your model

```php
use Cline\Archive\Contracts\Curator;
use Cline\Archive\Models\Concerns\HasArchive;

class User extends Model implements Curator
{
    use HasArchive;
}
```

### 2. Register collections (optional)

```php
use Cline\Archive\Archive;

// In a service provider boot method
Archive::collection('avatars')
    ->singleFile()
    ->toDisk('s3');

Archive::collection('documents')
    ->curatedBy(User::class)
    ->toDisk('public');
```

### 3. Add media files explicitly

```php
use Cline\Archive\Archive;

// Add media to a model
$media = Archive::add($request->file('photo'))
    ->toCurator($user)
    ->toCollection('avatars')
    ->store();

// Add orphan media (no curator) - explicit
$media = Archive::add($file)
    ->asAnonymous()
    ->store();

// Add with custom properties
$media = Archive::add($file)
    ->toCurator($user)
    ->withName('Profile Picture')
    ->toCollection('avatars')
    ->withProperties(['featured' => true])
    ->toDisk('s3')
    ->store();
```

### 4. Retrieve media

```php
// Get all media in a collection
$avatars = $user->media()
    ->where('collection', 'avatars')
    ->get();

// Get first media in a collection
$avatar = $user->media()
    ->where('collection', 'avatars')
    ->first();

// Get URL
$url = $avatar->getUrl();
```

## Primary Key Types

Configure the primary key type using the `PrimaryKeyType` enum:

```php
// In config/archive.php
'primary_key_type' => 'ulid', // 'id', 'ulid', or 'uuid'
```

Options:
- `id` - Auto-incrementing integer (default)
- `ulid` - Universally Unique Lexicographically Sortable Identifier
- `uuid` - Universally Unique Identifier

## Next Steps

- [Fluent API](fluent-api.md) - All available methods
- [Collections](collections.md) - Organizing media with collections
- [Examples](examples.md) - Real-world use cases

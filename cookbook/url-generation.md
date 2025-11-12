# URL Generation

Archive provides simple, powerful URL generation for your media files.

## Basic URL Generation

```php
$media = Archive::add($file)->store();

// Public URL
$url = $media->getUrl();
// https://example.com/storage/media/1/photo.jpg

// Use in views
<img src="{{ $media->getUrl() }}" alt="{{ $media->name }}">
```

## Temporary URLs (S3, etc.)

For private disks that support temporary URLs:

```php
$url = $media->getTemporaryUrl(
    expiration: now()->addMinutes(30)
);

// With custom options
$url = $media->getTemporaryUrl(
    expiration: now()->addHour(),
    options: [
        'ResponseContentType' => 'application/pdf',
        'ResponseContentDisposition' => 'attachment; filename="invoice.pdf"',
    ]
);
```

## Path Generation

Get the storage path without the domain:

```php
$path = $media->getPath();
// media/1/photo.jpg

// Use with Storage facade
$exists = Storage::disk($media->disk)->exists($media->getPath());
$size = Storage::disk($media->disk)->size($media->getPath());
```

## Custom Path Generators

Create custom path organization by implementing `PathGenerator`:

```php
namespace App\Archive;

use Cline\Archive\Models\Media;
use Cline\Archive\Storage\PathGenerator\PathGenerator;

class DateBasedPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $date = $media->created_at->format('Y/m/d');
        return "{$date}/{$media->collection}/{$media->id}/{$media->file_name}";
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media);
    }
}
```

Update config:

```php
// config/archive.php
return [
    'path_generator' => \App\Archive\DateBasedPathGenerator::class,
];
```

Results in paths like: `2024/11/10/avatars/1/photo.jpg`

## Examples

### Collection-Based Organization

```php
class CollectionPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $prefix = config('archive.prefix', '');
        $collection = str_replace('.', '/', $media->collection);

        return "{$prefix}/{$collection}/{$media->id}/{$media->file_name}";
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media);
    }
}
```

Paths: `media/avatars/1/photo.jpg`, `media/documents/proposals/2/doc.pdf`

### Curator-Based Organization

```php
class OwnerPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        if (!$media->curator_type || !$media->curator_id) {
            return "orphans/{$media->id}/{$media->file_name}";
        }

        $ownerSlug = str_replace('\\', '/', strtolower($media->curator_type));

        return "{$ownerSlug}/{$media->curator_id}/{$media->collection}/{$media->file_name}";
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media);
    }
}
```

Paths: `app/models/user/123/avatars/photo.jpg`

### Hash-Based (CDN Friendly)

```php
class HashPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        // Distribute files across subdirectories
        $hash = md5((string) $media->id);
        $level1 = substr($hash, 0, 2);
        $level2 = substr($hash, 2, 2);

        return "{$level1}/{$level2}/{$media->id}/{$media->file_name}";
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media);
    }
}
```

Paths: `a3/4f/1/photo.jpg` (balanced distribution)

### Downloadable Files

```php
// Controller
class MediaController
{
    public function download(Media $media)
    {
        return Storage::disk($media->disk)->download(
            $media->getPath(),
            $media->name
        );
    }

    public function stream(Media $media)
    {
        return Storage::disk($media->disk)->response(
            $media->getPath(),
            $media->name,
            ['Content-Type' => $media->mime_type]
        );
    }
}

// Routes
Route::get('/media/{media}/download', [MediaController::class, 'download'])
    ->name('media.download');
Route::get('/media/{media}/stream', [MediaController::class, 'stream'])
    ->name('media.stream');

// Usage in views
<a href="{{ route('media.download', $media) }}">Download</a>
```

### Signed URLs for Protected Media

```php
// Controller
class ProtectedMediaController
{
    public function show(Request $request, Media $media)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        return Storage::disk($media->disk)->response($media->getPath());
    }
}

// Route
Route::get('/media/{media}', [ProtectedMediaController::class, 'show'])
    ->name('media.show');

// Generate signed URL
$signedUrl = URL::temporarySignedRoute(
    'media.show',
    now()->addHours(24),
    ['media' => $media]
);
```

# Fluent API

The package provides a fluent, chainable API for adding media files to your application.

## Basic Usage

```php
use Cline\Archive\Archive;

$media = Archive::add($file)
    ->toCurator($model)              // Optional: attach to curator
    ->withFileName('filename.jpg') // Optional: custom filename
    ->toCollection('avatars')      // Optional: collection name
    ->withProperties(['key' => 'val']) // Optional: custom properties
    ->toDisk('s3')                 // Optional: specific disk
    ->preservingOriginal()         // Optional: keep original file
    ->withOrder(1)                 // Optional: set order
    ->store();                     // Save and store
```

## Available Methods

### `add($file)`

Accepts:
- `Illuminate\Http\UploadedFile` - File from request
- `Symfony\Component\HttpFoundation\File\File` - Symfony file instance
- `string` - Path to file on disk

```php
// From request
Archive::add($request->file('upload'));

// From path
Archive::add('/tmp/image.jpg');

// From UploadedFile
Archive::add($uploadedFile);
```

### `toCurator($curator)`

Attach the media to a curator (model or Curator instance).

```php
// Attach to Eloquent model
Archive::add($file)->toCurator($user)->store();

// Attach to custom Curator
Archive::add($file)->toCurator($customCurator)->store();

// No curator (orphan media) - explicit
Archive::add($file)->asAnonymous()->store();
```

### `asAnonymous()`

Explicitly mark media as anonymous (no curator).

```php
Archive::add($file)
    ->asAnonymous()
    ->toCollection('temp-uploads')
    ->store();
```

### `withFileName($filename)`

Set a custom filename for storage.

```php
Archive::add($file)
    ->withFileName('custom-name.jpg')
    ->store();
```

### `withName($name)`

Set the human-readable name (different from filename).

```php
Archive::add($file)
    ->withFileName('abc123.jpg')       // Storage filename
    ->withName('Profile Photo')        // Human-readable name
    ->store();
```

### `toCollection($collection)`

Organize media into collections (default: 'default').

```php
Archive::add($file)->toCollection('avatars')->store();
Archive::add($file)->toCollection('documents')->store();
```

### `withProperties($properties)`

Store custom metadata with the media.

```php
Archive::add($file)
    ->withProperties([
        'alt_text' => 'Beautiful sunset',
        'photographer' => 'Jane Doe',
        'copyright' => '© 2024',
    ])
    ->store();

// Access later
$media->custom_properties['alt_text'];
```

### `toDisk($disk)`

Store on a specific filesystem disk.

```php
Archive::add($file)->toDisk('s3')->store();
Archive::add($file)->toDisk('local')->store();
```

### `preservingOriginal()`

Keep the original file after storing (default: deletes original).

```php
Archive::add('/tmp/photo.jpg')
    ->preservingOriginal()
    ->store();
// Original file at /tmp/photo.jpg still exists
```

### `withOrder($order)`

Set explicit ordering within a collection.

```php
Archive::add($file1)->toCollection('gallery')->withOrder(1)->store();
Archive::add($file2)->toCollection('gallery')->withOrder(2)->store();
```

### `store()`

Finalize and save the media. This method:
1. Validates the file exists
2. Wraps operations in a database transaction
3. Creates the Media model record
4. Stores the file to the configured disk
5. Deletes the original (unless `preservingOriginal()`)
6. Returns the Media instance

```php
$media = Archive::add($file)->store();

echo $media->id;
echo $media->file_name;
echo $media->getUrl();
```

## Query Builder

The Media model includes a custom query builder with convenient scopes:

```php
// Filter by collection
$avatars = Media::inCollection('avatars')->get();

// Filter by curator
$userMedia = Media::curatedBy($user)->get();

// Get anonymous media
$orphans = Media::anonymous()->get();

// Filter by disk
$s3Media = Media::onDisk('s3')->get();

// Filter by MIME type
$images = Media::ofType('image')->get();
$pdfs = Media::ofType('application/pdf')->get();

// Get ordered media
$gallery = Media::inCollection('gallery')->ordered()->get();

// Chain multiple scopes
$userAvatars = Media::curatedBy($user)
    ->inCollection('avatars')
    ->ofType('image')
    ->get();
```

## Chaining Examples

### Minimal

```php
$media = Archive::add($file)->store();
```

### Full Featured

```php
$media = Archive::add($request->file('document'))
    ->toCurator($project)
    ->toCollection('project-files')
    ->withFileName('report-2024.pdf')
    ->withName('Annual Report')
    ->toDisk('s3')
    ->withProperties([
        'department' => 'Finance',
        'year' => 2024,
        'confidential' => true,
    ])
    ->withOrder(1)
    ->preservingOriginal()
    ->store();
```

### Multiple Files

```php
$mediaCollection = collect($request->file('photos'))->map(
    fn ($file, $index) => Archive::add($file)
        ->toCurator($album)
        ->toCollection('photos')
        ->withOrder($index)
        ->store()
);
```

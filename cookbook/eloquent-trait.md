# Eloquent Trait Usage

The `HasArchive` trait provides the `media()` relationship and implements the `Curator` interface for Eloquent models.

## Setup

Add the trait to your model and implement the `Curator` interface:

```php
use Cline\Archive\Contracts\Curator;
use Cline\Archive\Models\Concerns\HasArchive;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements Curator
{
    use HasArchive;
}
```

## What the Trait Provides

### `media()` Relationship

Returns a morph-many relationship to all media owned by this model.

```php
$user->media; // All media
$user->media()->where('collection', 'avatars')->get();
$user->media()->count();
```

### Curator Interface Implementation

The trait implements `getCuratorId()` and `getCuratorType()`:

```php
// Automatically provided:
$user->getCuratorId();    // Returns $user->getKey() as string
$user->getCuratorType();  // Returns $user->getMorphClass()
```

## Adding Media

Use `Archive::add()` with `->toCurator()`:

```php
Archive::add($file)
    ->toCurator($user)
    ->toCollection('avatars')
    ->store();
```

## Retrieving Media

Query the `media()` relationship:

```php
// Get all media in a collection
$avatars = $user->media()->where('collection', 'avatars')->get();

// Get first media in a collection
$avatar = $user->media()->where('collection', 'avatars')->first();

// Get all media
$allMedia = $user->media;
```

## Deleting Media

Delete media by querying the relationship:

```php
// Delete all media in a collection
$user->media()->where('collection', 'avatars')->delete();

// Delete all media
$user->media()->delete();
```

## Complete Examples

### User Avatar

```php
class User extends Model implements Curator
{
    use HasArchive;

    public function updateAvatar(UploadedFile $file): void
    {
        // Clear old avatar
        $this->media()->where('collection', 'avatars')->delete();

        // Add new avatar
        Archive::add($file)
            ->toCurator($this)
            ->toCollection('avatars')
            ->withFileName("{$this->id}-avatar.jpg")
            ->store();
    }

    public function getAvatarUrl(): ?string
    {
        return $this->media()
            ->where('collection', 'avatars')
            ->first()?->getUrl();
    }
}

// Usage
$user->updateAvatar($request->file('avatar'));
$url = $user->getAvatarUrl();
```

### Product Gallery

```php
class Product extends Model implements Curator
{
    use HasArchive;

    public function addGalleryImage(UploadedFile $file, array $metadata = []): Media
    {
        $position = $this->media()->where('collection', 'gallery')->count() + 1;

        return Archive::add($file)
            ->toCurator($this)
            ->toCollection('gallery')
            ->withOrder($position)
            ->withProperties($metadata)
            ->store();
    }

    public function getGalleryImages()
    {
        return $this->media()
            ->where('collection', 'gallery')
            ->orderBy('order_column')
            ->get();
    }

    public function getFeaturedImage(): ?Media
    {
        return $this->media()
            ->where('collection', 'gallery')
            ->orderBy('order_column')
            ->first();
    }
}

// Usage
$product->addGalleryImage($file, ['caption' => 'Front view']);
$images = $product->getGalleryImages();
```

### Document Attachments

```php
class Project extends Model implements Curator
{
    use HasArchive;

    public function attachDocument(
        UploadedFile $file,
        string $category,
        array $metadata = []
    ): Media {
        return Archive::add($file)
            ->toCurator($this)
            ->toCollection("documents.{$category}")
            ->withName($file->getClientOriginalName())
            ->withProperties(array_merge(['category' => $category], $metadata))
            ->toDisk('s3')
            ->store();
    }

    public function getDocuments(string $category = null)
    {
        if ($category) {
            return $this->media()
                ->where('collection', "documents.{$category}")
                ->get();
        }

        return $this->media()
            ->where('collection', 'like', 'documents.%')
            ->get();
    }
}

// Usage
$project->attachDocument($file, 'proposals', [
    'version' => '2.1',
    'author' => 'Jane Doe',
]);

$proposals = $project->getDocuments('proposals');
$allDocs = $project->getDocuments();
```

## Relationship Features

Since `media()` is a standard Eloquent relationship, you can use all relationship features:

```php
// Eager loading
$users = User::with('media')->get();
$users = User::with(['media' => fn($q) => $q->where('collection', 'avatars')])->get();

// Counting
$users = User::withCount('media')->get();
$user->media_count;

// Existence
User::has('media')->get();
User::whereHas('media', fn($q) => $q->where('collection', 'documents'))->get();

// Lazy eager loading
$users->load('media');
```

## Query Builder Scopes

The Media model provides convenient query scopes:

```php
// Filter by collection
$user->media()->inCollection('avatars')->get();

// Filter by disk
$user->media()->onDisk('s3')->get();

// Filter by MIME type
$user->media()->ofType('image')->get();

// Get ordered media
$user->media()->inCollection('gallery')->ordered()->get();

// Chain multiple scopes
$user->media()
    ->inCollection('avatars')
    ->ofType('image')
    ->onDisk('s3')
    ->get();
```

# Collections

Collections organize media into logical groups, similar to folders or categories.

## Basic Collections

```php
// Different collections for different purposes
Archive::add($avatar)->toCurator($user)->toCollection('avatars')->store();
Archive::add($cover)->toCurator($user)->toCollection('covers')->store();
Archive::add($doc)->toCurator($user)->toCollection('documents')->store();

// Retrieve by collection
$avatars = $user->media()->where('collection', 'avatars')->get();
$documents = $user->media()->where('collection', 'documents')->get();
```

## Nested Collections

Use dot notation for hierarchical organization:

```php
Archive::add($file)->toCollection('documents.invoices')->store();
Archive::add($file)->toCollection('documents.receipts')->store();
Archive::add($file)->toCollection('images.products.featured')->store();
Archive::add($file)->toCollection('images.products.gallery')->store();
```

## Default Collection

If not specified, media goes into the 'default' collection:

```php
Archive::add($file)->store();
// Same as:
Archive::add($file)->toCollection('default')->store();
```

## Collection Queries

```php
// All media in a collection
$invoices = Media::where('collection', 'documents.invoices')->get();

// Pattern matching
$allDocuments = Media::where('collection', 'like', 'documents.%')->get();

// By curator and collection
$userInvoices = Media::where('curator_type', User::class)
    ->where('curator_id', $user->id)
    ->where('collection', 'invoices')
    ->get();
```

## Organizing Collections

### By Purpose

```php
class User extends Model
{
    use HasArchive;

    public const COLLECTION_AVATAR = 'avatar';
    public const COLLECTION_DOCUMENTS = 'documents';
    public const COLLECTION_SIGNATURES = 'signatures';

    public function updateAvatar(UploadedFile $file): Media
    {
        $this->media()->where('collection', self::COLLECTION_AVATAR)->delete();

        return Archive::add($file)
            ->toCurator($this)
            ->toCollection(self::COLLECTION_AVATAR)
            ->store();
    }

    public function addDocument(UploadedFile $file, string $type): Media
    {
        return Archive::add($file)
            ->toCurator($this)
            ->toCollection(self::COLLECTION_DOCUMENTS . ".{$type}")
            ->store();
    }
}
```

### By Date

```php
$collection = 'uploads.' . now()->format('Y.m');

Archive::add($file)
    ->toCollection($collection)
    ->store();

// uploads.2024.11
```

### By Category

```php
class Product extends Model
{
    use HasArchive;

    public function addImage(
        UploadedFile $file,
        string $category = 'general'
    ): Media {
        return Archive::add($file)
            ->toCurator($this)
            ->toCollection("images.{$category}")
            ->store();
    }
}

$product->addImage($file, 'featured');
$product->addImage($file, 'gallery');
$product->addImage($file, 'technical');
```

## Collection Management

### Clear Specific Collection

```php
// Clear all avatars for a user
$user->media()->where('collection', 'avatars')->delete();

// Clear nested collection
$user->media()->where('collection', 'documents.invoices')->delete();
```

### Replace Collection

```php
public function replaceGallery(array $files): void
{
    $this->media()->where('collection', 'gallery')->delete();

    foreach ($files as $index => $file) {
        Archive::add($file)
            ->toCurator($this)
            ->toCollection('gallery')
            ->withOrder($index + 1)
            ->store();
    }
}
```

### Move Between Collections

```php
$media = Media::find(1);
$media->collection = 'archive';
$media->save();
```

## Collection Patterns

### Single-Item Collections

For resources that have exactly one media item:

```php
class Company extends Model
{
    use HasArchive;

    public function setLogo(UploadedFile $file): Media
    {
        $this->media()->where('collection', 'logo')->delete();

        return Archive::add($file)
            ->toCurator($this)
            ->toCollection('logo')
            ->store();
    }

    public function getLogo(): ?Media
    {
        return $this->media()->where('collection', 'logo')->first();
    }

    public function getLogoUrl(): ?string
    {
        return $this->getLogo()?->getUrl();
    }
}
```

### Multi-Item Collections

For resources with multiple media items:

```php
class Album extends Model
{
    use HasArchive;

    public function addPhoto(UploadedFile $file, array $metadata = []): Media
    {
        return Archive::add($file)
            ->toCurator($this)
            ->toCollection('photos')
            ->withProperties($metadata)
            ->store();
    }

    public function getPhotos()
    {
        return $this->media()
            ->where('collection', 'photos')
            ->orderBy('order_column')
            ->get();
    }

    public function removePhoto(Media $photo): void
    {
        if ($photo->curator_id === $this->id) {
            $photo->delete();
        }
    }
}
```

### Polymorphic Collections

Share collection names across different models:

```php
// Users, Products, and Posts all have 'featured_image'
Archive::add($file)->toCurator($user)->toCollection('featured_image')->store();
Archive::add($file)->toCurator($product)->toCollection('featured_image')->store();
Archive::add($file)->toCurator($post)->toCollection('featured_image')->store();

// Query all featured images across types
$featuredImages = Media::where('collection', 'featured_image')
    ->with('curator')
    ->get();
```

### Temporary Collections

For uploads pending approval:

```php
Archive::add($file)
    ->toCurator($user)
    ->toCollection('temp.pending_approval')
    ->withProperties(['uploaded_at' => now()])
    ->store();

// Later, after approval
$media = Media::find($id);
$media->collection = 'documents.approved';
$media->save();

// Clean up old temp files
Media::where('collection', 'like', 'temp.%')
    ->where('created_at', '<', now()->subDays(7))
    ->delete();
```

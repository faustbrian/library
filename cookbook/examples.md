# Examples

Real-world examples demonstrating common Archive usage patterns.

## User Profile System

```php
use Cline\Archive\Models\Concerns\HasArchive;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasArchive;

    public function updateAvatar(UploadedFile $file): void
    {
        $this->media()->where('collection', 'avatar')->delete();

        Archive::add($file)
            ->toCurator($this)
            ->toCollection('avatar')
            ->withFileName("{$this->id}-avatar.jpg")
            ->withProperties(['uploaded_by' => auth()->id()])
            ->store();
    }

    public function updateCoverPhoto(UploadedFile $file): void
    {
        $this->media()->where('collection', 'cover')->delete();

        Archive::add($file)
            ->toCurator($this)
            ->toCollection('cover')
            ->store();
    }

    public function getAvatarUrl(): string
    {
        return $this->media()->where('collection', 'avatar')->first()?->getUrl()
            ?? asset('images/default-avatar.jpg');
    }

    public function getCoverUrl(): string
    {
        return $this->media()->where('collection', 'cover')->first()?->getUrl()
            ?? asset('images/default-cover.jpg');
    }
}

// Controller
class ProfileController
{
    public function updateAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:2048']);

        auth()->user()->updateAvatar($request->file('avatar'));

        return back()->withProperties('success', 'Avatar updated!');
    }
}

// Blade
<img src="{{ auth()->user()->getAvatarUrl() }}" alt="Avatar">
```

## E-commerce Product Gallery

```php
class Product extends Model
{
    use HasArchive;

    public function addGalleryImage(
        UploadedFile $file,
        bool $featured = false
    ): Media {
        $collection = $featured ? 'images.featured' : 'images.gallery';
        $position = $this->media()->where('collection', $collection)->count() + 1;

        return Archive::add($file)
            ->toCurator($this)
            ->toCollection($collection)
            ->withOrder($position)
            ->toDisk('s3-public')
            ->store();
    }

    public function getFeaturedImage(): ?Media
    {
        return $this->media()->where('collection', 'images.featured')->first();
    }

    public function getGalleryImages()
    {
        return $this->media()
            ->where('collection', 'images.gallery')
            ->orderBy('order_column')
            ->get();
    }

    public function reorderGallery(array $mediaIds): void
    {
        foreach ($mediaIds as $position => $mediaId) {
            Media::where('id', $mediaId)
                ->where('curator_id', $this->id)
                ->update(['order_column' => $position + 1]);
        }
    }
}

// Controller
class ProductImageController
{
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'required|image',
            'featured' => 'boolean',
        ]);

        $media = $product->addGalleryImage(
            $request->file('image'),
            $request->boolean('featured')
        );

        return response()->json($media);
    }

    public function reorder(Request $request, Product $product)
    {
        $request->validate(['order' => 'required|array']);

        $product->reorderGallery($request->input('order'));

        return response()->json(['success' => true]);
    }
}
```

## Document Management System

```php
class Project extends Model
{
    use HasArchive;

    public function uploadDocument(
        UploadedFile $file,
        string $category,
        array $metadata = []
    ): Media {
        return Archive::add($file)
            ->toCurator($this)
            ->toCollection("documents.{$category}")
            ->withName($file->getClientOriginalName())
            ->toDisk('s3-private')
            ->withProperties(array_merge([
                'category' => $category,
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now()->toIso8601String(),
            ], $metadata))
            ->store();
    }

    public function getDocuments(string $category = null)
    {
        if ($category) {
            return $this->media()->where('collection', "documents.{$category}")->get();
        }

        return $this->media()
            ->where('collection', 'like', 'documents.%')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getDocumentCategories(): array
    {
        return $this->media()
            ->where('collection', 'like', 'documents.%')
            ->get()
            ->pluck('custom_properties.category')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}

// Controller
class DocumentController
{
    public function store(Request $request, Project $project)
    {
        $request->validate([
            'document' => 'required|file|max:10240',
            'category' => 'required|string',
            'version' => 'nullable|string',
        ]);

        $media = $project->uploadDocument(
            $request->file('document'),
            $request->input('category'),
            $request->only('version')
        );

        return redirect()
            ->route('projects.show', $project)
            ->withProperties('success', 'Document uploaded!');
    }

    public function download(Project $project, Media $media)
    {
        abort_unless($media->curator_id === $project->id, 403);

        return Storage::disk($media->disk)->download(
            $media->getPath(),
            $media->name
        );
    }
}
```

## Blog with Featured Images

```php
class Post extends Model
{
    use HasArchive;

    protected static function booted()
    {
        static::deleting(function (Post $post) {
            $post->media()->where('collection', 'featured')->delete();
            $post->media()->where('collection', 'attachments')->delete();
        });
    }

    public function setFeaturedImage(UploadedFile|string $file): void
    {
        $this->media()->where('collection', 'featured')->delete();

        Archive::add($file)
            ->toCurator($this)
            ->toCollection('featured')
            ->withProperties([
                'alt_text' => $this->title,
                'caption' => null,
            ])
            ->store();
    }

    public function addAttachment(UploadedFile $file): Media
    {
        return Archive::add($file)
            ->toCurator($this)
            ->toCollection('attachments')
            ->store();
    }

    public function getFeaturedImageUrl(): ?string
    {
        return $this->media()->where('collection', 'featured')->first()?->getUrl();
    }

    public function hasFeaturedImage(): bool
    {
        return $this->media()->where('collection', 'featured')->exists();
    }
}

// Usage in views
@if($post->hasFeaturedImage())
    <img src="{{ $post->getFeaturedImageUrl() }}" alt="{{ $post->title }}">
@endif
```

## Invoice System with PDFs

```php
class Invoice extends Model
{
    use HasArchive;

    public function generatePdf(): Media
    {
        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $this]);
        $path = storage_path("app/temp/invoice-{$this->id}.pdf");
        $pdf->save($path);

        $this->media()->where('collection', 'pdf')->delete();

        $media = Archive::add($path)
            ->toCurator($this)
            ->toCollection('pdf')
            ->withFileName("invoice-{$this->number}.pdf")
            ->withProperties([
                'generated_at' => now()->toIso8601String(),
                'invoice_number' => $this->number,
            ])
            ->store();

        return $media;
    }

    public function getPdfUrl(): ?string
    {
        $media = $this->media()->where('collection', 'pdf')->first();

        if (!$media) {
            return null;
        }

        // Generate temporary signed URL for private files
        return $media->getTemporaryUrl(now()->addMinutes(30));
    }

    public function downloadPdf()
    {
        $media = $this->media()->where('collection', 'pdf')->first();

        if (!$media) {
            $media = $this->generatePdf();
        }

        return Storage::disk($media->disk)->download(
            $media->getPath(),
            "invoice-{$this->number}.pdf"
        );
    }
}

// Controller
class InvoiceController
{
    public function download(Invoice $invoice)
    {
        return $invoice->downloadPdf();
    }

    public function regeneratePdf(Invoice $invoice)
    {
        $invoice->generatePdf();

        return back()->withProperties('success', 'PDF regenerated!');
    }
}
```

## Multi-Tenant File Storage

```php
class Tenant implements Curator
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $slug,
    ) {}

    public function getCuratorId(): string
    {
        return $this->uuid;
    }

    public function getCuratorType(): string
    {
        return 'tenants';
    }

    public function uploadBrandingAsset(
        UploadedFile $file,
        string $type
    ): Media {
        // Clear existing asset of this type
        Media::where('curator_id', $this->uuid)
            ->where('curator_type', 'tenants')
            ->where('collection', "branding.{$type}")
            ->delete();

        return Archive::add($file)
            ->toCurator($this)
            ->toCollection("branding.{$type}")
            ->withProperties([
                'tenant_slug' => $this->slug,
                'asset_type' => $type,
            ])
            ->toDisk('s3-tenants')
            ->store();
    }

    public function getBrandingAsset(string $type): ?Media
    {
        return Media::where('curator_id', $this->uuid)
            ->where('curator_type', 'tenants')
            ->where('collection', "branding.{$type}")
            ->first();
    }

    public function getLogoUrl(): ?string
    {
        return $this->getBrandingAsset('logo')?->getUrl();
    }
}

// Usage
$tenant = new Tenant(
    uuid: '550e8400-e29b-41d4-a716-446655440000',
    slug: 'acme-corp'
);

$tenant->uploadBrandingAsset($logoFile, 'logo');
$tenant->uploadBrandingAsset($faviconFile, 'favicon');

$logoUrl = $tenant->getLogoUrl();
```

## Temporary Upload Processing

```php
class UploadController
{
    public function store(Request $request)
    {
        $request->validate(['file' => 'required|file']);

        // Store in temporary collection
        $media = Archive::add($request->file('file'))
            ->toCollection('temp.uploads')
            ->withProperties([
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
            ])
            ->store();

        return response()->json([
            'id' => $media->id,
            'url' => $media->getUrl(),
        ]);
    }

    public function finalize(Request $request)
    {
        $request->validate([
            'media_id' => 'required|exists:media,id',
            'curator_type' => 'required|string',
            'curator_id' => 'required',
            'collection' => 'required|string',
        ]);

        $media = Media::findOrFail($request->input('media_id'));

        // Move from temp to permanent collection
        $media->curator_type = $request->input('curator_type');
        $media->curator_id = $request->input('curator_id');
        $media->collection = $request->input('collection');
        $media->save();

        return response()->json(['success' => true]);
    }
}

// Clean up abandoned temp uploads
Schedule::command(function () {
    Media::where('collection', 'temp.uploads')
        ->where('created_at', '<', now()->subHours(24))
        ->delete();
})->daily();
```

# Archive Package - Improvement Tasks

This document contains a comprehensive list of improvements, fixes, and enhancements identified during the code review. Tasks are organized by priority and category.

---

## 🚨 Critical Issues (Must Fix Before Production)

### TASK-001: HasArchive trait must implement Curator interface

**File:** `src/Models/Concerns/HasArchive.php:27`

**Problem:**
The trait docblock claims to implement the Curator interface but doesn't have a formal `implements Curator` declaration. This means type checks like `$curator instanceof Curator` will fail for models using this trait.

**Current Code:**
```php
/**
 * @mixin Model
 */
trait HasArchive
{
    // ... methods that match Curator interface
}
```

**Solution:**
Since traits can't implement interfaces directly, models using HasArchive must implement Curator:

```php
/**
 * @mixin Model
 */
trait HasArchive
{
    // Add note in docblock:
    // Models using this trait must implement Cline\Archive\Contracts\Curator

    public function getCuratorId(): string
    {
        return (string) $this->getKey();
    }

    public function getCuratorType(): string
    {
        return $this->getMorphClass();
    }
}
```

Then update TestModel to:
```php
class TestModel extends Model implements \Cline\Archive\Contracts\Curator
{
    use HasArchive;
}
```

**Alternative:** Create a base CuratorModel class that implements the interface.

**Impact:** High - breaks type safety for curator checks
**Effort:** Low - 15 minutes

---

### TASK-002: Remove final keyword from DefaultPathGenerator

**File:** `src/Storage/PathGenerator/DefaultPathGenerator.php:29`

**Problem:**
The class is marked `final` but the test suite tries to extend it with an anonymous class, causing test failures:
```
Class DefaultPathGenerator@anonymous cannot extend final class DefaultPathGenerator
```

**Current Code:**
```php
final class DefaultPathGenerator implements PathGenerator
```

**Solution Option 1 (Recommended):**
Remove `final` keyword since the interface exists for customization:
```php
class DefaultPathGenerator implements PathGenerator
```

**Solution Option 2:**
Refactor the test to use composition instead of inheritance:
```php
it('can use custom path generator', function (): void {
    $customGenerator = new class implements PathGenerator {
        public function getPath(Media $media): string
        {
            return 'custom/'.$media->collection.'/'.$media->getKey().'/'.$media->file_name;
        }

        public function getPathForConversions(Media $media): string
        {
            return 'custom/'.$media->collection.'/'.$media->getKey().'/conversions/';
        }
    };

    // ... rest of test
});
```

**Impact:** High - tests are currently failing
**Effort:** Low - 5 minutes

---

### TASK-003: Implement max_file_size validation

**Files:**
- `config/archive.php:73` (defines limit)
- `src/Storage/MediaAdder.php:310` (should validate)

**Problem:**
Configuration defines `max_file_size` (10MB default) but MediaAdder never validates against it. This is a security and resource management issue.

**Current Code:**
```php
// config/archive.php
'max_file_size' => 1024 * 1024 * 10, // 10MB

// MediaAdder.php - no validation!
public function store(): Media
{
    if (!is_file($this->pathToFile)) {
        throw FileDoesNotExist::create($this->pathToFile);
    }
    // Missing: file size check here
}
```

**Solution:**
Create new exception and add validation:

```php
// src/Exceptions/FileTooLarge.php
final class FileTooLarge extends InvalidArgumentException
{
    public static function create(string $path, int $size, int $maxSize): self
    {
        $sizeMB = round($size / 1024 / 1024, 2);
        $maxMB = round($maxSize / 1024 / 1024, 2);

        return new self(
            sprintf(
                'File at path "%s" exceeds maximum size. File size: %sMB, Maximum: %sMB',
                $path,
                $sizeMB,
                $maxMB
            )
        );
    }
}

// MediaAdder.php
public function store(): Media
{
    if (!is_file($this->pathToFile)) {
        throw FileDoesNotExist::create($this->pathToFile);
    }

    $fileSize = filesize($this->pathToFile);
    $maxSize = config('archive.max_file_size');

    if ($maxSize > 0 && $fileSize > $maxSize) {
        throw FileTooLarge::create($this->pathToFile, $fileSize, $maxSize);
    }

    // ... rest of method
}
```

**Tests to Add:**
```php
it('throws exception when file exceeds max size', function (): void {
    config()->set('archive.max_file_size', 100); // 100 bytes
    $file = $this->createTempFile('large.txt', str_repeat('x', 200));

    expect(fn() => Archive::add($file)->store())
        ->toThrow(FileTooLarge::class);
});

it('allows file at exact max size', function (): void {
    config()->set('archive.max_file_size', 100);
    $file = $this->createTempFile('exact.txt', str_repeat('x', 100));

    expect(Archive::add($file)->store())->toBeInstanceOf(Media::class);
});

it('disables size check when max_file_size is 0', function (): void {
    config()->set('archive.max_file_size', 0);
    $file = $this->createTempFile('huge.txt', str_repeat('x', 999999));

    expect(Archive::add($file)->store())->toBeInstanceOf(Media::class);
});
```

**Impact:** High - security and resource management
**Effort:** Medium - 30 minutes

---

### TASK-004: Fix uppercase PHP extension security bypass

**File:** `src/Storage/MediaAdder.php:375-391`

**Problem:**
The sanitizer blocks `.php` but applies `mb_strtolower()` AFTER the extension check, allowing `.PHP`, `.Php`, etc. to bypass security.

**Current Code:**
```php
private function sanitizeFileName(string $fileName): string
{
    // Remove control characters
    $sanitized = preg_replace('#\p{C}+#u', '', $fileName);

    // Replace problematic characters
    $sanitized = str_replace(['#', '/', '\\', ' '], '-', $sanitized);

    // Block PHP extensions - BUT sanitized is not lowercased yet!
    $phpExtensions = ['.php', '.php3', '.php4', /* ... */];

    throw_if(
        Str::endsWith(mb_strtolower($sanitized), $phpExtensions), // lowercase HERE
        InvalidArgumentException::class,
        'PHP files are not allowed: '.$fileName
    );

    return $sanitized; // Original case returned!
}
```

**Solution:**
Apply lowercase before checking:

```php
private function sanitizeFileName(string $fileName): string
{
    // Remove control characters
    $sanitized = preg_replace('#\p{C}+#u', '', $fileName);

    // Replace problematic characters
    $sanitized = str_replace(['#', '/', '\\', ' '], '-', $sanitized);

    // Normalize to lowercase for security checks
    $lowerFileName = mb_strtolower($sanitized);

    // Block PHP file extensions to prevent code execution
    $phpExtensions = [
        '.php', '.php3', '.php4', '.php5', '.php7', '.php8',
        '.phtml', '.phar',
    ];

    foreach ($phpExtensions as $ext) {
        if (Str::endsWith($lowerFileName, $ext)) {
            throw new InvalidArgumentException('PHP files are not allowed: '.$fileName);
        }
    }

    return $sanitized;
}
```

**Tests to Add:**
```php
it('blocks uppercase PHP extensions', function (): void {
    $file = $this->createTempFile('malicious.PHP', 'code');

    expect(fn() => Archive::add($file)->store())
        ->toThrow(InvalidArgumentException::class, 'PHP files are not allowed');
});

it('blocks mixed case PHP extensions', function (): void {
    $file = $this->createTempFile('script.PhP', 'code');

    expect(fn() => Archive::add($file)->store())
        ->toThrow(InvalidArgumentException::class);
});

it('blocks uppercase phtml extension', function (): void {
    $file = $this->createTempFile('page.PHTML', 'code');

    expect(fn() => Archive::add($file)->store())
        ->toThrow(InvalidArgumentException::class);
});
```

**Impact:** Critical - security vulnerability
**Effort:** Low - 15 minutes

---

### TASK-005: Fix migration morph column type mismatch

**File:** `database/migrations/create_media_table.php.stub:21`

**Problem:**
Migration hardcodes `nullableUlidMorphs('curator')` but the Media model's primary key can be ID/UUID/ULID based on config. This creates a type mismatch when a model with integer IDs tries to be a curator.

**Current Code:**
```php
$table->nullableUlidMorphs('curator'); // Always ULID!

// But primary key is dynamic:
match ($primaryKeyType) {
    PrimaryKeyType::ULID => $table->ulid('id')->primary(),
    PrimaryKeyType::UUID => $table->uuid('id')->primary(),
    PrimaryKeyType::ID => $table->id(), // Integer!
};
```

**Solution:**
Use flexible morphs that adapt to the curator's key type:

```php
Schema::create('media', function (Blueprint $table) use ($primaryKeyType) {
    match ($primaryKeyType) {
        PrimaryKeyType::ULID => $table->ulid('id')->primary(),
        PrimaryKeyType::UUID => $table->uuid('id')->primary(),
        PrimaryKeyType::ID => $table->id(),
    };

    // Use flexible morphs instead of hardcoded ULID
    $table->nullableMorphs('curator');

    // ... rest of schema
});
```

**Documentation Note:**
Add to migration stub comments:
```php
// NOTE: curator_id type is flexible to support curators with different primary key types.
// This uses nullableMorphs() which creates curator_id as string, accommodating:
// - Integer IDs (stored as strings)
// - UUIDs (native strings)
// - ULIDs (native strings)
```

**Impact:** High - prevents models with integer IDs from being curators
**Effort:** Low - 10 minutes

---

## ⚠️ Architecture Concerns (Should Fix for v1.0)

### TASK-006: Remove conversion-related code entirely

**Files:**
- `src/Storage/PathGenerator/PathGenerator.php:48`
- `src/Storage/PathGenerator/DefaultPathGenerator.php:60`
- Config comments mention conversions

**Problem:**
Package description explicitly states "no conversions" but the PathGenerator interface and implementation include `getPathForConversions()`. This is dead code that confuses the API surface area and misleads users.

**Current Code:**
```php
// PathGenerator.php
interface PathGenerator
{
    public function getPath(Media $media): string;

    // This method serves no purpose!
    public function getPathForConversions(Media $media): string;
}
```

**Solution:**
Remove all conversion-related code:

```php
// PathGenerator.php
interface PathGenerator
{
    /**
     * Generates the complete storage path for a media file.
     */
    public function getPath(Media $media): string;
}

// DefaultPathGenerator.php
class DefaultPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/'.$media->file_name;
    }

    // Remove getPathForConversions() entirely

    private function getBasePath(Media $media): string
    {
        $prefix = config('archive.prefix', '');

        if ($prefix !== '') {
            $prefix = mb_trim($prefix, '/').'/';
        }

        return $prefix.$media->getKey();
    }
}
```

**Update Tests:**
Remove any tests that reference conversions.

**Update Config:**
```php
// Remove or update this comment section:
/*
|--------------------------------------------------------------------------
| Path Generator
|--------------------------------------------------------------------------
|
| The path generator determines how file paths are structured when files
| are stored in your archive. The default generator creates organized
| directory structures based on media IDs.
|
*/
```

**Impact:** Medium - simplifies API, prevents confusion
**Effort:** Low - 20 minutes

---

### TASK-007: Enforce MediaCollection constraints or remove feature

**Files:**
- `src/Support/MediaCollection.php`
- `src/Storage/MediaAdder.php:321`

**Problem:**
MediaCollection has methods like `curatedBy()`, `curatedByAnonymous()`, and `allowsAnonymous()` that suggest constraint enforcement, but these constraints are never validated. The only enforced constraint is `singleFile()`.

**Current Code:**
```php
// MediaCollection defines constraints but doesn't enforce them
$collection->curatedBy(Product::class)  // Not enforced!
           ->curatedByAnonymous();      // Not enforced!

// Only singleFile() is enforced in MediaAdder:
if ($registeredCollection?->isSingleFile() && $this->curator instanceof Curator) {
    // Clear existing media
}
```

**Solution Option 1 (Recommended): Enforce constraints**

```php
// MediaAdder.php
public function store(): Media
{
    // ... existing validation ...

    // Check if collection is registered and get its configuration
    $registeredCollection = MediaCollectionRegistry::get($this->collection);

    if ($registeredCollection) {
        // Validate curator type constraint
        if ($registeredCollection->getCuratorType() !== null) {
            if (!$this->curator) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Collection "%s" requires a curator of type "%s"',
                        $this->collection,
                        $registeredCollection->getCuratorType()
                    )
                );
            }

            if (!$this->curator instanceof $registeredCollection->getCuratorType()) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Collection "%s" requires curator of type "%s", got "%s"',
                        $this->collection,
                        $registeredCollection->getCuratorType(),
                        get_class($this->curator)
                    )
                );
            }
        }

        // Validate anonymous constraint
        if (!$registeredCollection->allowsAnonymous() && !$this->curator) {
            throw new InvalidArgumentException(
                sprintf(
                    'Collection "%s" does not allow anonymous media',
                    $this->collection
                )
            );
        }
    }

    return DB::transaction(function () use ($registeredCollection): Media {
        // ... existing code ...
    });
}
```

**Solution Option 2: Remove unused features**
If constraints won't be enforced, remove:
- `curatedBy()` method
- `curatedByAnonymous()` method
- `allowsAnonymous()` method
- `getCuratorType()` method
- Related properties

Keep only:
- `singleFile()` / `isSingleFile()` (actually enforced)
- `toDisk()` / `getDisk()` (actually used)

**Tests to Add (if enforcing):**
```php
it('enforces curator type constraint', function (): void {
    Archive::collection('products')->curatedBy(Product::class);

    $user = User::create(['name' => 'Test']);
    $file = $this->createTempFile('test.txt', 'content');

    expect(fn() => Archive::add($file)
        ->toCurator($user)
        ->toCollection('products')
        ->store()
    )->toThrow(InvalidArgumentException::class, 'requires curator of type');
});

it('enforces anonymous restriction', function (): void {
    Archive::collection('avatars')
        ->curatedBy(User::class); // Implicitly disallows anonymous

    $file = $this->createTempFile('avatar.jpg', 'image');

    expect(fn() => Archive::add($file)
        ->toCollection('avatars')
        ->store()
    )->toThrow(InvalidArgumentException::class, 'does not allow anonymous');
});
```

**Impact:** Medium - either enforce promises or remove confusion
**Effort:** Medium - 1 hour for enforcement, 30 min for removal

---

### TASK-008: Add MIME type validation to MediaCollection

**Files:**
- `src/Support/MediaCollection.php`
- `src/Storage/MediaAdder.php`

**Problem:**
Collections have no way to restrict file types. An "avatars" collection can't enforce "images only", a "documents" collection can't restrict to PDFs, etc.

**Solution:**
Add MIME type constraints to MediaCollection:

```php
// MediaCollection.php
final class MediaCollection
{
    private array $allowedMimeTypes = [];
    private array $blockedMimeTypes = [];

    /**
     * Restrict collection to specific MIME types.
     *
     * @param string|array<string> $mimeTypes MIME types or patterns (e.g., 'image/*', 'application/pdf')
     */
    public function acceptsMimeTypes(string|array $mimeTypes): self
    {
        $this->allowedMimeTypes = is_array($mimeTypes) ? $mimeTypes : [$mimeTypes];

        return $this;
    }

    /**
     * Block specific MIME types from this collection.
     *
     * @param string|array<string> $mimeTypes MIME types or patterns to block
     */
    public function rejectsMimeTypes(string|array $mimeTypes): self
    {
        $this->blockedMimeTypes = is_array($mimeTypes) ? $mimeTypes : [$mimeTypes];

        return $this;
    }

    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    public function getBlockedMimeTypes(): array
    {
        return $this->blockedMimeTypes;
    }

    /**
     * Check if a MIME type is allowed in this collection.
     */
    public function acceptsMimeType(string $mimeType): bool
    {
        // If blocklist exists, check it first
        if (!empty($this->blockedMimeTypes)) {
            foreach ($this->blockedMimeTypes as $blocked) {
                if ($this->matchesMimePattern($mimeType, $blocked)) {
                    return false;
                }
            }
        }

        // If allowlist exists, must match
        if (!empty($this->allowedMimeTypes)) {
            foreach ($this->allowedMimeTypes as $allowed) {
                if ($this->matchesMimePattern($mimeType, $allowed)) {
                    return true;
                }
            }
            return false; // Not in allowlist
        }

        return true; // No restrictions
    }

    private function matchesMimePattern(string $mimeType, string $pattern): bool
    {
        // Support wildcard patterns like 'image/*'
        if (str_ends_with($pattern, '/*')) {
            $prefix = substr($pattern, 0, -2);
            return str_starts_with($mimeType, $prefix.'/');
        }

        return $mimeType === $pattern;
    }
}
```

**Validate in MediaAdder:**
```php
// MediaAdder.php
public function store(): Media
{
    if (!is_file($this->pathToFile)) {
        throw FileDoesNotExist::create($this->pathToFile);
    }

    $mimeType = mime_content_type($this->pathToFile);
    $registeredCollection = MediaCollectionRegistry::get($this->collection);

    // Validate MIME type against collection constraints
    if ($registeredCollection && !$registeredCollection->acceptsMimeType($mimeType)) {
        throw new InvalidMimeTypeException(
            sprintf(
                'MIME type "%s" is not allowed in collection "%s"',
                $mimeType,
                $this->collection
            )
        );
    }

    // ... rest of method
}
```

**Usage Examples:**
```php
// Avatars: images only
Archive::collection('avatars')
    ->singleFile()
    ->acceptsMimeTypes(['image/*']);

// Documents: PDFs and Word docs only
Archive::collection('documents')
    ->acceptsMimeTypes([
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

// Videos: block executables
Archive::collection('videos')
    ->acceptsMimeTypes('video/*')
    ->rejectsMimeTypes(['application/x-executable', 'application/x-dosexec']);
```

**Tests:**
```php
it('enforces MIME type allowlist', function (): void {
    Archive::collection('images')->acceptsMimeTypes('image/*');

    $pdfFile = $this->createTempFile('doc.pdf', 'content');

    expect(fn() => Archive::add($pdfFile)
        ->toCollection('images')
        ->store()
    )->toThrow(InvalidMimeTypeException::class);
});

it('allows wildcard MIME patterns', function (): void {
    Archive::collection('media')->acceptsMimeTypes(['image/*', 'video/*']);

    $imageFile = $this->createTempFile('photo.jpg', 'image');
    $media = Archive::add($imageFile)->toCollection('media')->store();

    expect($media)->toBeInstanceOf(Media::class);
});
```

**Impact:** Medium - adds useful feature
**Effort:** Medium - 1.5 hours

---

### TASK-009: Cache path and URL generator instances

**Files:**
- `src/Models/Media.php:84-88, 100-104, 119-122`

**Problem:**
Every call to `getUrl()`, `getPath()`, or `getTemporaryUrl()` resolves the generator from the container via `app()`. This is inefficient when these methods are called repeatedly.

**Current Code:**
```php
public function getUrl(): string
{
    $urlGenerator = config('archive.url_generator');
    return app($urlGenerator)->getUrl($this); // Resolved every call!
}

public function getPath(): string
{
    $pathGenerator = config('archive.path_generator');
    return app($pathGenerator)->getPath($this); // Resolved every call!
}
```

**Solution Option 1: Singleton binding in service provider**

```php
// ArchiveServiceProvider.php
public function register(): void
{
    $this->mergeConfigFrom(__DIR__.'/../config/archive.php', 'archive');

    // Register generators as singletons
    $this->app->singleton(
        PathGenerator::class,
        fn($app) => $app->make(config('archive.path_generator'))
    );

    $this->app->singleton(
        UrlGenerator::class,
        fn($app) => $app->make(config('archive.url_generator'))
    );
}

// Media.php
public function getUrl(): string
{
    return app(UrlGenerator::class)->getUrl($this);
}

public function getPath(): string
{
    return app(PathGenerator::class)->getPath($this);
}
```

**Solution Option 2: Instance memoization**

```php
// Media.php
private static ?PathGenerator $pathGenerator = null;
private static ?UrlGenerator $urlGenerator = null;

public function getUrl(): string
{
    if (self::$urlGenerator === null) {
        self::$urlGenerator = app(config('archive.url_generator'));
    }

    return self::$urlGenerator->getUrl($this);
}

public function getPath(): string
{
    if (self::$pathGenerator === null) {
        self::$pathGenerator = app(config('archive.path_generator'));
    }

    return self::$pathGenerator->getPath($this);
}
```

**Impact:** Low-Medium - performance optimization
**Effort:** Low - 20 minutes

---

### TASK-010: Add disk validation before storage

**File:** `src/Storage/MediaAdder.php:194-204`

**Problem:**
Disk validation only happens when explicitly calling `toDisk()`. If using the default disk or collection-specific disk, validation is skipped until storage fails.

**Current Code:**
```php
public function toDisk(string $disk): static
{
    if (!array_key_exists($disk, config('filesystems.disks', []))) {
        throw InvalidDiskException::diskDoesNotExist($disk);
    }

    // ... set disk
}

public function store(): Media
{
    // No validation of default disk or collection disk!
    $media->disk = $this->disk ?: $registeredCollection?->getDisk() ?: config('archive.disk');
}
```

**Solution:**
Validate the final disk before storage:

```php
// MediaAdder.php
public function store(): Media
{
    // ... existing validation ...

    return DB::transaction(function () use ($registeredCollection): Media {
        $media = new Media();

        // Determine final disk
        $finalDisk = $this->disk
            ?: $registeredCollection?->getDisk()
            ?: config('archive.disk', config('filesystems.default'));

        // Validate disk exists
        if (!array_key_exists($finalDisk, config('filesystems.disks', []))) {
            throw InvalidDiskException::diskDoesNotExist($finalDisk);
        }

        $media->disk = $finalDisk;

        // ... rest of storage logic
    });
}
```

**Tests:**
```php
it('validates default disk from config', function (): void {
    config()->set('archive.disk', 'non-existent-disk');

    $file = $this->createTempFile('test.txt', 'content');

    expect(fn() => Archive::add($file)->store())
        ->toThrow(InvalidDiskException::class, 'non-existent-disk');
});

it('validates collection-specific disk', function (): void {
    Archive::collection('images')->toDisk('invalid-disk');

    $file = $this->createTempFile('image.jpg', 'content');

    expect(fn() => Archive::add($file)->toCollection('images')->store())
        ->toThrow(InvalidDiskException::class);
});
```

**Impact:** Medium - better error messages, fail fast
**Effort:** Low - 15 minutes

---

## 🔧 Code Quality Issues

### TASK-011: Fix MediaAdder clone sharing Filesystem instance

**File:** `src/Storage/MediaAdder.php:85-89, 103-128`

**Problem:**
MediaAdder uses immutable clone pattern but all clones share the same Filesystem instance injected in constructor. This could cause issues if Filesystem becomes stateful.

**Current Code:**
```php
public function __construct(
    private ?Filesystem $filesystem = null,
) {
    $this->filesystem ??= app(Filesystem::class);
}

public function setFile(UploadedFile|SymfonyFile|string $file): static
{
    $clone = clone $this; // Shares $this->filesystem!
    // ... set properties
    return $clone;
}
```

**Solution Option 1: Make Filesystem stateless (current state)**
Filesystem is already stateless, so document this:

```php
/**
 * Creates a new media adder instance.
 *
 * @param null|Filesystem $filesystem Filesystem handler for storage operations.
 *                                    Resolved from container if not provided.
 *                                    Note: Shared across clones as Filesystem is stateless.
 */
public function __construct(
    private ?Filesystem $filesystem = null,
) {
    $this->filesystem ??= app(Filesystem::class);
}
```

**Solution Option 2: Clone Filesystem too**
```php
public function __clone(): void
{
    $this->filesystem = clone $this->filesystem;
}
```

**Solution Option 3: Resolve Filesystem on demand**
```php
private function getFilesystem(): Filesystem
{
    return $this->filesystem ??= app(Filesystem::class);
}

public function store(): Media
{
    // ...
    $this->getFilesystem()->add($this->pathToFile, $media);
}
```

**Impact:** Low - mostly theoretical issue
**Effort:** Low - 10 minutes

---

### TASK-012: Simplify service provider timestamp generation

**File:** `src/ArchiveServiceProvider.php:61`

**Problem:**
Overly complex timestamp generation with redundant conversion.

**Current Code:**
```php
Date::createFromTimestamp(Date::now()->getTimestamp())->format('Y_m_d_His')
```

**Solution:**
```php
now()->format('Y_m_d_His')
```

**Impact:** Low - code clarity
**Effort:** Trivial - 2 minutes

---

### TASK-013: Remove asAnonymous() method or make it meaningful

**File:** `src/Storage/MediaAdder.php:148-161`

**Problem:**
`asAnonymous()` method does exactly the same thing as not calling `toCurator()`. It provides no value and adds API confusion.

**Current Code:**
```php
public function asAnonymous(): static
{
    $clone = clone $this;
    $clone->curator = null; // This is already the default!

    return $clone;
}
```

**Solution Option 1: Remove the method entirely**
Users can simply not call `toCurator()` for anonymous media.

**Solution Option 2: Make it explicit and useful**
```php
public function asAnonymous(): static
{
    $clone = clone $this;
    $clone->curator = null;
    $clone->requiresAnonymous = true; // New flag

    return $clone;
}

public function toCurator(Curator $curator): static
{
    // Throw if asAnonymous() was called
    if ($this->requiresAnonymous) {
        throw new LogicException('Cannot set curator on media marked as anonymous');
    }

    // ... existing code
}
```

**Impact:** Low - API cleanup
**Effort:** Low - 15 minutes

---

### TASK-014: Add PrimaryKeyType support to Media model

**Files:**
- `src/Enums/PrimaryKeyType.php` (defined)
- `config/archive.php:87` (configured)
- `database/migrations/create_media_table.php.stub:12` (used in migration)
- `src/Models/Media.php` (missing implementation!)

**Problem:**
PrimaryKeyType enum exists and migration uses it, but the Media model doesn't apply the necessary traits for UUID/ULID support.

**Current Code:**
```php
// Media.php - no UUID/ULID support!
final class Media extends Model
{
    use HasFactory;

    protected $table = 'media';
    // Missing: HasUuids or HasUlids trait
}
```

**Solution:**
Add conditional trait usage based on config:

```php
// Media.php
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

final class Media extends Model
{
    use HasFactory;

    protected $table = 'media';
    protected $guarded = [];

    /**
     * Get the primary key type for the model.
     */
    public function getKeyType(): string
    {
        return match (PrimaryKeyType::tryFrom(config('archive.primary_key_type', 'id'))) {
            PrimaryKeyType::UUID, PrimaryKeyType::ULID => 'string',
            default => 'int',
        };
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     */
    public function getIncrementing(): bool
    {
        return match (PrimaryKeyType::tryFrom(config('archive.primary_key_type', 'id'))) {
            PrimaryKeyType::UUID, PrimaryKeyType::ULID => false,
            default => true,
        };
    }

    /**
     * Boot the model and configure primary key strategy.
     */
    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            if (!$media->getKey()) {
                $keyType = PrimaryKeyType::tryFrom(config('archive.primary_key_type', 'id'));

                match ($keyType) {
                    PrimaryKeyType::UUID => $media->{$media->getKeyName()} = (string) Str::uuid(),
                    PrimaryKeyType::ULID => $media->{$media->getKeyName()} = (string) Str::ulid(),
                    default => null, // Auto-increment handles it
                };
            }
        });

        static::deleting(function (Media $media): void {
            // Automatically delete the physical file when the model is deleted
            app(Filesystem::class)->delete($media);
        });
    }
}
```

**Alternative Solution:**
Create separate model classes:
```php
// MediaWithUuid.php
class MediaWithUuid extends Media
{
    use HasUuids;
}

// MediaWithUlid.php
class MediaWithUlid extends Media
{
    use HasUlids;
}

// Then bind in service provider based on config
$this->app->bind(Media::class, function ($app) {
    return match (PrimaryKeyType::tryFrom(config('archive.primary_key_type'))) {
        PrimaryKeyType::UUID => new MediaWithUuid(),
        PrimaryKeyType::ULID => new MediaWithUlid(),
        default => new Media(),
    };
});
```

**Tests:**
```php
it('generates UUID when configured', function (): void {
    config()->set('archive.primary_key_type', 'uuid');

    $file = $this->createTempFile('test.txt', 'content');
    $media = Archive::add($file)->store();

    expect($media->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('generates ULID when configured', function (): void {
    config()->set('archive.primary_key_type', 'ulid');

    $file = $this->createTempFile('test.txt', 'content');
    $media = Archive::add($file)->store();

    expect($media->id)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});
```

**Impact:** Medium - config feature doesn't work
**Effort:** Medium - 45 minutes

---

## 📚 Missing Features

### TASK-015: Add file existence checks before URL generation

**Files:**
- `src/Models/Media.php:84, 117`
- `src/Support/UrlGenerator/DefaultUrlGenerator.php:44, 69`

**Problem:**
URL generation methods don't verify the physical file exists before generating URLs, potentially returning links to non-existent files.

**Current Code:**
```php
public function getUrl(): string
{
    return app(UrlGenerator::class)->getUrl($this);
    // No existence check!
}
```

**Solution Option 1: Add existence check**
```php
// Media.php
public function getUrl(): string
{
    if (!$this->exists()) {
        throw new FileDoesNotExist::create($this->getPath());
    }

    return app(UrlGenerator::class)->getUrl($this);
}

public function exists(): bool
{
    return Storage::disk($this->disk)->exists($this->getPath());
}
```

**Solution Option 2: Document the behavior**
```php
/**
 * Generates the public URL for accessing this media file.
 *
 * NOTE: This method does not verify the physical file exists.
 * Use exists() to check file existence before generating URLs if needed.
 *
 * @return string The public URL to access this media file
 */
public function getUrl(): string
{
    return app(UrlGenerator::class)->getUrl($this);
}
```

**Impact:** Low-Medium - depends on use case
**Effort:** Low - 15 minutes

---

### TASK-016: Add eager loading query scope

**File:** `src/Models/MediaQueryBuilder.php`

**Problem:**
No convenience method for eager loading curator relationship. Users must remember to use `with('curator')` manually.

**Solution:**
```php
// MediaQueryBuilder.php
/**
 * Eager load the curator relationship.
 */
public function withCurator(): static
{
    return $this->with('curator');
}

/**
 * Eager load curator only if media has one.
 */
public function withCuratorWhenPresent(): static
{
    return $this->with(['curator' => function ($query) {
        $query->whereNotNull('curator_id');
    }]);
}
```

**Usage:**
```php
// Before
$media = Media::query()->with('curator')->get();

// After
$media = Media::query()->withCurator()->get();
```

**Impact:** Low - convenience feature
**Effort:** Trivial - 10 minutes

---

### TASK-017: Add event system for extensibility

**Files:**
- Create `src/Events/MediaStored.php`
- Create `src/Events/MediaDeleting.php`
- Update `src/Storage/MediaAdder.php`
- Update `src/Models/Media.php`

**Problem:**
No way to hook into media lifecycle events. Users can't trigger actions when media is uploaded or deleted without modifying package code.

**Solution:**
Create event classes:

```php
// src/Events/MediaStored.php
namespace Cline\Archive\Events;

use Cline\Archive\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MediaStored
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Media $media,
    ) {}
}

// src/Events/MediaDeleting.php
namespace Cline\Archive\Events;

use Cline\Archive\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MediaDeleting
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Media $media,
    ) {}
}
```

Dispatch events:

```php
// MediaAdder.php
public function store(): Media
{
    return DB::transaction(function () use ($registeredCollection): Media {
        // ... create and save media

        $this->filesystem->add($this->pathToFile, $media);

        // Remove original file unless preservation is enabled
        if (!$this->preserveOriginal && is_file($this->pathToFile)) {
            unlink($this->pathToFile);
        }

        event(new MediaStored($media));

        return $media;
    });
}

// Media.php
protected static function booted(): void
{
    static::deleting(function (Media $media): void {
        event(new MediaDeleting($media));

        // Automatically delete the physical file when the model is deleted
        app(Filesystem::class)->delete($media);
    });
}
```

**Usage:**
```php
// In EventServiceProvider or listener
Event::listen(MediaStored::class, function (MediaStored $event) {
    // Generate thumbnails
    // Send notification
    // Update analytics
    Log::info('Media stored: '.$event->media->name);
});

Event::listen(MediaDeleting::class, function (MediaDeleting $event) {
    // Clean up related resources
    // Audit logging
});
```

**Impact:** Medium - enables extensibility
**Effort:** Medium - 30 minutes

---

## 🧪 Testing Improvements

### TASK-018: Add test isolation for MediaCollectionRegistry

**Files:**
- `tests/Pest.php`
- `src/Support/MediaCollectionRegistry.php:121`

**Problem:**
MediaCollectionRegistry uses global static state that persists between tests, potentially causing test pollution. The `clear()` method exists but tests don't call it.

**Solution:**
```php
// tests/Pest.php
uses(Tests\TestCase::class)->beforeEach(function () {
    // Existing setup...
})->afterEach(function () {
    // Clear global registry after each test
    \Cline\Archive\Support\MediaCollectionRegistry::clear();
})->in('Unit');
```

**Impact:** Medium - prevents flaky tests
**Effort:** Trivial - 5 minutes

---

### TASK-019: Add tests for missing scenarios

**Files:** Create new test cases

**Missing Test Coverage:**

**1. Single-file collection replacement:**
```php
it('replaces existing media in single-file collection', function (): void {
    Archive::collection('avatar')->singleFile();

    $model = TestModel::create(['name' => 'User']);

    $file1 = $this->createTempFile('avatar1.jpg', 'first');
    $media1 = Archive::add($file1)
        ->toCurator($model)
        ->toCollection('avatar')
        ->store();

    $file2 = $this->createTempFile('avatar2.jpg', 'second');
    $media2 = Archive::add($file2)
        ->toCurator($model)
        ->toCollection('avatar')
        ->store();

    // First media should be deleted
    expect(Media::find($media1->id))->toBeNull()
        ->and(Media::find($media2->id))->not->toBeNull();

    // Only one media should exist for this curator/collection
    expect($model->media()->where('collection', 'avatar')->count())->toBe(1);
});
```

**2. Collection-specific disk usage:**
```php
it('uses collection-specific disk over default', function (): void {
    Storage::fake('s3');
    Archive::collection('backups')->toDisk('s3');

    $file = $this->createTempFile('backup.zip', 'data');
    $media = Archive::add($file)->toCollection('backups')->store();

    expect($media->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($media->getPath());
});
```

**3. Different primary key type scenarios:**
```php
it('works with UUID primary keys', function (): void {
    config()->set('archive.primary_key_type', 'uuid');
    // Run migration with UUID config
    // Test media creation
});

it('works with ULID primary keys', function (): void {
    config()->set('archive.primary_key_type', 'ulid');
    // Run migration with ULID config
    // Test media creation
});

it('handles curator with different key type than media', function (): void {
    // Media uses ULIDs, curator uses integer IDs
    // Verify polymorphic relationship works
});
```

**4. Edge cases with file sanitization:**
```php
it('handles double extensions safely', function (): void {
    $file = $this->createTempFile('file.txt.php', 'content');

    expect(fn() => Archive::add($file)->store())
        ->toThrow(InvalidArgumentException::class);
});

it('handles very long file names', function (): void {
    $longName = str_repeat('a', 300).'.txt';
    $file = $this->createTempFile($longName, 'content');

    $media = Archive::add($file)->store();

    // Verify filename is stored (may be truncated by DB column)
    expect($media->file_name)->toBeString();
});
```

**Impact:** Medium - improves test coverage
**Effort:** Medium - 2 hours

---

## 📖 Documentation Improvements

### TASK-020: Update config comments to remove conversion references

**File:** `config/archive.php:46`

**Problem:**
Configuration comments reference conversions despite package not supporting them.

**Current Code:**
```php
/*
|--------------------------------------------------------------------------
| Path Generator
|--------------------------------------------------------------------------
|
| The path generator determines how file paths are structured when files
| are stored in your archive. The default generator creates organized
| directory structures, but you may implement your own path generator
| by creating a class that matches the PathGenerator interface.
|
*/
```

**Solution:**
```php
/*
|--------------------------------------------------------------------------
| Path Generator
|--------------------------------------------------------------------------
|
| The path generator determines how file paths are structured when files
| are stored in your archive. The default generator creates organized
| directory structures based on media IDs (e.g., "media/{id}/filename.jpg").
|
| You may implement your own path generator by creating a class that
| implements the PathGenerator interface. Useful for custom organization
| schemes like date-based paths, UUID-based paths, or collection-specific
| directory structures.
|
| Example custom generator:
|   - Date-based: "media/2024/01/15/{id}/file.jpg"
|   - Collection-based: "{collection}/{id}/file.jpg"
|   - Flat: "media/{uuid}-{filename}"
|
*/
```

**Impact:** Low - documentation clarity
**Effort:** Trivial - 10 minutes

---

### TASK-021: Add query scope examples to MediaQueryBuilder docblocks

**File:** `src/Models/MediaQueryBuilder.php`

**Problem:**
Query scopes lack usage examples, making them less discoverable.

**Solution:**
```php
/**
 * Scope query to specific collection.
 *
 * Example:
 * ```php
 * Media::query()->inCollection('avatars')->get();
 * ```
 *
 * @param string $collection Collection name to filter by
 */
public function inCollection(string $collection): static

/**
 * Scope query to specific curator.
 *
 * Example:
 * ```php
 * $user = User::find(1);
 * $userMedia = Media::query()->curatedBy($user)->get();
 * ```
 *
 * @param Curator $curator Curator to filter by
 */
public function curatedBy(Curator $curator): static

/**
 * Scope query to anonymous media (no curator).
 *
 * Example:
 * ```php
 * $orphans = Media::query()->anonymous()->get();
 * ```
 */
public function anonymous(): static

/**
 * Scope query to specific disk.
 *
 * Example:
 * ```php
 * $s3Media = Media::query()->onDisk('s3')->get();
 * ```
 *
 * @param string $disk Disk name to filter by
 */
public function onDisk(string $disk): static

/**
 * Scope query to specific MIME type or type category.
 *
 * Examples:
 * ```php
 * // Exact MIME type
 * Media::query()->ofType('image/jpeg')->get();
 *
 * // Type category (all images)
 * Media::query()->ofType('image')->get();
 * ```
 *
 * @param string $mimeType MIME type or prefix (e.g., 'image/jpeg' or 'image')
 */
public function ofType(string $mimeType): static

/**
 * Scope query to ordered media only.
 *
 * Returns media that has an order_column value, sorted by that column.
 *
 * Example:
 * ```php
 * $gallery = Media::query()
 *     ->inCollection('gallery')
 *     ->ordered()
 *     ->get();
 * ```
 */
public function ordered(): static
```

**Impact:** Low - improves developer experience
**Effort:** Low - 15 minutes

---

## Summary Statistics

- **Critical Issues:** 5 tasks (must fix before production)
- **Architecture Concerns:** 5 tasks (should fix for v1.0)
- **Code Quality:** 4 tasks (nice to have)
- **Missing Features:** 3 tasks (enhancements)
- **Testing Improvements:** 2 tasks (quality assurance)
- **Documentation:** 2 tasks (developer experience)

**Total:** 21 tasks

**Estimated Total Effort:** ~12-15 hours

**Recommended Priority Order:**
1. TASK-004 (security vulnerability)
2. TASK-001 (interface implementation)
3. TASK-002 (tests failing)
4. TASK-005 (migration bug)
5. TASK-003 (file size validation)
6. TASK-006 (remove dead code)
7. All remaining tasks based on project priorities

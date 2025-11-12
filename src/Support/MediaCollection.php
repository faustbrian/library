<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Defines configuration and constraints for a named media file collection.
 *
 * MediaCollection encapsulates the rules and settings for a group of related media
 * files (images, documents, videos, etc.) attached to models. Each collection can
 * specify file quantity constraints (single vs. multiple), storage location (disk),
 * ownership requirements, and access permissions. Collections are typically registered
 * within model classes using the InteractsWithMediaCollections trait.
 *
 * ```php
 * $collection = new MediaCollection('product-images');
 * $collection->toDisk('s3')
 *            ->curatedBy(Product::class);
 *
 * $avatar = new MediaCollection('avatar');
 * $avatar->singleFile()
 *        ->toDisk('public')
 *        ->curatedByAnonymous();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MediaCollection
{
    /**
     * Whether this collection is restricted to a single file.
     *
     * When true, only one file can exist in this collection at a time.
     * Useful for avatars, thumbnails, or other singular media items.
     */
    private bool $singleFile = false;

    /**
     * The filesystem disk where media files are stored.
     *
     * References a disk name from config/filesystems.php. When null,
     * the application's default disk will be used for storage operations.
     */
    private ?string $disk = null;

    /**
     * The Eloquent model class that can curate media in this collection.
     *
     * Restricts media curation to instances of the specified model class.
     * When null, any model can curate media in this collection.
     *
     * @var null|class-string<Model>
     */
    private ?string $curatorType = null;

    /**
     * Whether this collection permits media without a curator model.
     *
     * When true, media can exist in this collection without being attached
     * to a specific model instance. Useful for temporary uploads or shared
     * media libraries that aren't tied to individual records.
     */
    private bool $allowAnonymous = false;

    /**
     * Creates a new media collection with the specified name.
     *
     * @param string $name Unique identifier for this collection within its registration context
     */
    public function __construct(
        private readonly string $name,
    ) {}

    /**
     * Restricts this collection to contain only a single file.
     *
     * Configures the collection to allow exactly one media file. When a new
     * file is added to a single-file collection, the existing file should be
     * replaced or removed. Commonly used for profile pictures, primary images,
     * or featured documents.
     *
     * @return self Fluent interface for method chaining
     */
    public function singleFile(): self
    {
        $this->singleFile = true;

        return $this;
    }

    /**
     * Restricts media curation to instances of the specified model class.
     *
     * Enforces type safety by ensuring only the designated model type can curate
     * media in this collection. Useful for preventing incorrect associations
     * in polymorphic media systems where multiple models share collections.
     *
     * @param  class-string<Model> $modelClass The fully-qualified class name of the curator model
     * @return self                Fluent interface for method chaining
     */
    public function curatedBy(string $modelClass): self
    {
        $this->curatorType = $modelClass;

        return $this;
    }

    /**
     * Allows media to exist in this collection without a curator model.
     *
     * Permits orphaned media files that aren't attached to a specific model
     * instance. Useful for temporary file uploads pending user action, shared
     * media libraries, or content that exists independently of domain models.
     *
     * @return self Fluent interface for method chaining
     */
    public function curatedByAnonymous(): self
    {
        $this->allowAnonymous = true;

        return $this;
    }

    /**
     * Specifies the filesystem disk where media files will be stored.
     *
     * Sets the storage location for files in this collection using a disk name
     * defined in config/filesystems.php. Common values include 'local', 'public',
     * 's3', or custom disk configurations. The disk determines storage driver,
     * visibility, and access URL patterns.
     *
     * @param  string $disk The filesystem disk name from configuration
     * @return self   Fluent interface for method chaining
     */
    public function toDisk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Checks whether this collection is restricted to a single file.
     *
     * @return bool True if only one file can exist in this collection
     */
    public function isSingleFile(): bool
    {
        return $this->singleFile;
    }

    /**
     * Retrieves the collection's unique identifier.
     *
     * @return string The collection name used for registration and lookup
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Retrieves the configured filesystem disk for this collection.
     *
     * @return null|string The disk name or null if using the default disk
     */
    public function getDisk(): ?string
    {
        return $this->disk;
    }

    /**
     * Retrieves the model class that can curate media in this collection.
     *
     * @return null|class-string<Model> The curator model class or null if unrestricted
     */
    public function getCuratorType(): ?string
    {
        return $this->curatorType;
    }

    /**
     * Checks whether this collection permits media without a curator model.
     *
     * @return bool True if orphaned media is allowed in this collection
     */
    public function allowsAnonymous(): bool
    {
        return $this->allowAnonymous;
    }
}

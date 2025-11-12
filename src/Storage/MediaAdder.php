<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Storage;

use Cline\Archive\Contracts\Curator;
use Cline\Archive\Exceptions\FileDoesNotExist;
use Cline\Archive\Exceptions\FileTooLarge;
use Cline\Archive\Exceptions\InvalidDiskException;
use Cline\Archive\Models\Media;
use Cline\Archive\Support\MediaCollectionRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

use const PATHINFO_BASENAME;
use const PATHINFO_FILENAME;

use function app;
use function array_key_exists;
use function assert;
use function config;
use function filesize;
use function is_file;
use function is_int;
use function is_string;
use function mb_strtolower;
use function mime_content_type;
use function pathinfo;
use function preg_replace;
use function str_replace;
use function throw_if;
use function unlink;

/**
 * Fluent builder for adding media files to the archive system.
 *
 * This class provides a chainable interface for configuring and adding media
 * files to the archive. It handles file validation, sanitization, metadata
 * extraction, and storage operations. The builder pattern allows for flexible
 * configuration before committing the file to storage.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MediaAdder
{
    /** @var string Absolute path to the file being added */
    private string $pathToFile = '';

    /** @var string The file name that will be stored on disk (sanitized) */
    private string $fileName = '';

    /** @var string Human-readable name for the media file */
    private string $mediaName = '';

    /** @var string Collection name for logical grouping of media files */
    private string $collection = 'default';

    /** @var string Storage disk name where the file will be stored */
    private string $disk = '';

    /** @var array<string, mixed> Additional metadata to store with the media file */
    private array $customProperties = [];

    /** @var bool Whether to keep the original file after adding to storage */
    private bool $preserveOriginal = false;

    /** @var null|Curator The entity that owns this media file */
    private ?Curator $curator = null;

    /** @var null|int Optional ordering value for sorting media within a collection */
    private ?int $order = null;

    /**
     * Creates a new media adder instance.
     *
     * @param null|Filesystem $filesystem Filesystem handler for storage operations.
     *                                    When null, resolves from the service container
     *                                    using app(Filesystem::class) for dependency injection.
     */
    public function __construct(
        private ?Filesystem $filesystem = null,
    ) {
        $this->filesystem ??= app(Filesystem::class);
    }

    /**
     * Sets the file to be added to the archive.
     *
     * Accepts various file input types and extracts the file path, file name,
     * and media name from the provided file. Handles uploaded files, Symfony
     * file objects, and file system paths differently to extract appropriate
     * metadata from each type.
     *
     * @param  string|SymfonyFile|UploadedFile $file The file to add - can be an uploaded file,
     *                                               Symfony file object, or absolute file system path
     * @return static                          New immutable instance with file set
     */
    public function setFile(UploadedFile|SymfonyFile|string $file): static
    {
        $clone = clone $this;

        if (is_string($file)) {
            $clone->pathToFile = $file;
            $clone->fileName = pathinfo($file, PATHINFO_BASENAME);
            $clone->mediaName = pathinfo($file, PATHINFO_FILENAME);

            return $clone;
        }

        if ($file instanceof UploadedFile) {
            $clone->pathToFile = $file->getPath().'/'.$file->getFilename();
            $clone->fileName = $file->getClientOriginalName();
            $clone->mediaName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            return $clone;
        }

        $clone->pathToFile = $file->getPath().'/'.$file->getFilename();
        $clone->fileName = pathinfo($file->getFilename(), PATHINFO_BASENAME);
        $clone->mediaName = pathinfo($file->getFilename(), PATHINFO_FILENAME);

        return $clone;
    }

    /**
     * Associates the media file with a curator.
     *
     * Sets the entity that will curate this media file. The curator must implement
     * the Curator interface (Eloquent models should use the HasArchive trait).
     *
     * @param  Curator $curator The entity that will curate this media file
     * @return static  New immutable instance with curator set
     */
    public function toCurator(Curator $curator): static
    {
        $clone = clone $this;
        $clone->curator = $curator;

        return $clone;
    }

    /**
     * Assigns the media file to a collection.
     *
     * Collections provide logical grouping of related media files, such as
     * 'avatars', 'documents', 'thumbnails', etc. This allows for organized
     * retrieval and management of media files by category.
     *
     * @param  string $collection Collection name to assign the media to
     * @return static New immutable instance with collection set
     */
    public function toCollection(string $collection): static
    {
        $clone = clone $this;
        $clone->collection = $collection;

        return $clone;
    }

    /**
     * Specifies the storage disk for the media file.
     *
     * Sets the Laravel filesystem disk where the media file will be stored.
     * If not specified, the package will use the configured default disk from
     * the archive configuration or the application's default filesystem disk.
     *
     * @param string $disk Laravel filesystem disk name (e.g., 'public', 's3', 'local')
     *
     * @throws InvalidDiskException When the specified disk does not exist
     *
     * @return static New immutable instance with disk set
     */
    public function toDisk(string $disk): static
    {
        // @phpstan-ignore-next-line argument.type (config() returns mixed, but we know it's an array)
        if (!array_key_exists($disk, config('filesystems.disks', []))) {
            throw InvalidDiskException::diskDoesNotExist($disk);
        }

        $clone = clone $this;
        $clone->disk = $disk;

        return $clone;
    }

    /**
     * Sets a custom file name for storage.
     *
     * Overrides the default file name with a custom value. The file name
     * will be sanitized before storage to remove invalid characters and
     * prevent security issues.
     *
     * @param  string $fileName Custom file name to use for storage
     * @return static New immutable instance with file name set
     */
    public function withFileName(string $fileName): static
    {
        $clone = clone $this;
        $clone->fileName = $fileName;

        return $clone;
    }

    /**
     * Sets a human-readable name for the media file.
     *
     * Assigns a descriptive name to the media file that can be different
     * from the file name. This name is stored in the database and can be
     * used for display purposes.
     *
     * @param  string $name Human-readable name for the media file
     * @return static New immutable instance with name set
     */
    public function withName(string $name): static
    {
        $clone = clone $this;
        $clone->mediaName = $name;

        return $clone;
    }

    /**
     * Attaches custom metadata to the media file.
     *
     * Stores additional metadata with the media file as JSON in the database.
     * This is useful for storing arbitrary data like author information, tags,
     * processing status, or any other context-specific information.
     *
     * @param  array<string, mixed> $customProperties Associative array of metadata key-value pairs
     * @return static               New immutable instance with custom properties set
     */
    public function withProperties(array $customProperties): static
    {
        $clone = clone $this;
        $clone->customProperties = $customProperties;

        return $clone;
    }

    /**
     * Sets the order position for the media file within its collection.
     *
     * Allows manual ordering of media files within a collection by assigning
     * a numeric position. Useful for maintaining specific display orders like
     * gallery image sequences or document priorities.
     *
     * @param  null|int $order Order position value, or null for no specific ordering
     * @return static   New immutable instance with order position set
     */
    public function withOrder(?int $order): static
    {
        $clone = clone $this;
        $clone->order = $order;

        return $clone;
    }

    /**
     * Configures whether to preserve the original file after adding.
     *
     * By default, the original file is deleted after being copied to storage.
     * Setting this to true will keep the original file in its location after
     * the media file has been added to the archive.
     *
     * @param  bool   $preserve Whether to keep the original file (default: true)
     * @return static New immutable instance with preserve flag set
     */
    public function preservingOriginal(bool $preserve = true): static
    {
        $clone = clone $this;
        $clone->preserveOriginal = $preserve;

        return $clone;
    }

    /**
     * Stores the media file to storage and creates the database record.
     *
     * This method performs the final operation of adding the media file to
     * the archive. It validates file existence, extracts metadata, creates
     * the database record, copies the file to storage, and optionally removes
     * the original file. All configuration from previous method calls is
     * applied during this operation.
     *
     * @throws FileDoesNotExist         When the specified file path does not exist
     * @throws FileTooLarge             When the file exceeds the maximum allowed size
     * @throws InvalidArgumentException When the file name has a PHP extension (security)
     *
     * @return Media The persisted media model with all metadata
     */
    public function store(): Media
    {
        if (!is_file($this->pathToFile)) {
            throw FileDoesNotExist::create($this->pathToFile);
        }

        // Validate file size against configured maximum
        $fileSize = filesize($this->pathToFile);
        assert($fileSize !== false, 'filesize() should not return false for existing file');

        $maxSize = config('archive.max_file_size');
        assert(is_int($maxSize), 'max_file_size config must be an integer');

        if ($maxSize > 0 && $fileSize > $maxSize) {
            throw FileTooLarge::create($this->pathToFile, $fileSize, $maxSize);
        }

        // Check if collection is registered and get its configuration
        $registeredCollection = MediaCollectionRegistry::get($this->collection);

        // @phpstan-ignore return.type (DB::transaction return type is correctly typed via closure return type)
        return DB::transaction(function () use ($registeredCollection): Media {
            // If collection registered as single-file, clear existing media first
            if ($registeredCollection?->isSingleFile() && $this->curator instanceof Curator) {
                Media::query()
                    ->where('curator_id', $this->curator->getCuratorId())
                    ->where('curator_type', $this->curator->getCuratorType())
                    ->where('collection', $this->collection)
                    ->delete();
            }

            $media = new Media();
            $media->name = $this->mediaName;
            $media->file_name = $this->sanitizeFileName($this->fileName);
            $media->collection = $this->collection;

            // Use collection-specific disk if configured, otherwise use explicit or default
            $configuredDisk = config('archive.disk', config('filesystems.default'));
            assert(is_string($configuredDisk), 'disk config must be a string');
            $media->disk = $this->disk ?: $registeredCollection?->getDisk() ?: $configuredDisk;

            $mimeType = mime_content_type($this->pathToFile);
            assert($mimeType !== false, 'mime_content_type() should not return false for existing file');
            $media->mime_type = $mimeType;

            $size = filesize($this->pathToFile);
            assert($size !== false, 'filesize() should not return false for existing file');
            $media->size = $size;
            $media->custom_properties = $this->customProperties;
            $media->order_column = $this->order;

            // Associate with curator if one was specified
            if ($this->curator instanceof Curator) {
                $media->curator_id = $this->curator->getCuratorId();
                $media->curator_type = $this->curator->getCuratorType();
            }

            $media->save();

            assert($this->filesystem instanceof Filesystem, 'Filesystem must be set in constructor');
            $this->filesystem->add($this->pathToFile, $media);

            // Remove original file unless preservation is enabled
            if (!$this->preserveOriginal && is_file($this->pathToFile)) {
                unlink($this->pathToFile);
            }

            return $media;
        });
    }

    /**
     * Sanitizes a file name to prevent security issues and filesystem errors.
     *
     * Removes control characters, replaces problematic characters with hyphens,
     * and blocks PHP file extensions to prevent code execution vulnerabilities.
     * This is a critical security measure to prevent arbitrary code execution
     * through uploaded files.
     *
     * @param string $fileName The original file name to sanitize
     *
     * @throws InvalidArgumentException When the file has a PHP extension
     *
     * @return string The sanitized file name safe for storage
     */
    private function sanitizeFileName(string $fileName): string
    {
        // Remove control characters
        $sanitized = preg_replace('#\p{C}+#u', '', $fileName);
        assert($sanitized !== null, 'preg_replace should not return null for valid pattern');

        // Replace problematic characters with hyphens
        $sanitized = str_replace(['#', '/', '\\', ' '], '-', $sanitized);

        // Normalize to lowercase for security checks
        $lowerFileName = mb_strtolower($sanitized);

        // Block PHP file extensions to prevent code execution
        $phpExtensions = [
            '.php', '.php3', '.php4', '.php5', '.php7', '.php8', '.phtml', '.phar',
        ];

        foreach ($phpExtensions as $ext) {
            throw_if(Str::endsWith($lowerFileName, $ext), InvalidArgumentException::class, 'PHP files are not allowed: '.$fileName);
        }

        return $sanitized;
    }
}

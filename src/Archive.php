<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive;

use Cline\Archive\Storage\MediaAdder;
use Cline\Archive\Support\MediaCollection;
use Cline\Archive\Support\MediaCollectionRegistry;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

use function app;

/**
 * Facade for media archive operations providing static access to media functionality.
 *
 * This class serves as the primary entry point for adding media files to the archive
 * system without requiring a curator context. For model-specific media operations,
 * use the HasArchive trait on your models instead.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Archive
{
    /**
     * Defines or retrieves a media collection configuration.
     *
     * Creates a new collection configuration or retrieves an existing one from
     * the registry. Collections allow grouping media files with shared settings
     * such as disk location, allowed MIME types, and file size limits.
     *
     * ```php
     * Archive::collection('avatars')
     *     ->acceptsMimeTypes(['image/jpeg', 'image/png'])
     *     ->maxFileSize(2 * 1024 * 1024);
     * ```
     *
     * @param  string          $name The unique identifier for the collection
     * @return MediaCollection Fluent collection configuration instance
     */
    public static function collection(string $name): MediaCollection
    {
        return MediaCollectionRegistry::define($name);
    }

    /**
     * Creates a media adder instance for adding files to the archive.
     *
     * This method initializes a MediaAdder configured with the provided file.
     * The MediaAdder can then be configured with additional options like collection,
     * disk, custom properties, and curator before storing the file.
     *
     * ```php
     * Archive::add($uploadedFile)
     *     ->toCollection('avatars')
     *     ->toDisk('s3')
     *     ->withProperties(['author' => 'John Doe'])
     *     ->store();
     * ```
     *
     * @param  string|SymfonyFile|UploadedFile $file The file to add - can be an uploaded file,
     *                                               Symfony file object, or absolute file system path
     * @return MediaAdder                      Configured media adder instance ready for chaining additional options
     */
    public static function add(UploadedFile|SymfonyFile|string $file): MediaAdder
    {
        $adder = app(MediaAdder::class);

        return $adder->setFile($file);
    }
}

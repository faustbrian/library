<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Storage;

use Cline\Archive\Models\Media;
use Cline\Archive\Storage\PathGenerator\PathGenerator;
use Illuminate\Support\Facades\Storage;

use function app;
use function assert;
use function config;
use function fopen;

/**
 * Handles physical file operations for media storage.
 *
 * This class provides low-level filesystem operations for storing and deleting
 * media files on Laravel storage disks. It coordinates with path generators to
 * determine file locations and uses Laravel's Storage facade for disk operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Filesystem
{
    /**
     * Copies a file from a temporary location to its permanent storage location.
     *
     * Streams the file content from the temporary path to the configured storage
     * disk using the path generator to determine the destination path. The file
     * is opened in binary read mode to ensure proper handling of all file types.
     *
     * @param  string $pathToFile Absolute path to the temporary file location
     * @param  Media  $media      Media model instance containing storage configuration
     * @return bool   True if the file was successfully stored, false otherwise
     */
    public function add(string $pathToFile, Media $media): bool
    {
        /** @var class-string<PathGenerator> $pathGenerator */
        $pathGenerator = config('archive.path_generator');
        $destinationPath = app($pathGenerator)->getPath($media);

        $fileHandle = fopen($pathToFile, 'rb');
        assert($fileHandle !== false, 'fopen should not fail for existing file');

        return Storage::disk($media->disk)->put(
            $destinationPath,
            $fileHandle,
        );
    }

    /**
     * Deletes a media file from storage.
     *
     * Removes the physical file from the storage disk using the path generator
     * to locate the file. This operation is typically called when a media model
     * is deleted from the database.
     *
     * @param  Media $media Media model instance representing the file to delete
     * @return bool  True if the file was successfully deleted, false otherwise
     */
    public function delete(Media $media): bool
    {
        /** @var class-string<PathGenerator> $pathGenerator */
        $pathGenerator = config('archive.path_generator');
        $path = app($pathGenerator)->getPath($media);

        return Storage::disk($media->disk)->delete($path);
    }
}

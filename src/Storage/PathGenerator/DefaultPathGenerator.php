<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Storage\PathGenerator;

use Cline\Archive\Models\Media;

use function assert;
use function config;
use function is_int;
use function is_string;
use function mb_trim;

/**
 * Default implementation for generating media file storage paths.
 *
 * This path generator creates a hierarchical directory structure based on the
 * media ID, with optional prefix support for namespace organization. Files are
 * stored in directories named after their media ID, providing isolation and
 * making file management straightforward.
 *
 * Example structure: [prefix]/123/filename.jpg
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DefaultPathGenerator implements PathGenerator
{
    /**
     * Generates the complete storage path for a media file.
     *
     * Constructs the full path where the media file will be stored on the
     * storage disk. The path includes the configured prefix (if any), the
     * media ID as a directory, and the sanitized file name.
     *
     * Example: "media/123/document.pdf" or just "123/document.pdf" without prefix
     *
     * @param  Media  $media The media model instance
     * @return string The complete relative path for storing the file
     */
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/'.$media->file_name;
    }

    /**
     * Builds the base directory path for a media file.
     *
     * Constructs the directory portion of the storage path, combining the
     * configured prefix (if any) with the media ID. The prefix is trimmed
     * of leading/trailing slashes and reformatted for consistency.
     *
     * @param  Media  $media The media model instance
     * @return string The base directory path without the file name
     */
    private function getBasePath(Media $media): string
    {
        $prefix = config('archive.prefix', '');
        assert(is_string($prefix), 'archive.prefix config must be a string');

        if ($prefix !== '') {
            $prefix = mb_trim($prefix, '/').'/';
        }

        $key = $media->getKey();
        assert(is_int($key) || is_string($key), 'Media key must be int or string');

        return $prefix.$key;
    }
}

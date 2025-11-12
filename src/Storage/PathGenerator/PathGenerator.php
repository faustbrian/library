<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Storage\PathGenerator;

use Cline\Archive\Models\Media;

/**
 * Contract for implementing custom media file path generation strategies.
 *
 * This interface defines the contract for generating storage paths for media
 * files. Implementations can provide custom path structures to organize files
 * based on dates, UUIDs, categories, or any other organizational scheme.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface PathGenerator
{
    /**
     * Generates the complete storage path for a media file.
     *
     * Returns the relative path where the media file should be stored on the
     * storage disk. The path must include the file name and any necessary
     * directory structure for organizing files.
     *
     * @param  Media  $media The media model instance containing file metadata
     * @return string The complete relative path for storing the media file
     */
    public function getPath(Media $media): string;
}

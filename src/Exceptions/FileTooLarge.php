<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Exceptions;

use InvalidArgumentException;

use function round;
use function sprintf;

/**
 * Exception thrown when a file exceeds the maximum allowed size.
 *
 * This exception is raised during file upload validation when the file size
 * exceeds the configured maximum size limit in the archive configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileTooLarge extends InvalidArgumentException
{
    /**
     * Creates an exception for a file that exceeds the size limit.
     *
     * Generates a descriptive error message with file sizes in megabytes for
     * better readability. Indicates which file exceeded the limit and by how much.
     *
     * @param  string $path    The path to the file that exceeds the size limit
     * @param  int    $size    The actual size of the file in bytes
     * @param  int    $maxSize The maximum allowed size in bytes
     * @return self   The exception instance with formatted error message
     */
    public static function create(string $path, int $size, int $maxSize): self
    {
        $sizeMB = round($size / 1_024 / 1_024, 2);
        $maxMB = round($maxSize / 1_024 / 1_024, 2);

        return new self(
            sprintf(
                'File at path "%s" exceeds maximum size. File size: %sMB, Maximum: %sMB',
                $path,
                $sizeMB,
                $maxMB,
            ),
        );
    }
}

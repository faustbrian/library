<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Exceptions;

use Exception;

/**
 * Exception thrown when attempting to add a non-existent file to the media archive.
 *
 * This exception is thrown during the media addition process when the specified
 * file path does not point to an existing file on the filesystem.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FileDoesNotExist extends Exception
{
    /**
     * Creates a new exception instance for a missing file.
     *
     * @param  string $path The file path that does not exist on the filesystem
     * @return self   The exception instance with a descriptive error message
     */
    public static function create(string $path): self
    {
        return new self('File does not exist at path: '.$path);
    }
}

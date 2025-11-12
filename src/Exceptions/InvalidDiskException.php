<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Exceptions;

use InvalidArgumentException;

use function sprintf;

/**
 * Exception thrown when attempting to use an undefined filesystem disk.
 *
 * This exception is raised when code references a disk name that has not been
 * configured in Laravel's filesystems.php configuration file. All disk names
 * must be registered in the 'disks' array before use with media collections
 * or file storage operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidDiskException extends InvalidArgumentException
{
    /**
     * Creates an exception for a non-existent filesystem disk.
     *
     * Generates a descriptive error message indicating which disk name was
     * referenced but could not be found in the filesystem configuration.
     * This typically occurs when a media collection specifies a disk that
     * hasn't been defined in config/filesystems.php.
     *
     * @param  string $disk The name of the disk that does not exist in the filesystem configuration
     * @return self   The exception instance with formatted error message
     */
    public static function diskDoesNotExist(string $disk): self
    {
        return new self(sprintf("Disk '%s' does not exist in filesystem configuration.", $disk));
    }
}

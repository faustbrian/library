<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Support\UrlGenerator;

use Cline\Archive\Models\Media;
use DateTimeInterface;

/**
 * Contract for generating publicly accessible URLs for media files.
 *
 * Defines the contract for URL generation strategies that can create both
 * permanent and temporary signed URLs for media files stored across different
 * storage disks (local, S3, etc.). Implementations handle the disk-specific
 * URL generation logic and authentication requirements.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface UrlGenerator
{
    /**
     * Generates a permanent public URL for the given media file.
     *
     * Creates a publicly accessible URL that points to the media file's location
     * on its configured storage disk. The URL format depends on the storage driver
     * (e.g., local filesystem paths, S3 bucket URLs, CDN endpoints).
     *
     * @param  Media  $media The media model instance containing disk configuration
     *                       and file path information required for URL generation
     * @return string The fully qualified public URL to access the media file
     */
    public function getUrl(Media $media): string;

    /**
     * Generates a temporary signed URL with expiration for the given media file.
     *
     * Creates a time-limited, cryptographically signed URL that provides temporary
     * access to private media files. Particularly useful for S3 presigned URLs or
     * other storage systems requiring time-boxed access control. The URL automatically
     * becomes invalid after the specified expiration time.
     *
     * @param  Media                $media      The media model instance containing disk configuration
     *                                          and file path information required for URL generation
     * @param  DateTimeInterface    $expiration The date and time when the generated URL
     *                                          should expire and become inaccessible.
     *                                          Accepts any DateTime-compatible object.
     * @param  array<string, mixed> $options    Optional driver-specific configuration array
     *                                          for customizing URL generation behavior such as
     *                                          response headers, cache control, or access permissions
     * @return string               The fully qualified temporary URL with embedded signature and expiration data
     */
    public function getTemporaryUrl(Media $media, DateTimeInterface $expiration, array $options = []): string;
}

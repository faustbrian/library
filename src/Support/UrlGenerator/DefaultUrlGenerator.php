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
use Illuminate\Support\Facades\Storage;

/**
 * Default URL generator implementation using Laravel's Storage facade.
 *
 * Provides standard URL generation for media files by delegating to Laravel's
 * Storage system. This implementation supports all Laravel storage drivers
 * (local, s3, ftp, etc.) and leverages their native URL generation capabilities
 * including S3 presigned URLs, local filesystem paths, and CDN integrations.
 *
 * The generator automatically adapts to the media's configured disk, making it
 * suitable for multi-disk applications where different media types use different
 * storage backends.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DefaultUrlGenerator implements UrlGenerator
{
    /**
     * Generates a permanent public URL for the given media file.
     *
     * Retrieves the configured storage disk from the media model and delegates
     * URL generation to Laravel's Storage facade. The resulting URL format depends
     * on the disk driver configuration (e.g., S3 bucket URLs, local paths with
     * public URL prefix, or custom CDN endpoints).
     *
     * @param  Media  $media The media model instance containing disk configuration
     *                       and file path information required for URL generation
     * @return string The fully qualified public URL to access the media file
     */
    public function getUrl(Media $media): string
    {
        // @phpstan-ignore-next-line method.notFound, return.type (Cloud interface has url() method, not Filesystem contract)
        return Storage::disk($media->disk)->url($media->getPath());
    }

    /**
     * Generates a temporary signed URL with expiration for the given media file.
     *
     * Creates a time-limited URL by delegating to the storage disk's temporaryUrl
     * method. For S3 disks, this generates presigned URLs with embedded authentication.
     * For other drivers, behavior depends on driver implementation. The URL becomes
     * invalid after the specified expiration time, providing secure time-boxed access.
     *
     * @param  Media                $media      The media model instance containing disk configuration
     *                                          and file path information required for URL generation
     * @param  DateTimeInterface    $expiration The date and time when the generated URL
     *                                          should expire and become inaccessible.
     *                                          Accepts any DateTime-compatible object.
     * @param  array<string, mixed> $options    Optional driver-specific configuration array
     *                                          for customizing URL generation behavior. Common
     *                                          options include response headers (ResponseContentType,
     *                                          ResponseContentDisposition), cache control directives,
     *                                          and access permissions specific to the storage driver.
     * @return string               The fully qualified temporary URL with embedded signature and expiration data
     */
    public function getTemporaryUrl(Media $media, DateTimeInterface $expiration, array $options = []): string
    {
        // @phpstan-ignore-next-line method.notFound, return.type (Cloud interface has temporaryUrl() method, not Filesystem contract)
        return Storage::disk($media->disk)->temporaryUrl(
            $media->getPath(),
            $expiration,
            $options,
        );
    }
}

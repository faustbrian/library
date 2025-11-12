<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Models;

use Cline\Archive\Contracts\Curator;
use Cline\Archive\Storage\Filesystem;
use Cline\Archive\Storage\PathGenerator\PathGenerator;
use Cline\Archive\Support\UrlGenerator\UrlGenerator;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Override;

use function app;
use function config;

/**
 * Eloquent model representing a media file in the archive system.
 *
 * This model stores metadata about uploaded files including their location,
 * mime type, size, and custom properties. Media files are associated with
 * curator models through a polymorphic relationship, allowing any model to
 * own media files by implementing the Curator interface or using the
 * HasArchive trait.
 *
 * @property string               $collection        Collection name for logical grouping (e.g., 'avatars', 'documents')
 * @property null|Carbon          $created_at        Timestamp when the media was created
 * @property null|string          $curator_id        ID of the owning model
 * @property null|string          $curator_type      Fully qualified class name of the owning model
 * @property array<string, mixed> $custom_properties Additional metadata stored as JSON
 * @property string               $disk              Storage disk name where the file is stored
 * @property string               $file_name         Sanitized file name stored on disk
 * @property int                  $id                Primary key
 * @property string               $mime_type         MIME type of the file (e.g., 'image/jpeg', 'application/pdf')
 * @property string               $name              Human-readable name of the media file
 * @property null|int             $order_column      Optional ordering value for sorting media within a collection
 * @property int                  $size              File size in bytes
 * @property null|Carbon          $updated_at        Timestamp when the media was last updated
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Media extends Model
{
    // @phpstan-ignore-next-line missingType.generics (Factory not implemented yet)
    use HasFactory;

    /** @var string The database table name for media records */
    protected $table = 'media';

    /** @var array<int, string> Mass assignment protection (empty allows all attributes) */
    protected $guarded = [];

    /**
     * Defines the polymorphic relationship to the curator model.
     *
     * Establishes a belongs-to polymorphic relationship with any model that
     * curates this media file, allowing flexible association with any entity type.
     *
     * @return MorphTo<Model, self> The polymorphic relationship to the curator
     */
    public function curator(): MorphTo
    {
        // @phpstan-ignore-next-line return.type (MorphTo TDeclaringModel is not covariant)
        return $this->morphTo('curator', 'curator_type', 'curator_id');
    }

    /**
     * Generates the public URL for accessing this media file.
     *
     * Uses the configured URL generator to create a publicly accessible URL
     * for the media file. The URL format depends on the storage disk and
     * URL generator implementation.
     *
     * @return string The public URL to access this media file
     */
    public function getUrl(): string
    {
        /** @var class-string<UrlGenerator> $urlGenerator */
        $urlGenerator = config('archive.url_generator');

        return app($urlGenerator)->getUrl($this);
    }

    /**
     * Returns the storage path for this media file.
     *
     * Uses the configured path generator to determine the file's location
     * on the storage disk. The path format is determined by the path
     * generator implementation.
     *
     * @return string The relative path to the file on the storage disk
     */
    public function getPath(): string
    {
        /** @var class-string<PathGenerator> $pathGenerator */
        $pathGenerator = config('archive.path_generator');

        return app($pathGenerator)->getPath($this);
    }

    /**
     * Generates a temporary URL with expiration for private storage disks.
     *
     * Creates a time-limited URL for accessing media files stored on private
     * disks like S3. The URL remains valid until the expiration time and can
     * be configured with additional options specific to the storage driver.
     *
     * @param  DateTimeInterface    $expiration The date and time when the URL should expire
     * @param  array<string, mixed> $options    Optional driver-specific parameters for URL generation
     * @return string               The temporary URL that expires at the specified time
     */
    public function getTemporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        /** @var class-string<UrlGenerator> $urlGenerator */
        $urlGenerator = config('archive.url_generator');

        return app($urlGenerator)->getTemporaryUrl($this, $expiration, $options);
    }

    /**
     * Associates this media file with a curator model.
     *
     * Establishes curation by setting the polymorphic relationship fields
     * and persisting the changes. Accepts either Eloquent models or objects
     * implementing the Curator interface for flexible curation patterns.
     *
     * @param Curator|Model $curator The model or entity that will curate this media file
     */
    public function attachToCurator(Model|Curator $curator): void
    {
        if ($curator instanceof Model) {
            $this->curator()->associate($curator);
        } elseif ($curator instanceof Curator) {
            $this->curator_id = $curator->getCuratorId();
            $this->curator_type = $curator->getCuratorType();
        }

        $this->save();
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param Builder $query
     */
    #[Override()]
    public function newEloquentBuilder($query): MediaQueryBuilder
    {
        return new MediaQueryBuilder($query);
    }

    /**
     * Bootstrap the model and register event listeners.
     */
    #[Override()]
    protected static function booted(): void
    {
        self::deleting(function (Media $media): void {
            // Automatically delete the physical file when the model is deleted
            app(Filesystem::class)->delete($media);
        });
    }

    /**
     * Defines attribute casting for type safety.
     *
     * Configures automatic casting of the custom_properties attribute to and
     * from JSON, enabling storage of arbitrary metadata as a PHP array while
     * maintaining JSON format in the database.
     *
     * @return array<string, string> Attribute casting configuration
     */
    protected function casts(): array
    {
        return [
            'custom_properties' => 'array',
        ];
    }
}

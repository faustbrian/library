<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Models\Concerns;

use Cline\Archive\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait for adding media archive relationship to Eloquent models.
 *
 * This trait provides the polymorphic relationship to media files and
 * implements the Curator interface. Models using this trait MUST
 * formally implement the Curator interface:
 *
 * ```php
 * use Cline\Archive\Contracts\Curator;
 * use Cline\Archive\Models\Concerns\HasArchive;
 *
 * class Product extends Model implements Curator
 * {
 *     use HasArchive;
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @mixin Model
 *
 * @phpstan-ignore trait.unused (This trait is used by external code, not analyzed by PHPStan)
 */
trait HasArchive
{
    /**
     * Defines the polymorphic relationship to media files.
     *
     * @return MorphMany<Media> The polymorphic relationship to media files
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'curator');
    }

    /**
     * Returns the model's primary key as the curator identifier.
     *
     * Implements the Curator interface requirement by providing the model's
     * primary key value cast to string for use in polymorphic relationships.
     *
     * @return string The model's primary key cast to string
     */
    public function getCuratorId(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Returns the model's morph class as the curator type.
     *
     * Implements the Curator interface requirement by providing the model's
     * morph class name, which is used to identify the model type in polymorphic
     * relationships. Respects any custom morph map aliases defined in the application.
     *
     * @return string The model's fully qualified class name or morph map alias
     */
    public function getCuratorType(): string
    {
        return $this->getMorphClass();
    }
}

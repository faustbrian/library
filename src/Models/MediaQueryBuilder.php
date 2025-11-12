<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Models;

use Cline\Archive\Contracts\Curator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Custom query builder for Media model providing convenient query scopes.
 *
 * Example usage:
 * ```php
 * // Filter by collection
 * Media::query()->inCollection('avatars')->get();
 *
 * // Filter by curator
 * Media::query()->curatedBy($user)->get();
 *
 * // Get anonymous media only
 * Media::query()->anonymous()->get();
 *
 * // Filter by storage disk
 * Media::query()->onDisk('s3')->get();
 *
 * // Filter by MIME type or category
 * Media::query()->ofType('image/jpeg')->get();
 * Media::query()->ofType('image')->get(); // All images
 *
 * // Get ordered media
 * Media::query()->ordered()->get();
 *
 * // Eager load curator relationship
 * Media::query()->withCurator()->get();
 *
 * // Chain multiple scopes
 * Media::query()
 *     ->curatedBy($user)
 *     ->inCollection('documents')
 *     ->ofType('application/pdf')
 *     ->withCurator()
 *     ->get();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @extends Builder<Media>
 */
final class MediaQueryBuilder extends Builder
{
    /**
     * Scope query to specific collection.
     *
     * Example:
     * ```php
     * Media::query()->inCollection('avatars')->get();
     * ```
     *
     * @param string $collection Collection name to filter by
     */
    public function inCollection(string $collection): static
    {
        return $this->where('collection', $collection);
    }

    /**
     * Scope query to specific curator.
     *
     * Example:
     * ```php
     * $user = User::find(1);
     * Media::query()->curatedBy($user)->get();
     * ```
     *
     * @param Curator $curator Curator to filter by
     */
    public function curatedBy(Curator $curator): static
    {
        return $this->where('curator_type', $curator->getCuratorType())
            ->where('curator_id', $curator->getCuratorId());
    }

    /**
     * Scope query to anonymous media (no curator).
     *
     * Example:
     * ```php
     * Media::query()->anonymous()->get();
     * ```
     */
    public function anonymous(): static
    {
        // @phpstan-ignore-next-line return.type (Eloquent query builder return type variance)
        return $this->whereNull('curator_id')
            ->whereNull('curator_type');
    }

    /**
     * Scope query to specific disk.
     *
     * Example:
     * ```php
     * Media::query()->onDisk('s3')->get();
     * ```
     *
     * @param string $disk Disk name to filter by
     */
    public function onDisk(string $disk): static
    {
        return $this->where('disk', $disk);
    }

    /**
     * Scope query to specific MIME type or type category.
     *
     * Example:
     * ```php
     * // Exact MIME type
     * Media::query()->ofType('image/jpeg')->get();
     *
     * // All images
     * Media::query()->ofType('image')->get();
     *
     * // All PDFs
     * Media::query()->ofType('application/pdf')->get();
     * ```
     *
     * @param string $mimeType MIME type or prefix (e.g., 'image/jpeg' or 'image')
     */
    public function ofType(string $mimeType): static
    {
        return $this->where('mime_type', 'like', $mimeType.'%');
    }

    /**
     * Scope query to ordered media only.
     *
     * Example:
     * ```php
     * // Get all media with order_column set, sorted by order
     * Media::query()->ordered()->get();
     *
     * // Get ordered images in a collection
     * Media::query()->inCollection('gallery')->ordered()->get();
     * ```
     */
    public function ordered(): static
    {
        // @phpstan-ignore-next-line return.type (Eloquent query builder return type variance)
        return $this->whereNotNull('order_column')
            ->orderBy('order_column');
    }

    /**
     * Eager load the curator relationship.
     *
     * Example:
     * ```php
     * // Prevent N+1 queries when accessing curator
     * $media = Media::query()->withCurator()->get();
     * foreach ($media as $item) {
     *     echo $item->curator->name; // No additional queries
     * }
     *
     * // Chain with other scopes
     * Media::query()
     *     ->withCurator()
     *     ->inCollection('documents')
     *     ->get();
     * ```
     */
    public function withCurator(): static
    {
        return $this->with('curator');
    }
}

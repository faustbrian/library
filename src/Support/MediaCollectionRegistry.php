<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Support;

use function array_key_exists;

/**
 * Global registry for application-wide media collection definitions.
 *
 * MediaCollectionRegistry provides centralized management of media collections that
 * are shared across the entire application, rather than being tied to specific model
 * classes. This is useful for defining reusable collection configurations, global
 * media types, or shared media libraries that multiple models can reference.
 *
 * Unlike the trait-based InteractsWithMediaCollections which scopes collections to
 * individual model classes, this registry maintains a single global namespace for
 * collection names. Ideal for bootstrapping standard collections during application
 * initialization or service provider registration.
 *
 * ```php
 * // In a service provider
 * MediaCollectionRegistry::define('avatars')
 *     ->singleFile()
 *     ->toDisk('public');
 *
 * MediaCollectionRegistry::define('documents')
 *     ->toDisk('s3')
 *     ->curatedByAnonymous();
 *
 * // Later in application code
 * $collection = MediaCollectionRegistry::get('avatars');
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MediaCollectionRegistry
{
    /**
     * Global collection registry mapping collection names to MediaCollection instances.
     *
     * Stores all application-wide media collection definitions in a flat namespace.
     * Collection names must be unique across the entire application.
     *
     * @var array<string, MediaCollection>
     */
    private static array $collections = [];

    /**
     * Defines a new global media collection with the specified name.
     *
     * Creates a MediaCollection instance and registers it in the global namespace.
     * Returns the collection for fluent configuration. Overwrites any existing
     * collection with the same name without warning.
     *
     * @param  string          $name Unique identifier for the collection in the global registry
     * @return MediaCollection The newly created collection for fluent configuration
     */
    public static function define(string $name): MediaCollection
    {
        $collection = new MediaCollection($name);
        self::$collections[$name] = $collection;

        return $collection;
    }

    /**
     * Retrieves a registered collection by name.
     *
     * Searches the global registry for a collection with the specified name.
     * Returns null if no collection has been registered under that name.
     *
     * @param  string               $name The collection identifier to look up
     * @return null|MediaCollection The collection instance or null if not found
     */
    public static function get(string $name): ?MediaCollection
    {
        return self::$collections[$name] ?? null;
    }

    /**
     * Checks whether a collection exists in the global registry.
     *
     * Determines if a collection with the specified name has been registered,
     * useful for conditional logic or validation before attempting retrieval.
     *
     * @param  string $name The collection identifier to check
     * @return bool   True if the collection exists in the registry
     */
    public static function has(string $name): bool
    {
        return array_key_exists($name, self::$collections);
    }

    /**
     * Retrieves all registered collections from the global registry.
     *
     * Returns the complete collection registry as an associative array mapping
     * collection names to MediaCollection instances. Useful for introspection,
     * debugging, or batch operations across all registered collections.
     *
     * @return array<string, MediaCollection> All registered collections keyed by name
     */
    public static function all(): array
    {
        return self::$collections;
    }

    /**
     * Removes all collections from the global registry.
     *
     * Resets the registry to an empty state, removing all collection definitions.
     * Primarily used in testing scenarios to ensure clean state between tests
     * or when reinitializing the application configuration.
     */
    public static function clear(): void
    {
        self::$collections = [];
    }
}

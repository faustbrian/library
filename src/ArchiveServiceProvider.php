<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive;

use Illuminate\Support\ServiceProvider;
use Override;

use function class_exists;
use function config_path;
use function database_path;
use function now;

/**
 * Service provider for the media archive package.
 *
 * Registers package configuration and publishes configuration and migration files
 * for Laravel applications. Handles package bootstrapping and asset publishing
 * for console operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ArchiveServiceProvider extends ServiceProvider
{
    /**
     * Registers package configuration with the Laravel application.
     *
     * Merges the package's default configuration with any published configuration
     * in the application, allowing developers to override package defaults.
     */
    #[Override()]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/archive.php', 'archive');
    }

    /**
     * Bootstraps package services and publishes assets.
     *
     * When running in console mode, this method publishes the configuration file
     * and database migration. The migration is published with a timestamp to ensure
     * proper ordering in the migrations directory. Migration publishing is skipped
     * if the CreateMediaTable class already exists, preventing duplicate migrations.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/archive.php' => config_path('archive.php'),
            ], 'archive-config');

            // Skip migration publishing if already published to prevent duplicates
            if (!class_exists('CreateMediaTable')) {
                $this->publishes([
                    __DIR__.'/../database/migrations/create_media_table.php.stub' => database_path('migrations/'.now()->format('Y_m_d_His').'_create_media_table.php'),
                ], 'archive-migrations');
            }
        }
    }
}

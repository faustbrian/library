<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Archive\ArchiveServiceProvider;
use Cline\Archive\Storage\PathGenerator\DefaultPathGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function array_key_exists;
use function config;
use function env;
use function file_exists;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function storage_path;
use function unlink;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class TestCase extends BaseTestCase
{
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
        $this->setUpTempTestFiles();
    }

    #[Override()]
    protected function tearDown(): void
    {
        $this->deleteTempDirectory();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ArchiveServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ]);

        config()->set('filesystems.disks.s3', [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
        ]);

        config()->set('archive.disk', 'public');
        config()->set('archive.prefix', 'media');
        config()->set('archive.path_generator', DefaultPathGenerator::class);
    }

    protected function setUpDatabase(): void
    {
        if (!Schema::hasTable('media')) {
            Schema::create('media', function (Blueprint $table): void {
                $table->id();
                $table->nullableUlidMorphs('curator');
                $table->string('collection')->default('default');
                $table->string('name');
                $table->string('file_name');
                $table->string('mime_type')->nullable();
                $table->string('disk');
                $table->unsignedBigInteger('size');
                $table->json('custom_properties')->nullable();
                $table->unsignedInteger('order_column')->nullable()->index();
                $table->timestamps();

                $table->index(['collection', 'curator_type', 'curator_id'], 'media_collection_owner_index');
            });
        }

        if (!Schema::hasTable('test_models')) {
            Schema::create('test_models', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }
    }

    protected function setUpTempTestFiles(): void
    {
        $this->initializeTempDirectory();
    }

    protected function getTempDirectory(string $suffix = ''): string
    {
        $base = __DIR__.'/temp';

        if (array_key_exists('TEST_TOKEN', $_SERVER)) {
            $base .= '/'.$_SERVER['TEST_TOKEN'];
        }

        return $base.($suffix === '' ? '' : '/'.$suffix);
    }

    protected function initializeTempDirectory(): void
    {
        $this->deleteTempDirectory();

        if (!file_exists($this->getTempDirectory())) {
            mkdir($this->getTempDirectory(), 0o777, true);
        }
    }

    protected function deleteTempDirectory(): void
    {
        if (file_exists($this->getTempDirectory())) {
            $this->deleteDirectory($this->getTempDirectory());
        }
    }

    protected function deleteDirectory(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $path = $item->getRealPath();

            if (!file_exists($path)) {
                continue;
            }

            if ($item->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        if (file_exists($directory)) {
            rmdir($directory);
        }
    }

    protected function getTestJpg(): string
    {
        return __DIR__.'/Support/test.jpg';
    }

    protected function getTestPng(): string
    {
        return __DIR__.'/Support/test.png';
    }

    protected function getTestPdf(): string
    {
        return __DIR__.'/Support/test.pdf';
    }

    protected function getTempFilePath(string $filename): string
    {
        return $this->getTempDirectory().'/'.$filename;
    }

    protected function createTempFile(string $filename, string $content = 'test content'): string
    {
        $directory = $this->getTempDirectory();

        if (!file_exists($directory)) {
            mkdir($directory, 0o777, true);
        }

        $path = $this->getTempFilePath($filename);
        file_put_contents($path, $content);

        return $path;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Archive;
use Cline\Archive\Models\Media;
use Cline\Archive\Models\MediaQueryBuilder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestCurator;
use Tests\Support\TestModel;

describe('Media Model - Happy Path', function (): void {
    it('has curator relationship for Eloquent models', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->toCurator($model)->store();
        $curator = $media->curator;

        // Assert
        expect($curator)->toBeInstanceOf(TestModel::class)
            ->and($curator->id)->toBe($model->id)
            ->and($curator->name)->toBe('Test');
    });

    it('can get URL for media', function (): void {
        // Arrange
        Storage::fake('public');
        $file = $this->createTempFile('document.pdf', 'content');
        $media = Archive::add($file)->store();

        // Act
        $url = $media->getUrl();

        // Assert
        expect($url)->toBeString()
            ->and($url)->toContain($media->getKey());
    });

    it('can get path for media', function (): void {
        // Arrange
        Storage::fake('public');
        $file = $this->createTempFile('image.jpg', 'data');
        $media = Archive::add($file)->store();

        // Act
        $path = $media->getPath();

        // Assert
        expect($path)->toBeString()
            ->and($path)->toContain((string) $media->getKey())
            ->and($path)->toContain('image.jpg');
    });

    it('can get temporary URL for media', function (): void {
        // Arrange
        Storage::fake('s3');
        $file = $this->createTempFile('private.pdf', 'secret');
        $media = Archive::add($file)->toDisk('s3')->store();
        $expiration = now()->addHour();

        // Act
        $url = $media->getTemporaryUrl($expiration);

        // Assert
        expect($url)->toBeString()->toContain($media->getPath());
    });

    it('can attach to Eloquent model curator', function (): void {
        // Arrange
        $file = $this->createTempFile('orphan.txt', 'content');
        $media = Archive::add($file)->store();
        $model = TestModel::query()->create(['name' => 'New Curator']);

        // Act
        $media->attachToCurator($model);
        $media->refresh();

        // Assert
        expect($media->curator_id)->toBe((string) $model->id)
            ->and($media->curator_type)->toBe(TestModel::class);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'curator_id' => (string) $model->id,
            'curator_type' => TestModel::class,
        ]);
    });

    it('can attach to Curator owner', function (): void {
        // Arrange
        $file = $this->createTempFile('orphan.txt', 'content');
        $media = Archive::add($file)->store();
        $curator = new TestCurator('new-uuid', 'new-type');

        // Act
        $media->attachToCurator($curator);

        // Assert
        expect($media->curator_id)->toBe('new-uuid')
            ->and($media->curator_type)->toBe('new-type');

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'curator_id' => 'new-uuid',
            'curator_type' => 'new-type',
        ]);
    });

    it('casts custom_properties to array', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');
        $properties = ['key' => 'value', 'nested' => ['a' => 1]];

        // Act
        $media = Archive::add($file)->withProperties($properties)->store();
        $freshMedia = Media::query()->find($media->id);

        // Assert
        expect($freshMedia->custom_properties)->toBeArray()
            ->and($freshMedia->custom_properties)->toBe($properties);
    });
});

describe('Media Model - Edge Cases', function (): void {
    it('handles null curator gracefully', function (): void {
        // Arrange
        $file = $this->createTempFile('orphan.txt', 'content');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media->curator)->toBeNull();
    });

    it('handles null custom properties', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        // JSON column with null value gets cast to empty array by Laravel
        expect($media->custom_properties)->toBeArray()->toBeEmpty();
    });

    it('handles empty custom properties', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->withProperties([])->store();
        $freshMedia = Media::query()->find($media->id);

        // Assert
        expect($freshMedia->custom_properties)->toBe([]);
    });

    it('preserves order when updating curator', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->withOrder(5)->store();
        $model = TestModel::query()->create(['name' => 'Curator']);

        // Act
        $media->attachToCurator($model);

        // Assert
        expect($media->order_column)->toBe(5);
    });

    it('can update curator multiple times', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $model1 = TestModel::query()->create(['name' => 'First']);
        $model2 = TestModel::query()->create(['name' => 'Second']);

        // Act
        $media->attachToCurator($model1);
        $media->refresh();

        expect($media->curator_id)->toBe((string) $model1->id);

        $media->attachToCurator($model2);
        $media->refresh();

        // Assert
        expect($media->curator_id)->toBe((string) $model2->id);
    });

    it('handles path with custom prefix', function (): void {
        // Arrange
        config()->set('archive.prefix', 'custom/prefix');
        Storage::fake('public');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();
        $path = $media->getPath();

        // Assert
        expect($path)->toContain('custom/prefix');
    });

    it('handles path with empty prefix', function (): void {
        // Arrange
        config()->set('archive.prefix', '');
        Storage::fake('public');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();
        $path = $media->getPath();

        // Assert
        expect($path)->toStartWith((string) $media->getKey());
    });
});

describe('Media Model - Relationships', function (): void {
    it('returns null for curator when unattached', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media->curator)->toBeNull();
    });

    it('eager loads curator relationship', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->toCurator($model)->store();

        // Act
        $loadedMedia = Media::with('curator')->find($media->id);

        // Assert
        expect($loadedMedia->relationLoaded('curator'))->toBeTrue()
            ->and($loadedMedia->curator)->toBeInstanceOf(TestModel::class);
    });

    it('can query media by curator', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('test1.txt', 'content');
        $file2 = $this->createTempFile('test2.txt', 'content');

        Archive::add($file1)->toCurator($model)->store();
        Archive::add($file2)->toCurator($model)->store();

        // Act
        $count = Media::query()->where('curator_type', TestModel::class)
            ->where('curator_id', (string) $model->id)
            ->count();

        // Assert
        expect($count)->toBe(2);
    });
});

describe('Media Model - Query Builder', function (): void {
    it('returns MediaQueryBuilder instance from query method', function (): void {
        // Arrange & Act
        $builder = Media::query();

        // Assert
        expect($builder)->toBeInstanceOf(MediaQueryBuilder::class);
    });
});

describe('Media Model - Lifecycle Events', function (): void {
    it('deletes physical file when model is deleted', function (): void {
        // Arrange
        Storage::fake('public');
        $file = $this->createTempFile('test.txt', 'test content');
        $media = Archive::add($file)->store();

        // Verify file exists
        $path = $media->getPath();
        expect(Storage::disk('public')->exists($path))->toBeTrue();

        // Act
        $media->delete();

        // Assert
        expect(Storage::disk('public')->exists($path))->toBeFalse();
    });
});

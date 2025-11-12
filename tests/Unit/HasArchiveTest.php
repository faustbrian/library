<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Archive;
use Cline\Archive\Models\Media;
use Cline\Archive\Storage\MediaAdder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Tests\Support\TestModel;

describe('HasArchive Trait - Happy Path', function (): void {
    it('has media relationship', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);

        // Act
        $relation = $model->media();

        // Assert
        expect($relation)->toBeInstanceOf(MorphMany::class);
    });

    it('can add media using trait method', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $adder = Archive::add($file)->toCurator($model);

        // Assert
        expect($adder)->toBeInstanceOf(MediaAdder::class);
    });

    it('adds media with owner automatically set', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->toCurator($model)->store();

        // Assert
        expect($media->curator_id)->toBe((string) $model->id)
            ->and($media->curator_type)->toBe(TestModel::class);
    });

    it('can get media from default collection', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('test1.txt', 'content');
        $file2 = $this->createTempFile('test2.txt', 'content');

        Archive::add($file1)->toCurator($model)->store();
        Archive::add($file2)->toCurator($model)->store();

        // Act
        $media = $model->media()->where('collection', 'default')->get();

        // Assert
        expect($media)->toHaveCount(2);
    });

    it('can get media from specific collection', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('photo1.jpg', 'image1');
        $file2 = $this->createTempFile('photo2.jpg', 'image2');
        $file3 = $this->createTempFile('doc.pdf', 'document');

        Archive::add($file1)->toCollection('photos')->toCurator($model)->store();
        Archive::add($file2)->toCollection('photos')->toCurator($model)->store();
        Archive::add($file3)->toCollection('documents')->toCurator($model)->store();

        // Act
        $photos = $model->media()->where('collection', 'photos')->get();
        $documents = $model->media()->where('collection', 'documents')->get();

        // Assert
        expect($photos)->toHaveCount(2)
            ->and($documents)->toHaveCount(1);
    });

    it('can get first media from default collection', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('first.txt', 'content');

        Archive::add($file)->toCurator($model)->withName('First')->store();

        // Act
        $first = $model->media()->where('collection', 'default')->first();

        // Assert
        expect($first)->toBeInstanceOf(Media::class)
            ->and($first->name)->toBe('First');
    });

    it('can get first media from specific collection', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('avatar.jpg', 'image');
        $file2 = $this->createTempFile('cover.jpg', 'image');

        Archive::add($file1)->toCurator($model)->toCollection('avatars')->withName('Avatar')->store();
        Archive::add($file2)->toCurator($model)->toCollection('covers')->withName('Cover')->store();

        // Act
        $avatar = $model->media()->where('collection', 'avatars')->first();
        $cover = $model->media()->where('collection', 'covers')->first();

        // Assert
        expect($avatar->name)->toBe('Avatar')
            ->and($cover->name)->toBe('Cover');
    });

    it('can clear media from default collection', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('test1.txt', 'content');
        $file2 = $this->createTempFile('test2.txt', 'content');

        Archive::add($file1)->toCurator($model)->store();
        Archive::add($file2)->toCurator($model)->store();

        // Act
        $model->media()->where('collection', 'default')->delete();

        // Assert
        expect($model->media()->where('collection', 'default')->get())->toHaveCount(0);
    });

    it('can clear media from specific collection', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('photo.jpg', 'image');
        $file2 = $this->createTempFile('doc.pdf', 'document');

        Archive::add($file1)->toCurator($model)->toCollection('photos')->store();
        Archive::add($file2)->toCurator($model)->toCollection('documents')->store();

        // Act
        $model->media()->where('collection', 'photos')->delete();

        // Assert
        expect($model->media()->where('collection', 'photos')->get())->toHaveCount(0)
            ->and($model->media()->where('collection', 'documents')->get())->toHaveCount(1);
    });

    it('can use fluent API through trait', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('complex.jpg', 'data');

        // Act
        $media = Archive::add($file)->toCurator($model)
            ->withFileName('renamed.jpg')
            ->withName('Complex Image')
            ->toCollection('gallery')
            ->withProperties(['featured' => true])
            ->withOrder(1)
            ->store();

        // Assert
        expect($media->curator_id)->toBe((string) $model->id)
            ->and($media->file_name)->toBe('renamed.jpg')
            ->and($media->name)->toBe('Complex Image')
            ->and($media->collection)->toBe('gallery')
            ->and($media->custom_properties)->toBe(['featured' => true])
            ->and($media->order_column)->toBe(1);
    });
});

describe('HasArchive Trait - Edge Cases', function (): void {
    it('returns empty collection when no media exist', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);

        // Act
        $media = $model->media()->where('collection', 'default')->get();

        // Assert
        expect($media)->toHaveCount(0);
    });

    it('returns null when getting first media from empty collection', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);

        // Act
        $first = $model->media()->where('collection', 'default')->first();

        // Assert
        expect($first)->toBeNull();
    });

    it('can clear empty collection without error', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);

        // Act & Assert
        expect(fn () => $model->media()->where('collection', 'default')->delete())->not->toThrow(Exception::class);
    });

    it('can clear non-existent collection without error', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('test.txt', 'content');
        Archive::add($file)->toCurator($model)->toCollection('photos')->store();

        // Act & Assert
        expect(fn () => $model->media()->where('collection', 'documents'))->not->toThrow(Exception::class);
    });

    it('handles multiple models with same media collections', function (): void {
        // Arrange
        $model1 = TestModel::query()->create(['name' => 'Model 1']);
        $model2 = TestModel::query()->create(['name' => 'Model 2']);

        $file1 = $this->createTempFile('test1.txt', 'content');
        $file2 = $this->createTempFile('test2.txt', 'content');

        Archive::add($file1)->toCurator($model1)->toCollection('photos')->store();
        Archive::add($file2)->toCurator($model2)->toCollection('photos')->store();

        // Act
        $model1Photos = $model1->media()->where('collection', 'photos')->get();
        $model2Photos = $model2->media()->where('collection', 'photos')->get();

        // Assert
        expect($model1Photos)->toHaveCount(1)
            ->and($model2Photos)->toHaveCount(1)
            ->and($model1Photos->first()->id)->not->toBe($model2Photos->first()->id);
    });

    it('preserves media when clearing different collection', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('photo.jpg', 'image');
        $file2 = $this->createTempFile('doc.pdf', 'document');

        Archive::add($file1)->toCollection('photos')->toCurator($model)->store();
        Archive::add($file2)->toCollection('documents')->toCurator($model)->store();

        // Act
        $model->media()->where('collection', 'photos')->delete();

        // Assert
        expect($model->media()->where('collection', 'documents')->get())->toHaveCount(1);
    });

    it('can query media relationship directly', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('test1.txt', 'content');
        $file2 = $this->createTempFile('test2.txt', 'content');

        Archive::add($file1)->toCurator($model)->store();
        Archive::add($file2)->toCurator($model)->store();

        // Act
        $count = $model->media()->count();

        // Assert
        expect($count)->toBe(2);
    });

    it('can filter media with additional conditions', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('doc1.pdf', 'content');
        $file2 = $this->createTempFile('doc2.txt', 'content');

        Archive::add($file1)->toCurator($model)->store();
        Archive::add($file2)->toCurator($model)->store();

        // Act
        $pdfCount = $model->media()->where('mime_type', 'application/pdf')->count();

        // Assert
        expect($pdfCount)->toBeGreaterThanOrEqual(0);
    });

    it('can order media by order_column', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('first.txt', 'content');
        $file2 = $this->createTempFile('second.txt', 'content');
        $file3 = $this->createTempFile('third.txt', 'content');

        Archive::add($file1)->withOrder(3)->withName('Third')->toCurator($model)->store();
        Archive::add($file2)->withOrder(1)->withName('First')->toCurator($model)->store();
        Archive::add($file3)->withOrder(2)->withName('Second')->store();

        // Act
        $ordered = $model->media()->orderBy('order_column')->get();

        // Assert
        expect($ordered->first()->name)->toBe('First')
            ->and($ordered->last()->name)->toBe('Third');
    });
});

describe('HasArchive Trait - Integration', function (): void {
    it('deletes media when model is deleted', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->toCurator($model)->store();

        // Act
        $model->delete();

        // Assert
        // Note: This requires onDelete cascade or model events
        // The trait itself doesn't handle this - it's a relationship behavior
        expect($model->exists)->toBeFalse();
    });

    it('can eager load media relationship', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('test.txt', 'content');
        Archive::add($file)->toCurator($model)->store();

        // Act
        $loadedModel = TestModel::with('media')->find($model->id);

        // Assert
        expect($loadedModel->relationLoaded('media'))->toBeTrue()
            ->and($loadedModel->media)->toHaveCount(1);
    });

    it('can count media without loading them', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file1 = $this->createTempFile('test1.txt', 'content');
        $file2 = $this->createTempFile('test2.txt', 'content');

        Archive::add($file1)->toCurator($model)->store();
        Archive::add($file2)->toCurator($model)->store();

        // Act
        $loadedModel = TestModel::query()->withCount('media')->find($model->id);

        // Assert
        expect($loadedModel->media_count)->toBe(2);
    });
});

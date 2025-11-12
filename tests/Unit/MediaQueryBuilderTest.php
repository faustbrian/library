<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Archive;
use Cline\Archive\Models\Media;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestModel;

describe('MediaQueryBuilder - Eager Loading', function (): void {
    it('eager loads curator relationship with withCurator scope', function (): void {
        // Arrange
        $curator = TestModel::query()->create(['name' => 'Test Curator']);
        $file = $this->createTempFile('test.txt', 'content');
        Archive::add($file)->toCurator($curator)->store();

        // Act
        $media = Media::query()->withCurator()->first();

        // Assert
        expect($media)->toBeInstanceOf(Media::class)
            ->and($media->relationLoaded('curator'))->toBeTrue()
            ->and($media->curator)->toBeInstanceOf(TestModel::class)
            ->and($media->curator->name)->toBe('Test Curator');
    });

    it('withCurator does not throw when curator is null', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');
        Archive::add($file)->store();

        // Act
        $media = Media::query()->withCurator()->first();

        // Assert
        expect($media)->toBeInstanceOf(Media::class)
            ->and($media->relationLoaded('curator'))->toBeTrue()
            ->and($media->curator)->toBeNull();
    });

    it('withCurator can be chained with other scopes', function (): void {
        // Arrange
        $curator = TestModel::query()->create(['name' => 'Test Curator']);
        $file1 = $this->createTempFile('image.jpg', 'content');
        $file2 = $this->createTempFile('document.pdf', 'content');

        Archive::add($file1)->toCurator($curator)->toCollection('images')->store();
        Archive::add($file2)->toCurator($curator)->toCollection('documents')->store();

        // Act
        $media = Media::query()
            ->withCurator()
            ->inCollection('images')
            ->first();

        // Assert
        expect($media)->toBeInstanceOf(Media::class)
            ->and($media->relationLoaded('curator'))->toBeTrue()
            ->and($media->collection)->toBe('images')
            ->and($media->curator)->toBeInstanceOf(TestModel::class);
    });

    it('prevents N+1 query problem when accessing curator', function (): void {
        // Arrange
        $curator = TestModel::query()->create(['name' => 'Test Curator']);
        $file1 = $this->createTempFile('file1.txt', 'content1');
        $file2 = $this->createTempFile('file2.txt', 'content2');
        $file3 = $this->createTempFile('file3.txt', 'content3');

        Archive::add($file1)->toCurator($curator)->store();
        Archive::add($file2)->toCurator($curator)->store();
        Archive::add($file3)->toCurator($curator)->store();

        // Act - Enable query logging
        DB::enableQueryLog();
        DB::flushQueryLog();

        $mediaItems = Media::query()->withCurator()->get();

        // Access curator on each item - should not trigger additional queries
        foreach ($mediaItems as $media) {
            $curatorName = $media->curator?->name;
        }

        $queries = DB::getQueryLog();

        // Assert - Should be 2 queries: 1 for media, 1 for curators
        // Without eager loading it would be 1 + N queries (1 for media + 3 for each curator)
        expect($queries)->toHaveCount(2);
    });
});

describe('MediaQueryBuilder - curatedBy Scope', function (): void {
    it('filters media by specific curator', function (): void {
        // Arrange
        $curator1 = TestModel::query()->create(['name' => 'Curator 1']);
        $curator2 = TestModel::query()->create(['name' => 'Curator 2']);

        $file1 = $this->createTempFile('file1.txt', 'content1');
        $file2 = $this->createTempFile('file2.txt', 'content2');
        $file3 = $this->createTempFile('file3.txt', 'content3');

        Archive::add($file1)->toCurator($curator1)->store();
        Archive::add($file2)->toCurator($curator1)->store();
        Archive::add($file3)->toCurator($curator2)->store();

        // Act
        $curator1Media = Media::query()->curatedBy($curator1)->get();
        $curator2Media = Media::query()->curatedBy($curator2)->get();

        // Assert
        expect($curator1Media)->toHaveCount(2)
            ->and($curator2Media)->toHaveCount(1)
            ->and($curator1Media->first()->curator_type)->toBe($curator1::class)
            ->and($curator1Media->first()->curator_id)->toBe((string) $curator1->id);
    });

    it('returns empty collection when curator has no media', function (): void {
        // Arrange
        $curator = TestModel::query()->create(['name' => 'Empty Curator']);
        $otherCurator = TestModel::query()->create(['name' => 'Other Curator']);

        $file = $this->createTempFile('file.txt', 'content');
        Archive::add($file)->toCurator($otherCurator)->store();

        // Act
        $media = Media::query()->curatedBy($curator)->get();

        // Assert
        expect($media)->toBeEmpty();
    });

    it('can be chained with other scopes', function (): void {
        // Arrange
        $curator1 = TestModel::query()->create(['name' => 'Curator 1']);
        $curator2 = TestModel::query()->create(['name' => 'Curator 2']);

        $file1 = $this->createTempFile('image.jpg', 'content');
        $file2 = $this->createTempFile('doc.pdf', 'content');
        $file3 = $this->createTempFile('photo.jpg', 'content');

        Archive::add($file1)->toCurator($curator1)->toCollection('images')->store();
        Archive::add($file2)->toCurator($curator1)->toCollection('documents')->store();
        Archive::add($file3)->toCurator($curator2)->toCollection('images')->store();

        // Act
        $media = Media::query()
            ->curatedBy($curator1)
            ->inCollection('images')
            ->get();

        // Assert
        expect($media)->toHaveCount(1)
            ->and($media->first()->collection)->toBe('images')
            ->and($media->first()->curator_id)->toBe((string) $curator1->id);
    });
});

describe('MediaQueryBuilder - anonymous Scope', function (): void {
    it('filters media with no curator', function (): void {
        // Arrange
        $curator = TestModel::query()->create(['name' => 'Test Curator']);

        $file1 = $this->createTempFile('anonymous1.txt', 'content1');
        $file2 = $this->createTempFile('curated.txt', 'content2');
        $file3 = $this->createTempFile('anonymous2.txt', 'content3');

        Archive::add($file1)->store(); // Anonymous
        Archive::add($file2)->toCurator($curator)->store(); // Curated
        Archive::add($file3)->store(); // Anonymous

        // Act
        $anonymousMedia = Media::query()->anonymous()->get();

        // Assert
        expect($anonymousMedia)->toHaveCount(2)
            ->and($anonymousMedia->first()->curator_id)->toBeNull()
            ->and($anonymousMedia->first()->curator_type)->toBeNull()
            ->and($anonymousMedia->last()->curator_id)->toBeNull()
            ->and($anonymousMedia->last()->curator_type)->toBeNull();
    });

    it('returns empty collection when all media has curators', function (): void {
        // Arrange
        $curator = TestModel::query()->create(['name' => 'Test Curator']);

        $file1 = $this->createTempFile('file1.txt', 'content1');
        $file2 = $this->createTempFile('file2.txt', 'content2');

        Archive::add($file1)->toCurator($curator)->store();
        Archive::add($file2)->toCurator($curator)->store();

        // Act
        $anonymousMedia = Media::query()->anonymous()->get();

        // Assert
        expect($anonymousMedia)->toBeEmpty();
    });

    it('can be chained with collection filter', function (): void {
        // Arrange
        $curator = TestModel::query()->create(['name' => 'Test Curator']);

        $file1 = $this->createTempFile('image1.jpg', 'content1');
        $file2 = $this->createTempFile('image2.jpg', 'content2');
        $file3 = $this->createTempFile('doc.pdf', 'content3');

        Archive::add($file1)->toCollection('images')->store(); // Anonymous images
        Archive::add($file2)->toCurator($curator)->toCollection('images')->store(); // Curated images
        Archive::add($file3)->toCollection('documents')->store(); // Anonymous documents

        // Act
        $media = Media::query()
            ->anonymous()
            ->inCollection('images')
            ->get();

        // Assert
        expect($media)->toHaveCount(1)
            ->and($media->first()->collection)->toBe('images')
            ->and($media->first()->curator_id)->toBeNull();
    });
});

describe('MediaQueryBuilder - onDisk Scope', function (): void {
    it('filters media by storage disk', function (): void {
        // Arrange
        $file1 = $this->createTempFile('public1.txt', 'content1');
        $file2 = $this->createTempFile('public2.txt', 'content2');
        $file3 = $this->createTempFile('local.txt', 'content3');

        Archive::add($file1)->toDisk('public')->store();
        Archive::add($file2)->toDisk('public')->store();

        // Create media with different disk manually to avoid s3 dependency
        $media3 = Archive::add($file3)->toDisk('public')->store();
        $media3->update(['disk' => 'local']);

        // Act
        $publicMedia = Media::query()->onDisk('public')->get();
        $localMedia = Media::query()->onDisk('local')->get();

        // Assert
        expect($publicMedia)->toHaveCount(2)
            ->and($localMedia)->toHaveCount(1)
            ->and($publicMedia->first()->disk)->toBe('public')
            ->and($localMedia->first()->disk)->toBe('local');
    });

    it('returns empty collection when disk has no media', function (): void {
        // Arrange
        $file = $this->createTempFile('file.txt', 'content');
        Archive::add($file)->toDisk('public')->store();

        // Act
        $media = Media::query()->onDisk('nonexistent')->get();

        // Assert
        expect($media)->toBeEmpty();
    });

    it('can be chained with curator filter', function (): void {
        // Arrange
        $curator = TestModel::query()->create(['name' => 'Test Curator']);

        $file1 = $this->createTempFile('file1.txt', 'content1');
        $file2 = $this->createTempFile('file2.txt', 'content2');
        $file3 = $this->createTempFile('file3.txt', 'content3');

        Archive::add($file1)->toCurator($curator)->toDisk('public')->store();

        $media2 = Archive::add($file2)->toCurator($curator)->toDisk('public')->store();
        $media2->update(['disk' => 'local']); // Simulate different disk

        Archive::add($file3)->toDisk('public')->store(); // Anonymous

        // Act
        $media = Media::query()
            ->curatedBy($curator)
            ->onDisk('public')
            ->get();

        // Assert
        expect($media)->toHaveCount(1)
            ->and($media->first()->disk)->toBe('public')
            ->and($media->first()->curator_id)->toBe((string) $curator->id);
    });
});

describe('MediaQueryBuilder - ofType Scope', function (): void {
    it('filters media by exact MIME type', function (): void {
        // Arrange
        $file1 = $this->createTempFile('image.jpg', 'content');
        $file2 = $this->createTempFile('doc.pdf', 'content');
        $file3 = $this->createTempFile('photo.png', 'content');

        $media1 = Archive::add($file1)->store();
        $media2 = Archive::add($file2)->store();
        $media3 = Archive::add($file3)->store();

        // Manually set MIME types for testing
        $media1->update(['mime_type' => 'image/jpeg']);
        $media2->update(['mime_type' => 'application/pdf']);
        $media3->update(['mime_type' => 'image/png']);

        // Act
        $jpegMedia = Media::query()->ofType('image/jpeg')->get();
        $pdfMedia = Media::query()->ofType('application/pdf')->get();

        // Assert
        expect($jpegMedia)->toHaveCount(1)
            ->and($jpegMedia->first()->mime_type)->toBe('image/jpeg')
            ->and($pdfMedia)->toHaveCount(1)
            ->and($pdfMedia->first()->mime_type)->toBe('application/pdf');
    });

    it('filters media by MIME type category', function (): void {
        // Arrange
        $file1 = $this->createTempFile('image1.jpg', 'content');
        $file2 = $this->createTempFile('image2.png', 'content');
        $file3 = $this->createTempFile('video.mp4', 'content');
        $file4 = $this->createTempFile('doc.pdf', 'content');

        $media1 = Archive::add($file1)->store();
        $media2 = Archive::add($file2)->store();
        $media3 = Archive::add($file3)->store();
        $media4 = Archive::add($file4)->store();

        $media1->update(['mime_type' => 'image/jpeg']);
        $media2->update(['mime_type' => 'image/png']);
        $media3->update(['mime_type' => 'video/mp4']);
        $media4->update(['mime_type' => 'application/pdf']);

        // Act
        $imageMedia = Media::query()->ofType('image')->get();
        $videoMedia = Media::query()->ofType('video')->get();
        $applicationMedia = Media::query()->ofType('application')->get();

        // Assert
        expect($imageMedia)->toHaveCount(2)
            ->and($videoMedia)->toHaveCount(1)
            ->and($applicationMedia)->toHaveCount(1)
            ->and($imageMedia->first()->mime_type)->toStartWith('image/')
            ->and($videoMedia->first()->mime_type)->toStartWith('video/')
            ->and($applicationMedia->first()->mime_type)->toStartWith('application/');
    });

    it('returns empty collection when no media matches type', function (): void {
        // Arrange
        $file = $this->createTempFile('image.jpg', 'content');
        $media = Archive::add($file)->store();
        $media->update(['mime_type' => 'image/jpeg']);

        // Act
        $audioMedia = Media::query()->ofType('audio')->get();

        // Assert
        expect($audioMedia)->toBeEmpty();
    });

    it('can be chained with collection filter', function (): void {
        // Arrange
        $file1 = $this->createTempFile('avatar.jpg', 'content');
        $file2 = $this->createTempFile('doc.jpg', 'content');
        $file3 = $this->createTempFile('profile.png', 'content');

        $media1 = Archive::add($file1)->toCollection('avatars')->store();
        $media2 = Archive::add($file2)->toCollection('documents')->store();
        $media3 = Archive::add($file3)->toCollection('avatars')->store();

        $media1->update(['mime_type' => 'image/jpeg']);
        $media2->update(['mime_type' => 'image/jpeg']);
        $media3->update(['mime_type' => 'image/png']);

        // Act
        $media = Media::query()
            ->inCollection('avatars')
            ->ofType('image/jpeg')
            ->get();

        // Assert
        expect($media)->toHaveCount(1)
            ->and($media->first()->collection)->toBe('avatars')
            ->and($media->first()->mime_type)->toBe('image/jpeg');
    });
});

describe('MediaQueryBuilder - ordered Scope', function (): void {
    it('filters and orders media with order_column set', function (): void {
        // Arrange
        $file1 = $this->createTempFile('file1.txt', 'content1');
        $file2 = $this->createTempFile('file2.txt', 'content2');
        $file3 = $this->createTempFile('file3.txt', 'content3');
        $file4 = $this->createTempFile('file4.txt', 'content4');

        $media1 = Archive::add($file1)->store();
        $media2 = Archive::add($file2)->store();
        $media3 = Archive::add($file3)->store();
        $media4 = Archive::add($file4)->store();

        // Set order columns
        $media1->update(['order_column' => 3]);
        $media2->update(['order_column' => 1]);
        $media3->update(['order_column' => 2]);
        // media4 has no order_column (null)

        // Act
        $orderedMedia = Media::query()->ordered()->get();

        // Assert
        expect($orderedMedia)->toHaveCount(3)
            ->and($orderedMedia->pluck('order_column')->toArray())->toBe([1, 2, 3])
            ->and($orderedMedia->pluck('id')->toArray())->toBe([$media2->id, $media3->id, $media1->id]);
    });

    it('returns empty collection when no media has order_column', function (): void {
        // Arrange
        $file1 = $this->createTempFile('file1.txt', 'content1');
        $file2 = $this->createTempFile('file2.txt', 'content2');

        Archive::add($file1)->store();
        Archive::add($file2)->store();

        // Act
        $orderedMedia = Media::query()->ordered()->get();

        // Assert
        expect($orderedMedia)->toBeEmpty();
    });

    it('can be chained with collection filter', function (): void {
        // Arrange
        $file1 = $this->createTempFile('gallery1.jpg', 'content');
        $file2 = $this->createTempFile('gallery2.jpg', 'content');
        $file3 = $this->createTempFile('doc.pdf', 'content');

        $media1 = Archive::add($file1)->toCollection('gallery')->store();
        $media2 = Archive::add($file2)->toCollection('gallery')->store();
        $media3 = Archive::add($file3)->toCollection('documents')->store();

        $media1->update(['order_column' => 2]);
        $media2->update(['order_column' => 1]);
        $media3->update(['order_column' => 1]);

        // Act
        $media = Media::query()
            ->inCollection('gallery')
            ->ordered()
            ->get();

        // Assert
        expect($media)->toHaveCount(2)
            ->and($media->first()->collection)->toBe('gallery')
            ->and($media->first()->order_column)->toBe(1)
            ->and($media->last()->order_column)->toBe(2);
    });

    it('maintains sort order with duplicate order_column values', function (): void {
        // Arrange
        $file1 = $this->createTempFile('file1.txt', 'content1');
        $file2 = $this->createTempFile('file2.txt', 'content2');
        $file3 = $this->createTempFile('file3.txt', 'content3');

        $media1 = Archive::add($file1)->store();
        $media2 = Archive::add($file2)->store();
        $media3 = Archive::add($file3)->store();

        $media1->update(['order_column' => 1]);
        $media2->update(['order_column' => 1]);
        $media3->update(['order_column' => 2]);

        // Act
        $orderedMedia = Media::query()->ordered()->get();

        // Assert
        expect($orderedMedia)->toHaveCount(3)
            ->and($orderedMedia->pluck('order_column')->toArray())->toBe([1, 1, 2]);
    });
});

describe('MediaQueryBuilder - inCollection Scope', function (): void {
    it('filters media by collection name', function (): void {
        // Arrange
        $file1 = $this->createTempFile('avatar.jpg', 'content');
        $file2 = $this->createTempFile('doc.pdf', 'content');
        $file3 = $this->createTempFile('profile.jpg', 'content');

        Archive::add($file1)->toCollection('avatars')->store();
        Archive::add($file2)->toCollection('documents')->store();
        Archive::add($file3)->toCollection('avatars')->store();

        // Act
        $avatars = Media::query()->inCollection('avatars')->get();
        $documents = Media::query()->inCollection('documents')->get();

        // Assert
        expect($avatars)->toHaveCount(2)
            ->and($documents)->toHaveCount(1)
            ->and($avatars->first()->collection)->toBe('avatars')
            ->and($documents->first()->collection)->toBe('documents');
    });

    it('returns empty collection when collection has no media', function (): void {
        // Arrange
        $file = $this->createTempFile('file.txt', 'content');
        Archive::add($file)->toCollection('existing')->store();

        // Act
        $media = Media::query()->inCollection('nonexistent')->get();

        // Assert
        expect($media)->toBeEmpty();
    });

    it('handles default collection', function (): void {
        // Arrange
        $file1 = $this->createTempFile('default.txt', 'content');
        $file2 = $this->createTempFile('custom.txt', 'content');

        Archive::add($file1)->store(); // Default collection
        Archive::add($file2)->toCollection('custom')->store();

        // Act
        $defaultMedia = Media::query()->inCollection('default')->get();

        // Assert
        expect($defaultMedia)->toHaveCount(1)
            ->and($defaultMedia->first()->collection)->toBe('default');
    });
});

describe('MediaQueryBuilder - Complex Chaining', function (): void {
    it('chains multiple scopes together', function (): void {
        // Arrange
        $curator1 = TestModel::query()->create(['name' => 'Curator 1']);
        $curator2 = TestModel::query()->create(['name' => 'Curator 2']);

        $file1 = $this->createTempFile('image1.jpg', 'content');
        $file2 = $this->createTempFile('image2.jpg', 'content');
        $file3 = $this->createTempFile('doc.pdf', 'content');
        $file4 = $this->createTempFile('image3.jpg', 'content');

        $media1 = Archive::add($file1)->toCurator($curator1)->toCollection('images')->toDisk('public')->store();
        $media2 = Archive::add($file2)->toCurator($curator1)->toCollection('images')->toDisk('public')->store();
        $media3 = Archive::add($file3)->toCurator($curator1)->toCollection('documents')->toDisk('public')->store();
        $media4 = Archive::add($file4)->toCurator($curator2)->toCollection('images')->toDisk('public')->store();

        $media1->update(['mime_type' => 'image/jpeg', 'order_column' => 1]);
        $media2->update(['mime_type' => 'image/jpeg', 'order_column' => 2, 'disk' => 'local']);
        $media3->update(['mime_type' => 'application/pdf']);
        $media4->update(['mime_type' => 'image/jpeg', 'order_column' => 1]);

        // Act
        $media = Media::query()
            ->curatedBy($curator1)
            ->inCollection('images')
            ->onDisk('public')
            ->ofType('image')
            ->ordered()
            ->withCurator()
            ->get();

        // Assert
        expect($media)->toHaveCount(1)
            ->and($media->first()->id)->toBe($media1->id)
            ->and($media->first()->curator_id)->toBe((string) $curator1->id)
            ->and($media->first()->collection)->toBe('images')
            ->and($media->first()->disk)->toBe('public')
            ->and($media->first()->mime_type)->toStartWith('image/')
            ->and($media->first()->order_column)->toBe(1)
            ->and($media->first()->relationLoaded('curator'))->toBeTrue();
    });

    it('chains anonymous with collection and type filters', function (): void {
        // Arrange
        $curator = TestModel::query()->create(['name' => 'Test Curator']);

        $file1 = $this->createTempFile('anon-image.jpg', 'content');
        $file2 = $this->createTempFile('curated-image.jpg', 'content');
        $file3 = $this->createTempFile('anon-doc.pdf', 'content');

        $media1 = Archive::add($file1)->toCollection('gallery')->store(); // Anonymous
        $media2 = Archive::add($file2)->toCurator($curator)->toCollection('gallery')->store(); // Curated
        $media3 = Archive::add($file3)->toCollection('gallery')->store(); // Anonymous

        $media1->update(['mime_type' => 'image/jpeg']);
        $media2->update(['mime_type' => 'image/jpeg']);
        $media3->update(['mime_type' => 'application/pdf']);

        // Act
        $media = Media::query()
            ->anonymous()
            ->inCollection('gallery')
            ->ofType('image')
            ->get();

        // Assert
        expect($media)->toHaveCount(1)
            ->and($media->first()->id)->toBe($media1->id)
            ->and($media->first()->curator_id)->toBeNull()
            ->and($media->first()->collection)->toBe('gallery')
            ->and($media->first()->mime_type)->toStartWith('image/');
    });

    it('chains ordered with disk and type filters', function (): void {
        // Arrange
        $file1 = $this->createTempFile('image1.jpg', 'content');
        $file2 = $this->createTempFile('image2.jpg', 'content');
        $file3 = $this->createTempFile('image3.jpg', 'content');

        $media1 = Archive::add($file1)->toDisk('public')->store();
        $media2 = Archive::add($file2)->toDisk('public')->store();
        $media3 = Archive::add($file3)->toDisk('public')->store();

        $media1->update(['mime_type' => 'image/jpeg', 'order_column' => 2]);
        $media2->update(['mime_type' => 'image/png', 'order_column' => 1]);
        $media3->update(['mime_type' => 'image/jpeg', 'order_column' => 1, 'disk' => 'local']);

        // Act
        $media = Media::query()
            ->onDisk('public')
            ->ofType('image/jpeg')
            ->ordered()
            ->get();

        // Assert
        expect($media)->toHaveCount(1)
            ->and($media->first()->id)->toBe($media1->id)
            ->and($media->first()->disk)->toBe('public')
            ->and($media->first()->mime_type)->toBe('image/jpeg')
            ->and($media->first()->order_column)->toBe(2);
    });
});

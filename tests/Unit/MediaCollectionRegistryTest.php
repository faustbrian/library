<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Support\MediaCollection;
use Cline\Archive\Support\MediaCollectionRegistry;
use Tests\Support\TestModel;

describe('MediaCollectionRegistry - Happy Path', function (): void {
    beforeEach(function (): void {
        // Clear registry before each test to ensure clean state
        MediaCollectionRegistry::clear();
    });

    it('can define a new collection', function (): void {
        // Arrange & Act
        $collection = MediaCollectionRegistry::define('avatars');

        // Assert
        expect($collection)->toBeInstanceOf(MediaCollection::class)
            ->and($collection->getName())->toBe('avatars');
    });

    it('can define collection with fluent configuration', function (): void {
        // Arrange & Act
        $collection = MediaCollectionRegistry::define('documents')
            ->singleFile()
            ->toDisk('s3')
            ->curatedBy(TestModel::class);

        // Assert
        expect($collection->getName())->toBe('documents')
            ->and($collection->isSingleFile())->toBeTrue()
            ->and($collection->getDisk())->toBe('s3')
            ->and($collection->getCuratorType())->toBe(TestModel::class);
    });

    it('can define collection allowing anonymous media', function (): void {
        // Arrange & Act
        $collection = MediaCollectionRegistry::define('temp-uploads')
            ->toDisk('public')
            ->curatedByAnonymous();

        // Assert
        expect($collection->getName())->toBe('temp-uploads')
            ->and($collection->allowsAnonymous())->toBeTrue()
            ->and($collection->getDisk())->toBe('public');
    });

    it('can retrieve defined collection by name', function (): void {
        // Arrange
        MediaCollectionRegistry::define('photos')->toDisk('public');

        // Act
        $collection = MediaCollectionRegistry::get('photos');

        // Assert
        expect($collection)->toBeInstanceOf(MediaCollection::class)
            ->and($collection->getName())->toBe('photos')
            ->and($collection->getDisk())->toBe('public');
    });

    it('returns same instance when retrieving collection', function (): void {
        // Arrange
        $original = MediaCollectionRegistry::define('videos');

        // Act
        $retrieved = MediaCollectionRegistry::get('videos');

        // Assert
        expect($retrieved)->toBe($original);
    });

    it('can check if collection exists', function (): void {
        // Arrange
        MediaCollectionRegistry::define('images');

        // Act
        $exists = MediaCollectionRegistry::has('images');
        $notExists = MediaCollectionRegistry::has('nonexistent');

        // Assert
        expect($exists)->toBeTrue()
            ->and($notExists)->toBeFalse();
    });

    it('can retrieve all registered collections', function (): void {
        // Arrange
        MediaCollectionRegistry::define('avatars')->singleFile();
        MediaCollectionRegistry::define('documents')->toDisk('s3');
        MediaCollectionRegistry::define('photos');

        // Act
        $all = MediaCollectionRegistry::all();

        // Assert
        expect($all)->toBeArray()
            ->and($all)->toHaveCount(3)
            ->and($all)->toHaveKeys(['avatars', 'documents', 'photos'])
            ->and($all['avatars'])->toBeInstanceOf(MediaCollection::class)
            ->and($all['documents'])->toBeInstanceOf(MediaCollection::class)
            ->and($all['photos'])->toBeInstanceOf(MediaCollection::class);
    });

    it('can clear all collections from registry', function (): void {
        // Arrange
        MediaCollectionRegistry::define('avatars');
        MediaCollectionRegistry::define('documents');
        MediaCollectionRegistry::define('photos');
        expect(MediaCollectionRegistry::all())->toHaveCount(3);

        // Act
        MediaCollectionRegistry::clear();

        // Assert
        expect(MediaCollectionRegistry::all())->toBeEmpty()
            ->and(MediaCollectionRegistry::has('avatars'))->toBeFalse()
            ->and(MediaCollectionRegistry::has('documents'))->toBeFalse()
            ->and(MediaCollectionRegistry::has('photos'))->toBeFalse();
    });

    it('returns empty array when no collections defined', function (): void {
        // Arrange
        MediaCollectionRegistry::clear();

        // Act
        $all = MediaCollectionRegistry::all();

        // Assert
        expect($all)->toBeArray()
            ->and($all)->toBeEmpty();
    });
});

describe('MediaCollectionRegistry - Sad Path', function (): void {
    beforeEach(function (): void {
        MediaCollectionRegistry::clear();
    });

    it('returns null when getting non-existent collection', function (): void {
        // Arrange
        MediaCollectionRegistry::clear();

        // Act
        $collection = MediaCollectionRegistry::get('nonexistent');

        // Assert
        expect($collection)->toBeNull();
    });

    it('returns null when getting collection from empty registry', function (): void {
        // Arrange
        MediaCollectionRegistry::clear();

        // Act
        $collection = MediaCollectionRegistry::get('anything');

        // Assert
        expect($collection)->toBeNull();
    });

    it('returns false when checking non-existent collection', function (): void {
        // Arrange
        MediaCollectionRegistry::define('avatars');

        // Act
        $exists = MediaCollectionRegistry::has('nonexistent');

        // Assert
        expect($exists)->toBeFalse();
    });

    it('overwrites existing collection when defining with same name', function (): void {
        // Arrange
        $original = MediaCollectionRegistry::define('photos')->toDisk('local');
        expect($original->getDisk())->toBe('local');

        // Act
        $overwritten = MediaCollectionRegistry::define('photos')->toDisk('s3');
        $retrieved = MediaCollectionRegistry::get('photos');

        // Assert
        expect($retrieved)->toBe($overwritten)
            ->and($retrieved)->not->toBe($original)
            ->and($retrieved->getDisk())->toBe('s3');
    });
});

describe('MediaCollectionRegistry - Edge Cases', function (): void {
    beforeEach(function (): void {
        MediaCollectionRegistry::clear();
    });

    it('can define collection with empty string name', function (): void {
        // Arrange & Act
        $collection = MediaCollectionRegistry::define('');

        // Assert
        expect($collection->getName())->toBe('')
            ->and(MediaCollectionRegistry::has(''))->toBeTrue()
            ->and(MediaCollectionRegistry::get(''))->toBe($collection);
    });

    it('can define collection with special characters in name', function (): void {
        // Arrange & Act
        $name = 'user-avatars_v2.temp';
        $collection = MediaCollectionRegistry::define($name);

        // Assert
        expect($collection->getName())->toBe($name)
            ->and(MediaCollectionRegistry::has($name))->toBeTrue()
            ->and(MediaCollectionRegistry::get($name))->toBe($collection);
    });

    it('can define collection with unicode characters in name', function (): void {
        // Arrange & Act
        $name = 'médias-üñîçödé';
        $collection = MediaCollectionRegistry::define($name);

        // Assert
        expect($collection->getName())->toBe($name)
            ->and(MediaCollectionRegistry::has($name))->toBeTrue()
            ->and(MediaCollectionRegistry::get($name))->toBe($collection);
    });

    it('maintains separate collections with similar names', function (): void {
        // Arrange
        $photos = MediaCollectionRegistry::define('photos')->toDisk('local');
        $photosArchive = MediaCollectionRegistry::define('photos_archive')->toDisk('s3');
        $photosTmp = MediaCollectionRegistry::define('photos-tmp')->toDisk('public');

        // Act & Assert
        expect(MediaCollectionRegistry::all())->toHaveCount(3)
            ->and(MediaCollectionRegistry::get('photos'))->toBe($photos)
            ->and(MediaCollectionRegistry::get('photos_archive'))->toBe($photosArchive)
            ->and(MediaCollectionRegistry::get('photos-tmp'))->toBe($photosTmp)
            ->and($photos->getDisk())->toBe('local')
            ->and($photosArchive->getDisk())->toBe('s3')
            ->and($photosTmp->getDisk())->toBe('public');
    });

    it('can clear and redefine collections', function (): void {
        // Arrange
        MediaCollectionRegistry::define('avatars')->toDisk('local');
        MediaCollectionRegistry::define('documents')->toDisk('s3');
        expect(MediaCollectionRegistry::all())->toHaveCount(2);

        // Act
        MediaCollectionRegistry::clear();
        $newAvatars = MediaCollectionRegistry::define('avatars')->toDisk('public');

        // Assert
        expect(MediaCollectionRegistry::all())->toHaveCount(1)
            ->and(MediaCollectionRegistry::has('documents'))->toBeFalse()
            ->and(MediaCollectionRegistry::get('avatars'))->toBe($newAvatars)
            ->and($newAvatars->getDisk())->toBe('public');
    });

    it('returns consistent results after multiple clear operations', function (): void {
        // Arrange
        MediaCollectionRegistry::define('test1');
        MediaCollectionRegistry::define('test2');

        // Act
        MediaCollectionRegistry::clear();
        MediaCollectionRegistry::clear();
        MediaCollectionRegistry::clear();

        // Assert
        expect(MediaCollectionRegistry::all())->toBeEmpty()
            ->and(MediaCollectionRegistry::has('test1'))->toBeFalse()
            ->and(MediaCollectionRegistry::has('test2'))->toBeFalse();
    });

    it('maintains collection state across multiple get calls', function (): void {
        // Arrange
        MediaCollectionRegistry::define('immutable')
            ->singleFile()
            ->toDisk('s3')
            ->curatedBy(TestModel::class);

        // Act
        $first = MediaCollectionRegistry::get('immutable');
        $second = MediaCollectionRegistry::get('immutable');
        $third = MediaCollectionRegistry::get('immutable');

        // Assert
        expect($first)->toBe($second)
            ->and($second)->toBe($third)
            ->and($first->isSingleFile())->toBeTrue()
            ->and($second->getDisk())->toBe('s3')
            ->and($third->getCuratorType())->toBe(TestModel::class);
    });

    it('maintains global state across registry operations', function (): void {
        // Arrange
        MediaCollectionRegistry::define('collection1')->toDisk('disk1');
        MediaCollectionRegistry::define('collection2')->toDisk('disk2');

        // Act
        MediaCollectionRegistry::define('collection3')->toDisk('disk3');
        $has1 = MediaCollectionRegistry::has('collection1');
        $has2 = MediaCollectionRegistry::has('collection2');
        $has3 = MediaCollectionRegistry::has('collection3');
        $all = MediaCollectionRegistry::all();

        // Assert
        expect($has1)->toBeTrue()
            ->and($has2)->toBeTrue()
            ->and($has3)->toBeTrue()
            ->and($all)->toHaveCount(3)
            ->and($all['collection1']->getDisk())->toBe('disk1')
            ->and($all['collection2']->getDisk())->toBe('disk2')
            ->and($all['collection3']->getDisk())->toBe('disk3');
    });

    it('handles rapid define and clear operations', function (): void {
        // Act & Assert
        for ($i = 0; $i < 10; ++$i) {
            MediaCollectionRegistry::define('collection'.$i);
            expect(MediaCollectionRegistry::has('collection'.$i))->toBeTrue();
        }

        expect(MediaCollectionRegistry::all())->toHaveCount(10);

        MediaCollectionRegistry::clear();
        expect(MediaCollectionRegistry::all())->toBeEmpty();

        for ($i = 0; $i < 10; ++$i) {
            expect(MediaCollectionRegistry::has('collection'.$i))->toBeFalse();
        }
    });
});

describe('MediaCollectionRegistry - Integration', function (): void {
    beforeEach(function (): void {
        MediaCollectionRegistry::clear();
    });

    it('can define multiple collections with different configurations', function (): void {
        // Arrange & Act
        MediaCollectionRegistry::define('user-avatars')
            ->singleFile()
            ->toDisk('public')
            ->curatedBy(TestModel::class);

        MediaCollectionRegistry::define('product-images')
            ->toDisk('s3');

        MediaCollectionRegistry::define('temp-uploads')
            ->curatedByAnonymous()
            ->toDisk('local');

        MediaCollectionRegistry::define('documents')
            ->singleFile()
            ->toDisk('s3')
            ->curatedBy(TestModel::class);

        // Assert
        $all = MediaCollectionRegistry::all();
        expect($all)->toHaveCount(4);

        $avatars = MediaCollectionRegistry::get('user-avatars');
        expect($avatars->isSingleFile())->toBeTrue()
            ->and($avatars->getDisk())->toBe('public')
            ->and($avatars->getCuratorType())->toBe(TestModel::class)
            ->and($avatars->allowsAnonymous())->toBeFalse();

        $products = MediaCollectionRegistry::get('product-images');
        expect($products->isSingleFile())->toBeFalse()
            ->and($products->getDisk())->toBe('s3')
            ->and($products->getCuratorType())->toBeNull();

        $temps = MediaCollectionRegistry::get('temp-uploads');
        expect($temps->allowsAnonymous())->toBeTrue()
            ->and($temps->getDisk())->toBe('local');

        $docs = MediaCollectionRegistry::get('documents');
        expect($docs->isSingleFile())->toBeTrue()
            ->and($docs->getDisk())->toBe('s3')
            ->and($docs->getCuratorType())->toBe(TestModel::class);
    });

    it('maintains registry independence from collection modifications', function (): void {
        // Arrange
        $collection = MediaCollectionRegistry::define('photos');

        // Act - Retrieve and verify same instance
        $retrieved = MediaCollectionRegistry::get('photos');

        // Assert - Modifications to retrieved instance affect registry
        expect($retrieved)->toBe($collection)
            ->and(MediaCollectionRegistry::has('photos'))->toBeTrue();
    });

    it('supports chaining all collection configuration methods', function (): void {
        // Arrange & Act
        $collection = MediaCollectionRegistry::define('full-config')
            ->singleFile()
            ->toDisk('s3')
            ->curatedBy(TestModel::class)
            ->curatedByAnonymous();

        // Assert
        expect($collection->getName())->toBe('full-config')
            ->and($collection->isSingleFile())->toBeTrue()
            ->and($collection->getDisk())->toBe('s3')
            ->and($collection->getCuratorType())->toBe(TestModel::class)
            ->and($collection->allowsAnonymous())->toBeTrue();
    });
});

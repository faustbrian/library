<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Support\MediaCollection;
use Tests\Support\TestModel;

describe('MediaCollection - Happy Path', function (): void {
    it('creates collection with given name', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('product-images');

        // Assert
        expect($collection->getName())->toBe('product-images');
    });

    it('defaults to multiple files allowed', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('gallery');

        // Assert
        expect($collection->isSingleFile())->toBeFalse();
    });

    it('defaults to null disk', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('documents');

        // Assert
        expect($collection->getDisk())->toBeNull();
    });

    it('defaults to null curator type', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('uploads');

        // Assert
        expect($collection->getCuratorType())->toBeNull();
    });

    it('defaults to disallow anonymous uploads', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('avatars');

        // Assert
        expect($collection->allowsAnonymous())->toBeFalse();
    });

    it('marks collection as single file', function (): void {
        // Arrange
        $collection = new MediaCollection('avatar');

        // Act
        $result = $collection->singleFile();

        // Assert
        expect($result)->toBe($collection)
            ->and($collection->isSingleFile())->toBeTrue();
    });

    it('sets disk name', function (): void {
        // Arrange
        $collection = new MediaCollection('photos');

        // Act
        $result = $collection->toDisk('s3');

        // Assert
        expect($result)->toBe($collection)
            ->and($collection->getDisk())->toBe('s3');
    });

    it('sets curator type', function (): void {
        // Arrange
        $collection = new MediaCollection('profile-images');

        // Act
        $result = $collection->curatedBy(TestModel::class);

        // Assert
        expect($result)->toBe($collection)
            ->and($collection->getCuratorType())->toBe(TestModel::class);
    });

    it('allows anonymous uploads', function (): void {
        // Arrange
        $collection = new MediaCollection('temp-uploads');

        // Act
        $result = $collection->curatedByAnonymous();

        // Assert
        expect($result)->toBe($collection)
            ->and($collection->allowsAnonymous())->toBeTrue();
    });

    it('supports fluent method chaining', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('featured-image')
            ->singleFile()
            ->toDisk('public')
            ->curatedBy(TestModel::class);

        // Assert
        expect($collection->getName())->toBe('featured-image')
            ->and($collection->isSingleFile())->toBeTrue()
            ->and($collection->getDisk())->toBe('public')
            ->and($collection->getCuratorType())->toBe(TestModel::class)
            ->and($collection->allowsAnonymous())->toBeFalse();
    });

    it('supports fluent method chaining with anonymous uploads', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('shared-gallery')
            ->toDisk('s3')
            ->curatedByAnonymous();

        // Assert
        expect($collection->getName())->toBe('shared-gallery')
            ->and($collection->isSingleFile())->toBeFalse()
            ->and($collection->getDisk())->toBe('s3')
            ->and($collection->getCuratorType())->toBeNull()
            ->and($collection->allowsAnonymous())->toBeTrue();
    });

    it('maintains immutability of name property', function (): void {
        // Arrange
        $collectionName = 'immutable-collection';
        $collection = new MediaCollection($collectionName);

        // Act
        $collection->singleFile()->toDisk('local')->curatedByAnonymous();

        // Assert
        expect($collection->getName())->toBe($collectionName);
    });

    it('can change disk after initial setting', function (): void {
        // Arrange
        $collection = new MediaCollection('flexible-storage');
        $collection->toDisk('local');

        // Act
        $collection->toDisk('s3');

        // Assert
        expect($collection->getDisk())->toBe('s3');
    });

    it('can change curator type after initial setting', function (): void {
        // Arrange
        $collection = new MediaCollection('polymorphic-media');
        $collection->curatedBy(TestModel::class);

        // Act
        $collection->curatedBy('App\Models\User');

        // Assert
        expect($collection->getCuratorType())->toBe('App\Models\User');
    });
});

describe('MediaCollection - Edge Cases', function (): void {
    it('handles empty string as disk name', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $collection->toDisk('');

        // Assert
        expect($collection->getDisk())->toBe('');
    });

    it('handles special characters in collection name', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('user-profile_images@2024');

        // Assert
        expect($collection->getName())->toBe('user-profile_images@2024');
    });

    it('handles unicode characters in collection name', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('文档-photos-🖼️');

        // Assert
        expect($collection->getName())->toBe('文档-photos-🖼️');
    });

    it('allows calling singleFile multiple times', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $collection->singleFile();
        $collection->singleFile();

        // Assert
        expect($collection->isSingleFile())->toBeTrue();
    });

    it('allows calling curatedByAnonymous multiple times', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $collection->curatedByAnonymous();
        $collection->curatedByAnonymous();

        // Assert
        expect($collection->allowsAnonymous())->toBeTrue();
    });

    it('handles very long collection names', function (): void {
        // Arrange
        $longName = str_repeat('a', 1_000);

        // Act
        $collection = new MediaCollection($longName);

        // Assert
        expect($collection->getName())->toBe($longName)
            ->and(mb_strlen($collection->getName()))->toBe(1_000);
    });

    it('handles fully qualified class names as curator type', function (): void {
        // Arrange
        $collection = new MediaCollection('test');
        $fqcn = 'App\Domain\Products\Models\ProductVariant';

        // Act
        $collection->curatedBy($fqcn);

        // Assert
        expect($collection->getCuratorType())->toBe($fqcn);
    });

    it('preserves method call order in fluent chain', function (): void {
        // Arrange & Act
        $collection = new MediaCollection('test')
            ->curatedBy(TestModel::class)
            ->toDisk('s3')
            ->singleFile()
            ->curatedByAnonymous();

        // Assert
        expect($collection->getCuratorType())->toBe(TestModel::class)
            ->and($collection->getDisk())->toBe('s3')
            ->and($collection->isSingleFile())->toBeTrue()
            ->and($collection->allowsAnonymous())->toBeTrue();
    });
});

describe('MediaCollection - Configuration Scenarios', function (): void {
    it('configures avatar collection pattern', function (): void {
        // Arrange & Act
        $avatar = new MediaCollection('avatar')
            ->singleFile()
            ->toDisk('public')
            ->curatedBy(TestModel::class);

        // Assert
        expect($avatar->getName())->toBe('avatar')
            ->and($avatar->isSingleFile())->toBeTrue()
            ->and($avatar->getDisk())->toBe('public')
            ->and($avatar->getCuratorType())->toBe(TestModel::class)
            ->and($avatar->allowsAnonymous())->toBeFalse();
    });

    it('configures product images collection pattern', function (): void {
        // Arrange & Act
        $productImages = new MediaCollection('product-images')
            ->toDisk('s3')
            ->curatedBy('App\Models\Product');

        // Assert
        expect($productImages->getName())->toBe('product-images')
            ->and($productImages->isSingleFile())->toBeFalse()
            ->and($productImages->getDisk())->toBe('s3')
            ->and($productImages->getCuratorType())->toBe('App\Models\Product')
            ->and($productImages->allowsAnonymous())->toBeFalse();
    });

    it('configures temporary upload collection pattern', function (): void {
        // Arrange & Act
        $tempUploads = new MediaCollection('temp-uploads')
            ->toDisk('local')
            ->curatedByAnonymous();

        // Assert
        expect($tempUploads->getName())->toBe('temp-uploads')
            ->and($tempUploads->isSingleFile())->toBeFalse()
            ->and($tempUploads->getDisk())->toBe('local')
            ->and($tempUploads->getCuratorType())->toBeNull()
            ->and($tempUploads->allowsAnonymous())->toBeTrue();
    });

    it('configures shared media library pattern', function (): void {
        // Arrange & Act
        $sharedLibrary = new MediaCollection('shared-library')
            ->curatedByAnonymous();

        // Assert
        expect($sharedLibrary->getName())->toBe('shared-library')
            ->and($sharedLibrary->isSingleFile())->toBeFalse()
            ->and($sharedLibrary->getDisk())->toBeNull()
            ->and($sharedLibrary->getCuratorType())->toBeNull()
            ->and($sharedLibrary->allowsAnonymous())->toBeTrue();
    });

    it('configures featured image collection pattern', function (): void {
        // Arrange & Act
        $featured = new MediaCollection('featured-image')
            ->singleFile()
            ->toDisk('public');

        // Assert
        expect($featured->getName())->toBe('featured-image')
            ->and($featured->isSingleFile())->toBeTrue()
            ->and($featured->getDisk())->toBe('public')
            ->and($featured->getCuratorType())->toBeNull()
            ->and($featured->allowsAnonymous())->toBeFalse();
    });

    it('configures minimal collection with name only', function (): void {
        // Arrange & Act
        $minimal = new MediaCollection('minimal');

        // Assert
        expect($minimal->getName())->toBe('minimal')
            ->and($minimal->isSingleFile())->toBeFalse()
            ->and($minimal->getDisk())->toBeNull()
            ->and($minimal->getCuratorType())->toBeNull()
            ->and($minimal->allowsAnonymous())->toBeFalse();
    });

    it('configures collection with all options enabled', function (): void {
        // Arrange & Act
        $maximal = new MediaCollection('maximal')
            ->singleFile()
            ->toDisk('s3')
            ->curatedBy(TestModel::class)
            ->curatedByAnonymous();

        // Assert
        expect($maximal->getName())->toBe('maximal')
            ->and($maximal->isSingleFile())->toBeTrue()
            ->and($maximal->getDisk())->toBe('s3')
            ->and($maximal->getCuratorType())->toBe(TestModel::class)
            ->and($maximal->allowsAnonymous())->toBeTrue();
    });
});

describe('MediaCollection - Method Return Values', function (): void {
    it('returns self from singleFile for chaining', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $result = $collection->singleFile();

        // Assert
        expect($result)->toBe($collection);
    });

    it('returns self from toDisk for chaining', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $result = $collection->toDisk('public');

        // Assert
        expect($result)->toBe($collection);
    });

    it('returns self from curatedBy for chaining', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $result = $collection->curatedBy(TestModel::class);

        // Assert
        expect($result)->toBe($collection);
    });

    it('returns self from curatedByAnonymous for chaining', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $result = $collection->curatedByAnonymous();

        // Assert
        expect($result)->toBe($collection);
    });

    it('returns boolean from isSingleFile', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $result = $collection->isSingleFile();

        // Assert
        expect($result)->toBeBool();
    });

    it('returns string from getName', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $result = $collection->getName();

        // Assert
        expect($result)->toBeString();
    });

    it('returns nullable string from getDisk', function (): void {
        // Arrange
        $collection1 = new MediaCollection('test1');
        $collection2 = new MediaCollection('test2')->toDisk('s3');

        // Act
        $result1 = $collection1->getDisk();
        $result2 = $collection2->getDisk();

        // Assert
        expect($result1)->toBeNull()
            ->and($result2)->toBeString();
    });

    it('returns nullable string from getCuratorType', function (): void {
        // Arrange
        $collection1 = new MediaCollection('test1');
        $collection2 = new MediaCollection('test2')->curatedBy(TestModel::class);

        // Act
        $result1 = $collection1->getCuratorType();
        $result2 = $collection2->getCuratorType();

        // Assert
        expect($result1)->toBeNull()
            ->and($result2)->toBeString();
    });

    it('returns boolean from allowsAnonymous', function (): void {
        // Arrange
        $collection = new MediaCollection('test');

        // Act
        $result = $collection->allowsAnonymous();

        // Assert
        expect($result)->toBeBool();
    });
});

describe('MediaCollection - State Independence', function (): void {
    it('maintains independent state across instances', function (): void {
        // Arrange & Act
        $collection1 = new MediaCollection('col1')
            ->singleFile()
            ->toDisk('s3');

        $collection2 = new MediaCollection('col2')
            ->toDisk('public')
            ->curatedByAnonymous();

        // Assert
        expect($collection1->getName())->toBe('col1')
            ->and($collection1->isSingleFile())->toBeTrue()
            ->and($collection1->getDisk())->toBe('s3')
            ->and($collection1->allowsAnonymous())->toBeFalse()
            ->and($collection2->getName())->toBe('col2')
            ->and($collection2->isSingleFile())->toBeFalse()
            ->and($collection2->getDisk())->toBe('public')
            ->and($collection2->allowsAnonymous())->toBeTrue();
    });

    it('does not share state between instances', function (): void {
        // Arrange
        $collection1 = new MediaCollection('shared-name');
        $collection1->singleFile();

        // Act
        $collection2 = new MediaCollection('shared-name');

        // Assert
        expect($collection1->isSingleFile())->toBeTrue()
            ->and($collection2->isSingleFile())->toBeFalse();
    });
});

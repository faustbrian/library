<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Archive;
use Cline\Archive\Storage\MediaAdder;
use Cline\Archive\Support\MediaCollection;
use Cline\Archive\Support\MediaCollectionRegistry;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;

describe('Archive', function (): void {
    beforeEach(function (): void {
        // Clear registry before each test to ensure clean state
        MediaCollectionRegistry::clear();
    });

    it('can define media collection and register in registry', function (): void {
        // Arrange
        $collectionName = 'test-avatars';

        // Act
        $collection = Archive::collection($collectionName);

        // Assert
        expect($collection)
            ->toBeInstanceOf(MediaCollection::class)
            ->and(MediaCollectionRegistry::has($collectionName))->toBeTrue()
            ->and(MediaCollectionRegistry::get($collectionName))->toBe($collection);
    });

    it('can create media adder from static method', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt');

        // Act
        $adder = Archive::add($file);

        // Assert
        expect($adder)->toBeInstanceOf(MediaAdder::class);
    });

    it('can create media adder with UploadedFile', function (): void {
        // Arrange
        $tempFile = $this->createTempFile('upload.jpg', 'test image');
        $file = new UploadedFile(
            $tempFile,
            'test.jpg',
            'image/jpeg',
            null,
            true,
        );

        // Act
        $adder = Archive::add($file);

        // Assert
        expect($adder)->toBeInstanceOf(MediaAdder::class);
    });

    it('can create media adder with SymfonyFile', function (): void {
        // Arrange
        $tempFile = $this->createTempFile('symfony.png', 'test image');
        $file = new File($tempFile);

        // Act
        $adder = Archive::add($file);

        // Assert
        expect($adder)->toBeInstanceOf(MediaAdder::class);
    });

    it('can create media adder with string path', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt');

        // Act
        $adder = Archive::add($file);

        // Assert
        expect($adder)->toBeInstanceOf(MediaAdder::class);
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Archive;
use Cline\Archive\Storage\Filesystem;
use Cline\Archive\Storage\PathGenerator\DefaultPathGenerator;
use Illuminate\Support\Facades\Storage;

describe('Filesystem - Happy Path', function (): void {
    it('can add file to storage', function (): void {
        // Arrange
        Storage::fake('public');
        $filesystem = new Filesystem();
        $file = $this->createTempFile('test.txt', 'test content');
        $media = Archive::add($file)->store();

        // Act
        $path = $media->getPath();

        // Assert
        Storage::disk('public')->assertExists($path);
    });

    it('stores file with correct content', function (): void {
        // Arrange
        Storage::fake('public');
        $filesystem = new Filesystem();
        $content = 'specific test content';
        $file = $this->createTempFile('content-test.txt', $content);

        // Act
        $media = Archive::add($file)->store();
        $storedContent = Storage::disk('public')->get($media->getPath());

        // Assert
        expect($storedContent)->toBe($content);
    });

    it('can delete file from storage', function (): void {
        // Arrange
        Storage::fake('public');
        $filesystem = new Filesystem();
        $file = $this->createTempFile('delete-me.txt', 'content');
        $media = Archive::add($file)->store();
        $path = $media->getPath();

        Storage::disk('public')->assertExists($path);

        // Act
        $result = $filesystem->delete($media);

        // Assert
        expect($result)->toBeTrue();
        Storage::disk('public')->assertMissing($path);
    });

    it('stores file on specified disk', function (): void {
        // Arrange
        Storage::fake('s3');
        $file = $this->createTempFile('s3-file.txt', 'content');

        // Act
        $media = Archive::add($file)->toDisk('s3')->store();

        // Assert
        Storage::disk('s3')->assertExists($media->getPath());
    });

    it('stores file with path from path generator', function (): void {
        // Arrange
        Storage::fake('public');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();
        $path = $media->getPath();

        // Assert
        expect($path)->toContain((string) $media->getKey())
            ->and($path)->toContain('test.txt');
    });

    it('stores binary file correctly', function (): void {
        // Arrange
        Storage::fake('public');
        $binaryContent = "\x00\x01\x02\xFF\xFE";
        $file = $this->createTempFile('binary.dat', $binaryContent);

        // Act
        $media = Archive::add($file)->store();
        $storedContent = Storage::disk('public')->get($media->getPath());

        // Assert
        expect($storedContent)->toBe($binaryContent);
    });
});

describe('Filesystem - Edge Cases', function (): void {
    it('handles empty file', function (): void {
        // Arrange
        Storage::fake('public');
        $file = $this->createTempFile('empty.txt', '');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        Storage::disk('public')->assertExists($media->getPath());
        expect(Storage::disk('public')->size($media->getPath()))->toBe(0);
    });

    it('handles large file', function (): void {
        // Arrange
        Storage::fake('public');
        $largeContent = str_repeat('A', 1_024 * 100); // 100KB
        $file = $this->createTempFile('large.txt', $largeContent);

        // Act
        $media = Archive::add($file)->store();
        $storedContent = Storage::disk('public')->get($media->getPath());

        // Assert
        expect(mb_strlen($storedContent))->toBe(mb_strlen($largeContent));
    });

    it('handles file with special characters in name', function (): void {
        // Arrange
        Storage::fake('public');
        $file = $this->createTempFile('file with spaces.txt', 'content');

        // Act
        $media = Archive::add($file)->withFileName('special #chars.txt')->store();

        // Assert
        Storage::disk('public')->assertExists($media->getPath());
    });

    it('handles nested path structure', function (): void {
        // Arrange
        Storage::fake('public');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();
        $path = $media->getPath();

        // Assert
        expect($path)->toContain('/');
        Storage::disk('public')->assertExists($path);
    });

    it('overwrites existing file at same path', function (): void {
        // Arrange
        Storage::fake('public');
        $file1 = $this->createTempFile('first.txt', 'first content');
        $media1 = Archive::add($file1)->store();
        $path = $media1->getPath();

        // Force same path by creating new media with same ID
        Storage::disk('public')->put($path, 'original content');

        $file2 = $this->createTempFile('second.txt', 'new content');

        // Act
        $filesystem = new Filesystem();
        $result = $filesystem->add($file2, $media1);

        // Assert
        expect($result)->toBeTrue();
        $content = Storage::disk('public')->get($path);
        expect($content)->toBe('new content');
    });

    it('can handle deleting file that does not exist', function (): void {
        // Arrange
        Storage::fake('public');
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $filesystem = new Filesystem();

        // Delete the file first
        $filesystem->delete($media);

        // Act - try to delete again (Laravel Storage::delete returns true even if file doesn't exist)
        $result = $filesystem->delete($media);

        // Assert
        expect($result)->toBeTrue(); // Laravel's delete returns true even if file doesn't exist
    });
});

describe('Filesystem - Integration', function (): void {
    it('uses custom path generator from config', function (): void {
        // Arrange
        Storage::fake('public');
        config()->set('archive.path_generator', DefaultPathGenerator::class);
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media->getPath())->toBeString();
        Storage::disk('public')->assertExists($media->getPath());
    });

    it('respects disk configuration', function (): void {
        // Arrange
        Storage::fake('public');
        config()->set('archive.disk', 'public');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media->disk)->toBe('public');
    });

    it('handles prefix from configuration', function (): void {
        // Arrange
        Storage::fake('public');
        config()->set('archive.prefix', 'media');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();
        $path = $media->getPath();

        // Assert
        expect($path)->toContain('media');
    });

    it('can store multiple files without conflicts', function (): void {
        // Arrange
        Storage::fake('public');
        $file1 = $this->createTempFile('file1.txt', 'content1');
        $file2 = $this->createTempFile('file2.txt', 'content2');
        $file3 = $this->createTempFile('file3.txt', 'content3');

        // Act
        $media1 = Archive::add($file1)->store();
        $media2 = Archive::add($file2)->store();
        $media3 = Archive::add($file3)->store();

        // Assert
        Storage::disk('public')->assertExists($media1->getPath());
        Storage::disk('public')->assertExists($media2->getPath());
        Storage::disk('public')->assertExists($media3->getPath());

        expect($media1->getPath())->not->toBe($media2->getPath())
            ->and($media2->getPath())->not->toBe($media3->getPath());
    });
});

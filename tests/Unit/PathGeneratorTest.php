<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Archive;
use Cline\Archive\Models\Media;
use Cline\Archive\Storage\PathGenerator\DefaultPathGenerator;
use Cline\Archive\Storage\PathGenerator\PathGenerator;
use Illuminate\Support\Facades\Storage;

describe('PathGenerator - Happy Path', function (): void {
    it('generates path with media ID and filename', function (): void {
        // Arrange
        $file = $this->createTempFile('document.pdf', 'content');
        $media = Archive::add($file)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path = $generator->getPath($media);

        // Assert
        expect($path)->toContain((string) $media->getKey())
            ->and($path)->toContain('document.pdf')
            ->and($path)->toContain('/');
    });

    it('generates path with configured prefix', function (): void {
        // Arrange
        config()->set('archive.prefix', 'media');
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path = $generator->getPath($media);

        // Assert
        expect($path)->toStartWith('media/');
    });

    it('generates path without prefix when config is empty', function (): void {
        // Arrange
        config()->set('archive.prefix', '');
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path = $generator->getPath($media);

        // Assert
        expect($path)->toStartWith((string) $media->getKey());
    });
});

describe('PathGenerator - Edge Cases', function (): void {
    it('handles prefix with leading slash', function (): void {
        // Arrange
        config()->set('archive.prefix', '/media');
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path = $generator->getPath($media);

        // Assert
        expect($path)->toStartWith('media/')
            ->and($path)->not->toStartWith('//');
    });

    it('handles prefix with trailing slash', function (): void {
        // Arrange
        config()->set('archive.prefix', 'media/');
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path = $generator->getPath($media);

        // Assert
        expect($path)->toStartWith('media/')
            ->and($path)->not->toContain('media//');
    });

    it('handles prefix with both leading and trailing slashes', function (): void {
        // Arrange
        config()->set('archive.prefix', '/media/');
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path = $generator->getPath($media);

        // Assert
        expect($path)->toStartWith('media/')
            ->and($path)->not->toContain('//');
    });

    it('handles nested prefix', function (): void {
        // Arrange
        config()->set('archive.prefix', 'storage/media/files');
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path = $generator->getPath($media);

        // Assert
        expect($path)->toStartWith('storage/media/files/')
            ->and($path)->toContain((string) $media->getKey());
    });

    it('handles filename with special characters', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->withFileName('file-with-dashes_and_underscores.txt')->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path = $generator->getPath($media);

        // Assert
        expect($path)->toContain('file-with-dashes_and_underscores.txt');
    });

    it('generates unique paths for different media', function (): void {
        // Arrange
        $file1 = $this->createTempFile('file1.txt', 'content1');
        $file2 = $this->createTempFile('file2.txt', 'content2');
        $media1 = Archive::add($file1)->store();
        $media2 = Archive::add($file2)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path1 = $generator->getPath($media1);
        $path2 = $generator->getPath($media2);

        // Assert
        expect($path1)->not->toBe($path2);
    });

    it('generates same path for same media on multiple calls', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path1 = $generator->getPath($media);
        $path2 = $generator->getPath($media);

        // Assert
        expect($path1)->toBe($path2);
    });

    it('handles media with numeric IDs', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();
        $generator = new DefaultPathGenerator();

        // Act
        $path = $generator->getPath($media);

        // Assert
        expect($path)->toMatch('/\d+/');
    });
});

describe('PathGenerator - Integration', function (): void {
    it('path is used by Media model getPath method', function (): void {
        // Arrange
        config()->set('archive.prefix', 'test-prefix');
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();

        // Act
        $path = $media->getPath();

        // Assert
        expect($path)->toStartWith('test-prefix/')
            ->and($path)->toContain((string) $media->getKey())
            ->and($path)->toContain('test.txt');
    });

    it('path is used by Filesystem for storage', function (): void {
        // Arrange
        Storage::fake('public');
        config()->set('archive.prefix', 'uploads');
        $file = $this->createTempFile('document.pdf', 'content');

        // Act
        $media = Archive::add($file)->store();
        $path = $media->getPath();

        // Assert
        Storage::disk('public')->assertExists($path);
        expect($path)->toStartWith('uploads/');
    });

    it('can use custom path generator', function (): void {
        // Arrange
        $customGenerator = new class() implements PathGenerator
        {
            public function getPath(Media $media): string
            {
                return 'custom/'.$media->collection.'/'.$media->getKey().'/'.$media->file_name;
            }
        };

        config()->set('archive.path_generator', $customGenerator::class);
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->toCollection('documents')->store();
        $path = $media->getPath();

        // Assert
        expect($path)->toStartWith('custom/documents/')
            ->and($path)->toContain((string) $media->getKey());
    });
});

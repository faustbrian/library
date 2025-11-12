<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Archive;
use Cline\Archive\Exceptions\FileDoesNotExist;
use Cline\Archive\Exceptions\FileTooLarge;
use Cline\Archive\Exceptions\InvalidDiskException;
use Cline\Archive\Models\Media;
use Cline\Archive\Support\MediaCollectionRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Tests\Support\TestCurator;
use Tests\Support\TestModel;

describe('MediaAdder - Happy Path', function (): void {
    it('can add media without curator (orphan)', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'test content');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media)->toBeInstanceOf(Media::class)
            ->and($media->name)->toBe('test')
            ->and($media->file_name)->toBe('test.txt')
            ->and($media->collection)->toBe('default')
            ->and($media->curator_id)->toBeNull()
            ->and($media->curator_type)->toBeNull()
            ->and($media->disk)->toBe('public')
            ->and($media->mime_type)->toBe('text/plain')
            ->and($media->size)->toBeGreaterThan(0);

        $this->assertDatabaseHas('media', [
            'name' => 'test',
            'file_name' => 'test.txt',
            'collection' => 'default',
        ]);
    });

    it('can add media with Eloquent model curator', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test Model']);
        $file = $this->createTempFile('document.pdf', 'pdf content');

        // Act
        $media = Archive::add($file)->toCurator($model)->store();

        // Assert
        expect($media->curator_id)->toBe((string) $model->id)
            ->and($media->curator_type)->toBe(TestModel::class);

        $this->assertDatabaseHas('media', [
            'curator_id' => (string) $model->id,
            'curator_type' => TestModel::class,
        ]);
    });

    it('can add media with Curator owner', function (): void {
        // Arrange
        $owner = new TestCurator('uuid-123', 'custom-type');
        $file = $this->createTempFile('image.jpg', 'image content');

        // Act
        $media = Archive::add($file)->toCurator($owner)->store();

        // Assert
        expect($media->curator_id)->toBe('uuid-123')
            ->and($media->curator_type)->toBe('custom-type');

        $this->assertDatabaseHas('media', [
            'curator_id' => 'uuid-123',
            'curator_type' => 'custom-type',
        ]);
    });

    it('can set custom file name', function (): void {
        // Arrange
        $file = $this->createTempFile('original.txt', 'content');

        // Act
        $media = Archive::add($file)->withFileName('custom-name.txt')->store();

        // Assert
        expect($media->file_name)->toBe('custom-name.txt');
    });

    it('can set custom media name', function (): void {
        // Arrange
        $file = $this->createTempFile('file.txt', 'content');

        // Act
        $media = Archive::add($file)->withName('My Custom Name')->store();

        // Assert
        expect($media->name)->toBe('My Custom Name');
    });

    it('can set custom collection', function (): void {
        // Arrange
        $file = $this->createTempFile('photo.jpg', 'image');

        // Act
        $media = Archive::add($file)->toCollection('photos')->store();

        // Assert
        expect($media->collection)->toBe('photos');
    });

    it('can set nested collection', function (): void {
        // Arrange
        $file = $this->createTempFile('avatar.jpg', 'image');

        // Act
        $media = Archive::add($file)->toCollection('users/avatars')->store();

        // Assert
        expect($media->collection)->toBe('users/avatars');
    });

    it('can set custom disk', function (): void {
        // Arrange
        Storage::fake('s3');
        $file = $this->createTempFile('backup.zip', 'archive');

        // Act
        $media = Archive::add($file)->toDisk('s3')->store();

        // Assert
        expect($media->disk)->toBe('s3');
    });

    it('can set custom properties', function (): void {
        // Arrange
        $file = $this->createTempFile('document.pdf', 'content');
        $properties = ['author' => 'John Doe', 'category' => 'reports'];

        // Act
        $media = Archive::add($file)->withProperties($properties)->store();

        // Assert
        expect($media->custom_properties)->toBe($properties)
            ->and($media->custom_properties['author'])->toBe('John Doe')
            ->and($media->custom_properties['category'])->toBe('reports');
    });

    it('can set order column', function (): void {
        // Arrange
        $file = $this->createTempFile('item.txt', 'content');

        // Act
        $media = Archive::add($file)->withOrder(5)->store();

        // Assert
        expect($media->order_column)->toBe(5);
    });

    it('can preserve original file', function (): void {
        // Arrange
        $file = $this->createTempFile('preserve.txt', 'keep me');
        $originalPath = $file;

        // Act
        $media = Archive::add($file)->preservingOriginal()->store();

        // Assert
        expect(file_exists($originalPath))->toBeTrue();
    });

    it('deletes original file by default', function (): void {
        // Arrange
        $file = $this->createTempFile('delete-me.txt', 'remove');
        $originalPath = $file;

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect(file_exists($originalPath))->toBeFalse();
    });

    it('can chain all fluent methods', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('complex.jpg', 'data');

        // Act
        $media = Archive::add($file)
            ->toCurator($model)
            ->withFileName('renamed.jpg')
            ->withName('Complex Image')
            ->toCollection('gallery/featured')
            ->toDisk('public')
            ->withProperties(['featured' => true, 'priority' => 1])
            ->withOrder(10)
            ->preservingOriginal()
            ->store();

        // Assert
        expect($media->curator_id)->toBe((string) $model->id)
            ->and($media->file_name)->toBe('renamed.jpg')
            ->and($media->name)->toBe('Complex Image')
            ->and($media->collection)->toBe('gallery/featured')
            ->and($media->disk)->toBe('public')
            ->and($media->custom_properties)->toBe(['featured' => true, 'priority' => 1])
            ->and($media->order_column)->toBe(10);
    });

    it('handles UploadedFile correctly', function (): void {
        // Arrange
        $tempFile = $this->createTempFile('photo.jpg', 'test image');
        $uploadedFile = new UploadedFile(
            $tempFile,
            'uploaded-photo.jpg',
            'image/jpeg',
            null,
            true,
        );

        // Act
        $media = Archive::add($uploadedFile)->store();

        // Assert
        expect($media->file_name)->toBe('uploaded-photo.jpg')
            ->and($media->name)->toBe('uploaded-photo');
    });

    it('handles SymfonyFile correctly', function (): void {
        // Arrange
        $tempFile = $this->createTempFile('image.png', 'test image');
        $symfonyFile = new File($tempFile);

        // Act
        $media = Archive::add($symfonyFile)->store();

        // Assert
        expect($media->file_name)->toBe('image.png');
    });

    it('stores file in correct location', function (): void {
        // Arrange
        Storage::fake('public');
        $file = $this->createTempFile('storage-test.txt', 'content');

        // Act
        $media = Archive::add($file)->store();
        $expectedPath = $media->getPath();

        // Assert
        Storage::disk('public')->assertExists($expectedPath);
    });
});

describe('MediaAdder - Sad Path', function (): void {
    it('throws exception when file does not exist', function (): void {
        // Arrange
        $nonExistentFile = '/tmp/non-existent-file-'.uniqid().'.txt';

        // Act & Assert
        expect(fn (): Media => Archive::add($nonExistentFile)->store())
            ->toThrow(FileDoesNotExist::class);
    });

    it('throws exception for PHP file uploads (security)', function (): void {
        // Arrange
        $phpFile = $this->createTempFile('malicious.php', '<?php phpinfo(); ?>');

        // Act & Assert
        expect(fn (): Media => Archive::add($phpFile)->store())
            ->toThrow(InvalidArgumentException::class, 'PHP files are not allowed');
    });

    it('throws exception for php3 files', function (): void {
        // Arrange
        $phpFile = $this->createTempFile('script.php3', 'code');

        // Act & Assert
        expect(fn (): Media => Archive::add($phpFile)->store())
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws exception for phtml files', function (): void {
        // Arrange
        $phpFile = $this->createTempFile('page.phtml', 'code');

        // Act & Assert
        expect(fn (): Media => Archive::add($phpFile)->store())
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws exception for phar files', function (): void {
        // Arrange
        $pharFile = $this->createTempFile('archive.phar', 'data');

        // Act & Assert
        expect(fn (): Media => Archive::add($pharFile)->store())
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws exception for uppercase PHP extensions', function (): void {
        // Arrange
        $phpFile = $this->createTempFile('malicious.PHP', '<?php phpinfo(); ?>');

        // Act & Assert
        expect(fn (): Media => Archive::add($phpFile)->store())
            ->toThrow(InvalidArgumentException::class, 'PHP files are not allowed');
    });

    it('throws exception for mixed case PHP extensions', function (): void {
        // Arrange
        $phpFile = $this->createTempFile('script.PhP', 'code');

        // Act & Assert
        expect(fn (): Media => Archive::add($phpFile)->store())
            ->toThrow(InvalidArgumentException::class, 'PHP files are not allowed');
    });

    it('throws exception for uppercase phtml extension', function (): void {
        // Arrange
        $phpFile = $this->createTempFile('page.PHTML', 'code');

        // Act & Assert
        expect(fn (): Media => Archive::add($phpFile)->store())
            ->toThrow(InvalidArgumentException::class, 'PHP files are not allowed');
    });

    it('sanitizes filename with special characters', function (): void {
        // Arrange
        $file = $this->createTempFile('file with spaces.txt', 'content');

        // Act
        $media = Archive::add($file)->withFileName('special #chars/test\\file.txt')->store();

        // Assert
        expect($media->file_name)->toBe('special--chars-test-file.txt');
    });

    it('sanitizes filename with unicode control characters', function (): void {
        // Arrange
        $file = $this->createTempFile('normal.txt', 'content');

        // Act
        $media = Archive::add($file)->withFileName("file\x00name.txt")->store();

        // Assert
        expect($media->file_name)->not->toContain("\x00");
    });

    it('throws exception when file exceeds max size', function (): void {
        // Arrange
        config()->set('archive.max_file_size', 100); // 100 bytes
        $file = $this->createTempFile('large.txt', str_repeat('x', 200));

        // Act & Assert
        expect(fn (): Media => Archive::add($file)->store())
            ->toThrow(FileTooLarge::class);
    });

    it('allows file at exact max size', function (): void {
        // Arrange
        config()->set('archive.max_file_size', 100);
        $file = $this->createTempFile('exact.txt', str_repeat('x', 100));

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media)->toBeInstanceOf(Media::class)
            ->and($media->size)->toBe(100);
    });

    it('allows file just under max size', function (): void {
        // Arrange
        config()->set('archive.max_file_size', 100);
        $file = $this->createTempFile('under.txt', str_repeat('x', 99));

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media)->toBeInstanceOf(Media::class)
            ->and($media->size)->toBe(99);
    });

    it('disables size check when max_file_size is 0', function (): void {
        // Arrange
        config()->set('archive.max_file_size', 0);
        $file = $this->createTempFile('huge.txt', str_repeat('x', 999_999));

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media)->toBeInstanceOf(Media::class)
            ->and($media->size)->toBe(999_999);
    });

    it('disables size check when max_file_size is negative', function (): void {
        // Arrange
        config()->set('archive.max_file_size', -1);
        $file = $this->createTempFile('big.txt', str_repeat('x', 500));

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media)->toBeInstanceOf(Media::class);
    });

    it('throws exception when setting invalid disk', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');

        // Act & Assert
        expect(fn (): Media => Archive::add($file)->toDisk('non-existent-disk')->store())
            ->toThrow(
                InvalidDiskException::class,
                "Disk 'non-existent-disk' does not exist in filesystem configuration.",
            );
    });
});

describe('MediaAdder - Edge Cases', function (): void {
    it('handles null curator explicitly', function (): void {
        // Arrange
        $file = $this->createTempFile('orphan.txt', 'content');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media->curator_id)->toBeNull()
            ->and($media->curator_type)->toBeNull();
    });

    it('handles empty custom properties', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->withProperties([])->store();

        // Assert
        expect($media->custom_properties)->toBe([]);
    });

    it('handles null order column', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->withOrder(null)->store();

        // Assert
        expect($media->order_column)->toBeNull();
    });

    it('handles very large custom properties array', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');
        $largeProperties = [];

        for ($i = 0; $i < 100; ++$i) {
            $largeProperties['key_'.$i] = 'value_'.$i;
        }

        // Act
        $media = Archive::add($file)->withProperties($largeProperties)->store();

        // Assert
        expect($media->custom_properties)->toHaveCount(100);
    });

    it('handles file with no extension', function (): void {
        // Arrange
        $file = $this->createTempFile('README', 'readme content');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media->file_name)->toBe('README')
            ->and($media->name)->toBe('README');
    });

    it('preserves file extension case', function (): void {
        // Arrange
        $file = $this->createTempFile('Document.PDF', 'content');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media->file_name)->toBe('Document.PDF');
    });

    it('handles empty file', function (): void {
        // Arrange
        $file = $this->createTempFile('empty.txt', '');

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media->size)->toBe(0);
    });

    it('handles binary file content', function (): void {
        // Arrange
        $file = $this->createTempFile('binary.dat', "\x00\x01\x02\xFF");

        // Act
        $media = Archive::add($file)->store();

        // Assert
        expect($media->size)->toBe(4);
    });

    it('deletes existing media when adding to single-file collection with curator', function (): void {
        // Arrange
        MediaCollectionRegistry::define('avatar')->singleFile();

        $model = TestModel::query()->create(['name' => 'User']);
        $firstFile = $this->createTempFile('avatar1.jpg', 'first avatar');
        $secondFile = $this->createTempFile('avatar2.jpg', 'second avatar');

        // Act - Add first media
        $firstMedia = Archive::add($firstFile)
            ->toCurator($model)
            ->toCollection('avatar')
            ->store();

        // Assert first media exists
        expect($firstMedia)->toBeInstanceOf(Media::class)
            ->and($firstMedia->collection)->toBe('avatar');

        $this->assertDatabaseHas('media', [
            'id' => $firstMedia->id,
            'collection' => 'avatar',
            'curator_id' => (string) $model->id,
        ]);

        // Act - Add second media to same single-file collection
        $secondMedia = Archive::add($secondFile)
            ->toCurator($model)
            ->toCollection('avatar')
            ->store();

        // Assert - First media should be deleted, second media should exist
        $this->assertDatabaseMissing('media', [
            'id' => $firstMedia->id,
        ]);

        $this->assertDatabaseHas('media', [
            'id' => $secondMedia->id,
            'collection' => 'avatar',
            'curator_id' => (string) $model->id,
            'file_name' => 'avatar2.jpg',
        ]);

        // Assert only one media exists for this curator+collection
        $mediaCount = Media::query()
            ->where('curator_id', $model->id)
            ->where('curator_type', TestModel::class)
            ->where('collection', 'avatar')
            ->count();

        expect($mediaCount)->toBe(1);
    });
});

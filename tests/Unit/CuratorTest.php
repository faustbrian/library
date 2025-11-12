<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Archive;
use Cline\Archive\Contracts\Curator;
use Cline\Archive\Models\Media;
use Tests\Support\TestCurator;
use Tests\Support\TestModel;

describe('Curator Interface - Happy Path', function (): void {
    it('can create Curator implementation', function (): void {
        // Arrange & Act
        $owner = new TestCurator('test-id-123', 'custom-type');

        // Assert
        expect($owner)->toBeInstanceOf(Curator::class);
    });

    it('returns correct owner ID', function (): void {
        // Arrange
        $owner = new TestCurator('unique-id-456', 'type');

        // Act
        $id = $owner->getCuratorId();

        // Assert
        expect($id)->toBe('unique-id-456');
    });

    it('returns correct owner type', function (): void {
        // Arrange
        $owner = new TestCurator('id', 'custom-entity-type');

        // Act
        $type = $owner->getCuratorType();

        // Assert
        expect($type)->toBe('custom-entity-type');
    });

    it('can add media to Curator owner', function (): void {
        // Arrange
        $owner = new TestCurator('owner-123', 'user');
        $file = $this->createTempFile('document.pdf', 'content');

        // Act
        $media = Archive::add($file)->toCurator($owner)->store();

        // Assert
        expect($media->curator_id)->toBe('owner-123')
            ->and($media->curator_type)->toBe('user');
    });

    it('can attach media to Curator after creation', function (): void {
        // Arrange
        $file = $this->createTempFile('orphan.txt', 'content');
        $media = Archive::add($file)->store();
        $owner = new TestCurator('new-owner-789', 'entity');

        // Act
        $media->attachToCurator($owner);

        // Assert
        expect($media->curator_id)->toBe('new-owner-789')
            ->and($media->curator_type)->toBe('entity');

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'curator_id' => 'new-owner-789',
            'curator_type' => 'entity',
        ]);
    });

    it('can use all fluent methods with Curator', function (): void {
        // Arrange
        $owner = new TestCurator('complex-id', 'complex-type');
        $file = $this->createTempFile('complex.jpg', 'data');

        // Act
        $media = Archive::add($file)
            ->toCurator($owner)
            ->withFileName('renamed.jpg')
            ->withName('Complex Image')
            ->toCollection('gallery')
            ->withProperties(['featured' => true])
            ->withOrder(5)
            ->store();

        // Assert
        expect($media->curator_id)->toBe('complex-id')
            ->and($media->curator_type)->toBe('complex-type')
            ->and($media->file_name)->toBe('renamed.jpg')
            ->and($media->name)->toBe('Complex Image')
            ->and($media->collection)->toBe('gallery')
            ->and($media->custom_properties)->toBe(['featured' => true])
            ->and($media->order_column)->toBe(5);
    });
});

describe('Curator Interface - Edge Cases', function (): void {
    it('handles UUID as owner ID', function (): void {
        // Arrange
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $owner = new TestCurator($uuid, 'entity');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->toCurator($owner)->store();

        // Assert
        expect($media->curator_id)->toBe($uuid);
    });

    it('handles ULID as owner ID', function (): void {
        // Arrange
        $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $owner = new TestCurator($ulid, 'entity');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->toCurator($owner)->store();

        // Assert
        expect($media->curator_id)->toBe($ulid);
    });

    it('handles numeric string as owner ID', function (): void {
        // Arrange
        $owner = new TestCurator('12345', 'legacy-system');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->toCurator($owner)->store();

        // Assert
        expect($media->curator_id)->toBe('12345');
    });

    it('handles special characters in type', function (): void {
        // Arrange
        $owner = new TestCurator('id', 'App\\Models\\CustomEntity');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->toCurator($owner)->store();

        // Assert
        expect($media->curator_type)->toBe('App\\Models\\CustomEntity');
    });

    it('handles empty string as ID', function (): void {
        // Arrange
        $owner = new TestCurator('', 'type');
        $file = $this->createTempFile('test.txt', 'content');

        // Act
        $media = Archive::add($file)->toCurator($owner)->store();

        // Assert
        expect($media->curator_id)->toBe('');
    });

    it('can query media by Curator type', function (): void {
        // Arrange
        $owner1 = new TestCurator('id1', 'type-a');
        $owner2 = new TestCurator('id2', 'type-a');
        $owner3 = new TestCurator('id3', 'type-b');

        $file1 = $this->createTempFile('test1.txt', 'content');
        $file2 = $this->createTempFile('test2.txt', 'content');
        $file3 = $this->createTempFile('test3.txt', 'content');

        Archive::add($file1)->toCurator($owner1)->store();
        Archive::add($file2)->toCurator($owner2)->store();
        Archive::add($file3)->toCurator($owner3)->store();

        // Act
        $count = Media::query()->where('curator_type', 'type-a')->count();

        // Assert
        expect($count)->toBe(2);
    });

    it('can query media by Curator ID', function (): void {
        // Arrange
        $id = 'specific-id-999';
        $owner = new TestCurator($id, 'entity');

        $file1 = $this->createTempFile('file1.txt', 'content');
        $file2 = $this->createTempFile('file2.txt', 'content');

        Archive::add($file1)->toCurator($owner)->store();
        Archive::add($file2)->toCurator($owner)->store();

        // Act
        $count = Media::query()->where('curator_id', $id)->count();

        // Assert
        expect($count)->toBe(2);
    });

    it('can have multiple collections for same Curator', function (): void {
        // Arrange
        $owner = new TestCurator('multi-collection-id', 'entity');

        $file1 = $this->createTempFile('photo.jpg', 'image');
        $file2 = $this->createTempFile('doc.pdf', 'document');

        // Act
        $media1 = Archive::add($file1)->toCurator($owner)->toCollection('photos')->store();
        $media2 = Archive::add($file2)->toCurator($owner)->toCollection('documents')->store();

        // Assert
        expect($media1->collection)->toBe('photos')
            ->and($media2->collection)->toBe('documents')
            ->and($media1->curator_id)->toBe($media2->curator_id);
    });

    it('can replace owner from Eloquent to Curator', function (): void {
        // Arrange
        $model = TestModel::query()->create(['name' => 'Test']);
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->toCurator($model)->store();

        $owner = new TestCurator('new-id', 'new-type');

        // Act
        $media->attachToCurator($owner);

        // Assert
        expect($media->curator_id)->toBe('new-id')
            ->and($media->curator_type)->toBe('new-type');

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'curator_id' => 'new-id',
            'curator_type' => 'new-type',
        ]);
    });

    it('can attach orphan media to Eloquent model', function (): void {
        // Arrange
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->store();

        $model = TestModel::query()->create(['name' => 'Test']);

        // Act
        $media->attachToCurator($model);

        // Assert
        expect((string) $media->curator_id)->toBe((string) $model->id)
            ->and($media->curator_type)->toBe(TestModel::class);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'curator_id' => (string) $model->id,
            'curator_type' => TestModel::class,
        ]);
    });
});

describe('Curator Interface - Integration', function (): void {
    it('supports different Curator implementations', function (): void {
        // Arrange
        $owner1 = new TestCurator('id1', 'type1');
        $owner2 = new readonly class('id2', 'type2') implements Curator
        {
            public function __construct(
                private string $id,
                private string $type,
            ) {}

            public function getCuratorId(): string
            {
                return $this->id;
            }

            public function getCuratorType(): string
            {
                return $this->type;
            }
        };

        $file1 = $this->createTempFile('test1.txt', 'content');
        $file2 = $this->createTempFile('test2.txt', 'content');

        // Act
        $media1 = Archive::add($file1)->toCurator($owner1)->store();
        $media2 = Archive::add($file2)->toCurator($owner2)->store();

        // Assert
        expect($media1->curator_id)->toBe('id1')
            ->and($media2->curator_id)->toBe('id2');
    });

    it('can use Curator for non-database entities', function (): void {
        // Arrange
        $externalEntity = new TestCurator('external-api-id', 'external-service');
        $file = $this->createTempFile('upload.pdf', 'content');

        // Act
        $media = Archive::add($file)->toCurator($externalEntity)->store();

        // Assert
        expect($media->curator_id)->toBe('external-api-id')
            ->and($media->curator_type)->toBe('external-service');

        $this->assertDatabaseHas('media', [
            'curator_id' => 'external-api-id',
            'curator_type' => 'external-service',
        ]);
    });

    it('maintains Curator data integrity on refresh', function (): void {
        // Arrange
        $owner = new TestCurator('persist-id', 'persist-type');
        $file = $this->createTempFile('test.txt', 'content');
        $media = Archive::add($file)->toCurator($owner)->store();

        // Act
        $refreshed = Media::query()->find($media->id);

        // Assert
        expect($refreshed->curator_id)->toBe('persist-id')
            ->and($refreshed->curator_type)->toBe('persist-type');
    });
});

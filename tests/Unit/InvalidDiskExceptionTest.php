<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Exceptions\InvalidDiskException;

describe('InvalidDiskException', function (): void {
    it('creates exception for non-existent disk', function (): void {
        // Arrange
        $diskName = 'invalid-disk';

        // Act
        $exception = InvalidDiskException::diskDoesNotExist($diskName);

        // Assert
        expect($exception)
            ->toBeInstanceOf(InvalidDiskException::class)
            ->and($exception->getMessage())
            ->toBe("Disk 'invalid-disk' does not exist in filesystem configuration.");
    });

    it('extends InvalidArgumentException', function (): void {
        // Arrange & Act
        $exception = InvalidDiskException::diskDoesNotExist('test');

        // Assert
        expect($exception)->toBeInstanceOf(InvalidArgumentException::class);
    });

    it('formats error message with disk name', function (): void {
        // Arrange
        $diskName = 's3-backup';

        // Act
        $exception = InvalidDiskException::diskDoesNotExist($diskName);

        // Assert
        expect($exception->getMessage())
            ->toContain($diskName)
            ->and($exception->getMessage())
            ->toContain('does not exist in filesystem configuration');
    });

    it('handles disk names with special characters', function (): void {
        // Arrange
        $diskName = 'disk-with_special.chars123';

        // Act
        $exception = InvalidDiskException::diskDoesNotExist($diskName);

        // Assert
        expect($exception->getMessage())
            ->toBe("Disk 'disk-with_special.chars123' does not exist in filesystem configuration.");
    });

    it('handles empty disk name', function (): void {
        // Arrange
        $diskName = '';

        // Act
        $exception = InvalidDiskException::diskDoesNotExist($diskName);

        // Assert
        expect($exception->getMessage())
            ->toBe("Disk '' does not exist in filesystem configuration.");
    });

    it('can be thrown and caught', function (): void {
        // Arrange
        $diskName = 'missing-disk';

        // Act & Assert
        expect(fn () => throw InvalidDiskException::diskDoesNotExist($diskName))
            ->toThrow(InvalidDiskException::class, "Disk 'missing-disk' does not exist in filesystem configuration.");
    });

    it('can be caught as InvalidArgumentException', function (): void {
        // Arrange
        $diskName = 'test-disk';

        // Act & Assert
        expect(fn () => throw InvalidDiskException::diskDoesNotExist($diskName))
            ->toThrow(InvalidArgumentException::class);
    });

    it('handles disk names with unicode characters', function (): void {
        // Arrange
        $diskName = 'дискéñ';

        // Act
        $exception = InvalidDiskException::diskDoesNotExist($diskName);

        // Assert
        expect($exception->getMessage())
            ->toContain($diskName)
            ->and($exception->getMessage())
            ->toBe("Disk 'дискéñ' does not exist in filesystem configuration.");
    });

    it('handles very long disk names', function (): void {
        // Arrange
        $diskName = str_repeat('long-disk-name-', 10);

        // Act
        $exception = InvalidDiskException::diskDoesNotExist($diskName);

        // Assert
        expect($exception->getMessage())
            ->toContain($diskName)
            ->and($exception->getMessage())
            ->toBe(sprintf("Disk '%s' does not exist in filesystem configuration.", $diskName));
    });
});

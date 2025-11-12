<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Archive\Contracts;

/**
 * Defines the contract for entities that can curate media files.
 *
 * This interface must be implemented by any entity that needs to associate media
 * files with itself. It provides the necessary identifiers for polymorphic
 * relationships with the Media model.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Curator
{
    /**
     * Returns the unique identifier for the curator.
     *
     * This identifier is used to establish the polymorphic relationship between
     * the curator and its media files.
     *
     * @return string The unique identifier of the curating entity
     */
    public function getCuratorId(): string;

    /**
     * Returns the type identifier for the curator.
     *
     * This type identifier is used in polymorphic relationships to determine
     * the class type of the curating entity. Typically returns the fully qualified
     * class name or morph map alias.
     *
     * @return string The type identifier of the curating entity
     */
    public function getCuratorType(): string;
}

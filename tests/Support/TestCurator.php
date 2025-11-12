<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support;

use Cline\Archive\Contracts\Curator;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TestCurator implements Curator
{
    public function __construct(
        private string $id,
        private string $type = 'test-owner',
    ) {}

    public function getCuratorId(): string
    {
        return $this->id;
    }

    public function getCuratorType(): string
    {
        return $this->type;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Archive\Support\MediaCollectionRegistry;
use Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

// Clear global MediaCollectionRegistry after each test to prevent pollution
afterEach(function (): void {
    MediaCollectionRegistry::clear();
});

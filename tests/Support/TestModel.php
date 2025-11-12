<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support;

use Cline\Archive\Contracts\Curator;
use Cline\Archive\Models\Concerns\HasArchive;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class TestModel extends Model implements Curator
{
    use HasFactory;
    use HasArchive;

    protected $guarded = [];
}

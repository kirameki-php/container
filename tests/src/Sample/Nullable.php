<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

use DateTime;

class Nullable
{
    public function __construct(
        public DateTime|null $a,
    )
    {
    }
}

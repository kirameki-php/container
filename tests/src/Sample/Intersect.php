<?php declare(strict_types=1);

namespace Tests\Kirameki\Sample;

class Intersect
{
    public function __construct(
        public Basic&BasicExtended $a,
    )
    {
    }
}

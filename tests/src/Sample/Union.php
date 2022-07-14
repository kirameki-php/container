<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

class Union
{
    public function __construct(
        public Basic|BasicExtended $a,
    )
    {
    }
}

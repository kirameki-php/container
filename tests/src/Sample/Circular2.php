<?php declare(strict_types=1);

namespace Tests\Kirameki\Sample;

class Circular2
{
    public function __construct(
        public Circular1 $circular1,
    )
    {

    }
}

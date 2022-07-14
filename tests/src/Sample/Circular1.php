<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

class Circular1
{
    public function __construct(
        public Circular2 $circular2,
    )
    {

    }
}

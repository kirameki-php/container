<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

class SelfType
{
    public function __construct(
        public self $self,
    )
    {
    }
}

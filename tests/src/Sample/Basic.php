<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

use DateTime;

class Basic
{
    public function __construct(
        public DateTime $d,
        public int $i = 1,
    )
    {
    }
}

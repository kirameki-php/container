<?php declare(strict_types=1);

namespace Tests\Kirameki\Sample;

use DateTime;
use Kirameki\Utils\Type;

class Basic extends Type
{
    public function __construct(
        public DateTime $d,
        public int $i = 1,
    )
    {

    }
}
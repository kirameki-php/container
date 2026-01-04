<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

use DateTime;
use DateTimeInterface;

class InterfaceDefault
{
    public function __construct(
        public DateTimeInterface $d = new DateTime()
    ) {
    }
}

<?php declare(strict_types=1);

namespace Tests\Kirameki\Sample;

use DateTime;

class BasicExtended extends Basic
{
    public function __construct(
        ?DateTime $d = null,
        int $i = 100,
    )
    {
        parent::__construct(
            $d ?? new DateTime('2022-02-02 00:00:00'),
            $i,
        );
    }
}
